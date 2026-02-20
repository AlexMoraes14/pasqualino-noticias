<?php
declare(strict_types=1);

/**
 * PIPELINE CORPORATIVO - CENTRAL DE NOTICIAS
 * Responsabilidade:
 * - Scrap
 * - IA
 * - Criar rascunhos para revisao
 * - Lock + logs
 */

date_default_timezone_set('America/Sao_Paulo');

const CNP_PIPELINE_LOCK_TTL = 300;

// ================= CONFIG =================
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/integracao.php';
require_once __DIR__ . '/ia/reformular.php';
require_once __DIR__ . '/publish/publicar_wp.php';
require_once __DIR__ . '/scrap/scrap_econet.php';

// ================= LOG ====================
if (!defined('LOG_DIR')) {
    define('LOG_DIR', __DIR__ . '/logs');
}

if (!is_dir(LOG_DIR)) {
    mkdir(LOG_DIR, 0777, true);
}

function logPipeline(string $msg): void
{
    file_put_contents(
        LOG_DIR . '/pipeline.log',
        '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL,
        FILE_APPEND
    );
}

function cnp_lock_age_seconds(string $lockFile): ?int
{
    if (!is_file($lockFile)) {
        return null;
    }

    $mtime = @filemtime($lockFile);
    if ($mtime === false) {
        return null;
    }

    return max(0, time() - (int) $mtime);
}

function cnp_hash_origem_conteudo(string $html): string
{
    $texto = strip_tags($html);
    $texto = html_entity_decode($texto, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $texto = preg_replace('/\s+/u', ' ', trim((string) $texto));

    if ($texto === null || $texto === '') {
        $texto = trim($html);
    }

    return sha1((string) $texto);
}

function cnp_hash_origem_noticia_pipeline(string $titulo, string $dataFonte): string
{
    $titulo = mb_strtolower(trim($titulo), 'UTF-8');
    $dataFonte = trim($dataFonte);
    return sha1($titulo . '|' . $dataFonte);
}

function cnp_extrair_data_fonte(string $raw): string
{
    $raw = trim($raw);
    if ($raw === '') {
        return '';
    }

    if (preg_match('/(\d{2}\/\d{2}\/\d{4})/', $raw, $m) === 1) {
        return $m[1];
    }

    return '';
}

function coletarNoticiasEconet(): array
{
    if (!function_exists('cnp_scrap_econet_noticias')) {
        throw new RuntimeException('Scraper da Econet nao carregado.');
    }

    $noticias = cnp_scrap_econet_noticias();
    if (!is_array($noticias)) {
        throw new RuntimeException('Retorno invalido do scraper da Econet.');
    }

    return $noticias;
}

/**
 * Evita reformular tudo a cada clique.
 * Processa apenas noticias novas ou alteradas (data/hash diferente).
 */
function cnp_filtrar_noticias_para_processamento(array $noticias): array
{
    $pdo = cnp_integracao_db_pdo();
    cnp_integracao_db_ensure_schema($pdo);
    $table = cnp_integracao_db_table_noticias();

    $stmt = $pdo->prepare("
        SELECT id, hash_conteudo
        FROM `{$table}`
        WHERE hash_origem = :hash_origem
        LIMIT 1
    ");

    $filtradas = [];

    foreach ($noticias as $n) {
        $titulo = trim((string) ($n['titulo'] ?? ''));
        if ($titulo === '') {
            continue;
        }

        $dataAtual = cnp_extrair_data_fonte((string) ($n['data'] ?? ''));
        if ($dataAtual === '') {
            $dataAtual = trim((string) ($n['data'] ?? ''));
        }
        if ($dataAtual === '') {
            $dataAtual = (new DateTime('now', new DateTimeZone('America/Sao_Paulo')))->format('d/m/Y');
        }

        $hashOrigem = cnp_hash_origem_noticia_pipeline($titulo, $dataAtual);
        $hashConteudoAtual = cnp_hash_origem_conteudo((string) ($n['conteudo'] ?? ''));

        $stmt->execute([':hash_origem' => $hashOrigem]);
        $existente = $stmt->fetch();

        if ($existente && trim((string) ($existente['hash_conteudo'] ?? '')) === $hashConteudoAtual) {
            continue;
        }

        $n['_origem_hash'] = $hashOrigem;
        $n['_conteudo_hash'] = $hashConteudoAtual;
        $n['data'] = $dataAtual;
        $filtradas[] = $n;
    }

    return $filtradas;
}

// ================= PIPELINE =================
function runPipeline(): array
{
    @ini_set('max_execution_time', '0');
    @set_time_limit(0);
    ignore_user_abort(true);

    $lockFile = LOG_DIR . '/pipeline.lock';

    if (is_file($lockFile)) {
        $lockAge = cnp_lock_age_seconds($lockFile);
        if ($lockAge !== null && $lockAge > CNP_PIPELINE_LOCK_TTL) {
            @unlink($lockFile);
            logPipeline("LOCK EXPIRADO REMOVIDO (idade={$lockAge}s)");
        } else {
            return [
                'status' => 'locked',
                'mensagem' => 'Pipeline ja esta em execucao.',
                'lock_age' => $lockAge,
            ];
        }
    }

    file_put_contents($lockFile, (string) getmypid());

    try {
        if (!cnp_integracao_db_is_configured()) {
            logPipeline('ERRO: Banco externo nao configurado');
            return [
                'status' => 'error',
                'mensagem' => 'Banco externo nao configurado. Abra Integracoes no painel admin.',
            ];
        }

        cnp_integracao_db_ensure_schema();
        logPipeline('PIPELINE START');

        $noticias = coletarNoticiasEconet();
        if (!is_array($noticias) || empty($noticias)) {
            logPipeline('Nenhuma noticia coletada');
            logPipeline('PIPELINE END');
            return [
                'status' => 'ok',
                'coletadas' => 0,
                'processadas' => 0,
                'criadas' => 0,
                'atualizados' => 0,
                'ignorados' => 0,
                'mensagem' => 'Nenhuma noticia nova encontrada',
            ];
        }

        $noticiasParaProcessar = cnp_filtrar_noticias_para_processamento($noticias);
        if (empty($noticiasParaProcessar)) {
            logPipeline('Nenhuma noticia nova/alterada para processar');
            logPipeline('PIPELINE END');
            return [
                'status' => 'ok',
                'coletadas' => count($noticias),
                'processadas' => 0,
                'criadas' => 0,
                'atualizados' => 0,
                'ignorados' => 0,
                'mensagem' => 'Nenhuma noticia nova ou alterada',
            ];
        }

        $totalParaProcessar = count($noticiasParaProcessar);
        $lote = array_slice($noticiasParaProcessar, 0, PIPELINE_MAX_PROCESS_PER_RUN);
        $restantes = max(0, $totalParaProcessar - count($lote));

        $processadas = [];
        foreach ($lote as $n) {
            $n['conteudo_final'] = reformularNoticia((string) ($n['conteudo'] ?? ''));
            $processadas[] = $n;
        }

        $wp = publicarNoticiasWP($processadas);

        logPipeline(
            'Processadas: ' . count($processadas) .
            " | Restantes: {$restantes}" .
            " | Fila: {$wp['criadas']} criadas | {$wp['atualizados']} atualizadas | {$wp['ignorados']} ignoradas"
        );
        logPipeline('PIPELINE END');

        $mensagem = $restantes > 0
            ? 'Lote concluido. Ainda restam noticias para processar; clique em atualizar novamente.'
            : 'Conteudos enviados para revisao editorial';

        return [
            'status' => 'ok',
            'coletadas' => count($noticias),
            'processadas' => count($processadas),
            'restantes' => $restantes,
            'criadas' => $wp['criadas'],
            'atualizados' => $wp['atualizados'],
            'ignorados' => $wp['ignorados'],
            'mensagem' => $mensagem,
        ];
    } catch (Throwable $e) {
        logPipeline('ERRO: ' . $e->getMessage());

        return [
            'status' => 'error',
            'mensagem' => $e->getMessage(),
        ];
    } finally {
        @unlink($lockFile);
    }
}
