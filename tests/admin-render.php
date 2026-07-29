<?php
declare(strict_types=1);

$view = $argv[1] ?? 'dashboard';
$private = sys_get_temp_dir() . '/aiouez-admin-render-' . bin2hex(random_bytes(5));
putenv('AIOUEZ_CMS_PRIVATE_DIR=' . $private);
mkdir($private . '/submissions', 0750, true);
mkdir($private . '/sessions', 0750, true);
ini_set('session.save_path', $private . '/sessions');
file_put_contents($private . '/config.php', "<?php\nreturn " . var_export([
    'admin_username' => 'admin',
    'password_hash' => password_hash('Test-password-2026!', PASSWORD_DEFAULT),
    'rate_secret' => bin2hex(random_bytes(16)),
    'database' => ['driver' => 'sqlite', 'path' => $private . '/crm.sqlite'],
], true) . ";\n");

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTPS'] = 'on';
$_GET = ['view' => $view];
if (isset($argv[2])) {
    parse_str($argv[2], $extraQuery);
    $_GET = array_merge($_GET, $extraQuery);
}

require dirname(__DIR__) . '/public/api/_crm.php';
crm_db();
$user = crm_fetch_one('SELECT id FROM users ORDER BY id LIMIT 1');
$companyId = crm_create_company(['name' => 'Entreprise Démo', 'owner_id' => $user['id']], (int)$user['id']);
$contactId = crm_create_contact(['first_name' => 'Nadia', 'last_name' => 'Benali', 'email' => 'nadia@example.test', 'company_id' => $companyId, 'owner_id' => $user['id']], (int)$user['id']);
$leadId = crm_create_lead(['name' => 'Karim Test', 'email' => 'karim@example.test', 'company_name' => 'Test SARL', 'owner_id' => $user['id']], (int)$user['id']);
$stage = crm_fetch_one('SELECT id,probability FROM pipeline_stages ORDER BY position LIMIT 1');
crm_execute(
    'INSERT INTO opportunities(uid,name,value,currency,probability,stage_id,owner_id,contact_id,company_id,created_at,updated_at)
     VALUES(?,?,?,?,?,?,?,?,?,?,?)',
    [crm_uid(), 'Mission démo', 250000, 'DZD', $stage['probability'], $stage['id'], $user['id'], $contactId, $companyId, crm_now(), crm_now()]
);
$opportunityId = crm_last_id();
crm_execute(
    'INSERT INTO tasks(uid,title,status,priority,due_at,assigned_to,created_by,contact_id,created_at,updated_at)
     VALUES(?,?,"open","high",?,?,?,?,?,?)',
    [crm_uid(), 'Relancer Nadia', gmdate('Y-m-d H:i:s', time() + 3600), $user['id'], $user['id'], $contactId, crm_now(), crm_now()]
);
$taskId = crm_last_id();
$ids = ['lead' => $leadId, 'contact' => $contactId, 'company' => $companyId, 'opportunity' => $opportunityId, 'task' => $taskId];
if (isset($_GET['fixture_id']) && isset($ids[(string)$_GET['fixture_id']])) {
    $_GET['id'] = $ids[(string)$_GET['fixture_id']];
    unset($_GET['fixture_id']);
}
cms_start_session();
$_SESSION['authenticated'] = true;
$_SESSION['user_id'] = 1;

ob_start();
require dirname(__DIR__) . '/public/admin/index.php';
$html = (string)ob_get_clean();

if (!str_contains($html, '<!doctype html>') || !str_contains($html, 'dashboard-shell')) {
    throw new RuntimeException('Admin render failed for view: ' . $view);
}
if (preg_match('/Warning|Fatal error|Uncaught /i', $html)) {
    throw new RuntimeException('PHP error rendered for view: ' . $view);
}

echo $view . " rendered\n";
