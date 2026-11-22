<?php
/**
 * Per-plugin report regression, including errors, warnings, isolation and auth.
 *
 * Run: php taxocommerce-system-check/tests/module-audit-smoke.php
 */
if ( 'cli' !== PHP_SAPI ) { exit; }
defined( 'ABSPATH' ) || define( 'ABSPATH', __DIR__ . '/' );
define( 'DAY_IN_SECONDS', 86400 );
define( 'TAXOCOMMERCE_SYSTEM_CHECK_PATH', dirname( __DIR__ ) . '/' );
$GLOBALS['module_audit_storage'] = array();
$GLOBALS['module_audit_allowed'] = true;
$GLOBALS['module_audit_nonce'] = true;

function test_check( $ok, $message ) { if ( ! $ok ) { throw new RuntimeException( $message ); } }
function add_action( $name, $callback ) { $GLOBALS['module_audit_hooks'][ $name ] = $callback; }
function add_filter( $name, $callback ) { $GLOBALS['module_audit_filters'][ $name ] = $callback; }
function get_current_blog_id() { return 1; }
function get_current_user_id() { return 2; }
function get_transient( $key ) { return $GLOBALS['module_audit_storage'][ $key ] ?? false; }
function set_transient( $key, $value, $ttl ) { test_check( DAY_IN_SECONDS === $ttl, 'Wrong TTL' ); $GLOBALS['module_audit_storage'][ $key ] = $value; return true; }
function current_user_can( $cap ) { return 'manage_options' === $cap && $GLOBALS['module_audit_allowed']; }
function check_ajax_referer( $action, $field ) { if ( ! $GLOBALS['module_audit_nonce'] || 'tcs_module_audit' !== $action ) { throw new RuntimeException( 'nonce' ); } }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_-]/', '', strtolower( (string) $s ) ); }
function wp_unslash( $s ) { return $s; }
function absint( $s ) { return abs( (int) $s ); }
function sanitize_text_field( $s ) { return strip_tags( (string) $s ); }
function wp_generate_uuid4() { static $seq = 0; return '00000000-0000-4000-8000-' . str_pad( (string) ++$seq, 12, '0', STR_PAD_LEFT ); }
function is_wp_error( $v ) { return $v instanceof WP_Error; }
function __( $s, $d = '' ) { return $s; }
function esc_html__( $s, $d = '' ) { return htmlspecialchars( $s, ENT_QUOTES, 'UTF-8' ); }
function esc_attr__( $s, $d = '' ) { return htmlspecialchars( $s, ENT_QUOTES, 'UTF-8' ); }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $s ) { return esc_html( $s ); }
function selected( $a, $b, $echo = true ) { return $a === $b ? ' selected="selected"' : ''; }
function wp_normalize_path( $s ) { return str_replace( '\\', '/', (string) $s ); }
function wp_send_json_success( $v ) { throw new RuntimeException( 'success:' . json_encode( $v ) ); }
function wp_send_json_error( $v, $code = 400 ) { throw new RuntimeException( 'error:' . $code ); }
function wp_json_encode( $v ) { return json_encode( $v ); }
function admin_url( $p = '' ) { return 'https://example.org/wp-admin/' . $p; }
function wp_create_nonce() { return 'nonce'; }
function wp_nonce_url( $url ) { return $url . '&_wpnonce=nonce'; }
function wp_add_inline_script( $id, $script, $after ) { $GLOBALS['module_audit_scripts'][ $id ] = $script; }
function taxocommerce_system_check_registered_modules() {
    return array(
        'system_check' => array( 'name' => 'System Check', 'version' => '0.2.41', 'status' => 'active' ),
        'core' => array( 'name' => 'Core', 'version' => '0.4.85', 'status' => 'active' ),
    );
}
function taxocommerce_system_check_module_checks( $id, $module ) {
    return array( 'registered' => array( 'ok' => true, 'label' => 'Loaded' ), 'version' => array( 'ok' => true, 'label' => 'Version' ) );
}
class WP_Error {
    private $msg;
    public function __construct( $code, $message ) { $this->msg = $message; }
    public function get_error_message() { return $this->msg; }
}

/** The fixture must be in the real selected module directory for reflection. */
class Test_Auto_Test {
    public static function cases() { return array( 'pass' => array( 'group', 'Pass' ), 'fail' => array( 'group', 'Fail' ), 'warning' => array( 'group', 'Warning' ), 'write_uat' => array( 'UAT', 'Dangerous write' ) ); }
    public static function run_suite( $cases = null ) {
        if ( isset( $cases['write_uat'] ) ) { throw new RuntimeException( 'Destructive case reached' ); }
        $rows = array();
        foreach ( (array) $cases as $id => $definition ) {
            $rows[] = array( 'id' => $id, 'group' => $definition[0], 'label' => $definition[1], 'status' => 'warning' === $id ? 'warning' : ( 'fail' === $id ? 'fail' : 'pass' ), 'message' => $id === 'fail' ? 'Specific failure reason' : 'Works' );
        }
        return array( 'rows' => $rows );
    }
}
class Test_Autotest_Code {
    public static function files() { return array( 'one.php', 'two.php' ); }
    public static function step( $state ) {
        $file = $state['files'][ $state['cursor'] ];
        $state['results'][ $file ] = array( 'php' => array( 'status' => 'pass', 'evidence' => array() ) );
        ++$state['cursor'];
        if ( $state['cursor'] >= count( $state['files'] ) ) { $state['status'] = 'completed'; }
        return $state;
    }
    public static function summary( $state ) { return array( 'status' => $state['status'], 'findings' => count( $state['results'] ), 'failed' => 0, 'skipped' => 0 ); }
    public static function classified_findings( $state ) {
        $warnings = array();
        foreach ( array_keys( $state['results'] ) as $file ) {
            $warnings[] = array( 'file' => $file, 'line' => 14, 'rule' => 'review_nonce', 'severity' => 'warning', 'description' => 'Check nonce', 'recommendation' => 'Verify the actual call path' );
        }
        return array( 'errors' => array(), 'warnings' => $warnings, 'unchecked' => array() );
    }
}
require dirname( __DIR__ ) . '/includes/class-taxocommerce-system-check-module-audit.php';

function call_audit( $module, $op ) {
    $_POST = array( 'module' => $module, 'operation' => $op, 'speed' => 5 );
    try { TaxoCommerce_System_Check_Module_Audit::ajax(); } catch ( RuntimeException $error ) {
        if ( 0 === strpos( $error->getMessage(), 'success:' ) ) {
            return json_decode( substr( $error->getMessage(), 8 ), true );
        }
        throw $error;
    }
    throw new RuntimeException( 'AJAX result missing' );
}

TaxoCommerce_System_Check_Module_Audit::boot();
test_check( isset( $GLOBALS['module_audit_hooks']['wp_ajax_tcs_module_audit'] ), 'Missing AJAX handler' );
test_check( isset( $GLOBALS['module_audit_hooks']['admin_post_tcs_module_audit_export'] ), 'Missing JSON export handler' );
test_check( isset( $GLOBALS['module_audit_filters']['taxocommerce_system_check_process_workers'] ), 'Process monitor not connected' );
test_check( isset( $GLOBALS['module_audit_filters']['taxocommerce_system_check_stop_worker'] ), 'Cooperative stop not connected' );
// Billing returns a list of rows, not an envelope. Do not show an invalid report.
$append = new ReflectionMethod( 'TaxoCommerce_System_Check_Module_Audit', 'append_rows' );
$append->setAccessible( true );
$fixture = array( 'functional_rows' => array() );
$billing_rows = array( array( 'group' => 'Billing', 'name' => 'WooCommerce API', 'status' => 'warning', 'detail' => 'No order API' ) );
$append->invokeArgs( null, array( &$fixture, $billing_rows ) );
test_check( count( $fixture['functional_rows'] ) === 1 && 'warning' === $fixture['functional_rows'][0]['status'] && 'WooCommerce API' === $fixture['functional_rows'][0]['label'], 'Billing-style direct row list accepted with real warning' );

$s = call_audit( 'system_check', 'start' );
test_check( 'running' === $s['status'] && 'system_check' === $s['plugin'], 'Wrong plugin started' );
test_check( 'system_check' === get_transient( 'tcs_module_audit_recent_1_2' ), 'Selection persists after navigation' );
$processes = TaxoCommerce_System_Check_Module_Audit::process_rows( array() );
test_check( 1 === count( $processes ) && 'audit:system_check' === $processes[0]['id'] && $processes[0]['can_stop'], 'Check appears in Processes for its owner' );
test_check( TaxoCommerce_System_Check_Module_Audit::process_stop( false, 'audit:system_check' ), 'Stop from Processes acknowledged' );
$s = call_audit( 'system_check', 'status' );
test_check( 'paused' === $s['status'], 'Process stop persisted as paused' );
$s = call_audit( 'system_check', 'resume' );
test_check( 'running' === $s['status'], 'Resume after reload works' );
$s = call_audit( 'system_check', 'step' );
test_check( 'code' === $s['phase'] && 1 === $s['functional']['totals']['fail'] && 1 === $s['functional']['totals']['warning'], 'Native functional failures must appear' );
$s = call_audit( 'system_check', 'step' );
$s = call_audit( 'system_check', 'step' );
test_check( 'completed' === $s['status'] && 2 === $s['code']['warnings'], 'Native findings must appear as warnings' );
$report = call_audit( 'system_check', 'report' );
test_check( count( $report['functional_tests'] ) === 4, 'Excluded UAT still documented, not executed' );
test_check( count( $report['code_findings']['warnings'] ) === 2, 'Detailed code findings preserved' );
test_check( 'Specific failure reason' === $report['functional_tests'][2]['message'], 'Specific functional error present' );
test_check( count( $report['execution_log'] ) >= 5, 'Start/stop/resume and batch history included in report' );
test_check( 1 === count( TaxoCommerce_System_Check_Module_Audit::process_rows( array() ) ), 'Completed check remains visible for audit' );

$other = call_audit( 'core', 'status' );
test_check( 'not_run' === $other['status'], 'Different plugin must have independent report state' );
try { call_audit( 'evil', 'start' ); throw new RuntimeException( 'Unregistered plugin accepted' ); }
catch ( RuntimeException $error ) { test_check( 'error:400' === $error->getMessage(), 'Incorrect unregistered response' ); }

$GLOBALS['module_audit_allowed'] = false;
try { call_audit( 'system_check', 'report' ); throw new RuntimeException( 'Unauthorized report accepted' ); }
catch ( RuntimeException $error ) { test_check( 'error:403' === $error->getMessage(), 'Admin capability not enforced' ); }
$GLOBALS['module_audit_allowed'] = true;
$GLOBALS['module_audit_nonce'] = false;
try { call_audit( 'system_check', 'report' ); throw new RuntimeException( 'Missing nonce accepted' ); }
catch ( RuntimeException $error ) { test_check( 'nonce' === $error->getMessage(), 'AJAX nonce not enforced' ); }

ob_start();
TaxoCommerce_System_Check_Module_Audit::row( 'system_check' );
$html = ob_get_clean();
test_check( false !== strpos( $html, 'data-tcs-module-start="system_check"' ), 'Specific plugin row button missing' );
test_check( false !== strpos( $html, 'data-tcs-module-toggle="system_check"' ), 'Per-row Stop/Resume missing' );
// Run-all is a separate browser-side orchestrator, never a second module inventory.
ob_start();
TaxoCommerce_System_Check_Module_Audit::render();
$bulk_html = ob_get_clean();
test_check( false !== strpos( $bulk_html, 'data-tcs-module-all' ), 'Run-all button missing beside CPU selector' );
test_check( false !== strpos( $bulk_html, 'id="tcs-module-bulk-progress"' ), 'Aggregate progress marker missing' );
test_check( false !== strpos( $GLOBALS['module_audit_scripts']['taxocommerce-system-check-module-audit'], 'Plugins checked' ), 'Aggregate progress translation missing' );
echo "module-audit-smoke: PASS\n";
