<?php
/**
 * CLI regression contract for FAQs Auto Test.
 * No WordPress bootstrap, database changes, FAQ writes or filesystem writes.
 */
if ( 'cli' !== PHP_SAPI ) { exit( 1 ); }
define( 'ABSPATH', __DIR__ );
define( 'TAXOCOMMERCE_FAQS_VERSION', '0.1.20' );
define( 'HOUR_IN_SECONDS', 3600 );
function tc_faq_at_check( $ok, $name ) {
	if ( ! $ok ) { fwrite( STDERR, 'FAIL FAQ Auto Test: ' . $name . PHP_EOL ); exit( 1 ); }
}
function home_url( $path = '/' ) { return 'https://example.test' . $path; }
function get_current_blog_id() { return 2; }
function get_current_user_id() { return 37; }
function current_time( $format ) { return '2026-10-29 18:00:00'; }
function absint( $value ) { return abs( (int) $value ); }
function __( $s, $domain = '' ) { return $s; }
function esc_attr__( $s, $domain = '' ) { return htmlspecialchars( $s, ENT_QUOTES, 'UTF-8' ); }
function esc_html__( $s, $domain = '' ) { return htmlspecialchars( $s, ENT_QUOTES, 'UTF-8' ); }
function esc_attr_e( $s, $domain = '' ) { echo esc_attr__( $s, $domain ); }
function esc_html_e( $s, $domain = '' ) { echo esc_html__( $s, $domain ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $s ) { return esc_attr( $s ); }
function current_user_can( $s ) { return 'manage_options' === $s; }
function wp_die( $s ) { throw new RuntimeException( $s ); }
function get_user_meta( $id, $key, $single ) { return array(); }
function update_user_meta( $id, $key, $value ) { throw new RuntimeException( 'Auto Test attempted data write on read-only test' ); }
function admin_url( $s ) { return 'https://example.test/wp-admin/' . $s; }
function remove_query_arg( $key, $url = 'https://example.test/wp-admin/admin.php?page=taxocommerce-faqs' ) { return $url; }
function add_query_arg( $key, $value = null, $url = '' ) { return ( $url ?: admin_url( 'admin.php?page=taxocommerce-faqs' ) ) . '&' . rawurlencode( $key ) . '=' . rawurlencode( (string) $value ); }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_-]/', '', strtolower( (string) $value ) ); }
function wp_unslash( $value ) { return $value; }
function wp_nonce_field( $action, $name ) { echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="test">'; }
function disabled( $check, $current = true, $echo = true ) {
    $value = $check === $current ? ' disabled="disabled"' : '';
    if ( $echo ) { echo $value; }
    return $value;
}
require dirname( __DIR__ ) . '/includes/class-taxocommerce-autotest-code.php';
require dirname( __DIR__ ) . '/includes/class-taxocommerce-faqs-auto-test.php';

$groups = TaxoCommerce_FAQs_Auto_Test::groups();
$cases = TaxoCommerce_FAQs_Auto_Test::cases();
tc_faq_at_check( count( $groups ) === 6, 'groups not equivalent' );
tc_faq_at_check( count( $cases ) >= 25, 'insufficient coverage' );
foreach ( $cases as $id => $labels ) {
	tc_faq_at_check( isset( $groups[ $labels[0] ] ), 'unknown group for ' . $id );
}

$report = TaxoCommerce_FAQs_Auto_Test::run_suite( 'uat' );
tc_faq_at_check( $report['totals']['skip'] === 5, 'real operations must be marked manual' );
tc_faq_at_check( $report['totals']['not_run'] === count( $cases ) - 5, 'group scope' );
tc_faq_at_check( $report['totals']['pass'] === 0 && $report['totals']['fail'] === 0, 'unexpected work in UAT test' );
tc_faq_at_check( ! isset( $report['faq_id'] ) && ! isset( $report['question'] ) && ! isset( $report['answer'] ), 'private data in report' );

$before = array( 'site_hash' => 'site-A', 'phase' => 'before', 'group' => 'all', 'timestamp' => 100, 'rows' =>
	array( array( 'id' => 'a', 'status' => 'pass' ), array( 'id' => 'b', 'status' => 'fail' ), array( 'id' => 'c', 'status' => 'pass' ) ) );
$after = array( 'site_hash' => 'site-A', 'phase' => 'after', 'group' => 'all', 'timestamp' => 200, 'rows' =>
	array( array( 'id' => 'a', 'status' => 'fail' ), array( 'id' => 'b', 'status' => 'pass' ), array( 'id' => 'c', 'status' => 'skip' ), array( 'id' => 'd', 'status' => 'fail' ) ) );
$diff = TaxoCommerce_FAQs_Auto_Test::compare( $before, $after );
tc_faq_at_check( count( $diff['new_failures'] ) === 2, 'new failures' );
tc_faq_at_check( count( $diff['resolved'] ) === 1, 'resolved regressions' );
tc_faq_at_check( count( $diff['coverage_lost'] ) === 1, 'lost coverage' );
tc_faq_at_check( count( $diff['new_checks'] ) === 1, 'new cases' );
$after['site_hash'] = 'another-site';
tc_faq_at_check( null === TaxoCommerce_FAQs_Auto_Test::compare( $before, $after ), 'cross-site comparison must be rejected' );
$after['site_hash'] = 'site-A';
$after['timestamp'] = 10;
tc_faq_at_check( null === TaxoCommerce_FAQs_Auto_Test::compare( $before, $after ), 'reverse chronology rejected' );
$after['timestamp'] = 200;
$after['group'] = 'data';
tc_faq_at_check( null === TaxoCommerce_FAQs_Auto_Test::compare( $before, $after ), 'partial comparison rejected' );

$entry = file_get_contents( dirname( __DIR__ ) . '/taxocommerce-faqs.php' );
$admin = file_get_contents( dirname( __DIR__ ) . '/includes/class-taxocommerce-faqs.php' );
tc_faq_at_check( false !== strpos( $entry, 'class-taxocommerce-faqs-auto-test.php' ), 'entrypoint include' );
tc_faq_at_check( false !== strpos( $entry, 'TaxoCommerce_FAQs_Auto_Test::register()' ), 'actions registered' );
tc_faq_at_check( false !== strpos( $admin, 'TaxoCommerce_FAQs_Auto_Test::render()' ), 'UI routing' );
tc_faq_at_check( false !== strpos( $admin, "'auto-test' => __( 'Autocheck', 'taxocommerce-faqs' )" ), 'consistent Auto Test tab' );
tc_faq_at_check( false !== strpos( $admin, 'self::ensure_category()' ), 'legacy schema method preserved' );
tc_faq_at_check( false !== strpos( $admin, 'taxocommerce_faqs_save' ), 'FAQ CRUD preserved' );

ob_start();
TaxoCommerce_FAQs_Auto_Test::render();
$html = ob_get_clean();
tc_faq_at_check( false !== strpos( $html, 'Ejecutar Autocheck' ), 'single-run button' );
tc_faq_at_check( false !== strpos( $html, 'Descargar resultados (JSON)' ), 'latest-result JSON download' );
tc_faq_at_check( false === strpos( $html, 'Guardar estado ANTES' ), 'legacy baseline button removed from UI' );
tc_faq_at_check( false === strpos( $html, 'Comprobar DESPUÉS' ), 'legacy comparison button removed from UI' );
tc_faq_at_check( false === strpos( $html, 'Referencia ANTES' ), 'legacy comparison panel removed from UI' );
tc_faq_at_check( false !== strpos( $html, 'Functionality Check' ), 'functionality subtab preserved' );
tc_faq_at_check( false !== strpos( $html, 'Code Check' ), 'code subtab preserved' );
tc_faq_at_check( 2 === substr_count( $html, '<form ' ), 'single execution and single download forms' );
tc_faq_at_check( false !== strpos( $html, 'disabled="disabled"' ), 'JSON disabled until a report exists' );
$autotest = file_get_contents( dirname( __DIR__ ) . '/includes/class-taxocommerce-faqs-auto-test.php' );
tc_faq_at_check( false !== strpos( $autotest, "self::run_suite( 'all' )" ), 'server ignores obsolete partial/phase parameters' );
tc_faq_at_check( false !== strpos( $autotest, "self::meta_key( 'latest' )" ), 'latest report continues per-user per-site' );
tc_faq_at_check( substr_count( $html, '<details' ) === count( $groups ), 'groups rendered' );


// Exercise the real admin-notice branding callback without loading WordPress.
// The FAQ content route in Core must show exactly the same hero as the standalone page.
$GLOBALS['tc_faq_header_filters'] = array();
$GLOBALS['tc_faq_header_actions'] = array();
function add_filter( $hook, $callback, $priority = 10 ) {
    $GLOBALS['tc_faq_header_filters'][ $hook ][] = $callback;
}
function add_action( $hook, $callback, $priority = 10 ) {
    $GLOBALS['tc_faq_header_actions'][ $hook ][] = $callback;
}
function determine_locale() { return 'es_ES'; }
function wp_strip_all_tags( $text ) { return strip_tags( $text ); }
function esc_url_raw( $url, $allowed = array() ) { return $url; }
function get_file_data( $file, $fields, $context = '' ) {
    $source = file_get_contents( $file );
    $values = array();
    foreach ( $fields as $key => $field ) {
        $found = preg_match( '/^\\s*\\*\\s*' . preg_quote( $field, '/' ) . ':\\s*(.*?)\\s*$/m', $source, $match );
        $values[ $key ] = $found ? $match[1] : '';
    }
    return $values;
}
require dirname( __DIR__ ) . '/includes/admin-branding.php';
tc_faq_at_check( 1 === count( $GLOBALS['tc_faq_header_filters']['admin_body_class'] ?? array() ), 'branding filter registered' );
tc_faq_at_check( 1 === count( $GLOBALS['tc_faq_header_actions']['admin_notices'] ?? array() ), 'hero callback registered' );
$body_filter = $GLOBALS['tc_faq_header_filters']['admin_body_class'][0];
$notice = $GLOBALS['tc_faq_header_actions']['admin_notices'][0];
foreach ( array(
    array( 'page' => 'taxocommerce-faqs', 'tc_faq_tab' => 'faqs' ),
    array( 'page' => 'taxocommerce-core-content', 'tc_type' => 'faqs', 'tc_faq_tab' => 'faqs' ),
    array( 'page' => 'taxocommerce-core-content', 'tc_type' => 'faqs', 'tc_faq_tab' => 'auto-test' ),
    array( 'page' => 'taxocommerce-core-content', 'tc_type' => 'faqs', 'tc_faq_tab' => 'auto-test', 'tc_autotest_section' => 'code' ),
    array( 'page' => 'taxocommerce-core-content', 'tc_type' => 'faqs', 'tc_faq_tab' => 'manual' ),
) as $route ) {
    $_GET = $route; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only fixture.
    tc_faq_at_check( false !== strpos( $body_filter( '' ), 'tc-family-screen' ), 'FAQ branded in route: ' . json_encode( $route ) );
    ob_start();
    $notice();
    $hero = ob_get_clean();
    tc_faq_at_check( 1 === substr_count( $hero, '<header class="tc-family-hero">' ), 'exactly one FAQ header: ' . json_encode( $route ) );
    tc_faq_at_check( false !== strpos( $hero, 'TaxoCommerce FAQs' ), 'FAQ title visible' );
    tc_faq_at_check( false !== strpos( $hero, '0.1.20' ), 'FAQ release version visible' );
    tc_faq_at_check( false !== strpos( $hero, 'Historial de versiones y mejoras' ), 'version history visible' );
}
foreach ( array(
    array( 'page' => 'taxocommerce-core-content', 'tc_type' => 'products' ),
    array( 'page' => 'taxocommerce-core-content', 'tc_type' => 'categories' ),
    array( 'page' => 'taxocommerce-google-bing', 'tc_type' => 'faqs' ),
) as $route ) {
    $_GET = $route; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only fixture.
    tc_faq_at_check( false === strpos( $body_filter( '' ), 'tc-family-screen' ), 'non-FAQ Core route untouched' );
    ob_start();
    $notice();
    tc_faq_at_check( '' === ob_get_clean(), 'no FAQ hero outside its routes' );
}
$_GET = array();

echo 'FAQ Auto Test smoke passed: 25 checks, compatible comparison helper, unified UI, protected JSON and no FAQ writes.' . PHP_EOL;
