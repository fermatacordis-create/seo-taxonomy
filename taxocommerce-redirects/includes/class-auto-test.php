<?php
/**
 * Non-destructive pre/post-deployment self-checks for TaxoCommerce Redirects.
 *
 * Diagnostics never create, change or delete redirect rules, follow a redirect,
 * hit a public URL, call Store::record_hit(), or run assisted deletion. Only
 * explicit test runs store per-administrator reports for comparison.
 *
 * @package TaxoCommerceRedirects
 */
namespace TaxoCommerce\Redirects;

defined( 'ABSPATH' ) || exit;

final class Auto_Test {
    const RUN_ACTION = 'tc_redirects_auto_test_run';
    const EXPORT_ACTION = 'tc_redirects_auto_test_export';
    const NONCE = 'tc_redirects_auto_test';
    const BEFORE_KEY = 'tc_redirects_auto_test_before_';
    const LAST_KEY = 'tc_redirects_auto_test_last_';

    public static function register() {
        add_action( 'admin_post_' . self::RUN_ACTION, array( __CLASS__, 'handle_run' ) );
        add_action( 'admin_post_' . self::EXPORT_ACTION, array( __CLASS__, 'handle_export' ) );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
    }

    public static function assets( $hook ) {
        if ( false === strpos( (string) $hook, Plugin::SLUG ) ) {
            return;
        }
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only tab selection.
        $tab = isset( $_GET['tc_rd_tab'] ) && is_string( $_GET['tc_rd_tab'] )
            ? sanitize_key( wp_unslash( $_GET['tc_rd_tab'] ) ) : '';
        // phpcs:enable WordPress.Security.NonceVerification.Recommended
        if ( 'auto-test' === $tab ) {
            wp_enqueue_style( 'tc-redirects-auto-test', plugins_url( 'assets/auto-test.css', dirname( __DIR__ ) . '/taxocommerce-redirects.php' ), array(), Plugin::VERSION );
        }
    }

    public static function groups() {
        return array(
            'startup' => 'Arranque y administración',
            'storage' => 'Tabla y datos compartidos',
            'rules' => 'Validación y diagnóstico de reglas',
            'engine' => 'Motor de redirecciones',
            'assisted' => 'Borrado asistido',
            'transfer' => 'Importación y exportación JSON',
            'security' => 'Seguridad y recuperación',
            'uat' => 'Pruebas externas no ejecutadas',
        );
    }

    public static function cases() {
        return array(
            'classes' => array( 'startup', 'Componentes del plugin cargados' ),
            'menu' => array( 'startup', 'Menú, pestaña y acciones de Autocheck' ),
            'assets' => array( 'startup', 'Recursos administrativos y editores' ),
            'core' => array( 'startup', 'Integración opcional con Core' ),
            'table' => array( 'storage', 'Tabla compartida seo_redirects' ),
            'columns' => array( 'storage', 'Columnas mínimas de la tabla' ),
            'readiness' => array( 'storage', 'Marca de instalación y tabla coherentes' ),
            'limits' => array( 'storage', 'Límites de registros y tamaño JSON' ),
            'origins' => array( 'rules', 'Normalización de rutas de origen' ),
            'targets' => array( 'rules', 'Destinos locales y bloqueo de externos' ),
            'effective' => array( 'rules', 'Rutas efectivas y www' ),
            'diagnostics' => array( 'rules', 'Detección de cadenas y ciclos sin SQL' ),
            'runtime' => array( 'engine', 'Hooks del motor y respuesta 301/302' ),
            'legacy' => array( 'engine', 'Convivencia con motor SEO histórico' ),
            'editors' => array( 'assisted', 'Entradas, páginas, productos y categorías' ),
            'delete_hooks' => array( 'assisted', 'Confirmación, papelera y restauración' ),
            'category_guard' => array( 'assisted', 'Protección de categorías coexistentes' ),
            'transfer_hooks' => array( 'transfer', 'Controladores de importación/exportación' ),
            'transfer_schema' => array( 'transfer', 'Contrato y límites de JSON' ),
            'permission' => array( 'security', 'Autorización de administrador y nonce' ),
            'lifecycle' => array( 'security', 'Instalación y desinstalación protegidas' ),
            'history' => array( 'security', 'Informes aislados por administrador y sitio' ),
            'http' => array( 'uat', 'Redirección HTTP real y contador de hits' ),
            'json_roundtrip' => array( 'uat', 'Exportar/importar JSON y conservar reglas' ),
            'assisted_uat' => array( 'uat', 'Borrado y restauración reales de contenido' ),
            'destinations' => array( 'uat', 'Disponibilidad HTTP de URLs destino' ),
        );
    }

    private static function verdict( $status, $detail ) {
        return array( 'status' => $status, 'detail' => $detail );
    }

    private static function pass( $detail ) { return self::verdict( 'pass', $detail ); }
    private static function fail( $detail ) { return self::verdict( 'fail', $detail ); }
    private static function skip( $detail ) { return self::verdict( 'skip', $detail ); }

    private static function site_hash() {
        return hash( 'sha256', (string) home_url( '/' ) );
    }

    private static function key( $before ) {
        return ( $before ? self::BEFORE_KEY : self::LAST_KEY ) . get_current_blog_id();
    }

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

    public static function suite( $group = 'all', $override = null ) {
        $groups = self::groups();
        $group = 'all' === $group || isset( $groups[ $group ] ) ? $group : 'all';
        $cases = null === $override ? self::cases() : $override;
        $start = microtime( true );
        $rows = array();
        $totals = array( 'pass' => 0, 'fail' => 0, 'skip' => 0, 'not_run' => 0 );
        foreach ( $cases as $id => $item ) {
            $began = microtime( true );
            if ( 'all' !== $group && $item[0] !== $group ) {
                $result = self::verdict( 'not_run', 'Este grupo no se ha ejecutado.' );
            } else {
                try {
                    $callback = array( __CLASS__, 'check_' . $id );
                    $result = is_callable( $callback ) ? call_user_func( $callback ) : self::fail( 'Prueba sin implementar.' );
                    if ( ! is_array( $result ) || ! isset( $result['status'], $result['detail'] )
                        || ! in_array( $result['status'], array( 'pass', 'fail', 'skip' ), true ) ) {
                        $result = self::fail( 'La prueba ha devuelto un resultado incorrecto.' );
                    }
                } catch ( \Throwable $error ) {
                    // Exception content can reveal paths/URLs. Never persist raw exception details.
                    $result = self::fail( 'Excepción durante la comprobación (' . get_class( $error ) . '). Consultar log privado del servidor.' );
                }
            }
            $rows[] = array(
                'id' => (string) $id, 'group' => (string) $item[0],
                'name' => (string) $item[1], 'status' => $result['status'],
                'detail' => $result['detail'],
                'ms' => (int) round( ( microtime( true ) - $began ) * 1000 ),
            );
            ++$totals[ $result['status'] ];
        }
        global $wp_version;
        return self::standardize_report( array(
            'schema' => 1, 'phase' => 'check', 'group' => $group,
            'site_hash' => self::site_hash(), 'version' => Plugin::VERSION,
            'wp_version' => isset( $wp_version ) ? (string) $wp_version : '',
            'php_version' => PHP_VERSION, 'date_utc' => gmdate( 'c' ), 'executed_at' => gmdate( 'c' ),
            'duration_ms' => (int) round( ( microtime( true ) - $start ) * 1000 ),
            'totals' => $totals, 'rows' => $rows,
        ) );
    }

    public static function compare( $before, $after ) {
        if ( ! is_array( $before ) || ! is_array( $after ) || empty( $before['site_hash'] )
            || ! hash_equals( (string) $before['site_hash'], (string) ( $after['site_hash'] ?? '' ) )
            || 'before' !== ( $before['phase'] ?? '' ) || 'after' !== ( $after['phase'] ?? '' )
            || 'all' !== ( $before['group'] ?? '' ) || 'all' !== ( $after['group'] ?? '' ) ) {
            return null;
        }
        $old = array();
        $new = array();
        foreach ( (array) ( $before['rows'] ?? array() ) as $entry ) {
            if ( isset( $entry['id'], $entry['status'] ) ) { $old[ $entry['id'] ] = $entry['status']; }
        }
        foreach ( (array) ( $after['rows'] ?? array() ) as $entry ) {
            if ( isset( $entry['id'], $entry['status'] ) ) { $new[ $entry['id'] ] = $entry['status']; }
        }
        $out = array( 'new_failures' => array(), 'resolved' => array(), 'existing_failures' => array(), 'lost_coverage' => array(), 'new_checks' => array() );
        foreach ( $new as $id => $status ) {
            if ( ! isset( $old[ $id ] ) ) {
                $out['new_checks'][] = $id;
                if ( 'fail' === $status ) { $out['new_failures'][] = $id; }
                continue;
            }
            if ( 'fail' === $status ) {
                $out[ 'fail' === $old[ $id ] ? 'existing_failures' : 'new_failures' ][] = $id;
            } elseif ( 'pass' === $status && 'fail' === $old[ $id ] ) {
                $out['resolved'][] = $id;
            } elseif ( in_array( $old[ $id ], array( 'pass', 'fail' ), true )
                && in_array( $status, array( 'skip', 'not_run' ), true ) ) {
                $out['lost_coverage'][] = $id;
            }
        }
        foreach ( $old as $id => $status ) {
            if ( ! array_key_exists( $id, $new ) ) { $out['lost_coverage'][] = $id; }
        }
        return $out;
    }

    private static function missing_actions( $actions ) {
        $missing = array();
        foreach ( $actions as $action ) {
            if ( false === has_action( $action ) ) { $missing[] = $action; }
        }
        return $missing;
    }

    private static function check_classes() {
        foreach ( array( 'Plugin', 'Store', 'Engine', 'Admin', 'Lifecycle', 'Assisted_Delete', 'Auto_Test' ) as $class ) {
            if ( ! class_exists( __NAMESPACE__ . '\\' . $class ) ) {
                return self::fail( 'Componente no disponible: ' . $class );
            }
        }
        return self::pass( 'Siete componentes de Redirects cargados.' );
    }

    private static function check_menu() {
        $required = array( 'admin_menu', 'admin_post_' . self::RUN_ACTION, 'admin_post_' . self::EXPORT_ACTION );
        return ! self::missing_actions( $required )
            && false !== has_action( 'admin_menu', array( __NAMESPACE__ . '\\Admin', 'menu' ) )
            ? self::pass( 'Menú y rutas protegidas de Autocheck registrados.' )
            : self::fail( 'Falta la navegación o algún controlador del autochequeo.' );
    }

    private static function check_assets() {
        foreach ( array( 'assets/admin.css', 'assets/auto-test.css', 'assets/core.js', 'assets/block-editor.js' ) as $file ) {
            if ( ! is_readable( dirname( __DIR__ ) . '/' . $file ) ) {
                return self::fail( 'Recurso obligatorio no disponible: ' . $file );
            }
        }
        return self::pass( 'CSS, editor clásico/Core y Gutenberg disponibles en el paquete.' );
    }

    private static function check_core() {
        if ( ! function_exists( 'taxocommerce_core' ) ) {
            return self::skip( 'Core no está activo; Redirects funciona de forma independiente.' );
        }
        return false !== has_action( 'taxocommerce_core_register_modules', array( __NAMESPACE__ . '\\Plugin', 'register_core' ) )
            ? self::pass( 'Registro opcional con Core disponible.' )
            : self::fail( 'Core activo, pero Redirects no ha registrado su integración.' );
    }

    private static function check_table() {
        return Store::table_exists()
            ? self::pass( 'La tabla compartida de reglas existe; no se han modificado sus registros.' )
            : self::fail( 'Falta la tabla seo_redirects. Comprobar ciclo de activación y copias de seguridad.' );
    }

    private static function check_columns() {
        if ( ! Store::table_exists() ) { return self::skip( 'La tabla no existe; no se pueden validar columnas.' ); }
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Live read-only schema verification must detect recent table changes in the manually run self-test.
        $columns = $wpdb->get_col( $wpdb->prepare( 'SHOW COLUMNS FROM %i', Store::table() ) );
        if ( ! is_array( $columns ) ) { return self::fail( 'No es posible inspeccionar la estructura de la tabla.' ); }
        $required = array( 'id', 'origin_url', 'target_url', 'status_code', 'hits', 'last_hit', 'created_at', 'updated_at' );
        $missing = array_diff( $required, $columns );
        return ! $missing ? self::pass( 'Ocho columnas del esquema compartido presentes.' )
            : self::fail( 'Columnas ausentes: ' . implode( ', ', $missing ) );
    }

    private static function check_readiness() {
        if ( ! Store::table_exists() ) { return self::fail( 'Sin tabla de reglas; el motor no puede funcionar.' ); }
        return '1' === (string) get_option( Lifecycle::READY, '' )
            ? self::pass( 'Tabla y marca de instalación preparadas.' )
            : self::fail( 'Existe la tabla, pero la marca de preparación no está activa: revisar activación.' );
    }

    private static function check_limits() {
        return Store::MAX_ROWS === 50000 && Store::MAX_IMPORT_BYTES === 26214400
            ? self::pass( 'Límites de 50.000 reglas y 25 MiB registrados.' )
            : self::fail( 'Han cambiado los límites de seguridad del importador.' );
    }

    private static function check_origins() {
        return '/antigua' === Store::normalize_origin( '/antigua/' )
            && is_wp_error( Store::normalize_origin( 'https://other-domain.invalid/ruta' ) )
            && is_wp_error( Store::normalize_origin( '/' ) )
            ? self::pass( 'Normalización, protección de inicio y bloqueo de otros dominios correctos.' )
            : self::fail( 'La validación de URLs de origen ha cambiado.' );
    }

    private static function check_targets() {
        $local = Store::normalize_target( '/nueva/?ref=x' );
        return '/nueva?ref=x' === $local
            && is_wp_error( Store::normalize_target( 'https://other-domain.invalid/externa' ) )
            ? self::pass( 'Destinos internos y parámetros conservados; dominio externo rechazado.' )
            : self::fail( 'La política de destinos locales no se cumple.' );
    }

    private static function check_effective() {
        return Store::same_host( 'www.example.test', 'example.test' )
            && '/ruta' === Store::effective_path( '/ruta/?x=1' )
            ? self::pass( 'Equivalencia www y ruta efectiva estables.' )
            : self::fail( 'La resolución de rutas efectivas ha cambiado.' );
    }

    private static function check_diagnostics() {
        $base = array(
            (object) array( 'id'=>1, 'origin_url'=>'/a', 'target_url'=>'/b', 'status_code'=>301, 'hits'=>0, 'last_hit'=>null ),
            (object) array( 'id'=>2, 'origin_url'=>'/b', 'target_url'=>'/c', 'status_code'=>302, 'hits'=>0, 'last_hit'=>null ),
            (object) array( 'id'=>3, 'origin_url'=>'/d', 'target_url'=>'/e', 'status_code'=>301, 'hits'=>0, 'last_hit'=>null ),
            (object) array( 'id'=>4, 'origin_url'=>'/e', 'target_url'=>'/d', 'status_code'=>301, 'hits'=>0, 'last_hit'=>null ),
        );
        $report = Store::diagnostics( $base );
        return isset( $report['stats']['chains'], $report['stats']['cycles'] )
            && $report['stats']['chains'] > 0 && $report['stats']['cycles'] > 0
            ? self::pass( 'Cadenas y ciclos detectados en cuatro reglas sintéticas; sin escribir en MySQL.' )
            : self::fail( 'El diagnóstico estructural no detecta anomalías conocidas.' );
    }

    private static function check_runtime() {
        return Engine::MAX_CHAIN_HOPS === 20
            && false !== has_action( 'template_redirect', array( __NAMESPACE__ . '\\Engine', 'maybe_redirect' ) )
            && false !== has_action( 'init', array( __NAMESPACE__ . '\\Engine', 'disable_legacy_runtime' ) )
            ? self::pass( 'Motor registrado con límite de cadenas; códigos 301/302 sujetos a tests CI.' )
            : self::fail( 'Falta un hook o protección del motor de redirección.' );
    }

    private static function check_legacy() {
        if ( false === has_action( 'send_headers', 'interceptar_redireccion_antes_de_wordpress' ) ) {
            return self::pass( 'No hay un segundo motor histórico de cabeceras registrado.' );
        }
        return self::skip( 'El motor histórico está registrado en administración; en frontend Redirects lo desactiva solo si la tabla está lista. Verificar el tráfico real.' );
    }

    private static function check_editors() {
        return count( Assisted_Delete::ALLOWED ) === 4
            && ! array_diff( array( 'post', 'page', 'product', 'product_cat' ), array_keys( Assisted_Delete::ALLOWED ) )
            ? self::pass( 'Los cuatro tipos de contenido admitidos conservan confirmación individual.' )
            : self::fail( 'Ha cambiado la lista de tipos de borrado asistido.' );
    }

    private static function check_delete_hooks() {
        $missing = self::missing_actions( array( 'admin_post_tc_redirects_assisted_delete', 'untrashed_post',
            'admin_init', 'post_submitbox_misc_actions', 'product_cat_edit_form_fields', 'enqueue_block_editor_assets' ) );
        return ! $missing ? self::pass( 'Confirmación, papelera, restauración y accesos desde editores registrados.' )
            : self::fail( 'No están disponibles: ' . implode( ', ', $missing ) );
    }

    private static function check_category_guard() {
        return false !== has_action( 'pre_delete_term', 'seo_product_category_pre_delete_term' )
            ? self::skip( 'El guard del monolito permanece activo: el borrado de categorías requiere el flujo antiguo.' )
            : self::pass( 'No consta el bloqueo de categoría del monolito. Persisten las comprobaciones propias.' );
    }

    private static function check_transfer_hooks() {
        $missing = self::missing_actions( array( 'admin_post_tc_redirects_manage', 'admin_post_tc_redirects_export' ) );
        return ! $missing ? self::pass( 'CRUD, importación JSON y exportación JSON registrados.' )
            : self::fail( 'Controladores JSON ausentes: ' . implode( ', ', $missing ) );
    }

    private static function check_transfer_schema() {
        return method_exists( __NAMESPACE__ . '\\Admin', 'handle_manage' )
            && method_exists( __NAMESPACE__ . '\\Admin', 'handle_export' )
            && Store::MAX_ROWS > 0 && Store::MAX_IMPORT_BYTES > 0
            ? self::pass( 'El contrato taxocommerce-redirects-v1 y límites tienen controladores; no se importa ni exporta.' )
            : self::fail( 'El contrato de transferencia no está disponible.' );
    }

    private static function check_permission() {
        return current_user_can( 'manage_options' )
            && ! self::missing_actions( array( 'admin_post_' . self::RUN_ACTION, 'admin_post_' . self::EXPORT_ACTION ) )
            ? self::pass( 'Autocheck requiere administrador y nonces en ambas operaciones.' )
            : self::fail( 'Rutas Autocheck o permisos no válidos.' );
    }

    private static function check_lifecycle() {
        return method_exists( __NAMESPACE__ . '\\Lifecycle', 'activate' )
            && method_exists( __NAMESPACE__ . '\\Lifecycle', 'uninstall' )
            && 'tc_redirects_table_managed' === Lifecycle::MANAGED
            && 'tc_redirects_schema_ready' === Lifecycle::READY
            ? self::pass( 'Activación reutiliza tabla; desinstalación es explícita. No se ejecuta ninguna de ellas.' )
            : self::fail( 'Contratos de ciclo de vida diferentes a los esperados.' );
    }

    private static function check_history() {
        return self::key( true ) !== self::key( false ) && function_exists( 'get_user_meta' )
            && function_exists( 'update_user_meta' )
            ? self::pass( 'Dos informes por usuario y sitio. No contienen rutas ni reglas privadas.' )
            : self::fail( 'No puede conservarse el historial del diagnóstico.' );
    }

    private static function check_http() {
        return self::skip( 'No se siguen URLs ni se incrementan hits. Comprobar una 301 y una 302 con navegador/HTTP tras el despliegue.' );
    }
    private static function check_json_roundtrip() {
        return self::skip( 'La importación real cambia reglas; validar exportación y restauración sobre staging con copia previa.' );
    }
    private static function check_assisted_uat() {
        return self::skip( 'La eliminación real puede ser irreversible; probar con contenido desechable en staging.' );
    }
    private static function check_destinations() {
        return self::skip( 'No se consultan destinos por HTTP; «correcto» no garantiza página disponible.' );
    }

    public static function handle_run() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Sin permiso para ejecutar Autocheck.', 'taxocommerce-redirects' ) );
        }
        check_admin_referer( self::NONCE, 'tc_rd_test_nonce' );
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified above.
        $group = isset( $_POST['test_group'] ) && is_string( $_POST['test_group'] )
            ? sanitize_key( wp_unslash( $_POST['test_group'] ) ) : 'all';
        $phase = isset( $_POST['test_phase'] ) && is_string( $_POST['test_phase'] )
            ? sanitize_key( wp_unslash( $_POST['test_phase'] ) ) : 'check';
        // phpcs:enable WordPress.Security.NonceVerification.Missing
        if ( 'all' !== $group && ! isset( self::groups()[ $group ] ) ) { $group = 'all'; }
        if ( 'all' !== $group || ! in_array( $phase, array( 'before', 'after', 'check' ), true ) ) { $phase = 'check'; }
        $report = self::suite( $group );
        $report['phase'] = $phase;
        update_user_meta( get_current_user_id(), self::key( false ), $report );
        if ( 'before' === $phase ) { update_user_meta( get_current_user_id(), self::key( true ), $report ); }
        wp_safe_redirect( self::url() );
        exit;
    }

    public static function handle_export() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Sin permiso para exportar el diagnóstico.', 'taxocommerce-redirects' ) );
        }
        check_admin_referer( self::NONCE, 'tc_rd_test_nonce' );
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified above.
        $kind = isset( $_POST['report_kind'] ) && is_string( $_POST['report_kind'] )
            ? sanitize_key( wp_unslash( $_POST['report_kind'] ) ) : 'last';
        // phpcs:enable WordPress.Security.NonceVerification.Missing
        $before = 'before' === $kind;
        $report = self::stored( $before );
        if ( ! $report ) { wp_die( esc_html__( 'No se ha guardado ese informe.', 'taxocommerce-redirects' ) ); }
        $json = wp_json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE );
        if ( false === $json ) { wp_die( esc_html__( 'No se ha podido preparar el JSON.', 'taxocommerce-redirects' ) ); }
        nocache_headers();
        header( 'Content-Type: application/json; charset=utf-8' );
        header( 'X-Content-Type-Options: nosniff' );
        header( 'Content-Disposition: attachment; filename="taxocommerce-redirects-auto-test-' . ( $before ? 'before' : 'last' ) . '-' . gmdate( 'Ymd-His' ) . '.json"' );
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Response is JSON, not HTML.
        echo $json;
        exit;
    }

    private static function stored( $before ) {
        $report = get_user_meta( get_current_user_id(), self::key( $before ), true );
        return is_array( $report ) && ! empty( $report['rows'] ) && ! empty( $report['site_hash'] )
            && hash_equals( self::site_hash(), (string) $report['site_hash'] ) ? self::standardize_report( $report ) : array();
    }

    private static function url() {
        return add_query_arg( array( 'page' => Plugin::SLUG, 'tc_rd_tab' => 'auto-test' ), admin_url( 'admin.php' ) );
    }

    private static function run_button( $group, $phase, $label, $primary = false ) {
        ?>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="tc-rd-at-form">
            <input type="hidden" name="action" value="<?php echo esc_attr( self::RUN_ACTION ); ?>">
            <input type="hidden" name="test_group" value="<?php echo esc_attr( $group ); ?>">
            <input type="hidden" name="test_phase" value="<?php echo esc_attr( $phase ); ?>">
            <?php wp_nonce_field( self::NONCE, 'tc_rd_test_nonce' ); ?>
            <button type="submit" class="button <?php echo $primary ? 'button-primary' : 'button-secondary'; ?>"><?php echo esc_html( $label ); ?></button>
        </form>
        <?php
    }

    private static function export_button( $before, $label ) {
        ?>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="tc-rd-at-form">
            <input type="hidden" name="action" value="<?php echo esc_attr( self::EXPORT_ACTION ); ?>">
            <input type="hidden" name="report_kind" value="<?php echo $before ? 'before' : 'last'; ?>">
            <?php wp_nonce_field( self::NONCE, 'tc_rd_test_nonce' ); ?>
            <button type="submit" class="button button-secondary"><?php echo esc_html( $label ); ?></button>
        </form>
        <?php
    }

    public static function render() {
        if ( \TaxoCommerce_Redirects_Autotest_Code::render_subtabs() ) { return; }
        if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Sin permiso para abrir Autocheck.', 'taxocommerce-redirects' ) ); }
        $last = self::stored( false );
        $groups = self::groups();
        $cases = self::cases();
        $indexed = array();
        foreach ( (array) ( $last['rows'] ?? array() ) as $row ) {
            if ( isset( $row['id'] ) ) { $indexed[ $row['id'] ] = $row; }
        }
        $counts = $last['totals'] ?? array( 'pass' => 0, 'fail' => 0, 'skip' => 0, 'not_run' => count( $cases ) );
        ?>
        <section class="tc-rd-at" aria-labelledby="tc-rd-auto-title">
            <h2 id="tc-rd-auto-title"><?php esc_html_e( 'Autocheck de TaxoCommerce Redirects', 'taxocommerce-redirects' ); ?></h2>
            <p><?php esc_html_e( 'Diagnóstico manual y no destructivo: comprueba las funcionalidades y la configuración sin modificar redirecciones, visitas ni contenidos.', 'taxocommerce-redirects' ); ?></p>
            <div class="tc-rd-at-actions">
                <?php self::run_button( 'all', 'check', __( 'Ejecutar Autocheck', 'taxocommerce-redirects' ), false ); ?>
                <?php if ( $last ) : ?>
                    <?php self::export_button( false, __( 'Descargar resultados (JSON)', 'taxocommerce-redirects' ) ); ?>
                <?php else : ?>
                    <button type="button" class="button button-secondary" disabled><?php esc_html_e( 'Descargar resultados (JSON)', 'taxocommerce-redirects' ); ?></button>
                <?php endif; ?>
            </div>
            <?php if ( $last ) : ?>
                <p class="tc-rd-at-last">
                    <strong><?php esc_html_e( 'Fecha de ejecución:', 'taxocommerce-redirects' ); ?></strong>
                    <?php echo esc_html( (string) ( $last['executed_at'] ?? '' ) ); ?>
                    · <?php echo esc_html( 'v' . (string) ( $last['version'] ?? '' ) ); ?>
                    · <?php echo esc_html( absint( $last['duration_ms'] ?? 0 ) . ' ms' ); ?>
                </p>
            <?php else : ?>
                <p class="description"><?php esc_html_e( 'Todavía no se ha ejecutado el Autocheck en este sitio.', 'taxocommerce-redirects' ); ?></p>
            <?php endif; ?>
            <div class="tc-rd-at-totals" aria-label="<?php esc_attr_e( 'Resumen del autochequeo', 'taxocommerce-redirects' ); ?>">
                <?php foreach ( array( 'pass' => __( 'Correctos', 'taxocommerce-redirects' ), 'fail' => __( 'Errores', 'taxocommerce-redirects' ), 'skip' => __( 'Omitidos / externos', 'taxocommerce-redirects' ), 'not_run' => __( 'No ejecutadas', 'taxocommerce-redirects' ) ) as $key => $label ) : ?>
                    <div><strong><?php echo esc_html( absint( $counts[ $key ] ?? 0 ) ); ?></strong><span><?php echo esc_html( $label ); ?></span></div>
                <?php endforeach; ?>
            </div>
            <?php foreach ( $groups as $group => $title ) : ?>
                <?php
                $subset = array_filter( $cases, static function ( $entry ) use ( $group ) { return $entry[0] === $group; } );
                $fails = 0;
                foreach ( $subset as $id => $item ) { if ( 'fail' === ( $indexed[ $id ]['status'] ?? '' ) ) { ++$fails; } }
                ?>
                <details class="tc-rd-at-group" <?php echo $fails || ! $last ? 'open' : ''; ?>>
                    <summary><?php echo esc_html( $title . ' · ' . count( $subset ) . ' pruebas' . ( $fails ? ' · ' . $fails . ' errores' : '' ) ); ?></summary>
                    <?php foreach ( $subset as $id => $label ) : ?>
                        <?php $row = $indexed[ $id ] ?? array( 'status' => 'not_run', 'label' => $label[1], 'message' => 'No ejecutada.', 'ms' => 0 ); ?>
                        <div class="tc-rd-at-row">
                            <span class="tc-rd-at-dot tc-rd-at-dot--<?php echo esc_attr( $row['status'] ); ?>" aria-hidden="true"></span>
                            <div><strong><?php echo esc_html( (string) $row['label'] ); ?> <small><?php echo esc_html( $id ); ?></small></strong><p><?php echo esc_html( (string) $row['message'] ); ?></p><small><?php echo esc_html( absint( $row['ms'] ) . ' ms' ); ?></small></div>
                        </div>
                    <?php endforeach; ?>
                </details>
            <?php endforeach; ?>
            <p class="description"><?php esc_html_e( 'Correcto solo acredita el contrato probado. Las verificaciones HTTP, importaciones JSON y borrados de contenido aparecen como NO verificadas y requieren pruebas controladas en staging. Un destino no encontrado puede coexistir con una regla estructuralmente correcta.', 'taxocommerce-redirects' ); ?></p>
        </section>
        <?php
    }
}
