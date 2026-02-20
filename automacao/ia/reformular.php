<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

const CNP_IA_MAX_INPUT_CHARS = 12000;
const CNP_HF_MAX_TOKENS = 1200;

/**
 * Funcao principal chamada pelo publicador.
 */
function reformularNoticia($html)
{
    if (!is_string($html) || trim($html) === '') {
        return $html;
    }

    preg_match_all('/<table.*?>.*?<\/table>/is', $html, $matches);
    $tabelas = $matches[0] ?? [];

    $textoSemTabelas = preg_replace('/<table.*?>.*?<\/table>/is', '', $html);
    $textoLimpo = strip_tags((string) $textoSemTabelas);
    $textoLimpo = preg_replace('/\s+/u', ' ', trim((string) $textoLimpo));
    $textoLimpo = cnp_limitar_texto_por_frase((string) $textoLimpo, CNP_IA_MAX_INPUT_CHARS);

    if (IA_PROVIDER === 'openai' && OPENAI_API_KEY !== '') {
        $textoReformulado = reformularComOpenAI($textoLimpo);
    } elseif (IA_PROVIDER === 'huggingface' && HF_API_KEY !== '') {
        $textoReformulado = reformularComHuggingFace($textoLimpo);
    } else {
        $textoReformulado = $textoLimpo;
    }

    $textoReformulado = cnp_validar_texto_reformulado($textoLimpo, $textoReformulado);

    if (!empty($tabelas)) {
        $textoReformulado .= "\n\n<h3>Dados complementares</h3>";
        foreach ($tabelas as $tabela) {
            $textoReformulado .= "\n\n" . $tabela;
        }
    }

    return $textoReformulado;
}

function cnp_limitar_texto_por_frase(string $texto, int $limite): string
{
    $texto = trim($texto);
    if ($texto === '' || mb_strlen($texto) <= $limite) {
        return $texto;
    }

    $textoCortado = mb_substr($texto, 0, $limite);
    $ultimoPonto = mb_strrpos($textoCortado, '.');

    if ($ultimoPonto !== false) {
        return trim(mb_substr($textoCortado, 0, $ultimoPonto + 1));
    }

    return trim($textoCortado);
}

function cnp_prompt_reescrita(string $texto): string
{
    return
        "Reescreva a noticia abaixo com linguagem jornalistica propria, "
        . "sem copiar a estrutura original, sem resumir e sem omitir informacoes. "
        . "Mantenha todos os dados, numeros, datas e contexto. "
        . "Mantenha extensao semelhante ao texto original. "
        . "Nao use Markdown (sem **, sem ### e sem listas markdown). "
        . "Nao invente fatos.\n\n"
        . $texto;
}

function cnp_extract_openai_generated_text($data): ?string
{
    if (!is_array($data)) {
        return null;
    }

    $finishReason = (string) ($data['choices'][0]['finish_reason'] ?? '');
    if (in_array($finishReason, ['length', 'max_tokens'], true)) {
        return null;
    }

    if (isset($data['choices'][0]['message']['content']) && is_string($data['choices'][0]['message']['content'])) {
        return $data['choices'][0]['message']['content'];
    }

    return null;
}

function cnp_normalizar_marcacao_markdown(string $texto): string
{
    $texto = preg_replace('/(^|[\r\n>])\s{0,3}#{1,6}\s+/u', '$1', $texto);
    $texto = preg_replace('/\*\*([^*]+?)\*\*/u', '$1', $texto);
    $texto = str_replace('**', '', (string) $texto);

    return trim((string) $texto);
}

function cnp_resposta_parece_truncada(string $texto): bool
{
    $texto = trim($texto);
    if ($texto === '') {
        return true;
    }

    if (preg_match('/[,:;\-]$/u', $texto) === 1) {
        return true;
    }

    if (preg_match('/\b(de|da|do|das|dos|e|ou|para|com|sem|em|por|que|como)\s*$/iu', $texto) === 1) {
        return true;
    }

    return false;
}

function cnp_validar_texto_reformulado(string $original, string $gerado): string
{
    $original = trim($original);
    $gerado = cnp_normalizar_marcacao_markdown(trim($gerado));

    if ($gerado === '') {
        return $original;
    }

    $lenOriginal = mb_strlen($original);
    $lenGerado = mb_strlen($gerado);

    if ($lenOriginal >= 1600 && $lenGerado < (int) floor($lenOriginal * 0.55)) {
        return $original;
    }

    if (cnp_resposta_parece_truncada($gerado)) {
        return $original;
    }

    return $gerado;
}

/* ===============================
   OPENAI
================================ */
function reformularComOpenAI($texto)
{
    $payload = [
        'model' => 'gpt-4o-mini',
        'messages' => [
            ['role' => 'system', 'content' => 'Voce e um redator jornalistico profissional.'],
            ['role' => 'user', 'content' => cnp_prompt_reescrita((string) $texto)],
        ],
        'temperature' => 0.5,
    ];

    return callApi(
        'https://api.openai.com/v1/chat/completions',
        [
            'Authorization: Bearer ' . OPENAI_API_KEY,
            'Content-Type: application/json',
        ],
        $payload,
        'cnp_extract_openai_generated_text',
        (string) $texto
    );
}

/* ===============================
   HUGGING FACE (FREE)
================================ */
function reformularComHuggingFace($texto)
{
    $payload = [
        'model' => (string) HF_MODEL,
        'messages' => [
            ['role' => 'system', 'content' => 'Voce e um redator jornalistico profissional.'],
            ['role' => 'user', 'content' => cnp_prompt_reescrita((string) $texto)],
        ],
        'temperature' => 0.5,
        'max_tokens' => CNP_HF_MAX_TOKENS,
        'stream' => false,
    ];

    return callApi(
        cnp_hf_chat_completions_url(),
        [
            'Authorization: Bearer ' . HF_API_KEY,
            'Content-Type: application/json',
        ],
        $payload,
        'cnp_extract_hf_generated_text',
        (string) $texto
    );
}

function cnp_hf_chat_completions_url(): string
{
    return rtrim((string) HF_CHAT_COMPLETIONS_URL, '/');
}

function cnp_extract_hf_generated_text($data): ?string
{
    if (!is_array($data)) {
        return null;
    }

    $finishReason = (string) ($data['choices'][0]['finish_reason'] ?? '');
    if (in_array($finishReason, ['length', 'max_tokens'], true)) {
        return null;
    }

    if (isset($data[0]['generated_text']) && is_string($data[0]['generated_text'])) {
        return $data[0]['generated_text'];
    }

    if (isset($data['generated_text']) && is_string($data['generated_text'])) {
        return $data['generated_text'];
    }

    if (isset($data['choices'][0]['text']) && is_string($data['choices'][0]['text'])) {
        return $data['choices'][0]['text'];
    }

    if (isset($data['choices'][0]['message']['content']) && is_string($data['choices'][0]['message']['content'])) {
        return $data['choices'][0]['message']['content'];
    }

    return null;
}

/* ===============================
   CURL GENERICO
================================ */
function callApi($url, $headers, $payload, $extractor, $fallback)
{
    @set_time_limit(0);

    $verifySsl = !defined('IA_DISABLE_SSL_VERIFY') || IA_DISABLE_SSL_VERIFY !== true;
    $jsonPayload = json_encode($payload, JSON_UNESCAPED_UNICODE);
    if (!is_string($jsonPayload) || $jsonPayload === '') {
        return $fallback;
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => $jsonPayload,
        CURLOPT_CONNECTTIMEOUT => IA_CONNECT_TIMEOUT,
        CURLOPT_TIMEOUT => IA_HTTP_TIMEOUT,
        CURLOPT_SSL_VERIFYPEER => $verifySsl,
        CURLOPT_SSL_VERIFYHOST => $verifySsl ? 2 : 0,
    ]);

    $response = curl_exec($ch);
    if ($response === false) {
        curl_close($ch);
        return $fallback;
    }

    $httpCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    if ($httpCode >= 400) {
        curl_close($ch);
        return $fallback;
    }

    curl_close($ch);

    $data = json_decode($response, true);
    $result = is_callable($extractor) ? $extractor($data) : null;

    return is_string($result) && trim($result) !== ''
        ? trim($result)
        : $fallback;
}
