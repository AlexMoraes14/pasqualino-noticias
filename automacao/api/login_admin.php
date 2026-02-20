<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';

header('Content-Type: application/json; charset=utf-8');
cnp_require_post_json();

$email = cnp_normalize_email($_POST['email'] ?? '');
$password = (string) ($_POST['password'] ?? '');

if ($email === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Email obrigatorio']);
    exit;
}

if ($password === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Senha obrigatoria']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/@pasqualino\.com\.br$/', $email)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Email corporativo invalido']);
    exit;
}

if (!cnp_is_panel_password_configured()) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Senha admin nao configurada']);
    exit;
}

if (!cnp_has_panel_access_role(cnp_role_for_email($email))) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Email sem permissao']);
    exit;
}

if (!cnp_admin_login($email, $password)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Senha incorreta']);
    exit;
}

echo json_encode([
    'success' => true,
    'email' => cnp_current_authenticated_email(),
    'role' => cnp_current_authenticated_role(),
]);
