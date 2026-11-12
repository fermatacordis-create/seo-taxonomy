<?php
/** Offline check for read-only cleanup loopback without WP-Cron. */
define( 'ABSPATH', __DIR__ );
class WP_Error {
    private $code;
    public function __construct( $code, $message ) { $this->code = $code; }
    public function get_error_code() { return $this->code; }
}
function __( $text, $domain = '' ) { return $text; }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function absint( $value ) { return abs( (int) $value ); }
function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $value ) ); }
function sanitize_text_field( $value ) { return (string) $value; }
function wp_unslash( $value ) { return $value; }
function wp_generate_uuid4() { return 'safe-test-run'; }
function current_time( $format ) { return '2026-11-12 14:00:00'; }
function current_user_can( $capability ) { return 1 === $GLOBALS['tci_user']; }
function user_can( $id, $capability ) { return 1 === (int) $id; }
function get_current_user_id() { return $GLOBALS['tci_user']; }
function wp_set_current_user( $id ) { $GLOBALS['tci_user'] = $id; }
function get_option( $key, $default = false ) { return $GLOBALS['tci_options'][ $key ] ?? $default; }
function update_option( $key, $value, $autoload = false ) { $GLOBALS['tci_options'][ $key ] = $value; return true; }
function wp_remote_post( $url, $args ) { $GLOBALS['tci_dispatched'][] = $args; return array( 'response' => array( 'code' => 0 ) ); }
function wp_salt( $scheme = '' ) { return 'server-side-test-secret'; }
function admin_url( $path ) { return 'https://example.test/wp-admin/' . $path; }
function wp_next_scheduled( $hook ) { return false; }
function wp_schedule_single_event( $time, $hook ) { ++$GLOBALS['tci_cron_calls']; return true; }
function wp_doing_ajax() { return true; }
function status_header( $code ) { $GLOBALS['tci_last_status'] = $code; }
function wp_die() { throw new RuntimeException( 'end of AJAX' ); }
function check_bg( $ok, $message ) {
    if ( ! $ok ) { fwrite( STDERR, 'FAIL: ' . $message . PHP_EOL ); exit( 1 ); }
}
class TaxoCommerce_Images_Assignment {
    public static function scopes() { return array(); }
}
class TaxoCommerce_Images_Load {
    public static function delay_ms( $mode ) { return 1000; }
    public static function begin() { return array(); }
    public static function finish( $mode, $measurement, $items ) { return array(); }
}
class TaxoCommerce_Images_Maintenance {
    public static $scan = array( 'run' => 'snapshot-1', 'scanned' => 3, 'total' => 6, 'status' => 'running' );
    public static $runs = 0;
    public static function state( $mode ) { return self::$scan; }
    public static function lock( $name ) { return true; }
    public static function unlock( $name ) {}
    public static function scan( $mode ) {
        ++self::$runs;
        self::$scan['scanned'] = 6;
        self::$scan['status'] = 'complete';
        return self::$scan;
    }
}
$GLOBALS['tci_options'] = array();
$GLOBALS['tci_dispatched'] = array();
$GLOBALS['tci_cron_calls'] = 0;
$GLOBALS['tci_user'] = 1;
require dirname( __DIR__ ) . '/includes/class-taxocommerce-images-background.php';
$job = TaxoCommerce_Images_Background::start( 'cleanup', 1 );
check_bg( 'running' === $job['status'], 'existing analysis is running' );
check_bg( 3 === $job['processed'] && 'snapshot-1' === $job['scan_run'], 'cursor is adopted without restart' );
check_bg( 1 === count( $GLOBALS['tci_dispatched'] ), 'signed internal request dispatched' );
check_bg( 0 === $GLOBALS['tci_cron_calls'], 'cleanup never schedules WP-Cron' );
$body = $GLOBALS['tci_dispatched'][0]['body'];
check_bg( 'taxocommerce_images_cleanup_worker' === $body['action'], 'worker endpoint is scoped to cleanup' );
check_bg( hash_equals( hash_hmac( 'sha256', $job['run'], wp_salt( 'auth' ) ), $body['signature'] ), 'loopback signature is valid' );
$_POST = array( 'run' => $job['run'], 'signature' => 'forged' );
try {
    TaxoCommerce_Images_Background::cleanup_worker();
    check_bg( false, 'forged worker must be rejected' );
} catch ( RuntimeException $error ) {
    check_bg( 403 === $GLOBALS['tci_last_status'], 'unsigned continuation forbidden' );
}
check_bg( 0 === TaxoCommerce_Images_Maintenance::$runs, 'forged worker cannot process an image' );
$live = TaxoCommerce_Images_Background::state( 'cleanup' );
$live['next_run'] = time() - 1;
update_option( 'taxocommerce_images_bg_cleanup', $live, false );
$_POST = $body;
try {
    TaxoCommerce_Images_Background::cleanup_worker();
} catch ( RuntimeException $error ) {
    check_bg( 'end of AJAX' === $error->getMessage(), 'worker ended normally' );
}
$done = TaxoCommerce_Images_Background::state( 'cleanup' );
check_bg( 'complete' === $done['status'] && 6 === $done['processed'], 'signed worker finishes persisted scan' );
check_bg( 1 === TaxoCommerce_Images_Maintenance::$runs, 'one bounded batch executed' );
check_bg( 1 === count( $GLOBALS['tci_dispatched'] ), 'completed scan does not dispatch another batch' );
check_bg( 0 === $GLOBALS['tci_cron_calls'], 'no WP-Cron dependencies introduced' );
echo "Images cleanup server worker: PASS (signed, cursored, cron-independent)\n";
