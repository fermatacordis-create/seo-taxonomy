<?php
/**
 * Non-destructive functional self-check for the five independent Auditor scopes.
 * Only inspections of registered services and existing WordPress options are run.
 */
namespace TaxoCommerce\Auditor;

defined( 'ABSPATH' ) || exit;

final class Auto_Test {
    const JSON_ACTION = 'taxocommerce_auditor_autocheck_json';
    const ACTION = 'taxocommerce_auditor_auto_test';
    const NONCE = 'taxocommerce_auditor_auto_test_run';
    const TRANSIENT = 'taxocommerce_auditor_auto_test_';

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
            'runtime'    => array( __( 'Plugin', 'taxocommerce-auditor' ), __( 'Servicios Auditor cargados', 'taxocommerce-auditor' ) ),
            'hooks'      => array( __( 'Seguridad', 'taxocommerce-auditor' ), __( 'Rutas protegidas y proceso por lotes registrados', 'taxocommerce-auditor' ) ),
            'scopes'     => array( __( 'Ámbitos', 'taxocommerce-auditor' ), __( 'Cinco tipos de contenido', 'taxocommerce-auditor' ) ),
            'products'   => array( __( 'Productos', 'taxocommerce-auditor' ), __( 'WooCommerce y productos disponibles', 'taxocommerce-auditor' ) ),
            'categories' => array( __( 'Categorías', 'taxocommerce-auditor' ), __( 'Taxonomía de productos disponible', 'taxocommerce-auditor' ) ),
            'faqs'       => array( __( 'FAQs', 'taxocommerce-auditor' ), __( 'Fuente FAQ opcional o alternativa', 'taxocommerce-auditor' ) ),
            'quality'    => array( __( 'Calidad', 'taxocommerce-auditor' ), __( 'Ponderaciones y bandas de puntuación', 'taxocommerce-auditor' ) ),
            'priority'   => array( __( 'Calidad', 'taxocommerce-auditor' ), __( 'Prioridad y revisión editorial', 'taxocommerce-auditor' ) ),
            'filters'    => array( __( 'Auditorías', 'taxocommerce-auditor' ), __( 'Filtros y límites disponibles', 'taxocommerce-auditor' ) ),
            'runner'     => array( __( 'Auditorías', 'taxocommerce-auditor' ), __( 'Motor por lotes y controles de ejecución', 'taxocommerce-auditor' ) ),
            'storage'    => array( __( 'Exportación', 'taxocommerce-auditor' ), __( 'Contratos del almacén JSONL y descarga', 'taxocommerce-auditor' ) ),
            'state'      => array( __( 'Informes', 'taxocommerce-auditor' ), __( 'Acceso a estados e informes guardados', 'taxocommerce-auditor' ) ),
            'history'    => array( __( 'Informes', 'taxocommerce-auditor' ), __( 'Historial de los cinco ámbitos', 'taxocommerce-auditor' ) ),
            'context'    => array( __( 'Integraciones', 'taxocommerce-auditor' ), __( 'Lectura de evidencia local y opcional', 'taxocommerce-auditor' ) ),
            'manual'     => array( __( 'Manual', 'taxocommerce-auditor' ), __( 'Guías de usuario y técnico', 'taxocommerce-auditor' ) ),
            'operations' => array( __( 'Seguridad', 'taxocommerce-auditor' ), __( 'Auditorías completas, exportaciones y cambios', 'taxocommerce-auditor' ) ),
        );
    }

    public static function run_suite() {
        $started = microtime( true );
        $rows = array();
        $totals = array( 'pass' => 0, 'fail' => 0, 'skip' => 0 );
        foreach ( self::cases() as $id => $labels ) {
            $begin = microtime( true );
            try {
                $method = 'test_' . $id;
                $result = self::$method();
                if ( ! is_array( $result ) || ! isset( $result['status'], $result['message'] ) || ! isset( $totals[ $result['status'] ] ) ) {
                    $result = self::fail( __( 'Formato de resultado de prueba no reconocido.', 'taxocommerce-auditor' ) );
                }
            } catch ( \Throwable $error ) {
                $result = self::fail( __( 'No se ha podido completar la comprobación:', 'taxocommerce-auditor' ) . ' ' . $error->getMessage() );
            }
            $rows[] = array(
                'id' => $id, 'group' => $labels[0], 'label' => $labels[1],
                'status' => $result['status'], 'message' => $result['message'],
                'ms' => (int) round( ( microtime( true ) - $begin ) * 1000 ),
            );
            ++$totals[ $result['status'] ];
        }
        return array(
            'version' => Plugin::VERSION, 'date' => current_time( 'mysql' ), 'executed_at' => gmdate( 'c' ),
            'ms' => (int) round( ( microtime( true ) - $started ) * 1000 ),
            'totals' => $totals, 'rows' => $rows,
        );
    }

    private static function test_runtime() {
        foreach ( array( 'Plugin', 'Admin', 'Runner', 'Rules', 'Priority', 'Editorial_Specialist', 'Category_Review', 'FAQ_Source', 'Export_Store', 'Decision_Context', 'Workload', 'Manual' ) as $class ) {
            if ( ! class_exists( __NAMESPACE__ . '\\' . $class ) ) {
                return self::fail( __( 'Falta la clase', 'taxocommerce-auditor' ) . ' ' . $class . '.' );
            }
        }
        return self::pass( __( 'Auditor', 'taxocommerce-auditor' ) . ' ' . Plugin::VERSION . ': ' . __( 'componentes cargados.', 'taxocommerce-auditor' ) );
    }

    private static function test_hooks() {
        foreach ( array(
            'admin_post_taxocommerce_auditor_action',
            'admin_post_taxocommerce_auditor_export',
            'wp_ajax_taxocommerce_auditor_watch',
            'admin_post_' . self::ACTION,
            Runner::HOOK,
        ) as $hook ) {
            if ( ! has_action( $hook ) ) {
                return self::fail( __( 'No está registrado:', 'taxocommerce-auditor' ) . ' ' . $hook . '.' );
            }
        }
        return self::pass( __( 'Formularios, descargas, Autocheck y callback de lotes registrados, sin invocarlos.', 'taxocommerce-auditor' ) );
    }

    private static function test_scopes() {
        $expected = array( 'products', 'categories', 'posts', 'pages', 'faqs' );
        return array_keys( Runner::scopes() ) === $expected
            && ! Runner::valid_scope( 'unknown' )
            ? self::pass( __( 'Los cinco ámbitos de auditoría están disponibles.', 'taxocommerce-auditor' ) )
            : self::fail( __( 'Los ámbitos difieren del contrato de Auditor.', 'taxocommerce-auditor' ) );
    }

    private static function test_products() {
        return function_exists( 'wc_get_product' ) && post_type_exists( 'product' )
            ? self::pass( __( 'Producto WooCommerce y su API de lectura disponibles.', 'taxocommerce-auditor' ) )
            : self::fail( __( 'WooCommerce o el tipo product no están disponibles.', 'taxocommerce-auditor' ) );
    }

    private static function test_categories() {
        return taxonomy_exists( 'product_cat' )
            ? self::pass( __( 'La taxonomía product_cat está registrada.', 'taxocommerce-auditor' ) )
            : self::fail( __( 'No está registrada product_cat; comprueba WooCommerce.', 'taxocommerce-auditor' ) );
    }

    private static function test_faqs() {
        // The FAQ module and custom FAQ CPTs are optional. No SQL is needed.
        return method_exists( __NAMESPACE__ . '\\FAQ_Source', 'available' )
            && method_exists( __NAMESPACE__ . '\\FAQ_Source', 'batch' )
            && method_exists( __NAMESPACE__ . '\\Runner', 'faq_post_type' )
            ? self::pass( __( 'Adaptador FAQ canónico y alternativa por CPT disponibles; la fuente puede ser opcional.', 'taxocommerce-auditor' ) )
            : self::fail( __( 'No están disponibles los lectores FAQ esperados.', 'taxocommerce-auditor' ) );
    }

    private static function test_quality() {
        $total = array_sum( Rules::DIMENSIONS );
        return 100 === $total && 'fuerte' === Rules::band( 85 )
            && 'correcta' === Rules::band( 70 ) && 'mejorable' === Rules::band( 55 )
            && 'debil' === Rules::band( 54 ) && 'no_aplica' === Rules::band( null )
            ? self::pass( __( 'Cinco dimensiones suman 100 y las bandas responden como se espera.', 'taxocommerce-auditor' ) )
            : self::fail( __( 'Hay un desajuste en los criterios de calidad.', 'taxocommerce-auditor' ) );
    }

    private static function test_priority() {
        $row = Priority::decorate( array(
            'code' => 'product_attribute_title_conflict', 'severity' => 'high',
            'entity_type' => 'product', 'entity_id' => 0,
        ) );
        return is_array( $row ) && 'P1' === ( $row['priority'] ?? '' )
            && ! empty( $row['requires_source_check'] )
            ? self::pass( __( 'Clasificación P1 requiere verificación de fuente; no se crea ningún hallazgo.', 'taxocommerce-auditor' ) )
            : self::fail( __( 'El contrato de prioridades no coincide.', 'taxocommerce-auditor' ) );
    }

    private static function test_filters() {
        $filtered = Runner::sanitize_filters( 'products', array( 'mode' => 'filtered', 'rows' => 999 ) );
        $full = Runner::sanitize_filters( 'categories', array( 'mode' => 'full', 'rows' => 10 ) );
        return 25 === $filtered['rows'] && 0 === $full['rows'] && 'full' === $full['mode']
            ? self::pass( __( 'Filtros acotados y modo completo distinguidos sin ejecutar auditorías.', 'taxocommerce-auditor' ) )
            : self::fail( __( 'Los límites de búsqueda han cambiado inesperadamente.', 'taxocommerce-auditor' ) );
    }

    private static function test_runner() {
        foreach ( array( 'start', 'pause', 'resume', 'run_batch', 'ensure_scheduled', 'browser_batch_due', 'progress', 'report' ) as $method ) {
            if ( ! method_exists( __NAMESPACE__ . '\\Runner', $method ) ) {
                return self::fail( __( 'Falta Runner::', 'taxocommerce-auditor' ) . $method . '.' );
            }
        }
        $valid = Runner::BATCH_SIZE > 0 && Runner::BATCH_SIZE <= 500;
        foreach ( array_keys( Runner::scopes() ) as $scope ) {
            $speed = Workload::speed( $scope );
            $valid = $valid && $speed >= 1 && $speed <= 5
                && Workload::batch_size( $scope ) >= 1
                && Workload::batch_size( $scope ) <= Runner::BATCH_SIZE
                && Workload::delay_seconds( $scope ) >= 12
                && Workload::budget_seconds( $scope ) >= 1
                && Workload::client_delay_ms( $scope ) >= 300
                && Workload::client_delay_ms( $scope ) <= 6000
                && Workload::effective_batch_size( $scope ) >= 1
                && Workload::effective_batch_size( $scope ) <= Workload::batch_size( $scope )
                && Workload::adjusted_batch_size(
                    $scope,
                    Workload::effective_batch_size( $scope ),
                    Workload::budget_seconds( $scope ) * 1000,
                    85,
                    1
                ) <= Workload::batch_size( $scope );
        }
        return $valid
            ? self::pass( __( 'Ejecución por lotes y controles disponibles; ningún cron se dispara.', 'taxocommerce-auditor' ) )
            : self::fail( __( 'Tamaño de lote fuera del contrato esperado.', 'taxocommerce-auditor' ) );
    }

    private static function test_storage() {
        foreach ( array( 'begin', 'append', 'rows', 'exists', 'stream_report' ) as $method ) {
            if ( ! method_exists( __NAMESPACE__ . '\\Export_Store', $method ) ) {
                return self::fail( __( 'Falta Export_Store::', 'taxocommerce-auditor' ) . $method . '.' );
            }
        }
        return self::pass( __( 'Almacén JSONL y descarga completa disponibles, sin crear ni abrir archivos.', 'taxocommerce-auditor' ) );
    }

    private static function test_state() {
        foreach ( array_keys( Runner::scopes() ) as $scope ) {
            if ( ! is_array( Runner::state( $scope ) ) || ! is_array( Runner::report( $scope ) ) ) {
                return self::fail( __( 'Formato de estado o informe inválido para', 'taxocommerce-auditor' ) . ' ' . $scope . '.' );
            }
        }
        return self::pass( __( 'Estados e informes de los cinco ámbitos legibles; no se procesan datos.', 'taxocommerce-auditor' ) );
    }

    private static function test_history() {
        foreach ( array_keys( Runner::scopes() ) as $scope ) {
            if ( ! is_array( Runner::history( $scope ) ) ) {
                return self::fail( __( 'Historial no disponible:', 'taxocommerce-auditor' ) . ' ' . $scope . '.' );
            }
        }
        return self::pass( __( 'Historial de cinco ámbitos disponible; puede estar vacío sin ser un error.', 'taxocommerce-auditor' ) );
    }

    private static function test_context() {
        return class_exists( __NAMESPACE__ . '\\Decision_Context' )
            && method_exists( __NAMESPACE__ . '\\Decision_Context', 'decorate' )
            ? self::pass( __( 'Contrato de evidencia local disponible; no se consultan servicios externos.', 'taxocommerce-auditor' ) )
            : self::fail( __( 'No está disponible el contrato de contexto local.', 'taxocommerce-auditor' ) );
    }

    private static function test_manual() {
        return class_exists( __NAMESPACE__ . '\\Manual' ) && method_exists( __NAMESPACE__ . '\\Manual', 'render' )
            ? self::pass( __( 'Guía de usuario y guía técnica integradas.', 'taxocommerce-auditor' ) )
            : self::fail( __( 'No se encuentra la documentación integrada.', 'taxocommerce-auditor' ) );
    }

    private static function test_operations() {
        return self::skip( __( 'No se ejecutan auditorías, procesos WP-Cron, escrituras de JSONL, descargas completas ni cambios editoriales. Probarlos en staging.', 'taxocommerce-auditor' ) );
    }

    public static function handle() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Sin permisos para Autocheck.', 'taxocommerce-auditor' ) );
        }
        check_admin_referer( self::NONCE, 'taxocommerce_auditor_auto_nonce' );
        $key = self::TRANSIENT . get_current_user_id();
        $history = get_transient( $key );
        if ( ! is_array( $history ) ) $history = array();
        array_unshift( $history, self::run_suite() );
        set_transient( $key, array_slice( $history, 0, 2 ), 30 * DAY_IN_SECONDS );
        wp_safe_redirect( admin_url( 'admin.php?page=' . Plugin::MENU_SLUG . '&auditor_scope=auto-test' ) );
        exit;
    }

    /** Download only the authenticated user's saved read-only diagnostics. */
    public static function handle_download_json() {
        if ( ! (current_user_can( 'manage_options' )) ) {
            wp_die( esc_html__( 'Sin permisos para descargar el autocheck.', 'taxocommerce-auditor' ) );
        }
        check_admin_referer( self::NONCE, 'taxocommerce_auditor_auto_nonce' );
        $history = get_transient( self::TRANSIENT . get_current_user_id() );
        $report = is_array( $history ) && isset( $history[0]['rows'] ) ? $history[0] : null;
        if ( ! is_array( $report ) || ! isset( $report['rows'], $report['totals'] ) ) {
            wp_die( esc_html__( 'Ejecuta el autocheck antes de descargar el JSON.', 'taxocommerce-auditor' ) );
        }
        $report['plugin'] = 'taxocommerce-auditor';
        $report['schema'] = 1;
        $json = wp_json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE );
        if ( ! is_string( $json ) ) {
            wp_die( esc_html__( 'No se pudo generar el informe JSON.', 'taxocommerce-auditor' ) );
        }
        nocache_headers();
        header( 'Content-Type: application/json; charset=utf-8' );
        header( 'X-Content-Type-Options: nosniff' );
        header( 'Content-Disposition: attachment; filename="taxocommerce-auditor-autocheck-' . gmdate( 'Ymd-His' ) . '.json"' );
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Download JSON, not HTML.
        echo $json;
        exit;
    }

    public static function render() {
        if ( \TaxoCommerce_Auditor_Autotest_Code::render_subtabs() ) { return; }
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Sin permisos para Autocheck.', 'taxocommerce-auditor' ) );
        }
        $history = get_transient( self::TRANSIENT . get_current_user_id() );
        $current = is_array( $history ) && isset( $history[0]['rows'] ) ? $history[0] : null;
        $previous = is_array( $history ) && isset( $history[1]['rows'] ) ? $history[1] : null;
        echo '<section class="card" style="max-width:1200px">' . '<h2>' . esc_html__( 'Autocheck de Auditor', 'taxocommerce-auditor' ) . '</h2>';
        echo '<p>' . esc_html__( 'Comprueba servicios, rutas, ámbitos, calidad, opciones, historial, FAQ y disponibilidad de exportación. Es de solo lectura: no inicia procesos ni modifica contenidos. Se guardan dos resultados por usuario durante 30 días.', 'taxocommerce-auditor' ) . '</p>';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '">';
        wp_nonce_field( self::NONCE, 'taxocommerce_auditor_auto_nonce' );
        submit_button( __( 'Ejecutar Autocheck', 'taxocommerce-auditor' ), 'primary', 'submit', false );
        echo '</form>';
        if ( ! $current ) {
            echo '<p>' . esc_html__( 'Todavía no hay resultados. Pulsa Ejecutar Autocheck.', 'taxocommerce-auditor' ) . '</p>' . '</section>';
            return;
        }
        $totals = $current['totals'];
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="' . esc_attr( self::JSON_ACTION ) . '">';
        wp_nonce_field( self::NONCE, 'taxocommerce_auditor_auto_nonce' );
        submit_button( __( 'Descargar resultados (JSON)', 'taxocommerce-auditor' ), 'secondary', 'submit', false );
        echo '</form>';

        echo '<h3>' . esc_html__( 'Último informe', 'taxocommerce-auditor' ) . ' · v' . esc_html( $current['version'] ) . ' · ' . esc_html__( 'Fecha de ejecución:', 'taxocommerce-auditor' ) . ' ' . esc_html( $current['date'] ) . '</h3>';
        echo '<p><strong>' . esc_html( (string) $totals['pass'] ) . ' ' . esc_html__( 'correctas', 'taxocommerce-auditor' ) . '</strong> · <strong>' . esc_html( (string) $totals['fail'] ) . ' ' . esc_html__( 'fallidas', 'taxocommerce-auditor' ) . '</strong> · ' . esc_html( (string) $totals['skip'] ) . ' ' . esc_html__( 'omitidas', 'taxocommerce-auditor' ) . ' · ' . esc_html( (string) $current['ms'] ) . ' ms</p>';
        if ( $previous ) {
            $old = array();
            foreach ( $previous['rows'] as $row ) $old[ $row['id'] ] = $row['status'];
            $regressions = array();
            foreach ( $current['rows'] as $row ) {
                if ( 'fail' === $row['status'] && ( $old[ $row['id'] ] ?? '' ) === 'pass' ) $regressions[] = $row['label'];
            }
            echo '<p><strong>' . esc_html__( 'Comparativa:', 'taxocommerce-auditor' ) . '</strong> ' . esc_html( $previous['version'] . ' (' . $previous['date'] . ') → ' . $current['version'] ) . '. ';
            echo $regressions ? '<strong style="color:#b32d2e">' . esc_html__( 'Regresiones:', 'taxocommerce-auditor' ) . ' ' . esc_html( implode( ', ', $regressions ) ) . '</strong>' : esc_html__( 'Sin regresiones de correcto a fallido.', 'taxocommerce-auditor' );
            echo '</p>';
        } else {
            echo '<p>' . esc_html__( 'No hay una ejecución anterior para comparar.', 'taxocommerce-auditor' ) . '</p>';
        }
        echo '<div style="overflow-x:auto">' . '<table class="widefat striped">' . '<thead>' . '<tr>' . '<th>' . esc_html__( 'Grupo', 'taxocommerce-auditor' ) . '</th>' . '<th>' . esc_html__( 'Prueba', 'taxocommerce-auditor' ) . '</th>' . '<th>' . esc_html__( 'Estado', 'taxocommerce-auditor' ) . '</th>' . '<th>' . esc_html__( 'Resultado', 'taxocommerce-auditor' ) . '</th>' . '<th>' . esc_html__( 'Tiempo', 'taxocommerce-auditor' ) . '</th>' . '</tr>' . '</thead>' . '<tbody>';
        $names = array( 'pass' => __( 'Correcto', 'taxocommerce-auditor' ), 'fail' => __( 'Error', 'taxocommerce-auditor' ), 'skip' => __( 'No ejecutado', 'taxocommerce-auditor' ) );
        foreach ( $current['rows'] as $row ) {
            echo '<tr><td>' . esc_html( $row['group'] ) . '</td><td>' . esc_html( $row['label'] ) . '</td><td><strong>' . esc_html( $names[ $row['status'] ] ) . '</strong></td><td>' . esc_html( $row['message'] ) . '</td><td>' . esc_html( (string) $row['ms'] ) . ' ms</td></tr>';
        }
        echo '</tbody>' . '</table>' . '</div>' . '<p class="description">' . esc_html__( 'No demuestra por sí mismo que una auditoría completa o la exportación JSONL funcionen con un catálogo real.', 'taxocommerce-auditor' ) . '</p>' . '</section>';
    }
}
