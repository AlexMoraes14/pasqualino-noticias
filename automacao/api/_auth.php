<?php
declare(strict_types=1);

const CNP_ADMIN_SESSION_KEY = 'cnp_admin_email';

function cnp_admin_allowed_emails(): array
{
    return [
        'pedrobirolim@pasqualino.com.br',
        'alexsandromoraes@pasqualino.com.br',
        'murilo@pasqualino.com.br',
    ];
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

function cnp_admin_login(string $email): bool
{
    cnp_admin_start_session();
    $normalized = cnp_normalize_email($email);

    if (!in_array($normalized, cnp_admin_allowed_emails(), true)) {
        return false;
    }

    session_regenerate_id(true);
    $_SESSION[CNP_ADMIN_SESSION_KEY] = $normalized;
    return true;
}

function cnp_is_admin_authenticated(): bool
{
    cnp_admin_start_session();

    if (empty($_SESSION[CNP_ADMIN_SESSION_KEY])) {
        return false;
    }

    return in_array($_SESSION[CNP_ADMIN_SESSION_KEY], cnp_admin_allowed_emails(), true);
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
