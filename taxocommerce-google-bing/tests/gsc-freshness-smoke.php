<?php
/**
 * Standalone read-only regression for GSC report snapshots and Analysis freshness.
 *
 * @package TaxoCommerceInsights
 */
define( 'ABSPATH', __DIR__ );
define( 'DAY_IN_SECONDS', 86400 );
function absint( $value ) { return abs( (int) $value ); }
function seo_google_finalized_end_date() { return '2026-11-18'; }
$GLOBALS['sql_date'] = '2026-11-08';
$GLOBALS['sql_rows'] = array(
    array( 'data_date' => '2026-11-08', 'clicks' => 3, 'impressions' => 144, 'position' => 35.4 ),
);
function seo_google_latest_data_date( $property ) { return $GLOBALS['sql_date']; }
function seo_google_get_summary_trend_data( $property, $days ) { return $GLOBALS['sql_rows']; }
function seo_analista_resolve_property_id() { return 'sc-domain:example.test'; }
class TaxoCommerce_Google_Bing_Reports {
    public static $saved = array();
    public static function saved_reports( $source ) { return 'search' === $source ? self::$saved : array(); }
}
function assert_fresh( $ok, $message ) {
    if ( !$ok ) { fwrite( STDERR, 'FAIL: ' . $message . PHP_EOL ); exit( 1 ); }
}
$make = static function( $property, $downloaded ) {
    return array(
        'downloaded_at' => $downloaded,
        'current' => array( 'start' => '2026-11-17', 'end' => '2026-11-18' ),
        'previous' => array( 'start' => '2026-11-15', 'end' => '2026-11-16' ),
        'data' => array(
            'source_property' => $property,
            'daily' => array(
                'current_clicks' => array( 4, 6 ),
                'current_impressions' => array( 130, 180 ),
                'current_position' => array( 30.0, 25.0 ),
                'previous_clicks' => array( 2, 3 ),
                'previous_impressions' => array( 60, 80 ),
                'previous_position' => array( 40.0, 35.0 ),
            ),
        ),
    );
};
TaxoCommerce_Google_Bing_Reports::$saved = array( $make( 'sc-domain:example.test', 200 ) );
require dirname( __DIR__ ) . '/includes/analysis/analista-evolucion.php';
require dirname( __DIR__ ) . '/includes/class-taxocommerce-google-bing-auto-test.php';
$health = taxocommerce_insights_gsc_trend_health( 'sc-domain:example.test', 120 );
assert_fresh( '2026-11-18' === $health['snapshot_date'], 'saved report latest date' );
assert_fresh( '2026-11-08' === $health['sql_date'], 'historical SQL date' );
assert_fresh( 4 === count( $health['snapshot_rows'] ), 'current and previous days retained' );
$rows = seo_analista_trend_series( 120 );
assert_fresh( '2026-11-18' === end( $rows )['data_date'], 'chart advances to saved data date' );
assert_fresh( 6.0 === (float) end( $rows )['clicks'], 'chart displays latest saved value' );
$method = new ReflectionMethod( 'TaxoCommerce_Google_Bing_Auto_Test', 'check_gsc_freshness' );
$method->setAccessible( true );
$outcome = $method->invoke( null );
assert_fresh( 'fail' === $outcome['status'] && false !== strpos( $outcome['message'], '2026-11-08' ), 'autocheck explains stale SQL' );
TaxoCommerce_Google_Bing_Reports::$saved[] = $make( 'sc-domain:another.test', 300 );
$health = taxocommerce_insights_gsc_trend_health( 'sc-domain:example.test', 120 );
assert_fresh( 1 === $health['other_snapshots'], 'different property isolated' );
assert_fresh( '2026-11-18' === $health['snapshot_date'], 'mismatched property not mixed' );
$GLOBALS['sql_date'] = '2026-11-18';
$GLOBALS['sql_rows'][] = array( 'data_date' => '2026-11-17', 'clicks' => 19, 'impressions' => 200, 'position' => 9.0 );
$GLOBALS['sql_rows'][] = array( 'data_date' => '2026-11-18', 'clicks' => 20, 'impressions' => 210, 'position' => 8.0 );
$rows = seo_analista_trend_series( 120 );
assert_fresh( 20.0 === (float) end( $rows )['clicks'], 'SQL takes priority for overlapping days' );
assert_fresh( 'pass' === $method->invoke( null )['status'], 'autocheck accepts coherent and recent data' );
TaxoCommerce_Google_Bing_Reports::$saved = array();
$GLOBALS['sql_date'] = '';
$GLOBALS['sql_rows'] = array();
assert_fresh( 'skip' === $method->invoke( null )['status'], 'no-data installation is not falsely failed' );
echo 'Insights GSC freshness regression passed.' . PHP_EOL;
