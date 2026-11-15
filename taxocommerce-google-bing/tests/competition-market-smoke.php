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
function sanitize_key( $v ) { return preg_replace( '/[^a-z0-9_\\-]/', '', strtolower( (string) $v ) ); }
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
    public static function account() { return '123456789'; }
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
$categories = taxocommerce_insights_competitive_google_categories();
check_market( array( 922 ) === $categories, 'category IDs sourced from Best Sellers' );
// Visibility is fetched via a separate authenticated request, not during render.
$signature = md5( '123456789|28|922' );
$GLOBALS['test_options']['taxocommerce_insights_competitive_visibility_progress_v1'] = array(
    'signature' => $signature,
    'status' => 'available',
    'complete' => true,
    'rows' => array(
        array( 'domain' => 'competitor.example', 'category_id' => 922,
            'is_own' => false, 'rank' => 2, 'overlap' => 0.35, 'higher_position' => 0.4 ),
        array( 'domain' => 'shop.example.test', 'category_id' => 922,
            'is_own' => true, 'rank' => 3 ),
    ),
    'errors' => array(),
);
$report = taxocommerce_insights_competitive_build_report( 28 );
check_market( 0 === $GLOBALS['test_calls']['visibility'], 'report generation never performs repeated Merchant requests' );
check_market( 'available' === $report['sources']['merchant_competitive_visibility']['status'], 'staged visibility reused' );
check_market( true === $report['sources']['merchant_competitive_visibility']['complete'], 'staged visibility marked complete' );
check_market( 2 === $report['schema']['version'], 'new structured report version' );
check_market( 1 === $report['summary']['competitor_count'], 'own domain excluded from competitors' );
check_market( array() === $report['configuration']['competitors'], 'no hardcoded competitors in new report' );
check_market( array() === $report['configuration']['suppliers'], 'no hardcoded suppliers' );
check_market( 'both' === $report['configuration']['mode'], 'default combined analysis' );
$actors = taxocommerce_insights_competitive_parse_actors( array( 'Empresa A | https://www.example.org/shop', 'Empresa A | example.org', 'Proveedor X' ) );
check_market( 2 === count( $actors ), 'free actors normalized and deduplicated' );
check_market( 'example.org' === $actors[0]['domain'], 'company domain normalized' );
check_market( '' === $actors[1]['domain'], 'supplier without domain is allowed' );
$GLOBALS['test_options']['taxocommerce_insights_competitive_settings_v1'] = array( 'mode' => 'current', 'scope' => 'all', 'competitors' => array( array( 'name' => 'Empresa A', 'domain' => 'competitor.example' ) ), 'suppliers' => array( array( 'name' => 'Proveedor X', 'domain' => '' ) ), 'signals' => array( 'popular', 'visibility' ) );
$configured_report = taxocommerce_insights_competitive_build_report( 28 );
check_market( 1 === count( $configured_report['selected_competitors'] ), 'explicit competitor list recorded' );
check_market( 'available' === $configured_report['selected_competitors'][0]['status'], 'merchant matches configured company domain' );
check_market( 'not_connected' === $configured_report['selected_suppliers'][0]['status'], 'supplier data not invented' );
check_market( 0 === count( $configured_report['market_context'] ), 'unchecked Trends not reported' );
check_market( 0 === count( $configured_report['own_acceleration'] ), 'unchecked GSC not reported' );
// Visibility-only selection must still use the official Google categories
// learned from Merchant, without displaying Best Sellers opportunities.
$GLOBALS['test_options']['taxocommerce_insights_competitive_settings_v1']['signals'] = array( 'visibility' );
$visibility_only = taxocommerce_insights_competitive_build_report( 28 );
check_market( 1 === $visibility_only['summary']['competitor_count'], 'visibility works when Best Sellers display is disabled' );
check_market( 0 === $visibility_only['summary']['merchant_popular_count'], 'unselected Best Sellers are not displayed' );
check_market( 'not_selected' === $visibility_only['sources']['merchant_best_sellers']['status'], 'unselected indicator clearly marked' );
check_market( 1 === $report['summary']['merchant_missing_count'], 'feed gap counted' );
check_market( 1 === $report['summary']['trends_count'], 'Trends reused' );
check_market( 1 === $report['summary']['gsc_accelerations'], 'GSC demand reused' );
check_market( array() === $report['matrix'], 'no fake 5/5 ranking' );
check_market( count( $report['opportunities'] ) === 3, 'three evidenced opportunities' );
check_market( taxocommerce_insights_competitive_save_report( $report ), 'saved report' );
check_market( ! isset( $GLOBALS['test_options']['seo_analista_competitive_report_v1'] ), 'legacy snapshot never overwritten' );
check_market( isset( $GLOBALS['test_options'][TAXOCOMMERCE_INSIGHTS_COMPETITIVE_REPORT_OPTION] ), 'Insights-owned snapshot saved' );
check_market( false === strpos( $source, "seo_analista_admin_url( array(" ), 'no redirect through monolith helper' );
check_market( false !== strpos( $source, "check_admin_referer( 'taxocommerce_insights_competition_generate'" ), 'separate generation nonce' );
check_market( false !== strpos( $source, "check_admin_referer( 'taxocommerce_insights_competition_export'" ), 'separate export nonce' );
check_market( false !== strpos( $source, "tgb_tab' => 'analysis'" ), 'post/redirect returns to Insights tab' );

check_market( 1 === count( $GLOBALS['test_options'][TAXOCOMMERCE_INSIGHTS_COMPETITIVE_HISTORY_OPTION] ), 'history kept' );
check_market( false !== strpos( $source, "wp_ajax_taxocommerce_insights_competitive_source_refresh" ), 'secure AJAX refresh registered' );
check_market( false !== strpos( $source, "check_ajax_referer( 'taxocommerce_insights_competition_sources'" ), 'refresh nonce validated' );
check_market( false !== strpos( $source, "current_user_can( 'manage_options' )" ), 'refresh requires admin capability' );
check_market( false !== strpos( $source, "TaxoCommerce_Google_Bing_Merchant::sync( 'popular', " ), 'best sellers updated through Merchant connector' );
check_market( false !== strpos( $source, "seo_google_trends_sync( false )" ), 'Trends uses existing rate-limited connector' );
check_market( false !== strpos( $source, 'TaxoCommerce_Google_Bing_Merchant::competitive_visibility( array( $categories[ $step ] ), $day_count )' ), 'one visibility category per request' );
$refresh_js = file_get_contents( __DIR__ . '/../includes/analysis/assets/analista-lazy.js' );
check_market( false !== strpos( $refresh_js, "steps.push('merchant')" ), 'Merchant stage queued' );
check_market( false !== strpos( $refresh_js, "steps.push('trends')" ), 'Trends stage queued' );
check_market( false !== strpos( $refresh_js, "steps.push('visibility:0')" ), 'visibility category stages queued' );
check_market( false !== strpos( $refresh_js, "form.get(0).submit()" ), 'existing secure generation POST retained' );

// The admin-post redirect must re-open the saved panel instead of the generic
// Analysis menu. No remote provider calls may be triggered by page restoration.
$analysis_screen = file_get_contents( __DIR__ . '/../taxocommerce-google-bing.php' );
check_market( false !== strpos( $source, "'analysis_open' => 'competencia'" ), 'generate and settings preserve selected report' );
check_market( false !== strpos( $source, "'analysis_days' => $days" ), 'redirect uses Analysis period argument' );
check_market( false !== strpos( $source, "'#seo-analista-lazy-result'" ), 'return targets report container' );
check_market( false !== strpos( $analysis_screen, "'competencia' === $open_report" ), 'Analysis restores the selected report' );
check_market( false !== strpos( $analysis_screen, 'taxocommerce_insights_competitive_render_panel( $days );' ), 'return paints saved snapshot' );
check_market( false === strpos( $analysis_screen, 'taxocommerce_insights_competitive_build_report( $days );' ), 'return never regenerates the report' );
check_market( false !== strpos( $source, 'name="analysis_days"' ), 'settings form preserves report period' );
// Data is optional; report must still work with no external Merchant evidence.
$report['sources']['merchant_competitive_visibility']['status'] = 'unavailable';
check_market( 'unavailable' === $report['sources']['merchant_competitive_visibility']['status'], 'missing data explicit' );
echo "Competition market smoke: OK\n";
