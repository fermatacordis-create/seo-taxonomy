<?php
/**
 * Standalone calendar indicator regression, no WordPress or provider account.
 */
define( 'ABSPATH', __DIR__ );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'DAY_IN_SECONDS', 86400 );
function indicator_assert( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, 'FAIL indicators: ' . $message . PHP_EOL );
        exit( 1 );
    }
}
function absint( $value ) { return abs( (int) $value ); }
function wp_timezone() { return new DateTimeZone( 'Europe/Madrid' ); }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
class WP_Error {}
function number_format_i18n( $n, $decimals = 0 ) { return number_format( (float) $n, $decimals, ',', '.' ); }
function __( $value, $domain ) { return $value; }
function esc_html__( $value, $domain ) { return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' ); }
function esc_html( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
function sanitize_title( $title ) { return strtolower( trim( $title ) ); }
function get_term_by( $field, $value, $taxonomy ) {
    if ( 'product_cat' !== $taxonomy ) return false;
    if ( 'limpiadores-ultrasonicos' === $value ) return (object) array( 'term_id' => 456, 'name' => 'Limpiadores ultrasónicos', 'count' => 4 );
    if ( 'bolardos-de-seguridad' === $value ) return (object) array( 'term_id' => 57, 'name' => 'Bolardos de seguridad', 'count' => 14 );
    return false;
}
function get_term_link( $term ) { return 'https://example.test/tienda/' . ( 57 === $term->term_id ? 'bolardos-de-seguridad/' : 'limpiadores-ultrasonicos/' ); }
function home_url( $path ) { return 'https://example.test' . $path; }
function url_to_postid( $url ) { return false !== strpos( $url, '/producto/' ) ? 800 : ( false !== strpos( $url, '/noticias/' ) ? 900 : 0 ); }
function get_post_type( $id ) { return 800 === $id ? 'product' : ( 900 === $id ? 'post' : false ); }
function get_the_title( $id ) { return 800 === $id ? 'Taladro de muestra' : 'Noticia de muestra'; }
$GLOBALS['ga4_calls'] = array();
$GLOBALS['ga4_unavailable'] = false;
$GLOBALS['indicator_cache'] = array();
function get_transient( $key ) { return $GLOBALS['indicator_cache'][ $key ] ?? false; }
function set_transient( $key, $value, $ttl ) { $GLOBALS['indicator_cache'][ $key ] = $value; return true; }
function seo_google_analytics_run_report( $args ) {
    if ( ! empty( $GLOBALS['ga4_unavailable'] ) ) {
        return new WP_Error();
    }
    $from = $args['dateRanges'][0]['startDate'];
    $to = $args['dateRanges'][0]['endDate'];
    $GLOBALS['ga4_calls'][] = array( $from, $to );
    if ( isset( $args['dimensions'] ) && 'eventName' === $args['dimensions'][0]['name'] ) {
        return array( 'rows' => array(
            array( 'dimensionValues' => array( array( 'value' => 'view_item' ) ), 'metricValues' => array( array( 'value' => '40' ) ) ),
            array( 'dimensionValues' => array( array( 'value' => 'add_to_cart' ) ), 'metricValues' => array( array( 'value' => '4' ) ) ),
        ) );
    }
    if ( isset( $args['dimensions'] ) ) {
        $dim = $args['dimensions'][0]['name'];
        if ( 'sessionSourceMedium' === $dim ) return array( 'rows' => array( array( 'dimensionValues' => array( array( 'value' => 'google / organic' ) ), 'metricValues' => array( array( 'value' => '25' ) ) ) ) );
        if ( 'pagePath' === $dim ) return array( 'rows' => array( array( 'dimensionValues' => array( array( 'value' => '/producto/taladro/' ), array( 'value' => 'Taladro de muestra' ) ), 'metricValues' => array( array( 'value' => '22' ) ) ), array( 'dimensionValues' => array( array( 'value' => '/noticias/articulo/' ), array( 'value' => 'Artículo nuevo' ) ), 'metricValues' => array( array( 'value' => '17' ) ) ) ) );
        if ( 'itemName' === $dim ) return array( 'rows' => array( array( 'dimensionValues' => array( array( 'value' => 'Taladro de muestra' ), array( 'value' => '800' ) ), 'metricValues' => array( array( 'value' => '14' ), array( 'value' => '3' ), array( 'value' => '0' ) ) ) ) );
        return new WP_Error();
    }
    return array( 'rows' => array( array( 'metricValues' => array_map(
        static function( $v ) { return array( 'value' => (string) $v ); },
        array( 60, 50, 90, 0, 0 )
    ) ) ) );
}
function seo_analista_resolve_property_id() { return 'sc-domain:example.test'; }
function seo_google_latest_data_date( $property ) { return '2026-11-08'; }
function seo_analista_period_metrics( $property, $from, $to ) {
    $days = ( new DateTimeImmutable( $from ) )->diff( new DateTimeImmutable( $to ) )->days + 1;
    return array(
        'source' => 'gsc_property_web_final',
        'days_available' => '2026-11-15' === $to ? $days - 1 : $days,
        'days_expected' => $days,
        'impressions' => '2026-11-02' === $from ? 153 : 21,
        'clicks' => '2026-11-02' === $from ? 2 : 1,
        'position' => 19.4,
    );
}
function seo_analista_query_rows( $property, $from, $to, $limit ) {
    return array( array(
        'query_text' => 'limpiador ultrasonicos',
        'impressions' => '2026-11-02' === $from ? 100 : 10,
        'clicks' => 0,
        'position' => 15.0,
    ), array(
        'query_text' => 'venta de bolardos',
        'impressions' => '2026-11-02' === $from ? 30 : 3,
        'clicks' => 0,
        'position' => 5.0,
    ) );
}
function seo_google_pages_period_complete( $property, $from, $to ) { return true; }
function seo_analista_page_rows( $property, $from, $to, $limit ) {
    return array( array(
        'page_url' => 'https://example.test/tienda/limpiadores-ultrasonicos/',
        'impressions' => '2026-11-02' === $from ? 80 : 10,
        'clicks' => 2,
        'position' => 12.0,
    ), array(
        'page_url' => 'https://example.test/tienda/bolardos-de-seguridad/',
        'impressions' => '2026-11-02' === $from ? 35 : 4,
        'clicks' => 0,
        'position' => 9.9,
    ), array(
        'page_url' => 'https://example.test/producto/taladro/',
        'impressions' => '2026-11-02' === $from ? 20 : 12,
        'clicks' => 2,
        'position' => 8,
    ) );
}

require dirname( __DIR__ ) . '/includes/analysis/analista-direccion.php';
require dirname( __DIR__ ) . '/includes/analysis/analista-indicadores.php';

// Verify low-volume executive evidence that version 1.1.65 missed.
$bolard_query = taxocommerce_insights_indicators_explain( 'consulta_visible_sin_clics_organicos', 30, 0, 5, false );
indicator_assert( 'preliminary' === $bolard_query['confidence'], '30 impressions remain a preliminary signal' );
indicator_assert( null === $bolard_query['suggestion'], 'Low-volume example never triggers an action' );
$category_candidate = taxocommerce_insights_indicators_explain( 'visibilidad_sin_clics_organicos', 35, 0, 9.9, true );
indicator_assert( null === $category_candidate['suggestion'], '35 impressions still cannot prescribe actions' );
$verified_candidate = taxocommerce_insights_indicators_explain( 'visibilidad_sin_clics_organicos', 65, 0, 6, true );
indicator_assert( 'moderate' === $verified_candidate['confidence'], 'Moderate repeated evidence classified separately' );
indicator_assert( is_string( $verified_candidate['suggestion'] ) && '' !== $verified_candidate['suggestion'], 'Strong evidence allows a strictly bounded check recommendation' );
indicator_assert( null === taxocommerce_insights_indicators_explain( 'nicho_pequeno_con_clics', 65, 4, 8, true )['suggestion'], 'Niche identification does not prescribe commercial strategy' );
$test_findings = taxocommerce_insights_indicators_findings(
    array( array( 'query' => 'venta de bolardos', 'source' => 'Google Search Console', 'signals' => array( 'consulta_visible_sin_clics_organicos' ), 'metrics' => array( 'impressions' => 30, 'clicks' => 0, 'position' => 5, 'previous_impressions' => null ) ) ),
    'query',
    array( 'date_from' => '2026-11-02', 'date_to' => '2026-11-08' )
);
indicator_assert( 1 === count( $test_findings ), 'Low-volume finding serialized for executive report' );
indicator_assert( '2026-11-02' === $test_findings[0]['period']['date_from'] && 30 === $test_findings[0]['metrics']['impressions'], 'Finding preserves date and original evidence' );
indicator_assert( null === $test_findings[0]['suggestion'], 'Unverified executive finding is descriptive only' );

$weeks = taxocommerce_insights_indicators_periods( 'week', '2026-11-08', '2026-11-15', 4 );
indicator_assert( count( $weeks ) === 4, 'Four complete weeks' );
indicator_assert( $weeks[0]['date_from'] === '2026-11-02' && $weeks[0]['date_to'] === '2026-11-08', 'Shared last Monday-to-Sunday week' );
indicator_assert( $weeks[1]['date_from'] === '2026-10-26' && $weeks[1]['date_to'] === '2026-11-01', 'Previous week shares calendar boundaries' );
$months = taxocommerce_insights_indicators_periods( 'month', '2026-11-08', '2026-11-15', 3 );
indicator_assert( count( $months ) === 3, 'Three complete months' );
indicator_assert( '2026-10-01' === $months[0]['date_from'] && '2026-10-31' === $months[0]['date_to'], 'October is last complete month' );
indicator_assert( '2026-09-01' === $months[1]['date_from'] && '2026-09-30' === $months[1]['date_to'], 'September has 30 days' );
indicator_assert( '2026-08-01' === $months[2]['date_from'] && '2026-08-31' === $months[2]['date_to'], 'August has 31 days' );
$march = taxocommerce_insights_indicators_periods( 'month', '2026-03-30', '2026-03-30', 3 );
indicator_assert( '2026-02-01' === $march[0]['date_from'] && '2026-02-28' === $march[0]['date_to'], 'Month end never overflows into March' );
$leap = taxocommerce_insights_indicators_periods( 'month', '2024-03-02', '2024-03-02', 1 );
indicator_assert( '2024-02-29' === $leap[0]['date_to'], 'Leap-year February is complete' );
indicator_assert( taxocommerce_insights_indicators_delta( 10, 0 )['growth_pct'] === null, 'Zero baseline is not an infinite percentage' );
indicator_assert( taxocommerce_insights_indicators_delta( null, 0 ) === null, 'Unavailable source is not a measured zero' );

$report = taxocommerce_insights_indicators_build( 'week' );
indicator_assert( 'taxocommerce-insights-indicadores' === $report['schema']['name'], 'Evidence-only JSON schema' );
indicator_assert( 3 === $report['schema']['version'], 'Structured executive report schema version 3' );
indicator_assert( ! empty( $report['executive']['summary'] ), 'Executive summary with five clear business KPIs' );
indicator_assert( 'available' === $report['executive']['ga4_pages']['state'], 'GA4 pageviews grouped by pages' );
indicator_assert( 'product' === $report['executive']['ga4_pages']['rows'][0]['type'], 'Recognized product page' );
indicator_assert( 'post' === $report['executive']['ga4_pages']['rows'][1]['type'], 'Recognized recent entry' );
indicator_assert( ! empty( $report['executive']['ga4_pages_by_type']['product'][0] ), 'GA4 product visits are presented separately' );
indicator_assert( ! empty( $report['executive']['ga4_pages_by_type']['post'][0] ), 'GA4 article visits are presented separately' );
indicator_assert( 14 === $report['executive']['ga4_items']['rows'][0]['views'], 'Products viewed in GA4, not inferred from GSC' );
indicator_assert( 3 === $report['executive']['ga4_items']['most_added_to_cart'][0]['added_to_cart'], 'Cart product ranking available' );
indicator_assert( 'google / organic' === $report['executive']['ga4_traffic_sources']['rows'][0]['name'], 'Traffic provenance exposed' );
indicator_assert( ! empty( $report['executive']['gsc_rankings']['products'] ), 'Product search visibility list exposed' );
indicator_assert( 'unavailable' === $report['executive']['market']['suppliers']['state'], 'Unknown suppliers explicitly marked unavailable' );
indicator_assert( ! empty( $report['findings'] ), 'Exported JSON includes documented executive findings' );
indicator_assert( ! empty( $report['findings'][0]['observation'] ), 'Finding contains human-readable observation' );
indicator_assert( 'week' === $report['period_mode'], 'Calendar mode in JSON' );
indicator_assert( count( $report['periods'] ) === 4, 'Four historical periods exported' );
indicator_assert( '2026-11-02' === $report['periods'][0]['date_from'], 'Same current window in GSC and GA4' );
indicator_assert( 'available' === $report['periods'][0]['gsc']['state'] && 'available' === $report['periods'][0]['ga4']['state'], 'Both sources available' );
indicator_assert( 153.0 === $report['periods'][0]['gsc']['metrics']['impressions'], 'GSC figures are sourced' );
indicator_assert( 60 === $report['periods'][0]['ga4']['metrics']['sessions'], 'GA4 figures are sourced' );
indicator_assert( 132.0 === $report['changes']['gsc_impressions']['delta'], 'Comparable GSC impression change' );
indicator_assert( ! empty( $report['signals']['categories'][0] ), 'Real category observation, linked to verified WooCommerce term URL' );
indicator_assert( in_array( 'nicho_pequeno_con_clics', $report['signals']['categories'][0]['signals'], true ), 'Small niche with organic clicks is an observation' );
indicator_assert( ! empty( $report['signals']['queries'][0] ), 'Search terms with rising impressions included' );
indicator_assert( (bool) array_filter( $report['signals']['queries'], static function ( $q ) { return 'venta de bolardos' === $q['query'] && in_array( 'consulta_visible_sin_clics_organicos', $q['signals'], true ); } ), 'A well-positioned 30-impression search term with integer zero clicks cannot disappear' );
indicator_assert( (bool) array_filter( $report['signals']['categories'], static function ( $c ) { return 'Bolardos de seguridad' === $c['name'] && in_array( 'visibilidad_sin_clics_organicos', $c['signals'], true ); } ), 'Category with 35 impressions and no clicks remains a measured finding' );
indicator_assert( ! isset( $report['plan_accion'] ) && ! isset( $report['recommended_changes'] ), 'No fabricated strategic actions' );
indicator_assert( $GLOBALS['ga4_calls'][0] === array( '2026-11-02', '2026-11-08' ), 'GA4 first call uses exact GSC dates' );
indicator_assert( $GLOBALS['ga4_calls'][2] === array( '2026-10-26', '2026-11-01' ), 'GA4 previous week dates are aligned' );

ob_start();
taxocommerce_insights_indicators_render( $report );
$html = ob_get_clean();
indicator_assert( false !== strpos( $html, 'Indicadores para la acción' ), 'New report title displayed' );
indicator_assert( false === strpos( $html, 'HACER AHORA' ), 'No execution queue in indicator report' );
indicator_assert( false !== strpos( $html, 'Resumen para dirección' ), 'Main report displays clear executive summary' );
indicator_assert( false !== strpos( $html, 'Productos más consultados' ), 'Executive product ranking is visible' );
indicator_assert( false !== strpos( $html, 'Categorías más visitadas (GA4)' ), 'Executives can see category visits separately' );
indicator_assert( false !== strpos( $html, 'Tendencias, proveedores y competencia' ), 'External source sections are visible even if unavailable' );

$monthly = taxocommerce_insights_indicators_build( 'month' );
indicator_assert( '2026-10-01' === $monthly['periods'][0]['date_from'], 'Monthly indicators anchored to last complete month' );
indicator_assert( '2026-10-31' === $monthly['periods'][0]['date_to'], 'Monthly GA4 aligned with GSC' );

$GLOBALS['ga4_unavailable'] = true;
$missing_ga4 = taxocommerce_insights_indicators_ga4( array( 'date_from' => '2026-01-01', 'date_to' => '2026-01-31' ) );
indicator_assert( 'unavailable' === $missing_ga4['state'] && null === $missing_ga4['metrics'], 'Unavailable GA4 never reported as zero sessions/sales' );
$partial_gsc = taxocommerce_insights_indicators_gsc( 'sc-domain:example.test', array( 'date_from' => '2026-11-02', 'date_to' => '2026-11-15', 'days' => 14 ) );
indicator_assert( 'partial' === $partial_gsc['state'] && null === $partial_gsc['metrics'], 'Incomplete GSC interval never treated as complete' );
echo "Insights calendar indicators: OK\n";
