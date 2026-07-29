<?php
declare(strict_types=1);

const CMS_STATUSES = [
    'new' => 'Nouveau',
    'in_progress' => 'En cours',
    'contacted' => 'Contacté',
    'closed' => 'Clôturé',
    'archived' => 'Archivé',
];

function cms_private_dir(): string
{
    $override = getenv('AIOUEZ_CMS_PRIVATE_DIR');
    if (is_string($override) && $override !== '') {
        return rtrim($override, '/');
    }

    return dirname(__DIR__, 2) . '/private/cms';
}

function cms_bootstrap_storage(): void
{
    $base = cms_private_dir();
    foreach ([$base, "$base/submissions", "$base/rate-limits"] as $directory) {
        if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
            throw new RuntimeException('Impossible d’initialiser le stockage sécurisé.');
        }
    }
}

function cms_config(): array
{
    $path = cms_private_dir() . '/config.php';
    if (!is_file($path)) {
        return [];
    }

    $config = require $path;
    return is_array($config) ? $config : [];
}

function cms_security_headers(): void
{
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: same-origin');
    header("Permissions-Policy: camera=(), microphone=(), geolocation=()");
}

function cms_json_response(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function cms_request_ip(): string
{
    return (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
}

function cms_rate_limit(string $bucket, int $maximum, int $windowSeconds): bool
{
    cms_bootstrap_storage();
    $config = cms_config();
    $secret = (string)($config['rate_secret'] ?? 'aiouez-rate-limit');
    $key = hash_hmac('sha256', "$bucket:" . cms_request_ip(), $secret);
    $path = cms_private_dir() . "/rate-limits/$key.json";
    $now = time();
    $handle = fopen($path, 'c+');
    if ($handle === false || !flock($handle, LOCK_EX)) {
        return false;
    }

    $raw = stream_get_contents($handle);
    $events = is_string($raw) && $raw !== '' ? json_decode($raw, true) : [];
    $events = is_array($events) ? array_values(array_filter(
        $events,
        static fn ($timestamp): bool => is_int($timestamp) && $timestamp > $now - $windowSeconds
    )) : [];

    $allowed = count($events) < $maximum;
    if ($allowed) {
        $events[] = $now;
    }

    ftruncate($handle, 0);
    rewind($handle);
    fwrite($handle, json_encode($events));
    fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);

    return $allowed;
}

function cms_clean_text(mixed $value, int $maximum): string
{
    $text = trim((string)$value);
    $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text) ?? '';
    return mb_substr($text, 0, $maximum);
}

function cms_submission_path(string $id): string
{
    if (!preg_match('/^[a-f0-9]{24}$/', $id)) {
        throw new InvalidArgumentException('Identifiant invalide.');
    }

    return cms_private_dir() . "/submissions/$id.json";
}

function cms_save_submission(array $submission): void
{
    cms_bootstrap_storage();
    $path = cms_submission_path((string)$submission['id']);
    $temporary = "$path.tmp-" . bin2hex(random_bytes(4));
    $json = json_encode(
        $submission,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR
    );
    if (file_put_contents($temporary, $json, LOCK_EX) === false || !rename($temporary, $path)) {
        @unlink($temporary);
        throw new RuntimeException('Impossible d’enregistrer la demande.');
    }
    @chmod($path, 0640);
}

function cms_get_submission(string $id): ?array
{
    try {
        $path = cms_submission_path($id);
    } catch (InvalidArgumentException) {
        return null;
    }
    if (!is_file($path)) {
        return null;
    }
    $item = json_decode((string)file_get_contents($path), true);
    return is_array($item) ? $item : null;
}

function cms_list_submissions(): array
{
    cms_bootstrap_storage();
    $items = [];
    foreach (glob(cms_private_dir() . '/submissions/*.json') ?: [] as $path) {
        $item = json_decode((string)file_get_contents($path), true);
        if (is_array($item) && isset($item['id'], $item['created_at'])) {
            $items[] = $item;
        }
    }
    usort($items, static fn (array $a, array $b): int =>
        strcmp((string)$b['created_at'], (string)$a['created_at'])
    );
    return $items;
}

function cms_start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_name('aiouez_admin');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/admin',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

function cms_csrf_token(): string
{
    cms_start_session();
    if (!isset($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(24));
    }
    return (string)$_SESSION['csrf'];
}

function cms_verify_csrf(): void
{
    cms_start_session();
    $submitted = (string)($_POST['csrf'] ?? '');
    if ($submitted === '' || !hash_equals((string)($_SESSION['csrf'] ?? ''), $submitted)) {
        http_response_code(419);
        exit('La session a expiré. Rechargez la page et réessayez.');
    }
}

function cms_is_authenticated(): bool
{
    cms_start_session();
    return ($_SESSION['authenticated'] ?? false) === true;
}

function cms_password_hash(): string
{
    $override = cms_private_dir() . '/password.hash';
    if (is_file($override)) {
        return trim((string)file_get_contents($override));
    }
    return (string)(cms_config()['password_hash'] ?? '');
}

function cms_admin_username(): string
{
    return (string)(cms_config()['username'] ?? 'admin');
}

function cms_require_auth(): void
{
    if (!cms_is_authenticated()) {
        header('Location: /admin/');
        exit;
    }
}

function cms_flash(string $type, string $message): void
{
    cms_start_session();
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function cms_take_flash(): ?array
{
    cms_start_session();
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return is_array($flash) ? $flash : null;
}

cms_security_headers();
