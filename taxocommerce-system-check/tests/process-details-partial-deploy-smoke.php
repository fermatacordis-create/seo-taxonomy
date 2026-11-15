<?php
/**
 * Verify a non-atomic FTPS rollout: the Processes file arrives before Details.
 * The old table, protected cancellation and queue inventory must still render.
 *
 * Run: php taxocommerce-system-check/tests/process-details-partial-deploy-smoke.php
 *
 * @package TaxoCommerceSystemCheck
 */
defined( 'ABSPATH' ) || define( 'ABSPATH', '/' );

function tcs_partial_assert( $value, $name ) {
    if ( ! $value ) {
        throw new RuntimeException( $name );
    }
}
function current_user_can( $cap ) { return 'manage_options' === $cap; }
function apply_filters( $name, $value ) { return $value; }
function __( $s, $domain = '' ) { return $s; }
function esc_html__( $s, $domain = '' ) { return $s; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $s ) { return esc_html( $s ); }
function esc_url( $s ) { return esc_html( $s ); }
function admin_url( $p = '' ) { return 'https://example.test/wp-admin/' . $p; }
function wp_nonce_field() {}
function wp_nonce_url( $url, $action ) { return $url . '&_wpnonce=test'; }
function add_query_arg( $args, $url ) { return $url . '?' . http_build_query( $args ); }
function absint( $x ) { return abs( (int) $x ); }
function sanitize_key( $x ) { return preg_replace( '/[^a-z0-9_-]/', '', strtolower( (string) $x ) ); }
function wp_json_encode( $x ) { return json_encode( $x ); }
function _get_cron_array() {
    return array(
        time() + 3600 => array( 'taxocommerce_demo_cleanup' => array(
            'fixture' => array( 'args' => array( 1 ), 'schedule' => false ),
        ) ),
    );
}

$source = dirname( __DIR__ ) . '/includes/';
$dir = sys_get_temp_dir() . '/tcs-partial-' . getmypid();
tcs_partial_assert( mkdir( $dir, 0700 ), 'Create isolated staged plugin folder' );
try {
    $process_file = $dir . '/class-taxocommerce-system-check-processes.php';
    tcs_partial_assert( copy( $source . 'class-taxocommerce-system-check-processes.php', $process_file ), 'Copy process manager only' );
    require $process_file;
    tcs_partial_assert( ! class_exists( 'TaxoCommerce_System_Check_Process_Details', false ), 'Details initially missing' );

    ob_start();
    TaxoCommerce_System_Check_Processes::render();
    $fallback = ob_get_clean();
    tcs_partial_assert( false !== strpos( $fallback, 'Process diagnostics are unavailable until the plugin update completes.' ), 'Missing-class warning' );
    tcs_partial_assert( false !== strpos( $fallback, 'Action Scheduler' ), 'Action Scheduler still visible' );
    tcs_partial_assert( false !== strpos( $fallback, 'Cancelar programación' ), 'Existing safe cancel remains visible' );
    tcs_partial_assert( false === strpos( $fallback, 'Ver log / diagnóstico' ), 'Missing details do not create broken links' );

    // Simulate the last file arriving while PHP is still running.
    tcs_partial_assert( copy( $source . 'class-taxocommerce-system-check-process-details.php', $dir . '/class-taxocommerce-system-check-process-details.php' ), 'Complete transfer' );
    ob_start();
    TaxoCommerce_System_Check_Processes::render();
    $ready = ob_get_clean();
    tcs_partial_assert( class_exists( 'TaxoCommerce_System_Check_Process_Details', false ), 'Details loaded lazily' );
    tcs_partial_assert( false !== strpos( $ready, 'Ver detalles' ), 'Detail links now appear' );
    tcs_partial_assert( false === strpos( $ready, 'Process diagnostics are unavailable until the plugin update completes.' ), 'Warning cleared after transfer' );
    echo "process-details-partial-deploy-smoke: PASS\n";
} finally {
    if ( is_file( $dir . '/class-taxocommerce-system-check-process-details.php' ) ) {
        unlink( $dir . '/class-taxocommerce-system-check-process-details.php' );
    }
    if ( is_file( $dir . '/class-taxocommerce-system-check-processes.php' ) ) {
        unlink( $dir . '/class-taxocommerce-system-check-processes.php' );
    }
    rmdir( $dir );
}
