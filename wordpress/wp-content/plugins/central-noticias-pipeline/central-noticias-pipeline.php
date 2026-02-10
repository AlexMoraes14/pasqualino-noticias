<?php
/**
 * Plugin Name: Central de Notícias – Atualização Automática
 * Description: Executa o pipeline de notícias Econet com 1 clique no painel.
 * Version: 1.0
 */

if (!defined('ABSPATH')) exit;

// ================== PERMISSÃO ==================
function cnp_user_can_run(): bool {
    return current_user_can('edit_others_posts');
}

// ================== MENU ADMIN =================
add_action('admin_menu', function () {
    add_menu_page(
        'Central de Notícias',
        'Central de Notícias',
        'edit_others_posts',
        'central-noticias',
        'cnp_render_page',
        'dashicons-megaphone',
        3
    );

    add_submenu_page(
        'central-noticias',
        'Histórico',
        'Histórico',
        'edit_others_posts',
        'central-noticias-historico',
        'cnp_render_historico'
    );
});

// ================== TELA PRINCIPAL =================
function cnp_render_page()
{
    if (!cnp_user_can_run()) {
        wp_die('Sem permissão.');
    }

    echo '<div class="wrap">';
    echo '<h1>Central de Notícias</h1>';

    if (isset($_POST['executar_pipeline'])) {

        require_once ABSPATH . '../automacao/pipeline.php';

        if (!function_exists('runPipeline')) {
            echo '<div class="notice notice-error"><p>Pipeline não encontrado.</p></div>';
        } else {

            $resultado = runPipeline();

            if ($resultado['status'] === 'locked') {
                echo '<div class="notice notice-warning"><p>' . esc_html($resultado['mensagem']) . '</p></div>';
            }
            elseif ($resultado['status'] === 'error') {
                echo '<div class="notice notice-error"><p>' . esc_html($resultado['mensagem']) . '</p></div>';
            }
            else {
                echo '<div class="notice notice-success">';
                echo '<p><strong>Atualização concluída.</strong></p>';
                echo '<ul>';
                echo '<li>Notícias coletadas: ' . ($resultado['coletadas'] ?? 0) . '</li>';
                echo '<li>Notícias criadas (pendentes): ' . ($resultado['criadas'] ?? 0) . '</li>';
                echo '<li>Notícias atualizadas: ' . ($resultado['atualizados'] ?? 0) . '</li>';
                echo '<li>Ignoradas: ' . ($resultado['ignorados'] ?? 0) . '</li>';
                echo '</ul>';
                echo '</div>';

                cnp_salvar_historico($resultado);
            }

        }
    }

    echo '<form method="post">';
    echo '<p>Este botão busca novas notícias, reformula o conteúdo e manda para a revisão.</p>';
    echo '<p><strong>A operação pode levar até 1 minuto.</strong></p>';
    echo '<button class="button button-primary button-hero" name="executar_pipeline">';
    echo 'Atualizar Notícias Agora';
    echo '</button>';
    echo '</form>';

    echo '</div>';
}

// ================== HISTÓRICO (DB) =================
register_activation_hook(__FILE__, function () {
    global $wpdb;

    $table = $wpdb->prefix . 'pipeline_logs';
    $charset = $wpdb->get_charset_collate();

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    dbDelta("
        CREATE TABLE IF NOT EXISTS $table (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            executed_at DATETIME NOT NULL,
            status VARCHAR(20),
            criados INT DEFAULT 0,
            atualizados INT DEFAULT 0,
            ignorados INT DEFAULT 0,
            mensagem TEXT
        ) $charset;
    ");
});

function cnp_salvar_historico(array $dados): void
{
    global $wpdb;

    $wpdb->insert(
        $wpdb->prefix . 'pipeline_logs',
        [
            'executed_at' => current_time('mysql'),
            'status'      => $dados['status'],
            'coletadas'   => $dados['coletadas'],
            'criadas'     => $dados['criadas'],
            'atualizados' => $dados['atualizados'],
            'ignorados'   => $dados['ignorados'],
            'mensagem'    => $dados['mensagem'],
        ]
    );
}

// ================== TELA HISTÓRICO =================
function cnp_render_historico()
{
    global $wpdb;

    $table = $wpdb->prefix . 'pipeline_logs';
    $logs = $wpdb->get_results(
        "SELECT * FROM $table ORDER BY executed_at DESC LIMIT 50"
    );

    echo '<div class="wrap">';
    echo '<h1>Histórico de Execuções</h1>';

    echo '<table class="widefat striped">';
    echo '<thead>
        <tr>
            <th>Data</th>
            <th>Status</th>
            <th>Coletadas</th>
            <th>Criadas</th>
            <th>Atualizados</th>
            <th>Ignorados</th>
            <th>Mensagem</th>
        </tr>
    </thead><tbody>';

    if (!$logs) {
        echo '<tr><td colspan="7">Nenhum histórico encontrado.</td></tr>';
    }

    foreach ($logs as $log) {

        $statusColor = $log->status === 'ok' ? '#2ecc71' : '#e74c3c';

        echo '<tr>';
        echo '<td>' . esc_html($log->executed_at) . '</td>';
        echo '<td style="color:' . $statusColor . ';">' . esc_html($log->status) . '</td>';
        echo '<td>' . intval($log->coletadas ?? 0) . '</td>';
        echo '<td>' . intval($log->criadas ?? 0) . '</td>';
        echo '<td>' . intval($log->atualizados ?? 0) . '</td>';
        echo '<td>' . intval($log->ignorados ?? 0) . '</td>';
        echo '<td>' . esc_html($log->mensagem ?? '') . '</td>';
        echo '</tr>';
    }

    echo '</tbody></table>';
    echo '</div>';

    add_action('transition_post_status', function ($new_status, $old_status, $post) {

    if ($new_status !== 'publish') {
        return;
    }

    if ($post->post_type !== 'post') {
        return;
    }

    if (get_post_meta($post->ID, '_tipo_publicacao', true) !== 'automacao') {
        return;
    }

    global $wpdb;

    $table = $wpdb->prefix . 'pipeline_logs';

    $wpdb->insert(
        $table,
        [
            'executed_at' => current_time('mysql'),
            'status'      => 'publicado',
            'criadas'     => 0,
            'atualizados' => 1,
            'ignorados'   => 0,
            'mensagem'    => 'Post publicado após revisão: ' . $post->post_title
        ]
    );

    }, 10, 3);

    add_action('transition_post_status', function ($new, $old, $post) {
        if ($old === 'pending' && $new === 'publish') {
            cnp_salvar_historico([
                'status'       => 'publicado',
                'criadas'      => 0,
                'atualizadas'  => 1,
                'ignoradas'    => 0,
                'mensagem'     => 'Notícia revisada e publicada manualmente'
            ]);
        }
    }, 10, 3);




}


