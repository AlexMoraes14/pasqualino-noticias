<?php
require_once __DIR__ . '/../../wordpress/wp-load.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_POST['id'])) {
    echo json_encode(['erro' => 'ID não informado']);
    exit;
}

$id = intval($_POST['id']);

// joga no lixo
$resultado = wp_trash_post($id);

if (!$resultado) {
    echo json_encode(['erro' => 'Não foi possível ignorar a notícia']);
    exit;
}

echo json_encode(['sucesso' => true]);
exit;
