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

/**
 * Carrega variaveis de ambiente de um arquivo .env local
 * sem sobrescrever valores ja definidos no ambiente do servidor.
 */
if (!function_exists('cnp_load_env_file')) {
    function cnp_load_env_file(string $path): void
    {
        if (!is_file($path) || !is_readable($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES);
        if (!is_array($lines)) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $eqPos = strpos($line, '=');
            if ($eqPos === false || $eqPos <= 0) {
                continue;
            }

            $key = trim(substr($line, 0, $eqPos));
            $value = trim(substr($line, $eqPos + 1));

            if ($key === '' || getenv($key) !== false) {
                continue;
            }

            if (
                (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                (str_starts_with($value, "'") && str_ends_with($value, "'"))
            ) {
                $value = substr($value, 1, -1);
            }

            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}

cnp_load_env_file(BASE_PATH . '/.env');

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

if (!defined('IA_DISABLE_SSL_VERIFY')) {
    define('IA_DISABLE_SSL_VERIFY', getenv('IA_DISABLE_SSL_VERIFY') === '1');
}

if (!defined('WP_AUTHOR_ID')) {
    define('WP_AUTHOR_ID', max(1, (int) (getenv('WP_AUTHOR_ID') ?: 1)));
}

if (!defined('WP_DEFAULT_CATEGORY')) {
    define('WP_DEFAULT_CATEGORY', max(1, (int) (getenv('WP_DEFAULT_CATEGORY') ?: 3)));
}

