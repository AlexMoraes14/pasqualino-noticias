<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../config/integracao.php';

header('Content-Type: application/json; charset=utf-8');
cnp_require_admin_json();

$resultado = [
    'success' => true,
    'db' => ['ok' => false, 'message' => ''],
    'wp' => ['ok' => false, 'message' => ''],
];

try {
    if (!cnp_integracao_db_is_configured()) {
        throw new RuntimeException('Banco externo nao configurado.');
    }

    $pdo = cnp_integracao_db_pdo();
    cnp_integracao_db_ensure_schema($pdo);
    $pdo->query('SELECT 1');
    $resultado['db'] = ['ok' => true, 'message' => 'Conexao com banco externo OK.'];
} catch (Throwable $e) {
    $resultado['success'] = false;
    $resultado['db'] = ['ok' => false, 'message' => $e->getMessage()];
}

try {
    if (!cnp_integracao_wp_is_configured()) {
        throw new RuntimeException('API WordPress externa nao configurada.');
    }

    $wp = cnp_integracao_testar_wp();
    $userName = trim((string) ($wp['usuario'] ?? ''));
    if ($userName === '') {
        $userName = 'usuario nao identificado';
    }

    $resultado['wp'] = ['ok' => true, 'message' => 'Conexao WordPress OK (' . $userName . ').'];
} catch (Throwable $e) {
    $resultado['success'] = false;
    $resultado['wp'] = ['ok' => false, 'message' => $e->getMessage()];
}

echo json_encode($resultado, JSON_UNESCAPED_UNICODE);

