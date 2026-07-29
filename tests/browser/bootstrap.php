<?php
declare(strict_types=1);

$private = getenv('AIOUEZ_CMS_PRIVATE_DIR');
if (!is_string($private) || $private === '') {
    return;
}

if (str_starts_with((string)($_SERVER['REQUEST_URI'] ?? ''), '/admin/')) {
    session_save_path($private . '/sessions');
    session_name('aiouez_admin');
    session_start();
    $_SESSION['authenticated'] = true;
    $_SESSION['user_id'] = 1;
}
