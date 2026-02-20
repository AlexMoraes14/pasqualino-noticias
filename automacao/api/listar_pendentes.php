<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../config/integracao.php';

header('Content-Type: application/json; charset=utf-8');
cnp_require_admin_json();

try {
    if (!cnp_integracao_db_is_configured()) {
        http_response_code(503);
        echo json_encode([
            'success' => false,
            'message' => 'Banco externo nao configurado. Abra Integracoes no painel admin.',
        ]);
        exit;
    }

    $pdo = cnp_integracao_db_pdo();
    cnp_integracao_db_ensure_schema($pdo);
    $table = cnp_integracao_db_table_noticias();

    $stmt = $pdo->prepare("
        SELECT id, titulo, conteudo_final, data_fonte
        FROM `{$table}`
        WHERE status = 'pending'
        ORDER BY STR_TO_DATE(data_fonte, '%d/%m/%Y') DESC, updated_at DESC
        LIMIT 200
    ");
    $stmt->execute();
    $rows = $stmt->fetchAll();

    $saida = [];
    foreach ($rows as $row) {
        $conteudoHtml = (string) ($row['conteudo_final'] ?? '');
        $textoLimpo = trim((string) preg_replace('/\s+/u', ' ', strip_tags($conteudoHtml)));

        $saida[] = [
            'id' => (int) ($row['id'] ?? 0),
            'titulo' => (string) ($row['titulo'] ?? ''),
            'texto' => $textoLimpo,
            'conteudo_html' => $conteudoHtml,
            'data' => (string) ($row['data_fonte'] ?? ''),
        ];
    }

    echo json_encode($saida, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}

