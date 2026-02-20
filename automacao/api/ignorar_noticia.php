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
    $pdo = cnp_integracao_db_pdo();
    cnp_integracao_db_ensure_schema($pdo);
    $table = cnp_integracao_db_table_noticias();

    $stmt = $pdo->prepare("
        UPDATE `{$table}`
        SET status = 'ignored', updated_at = NOW()
        WHERE id = :id
        LIMIT 1
    ");
    $stmt->execute([':id' => $id]);

    if ($stmt->rowCount() < 1) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Noticia nao encontrada']);
        exit;
    }

    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

