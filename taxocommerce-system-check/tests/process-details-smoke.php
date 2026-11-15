<?php
/**
 * Regression: on-demand process detail, log safety, nonce/capability, no mutations.
 * Run: php taxocommerce-system-check/tests/process-details-smoke.php
 */
defined( 'ABSPATH' ) || define( 'ABSPATH', '/' );
$GLOBALS['tcs_detail_allowed'] = true;
$GLOBALS['tcs_detail_nonce'] = true;
$GLOBALS['tcs_detail_mutations'] = 0;
$GLOBALS['tcs_detail_log_calls'] = 0;
function detail_assert( $ok, $message ) {
    if ( ! $ok ) { throw new RuntimeException( $message ); }
}
function absint( $v ) { return abs( (int) $v ); }
function sanitize_key( $v ) { return preg_replace( '/[^a-z0-9_-]/', '', strtolower( (string) $v ) ); }
function sanitize_text_field( $v ) { return (string) $v; }
function wp_strip_all_tags( $v ) { return strip_tags( (string) $v ); }
function wp_html_excerpt( $v, $limit ) { return substr( (string) $v, 0, $limit ); }
function wp_unslash( $v ) { return $v; }
function wp_json_encode( $v ) { return json_encode( $v ); }
function current_user_can( $cap ) { return 'manage_options' === $cap && $GLOBALS['tcs_detail_allowed']; }
function check_admin_referer( $nonce ) {
    if ( ! $GLOBALS['tcs_detail_nonce'] || 'taxocommerce_system_check_process_details' !== $nonce ) { throw new RuntimeException( 'nonce' ); }
}
function has_action( $hook ) { return 'test_registered' === $hook ? 5 : false; }
function __( $value, $domain = '' ) { return $value; }
function esc_html__( $value, $domain = '' ) { return esc_html( $value ); }
function esc_html( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $v ) { return esc_html( $v ); }
function admin_url( $p = '' ) { return 'https://example.test/wp-admin/' . $p; }
function add_query_arg( $args, $url ) { return $url . '?' . http_build_query( $args ); }
function wp_nonce_url( $url ) { return $url . '&_wpnonce=fixture'; }
function update_option() { ++$GLOBALS['tcs_detail_mutations']; throw new RuntimeException( 'mutation' ); }
function wp_unschedule_event() { ++$GLOBALS['tcs_detail_mutations']; throw new RuntimeException( 'mutation' ); }
function do_action() { ++$GLOBALS['tcs_detail_mutations']; throw new RuntimeException( 'mutation' ); }
class TCS_Detail_Action {
    public function get_hook() { return 'test_registered'; }
    public function get_group() { return 'safe_fixture'; }
}
class TCS_Detail_Store {
    public function fetch_action( $id ) { if ( 100 !== $id ) { throw new RuntimeException( 'missing' ); } return new TCS_Detail_Action(); }
    public function get_status( $id ) { return 'failed'; }
}
class TCS_Detail_Log {
    private $index;
    public function __construct( $index ) { $this->index = $index; }
    public function get_date() { return new DateTimeImmutable( '2026-11-15 10:00:00', new DateTimeZone( 'UTC' ) ); }
    public function get_message() {
        return 'failure ' . $this->index . ' token=abc123 https://example.test/?api_key=xyz&ok=yes <script>alert(1)</script>';
    }
}
class TCS_Detail_Logger {
    public function get_logs( $id ) {
        ++$GLOBALS['tcs_detail_log_calls'];
        return array_map( function ( $i ) { return new TCS_Detail_Log( $i ); }, range( 1, 27 ) );
    }
}
class ActionScheduler {
    public static function store() { return new TCS_Detail_Store(); }
    public static function logger() { return new TCS_Detail_Logger(); }
}
class TaxoCommerce_System_Check_Processes {
    public static function cron() {
        return array( array( 'hook' => 'seo_orphan_hook', 'due' => 1794744000, 'args' => array( 'task' => 123 ), 'status' => 'due' ) );
    }
    public static function workers() {
        return array( array( 'id' => 'http:7', 'label' => 'HTTP audit', 'status' => 'paused', 'detail' => '4/9' ) );
    }
}
require dirname( __DIR__ ) . '/includes/class-taxocommerce-system-check-process-details.php';
$c = 'TaxoCommerce_System_Check_Process_Details';
detail_assert( 'missing' === $c::callback_status( 'seo_orphan_hook' ), 'missing callback detected' );
detail_assert( 'registered' === $c::callback_status( 'test_registered' ), 'registered callback detected' );
$log = $c::safe_log_message( 'Bearer abc123 token=xyz&x=2 https://example.test/?api_key=shh' );
detail_assert( false === strpos( $log, 'abc123' ) && false === strpos( $log, 'xyz' ) && false === strpos( $log, 'shh' ), 'log secrets masked' );
$json_log = $c::safe_log_message( '{"access_token":"secretXYZ","password":"privateValue"}' );
detail_assert( false === strpos( $json_log, 'secretXYZ' ) && false === strpos( $json_log, 'privateValue' ), 'JSON-style credentials masked' );
$a = $c::action( 100 );
detail_assert( 'failed' === $a['status'] && count( $a['logs'] ) === 20 && 'registered' === $a['callback'], 'action and last 20 logs returned' );
detail_assert( false !== strpos( $a['logs'][0]['message'], 'failure 8' ), 'logs bounded to latest 20' );
$signature = md5( wp_json_encode( array( 'task' => 123 ) ) );
$cron = $c::cron( 'seo_orphan_hook', 1794744000, $signature );
detail_assert( 'missing' === $cron['callback'], 'WP-Cron orphan reported' );
detail_assert( false === $c::cron( 'seo_orphan_hook', 1794744000, str_repeat( '0', 32 ) ), 'wrong cron signature refused' );
detail_assert( 'paused' === $c::worker( 'http:7' )['status'], 'worker shown without running PHP claims' );
$_GET = array( 'tcs_detail' => 'action', 'tcs_item' => '100' );
ob_start(); $c::render_selected(); $html = ob_get_clean();
detail_assert( false !== strpos( $html, 'failure 27' ) && false === strpos( $html, 'abc123' ), 'rendered logs redacted' );
detail_assert( 0 === $GLOBALS['tcs_detail_mutations'], 'read-only diagnostics' );
$GLOBALS['tcs_detail_allowed'] = false;
detail_assert( false === $c::selected(), 'unprivileged detail denied' );
$GLOBALS['tcs_detail_allowed'] = true;
$GLOBALS['tcs_detail_nonce'] = false;
try { $c::selected(); throw new RuntimeException( 'unauthorized detail' ); }
catch ( RuntimeException $e ) { detail_assert( 'nonce' === $e->getMessage(), 'nonce required' ); }
echo "process-details-smoke: PASS\n";
