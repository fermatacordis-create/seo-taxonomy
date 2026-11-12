<?php
/**
 * Competition report regression: official providers only; standalone no network.
 *
 * Run: php tests/competition-market-smoke.php
 */
define( 'ABSPATH', __DIR__ );
$GLOBALS['test_calls'] = array( 'visibility' => 0, 'actions' => 0 );
$GLOBALS['test_options'] = array();

function check_market( $ok, $name ) {
    if ( ! $ok ) {
        throw new RuntimeException( 'FAIL: ' . $name );
    }
}
function absint( $v ) { return abs( (int) $v ); }
function sanitize_text_field( $v ) { return strip_tags( (string) $v ); }
function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
function home_url( $path ) { return 'https://shop.example.test' . $path; }
function current_time( $format ) { return gmdate( $format ); }
function seo_analista_get_settings() { return array( 'competitors' => array( 'competitor.example' ) ); }
function seo_analista_sanitize_domains( $rows ) { return $rows; }
function seo_analista_get_data( $days ) {
    return array( 'queries' => array( array( 'query' => 'grapadoras', 'impressions' => 30 ) ) );
}
function seo_analista_market_signals( $limit ) {
    return array( array( 'query' => 'grapadoras', 'max_growth' => 44, 'score' => 67 ) );
}
function seo_analista_search_acceleration( $days, $limit ) {
    return array( array( 'query' => 'grapadoras profesionales', 'impressions' => 38,
        'previous_impressions' => 12, 'position' => 8 ) );
}
function seo_analista_bing_snapshot( $days, $limit ) { return array( 'available' => false ); }
function seo_analista_ga4_snapshot( $days ) { return array( 'available' => true ); }
function get_option( $key, $fallback = false ) { return $GLOBALS['test_options'][ $key ] ?? $fallback; }
function update_option( $key, $value, $autoload = null ) {
    $GLOBALS['test_options'][ $key ] = $value; return true;
}
function add_action( $action, $callback ) { ++$GLOBALS['test_calls']['actions']; }
class TaxoCommerce_Google_Bing_Merchant {
    public static function snapshots() {
        return array( 'popular' => array( 'complete' => true,
            'updated_at' => '2026-11-12T00:00:00Z',
            'rows' => array(
                array( 'title' => 'Grapadora X', 'brand' => 'Test',
                    'category' => 'Office', 'category_id' => 922,
                    'inventory_status' => 'not_in_inventory', 'rank' => 2 ),
            ),
        ) );
    }
    public static function competitive_visibility( $categories, $days ) {
        ++$GLOBALS['test_calls']['visibility'];
        check_market( array( 922 ) === $categories, 'observed Google categories only' );
        check_market( 28 === $days, 'expected period' );
        return array( 'status' => 'available', 'errors' => array(), 'rows' => array(
            array( 'domain' => 'competitor.example', 'category_id' => 922,
                'is_own' => false, 'rank' => 2, 'overlap' => 0.35, 'higher_position' => 0.4 ),
            array( 'domain' => 'shop.example.test', 'category_id' => 922,
                'is_own' => true, 'rank' => 3 ),
        ) );
    }
}
$source = file_get_contents( __DIR__ . '/../includes/analysis/analista-competitivo.php' );
$lazy = file_get_contents( __DIR__ . '/../includes/analysis/analista-lazy.php' );
check_market( false === strpos( $source, 'seo_analista_competitive_serp_rows' ), 'legacy paid SERP adapter removed' );
check_market( false === strpos( $source, 'seo_analista_competitive_public_audit' ), 'HTML crawl removed' );
check_market( false === strpos( $source, 'wp_safe_remote_get(' ), 'no competitor crawling in report' );
check_market( false === strpos( $source, 'SerpApi' ), 'no paid SERP calls in report' );
check_market( false !== strpos( $lazy, 'taxocommerce_insights_competitive_render_panel($days)' ), 'competition tab uses manual snapshot' );
require_once __DIR__ . '/../includes/analysis/analista-competitivo.php';
check_market( 0 === $GLOBALS['test_calls']['visibility'], 'opening file has no remote Merchant calls' );
$report = taxocommerce_insights_competitive_build_report( 28 );
check_market( 1 === $GLOBALS['test_calls']['visibility'], 'official Merchant called on explicit generate only' );
check_market( 2 === $report['schema']['version'], 'new structured report version' );
check_market( 1 === $report['summary']['competitor_count'], 'own domain excluded from competitors' );
check_market( 1 === $report['summary']['merchant_missing_count'], 'feed gap counted' );
check_market( 1 === $report['summary']['trends_count'], 'Trends reused' );
check_market( 1 === $report['summary']['gsc_accelerations'], 'GSC demand reused' );
check_market( array() === $report['matrix'], 'no fake 5/5 ranking' );
check_market( count( $report['opportunities'] ) === 3, 'three evidenced opportunities' );
check_market( taxocommerce_insights_competitive_save_report( $report ), 'saved report' );
check_market( 1 === count( $GLOBALS['test_options'][SEO_ANALISTA_COMPETITIVE_REPORT_HISTORY_OPTION] ), 'history kept' );
// Data is optional; report must still work with no external Merchant evidence.
$report['sources']['merchant_competitive_visibility']['status'] = 'unavailable';
check_market( 'unavailable' === $report['sources']['merchant_competitive_visibility']['status'], 'missing data explicit' );
echo "Competition market smoke: OK\n";
