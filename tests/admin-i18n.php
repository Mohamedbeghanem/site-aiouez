<?php
declare(strict_types=1);

$private = sys_get_temp_dir() . '/aiouez-i18n-test-' . bin2hex(random_bytes(5));
putenv('AIOUEZ_CMS_PRIVATE_DIR=' . $private);
mkdir($private . '/submissions', 0750, true);
mkdir($private . '/sessions', 0750, true);
ini_set('session.save_path', $private . '/sessions');
file_put_contents($private . '/config.php', "<?php\nreturn " . var_export([
    'admin_username' => 'admin',
    'password_hash' => password_hash('I18n-test-2026!', PASSWORD_DEFAULT),
    'rate_secret' => bin2hex(random_bytes(16)),
    'database' => ['driver' => 'sqlite', 'path' => $private . '/crm.sqlite'],
], true) . ";\n");

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTPS'] = 'on';
$_GET = ['view' => 'dashboard', 'lang' => 'ar'];

require dirname(__DIR__) . '/public/api/_crm.php';
crm_db();
cms_start_session();
$_SESSION['authenticated'] = true;
$_SESSION['user_id'] = 1;

function i18n_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

try {
    ob_start();
    require dirname(__DIR__) . '/public/admin/index.php';
    $html = (string)ob_get_clean();

    i18n_assert(admin_locale() === 'ar', 'Arabic locale was not selected.');
    i18n_assert(admin_direction() === 'rtl', 'Arabic direction must be RTL.');
    i18n_assert(admin_t('Réglages') === 'الإعدادات', 'Arabic settings translation is missing.');
    i18n_assert(str_contains($html, '<html lang="ar" dir="rtl">'), 'Rendered document locale or direction is incorrect.');
    i18n_assert(str_contains($html, '<title>نظرة عامة ·'), 'Rendered Arabic page title is missing.');
    i18n_assert(str_contains($html, '🇩🇿'), 'Algerian flag is missing.');
    i18n_assert(str_contains($html, '🇫🇷'), 'French flag is missing.');
    i18n_assert(str_contains($html, '🇬🇧'), 'English flag is missing.');
    i18n_assert(str_contains($html, '/admin/admin-i18n.js?v='), 'Localization runtime is missing.');
    i18n_assert(str_contains(admin_money(3000000), 'dir="ltr">3 000 000 DZD'), 'RTL-safe currency formatting failed.');
    i18n_assert(str_contains(admin_date('2026-08-01 12:00:00', 'd M'), 'أوت'), 'Arabic month localization failed.');

    echo "Admin i18n test passed\n";
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
