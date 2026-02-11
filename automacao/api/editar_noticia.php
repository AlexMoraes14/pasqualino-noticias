<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../wordpress/wp-load.php';

header('Content-Type: application/json; charset=utf-8');
cnp_require_post_json();
cnp_require_admin_json();

$id = intval($_POST['id'] ?? 0);
$titulo = sanitize_text_field($_POST['titulo'] ?? '');
$texto = wp_kses_post($_POST['texto'] ?? '');

if (!$id || !$titulo || !$texto) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Dados invalidos']);
    exit;
}

$resultado = wp_update_post([
    'ID' => $id,
    'post_title' => $titulo,
    'post_content' => $texto,
], true);

if (is_wp_error($resultado)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $resultado->get_error_message()]);
    exit;
}

echo json_encode(['success' => true]);
