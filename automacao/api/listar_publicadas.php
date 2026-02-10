<?php
require_once __DIR__ . '/../../wordpress/wp-load.php';

header('Content-Type: application/json; charset=utf-8');

$args = [
    'post_type'      => 'post',
    'post_status'    => 'publish',
    'posts_per_page' => 50,
    'meta_query'     => [
        [
            'key'   => '_conteudo_ia',
            'value' => 'sim'
        ]
    ]
];

$query = new WP_Query($args);

$saida = [];

foreach ($query->posts as $post) {

    // remove QUALQUER HTML para o editor humano
    $textoLimpo = wp_strip_all_tags($post->post_content, true);

    $saida[] = [
        'id'     => $post->ID,
        'titulo' => $post->post_title,
        'texto'  => trim($textoLimpo),
        'data'   => get_the_date('d/m/Y', $post->ID),
    ];
}

echo json_encode($saida);
exit;
