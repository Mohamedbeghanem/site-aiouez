<?php
declare(strict_types=1);

$private = $argv[1] ?? '';
if ($private === '' || !str_starts_with($private, sys_get_temp_dir() . '/aiouez-crm-visual-')) {
    throw new RuntimeException('Use an isolated aiouez-crm-visual-* temporary directory.');
}

foreach ([$private, $private . '/submissions', $private . '/sessions', $private . '/documents'] as $directory) {
    if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
        throw new RuntimeException('Unable to create fixture directory.');
    }
}

putenv('AIOUEZ_CMS_PRIVATE_DIR=' . $private);
file_put_contents($private . '/config.php', "<?php\nreturn " . var_export([
    'admin_username' => 'admin',
    'password_hash' => password_hash('Visual-test-only-2026!', PASSWORD_DEFAULT),
    'rate_secret' => bin2hex(random_bytes(16)),
    'database' => ['driver' => 'sqlite', 'path' => $private . '/crm.sqlite'],
], true) . ";\n");

require dirname(__DIR__, 2) . '/public/api/_crm.php';

$pdo = crm_db();
$adminId = (int)crm_fetch_one('SELECT id FROM users ORDER BY id LIMIT 1')['id'];
$companyId = crm_create_company([
    'name' => 'Atlas Conseil',
    'legal_name' => 'Atlas Conseil EURL',
    'industry' => 'Services professionnels',
    'email' => 'contact@atlas.test',
    'phone' => '+213 555 10 20 30',
    'city' => 'Alger',
    'status' => 'client',
    'owner_id' => $adminId,
], $adminId);
$contactId = crm_create_contact([
    'first_name' => 'Nadia',
    'last_name' => 'Benali',
    'email' => 'nadia@atlas.test',
    'phone' => '+213 555 42 21 18',
    'job_title' => 'Directrice financière',
    'status' => 'client',
    'company_id' => $companyId,
    'owner_id' => $adminId,
], $adminId);

$leadRows = [
    ['Karim Bensaïd', 'Bensaïd Distribution', 'karim@example.test', 'Fiscalité', 'new', 'urgent', 850000],
    ['Amel Haddad', 'Haddad Industrie', 'amel@example.test', 'Expertise comptable', 'qualified', 'high', 420000],
    ['Sofiane Merabet', 'Merabet & Associés', 'sofiane@example.test', 'Conseil en gestion', 'meeting', 'normal', 300000],
    ['Yasmine Kaci', 'Kaci Digital', 'yasmine@example.test', 'Commissariat aux comptes', 'proposal', 'normal', 1250000],
];
$leadIds = [];
foreach ($leadRows as [$name, $company, $email, $service, $status, $priority, $value]) {
    $leadIds[] = crm_create_lead([
        'name' => $name,
        'company_name' => $company,
        'email' => $email,
        'phone' => '+213 555 00 00 00',
        'service' => $service,
        'message' => 'Demande de démonstration destinée aux tests visuels.',
        'status' => $status,
        'priority' => $priority,
        'source' => $status === 'new' ? 'website' : 'recommendation',
        'estimated_value' => $value,
        'owner_id' => $adminId,
    ], $adminId);
}

$stageRows = crm_fetch_all('SELECT * FROM pipeline_stages ORDER BY position');
foreach (array_slice($stageRows, 0, 5) as $index => $stage) {
    crm_execute(
        'INSERT INTO opportunities(uid,name,service,description,value,currency,probability,expected_close_date,next_action,source,stage_id,owner_id,contact_id,company_id,created_at,updated_at)
         VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
        [
            crm_uid(), ['Mission Atlas', 'Audit annuel', 'Accompagnement fiscal', 'Mise en conformité', 'Conseil stratégique'][$index],
            'Expertise comptable', 'Opportunité de test visuel.', 250000 + $index * 175000, 'DZD',
            $stage['probability'], gmdate('Y-m-d', time() + ($index + 1) * 14 * 86400),
            'Préparer le prochain échange', 'fixture', $stage['id'], $adminId, $contactId, $companyId,
            crm_now(), crm_now(),
        ]
    );
}

$taskRows = [
    ['Relancer Atlas Conseil', 'urgent', gmdate('Y-m-d H:i:s', time() - 86400), 'open'],
    ['Préparer la proposition fiscale', 'high', gmdate('Y-m-d H:i:s', time() + 7200), 'in_progress'],
    ['Réunion de cadrage', 'normal', gmdate('Y-m-d H:i:s', time() + 3 * 86400), 'open'],
    ['Vérifier les pièces comptables', 'low', gmdate('Y-m-d H:i:s', time() + 8 * 86400), 'completed'],
];
foreach ($taskRows as [$title, $priority, $dueAt, $status]) {
    crm_execute(
        'INSERT INTO tasks(uid,title,description,status,priority,due_at,completed_at,assigned_to,created_by,contact_id,company_id,created_at,updated_at)
         VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)',
        [
            crm_uid(), $title, 'Tâche de démonstration pour la validation de l’interface.', $status, $priority,
            $dueAt, $status === 'completed' ? crm_now() : null, $adminId, $adminId, $contactId, $companyId,
            crm_now(), crm_now(),
        ]
    );
}

foreach ([
    ['call', 'Appel de qualification', 'Le besoin et le calendrier ont été confirmés.'],
    ['meeting', 'Réunion de cadrage', 'Prochain jalon : réception des documents.'],
    ['email', 'Proposition envoyée', 'La proposition a été transmise au contact principal.'],
] as [$type, $subject, $body]) {
    crm_execute(
        'INSERT INTO activities(uid,type,subject,body,activity_at,created_by,contact_id,company_id,created_at,updated_at)
         VALUES(?,?,?,?,?,?,?,?,?,?)',
        [crm_uid(), $type, $subject, $body, crm_now(), $adminId, $contactId, $companyId, crm_now(), crm_now()]
    );
}

crm_notify_user($adminId, 'lead.new', 'Nouveau lead reçu', 'Karim Bensaïd · Fiscalité', '/admin/?view=leads&id=' . $leadIds[0]);
crm_notify_user($adminId, 'task.overdue', 'Tâche en retard', 'Relancer Atlas Conseil', '/admin/?view=tasks');

echo $private . PHP_EOL;
