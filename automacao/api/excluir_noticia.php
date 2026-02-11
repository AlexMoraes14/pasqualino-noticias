<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../wordpress/wp-load.php';

header('Content-Type: application/json; charset=utf-8');
cnp_require_post_json();
cnp_require_admin_json();

$id = intval($_POST['id'] ?? 0);
if (!$id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID nao enviado']);
    exit;
}

$resultado = wp_delete_post($id, true);

if (!$resultado) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao excluir']);
    exit;
}

echo json_encode(['success' => true]);
