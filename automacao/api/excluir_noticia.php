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
    echo json_encode(['success' => false, 'message' => 'ID nao enviado']);
    exit;
}

try {
    $pdo = cnp_integracao_db_pdo();
    cnp_integracao_db_ensure_schema($pdo);
    $table = cnp_integracao_db_table_noticias();

    $sel = $pdo->prepare("
        SELECT id, wp_post_id
        FROM `{$table}`
        WHERE id = :id
        LIMIT 1
    ");
    $sel->execute([':id' => $id]);
    $registro = $sel->fetch();

    if (!$registro) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Noticia nao encontrada']);
        exit;
    }

    $wpPostId = (int) ($registro['wp_post_id'] ?? 0);
    if ($wpPostId > 0 && cnp_integracao_wp_is_configured()) {
        cnp_integracao_wp_excluir_post($wpPostId);
    }

    $upd = $pdo->prepare("
        UPDATE `{$table}`
        SET status = 'deleted', updated_at = NOW()
        WHERE id = :id
        LIMIT 1
    ");
    $upd->execute([':id' => $id]);

    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

