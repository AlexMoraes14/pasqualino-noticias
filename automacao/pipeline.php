<?php
/**
 * PIPELINE CORPORATIVO - CENTRAL DE NOTÍCIAS
 * Responsabilidade:
 * - Scrap
 * - IA
 * - Criar rascunhos para revisão
 * - Lock + logs
 */

date_default_timezone_set('America/Sao_Paulo');

// ================= CONFIG =================
require_once __DIR__ . '/config/config.php';
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

// ================= PIPELINE =================
function runPipeline(): array
{
    $lockFile = LOG_DIR . '/pipeline.lock';

    if (file_exists($lockFile)) {
        return [
            'status' => 'locked',
            'mensagem' => 'Pipeline já está em execução.'
        ];
    }

    file_put_contents($lockFile, getmypid());

    try {
        logPipeline('PIPELINE START');

        // SCRAP
        $noticias = coletarNoticiasEconet();
        if (!is_array($noticias) || empty($noticias)) {
            return [
                'status' => 'ok',
                'coletadas' => 0,
                'criadas' => 0,
                'atualizados' => 0,
                'ignorados' => 0,
                'mensagem' => 'Nenhuma notícia nova encontrada'
            ];
        }

        // IA
        $processadas = [];
        foreach ($noticias as $n) {
            $n['conteudo_final'] = reformularNoticia($n['conteudo']);
            $processadas[] = $n;
        }

        // WORDPRESS
        $wp = publicarNoticiasWP($processadas);

        logPipeline(
            "WP: {$wp['criadas']} criadas | {$wp['atualizados']} atualizadas | {$wp['ignorados']} ignoradas"
        );

        logPipeline('PIPELINE END');

        return [
            'status'      => 'ok',
            'coletadas'   => count($noticias),
            'criadas'     => $wp['criadas'],
            'atualizados' => $wp['atualizados'],
            'ignorados'   => $wp['ignorados'],
            'mensagem'    => 'Conteúdos enviados para revisão editorial'
        ];

    } catch (Throwable $e) {
        logPipeline('ERRO: ' . $e->getMessage());

        return [
            'status' => 'error',
            'mensagem' => $e->getMessage()
        ];
    } finally {
        @unlink($lockFile);
    }
}
