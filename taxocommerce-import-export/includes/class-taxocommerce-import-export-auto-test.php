<?php
/**
 * Non-destructive, on-demand diagnostics for TaxoCommerce Import-Export.
 *
 * Tests read in-memory contracts and small snapshots of existing job state.
 * They never call import/export handlers, queue workers, delete controls,
 * cron dispatch, HTTP endpoints, or file-writing methods. Only the test's
 * most recent report is saved per user and site for authenticated JSON export.
 *
 * @package TaxoCommerceImportExport
 */
defined( 'ABSPATH' ) || exit;

final class TaxoCommerce_Import_Export_Auto_Test {
    const ACTION = 'taxocommerce_ie_auto_test_run';
    const DOWNLOAD_ACTION = 'taxocommerce_ie_auto_test_download';
    const NONCE = 'taxocommerce_ie_auto_test';
    const LATEST = 'taxocommerce_ie_at_latest_';

    public static function register() {
        add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'handle_run' ) );
        add_action( 'admin_post_' . self::DOWNLOAD_ACTION, array( __CLASS__, 'handle_download' ) );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
    }

    public static function enqueue() {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only page/section selectors.
        $page = isset( $_GET['page'] ) && is_string( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
        $tab  = isset( $_GET['tc_ie_tab'] ) && is_string( $_GET['tc_ie_tab'] ) ? sanitize_key( wp_unslash( $_GET['tc_ie_tab'] ) ) : '';
        // phpcs:enable WordPress.Security.NonceVerification.Recommended
        if ( 'taxocommerce-import-export' === $page && 'auto-test' === $tab ) {
            wp_enqueue_style(
                'taxocommerce-ie-auto-test',
                plugin_dir_url( TAXOCOMMERCE_IMPORT_EXPORT_FILE ) . 'assets/auto-test.css',
                array(),
                TAXOCOMMERCE_IMPORT_EXPORT_VERSION
            );
        }
    }

    private static function allowed() {
        $capability = (string) apply_filters( 'taxocommerce_import_export_capability', 'manage_woocommerce' );
        return current_user_can( $capability ) || current_user_can( 'manage_options' );
    }

    private static function meta_key() {
        return self::LATEST . get_current_blog_id();
    }

    private static function site_hash() {
        return hash( 'sha256', (string) home_url( '/' ) );
    }

    private static function url() {
        return add_query_arg(
            array( 'page' => 'taxocommerce-import-export', 'tc_ie_tab' => 'auto-test' ),
            admin_url( 'admin.php' )
        );
    }

    public static function groups() {
        return array(
            'startup'  => 'Arranque y administración',
            'csv'      => 'CSV y países',
            'individual' => 'Importación individual',
            'batch'    => 'Cola de importación por lotes',
            'export'   => 'Exportación incremental',
            'media'    => 'Media ZIP',
            'load'     => 'Carga y ejecución',
            'security' => 'Seguridad y diagnóstico',
            'deployment' => 'Seguridad previa al despliegue',
            'uat'      => 'Operaciones reales (validación manual)',
        );
    }

    public static function cases() {
        return array(
            'classes'          => array( 'startup', 'Componentes PHP cargados' ),
            'menu'             => array( 'startup', 'Menú, pestañas y Core opcional' ),
            'assets'           => array( 'startup', 'Recursos JavaScript y CSS disponibles' ),
            'woocommerce'      => array( 'startup', 'WooCommerce y catálogo disponibles' ),
            'db_read'          => array( 'startup', 'Consulta sencilla de MySQL' ),
            'csv_columns'      => array( 'csv', __( 'Cabeceras de seis tipos de exportación', 'taxocommerce-import-export' ) ),
            'csv_countries'    => array( 'csv', 'Campo country_code en todos los tipos' ),
            'csv_aliases'      => array( 'csv', 'Cabeceras heredadas y alias de país' ),
            'csv_identify'     => array( 'csv', 'Detección segura del tipo de CSV' ),
            'csv_partial'      => array( 'csv', 'Importador y contrato de correcciones parciales' ),
            'individual_actions'=>array( 'individual', 'Acción de subida y estado AJAX' ),
            'individual_state' => array( 'individual', 'Último estado registrado (sin datos personales)' ),
            'queue_routes'     => array( 'batch', 'Subida, control, eliminación y progreso AJAX' ),
            'queue_worker'     => array( 'batch', 'Worker independiente y watchdog' ),
            'queue_state'      => array( 'batch', 'Estado persistente de la cola' ),
            'queue_progress'   => array( 'batch', 'Porcentaje y bytes de progreso' ),
            'queue_registry'   => array( 'batch', 'Integración con miniimportadores' ),
            'export_types'     => array( 'export', __( 'Seis tipos de exportación', 'taxocommerce-import-export' ) ),
            'export_actions'   => array( 'export', 'Inicio, bloque, estado, cancelación y descarga' ),
            'export_state'     => array( 'export', 'Estado de exportación por usuario' ),
            'export_headers'   => array( 'export', 'Contrato completo y reducido de productos' ),
            'media_handlers'   => array( 'media', 'Importación y exportación Media registradas' ),
            'media_zip'        => array( 'media', 'Extensión ZipArchive para paquetes Media' ),
            'media_contract'   => array( 'media', 'Funciones de relaciones Media presentes' ),
            'load_priorities'  => array( 'load', 'Cinco velocidades de importación y exportación' ),
            'load_adaptive'    => array( 'load', 'Regulador adaptativo sin escrituras' ),
            'load_state'       => array( 'load', 'Configuración actual del regulador' ),
            'security_actions' => array( 'security', 'Rutas protegidas del Autocheck' ),
            'reports'          => array( 'security', 'Persistencia aislada por usuario y sitio' ),
            'safe_queue_upgrade' => array( 'deployment', 'Cola de importación sin procesos activos' ),
            'safe_export_upgrade' => array( 'deployment', 'Exportación del administrador no activa' ),
            'real_import'      => array( 'uat', 'Importación CSV real y comprobación posterior' ),
            'real_export'      => array( 'uat', 'Exportación real y apertura del archivo' ),
            'real_media'       => array( 'uat', 'ZIP Media real y restauración de relaciones' ),
            'real_queue'       => array( 'uat', 'Reanudación de una cola real sin navegador' ),
        );
    }

    private static function output( $status, $message ) {
        return array( 'status' => $status, 'message' => $message );
    }
    private static function pass( $message ) { return self::output( 'pass', $message ); }
    private static function fail( $message ) { return self::output( 'fail', $message ); }
    private static function skip( $message ) { return self::output( 'skip', $message ); }

    /**
     * Group-scoped tests use the same stable IDs as full-suite comparisons.
     * Exceptions deliberately omit messages that might expose server paths,
     * filenames, imported product data or credentials.
     */
    public static function run_suite( $selected = 'all', $custom_cases = null ) {
        $groups = self::groups();
        $selected = 'all' === $selected || isset( $groups[ $selected ] ) ? $selected : 'all';
        $cases = null === $custom_cases ? self::cases() : $custom_cases;
        $start = microtime( true );
        $totals = array( 'pass' => 0, 'fail' => 0, 'skip' => 0, 'not_run' => 0 );
        $rows = array();
        foreach ( $cases as $id => $labels ) {
            $begin = microtime( true );
            if ( 'all' !== $selected && $selected !== $labels[0] ) {
                $result = self::output( 'not_run', 'Grupo no ejecutado.' );
            } else {
                try {
                    $callback = array( __CLASS__, 'check_' . $id );
                    $result = is_callable( $callback ) ? call_user_func( $callback ) : self::fail( 'Comprobación sin implementar.' );
                    if ( ! is_array( $result ) || ! isset( $result['status'], $result['message'] )
                        || ! in_array( $result['status'], array( 'pass', 'fail', 'skip' ), true ) ) {
                        $result = self::fail( 'Resultado de prueba no válido.' );
                    }
                } catch ( Throwable $e ) {
                    $result = self::fail( 'Excepción en el chequeo (' . get_class( $e ) . '). Consultar el log privado de PHP.' );
                }
            }
            $result['id'] = (string) $id;
            $result['group_id'] = (string) $labels[0];
            $result['group'] = isset( $groups[ $labels[0] ] ) ? $groups[ $labels[0] ] : $labels[0];
            $result['label'] = (string) $labels[1];
            $result['ms'] = (int) round( 1000 * ( microtime( true ) - $begin ) );
            $rows[] = $result;
            ++$totals[ $result['status'] ];
        }
        global $wp_version;
        return array(
            'schema' => 1,
            'phase' => 'check',
            'group' => $selected,
            'site_hash' => self::site_hash(),
            'version' => TAXOCOMMERCE_IMPORT_EXPORT_VERSION,
            'wordpress' => isset( $wp_version ) ? (string) $wp_version : '',
            'php' => PHP_VERSION,
            'date' => current_time( 'mysql' ),
            'executed_at' => gmdate( 'c' ),
            'timestamp' => time(),
            'duration_ms' => (int) round( 1000 * ( microtime( true ) - $start ) ),
            'totals' => $totals,
            'rows' => $rows,
        );
    }

    /**
     * Only the last report is retained for this user and current WordPress site.
     * Historical ANTES/DESPUÉS metadata is neither loaded nor deleted.
     */
    private static function get_saved() {
        $value = get_user_meta( get_current_user_id(), self::meta_key(), true );
        return is_array( $value ) && ! empty( $value['rows'] ) && isset( $value['site_hash'] )
            && hash_equals( self::site_hash(), (string) $value['site_hash'] ) ? $value : array();
    }

    public static function handle_run() {
        if ( ! self::allowed() ) {
            wp_die( esc_html__( 'Sin permisos para ejecutar Autocheck.', 'taxocommerce-import-export' ) );
        }
        check_admin_referer( self::NONCE, 'tc_ie_auto_nonce' );

        // Always execute the complete, non-destructive suite on explicit request.
        // Keep historical action names and the latest-report metadata key.
        update_user_meta( get_current_user_id(), self::meta_key(), self::run_suite( 'all' ) );
        wp_safe_redirect( self::url() );
        exit;
    }

    public static function handle_download() {
        if ( ! self::allowed() ) {
            wp_die( esc_html__( 'Sin permisos para descargar el Autocheck.', 'taxocommerce-import-export' ) );
        }
        check_admin_referer( self::NONCE, 'tc_ie_auto_nonce' );
        $report = self::get_saved();
        if ( ! $report ) {
            wp_die( esc_html__( 'Ejecuta el Autocheck antes de descargar el JSON.', 'taxocommerce-import-export' ) );
        }

        $json = wp_json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE );
        if ( ! is_string( $json ) ) {
            wp_die( esc_html__( 'No se ha podido generar el JSON.', 'taxocommerce-import-export' ) );
        }
        nocache_headers();
        header( 'Content-Type: application/json; charset=utf-8' );
        header( 'X-Content-Type-Options: nosniff' );
        header( 'Content-Disposition: attachment; filename="taxocommerce-import-export-autocheck-' . gmdate( 'Ymd-His' ) . '.json"' );
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Intentional JSON download, not HTML.
        echo $json;
        exit;
    }

    private static function has_hooks( $hooks ) {
        $missing = array();
        foreach ( $hooks as $name ) { if ( ! has_action( $name ) ) { $missing[] = $name; } }
        return $missing;
    }

    private static function csv() { return new TaxoCommerce_Import_Export_CSV(); }

    private static function check_classes() {
        $classes = array(
            'TaxoCommerce_Import_Export', 'TaxoCommerce_Import_Export_CSV',
            'TaxoCommerce_Import_Export_Batch', 'TaxoCommerce_Import_Export_Exporter',
            'TaxoCommerce_Import_Export_Media', 'TaxoCommerce_IE_Load', __CLASS__,
        );
        foreach ( $classes as $name ) {
            if ( ! class_exists( $name ) ) { return self::fail( 'Clase no cargada: ' . $name ); }
        }
        return self::pass( count( $classes ) . ' componentes disponibles. Versión ' . TAXOCOMMERCE_IMPORT_EXPORT_VERSION . '.' );
    }

    private static function check_menu() {
        $menu = taxocommerce_import_export();
        if ( ! is_object( $menu ) || ! method_exists( $menu, 'render_page' )
            || ! has_action( 'admin_menu', array( $menu, 'register_menu' ) ) ) {
            return self::fail( 'Menú administrativo no registrado.' );
        }
        if ( function_exists( 'taxocommerce_core' ) &&
            ! has_action( 'taxocommerce_core_register_modules', array( $menu, 'register_in_core' ) ) ) {
            return self::fail( 'Core está activo, pero Import-Export no registra su extensión.' );
        }
        return self::pass( 'Pestañas y menú de administración disponibles, con Core opcional.' );
    }

    private static function check_assets() {
        $files = array( 'assets/admin.css', 'assets/batch-runner.js', 'assets/exporter.js', 'assets/auto-test.css' );
        foreach ( $files as $file ) {
            if ( ! is_readable( TAXOCOMMERCE_IMPORT_EXPORT_PATH . $file ) ) {
                return self::fail( 'Recurso no disponible: ' . $file );
            }
        }
        return self::pass( 'Los cuatro recursos de interfaz son legibles.' );
    }

    private static function check_woocommerce() {
        return function_exists( 'wc_get_product' ) && post_type_exists( 'product' )
            ? self::pass( 'WooCommerce activo y tipo de contenido product registrado.' )
            : self::fail( 'Falta WooCommerce o su tipo de producto.' );
    }

    private static function check_db_read() {
        global $wpdb;
        if ( ! isset( $wpdb ) || ! is_object( $wpdb ) ) { return self::fail( 'No está disponible wpdb.' ); }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Live SELECT 1 intentionally tests MySQL availability without cached results.
        $value = $wpdb->get_var( 'SELECT 1' );
        return '1' === (string) $value ? self::pass( 'MySQL responde a SELECT 1 (solo lectura).' )
            : self::fail( 'Error en la consulta mínima de MySQL.' );
    }

    private static function check_csv_columns() {
        $csv = self::csv();
        $types = array( 'products', 'products_reduced', 'product_categories', 'wp_categories', 'posts', 'pages' );
        foreach ( $types as $type ) {
            $header = $csv->export_header_for_job( $type );
            if ( ! is_array( $header ) || count( $header ) !== count( array_unique( $header ) )
                || ! in_array( 'record_type', $header, true ) || ! in_array( 'id', $header, true ) ) {
                return self::fail( 'Cabecera inválida: ' . $type );
            }
        }
        return self::pass( 'Seis cabeceras válidas y sin columnas duplicadas.' );
    }

    private static function check_csv_countries() {
        $csv = self::csv();
        foreach ( array( 'products', 'products_reduced', 'product_categories', 'wp_categories', 'posts', 'pages' ) as $type ) {
            if ( ! in_array( 'country_code', $csv->export_header_for_job( $type ), true ) ) {
                return self::fail( 'Falta country_code en ' . $type );
            }
        }
        return self::pass( 'País incluido en productos, ambas taxonomías de categorías, entradas y páginas; no se presupone ES.' );
    }

    private static function check_csv_aliases() {
        $headers = self::csv()->normalize_headers_for_batch( array( 'pais_origen', 'country', 'sku', 'titulo' ) );
        return $headers === array( 'country_code', 'country_code', 'sku', 'name' )
            ? self::pass( 'Alias heredados y países normalizados.' )
            : self::fail( 'No se reconocen alias de país o contenido.' );
    }

    private static function check_csv_identify() {
        $csv = self::csv();
        return 'product' === $csv->infer_record_type_from_headers( array( 'sku', 'name' ) )
            && 'product_category' === $csv->infer_record_type_from_headers( array( 'parent_id', 'name' ) )
            && '' === $csv->infer_record_type_from_headers( array( 'title', 'slug' ) )
            ? self::pass( 'Detección de producto/categoría y rechazo de CSV ambiguos correctos.' )
            : self::fail( 'La inferencia CSV ha cambiado de comportamiento.' );
    }

    private static function check_csv_partial() {
        $csv = self::csv();
        return method_exists( $csv, 'import_row_for_batch' )
            && method_exists( $csv, 'normalize_headers_for_batch' )
            && method_exists( $csv, 'infer_record_type_from_headers' )
            ? self::pass( 'Contrato de importación por fila disponible. No se actualiza ningún producto.' )
            : self::fail( 'Falta una de las funciones del importador CSV.' );
    }

    private static function check_individual_actions() {
        $missing = self::has_hooks( array(
            'admin_post_taxocommerce_import_export_import',
            'wp_ajax_taxocommerce_ie_individual_status',
        ) );
        return $missing ? self::fail( 'Rutas individuales ausentes: ' . implode( ', ', $missing ) )
            : self::pass( 'Subida y estado AJAX de importación individual registrados.' );
    }

    private static function check_individual_state() {
        $value = get_option( TaxoCommerce_Import_Export_Batch::INDIVIDUAL_STATE, array() );
        return is_array( $value ) ? self::pass( 'Estado individual consultable, sin iniciar ningún trabajo.' )
            : self::fail( 'Estado de última importación individual con formato inválido.' );
    }

    private static function check_queue_routes() {
        $missing = self::has_hooks( array(
            'admin_post_taxocommerce_ie_batch_upload', 'admin_post_taxocommerce_ie_batch_control',
            'admin_post_taxocommerce_ie_batch_delete_pending', 'wp_ajax_taxocommerce_ie_batch_step',
            'wp_ajax_taxocommerce_ie_batch_continue',
        ) );
        return $missing ? self::fail( 'Faltan controladores de cola: ' . implode( ', ', $missing ) )
            : self::pass( 'Cola, parada, reanudación, borrado de pendientes y avance AJAX registrados.' );
    }

    private static function check_queue_worker() {
        // Since 0.4.29 there is intentionally no scheduled WORKER_HOOK.
        // Check the live signed loopback endpoints and actual worker methods.
        $missing = self::has_hooks( array(
            'wp_ajax_taxocommerce_ie_batch_continue',
            'wp_ajax_nopriv_taxocommerce_ie_batch_continue',
        ) );
        $batch = new TaxoCommerce_Import_Export_Batch( self::csv() );
        if ( $missing || ! method_exists( $batch, 'worker' ) || ! method_exists( $batch, 'internal_continue' ) || ! method_exists( $batch, 'watchdog' ) ) {
            return self::fail( 'Falta una ruta de continuación o el worker/watchdog de importación.' );
        }
        return self::pass( 'Worker de importación, autollamadas firmadas y watchdog disponibles; no requieren tareas programadas propias.' );
    }

    private static function check_queue_state() {
        $state = get_option( TaxoCommerce_Import_Export_Batch::OPTION_STATE, array() );
        if ( ! is_array( $state ) ) { return self::fail( 'El estado de la cola no es un array.' ); }
        if ( isset( $state['status'] ) && ! is_scalar( $state['status'] ) ) {
            return self::fail( 'El estado actual de la cola tiene tipo incorrecto.' );
        }
        return self::pass( 'Estado de cola legible, sin revelar CSV ni alterar offsets.' );
    }

    private static function check_safe_queue_upgrade() {
        $state = get_option( TaxoCommerce_Import_Export_Batch::OPTION_STATE, array() );
        if ( ! is_array( $state ) ) {
            return self::fail( 'Estado de cola ilegible: revisar antes de desplegar.' );
        }
        if ( ! empty( $state['enabled'] ) || ! empty( $state['current_file'] )
            || in_array( (string) ( $state['status'] ?? '' ), array( 'processing', 'waiting_next' ), true ) ) {
            return self::fail( 'COLA ACTIVA: termina o pausa la importación antes de actualizar.' );
        }
        return self::pass( 'Sin cola activa; no se han alterado archivos ni registros.' );
    }

    private static function check_safe_export_upgrade() {
        $state = get_transient( TaxoCommerce_Import_Export_Exporter::STATE_PREFIX . get_current_user_id() );
        if ( false !== $state && ! is_array( $state ) ) {
            return self::fail( 'Estado de exportación ilegible: revisar antes de desplegar.' );
        }
        if ( is_array( $state ) && in_array( (string) ( $state['status'] ?? '' ),
            array( 'queued', 'running', 'processing', 'pending', 'active', 'cancelling' ), true ) ) {
            return self::fail( 'Exportación de este usuario activa; no desplegar todavía.' );
        }
        return self::pass( 'Sin exportación activa de este usuario; revisar otros administradores por separado.' );
    }

    private static function check_queue_progress() {
        $progress = TaxoCommerce_Import_Export_Batch::progress_values( 25, 100 );
        $edge = TaxoCommerce_Import_Export_Batch::progress_values( 999, 100 );
        return 25.0 === (float) $progress['percent'] && 75 === $progress['bytes_remaining']
            && 100.0 === (float) $edge['percent'] && 0 === $edge['bytes_remaining']
            ? self::pass( 'Progreso acotado de 0 a 100% calculado únicamente con números.' )
            : self::fail( 'El indicador de avance por bytes ha sufrido una regresión.' );
    }

    private static function check_queue_registry() {
        $batch = new TaxoCommerce_Import_Export_Batch( self::csv() );
        return method_exists( $batch, 'worker' ) && method_exists( $batch, 'watchdog' )
            && method_exists( $batch, 'queue_individual' )
            ? self::pass( 'Registro de miniimportadores extensible y cola individual disponibles como contrato.' )
            : self::fail( 'Falta el motor de importadores por lotes.' );
    }

    private static function check_export_types() {
        $types = TaxoCommerce_Import_Export_Exporter::types();
        $required = array( 'products', 'products_reduced', 'product_categories', 'wp_categories', 'posts', 'pages' );
        return is_array( $types ) && ! array_diff( $required, array_keys( $types ) )
            ? self::pass( 'Seis conjuntos exportables registrados, incluidas las categorías nativas de WordPress.' )
            : self::fail( 'Se ha perdido algún tipo exportable.' );
    }

    private static function check_export_actions() {
        $missing = self::has_hooks( array(
            'wp_ajax_taxocommerce_ie_export_start', 'wp_ajax_taxocommerce_ie_export_step',
            'wp_ajax_taxocommerce_ie_export_status', 'wp_ajax_taxocommerce_ie_export_cancel',
            'wp_ajax_taxocommerce_ie_export_resume',
            'wp_ajax_taxocommerce_ie_export_continue', 'wp_ajax_nopriv_taxocommerce_ie_export_continue',
            'admin_post_taxocommerce_ie_export_download',
        ) );
        return $missing ? self::fail( 'Faltan acciones de exportación: ' . implode( ', ', $missing ) )
            : self::pass( 'Rutas de exportación, recuperación AJAX y descarga registradas; no demuestra una descarga HTTP real.' );
    }

    private static function check_export_state() {
        $state = get_transient( TaxoCommerce_Import_Export_Exporter::STATE_PREFIX . get_current_user_id() );
        if ( false === $state ) { return self::skip( 'No existe una exportación guardada de este administrador. No se ha probado la descarga.' ); }
        if ( ! is_array( $state ) || ! isset( $state['id'], $state['user_id'], $state['type'] ) ||
            absint( $state['user_id'] ) !== get_current_user_id() ) {
            return self::fail( 'El trabajo exportador guardado tiene formato inesperado o no pertenece a este administrador.' );
        }
        if ( 'failed' === (string) ( $state['status'] ?? '' ) ) {
            return self::fail( 'Exportación fallida: ' . sanitize_text_field( (string) ( $state['error'] ?? 'Consultar el servidor.' ) ) );
        }
        $file = TaxoCommerce_Import_Export_Exporter::validate_export_file( $state );
        if ( is_wp_error( $file ) ) {
            return self::fail( $file->get_error_message() );
        }
        if ( empty( $state['complete'] ) ) {
            $age = time() - absint( $state['updated'] ?? 0 );
            if ( $age > 60 && in_array( (string) ( $state['status'] ?? '' ), array( 'queued', 'running', 'cancelling' ), true ) ) {
                return self::fail( 'La exportación lleva más de 60 segundos sin avanzar. Revisar el worker, las autollamadas o la asistencia AJAX.' );
            }
            return self::pass( 'Archivo parcial legible, con cabecera válida; la exportación todavía no ha terminado.' );
        }
        return self::pass( 'CSV terminado, legible y con cabecera válida. La descarga HTTP sigue pendiente de validación manual.' );
    }

    private static function check_export_headers() {
        $csv = self::csv();
        $full = $csv->export_header_for_job( 'products' );
        $reduced = $csv->export_header_for_job( 'products_reduced' );
        return in_array( 'regular_price', $full, true ) && in_array( 'supplier', $full, true )
            && in_array( 'country_code', $reduced, true ) && ! in_array( 'regular_price', $reduced, true )
            && in_array( 'vocabulary_json', $reduced, true )
            ? self::pass( 'Inventario reducido no transporta precios; CSV completo incluye datos comerciales.' )
            : self::fail( 'Contratos de CSV completo/reducido inesperados.' );
    }

    private static function check_media_handlers() {
        $missing = self::has_hooks( array(
            'admin_post_taxocommerce_import_export_media_export',
            'admin_post_taxocommerce_import_export_media_import',
        ) );
        return $missing ? self::fail( 'Importación o exportación Media no registradas.' )
            : self::pass( 'Acciones ZIP de Media presentes, sin cargar ni guardar imágenes.' );
    }

    private static function check_media_zip() {
        return class_exists( 'ZipArchive' ) ? self::pass( 'ZipArchive disponible en PHP.' )
            : self::fail( 'ZipArchive no está disponible: el paquete Media no funcionará.' );
    }

    private static function check_media_contract() {
        $media = new TaxoCommerce_Import_Export_Media();
        return method_exists( $media, 'handle_import' ) && method_exists( $media, 'handle_export' )
            ? self::pass( 'Motor Media y restauración de relaciones cargados (sin ejecutar ZIP).' )
            : self::fail( 'Falta un controlador de paquetes Media.' );
    }

    private static function check_load_priorities() {
        $labels = TaxoCommerce_IE_Load::labels();
        $import = TaxoCommerce_IE_Load::size_limits( 'import' );
        $export = TaxoCommerce_IE_Load::size_limits( 'export' );
        return is_array( $labels ) && count( $labels ) === 5
            && $import['initial'] > $export['initial']
            && $import['maximum'] > $export['maximum']
            ? self::pass( 'Cinco prioridades y escalado independiente x10 para importar.' )
            : self::fail( 'El regulador ha perdido sus prioridades o la diferenciación importar/exportar.' );
    }

    private static function check_load_adaptive() {
        $idle = TaxoCommerce_IE_Load::import_adjustment( 20, 200, 0, 0, null, 25 );
        $fast = TaxoCommerce_IE_Load::import_adjustment( 20, 200, 20, 1, 30, 25 );
        $memory = TaxoCommerce_IE_Load::import_adjustment( 20, 200, 20, 5, 90, 25 );
        return 20 === $idle[0] && 40 === $fast[0] && 10 === $memory[0]
            ? self::pass( 'Control adaptativo responde correctamente a inactividad, rapidez y memoria alta.' )
            : self::fail( 'Cálculo adaptativo inconsistente.' );
    }

    private static function check_load_state() {
        $value = get_option( TaxoCommerce_IE_Load::OPTION, array() );
        if ( ! is_array( $value ) ) { return self::fail( 'Perfil de carga no válido.' ); }
        foreach ( array( 'import', 'export' ) as $kind ) {
            $priority = TaxoCommerce_IE_Load::priority( $kind );
            if ( $priority < 1 || $priority > 5 ) { return self::fail( 'Prioridad fuera de rango.' ); }
        }
        return self::pass( 'Prioridades existentes legibles y dentro de rango.' );
    }

    private static function check_security_actions() {
        return self::allowed()
            && has_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'handle_run' ) )
            && has_action( 'admin_post_' . self::DOWNLOAD_ACTION, array( __CLASS__, 'handle_download' ) )
            ? self::pass( 'Acciones protegidas por capacidad y nonce registradas.' )
            : self::fail( 'Autocheck no tiene sus acciones de administración.' );
    }

    private static function check_reports() {
        return function_exists( 'get_user_meta' ) && function_exists( 'update_user_meta' )
            && function_exists( 'get_current_blog_id' ) && '' !== self::meta_key()
            ? self::pass( 'Último informe aislado por administrador/sitio; no incluye CSV ni credenciales.' )
            : self::fail( 'No se puede conservar el último informe de Autocheck.' );
    }

    private static function check_real_import() { return self::skip( 'No se importan filas reales. Validar con un CSV pequeño en staging.' ); }
    private static function check_real_export() { return self::skip( 'No se genera ningún CSV. Confirmar descarga y contenido en staging.' ); }
    private static function check_real_media() { return self::skip( 'No se crean attachments. Validar un ZIP de prueba y sus relaciones en staging.' ); }
    private static function check_real_queue() { return self::skip( 'No se inicia la cola. Comprobar reanudación real y cron en staging.' ); }

    public static function render() {
        if ( \TaxoCommerce_Import_Export_Autotest_Code::render_subtabs() ) { return; }
        if ( ! self::allowed() ) {
            wp_die( esc_html__( 'Sin permisos para consultar Autocheck.', 'taxocommerce-import-export' ) );
        }

        $last = self::get_saved();
        $cases = self::cases();
        $groups = self::groups();
        $index = array();
        foreach ( (array) ( $last['rows'] ?? array() ) as $row ) {
            if ( isset( $row['id'] ) ) {
                $index[ $row['id'] ] = $row;
            }
        }
        $totals = $last['totals'] ?? array( 'pass' => 0, 'fail' => 0, 'skip' => 0, 'not_run' => count( $cases ) );
        ?>
        <section class="tc-ie-at" aria-labelledby="tc-ie-at-title">
            <h2 id="tc-ie-at-title"><?php esc_html_e( 'Autocheck de Import-Export', 'taxocommerce-import-export' ); ?></h2>
            <p><?php esc_html_e( 'Diagnóstico manual y no destructivo: comprueba las funciones y la configuración sin importar ni exportar archivos, ejecutar workers ni modificar productos.', 'taxocommerce-import-export' ); ?></p>
            <div class="tc-ie-at-actions">
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="tc-ie-at-form">
                    <input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>">
                    <?php wp_nonce_field( self::NONCE, 'tc_ie_auto_nonce' ); ?>
                    <button type="submit" class="button button-secondary"><?php esc_html_e( 'Ejecutar Autocheck', 'taxocommerce-import-export' ); ?></button>
                </form>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="tc-ie-at-form">
                    <input type="hidden" name="action" value="<?php echo esc_attr( self::DOWNLOAD_ACTION ); ?>">
                    <?php wp_nonce_field( self::NONCE, 'tc_ie_auto_nonce' ); ?>
                    <button type="submit" class="button button-secondary" <?php disabled( ! $last ); ?>><?php esc_html_e( 'Descargar resultados (JSON)', 'taxocommerce-import-export' ); ?></button>
                </form>
            </div>

            <?php if ( $last ) : ?>
                <p class="tc-ie-at-last">
                    <strong><?php esc_html_e( 'Fecha de ejecución:', 'taxocommerce-import-export' ); ?></strong>
                    <?php echo esc_html( (string) ( $last['date'] ?? '' ) ); ?>
                    · <?php echo esc_html( 'v' . (string) ( $last['version'] ?? '' ) ); ?>
                    · <?php echo esc_html( absint( $last['duration_ms'] ?? 0 ) . ' ms' ); ?>
                </p>
            <?php else : ?>
                <p class="description"><?php esc_html_e( 'Todavía no se ha ejecutado el Autocheck en este sitio.', 'taxocommerce-import-export' ); ?></p>
            <?php endif; ?>

            <div class="tc-ie-at-totals" aria-label="<?php esc_attr_e( 'Resultados', 'taxocommerce-import-export' ); ?>">
                <div><strong><?php echo esc_html( absint( $totals['pass'] ?? 0 ) ); ?></strong><span><?php esc_html_e( 'Correctos', 'taxocommerce-import-export' ); ?></span></div>
                <div class="tc-ie-at-fail"><strong><?php echo esc_html( absint( $totals['fail'] ?? 0 ) ); ?></strong><span><?php esc_html_e( 'Errores', 'taxocommerce-import-export' ); ?></span></div>
                <div><strong><?php echo esc_html( absint( $totals['skip'] ?? 0 ) ); ?></strong><span><?php esc_html_e( 'Omitidos / externos', 'taxocommerce-import-export' ); ?></span></div>
                <div><strong><?php echo esc_html( absint( $totals['not_run'] ?? 0 ) ); ?></strong><span><?php esc_html_e( 'No ejecutados', 'taxocommerce-import-export' ); ?></span></div>
            </div>

            <?php foreach ( $groups as $group => $name ) : ?>
                <?php
                $items = array_filter( $cases, static function ( $entry ) use ( $group ) {
                    return $entry[0] === $group;
                } );
                $failed = 0;
                foreach ( $items as $id => $entry ) {
                    if ( ( $index[ $id ]['status'] ?? '' ) === 'fail' ) {
                        ++$failed;
                    }
                }
                ?>
                <details class="tc-ie-at-group" <?php echo $failed || ! $last ? 'open' : ''; ?>>
                    <summary><?php echo esc_html( $name . ' · ' . count( $items ) . ' pruebas' . ( $failed ? ' · ' . $failed . ' errores' : '' ) ); ?></summary>
                    <?php foreach ( $items as $id => $entry ) : ?>
                        <?php $row = $index[ $id ] ?? array( 'status' => 'not_run', 'message' => 'Todavía no ejecutada.', 'ms' => 0 ); ?>
                        <div class="tc-ie-at-row">
                            <span class="tc-ie-at-dot tc-ie-at-dot--<?php echo esc_attr( $row['status'] ); ?>" aria-hidden="true"></span>
                            <div><strong><?php echo esc_html( $entry[1] ); ?> <small><?php echo esc_html( $id ); ?></small></strong>
                                <p><?php echo esc_html( $row['message'] ); ?></p>
                                <small><?php echo esc_html( absint( $row['ms'] ) . ' ms' ); ?></small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </details>
            <?php endforeach; ?>
            <p class="description"><?php esc_html_e( 'Los controles correctos validan contratos y lecturas, no operaciones reales. Los casos omitidos requieren pruebas manuales con CSV o ZIP en STAGING.', 'taxocommerce-import-export' ); ?></p>
        </section>
        <?php
    }
}
