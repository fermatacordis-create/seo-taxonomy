<?php
/**
 * Isolated background import test. No site, orders, tax return, or database writes.
 */
define( 'ABSPATH', __DIR__ . '/' );
$GLOBALS['tca_options'] = array( 'tca_settings_v1' => array( 'start_date' => '2026-11-01', 'auto_sync_sales' => 0 ) );
$GLOBALS['tca_scheduled'] = false;
$GLOBALS['tca_wc_calls'] = array();
$GLOBALS['tca_wc_pages'] = 5;

function __( $text, $domain = '' ) { return $text; }
function plugin_dir_path( $path ) { return dirname( $path ) . '/'; }
function add_action( $hook, $callback, $priority = 10, $args = 1 ) {}
function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {}
function register_activation_hook( $file, $callback ) {}
function register_deactivation_hook( $file, $callback ) {}
function wp_next_scheduled( $hook ) { return $GLOBALS['tca_scheduled'] ? 123456 : false; }
function wp_schedule_event( $timestamp, $recurrence, $hook ) { $GLOBALS['tca_scheduled'] = true; return true; }
function wp_clear_scheduled_hook( $hook ) { $GLOBALS['tca_scheduled'] = false; return true; }
function get_option( $key, $default = false ) { return array_key_exists( $key, $GLOBALS['tca_options'] ) ? $GLOBALS['tca_options'][ $key ] : $default; }
function update_option( $key, $value, $autoload = null ) { $GLOBALS['tca_options'][ $key ] = $value; return true; }
function wp_parse_args( $args, $defaults ) { return array_merge( $defaults, (array) $args ); }
function current_time( $format ) { return 'mysql' === $format ? '2026-11-15 11:00:00' : '2026-11-15'; }
function wc_get_base_location() { return array( 'country' => 'ES' ); }
function get_woocommerce_currency() { return 'EUR'; }
function wc_get_is_paid_statuses() { return array( 'completed' ); }
function wc_get_orders( $args ) {
    $GLOBALS['tca_wc_calls'][] = $args;
    return (object) array( 'orders' => array(), 'max_num_pages' => $GLOBALS['tca_wc_pages'] );
}
function is_wp_error( $object ) { return $object instanceof WP_Error; }
function absint( $value ) { return abs( (int) $value ); }
class WP_Error {
    public function get_error_message() { return 'Mock failure'; }
}
require_once dirname( __DIR__ ) . '/taxocommerce-accounting.php';
function tca_sync_assert( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}
tca_schedule_sales_sync();
tca_sync_assert( false === $GLOBALS['tca_scheduled'], 'Auto import stays disabled until the merchant opts in' );
tca_background_sales_sync();
tca_sync_assert( ! $GLOBALS['tca_wc_calls'], 'Disabled import performs no WooCommerce query' );
$GLOBALS['tca_options']['tca_settings_v1']['auto_sync_sales'] = 1;
tca_schedule_sales_sync();
tca_sync_assert( true === $GLOBALS['tca_scheduled'], 'Enabling schedules hourly WP-Cron' );
tca_background_sales_sync();
$state = get_option( 'tca_sales_sync_state', array() );
tca_sync_assert( 4 === $state['next_page'], 'Runs are bounded to three pages' );
tca_sync_assert( count( $GLOBALS['tca_wc_calls'] ) === 3, 'Only three bounded WooCommerce queries' );
tca_sync_assert( empty( $state['last_full_cycle'] ), 'No false claim of full sync' );
tca_background_sales_sync();
$state = get_option( 'tca_sales_sync_state', array() );
tca_sync_assert( 1 === $state['next_page'], 'Cursor resets after final page' );
tca_sync_assert( ! empty( $state['last_full_cycle'] ), 'Full reconciliation cycle recorded' );
tca_sync_assert( count( $GLOBALS['tca_wc_calls'] ) === 5, 'Reaches all pages across two cron ticks' );
$GLOBALS['tca_options']['tca_settings_v1']['auto_sync_sales'] = 0;
tca_schedule_sales_sync();
tca_sync_assert( false === $GLOBALS['tca_scheduled'], 'Disabling clears cron' );
tca_background_sales_sync();
tca_sync_assert( count( $GLOBALS['tca_wc_calls'] ) === 5, 'Disabling stops further imports' );
echo "Accounting opt-in background import: PASS\n";
