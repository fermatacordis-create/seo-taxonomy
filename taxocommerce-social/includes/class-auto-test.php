<?php
/**
 * Read-only, local Autocheck for TaxoCommerce Social.
 *
 * Never invokes provider connection tests, token decryption, scheduled jobs,
 * remote APIs, publishing, import or export handlers.
 */
defined( 'ABSPATH' ) || exit;

final class TCSocial_Auto_Test {
    const JSON_ACTION = 'taxocommerce_social_autocheck_json';
    const ACTION = 'tcsocial_run_auto_test';
    const NONCE = 'tcsocial_auto_test_run';
    const PREFIX = 'tcsocial_auto_test_';

    public static function register() {
        add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'handle' ) );
        add_action( 'admin_post_' . self::JSON_ACTION, array( __CLASS__, 'handle_download_json' ) );
    }

    private static function result( $status, $message ) {
        return array( 'status' => $status, 'message' => $message );
    }
    private static function pass( $message ) { return self::result( 'pass', $message ); }
    private static function fail( $message ) { return self::result( 'fail', $message ); }
    private static function skip( $message ) { return self::result( 'skip', $message ); }

    public static function cases() {
        return array(
            'runtime'            => array( 'Plugin', 'Arranque y paneles disponibles' ),
            'permissions'        => array( 'Seguridad', 'Rutas de administración registradas' ),
            'providers'          => array( 'Conexiones', 'Cinco proveedores declarados' ),
            'provider_callbacks' => array( 'Conexiones', 'Callbacks locales de cada red' ),
            'settings'           => array( 'Configuración', 'Acceso a ajustes existentes' ),
            'templates'          => array( 'Plantillas', 'Plantillas predeterminadas por red' ),
            'database'           => array( 'MySQL', 'Tablas operativas canónicas' ),
            'table_reads'        => array( 'MySQL', 'Lecturas limitadas de registros' ),
            'calendar'           => array( 'Calendario', 'Vistas y detección de coincidencias' ),
            'scheduler'          => array( 'Programador', 'Gestión de tareas y estados' ),
            'cron'               => array( 'Programador', 'Registro real de hooks y WP-Cron' ),
            'schedule_health'    => array( 'Calendario', 'Agenda contrastada con WP-Cron' ),
            'job_health'         => array( 'Programador', 'Cola de trabajos y eventos pendientes' ),
            'connection_health'  => array( 'Conexiones', 'Autorizaciones y permisos guardados' ),
            'pinterest_images'   => array( 'Publicaciones', 'Imágenes de futuros Pins' ),
            'recent_outcomes'    => array( 'Publicaciones', 'Resultados recientes de envíos' ),
            'csv'                => array( 'Importar / Exportar', 'Simulación y contratos CSV' ),
            'secrets'            => array( 'Seguridad', 'Contrato de cifrado de credenciales' ),
            'publishing'         => array( 'Publicaciones', 'Motor de publicación disponible' ),
            'manual'             => array( 'Manual', 'Guía de usuario y técnica integradas' ),
            'remote_ops'         => array( 'Seguridad', 'Conexión real, publicación y exportación' ),
        );
    }

    public static function run_suite() {
        $start = microtime( true );
        $totals = array( 'pass' => 0, 'fail' => 0, 'skip' => 0 );
        $rows = array();
        foreach ( self::cases() as $id => $labels ) {
            $begin = microtime( true );
            try {
                $method = 'test_' . $id;
                $result = self::$method();
                if ( ! is_array( $result ) || ! isset( $result['status'], $result['message'] ) || ! array_key_exists( $result['status'], $totals ) ) {
                    $result = self::fail( 'La prueba no ha devuelto un resultado válido.' );
                }
            } catch ( Throwable $error ) {
                $result = self::fail( 'No se pudo comprobar la funcionalidad: ' . $error->getMessage() );
            }
            $rows[] = array(
                'id' => $id, 'group' => $labels[0], 'label' => $labels[1],
                'status' => $result['status'], 'message' => $result['message'],
                'ms' => (int) round( ( microtime( true ) - $begin ) * 1000 ),
            );
            ++$totals[ $result['status'] ];
        }
        return array(
            'version' => TAXOCOMMERCE_SOCIAL_VERSION,
            'date' => current_time( 'mysql' ), 'executed_at' => gmdate( 'c' ),
            'ms' => (int) round( ( microtime( true ) - $start ) * 1000 ),
            'totals' => $totals,
            'rows' => $rows,
        );
    }

    private static function test_runtime() {
        $required = array(
            'tcsocial_admin_render', 'tcsocial_network_get_settings',
            'tcsocial_network_get_providers', 'tcsocial_programador_render_admin',
            'tcsocial_calendar_render_admin', 'tcsocial_manual_render',
        );
        foreach ( $required as $function ) {
            if ( ! function_exists( $function ) ) return self::fail( 'No existe ' . $function . '.' );
        }
        return self::pass( 'Social ' . TAXOCOMMERCE_SOCIAL_VERSION . ': componentes cargados.' );
    }

    private static function test_permissions() {
        $routes = array(
            self::ACTION,
            'tcsocial_network_save_connection',
            'tcsocial_network_disconnect',
            'tcsocial_network_save_templates',
            'tcsocial_network_publish_now',
            'tcsocial_programador_create',
            'tcsocial_programador_cancel',
            'tcsocial_programador_retry',
        );
        foreach ( $routes as $route ) {
            if ( ! has_action( 'admin_post_' . $route ) ) {
                return self::fail( 'No está registrada la ruta ' . $route . '.' );
            }
        }
        return self::pass( count( $routes ) . ' acciones de administración registradas; no se han ejecutado.' );
    }

    private static function test_providers() {
        $providers = tcsocial_network_get_providers();
        if ( ! is_array( $providers ) ) return self::fail( 'Registro de proveedores inválido.' );
        $missing = array_diff( array( 'facebook', 'instagram', 'linkedin', 'pinterest', 'x' ), array_keys( $providers ) );
        return $missing
            ? self::fail( 'Proveedores sin registrar: ' . implode( ', ', $missing ) . '.' )
            : self::pass( 'Facebook, Instagram, LinkedIn, Pinterest y X registrados (sin conexiones HTTP).' );
    }

    private static function test_provider_callbacks() {
        foreach ( tcsocial_network_get_providers() as $key => $provider ) {
            if ( ! is_array( $provider ) ) return self::fail( 'Definición inválida: ' . $key . '.' );
            foreach ( array( 'sanitize_connection_callback', 'test_callback', 'publish_callback', 'render_connection_callback' ) as $field ) {
                if ( empty( $provider[ $field ] ) || ! is_callable( $provider[ $field ] ) ) {
                    return self::fail( 'Falta ' . $field . ' de ' . $key . '.' );
                }
            }
        }
        return self::pass( 'Callbacks locales registradas sin probar credenciales ni publicar.' );
    }

    private static function test_settings() {
        $settings = tcsocial_network_get_settings();
        return is_array( $settings ) && isset( $settings['providers'], $settings['templates'], $settings['auto_publish'] )
            && is_array( $settings['providers'] ) && is_array( $settings['templates'] )
            ? self::pass( 'Ajustes leídos correctamente; no se muestran ni descifran credenciales.' )
            : self::fail( 'La configuración guardada no presenta la estructura prevista.' );
    }

    private static function test_templates() {
        $defaults = tcsocial_network_default_settings();
        foreach ( array( 'facebook', 'instagram', 'linkedin', 'pinterest', 'x' ) as $network ) {
            if ( empty( $defaults['templates'][ $network ]['post'] ) || empty( $defaults['templates'][ $network ]['page'] ) ) {
                return self::fail( 'Plantilla predeterminada incompleta: ' . $network . '.' );
            }
        }
        return self::pass( 'Plantillas base para entradas y páginas disponibles en cinco redes.' );
    }

    private static function table_exists( $table ) {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Administrative live schema check, not cached.
        return $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
    }

    private static function test_database() {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Live connectivity check, no writes.
        if ( '1' !== (string) $wpdb->get_var( 'SELECT 1' ) ) return self::fail( 'No responde MySQL a SELECT 1.' );
        $tables = array( tcsocial_network_publications_table(), tcsocial_programador_table() );
        foreach ( $tables as $table ) {
            if ( ! self::table_exists( $table ) ) {
                return self::fail( 'Falta la tabla ' . $table . '. Autocheck no la crea.' );
            }
        }
        return self::pass( 'Conexión MySQL y tablas de publicaciones/programador disponibles.' );
    }

    private static function test_table_reads() {
        global $wpdb;
        foreach ( array( tcsocial_network_publications_table(), tcsocial_programador_table() ) as $table ) {
            if ( ! self::table_exists( $table ) ) return self::skip( 'Falta ' . $table . '; no puede leerse.' );
            $wpdb->last_error = '';
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Bounded read-only diagnostic; does not return content or secrets.
            $sample = $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM %i LIMIT %d', $table, 1 ) );
            if ( ! is_array( $sample ) || '' !== (string) $wpdb->last_error ) {
                return self::fail( 'No se puede leer ' . $table . '.' );
            }
        }
        return self::pass( 'Lecturas de hasta una fila por tabla, sin modificar contenidos.' );
    }

    private static function test_calendar() {
        return function_exists( 'tcsocial_calendar_events' )
            && function_exists( 'tcsocial_calendar_group_by_day' )
            && function_exists( 'tcsocial_calendar_has_same_network_collision' )
            && function_exists( 'tcsocial_calendar_render_admin' )
            ? self::pass( 'Calendario y detección de coincidencias disponibles; no se consulta la agenda.' )
            : self::fail( 'Falta alguna función del calendario.' );
    }

    private static function test_scheduler() {
        foreach ( array( 'tcsocial_programador_create_job', 'tcsocial_programador_get_job', 'tcsocial_programador_run_job', 'tcsocial_programador_jobs', 'tcsocial_programador_handle_cancel', 'tcsocial_programador_handle_retry' ) as $function ) {
            if ( ! function_exists( $function ) ) return self::fail( 'Falta ' . $function . '.' );
        }
        return self::pass( 'Programador, consulta, cancelación y reintento disponibles sin crear trabajos.' );
    }

    private static function test_cron() {
        if (
            ! has_action( 'tcsocial_programador_run_job' )
            || ! has_action( 'tcsocial_network_publish_scheduled' )
            || ! has_action( 'tcsocial_network_publish_manual_scheduled' )
        ) {
            return self::fail( 'Falta un ejecutor WP-Cron del plugin Social.' );
        }
        if (
            ! has_action( 'transition_post_status', 'tcsocial_network_schedule_on_publish' )
            && ! has_action( 'transition_post_status', 'seo_social_network_schedule_on_publish' )
        ) {
            return self::fail( 'La programación automática de contenidos nuevos no está registrada en WordPress.' );
        }
        return self::pass( 'Ejecutores y programación automática registrados; las colas se comprueban por separado.' );
    }

    /**
     * Lectura acotada de agenda meta compartida entre Social y su predecesor.
     * No reconstruye ni ejecuta ningún evento.
     */
    private static function schedule_rows( $provider = '', $future_only = false ) {
        global $wpdb;
        $prefix = '_seo_social_schedule_';
        $like = $wpdb->esc_like( $prefix ) . '%';
        if ( $future_only ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Consulta de lectura acotada de agenda propia.
            $rows = $wpdb->get_results( $wpdb->prepare(
                "SELECT pm.post_id, pm.meta_key, pm.meta_value FROM %i pm
                 INNER JOIN %i p ON p.ID = pm.post_id
                 WHERE pm.meta_key LIKE %s AND p.post_status = 'publish'
                   AND CAST(pm.meta_value AS UNSIGNED) > %d
                 ORDER BY CAST(pm.meta_value AS UNSIGNED) ASC LIMIT %d",
                $wpdb->postmeta, $wpdb->posts, $like, time(), 100
            ) );
        } else {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Consulta de lectura acotada de agenda propia.
            $rows = $wpdb->get_results( $wpdb->prepare(
                "SELECT pm.post_id, pm.meta_key, pm.meta_value FROM %i pm
                 INNER JOIN %i p ON p.ID = pm.post_id
                 WHERE pm.meta_key LIKE %s AND p.post_status = 'publish'
                 ORDER BY CAST(pm.meta_value AS UNSIGNED) ASC LIMIT %d",
                $wpdb->postmeta, $wpdb->posts, $like, 100
            ) );
        }
        return is_array( $rows ) ? $rows : array();
    }

    private static function test_schedule_health() {
        $rows = self::schedule_rows();
        if ( ! $rows ) return self::skip( 'Sin agenda editorial visible en la muestra de hasta 100 filas.' );
        $overdue = 0;
        $orphaned = 0;
        $disconnected = 0;
        $settings = tcsocial_network_get_settings();
        $checked = 0;
        foreach ( $rows as $row ) {
            $id = absint( $row->post_id );
            $provider = sanitize_key( substr( (string) $row->meta_key, strlen( '_seo_social_schedule_' ) ) );
            $stamp = absint( $row->meta_value );
            if ( ! $id || ! $stamp || ! tcsocial_network_get_provider( $provider ) ) continue;
            ++$checked;
            if ( empty( $settings['providers'][ $provider ]['enabled'] ) ) ++$disconnected;
            if ( $stamp < time() - 600 ) {
                ++$overdue;
                continue;
            }
            $args = array( $id, $provider );
            if (
                ! wp_next_scheduled( 'tcsocial_network_publish_manual_scheduled', $args )
                && ! wp_next_scheduled( 'seo_social_network_publish_manual_scheduled', $args )
            ) ++$orphaned;
        }
        if ( $overdue || $orphaned || $disconnected ) {
            return self::fail( sprintf(
                'Agenda: %d vencidas sin confirmar, %d sin evento WP-Cron y %d para redes desconectadas, de %d comprobadas. No se reenviarán automáticamente; revisar y reprogramar tras descartar duplicados.',
                $overdue, $orphaned, $disconnected, $checked
            ) );
        }
        return self::pass( sprintf( '%d entradas de agenda con fecha futura y ejecutor WP-Cron (muestra hasta 100).', $checked ) );
    }

    private static function test_job_health() {
        global $wpdb;
        if ( ! self::table_exists( tcsocial_programador_table() ) ) {
            return self::skip( 'No existe la tabla del programador.' );
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Consulta diagnóstica acotada a tabla propia.
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT id, status, scheduled_at, updated_at FROM %i
             WHERE status IN ('scheduled','processing')
             ORDER BY scheduled_at ASC LIMIT %d",
            tcsocial_programador_table(), 100
        ) );
        if ( ! is_array( $rows ) ) return self::fail( 'No se puede leer la cola de publicaciones.' );
        if ( ! $rows ) return self::skip( 'No hay tareas activas en la muestra del programador.' );
        $missing = 0;
        $overdue = 0;
        $processing = 0;
        $now = current_time( 'mysql' );
        foreach ( $rows as $job ) {
            $scheduled_at = (string) $job->scheduled_at;
            if ( $job->status === 'scheduled' ) {
                if ( $scheduled_at < $now ) {
                    ++$overdue;
                }
                if ( ! wp_next_scheduled( 'tcsocial_programador_run_job', array( absint( $job->id ) ) ) ) {
                    ++$missing;
                }
            } elseif ( $job->status === 'processing' && (string) $job->updated_at < wp_date( 'Y-m-d H:i:s', time() - 30 * MINUTE_IN_SECONDS, wp_timezone() ) ) {
                ++$processing;
            }
        }
        if ( $missing || $processing || $overdue ) {
            return self::fail( sprintf(
                'Cola: %d eventos ausentes, %d vencidos y %d bloqueados en ejecución (máximo 100). Comprobar procesos antes de reintentar.',
                $missing, $overdue, $processing
            ) );
        }
        return self::pass( sprintf( '%d trabajos activos contrastados con eventos WP-Cron.', count( $rows ) ) );
    }

    private static function test_connection_health() {
        $settings = tcsocial_network_get_settings();
        $enabled = 0;
        $problems = array();
        foreach ( tcsocial_network_get_providers() as $provider => $definition ) {
            $config = $settings['providers'][ $provider ] ?? array();
            if ( empty( $config['enabled'] ) ) continue;
            ++$enabled;
            if ( empty( $config['access_token_enc'] ) && ! ( 'instagram' === $provider && ! empty( $config['use_facebook_connection'] ) && ! empty( $settings['providers']['facebook']['access_token_enc'] ) ) ) {
                $problems[] = $provider . ': sin token';
            }
            if ( in_array( $provider, array( 'facebook', 'pinterest', 'linkedin' ), true ) ) {
                $id_key = 'facebook' === $provider ? 'page_id' : ( 'pinterest' === $provider ? 'board_id' : 'organization_id' );
                if ( empty( $config[ $id_key ] ) ) $problems[] = $provider . ': sin destino';
            }
            if ( ! empty( $config['token_expires_at'] ) && absint( $config['token_expires_at'] ) <= time() ) {
                $problems[] = $provider . ': token caducado';
            }
            if ( ! empty( $config['last_test_at'] ) && empty( $config['last_test_ok'] ) ) {
                $problems[] = $provider . ': último test fallido';
            }
            if ( 'pinterest' === $provider && ! empty( $config['scope'] ) ) {
                $scopes = preg_split( '/[\\s,]+/', strtolower( (string) $config['scope'] ), -1, PREG_SPLIT_NO_EMPTY );
                if ( ! in_array( 'boards:write', $scopes, true ) ) {
                    $problems[] = 'Pinterest: falta boards:write; reconectar mediante OAuth';
                }
            }
        }
        if ( $problems ) return self::fail( implode( '; ', $problems ) . '. Sin conexiones externas realizadas.' );
        if ( ! $enabled ) return self::skip( 'No hay proveedores habilitados: la publicación automática no está operativa.' );
        return self::pass( $enabled . ' redes configuradas sin errores detectables localmente. OAuth remoto no validado.' );
    }

    private static function test_pinterest_images() {
        $settings = tcsocial_network_get_settings();
        if ( empty( $settings['providers']['pinterest']['enabled'] ) ) {
            return self::skip( 'Pinterest no está habilitado.' );
        }
        $missing = 0;
        $checked = 0;
        foreach ( self::schedule_rows( 'pinterest', true ) as $row ) {
            if ( '_seo_social_schedule_pinterest' !== (string) $row->meta_key ) continue;
            ++$checked;
            if ( ! has_post_thumbnail( absint( $row->post_id ) ) ) ++$missing;
        }
        if ( $missing ) return self::fail( $missing . ' de ' . $checked . ' futuros Pins no tienen imagen destacada. Pinterest no puede publicarlos.' );
        if ( ! $checked ) return self::skip( 'No hay futuros Pins en la muestra de agenda.' );
        return self::pass( $checked . ' futuros Pins tienen imagen destacada.' );
    }

    private static function test_recent_outcomes() {
        global $wpdb;
        if ( ! self::table_exists( tcsocial_network_publications_table() ) ) {
            return self::skip( 'No existe la tabla de historial social.' );
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Lectura de los últimos 150 resultados, sin publicar ni acceder a tokens.
        $rows = $wpdb->get_results( $wpdb->prepare(
            'SELECT provider, status, error_message FROM %i ORDER BY id DESC LIMIT %d',
            tcsocial_network_publications_table(), 150
        ) );
        if ( ! is_array( $rows ) ) return self::fail( 'No se puede consultar el historial de envíos.' );
        if ( ! $rows ) return self::skip( 'Sin intentos de publicación registrados.' );
        $latest = array();
        foreach ( $rows as $row ) {
            $provider = sanitize_key( $row->provider );
            if ( ! isset( $latest[ $provider ] ) ) {
                $latest[ $provider ] = $row;
            }
        }
        $errors = array();
        foreach ( $latest as $provider => $row ) {
            if ( 'failed' === $row->status || 'pending' === $row->status ) {
                $reason = 'failed' === $row->status ? wp_strip_all_tags( (string) $row->error_message ) : 'intento pendiente sin confirmación';
                $errors[] = $provider . ': ' . substr( $reason, 0, 180 );
            }
        }
        if ( $errors ) return self::fail( 'Último intento sin éxito: ' . implode( '; ', $errors ) . '. Comprobar permisos antes de reintentar.' );
        return self::pass( 'Último resultado por red con actividad sin errores en la muestra de 150; publicación externa no verificada.' );
    }

    private static function test_csv() {
        foreach ( array(
            'tcsocial_network_scheduler_import_columns',
            'tcsocial_network_scheduler_parse_import_file',
            'tcsocial_network_handle_scheduler_import_preview',
            'tcsocial_network_handle_scheduler_import_confirm',
            'tcsocial_network_handle_scheduler_export',
        ) as $function ) {
            if ( ! function_exists( $function ) ) return self::fail( 'Falta ' . $function . '.' );
        }
        return self::pass( 'Importación con vista previa y exportación CSV disponibles; ningún archivo se abre.' );
    }

    private static function test_secrets() {
        return function_exists( 'tcsocial_network_encrypt_secret' ) && function_exists( 'tcsocial_network_decrypt_secret' )
            ? self::pass( 'Capa de cifrado cargada; no se accede ni se imprime ningún token.' )
            : self::fail( 'Falta la capa de protección de credenciales.' );
    }

    private static function test_publishing() {
        return function_exists( 'tcsocial_network_publish_content' )
            && function_exists( 'tcsocial_network_create_pending_publication' )
            && function_exists( 'tcsocial_network_build_tracking_url' )
            ? self::pass( 'Contratos de publicación y seguimiento registrados; sin peticiones a redes.' )
            : self::fail( 'Falta una función de publicación.' );
    }

    private static function test_manual() {
        return function_exists( 'tcsocial_manual_render' )
            ? self::pass( 'Manual de usuario y técnico accesible desde Social.' )
            : self::fail( 'El manual integrado no está disponible.' );
    }

    private static function test_remote_ops() {
        return self::skip( 'No se prueban cuentas reales, peticiones a redes sociales, publicación, WP-Cron, importación/exportación ni cambios en agenda. Validar manualmente en STAGING.' );
    }

    public static function handle() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Sin permisos para Autocheck.', 'taxocommerce-social' ) );
        }
        check_admin_referer( self::NONCE, 'tcsocial_auto_nonce' );
        $key = self::PREFIX . get_current_user_id();
        $history = get_transient( $key );
        if ( ! is_array( $history ) ) $history = array();
        array_unshift( $history, self::run_suite() );
        set_transient( $key, array_slice( $history, 0, 2 ), 30 * DAY_IN_SECONDS );
        wp_safe_redirect( admin_url( 'admin.php?page=taxocommerce-social&tab=auto-test' ) );
        exit;
    }

    /** Download only the authenticated user's saved read-only diagnostics. */
    public static function handle_download_json() {
        if ( ! (current_user_can( 'manage_options' )) ) {
            wp_die( esc_html__( 'Sin permisos para descargar el autocheck.', 'taxocommerce-social' ) );
        }
        check_admin_referer( self::NONCE, 'tcsocial_auto_nonce' );
        $history = get_transient( self::PREFIX . get_current_user_id() );
        $report = is_array( $history ) && isset( $history[0]['rows'] ) ? $history[0] : null;
        if ( ! is_array( $report ) || ! isset( $report['rows'], $report['totals'] ) ) {
            wp_die( esc_html__( 'Ejecuta el autocheck antes de descargar el JSON.', 'taxocommerce-social' ) );
        }
        $report['plugin'] = 'taxocommerce-social';
        $report['schema'] = 1;
        $json = wp_json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE );
        if ( ! is_string( $json ) ) {
            wp_die( esc_html__( 'No se pudo generar el informe JSON.', 'taxocommerce-social' ) );
        }
        nocache_headers();
        header( 'Content-Type: application/json; charset=utf-8' );
        header( 'X-Content-Type-Options: nosniff' );
        header( 'Content-Disposition: attachment; filename="taxocommerce-social-autocheck-' . gmdate( 'Ymd-His' ) . '.json"' );
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Download JSON, not HTML.
        echo $json;
        exit;
    }

    public static function render() {
        if ( \TaxoCommerce_Social_Autotest_Code::render_subtabs() ) { return; }
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Sin permisos para Autocheck.', 'taxocommerce-social' ) );
        }
        $history = get_transient( self::PREFIX . get_current_user_id() );
        $current = is_array( $history ) && isset( $history[0]['rows'] ) ? $history[0] : null;
        $previous = is_array( $history ) && isset( $history[1]['rows'] ) ? $history[1] : null;

        echo '<section class="seo-social-card">' . '<h2>' . esc_html__( 'Autocheck de TaxoCommerce Social', 'taxocommerce-social' ) . '</h2>';
        echo '<p>' . esc_html__( 'Autocheck de solo lectura: contrasta agenda y WP-Cron, cola activa, permisos guardados, imágenes y resultados reales. No publica, no reintenta y no consulta las APIs externas. Se conservan dos informes durante 30 días.', 'taxocommerce-social' ) . '</p>';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '">';
        wp_nonce_field( self::NONCE, 'tcsocial_auto_nonce' );
        submit_button( esc_html__( 'Ejecutar Autocheck', 'taxocommerce-social' ), 'primary', 'submit', false );
        echo '</form>';
        if ( ! $current ) {
            echo '<p>' . esc_html__( 'No existen resultados todavía. Pulsa Ejecutar Autocheck.', 'taxocommerce-social' ) . '</p>' . '</section>';
            return;
        }
        $totals = $current['totals'];
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="' . esc_attr( self::JSON_ACTION ) . '">';
        wp_nonce_field( self::NONCE, 'tcsocial_auto_nonce' );
        submit_button( __( 'Descargar resultados (JSON)', 'taxocommerce-social' ), 'secondary', 'submit', false );
        echo '</form>';

        echo '<h3>' . esc_html__( 'Último resultado', 'taxocommerce-social' ) . ' · v' . esc_html( $current['version'] ) . ' · ' . esc_html__( 'Fecha de ejecución:', 'taxocommerce-social' ) . ' ' . esc_html( $current['date'] ) . '</h3>';
        echo '<p><strong>' . esc_html( (string) $totals['pass'] ) . ' ' . esc_html__( 'correctas', 'taxocommerce-social' ) . '</strong> · <strong>' . esc_html( (string) $totals['fail'] ) . ' ' . esc_html__( 'fallidas', 'taxocommerce-social' ) . '</strong> · ' . esc_html( (string) $totals['skip'] ) . ' ' . esc_html__( 'omitidas', 'taxocommerce-social' ) . ' · ' . esc_html( (string) $current['ms'] ) . ' ms</p>';
        if ( $previous ) {
            $old = array();
            foreach ( $previous['rows'] as $row ) $old[ $row['id'] ] = $row['status'];
            $regressions = array();
            foreach ( $current['rows'] as $row ) {
                if ( 'fail' === $row['status'] && isset( $old[ $row['id'] ] ) && 'pass' === $old[ $row['id'] ] ) $regressions[] = $row['label'];
            }
            echo '<p><strong>' . esc_html__( 'Comparación:', 'taxocommerce-social' ) . '</strong> ' . esc_html( $previous['version'] . ' (' . $previous['date'] . ') → ' . $current['version'] ) . '. ';
            echo $regressions ? '<strong style="color:#b32d2e">' . esc_html__( 'Regresiones:', 'taxocommerce-social' ) . ' ' . esc_html( implode( ', ', $regressions ) ) . '</strong>' : esc_html__( 'Sin regresiones de correcto a fallido.', 'taxocommerce-social' );
            echo '</p>';
        } else {
            echo '<p>' . esc_html__( 'No existe una ejecución anterior para comparar.', 'taxocommerce-social' ) . '</p>';
        }
        echo '<div style="overflow-x:auto">' . '<table class="widefat striped">' . '<thead>' . '<tr>' . '<th>' . esc_html__( 'Grupo', 'taxocommerce-social' ) . '</th>' . '<th>' . esc_html__( 'Prueba', 'taxocommerce-social' ) . '</th>' . '<th>' . esc_html__( 'Estado', 'taxocommerce-social' ) . '</th>' . '<th>' . esc_html__( 'Diagnóstico', 'taxocommerce-social' ) . '</th>' . '<th>' . esc_html__( 'Tiempo', 'taxocommerce-social' ) . '</th>' . '</tr>' . '</thead>' . '<tbody>';
        $names = array( 'pass' => esc_html__( 'Correcto', 'taxocommerce-social' ), 'fail' => esc_html__( 'Error', 'taxocommerce-social' ), 'skip' => esc_html__( 'No ejecutado', 'taxocommerce-social' ) );
        foreach ( $current['rows'] as $row ) {
            echo '<tr><td>' . esc_html( $row['group'] ) . '</td><td>' . esc_html( $row['label'] ) . '</td><td><strong>' . esc_html( $names[ $row['status'] ] ) . '</strong></td><td>' . esc_html( $row['message'] ) . '</td><td>' . esc_html( (string) $row['ms'] ) . ' ms</td></tr>';
        }
        echo '</tbody>' . '</table>' . '</div>' . '<p class="description">' . esc_html__( 'El resultado comprueba las colas y registros locales. No confirma el estado remoto de OAuth ni que una publicación esté visible en una red social; esas operaciones requieren validación externa.', 'taxocommerce-social' ) . '</p>' . '</section>';
    }
}
TCSocial_Auto_Test::register();
