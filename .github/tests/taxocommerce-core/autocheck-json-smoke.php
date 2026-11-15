<?php
/** Standalone Core Autocheck JSON export regression: no WordPress or network. */
define( 'ABSPATH', __DIR__ . '/' );
define( 'TAXOCOMMERCE_CORE_VERSION', '0.4.53' );
define( 'HOUR_IN_SECONDS', 3600 );

class TaxoCommerce_Core_User_Guide {
    const PAGE_SLUG = 'taxocommerce-core-user-guide';
}

/** The standalone JSON test does not load Core's full dashboard dependencies. */
class TaxoCommerce_Core_Dashboard {
    public static function render_header_nav( $active_tab = 'overview' ) {
        echo '<header class="tcc-hero">TaxoCommerce Core</header>';
        echo '<nav class="nav-tab-wrapper">Core navigation: ' . esc_html( $active_tab ) . '</nav>';
    }
}

$GLOBALS['allowed'] = true;
$GLOBALS['nonce_ok'] = true;
$GLOBALS['transient_reads'] = 0;
$GLOBALS['count'] = 0;
$GLOBALS['saved_report'] = array(
    'version' => '0.4.52',
    'date' => '2026-11-03 12:03:55',
    'ms' => 127,
    'totals' => array( 'pass' => 1, 'fail' => 1, 'skip' => 1 ),
    'rows' => array(
        array( 'id' => 'menus', 'group' => 'Menús', 'label' => 'Lectura', 'status' => 'pass', 'message' => 'Se han leído 2 menús.', 'ms' => 34 ),
        array( 'id' => 'report', 'group' => 'Informes', 'label' => 'Informe', 'status' => 'fail', 'message' => 'No se pudieron cargar las páginas.', 'ms' => 86 ),
        array( 'id' => 'write_uat', 'group' => 'Operaciones', 'label' => 'Guardar', 'status' => 'skip', 'message' => 'No ejecutada; requiere UAT.', 'ms' => 7 ),
    ),
);
function check( $condition, $reason ) {
    if ( ! $condition ) {
        fwrite( STDERR, 'FAIL: ' . $reason . "\n" );
        exit( 1 );
    }
    $GLOBALS['count']++;
}
function add_action( $name, $callback ) { $GLOBALS['registered_hooks'][ $name ] = $callback; }
function current_user_can( $cap ) { return (bool) $GLOBALS['allowed']; }
function apply_filters( $tag, $value ) { return $value; }
function get_current_user_id() { return 7; }
function get_current_blog_id() { return 1; }
function get_transient( $key ) {
    if ( 'tc_core_code_1_7' === $key ) { return false; }
    check( 'taxocommerce_core_autotest_7' === $key, 'Reports are strictly scoped to authenticated user' );
    $GLOBALS['transient_reads']++;
    return $GLOBALS['saved_report'];
}
function wp_die( $message ) { throw new RuntimeException( $message ); }
function check_admin_referer( $nonce, $field ) {
    if ( ! $GLOBALS['nonce_ok'] || $nonce !== 'taxocommerce_core_auto_test_export'
        || $field !== 'tc_autotest_export_nonce' ) {
        throw new RuntimeException( 'Invalid export nonce' );
    }
}
function home_url( $path = '' ) { return 'https://asociacionfocazul.example/' . ltrim( $path, '/' ); }
function get_locale() { return 'es_ES'; }
function wp_json_encode( $value, $flags = 0 ) { return json_encode( $value, $flags ); }
function wp_parse_url( $value, $part ) { return parse_url( $value, $part ); }
function sanitize_file_name( $name ) { return preg_replace( '/[^a-zA-Z0-9._-]/', '', $name ); }
function nocache_headers() {}
function esc_html__( $value, $domain = '' ) { return $value; }
function __( $value, $domain = '' ) { return $value; }
function esc_html( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $value ) { return esc_html( $value ); }
function esc_url( $value ) { return esc_html( $value ); }
function esc_html_e( $value, $domain = '' ) { echo esc_html( $value ); }
function esc_attr_e( $value, $domain = '' ) { echo esc_attr( $value ); }
function wp_nonce_field( $nonce, $field = '_wpnonce' ) { echo '<input name="' . esc_attr( $field ) . '" value="valid">'; }
function selected( $value, $current, $echo = true ) { return (string) $value === (string) $current ? ' selected="selected"' : ''; }
function submit_button( $label, $style = '', $name = '', $wrap = false ) { echo '<button type="submit">' . esc_html( $label ) . '</button>'; }
function admin_url( $path = '' ) { return 'https://asociacionfocazul.example/wp-admin/' . $path; }
function add_query_arg( $query, $value, $url = '' ) { return is_array( $query ) ? $value . '?' . http_build_query( $query ) : ( $url ?: admin_url( 'admin.php?page=taxocommerce-core' ) ) . '&' . rawurlencode( $query ) . '=' . rawurlencode( (string) $value ); }
function remove_query_arg( $key, $url = 'https://asociacionfocazul.example/wp-admin/admin.php?page=taxocommerce-core' ) { return $url; }
function esc_attr__( $text, $domain = '' ) { return esc_attr( $text ); }
function sanitize_key( $text ) { return preg_replace( '/[^a-z0-9_-]/', '', strtolower( (string) $text ) ); }
function wp_unslash( $text ) { return $text; }
function absint( $value ) { return abs( (int) $value ); }

require_once dirname( __DIR__, 3 ) . '/taxocommerce-core/includes/class-taxocommerce-autotest-code.php';
require_once dirname( __DIR__, 3 ) . '/taxocommerce-core/includes/class-taxocommerce-core-auto-test.php';

$mode = $argv[1] ?? 'assert';
if ( 'download' === $mode ) {
    // This branch must print only the real JSON and terminate in the handler.
    TaxoCommerce_Core_Auto_Test::handle_export_json();
    exit( 3 );
}
if ( 'validate' === $mode ) {
    $payload = json_decode( stream_get_contents( STDIN ), true );
    check( is_array( $payload ), 'Downloaded file is parsable JSON' );
    check( $payload['schema_version'] === 1, 'Versioned export format' );
    check( $payload['plugin']['slug'] === 'taxocommerce-core', 'Plugin identifier' );
    check( $payload['plugin']['version'] === '0.4.52', 'Version from original run, not current plugin' );
    check( $payload['installation']['url'] === 'https://asociacionfocazul.example/', 'Identifies the actual store' );
    check( $payload['installation']['locale'] === 'es_ES', 'WordPress language included' );
    check( $payload['autocheck']['mode'] === 'read_only', 'Export does not claim write UAT' );
    check( $payload['autocheck']['overall_status'] === 'fail', 'Real failures cannot be hidden' );
    check( $payload['autocheck']['executed_at'] === '2026-11-03 12:03:55', 'Original report timestamp' );
    check( $payload['autocheck']['duration_ms'] === 127, 'Duration unchanged' );
    check( $payload['autocheck']['totals'] === $GLOBALS['saved_report']['totals'], 'Totals equal UI report' );
    check( $payload['autocheck']['tests'] === $GLOBALS['saved_report']['rows'], 'Individual cases equal UI report, including skipped' );
    echo 'PASS: ' . $GLOBALS['count'] . " downloaded JSON assertions.\n";
    exit;
}

TaxoCommerce_Core_Auto_Test::register_hooks();
check( isset( $GLOBALS['registered_hooks']['admin_post_taxocommerce_core_export_auto_test_json'] ), 'Export handler registered for authenticated admin-post' );
check( ! isset( $GLOBALS['registered_hooks']['admin_post_nopriv_taxocommerce_core_export_auto_test_json'] ), 'No unauthenticated handler registered' );

$payload = TaxoCommerce_Core_Auto_Test::export_payload( $GLOBALS['saved_report'] );
check( is_array( $payload ), 'Saved report can be exported' );
check( $payload['autocheck']['overall_status'] === 'fail', 'Failure is not marked green' );
check( $payload['autocheck']['totals'] === $GLOBALS['saved_report']['totals'], 'Statuses are preserved' );
check( $payload['autocheck']['tests'] === $GLOBALS['saved_report']['rows'], 'Results are not recomputed' );
check( $payload['installation']['url'] === home_url( '/' ), 'Store URL belongs to this installation' );
check( $payload['plugin']['version'] === '0.4.52', 'Last executed version kept after plugin update' );
check( null === TaxoCommerce_Core_Auto_Test::export_payload( false ), 'No report does not mean pass' );
$wrong = $GLOBALS['saved_report'];
$wrong['totals']['pass'] = 3;
check( null === TaxoCommerce_Core_Auto_Test::export_payload( $wrong ), 'Inconsistent totals cannot be downloaded' );
$wrong = $GLOBALS['saved_report'];
$wrong['rows'][2]['status'] = 'unknown';
check( null === TaxoCommerce_Core_Auto_Test::export_payload( $wrong ), 'Invalid row status rejected' );
$skipped = $GLOBALS['saved_report'];
$skipped['rows'] = array( $skipped['rows'][2] );
$skipped['totals'] = array( 'pass' => 0, 'fail' => 0, 'skip' => 1 );
check( 'incomplete' === TaxoCommerce_Core_Auto_Test::export_payload( $skipped )['autocheck']['overall_status'], 'Skipped UAT must not mean full pass' );
$passed = $GLOBALS['saved_report'];
$passed['rows'] = array( $passed['rows'][0] );
$passed['totals'] = array( 'pass' => 1, 'fail' => 0, 'skip' => 0 );
check( 'pass' === TaxoCommerce_Core_Auto_Test::export_payload( $passed )['autocheck']['overall_status'], 'All included tests passed' );

ob_start();
TaxoCommerce_Core_Auto_Test::render();
$html = ob_get_clean();
check( strpos( $html, 'Descargar resultados (JSON)' ) !== false, 'Download button visible when suite has run' );
check( strpos( $html, 'tcc-hero' ) !== false, 'Functional report reuses the shared Core header' );
check( strpos( $html, 'Core navigation: autocheck' ) !== false, 'Core Autocheck remains the selected main tab' );
check( strpos( $html, 'Functionality Check' ) !== false && strpos( $html, 'Code Check' ) !== false, 'Both check tabs rendered after shared hero' );
check( strpos( $html, 'taxocommerce_core_export_auto_test_json' ) !== false, 'Download button submits to authenticated handler' );
check( strpos( $html, 'tc_autotest_export_nonce' ) !== false, 'Download button has its own nonce' );
check( strpos( $html, 'No se pudieron cargar las páginas' ) !== false, 'Failure displayed in UI' );
check( strpos( $html, 'No ejecutada; requiere UAT' ) !== false, 'Skipped test displayed in UI' );

// Switching to Code Check keeps the same Core hero, and never runs the functional suite.
$_GET['tc_autotest_section'] = 'code';
ob_start();
TaxoCommerce_Core_Auto_Test::render();
$code_html = ob_get_clean();
check( strpos( $code_html, 'tcc-hero' ) !== false, 'Code Check retains Core header' );
check( strpos( $code_html, 'Core navigation: autocheck' ) !== false, 'Code Check stays in Core Autocheck' );
check( strpos( $code_html, 'Run Code Check' ) !== false, 'Code Check form is available' );
check( strpos( $code_html, 'tc_autotest_manual_run' ) === false, 'Functional form is hidden on Code Check' );
check( strpos( $code_html, 'No se pudieron cargar las páginas' ) === false, 'Functional results are hidden on Code Check' );
unset( $_GET['tc_autotest_section'] );


$GLOBALS['saved_report'] = false;
ob_start();
TaxoCommerce_Core_Auto_Test::render();
$empty_html = ob_get_clean();
check( strpos( $empty_html, 'Descargar resultados (JSON)' ) === false, 'No download without report' );

$GLOBALS['allowed'] = false;
try {
    TaxoCommerce_Core_Auto_Test::handle_export_json();
    check( false, 'Unauthorized export must be rejected' );
} catch ( RuntimeException $e ) {
    check( strpos( $e->getMessage(), 'Sin permisos' ) !== false, 'Capability gate enforced' );
}
$GLOBALS['allowed'] = true;
$GLOBALS['nonce_ok'] = false;
try {
    TaxoCommerce_Core_Auto_Test::handle_export_json();
    check( false, 'Invalid nonce must be rejected' );
} catch ( RuntimeException $e ) {
    check( 'Invalid export nonce' === $e->getMessage(), 'Nonce gate enforced' );
}
$GLOBALS['nonce_ok'] = true;
try {
    TaxoCommerce_Core_Auto_Test::handle_export_json();
    check( false, 'No report must not produce an empty download' );
} catch ( RuntimeException $e ) {
    check( strpos( $e->getMessage(), 'No hay un informe' ) !== false, 'Expired report requires a fresh run' );
}
require __DIR__ . '/core-native-code-rules-smoke.php';

$tc_sections = TaxoCommerce_Core_Autotest_Code::classified_findings( array(
    'status' => 'completed',
    'files' => array( 'bad.php', 'review.php', 'clean.php', 'skip.php' ),
    'results' => array(
        'bad.php' => array(
            'php' => array( 'status' => 'fail', 'evidence' => array( array( 'rule' => 'php_parse_error', 'line' => 4 ) ) ),
            'wordpress' => array( 'status' => 'pass', 'evidence' => array() ),
            'security' => array( 'status' => 'pass', 'evidence' => array() ),
        ),
        'review.php' => array(
            'php' => array( 'status' => 'pass', 'evidence' => array() ),
            'wordpress' => array( 'status' => 'warning', 'evidence' => array( array( 'rule' => 'review_plugin_installation', 'line' => 89, 'severity' => 'review' ) ) ),
            'security' => array( 'status' => 'pass', 'evidence' => array() ),
        ),
        'clean.php' => array(
            'php' => array( 'status' => 'pass', 'evidence' => array() ),
            'wordpress' => array( 'status' => 'pass', 'evidence' => array() ),
            'security' => array( 'status' => 'pass', 'evidence' => array() ),
        ),
        'skip.php' => array(
            'php' => array( 'status' => 'skip', 'evidence' => array() ),
            'wordpress' => array( 'status' => 'skip', 'evidence' => array() ),
            'security' => array( 'status' => 'skip', 'evidence' => array() ),
        ),
    ),
) );
check( 1 === $tc_sections['counts']['errors'], 'One confirmed syntax error is red' );
check( 1 === $tc_sections['counts']['warnings'], 'One WordPress review warning is orange' );
check( 1 === $tc_sections['counts']['clean'], 'One fully checked file is green' );
check( 3 === $tc_sections['counts']['unchecked'], 'Skipped checks remain grey, not green' );
check( 4 === $tc_sections['errors'][0]['line'], 'Error source line is retained' );
check( 89 === $tc_sections['warnings'][0]['line'], 'Warning source line is retained' );
check( '' !== $tc_sections['warnings'][0]['recommendation'], 'Warnings include a concrete review action' );
check( '' !== $tc_sections['errors'][0]['description'], 'Errors include a user-facing description' );
check( 'review_plugin_installation' === $tc_sections['warnings'][0]['rule'], 'Original rule name is retained' );
$tc_missing = TaxoCommerce_Core_Autotest_Code::classified_findings( array( 'files' => array( 'missing.php' ), 'results' => array() ) );
check( 1 === $tc_missing['counts']['unchecked'] && 0 === $tc_missing['counts']['clean'], 'Not-scanned files never appear green' );


$code_overview = TaxoCommerce_Core_Autotest_Code::summary( array(
    'status' => 'completed',
    'files' => array( 'foo.php' ),
    'results' => array(
        'foo.php' => array(
            'php' => array( 'status' => 'pass', 'evidence' => array() ),
            'wordpress' => array( 'status' => 'warning', 'evidence' => array( array( 'rule' => 'review_plugin_installation', 'line' => 33 ) ) ),
            'security' => array( 'status' => 'warning', 'evidence' => array( array( 'rule' => 'review_request_nonce', 'line' => 44 ) ) ),
        ),
    ),
) );
check( 'review' === $code_overview['status'], 'PHP pass cannot conceal native WordPress and security findings' );
check( 2 === $code_overview['findings'] && 2 === $code_overview['distinct_rules'], 'Summary counts actual evidence, not checked files' );
check( 1 === $code_overview['by_group']['wordpress'] && 1 === $code_overview['by_group']['security'], 'Summary retains independent categories' );
check( 'incomplete' === TaxoCommerce_Core_Autotest_Code::summary( array( 'status' => 'running', 'files' => array( 'a.php' ) ) )['status'], 'Partial scan can never pass' );
check( 'incomplete' === TaxoCommerce_Core_Autotest_Code::summary( array(
    'status' => 'completed',
    'files' => array( 'a.php' ),
    'inventory_truncated' => true,
    'results' => array( 'a.php' => array( 'php' => array( 'status' => 'pass' ), 'wordpress' => array( 'status' => 'pass' ), 'security' => array( 'status' => 'pass' ) ) ),
) )['status'], 'Truncated inventory cannot pass' );
check( 'pass' === TaxoCommerce_Core_Autotest_Code::summary( array(
    'status' => 'completed',
    'files' => array( 'a.php' ),
    'results' => array( 'a.php' => array( 'php' => array( 'status' => 'pass' ), 'wordpress' => array( 'status' => 'pass' ), 'security' => array( 'status' => 'pass' ) ) ),
) )['status'], 'No findings in all completed files passes implemented checks only' );

echo 'PASS: ' . $GLOBALS['count'] . " Core Autocheck export/permission/UI assertions.\n";
