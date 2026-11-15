<?php
/**
 * TaxoCommerce SEO - Programador Social.
 *
 * MVP seguro para programar publicaciones futuras de entradas y landings en
 * los proveedores sociales ya conectados. No modifica la frecuencia SEO propia
 * de posts/landings y no republica automaticamente: cada tarea requiere una
 * programacion explicita.
 */

defined('ABSPATH') || exit;

if (!defined('TCSOCIAL_PROGRAMADOR_DB_VERSION')) {
    define('TCSOCIAL_PROGRAMADOR_DB_VERSION', 1);
}

if (!defined('TCSOCIAL_PROGRAMADOR_DB_VERSION_OPTION')) {
    define('TCSOCIAL_PROGRAMADOR_DB_VERSION_OPTION', 'seo_social_programador_db_version');
}

/**
 * @return string
 */
function tcsocial_programador_table()
{
    global $wpdb;
    return $wpdb->prefix . 'seo_social_schedule';
}

/**
 * Instala/actualiza la tabla del programador.
 */
function tcsocial_programador_maybe_install_table()
{
    $installed = (int) get_option(TCSOCIAL_PROGRAMADOR_DB_VERSION_OPTION, 0);
    if ($installed >= TCSOCIAL_PROGRAMADOR_DB_VERSION) {
        return;
    }

    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $table = tcsocial_programador_table();
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE {$table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        content_id bigint(20) unsigned NOT NULL,
        provider varchar(32) NOT NULL DEFAULT '',
        scheduled_at datetime NOT NULL,
        template longtext NULL,
        status varchar(24) NOT NULL DEFAULT 'scheduled',
        publication_id bigint(20) unsigned NOT NULL DEFAULT 0,
        source varchar(32) NOT NULL DEFAULT 'manual',
        notes text NULL,
        error_message text NULL,
        created_by bigint(20) unsigned NOT NULL DEFAULT 0,
        created_at datetime NOT NULL,
        updated_at datetime NOT NULL,
        executed_at datetime NULL,
        PRIMARY KEY  (id),
        KEY status_scheduled (status, scheduled_at),
        KEY content_provider (content_id, provider),
        KEY publication_id (publication_id)
    ) {$charset_collate};";

    dbDelta($sql);
    update_option(TCSOCIAL_PROGRAMADOR_DB_VERSION_OPTION, TCSOCIAL_PROGRAMADOR_DB_VERSION, false);
}
add_action('init', 'tcsocial_programador_maybe_install_table', 20);

/**
 * @return string
 */
function tcsocial_programador_admin_url($args = array())
{
    if (function_exists('tcsocial_network_admin_url')) {
        return tcsocial_network_admin_url('programador', is_array($args) ? $args : array());
    }

    return add_query_arg(
        array_merge(
            array(
                'page'          => 'taxocommerce-social',
                'tab'           => 'social',
                'social_subtab' => 'programador',
            ),
            is_array($args) ? $args : array()
        ),
        admin_url('admin.php')
    );
}

/**
 * Convierte datetime-local en timestamp respetando la zona horaria de WordPress.
 *
 * @param string $value
 * @return int|WP_Error
 */
function tcsocial_programador_parse_datetime($value)
{
    $value = trim((string) $value);
    if ($value === '') {
        return new WP_Error('missing_datetime', 'Selecciona fecha y hora de publicacion.');
    }

    try {
        $timezone = wp_timezone();
        $date = DateTimeImmutable::createFromFormat('Y-m-d\\TH:i', $value, $timezone);
        $errors = DateTimeImmutable::getLastErrors();
        if (false === $date || (is_array($errors) && (!empty($errors['warning_count']) || !empty($errors['error_count'])))) {
            return new WP_Error('invalid_datetime', 'La fecha y hora no son validas.');
        }
        return $date->getTimestamp();
    } catch (Exception $e) {
        return new WP_Error('invalid_datetime', 'La fecha y hora no son validas.');
    }
}

/**
 * @param int $timestamp
 * @return string
 */
function tcsocial_programador_mysql_from_timestamp($timestamp)
{
    return wp_date('Y-m-d H:i:s', (int) $timestamp, wp_timezone());
}


/**
 * Comprueba si una fecha/hora cae en la franja reservada a campañas.
 *
 * Las 18:00 quedan reservadas para publicaciones de campaña. El programador
 * editorial debe usar cualquier otra hora para evitar colisiones y mantener
 * ambos calendarios independientes.
 *
 * @param int $timestamp
 * @return bool
 */
function tcsocial_programador_is_campaign_reserved_time($timestamp)
{
    unset($timestamp);
    return false;
}

/**
 * @param int $schedule_id
 * @return object|null
 */
function tcsocial_programador_get_job($schedule_id)
{
    global $wpdb;
    $table = tcsocial_programador_table();

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Consulta por ID en tabla de programacion propia; SQL parametrizado, resultado debe reflejar el estado actual.
    return $wpdb->get_row(
        $wpdb->prepare('SELECT * FROM %i WHERE id = %d LIMIT 1', $table, absint($schedule_id))
    );
}

/**
 * Crea una tarea y su evento WP-Cron.
 *
 * @param int    $content_id
 * @param string $provider
 * @param int    $timestamp
 * @param string $template
 * @param string $source
 * @param string $notes
 * @return int|WP_Error
 */
function tcsocial_programador_create_job($content_id, $provider, $timestamp, $template = '', $source = 'manual', $notes = '')
{
    tcsocial_programador_maybe_install_table();

    $content_id = absint($content_id);
    $provider = sanitize_key($provider);
    $timestamp = (int) $timestamp;
    $post = get_post($content_id);

    if (!$post || 'publish' !== $post->post_status) {
        return new WP_Error('invalid_content', 'El contenido debe estar publicado en WordPress antes de programarlo.');
    }

    if (!function_exists('tcsocial_network_supported_post_types') || !in_array($post->post_type, tcsocial_network_supported_post_types(), true)) {
        return new WP_Error('unsupported_content', 'Este tipo de contenido no esta habilitado para redes sociales.');
    }

    $provider_config = function_exists('tcsocial_network_get_provider') ? tcsocial_network_get_provider($provider) : null;
    if (!$provider_config) {
        return new WP_Error('invalid_provider', 'La red social seleccionada no esta disponible.');
    }

    $settings = function_exists('tcsocial_network_get_settings') ? tcsocial_network_get_settings() : array();
    if (empty($settings['providers'][$provider]['enabled'])) {
        return new WP_Error('provider_disconnected', 'La red social seleccionada no esta conectada.');
    }

    if ($timestamp <= (time() + 30)) {
        return new WP_Error('past_datetime', 'Programa la publicacion al menos un minuto en el futuro.');
    }

    global $wpdb;
    $table = tcsocial_programador_table();
    $now = current_time('mysql');

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Insercion de tarea en tabla propia, persistencia inmediata para WP-Cron.
    $inserted = $wpdb->insert(
        $table,
        array(
            'content_id'    => $content_id,
            'provider'      => $provider,
            'scheduled_at'  => tcsocial_programador_mysql_from_timestamp($timestamp),
            'template'      => (string) $template,
            'status'        => 'scheduled',
            'publication_id'=> 0,
            'source'        => sanitize_key($source) ?: 'manual',
            'notes'         => sanitize_textarea_field($notes),
            'error_message' => '',
            'created_by'    => get_current_user_id(),
            'created_at'    => $now,
            'updated_at'    => $now,
        ),
        array('%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%d', '%s', '%s')
    );

    if (false === $inserted) {
        return new WP_Error('db_insert_failed', 'No se pudo guardar la publicacion programada.');
    }

    $schedule_id = (int) $wpdb->insert_id;
    $args = array($schedule_id);
    if (!wp_next_scheduled('tcsocial_programador_run_job', $args)) {
        $scheduled = wp_schedule_single_event($timestamp, 'tcsocial_programador_run_job', $args, true);
        if (is_wp_error($scheduled) || !$scheduled) {
            // No indicar éxito si WordPress no ha creado el evento.
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Estado de fallo de una tarea propia: persistencia inmediata necesaria para no informar éxito falso.
            $wpdb->update(
                $table,
                array(
                    'status' => 'failed',
                    'error_message' => 'No se ha podido registrar el evento WP-Cron.',
                    'updated_at' => current_time('mysql'),
                ),
                array('id' => $schedule_id),
                array('%s', '%s', '%s'),
                array('%d')
            );
            return new WP_Error('cron_schedule_failed', 'La publicación no se ha programado: WP-Cron ha rechazado el evento.');
        }
    }

    return $schedule_id;
}

/**
 * Ejecuta una tarea concreta.
 *
 * @param int $schedule_id
 */
function tcsocial_programador_run_job($schedule_id)
{
    $schedule_id = absint($schedule_id);
    $job = tcsocial_programador_get_job($schedule_id);
    if (!$job || 'scheduled' !== $job->status) {
        return;
    }

    global $wpdb;
    $table = tcsocial_programador_table();
    $now = current_time('mysql');

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Actualizacion inmediata del estado de tarea en tabla propia; no se cachean las escrituras.
    $wpdb->update(
        $table,
        array(
            'status'     => 'processing',
            'updated_at' => $now,
        ),
        array('id' => $schedule_id),
        array('%s', '%s'),
        array('%d')
    );

    if (!function_exists('tcsocial_network_publish_content')) {
        $result = new WP_Error('publisher_unavailable', 'El publicador social no esta disponible.');
    } else {
        $result = tcsocial_network_publish_content(
            (int) $job->content_id,
            (string) $job->provider,
            (string) $job->template
        );
    }

    $now = current_time('mysql');
    if (is_wp_error($result)) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Actualizacion inmediata del estado de tarea en tabla propia; no se cachean las escrituras.
        $wpdb->update(
            $table,
            array(
                'status'        => 'failed',
                'error_message' => $result->get_error_message(),
                'executed_at'   => $now,
                'updated_at'    => $now,
            ),
            array('id' => $schedule_id),
            array('%s', '%s', '%s', '%s'),
            array('%d')
        );
        do_action('tcsocial_programador_job_failed', $schedule_id, $job, $result);
        return;
    }

    $publication_id = isset($result['publication_id']) ? absint($result['publication_id']) : 0;
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Actualizacion inmediata del estado de tarea en tabla propia; no se cachean las escrituras.
    $wpdb->update(
        $table,
        array(
            'status'         => 'published',
            'publication_id' => $publication_id,
            'error_message'  => '',
            'executed_at'    => $now,
            'updated_at'     => $now,
        ),
        array('id' => $schedule_id),
        array('%s', '%d', '%s', '%s', '%s'),
        array('%d')
    );

    do_action('tcsocial_programador_job_published', $schedule_id, $job, $result);
}
add_action('tcsocial_programador_run_job', 'tcsocial_programador_run_job', 10, 1);

/**
 * Guarda una o varias tareas desde wp-admin.
 */
function tcsocial_programador_handle_create()
{
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('No tienes permisos para programar publicaciones.', 'taxocommerce-social'));
    }

    check_admin_referer('tcsocial_programador_create');

    $content_id = isset($_POST['content_id']) ? absint($_POST['content_id']) : 0;
    $providers = isset($_POST['providers']) && is_array($_POST['providers'])
        ? array_values(array_unique(array_filter(array_map('sanitize_key', wp_unslash($_POST['providers'])))))
        : array();
    $when = isset($_POST['scheduled_at']) ? sanitize_text_field(wp_unslash($_POST['scheduled_at'])) : '';
    $template = isset($_POST['message_template']) ? sanitize_textarea_field(wp_unslash($_POST['message_template'])) : '';
    $notes = isset($_POST['notes']) ? sanitize_textarea_field(wp_unslash($_POST['notes'])) : '';

    if (!$content_id || empty($providers)) {
        wp_safe_redirect(tcsocial_programador_admin_url(array('programador_msg' => 'missing_fields')));
        exit;
    }

    $timestamp = tcsocial_programador_parse_datetime($when);
    if (is_wp_error($timestamp)) {
        set_transient('tcsocial_programador_error_' . get_current_user_id(), $timestamp->get_error_message(), MINUTE_IN_SECONDS);
        wp_safe_redirect(tcsocial_programador_admin_url(array('programador_msg' => 'error')));
        exit;
    }

    $created = 0;
    $errors = array();
    foreach ($providers as $provider) {
        $result = tcsocial_programador_create_job($content_id, $provider, $timestamp, $template, 'manual', $notes);
        if (is_wp_error($result)) {
            $errors[] = $result->get_error_message();
        } else {
            $created++;
        }
    }

    if (!empty($errors)) {
        set_transient('tcsocial_programador_error_' . get_current_user_id(), implode(' ', array_unique($errors)), MINUTE_IN_SECONDS);
    }

    wp_safe_redirect(
        tcsocial_programador_admin_url(
            array(
                'programador_msg' => $created > 0 ? 'created' : 'error',
                'created'         => $created,
            )
        )
    );
    exit;
}
add_action('admin_post_tcsocial_programador_create', 'tcsocial_programador_handle_create');

/**
 * Cancela una tarea aun no ejecutada.
 */
function tcsocial_programador_handle_cancel()
{
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('No tienes permisos para cancelar publicaciones.', 'taxocommerce-social'));
    }

    $schedule_id = isset($_POST['schedule_id']) ? absint($_POST['schedule_id']) : 0;
    check_admin_referer('tcsocial_programador_cancel_' . $schedule_id);

    $job = tcsocial_programador_get_job($schedule_id);
    if ($job && 'scheduled' === $job->status) {
        $args = array($schedule_id);
        $timestamp = wp_next_scheduled('tcsocial_programador_run_job', $args);
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'tcsocial_programador_run_job', $args);
        }

        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Actualizacion inmediata del estado de tarea en tabla propia; no se cachean las escrituras.
        $wpdb->update(
            tcsocial_programador_table(),
            array(
                'status'     => 'cancelled',
                'updated_at' => current_time('mysql'),
            ),
            array('id' => $schedule_id),
            array('%s', '%s'),
            array('%d')
        );
    }

    wp_safe_redirect(tcsocial_programador_admin_url(array('programador_msg' => 'cancelled')));
    exit;
}
add_action('admin_post_tcsocial_programador_cancel', 'tcsocial_programador_handle_cancel');

/**
 * Reprograma una tarea fallida para ejecutarla inmediatamente.
 */
function tcsocial_programador_handle_retry()
{
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('No tienes permisos para reintentar publicaciones.', 'taxocommerce-social'));
    }

    $schedule_id = isset($_POST['schedule_id']) ? absint($_POST['schedule_id']) : 0;
    check_admin_referer('tcsocial_programador_retry_' . $schedule_id);

    $job = tcsocial_programador_get_job($schedule_id);
    if ($job && in_array($job->status, array('failed', 'cancelled'), true)) {
        global $wpdb;
        $run_at = time() + 10;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Actualizacion inmediata del estado de tarea en tabla propia; no se cachean las escrituras.
        $wpdb->update(
            tcsocial_programador_table(),
            array(
                'status'        => 'scheduled',
                'scheduled_at'  => tcsocial_programador_mysql_from_timestamp($run_at),
                'error_message' => '',
                'executed_at'   => null,
                'updated_at'    => current_time('mysql'),
            ),
            array('id' => $schedule_id),
            array('%s', '%s', '%s', '%s', '%s'),
            array('%d')
        );
        $scheduled = wp_schedule_single_event($run_at, 'tcsocial_programador_run_job', array($schedule_id), true);
        if (is_wp_error($scheduled) || !$scheduled) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Guardado inmediato de rechazo de reintento en tabla propia.
            $wpdb->update(
                tcsocial_programador_table(),
                array('status' => 'failed', 'error_message' => 'WP-Cron ha rechazado el reintento.', 'updated_at' => current_time('mysql')),
                array('id' => $schedule_id),
                array('%s', '%s', '%s'),
                array('%d')
            );
            wp_safe_redirect(tcsocial_programador_admin_url(array('programador_msg' => 'error')));
            exit;
        }
    }

    wp_safe_redirect(tcsocial_programador_admin_url(array('programador_msg' => 'retry')));
    exit;
}
add_action('admin_post_tcsocial_programador_retry', 'tcsocial_programador_handle_retry');

/**
 * @return array
 */
function tcsocial_programador_recent_content()
{
    return get_posts(
        array(
            'post_type'              => function_exists('tcsocial_network_supported_post_types') ? tcsocial_network_supported_post_types() : array('post', 'page'),
            'post_status'            => 'publish',
            'posts_per_page'         => 150,
            'orderby'                => 'modified',
            'order'                  => 'DESC',
            'no_found_rows'          => true,
            'ignore_sticky_posts'    => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        )
    );
}

/**
 * @param int $limit
 * @return array
 */
function tcsocial_programador_jobs($limit = 100)
{
    global $wpdb;
    $table = tcsocial_programador_table();
    $limit = min(250, max(1, absint($limit)));

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Listado de tareas en tabla propia sin API alternativa; SQL %i/%d y estado actualizado sin cache.
    return $wpdb->get_results(
        $wpdb->prepare("SELECT * FROM %i ORDER BY FIELD(status, 'processing', 'scheduled', 'failed', 'published', 'cancelled'), scheduled_at ASC, id DESC LIMIT %d", $table, $limit)
    );
}

/**
 * Avisos propios del Programador.
 */
function tcsocial_programador_render_notice()
{
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Mensaje informativo de redirección; no modifica datos.
    $message = isset($_GET['programador_msg']) ? sanitize_key(wp_unslash($_GET['programador_msg'])) : '';
    if ($message === '') {
        return;
    }

    $type = 'success';
    $text = '';
    if ('created' === $message) {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Contador únicamente visible en aviso, no modifica programación.
        $created = isset($_GET['created']) ? absint($_GET['created']) : 0;
        $text = 'Publicacion programada correctamente' . ($created > 1 ? ' en ' . $created . ' redes.' : '.');
        $extra = get_transient('tcsocial_programador_error_' . get_current_user_id());
        if ($extra) {
            delete_transient('tcsocial_programador_error_' . get_current_user_id());
            $text .= ' Algunas redes no pudieron programarse: ' . $extra;
            $type = 'warning';
        }
    } elseif ('cancelled' === $message) {
        $text = 'Publicacion programada cancelada.';
    } elseif ('retry' === $message) {
        $text = 'La tarea se ha puesto de nuevo en cola para ejecutarse ahora.';
    } elseif ('missing_fields' === $message) {
        $type = 'error';
        $text = 'Selecciona contenido y al menos una red social.';
    } elseif ('error' === $message) {
        $type = 'error';
        $text = get_transient('tcsocial_programador_error_' . get_current_user_id());
        if ($text) {
            delete_transient('tcsocial_programador_error_' . get_current_user_id());
        } else {
            $text = 'No se pudo crear la programacion.';
        }
    }

    if ($text !== '') {
        echo '<div class="notice notice-' . esc_attr($type) . ' is-dismissible"><p>' . esc_html($text) . '</p></div>';
    }
}

/**
 * Render de la subpestana Programador.
 */
function tcsocial_programador_render_admin()
{
    tcsocial_programador_maybe_install_table();
    tcsocial_programador_render_notice();

    $settings = function_exists('tcsocial_network_get_settings') ? tcsocial_network_get_settings() : array();
    $providers = function_exists('tcsocial_network_get_providers') ? tcsocial_network_get_providers() : array();
    $connected = array();
    foreach ($providers as $provider_key => $provider) {
        if (!empty($settings['providers'][$provider_key]['enabled'])) {
            $connected[$provider_key] = $provider;
        }
    }

    $default_timestamp = time() + HOUR_IN_SECONDS;
    $default_datetime = wp_date('Y-m-d\\TH:i', $default_timestamp, wp_timezone());
    $contents = tcsocial_programador_recent_content();

    echo '<section class="seo-social-card">';
    echo '<div style="display:flex;justify-content:space-between;gap:20px;align-items:flex-start;flex-wrap:wrap">';
    echo '<div>' . '<h2>' . esc_html__( 'Programador', 'taxocommerce-social' ) . '</h2>' . '<p>' . esc_html__( 'Agenda la publicacion de entradas y landings en las redes conectadas. Esta primera version solo crea ejecuciones unicas: no modifica la frecuencia SEO ni republica contenido por su cuenta.', 'taxocommerce-social' ) . '</p>' . '</div>';
    echo '<span class="seo-social-state is-pending">' . esc_html__( 'MVP · programacion manual', 'taxocommerce-social' ) . '</span>';
    echo '</div>';

    if (empty($connected)) {
        echo '<p class="seo-social-code-note">' . esc_html__( 'Conecta al menos una red social antes de crear programaciones.', 'taxocommerce-social' ) . '</p>';
    }

    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
    echo '<input type="hidden" name="action" value="tcsocial_programador_create">';
    wp_nonce_field('tcsocial_programador_create');

    echo '<div class="seo-social-grid" style="margin-top:18px">';
    echo '<div class="seo-social-field">' . '<label>' . esc_html__( 'Contenido', 'taxocommerce-social' ) . '</label>' . '<select name="content_id" required>' . '<option value="">' . esc_html__( 'Selecciona entrada o landing...', 'taxocommerce-social' ) . '</option>';
    foreach ($contents as $content) {
        if (!$content instanceof WP_Post) {
            continue;
        }
        $label = function_exists('tcsocial_network_content_type_label') ? tcsocial_network_content_type_label($content) : $content->post_type;
        echo '<option value="' . esc_attr((string) $content->ID) . '">' . esc_html('[' . $label . '] ' . get_the_title($content) . ' · #' . $content->ID) . '</option>';
    }
    echo '</select>' . '<p class="seo-social-help">' . esc_html__( 'Se muestran los 150 contenidos publicados modificados mas recientemente.', 'taxocommerce-social' ) . '</p>' . '</div>';

    echo '<div class="seo-social-field"><label>Fecha y hora</label><input type="datetime-local" name="scheduled_at" value="' . esc_attr($default_datetime) . '" required><p class="seo-social-help">Zona horaria WordPress: ' . esc_html(wp_timezone_string()) . '. Elige libremente la fecha y hora de publicación.</p></div>';
    echo '</div>';

    echo '<div class="seo-social-field" style="margin-top:14px">' . '<label>' . esc_html__( 'Publicar en', 'taxocommerce-social' ) . '</label>' . '<div style="display:flex;gap:18px;flex-wrap:wrap;padding:10px 0">';
    foreach ($providers as $provider_key => $provider) {
        $is_connected = isset($connected[$provider_key]);
        $label = isset($provider['label']) ? $provider['label'] : ucfirst($provider_key);
        echo '<label style="font-weight:500"><input type="checkbox" name="providers[]" value="' . esc_attr($provider_key) . '" ' . disabled(!$is_connected, true, false) . '> ' . esc_html($label) . ($is_connected ? '' : ' (sin conectar)') . '</label>';
    }
    echo '</div></div>';

    echo '<div class="seo-social-field" style="margin-top:8px"><label>Texto / plantilla opcional</label><textarea name="message_template" placeholder="Dejalo vacio para usar la plantilla vigente de cada red en el momento de publicar."></textarea><p class="seo-social-help">Si lo rellenas, este texto se usara como plantilla para todas las redes seleccionadas. Puedes usar {titulo}, {extracto}, {fecha}, {url}, {sitio}, {autor}, {tipo}, {categorias} e {imagen}.</p></div>';
    echo '<div class="seo-social-field" style="margin-top:12px">' . '<label>' . esc_html__( 'Nota interna opcional', 'taxocommerce-social' ) . '</label>' . '<input type="text" name="notes" value="" placeholder="Ej.: lanzamiento, noticia proveedor, campana septiembre...">' . '</div>';

    echo '<div class="seo-social-actions"><button type="submit" class="button button-primary" ' . disabled(empty($connected), true, false) . '>Programar publicacion</button>';
    echo '<span class="seo-social-help">' . esc_html__( 'La ejecucion usa WP-Cron. Mas adelante podremos anadir recurrencia, prioridades y recomendaciones de Analista.', 'taxocommerce-social' ) . '</span>' . '</div>';
    echo '</form>';
    echo '</section>';

    $jobs = tcsocial_programador_jobs(100);
    echo '<section class="seo-social-card">';
    echo '<h2>' . esc_html__( 'Cola y historial', 'taxocommerce-social' ) . '</h2>';
    echo '<div class="seo-social-table-wrap">' . '<table class="seo-social-table">' . '<thead>' . '<tr>' . '<th>' . esc_html__( 'Fecha', 'taxocommerce-social' ) . '</th>' . '<th>' . esc_html__( 'Contenido', 'taxocommerce-social' ) . '</th>' . '<th>' . esc_html__( 'Red', 'taxocommerce-social' ) . '</th>' . '<th>' . esc_html__( 'Estado', 'taxocommerce-social' ) . '</th>' . '<th>' . esc_html__( 'Origen / nota', 'taxocommerce-social' ) . '</th>' . '<th>' . esc_html__( 'Acciones', 'taxocommerce-social' ) . '</th>' . '</tr>' . '</thead>' . '<tbody>';

    if (empty($jobs)) {
        echo '<tr>' . '<td colspan="6">' . esc_html__( 'Todavia no hay publicaciones programadas.', 'taxocommerce-social' ) . '</td>' . '</tr>';
    }

    foreach ($jobs as $job) {
        $post = get_post((int) $job->content_id);
        $provider = function_exists('tcsocial_network_get_provider') ? tcsocial_network_get_provider((string) $job->provider) : null;
        $provider_label = $provider && isset($provider['label']) ? $provider['label'] : ucfirst((string) $job->provider);
        $status = sanitize_key((string) $job->status);
        $status_label = array(
            'scheduled'  => 'Programada',
            'processing' => 'Publicando',
            'published'  => 'Publicada',
            'failed'     => 'Fallida',
            'cancelled'  => 'Cancelada',
        );
        $status_text = isset($status_label[$status]) ? $status_label[$status] : ucfirst($status);

        echo '<tr>';
        echo '<td><strong>' . esc_html(mysql2date(get_option('date_format') . ' ' . get_option('time_format'), (string) $job->scheduled_at)) . '</strong>';
        if (!empty($job->executed_at)) {
            echo '<br><small>Ejecutada: ' . esc_html(mysql2date(get_option('date_format') . ' ' . get_option('time_format'), (string) $job->executed_at)) . '</small>';
        }
        echo '</td>';

        echo '<td>';
        if ($post) {
            echo '<strong><a href="' . esc_url(get_edit_post_link($post->ID)) . '">' . esc_html(get_the_title($post)) . '</a></strong><br><small>#' . esc_html((string) $post->ID) . ' · ' . esc_html($post->post_type) . '</small>';
        } else {
            echo '<span style="color:#b32d2e">Contenido #' . esc_html((string) $job->content_id) . ' no disponible</span>';
        }
        echo '</td>';

        echo '<td>' . esc_html($provider_label) . '</td>';
        echo '<td><span class="seo-social-state is-' . esc_attr($status) . '">' . esc_html($status_text) . '</span>';
        if ('failed' === $status && !empty($job->error_message)) {
            echo '<br><small style="color:#b32d2e">' . esc_html(wp_trim_words((string) $job->error_message, 18, '...')) . '</small>';
        }
        if ('published' === $status && !empty($job->publication_id)) {
            global $wpdb;
            $pub_table = function_exists('tcsocial_network_publications_table') ? tcsocial_network_publications_table() : '';
            if ($pub_table !== '') {
                $remote_url = $wpdb->get_var($wpdb->prepare('SELECT remote_url FROM %i WHERE id = %d', $pub_table, absint($job->publication_id))); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Consulta de URL actual en tabla propia, con identificador y valor preparados; no procede cache.
                if ($remote_url) {
                    echo '<br><a href="' . esc_url($remote_url) . '" target="_blank" rel="noopener noreferrer">Ver publicacion</a>';
                }
            }
        }
        echo '</td>';

        echo '<td><small>' . esc_html((string) $job->source) . '</small>';
        if (!empty($job->notes)) {
            echo '<br>' . esc_html((string) $job->notes);
        }
        echo '</td>';

        echo '<td>';
        if ('scheduled' === $status) {
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
            echo '<input type="hidden" name="action" value="tcsocial_programador_cancel"><input type="hidden" name="schedule_id" value="' . esc_attr((string) $job->id) . '">';
            wp_nonce_field('tcsocial_programador_cancel_' . (int) $job->id);
            echo '<button class="button" type="submit">' . esc_html__( 'Cancelar', 'taxocommerce-social' ) . '</button>' . '</form>';
        } elseif (in_array($status, array('failed', 'cancelled'), true)) {
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
            echo '<input type="hidden" name="action" value="tcsocial_programador_retry"><input type="hidden" name="schedule_id" value="' . esc_attr((string) $job->id) . '">';
            wp_nonce_field('tcsocial_programador_retry_' . (int) $job->id);
            echo '<button class="button" type="submit">' . esc_html__( 'Reintentar ahora', 'taxocommerce-social' ) . '</button>' . '</form>';
        } else {
            echo '<span class="seo-social-help">-</span>';
        }
        echo '</td>';
        echo '</tr>';
    }

    echo '</tbody></table></div>';
    echo '</section>';

    echo '<section class="seo-social-card">';
    echo '<h2>' . esc_html__( 'Siguiente evolucion', 'taxocommerce-social' ) . '</h2>';
    echo '<p class="seo-social-help" style="font-size:13px">La tabla ya conserva <code>source</code> y <code>notes</code> para que mas adelante Analista pueda proponer tareas con prioridad y motivo. La recurrencia no se activa en esta version para evitar republicar automaticamente contenido sin revisar si necesita cambios.</p>';
    echo '</section>';
}
