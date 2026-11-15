<?php
/**
 * Standalone Core pilot regression, without WordPress, database or external calls.
 * Run: php taxocommerce-system-check/tests/family-runner-pilot-smoke.php
 */
if ( 'cli' !== PHP_SAPI ) { exit; }
define( 'ABSPATH', __DIR__ . '/' );
define( 'HOUR_IN_SECONDS', 3600 );
$GLOBALS['tcs_family_storage'] = array();
$GLOBALS['tcs_family_allowed'] = true;
$GLOBALS['tcs_family_nonce'] = true;
function add_action( $name, $callback ) { $GLOBALS['tcs_family_hooks'][ $name ] = $callback; }
function current_user_can( $cap ) { return $GLOBALS['tcs_family_allowed'] && 'manage_options' === $cap; }
function check_ajax_referer( $action, $field ) { if ( ! $GLOBALS['tcs_family_nonce'] || 'tcs_family_validation' !== $action ) { throw new RuntimeException( 'nonce' ); } }
function get_current_blog_id() { return 1; }
function get_current_user_id() { return 2; }
function get_transient( $key ) { return $GLOBALS['tcs_family_storage'][ $key ] ?? false; }
function set_transient( $key, $value, $ttl ) { if ( HOUR_IN_SECONDS !== $ttl ) { throw new RuntimeException( 'TTL' ); } $GLOBALS['tcs_family_storage'][ $key ] = $value; }
function wp_send_json_error( $data, $code = 400 ) { throw new RuntimeException( 'error:' . $code ); }
function wp_send_json_success( $data ) { throw new RuntimeException( 'success:' . json_encode( $data ) ); }
function __( $s, $domain = '' ) { return $s; }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\\-]/', '', strtolower( (string) $s ) ); }
function wp_unslash( $s ) { return $s; }
function absint( $s ) { return abs( (int) $s ); }
function wp_convert_hr_to_bytes( $s ) { return (int) $s * 1024 * 1024; }
function is_wp_error( $x ) { return $x instanceof WP_Error; }
class WP_Error { private $message; public function __construct( $id, $message ) { $this->message = $message; } public function get_error_message() { return $this->message; } }
class TaxoCommerce_Core_Auto_Test {
    public static function cases() { return array( 'a' => array( 'Core', 'A' ), 'b' => array( 'Core', 'B' ), 'write_uat' => array( 'Write', 'Not safe' ) ); }
    public static function run_suite( $cases = null ) {
        if ( isset( $cases['write_uat'] ) ) { throw new RuntimeException( 'Destructive UAT must not run' ); }
        $rows = array();
        foreach ( $cases as $id => $labels ) { $rows[] = array( 'status' => 'pass', 'id' => $id, 'label' => $labels[1] ); }
        return array( 'rows' => $rows );
    }
}
class TaxoCommerce_Core_Autotest_Code {
    public static function files() { return array( 'one.php', 'two.php', 'three.php' ); }
    public static function speeds() { return array( 1 => array( 'size' => 1 ), 2 => array( 'size' => 1 ), 3 => array( 'size' => 2 ), 4 => array( 'size' => 3 ), 5 => array( 'size' => 4 ) ); }
    public static function step( $state ) {
        $state['cursor'] = min( count( $state['files'] ), $state['cursor'] + self::speeds()[ $state['speed'] ]['size'] );
        if ( $state['cursor'] === count( $state['files'] ) ) { $state['status'] = 'completed'; }
        return $state;
    }
    public static function summary( $state ) {
        return array( 'status' => 'completed' === $state['status'] ? 'pass' : 'incomplete', 'findings' => 0, 'failed' => 0, 'skipped' => 0 );
    }
}
require dirname( __DIR__ ) . '/includes/class-taxocommerce-system-check-family-runner.php';
function check( $truth, $message ) { if ( ! $truth ) { fwrite( STDERR, $message . "\n" ); exit( 1 ); } }
function run_op( $operation, $speed = 3 ) {
    $_POST = array( 'operation' => $operation, 'speed' => $speed );
    try { TaxoCommerce_System_Check_Family_Runner::ajax(); } catch ( RuntimeException $e ) {
        if ( 0 !== strpos( $e->getMessage(), 'success:' ) ) { throw $e; }
        return json_decode( substr( $e->getMessage(), 8 ), true );
    }
    throw new RuntimeException( 'Missing response' );
}
TaxoCommerce_System_Check_Family_Runner::boot();
check( isset( $GLOBALS['tcs_family_hooks']['wp_ajax_tcs_family_validation'] ), 'AJAX endpoint missing' );
$start = run_op( 'start', 5 );
check( 'functional' === $start['phase'] && 2 === $start['functional']['total'], 'Must start with read-only functionality checks' );
$step = run_op( 'step' );
check( 'code' === $step['phase'] && 2 === $step['functional']['done'], 'Must complete functional checks first' );
$step = run_op( 'step' );
check( 'completed' === $step['status'] && 3 === $step['code']['done'], 'Must finish code batch' );
$paused = run_op( 'start', 1 );
$paused = run_op( 'stop' );
check( 'paused' === $paused['status'], 'Must support stop' );
$paused = run_op( 'resume' );
check( 'running' === $paused['status'], 'Must support resume' );
$GLOBALS['tcs_family_allowed'] = false;
try { run_op( 'start' ); check( false, 'Unauthorized user accepted' ); }
catch ( RuntimeException $e ) { check( 'error:403' === $e->getMessage(), 'Wrong access result' ); }
$GLOBALS['tcs_family_allowed'] = true;
$GLOBALS['tcs_family_nonce'] = false;
try { run_op( 'start' ); check( false, 'Missing nonce accepted' ); }
catch ( RuntimeException $e ) { check( 'nonce' === $e->getMessage(), 'Nonce missing' ); }
echo "family-runner-pilot-smoke: OK\n";
