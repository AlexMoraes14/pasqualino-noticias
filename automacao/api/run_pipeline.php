<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../pipeline.php';

header('Content-Type: application/json; charset=utf-8');
@ini_set('max_execution_time', '0');
@set_time_limit(0);
cnp_require_admin_json();

// Libera lock da sessao antes da execucao longa do pipeline
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

$resultado = runPipeline();
echo json_encode($resultado);
