<?php
require_once __DIR__ . '/../pipeline.php';

header('Content-Type: application/json');

$resultado = runPipeline();
echo json_encode($resultado);
