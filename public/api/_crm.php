<?php
declare(strict_types=1);

require_once __DIR__ . '/_cms.php';

const CRM_ROLES = [
    'admin' => 'Administrateur',
    'manager' => 'Manager',
    'collaborator' => 'Collaborateur',
    'viewer' => 'Lecture seule',
];

const CRM_LEAD_STATUSES = [
    'new' => 'Nouvelle',
    'qualified' => 'Qualifiée',
    'meeting' => 'Rendez-vous',
    'proposal' => 'Proposition',
    'converted' => 'Convertie',
    'lost' => 'Perdue',
    'archived' => 'Archivée',
];

const CRM_PRIORITIES = [
    'low' => 'Faible',
    'normal' => 'Normale',
    'high' => 'Haute',
    'urgent' => 'Urgente',
];

const CRM_TASK_STATUSES = [
    'open' => 'À faire',
    'in_progress' => 'En cours',
    'completed' => 'Terminée',
    'cancelled' => 'Annulée',
];

const CRM_ACTIVITY_TYPES = [
    'note' => 'Note',
    'call' => 'Appel',
    'email' => 'Email',
    'meeting' => 'Réunion',
    'status' => 'Changement de statut',
    'document' => 'Document',
    'system' => 'Système',
];

function crm_now(): string
{
    return gmdate('Y-m-d H:i:s');
}

function crm_uid(): string
{
    return bin2hex(random_bytes(12));
}

function crm_database_config(): array
{
    $config = cms_config();
    $database = $config['database'] ?? [];
    if (!is_array($database)) {
        $database = [];
    }
    $database['driver'] = (string)($database['driver'] ?? 'sqlite');
    return $database;
}

function crm_db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    cms_bootstrap_storage();
    $config = crm_database_config();
    $driver = $config['driver'];

    if ($driver === 'mysql') {
        $host = (string)($config['host'] ?? 'localhost');
        $port = (int)($config['port'] ?? 3306);
        $name = (string)($config['name'] ?? '');
        $username = (string)($config['username'] ?? '');
        $password = (string)($config['password'] ?? '');
        if ($name === '' || $username === '') {
            throw new RuntimeException('La configuration MySQL du CRM est incomplète.');
        }
        $dsn = "mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4";
        $pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } else {
        $path = (string)($config['path'] ?? (cms_private_dir() . '/crm.sqlite'));
        $pdo = new PDO('sqlite:' . $path, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA journal_mode = WAL');
        $pdo->exec('PRAGMA busy_timeout = 5000');
        @chmod($path, 0640);
    }

    crm_migrate($pdo);
    crm_seed($pdo);
    crm_import_legacy_submissions($pdo);
    crm_run_scheduled_jobs($pdo);
    return $pdo;
}

function crm_driver(?PDO $pdo = null): string
{
    return (string)($pdo ?? crm_db())->getAttribute(PDO::ATTR_DRIVER_NAME);
}

function crm_migrate(PDO $pdo): void
{
    if (crm_driver($pdo) === 'mysql') {
        crm_migrate_mysql($pdo);
        return;
    }

    $statements = [
        'CREATE TABLE IF NOT EXISTS schema_migrations (
            version INTEGER PRIMARY KEY,
            applied_at TEXT NOT NULL
        )',
        'CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            uid TEXT NOT NULL UNIQUE,
            username TEXT NOT NULL UNIQUE COLLATE NOCASE,
            full_name TEXT NOT NULL,
            email TEXT,
            password_hash TEXT NOT NULL,
            role TEXT NOT NULL DEFAULT "collaborator",
            is_active INTEGER NOT NULL DEFAULT 1,
            notification_email INTEGER NOT NULL DEFAULT 1,
            last_login_at TEXT,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL,
            deleted_at TEXT
        )',
        'CREATE TABLE IF NOT EXISTS companies (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            uid TEXT NOT NULL UNIQUE,
            name TEXT NOT NULL,
            legal_name TEXT,
            industry TEXT,
            website TEXT,
            email TEXT,
            phone TEXT,
            address TEXT,
            city TEXT,
            country TEXT NOT NULL DEFAULT "Algérie",
            tax_id TEXT,
            registration_number TEXT,
            status TEXT NOT NULL DEFAULT "prospect",
            owner_id INTEGER,
            notes TEXT,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL,
            deleted_at TEXT,
            FOREIGN KEY(owner_id) REFERENCES users(id)
        )',
        'CREATE TABLE IF NOT EXISTS contacts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            uid TEXT NOT NULL UNIQUE,
            first_name TEXT NOT NULL,
            last_name TEXT NOT NULL DEFAULT "",
            email TEXT,
            phone TEXT,
            mobile TEXT,
            job_title TEXT,
            preferred_language TEXT NOT NULL DEFAULT "fr",
            address TEXT,
            city TEXT,
            country TEXT NOT NULL DEFAULT "Algérie",
            source TEXT,
            status TEXT NOT NULL DEFAULT "prospect",
            owner_id INTEGER,
            company_id INTEGER,
            notes TEXT,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL,
            deleted_at TEXT,
            FOREIGN KEY(owner_id) REFERENCES users(id),
            FOREIGN KEY(company_id) REFERENCES companies(id)
        )',
        'CREATE TABLE IF NOT EXISTS pipeline_stages (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            uid TEXT NOT NULL UNIQUE,
            name TEXT NOT NULL,
            slug TEXT NOT NULL UNIQUE,
            position INTEGER NOT NULL,
            probability INTEGER NOT NULL DEFAULT 0,
            color TEXT NOT NULL DEFAULT "#0f7fa6",
            is_won INTEGER NOT NULL DEFAULT 0,
            is_lost INTEGER NOT NULL DEFAULT 0,
            is_active INTEGER NOT NULL DEFAULT 1,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )',
        'CREATE TABLE IF NOT EXISTS leads (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            uid TEXT NOT NULL UNIQUE,
            legacy_id TEXT UNIQUE,
            name TEXT NOT NULL,
            company_name TEXT,
            email TEXT,
            phone TEXT,
            service TEXT,
            message TEXT,
            status TEXT NOT NULL DEFAULT "new",
            priority TEXT NOT NULL DEFAULT "normal",
            source TEXT NOT NULL DEFAULT "manual",
            estimated_value REAL,
            currency TEXT NOT NULL DEFAULT "DZD",
            owner_id INTEGER,
            contact_id INTEGER,
            company_id INTEGER,
            converted_opportunity_id INTEGER,
            lost_reason TEXT,
            converted_at TEXT,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL,
            deleted_at TEXT,
            FOREIGN KEY(owner_id) REFERENCES users(id),
            FOREIGN KEY(contact_id) REFERENCES contacts(id),
            FOREIGN KEY(company_id) REFERENCES companies(id)
        )',
        'CREATE TABLE IF NOT EXISTS opportunities (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            uid TEXT NOT NULL UNIQUE,
            name TEXT NOT NULL,
            service TEXT,
            description TEXT,
            value REAL NOT NULL DEFAULT 0,
            currency TEXT NOT NULL DEFAULT "DZD",
            probability INTEGER NOT NULL DEFAULT 10,
            expected_close_date TEXT,
            next_action TEXT,
            source TEXT,
            lost_reason TEXT,
            stage_id INTEGER NOT NULL,
            owner_id INTEGER,
            contact_id INTEGER,
            company_id INTEGER,
            lead_id INTEGER,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL,
            closed_at TEXT,
            deleted_at TEXT,
            FOREIGN KEY(stage_id) REFERENCES pipeline_stages(id),
            FOREIGN KEY(owner_id) REFERENCES users(id),
            FOREIGN KEY(contact_id) REFERENCES contacts(id),
            FOREIGN KEY(company_id) REFERENCES companies(id),
            FOREIGN KEY(lead_id) REFERENCES leads(id)
        )',
        'CREATE TABLE IF NOT EXISTS tasks (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            uid TEXT NOT NULL UNIQUE,
            title TEXT NOT NULL,
            description TEXT,
            status TEXT NOT NULL DEFAULT "open",
            priority TEXT NOT NULL DEFAULT "normal",
            due_at TEXT,
            completed_at TEXT,
            recurrence TEXT,
            assigned_to INTEGER,
            created_by INTEGER,
            contact_id INTEGER,
            company_id INTEGER,
            lead_id INTEGER,
            opportunity_id INTEGER,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL,
            deleted_at TEXT,
            FOREIGN KEY(assigned_to) REFERENCES users(id),
            FOREIGN KEY(created_by) REFERENCES users(id),
            FOREIGN KEY(contact_id) REFERENCES contacts(id),
            FOREIGN KEY(company_id) REFERENCES companies(id),
            FOREIGN KEY(lead_id) REFERENCES leads(id),
            FOREIGN KEY(opportunity_id) REFERENCES opportunities(id)
        )',
        'CREATE TABLE IF NOT EXISTS activities (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            uid TEXT NOT NULL UNIQUE,
            type TEXT NOT NULL,
            subject TEXT NOT NULL,
            body TEXT,
            activity_at TEXT NOT NULL,
            due_at TEXT,
            completed_at TEXT,
            created_by INTEGER,
            assigned_to INTEGER,
            contact_id INTEGER,
            company_id INTEGER,
            lead_id INTEGER,
            opportunity_id INTEGER,
            task_id INTEGER,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL,
            deleted_at TEXT,
            FOREIGN KEY(created_by) REFERENCES users(id),
            FOREIGN KEY(assigned_to) REFERENCES users(id),
            FOREIGN KEY(contact_id) REFERENCES contacts(id),
            FOREIGN KEY(company_id) REFERENCES companies(id),
            FOREIGN KEY(lead_id) REFERENCES leads(id),
            FOREIGN KEY(opportunity_id) REFERENCES opportunities(id),
            FOREIGN KEY(task_id) REFERENCES tasks(id)
        )',
        'CREATE TABLE IF NOT EXISTS documents (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            uid TEXT NOT NULL UNIQUE,
            original_name TEXT NOT NULL,
            stored_name TEXT NOT NULL UNIQUE,
            mime_type TEXT NOT NULL,
            size_bytes INTEGER NOT NULL,
            category TEXT NOT NULL DEFAULT "other",
            uploaded_by INTEGER,
            contact_id INTEGER,
            company_id INTEGER,
            lead_id INTEGER,
            opportunity_id INTEGER,
            created_at TEXT NOT NULL,
            deleted_at TEXT,
            FOREIGN KEY(uploaded_by) REFERENCES users(id),
            FOREIGN KEY(contact_id) REFERENCES contacts(id),
            FOREIGN KEY(company_id) REFERENCES companies(id),
            FOREIGN KEY(lead_id) REFERENCES leads(id),
            FOREIGN KEY(opportunity_id) REFERENCES opportunities(id)
        )',
        'CREATE TABLE IF NOT EXISTS tags (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            uid TEXT NOT NULL UNIQUE,
            name TEXT NOT NULL UNIQUE COLLATE NOCASE,
            color TEXT NOT NULL DEFAULT "#0f7fa6",
            created_at TEXT NOT NULL
        )',
        'CREATE TABLE IF NOT EXISTS record_tags (
            tag_id INTEGER NOT NULL,
            record_type TEXT NOT NULL,
            record_id INTEGER NOT NULL,
            created_at TEXT NOT NULL,
            PRIMARY KEY(tag_id, record_type, record_id),
            FOREIGN KEY(tag_id) REFERENCES tags(id) ON DELETE CASCADE
        )',
        'CREATE TABLE IF NOT EXISTS email_templates (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            uid TEXT NOT NULL UNIQUE,
            name TEXT NOT NULL,
            subject TEXT NOT NULL,
            body TEXT NOT NULL,
            is_active INTEGER NOT NULL DEFAULT 1,
            created_by INTEGER,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL,
            FOREIGN KEY(created_by) REFERENCES users(id)
        )',
        'CREATE TABLE IF NOT EXISTS notifications (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            uid TEXT NOT NULL UNIQUE,
            user_id INTEGER NOT NULL,
            type TEXT NOT NULL,
            title TEXT NOT NULL,
            body TEXT,
            link TEXT,
            read_at TEXT,
            created_at TEXT NOT NULL,
            FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
        )',
        'CREATE TABLE IF NOT EXISTS automation_rules (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            uid TEXT NOT NULL UNIQUE,
            name TEXT NOT NULL,
            trigger_event TEXT NOT NULL,
            conditions_json TEXT NOT NULL DEFAULT "{}",
            actions_json TEXT NOT NULL DEFAULT "{}",
            is_active INTEGER NOT NULL DEFAULT 1,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )',
        'CREATE TABLE IF NOT EXISTS settings (
            key TEXT PRIMARY KEY,
            value TEXT,
            updated_by INTEGER,
            updated_at TEXT NOT NULL,
            FOREIGN KEY(updated_by) REFERENCES users(id)
        )',
        'CREATE TABLE IF NOT EXISTS audit_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            uid TEXT NOT NULL UNIQUE,
            user_id INTEGER,
            action TEXT NOT NULL,
            record_type TEXT,
            record_id INTEGER,
            summary TEXT NOT NULL,
            changes_json TEXT,
            ip_hash TEXT,
            created_at TEXT NOT NULL,
            FOREIGN KEY(user_id) REFERENCES users(id)
        )',
    ];

    foreach ($statements as $statement) {
        $pdo->exec($statement);
    }

    $indexes = [
        'CREATE INDEX IF NOT EXISTS idx_contacts_email ON contacts(email)',
        'CREATE INDEX IF NOT EXISTS idx_contacts_company ON contacts(company_id)',
        'CREATE INDEX IF NOT EXISTS idx_companies_name ON companies(name)',
        'CREATE INDEX IF NOT EXISTS idx_leads_status ON leads(status)',
        'CREATE INDEX IF NOT EXISTS idx_leads_email ON leads(email)',
        'CREATE INDEX IF NOT EXISTS idx_leads_owner ON leads(owner_id)',
        'CREATE INDEX IF NOT EXISTS idx_opportunities_stage ON opportunities(stage_id)',
        'CREATE INDEX IF NOT EXISTS idx_opportunities_owner ON opportunities(owner_id)',
        'CREATE INDEX IF NOT EXISTS idx_tasks_due ON tasks(due_at, status)',
        'CREATE INDEX IF NOT EXISTS idx_activities_links ON activities(contact_id, company_id, lead_id, opportunity_id)',
        'CREATE INDEX IF NOT EXISTS idx_audit_created ON audit_logs(created_at)',
    ];
    foreach ($indexes as $statement) {
        $pdo->exec($statement);
    }

    $statement = $pdo->prepare('INSERT OR IGNORE INTO schema_migrations(version, applied_at) VALUES(1, ?)');
    $statement->execute([crm_now()]);
}

function crm_migrate_mysql(PDO $pdo): void
{
    $schemaPath = __DIR__ . '/_crm-mysql.sql';
    if (!is_file($schemaPath)) {
        throw new RuntimeException('Le schéma MySQL du CRM est introuvable.');
    }
    $sql = (string)file_get_contents($schemaPath);
    foreach (preg_split('/;\s*(?:\r?\n|$)/', $sql) ?: [] as $statement) {
        $statement = trim($statement);
        if ($statement !== '') {
            $pdo->exec($statement);
        }
    }
}

function crm_seed(PDO $pdo): void
{
    $now = crm_now();
    $stages = [
        ['Nouvelle demande', 'new', 1, 10, '#0f7fa6', 0, 0],
        ['À qualifier', 'qualification', 2, 20, '#41758a', 0, 0],
        ['Rendez-vous planifié', 'meeting', 3, 35, '#7b6baa', 0, 0],
        ['Proposition envoyée', 'proposal', 4, 60, '#b27a25', 0, 0],
        ['Négociation', 'negotiation', 5, 80, '#c05e32', 0, 0],
        ['Gagnée', 'won', 6, 100, '#5b8f26', 1, 0],
        ['Perdue', 'lost', 7, 0, '#9a4b4b', 0, 1],
    ];
    $insertStage = $pdo->prepare(
        'INSERT INTO pipeline_stages(uid, name, slug, position, probability, color, is_won, is_lost, created_at, updated_at)
         VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    foreach ($stages as $stage) {
        $exists = crm_fetch_one('SELECT id FROM pipeline_stages WHERE slug = ?', [$stage[1]], $pdo);
        if ($exists === null) {
            $insertStage->execute([crm_uid(), ...$stage, $now, $now]);
        }
    }

    $admin = crm_fetch_one('SELECT id FROM users WHERE username = ?', [cms_admin_username()], $pdo);
    if ($admin === null) {
        $hash = cms_password_hash();
        if ($hash !== '') {
            crm_execute(
                'INSERT INTO users(uid, username, full_name, email, password_hash, role, is_active, created_at, updated_at)
                 VALUES(?, ?, ?, ?, ?, "admin", 1, ?, ?)',
                [
                    crm_uid(),
                    cms_admin_username(),
                    'Administrateur Cabinet Aiouez',
                    (string)(cms_config()['notify_email'] ?? ''),
                    $hash,
                    $now,
                    $now,
                ],
                $pdo
            );
        }
    }

    $defaults = [
        'crm_name' => 'Cabinet Aiouez CRM',
        'currency' => 'DZD',
        'timezone' => 'Africa/Algiers',
        'retention_days' => '1825',
        'lead_assignment' => 'admin',
        'email_notifications' => '1',
    ];
    foreach ($defaults as $key => $value) {
        if (crm_fetch_one('SELECT `key` FROM settings WHERE `key` = ?', [$key], $pdo) === null) {
            crm_execute(
                'INSERT INTO settings(`key`, value, updated_at) VALUES(?, ?, ?)',
                [$key, $value, $now],
                $pdo
            );
        }
    }

    if (crm_fetch_one('SELECT id FROM email_templates LIMIT 1', [], $pdo) === null) {
        crm_execute(
            'INSERT INTO email_templates(uid, name, subject, body, created_at, updated_at)
             VALUES(?, ?, ?, ?, ?, ?)',
            [
                crm_uid(),
                'Accusé de réception',
                'Votre demande auprès du Cabinet Aiouez',
                "Bonjour {{contact_name}},\n\nNous avons bien reçu votre demande concernant {{service}}. Notre équipe reviendra vers vous prochainement.\n\nCabinet Aiouez",
                $now,
                $now,
            ],
            $pdo
        );
    }
}

function crm_import_legacy_submissions(PDO $pdo): void
{
    $directory = cms_private_dir() . '/submissions';
    foreach (glob($directory . '/*.json') ?: [] as $path) {
        $item = json_decode((string)file_get_contents($path), true);
        if (!is_array($item) || !isset($item['id'], $item['name'])) {
            continue;
        }
        if (crm_fetch_one('SELECT id FROM leads WHERE legacy_id = ?', [(string)$item['id']], $pdo) !== null) {
            continue;
        }
        $statusMap = [
            'new' => 'new',
            'in_progress' => 'qualified',
            'contacted' => 'meeting',
            'closed' => 'converted',
            'archived' => 'archived',
        ];
        $owner = crm_fetch_one('SELECT id FROM users WHERE role = "admin" AND is_active = 1 ORDER BY id LIMIT 1', [], $pdo);
        crm_execute(
            'INSERT INTO leads(
                uid, legacy_id, name, company_name, email, phone, service, message, status, priority,
                source, owner_id, created_at, updated_at
             ) VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, "normal", ?, ?, ?, ?)',
            [
                crm_uid(),
                (string)$item['id'],
                (string)$item['name'],
                (string)($item['company'] ?? ''),
                (string)($item['email'] ?? ''),
                (string)($item['phone'] ?? ''),
                (string)($item['need'] ?? ''),
                (string)($item['message'] ?? ''),
                $statusMap[(string)($item['status'] ?? 'new')] ?? 'new',
                (string)($item['source'] ?? 'website'),
                $owner['id'] ?? null,
                str_replace('T', ' ', substr((string)($item['created_at'] ?? crm_now()), 0, 19)),
                str_replace('T', ' ', substr((string)($item['updated_at'] ?? crm_now()), 0, 19)),
            ],
            $pdo
        );
    }
}

function crm_fetch_all(string $sql, array $params = [], ?PDO $pdo = null): array
{
    $statement = ($pdo ?? crm_db())->prepare($sql);
    $statement->execute($params);
    return $statement->fetchAll() ?: [];
}

function crm_fetch_one(string $sql, array $params = [], ?PDO $pdo = null): ?array
{
    $statement = ($pdo ?? crm_db())->prepare($sql);
    $statement->execute($params);
    $row = $statement->fetch();
    return is_array($row) ? $row : null;
}

function crm_execute(string $sql, array $params = [], ?PDO $pdo = null): int
{
    $statement = ($pdo ?? crm_db())->prepare($sql);
    $statement->execute($params);
    return $statement->rowCount();
}

function crm_last_id(?PDO $pdo = null): int
{
    return (int)($pdo ?? crm_db())->lastInsertId();
}

function crm_setting(string $key, ?string $fallback = null): ?string
{
    $row = crm_fetch_one('SELECT value FROM settings WHERE `key` = ?', [$key]);
    return $row === null ? $fallback : (string)$row['value'];
}

function crm_set_setting(string $key, string $value, ?int $userId): void
{
    if (crm_driver() === 'mysql') {
        crm_execute(
            'INSERT INTO settings(`key`, value, updated_by, updated_at) VALUES(?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE value = VALUES(value), updated_by = VALUES(updated_by), updated_at = VALUES(updated_at)',
            [$key, $value, $userId, crm_now()]
        );
    } else {
        crm_execute(
            'INSERT INTO settings(`key`, value, updated_by, updated_at) VALUES(?, ?, ?, ?)
             ON CONFLICT(`key`) DO UPDATE SET value = excluded.value, updated_by = excluded.updated_by, updated_at = excluded.updated_at',
            [$key, $value, $userId, crm_now()]
        );
    }
}

function crm_current_user(): ?array
{
    cms_start_session();
    $userId = (int)($_SESSION['user_id'] ?? 0);
    if ($userId <= 0) {
        return null;
    }
    return crm_fetch_one('SELECT * FROM users WHERE id = ? AND is_active = 1 AND deleted_at IS NULL', [$userId]);
}

function crm_authenticate(string $username, string $password): ?array
{
    $user = crm_fetch_one(
        'SELECT * FROM users WHERE username = ? AND is_active = 1 AND deleted_at IS NULL',
        [$username]
    );
    if ($user === null || !password_verify($password, (string)$user['password_hash'])) {
        return null;
    }
    crm_execute('UPDATE users SET last_login_at = ?, updated_at = ? WHERE id = ?', [crm_now(), crm_now(), $user['id']]);
    return $user;
}

function crm_login_user(array $user): void
{
    cms_start_session();
    session_regenerate_id(true);
    $_SESSION['authenticated'] = true;
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['admin_username'] = (string)$user['username'];
}

function crm_permission_map(): array
{
    return [
        'admin' => ['*'],
        'manager' => [
            'dashboard.view', 'records.view', 'records.create', 'records.edit', 'records.archive',
            'documents.manage', 'reports.view', 'exports.run', 'automations.manage',
        ],
        'collaborator' => [
            'dashboard.view', 'records.view', 'records.create', 'records.edit', 'documents.manage',
        ],
        'viewer' => ['dashboard.view', 'records.view', 'reports.view'],
    ];
}

function crm_notify_user(int $userId, string $type, string $title, string $body, string $link): void
{
    crm_execute(
        'INSERT INTO notifications(uid, user_id, type, title, body, link, created_at)
         VALUES(?, ?, ?, ?, ?, ?, ?)',
        [crm_uid(), $userId, $type, $title, $body, $link, crm_now()]
    );
}

function crm_can(string $permission, ?array $user = null): bool
{
    $user ??= crm_current_user();
    if ($user === null) {
        return false;
    }
    $permissions = crm_permission_map()[(string)$user['role']] ?? [];
    return in_array('*', $permissions, true) || in_array($permission, $permissions, true);
}

function crm_require_permission(string $permission): void
{
    if (!crm_can($permission)) {
        http_response_code(403);
        exit('Vous ne disposez pas de cette autorisation.');
    }
}

function crm_audit(
    string $action,
    string $recordType,
    ?int $recordId,
    string $summary,
    array $changes = [],
    ?int $userId = null
): void {
    $config = cms_config();
    $secret = (string)($config['rate_secret'] ?? 'aiouez-audit');
    $ipHash = hash_hmac('sha256', cms_request_ip(), $secret);
    $userId ??= isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    crm_execute(
        'INSERT INTO audit_logs(uid, user_id, action, record_type, record_id, summary, changes_json, ip_hash, created_at)
         VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [
            crm_uid(),
            $userId,
            $action,
            $recordType,
            $recordId,
            $summary,
            $changes === [] ? null : json_encode($changes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $ipHash,
            crm_now(),
        ]
    );
}

function crm_notify_users(string $type, string $title, string $body, string $link, array $roles = ['admin', 'manager']): void
{
    $placeholders = implode(',', array_fill(0, count($roles), '?'));
    $users = crm_fetch_all(
        "SELECT id FROM users WHERE role IN ($placeholders) AND is_active = 1 AND deleted_at IS NULL",
        $roles
    );
    foreach ($users as $user) {
        crm_notify_user((int)$user['id'], $type, $title, $body, $link);
    }
}

function crm_run_automations(string $event, array $context): void
{
    $rules = crm_fetch_all('SELECT * FROM automation_rules WHERE trigger_event = ? AND is_active = 1', [$event]);
    foreach ($rules as $rule) {
        $actions = json_decode((string)$rule['actions_json'], true);
        $action = is_array($actions) ? (string)($actions['action'] ?? '') : '';
        $ownerId = (int)($context['owner_id'] ?? 0);
        $leadId = (int)($context['lead_id'] ?? 0);
        $leadName = (string)($context['name'] ?? 'Nouveau lead');

        if ($action === 'notify_owner' && $ownerId > 0) {
            crm_notify_user($ownerId, 'automation', (string)$rule['name'], $leadName, '/admin/?view=leads&id=' . $leadId);
        } elseif ($action === 'notify_admin') {
            crm_notify_users('automation', (string)$rule['name'], $leadName, '/admin/?view=leads&id=' . $leadId, ['admin']);
        } elseif ($action === 'create_followup' && $leadId > 0) {
            crm_execute(
                'INSERT INTO tasks(uid,title,description,status,priority,due_at,assigned_to,created_by,lead_id,created_at,updated_at)
                 VALUES(?,?,?,"open","normal",?,?,?,?,?,?)',
                [
                    crm_uid(), 'Relancer · ' . $leadName, 'Tâche créée automatiquement.',
                    gmdate('Y-m-d H:i:s', time() + 2 * 86400), $ownerId ?: null, $ownerId ?: null,
                    $leadId, crm_now(), crm_now(),
                ]
            );
        }
        crm_audit('automation', 'automation_rule', (int)$rule['id'], 'Automatisation exécutée : ' . $rule['name'], ['event' => $event]);
    }
}

function crm_run_scheduled_jobs(PDO $pdo): void
{
    $last = crm_fetch_one('SELECT value FROM settings WHERE `key` = "last_scheduled_run"', [], $pdo);
    if ($last !== null && strtotime((string)$last['value']) > time() - 3600) {
        return;
    }
    $now = crm_now();
    if (crm_driver($pdo) === 'mysql') {
        crm_execute(
            'INSERT INTO settings(`key`,value,updated_at) VALUES("last_scheduled_run",?,?)
             ON DUPLICATE KEY UPDATE value=VALUES(value),updated_at=VALUES(updated_at)',
            [$now, $now],
            $pdo
        );
    } else {
        crm_execute(
            'INSERT INTO settings(`key`,value,updated_at) VALUES("last_scheduled_run",?,?)
             ON CONFLICT(`key`) DO UPDATE SET value=excluded.value,updated_at=excluded.updated_at',
            [$now, $now],
            $pdo
        );
    }

    $overdue = crm_fetch_all(
        'SELECT t.id,t.title,t.assigned_to FROM tasks t
         WHERE t.deleted_at IS NULL AND t.status IN ("open","in_progress") AND t.due_at IS NOT NULL AND t.due_at < ?',
        [$now],
        $pdo
    );
    $today = gmdate('Y-m-d');
    foreach ($overdue as $task) {
        $userId = (int)($task['assigned_to'] ?? 0);
        if ($userId <= 0) {
            continue;
        }
        $link = '/admin/?view=tasks&id=' . $task['id'];
        $exists = crm_fetch_one(
            'SELECT id FROM notifications WHERE user_id=? AND type="task.overdue" AND link=? AND created_at >= ? LIMIT 1',
            [$userId, $link, $today . ' 00:00:00'],
            $pdo
        );
        if ($exists === null) {
            crm_execute(
                'INSERT INTO notifications(uid,user_id,type,title,body,link,created_at) VALUES(?,?,"task.overdue",?,?,?,?)',
                [crm_uid(), $userId, 'Tâche en retard', (string)$task['title'], $link, $now],
                $pdo
            );
        }
    }

    $retentionDays = max(30, (int)(crm_fetch_one('SELECT value FROM settings WHERE `key`="retention_days"', [], $pdo)['value'] ?? 1825));
    $cutoff = gmdate('Y-m-d H:i:s', time() - $retentionDays * 86400);
    $expiredDocuments = crm_fetch_all('SELECT stored_name FROM documents WHERE deleted_at IS NOT NULL AND deleted_at < ?', [$cutoff], $pdo);
    foreach ($expiredDocuments as $document) {
        $path = cms_private_dir() . '/documents/' . basename((string)$document['stored_name']);
        if (is_file($path)) {
            @unlink($path);
        }
    }
    crm_execute('DELETE FROM documents WHERE deleted_at IS NOT NULL AND deleted_at < ?', [$cutoff], $pdo);
    crm_execute('DELETE FROM notifications WHERE read_at IS NOT NULL AND created_at < ?', [$cutoff], $pdo);
    crm_execute('DELETE FROM audit_logs WHERE created_at < ?', [$cutoff], $pdo);
}

function crm_split_name(string $name): array
{
    $parts = preg_split('/\s+/', trim($name), 2) ?: [];
    return [$parts[0] ?? '', $parts[1] ?? ''];
}

function crm_find_contact_by_identity(string $email, string $phone): ?array
{
    if ($email !== '') {
        $contact = crm_fetch_one(
            'SELECT * FROM contacts WHERE lower(email) = lower(?) AND deleted_at IS NULL LIMIT 1',
            [$email]
        );
        if ($contact !== null) {
            return $contact;
        }
    }
    if ($phone !== '') {
        $normalized = preg_replace('/\D+/', '', $phone);
        foreach (crm_fetch_all('SELECT * FROM contacts WHERE deleted_at IS NULL AND (phone IS NOT NULL OR mobile IS NOT NULL)') as $contact) {
            $candidatePhone = preg_replace('/\D+/', '', (string)($contact['phone'] ?: $contact['mobile']));
            if ($candidatePhone !== '' && $candidatePhone === $normalized) {
                return $contact;
            }
        }
    }
    return null;
}

function crm_find_company_by_name(string $name): ?array
{
    if (trim($name) === '') {
        return null;
    }
    return crm_fetch_one('SELECT * FROM companies WHERE lower(name) = lower(?) AND deleted_at IS NULL LIMIT 1', [trim($name)]);
}

function crm_create_company(array $data, ?int $userId): int
{
    $now = crm_now();
    crm_execute(
        'INSERT INTO companies(
            uid, name, legal_name, industry, website, email, phone, address, city, country,
            tax_id, registration_number, status, owner_id, notes, created_at, updated_at
         ) VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [
            crm_uid(), $data['name'], $data['legal_name'] ?? '', $data['industry'] ?? '',
            $data['website'] ?? '', $data['email'] ?? '', $data['phone'] ?? '',
            $data['address'] ?? '', $data['city'] ?? '', $data['country'] ?? 'Algérie',
            $data['tax_id'] ?? '', $data['registration_number'] ?? '',
            $data['status'] ?? 'prospect', $data['owner_id'] ?? $userId, $data['notes'] ?? '',
            $now, $now,
        ]
    );
    $id = crm_last_id();
    crm_audit('create', 'company', $id, 'Entreprise créée', ['name' => $data['name']], $userId);
    return $id;
}

function crm_create_contact(array $data, ?int $userId): int
{
    $now = crm_now();
    crm_execute(
        'INSERT INTO contacts(
            uid, first_name, last_name, email, phone, mobile, job_title, preferred_language,
            address, city, country, source, status, owner_id, company_id, notes, created_at, updated_at
         ) VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [
            crm_uid(), $data['first_name'], $data['last_name'] ?? '', $data['email'] ?? '',
            $data['phone'] ?? '', $data['mobile'] ?? '', $data['job_title'] ?? '',
            $data['preferred_language'] ?? 'fr', $data['address'] ?? '', $data['city'] ?? '',
            $data['country'] ?? 'Algérie', $data['source'] ?? 'manual', $data['status'] ?? 'prospect',
            $data['owner_id'] ?? $userId, $data['company_id'] ?? null, $data['notes'] ?? '',
            $now, $now,
        ]
    );
    $id = crm_last_id();
    crm_audit('create', 'contact', $id, 'Contact créé', ['email' => $data['email'] ?? ''], $userId);
    return $id;
}

function crm_create_lead(array $data, ?int $userId = null): int
{
    $now = crm_now();
    crm_execute(
        'INSERT INTO leads(
            uid, legacy_id, name, company_name, email, phone, service, message, status, priority, source,
            estimated_value, currency, owner_id, contact_id, company_id, created_at, updated_at
         ) VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [
            crm_uid(), $data['legacy_id'] ?? null, $data['name'], $data['company_name'] ?? '', $data['email'] ?? '',
            $data['phone'] ?? '', $data['service'] ?? '', $data['message'] ?? '',
            $data['status'] ?? 'new', $data['priority'] ?? 'normal', $data['source'] ?? 'manual',
            $data['estimated_value'] ?? null, $data['currency'] ?? crm_setting('currency', 'DZD'),
            $data['owner_id'] ?? $userId, $data['contact_id'] ?? null, $data['company_id'] ?? null,
            $now, $now,
        ]
    );
    $id = crm_last_id();
    crm_audit('create', 'lead', $id, 'Lead créé', ['name' => $data['name']], $userId);
    return $id;
}

function crm_convert_lead(int $leadId, array $options, int $userId): array
{
    $pdo = crm_db();
    $lead = crm_fetch_one('SELECT * FROM leads WHERE id = ? AND deleted_at IS NULL', [$leadId], $pdo);
    if ($lead === null) {
        throw new RuntimeException('Lead introuvable.');
    }
    if (!empty($lead['converted_at'])) {
        return [
            'contact_id' => (int)$lead['contact_id'],
            'company_id' => $lead['company_id'] ? (int)$lead['company_id'] : null,
            'opportunity_id' => $lead['converted_opportunity_id'] ? (int)$lead['converted_opportunity_id'] : null,
        ];
    }

    $pdo->beginTransaction();
    try {
        $company = crm_find_company_by_name((string)$lead['company_name']);
        $companyId = $company['id'] ?? null;
        if ($companyId === null && trim((string)$lead['company_name']) !== '') {
            $companyId = crm_create_company([
                'name' => (string)$lead['company_name'],
                'email' => '',
                'phone' => (string)$lead['phone'],
                'source' => (string)$lead['source'],
                'owner_id' => $lead['owner_id'] ?: $userId,
            ], $userId);
        }

        $contact = crm_find_contact_by_identity((string)$lead['email'], (string)$lead['phone']);
        $contactId = $contact['id'] ?? null;
        if ($contactId === null) {
            [$firstName, $lastName] = crm_split_name((string)$lead['name']);
            $contactId = crm_create_contact([
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => (string)$lead['email'],
                'phone' => (string)$lead['phone'],
                'company_id' => $companyId,
                'source' => (string)$lead['source'],
                'owner_id' => $lead['owner_id'] ?: $userId,
            ], $userId);
        } elseif ($companyId !== null && empty($contact['company_id'])) {
            crm_execute('UPDATE contacts SET company_id = ?, updated_at = ? WHERE id = ?', [$companyId, crm_now(), $contactId], $pdo);
        }

        $stage = crm_fetch_one('SELECT id, probability FROM pipeline_stages WHERE slug = "qualification"', [], $pdo);
        $opportunityId = null;
        if (($options['create_opportunity'] ?? true) && $stage !== null) {
            crm_execute(
                'INSERT INTO opportunities(
                    uid, name, service, description, value, currency, probability, expected_close_date,
                    next_action, source, stage_id, owner_id, contact_id, company_id, lead_id, created_at, updated_at
                 ) VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    crm_uid(),
                    (string)($options['opportunity_name'] ?? ('Mission · ' . $lead['name'])),
                    (string)$lead['service'],
                    (string)$lead['message'],
                    (float)($options['value'] ?? $lead['estimated_value'] ?? 0),
                    (string)($lead['currency'] ?? 'DZD'),
                    (int)$stage['probability'],
                    $options['expected_close_date'] ?? null,
                    (string)($options['next_action'] ?? 'Qualifier le besoin'),
                    (string)$lead['source'],
                    $stage['id'],
                    $lead['owner_id'] ?: $userId,
                    $contactId,
                    $companyId,
                    $leadId,
                    crm_now(),
                    crm_now(),
                ],
                $pdo
            );
            $opportunityId = crm_last_id($pdo);
        }

        crm_execute(
            'UPDATE leads SET status = "converted", contact_id = ?, company_id = ?,
             converted_opportunity_id = ?, converted_at = ?, updated_at = ? WHERE id = ?',
            [$contactId, $companyId, $opportunityId, crm_now(), crm_now(), $leadId],
            $pdo
        );
        crm_execute(
            'INSERT INTO activities(uid, type, subject, body, activity_at, created_by, contact_id, company_id, lead_id, opportunity_id, created_at, updated_at)
             VALUES(?, "system", ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                crm_uid(), 'Lead converti', 'Le lead a été converti en contact et opportunité.',
                crm_now(), $userId, $contactId, $companyId, $leadId, $opportunityId, crm_now(), crm_now(),
            ],
            $pdo
        );
        crm_audit('convert', 'lead', $leadId, 'Lead converti', [
            'contact_id' => $contactId,
            'company_id' => $companyId,
            'opportunity_id' => $opportunityId,
        ], $userId);
        $pdo->commit();
        return ['contact_id' => $contactId, 'company_id' => $companyId, 'opportunity_id' => $opportunityId];
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}

function crm_documents_dir(): string
{
    $directory = cms_private_dir() . '/documents';
    if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
        throw new RuntimeException('Impossible d’initialiser le stockage des documents.');
    }
    return $directory;
}

function crm_store_document(array $file, array $links, int $userId): int
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Le document n’a pas pu être transféré.');
    }
    $size = (int)($file['size'] ?? 0);
    if ($size <= 0 || $size > 15 * 1024 * 1024) {
        throw new RuntimeException('Le document doit peser moins de 15 Mo.');
    }
    $temporary = (string)($file['tmp_name'] ?? '');
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string)$finfo->file($temporary);
    $allowed = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
        'text/csv' => 'csv',
        'text/plain' => 'txt',
    ];
    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Ce format de document n’est pas autorisé.');
    }
    $stored = crm_uid() . '.' . $allowed[$mime];
    $destination = crm_documents_dir() . '/' . $stored;
    if (!move_uploaded_file($temporary, $destination)) {
        throw new RuntimeException('Impossible de stocker le document.');
    }
    @chmod($destination, 0640);
    crm_execute(
        'INSERT INTO documents(
            uid, original_name, stored_name, mime_type, size_bytes, category, uploaded_by,
            contact_id, company_id, lead_id, opportunity_id, created_at
         ) VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [
            crm_uid(),
            cms_clean_text($file['name'] ?? 'document', 220),
            $stored,
            $mime,
            $size,
            $links['category'] ?? 'other',
            $userId,
            $links['contact_id'] ?? null,
            $links['company_id'] ?? null,
            $links['lead_id'] ?? null,
            $links['opportunity_id'] ?? null,
            crm_now(),
        ]
    );
    $id = crm_last_id();
    crm_audit('upload', 'document', $id, 'Document ajouté', ['name' => $file['name'] ?? 'document'], $userId);
    return $id;
}

function crm_human_bytes(int $bytes): string
{
    if ($bytes >= 1024 * 1024) {
        return number_format($bytes / (1024 * 1024), 1, ',', ' ') . ' Mo';
    }
    if ($bytes >= 1024) {
        return number_format($bytes / 1024, 0, ',', ' ') . ' Ko';
    }
    return $bytes . ' o';
}
