<?php
declare(strict_types=1);

const CNP_ADMIN_SESSION_KEY = 'cnp_admin_email';
const CNP_AUTH_ROLE_SESSION_KEY = 'cnp_auth_role';

function cnp_admin_allowed_emails(): array
{
    return [
        'pedrobirolim@pasqualino.com.br',
        'alexsandromoraes@pasqualino.com.br',
        'murilo@pasqualino.com.br',
        'lucas@pasqualino.com.br',
    ];
}

function cnp_editor_allowed_emails(): array
{
    // Reservado para futura permissao de editor.
    return [];
}

function cnp_admin_start_session(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
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

function cnp_admin_login(string $email): bool
{
    cnp_admin_start_session();
    $normalized = cnp_normalize_email($email);
    $role = cnp_role_for_email($normalized);

    if ($role !== 'admin') {
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
    return cnp_current_authenticated_role() === 'admin';
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
