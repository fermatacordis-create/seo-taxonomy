<?php
/**
 * Standalone regression checks for the System Check process panel.
 *
 * Run: php taxocommerce-system-check/tests/processes-smoke.php
 *
 * @package TaxoCommerceSystemCheck
 */

define( 'ABSPATH', '/' );
$GLOBALS['tcs_process_actions'] = array();
$GLOBALS['tcs_process_cron'] = array();
$GLOBALS['tcs_process_options'] = array();
$GLOBALS['tcs_process_deleted'] = array();
$GLOBALS['tcs_process_stop'] = array();
$GLOBALS['tcs_process_denied'] = false;

function tcs_assert( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}
function add_action() {}
function apply_filters( $name, $value ) { return $value; }
function absint( $value ) { return abs( (int) $value ); }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_-]/', '', strtolower( (string) $value ) ); }
function sanitize_text_field( $value ) { return (string) $value; }
function current_user_can() { return ! $GLOBALS['tcs_process_denied']; }
function __( $text ) { return $text; }
function esc_html__( $text ) { return $text; }
function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES ); }
function esc_attr( $text ) { return esc_html( $text ); }
function esc_url( $text ) { return esc_html( $text ); }
function wp_json_encode( $value ) { return json_encode( $value ); }
function wp_nonce_field() {}
function wp_die( $message ) { throw new RuntimeException( (string) $message ); }
function wp_unslash( $value ) { return $value; }
function check_admin_referer() {}
function wp_nonce_url( $url ) { return $url . '&_wpnonce=smoke'; }
function add_query_arg( $args, $url ) { return $url . '?' . http_build_query( $args ); }
function admin_url( $path = '' ) { return 'https://example.test/wp-admin/' . $path; }
function get_option( $key, $default = false ) { return $GLOBALS['tcs_process_options'][ $key ] ?? $default; }
function update_option( $key, $value ) { $GLOBALS['tcs_process_options'][ $key ] = $value; return true; }
function wp_clear_scheduled_hook( $hook ) { $GLOBALS['tcs_process_deleted'][] = $hook; }
function wp_unschedule_event( $time, $hook, $args ) { $GLOBALS['tcs_process_deleted'][] = $hook; return true; }
function as_unschedule_all_actions( $hook ) { $GLOBALS['tcs_process_deleted'][] = $hook; }
function as_get_scheduled_actions( $args, $format ) {
	$ids = array();
	foreach ( $GLOBALS['tcs_process_actions'] as $id => $entry ) {
		if ( $entry['status'] === $args['status'] ) {
			$ids[] = $id;
		}
	}
	return array_slice( $ids, 0, $args['per_page'] );
}
function _get_cron_array() { return $GLOBALS['tcs_process_cron']; }

class TCS_Test_Action {
	private $hook;
	private $group;
	public function __construct( $hook, $group ) { $this->hook = $hook; $this->group = $group; }
	public function get_hook() { return $this->hook; }
	public function get_group() { return $this->group; }
}
class TCS_Test_Store {
	public function fetch_action( $id ) {
		if ( ! isset( $GLOBALS['tcs_process_actions'][$id] ) ) { throw new RuntimeException( 'Not found.' ); }
		$data = $GLOBALS['tcs_process_actions'][$id];
		return new TCS_Test_Action( $data['hook'], $data['group'] );
	}
	public function get_status( $id ) { return $GLOBALS['tcs_process_actions'][$id]['status']; }
	public function cancel_action( $id ) {
		tcs_assert( 'pending' === $this->get_status( $id ), 'A running action must never be cancelled.' );
		$GLOBALS['tcs_process_actions'][$id]['status'] = 'canceled';
	}
}
class ActionScheduler {
	public static function store() { return new TCS_Test_Store(); }
}
class TCD_Academy {
	const CRON_HOOK = 'tcd_learning_tick';
	public static function state() { return get_option( 'tcd_learning_state', array() ); }
	public static function save( $changes ) {
		$state = array_merge( self::state(), $changes );
		update_option( 'tcd_learning_state', $state );
	}
}
class TaxoCommerce_Import_Export_Batch {}

require dirname( __DIR__ ) . '/includes/class-taxocommerce-system-check-process-details.php';
require dirname( __DIR__ ) . '/includes/class-taxocommerce-system-check-processes.php';

$GLOBALS['tcs_process_actions'] = array(
	10 => array( 'hook' => 'taxocommerce_import_job', 'group' => 'taxocommerce-import-export', 'status' => 'pending' ),
	11 => array( 'hook' => 'woocommerce_scheduled_subscription_payment', 'group' => 'woocommerce', 'status' => 'pending' ),
	12 => array( 'hook' => 'tcd_learning_tick', 'group' => 'custom', 'status' => 'in-progress' ),
	13 => array( 'hook' => 'wp_update_plugins', 'group' => '', 'status' => 'pending' ),
);
$actions = TaxoCommerce_System_Check_Processes::actions();
$by_id = array();
foreach ( $actions as $row ) { $by_id[$row['id']] = $row; }
tcs_assert( $by_id[10]['can_cancel'], 'Plugin job should be cancellable.' );
tcs_assert( ! $by_id[11]['can_cancel'] && ! $by_id[13]['can_cancel'], 'Core and WooCommerce critical hooks must be protected.' );
tcs_assert( ! $by_id[12]['can_cancel'], 'Running PHP callbacks must not be killable.' );

$GLOBALS['tcs_process_cron'] = array(
	time() + 60 => array(
		'tcd_learning_tick' => array( 'a' => array( 'schedule' => false, 'args' => array() ) ),
		'wp_update_plugins' => array( 'b' => array( 'schedule' => 'twicedaily', 'args' => array() ) ),
	),
);
$cron = TaxoCommerce_System_Check_Processes::cron();
tcs_assert( count( $cron ) === 2 && $cron[0]['can_cancel'] && ! $cron[1]['can_cancel'], 'Only safe WP-Cron events may be cancelled.' );

$GLOBALS['tcs_process_options']['tcd_learning_state'] = array( 'run_id' => 'abc', 'status' => 'running', 'lesson' => 2, 'phase' => 'learning' );
$GLOBALS['tcs_process_options']['taxocommerce_ie_batch_state'] = array( 'enabled' => true, 'status' => 'processing', 'current_file' => 'test.csv', 'cursor' => 45 );
$workers = TaxoCommerce_System_Check_Processes::workers();
tcs_assert( 2 === count( $workers ) && $workers[0]['can_stop'] && $workers[1]['can_stop'], 'Both cooperative workers must be shown.' );
$reflect = new ReflectionMethod( 'TaxoCommerce_System_Check_Processes', 'stop_worker' );
$reflect->setAccessible( true );
tcs_assert( $reflect->invoke( null, 'dependiente' ), 'Dependiente stop should be signalled.' );
tcs_assert( 'abc' === get_option( 'tcd_learning_stop' ) && 'stopped' === TCD_Academy::state()['status'], 'Dependiente state and stop token must be set.' );
tcs_assert( $reflect->invoke( null, 'import' ), 'Import-Export stop should be signalled.' );
$state = get_option( 'taxocommerce_ie_batch_state' );
tcs_assert( ! $state['enabled'] && $state['stop_requested'] && 45 === $state['cursor'], 'Import must preserve the saved cursor.' );

$cancel = new ReflectionMethod( 'TaxoCommerce_System_Check_Processes', 'cancel_action' );
$cancel->setAccessible( true );
tcs_assert( ! $cancel->invoke( null, 11 ) && ! $cancel->invoke( null, 12 ), 'Protected or running jobs may not be cancelled.' );
tcs_assert( $cancel->invoke( null, 10 ) && 'canceled' === $GLOBALS['tcs_process_actions'][10]['status'], 'An eligible pending action should be cancelled.' );

ob_start();
TaxoCommerce_System_Check_Processes::render();
$html = ob_get_clean();
tcs_assert( false !== strpos( $html, 'Action Scheduler' ) && false !== strpos( $html, 'WP-Cron' ), 'The process screen must render.' );
tcs_assert( false !== strpos( $html, 'Ver log / diagnóstico' ) && false !== strpos( $html, 'Ver detalles' ), 'Each queue exposes read-only details.' );
tcs_assert( false === strpos( $html, 'serialize(' ), 'Cron signature is JSON-compatible, not serialized.' );
$cancel_cron = new ReflectionMethod( 'TaxoCommerce_System_Check_Processes', 'cancel_cron' );
$cancel_cron->setAccessible( true );
tcs_assert( $cancel_cron->invoke( null, $cron[0]['hook'], $cron[0]['due'], md5( wp_json_encode( $cron[0]['args'] ) ) ), 'Generated WP-Cron signature authorizes exactly the selected safe event.' );
tcs_assert( in_array( 'tcd_learning_tick', $GLOBALS['tcs_process_deleted'], true ), 'The selected event was unscheduled.' );
tcs_assert( ! $cancel_cron->invoke( null, $cron[1]['hook'], $cron[1]['due'], md5( wp_json_encode( $cron[1]['args'] ) ) ), 'Protected core cron cannot be cancelled.' );
$GLOBALS['tcs_process_denied'] = true;
ob_start();
TaxoCommerce_System_Check_Processes::render();
$denied = ob_get_clean();
tcs_assert( '' === $denied, 'Unprivileged users must not see the panel.' );
echo "System Check processes regression: OK\n";
