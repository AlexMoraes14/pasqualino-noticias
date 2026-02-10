<?php

// CONFIG GLOBAL DO PIPELINE

date_default_timezone_set('America/Sao_Paulo');

// ========= PATHS =========
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}

// ========= lOG =========
if (!defined('BASE_DIR')) {
    define('BASE_DIR', dirname(__DIR__));
}

if (!defined('LOG_DIR')) {
    define('LOG_DIR', BASE_PATH . '/logs');
}

// ========= IA =========
if (!defined('IA_PROVIDER')) {
    define('IA_PROVIDER', getenv('IA_PROVIDER') ?: 'huggingface');
}

if (!defined('OPENAI_API_KEY')) {
    define('OPENAI_API_KEY', getenv('OPENAI_API_KEY') ?: '');
}

if (!defined('HF_API_KEY')) {
    define('HF_API_KEY', getenv('HF_API_KEY') ?: '');
}


