<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../pipeline.php';

header('Content-Type: application/json; charset=utf-8');
cnp_require_admin_json();

$resultado = runPipeline();
echo json_encode($resultado);
