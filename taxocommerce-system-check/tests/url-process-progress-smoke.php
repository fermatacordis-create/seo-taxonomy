<?php
/**
 * Processes tab URL worker progress and resumability regression.
 * Standalone no HTTP requests, no WordPress database, no job mutations.
 */
define( 'ABSPATH', '/' );
$GLOBALS['tcs_http_state'] = array();
function seo_health_scan_active_run( $scope ) { return $GLOBALS['tcs_http_state'][ $scope ] ?? null; }
function absint( $value ) { return abs( (int) $value ); }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_-]/', '', strtolower( (string) $value ) ); }
function __( $text, $domain = '' ) { return $text; }
function apply_filters( $name, $default ) { return $default; }
function tcs_progress_assert( $ok, $reason ) {
    if ( ! $ok ) {
        fwrite( STDERR, "FAIL: {$reason}\n" );
        exit( 1 );
    }
}
require_once dirname( __DIR__ ) . '/includes/class-taxocommerce-system-check-url-audit.php';

$GLOBALS['tcs_http_state']['page'] = array(
    'id' => 123, 'mode' => 'local', 'status' => 'running',
    'processed_items' => 16, 'total_items' => 100,
);
$rows = TaxoCommerce_System_Check_URL_Audit::process_rows();
tcs_progress_assert( 1 === count( $rows ), 'Only one active URL worker' );
tcs_progress_assert( 'http:123' === $rows[0]['id'], 'Keep existing run identity and stop links' );
tcs_progress_assert( $rows[0]['can_stop'], 'Preserve cooperative cancellation' );
tcs_progress_assert( false !== strpos( $rows[0]['detail'], '5 URLs' ), 'Visible true bounded batch limit' );
tcs_progress_assert( false !== strpos( $rows[0]['detail'], '16/100' ), 'Real progress from the saved run' );
tcs_progress_assert( false !== strpos( $rows[0]['detail'], '17' ), 'Next queued position, not a fabricated URL' );
$GLOBALS['tcs_http_state']['page']['status'] = 'paused';
$paused = TaxoCommerce_System_Check_URL_Audit::process_rows();
tcs_progress_assert( 1 === count( $paused ) && 'paused' === $paused[0]['status'], 'Paused scans show their resume point without restarting' );
$GLOBALS['tcs_http_state']['page']['status'] = 'cancelled';
tcs_progress_assert( array() === TaxoCommerce_System_Check_URL_Audit::process_rows(), 'Cancelled runs do not show as running' );
$GLOBALS['tcs_http_state']['page']['status'] = 'running';
$GLOBALS['tcs_http_state']['page']['mode'] = 'github';
tcs_progress_assert( array() === TaxoCommerce_System_Check_URL_Audit::process_rows(), 'Old external scans are not mistaken for local workers' );
echo "System Check HTTP Procesos: batch limit, recorded progress, next queued position PASS\n";
