<?php
declare(strict_types=1);

const CNP_ADMIN_SESSION_KEY = 'cnp_admin_email';
const CNP_AUTH_ROLE_SESSION_KEY = 'cnp_auth_role';
const CNP_LAST_ACTIVITY_SESSION_KEY = 'cnp_last_activity';
const CNP_AUTH_ENV_PATH = __DIR__ . '/../.env';
const CNP_DEFAULT_SESSION_TTL = 7200;
const CNP_MIN_SESSION_TTL = 300;

function cnp_env_values(): array
{
    static $cache = null;

    if (is_array($cache)) {
        return $cache;
    }

    $cache = [];
    $path = CNP_AUTH_ENV_PATH;

    if (!is_file($path) || !is_readable($path)) {
        return $cache;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!is_array($lines)) {
        return $cache;
    }

    foreach ($lines as $line) {
        $line = trim((string) $line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        $eqPos = strpos($line, '=');
        if ($eqPos === false || $eqPos <= 0) {
            continue;
        }

        $key = trim(substr($line, 0, $eqPos));
        $value = trim(substr($line, $eqPos + 1));

        if ($key === '') {
            continue;
        }

        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
            (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            $value = substr($value, 1, -1);
        }

        $cache[$key] = $value;
    }

    return $cache;
}

function cnp_env(string $key, string $default = ''): string
{
    $envValue = getenv($key);
    if ($envValue !== false && trim((string) $envValue) !== '') {
        return trim((string) $envValue);
    }

    $values = cnp_env_values();
    if (isset($values[$key]) && trim((string) $values[$key]) !== '') {
        return trim((string) $values[$key]);
    }

    return $default;
}

function cnp_parse_email_list(string $raw): array
{
    if ($raw === '') {
        return [];
    }

    $parts = preg_split('/[\s,;]+/', $raw, -1, PREG_SPLIT_NO_EMPTY);
    if (!is_array($parts)) {
        return [];
    }

    $emails = [];
    foreach ($parts as $value) {
        $normalized = cnp_normalize_email($value);
        if ($normalized === '') {
            continue;
        }

        if (!filter_var($normalized, FILTER_VALIDATE_EMAIL)) {
            continue;
        }

        $emails[] = $normalized;
    }

    return array_values(array_unique($emails));
}

function cnp_admin_allowed_emails(): array
{
    return cnp_parse_email_list(cnp_env('CNP_ADMIN_ALLOWED_EMAILS', ''));
}

function cnp_editor_allowed_emails(): array
{
    return cnp_parse_email_list(cnp_env('CNP_EDITOR_ALLOWED_EMAILS', ''));
}

function cnp_has_panel_access_role(?string $role): bool
{
    return in_array((string) $role, ['admin', 'editor'], true);
}

function cnp_panel_password_hash(): string
{
    return cnp_env('CNP_PANEL_PASSWORD_HASH', '');
}

function cnp_is_panel_password_configured(): bool
{
    return cnp_panel_password_hash() !== '';
}

function cnp_verify_panel_password(string $password): bool
{
    $password = (string) $password;
    if ($password === '') {
        return false;
    }

    $hash = cnp_panel_password_hash();
    if ($hash !== '') {
        return password_verify($password, $hash);
    }

    return false;
}

function cnp_is_https_request(): bool
{
    $https = strtolower((string) ($_SERVER['HTTPS'] ?? ''));
    if ($https !== '' && $https !== 'off') {
        return true;
    }

    if ((string) ($_SERVER['SERVER_PORT'] ?? '') === '443') {
        return true;
    }

    $forwardedProto = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
    return str_contains($forwardedProto, 'https');
}

function cnp_session_timeout_seconds(): int
{
    $rawTtl = (int) cnp_env('CNP_SESSION_TTL', (string) CNP_DEFAULT_SESSION_TTL);
    if ($rawTtl < CNP_MIN_SESSION_TTL) {
        return CNP_MIN_SESSION_TTL;
    }

    return $rawTtl;
}

function cnp_clear_auth_session(): void
{
    unset(
        $_SESSION[CNP_ADMIN_SESSION_KEY],
        $_SESSION[CNP_AUTH_ROLE_SESSION_KEY],
        $_SESSION[CNP_LAST_ACTIVITY_SESSION_KEY]
    );

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
}

function cnp_enforce_session_timeout(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }

    $now = time();
    $lastActivity = (int) ($_SESSION[CNP_LAST_ACTIVITY_SESSION_KEY] ?? 0);

    if ($lastActivity > 0 && ($now - $lastActivity) > cnp_session_timeout_seconds()) {
        cnp_clear_auth_session();
    }

    $_SESSION[CNP_LAST_ACTIVITY_SESSION_KEY] = $now;
}

function cnp_admin_start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        cnp_enforce_session_timeout();
        return;
    }

    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_strict_mode', '1');

    session_name('CNPSESSID');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => cnp_is_https_request(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
    cnp_enforce_session_timeout();
}

function cnp_normalize_email(?string $email): string
{
    return strtolower(trim((string) $email));
}

function cnp_role_for_email(string $email): ?string
{
    $normalized = cnp_normalize_email($email);

    if (in_array($normalized, cnp_admin_allowed_emails(), true)) {
        return 'admin';
    }

    if (in_array($normalized, cnp_editor_allowed_emails(), true)) {
        return 'editor';
    }

    return null;
}

function cnp_admin_login(string $email, string $password): bool
{
    cnp_admin_start_session();
    $normalized = cnp_normalize_email($email);
    $role = cnp_role_for_email($normalized);

    if (!cnp_has_panel_access_role($role)) {
        return false;
    }

    if (!cnp_verify_panel_password($password)) {
        return false;
    }

    session_regenerate_id(true);
    $_SESSION[CNP_ADMIN_SESSION_KEY] = $normalized;
    $_SESSION[CNP_AUTH_ROLE_SESSION_KEY] = $role;
    return true;
}

function cnp_current_authenticated_email(): ?string
{
    cnp_admin_start_session();

    if (empty($_SESSION[CNP_ADMIN_SESSION_KEY])) {
        return null;
    }

    $email = cnp_normalize_email((string) $_SESSION[CNP_ADMIN_SESSION_KEY]);
    return $email !== '' ? $email : null;
}

function cnp_current_authenticated_role(): ?string
{
    cnp_admin_start_session();

    $email = cnp_current_authenticated_email();
    if ($email === null) {
        return null;
    }

    $role = cnp_role_for_email($email);
    if ($role === null) {
        return null;
    }

    $_SESSION[CNP_AUTH_ROLE_SESSION_KEY] = $role;
    return $role;
}

function cnp_is_admin_authenticated(): bool
{
    return cnp_has_panel_access_role(cnp_current_authenticated_role());
}

function cnp_require_post_json(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        http_response_code(405);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Metodo nao permitido']);
        exit;
    }
}

function cnp_require_admin_json(): void
{
    if (!cnp_is_admin_authenticated()) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Sem permissao']);
        exit;
    }
}
