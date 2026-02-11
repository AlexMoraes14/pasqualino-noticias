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
    echo json_encode(['success' => false, 'message' => 'ID nao informado']);
    exit;
}

$resultado = wp_update_post([
    'ID' => $id,
    'post_status' => 'publish',
], true);

if (is_wp_error($resultado)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $resultado->get_error_message()]);
    exit;
}

echo json_encode(['success' => true]);
