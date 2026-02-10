<?php
require_once __DIR__ . '/../../wordpress/wp-load.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_POST['id'])) {
    echo json_encode(['erro' => 'ID não informado']);
    exit;
}

$id = intval($_POST['id']);

// muda status para publicado
$resultado = wp_update_post([
    'ID'          => $id,
    'post_status'=> 'publish'
], true);

if (is_wp_error($resultado)) {
    echo json_encode(['erro' => $resultado->get_error_message()]);
    exit;
}

echo json_encode(['sucesso' => true]);
exit;
