<?php
/**
 * Offline Logistics Auto Test functional regression.
 * Boots real plugin classes but intercepts all writes and external requests.
 * Run: php taxocommerce-logistica/tests/auto-test-smoke.php
 */
define( 'ABSPATH', __DIR__ );

$GLOBALS['hooks'] = array();
$GLOBALS['writes'] = 0;
function __( $text, $domain = '' ) { return $text; }
function plugin_dir_path( $file ) { return dirname( $file ) . '/'; }
function plugin_dir_url( $file ) { return 'https://example.test/wp-content/plugins/taxocommerce-logistica/'; }
function add_action( $hook, $callback, $priority = 10, $args = 1 ) { $GLOBALS['hooks']['action'][ $hook ][] = $callback; }
function add_filter( $hook, $callback, $priority = 10, $args = 1 ) { $GLOBALS['hooks']['filter'][ $hook ][] = $callback; }
function add_menu_page( $page_title, $menu_title, $capability, $menu_slug, $callback, $icon = '', $position = null ) {
    $GLOBALS['test_menu'][] = array( 'slug' => $menu_slug, 'title' => $menu_title, 'capability' => $capability, 'callback' => $callback );
    return 'toplevel_page_' . $menu_slug;
}
function add_submenu_page( $parent, $page_title, $menu_title, $capability, $menu_slug, $callback ) {
    $GLOBALS['test_submenu'][] = array( 'parent' => $parent, 'slug' => $menu_slug, 'title' => $menu_title, 'capability' => $capability, 'callback' => $callback );
    return 'taxocommerce-core_page_' . $menu_slug;
}
function taxocommerce_core() { return null; } // Simulates Core installed without loading its unrelated implementation.
function has_action( $hook, $callback = false ) {
    $all = $GLOBALS['hooks']['action'][ $hook ] ?? array();
    if ( false === $callback ) { return $all ? 10 : false; }
    return in_array( $callback, $all, true ) ? 10 : false;
}
function has_filter( $hook, $callback = false ) {
    $all = $GLOBALS['hooks']['filter'][ $hook ] ?? array();
    if ( false === $callback ) { return $all ? 10 : false; }
    return in_array( $callback, $all, true ) ? 10 : false;
}
function apply_filters( $hook, $value ) { return $value; }
function function_exists_wc() { return true; }
function wc_get_orders( $args = array() ) { return array(); }
function wc_get_weight( $weight, $unit ) { return (float) $weight; }
function wc_get_dimension( $value, $unit ) { return (float) $value; }
function wc_get_price_decimals() { return 2; }
function wp_parse_args( $args, $defaults ) { return array_merge( $defaults, (array) $args ); }
function get_option( $key, $default = false ) { return $default; }
function update_option( $key, $value, $autoload = null ) { ++$GLOBALS['writes']; throw new RuntimeException( 'Unexpected options write' ); }
function set_transient( $key, $value, $ttl = 0 ) { ++$GLOBALS['writes']; throw new RuntimeException( 'Unexpected transient write' ); }
function delete_transient( $key ) { ++$GLOBALS['writes']; throw new RuntimeException( 'Unexpected transient removal' ); }
function get_transient( $key ) { return false; }
function get_user_meta( $user, $key, $single = true ) { return false; }
function update_user_meta( $user, $key, $value ) { ++$GLOBALS['writes']; throw new RuntimeException( 'Unexpected usermeta write during tests' ); }
function get_current_user_id() { return 1; }
function get_current_blog_id() { return 2; }
function current_user_can( $capability ) { return 'manage_woocommerce' === $capability || 'manage_options' === $capability; }
function home_url( $path = '/' ) { return 'https://example.test' . $path; }
function admin_url( $path = '' ) { return 'https://example.test/wp-admin/' . $path; }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) ); }
function wp_unslash( $v ) { return $v; }
function add_query_arg( $args, $url ) { return $url . '?' . http_build_query( $args ); }
function esc_html__( $text, $domain = '' ) { return $text; }
function esc_html_e( $text, $domain = '' ) { echo $text; }
function esc_attr_e( $text, $domain = '' ) { echo $text; }
function esc_attr( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function esc_html( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $value ) { return esc_attr( $value ); }
function wp_nonce_field( $action, $name = '_wpnonce' ) { echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="test">'; }
function wp_die( $message ) { throw new RuntimeException( $message ); }
function wp_enqueue_style( $handle, $source, $deps, $version ) { $GLOBALS['style'][ $handle ] = $source; }
function wc_format_decimal( $value ) { return (string) $value; }
function wp_remote_get() { ++$GLOBALS['writes']; throw new RuntimeException( 'Unexpected network request' ); }
function wp_remote_post() { ++$GLOBALS['writes']; throw new RuntimeException( 'Unexpected network request' ); }
function did_action( $hook ) { return 0; }

class WooCommerce {}
class WC_Shipping_Rate {}
// Simulates the simultaneously active legacy SEO engine.
class SEO_Transporte_Costes {}

require dirname( __DIR__ ) . '/taxocommerce-logistica.php';

function verify_log( $test, $message ) {
    if ( ! $test ) {
        fwrite( STDERR, 'FAIL Logistics Auto Test: ' . $message . PHP_EOL );
        exit( 1 );
    }
}

$groups = TaxoCommerce_Logistica_Auto_Test::groups();
$cases = TaxoCommerce_Logistica_Auto_Test::cases();
taxocommerce_logistica_register_menu();
verify_log( empty( $GLOBALS['test_menu'] ), 'Core mode must not register an independent top-level menu' );
verify_log( 1 === count( $GLOBALS['test_submenu'] ?? array() ), 'Logistics registered once in TaxoCommerce Core family' );
verify_log( 'taxocommerce-core' === $GLOBALS['test_submenu'][0]['parent'], 'Core is the parent of Logistics' );
verify_log( 'taxocommerce-logistica' === $GLOBALS['test_submenu'][0]['slug'], 'Unique Logistics page slug retained' );
verify_log( 'Logistics' === $GLOBALS['test_submenu'][0]['title'], 'English family navigation label' );
verify_log( array( 'TaxoCommerce_Logistica_Transporte_Costes', 'render_page' ) === $GLOBALS['test_submenu'][0]['callback'], 'Core submenu calls the same Logistics screen' );
verify_log( 'taxocommerce-logistica' === TaxoCommerce_Logistica_Transporte_Costes::PAGE, 'Shipping, Auto Test and Manual use same independent route' );
verify_log( false === TaxoCommerce_Logistica_Transporte_Costes::force_wc_shipping_enabled( false ), 'Legacy engine remains authoritative for shipping enabled flag' );
$legacy_packages = array( array( 'destination' => array( 'country' => 'ES', 'postcode' => '28001' ) ) );
verify_log( $legacy_packages === TaxoCommerce_Logistica_Transporte_Costes::filter_shipping_packages_destination( $legacy_packages ), 'Legacy shipping destinations are not recalculated by standalone Logistics' );
$legacy_rates = array( 'seo-legacy' => 'unchanged' );
verify_log( $legacy_rates === TaxoCommerce_Logistica_Transporte_Costes::filter_package_rates( $legacy_rates, array() ), 'Legacy shipping rates are not overwritten by standalone Logistics' );

verify_log( count( $groups ) === 8, 'Eight groups present' );
verify_log( count( $cases ) === 35, 'Thirty-five tests present' );
foreach ( $cases as $id => $case ) {
    verify_log( isset( $groups[ $case[0] ] ), 'Valid group for ' . $id );
    $method = new ReflectionMethod( 'TaxoCommerce_Logistica_Auto_Test', 'test_' . $id );
    verify_log( $method->isStatic(), 'Check implemented: ' . $id );
}
$report = TaxoCommerce_Logistica_Auto_Test::run_suite();
verify_log( 0 === $report['totals']['fail'], 'All deterministic shipping calculations pass: ' . implode( ', ', array_map( static function ( $row ) { return 'fail' === $row['status'] ? $row['id'] : ''; }, $report['rows'] ) ) );
verify_log( $report['totals']['pass'] >= 27, 'Deterministic checks execute without fake success' );
verify_log( $report['totals']['skip'] >= 5, 'Real checkout/import tests are deliberately skipped' );
verify_log( 0 === $GLOBALS['writes'], 'Suite never writes configuration, orders or usermeta' );

$_GET['page'] = 'taxocommerce-logistica';
$_GET['tc_logistica_tab'] = 'auto-test';
TaxoCommerce_Logistica_Auto_Test::assets();
verify_log( isset( $GLOBALS['style']['tc-logistica-auto-test'] ), 'Auto Test CSS routed on page' );

$before = array( 'site_hash'=>'same', 'group'=>'all', 'phase'=>'before', 'timestamp'=>100,
    'rows'=>array(
        array( 'id'=>'cost', 'status'=>'pass' ),
        array( 'id'=>'rules', 'status'=>'fail' ),
        array( 'id'=>'metrics', 'status'=>'pass' ),
        array( 'id'=>'removed', 'status'=>'pass' ),
    ),
);
$after = array( 'site_hash'=>'same', 'group'=>'all', 'phase'=>'after', 'timestamp'=>200,
    'rows'=>array(
        array( 'id'=>'cost', 'status'=>'fail' ),
        array( 'id'=>'rules', 'status'=>'pass' ),
        array( 'id'=>'metrics', 'status'=>'skip' ),
        array( 'id'=>'added', 'status'=>'fail' ),
    ),
);
$diff = TaxoCommerce_Logistica_Auto_Test::compare( $before, $after );
verify_log( 2 === count( $diff['new_failures'] ), 'Regressions detected' );
verify_log( 1 === count( $diff['resolved'] ), 'Resolved issues detected' );
verify_log( 2 === count( $diff['lost_coverage'] ), 'Lost test coverage detected' );
verify_log( 1 === count( $diff['new_checks'] ), 'New checks tracked' );
$after['site_hash'] = 'other';
verify_log( null === TaxoCommerce_Logistica_Auto_Test::compare( $before, $after ), 'Cross-site comparison blocked' );
$after['site_hash'] = 'same';
$after['timestamp'] = 90;
verify_log( null === TaxoCommerce_Logistica_Auto_Test::compare( $before, $after ), 'Reverse chronology blocked' );
$after['timestamp'] = 200;
$after['phase'] = 'check';
verify_log( null === TaxoCommerce_Logistica_Auto_Test::compare( $before, $after ), 'Only AFTER can compare' );
$after['phase'] = 'after';
$before['group'] = 'pricing';
verify_log( null === TaxoCommerce_Logistica_Auto_Test::compare( $before, $after ), 'Partial baseline rejected' );

$view_source = file_get_contents( dirname( __DIR__ ) . '/includes/class-auto-test.php' );
verify_log( false !== strpos( $view_source, "self::run_button( 'all', 'check', __( 'Ejecutar Autocheck'" ), 'Run button' );
verify_log( false !== strpos( $view_source, "self::download_button( 'latest', __( 'Descargar resultados (JSON)'" ), 'JSON download button' );
verify_log( false === strpos( $view_source, "self::run_button( 'all', 'before'" ), 'No BEFORE control' );
verify_log( false === strpos( $view_source, "self::run_button( 'all', 'after'" ), 'No AFTER control' );
verify_log( false !== strpos( $view_source, 'TaxoCommerce_Logistica_Autotest_Code::render_subtabs' ), 'Code check subtab' );

require __DIR__ . '/i18n-catalog-smoke.php';
echo 'Logistics Auto Test smoke passed.' . PHP_EOL;

/* Shared family report contract: new and previously saved check records. */
$legacy_report = array(
    'version' => '0', 'executed_at' => '2026-11-14T12:00:00+00:00',
    'rows' => array( array(
        'id' => 'test_case', 'group' => 'startup', 'name' => 'Legacy case',
        'detail' => 'Legacy detail', 'status' => 'pass', 'ms' => 2,
    ) ),
);
$canonical_report = TaxoCommerce_Logistica_Auto_Test::standardize_report( $legacy_report );
verify_log( isset( $canonical_report['rows'][0]['group_id'], $canonical_report['rows'][0]['group_name'], $canonical_report['rows'][0]['label'], $canonical_report['rows'][0]['message'], $canonical_report['rows'][0]['ms'] ), 'Unified row fields' );
verify_log( 'Legacy case' === $canonical_report['rows'][0]['label'] && 'Legacy detail' === $canonical_report['rows'][0]['message'], 'Historical text retained' );
verify_log( 'Legacy case' === $canonical_report['rows'][0]['name'] && 'Legacy detail' === $canonical_report['rows'][0]['detail'], 'Legacy keys retained' );
verify_log( isset( $canonical_report['wordpress'], $canonical_report['php'], $canonical_report['date'], $canonical_report['executed_at'], $canonical_report['timestamp'] ), 'Unified report metadata' );
verify_log( false !== json_encode( $canonical_report ), 'Report JSON encodes' );
