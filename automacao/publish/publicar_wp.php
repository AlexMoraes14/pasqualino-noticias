<?php
declare(strict_types=1);

/**
 * Persistencia da fila editorial em banco externo.
 * Mantem assinatura antiga da funcao para nao quebrar o pipeline:
 * publicarNoticiasWP(array $noticias): array
 */

require_once __DIR__ . '/../config/integracao.php';

function cnp_normalizar_data_fonte(string $raw): string
{
    $raw = trim($raw);
    if ($raw === '') {
        return (new DateTime('now', new DateTimeZone('America/Sao_Paulo')))->format('d/m/Y');
    }

    if (preg_match('/(\d{2}\/\d{2}\/\d{4})/', $raw, $m) === 1) {
        return $m[1];
    }

    return (new DateTime('now', new DateTimeZone('America/Sao_Paulo')))->format('d/m/Y');
}

function cnp_hash_texto_limpo(string $texto): string
{
    $texto = strip_tags($texto);
    $texto = html_entity_decode($texto, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $texto = preg_replace('/\s+/u', ' ', trim((string) $texto));
    return sha1((string) $texto);
}

function cnp_hash_origem_noticia(string $titulo, string $dataFonte): string
{
    $titulo = mb_strtolower(trim($titulo), 'UTF-8');
    $dataFonte = trim($dataFonte);
    return sha1($titulo . '|' . $dataFonte);
}

/**
 * @param array $noticias
 * @return array{criadas:int,atualizados:int,ignorados:int}
 */
function publicarNoticiasWP(array $noticias): array
{
    $resultado = [
        'criadas' => 0,
        'atualizados' => 0,
        'ignorados' => 0,
    ];

    $pdo = cnp_integracao_db_pdo();
    cnp_integracao_db_ensure_schema($pdo);
    $table = cnp_integracao_db_table_noticias();

    $select = $pdo->prepare("SELECT id, status FROM `{$table}` WHERE hash_origem = :hash LIMIT 1");

    $insert = $pdo->prepare("
        INSERT INTO `{$table}` (
            hash_origem, hash_conteudo, data_fonte, titulo, conteudo_original, conteudo_final,
            categoria, status, created_at, updated_at
        ) VALUES (
            :hash_origem, :hash_conteudo, :data_fonte, :titulo, :conteudo_original, :conteudo_final,
            :categoria, :status, NOW(), NOW()
        )
    ");

    $update = $pdo->prepare("
        UPDATE `{$table}`
        SET
            hash_conteudo = :hash_conteudo,
            data_fonte = :data_fonte,
            titulo = :titulo,
            conteudo_original = :conteudo_original,
            conteudo_final = :conteudo_final,
            categoria = :categoria,
            status = :status,
            updated_at = NOW()
        WHERE id = :id
        LIMIT 1
    ");

    foreach ($noticias as $noticia) {
        $titulo = trim((string) ($noticia['titulo'] ?? ''));
        $conteudoOriginal = trim((string) ($noticia['conteudo'] ?? ''));
        $conteudoFinal = trim((string) ($noticia['conteudo_final'] ?? ''));

        if ($titulo === '' || $conteudoFinal === '') {
            $resultado['ignorados']++;
            continue;
        }

        $dataFonte = cnp_normalizar_data_fonte((string) ($noticia['data'] ?? ''));
        $categoria = trim((string) ($noticia['categoria'] ?? 'federal'));
        if ($categoria === '') {
            $categoria = 'federal';
        }

        $hashOrigem = trim((string) ($noticia['_origem_hash'] ?? ''));
        if ($hashOrigem === '') {
            $hashOrigem = cnp_hash_origem_noticia($titulo, $dataFonte);
        }

        $hashConteudo = trim((string) ($noticia['_conteudo_hash'] ?? ''));
        if ($hashConteudo === '') {
            $hashConteudo = cnp_hash_texto_limpo($conteudoOriginal !== '' ? $conteudoOriginal : $conteudoFinal);
        }

        $select->execute([':hash' => $hashOrigem]);
        $existente = $select->fetch();

        if (!$existente) {
            $insert->execute([
                ':hash_origem' => $hashOrigem,
                ':hash_conteudo' => $hashConteudo,
                ':data_fonte' => $dataFonte,
                ':titulo' => $titulo,
                ':conteudo_original' => $conteudoOriginal,
                ':conteudo_final' => $conteudoFinal,
                ':categoria' => $categoria,
                ':status' => 'pending',
            ]);
            $resultado['criadas']++;
            continue;
        }

        $statusAtual = trim((string) ($existente['status'] ?? 'pending'));
        $novoStatus = in_array($statusAtual, ['published', 'ignored', 'deleted'], true) ? 'pending' : $statusAtual;
        if ($novoStatus === '') {
            $novoStatus = 'pending';
        }

        $update->execute([
            ':hash_conteudo' => $hashConteudo,
            ':data_fonte' => $dataFonte,
            ':titulo' => $titulo,
            ':conteudo_original' => $conteudoOriginal,
            ':conteudo_final' => $conteudoFinal,
            ':categoria' => $categoria,
            ':status' => $novoStatus,
            ':id' => (int) $existente['id'],
        ]);
        $resultado['atualizados']++;
    }

    return $resultado;
}

