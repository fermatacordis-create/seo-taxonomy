<?php
/**
 * Full Analysis export GSC source freshness and Autocheck smoke tests.
 *
 * @package TaxoCommerceInsights
 */
define( 'ABSPATH', __DIR__ );
define( 'DAY_IN_SECONDS', 86400 );
function add_action( $hook, $callback ) { return true; }
function absint( $value ) { return abs( (int) $value ); }
function __( $value, $domain = '' ) { return $value; }
class TaxoCommerce_Connections_Status {
    public static function definitions() { return array(); }
}
function seo_analista_resolve_property_id() { return $GLOBALS['gsc_property']; }
function seo_google_connection_status() { return $GLOBALS['gsc_connection']; }
function seo_google_sync_start_handler() {}
function seo_google_sync_day_handler() {}
function taxocommerce_insights_gsc_trend_health( $property, $days = 180 ) {
    return $GLOBALS['gsc_health'];
}
function seo_analista_period( $property, $days = 28 ) {
    return array( 'date_to' => $GLOBALS['gsc_report_date'] );
}
function must_pass( $condition, $label ) {
    if ( ! $condition ) { fwrite( STDERR, 'FAIL ' . $label . PHP_EOL ); exit( 1 ); }
}
$GLOBALS['gsc_property'] = 'sc-domain:example.test';
$GLOBALS['gsc_connection'] = 'connected';
$GLOBALS['gsc_report_date'] = '2026-11-08';
$GLOBALS['gsc_health'] = array(
    'sql_date' => '2026-11-08', 'snapshot_date' => '2026-11-18',
    'finalized_date' => '2026-11-18',
);
require dirname( __DIR__ ) . '/includes/analysis/analista-chunked-export.php';
require dirname( __DIR__ ) . '/includes/class-taxocommerce-google-bing-auto-test.php';
$state = TaxoCommerce_Insights_Chunked_Export::gsc_freshness_state();
must_pass( !empty($state['needs_sync']) && !empty($state['can_sync']), 'GSC old SQL triggers refresh' );
must_pass( '2026-11-18' === $state['target_date'], 'latest finalized/saved date is required' );
$method = new ReflectionMethod( 'TaxoCommerce_Google_Bing_Auto_Test', 'check_analyst_freshness' );
$method->setAccessible( true );
$outcome = $method->invoke( null );
must_pass( 'fail' === $outcome['status'] && false !== strpos( $outcome['message'], '2026-11-08' ), 'Autocheck exposes stale report date' );
$GLOBALS['gsc_health']['sql_date'] = '2026-11-18';
$GLOBALS['gsc_report_date'] = '2026-11-18';
$state = TaxoCommerce_Insights_Chunked_Export::gsc_freshness_state();
must_pass( empty($state['needs_sync']) && !empty($state['can_sync']), 'no repeated refresh when data current' );
must_pass( 'pass' === $method->invoke(null)['status'], 'report period validated after refresh' );
$GLOBALS['gsc_report_date'] = '2026-11-17';
$outcome = $method->invoke(null);
must_pass( 'fail' === $outcome['status'] && false !== strpos($outcome['message'], 'incoherente'), 'report period mismatch detected' );
$GLOBALS['gsc_health']['sql_date'] = '2026-11-08';
$GLOBALS['gsc_connection'] = 'not_authorized';
$state = TaxoCommerce_Insights_Chunked_Export::gsc_freshness_state();
must_pass( !empty($state['needs_sync']) && empty($state['can_sync']) && !empty($state['warning']), 'missing credential yields explicit warning' );
$GLOBALS['gsc_property'] = '';
$state = TaxoCommerce_Insights_Chunked_Export::gsc_freshness_state();
must_pass( empty($state['property_configured']) && empty($state['needs_sync']), 'site without Search Console remains independent' );
must_pass( 'skip' === $method->invoke(null)['status'], 'no-property Autocheck is skipped' );
$js = file_get_contents( dirname( __DIR__ ) . '/includes/analysis/assets/analista-lazy.js' );
$php = file_get_contents( dirname( __DIR__ ) . '/includes/analysis/analista-chunked-export.php' );
must_pass( false !== strpos($js, "'seo_google_sync_start'") && false !== strpos($js, "'seo_google_sync_day'"), 'use existing bounded GSC importer' );
must_pass( false !== strpos($js, "exportAjax('preflight'") && false !== strpos($php, "if ( 'preflight' === \$op )"), 'server/source preflight registered' );
must_pass( false !== strpos($php, "if ( \$gsc_health['needs_sync'] && \$gsc_health['can_sync'] )"), 'backend prevents stale full export' );
must_pass( isset(TaxoCommerce_Google_Bing_Auto_Test::cases()['analyst_freshness']), 'new case registered in Autocheck' );
echo 'Full report GSC refresh smoke passed.' . PHP_EOL;
