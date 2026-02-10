<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../wordpress/wp-load.php';

$id = intval($_GET['id'] ?? 0);

if (!$id) {
    echo json_encode([
        'status' => 'error',
        'message' => 'ID inválido'
    ]);
    exit;
}

$post = get_post($id);

if (!$post || $post->post_status !== 'publish') {
    echo json_encode([
        'status' => 'error',
        'message' => 'Notícia não encontrada'
    ]);
    exit;
}

echo json_encode([
    'status'   => 'ok',
    'titulo'   => $post->post_title,
    'conteudo' => apply_filters('the_content', $post->post_content),
    'data'     => get_the_date('d/m/Y', $post)
]);
exit;
