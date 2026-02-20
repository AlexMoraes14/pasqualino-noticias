<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../config/integracao.php';

header('Content-Type: application/json; charset=utf-8');
cnp_require_post_json();
cnp_require_admin_json();

try {
    $input = [
        'wp_base_url' => (string) ($_POST['wp_base_url'] ?? ''),
        'wp_rest_user' => (string) ($_POST['wp_rest_user'] ?? ''),
        'wp_app_password' => (string) ($_POST['wp_app_password'] ?? ''),
        'wp_author_id' => (string) ($_POST['wp_author_id'] ?? ''),
        'wp_category_federal' => (string) ($_POST['wp_category_federal'] ?? ''),
        'wp_category_trabalhista' => (string) ($_POST['wp_category_trabalhista'] ?? ''),
        'wp_category_comex' => (string) ($_POST['wp_category_comex'] ?? ''),
        'wp_timeout' => (string) ($_POST['wp_timeout'] ?? ''),
        'db_host' => (string) ($_POST['db_host'] ?? ''),
        'db_port' => (string) ($_POST['db_port'] ?? ''),
        'db_name' => (string) ($_POST['db_name'] ?? ''),
        'db_user' => (string) ($_POST['db_user'] ?? ''),
        'db_password' => (string) ($_POST['db_password'] ?? ''),
        'db_charset' => (string) ($_POST['db_charset'] ?? ''),
        'db_table_noticias' => (string) ($_POST['db_table_noticias'] ?? ''),
        'db_table_execucoes' => (string) ($_POST['db_table_execucoes'] ?? ''),
    ];

    $cfg = cnp_integracao_save($input);

    echo json_encode([
        'success' => true,
        'message' => 'Configuracoes salvas com sucesso.',
        'config' => cnp_integracao_public_view($cfg),
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}

