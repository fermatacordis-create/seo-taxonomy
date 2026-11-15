<?php
/**
 * Regression test for Processes-only, read-only queue diagnostics.
 * Uses an isolated WordPress/Action Scheduler fixture, never a live store.
 */
define( 'ABSPATH', '/' );
define( 'HOUR_IN_SECONDS', 3600 );
$GLOBALS['now_for_queue'] = 1794744000;
$GLOBALS['queue_table_available'] = true;
$GLOBALS['queue_cron'] = array();
function absint( $x ) { return abs( (int) $x ); }
function __( $value, $domain = '' ) { return $value; }
function esc_html( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
function number_format_i18n( $n ) { return number_format( $n, 0, '.', ',' ); }
function seo_server_status_table_exists( $name ) { return $GLOBALS['queue_table_available'] && 'wp_actionscheduler_actions' === $name; }
function _get_cron_array() { return $GLOBALS['queue_cron']; }
function tcs_queue_assert( $ok, $name ) {
    if ( ! $ok ) {
        fwrite( STDERR, "FAILED: {$name}\n" );
        exit( 1 );
    }
}
class TCS_Queue_Test_DB {
    public $prefix = 'wp_';
    public $queries = array();
    public function prepare( $query, ...$args ) {
        $this->queries[] = array( $query, $args );
        return $query;
    }
    public function get_row( $query ) {
        if ( false !== strpos( $query, 'scheduled_date_gmt' ) ) {
            return (object) array( 'hook' => 'taxocommerce_slow_scan', 'scheduled_date_gmt' => '2026-11-15 09:00:00' );
        }
        return (object) array( 'action_id' => 77, 'hook' => '<test_failure>', 'last_attempt_gmt' => '2026-11-15 09:40:00' );
    }
    public function get_results( $query ) {
        return array(
            (object) array( 'status' => 'complete', 'count_actions' => 8 ),
            (object) array( 'status' => 'failed', 'count_actions' => 2 ),
        );
    }
}
$GLOBALS['wpdb'] = new TCS_Queue_Test_DB();
require_once dirname( __DIR__ ) . '/includes/class-taxocommerce-system-check-queue-health.php';

tcs_queue_assert( 'no_old_backlog' === TaxoCommerce_System_Check_Queue_Health::assess( 600, 0, 0 ), 'No overdue work is not a runner failure' );
tcs_queue_assert( 'failures_recent' === TaxoCommerce_System_Check_Queue_Health::assess( 1800, 1, 2 ), 'Recent failure diagnosis' );
tcs_queue_assert( 'failures_recent' === TaxoCommerce_System_Check_Queue_Health::assess( 0, 1, 2 ), 'Recent failures matter even without a late pending job' );
tcs_queue_assert( 'backlog_with_activity' === TaxoCommerce_System_Check_Queue_Health::assess( 1800, 7, 0 ), 'Stale job but runner is active' );
tcs_queue_assert( 'runner_unconfirmed' === TaxoCommerce_System_Check_Queue_Health::assess( 1800, 0, 0 ), 'No activity cannot be reported as proven runner stop' );

$now = strtotime( '2026-11-15 10:00:00 UTC' );
$GLOBALS['queue_cron'] = array(
    $now - 1800 => array( 'some_old_hook' => array( 'one' => array( 'args' => array() ) ) ),
    $now - 1200 => array( 'second_hook' => array( 'one' => array( 'args' => array() ) ) ),
    $now + 600 => array( 'future_hook' => array( 'one' => array( 'args' => array() ) ) ),
);
$cron = TaxoCommerce_System_Check_Queue_Health::cron( $now );
tcs_queue_assert( $cron['available'] && 3 === $cron['total'] && 2 === $cron['overdue'], 'WP-Cron counts all and only late entries' );
tcs_queue_assert( 'some_old_hook' === $cron['oldest_hook'] && 1800 === $cron['oldest_delay_seconds'], 'Oldest hook and age are correct' );
tcs_queue_assert( $cron['oldest_due'] === $now - 1800, 'WP-Cron deadline grounded in fixture' );

$actions = TaxoCommerce_System_Check_Queue_Health::scheduler( $now );
tcs_queue_assert( $actions['available'] && 'taxocommerce_slow_scan' === $actions['oldest_hook'], 'Read the oldest native pending action' );
tcs_queue_assert( 8 === $actions['recent_completed'] && 2 === $actions['recent_failed'], 'Read recent completed/failed counts' );
tcs_queue_assert( 'failures_recent' === $actions['assessment'], 'Repeated failures are distinguished from no runner activity' );
tcs_queue_assert( '<test_failure>' === $actions['last_failed_hook'] && 77 === $actions['last_failed_id'], 'Last failed hook and action ID are retrieved' );
tcs_queue_assert( 3 === count( $GLOBALS['wpdb']->queries ), 'Only three bounded Action Scheduler diagnostic queries' );
foreach ( $GLOBALS['wpdb']->queries as $query ) {
    tcs_queue_assert( false !== stripos( $query[0], 'SELECT ' ) && false === stripos( $query[0], 'UPDATE ' ), 'Read-only prepared queries' );
    tcs_queue_assert( 'wp_actionscheduler_actions' === $query[1][0], 'Site table prefix is used' );
}
$GLOBALS['queue_table_available'] = false;
$none = TaxoCommerce_System_Check_Queue_Health::scheduler( $now + 1 );
tcs_queue_assert( ! $none['available'] && 'unavailable' === $none['assessment'], 'Missing Action Scheduler is informational, never fatal' );
$GLOBALS['queue_table_available'] = true;

ob_start();
TaxoCommerce_System_Check_Queue_Health::render_cron_rows();
TaxoCommerce_System_Check_Queue_Health::render_scheduler_rows();
$html = ob_get_clean();
tcs_queue_assert( false !== strpos( $html, 'Oldest overdue WP-Cron event' ), 'The metrics render as normal table rows' );
tcs_queue_assert( false !== strpos( $html, 'Action Scheduler diagnostic' ), 'The diagnostic assessment is visible' );
tcs_queue_assert( false === strpos( $html, '<test_failure>' ), 'Untrusted plugin hook name is escaped' );
$processes = file_get_contents( dirname( __DIR__ ) . '/includes/class-taxocommerce-system-check-processes.php' );
$server = file_get_contents( dirname( __DIR__ ) . '/includes/system-check/seo-system-server-status.php' );
tcs_queue_assert( false !== strpos( $processes, 'TaxoCommerce_System_Check_Queue_Health::render_cron_rows();' ), 'Metrics are inside the Processes tab' );
tcs_queue_assert( false !== strpos( $processes, 'TaxoCommerce_System_Check_Queue_Health::render_scheduler_rows();' ), 'Both queues are monitored in Procesos' );
tcs_queue_assert( false === strpos( $server, 'TaxoCommerce_System_Check_Queue_Health::' ), 'The Sistema dashboard is not extended or duplicated' );
tcs_queue_assert( false === strpos( $processes, "function kill_process" ), 'No OS process termination introduced' );
echo "System Check queue health regression: WP-Cron/Action Scheduler metrics, isolation and read-only PASS\n";
