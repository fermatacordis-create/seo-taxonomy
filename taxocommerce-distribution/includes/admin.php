<?php
/**
 * TaxoCommerce Distribution administration.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'admin_menu', 'taxocommerce_distribution_register_menu', 42 );
add_action( 'admin_post_tcdist_sitemap_regenerate', 'taxocommerce_distribution_handle_sitemap_regenerate' );
add_action( 'admin_post_tcdist_sitemap_speed', 'taxocommerce_distribution_save_sitemap_speed' );
add_action( 'admin_post_tcdist_sitemap_ownership', 'taxocommerce_distribution_save_sitemap_ownership' );
add_action( 'admin_post_tcdist_sitemap_stop', 'taxocommerce_distribution_stop_sitemap' );
add_action( 'wp_ajax_tcdist_sitemap_pulse', 'taxocommerce_distribution_sitemap_ajax_pulse' );
add_action( 'admin_enqueue_scripts', 'taxocommerce_distribution_sitemap_enqueue' );

function taxocommerce_distribution_capability() {
    return (string) apply_filters( 'taxocommerce_distribution_capability', 'manage_woocommerce' );
}

function taxocommerce_distribution_guard() {
    if ( ! current_user_can( taxocommerce_distribution_capability() ) && ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have permission to manage TaxoCommerce Distribution.', 'taxocommerce-distribution' ) );
    }
}

function taxocommerce_distribution_register_menu() {
    $capability = taxocommerce_distribution_capability();

    if ( function_exists( 'taxocommerce_core' ) ) {
        add_submenu_page(
            'taxocommerce-core',
            __( 'Distribution', 'taxocommerce-distribution' ),
            __( 'Distribution', 'taxocommerce-distribution' ),
            $capability,
            'taxocommerce-distribution',
            'taxocommerce_distribution_render_page'
        );
        return;
    }

    add_menu_page(
        __( 'TaxoCommerce Distribution', 'taxocommerce-distribution' ),
        __( 'Distribution', 'taxocommerce-distribution' ),
        $capability,
        'taxocommerce-distribution',
        'taxocommerce_distribution_render_page',
        'dashicons-rss',
        59
    );
}

function taxocommerce_distribution_url( $tab = 'feeds', $args = array() ) {
    return add_query_arg(
        array_merge(
            array(
                'page'       => 'taxocommerce-distribution',
                'tcdist_tab' => sanitize_key( $tab ),
            ),
            $args
        ),
        admin_url( 'admin.php' )
    );
}

function taxocommerce_distribution_handle_sitemap_regenerate() {
    taxocommerce_distribution_guard();
    check_admin_referer( 'tcdist_sitemap_regenerate' );

    $result = tcdist_sitemap_create_sitemaps();
    $args = array();
    if ( is_array( $result ) && 'success' === (string) ( $result['type'] ?? '' ) ) {
        $args['tcdist_sitemap_result'] = 'ok';
        $args['tcdist_sitemap_message'] = rawurlencode( wp_strip_all_tags( (string) ( $result['message'] ?? '' ) ) );
    } else {
        $args['tcdist_sitemap_result'] = 'error';
        $message = is_array( $result ) ? (string) ( $result['message'] ?? '' ) : __( 'Resultado desconocido.', 'taxocommerce-distribution' );
        $args['tcdist_sitemap_message'] = rawurlencode( wp_strip_all_tags( $message ) );
    }

    wp_safe_redirect( taxocommerce_distribution_url( 'sitemaps', $args ) );
    exit;
}


/**
 * Store explicit permission to publish the site's XML sitemap.
 *
 * The setting defaults to enabled for backwards compatibility. External
 * plugins may still claim ownership via the existing WordPress filter.
 */
function taxocommerce_distribution_save_sitemap_ownership() {
    taxocommerce_distribution_guard();
    check_admin_referer( 'tcdist_sitemap_ownership' );

    $enabled = isset( $_POST['tcdist_sitemap_enabled'] )
        && is_string( $_POST['tcdist_sitemap_enabled'] )
        && '1' === sanitize_text_field( wp_unslash( $_POST['tcdist_sitemap_enabled'] ) );
    $previous = tcdist_sitemap_enabled();

    // A running publisher must be stopped explicitly before switching owners.
    if ( ! $enabled && $previous ) {
        $state = tcdist_sitemap_job_state();
        if ( 'running' === ( $state['status'] ?? '' ) ) {
            wp_safe_redirect( taxocommerce_distribution_url( 'sitemaps', array(
                'tcdist_sitemap_result'  => 'error',
                'tcdist_sitemap_message' => rawurlencode( __( 'Detén primero la generación en curso antes de ceder el control del sitemap.', 'taxocommerce-distribution' ) ),
            ) ) );
            exit;
        }
    }

    update_option( 'tcdist_sitemap_enabled', $enabled ? 'yes' : 'no', false );

    if ( ! $enabled ) {
        wp_clear_scheduled_hook( TCDIST_SITEMAP_DAILY_HOOK );
        wp_clear_scheduled_hook( TCDIST_SITEMAP_WORKER_HOOK );
        wp_clear_scheduled_hook( 'tcdist_sitemap_regenerate_sitemaps_event' );
    }

    // Rebuild the public endpoint rules so native WordPress (or another
    // provider) can serve its own sitemap when Distribution is disabled.
    flush_rewrite_rules( false );

    wp_safe_redirect( taxocommerce_distribution_url( 'sitemaps', array(
        'tcdist_sitemap_result'  => 'ok',
        'tcdist_sitemap_message' => rawurlencode( $enabled
            ? __( 'Gestión de sitemaps habilitada en Distribution.', 'taxocommerce-distribution' )
            : __( 'Distribution ha cedido la gestión del sitemap. Los XML existentes se conservan.', 'taxocommerce-distribution' ) ),
    ) ) );
    exit;
}

/**
 * Schedule-safe priority controls; no changes to other TaxoCommerce plugins.
 */
function taxocommerce_distribution_save_sitemap_speed() {
    taxocommerce_distribution_guard();
    check_admin_referer( 'tcdist_sitemap_speed' );
    $raw = isset( $_POST['tcdist_sitemap_speed'] ) && is_scalar( $_POST['tcdist_sitemap_speed'] )
        ? sanitize_text_field( wp_unslash( $_POST['tcdist_sitemap_speed'] ) ) : '';
    if ( ! is_scalar( $raw ) || ! preg_match( '/^[1-5]$/', (string) $raw ) ) {
        wp_die( esc_html__( 'Selecciona una velocidad entre 1 y 5.', 'taxocommerce-distribution' ) );
    }
    update_option( TCDIST_SITEMAP_SPEED_OPTION, (int) $raw, false );
    wp_safe_redirect( taxocommerce_distribution_url( 'sitemaps' ) );
    exit;
}

function taxocommerce_distribution_stop_sitemap() {
    taxocommerce_distribution_guard();
    check_admin_referer( 'tcdist_sitemap_stop' );
    $result = tcdist_sitemap_stop_background_build();
    $message = is_wp_error( $result )
        ? $result->get_error_message()
        : __( 'La generación se ha detenido.', 'taxocommerce-distribution' );
    wp_safe_redirect( taxocommerce_distribution_url( 'sitemaps', array(
        'tcdist_sitemap_result' => is_wp_error( $result ) ? 'error' : 'ok',
        'tcdist_sitemap_message' => rawurlencode( $message ),
    ) ) );
    exit;
}

function taxocommerce_distribution_sitemap_ajax_pulse() {
    if ( ! current_user_can( taxocommerce_distribution_capability() ) && ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => __( 'Sin permisos.', 'taxocommerce-distribution' ) ), 403 );
    }
    check_ajax_referer( 'tcdist_sitemap_pulse', 'nonce' );
    $state = tcdist_sitemap_job_state();
    if ( 'running' === ( $state['status'] ?? '' ) ) {
        $state = tcdist_sitemap_background_worker_tick();
    }
    $updated = absint( $state['updated_at'] ?? 0 );
    $published = tcdist_sitemap_publication_time();
    // Never expose local filesystem paths or unpublished filename lists.
    wp_send_json_success( array(
        'started' => ! empty( $state['started_at'] ) ? wp_date( 'd/m/Y H:i:s', absint( $state['started_at'] ) ) : '',
        'published' => $published ? wp_date( 'd/m/Y H:i:s', $published ) : '',
        'heartbeat' => $updated ? wp_date( 'd/m/Y H:i:s', $updated ) : '',
        'stalled' => 'running' === ( $state['status'] ?? '' ) && $updated > 0 && time() - $updated > 180,
        'status' => sanitize_key( (string) ( $state['status'] ?? 'idle' ) ),
        'phase' => sanitize_key( (string) ( $state['phase'] ?? 'idle' ) ),
        'processed' => absint( $state['processed'] ?? 0 ),
        'urls' => absint( $state['urls'] ?? 0 ),
        'message' => sanitize_text_field( (string) ( $state['message'] ?? '' ) ),
        'sample' => (array) ( $state['last_sample'] ?? array() ),
    ) );
}

function taxocommerce_distribution_sitemap_enqueue() {
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only WordPress menu selection.
    $page = isset( $_GET['page'] ) && is_string( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only tab navigation.
    $tab = isset( $_GET['tcdist_tab'] ) && is_string( $_GET['tcdist_tab'] ) ? sanitize_key( wp_unslash( $_GET['tcdist_tab'] ) ) : '';
    if ( 'taxocommerce-distribution' !== $page || 'sitemaps' !== $tab ) {
        return;
    }
    wp_enqueue_script(
        'tcdist-sitemap-progress',
        plugins_url( 'assets/sitemap-progress.js', TAXOCOMMERCE_DISTRIBUTION_FILE ),
        array(),
        TAXOCOMMERCE_DISTRIBUTION_VERSION,
        true
    );
    wp_localize_script( 'tcdist-sitemap-progress', 'tcdistSitemapProgress', array(
        'url' => admin_url( 'admin-ajax.php' ),
        'nonce' => wp_create_nonce( 'tcdist_sitemap_pulse' ),
        'working' => __( 'Generando sitemaps por bloques de 250…', 'taxocommerce-distribution' ),
        'finished' => __( 'Finalizado. Actualizando el informe…', 'taxocommerce-distribution' ),
        'failed' => __( 'El trabajador ha detectado un error.', 'taxocommerce-distribution' ),
        'stalled' => __( 'Sin actividad reciente. Comprueba WP-Cron; el navegador intentará recuperar el proceso.', 'taxocommerce-distribution' ),
        'startedLabel' => __( 'Inicio de esta generación:', 'taxocommerce-distribution' ),
        'publishedLabel' => __( 'Última publicación completada:', 'taxocommerce-distribution' ),
        'heartbeatLabel' => __( 'Última actividad:', 'taxocommerce-distribution' ),
        'statusLabels' => array(
            'running' => __( 'En ejecución', 'taxocommerce-distribution' ),
            'completed' => __( 'Completado', 'taxocommerce-distribution' ),
            'stopped' => __( 'Detenido', 'taxocommerce-distribution' ),
            'error' => __( 'Error', 'taxocommerce-distribution' ),
            'idle' => __( 'Sin iniciar', 'taxocommerce-distribution' ),
        ),
    ) );
}

function taxocommerce_distribution_render_page() {
    taxocommerce_distribution_guard();
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only tab selection.
    $tab = isset( $_GET['tcdist_tab'] ) && is_string( $_GET['tcdist_tab'] ) ? sanitize_key( wp_unslash( $_GET['tcdist_tab'] ) ) : 'feeds';
    if ( ! in_array( $tab, array( 'feeds', 'sitemaps', 'automation', 'manual', 'autocheck' ), true ) ) {
        // Existing Overview bookmarks remain valid and open Commercial Feeds.
        $tab = 'feeds';
    }
    ?>
    <div class="wrap">
        <?php taxocommerce_distribution_render_summary(); ?>

        <nav class="nav-tab-wrapper" aria-label="<?php esc_attr_e( 'Secciones de Distribution', 'taxocommerce-distribution' ); ?>">
            <?php
            foreach (
                array(
                    'feeds'      => __( 'Commercial Feeds', 'taxocommerce-distribution' ),
                    'sitemaps'   => __( 'Sitemaps', 'taxocommerce-distribution' ),
                    'automation' => __( 'Automation', 'taxocommerce-distribution' ),
                    'manual'     => __( 'Manual', 'taxocommerce-distribution' ),
                    'autocheck'  => __( 'Autocheck', 'taxocommerce-distribution' ),
                ) as $key => $label
            ) :
                ?>
                <a class="nav-tab <?php echo esc_attr( $tab === $key ? 'nav-tab-active' : '' ); ?>" href="<?php echo esc_url( taxocommerce_distribution_url( $key ) ); ?>"><?php echo esc_html( $label ); ?></a>
            <?php endforeach; ?>
        </nav>

        <?php taxocommerce_distribution_render_compatibility_notice(); ?>

        <div style="margin-top:18px;">
            <?php
            if ( 'feeds' === $tab ) {
                tcdist_feed_render_admin();
            } elseif ( 'sitemaps' === $tab ) {
                taxocommerce_distribution_render_sitemaps();
            } elseif ( 'automation' === $tab ) {
                taxocommerce_distribution_render_automation();
            } elseif ( 'manual' === $tab ) {
                taxocommerce_distribution_render_manual();
            } elseif ( 'autocheck' === $tab ) {
                taxocommerce_distribution_render_autocheck();
            } else {
                tcdist_feed_render_admin();
            }
            ?>
        </div>
    </div>
    <?php
}

function taxocommerce_distribution_render_compatibility_notice() {
    $legacy_feed = defined( 'TCDIST_LEGACY_FEED_ACTIVE' ) && TCDIST_LEGACY_FEED_ACTIVE;
    $legacy_sitemap = defined( 'TCDIST_LEGACY_SITEMAP_ACTIVE' ) && TCDIST_LEGACY_SITEMAP_ACTIVE;

    if ( $legacy_feed || $legacy_sitemap ) {
        echo '<div class="notice notice-info inline" style="margin-top:18px">' . '<p>' . '<strong>' . esc_html__( 'Safe migration:', 'taxocommerce-distribution' ) . '</strong>' . ' ' . esc_html__( 'the legacy plugin was detected. Distribution uses its own state and worker while preserving the same public files. While both plugins coexist, the legacy scheduled automation keeps priority to avoid duplicate execution; manual regeneration can be tested from this plugin.', 'taxocommerce-distribution' ) . '</p>' . '</div>';
    }
}

/**
 * Compact, read-only overview immediately below the common plugin header.
 * Public feed/sitemap operations remain on their dedicated tabs.
 */
function taxocommerce_distribution_render_summary() {
    $feed = tcdist_feed_state();
    $settings = tcdist_feed_settings();
    $sitemap = tcdist_sitemap_get_sitemap_report();
    $channels = tcdist_feed_channels();
    $enabled = (array) ( $settings['enabled_channels'] ?? array() );
    $labels = array();
    foreach ( $enabled as $key ) {
        if ( isset( $channels[ $key ]['label'] ) ) {
            $labels[] = $channels[ $key ]['label'];
        }
    }
    ?>
    <div class="notice notice-info inline" style="margin:12px 0;padding:9px 13px;max-width:1200px;" aria-label="<?php esc_attr_e( 'Publishing summary', 'taxocommerce-distribution' ); ?>">
        <p style="margin:0 0 5px;">
            <strong><?php esc_html_e( 'Commercial feeds:', 'taxocommerce-distribution' ); ?></strong>
            <?php echo esc_html( tcdist_feed_admin_status_label( $feed['status'] ?? 'never' ) ); ?>
            · <?php esc_html_e( 'Published products:', 'taxocommerce-distribution' ); ?>
            <?php echo esc_html( number_format_i18n( absint( $feed['written'] ?? 0 ) ) ); ?>
            · <strong><?php esc_html_e( 'Active channels:', 'taxocommerce-distribution' ); ?></strong>
            <?php echo esc_html( number_format_i18n( count( $enabled ) ) ); ?>
        </p>
        <p style="margin:0;">
            <strong><?php esc_html_e( 'XML index:', 'taxocommerce-distribution' ); ?></strong>
            <?php echo ! empty( $sitemap['main_valid'] ) ? esc_html__( 'Valid', 'taxocommerce-distribution' ) : esc_html__( 'Review', 'taxocommerce-distribution' ); ?>
            · <?php esc_html_e( 'Published URLs:', 'taxocommerce-distribution' ); ?>
            <?php echo esc_html( number_format_i18n( absint( $sitemap['total_urls'] ?? 0 ) ) ); ?>
            · <?php esc_html_e( 'Files:', 'taxocommerce-distribution' ); ?>
            <?php echo esc_html( number_format_i18n( count( (array) ( $sitemap['files'] ?? array() ) ) ) ); ?>
        </p>
        <?php if ( $labels ) : ?>
            <p class="description" style="margin:5px 0 0;"><?php echo esc_html( implode( ' · ', $labels ) ); ?></p>
        <?php endif; ?>
    </div>
    <?php
}

function taxocommerce_distribution_sitemap_audit_stats() {
    if ( function_exists( 'seo_marketing_scan_stats_by_sitemap' ) ) {
        return (array) seo_marketing_scan_stats_by_sitemap();
    }

    global $wpdb;
    $table = $wpdb->prefix . 'seo_sitemap_inventory';
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Check optional legacy reporting table.
    $exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
    if ( $exists !== $table ) {
        return array();
    }

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Fresh diagnostic report; cached data would misreport progress.
    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT sitemap_url,
                COUNT(*) AS total,
                SUM(last_checked_at IS NOT NULL) AS checked,
                SUM(status_bucket='ok') AS ok_count,
                SUM(status_bucket IN ('3xx','seo_warning')) AS warnings,
                SUM(status_bucket IN ('404','403','4xx','5xx','network','seo_error')) AS errors
             FROM %i
             WHERE active_in_sitemap=1
             GROUP BY sitemap_url",
            $table
        ),
        ARRAY_A
    );

    $out = array();
    foreach ( (array) $rows as $row ) {
        $url = (string) ( $row['sitemap_url'] ?? '' );
        if ( '' === $url ) {
            continue;
        }
        $out[ $url ] = array(
            'total'    => absint( $row['total'] ?? 0 ),
            'checked'  => absint( $row['checked'] ?? 0 ),
            'ok'       => absint( $row['ok_count'] ?? 0 ),
            'warnings' => absint( $row['warnings'] ?? 0 ),
            'errors'   => absint( $row['errors'] ?? 0 ),
        );
    }
    return $out;
}

function taxocommerce_distribution_render_sitemaps() {
    $report = tcdist_sitemap_get_sitemap_report();
    $main_url = tcdist_sitemap_sitemap_public_url( 'sitemap.xml' );
    $audit = taxocommerce_distribution_sitemap_audit_stats();
    $job = tcdist_sitemap_job_state();
    $running = 'running' === ( $job['status'] ?? '' );
    $speed = tcdist_sitemap_speed();
    $speeds = tcdist_sitemap_speed_labels();
    $published_at = tcdist_sitemap_publication_time();
    $owner_enabled = tcdist_sitemap_enabled();
    $external_owner = tcdist_sitemap_external_owner();
    $legacy_sitemap = defined( 'TCDIST_LEGACY_SITEMAP_ACTIVE' ) && TCDIST_LEGACY_SITEMAP_ACTIVE;
    $published_at_label = $published_at ? wp_date( 'd/m/Y H:i:s', $published_at ) : __( 'Todavía no publicado', 'taxocommerce-distribution' );
    $started_at_label = ! empty( $job['started_at'] ) ? wp_date( 'd/m/Y H:i:s', absint( $job['started_at'] ) ) : '—';
    $updated_at_label = ! empty( $job['updated_at'] ) ? wp_date( 'd/m/Y H:i:s', absint( $job['updated_at'] ) ) : '—';
    $status_labels = array(
        'running' => __( 'En ejecución', 'taxocommerce-distribution' ),
        'completed' => __( 'Completado', 'taxocommerce-distribution' ),
        'stopped' => __( 'Detenido', 'taxocommerce-distribution' ),
        'error' => __( 'Error', 'taxocommerce-distribution' ),
        'idle' => __( 'Sin iniciar', 'taxocommerce-distribution' ),
    );

    $result = isset( $_GET['tcdist_sitemap_result'] ) ? sanitize_key( wp_unslash( $_GET['tcdist_sitemap_result'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $message = isset( $_GET['tcdist_sitemap_message'] ) ? sanitize_text_field( wp_unslash( $_GET['tcdist_sitemap_message'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    ?>
    <div class="card" style="max-width:1220px;padding:20px;">
        <h2 style="margin-top:0;"><?php esc_html_e( 'XML Sitemaps', 'taxocommerce-distribution' ); ?></h2>
        <p><?php esc_html_e( 'The same proven engine is preserved: atomic publishing, pre-publication XML validation, product batching, and stable root-domain URLs.', 'taxocommerce-distribution' ); ?></p>
        <p><?php esc_html_e( 'Professional index:', 'taxocommerce-distribution' ); ?> <a href="<?php echo esc_url( $main_url ); ?>" target="_blank" rel="noopener noreferrer"><code><?php echo esc_html( $main_url ); ?></code></a></p>

        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="padding:12px 14px;border:1px solid #c3c4c7;margin:12px 0;">
            <input type="hidden" name="action" value="tcdist_sitemap_ownership">
            <?php wp_nonce_field( 'tcdist_sitemap_ownership' ); ?>
            <label for="tcdist-sitemap-enabled">
                <input type="checkbox" id="tcdist-sitemap-enabled" name="tcdist_sitemap_enabled" value="1" <?php checked( $owner_enabled ); ?>>
                <strong><?php esc_html_e( 'TaxoCommerce Distribution gestiona el sitemap XML', 'taxocommerce-distribution' ); ?></strong>
            </label>
            <p class="description"><?php esc_html_e( 'Desmarcar permite usar el sitemap nativo de WordPress o el de otro plugin. Los archivos XML existentes no se borran; los feeds comerciales siguen funcionando. Si otro plugin reclama la gestión mediante el filtro de WordPress, tiene prioridad.', 'taxocommerce-distribution' ); ?></p>
            <?php if ( $legacy_sitemap ) : ?>
                <p class="description"><?php esc_html_e( 'Existe otro generador heredado activo: conserva su prioridad hasta que se desactive.', 'taxocommerce-distribution' ); ?></p>
            <?php elseif ( $owner_enabled && '' !== $external_owner ) : ?>
                <p class="description"><?php esc_html_e( 'Otro plugin gestiona actualmente el sitemap.', 'taxocommerce-distribution' ); ?></p>
            <?php elseif ( ! $owner_enabled ) : ?>
                <p class="description"><?php esc_html_e( 'Distribution no publica sitemaps. WordPress u otro proveedor puede gestionarlos.', 'taxocommerce-distribution' ); ?></p>
            <?php endif; ?>
            <?php submit_button( __( 'Guardar elección', 'taxocommerce-distribution' ), 'secondary', 'submit', false ); ?>
        </form>

        <?php if ( $message ) : ?>
            <div class="notice <?php echo esc_attr( 'ok' === $result ? 'notice-success' : 'notice-error' ); ?> inline"><p><?php echo esc_html( $message ); ?></p></div>
        <?php endif; ?>

        <p id="tcdist-sitemap-publication"><strong><?php esc_html_e( 'Última publicación completada:', 'taxocommerce-distribution' ); ?></strong>
            <span id="tcdist-sitemap-published-at"><?php echo esc_html( $published_at_label ); ?></span>.
            <?php esc_html_e( 'La fecha de los archivos XML corresponde a la última publicación, no al inicio del trabajo.', 'taxocommerce-distribution' ); ?>
        </p>
        <?php if ( $running ) : ?>
            <div class="notice notice-info inline"><p><?php esc_html_e( 'La generación está en curso. El índice visible todavía pertenece a la publicación anterior.', 'taxocommerce-distribution' ); ?></p></div>
        <?php endif; ?>
        <?php if ( ! empty( $report['main_valid'] ) ) : ?>
            <div class="notice notice-success inline"><p><strong><?php esc_html_e( 'Main index is valid.', 'taxocommerce-distribution' ); ?></strong> <?php
            /* translators: %s: number of URLs published in the sitemap index. */
            echo esc_html( sprintf( __( '%s URLs published.', 'taxocommerce-distribution' ), number_format_i18n( absint( $report['total_urls'] ?? 0 ) ) ) );
            ?></p></div>
        <?php else : ?>
            <div class="notice notice-warning inline"><p><strong><?php esc_html_e( 'The main index does not exist or is invalid.', 'taxocommerce-distribution' ); ?></strong></p></div>
        <?php endif; ?>


        <div class="card" style="max-width:none;padding:14px;margin:12px 0;">
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <input type="hidden" name="action" value="tcdist_sitemap_speed">
                <?php wp_nonce_field( 'tcdist_sitemap_speed' ); ?>
                <label for="tcdist-sitemap-speed"><strong><?php esc_html_e( 'Control de carga del generador XML', 'taxocommerce-distribution' ); ?></strong></label>
                <select name="tcdist_sitemap_speed" id="tcdist-sitemap-speed">
                    <?php foreach ( $speeds as $value => $label ) : ?>
                        <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $speed, $value ); ?>><?php echo esc_html( $value . ' · ' . $label ); ?></option>
                    <?php endforeach; ?>
                </select>
                <?php submit_button( __( 'Guardar velocidad', 'taxocommerce-distribution' ), 'secondary', 'submit', false ); ?>
                <p class="description"><?php esc_html_e( 'Máximo 250 elementos por ejecución. La velocidad regula las pausas; si PHP consume demasiada memoria o tiempo, el gestor reduce automáticamente la frecuencia. La CPU mostrada es del proceso PHP, no del servidor.', 'taxocommerce-distribution' ); ?></p>
            </form>
            <div id="tcdist-sitemap-progress" data-running="<?php echo esc_attr( $running ? '1' : '0' ); ?>" aria-live="polite" style="margin-top:10px;">
                <strong><?php esc_html_e( 'Estado de generación:', 'taxocommerce-distribution' ); ?></strong>
                <span id="tcdist-sitemap-status"><?php echo esc_html( $status_labels[ (string) ( $job['status'] ?? 'idle' ) ] ?? $status_labels['idle'] ); ?></span>.
                <span id="tcdist-sitemap-counts"><?php
                /* translators: 1: Number of sitemap items examined, 2: Number of sitemap URLs prepared. */
                echo esc_html( sprintf( __( '%1$d elementos revisados; %2$d URL preparadas.', 'taxocommerce-distribution' ), absint( $job['processed'] ?? 0 ), absint( $job['urls'] ?? 0 ) ) ); ?></span>
                <span id="tcdist-sitemap-phase"><?php echo esc_html( (string) ( $job['phase'] ?? '' ) ); ?></span>
                <span id="tcdist-sitemap-message"><?php echo esc_html( (string) ( $job['message'] ?? '' ) ); ?></span>
                <p><strong><?php esc_html_e( 'Inicio de esta generación:', 'taxocommerce-distribution' ); ?></strong>
                    <span id="tcdist-sitemap-started-at"><?php echo esc_html( $started_at_label ); ?></span> ·
                    <strong><?php esc_html_e( 'Última actividad:', 'taxocommerce-distribution' ); ?></strong>
                    <span id="tcdist-sitemap-updated-at"><?php echo esc_html( $updated_at_label ); ?></span></p>
                <?php if ( $running && ! empty( $job['updated_at'] ) && time() - absint( $job['updated_at'] ) > 180 ) : ?>
                    <p class="description" id="tcdist-sitemap-stalled"><?php esc_html_e( 'Sin actividad reciente. Comprueba WP-Cron; el navegador intentará recuperar el proceso.', 'taxocommerce-distribution' ); ?></p>
                <?php else : ?>
                    <p class="description" id="tcdist-sitemap-stalled"></p>
                <?php endif; ?>
                <p class="description" id="tcdist-sitemap-load"><?php
                $sample = (array) ( $job['last_sample'] ?? array() );
                echo esc_html( sprintf(
                    /* translators: %1$s: worker seconds; %2$s: PHP CPU; %3$s: PHP memory. */
                    __( 'Último bloque: %1$s s · CPU PHP: %2$s%% · Memoria PHP: %3$s%%', 'taxocommerce-distribution' ),
                    (string) ( $sample['seconds'] ?? '—' ),
                    (string) ( $sample['cpu_percent'] ?? '—' ),
                    (string) ( $sample['memory_percent'] ?? '—' )
                ) ); ?></p>
            </div>
        </div>

        <?php if ( ! $running && $owner_enabled && '' === $external_owner ) : ?>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin:18px 0;">
                <input type="hidden" name="action" value="tcdist_sitemap_regenerate">
                <?php wp_nonce_field( 'tcdist_sitemap_regenerate' ); ?>
                <?php submit_button( __( 'Generar sitemaps en bloques de 250', 'taxocommerce-distribution' ), 'primary', 'submit', false ); ?>
            </form>
        <?php else : ?>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin:18px 0;">
                <input type="hidden" name="action" value="tcdist_sitemap_stop">
                <?php wp_nonce_field( 'tcdist_sitemap_stop' ); ?>
                <?php submit_button( __( 'Detener generación', 'taxocommerce-distribution' ), 'secondary', 'submit', false ); ?>
            </form>
        <?php endif; ?>

        <details style="margin:16px 0;">
            <summary><strong><?php esc_html_e( 'What this generation does', 'taxocommerce-distribution' ); ?></strong></summary>
            <p><?php esc_html_e( 'Separates products, categories, posts, pages, clusters, and hubs. Only published, non-password-protected content is included; cart, checkout, and account pages are excluded. Each XML file is validated before replacing the previous public file.', 'taxocommerce-distribution' ); ?></p>
            <p><?php esc_html_e( 'Products are split into bounded groups to avoid oversized XML files. Generation uses a lock and atomic publishing so partial files are never exposed.', 'taxocommerce-distribution' ); ?></p>
        </details>

        <table class="widefat striped">
            <thead><tr><th><?php esc_html_e( 'File', 'taxocommerce-distribution' ); ?></th><th>XML</th><th>URLs</th><th><?php esc_html_e( 'Audited', 'taxocommerce-distribution' ); ?></th><th>OK</th><th><?php esc_html_e( 'Warnings', 'taxocommerce-distribution' ); ?></th><th><?php esc_html_e( 'Errors', 'taxocommerce-distribution' ); ?></th><th><?php esc_html_e( 'Coverage', 'taxocommerce-distribution' ); ?></th><th><?php esc_html_e( 'Size', 'taxocommerce-distribution' ); ?></th><th><?php esc_html_e( 'Modified', 'taxocommerce-distribution' ); ?></th></tr></thead>
            <tbody>
            <?php foreach ( (array) ( $report['files'] ?? array() ) as $file ) : ?>
                <?php
                $is_index = 'sitemap.xml' === (string) ( $file['filename'] ?? '' );
                $stats = ! $is_index && isset( $audit[ $file['url'] ] ) ? $audit[ $file['url'] ] : array( 'checked' => 0, 'ok' => 0, 'warnings' => 0, 'errors' => 0 );
                $count = absint( $file['count'] ?? 0 );
                $coverage = ! $is_index && $count > 0 ? min( 100, round( 100 * absint( $stats['checked'] ?? 0 ) / $count, 1 ) ) : 0;
                ?>
                <tr>
                    <td><a href="<?php echo esc_url( (string) $file['url'] ); ?>" target="_blank" rel="noopener"><code><?php echo esc_html( (string) $file['filename'] ); ?></code></a></td>
                    <td><?php echo ! empty( $file['valid'] ) ? '<span style="color:#1d6b43;font-weight:700">' . esc_html__( 'Valid', 'taxocommerce-distribution' ) . '</span>' : '<span style="color:#b32d2e;font-weight:700">' . esc_html__( 'Invalid', 'taxocommerce-distribution' ) . '</span>'; ?></td>
                    <td><?php echo esc_html( number_format_i18n( $count ) ); ?></td>
                    <?php if ( $is_index ) : ?>
                        <td>—</td><td>—</td><td>—</td><td>—</td><td>—</td>
                    <?php else : ?>
                        <td><?php echo esc_html( number_format_i18n( absint( $stats['checked'] ?? 0 ) ) ); ?></td>
                        <td><?php echo esc_html( number_format_i18n( absint( $stats['ok'] ?? 0 ) ) ); ?></td>
                        <td><?php echo esc_html( number_format_i18n( absint( $stats['warnings'] ?? 0 ) ) ); ?></td>
                        <td><?php echo esc_html( number_format_i18n( absint( $stats['errors'] ?? 0 ) ) ); ?></td>
                        <td><?php echo esc_html( number_format_i18n( $coverage, 1 ) ); ?>%</td>
                    <?php endif; ?>
                    <td><?php echo esc_html( size_format( absint( $file['bytes'] ?? 0 ), 1 ) ); ?></td>
                    <td><?php echo ! empty( $file['modified'] ) ? esc_html( wp_date( 'd/m/Y H:i', absint( $file['modified'] ) ) ) : '—'; ?></td>
                </tr>
                <?php if ( empty( $file['valid'] ) && ! empty( $file['error'] ) ) : ?>
                    <tr><td colspan="10" style="color:#b32d2e;"><?php echo esc_html( (string) $file['error'] ); ?></td></tr>
                <?php endif; ?>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}

function taxocommerce_distribution_render_automation() {
    $feed = tcdist_feed_state();
    $settings = tcdist_feed_settings();
    $legacy_feed = defined( 'TCDIST_LEGACY_FEED_ACTIVE' ) && TCDIST_LEGACY_FEED_ACTIVE;
    $legacy_sitemap = defined( 'TCDIST_LEGACY_SITEMAP_ACTIVE' ) && TCDIST_LEGACY_SITEMAP_ACTIVE;
    ?>
    <div class="card" style="max-width:1000px;padding:20px;">
        <h2 style="margin-top:0;"><?php esc_html_e( 'Automation and workload', 'taxocommerce-distribution' ); ?></h2>
        <p><?php esc_html_e( 'Feeds keep the adaptive regulator from the previous system, but work now runs through the dedicated Distribution worker. Each window uses a cursor, lock, and execution deadline; there is no dependency on the legacy central manager.', 'taxocommerce-distribution' ); ?></p>

        <table class="widefat striped">
            <tbody>
                <tr><th><?php esc_html_e( 'Feed worker', 'taxocommerce-distribution' ); ?></th><td><?php echo $legacy_feed ? esc_html__( 'Legacy automation has priority during coexistence; manual execution is available here.', 'taxocommerce-distribution' ) : esc_html__( 'Owned by Distribution', 'taxocommerce-distribution' ); ?></td></tr>
                <tr><th><?php esc_html_e( 'Daily regeneration', 'taxocommerce-distribution' ); ?></th><td><?php echo ! empty( $settings['auto_refresh'] ) ? esc_html( (string) ( $settings['daily_time'] ?? '03:30' ) ) : esc_html__( 'Disabled', 'taxocommerce-distribution' ); ?></td></tr>
                <tr><th><?php esc_html_e( 'Last batch', 'taxocommerce-distribution' ); ?></th><td><?php echo esc_html( number_format_i18n( absint( $feed['last_batch_rows'] ?? 0 ) ) ); ?> <?php esc_html_e( 'products', 'taxocommerce-distribution' ); ?> · <?php echo esc_html( number_format_i18n( (float) ( $feed['last_batch_duration'] ?? 0 ), 2 ) ); ?> s</td></tr>
                <tr><th><?php esc_html_e( 'Automatic sitemaps', 'taxocommerce-distribution' ); ?></th><td><?php echo $legacy_sitemap ? esc_html__( 'Temporarily managed by the legacy plugin', 'taxocommerce-distribution' ) : esc_html__( 'Distribution regenerates after product, page, post, and category changes', 'taxocommerce-distribution' ); ?></td></tr>
            </tbody>
        </table>

        <p class="description"><?php esc_html_e( 'During migration, two parallel cron jobs are prevented from writing to the same public files. Once the legacy module is removed, Distribution automatically takes over scheduling.', 'taxocommerce-distribution' ); ?></p>
    </div>
    <?php
}
