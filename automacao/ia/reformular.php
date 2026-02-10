<?php
require_once __DIR__ . '/../config/config.php';

/**
 * Função principal chamada pelo publicador
 */
function reformularNoticia($html)
{
    if (!is_string($html) || trim($html) === '') {
        return $html;
    }

    // 1. Separa tabelas do HTML
    preg_match_all('/<table.*?>.*?<\/table>/is', $html, $matches);
    $tabelas = $matches[0] ?? [];

    // Remove tabelas do texto
    $textoLimpo = preg_replace('/<table.*?>.*?<\/table>/is', '', $html);

    // Formatação de texto
    $textoLimpo = strip_tags($textoLimpo);

    // corta respeitando frases
    $limite = 2500;

    if (mb_strlen($textoLimpo) > $limite) {
        $textoCortado = mb_substr($textoLimpo, 0, $limite);

        // garante que termina em ponto final
        $ultimoPonto = mb_strrpos($textoCortado, '.');

        if ($ultimoPonto !== false) {
            $textoLimpo = mb_substr($textoCortado, 0, $ultimoPonto + 1);
        } else {
            $textoLimpo = $textoCortado;
        }
    }


    // 2. Reformula TEXTO (sem tabela)
    if (IA_PROVIDER === 'openai' && OPENAI_API_KEY !== '') {
        $textoReformulado = reformularComOpenAI($textoLimpo);
    } elseif (IA_PROVIDER === 'huggingface' && HF_API_KEY !== '') {
        $textoReformulado = reformularComHuggingFace($textoLimpo);
    } else {
        $textoReformulado = $textoLimpo;
    }

    // 3. Reanexa tabelas NO FINAL
    if (!empty($tabelas)) {
        $textoReformulado .= "\n\n<h3>Dados complementares</h3>";

        foreach ($tabelas as $tabela) {
            $textoReformulado .= "\n\n" . $tabela;
        }
    }

    // 4. Fonte obrigatória
    //$textoReformulado .=
       // '<br><br><em>Conteúdo reformulado automaticamente com apoio de IA.<br>
       // Fonte original: Econet Editora.</em>';

    return $textoReformulado;
}


/**
 * Extrai tabelas do HTML e coloca placeholder
 */
function separarTabelas($html)
{
    $tabelas = [];

    if (preg_match_all('/<table.*?>.*?<\/table>/is', $html, $matches)) {
        $tabelas = $matches[0];
        $html = preg_replace('/<table.*?>.*?<\/table>/is', '[TABELA_AQUI]', $html);
    }

    return [$html, $tabelas];
}

/* ===============================
   OPENAI
================================ */
function reformularComOpenAI($texto)
{
    $prompt =
        "Reescreva a notícia abaixo com linguagem jornalística própria, "
        . "sem copiar a estrutura original, mantendo o sentido. "
        . "Finalize com: Fonte: Econet Editora — informações adaptadas.\n\n"
        . $texto;

    $payload = [
        'model' => 'gpt-4o-mini',
        'messages' => [
            ['role' => 'system', 'content' => 'Você é um redator jornalístico profissional.'],
            ['role' => 'user', 'content' => $prompt]
        ],
        'temperature' => 0.7
    ];

    return callApi(
        'https://api.openai.com/v1/chat/completions',
        [
            'Authorization: Bearer ' . OPENAI_API_KEY,
            'Content-Type: application/json'
        ],
        $payload,
        fn ($data) => $data['choices'][0]['message']['content'] ?? null,
        $texto
    );
}

/* ===============================
   HUGGING FACE (FREE)
================================ */
function reformularComHuggingFace($texto)
{
    $prompt =
        "Reescreva a notícia abaixo com linguagem jornalística própria, "
        . "sem copiar a estrutura original, mantendo o sentido. "
        . "Finalize com: Fonte: Econet Editora — informações adaptadas.\n\n"
        . $texto;

    $payload = [
        "inputs" => $prompt,
        "parameters" => [
            "temperature" => 0.6,
            "max_new_tokens" => 400,
            "return_full_text" => false
        ]
    ];

    return callApi(
        'https://api-inference.huggingface.co/models/mistralai/Mistral-7B-Instruct-v0.2',
        [
            'Authorization: Bearer ' . HF_API_KEY,
            'Content-Type: application/json'
        ],
        $payload,
        fn ($data) => $data[0]['generated_text'] ?? null,
        $texto
    );
}

/* ===============================
   CURL GENÉRICO
================================ */
function callApi($url, $headers, $payload, $extractor, $fallback)
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 60,
        CURLOPT_SSL_VERIFYPEER => false // LOCAL ONLY
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($response, true);
    $result = is_callable($extractor) ? $extractor($data) : null;

    return is_string($result) && trim($result) !== ''
        ? trim($result)
        : $fallback;
}
