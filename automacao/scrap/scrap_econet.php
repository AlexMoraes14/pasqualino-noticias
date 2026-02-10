<?php
error_reporting(0);
ini_set('display_errors', 0);
libxml_use_internal_errors(true);

$url = 'https://www.econeteditora.com.br/links_pagina_inicial/noticias20.php';
$html = file_get_contents($url);

$html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');

$dom = new DOMDocument('1.0', 'UTF-8');
$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
$xpath = new DOMXPath($dom);

$anchors = $xpath->query("//a[starts-with(@name,'fed')]");
$noticias = [];

$limite = new DateTime('-7 days');

foreach ($anchors as $a) {

    $node = $a->nextSibling;
    while ($node && $node->nodeType !== XML_ELEMENT_NODE) {
        $node = $node->nextSibling;
    }
    if (!$node) continue;

    $texto = trim(preg_replace('/\s+/', ' ', $node->textContent));

    if (!preg_match('/^(\d{2}\/\d{2}\/\d{4})\s*(.+)$/', $texto, $m)) {
        continue;
    }

    $dataStr = $m[1];
    $titulo  = $m[2];

    $dataObj = DateTime::createFromFormat('d/m/Y', $dataStr);
    if (!$dataObj || $dataObj < $limite) continue;

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
        'data'      => $dataStr,
        'titulo'    => $titulo,
        'conteudo'  => trim($conteudoHtml),
        'categoria' => 'federal'
    ];
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode($noticias, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
exit;
