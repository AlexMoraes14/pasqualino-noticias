<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../config/integracao.php';

header('Content-Type: application/json; charset=utf-8');
cnp_require_post_json();
cnp_require_admin_json();

$id = (int) ($_POST['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID nao informado']);
    exit;
}

try {
    if (!cnp_integracao_wp_is_configured()) {
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'message' => 'API WordPress externa nao configurada. Abra Integracoes no painel admin.',
        ]);
        exit;
    }

    $pdo = cnp_integracao_db_pdo();
    cnp_integracao_db_ensure_schema($pdo);
    $table = cnp_integracao_db_table_noticias();

    $stmt = $pdo->prepare("
        SELECT id, titulo, conteudo_final, categoria, data_fonte, status, wp_post_id, wp_status
        FROM `{$table}`
        WHERE id = :id
        LIMIT 1
    ");
    $stmt->execute([':id' => $id]);
    $registro = $stmt->fetch();

    if (!$registro) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Noticia nao encontrada']);
        exit;
    }

    $registro['wp_target_status'] = 'draft';
    $wp = cnp_integracao_wp_publicar_noticia($registro);

    $upd = $pdo->prepare("
        UPDATE `{$table}`
        SET
            status = 'published',
            wp_post_id = :wp_post_id,
            wp_post_url = :wp_post_url,
            wp_status = :wp_status,
            published_at = COALESCE(published_at, NOW()),
            updated_at = NOW()
        WHERE id = :id
        LIMIT 1
    ");
    $upd->execute([
        ':wp_post_id' => (int) ($wp['id'] ?? 0),
        ':wp_post_url' => (string) ($wp['link'] ?? ''),
        ':wp_status' => (string) ($wp['status'] ?? ''),
        ':id' => $id,
    ]);

    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
