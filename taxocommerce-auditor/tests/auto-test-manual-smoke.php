<?php
/**
 * Auditor Auto Test and Manual offline smoke. It runs the existing native parity
 * fixtures first, then checks admin contracts without WordPress or the filesystem.
 * Kept in tests/; excluded from all installed plugin packages.
 */
require __DIR__ . '/smoke.php';

define( 'DAY_IN_SECONDS', 86400 );
$GLOBALS['tca_hooks'] = array();
$GLOBALS['tca_options'] = array();
$GLOBALS['tca_written'] = 0;

function add_filter( $hook, $callback, $priority = 10, $args = 1 ) { return true; }
function add_action( $hook, $callback, $priority = 10, $args = 1 ) {
    $GLOBALS['tca_hooks'][ $hook ][] = $callback;
}
function has_action( $hook ) { return ! empty( $GLOBALS['tca_hooks'][ $hook ] ); }
function register_deactivation_hook( $file, $callback ) {}
function current_user_can( $permission ) { return 'manage_options' === $permission; }
function post_type_exists( $type ) { return 'product' === $type; }
function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function get_option( $name, $default = false ) { return $GLOBALS['tca_options'][ $name ] ?? $default; }
function update_option( $name, $value ) {
    ++$GLOBALS['tca_written'];
    return false;
}
function current_time( $format, $gmt = false ) { return '2026-10-31 22:30:00'; }
function get_transient( $name ) { return false; }
function wp_next_scheduled( $hook, $args = array() ) { return false; }
function get_current_user_id() { return 1; }
function admin_url( $path ) { return '/wp-admin/' . $path; }
// Autotest's new read-only sub-tabs use standard WordPress URL helpers.
function remove_query_arg( $key, $url = '/wp-admin/admin.php?page=taxocommerce-auditor' ) {
    return $url;
}
function add_query_arg( $key, $value = null, $url = '' ) {
    return ( $url ?: '/wp-admin/admin.php?page=taxocommerce-auditor' ) . '&' . rawurlencode( $key ) . '=' . rawurlencode( (string) $value );
}
function esc_html( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $value ) { return esc_html( $value ); }
function esc_url( $value ) { return esc_html( $value ); }
function esc_html__( $message, $domain = '' ) { return $message; }
function wp_nonce_field( $action, $name = '_wpnonce' ) {
    echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="test">';
}
function submit_button( $label, $type = 'primary', $name = 'submit', $wrap = true ) {
    echo '<button type="submit">' . esc_html( $label ) . '</button>';
}
function wp_die( $message ) { throw new \RuntimeException( (string) $message ); }

require dirname( __DIR__ ) . '/taxocommerce-auditor.php';
\TaxoCommerce\Auditor\Plugin::boot();

use TaxoCommerce\Auditor\Auto_Test;
use TaxoCommerce\Auditor\Manual;
use TaxoCommerce\Auditor\Plugin;

verify( preg_match( '/^\\d+\\.\\d+\\.\\d+$/', Plugin::VERSION ) === 1, 'Plugin reports a valid semantic version' );
verify( false !== strpos( file_get_contents( dirname( __DIR__ ) . '/taxocommerce-auditor.php' ), 'Version: ' . Plugin::VERSION ), 'Version constant matches installed plugin header' );
verify( has_action( 'admin_post_' . Auto_Test::ACTION ), 'Auto Test action registered' );
verify( has_action( \TaxoCommerce\Auditor\Runner::HOOK ), 'Existing cron hook remains registered' );
verify( has_action( 'wp_ajax_taxocommerce_auditor_watch' ), 'Browser recovery AJAX hook registered' );
verify( count( Auto_Test::cases() ) === 16, '16 functional checks' );

$report = Auto_Test::run_suite();
verify( $report['totals']['pass'] === 15, 'All 15 safe checks pass with healthy fixtures' );
verify( $report['totals']['fail'] === 0 && $report['totals']['skip'] === 1, 'Destructive actions skipped, not passed' );
verify( count( $report['rows'] ) === 16, 'Every check emits one result' );
verify( $report['version'] === Plugin::VERSION && $report['ms'] >= 0, 'Timestamp/version/time reported' );
verify( $GLOBALS['tca_written'] === 0, 'No WordPress option mutated by suite' );

ob_start();
Auto_Test::render();
$auto = ob_get_clean();
verify( strpos( $auto, 'Ejecutar Autocheck' ) !== false, 'Auto Test renders run button' );
verify( strpos( $auto, 'taxocommerce_auditor_auto_nonce' ) !== false, 'Run form nonce present' );

ob_start();
Manual::render();
$manual = ob_get_clean();
foreach ( array(
    'Manual de usuario', 'Manual técnico', 'auditoría completa',
    'Productos', 'FAQs', 'JSON Lines', 'WP-Cron', 'manage_options', 'Auto Test',
) as $word ) {
    verify( strpos( $manual, $word ) !== false, 'Manual covers ' . $word );
}

$admin = file_get_contents( dirname( __DIR__ ) . '/includes/class-admin.php' );
verify( strpos( $admin, "'auto-test' => __( 'Autocheck', 'taxocommerce-auditor' )" ) !== false, 'Auto Test tab wired' );
verify( strpos( $admin, "'manual' => __( 'Manual', 'taxocommerce-auditor' )" ) !== false, 'Manual tab wired' );
verify( strpos( $admin, 'self::render_scope_controls( $selected )' ) !== false, 'Five legacy scope controls unchanged' );
verify( strpos( $admin, "'categories' === \$scope" ) !== false, 'Category-only report view still available' );

// The five scopes share one UI. They never require a manual next-batch click.
$controls = explode( 'public static function render()', explode( 'private static function render_scope_controls(', $admin )[1] )[0];
verify( false === strpos( $controls, "self::action_form( \$scope, 'step'" ), 'No manual next-batch button in any audit scope' );
verify( false !== strpos( $controls, "self::action_form( \$scope, 'pause'" ), 'Pause remains available to running scopes' );
verify( false !== strpos( $controls, "self::action_form( \$scope, 'resume'" ), 'Resume remains available to paused scopes' );
verify( false !== strpos( $controls, "if ( ! in_array( \$status, array( 'queued', 'running', 'paused' ), true ) )" ), 'A running or paused audit cannot offer another start button' );
verify( false !== strpos( $admin, 'self::enqueue_monitor();' ), 'The footer AJAX worker is enqueued by the admin hook' );
$render_boot = explode( 'private static function edit_url(', explode( 'public static function render()', $admin )[1] )[0];
verify( false !== strpos( $render_boot, 'self::enqueue_monitor();' ), 'Audit render re-enqueues the worker for custom Core menu hooks' );
verify( false !== strpos( $admin, 'private static $monitor_enqueued = false' ), 'The worker cannot be duplicated by two enqueue entry points' );

// All five scopes must have independent speeds and preserve existing batches.
$scopes = array_keys( \TaxoCommerce\Auditor\Runner::scopes() );
$workload = '\\TaxoCommerce\\Auditor\\Workload';
foreach ( $scopes as $scope ) {
    verify( 3 === $workload::speed( $scope ), 'Every scope defaults to normal speed' );
}
$size_previous = 0;
$delay_previous = PHP_INT_MAX;
$client_previous = PHP_INT_MAX;
foreach ( array( 1, 2, 3, 4, 5 ) as $speed ) {
    $GLOBALS['tca_options'][ $workload::OPTION ] = array( 'products' => $speed );
    $size = $workload::batch_size( 'products' );
    $delay = $workload::delay_seconds( 'products' );
    $client = $workload::client_delay_ms( 'products' );
    verify( $size > $size_previous && $size <= \TaxoCommerce\Auditor\Runner::BATCH_SIZE, 'Increasing speed raises bounded batch size' );
    verify( $delay < $delay_previous && $delay >= 12, 'Increasing speed lowers optional cron spacing' );
    verify( $client < $client_previous && $client >= 300, 'Increasing speed shortens independent browser chaining delay' );
    verify( $workload::budget_seconds( 'products' ) >= 1, 'Request budget remains bounded' );
    verify( 3 === $workload::speed( 'pages' ), 'Changing products never changes pages' );
    $size_previous = $size;
    $delay_previous = $delay;
    $client_previous = $client;
}
verify( ! $workload::set_speed( 'products', array( 5 ) ), 'Array input rejected' );
verify( ! $workload::set_speed( 'products', '6' ), 'Speed outside range rejected' );
verify( ! $workload::set_speed( 'unknown', '3' ), 'Unknown scope rejected' );
verify( $workload::set_speed( 'products', '2' ), 'Valid speed saved through the protected entry point' );
// The read-only test stub counts writes but does not persist them.
$GLOBALS['tca_options'][ $workload::OPTION ] = array( 'products' => 2 );
verify( 3 === $workload::speed( 'pages' ), 'Independent scope keeps its saved speed' );
verify( 12 === $workload::effective_batch_size( 'products' ), 'Legacy audit starts conservatively under a 90-record speed ceiling' );
verify( 4 === $workload::effective_batch_size( 'products', array( 'adaptive_batch_size' => 4 ) ), 'Adaptive cap persists without resetting the cursor' );
verify( 90 === $workload::effective_batch_size( 'products', array( 'adaptive_batch_size' => 500 ) ), 'Adaptive cap never exceeds level-two speed' );
verify( 1 === $workload::effective_batch_size( 'products', array( 'adaptive_batch_size' => 0 ) ), 'Adaptive cap never becomes zero' );
verify( 7 === $workload::adjusted_batch_size( 'products', 5, 200, 25, 5 ), 'Fast PHP batch grows within selected maximum' );
verify( 6 === $workload::adjusted_batch_size( 'products', 10, 3000, 35, 10 ), 'Slow batch reduces work when budget is reached' );
verify( 6 === $workload::adjusted_batch_size( 'products', 10, 200, 85, 10 ), 'Memory pressure reduces work despite fast response' );
verify( 7 === $workload::adjusted_batch_size( 'products', 7, 200, 20, 0 ), 'No-progress batch cannot accelerate' );
verify( 6 === $workload::adjusted_batch_size( 'products', 10, 200, 25, 10, 2800 ), 'Heavy PHP CPU reduces load even when wall time is low' );
verify( 13 === $workload::adjusted_batch_size( 'products', 10, 200, 25, 10, 100 ), 'Low PHP CPU permits gradual acceleration' );
verify( null === $workload::process_cpu_ms() || is_int( $workload::process_cpu_ms() ), 'Optional CPU measurement is typed and non-destructive' );
$GLOBALS['tca_options'][ $workload::OPTION ] = array( 'products' => 5 );
verify( 500 === $workload::batch_size( 'products' ), 'Level five supports up to 500 objects' );
verify( 63 === $workload::effective_batch_size( 'products' ), 'Level five begins with a memory-safe 63-object query' );
$GLOBALS['tca_options'][ $workload::OPTION ] = array( 'products' => 2 );
verify( 3 === $workload::speed( 'pages' ), 'Adaptive calculations do not affect other scopes' );
$runner_source = file_get_contents( dirname( __DIR__ ) . '/includes/class-runner.php' );
verify( strpos( $runner_source, '$processed_batch' ) !== false, 'Category offset tracks only processed objects under time budgets' );
verify( strpos( $runner_source, '$requested_batch' ) !== false, 'Completion compares the batch limit captured before fetching' );
verify( false !== strpos( $runner_source, "Workload::effective_batch_size( \$scope, \$state )" ), 'Runner applies per-scope adaptive batch cap' );
verify( false !== strpos( $runner_source, "Workload::adjusted_batch_size(" ), 'Runner adjusts the next batch after the previous one' );
verify( false !== strpos( $runner_source, "Workload::memory_pressure_percent()" ), 'Runner uses PHP memory pressure as a safety signal' );
verify( false !== strpos( $runner_source, "Workload::process_cpu_ms()" ), 'Runner measures PHP CPU time for each batch' );
verify( false !== strpos( $runner_source, "'last_batch_duration_ms'" ), 'Runner stores real batch duration for progress' );
verify( strpos( $admin, "wp_nonce_field( 'taxocommerce_auditor_action_' . \$scope )" ) !== false, 'Workload uses existing scope-specific nonce' );
verify( strpos( $admin, 'Workload::set_speed( $scope, $raw_speed )' ) !== false, 'Workload update routes through validated controls' );

// Cron recovery must respect per-scope speed, preserve cursor and never run paused scopes.
$runner = '\\TaxoCommerce\\Auditor\\Runner';
$now = time();
$GLOBALS['tca_options']['taxocommerce_auditor_state_products'] = array(
    'status' => 'running', 'processed' => 200, 'total' => 28426,
    'cursor' => 200, 'started_timestamp' => $now - 300,
    'last_batch_timestamp' => $now - 120,
);
$GLOBALS['tca_options']['taxocommerce_auditor_state_categories'] = array(
    'status' => 'queued', 'processed' => 0, 'total' => 450,
    'cursor' => 0, 'started_timestamp' => $now - 300,
);
verify( $runner::browser_batch_due( 'products' ), 'Stalled product audit can be rescued' );
verify( $runner::browser_batch_due( 'categories' ), 'Stalled category audit can be rescued independently' );
$progress = $runner::progress( 'products' );
verify( 200 === $progress['processed'] && 28426 === $progress['total'], 'Progress preserves the live 200/28426 state' );
verify( 0 === $runner::progress( 'categories' )['processed'], 'Category scope starts at its own cursor' );
verify( 60 === $progress['speed_delay_seconds'], 'Optional WP-Cron keeps the configured product speed' );
verify( 3500 === $progress['client_delay_ms'], 'Chained AJAX uses its own level-two delay, independently of WP-Cron' );
verify( 3000 === $progress['batch_budget_ms'], 'Browser receives the selected server-side budget' );
verify( 12 === $progress['effective_batch_size'], 'Progress exposes the conservative level-two adaptive cap' );
verify( 0 === $progress['batch_duration_ms'], 'Existing audit without telemetry remains compatible' );
verify( null === $progress['batch_cpu_ms'], 'Existing audit has no fictional CPU reading' );
verify( 0 === $progress['next_event_timestamp'], 'Missing WP-Cron event is diagnosed without a fatal error' );
verify( false === $runner::browser_batch_due( 'unknown' ), 'Unknown scope cannot execute work' );
$GLOBALS['tca_options']['taxocommerce_auditor_state_products']['last_batch_timestamp'] = $now;
verify( ! $runner::browser_batch_due( 'products' ), 'Browser cannot bypass the minimum inter-batch delay' );
$GLOBALS['tca_options']['taxocommerce_auditor_state_products']['status'] = 'paused';
verify( ! $runner::browser_batch_due( 'products' ), 'Pausing prevents browser-triggered work' );
verify( false !== strpos( $runner_source, 'add_option( $worker_option' ), 'Cron and browser share an atomic worker lock' );
verify( false !== strpos( $runner_source, 'delete_option( $worker_option )' ), 'Worker lock is released on exit' );
verify( false !== strpos( $admin, "check_ajax_referer( 'taxocommerce_auditor_watch', 'nonce' )" ), 'Browser recovery verifies nonce' );
$watch_handler = explode( 'public static function handle_action()', explode( 'public static function handle_watch()', $admin )[1] )[0];
verify( false !== strpos( $watch_handler, 'Runner::run_batch( $scope );' ), 'Every authenticated browser request executes one bounded batch' );
verify( false === strpos( $watch_handler, 'browser_batch_due' ), 'Browser worker never waits for WP-Cron due timestamp' );
verify( false !== strpos( $watch_handler, 'batch_processed' ), 'Chaining receives actual batch progress' );
verify( false !== strpos( $watch_handler, "wp_create_nonce( 'taxocommerce_auditor_watch' )" ), 'Long scans renew authenticated AJAX nonce' );
verify( false !== strpos( $admin, "self::guard();" ), 'Browser recovery checks administrator capabilities' );
$browser = file_get_contents( dirname( __DIR__ ) . '/assets/admin-watch.js' );
verify( false !== strpos( $browser, 'nextDelay = Math.min(60000,' ), 'Failed AJAX requests back off' );
verify( false !== strpos( $browser, 'schedule(nextDelay)' ), 'Next browser batch follows the response' );
verify( false !== strpos( $browser, 'data.client_delay_ms' ), 'Browser speed, not WP-Cron delay, governs the next batch' );
verify( false !== strpos( $browser, 'data.batch_duration_ms' ), 'Adaptive browser pacing reads real PHP batch duration' );
verify( false !== strpos( $browser, 'data.memory_pressure_percent' ), 'Adaptive browser pacing reads the PHP memory signal' );
verify( false !== strpos( $browser, 'data.batch_cpu_ms' ), 'Browser pacing reads measured PHP CPU time' );
verify( false === strpos( $admin, "'disabled' => __( 'WP-Cron" ), 'Auditor panel does not localize Cron warnings' );
verify( false === strpos( $browser, 'data.cron_disabled' ) && false === strpos( $browser, 'data.cron_overdue' ), 'UI never warns about irrelevant cron status' );
verify( false === strpos( $browser, 'data.speed_delay_seconds' ), 'Browser never waits for WP-Cron interval' );
verify( false !== strpos( $browser, "data.status === 'completed'" ), 'Completed audits automatically refresh the report' );
$code_source = file_get_contents( dirname( __DIR__ ) . '/includes/class-taxocommerce-autotest-code.php' );
$code_assets = explode( 'public static function speeds()', explode( 'public static function assets(', $code_source )[1] )[0];
verify( false === strpos( $code_assets, '$_GET' ), 'Code Check assets do not inspect GET without nonce' );

echo "Auditor Auto Test and Manual offline smoke passed.\n";

require __DIR__ . '/i18n-catalog-smoke.php';
