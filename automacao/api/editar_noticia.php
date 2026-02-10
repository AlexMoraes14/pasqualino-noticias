<?php
require_once __DIR__ . '/../../wordpress/wp-load.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

$id     = intval($_POST['id'] ?? 0);
$titulo = trim($_POST['titulo'] ?? '');
$texto  = trim($_POST['texto'] ?? '');

if (!$id || !$titulo || !$texto) {
    http_response_code(400);
    echo json_encode(['status' => 'error']);
    exit;
}

wp_update_post([
    'ID'           => $id,
    'post_title'   => $titulo,
    'post_content' => $texto,
    'post_status'  => 'pending',
]);

echo json_encode(['status' => 'ok']);
