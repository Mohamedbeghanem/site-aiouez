<?php
declare(strict_types=1);

$private = sys_get_temp_dir() . '/aiouez-calendar-test-' . bin2hex(random_bytes(5));
putenv('AIOUEZ_CMS_PRIVATE_DIR=' . $private);
mkdir($private . '/submissions', 0750, true);
file_put_contents($private . '/config.php', "<?php\nreturn " . var_export([
    'admin_username' => 'admin',
    'password_hash' => password_hash('Calendar-test-2026!', PASSWORD_DEFAULT),
    'rate_secret' => bin2hex(random_bytes(16)),
    'database' => ['driver' => 'sqlite', 'path' => $private . '/crm.sqlite'],
], true) . ";\n");

require dirname(__DIR__) . '/public/api/_crm.php';

function calendar_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

try {
    crm_db();
    $user = crm_fetch_one('SELECT id FROM users ORDER BY id LIMIT 1');
    $token = bin2hex(random_bytes(24));
    crm_set_setting('calendar_feed_token', $token, (int)$user['id']);
    crm_set_setting('calendar_feed_enabled', '1', (int)$user['id']);
    crm_execute(
        'INSERT INTO tasks(uid,title,status,priority,due_at,assigned_to,created_by,created_at,updated_at)
         VALUES(?,?,"open","normal",?,?,?,?,?)',
        [crm_uid(), 'Préparer le rendez-vous Atlas', gmdate('Y-m-d H:i:s', time() + 86400), $user['id'], $user['id'], crm_now(), crm_now()]
    );
    $_GET['token'] = $token;
    ob_start();
    require dirname(__DIR__) . '/public/admin/calendar.ics.php';
    $calendar = (string)ob_get_clean();

    calendar_assert(str_contains($calendar, "BEGIN:VCALENDAR\r\n"), 'Calendar header is missing.');
    calendar_assert(str_contains($calendar, "BEGIN:VEVENT\r\n"), 'Calendar event is missing.');
    calendar_assert(str_contains($calendar, 'SUMMARY:Préparer le rendez-vous Atlas'), 'Task is missing from calendar.');
    calendar_assert(str_contains($calendar, "END:VCALENDAR\r\n"), 'Calendar footer is missing.');
    echo "Calendar feed test passed\n";
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
