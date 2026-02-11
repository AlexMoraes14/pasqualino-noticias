<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';

header('Content-Type: application/json; charset=utf-8');
cnp_require_post_json();

$email = cnp_normalize_email($_POST['email'] ?? '');

if ($email === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Email obrigatorio']);
    exit;
}

if (!cnp_admin_login($email)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Usuario sem permissao de admin']);
    exit;
}

echo json_encode(['success' => true]);
