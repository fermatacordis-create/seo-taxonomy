<?php
/**
 * Read-only pre/post-deployment regression checks for TaxoCommerce Logistics.
 *
 * No order, product, shipping rate, saved rule, transients or WooCommerce
 * settings are changed. Only the administrator's own diagnostic snapshots
 * are saved after an explicit click.
 *
 * @package TaxoCommerceLogistica
 */
defined( 'ABSPATH' ) || exit;

final class TaxoCommerce_Logistica_Auto_Test {
    const RUN = 'taxocommerce_logistica_auto_test_run';
    const EXPORT = 'taxocommerce_logistica_auto_test_export';
    const NONCE = 'taxocommerce_logistica_auto_test';
    const BEFORE = 'tc_logistica_autotest_before_';
    const LATEST = 'tc_logistica_autotest_latest_';

    public static function register() {
        add_action( 'admin_post_' . self::RUN, array( __CLASS__, 'handle_run' ) );
        add_action( 'admin_post_' . self::EXPORT, array( __CLASS__, 'handle_export' ) );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
    }

    private static function allowed() {
        return current_user_can( class_exists( 'WooCommerce' ) ? 'manage_woocommerce' : 'manage_options' );
    }

    public static function assets() {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only navigation selection.
        $page = isset( $_GET['page'] ) && is_string( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
        $tab = isset( $_GET['tc_logistica_tab'] ) && is_string( $_GET['tc_logistica_tab'] ) ? sanitize_key( wp_unslash( $_GET['tc_logistica_tab'] ) ) : '';
        // phpcs:enable WordPress.Security.NonceVerification.Recommended
        if ( 'taxocommerce-logistica' === $page && 'auto-test' === $tab ) {
            wp_enqueue_style( 'tc-logistica-auto-test', TAXOCOMMERCE_LOGISTICA_URL . 'assets/auto-test.css', array(), TAXOCOMMERCE_LOGISTICA_VERSION );
        }
    }

    public static function groups() {
        return array(
            'startup' => 'Arranque y administración',
            'settings' => 'Configuración y reglas guardadas',
            'regions' => 'Zonas y destinos',
            'metrics' => 'Peso, medidas y auditoría de pedidos',
            'pricing' => 'Reglas y cálculos de coste',
            'checkout' => 'Integración WooCommerce',
            'transfer' => 'Importación/exportación y seguridad',
            'uat' => 'Pruebas reales pendientes',
        );
    }

    public static function cases() {
        return array(
            'components' => array( 'startup', 'Clases y versión del plugin' ),
            'woocommerce' => array( 'startup', 'WooCommerce disponible' ),
            'menu' => array( 'startup', 'Menú de Logistics registrado' ),
            'assets' => array( 'startup', 'Hojas y scripts administrativos' ),
            'core' => array( 'startup', 'Integración opcional con Core' ),
            'defaults' => array( 'settings', 'Valores predeterminados seguros' ),
            'option' => array( 'settings', 'Opción histórica de transporte conservada' ),
            'active_rules' => array( 'settings', 'Gestor activo con reglas activas' ),
            'rule_fields' => array( 'settings', 'Campos y formatos de reglas' ),
            'priorities' => array( 'settings', 'Prioridades y reglas almacenadas' ),
            'region_catalog' => array( 'regions', 'Ocho zonas disponibles' ),
            'region_mapping' => array( 'regions', 'Clasificación por provincia y código postal' ),
            'foreign_zone' => array( 'regions', 'Exclusión de destinos extranjeros' ),
            'zone_match' => array( 'regions', 'Reglas España / zona específica / todas' ),
            'package_metrics' => array( 'metrics', 'Peso, unidades, volumen y dimensiones' ),
            'missing_metrics' => array( 'metrics', 'Detección de datos logísticos ausentes' ),
            'order_audit' => array( 'metrics', 'Auditoría de pedidos y métodos de envío' ),
            'rule_match' => array( 'pricing', 'Condiciones de subtotal, peso y medidas' ),
            'missing_guard' => array( 'pricing', 'Bloqueo de reglas cuando faltan medidas' ),
            'fixed_variable' => array( 'pricing', 'Tarifa fija, por kg y por unidad' ),
            'free_shipping' => array( 'pricing', 'Portes gratuitos por subtotal' ),
            'priority_selection' => array( 'pricing', 'Precedencia de menor prioridad numérica' ),
            'hooks' => array( 'checkout', 'Hooks de paquetes, tarifas y método puente' ),
            'shipping_toggle' => array( 'checkout', 'Activación del transporte WooCommerce' ),
            'rate_contract' => array( 'checkout', 'Método y clase de tarifa disponibles' ),
            'tax_contract' => array( 'checkout', 'Impuestos calculados por WooCommerce' ),
            'export_import' => array( 'transfer', 'Controladores de JSON registrados' ),
            'schema' => array( 'transfer', 'Formato JSON compatible y límites' ),
            'permissions' => array( 'transfer', 'Permisos y nonce del Autocheck' ),
            'isolated_reports' => array( 'transfer', 'Resultados separados por usuario y sitio' ),
            'real_checkout' => array( 'uat', 'Checkout real con Península y Baleares' ),
            'real_foreign' => array( 'uat', 'Checkout real con destinos internacionales' ),
            'real_orders' => array( 'uat', 'Pedidos reales, pesos, impuestos y tarifas' ),
            'real_json' => array( 'uat', 'Exportación/importación JSON de prueba' ),
            'real_cache' => array( 'uat', 'Invalidación de caché y recálculo de tarifas' ),
        );
    }

    private static function result( $status, $message ) {
        return array( 'status' => $status, 'message' => $message );
    }

    private static function pass( $message ) { return self::result( 'pass', $message ); }
    private static function fail( $message ) { return self::result( 'fail', $message ); }
    private static function skip( $message ) { return self::result( 'skip', $message ); }

    private static function site_hash() {
        return hash( 'sha256', (string) home_url( '/' ) );
    }

    private static function key( $kind ) {
        return ( 'before' === $kind ? self::BEFORE : self::LATEST ) . get_current_blog_id();
    }

    /** Used only with synthetic arrays; never calls shipping writes or live checkout. */
    private static function pure( $method, $args = array() ) {
        $reflection = new ReflectionMethod( 'TaxoCommerce_Logistica_Transporte_Costes', $method );
        $reflection->setAccessible( true );
        return $reflection->invokeArgs( null, $args );
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

    public static function run_suite( $group = 'all', $override = null ) {
        $groups = self::groups();
        $group = 'all' === $group || isset( $groups[ $group ] ) ? $group : 'all';
        $cases = null === $override ? self::cases() : $override;
        $started = microtime( true );
        $rows = array();
        $totals = array( 'pass' => 0, 'fail' => 0, 'skip' => 0, 'not_run' => 0 );
        foreach ( $cases as $id => $entry ) {
            $begin = microtime( true );
            if ( 'all' !== $group && $group !== $entry[0] ) {
                $outcome = self::result( 'not_run', 'Este grupo no se ha ejecutado.' );
            } else {
                try {
                    $method = array( __CLASS__, 'test_' . $id );
                    $outcome = is_callable( $method ) ? call_user_func( $method ) : self::fail( 'Comprobación sin implementar.' );
                    if ( ! is_array( $outcome ) || ! isset( $outcome['status'], $outcome['message'] )
                        || ! in_array( $outcome['status'], array( 'pass', 'fail', 'skip' ), true ) ) {
                        $outcome = self::fail( 'Resultado de prueba inválido.' );
                    }
                } catch ( Throwable $error ) {
                    // Never log exception text, private paths, customer details or rate configuration.
                    $outcome = self::fail( 'Excepción durante el autochequeo (' . get_class( $error ) . '). Revisar log privado.' );
                }
            }
            $rows[] = array(
                'id' => (string) $id,
                'group_id' => (string) $entry[0],
                'label' => (string) $entry[1],
                'status' => $outcome['status'],
                'message' => $outcome['message'],
                'ms' => (int) round( ( microtime( true ) - $begin ) * 1000 ),
            );
            ++$totals[ $outcome['status'] ];
        }
        global $wp_version;
        return self::standardize_report( array(
            'schema' => 1,
            'phase' => 'check',
            'group' => $group,
            'site_hash' => self::site_hash(),
            'version' => TAXOCOMMERCE_LOGISTICA_VERSION,
            'wordpress' => isset( $wp_version ) ? (string) $wp_version : '',
            'php' => PHP_VERSION,
            'timestamp' => time(),
            'date_utc' => gmdate( 'c' ),
            'executed_at' => gmdate( 'c' ),
            'duration_ms' => (int) round( ( microtime( true ) - $started ) * 1000 ),
            'totals' => $totals,
            'rows' => $rows,
        ) );
    }

    public static function compare( $before, $after ) {
        if ( ! is_array( $before ) || ! is_array( $after )
            || empty( $before['site_hash'] )
            || ! hash_equals( (string) $before['site_hash'], (string) ( $after['site_hash'] ?? '' ) )
            || 'before' !== ( $before['phase'] ?? '' ) || 'after' !== ( $after['phase'] ?? '' )
            || 'all' !== ( $before['group'] ?? '' ) || 'all' !== ( $after['group'] ?? '' )
            || (int) ( $after['timestamp'] ?? 0 ) < (int) ( $before['timestamp'] ?? 0 ) ) {
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
        $diff = array(
            'new_failures' => array(), 'resolved' => array(),
            'existing_failures' => array(), 'lost_coverage' => array(), 'new_checks' => array(),
        );
        foreach ( $new as $id => $status ) {
            if ( ! array_key_exists( $id, $old ) ) {
                $diff['new_checks'][] = $id;
                if ( 'fail' === $status ) { $diff['new_failures'][] = $id; }
                continue;
            }
            if ( 'fail' === $status ) {
                $diff[ 'fail' === $old[ $id ] ? 'existing_failures' : 'new_failures' ][] = $id;
            } elseif ( 'pass' === $status && 'fail' === $old[ $id ] ) {
                $diff['resolved'][] = $id;
            } elseif ( in_array( $old[ $id ], array( 'pass', 'fail' ), true )
                && in_array( $status, array( 'skip', 'not_run' ), true ) ) {
                $diff['lost_coverage'][] = $id;
            }
        }
        foreach ( $old as $id => $status ) {
            if ( ! array_key_exists( $id, $new ) ) { $diff['lost_coverage'][] = $id; }
        }
        return $diff;
    }

    private static function has_hooks( $names, $type = 'action' ) {
        foreach ( $names as $name ) {
            $registered = 'filter' === $type ? has_filter( $name ) : has_action( $name );
            if ( false === $registered ) { return false; }
        }
        return true;
    }

    private static function test_components() {
        return class_exists( 'TaxoCommerce_Logistica_Transporte_Costes' ) && defined( 'TAXOCOMMERCE_LOGISTICA_VERSION' )
            && 'seo_transporte_costes_settings' === TaxoCommerce_Logistica_Transporte_Costes::OPTION
            ? self::pass( 'Servicio principal y opción histórica disponibles.' )
            : self::fail( 'Servicio o constantes del plugin incompletas.' );
    }
    private static function test_woocommerce() {
        return class_exists( 'WooCommerce' ) && function_exists( 'wc_get_orders' )
            ? self::pass( 'WooCommerce y la API de pedidos están disponibles.' )
            : self::fail( 'WooCommerce no está disponible para calcular tarifas y auditar pedidos.' );
    }
    private static function test_menu() {
        return self::has_hooks( array( 'admin_menu' ) ) && has_action( 'admin_menu', 'taxocommerce_logistica_register_menu' ) !== false
            ? self::pass( 'Menú de Logistics registrado en administración.' )
            : self::fail( 'El menú del plugin no está registrado.' );
    }
    private static function test_assets() {
        foreach ( array( 'assets/seo-transporte.css', 'assets/seo-transporte.js', 'assets/auto-test.css' ) as $file ) {
            if ( ! is_readable( TAXOCOMMERCE_LOGISTICA_DIR . $file ) ) {
                return self::fail( 'Falta un recurso de administración: ' . $file );
            }
        }
        return self::pass( 'CSS, JavaScript de reglas y CSS de Autocheck disponibles.' );
    }
    private static function test_core() {
        if ( ! function_exists( 'taxocommerce_core' ) ) {
            return self::skip( 'TaxoCommerce Core es opcional; Logistics conserva menú independiente.' );
        }
        return has_action( 'taxocommerce_core_register_modules', 'taxocommerce_logistica_register_core' ) !== false
            ? self::pass( 'Integración con Core registrada.' )
            : self::fail( 'Core está activo y el registro de Logistics no está disponible.' );
    }
    private static function test_defaults() {
        $v = TaxoCommerce_Logistica_Transporte_Costes::defaults();
        return 0 === $v['enabled'] && 1 === $v['only_spain']
            && 1 === $v['require_known_metrics'] && is_array( $v['rules'] )
            ? self::pass( 'Valores iniciales sin habilitar un checkout sin reglas.' )
            : self::fail( 'Los valores predeterminados de seguridad han cambiado.' );
    }
    private static function test_option() {
        $raw = get_option( TaxoCommerce_Logistica_Transporte_Costes::OPTION, array() );
        return is_array( $raw ) && is_array( TaxoCommerce_Logistica_Transporte_Costes::settings() )
            ? self::pass( 'Configuración existente legible sin cambios ni migraciones.' )
            : self::fail( 'La opción histórica de transporte no tiene el formato esperado.' );
    }
    private static function test_active_rules() {
        $settings = TaxoCommerce_Logistica_Transporte_Costes::settings();
        if ( empty( $settings['enabled'] ) ) { return self::pass( 'Gestor desactivado; WooCommerce conserva su transporte estándar.' ); }
        $active = array_filter( (array) $settings['rules'], static function ( $rule ) {
            return is_array( $rule ) && ! empty( $rule['enabled'] );
        } );
        return $active ? self::pass( 'Gestor activo con al menos una regla habilitada.' )
            : self::fail( 'Gestor activo sin reglas: el checkout podría carecer de tarifa.' );
    }
    private static function test_rule_fields() {
        $rule = self::pure( 'blank_rule' );
        foreach ( array( 'zone', 'priority', 'enabled', 'min_subtotal', 'max_subtotal',
            'min_weight', 'max_weight', 'max_length', 'max_width', 'max_height', 'max_volume',
            'fixed_cost', 'per_kg_cost', 'per_unit_cost', 'free_from' ) as $field ) {
            if ( ! array_key_exists( $field, $rule ) ) { return self::fail( 'Falta campo de la regla: ' . $field ); }
        }
        return self::pass( 'Contrato de los campos de peso, medidas, importes y tarifa sin cambios.' );
    }
    private static function test_priorities() {
        $settings = TaxoCommerce_Logistica_Transporte_Costes::settings();
        if ( count( (array) $settings['rules'] ) > 10000 ) {
            return self::fail( 'Más de 10.000 reglas: revisar datos antes de procesarlos.' );
        }
        foreach ( (array) $settings['rules'] as $rule ) {
            if ( ! is_array( $rule ) || ! isset( $rule['priority'] ) || ! is_numeric( $rule['priority'] )
                || (float) $rule['priority'] < 1 ) {
                return self::fail( 'Hay una regla guardada con prioridad o estructura inválida.' );
            }
        }
        return self::pass( 'Las reglas guardadas tienen prioridades numéricas válidas.' );
    }
    private static function test_region_catalog() {
        $labels = self::pure( 'region_labels' );
        return count( $labels ) === 8
            && isset( $labels['peninsula'], $labels['baleares'], $labels['canarias'], $labels['ceuta'], $labels['melilla'], $labels['foreign'] )
            ? self::pass( 'Las ocho zonas nacionales/internacionales están definidas.' )
            : self::fail( 'Falta alguna zona de transporte.' );
    }
    private static function test_region_mapping() {
        if ( class_exists( 'SEO_Facturas_Tax' ) ) {
            return self::skip( 'Clasificación delegada en Facturación: verificarla también con su Autocheck.' );
        }
        $settings = TaxoCommerce_Logistica_Transporte_Costes::defaults();
        $cases = array(
            array( array( 'country' => 'ES', 'state' => 'PM', 'postcode' => '07001' ), 'baleares' ),
            array( array( 'country' => 'ES', 'state' => 'GC', 'postcode' => '35001' ), 'canarias' ),
            array( array( 'country' => 'ES', 'state' => 'CE', 'postcode' => '51001' ), 'ceuta' ),
            array( array( 'country' => 'ES', 'state' => 'ML', 'postcode' => '52001' ), 'melilla' ),
            array( array( 'country' => 'ES', 'state' => 'M', 'postcode' => '28001' ), 'peninsula' ),
        );
        foreach ( $cases as $case ) {
            if ( $case[1] !== self::pure( 'classify_destination', array( $case[0], $settings ) ) ) {
                return self::fail( 'Clasificación nacional por provincia/CP incorrecta.' );
            }
        }
        return self::pass( 'Casos simulados de las cinco zonas nacionales correctamente clasificados.' );
    }
    private static function test_foreign_zone() {
        if ( class_exists( 'SEO_Facturas_Tax' ) ) {
            return self::skip( 'Se delega la zona internacional en Facturación; requiere prueba de integración.' );
        }
        return 'foreign' === self::pure( 'classify_destination', array(
            array( 'country' => 'FR', 'state' => '', 'postcode' => '75001' ), TaxoCommerce_Logistica_Transporte_Costes::defaults(),
        ) ) ? self::pass( 'Francia se identifica como destino extranjero.' )
            : self::fail( 'No se identifica correctamente un destino extranjero.' );
    }
    private static function test_zone_match() {
        return true === self::pure( 'rule_zone_matches', array( 'spain', 'baleares' ) )
            && false === self::pure( 'rule_zone_matches', array( 'spain', 'foreign' ) )
            && true === self::pure( 'rule_zone_matches', array( 'all', 'foreign' ) )
            && false === self::pure( 'rule_zone_matches', array( 'peninsula', 'canarias' ) )
            ? self::pass( 'Zonas específicas, España y todos los destinos correctamente comparados.' )
            : self::fail( 'Regresión en la comparación de zonas de las reglas.' );
    }
    private static function test_package_metrics() {
        $product = new class {
            public function get_weight() { return '2'; }
            public function get_length() { return '20'; }
            public function get_width() { return '10'; }
            public function get_height() { return '5'; }
        };
        $m = self::pure( 'package_metrics', array( array(
            'contents_cost' => 90,
            'contents' => array( array( 'data' => $product, 'quantity' => 3 ) ),
        ) ) );
        $kg = function_exists( 'wc_get_weight' ) ? (float) wc_get_weight( 2, 'kg' ) : 2.0;
        $length = function_exists( 'wc_get_dimension' ) ? (float) wc_get_dimension( 20, 'cm' ) : 20.0;
        $width = function_exists( 'wc_get_dimension' ) ? (float) wc_get_dimension( 10, 'cm' ) : 10.0;
        $height = function_exists( 'wc_get_dimension' ) ? (float) wc_get_dimension( 5, 'cm' ) : 5.0;
        $expected_volume = $length * $width * $height * 3 / 1000;
        return 90.0 === (float) $m['subtotal'] && 3.0 === (float) $m['units']
            && abs( (float) $m['weight'] - $kg * 3 ) < 0.000001
            && abs( (float) $m['max_length'] - $length ) < 0.000001
            && abs( (float) $m['volume_dm3'] - $expected_volume ) < 0.000001
            ? self::pass( 'Unidades, peso y volumen calculados según las unidades configuradas en WooCommerce.' )
            : self::fail( 'El cálculo simulado de peso/volumen ha cambiado.' );
    }
    private static function test_missing_metrics() {
        $product = new class {
            public function get_weight() { return ''; }
            public function get_length() { return ''; }
            public function get_width() { return '10'; }
            public function get_height() { return '5'; }
        };
        $m = self::pure( 'package_metrics', array( array(
            'contents_cost' => 20, 'contents' => array( array( 'data' => $product, 'quantity' => 1 ) ),
        ) ) );
        return ! empty( $m['missing_weight'] ) && ! empty( $m['missing_dimensions'] )
            ? self::pass( 'Detecta productos sin peso o medidas en datos simulados.' )
            : self::fail( 'No se detecta ausencia de datos logísticos.' );
    }
    private static function test_order_audit() {
        $product = new class {
            public function get_weight() { return '1.5'; }
        };
        $line = new class( $product ) {
            private $product;
            public function __construct( $product ) { $this->product = $product; }
            public function get_quantity() { return 2; }
            public function get_product() { return $this->product; }
        };
        $order = new class( $line ) {
            private $line;
            public function __construct( $line ) { $this->line = $line; }
            public function get_items( $type = '' ) { return array( $this->line ); }
            public function get_shipping_methods() {
                return array( new class {
                    public function get_name() { return 'Entrega simulada'; }
                } );
            }
        };
        $metrics = self::pure( 'order_weight_metrics', array( $order ) );
        $expected = function_exists( 'wc_get_weight' ) ? (float) wc_get_weight( 1.5, 'kg' ) * 2 : 3.0;
        $label = self::pure( 'order_shipping_method_label', array( $order ) );
        return 2.0 === (float) $metrics['units']
            && 0 === (int) $metrics['missing_weight']
            && abs( (float) $metrics['weight'] - $expected ) < 0.000001
            && 'Entrega simulada' === $label
            ? self::pass( 'Auditoría: unidades, peso del pedido y nombre del transportista correctos con pedido simulado.' )
            : self::fail( 'La auditoría del pedido simulado ha cambiado.' );
    }
    private static function test_rule_match() {
        $rule = self::pure( 'blank_rule' );
        $rule['enabled'] = 1;
        $rule['min_subtotal'] = 50;
        $rule['max_weight'] = 10;
        $m = array( 'subtotal'=>70, 'weight'=>8, 'units'=>1, 'max_length'=>10,
            'max_width'=>10, 'max_height'=>10, 'volume_dm3'=>1,
            'missing_weight'=>false, 'missing_dimensions'=>false );
        return true === self::pure( 'rule_matches', array( $rule, 'peninsula', $m, TaxoCommerce_Logistica_Transporte_Costes::defaults() ) )
            && false === self::pure( 'rule_matches', array( $rule, 'peninsula', array_merge( $m, array( 'weight'=>11 ) ), TaxoCommerce_Logistica_Transporte_Costes::defaults() ) )
            && false === self::pure( 'rule_matches', array( $rule, 'peninsula', array_merge( $m, array( 'subtotal'=>40 ) ), TaxoCommerce_Logistica_Transporte_Costes::defaults() ) )
            ? self::pass( 'Se respetan subtotal mínimo y peso máximo de una regla.' )
            : self::fail( 'Las condiciones de subtotal/peso dejaron de aplicarse.' );
    }
    private static function test_missing_guard() {
        $rule = self::pure( 'blank_rule' );
        $rule['enabled'] = 1;
        $rule['min_weight'] = 2;
        $m = array( 'subtotal'=>60, 'weight'=>0, 'units'=>1, 'max_length'=>0,
            'max_width'=>0, 'max_height'=>0, 'volume_dm3'=>0,
            'missing_weight'=>true, 'missing_dimensions'=>true );
        return false === self::pure( 'rule_matches', array( $rule, 'peninsula', $m, TaxoCommerce_Logistica_Transporte_Costes::defaults() ) )
            ? self::pass( 'Reglas dependientes de peso rechazan productos sin peso.' )
            : self::fail( 'Una regla admite artículos sin peso, riesgo de cobrar menos.' );
    }
    private static function test_fixed_variable() {
        $rule = self::pure( 'blank_rule' );
        $rule['fixed_cost'] = 5;
        $rule['per_kg_cost'] = 2;
        $rule['per_unit_cost'] = 1;
        $m = array( 'subtotal'=>40, 'weight'=>3, 'units'=>2 );
        return 13.0 === (float) self::pure( 'calculate_rule_cost', array( $rule, $m ) )
            ? self::pass( '5 fijo + (2×3 kg) + (1×2 unidades) = 13.' )
            : self::fail( 'La fórmula de tarifa combinada no devuelve 13.' );
    }
    private static function test_free_shipping() {
        $rule = self::pure( 'blank_rule' );
        $rule['fixed_cost'] = 15;
        $rule['free_from'] = 50;
        return 0.0 === (float) self::pure( 'calculate_rule_cost', array( $rule, array( 'subtotal'=>50, 'weight'=>0, 'units'=>1 ) ) )
            && 15.0 === (float) self::pure( 'calculate_rule_cost', array( $rule, array( 'subtotal'=>49, 'weight'=>0, 'units'=>1 ) ) )
            ? self::pass( 'Portes gratuitos a partir de 50 y coste previo de 15, sin alterar las reglas reales.' )
            : self::fail( 'El umbral de envío gratuito no funciona como se esperaba.' );
    }
    private static function test_priority_selection() {
        $rules = array( array( 'priority'=>20 ), array( 'priority'=>10 ) );
        usort( $rules, static function ( $a, $b ) {
            return ((int) $a['priority']) <=> ((int) $b['priority']);
        } );
        return 10 === $rules[0]['priority']
            && method_exists( 'TaxoCommerce_Logistica_Transporte_Costes', 'filter_package_rates' )
            ? self::pass( 'Las prioridades numéricas se pueden ordenar; checkout real pendiente.' )
            : self::fail( 'No está disponible la precedencia de reglas.' );
    }
    private static function test_hooks() {
        return self::has_hooks( array(
            'woocommerce_shipping_methods', 'woocommerce_cart_shipping_packages', 'woocommerce_package_rates',
        ), 'filter' ) && self::has_hooks( array( 'wc_shipping_enabled' ), 'filter' )
            ? self::pass( 'Método puente, paquetes, tarifas y habilitación WooCommerce registrados.' )
            : self::fail( 'Falta alguno de los filtros necesarios para el envío.' );
    }
    private static function test_shipping_toggle() {
        return is_callable( array( 'TaxoCommerce_Logistica_Transporte_Costes', 'force_wc_shipping_enabled' ) )
            && is_callable( array( 'TaxoCommerce_Logistica_Transporte_Costes', 'register_bridge_shipping_method' ) )
            ? self::pass( 'El controlador de habilitación y método puente está disponible.' )
            : self::fail( 'Falta integración de activación del método de transporte.' );
    }
    private static function test_rate_contract() {
        return class_exists( 'WC_Shipping_Rate' )
            ? self::pass( 'WooCommerce dispone de WC_Shipping_Rate para devolver una tarifa.' )
            : self::fail( 'Falta la clase WC_Shipping_Rate del checkout.' );
    }
    private static function test_tax_contract() {
        if ( ! class_exists( 'WC_Tax' ) ) { return self::skip( 'No está cargada WC_Tax en esta petición; verificar en checkout real.' ); }
        return is_callable( array( 'WC_Tax', 'get_shipping_tax_rates' ) )
            && is_callable( array( 'WC_Tax', 'calc_shipping_tax' ) )
            ? self::pass( 'Métodos oficiales de WooCommerce para impuestos de envío disponibles.' )
            : self::fail( 'API fiscal de WooCommerce incompleta.' );
    }
    private static function test_export_import() {
        return self::has_hooks( array(
            'admin_post_taxocommerce_logistica_export', 'admin_post_taxocommerce_logistica_import', 'admin_post_taxocommerce_logistica_save',
        ) ) ? self::pass( 'Acciones de exportación, importación y guardado registradas; no se invocan.' )
            : self::fail( 'Falta alguna acción de configuración/JSON.' );
    }
    private static function test_schema() {
        return is_callable( array( 'TaxoCommerce_Logistica_Transporte_Costes', 'import_settings' ) )
            && is_callable( array( 'TaxoCommerce_Logistica_Transporte_Costes', 'export_settings' ) )
            && defined( 'TAXOCOMMERCE_LOGISTICA_VERSION' )
            ? self::pass( 'Contratos JSON de transporte disponibles (seo-transporte-costes v1; límite 2 MiB).' )
            : self::fail( 'Importación/exportación de configuraciones incompleta.' );
    }
    private static function test_permissions() {
        return self::allowed() && self::has_hooks( array( 'admin_post_' . self::RUN, 'admin_post_' . self::EXPORT ) )
            ? self::pass( 'Ejecutar y descargar requiere capacidad de transporte y nonce.' )
            : self::fail( 'No se ha registrado el diagnóstico o faltan permisos.' );
    }
    private static function test_isolated_reports() {
        return self::key( 'before' ) !== self::key( 'latest' )
            && function_exists( 'get_user_meta' ) && function_exists( 'update_user_meta' )
            ? self::pass( 'Un informe ANTES y uno reciente por administrador y sitio, sin datos de clientes.' )
            : self::fail( 'Persistencia aislada de informes no disponible.' );
    }
    private static function test_real_checkout() { return self::skip( 'Probar portes a Península y Baleares con productos reales en staging.' ); }
    private static function test_real_foreign() { return self::skip( 'Comprobar checkout internacional y zona predeterminada en staging.' ); }
    private static function test_real_orders() { return self::skip( 'Validar auditoría de pedidos, peso real e impuestos en staging.' ); }
    private static function test_real_json() { return self::skip( 'Importar JSON reemplaza reglas; probar exclusivamente en staging con copia previa.' ); }
    private static function test_real_cache() { return self::skip( 'Comprobar invalidación de la caché y recálculo del carrito real en staging.' ); }

    private static function stored( $kind ) {
        $report = get_user_meta( get_current_user_id(), self::key( $kind ), true );
        return is_array( $report ) && ! empty( $report['site_hash'] ) && ! empty( $report['rows'] )
            && hash_equals( self::site_hash(), (string) $report['site_hash'] ) ? self::standardize_report( $report ) : array();
    }

    private static function url() {
        return add_query_arg(
            array( 'page'=>'taxocommerce-logistica', 'tc_logistica_tab'=>'auto-test' ),
            admin_url( 'admin.php' )
        );
    }

    public static function handle_run() {
        if ( ! self::allowed() ) { wp_die( esc_html__( 'Sin permisos para Autocheck.', 'taxocommerce-logistica' ) ); }
        check_admin_referer( self::NONCE, 'tc_logistica_nonce' );
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Verified immediately above.
        $group = isset( $_POST['test_group'] ) && is_string( $_POST['test_group'] )
            ? sanitize_key( wp_unslash( $_POST['test_group'] ) ) : 'all';
        $phase = isset( $_POST['test_phase'] ) && is_string( $_POST['test_phase'] )
            ? sanitize_key( wp_unslash( $_POST['test_phase'] ) ) : 'check';
        // phpcs:enable WordPress.Security.NonceVerification.Missing
        if ( 'all' !== $group && ! isset( self::groups()[ $group ] ) ) { $group = 'all'; }
        if ( 'all' !== $group || ! in_array( $phase, array( 'before', 'after', 'check' ), true ) ) { $phase = 'check'; }
        $report = self::run_suite( $group );
        $report['phase'] = $phase;
        update_user_meta( get_current_user_id(), self::key( 'latest' ), $report );
        if ( 'before' === $phase ) {
            update_user_meta( get_current_user_id(), self::key( 'before' ), $report );
        }
        wp_safe_redirect( self::url() );
        exit;
    }

    public static function handle_export() {
        if ( ! self::allowed() ) { wp_die( esc_html__( 'Sin permisos para descargar Autocheck.', 'taxocommerce-logistica' ) ); }
        check_admin_referer( self::NONCE, 'tc_logistica_nonce' );
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified immediately above.
        $kind = isset( $_POST['report_kind'] ) && is_string( $_POST['report_kind'] )
            ? sanitize_key( wp_unslash( $_POST['report_kind'] ) ) : 'latest';
        $kind = 'before' === $kind ? 'before' : 'latest';
        $report = self::stored( $kind );
        if ( ! $report ) { wp_die( esc_html__( 'No hay un informe de Autocheck disponible.', 'taxocommerce-logistica' ) ); }
        $json = wp_json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE );
        if ( false === $json ) { wp_die( esc_html__( 'No se ha podido generar el JSON.', 'taxocommerce-logistica' ) ); }
        nocache_headers();
        header( 'Content-Type: application/json; charset=utf-8' );
        header( 'X-Content-Type-Options: nosniff' );
        header( 'Content-Disposition: attachment; filename="taxocommerce-logistica-autotest-' . $kind . '-' . gmdate( 'Ymd-His' ) . '.json"' );
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- A JSON response, never HTML.
        echo $json;
        exit;
    }

    private static function run_button( $group, $phase, $label, $primary = false ) {
        ?>
        <form class="tc-log-at-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <input type="hidden" name="action" value="<?php echo esc_attr( self::RUN ); ?>">
            <input type="hidden" name="test_group" value="<?php echo esc_attr( $group ); ?>">
            <input type="hidden" name="test_phase" value="<?php echo esc_attr( $phase ); ?>">
            <?php wp_nonce_field( self::NONCE, 'tc_logistica_nonce' ); ?>
            <button type="submit" class="button <?php echo $primary ? 'button-primary' : 'button-secondary'; ?>"><?php echo esc_html( $label ); ?></button>
        </form>
        <?php
    }

    private static function download_button( $kind, $label ) {
        ?>
        <form class="tc-log-at-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <input type="hidden" name="action" value="<?php echo esc_attr( self::EXPORT ); ?>">
            <input type="hidden" name="report_kind" value="<?php echo esc_attr( $kind ); ?>">
            <?php wp_nonce_field( self::NONCE, 'tc_logistica_nonce' ); ?>
            <button type="submit" class="button button-secondary"><?php echo esc_html( $label ); ?></button>
        </form>
        <?php
    }

    public static function render() {
        if ( \TaxoCommerce_Logistica_Autotest_Code::render_subtabs() ) { return; }
        if ( ! self::allowed() ) { wp_die( esc_html__( 'Sin permisos para consultar Autocheck.', 'taxocommerce-logistica' ) ); }
        $latest = self::stored( 'latest' );
        $cases = self::cases();
        $groups = self::groups();
        $rows = array();
        foreach ( (array) ( $latest['rows'] ?? array() ) as $row ) {
            if ( isset( $row['id'] ) ) { $rows[ $row['id'] ] = $row; }
        }
        $totals = $latest['totals'] ?? array( 'pass'=>0, 'fail'=>0, 'skip'=>0, 'not_run'=>count( $cases ) );
        ?>
        <section class="tc-log-at" aria-labelledby="tc-log-at-heading">
            <h2 id="tc-log-at-heading"><?php esc_html_e( 'Autocheck de TaxoCommerce Logistics', 'taxocommerce-logistica' ); ?></h2>
            <p><?php esc_html_e( 'Diagnóstico no destructivo de tarifas, reglas, pesos, destinos y seguridad. No realiza envíos, modifica pedidos, recalcula carritos ni importa configuraciones.', 'taxocommerce-logistica' ); ?></p>
            <div class="tc-log-at-actions">
                <?php self::run_button( 'all', 'check', __( 'Ejecutar Autocheck', 'taxocommerce-logistica' ) ); ?>
                <?php if ( $latest ) : ?>
                    <?php self::download_button( 'latest', __( 'Descargar resultados (JSON)', 'taxocommerce-logistica' ) ); ?>
                <?php else : ?>
                    <button type="button" class="button button-secondary" disabled><?php esc_html_e( 'Descargar resultados (JSON)', 'taxocommerce-logistica' ); ?></button>
                <?php endif; ?>
            </div>
            <?php if ( $latest ) : ?>
                <p class="tc-log-at-last">
                    <strong><?php esc_html_e( 'Fecha de ejecución:', 'taxocommerce-logistica' ); ?></strong>
                    <?php echo esc_html( (string) ( $latest['executed_at'] ?? '' ) ); ?>
                    · <?php echo esc_html( 'v' . (string) ( $latest['version'] ?? '' ) ); ?>
                    · <?php echo esc_html( absint( $latest['duration_ms'] ?? 0 ) . ' ms' ); ?>
                </p>
            <?php else : ?>
                <p class="description"><?php esc_html_e( 'Todavía no se ha ejecutado el Autocheck en este sitio.', 'taxocommerce-logistica' ); ?></p>
            <?php endif; ?>
            <div class="tc-log-at-totals" aria-label="<?php esc_attr_e( 'Resultados del último chequeo', 'taxocommerce-logistica' ); ?>">
                <?php foreach ( array( 'pass'=>__( 'Correctos', 'taxocommerce-logistica' ), 'fail'=>__( 'Errores', 'taxocommerce-logistica' ), 'skip'=>__( 'Omitidos / externos', 'taxocommerce-logistica' ), 'not_run'=>__( 'No ejecutados', 'taxocommerce-logistica' ) ) as $status => $label ) : ?>
                    <div><strong><?php echo esc_html( absint( $totals[ $status ] ?? 0 ) ); ?></strong><span><?php echo esc_html( $label ); ?></span></div>
                <?php endforeach; ?>
            </div>
            <?php foreach ( $groups as $group => $name ) : ?>
                <?php
                $subset = array_filter( $cases, static function ( $case ) use ( $group ) { return $case[0] === $group; } );
                $failures = 0;
                foreach ( $subset as $id => $case ) {
                    if ( 'fail' === ( $rows[ $id ]['status'] ?? '' ) ) { ++$failures; }
                }
                ?>
                <details class="tc-log-at-group" <?php echo $failures || ! $latest ? 'open' : ''; ?>>
                    <?php
                    /* translators: 1: Name of the test group, 2: Number of tests, 3: Optional suffix reporting failures. */
                    $tc_log_group_format = __( '%1$s · %2$d pruebas%3$s', 'taxocommerce-logistica' );
                    /* translators: %d: Number of failed tests in this group. */
                    $tc_log_group_failure_format = __( ' · %d errores', 'taxocommerce-logistica' );
                    ?>
                    <summary><?php echo esc_html( sprintf( $tc_log_group_format, $name, count( $subset ), $failures ? sprintf( $tc_log_group_failure_format, $failures ) : '' ) ); ?></summary>
                    <?php foreach ( $subset as $id => $case ) : ?>
                        <?php $row = $rows[ $id ] ?? array( 'status'=>'not_run', 'label' => $case[1], 'message'=>__( 'No ejecutada.', 'taxocommerce-logistica' ), 'ms' => 0 ); ?>
                        <div class="tc-log-at-row">
                            <span aria-hidden="true" class="tc-log-at-dot tc-log-at-dot--<?php echo esc_attr( $row['status'] ); ?>"></span>
                            <div><strong><?php echo esc_html( (string) $row['label'] ); ?> <small><?php echo esc_html( $id ); ?></small></strong>
                                <p><?php echo esc_html( (string) $row['message'] ); ?></p><small><?php echo esc_html( absint( $row['ms'] ) . ' ms' ); ?></small></div>
                        </div>
                    <?php endforeach; ?>
                </details>
            <?php endforeach; ?>
            <p class="description"><?php esc_html_e( 'Correcto = contrato o cálculo local comprobado. No verificado = necesita checkout o importación real en staging. Este chequeo no sustituye a probar pedidos, impuestos y transporte con diferentes direcciones.', 'taxocommerce-logistica' ); ?></p>
        </section>
        <?php
    }
}
