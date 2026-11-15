<?php
/**
 * Non-destructive, on-demand health checks for TaxoCommerce Insights.
 *
 * No provider HTTP requests, credential refresh, report generation, database
 * mutations, catalog writes or remote tracking take place in this test suite.
 * Only two small, per-user WordPress metadata reports are stored to compare
 * an installation before and after a deployment. No secrets or raw search
 * terms are included in the reports.
 *
 * @package TaxoCommerceInsights
 */
defined( 'ABSPATH' ) || exit;

final class TaxoCommerce_Google_Bing_Auto_Test {
    const ACTION = 'tgb_run_auto_test';
    const EXPORT_ACTION = 'tgb_export_auto_test';
    const NONCE = 'tgb_auto_test';
    const BASELINE_PREFIX = 'tgb_auto_test_before_';
    const LAST_PREFIX = 'tgb_auto_test_last_';

    public static function register() {
        add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'handle_run' ) );
        add_action( 'admin_post_' . self::EXPORT_ACTION, array( __CLASS__, 'handle_export' ) );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
    }

    public static function assets() {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only admin navigation.
        $page = isset( $_GET['page'] ) && is_string( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
        $tab = isset( $_GET['tgb_tab'] ) && is_string( $_GET['tgb_tab'] ) ? sanitize_key( wp_unslash( $_GET['tgb_tab'] ) ) : '';
        // phpcs:enable WordPress.Security.NonceVerification.Recommended
        if ( 'taxocommerce-google-bing' === $page && 'auto_test' === $tab ) {
            wp_enqueue_style( 'tgb-auto-test', TAXOCOMMERCE_GOOGLE_BING_URL . 'assets/auto-test.css', array(), TAXOCOMMERCE_GOOGLE_BING_VERSION );
        }
    }

    private static function allowed() {
        return current_user_can( 'manage_options' );
    }

    /** One diagnostic baseline per user AND per site in multisite. */
    private static function key( $type ) {
        $prefix = 'baseline' === $type ? self::BASELINE_PREFIX : self::LAST_PREFIX;
        return $prefix . get_current_blog_id();
    }

    public static function groups() {
        return array(
            'startup'     => 'Arranque y administración',
            'connections' => 'Conexiones externas (estado conocido)',
            'search'      => 'Google Search Console',
            'analytics'   => 'Google Analytics 4',
            'bing'        => 'Bing Webmaster',
            'trends'      => 'Google Trends',
            'analysis'    => 'Analysis y Analista',
            'snapshots'   => 'Informes y exportaciones',
            'security'    => 'Seguridad y compatibilidad',
            'uat'         => 'Pruebas funcionales externas',
        );
    }

    public static function cases() {
        $cases = array(
            'runtime'           => array( 'startup', 'Clases y funciones cargadas' ),
            'navigation'        => array( 'startup', 'Menú, rutas y pantalla Autocheck' ),
            'assets'            => array( 'startup', 'CSS y JavaScript de las pestañas' ),
            'core'              => array( 'startup', 'Integración opcional en Core' ),
            'connection_hooks'  => array( 'connections', 'Acciones de conexiones y credenciales' ),
            'connection_model'  => array( 'connections', 'Modelo de estado de conexiones' ),
            'google_oauth'      => array( 'connections', 'OAuth de Google y callback compatible' ),
            'amazon_helpers'    => array( 'connections', 'Afiliación y Creators API: contratos' ),
            'pinterest_helpers'=> array( 'connections', 'Pinterest Business y Merchant: contratos' ),
            'gsc_handler'       => array( 'search', 'Descarga manual Search Console' ),
            'gsc_snapshot'      => array( 'search', 'Último informe de Search Console' ),
            'ga4_handler'       => array( 'analytics', 'Descarga manual GA4' ),
            'ga4_snapshot'      => array( 'analytics', 'Último informe de GA4' ),
            'bing_handler'      => array( 'bing', 'Descarga manual y configuración Bing' ),
            'bing_snapshot'     => array( 'bing', 'Último informe de Bing' ),
            'trends_hooks'      => array( 'trends', 'Consulta y exportación de Trends' ),
            'trends_terms'      => array( 'trends', 'Normalización y límite de cinco términos' ),
            'trends_link'       => array( 'trends', 'Enlace Explore oficial, país ES' ),
            'trends_locale'     => array( 'trends', 'Filtro de contenido por idioma y país' ),
            'trends_hierarchy'  => array( 'trends', 'Jerarquía y grupos de comparación (lectura)' ),
            'analyst_catalog'   => array( 'analysis', 'Catálogo de once informes bajo demanda' ),
            'analyst_ajax'      => array( 'analysis', 'AJAX protegido de Analista' ),
            'analyst_tests'     => array( 'analysis', 'Pruebas deterministas del motor Analista 3.8.3' ),
            'report_store'      => array( 'snapshots', 'Contrato de lectura de tres proveedores' ),
            'report_structure'  => array( 'snapshots', 'Validación de metadatos de snapshots' ),
            'report_exports'    => array( 'snapshots', 'Acciones protegidas de exportación' ),
            'admin_permissions' => array( 'security', 'Permisos y nonces de Autocheck' ),
            'oauth_safelist'    => array( 'security', 'Destino OAuth de Google permitido' ),
            'baseline_storage'  => array( 'security', 'Historial de pruebas por usuario y sitio' ),
            'live_requests'     => array( 'uat', 'Verificación HTTP actual de APIs externas' ),
            'download_uat'      => array( 'uat', 'Descarga y exportación reales de cada API' ),
            'oauth_uat'         => array( 'uat', 'Autorización OAuth interactiva' ),
            'trend_embed_uat'   => array( 'uat', 'Renderizado real de gráficos Trends en navegador' ),
        );
        foreach ( array_keys( TaxoCommerce_Connections_Status::definitions() ) as $provider ) {
            $cases[ 'provider_' . $provider ] = array( 'connections', 'Estado de ' . self::provider_label( $provider ) );
        }
        return $cases;
    }

    /** No configuration values, tokens, tags, URLs or raw external errors in labels. */
    private static function provider_label( $id ) {
        $labels = array(
            'gsc' => 'Search Console', 'ga4' => 'Analytics 4',
            'merchant' => 'Merchant Center', 'oauth' => 'Google OAuth',
            'bing' => 'Bing Webmaster', 'trends' => 'Google Trends',
            'amazon_tag' => 'Amazon Afiliados', 'amazon_api' => 'Amazon Creators API',
            'pinterest' => 'Pinterest Business', 'pinterest_catalog' => 'Pinterest Merchant',
        );
        return isset( $labels[ $id ] ) ? $labels[ $id ] : sanitize_key( $id );
    }

    public static function handle_run() {
        if ( ! self::allowed() ) {
            wp_die( esc_html__( 'No tienes permisos para ejecutar Autocheck.', 'taxocommerce-google-bing' ) );
        }
        check_admin_referer( self::NONCE, 'tgb_auto_test_nonce' );
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Validated by check_admin_referer() above.
        $group = isset( $_POST['test_group'] ) && is_string( $_POST['test_group'] )
            ? sanitize_key( wp_unslash( $_POST['test_group'] ) ) : 'all';
        $phase = isset( $_POST['test_phase'] ) && is_string( $_POST['test_phase'] )
            ? sanitize_key( wp_unslash( $_POST['test_phase'] ) ) : 'check';
        // phpcs:enable WordPress.Security.NonceVerification.Missing
        if ( 'all' !== $group && ! isset( self::groups()[ $group ] ) ) {
            $group = 'all';
        }
        if ( ! in_array( $phase, array( 'before', 'after', 'check' ), true ) ) {
            $phase = 'check';
        }
        if ( 'all' !== $group ) {
            $phase = 'check';
        }
        $report = self::run_suite( $group );
        $report['phase'] = $phase;
        update_user_meta( get_current_user_id(), self::key( 'last' ), $report );
        if ( 'before' === $phase ) {
            update_user_meta( get_current_user_id(), self::key( 'baseline' ), $report );
        }
        wp_safe_redirect( self::url() );
        exit;
    }

    public static function handle_export() {
        if ( ! self::allowed() ) {
            wp_die( esc_html__( 'Sin permisos para descargar el diagnóstico.', 'taxocommerce-google-bing' ) );
        }
        check_admin_referer( self::NONCE, 'tgb_auto_test_nonce' );
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Validated by check_admin_referer() above.
        $which = isset( $_POST['report_kind'] ) && is_string( $_POST['report_kind'] )
            ? sanitize_key( wp_unslash( $_POST['report_kind'] ) ) : 'last';
        // phpcs:enable WordPress.Security.NonceVerification.Missing
        $which = 'baseline' === $which ? 'baseline' : 'last';
        $report = get_user_meta( get_current_user_id(), self::key( $which ), true );
        if ( ! is_array( $report ) || ! isset( $report['rows'], $report['site_hash'] )
            || ! hash_equals( self::site_hash(), (string) $report['site_hash'] ) ) {
            wp_die( esc_html__( 'No existe un informe válido para esta instalación.', 'taxocommerce-google-bing' ) );
        }
        $report = self::standardize_report( $report );
        $json = wp_json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE );
        if ( false === $json ) {
            wp_die( esc_html__( 'No se ha podido generar el informe JSON.', 'taxocommerce-google-bing' ) );
        }
        nocache_headers();
        header( 'Content-Type: application/json; charset=utf-8' );
        header( 'X-Content-Type-Options: nosniff' );
        header( 'Content-Disposition: attachment; filename="taxocommerce-google-bing-autocheck-' . $which . '-' . gmdate( 'Ymd-His' ) . '.json"' );
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The response is an explicit JSON download, not HTML.
        echo $json;
        exit;
    }

    private static function url() {
        return add_query_arg(
            array( 'page' => 'taxocommerce-google-bing', 'tgb_tab' => 'auto_test' ),
            admin_url( 'admin.php' )
        );
    }

    private static function site_hash() {
        return hash( 'sha256', (string) home_url( '/' ) );
    }

    private static function result( $status, $message ) {
        return array( 'status' => $status, 'message' => (string) $message );
    }
    private static function pass( $message ) { return self::result( 'pass', $message ); }
    private static function fail( $message ) { return self::result( 'fail', $message ); }
    private static function skip( $message ) { return self::result( 'skip', $message ); }

    /** Execute groups independently. One error must not hide later results. */
    /**
     * Canonical, backwards-compatible functional report fields.
     * Never writes to the database and never exposes private site URLs.
     *
     * @param array $report Stored or newly generated report.
     * @return array Normalized report with historical keys preserved.
     */
    public static function standardize_report( $report ) {
        if ( ! is_array( $report ) ) {
            return array();
        }
        $names = self::groups();
        foreach ( (array) ( $report['rows'] ?? array() ) as $index => $row ) {
            if ( ! is_array( $row ) ) {
                continue;
            }
            $group_id = (string) ( $row['group_id'] ?? $row['group'] ?? '' );
            $row['group_id'] = $group_id;
            $row['group_name'] = (string) ( $names[ $group_id ] ?? $row['group'] ?? $group_id );
            $row['label'] = (string) ( $row['label'] ?? $row['name'] ?? '' );
            $row['message'] = (string) ( $row['message'] ?? $row['detail'] ?? '' );
            $row['ms'] = max( 0, (int) ( $row['ms'] ?? 0 ) );
            $report['rows'][ $index ] = $row;
        }
        $report['wordpress'] = (string) ( $report['wordpress'] ?? $report['wordpress_version'] ?? $report['wp_version'] ?? '' );
        $report['php'] = (string) ( $report['php'] ?? $report['php_version'] ?? '' );
        $report['date'] = (string) ( $report['date'] ?? $report['date_utc'] ?? $report['executed_at'] ?? '' );
        $report['executed_at'] = (string) ( $report['executed_at'] ?? $report['date_utc'] ?? $report['date'] );
        $report['timestamp'] = max( 0, (int) ( $report['timestamp'] ?? strtotime( $report['executed_at'] ) ) );
        return $report;
    }

    public static function run_suite( $group = 'all', $cases = null ) {
        $groups = self::groups();
        $group = 'all' === $group || isset( $groups[ $group ] ) ? $group : 'all';
        $cases = null === $cases ? self::cases() : $cases;
        $start = microtime( true );
        $rows = array();
        $totals = array( 'pass' => 0, 'fail' => 0, 'skip' => 0, 'not_run' => 0 );
        foreach ( $cases as $id => $labels ) {
            $begin = microtime( true );
            if ( 'all' !== $group && $group !== $labels[0] ) {
                $outcome = self::result( 'not_run', 'Este grupo no se ha ejecutado.' );
            } else {
                try {
                    $method = array( __CLASS__, 'check_' . $id );
                    if ( is_callable( $method ) ) {
                        $outcome = call_user_func( $method );
                    } elseif ( 0 === strpos( $id, 'provider_' ) ) {
                        $outcome = self::check_provider( substr( $id, 9 ) );
                    } else {
                        $outcome = self::fail( 'Comprobación sin implementación.' );
                    }
                    if ( ! is_array( $outcome ) || ! isset( $outcome['status'], $outcome['message'] )
                        || ! in_array( $outcome['status'], array( 'pass', 'fail', 'skip' ), true ) ) {
                        $outcome = self::fail( 'Comprobación con respuesta inválida.' );
                    }
                } catch ( Throwable $error ) {
                    // Exception contents may include tokens, URLs or credentials: do not persist them.
                    $outcome = self::fail( 'Excepción durante esta prueba (' . get_class( $error ) . '). Consulte el log privado del servidor.' );
                }
            }
            $outcome['id'] = (string) $id;
            $outcome['group_id'] = (string) $labels[0];
            $outcome['group'] = (string) ( $groups[ $labels[0] ] ?? $labels[0] );
            $outcome['label'] = (string) $labels[1];
            $outcome['ms'] = (int) round( ( microtime( true ) - $begin ) * 1000 );
            $rows[] = $outcome;
            ++$totals[ $outcome['status'] ];
        }
        global $wp_version;
        return self::standardize_report( array(
            'schema' => 1,
            'phase' => 'check',
            'site_hash' => self::site_hash(),
            'version' => defined( 'TAXOCOMMERCE_GOOGLE_BING_VERSION' ) ? TAXOCOMMERCE_GOOGLE_BING_VERSION : '',
            'wordpress_version' => isset( $wp_version ) ? (string) $wp_version : '',
            'php_version' => PHP_VERSION,
            'date' => current_time( 'mysql' ), 'executed_at' => gmdate( 'c' ),
            'timestamp' => time(),
            'group' => $group,
            'duration_ms' => (int) round( ( microtime( true ) - $start ) * 1000 ),
            'totals' => $totals,
            'rows' => $rows,
        ) );
    }

    /** A pure comparison for reproducible pre/post deployment regression tests. */
    public static function compare_reports( $before, $after ) {
        if ( ! is_array( $before ) || ! is_array( $after )
            || empty( $before['site_hash'] ) || empty( $after['site_hash'] )
            || ! hash_equals( (string) $before['site_hash'], (string) $after['site_hash'] )
            || ( $before['group'] ?? '' ) !== 'all'
            || ( $after['group'] ?? '' ) !== 'all'
            || ( $after['phase'] ?? '' ) !== 'after' ) {
            return null;
        }
        $old = array();
        $new = array();
        foreach ( (array) ( $before['rows'] ?? array() ) as $row ) {
            if ( isset( $row['id'], $row['status'] ) ) { $old[ $row['id'] ] = $row['status']; }
        }
        foreach ( (array) ( $after['rows'] ?? array() ) as $row ) {
            if ( isset( $row['id'], $row['status'] ) ) { $new[ $row['id'] ] = $row['status']; }
        }
        $result = array( 'new_failures' => array(), 'resolved' => array(), 'unchanged_failures' => array(), 'coverage_lost' => array(), 'new_checks' => array() );
        foreach ( $new as $id => $status ) {
            if ( ! isset( $old[ $id ] ) ) {
                $result['new_checks'][] = $id;
                if ( 'fail' === $status ) { $result['new_failures'][] = $id; }
                continue;
            }
            if ( 'fail' === $status ) {
                if ( 'fail' === $old[ $id ] ) { $result['unchanged_failures'][] = $id; }
                else { $result['new_failures'][] = $id; }
            } elseif ( 'fail' === $old[ $id ] && 'pass' === $status ) {
                $result['resolved'][] = $id;
            } elseif ( 'pass' === $old[ $id ] && ( 'skip' === $status || 'not_run' === $status ) ) {
                $result['coverage_lost'][] = $id;
            } elseif ( 'fail' === $old[ $id ] && ( 'skip' === $status || 'not_run' === $status ) ) {
                $result['coverage_lost'][] = $id;
            }
        }
        foreach ( $old as $id => $status ) {
            if ( ! array_key_exists( $id, $new ) ) { $result['coverage_lost'][] = $id; }
        }
        return $result;
    }

    private static function missing( $list, $hook = false ) {
        $missing = array();
        foreach ( $list as $value ) {
            if ( $hook ? ! has_action( $value ) : ! function_exists( $value ) ) {
                $missing[] = $value;
            }
        }
        return $missing;
    }

    private static function check_runtime() {
        $classes = array(
            'TaxoCommerce_Google_Bing_Connections', 'TaxoCommerce_Google_Bing_OAuth',
            'TaxoCommerce_Google_Bing_GSC', 'TaxoCommerce_Google_Bing_Reports',
            'TaxoCommerce_Google_Bing_Trends', 'TaxoCommerce_Google_Bing_Trends_Hierarchy',
            'TaxoCommerce_Connections_Amazon', 'TaxoCommerce_Connections_Pinterest',
            'TaxoCommerce_Connections_Status', 'TaxoCommerce_Connections_Admin',
            'TaxoCommerce_Google_Bing_User_Guide', __CLASS__,
        );
        foreach ( $classes as $class ) {
            if ( ! class_exists( $class ) ) { return self::fail( 'No carga el componente: ' . $class ); }
        }
        return self::pass( count( $classes ) . ' componentes cargados correctamente; versión ' . TAXOCOMMERCE_GOOGLE_BING_VERSION . '.' );
    }

    private static function check_navigation() {
        $missing = self::missing( array( 'taxocommerce_google_bing_register_menu', 'taxocommerce_google_bing_screen' ) );
        if ( $missing || ! has_action( 'admin_menu', 'taxocommerce_google_bing_register_menu' ) ) {
            return self::fail( 'Falta el registro del menú o la pantalla Insights.' );
        }
        return self::pass( 'Menú principal y pantalla registrados. Autocheck tiene ruta y acciones separadas.' );
    }

    private static function check_assets() {
        $files = array(
            'assets/connections-guide.css', 'assets/trends-hierarchy.js',
            'assets/user-guide.css', 'assets/auto-test.css',
            'includes/analysis/assets/analista-lazy.js',
            'includes/analysis/assets/analista-informe.css',
        );
        foreach ( $files as $name ) {
            if ( ! is_readable( TAXOCOMMERCE_GOOGLE_BING_PATH . $name ) ) {
                return self::fail( 'Falta un recurso obligatorio: ' . $name );
            }
        }
        return self::pass( count( $files ) . ' recursos del administrador presentes y legibles.' );
    }

    private static function check_core() {
        if ( ! function_exists( 'taxocommerce_core' ) && ! defined( 'TAXOCOMMERCE_CORE_VERSION' ) ) {
            return self::skip( 'Core es opcional y no está activo.' );
        }
        return has_action( 'taxocommerce_core_register_modules', 'taxocommerce_google_bing_register_with_core' )
            ? self::pass( 'Integración con Core registrada.' )
            : self::fail( 'Core está presente, pero falta el registro de Insights.' );
    }

    private static function check_connection_hooks() {
        $hooks = array(
            'admin_post_tgb_save_google', 'admin_post_tgb_test_google',
            'admin_post_tgb_google_oauth_save', 'admin_post_tgb_google_oauth_connect',
            'admin_post_tgb_google_oauth_disconnect', 'admin_post_tgb_google_oauth_test',
            'admin_post_tgb_save_bing', 'admin_post_tgb_test_bing',
            'admin_post_taxocommerce_connections_save_amazon',
            'admin_post_taxocommerce_connections_save_pinterest',
            'admin_post_taxocommerce_connections_test_amazon',
            'admin_post_taxocommerce_connections_test_pinterest',
            'admin_post_tgb_check_connection',
        );
        $missing = self::missing( $hooks, true );
        return $missing ? self::fail( 'Acciones no registradas: ' . implode( ', ', $missing ) )
            : self::pass( count( $hooks ) . ' acciones de conexiones protegidas disponibles.' );
    }

    private static function check_connection_model() {
        if ( ! method_exists( 'TaxoCommerce_Connections_Status', 'definitions' ) ||
            ! method_exists( 'TaxoCommerce_Connections_Status', 'state' ) ) {
            return self::fail( 'No existe el modelo de salud de conexiones.' );
        }
        $definitions = TaxoCommerce_Connections_Status::definitions();
        return is_array( $definitions ) && count( $definitions ) >= 10
            ? self::pass( count( $definitions ) . ' proveedores evaluables sin llamadas a las APIs.' )
            : self::fail( 'El registro de proveedores es incompleto.' );
    }

    private static function check_provider( $id ) {
        $all = TaxoCommerce_Connections_Status::definitions();
        if ( ! isset( $all[ $id ] ) ) { return self::fail( 'Proveedor no registrado.' ); }
        $config = $all[ $id ];
        $records = get_option( TaxoCommerce_Connections_Status::OPTION, array() );
        $saved = is_array( $records ) ? ( $records[ $id ] ?? array() ) : array();
        $state = TaxoCommerce_Connections_Status::state( $config, $saved );
        if ( 'error' === $state ) {
            return self::fail( 'La última verificación registrada de este proveedor falló. Repetir prueba desde Connections.' );
        }
        if ( 'connected' === $state ) {
            return self::pass( 'Última comprobación externa válida (máximo siete días). No se ha efectuado una nueva petición.' );
        }
        if ( 'public' === $state ) { return self::pass( 'Servicio público; no requiere credenciales.' ); }
        if ( 'configured' === $state && 'local' === ( $config['mode'] ?? '' ) ) {
            return self::pass( 'Configuración local existente; no equivale a una prueba externa.' );
        }
        if ( 'configured' === $state ) {
            return self::skip( 'Credenciales configuradas, pero sin prueba externa reciente. Usa Connections → Comprobar.' );
        }
        if ( 'disabled' === $state ) { return self::skip( 'Proveedor desactivado; no aplicable.' ); }
        return self::skip( 'Proveedor sin configurar. Si lo utilizas, completa Connections y pruébalo.' );
    }

    private static function check_google_oauth() {
        $hooks = array(
            'admin_post_seo_google_oauth_callback', 'admin_post_tgb_google_oauth_connect',
            'admin_post_tgb_google_oauth_test',
        );
        foreach ( $hooks as $hook ) {
            if ( ! has_action( $hook ) ) { return self::fail( 'Falta la ruta OAuth: ' . $hook ); }
        }
        $url = TaxoCommerce_Google_Bing_OAuth::redirect_uri();
        return false !== strpos( $url, 'action=seo_google_oauth_callback' )
            ? self::pass( 'Callback histórico compatible; acciones OAuth disponibles.' )
            : self::fail( 'La URL de retorno OAuth ha cambiado.' );
    }

    private static function check_amazon_helpers() {
        foreach ( array( 'settings', 'affiliate_ready', 'creators_ready', 'affiliate_search_url', 'search_items' ) as $method ) {
            if ( ! method_exists( 'TaxoCommerce_Connections_Amazon', $method ) ) {
                return self::fail( 'Método de Amazon ausente: ' . $method );
            }
        }
        return self::pass( 'Contratos de afiliación y Creators API presentes; ninguna llamada remota realizada.' );
    }

    private static function check_pinterest_helpers() {
        foreach ( array( 'settings', 'test_connection', 'merchant_settings', 'register_rest_route', 'oauth_callback' ) as $method ) {
            if ( ! method_exists( 'TaxoCommerce_Connections_Pinterest', $method ) ) {
                return self::fail( 'Método Pinterest ausente: ' . $method );
            }
        }
        return has_action( 'rest_api_init', array( 'TaxoCommerce_Connections_Pinterest', 'register_rest_route' ) )
            ? self::pass( 'OAuth, lectura de tableros y Merchant disponibles como contratos.' )
            : self::fail( 'No se ha registrado la ruta REST de Pinterest.' );
    }

    private static function check_gsc_handler() {
        return method_exists( 'TaxoCommerce_Google_Bing_GSC', 'download' )
            && has_action( 'admin_post_tgb_download_report' )
            ? self::pass( 'Motor Search Console y descarga explícita disponibles.' )
            : self::fail( 'Falta el motor Search Console o su ruta de descarga.' );
    }

    private static function check_ga4_handler() {
        return method_exists( 'TaxoCommerce_Google_Bing_Reports', 'download_report' )
            && method_exists( 'TaxoCommerce_Google_Bing_Connections', 'access_token' )
            ? self::pass( 'Descarga GA4 y autenticación de servicio disponibles.' )
            : self::fail( 'No están disponibles las rutas GA4 o de autenticación.' );
    }

    private static function check_bing_handler() {
        $settings = TaxoCommerce_Google_Bing_Connections::bing_settings();
        if ( ! is_array( $settings ) || ! isset( $settings['site_url'] ) ) {
            return self::fail( 'La configuración Bing no tiene el formato esperado.' );
        }
        return has_action( 'admin_post_tgb_test_bing' ) && has_action( 'admin_post_tgb_download_report' )
            ? self::pass( 'Configuración Bing legible y acciones de prueba/descarga presentes.' )
            : self::fail( 'Falta un controlador de Bing.' );
    }

    private static function snapshot( $source ) {
        $entries = TaxoCommerce_Google_Bing_Reports::saved_reports( $source );
        if ( ! is_array( $entries ) ) { return self::fail( 'La lectura del almacén devolvió un tipo incorrecto.' ); }
        if ( ! $entries ) {
            return self::skip( 'No hay informes guardados; descargar uno manualmente desde su pestaña.' );
        }
        $latest = end( $entries );
        if ( ! is_array( $latest ) || ( $latest['source'] ?? '' ) !== $source
            || empty( $latest['downloaded_at'] ) || ! isset( $latest['data'] )
            || ! is_array( $latest['data'] ) || ! isset( $latest['current']['start'], $latest['current']['end'] )
            || ! isset( $latest['previous']['start'], $latest['previous']['end'] ) ) {
            return self::fail( 'El último snapshot tiene estructura incompleta.' );
        }
        $found = TaxoCommerce_Google_Bing_Reports::get_saved_snapshot(
            $source, $latest['current']['start'], $latest['current']['end']
        );
        return is_array( $found )
            ? self::pass( count( $entries ) . ' versiones almacenadas; última lectura y búsqueda por fechas correctas.' )
            : self::fail( 'El informe se guarda, pero no se recupera por su clave de fechas.' );
    }
    private static function check_gsc_snapshot() { return self::snapshot( 'search' ); }
    private static function check_ga4_snapshot() { return self::snapshot( 'analytics' ); }
    private static function check_bing_snapshot() { return self::snapshot( 'bing' ); }

    private static function check_trends_hooks() {
        $missing = self::missing( array( 'admin_post_tgb_trends_explore', 'admin_post_tgb_trends_download', 'admin_post_tgb_trends_export' ), true );
        return ! $missing
            ? self::pass( 'Exploración y compatibilidad RSS/JSON registradas.' )
            : self::fail( 'Faltan acciones de Google Trends.' );
    }

    private static function check_trends_terms() {
        $terms = TaxoCommerce_Google_Bing_Trends::parse_terms( "taladros; martillos; taladros" );
        $over = TaxoCommerce_Google_Bing_Trends::parse_terms( "a,b,c,d,e,f" );
        return is_array( $terms ) && count( $terms ) === 2 && is_wp_error( $over )
            ? self::pass( 'El parser elimina duplicados y rechaza listas de más de cinco términos.' )
            : self::fail( 'La validación de consultas de Trends es incorrecta.' );
    }

    private static function check_trends_link() {
        $url = TaxoCommerce_Google_Bing_Trends::explore_link( array( 'taladros' ), 'US', 'today 12-m' );
        $parts = wp_parse_url( $url );
        $args = array();
        if ( is_array( $parts ) && isset( $parts['query'] ) ) {
            wp_parse_str( $parts['query'], $args );
        }
        return is_array( $parts ) && ( $parts['host'] ?? '' ) === 'trends.google.com'
            && ( $args['geo'] ?? '' ) === 'ES'
            ? self::pass( 'Explore usa el dominio oficial y el ámbito ES previsto.' )
            : self::fail( 'El enlace Explore no utiliza el dominio o país esperado.' );
    }

    private static function check_trends_locale() {
        return TaxoCommerce_Google_Bing_Trends_Hierarchy::is_spanish( 'es-ES', 'ES' )
            && ! TaxoCommerce_Google_Bing_Trends_Hierarchy::is_spanish( 'de-DE', 'DE' )
            && ! TaxoCommerce_Google_Bing_Trends_Hierarchy::is_spanish( 'en-US', 'US' )
            ? self::pass( 'Filtro de contenidos españoles y exclusión de traducciones correctos.' )
            : self::fail( 'El filtro internacional de jerarquía ha sufrido una regresión.' );
    }

    private static function check_trends_hierarchy() {
        if ( ! class_exists( 'TaxoCommerce_Core_Hierarchy_Classic' )
            || ! TaxoCommerce_Core_Hierarchy_Classic::available() ) {
            return self::skip( 'El árbol SEO de Core no está disponible en esta instalación.' );
        }
        $tree = TaxoCommerce_Google_Bing_Trends_Hierarchy::tree();
        if ( ! is_array( $tree ) ) { return self::fail( 'El árbol de comparaciones no es un array.' ); }
        if ( ! $tree ) { return self::skip( 'No hay clústeres españoles publicados para comparar.' ); }
        $groups = TaxoCommerce_Google_Bing_Trends_Hierarchy::comparison_groups( $tree );
        if ( ! is_array( $groups ) || ! $groups ) { return self::fail( 'La jerarquía existe, pero no se generan comparaciones.' ); }
        foreach ( $groups as $group ) {
            if ( ! is_array( $group ) || count( (array) ( $group['terms'] ?? array() ) ) > 5 ) {
                return self::fail( 'El agrupador supera el límite de cinco términos.' );
            }
        }
        return self::pass( count( $tree ) . ' clústeres y ' . count( $groups ) . ' grupos calculados sin llamar a Google.' );
    }

    private static function check_analyst_catalog() {
        if ( ! function_exists( 'seo_analista_lazy_reports' ) ) {
            return self::fail( 'El catálogo de informes Analista no está disponible.' );
        }
        $expected = array(
            'directrices', 'resumen', 'search_console', 'contenido', 'estructura', 'catalogo',
            'tendencias', 'trabajo', 'competencia', 'proveedores', 'fuentes',
        );
        $reports = seo_analista_lazy_reports();
        return is_array( $reports ) && count( $reports ) === 11
            && ! array_diff( $expected, array_keys( $reports ) )
            ? self::pass( 'Los once bloques de Analysis están registrados para ejecución individual.' )
            : self::fail( 'La lista de once informes es incompleta o ha cambiado inesperadamente.' );
    }

    private static function check_analyst_ajax() {
        return function_exists( 'seo_analista_ajax_partial_report' )
            && function_exists( 'seo_analista_lazy_render_partial' )
            && has_action( 'wp_ajax_seo_analista_partial_report', 'seo_analista_ajax_partial_report' )
            && has_action( 'admin_post_seo_analista_export_json' )
            ? self::pass( 'Renderizado parcial, AJAX y exportador JSON disponibles.' )
            : self::fail( 'Un endpoint del motor Analysis no está registrado.' );
    }

    private static function check_analyst_tests() {
        if ( ! function_exists( 'seo_analista_383_self_tests' ) ) {
            return self::fail( 'El paquete de pruebas deterministas de Analista falta.' );
        }
        $tests = seo_analista_383_self_tests();
        if ( ! is_array( $tests ) || ! isset( $tests['total'], $tests['failed'] ) ) {
            return self::fail( 'Analista devolvió resultados de pruebas incorrectos.' );
        }
        if ( (int) $tests['failed'] > 0 ) {
            return self::fail( (int) $tests['failed'] . ' de ' . absint( $tests['total'] ) . ' regresiones de Analista fallidas. Consultar diagnóstico Analista.' );
        }
        return self::pass( absint( $tests['total'] ) . ' casos deterministas de Analista superados.' );
    }

    private static function check_report_store() {
        if ( TaxoCommerce_Google_Bing_Reports::STORE_PREFIX !== 'tgb_manual_report_v1_' ) {
            return self::fail( 'Ha cambiado la clave del almacén de informes.' );
        }
        foreach ( array( 'analytics', 'search', 'bing' ) as $source ) {
            if ( ! is_array( TaxoCommerce_Google_Bing_Reports::saved_reports( $source ) ) ) {
                return self::fail( 'No se puede leer el almacén de ' . $source );
            }
        }
        return self::pass( 'Los tres almacenes locales responden sin descargar información.' );
    }

    private static function check_report_structure() {
        $total = 0;
        foreach ( array( 'analytics', 'search', 'bing' ) as $source ) {
            $items = TaxoCommerce_Google_Bing_Reports::saved_reports( $source );
            foreach ( $items as $item ) {
                ++$total;
                if ( ! is_array( $item ) || ! isset( $item['data'], $item['source'], $item['current'], $item['previous'] )
                    || ! is_array( $item['data'] ) || $item['source'] !== $source ) {
                    return self::fail( 'Metadatos incorrectos en el almacén de ' . $source );
                }
            }
        }
        return $total ? self::pass( $total . ' snapshots con contrato mínimo válido.' )
            : self::skip( 'Todavía no hay snapshots descargados para validar.' );
    }

    private static function check_report_exports() {
        return has_action( 'admin_post_tgb_export_report' )
            && has_action( 'admin_post_seo_analista_export_json' )
            && has_action( 'admin_post_tgb_trends_export' )
            ? self::pass( 'Exportaciones de informes, Analysis y Trends registradas.' )
            : self::fail( 'Falta un exportador de informes.' );
    }

    private static function check_admin_permissions() {
        return has_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'handle_run' ) )
            && has_action( 'admin_post_' . self::EXPORT_ACTION, array( __CLASS__, 'handle_export' ) )
            && self::allowed()
            ? self::pass( 'Ejecución y exportación solo de administradores, con nonce independiente.' )
            : self::fail( 'Faltan las acciones protegidas de Autocheck.' );
    }

    private static function check_oauth_safelist() {
        return method_exists( 'TaxoCommerce_Google_Bing_OAuth', 'allow_google_oauth_host' )
            && in_array( 'accounts.google.com', TaxoCommerce_Google_Bing_OAuth::allow_google_oauth_host( array() ), true )
            ? self::pass( 'El host autorizado en el flujo OAuth es accounts.google.com.' )
            : self::fail( 'No está disponible la lista permitida para el OAuth externo.' );
    }

    private static function check_baseline_storage() {
        return function_exists( 'get_user_meta' ) && function_exists( 'update_user_meta' )
            && 'tgb_auto_test_before_' . get_current_blog_id() === self::key( 'baseline' )
            && 'tgb_auto_test_last_' . get_current_blog_id() === self::key( 'last' )
            ? self::pass( 'Informes antes/después aislados por administrador y sitio, sin secretos.' )
            : self::fail( 'El mecanismo de conservación de pruebas no está disponible.' );
    }

    private static function check_live_requests() {
        return self::skip( 'No se realizan llamadas externas automáticamente. En Connections ejecuta Comprobar por cada proveedor activo.' );
    }

    private static function check_download_uat() {
        return self::skip( 'Una descarga real puede consumir cuotas y sobrescribir snapshots: probar manualmente Google Search, GA4 y Bing en cada despliegue.' );
    }

    private static function check_oauth_uat() {
        return self::skip( 'Reautorizar Google/Pinterest cambia tokens y requiere interacción; comprobar login y callback manualmente si cambian.' );
    }

    private static function check_trend_embed_uat() {
        return self::skip( 'El embed de Google Trends solo puede verificarse visualmente en el navegador; esta prueba valida URLs, términos y jerarquía.' );
    }

    private static function stored_report( $type ) {
        $value = get_user_meta( get_current_user_id(), self::key( $type ), true );
        return is_array( $value ) && ! empty( $value['rows'] )
            && isset( $value['site_hash'] ) && hash_equals( self::site_hash(), (string) $value['site_hash'] )
            ? self::standardize_report( $value ) : array();
    }

    private static function export_form( $kind, $label ) {
        ?>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="tgb-at-export">
            <input type="hidden" name="action" value="<?php echo esc_attr( self::EXPORT_ACTION ); ?>">
            <input type="hidden" name="report_kind" value="<?php echo esc_attr( $kind ); ?>">
            <?php wp_nonce_field( self::NONCE, 'tgb_auto_test_nonce' ); ?>
            <button type="submit" class="button button-secondary"><?php echo esc_html( $label ); ?></button>
        </form>
        <?php
    }

    private static function run_form( $group, $phase, $label, $primary = false ) {
        ?>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="tgb-at-run">
            <input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>">
            <input type="hidden" name="test_group" value="<?php echo esc_attr( $group ); ?>">
            <input type="hidden" name="test_phase" value="<?php echo esc_attr( $phase ); ?>">
            <?php wp_nonce_field( self::NONCE, 'tgb_auto_test_nonce' ); ?>
            <button type="submit" class="button <?php echo $primary ? 'button-primary' : 'button-secondary'; ?>"><?php echo esc_html( $label ); ?></button>
        </form>
        <?php
    }

    public static function render() {
        if ( \TaxoCommerce_Insights_Autotest_Code::render_subtabs() ) { return; }
        if ( ! self::allowed() ) {
            wp_die( esc_html__( 'No tienes permiso para abrir Autocheck.', 'taxocommerce-google-bing' ) );
        }
        $last = self::stored_report( 'last' );
        $groups = self::groups();
        $cases = self::cases();
        $indexed = array();
        foreach ( (array) ( $last['rows'] ?? array() ) as $row ) {
            if ( isset( $row['id'] ) ) { $indexed[ $row['id'] ] = $row; }
        }
        $totals = $last['totals'] ?? array( 'pass' => 0, 'fail' => 0, 'skip' => 0, 'not_run' => count( $cases ) );
        ?>
        <section class="tgb-at" aria-labelledby="tgb-at-title">
            <h2 id="tgb-at-title"><?php esc_html_e( 'Autocheck de TaxoCommerce Insights', 'taxocommerce-google-bing' ); ?></h2>
            <p><?php esc_html_e( 'Diagnóstico manual y no destructivo: comprueba las funcionalidades y la configuración sin importar ni exportar archivos, generar informes ni modificar conexiones.', 'taxocommerce-google-bing' ); ?></p>
            <div class="tgb-at-actions">
                <?php self::run_form( 'all', 'check', __( 'Ejecutar Autocheck', 'taxocommerce-google-bing' ) ); ?>
                <?php if ( $last ) : ?>
                    <?php self::export_form( 'last', __( 'Descargar resultados (JSON)', 'taxocommerce-google-bing' ) ); ?>
                <?php else : ?>
                    <button class="button button-secondary" type="button" disabled><?php esc_html_e( 'Descargar resultados (JSON)', 'taxocommerce-google-bing' ); ?></button>
                <?php endif; ?>
            </div>
            <?php if ( $last ) : ?>
                <p class="tgb-at-last">
                    <strong><?php esc_html_e( 'Fecha de ejecución:', 'taxocommerce-google-bing' ); ?></strong>
                    <?php echo esc_html( (string) ( $last['executed_at'] ?? '' ) ); ?>
                    · <?php echo esc_html( 'v' . (string) ( $last['version'] ?? '' ) ); ?>
                    · <?php echo esc_html( absint( $last['duration_ms'] ?? 0 ) . ' ms' ); ?>
                </p>
            <?php else : ?>
                <p class="description"><?php esc_html_e( 'Todavía no se ha ejecutado el Autocheck en este sitio.', 'taxocommerce-google-bing' ); ?></p>
            <?php endif; ?>
            <div class="tgb-at-totals" aria-label="<?php esc_attr_e( 'Resultados del último chequeo', 'taxocommerce-google-bing' ); ?>">
                <div><strong><?php echo esc_html( absint( $totals['pass'] ?? 0 ) ); ?></strong><span><?php esc_html_e( 'Correctos', 'taxocommerce-google-bing' ); ?></span></div>
                <div class="tgb-at-danger"><strong><?php echo esc_html( absint( $totals['fail'] ?? 0 ) ); ?></strong><span><?php esc_html_e( 'Errores', 'taxocommerce-google-bing' ); ?></span></div>
                <div><strong><?php echo esc_html( absint( $totals['skip'] ?? 0 ) ); ?></strong><span><?php esc_html_e( 'Omitidos / externos', 'taxocommerce-google-bing' ); ?></span></div>
                <div><strong><?php echo esc_html( absint( $totals['not_run'] ?? 0 ) ); ?></strong><span><?php esc_html_e( 'No ejecutadas', 'taxocommerce-google-bing' ); ?></span></div>
            </div>
            <?php foreach ( $groups as $group_id => $title ) : ?>
                <?php
                $group_cases = array_filter( $cases, static function ( $entry ) use ( $group_id ) {
                    return $entry[0] === $group_id;
                } );
                $failed = 0;
                foreach ( $group_cases as $id => $entry ) {
                    if ( 'fail' === ( $indexed[ $id ]['status'] ?? '' ) ) { ++$failed; }
                }
                ?>
                <details class="tgb-at-group" <?php echo $failed || ! $last ? 'open' : ''; ?>>
                    <summary><?php echo esc_html( $title . ' · ' . count( $group_cases ) . ' pruebas' . ( $failed ? ' · ' . $failed . ' errores' : '' ) ); ?></summary>
                    <?php foreach ( $group_cases as $id => $entry ) : ?>
                        <?php $row = $indexed[ $id ] ?? array( 'status' => 'not_run', 'message' => 'No ejecutada.', 'ms' => 0 ); ?>
                        <div class="tgb-at-row">
                            <span class="tgb-at-dot tgb-at-dot--<?php echo esc_attr( $row['status'] ); ?>" aria-hidden="true"></span>
                            <div>
                                <strong><?php echo esc_html( (string) ( $row['label'] ?? $entry[1] ) ); ?> <small><?php echo esc_html( $id ); ?></small></strong>
                                <p><?php echo esc_html( (string) $row['message'] ); ?></p>
                                <small><?php echo esc_html( absint( $row['ms'] ) . ' ms' ); ?></small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </details>
            <?php endforeach; ?>
            <p class="description"><?php esc_html_e( 'Verde: contrato comprobado. Rojo: error detectado. Gris: sin prueba en vivo o no aplicable. El autochequeo no equivale a probar compras, llamadas API ni autorizaciones externas en producción. Para validar proveedores utiliza también las acciones «Comprobar» de Connections.', 'taxocommerce-google-bing' ); ?></p>
        </section>
        <?php
    }
}
