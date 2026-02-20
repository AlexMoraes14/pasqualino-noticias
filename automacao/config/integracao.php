<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

const CNP_INTEGRACAO_RUNTIME_FILE = BASE_PATH . '/config/integracao.runtime.json';

/**
 * Configuracoes de integracao carregadas com prioridade:
 * 1) arquivo runtime (painel admin)
 * 2) variaveis de ambiente
 */
function cnp_integracao_defaults(): array
{
    return [
        'wp_base_url' => trim((string) (getenv('WP_EXT_BASE_URL') ?: '')),
        'wp_rest_user' => trim((string) (getenv('WP_EXT_USER') ?: '')),
        'wp_app_password' => (string) (getenv('WP_EXT_APP_PASSWORD') ?: ''),
        'wp_author_id' => max(0, (int) (getenv('WP_EXT_AUTHOR_ID') ?: 0)),
        'wp_category_federal' => max(0, (int) (getenv('WP_EXT_CATEGORY_FEDERAL') ?: 0)),
        'wp_category_trabalhista' => max(0, (int) (getenv('WP_EXT_CATEGORY_TRABALHISTA') ?: 0)),
        'wp_category_comex' => max(0, (int) (getenv('WP_EXT_CATEGORY_COMEX') ?: 0)),
        'wp_timeout' => max(10, (int) (getenv('WP_EXT_TIMEOUT') ?: 20)),
        'db_host' => trim((string) (getenv('DB_EXT_HOST') ?: '')),
        'db_port' => max(1, (int) (getenv('DB_EXT_PORT') ?: 3306)),
        'db_name' => trim((string) (getenv('DB_EXT_NAME') ?: '')),
        'db_user' => trim((string) (getenv('DB_EXT_USER') ?: '')),
        'db_password' => (string) (getenv('DB_EXT_PASSWORD') ?: ''),
        'db_charset' => trim((string) (getenv('DB_EXT_CHARSET') ?: 'utf8mb4')),
        'db_table_noticias' => trim((string) (getenv('DB_EXT_TABLE_NOTICIAS') ?: 'cnp_noticias')),
        'db_table_execucoes' => trim((string) (getenv('DB_EXT_TABLE_EXECUCOES') ?: 'cnp_pipeline_execucoes')),
    ];
}

function cnp_integracao_safe_identifier(string $value, string $fallback): string
{
    $value = trim($value);
    if ($value === '' || preg_match('/^[a-zA-Z0-9_]+$/', $value) !== 1) {
        return $fallback;
    }

    return $value;
}

function cnp_integracao_sanitize(array $config): array
{
    $defaults = cnp_integracao_defaults();
    $out = array_merge($defaults, $config);

    $out['wp_base_url'] = rtrim(trim((string) $out['wp_base_url']), '/');
    $out['wp_rest_user'] = trim((string) $out['wp_rest_user']);
    $out['wp_app_password'] = trim((string) $out['wp_app_password']);
    $out['wp_author_id'] = max(0, (int) $out['wp_author_id']);
    $out['wp_category_federal'] = max(0, (int) $out['wp_category_federal']);
    $out['wp_category_trabalhista'] = max(0, (int) $out['wp_category_trabalhista']);
    $out['wp_category_comex'] = max(0, (int) $out['wp_category_comex']);
    $wpTimeout = (int) $out['wp_timeout'];
    if ($wpTimeout <= 0) {
        $wpTimeout = (int) ($defaults['wp_timeout'] ?? 20);
    }
    $out['wp_timeout'] = max(10, $wpTimeout);

    $out['db_host'] = trim((string) $out['db_host']);
    $dbPort = (int) $out['db_port'];
    if ($dbPort <= 0) {
        $dbPort = (int) ($defaults['db_port'] ?? 3306);
    }
    $out['db_port'] = max(1, $dbPort);
    $out['db_name'] = trim((string) $out['db_name']);
    $out['db_user'] = trim((string) $out['db_user']);
    $out['db_password'] = (string) $out['db_password'];
    $out['db_charset'] = trim((string) $out['db_charset']);
    if ($out['db_charset'] === '') {
        $out['db_charset'] = 'utf8mb4';
    }

    $out['db_table_noticias'] = cnp_integracao_safe_identifier((string) $out['db_table_noticias'], 'cnp_noticias');
    $out['db_table_execucoes'] = cnp_integracao_safe_identifier((string) $out['db_table_execucoes'], 'cnp_pipeline_execucoes');

    return $out;
}

function cnp_integracao_load(): array
{
    if (isset($GLOBALS['cnp_integracao_cache']) && is_array($GLOBALS['cnp_integracao_cache'])) {
        return $GLOBALS['cnp_integracao_cache'];
    }

    $config = cnp_integracao_defaults();

    if (is_file(CNP_INTEGRACAO_RUNTIME_FILE) && is_readable(CNP_INTEGRACAO_RUNTIME_FILE)) {
        $raw = file_get_contents(CNP_INTEGRACAO_RUNTIME_FILE);
        $decoded = json_decode((string) $raw, true);
        if (is_array($decoded)) {
            $config = array_merge($config, $decoded);
        }
    }

    $config = cnp_integracao_sanitize($config);
    $GLOBALS['cnp_integracao_cache'] = $config;

    return $config;
}

function cnp_integracao_save(array $input): array
{
    $current = cnp_integracao_load();
    $next = $current;

    foreach (array_keys(cnp_integracao_defaults()) as $key) {
        if (!array_key_exists($key, $input)) {
            continue;
        }

        $value = (string) $input[$key];

        if (in_array($key, ['wp_app_password', 'db_password'], true) && trim($value) === '') {
            continue;
        }

        $next[$key] = $value;
    }

    $next = cnp_integracao_sanitize($next);
    $dir = dirname(CNP_INTEGRACAO_RUNTIME_FILE);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    $json = json_encode($next, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if (!is_string($json) || $json === '') {
        throw new RuntimeException('Falha ao serializar configuracoes de integracao.');
    }

    if (file_put_contents(CNP_INTEGRACAO_RUNTIME_FILE, $json, LOCK_EX) === false) {
        throw new RuntimeException('Falha ao salvar configuracoes de integracao.');
    }

    @chmod(CNP_INTEGRACAO_RUNTIME_FILE, 0600);
    $GLOBALS['cnp_integracao_cache'] = $next;
    unset($GLOBALS['cnp_db_pdo_cache'], $GLOBALS['cnp_db_pdo_hash']);

    return $next;
}

function cnp_integracao_public_view(array $config): array
{
    $out = $config;
    $out['wp_app_password'] = '';
    $out['db_password'] = '';
    $out['wp_app_password_configured'] = trim((string) $config['wp_app_password']) !== '';
    $out['db_password_configured'] = trim((string) $config['db_password']) !== '';

    return $out;
}

function cnp_integracao_db_is_configured(?array $config = null): bool
{
    $cfg = $config ?? cnp_integracao_load();

    return
        trim((string) $cfg['db_host']) !== '' &&
        trim((string) $cfg['db_name']) !== '' &&
        trim((string) $cfg['db_user']) !== '';
}

function cnp_integracao_wp_is_configured(?array $config = null): bool
{
    $cfg = $config ?? cnp_integracao_load();

    return
        trim((string) $cfg['wp_base_url']) !== '' &&
        trim((string) $cfg['wp_rest_user']) !== '' &&
        trim((string) $cfg['wp_app_password']) !== '';
}

function cnp_integracao_db_table_noticias(?array $config = null): string
{
    $cfg = $config ?? cnp_integracao_load();
    return cnp_integracao_safe_identifier((string) ($cfg['db_table_noticias'] ?? ''), 'cnp_noticias');
}

function cnp_integracao_db_table_execucoes(?array $config = null): string
{
    $cfg = $config ?? cnp_integracao_load();
    return cnp_integracao_safe_identifier((string) ($cfg['db_table_execucoes'] ?? ''), 'cnp_pipeline_execucoes');
}

function cnp_integracao_db_pdo(): PDO
{
    $cfg = cnp_integracao_load();
    if (!cnp_integracao_db_is_configured($cfg)) {
        throw new RuntimeException('Banco externo nao configurado. Abra Integracoes no painel admin.');
    }

    $connHash = sha1(
        implode('|', [
            (string) $cfg['db_host'],
            (string) $cfg['db_port'],
            (string) $cfg['db_name'],
            (string) $cfg['db_user'],
            (string) $cfg['db_charset'],
        ])
    );

    if (
        isset($GLOBALS['cnp_db_pdo_cache'], $GLOBALS['cnp_db_pdo_hash']) &&
        $GLOBALS['cnp_db_pdo_cache'] instanceof PDO &&
        (string) $GLOBALS['cnp_db_pdo_hash'] === $connHash
    ) {
        return $GLOBALS['cnp_db_pdo_cache'];
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        (string) $cfg['db_host'],
        (int) $cfg['db_port'],
        (string) $cfg['db_name'],
        (string) $cfg['db_charset']
    );

    $pdo = new PDO(
        $dsn,
        (string) $cfg['db_user'],
        (string) $cfg['db_password'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );

    $GLOBALS['cnp_db_pdo_cache'] = $pdo;
    $GLOBALS['cnp_db_pdo_hash'] = $connHash;

    return $pdo;
}

function cnp_integracao_db_ensure_schema(?PDO $pdo = null): void
{
    $cfg = cnp_integracao_load();
    $conn = $pdo ?? cnp_integracao_db_pdo();
    $tableNoticias = cnp_integracao_db_table_noticias($cfg);
    $tableExecucoes = cnp_integracao_db_table_execucoes($cfg);

    $conn->exec("
        CREATE TABLE IF NOT EXISTS `{$tableNoticias}` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `hash_origem` CHAR(40) NOT NULL,
            `hash_conteudo` CHAR(40) NOT NULL,
            `data_fonte` VARCHAR(10) NOT NULL,
            `titulo` VARCHAR(500) NOT NULL,
            `conteudo_original` LONGTEXT NOT NULL,
            `conteudo_final` LONGTEXT NOT NULL,
            `categoria` VARCHAR(32) NOT NULL DEFAULT 'federal',
            `status` VARCHAR(20) NOT NULL DEFAULT 'pending',
            `wp_post_id` BIGINT NULL,
            `wp_post_url` VARCHAR(1000) NULL,
            `wp_status` VARCHAR(20) NULL,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NOT NULL,
            `published_at` DATETIME NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_hash_origem` (`hash_origem`),
            KEY `idx_status` (`status`),
            KEY `idx_data_fonte` (`data_fonte`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $conn->exec("
        CREATE TABLE IF NOT EXISTS `{$tableExecucoes}` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `executed_at` DATETIME NOT NULL,
            `status` VARCHAR(20) NOT NULL DEFAULT '',
            `coletadas` INT NOT NULL DEFAULT 0,
            `processadas` INT NOT NULL DEFAULT 0,
            `criadas` INT NOT NULL DEFAULT 0,
            `atualizados` INT NOT NULL DEFAULT 0,
            `ignorados` INT NOT NULL DEFAULT 0,
            `mensagem` TEXT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_exec_at` (`executed_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
}

function cnp_integracao_data_fonte_para_datetime_wp(string $dataFonte): ?string
{
    $dt = DateTime::createFromFormat('!d/m/Y', trim($dataFonte), new DateTimeZone('America/Sao_Paulo'));
    if ($dt === false) {
        return null;
    }

    return $dt->format('Y-m-d\T00:00:00');
}

function cnp_integracao_formatar_conteudo_wp(string $conteudo): string
{
    $conteudo = trim($conteudo);
    if ($conteudo === '') {
        return '';
    }

    $hasHtml = preg_match('/<\s*(p|table|h1|h2|h3|h4|ul|ol|li|blockquote|div|br)\b/i', $conteudo) === 1;

    if (!$hasHtml) {
        $partes = preg_split('/\R{2,}/u', $conteudo);
        $blocos = [];
        if (is_array($partes)) {
            foreach ($partes as $parte) {
                $parte = trim((string) $parte);
                if ($parte === '') {
                    continue;
                }

                $blocos[] = '<p>' . nl2br(htmlspecialchars($parte, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')) . '</p>';
            }
        }
        $conteudo = implode("\n", $blocos);
    }

    if (stripos($conteudo, 'Fonte original: Econet Editora') === false) {
        $conteudo .=
            '<hr>' .
            '<p style="font-size:12px;color:#666;line-height:1.4;"><em>' .
            'Conteudo reformulado automaticamente com apoio de IA.<br>' .
            'Fonte original: Econet Editora.' .
            '</em></p>';
    }

    return $conteudo;
}

function cnp_integracao_wp_categoria_id(string $categoria, ?array $config = null): int
{
    $cfg = $config ?? cnp_integracao_load();
    $categoria = strtolower(trim($categoria));

    if ($categoria === 'trabalhista') {
        return (int) ($cfg['wp_category_trabalhista'] ?? 0);
    }
    if ($categoria === 'comex') {
        return (int) ($cfg['wp_category_comex'] ?? 0);
    }

    return (int) ($cfg['wp_category_federal'] ?? 0);
}

function cnp_integracao_wp_request(
    string $method,
    string $path,
    ?array $payload = null,
    array $query = []
): array {
    $cfg = cnp_integracao_load();
    if (!cnp_integracao_wp_is_configured($cfg)) {
        throw new RuntimeException('API WordPress externa nao configurada. Abra Integracoes no painel admin.');
    }

    $base = rtrim((string) $cfg['wp_base_url'], '/');
    $uri = $base . '/wp-json/wp/v2' . (str_starts_with($path, '/') ? $path : '/' . $path);
    if (!empty($query)) {
        $uri .= '?' . http_build_query($query);
    }

    $auth = base64_encode((string) $cfg['wp_rest_user'] . ':' . (string) $cfg['wp_app_password']);
    $headers = [
        'Authorization: Basic ' . $auth,
        'Content-Type: application/json; charset=utf-8',
    ];

    $ch = curl_init($uri);
    if ($ch === false) {
        throw new RuntimeException('Falha ao inicializar conexao com WordPress externo.');
    }

    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => max(10, (int) ($cfg['wp_timeout'] ?? 20)),
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
    ];

    if ($payload !== null) {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if (!is_string($json)) {
            curl_close($ch);
            throw new RuntimeException('Falha ao serializar payload para API do WordPress.');
        }
        $options[CURLOPT_POSTFIELDS] = $json;
    }

    curl_setopt_array($ch, $options);
    $raw = curl_exec($ch);

    if ($raw === false) {
        $err = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException('Erro de comunicacao com WordPress externo: ' . $err);
    }

    $httpCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    $decoded = json_decode((string) $raw, true);
    $data = is_array($decoded) ? $decoded : [];

    if ($httpCode >= 400) {
        $msg = trim((string) ($data['message'] ?? 'Falha na API do WordPress externo.'));
        throw new RuntimeException($msg . " (HTTP {$httpCode})");
    }

    return $data;
}

function cnp_integracao_wp_publicar_noticia(array $registro): array
{
    $cfg = cnp_integracao_load();
    $dataWp = cnp_integracao_data_fonte_para_datetime_wp((string) ($registro['data_fonte'] ?? ''));
    $categoriaId = cnp_integracao_wp_categoria_id((string) ($registro['categoria'] ?? 'federal'), $cfg);
    $targetStatus = strtolower(trim((string) ($registro['wp_target_status'] ?? $registro['wp_status'] ?? 'publish')));
    if (!in_array($targetStatus, ['publish', 'draft', 'pending', 'private'], true)) {
        $targetStatus = 'publish';
    }

    $payload = [
        'title' => (string) ($registro['titulo'] ?? ''),
        'content' => cnp_integracao_formatar_conteudo_wp((string) ($registro['conteudo_final'] ?? '')),
        'status' => $targetStatus,
    ];

    if ($dataWp !== null) {
        $payload['date'] = $dataWp;
    }

    if ($categoriaId > 0) {
        $payload['categories'] = [$categoriaId];
    }

    $authorId = (int) ($cfg['wp_author_id'] ?? 0);
    if ($authorId > 0) {
        $payload['author'] = $authorId;
    }

    $postId = max(0, (int) ($registro['wp_post_id'] ?? 0));
    try {
        $data = $postId > 0
            ? cnp_integracao_wp_request('POST', '/posts/' . $postId, $payload)
            : cnp_integracao_wp_request('POST', '/posts', $payload);
    } catch (Throwable $e) {
        if ($postId > 0 && str_contains(strtolower($e->getMessage()), 'invalid post id')) {
            $data = cnp_integracao_wp_request('POST', '/posts', $payload);
        } else {
            throw $e;
        }
    }

    return [
        'id' => (int) ($data['id'] ?? 0),
        'link' => (string) ($data['link'] ?? ''),
        'status' => (string) ($data['status'] ?? ''),
    ];
}

function cnp_integracao_wp_excluir_post(int $postId): void
{
    if ($postId <= 0) {
        return;
    }

    cnp_integracao_wp_request('DELETE', '/posts/' . $postId, null, ['force' => 'true']);
}

function cnp_integracao_testar_wp(): array
{
    $data = cnp_integracao_wp_request('GET', '/users/me', null, ['context' => 'edit']);

    return [
        'usuario' => (string) ($data['name'] ?? $data['slug'] ?? ''),
        'id' => (int) ($data['id'] ?? 0),
    ];
}
