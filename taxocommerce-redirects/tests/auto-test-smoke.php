<?php
/**
 * Pure CLI regression for Redirects Auto Test comparison and no-write UAT.
 * No live WordPress, MySQL, HTTP or filesystem writes required.
 */
namespace TaxoCommerce\Redirects;
define( 'ABSPATH', __DIR__ );
final class Plugin { const VERSION = '0.1.23'; }
function home_url( $path = '/' ) { return 'https://example.test' . $path; }
function get_current_blog_id() { return 1; }
require dirname( __DIR__ ) . '/includes/class-auto-test.php';
function assertion( $ok, $label ) {
    if ( ! $ok ) {
        echo 'FAIL Auto Test: ' . $label . PHP_EOL;
        exit( 1 );
    }
}
$cases = Auto_Test::cases();
$groups = Auto_Test::groups();
assertion( count( $groups ) === 8, 'Eight test groups' );
assertion( count( $cases ) === 26, 'Twenty-six checks' );
foreach ( $cases as $id => $case ) {
    $method = new \ReflectionMethod( Auto_Test::class, 'check_' . $id );
    assertion( $method->isStatic() && isset( $groups[ $case[0] ] ), 'Check defined and grouped: ' . $id );
}
$report = Auto_Test::suite( 'uat' );
assertion( 4 === $report['totals']['skip'] && 22 === $report['totals']['not_run'], 'External test remains unverified' );
assertion( 0 === $report['totals']['pass'] && 0 === $report['totals']['fail'], 'No invented success' );
assertion( Plugin::VERSION === $report['version'], 'Recorded version' );
assertion( hash( 'sha256', 'https://example.test/' ) === $report['site_hash'], 'Bound to site' );
$before = array(
    'site_hash' => 'site', 'phase' => 'before', 'group' => 'all',
    'rows' => array(
        array( 'id' => 'engine', 'status' => 'pass' ),
        array( 'id' => 'schema', 'status' => 'fail' ),
        array( 'id' => 'old', 'status' => 'pass' ),
        array( 'id' => 'unverified', 'status' => 'pass' ),
    ),
);
$after = array(
    'site_hash' => 'site', 'phase' => 'after', 'group' => 'all',
    'rows' => array(
        array( 'id' => 'engine', 'status' => 'fail' ),
        array( 'id' => 'schema', 'status' => 'pass' ),
        array( 'id' => 'unverified', 'status' => 'skip' ),
        array( 'id' => 'new', 'status' => 'fail' ),
    ),
);
$comparison = Auto_Test::compare( $before, $after );
assertion( 2 === count( $comparison['new_failures'] ), 'New regression detected' );
assertion( 1 === count( $comparison['resolved'] ), 'Resolved issue detected' );
assertion( 2 === count( $comparison['lost_coverage'] ), 'Lost and skipped coverage detected' );
assertion( 1 === count( $comparison['new_checks'] ), 'New tests tracked' );
$after['site_hash'] = 'other';
assertion( null === Auto_Test::compare( $before, $after ), 'Reject cross-site comparison' );
$after['site_hash'] = 'site';
$after['phase'] = 'check';
assertion( null === Auto_Test::compare( $before, $after ), 'Only AFTER compares' );
$after['phase'] = 'after';
$before['group'] = 'engine';
assertion( null === Auto_Test::compare( $before, $after ), 'Reject partial baselines' );
$view_source = file_get_contents( dirname( __DIR__ ) . '/includes/class-auto-test.php' );
assertion( false !== strpos( $view_source, "self::run_button( 'all', 'check', __( 'Ejecutar Autocheck'" ), 'Single one-click functional check' );
assertion( false !== strpos( $view_source, "self::export_button( false, __( 'Descargar resultados (JSON)'" ), 'Latest JSON export visible' );
assertion( false === strpos( $view_source, "self::run_button( 'all', 'before'" ), 'No BEFORE button' );
assertion( false === strpos( $view_source, "self::run_button( 'all', 'after'" ), 'No AFTER button' );
assertion( false === strpos( $view_source, 'self::run_button( $group, \'check\'' ), 'No per-group execution buttons' );
assertion( false !== strpos( $view_source, 'TaxoCommerce_Redirects_Autotest_Code::render_subtabs' ), 'Code Check subtab kept' );
echo 'Redirects Auto Test smoke passed.' . PHP_EOL;

/* Shared family report contract: new and previously saved check records. */
$legacy_report = array(
    'version' => '0', 'executed_at' => '2026-11-14T12:00:00+00:00',
    'rows' => array( array(
        'id' => 'test_case', 'group' => 'startup', 'name' => 'Legacy case',
        'detail' => 'Legacy detail', 'status' => 'pass', 'ms' => 2,
    ) ),
);
$canonical_report = Auto_Test::standardize_report( $legacy_report );
assertion( isset( $canonical_report['rows'][0]['group_id'], $canonical_report['rows'][0]['group_name'], $canonical_report['rows'][0]['label'], $canonical_report['rows'][0]['message'], $canonical_report['rows'][0]['ms'] ), 'Unified row fields' );
assertion( 'Legacy case' === $canonical_report['rows'][0]['label'] && 'Legacy detail' === $canonical_report['rows'][0]['message'], 'Historical text retained' );
assertion( 'Legacy case' === $canonical_report['rows'][0]['name'] && 'Legacy detail' === $canonical_report['rows'][0]['detail'], 'Legacy keys retained' );
assertion( isset( $canonical_report['wordpress'], $canonical_report['php'], $canonical_report['date'], $canonical_report['executed_at'], $canonical_report['timestamp'] ), 'Unified report metadata' );
assertion( false !== json_encode( $canonical_report ), 'Report JSON encodes' );
