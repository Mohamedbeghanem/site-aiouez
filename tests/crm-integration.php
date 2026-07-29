<?php
declare(strict_types=1);

$private = sys_get_temp_dir() . '/aiouez-crm-test-' . bin2hex(random_bytes(5));
putenv('AIOUEZ_CMS_PRIVATE_DIR=' . $private);
mkdir($private . '/submissions', 0750, true);

$password = 'Test-password-2026!';
$config = "<?php\nreturn " . var_export([
    'admin_username' => 'admin',
    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
    'rate_secret' => bin2hex(random_bytes(16)),
    'database' => ['driver' => 'sqlite', 'path' => $private . '/crm.sqlite'],
], true) . ";\n";
file_put_contents($private . '/config.php', $config);

$legacy = [
    'id' => 'abcdef012345abcdef012345',
    'created_at' => '2026-07-01T09:00:00+00:00',
    'updated_at' => '2026-07-01T09:00:00+00:00',
    'status' => 'new',
    'name' => 'Client Historique',
    'company' => 'Entreprise Test',
    'email' => 'legacy@example.test',
    'phone' => '+213555000000',
    'need' => 'Fiscalité',
    'message' => 'Demande importée',
    'notes' => '',
    'source' => 'aiouez.com',
];
file_put_contents(
    $private . '/submissions/' . $legacy['id'] . '.json',
    json_encode($legacy, JSON_THROW_ON_ERROR)
);

require dirname(__DIR__) . '/public/api/_crm.php';

function assert_true(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

try {
    $pdo = crm_db();
    assert_true(crm_driver($pdo) === 'sqlite', 'SQLite should be active.');
    assert_true((int)crm_fetch_one('SELECT COUNT(*) AS total FROM users')['total'] === 1, 'Admin seed failed.');
    assert_true((int)crm_fetch_one('SELECT COUNT(*) AS total FROM pipeline_stages')['total'] === 7, 'Pipeline seed failed.');
    assert_true((int)crm_fetch_one('SELECT COUNT(*) AS total FROM leads')['total'] === 1, 'Legacy import failed.');

    $user = crm_authenticate('admin', $password);
    assert_true($user !== null && $user['role'] === 'admin', 'Authentication failed.');

    $leadId = crm_create_lead([
        'name' => 'Nadia Benali',
        'company_name' => 'Benali Conseil',
        'email' => 'nadia@example.test',
        'phone' => '+213555111222',
        'service' => 'Expertise comptable',
        'message' => 'Besoin de conseil.',
        'source' => 'manual',
        'owner_id' => $user['id'],
    ], (int)$user['id']);
    $converted = crm_convert_lead($leadId, [], (int)$user['id']);
    assert_true((int)$converted['contact_id'] > 0, 'Contact conversion failed.');
    assert_true((int)$converted['company_id'] > 0, 'Company conversion failed.');
    assert_true((int)$converted['opportunity_id'] > 0, 'Opportunity conversion failed.');
    assert_true(crm_fetch_one('SELECT status FROM leads WHERE id = ?', [$leadId])['status'] === 'converted', 'Lead status was not converted.');
    $checklist = crm_fetch_one('SELECT id,status FROM onboarding_checklists WHERE company_id=?', [$converted['company_id']]);
    assert_true($checklist !== null && $checklist['status'] === 'active', 'Company onboarding checklist was not created.');
    assert_true(
        (int)crm_fetch_one('SELECT COUNT(*) AS total FROM onboarding_items WHERE checklist_id=?', [$checklist['id']])['total'] === 8,
        'Default accounting onboarding steps were not created.'
    );

    $communicationId = crm_create_communication([
        'channel' => 'email',
        'direction' => 'outbound',
        'subject' => 'Pièces comptables attendues',
        'body' => 'Merci de transmettre les pièces du dossier.',
        'recipients' => 'nadia@example.test',
        'delivery_status' => 'logged',
        'contact_id' => $converted['contact_id'],
        'company_id' => $converted['company_id'],
    ], (int)$user['id']);
    assert_true($communicationId > 0, 'Communication logging failed.');
    $timeline = crm_timeline('company', (int)$converted['company_id']);
    assert_true(
        count(array_filter($timeline, static fn(array $item): bool => $item['kind'] === 'email' && $item['title'] === 'Pièces comptables attendues')) === 1,
        'Unified timeline did not include the logged email.'
    );

    crm_set_setting('retention_days', '365', (int)$user['id']);
    assert_true(crm_setting('retention_days') === '365', 'Settings upsert failed.');
    crm_execute(
        'INSERT INTO automation_rules(uid,name,trigger_event,conditions_json,actions_json,is_active,created_at,updated_at)
         VALUES(?,?,?,"{}",?,1,?,?)',
        [crm_uid(), 'Relance automatique', 'lead.created', '{"action":"create_followup"}', crm_now(), crm_now()]
    );
    $automatedLeadId = crm_create_lead([
        'name' => 'Amine Relance',
        'email' => 'amine@example.test',
        'source' => 'manual',
        'owner_id' => $user['id'],
    ], (int)$user['id']);
    $automatedTask = crm_fetch_one('SELECT title,due_at FROM tasks WHERE lead_id=?', [$automatedLeadId]);
    assert_true($automatedTask !== null && $automatedTask['title'] === 'Relancer · Amine Relance', 'Automatic follow-up creation failed.');
    $expectedDue = time() + 2 * 86400;
    assert_true(abs(strtotime((string)$automatedTask['due_at']) - $expectedDue) < 120, 'Automatic follow-up delay is incorrect.');

    crm_execute(
        'INSERT INTO tasks(uid,title,status,priority,due_at,assigned_to,created_by,created_at,updated_at)
         VALUES(?,?,"open","high",?,?,?,?,?)',
        [crm_uid(), 'Tâche en retard', '2026-01-01 08:00:00', $user['id'], $user['id'], crm_now(), crm_now()]
    );
    crm_execute('UPDATE settings SET value=? WHERE `key`="last_scheduled_run"', ['2026-01-01 00:00:00']);
    crm_run_scheduled_jobs($pdo);
    assert_true((int)crm_fetch_one('SELECT COUNT(*) AS total FROM notifications WHERE type="task.overdue"')['total'] === 1, 'Overdue reminder failed.');
    assert_true((int)crm_fetch_one('SELECT COUNT(*) AS total FROM audit_logs')['total'] >= 4, 'Audit logging failed.');

    echo "CRM integration test passed\n";
} finally {
    $remove = static function (string $directory) use (&$remove): void {
        foreach (scandir($directory) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $directory . '/' . $entry;
            is_dir($path) ? $remove($path) : @unlink($path);
        }
        @rmdir($directory);
    };
    if (is_dir($private)) {
        $remove($private);
    }
}
