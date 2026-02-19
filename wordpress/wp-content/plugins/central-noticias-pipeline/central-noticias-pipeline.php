<?php
/**
 * Plugin Name: Central de Noticias - Atualizacao Automatica
 * Description: Executa o pipeline de noticias Econet com 1 clique no painel.
 * Version: 1.1
 */

if (!defined('ABSPATH')) {
    exit;
}

function cnp_user_can_run(): bool
{
    return current_user_can('edit_others_posts');
}

function cnp_logs_table_name(): string
{
    global $wpdb;
    return $wpdb->prefix . 'pipeline_logs';
}

function cnp_pipeline_file_path(): string
{
    return dirname(ABSPATH) . '/automacao/pipeline.php';
}

function cnp_install_logs_table(): void
{
    global $wpdb;

    $table = cnp_logs_table_name();
    $charset = $wpdb->get_charset_collate();

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    dbDelta("
        CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            executed_at datetime NOT NULL,
            status varchar(20) NOT NULL DEFAULT '',
            coletadas int(11) NOT NULL DEFAULT 0,
            criadas int(11) NOT NULL DEFAULT 0,
            atualizados int(11) NOT NULL DEFAULT 0,
            ignorados int(11) NOT NULL DEFAULT 0,
            mensagem text NULL,
            PRIMARY KEY  (id),
            KEY executed_at (executed_at)
        ) {$charset};
    ");
}

function cnp_maybe_upgrade_logs_table(): void
{
    $currentVersion = get_option('cnp_pipeline_db_version', '');
    $targetVersion = '1.1';

    if ($currentVersion === $targetVersion) {
        return;
    }

    cnp_install_logs_table();
    update_option('cnp_pipeline_db_version', $targetVersion);
}

register_activation_hook(__FILE__, 'cnp_maybe_upgrade_logs_table');
add_action('admin_init', 'cnp_maybe_upgrade_logs_table');

function cnp_salvar_historico(array $dados): void
{
    global $wpdb;

    $table = cnp_logs_table_name();

    $dados = wp_parse_args($dados, [
        'status' => 'ok',
        'coletadas' => 0,
        'criadas' => 0,
        'atualizados' => 0,
        'ignorados' => 0,
        'mensagem' => '',
    ]);

    $wpdb->insert(
        $table,
        [
            'executed_at' => current_time('mysql'),
            'status' => (string) $dados['status'],
            'coletadas' => (int) $dados['coletadas'],
            'criadas' => (int) $dados['criadas'],
            'atualizados' => (int) $dados['atualizados'],
            'ignorados' => (int) $dados['ignorados'],
            'mensagem' => (string) $dados['mensagem'],
        ],
        ['%s', '%s', '%d', '%d', '%d', '%d', '%s']
    );
}

add_action('admin_menu', function (): void {
    add_menu_page(
        'Central de Noticias',
        'Central de Noticias',
        'edit_others_posts',
        'central-noticias',
        'cnp_render_page',
        'dashicons-megaphone',
        3
    );

    add_submenu_page(
        'central-noticias',
        'Historico',
        'Historico',
        'edit_others_posts',
        'central-noticias-historico',
        'cnp_render_historico'
    );
});

function cnp_render_page(): void
{
    if (!cnp_user_can_run()) {
        wp_die('Sem permissao.');
    }

    echo '<div class="wrap">';
    echo '<h1>Central de Noticias</h1>';

    if (isset($_POST['executar_pipeline'])) {
        check_admin_referer('cnp_run_pipeline');

        $pipelinePath = cnp_pipeline_file_path();
        if (!is_file($pipelinePath)) {
            echo '<div class="notice notice-error"><p>Pipeline nao encontrado.</p></div>';
        } else {
            require_once $pipelinePath;

            if (!function_exists('runPipeline')) {
                echo '<div class="notice notice-error"><p>Pipeline nao encontrado.</p></div>';
            } else {
                $resultado = runPipeline();

                if (($resultado['status'] ?? '') === 'locked') {
                    echo '<div class="notice notice-warning"><p>' . esc_html((string) ($resultado['mensagem'] ?? 'Pipeline em execucao.')) . '</p></div>';
                } elseif (($resultado['status'] ?? '') === 'error') {
                    echo '<div class="notice notice-error"><p>' . esc_html((string) ($resultado['mensagem'] ?? 'Falha ao executar pipeline.')) . '</p></div>';
                } else {
                    echo '<div class="notice notice-success">';
                    echo '<p><strong>Atualizacao concluida.</strong></p>';
                    echo '<ul>';
                    echo '<li>Noticias coletadas: ' . (int) ($resultado['coletadas'] ?? 0) . '</li>';
                    echo '<li>Noticias criadas (pendentes): ' . (int) ($resultado['criadas'] ?? 0) . '</li>';
                    echo '<li>Noticias atualizadas: ' . (int) ($resultado['atualizados'] ?? 0) . '</li>';
                    echo '<li>Ignoradas: ' . (int) ($resultado['ignorados'] ?? 0) . '</li>';
                    echo '</ul>';
                    echo '</div>';

                    cnp_salvar_historico($resultado);
                }
            }
        }
    }

    echo '<form method="post">';
    wp_nonce_field('cnp_run_pipeline');
    echo '<p>Este botao busca novas noticias, reformula o conteudo e envia para revisao.</p>';
    echo '<p><strong>A operacao pode levar ate 1 minuto.</strong></p>';
    echo '<button class="button button-primary button-hero" name="executar_pipeline">Atualizar Noticias Agora</button>';
    echo '</form>';
    echo '</div>';
}

function cnp_render_historico(): void
{
    global $wpdb;

    $table = cnp_logs_table_name();
    $logs = $wpdb->get_results("SELECT * FROM {$table} ORDER BY executed_at DESC LIMIT 50");

    echo '<div class="wrap">';
    echo '<h1>Historico de Execucoes</h1>';
    echo '<table class="widefat striped">';
    echo '<thead><tr>';
    echo '<th>Data</th><th>Status</th><th>Coletadas</th><th>Criadas</th><th>Atualizados</th><th>Ignorados</th><th>Mensagem</th>';
    echo '</tr></thead><tbody>';

    if (!$logs) {
        echo '<tr><td colspan="7">Nenhum historico encontrado.</td></tr>';
    } else {
        foreach ($logs as $log) {
            $status = (string) ($log->status ?? '');
            $statusColor = $status === 'ok' ? '#2ecc71' : '#e74c3c';

            echo '<tr>';
            echo '<td>' . esc_html((string) ($log->executed_at ?? '')) . '</td>';
            echo '<td style="color:' . esc_attr($statusColor) . ';">' . esc_html($status) . '</td>';
            echo '<td>' . (int) ($log->coletadas ?? 0) . '</td>';
            echo '<td>' . (int) ($log->criadas ?? 0) . '</td>';
            echo '<td>' . (int) ($log->atualizados ?? 0) . '</td>';
            echo '<td>' . (int) ($log->ignorados ?? 0) . '</td>';
            echo '<td>' . esc_html((string) ($log->mensagem ?? '')) . '</td>';
            echo '</tr>';
        }
    }

    echo '</tbody></table>';
    echo '</div>';
}

function cnp_registrar_publicacao_manual(string $new_status, string $old_status, $post): void
{
    if ($old_status !== 'pending' || $new_status !== 'publish') {
        return;
    }

    if (!$post instanceof WP_Post || $post->post_type !== 'post') {
        return;
    }

    if (get_post_meta($post->ID, '_conteudo_ia', true) !== 'sim') {
        return;
    }

    cnp_salvar_historico([
        'status' => 'publicado',
        'coletadas' => 0,
        'criadas' => 0,
        'atualizados' => 1,
        'ignorados' => 0,
        'mensagem' => 'Noticia revisada e publicada manualmente: ' . $post->post_title,
    ]);
}

add_action('transition_post_status', 'cnp_registrar_publicacao_manual', 10, 3);
