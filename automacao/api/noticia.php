<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/integracao.php';

header('Content-Type: application/json; charset=utf-8');

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    echo json_encode([
        'status' => 'error',
        'message' => 'ID invalido',
    ]);
    exit;
}

try {
    if (!cnp_integracao_db_is_configured()) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Integracao indisponivel',
        ]);
        exit;
    }

    $pdo = cnp_integracao_db_pdo();
    cnp_integracao_db_ensure_schema($pdo);
    $table = cnp_integracao_db_table_noticias();

    $stmt = $pdo->prepare("
        SELECT id, titulo, conteudo_final, data_fonte
        FROM `{$table}`
        WHERE id = :id AND status = 'published'
        LIMIT 1
    ");
    $stmt->execute([':id' => $id]);
    $post = $stmt->fetch();

    if (!$post) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Noticia nao encontrada',
        ]);
        exit;
    }

    echo json_encode([
        'status' => 'ok',
        'titulo' => (string) ($post['titulo'] ?? ''),
        'conteudo' => cnp_integracao_formatar_conteudo_wp((string) ($post['conteudo_final'] ?? '')),
        'data' => (string) ($post['data_fonte'] ?? ''),
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
    ]);
}

