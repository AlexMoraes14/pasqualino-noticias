<?php
/**
 * PUBLICADOR WORDPRESS
 * Responsabilidade:
 * - Criar ou atualizar posts
 * - Enviar para revisão editorial (pending)
 * - Retornar estatísticas confiáveis
 */

if (!defined('ABSPATH')) {
    require_once __DIR__ . '/../../wordpress/wp-load.php';
}

/**
 * @param array $noticias
 * @return array
 */
function publicarNoticiasWP(array $noticias): array
{
    $resultado = [
        'criadas'     => 0,
        'atualizados' => 0,
        'ignorados'   => 0,
    ];

    $autorPadrao = defined('WP_AUTHOR_ID') ? max(1, (int) WP_AUTHOR_ID) : 1;
    $categoriaPadrao = defined('WP_DEFAULT_CATEGORY') ? max(1, (int) WP_DEFAULT_CATEGORY) : 3;

    $mapaCategorias = [
        'federal'     => $categoriaPadrao,
        'trabalhista' => 4,
        'comex'       => 5,
    ];

    foreach ($noticias as $noticia) {

        // validação mínima
        if (
            empty($noticia['titulo']) ||
            empty($noticia['conteudo_final']) ||
            empty($noticia['data'])
        ) {
            $resultado['ignorados']++;
            continue;
        }

        $titulo = trim($noticia['titulo']);
        $existente = get_page_by_title($titulo, OBJECT, 'post');

        $categoria   = $noticia['categoria'] ?? 'federal';
        $categoriaId = $mapaCategorias[$categoria] ?? 1;

        $dataObj = DateTime::createFromFormat('d/m/Y', $noticia['data']);
        if (!$dataObj) {
            $dataObj = new DateTime();
        }

        $conteudoFinal =
            wpautop($noticia['conteudo_final']) .
            '<hr>' .
            '<p style="font-size:12px;color:#666;line-height:1.4;">
                <em>
                Conteúdo reformulado automaticamente com apoio de IA.<br>
                Fonte original: Econet Editora.
                </em>
            </p>';

        $post = [
            'post_title'    => $titulo,
            'post_content'  => $conteudoFinal,
            'post_status'   => 'pending',
            'post_author'   => $autorPadrao,
            'post_category' => [$categoriaId],
            'post_date'     => $dataObj->format('Y-m-d H:i:s'),
        ];

        if ($existente) {
            // Mantem status atual para nao despublicar noticia aprovada.
            // Excecao: se estava na lixeira (ignorada), volta para pending ao atualizar.
            $statusAtual = get_post_status($existente->ID);
            if ($statusAtual === 'trash') {
                $post['post_status'] = 'pending';
            } elseif (is_string($statusAtual) && $statusAtual !== '') {
                $post['post_status'] = $statusAtual;
            }

            $post['ID'] = $existente->ID;
            $post_id = wp_update_post($post, true);

            if (is_wp_error($post_id)) {
                $resultado['ignorados']++;
                continue;
            }

            $resultado['atualizados']++;

        } else {

            $post_id = wp_insert_post($post, true);

            if (is_wp_error($post_id)) {
                $resultado['ignorados']++;
                continue;
            }

            $resultado['criadas']++;
        }

        // metadados (só se $post_id existir)
        update_post_meta($post_id, '_origem_conteudo', 'Econet Editora');
        update_post_meta($post_id, '_conteudo_ia', 'sim');
        update_post_meta($post_id, '_status_pipeline', 'aguardando_revisao');
    }

    return $resultado;
}
