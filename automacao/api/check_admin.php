<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';

header('Content-Type: application/json; charset=utf-8');

if (!cnp_is_admin_authenticated()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Nao autenticado']);
    exit;
}

echo json_encode([
    'success' => true,
    'email' => cnp_current_authenticated_email(),
    'role' => cnp_current_authenticated_role(),
]);
