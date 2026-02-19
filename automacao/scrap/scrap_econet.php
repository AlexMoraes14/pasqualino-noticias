<?php
declare(strict_types=1);

/**
 * Coleta noticias da Econet publicadas nos ultimos dias.
 *
 * @throws RuntimeException
 */
function cnp_scrap_econet_noticias(int $dias = 7): array
{
    $url = 'https://www.econeteditora.com.br/links_pagina_inicial/noticias20.php';
    $html = @file_get_contents($url);

    if (!is_string($html) || trim($html) === '') {
        throw new RuntimeException('Falha ao obter conteudo do scraper da Econet.');
    }

    $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    $previousErrors = libxml_use_internal_errors(true);

    $dom = new DOMDocument('1.0', 'UTF-8');
    if (!$dom->loadHTML('<?xml encoding="UTF-8">' . $html)) {
        libxml_clear_errors();
        libxml_use_internal_errors($previousErrors);
        throw new RuntimeException('Falha ao processar HTML retornado pela Econet.');
    }

    libxml_clear_errors();
    libxml_use_internal_errors($previousErrors);

    $xpath = new DOMXPath($dom);
    $anchors = $xpath->query("//a[starts-with(@name,'fed')]");
    if (!$anchors) {
        return [];
    }

    $noticias = [];
    $limite = new DateTime('-' . max(1, $dias) . ' days');

    foreach ($anchors as $a) {
        $node = $a->nextSibling;
        while ($node && $node->nodeType !== XML_ELEMENT_NODE) {
            $node = $node->nextSibling;
        }

        if (!$node) {
            continue;
        }

        $texto = trim((string) preg_replace('/\s+/', ' ', $node->textContent));

        if (!preg_match('/^(\d{2}\/\d{2}\/\d{4})\s*(.+)$/', $texto, $m)) {
            continue;
        }

        $dataStr = $m[1];
        $titulo = $m[2];

        $dataObj = DateTime::createFromFormat('d/m/Y', $dataStr);
        if (!$dataObj || $dataObj < $limite) {
            continue;
        }

        $conteudoHtml = '';
        $p = $node->nextSibling;

        while ($p) {
            if ($p->nodeType === XML_ELEMENT_NODE && $p->nodeName === 'a') {
                break;
            }

            if ($p->nodeType === XML_ELEMENT_NODE) {
                $conteudoHtml .= $dom->saveHTML($p);
            }

            $p = $p->nextSibling;
        }

        $noticias[] = [
            'data' => $dataStr,
            'titulo' => $titulo,
            'conteudo' => trim($conteudoHtml),
            'categoria' => 'federal',
        ];
    }

    return $noticias;
}

/**
 * Permite executar o scraper diretamente via HTTP/CLI.
 */
function cnp_scrap_econet_is_direct_call(): bool
{
    $scriptFile = (string) ($_SERVER['SCRIPT_FILENAME'] ?? '');
    if ($scriptFile === '') {
        return false;
    }

    $realScript = realpath($scriptFile);
    $realCurrent = realpath(__FILE__);
    return $realScript !== false && $realCurrent !== false && $realScript === $realCurrent;
}

if (cnp_scrap_econet_is_direct_call()) {
    try {
        $noticias = cnp_scrap_econet_noticias();
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($noticias, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    } catch (Throwable $e) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage(),
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
    exit;
}
