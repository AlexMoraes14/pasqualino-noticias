<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../config/integracao.php';

header('Content-Type: application/json; charset=utf-8');
cnp_require_post_json();
cnp_require_admin_json();

$id = (int) ($_POST['id'] ?? 0);
$titulo = trim((string) ($_POST['titulo'] ?? ''));
$texto = trim((string) ($_POST['texto'] ?? ''));

if ($id <= 0 || $titulo === '' || $texto === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Dados invalidos']);
    exit;
}

try {
    $pdo = cnp_integracao_db_pdo();
    cnp_integracao_db_ensure_schema($pdo);
    $table = cnp_integracao_db_table_noticias();

    $sel = $pdo->prepare("
        SELECT id, status, wp_post_id, wp_post_url, wp_status, categoria, data_fonte
        FROM `{$table}`
        WHERE id = :id
        LIMIT 1
    ");
    $sel->execute([':id' => $id]);
    $row = $sel->fetch();

    if (!$row) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Noticia nao encontrada']);
        exit;
    }

    $upd = $pdo->prepare("
        UPDATE `{$table}`
        SET titulo = :titulo, conteudo_final = :conteudo_final, updated_at = NOW()
        WHERE id = :id
        LIMIT 1
    ");
    $upd->execute([
        ':titulo' => $titulo,
        ':conteudo_final' => $texto,
        ':id' => $id,
    ]);

    $status = trim((string) ($row['status'] ?? ''));
    if ($status === 'published') {
        if (!cnp_integracao_wp_is_configured()) {
            http_response_code(422);
            echo json_encode([
                'success' => false,
                'message' => 'API WordPress externa nao configurada para sincronizar edicao de noticia publicada.',
            ]);
            exit;
        }

        $registro = [
            'id' => $id,
            'titulo' => $titulo,
            'conteudo_final' => $texto,
            'categoria' => (string) ($row['categoria'] ?? 'federal'),
            'data_fonte' => (string) ($row['data_fonte'] ?? ''),
            'wp_post_id' => (int) ($row['wp_post_id'] ?? 0),
            'wp_target_status' => (string) ($row['wp_status'] ?? 'publish'),
        ];

        $wp = cnp_integracao_wp_publicar_noticia($registro);
        $sync = $pdo->prepare("
            UPDATE `{$table}`
            SET wp_post_id = :wp_post_id, wp_post_url = :wp_post_url, wp_status = :wp_status, updated_at = NOW()
            WHERE id = :id
            LIMIT 1
        ");
        $sync->execute([
            ':wp_post_id' => (int) ($wp['id'] ?? 0),
            ':wp_post_url' => (string) ($wp['link'] ?? ''),
            ':wp_status' => (string) ($wp['status'] ?? ''),
            ':id' => $id,
        ]);
    }

    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
