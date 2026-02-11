<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../wordpress/wp-load.php';

header('Content-Type: application/json; charset=utf-8');
cnp_require_admin_json();

$args = [
    'post_type' => 'post',
    'post_status' => 'publish',
    'posts_per_page' => 50,
    'meta_query' => [
        [
            'key' => '_conteudo_ia',
            'value' => 'sim',
        ],
    ],
];

$query = new WP_Query($args);
$saida = [];

foreach ($query->posts as $post) {
    $textoLimpo = wp_strip_all_tags($post->post_content, true);

    $saida[] = [
        'id' => $post->ID,
        'titulo' => $post->post_title,
        'texto' => trim($textoLimpo),
        'data' => get_the_date('d/m/Y', $post->ID),
    ];
}

echo json_encode($saida);
