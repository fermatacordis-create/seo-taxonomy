<?php
/**
 * Independent Code Check contract for one vendored TaxoCommerce plugin.
 *
 * Run: php .github/tests/autotest-code-vendor-smoke.php images
 */
if ( 'cli' !== PHP_SAPI || ! isset( $argv[1] ) ) {
    exit( 1 );
}
define( 'ABSPATH', '/' );
function __( $message, $domain = '' ) { return $message; }
function absint( $value ) { return abs( (int) $value ); }
function smoke_expect( $ok, $message ) {
    if ( ! $ok ) {
        fwrite( STDERR, "FAIL: " . $message . "\n" );
        exit( 1 );
    }
}
$slug = preg_replace( '/[^a-z0-9-]/', '', strtolower( (string) $argv[1] ) );
$root = dirname( __DIR__, 2 ) . '/taxocommerce-' . $slug;
$main = $root . '/taxocommerce-' . $slug . '.php';
$vendor = $root . '/includes/class-taxocommerce-autotest-code.php';
$asset = $root . '/assets/autotest-code.js';
smoke_expect( is_file( $main ) && is_file( $vendor ) && is_file( $asset ), 'Complete standalone package: ' . $slug );
$source = file_get_contents( $vendor );
smoke_expect( 1 === preg_match( '/final class ([A-Za-z0-9_]+)/', $source, $match ), 'Unique checker class.' );
smoke_expect( false === strpos( $source, '%%' ), 'No unexpanded template markers.' );
smoke_expect( false === strpos( $source, 'self::DOMAIN )' ), 'Literal gettext textdomain required.' );
smoke_expect( false !== strpos( file_get_contents( $main ), 'class-taxocommerce-autotest-code.php' ), 'Plugin entrypoint loads own Code Check.' );
require_once $vendor;
$class = $match[1];
smoke_expect( method_exists( $class, 'render_subtabs' ) && method_exists( $class, 'analyze_source' ), 'Common public API.' );
$valid = $class::analyze_source( "<?php defined('ABSPATH') || exit; class HealthySample {}", 'includes/example.php', realpath( $root ) );
smoke_expect( 'pass' === $valid['php']['status'], 'Valid PHP syntax must pass.' );
$invalid = $class::analyze_source( '<?php function broken( {', 'includes/example.php', realpath( $root ) );
smoke_expect( 'fail' === $invalid['php']['status'], 'Invalid PHP syntax must fail.' );
$risky = $class::analyze_source( '<?php eval("echo 1;"); $_POST["q"];', 'includes/example.php', realpath( $root ) );
smoke_expect( 'warning' === $risky['security']['status'], 'Potential risk must be reported.' );
// Core 0.4.82 parity: native WordPress.org review signals and JSON severity contract.
$review = $class::analyze_source(
    "<?php defined( 'ABSPATH' ) || exit; function taxocommerce_sample() { session_start(); }",
    'includes/example.php',
    realpath( $root )
);
$rules = array_column( $review['php']['evidence'], 'rule' );
smoke_expect( in_array( 'review_php_session', $rules, true ), 'Expanded native code rules detect PHP sessions.' );
smoke_expect( method_exists( $class, 'summary' ) && method_exists( $class, 'classified_findings' ), 'Core compatible summary and classified results.' );
$pass = array( 'status' => 'pass', 'evidence' => array() );
$warning = array( 'status' => 'warning', 'evidence' => array(
    array( 'rule' => 'review_request_nonce', 'line' => 12 ),
) );
$state = array(
    'status' => 'completed',
    'files' => array( 'clean.php', 'review.php' ),
    'results' => array(
        'clean.php' => array( 'php' => $pass, 'wordpress' => $pass, 'security' => $pass ),
        'review.php' => array( 'php' => $pass, 'wordpress' => $warning, 'security' => $pass ),
    ),
    'inventory_truncated' => false,
);
$summary = $class::summary( $state );
$classified = $class::classified_findings( $state );
smoke_expect( 'review' === $summary['status'] && 1 === $summary['findings'], 'A PHP pass must not conceal a WordPress warning.' );
smoke_expect( 1 === $classified['counts']['warnings'] && 1 === $classified['counts']['clean'] &&
    0 === $classified['counts']['errors'] && 0 === $classified['counts']['unchecked'],
    'Code Check sections retain the Core 0.4.82 JSON contract.' );
smoke_expect( 12 === $classified['warnings'][0]['line'] &&
    ! empty( $classified['warnings'][0]['recommendation'] ),
    'Warnings include source line and recommended action.' );
$state['status'] = 'running';
$state['files'][] = 'not-scanned.php';
smoke_expect( 'incomplete' === $class::summary( $state )['status'] &&
    1 === $class::classified_findings( $state )['counts']['unchecked'],
    'Unfinished inventories must never be reported clean.' );
if ( 'core' !== $slug ) {
    $rules_path = $root . '/includes/class-taxocommerce-code-review-rules.php';
    smoke_expect( is_file( $rules_path ) &&
        false !== strpos( $source, "require_once __DIR__ . '/class-taxocommerce-code-review-rules.php';" ),
        'Native rules must be bundled inside each independent plugin.' );
    smoke_expect( is_file( $root . '/assets/autotest-code.css' ) &&
        false !== strpos( $source, 'wp_enqueue_style(' ),
        'Each plugin enqueues its own severity styles.' );
}

$files = $class::files();
smoke_expect( in_array( 'taxocommerce-' . $slug . '.php', $files, true ), 'Only own plugin tree is inventoried.' );
smoke_expect( count( $class::speeds() ) === 5, 'Five bounded CPU speeds.' );
echo 'PASS standalone Autotest Code Check: ' . $slug . "\n";
