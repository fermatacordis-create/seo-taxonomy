<?php
/**
 * On-demand, read-only functional smoke checks for TaxoCommerce Core.
 * Only a user-specific report transient is written; site content is untouched.
 *
 * @package TaxoCommerceCore
 */
defined( 'ABSPATH' ) || exit;

final class TaxoCommerce_Core_Auto_Test {
    const PAGE_SLUG = 'taxocommerce-core-auto-test';
    const ACTION = 'taxocommerce_core_run_auto_test';
    const NONCE = 'taxocommerce_core_auto_test_run';
    const EXPORT_ACTION = 'taxocommerce_core_export_auto_test_json';
    const EXPORT_NONCE = 'taxocommerce_core_auto_test_export';
    const REPORT_PREFIX = 'taxocommerce_core_autotest_';

    public static function register_hooks() {
        // The suite runs only after a deliberate POST from the Core dashboard.
        // The dashboard load hook runs after WordPress has registered admin menus,
        // allowing access tests to inspect the real route registry.
        add_action( 'load-toplevel_page_taxocommerce-core', array( __CLASS__, 'handle_dashboard_run' ) );
        add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'handle_run' ) );
        add_action( 'admin_post_' . self::EXPORT_ACTION, array( __CLASS__, 'handle_export_json' ) );
    }

    private static function allowed() {
        return current_user_can( apply_filters( 'taxocommerce_core_manage_capability', 'manage_options' ) );
    }

    /**
     * The new manual execution path has a fully initialized WordPress admin menu.
     * GET requests, activations, updates and deployments never run the suite.
     */
    public static function handle_dashboard_run() {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Read-only trigger guard; handle_run() immediately verifies capability and nonce.
        if ( ! isset( $_POST['tc_autotest_manual_run'] ) ) {
            return;
        }
        self::handle_run();
    }

    /** Keep the historical manual admin-post endpoint for bookmarked forms. */
    public static function handle_run() {
        if ( ! self::allowed() ) {
            wp_die( esc_html__( 'Sin permisos para ejecutar autocheck.', 'taxocommerce-core' ) );
        }
        check_admin_referer( self::NONCE, 'tc_autotest_nonce' );
        set_transient( self::REPORT_PREFIX . get_current_user_id(), self::run_suite(), DAY_IN_SECONDS );
        wp_safe_redirect( add_query_arg( array( 'page' => 'taxocommerce-core', 'tab' => 'autocheck' ), admin_url( 'admin.php' ) ) );
        exit;
    }

    /**
     * Check that this transient belongs to a completed run, rather than
     * treating stale or partial data as a successful test.
     */
    private static function valid_report( $report ) {
        if ( ! is_array( $report ) || ! isset(
            $report['version'], $report['date'], $report['ms'], $report['totals'], $report['rows']
        ) || ! is_array( $report['totals'] ) || ! is_array( $report['rows'] ) ) {
            return false;
        }
        $total = 0;
        foreach ( array( 'pass', 'fail', 'skip' ) as $status ) {
            if ( ! isset( $report['totals'][ $status ] )
                || ! is_numeric( $report['totals'][ $status ] )
                || (int) $report['totals'][ $status ] < 0 ) {
                return false;
            }
            $total += (int) $report['totals'][ $status ];
        }
        if ( 0 === $total || $total !== count( $report['rows'] ) ) {
            return false;
        }
        foreach ( $report['rows'] as $row ) {
            if ( ! is_array( $row )
                || ! isset( $row['id'], $row['group'], $row['label'], $row['status'], $row['message'], $row['ms'] )
                || ! in_array( $row['status'], array( 'pass', 'fail', 'skip' ), true ) ) {
                return false;
            }
        }
        return true;
    }

    /**
     * Stable JSON contract: the underlying tests and their statuses come from
     * the saved report, not from a new run or an assumption of UAT success.
     */
    public static function export_payload( $report ) {
        if ( ! self::valid_report( $report ) ) {
            return null;
        }
        $totals = $report['totals'];
        return array(
            'schema_version' => 1,
            'plugin' => array(
                'slug' => 'taxocommerce-core',
                'version' => (string) $report['version'],
            ),
            'installation' => array(
                'url' => home_url( '/' ),
                'locale' => get_locale(),
            ),
            'autocheck' => array(
                'executed_at' => (string) ( $report['executed_at'] ?? $report['date'] ),
                'duration_ms' => (int) $report['ms'],
                'mode' => 'read_only',
                'overall_status' => (int) $totals['fail'] > 0 ? 'fail'
                    : ( (int) $totals['skip'] > 0 ? 'incomplete' : 'pass' ),
                'totals' => array(
                    'pass' => (int) $totals['pass'],
                    'fail' => (int) $totals['fail'],
                    'skip' => (int) $totals['skip'],
                ),
                'tests' => $report['rows'],
                'errors' => array_values( array_map( static function( $row ) {
                    return array(
                        'id' => $row['id'],
                        'group' => $row['group'],
                        'message' => $row['message'],
                        'php_errors' => $row['php_errors'] ?? array(),
                    );
                }, array_filter( $report['rows'], static function( $row ) {
                    return 'fail' === $row['status'];
                } ) ) ),
                'not_executed' => array_values( array_map( static function( $row ) {
                    return array( 'id' => $row['id'], 'message' => $row['message'] );
                }, array_filter( $report['rows'], static function( $row ) {
                    return 'skip' === $row['status'];
                } ) ) ),
            ),
        );
    }

    /** Download only the signed-in user's last stored report, without rerunning the suite. */
    public static function handle_export_json() {
        if ( ! self::allowed() ) {
            wp_die( esc_html__( 'Sin permisos para descargar el autocheck.', 'taxocommerce-core' ) );
        }
        check_admin_referer( self::EXPORT_NONCE, 'tc_autotest_export_nonce' );
        $report = get_transient( self::REPORT_PREFIX . get_current_user_id() );
        $payload = self::export_payload( $report );
        if ( null === $payload ) {
            wp_die( esc_html__( 'No hay un informe de Autocheck válido. Ejecuta el chequeo primero.', 'taxocommerce-core' ) );
        }
        $json = wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE );
        if ( ! is_string( $json ) ) {
            wp_die( esc_html__( 'No se pudo generar el archivo JSON.', 'taxocommerce-core' ) );
        }
        $host = (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST );
        $host = sanitize_file_name( $host );
        if ( '' === $host ) {
            $host = 'wordpress';
        }
        $filename = 'taxocommerce-core-autocheck-' . $host . '-' . gmdate( 'Ymd-His' ) . '.json';
        nocache_headers();
        header( 'Content-Type: application/json; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'X-Content-Type-Options: nosniff' );
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON encoded, not HTML.
        echo $json;
        exit;
    }

    /** List of observable behaviours, not just installed class names. */
    public static function cases() {
        return array(
            'runtime'      => array( 'Core', 'Arranque y servicios' ),
            'plugin_files' => array( 'Core', 'Versión y archivos esenciales del plugin' ),
            'database'     => array( 'WordPress', 'Consulta de base de datos' ),
            'admin_routes' => array( 'WordPress', 'Acceso a pantallas internas de Core' ),
            'actions'      => array( 'Core', 'Acciones administrativas registradas' ),
            'content'      => array( 'Contenidos', 'Consulta de páginas y entradas' ),
            'catalog'      => array( 'Contenidos', 'Consulta de productos y categorías' ),
            'category_fields' => array( 'Contenidos', 'Descripción y extracto nativos de categorías' ),
            'native_roles' => array( 'Jerarquía', 'Lectura de roles SEO desde WordPress' ),
            'inventory'    => array( 'Contenidos', 'Inventario de contenidos' ),
            'countries'    => array( 'Países', 'Códigos de país disponibles' ),
            'post_country' => array( 'Países', 'País de páginas internacionales' ),
            'term_country' => array( 'Países', 'País de categorías internacionales' ),
            'nodes'        => array( 'SEO Nodes', 'Tabla de roles SEO' ),
            'role_read'    => array( 'SEO Nodes', 'Lectura de un rol guardado' ),
            'relations'    => array( 'SEO Relations', 'Tabla y lectura de relaciones' ),
            'edges'        => array( 'SEO Relations', 'Integridad de extremos y países (muestra)' ),
            'menus'        => array( 'Menús', 'Lectura de menús SEO por país cuando están configurados' ),
            'menu_actions' => array( 'Menús', 'Controles de creación, carga, guardado y eliminación' ),
            'native_classic' => array( 'Menús', 'Menú activo del tema clásico y lectura de elementos' ),
            'native_blocks'  => array( 'Menús', 'Navegación de bloques utilizada en la cabecera' ),
            'extensions'   => array( 'Extensiones', 'Registro de módulos' ),
            'vocabulary'   => array( 'Editor', 'Tipos y vocabulario semántico' ),
            'report'       => array( 'Informes', 'Cálculo del resumen de arquitectura' ),
            'write_uat'    => array( 'Operaciones de escritura', 'Guardado, edición y sincronización reales' ),
        );
    }

    private static function outcome( $status, $message ) {
        return array( 'status' => $status, 'message' => (string) $message );
    }
    private static function ok( $message ) { return self::outcome( 'pass', $message ); }
    private static function bad( $message ) { return self::outcome( 'fail', $message ); }
    private static function skip( $message ) { return self::outcome( 'skip', $message ); }

    /** Keeps the rest of the suite running if a check throws. */
    public static function run_suite( $cases = null ) {
        $cases = null === $cases ? self::cases() : $cases;
        $start = microtime( true );
        $rows = array();
        $totals = array( 'pass' => 0, 'fail' => 0, 'skip' => 0 );
        foreach ( $cases as $id => $labels ) {
            $began = microtime( true );
            $warnings = array();
            // The temporary error handler captures only PHP errors raised inside
            // this particular manual check, never all site errors or unrelated logs.
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- Isolated manual Autocheck; handler is restored in finally, no production-wide configuration change.
            set_error_handler( static function( $severity, $message, $file, $line ) use ( &$warnings ) {
                if ( count( $warnings ) < 10 ) {
                    $warnings[] = array(
                        'severity' => (int) $severity,
                        'message' => sanitize_text_field( str_replace( ABSPATH, '', (string) $message ) ),
                        'file' => basename( (string) $file ),
                        'line' => (int) $line,
                    );
                }
                return true;
            } );
            try {
                $method = array( __CLASS__, 'check_' . $id );
                $result = is_callable( $method ) ? call_user_func( $method ) : self::bad( 'Test sin implementación: ' . $id );
                if ( ! is_array( $result ) || ! isset( $result['status'], $result['message'] ) ||
                     ! in_array( $result['status'], array( 'pass', 'fail', 'skip' ), true ) ) {
                    $result = self::bad( 'Resultado inválido del test.' );
                }
            } catch ( Throwable $error ) {
                $result = self::bad( get_class( $error ) . ': ' . $error->getMessage() );
            } finally {
                restore_error_handler();
            }
            if ( $warnings ) {
                $result['status'] = 'fail';
                $result['message'] .= ' Se produjeron ' . count( $warnings ) . ' avisos/errores PHP durante la prueba.';
                $result['php_errors'] = $warnings;
            }
            $result['id'] = $id;
            $result['group'] = $labels[0];
            $result['label'] = $labels[1];
            $result['ms'] = (int) round( ( microtime( true ) - $began ) * 1000 );
            $rows[] = $result;
            $totals[ $result['status'] ]++;
        }
        return array(
            'version' => defined( 'TAXOCOMMERCE_CORE_VERSION' ) ? TAXOCOMMERCE_CORE_VERSION : '',
            'date' => current_time( 'mysql' ), 'executed_at' => gmdate( 'c' ),
            'ms' => (int) round( ( microtime( true ) - $start ) * 1000 ),
            'totals' => $totals,
            'rows' => $rows,
        );
    }

    private static function table( $suffix ) {
        global $wpdb;
        return $wpdb->prefix . $suffix;
    }

    private static function exists( $table ) {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Auto Test requires uncached diagnostic reads.
        return $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
    }

    private static function missing( $table, $columns ) {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Auto Test must inspect live table columns.
        $available = $wpdb->get_col( $wpdb->prepare( 'SHOW COLUMNS FROM %i', $table ) );
        return is_array( $available ) ? array_diff( $columns, $available ) : $columns;
    }

    private static function check_runtime() {
        if ( ! defined( 'TAXOCOMMERCE_CORE_VERSION' ) || ! function_exists( 'taxocommerce_core' ) ) {
            return self::bad( 'Core no cargó su punto de entrada.' );
        }
        $core = taxocommerce_core();
        if ( ! is_object( $core ) || ! is_callable( array( $core, 'content' ) ) ||
            ! is_callable( array( $core, 'hierarchy' ) ) || ! is_callable( array( $core, 'menu' ) ) ) {
            return self::bad( 'Faltan los servicios de contenido, jerarquía o menús.' );
        }
        return self::ok( 'Core ' . TAXOCOMMERCE_CORE_VERSION . '; servicios accesibles.' );
    }

    private static function check_plugin_files() {
        if ( ! defined( 'TAXOCOMMERCE_CORE_PATH' ) || ! defined( 'TAXOCOMMERCE_CORE_FILE' ) ) {
            return self::bad( 'No están definidas las rutas del plugin Core.' );
        }
        $required = array(
            TAXOCOMMERCE_CORE_FILE,
            TAXOCOMMERCE_CORE_PATH . 'includes/class-taxocommerce-core.php',
            TAXOCOMMERCE_CORE_PATH . 'includes/class-taxocommerce-core-auto-test.php',
            TAXOCOMMERCE_CORE_PATH . 'includes/class-taxocommerce-core-content-manager.php',
            TAXOCOMMERCE_CORE_PATH . 'includes/class-taxocommerce-core-hierarchy.php',
            TAXOCOMMERCE_CORE_PATH . 'includes/class-taxocommerce-core-menu.php',
        );
        foreach ( $required as $file ) {
            if ( ! is_file( $file ) || ! is_readable( $file ) ) {
                return self::bad( 'No existe o no se puede leer el archivo de Core: ' . basename( $file ) );
            }
        }
        if ( function_exists( 'get_file_data' ) ) {
            $data = get_file_data( TAXOCOMMERCE_CORE_FILE, array( 'Version' => 'Version' ), 'plugin' );
            if ( empty( $data['Version'] ) || $data['Version'] !== TAXOCOMMERCE_CORE_VERSION ) {
                return self::bad( 'La cabecera de versión no coincide con TAXOCOMMERCE_CORE_VERSION.' );
            }
        }
        return self::ok( count( $required ) . ' archivos esenciales legibles y versión coherente.' );
    }

    private static function check_database() {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Requested manual diagnostic.
        $answer = $wpdb->get_var( 'SELECT 1' );
        return '1' === (string) $answer
            ? self::ok( 'WordPress puede consultar su base de datos.' )
            : self::bad( 'No se ha podido ejecutar una consulta de lectura a MySQL.' );
    }

    /**
     * A removed submenu registration may cause "Sorry, you are not allowed to
     * access this page", even when a callable callback still exists.
     */
    private static function check_admin_routes() {
        global $submenu;
        if ( ! function_exists( 'get_plugin_page_hook' ) || ! function_exists( 'get_current_screen' ) ) {
            return self::skip( 'La ejecución no dispone de un contexto de administración completo.' );
        }
        $screen = get_current_screen();
        if ( ! $screen || 'toplevel_page_taxocommerce-core' !== $screen->id ) {
            return self::skip( 'Ejecuta el AutoCheck desde la pantalla principal de Core para comprobar las rutas.' );
        }
        $permission = apply_filters( 'taxocommerce_core_manage_capability', 'manage_options' );
        if ( ! current_user_can( $permission ) ) {
            return self::bad( 'El usuario actual no dispone del permiso de administración requerido.' );
        }
        $pages = array(
            'taxocommerce-core-content',
            'taxocommerce-core-hierarchy',
            'taxocommerce-core-menu',
            TaxoCommerce_Core_User_Guide::PAGE_SLUG,
        );
        $registered = isset( $submenu['taxocommerce-core'] ) ? (array) $submenu['taxocommerce-core'] : array();
        foreach ( $pages as $slug ) {
            $found = false;
            foreach ( $registered as $item ) {
                if ( isset( $item[2] ) && $slug === (string) $item[2] ) {
                    $found = true;
                    if ( ! current_user_can( $item[1] ) ) {
                        return self::bad( 'El usuario no puede acceder a la pantalla ' . $slug . ' (' . $item[1] . ').' );
                    }
                    break;
                }
            }
            if ( ! $found ) {
                return self::bad( 'Ruta administrativa ausente del registro WordPress: ' . $slug . '. Puede causar acceso denegado.' );
            }
            $hook = get_plugin_page_hook( $slug, 'taxocommerce-core' );
            if ( ! $hook || false === has_action( $hook ) ) {
                return self::bad( 'Pantalla administrativa sin controlador ejecutable: ' . $slug . '.' );
            }
        }
        return self::ok( count( $pages ) . ' pantallas de Core registradas con permisos y controladores.' );
    }

    /** Read a real WooCommerce category using WordPress taxonomy and termmeta. */
    private static function check_category_fields() {
        if ( ! taxonomy_exists( 'product_cat' ) ) {
            return self::skip( 'WooCommerce no tiene activa la taxonomía product_cat.' );
        }
        $terms = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false, 'number' => 1 ) );
        if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
            return self::bad( 'WordPress no puede recuperar una categoría de productos.' );
        }
        if ( ! $terms ) {
            return self::skip( 'Esta tienda todavía no tiene categorías para comprobar.' );
        }
        $term = get_term( $terms[0]->term_id, 'product_cat' );
        if ( ! $term || is_wp_error( $term ) || ! isset( $term->description ) ) {
            return self::bad( 'No se puede leer la descripción nativa de la categoría.' );
        }
        $excerpt = get_term_meta( $term->term_id, 'seo_excerpt', true );
        if ( ! is_string( $excerpt ) ) {
            return self::bad( 'No se puede leer el extracto nativo (termmeta.seo_excerpt).' );
        }
        return self::ok( 'Categoría #' . absint( $term->term_id ) . ': descripción y extracto accesibles con las APIs de WordPress.' );
    }

    private static function check_native_roles() {
        if ( ! is_callable( array( 'TaxoCommerce_Core_Hierarchy_Classic', 'role_pages' ) ) ) {
            return self::bad( 'No está disponible el lector de roles de páginas.' );
        }
        $hierarchy = taxocommerce_core()->hierarchy();
        foreach ( array( 'cluster', 'hub_primary', 'hub_secondary', 'landing' ) as $role ) {
            $pages = TaxoCommerce_Core_Hierarchy_Classic::role_pages( $role );
            if ( ! is_array( $pages ) ) {
                return self::bad( 'Error al consultar las páginas del rol ' . $role . '.' );
            }
            foreach ( array_slice( $pages, 0, 1 ) as $page ) {
                if ( ! isset( $page->ID ) || $hierarchy->get_page_role( $page->ID ) !== $role ) {
                    return self::bad( 'Discrepancia del rol nativo ' . $role . ' en página #' . absint( $page->ID ?? 0 ) . '.' );
                }
                return self::ok( 'Rol ' . $role . ' recuperado correctamente para la página #' . absint( $page->ID ) . '.' );
            }
        }
        return self::skip( 'No hay páginas clasificadas para comprobar la lectura de roles.' );
    }

    private static function check_actions() {
        $names = array(
            'taxocommerce_core_save_content', 'taxocommerce_core_save_page_role',
            'taxocommerce_core_replace_hierarchy_relations', 'taxocommerce_core_sync_menu',
            'taxocommerce_core_activate_menu', 'taxocommerce_core_upload_extension', self::ACTION,
        );
        foreach ( $names as $name ) {
            if ( false === has_action( 'admin_post_' . $name ) ) {
                return self::bad( 'Acción no registrada: admin_post_' . $name );
            }
        }
        return self::ok( count( $names ) . ' acciones conectadas. No se ejecutan sus escrituras.' );
    }

    private static function check_content() {
        if ( ! post_type_exists( 'page' ) || ! post_type_exists( 'post' ) ) {
            return self::bad( 'WordPress no registra page o post.' );
        }
        $pages = wp_count_posts( 'page' );
        $posts = wp_count_posts( 'post' );
        if ( ! is_object( $pages ) || ! is_object( $posts ) ) {
            return self::bad( 'No se pudo leer wp_count_posts().' );
        }
        return self::ok( 'Páginas publicadas: ' . absint( $pages->publish ?? 0 ) .
            '; entradas publicadas: ' . absint( $posts->publish ?? 0 ) . '.' );
    }

    private static function check_catalog() {
        if ( ! post_type_exists( 'product' ) || ! taxonomy_exists( 'product_cat' ) ) {
            return self::skip( 'WooCommerce no está disponible en esta instalación.' );
        }
        $products = wp_count_posts( 'product' );
        $cats = wp_count_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false ) );
        if ( ! is_object( $products ) || is_wp_error( $cats ) ) {
            return self::bad( 'Fallo en el recuento de productos o categorías.' );
        }
        return self::ok( 'Productos publicados: ' . absint( $products->publish ?? 0 ) .
            '; categorías: ' . absint( $cats ) . '.' );
    }

    private static function check_inventory() {
        $data = TaxoCommerce_Core_Content_Inventory::snapshot();
        if ( ! is_array( $data ) || ! isset( $data['types']['pages'], $data['types']['posts'], $data['generated_at'] ) ) {
            return self::bad( 'El inventario no devuelve páginas, entradas y fecha.' );
        }
        return self::ok( 'Inventario generado correctamente; timestamp ' . absint( $data['generated_at'] ) . '.' );
    }

    private static function check_countries() {
        foreach ( array( 'es', 'pt', 'jp' ) as $country ) {
            $code = strtoupper( $country );
            if ( TaxoCommerce_Core_Country::normalize( $country ) !== $code ) {
                return self::bad( 'Normalización incorrecta: ' . $code );
            }
        }
        return '' === TaxoCommerce_Core_Country::normalize( 'NOT-A-COUNTRY' )
            ? self::ok( 'Códigos ISO de dos letras normalizados; entradas inválidas rechazadas.' )
            : self::bad( 'Se aceptan códigos de país de longitud inválida.' );
    }

    private static function check_post_country() {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Auto Test requires uncached diagnostic reads.
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT ID, post_name FROM {$wpdb->posts} WHERE post_type=%s AND post_status NOT IN ('trash','auto-draft')
             AND post_name LIKE %s ORDER BY ID DESC LIMIT 100",
            'page', $wpdb->esc_like( 'vevor-' ) . '%'
        ), ARRAY_A );
        if ( ! is_array( $rows ) ) {
            return self::bad( 'Error leyendo páginas: ' . $wpdb->last_error );
        }
        $checked = 0;
        foreach ( $rows as $row ) {
            if ( ! preg_match( '/^vevor-(us|fr|de|nl)(?:-|$)/', $row['post_name'], $m ) ) {
                continue;
            }
            $country = strtoupper( $m[1] );
            if ( $country !== TaxoCommerce_Core_Country::post( $row['ID'] ) ) {
                return self::bad( 'Página #' . absint( $row['ID'] ) . ': el país no coincide con el slug (' . $country . ').' );
            }
            $checked++;
        }
        return $checked ? self::ok( $checked . ' páginas validadas (muestra hasta 100).' ) :
            self::skip( 'No existen páginas internacionales en la muestra.' );
    }

    private static function check_term_country() {
        global $wpdb;
        if ( ! taxonomy_exists( 'product_cat' ) ) {
            return self::skip( 'No existe la taxonomía product_cat.' );
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Auto Test requires uncached diagnostic reads.
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT t.term_id, t.slug FROM {$wpdb->terms} t
             JOIN {$wpdb->term_taxonomy} tt ON tt.term_id=t.term_id AND tt.taxonomy=%s
             WHERE t.slug LIKE %s ORDER BY t.term_id DESC LIMIT 100",
            'product_cat', $wpdb->esc_like( 'vevor-' ) . '%'
        ), ARRAY_A );
        if ( ! is_array( $rows ) ) {
            return self::bad( 'Error leyendo categorías: ' . $wpdb->last_error );
        }
        $checked = 0;
        foreach ( $rows as $row ) {
            if ( ! preg_match( '/^vevor-(us|fr|de|nl)(?:-|$)/', $row['slug'], $m ) ) {
                continue;
            }
            $country = strtoupper( $m[1] );
            if ( $country !== TaxoCommerce_Core_Country::term( $row['term_id'] ) ) {
                return self::bad( 'Categoría #' . absint( $row['term_id'] ) . ': país incoherente con ' . $country . '.' );
            }
            $checked++;
        }
        return $checked ? self::ok( $checked . ' categorías verificadas (muestra hasta 100).' ) :
            self::skip( 'No existen categorías internacionales en la muestra.' );
    }

    private static function check_nodes() {
        global $wpdb;
        $table = self::table( 'seo_nodes' );
        if ( ! self::exists( $table ) ) {
            return self::skip( 'La tabla de nodos SEO no existe en esta instalación.' );
        }
        $missing = self::missing( $table, array( 'id', 'object_type', 'object_id', 'seo_role', 'status' ) );
        if ( $missing ) {
            return self::bad( 'Columnas ausentes en seo_nodes: ' . implode( ', ', $missing ) );
        }
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Verified Core table, fixed predicate.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Auto Test requires uncached diagnostic reads.
        $count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE object_type='page' AND status=1", $table ) );
        return null === $count ? self::bad( 'Error leyendo nodos: ' . $wpdb->last_error ) :
            self::ok( absint( $count ) . ' nodos activos; columnas obligatorias disponibles.' );
    }

    private static function check_role_read() {
        global $wpdb;
        $table = self::table( 'seo_nodes' );
        if ( ! self::exists( $table ) ) {
            return self::skip( 'La tabla de nodos SEO todavía no está instalada.' );
        }
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Verified Core table, fixed predicate.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Auto Test requires uncached diagnostic reads.
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT object_id,seo_role FROM %i WHERE object_type='page' AND status=1 ORDER BY id DESC LIMIT 1", $table ), ARRAY_A );
        if ( ! is_array( $row ) ) {
            return $wpdb->last_error ? self::bad( 'Error leyendo roles: ' . $wpdb->last_error ) :
                self::skip( 'No hay nodos activos para comprobar lectura.' );
        }
        $actual = taxocommerce_core()->hierarchy()->get_page_role( $row['object_id'] );
        return $actual === $row['seo_role'] ?
            self::ok( 'Página #' . absint( $row['object_id'] ) . ': rol "' . $actual . '" recuperado.' ) :
            self::bad( 'Página #' . absint( $row['object_id'] ) . ': rol guardado "' . $row['seo_role'] . '", recuperado "' . $actual . '".' );
    }

    private static function check_relations() {
        global $wpdb;
        $table = self::table( 'seo_relations' );
        if ( ! self::exists( $table ) ) {
            return self::skip( 'La tabla de relaciones SEO no existe en esta instalación.' );
        }
        $missing = self::missing( $table, array( 'id', 'source_type', 'source_id', 'target_type', 'target_id', 'relation_type' ) );
        if ( $missing ) {
            return self::bad( 'Columnas ausentes en seo_relations: ' . implode( ', ', $missing ) );
        }
        if ( ! is_callable( array( 'TaxoCommerce_Core_Hierarchy_Classic', 'rows' ) ) ) {
            return self::bad( 'No está disponible el lector de jerarquía.' );
        }
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Verified Core table identifier.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Auto Test requires uncached diagnostic reads.
        $count = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $table ) );
        return null === $count ? self::bad( 'Error leyendo relaciones: ' . $wpdb->last_error ) :
            self::ok( absint( $count ) . ' relaciones en tabla; lector cargado.' );
    }

    /**
     * Known non-hierarchy relations use the same SEO relations table. Their
     * endpoints must be verified by object type, not by the hierarchy roles.
     */
    private static function relation_type_schema( $type ) {
        $definitions = TaxoCommerce_Core_Hierarchy_Classic::definitions();
        if ( isset( $definitions[ $type ] ) ) {
            $definition = $definitions[ $type ];
            return array(
                'source_type' => $definition['source_type'],
                'target_type' => $definition['target_type'],
                'source_post_type' => 'page',
                'target_post_type' => in_array( $definition['target_type'], array( 'product_cat', 'post' ), true ) ? $definition['target_type'] : 'page',
                'check_country' => true,
            );
        }
        // Shared table: editorial links do not form part of the Core hierarchy.
        $editorial = array(
            'post_to_category' => array( 'post', 'product_cat' ),
            'landing_to_category' => array( 'landing', 'product_cat' ),
            // Translations intentionally connect different countries.
            'category_translation' => array( 'product_cat', 'product_cat' ),
        );
        if ( ! isset( $editorial[ $type ] ) ) {
            return null;
        }
        $from = $editorial[ $type ][0];
        $to = $editorial[ $type ][1];
        return array(
            'source_type' => $from,
            'target_type' => $to,
            'source_post_type' => 'landing' === $from ? 'page' : $from,
            'target_post_type' => $to,
            'check_country' => 'category_translation' !== $type,
        );
    }

    /** Read-only existence check with native post/term APIs. */
    private static function relation_endpoint_exists( $type, $id, $post_type ) {
        $id = absint( $id );
        if ( ! $id ) {
            return false;
        }
        if ( 'product_cat' === $type ) {
            $term = get_term( $id, 'product_cat' );
            return $term && ! is_wp_error( $term ) && 'product_cat' === $term->taxonomy;
        }
        $post = get_post( $id );
        return $post && ! is_wp_error( $post ) && $post_type === $post->post_type;
    }

    private static function check_edges() {
        global $wpdb;
        $table = self::table( 'seo_relations' );
        if ( ! self::exists( $table ) ) {
            return self::skip( 'La tabla de relaciones SEO todavía no está instalada.' );
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Bounded diagnostic sample.
        $rows = $wpdb->get_results( $wpdb->prepare(
            'SELECT id,source_type,source_id,target_type,target_id,relation_type FROM %i ORDER BY id DESC LIMIT 60',
            $table
        ), ARRAY_A );
        if ( ! is_array( $rows ) ) {
            return self::bad( 'Error leyendo relaciones: ' . $wpdb->last_error );
        }
        if ( ! $rows ) {
            return self::skip( 'No hay relaciones para comprobar.' );
        }
        $checked = 0;
        $unknown = array();
        foreach ( $rows as $row ) {
            $id = absint( $row['id'] );
            $type = (string) $row['relation_type'];
            $schema = self::relation_type_schema( $type );
            if ( null === $schema ) {
                $unknown[ $type ] = true;
                continue;
            }
            if ( $row['source_type'] !== $schema['source_type'] ||
                 $row['target_type'] !== $schema['target_type'] ) {
                return self::bad( 'Relación #' . $id . ': tipos de extremos incorrectos para "' . $type . '".' );
            }
            if ( ! self::relation_endpoint_exists( $row['source_type'], $row['source_id'], $schema['source_post_type'] ) ) {
                return self::bad( 'Relación #' . $id . ': origen inexistente o de tipo incorrecto (' . $type . ').' );
            }
            if ( ! self::relation_endpoint_exists( $row['target_type'], $row['target_id'], $schema['target_post_type'] ) ) {
                return self::bad( 'Relación #' . $id . ': destino inexistente o de tipo incorrecto (' . $type . ').' );
            }
            if ( $schema['check_country'] && '' === TaxoCommerce_Core_Country::relation( $row ) ) {
                return self::bad( 'Relación #' . $id . ': países de origen/destino incompatibles.' );
            }
            ++$checked;
        }
        if ( $unknown ) {
            return self::skip( $checked . ' relaciones conocidas correctas; ' .
                count( $rows ) - $checked . ' no comprobadas de tipo(s): ' .
                implode( ', ', array_keys( $unknown ) ) . '. El resultado no es una auditoría integral.' );
        }
        return self::ok( $checked . ' relaciones conocidas comprobadas; muestra limitada, no auditoría integral.' );
    }

    /** Read only the markets on this WordPress installation, never a fixed store preset. */
    private static function check_menus() {
        $countries = TaxoCommerce_Core_Menu::available_countries();
        if ( ! is_array( $countries ) ) {
            return self::bad( 'No se puede consultar la lista de países de los menús.' );
        }
        $stored_ids = array();
        $checked = 0;
        foreach ( array_keys( $countries ) as $country ) {
            $code = TaxoCommerce_Core_Menu::normalize_code( $country );
            $name = $code ? $code : 'Global';
            $id = absint( get_option( TaxoCommerce_Core_Menu::menu_option( $code ), 0 ) );
            if ( $id && isset( $stored_ids[ $id ] ) ) {
                return self::bad( 'Menús ' . $stored_ids[ $id ] . ' y ' . $name . ' comparten ID ' . $id . '.' );
            }
            if ( $id ) {
                $stored_ids[ $id ] = $name;
            }
            $resolved = TaxoCommerce_Core_Menu::managed_menu_id( $code, false );
            if ( is_wp_error( $resolved ) ) {
                return self::bad( 'Menú ' . $name . ': ' . $resolved->get_error_message() );
            }
            if ( ! $resolved ) {
                if ( $id ) {
                    return self::bad( 'Menú ' . $name . ': ID guardado ' . $id . ' sin un menú nativo válido.' );
                }
                continue;
            }
            if ( $id && (int) $resolved !== $id ) {
                return self::bad( 'Menú ' . $name . ': ID guardado ' . $id . ' distinto del menú nativo localizado #' . absint( $resolved ) . '.' );
            }
            $menu = wp_get_nav_menu_object( $resolved );
            if ( ! $menu || is_wp_error( $menu ) ||
                 ( (string) $menu->name !== TaxoCommerce_Core_Menu::menu_name( $code ) &&
                   ! ( 'ES' === $code && 'TaxoCommerce' === (string) $menu->name ) ) ) {
                return self::bad( 'Menú ' . $name . ': identidad del menú nativo incoherente.' );
            }
            $items = wp_get_nav_menu_items( $resolved );
            // An existing but empty menu is not a broken menu.
            if ( false !== $items && ( is_wp_error( $items ) || ! is_array( $items ) ) ) {
                return self::bad( 'Menú ' . $name . ': sus elementos no se pueden recuperar.' );
            }
            ++$checked;
        }
        return $checked ? self::ok( $checked . ' menús SEO configurados y leídos correctamente.' ) :
            self::skip( 'No hay menús SEO gestionados por Core; los menús nativos se comprueban por separado.' );
    }

    /** Check that the real native/block editor endpoints are registered, without calling them. */
    private static function check_menu_actions() {
        $native = array(
            TaxoCommerce_Core_Menu_Editor::ACTION,
            TaxoCommerce_Core_Block_Navigation::ACTION,
            TaxoCommerce_Core_Block_Navigation::CREATE,
            TaxoCommerce_Core_Block_Navigation::ACTIVATE,
            TaxoCommerce_Core_Block_Navigation::ADD_LINK,
            TaxoCommerce_Core_Block_Navigation::ADD_OBJECTS,
            TaxoCommerce_Core_Block_Navigation::REMOVE,
            TaxoCommerce_Core_Block_Navigation::DELETE,
        );
        foreach ( $native as $action ) {
            if ( false === has_action( 'admin_post_' . $action ) ) {
                return self::bad( 'Falta el controlador de menús: ' . $action );
            }
        }
        if ( false === has_action( 'wp_ajax_' . TaxoCommerce_Core_Menu_Editor::SEARCH ) ) {
            return self::bad( 'No está disponible el buscador de elementos del gestor de menús.' );
        }
        foreach ( array(
            array( 'TaxoCommerce_Core_Menu_Editor', 'render_wordpress_screen' ),
            array( 'TaxoCommerce_Core_Menu_Editor', 'wordpress_menu_id' ),
            array( 'TaxoCommerce_Core_Block_Navigation', 'header_references' ),
        ) as $method ) {
            if ( ! is_callable( $method ) ) {
                return self::bad( 'No se puede usar ' . implode( '::', $method ) . '().' );
            }
        }
        return self::ok( count( $native ) . ' controladores de escritura y buscador disponibles; no se ejecutan.' );
    }

    /** Load active classic menus via the theme's own locations, without assuming location slugs. */
    private static function check_native_classic() {
        if ( function_exists( 'wp_is_block_theme' ) && wp_is_block_theme() ) {
            return self::skip( 'El tema utiliza navegación por bloques; se comprueba por separado.' );
        }
        $locations = get_registered_nav_menus();
        if ( ! is_array( $locations ) || ! $locations ) {
            return self::skip( 'El tema no registra ubicaciones de menú clásico.' );
        }
        $assigned = (array) get_theme_mod( 'nav_menu_locations', array() );
        $ids = array();
        $count = 0;
        foreach ( $locations as $location => $label ) {
            $id = absint( $assigned[ $location ] ?? 0 );
            if ( ! $id ) {
                continue;
            }
            $menu = wp_get_nav_menu_object( $id );
            if ( ! $menu || is_wp_error( $menu ) ) {
                return self::bad( 'Ubicación ' . $location . ': el menú activo #' . $id . ' no existe.' );
            }
            $items = wp_get_nav_menu_items( $id, array( 'post_status' => 'publish,draft' ) );
            // WordPress may return false for a valid, empty menu.
            if ( false === $items ) {
                $items = array();
            }
            if ( is_wp_error( $items ) || ! is_array( $items ) ) {
                return self::bad( 'Ubicación ' . $location . ': no se pueden cargar los elementos del menú #' . $id . '.' );
            }
            $ids[ $id ] = true;
            ++$count;
        }
        if ( ! $ids ) {
            return self::skip( 'El tema no tiene un menú asignado a ninguna ubicación clásica.' );
        }
        $selected = TaxoCommerce_Core_Menu_Editor::wordpress_menu_id();
        // Single-menu sites load automatically. Multiple distinct menus require selection.
        $expected = 1 === count( $ids ) ? (int) key( $ids ) : 0;
        if ( $selected !== $expected ) {
            return self::bad( 'El gestor carga #' . $selected . ', pero el tema requiere #' . $expected . '.' );
        }
        return self::ok( $count . ' ubicaciones leídas; ' . count( $ids ) . ' menú(s) válido(s). ' .
            ( $expected ? 'El gestor carga automáticamente el menú #' . $expected . '.' :
              'El gestor permite seleccionar entre varios menús.' ) );
    }

    /** Validate actual wp_navigation posts used in block-theme headers. */
    private static function check_native_blocks() {
        if ( ! function_exists( 'wp_is_block_theme' ) || ! wp_is_block_theme() ) {
            return self::skip( 'El tema es clásico; la navegación de bloques no corresponde.' );
        }
        if ( ! post_type_exists( 'wp_navigation' ) || ! function_exists( 'parse_blocks' ) ) {
            return self::bad( 'WordPress no dispone de wp_navigation o parse_blocks().' );
        }
        $found = TaxoCommerce_Core_Block_Navigation::header_references();
        if ( ! is_array( $found ) || ! isset( $found['menus'], $found['inline'] ) || ! is_array( $found['menus'] ) ) {
            return self::bad( 'No se puede interpretar la navegación de cabecera del tema.' );
        }
        if ( ! $found['menus'] ) {
            return ! empty( $found['inline'] )
                ? self::ok( 'La cabecera utiliza navegación integrada; no hay un wp_navigation independiente que cargar.' )
                : self::skip( 'No se encontró referencia publicada en la cabecera; puede existir una alternativa del tema.' );
        }
        foreach ( $found['menus'] as $id => $source ) {
            $nav = get_post( absint( $id ) );
            if ( ! $nav || 'wp_navigation' !== $nav->post_type || 'publish' !== $nav->post_status ) {
                return self::bad( 'La navegación #' . absint( $id ) . ' de la cabecera no está publicada.' );
            }
            $blocks = parse_blocks( (string) $nav->post_content );
            if ( ! is_array( $blocks ) ) {
                return self::bad( 'No se pueden leer los bloques de navegación #' . absint( $id ) . '.' );
            }
        }
        return self::ok( count( $found['menus'] ) . ' navegación(es) de cabecera localizada(s) y legible(s).' );
    }

    private static function check_extensions() {
        $modules = taxocommerce_core()->get_registered_modules();
        return is_array( $modules ) && isset( $modules['core'] )
            ? self::ok( count( $modules ) . ' módulos registrados, incluido Core.' )
            : self::bad( 'Core no figura en el registro de extensiones.' );
    }

    private static function check_vocabulary() {
        if ( 'page' !== TaxoCommerce_Core_Vocabulary::object_type( 'pages' ) ||
            'product_cat' !== TaxoCommerce_Core_Vocabulary::object_type( 'categories' ) ) {
            return self::bad( 'El editor no asigna correctamente el tipo de objeto.' );
        }
        if ( ! TaxoCommerce_Core_Vocabulary::available() ) {
            return self::skip( 'Editor cargado; tablas opcionales de vocabulario no instaladas.' );
        }
        $groups = TaxoCommerce_Core_Vocabulary::assignments( 'pages', 0 );
        return isset( $groups['rol'], $groups['tipo'] )
            ? self::ok( 'Tipos del editor y grupos de vocabulario disponibles.' )
            : self::bad( 'No se recuperan los grupos del vocabulario.' );
    }

    private static function check_report() {
        $result = TaxoCommerce_Core_Architecture_Report::summarize(
            array( array( 'id' => 10, 'role' => 'cluster' ) ), array(), array(), array(), 0
        );
        return isset( $result['clusters_count'], $result['seo_pages_count'] ) &&
            1 === (int) $result['clusters_count'] && 1 === (int) $result['seo_pages_count']
            ? self::ok( 'Resumen de arquitectura validado con un caso de prueba controlado.' )
            : self::bad( 'El resumen de arquitectura devuelve contadores incorrectos.' );
    }

    private static function check_write_uat() {
        return self::skip( 'Las escrituras sobre páginas, relaciones y menús se prueban en staging/CI, no en producción.' );
    }

    public static function render() {
        if ( ! self::allowed() ) {
            wp_die( esc_html__( 'Acceso denegado a Autocheck.', 'taxocommerce-core' ) );
        }
        $report = get_transient( self::REPORT_PREFIX . get_current_user_id() );
        $totals = is_array( $report ) && isset( $report['totals'], $report['rows'] ) ? $report['totals'] : null;
        ?>
        <div class="wrap tcc-dashboard tcc-family-screen" id="tc-core-autotest">
            <?php
            // Core's own hero, version/history and navigation remain identical
            // on Overview, Functionality Check and Code Check.
            TaxoCommerce_Core_Dashboard::render_header_nav( 'autocheck' );
            ?>
            <?php
            // The functional and source-code checks share Core's screen and navigation.
            // Switching to Code Check must not run the functional suite.
            if ( ! \TaxoCommerce_Core_Autotest_Code::render_subtabs() ) :
            ?>
            <form class="tc-at-actions" action="<?php echo esc_url( admin_url( 'admin.php?page=taxocommerce-core&tab=autocheck' ) ); ?>" method="post">
                <input type="hidden" name="tc_autotest_manual_run" value="1">
                <?php wp_nonce_field( self::NONCE, 'tc_autotest_nonce' ); ?>
                <?php submit_button( __( 'Ejecutar autocheck', 'taxocommerce-core' ), 'primary', 'submit', false ); ?>
                <span><?php esc_html_e( 'Sin cambios en el catálogo, la jerarquía ni los menús.', 'taxocommerce-core' ); ?></span>
            </form>
            <?php if ( null !== $totals ) : ?>
                <form class="tc-at-actions" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
                    <input type="hidden" name="action" value="<?php echo esc_attr( self::EXPORT_ACTION ); ?>">
                    <?php wp_nonce_field( self::EXPORT_NONCE, 'tc_autotest_export_nonce' ); ?>
                    <?php submit_button( __( 'Descargar resultados (JSON)', 'taxocommerce-core' ), 'secondary', 'submit', false ); ?>
                </form>
            <?php endif; ?>
            <?php if ( null === $totals ) : ?>
                <p><?php esc_html_e( 'No existen resultados todavía. Ejecuta el chequeo para ver el estado de Core.', 'taxocommerce-core' ); ?></p>
            <?php else : ?>
                <p><strong><?php esc_html_e( 'Fecha de ejecución:', 'taxocommerce-core' ); ?> <?php echo esc_html( $report['date'] ); ?></strong> · <?php echo esc_html( $report['version'] ); ?> · <?php echo esc_html( absint( $report['ms'] ) . ' ms' ); ?></p>
                <div class="tc-at-summary">
                    <span><strong style="color:#188038"><?php echo esc_html( absint( $totals['pass'] ) ); ?></strong> <?php esc_html_e( 'Correctas', 'taxocommerce-core' ); ?></span>
                    <span><strong style="color:#c5221f"><?php echo esc_html( absint( $totals['fail'] ) ); ?></strong> <?php esc_html_e( 'Errores', 'taxocommerce-core' ); ?></span>
                    <span><strong><?php echo esc_html( absint( $totals['skip'] ) ); ?></strong> <?php esc_html_e( 'No ejecutadas', 'taxocommerce-core' ); ?></span>
                </div>
                <?php foreach ( $report['rows'] as $row ) : ?>
                    <div class="tc-at-row tc-at-row--<?php echo esc_attr( $row['status'] ); ?>">
                        <span class="tc-at-dot tc-at-dot--<?php echo esc_attr( $row['status'] ); ?>" aria-hidden="true"></span>
                        <div><strong><?php echo esc_html( $row['group'] . ' · ' . $row['label'] ); ?> <span class="tc-at-small"><?php echo esc_html( $row['id'] ); ?></span></strong>
                            <p><?php echo esc_html( $row['message'] ); ?></p>
                            <?php if ( ! empty( $row['php_errors'] ) ) : ?>
                                <ul class="tc-at-small">
                                <?php foreach ( $row['php_errors'] as $php_error ) : ?>
                                    <li><?php echo esc_html( (string) $php_error['message'] . ' (' . $php_error['file'] . ':' . (int) $php_error['line'] . ')' ); ?></li>
                                <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                            <span class="tc-at-small"><?php echo esc_html( absint( $row['ms'] ) . ' ms' ); ?></span></div>
                    </div>
                <?php endforeach; ?>
                <p class="description"><?php esc_html_e( 'Verde significa que pasó la comprobación indicada. Gris significa no aplicable o no ejecutado. Los resultados basados en muestras no sustituyen una auditoría completa ni las UAT de escritura en staging.', 'taxocommerce-core' ); ?></p>
            <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php
    }
}
