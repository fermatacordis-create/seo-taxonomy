<?php
/**
 * Standalone regression: the Insights AJAX action unifies commercial advice,
 * evidence-based GEO and the historical roadmap, with one decision-plan call.
 * No WordPress installation or network access is required.
 */
define( 'ABSPATH', __DIR__ );
define( 'SEO_ANALISTA_VERSION', '3.9.0' );
class Insights_Stop_Response extends Exception {}
$GLOBALS['invocations'] = array(
    'decision_plan' => 0, 'directrices' => 0, 'roadmap' => 0,
    'geo' => 0, 'google_snapshot' => 0, 'other' => 0,
);
function insights_check( $condition, $description ) {
    if ( ! $condition ) {
        fwrite( STDERR, 'FAIL unified Analysis: ' . $description . PHP_EOL );
        exit( 1 );
    }
}
function current_user_can( $capability ) { return 'manage_options' === $capability; }
function check_ajax_referer( $action, $query_arg ) {
    insights_check( 'taxocommerce_insights_analysis' === $action, 'Insights nonce' );
    insights_check( 'nonce' === $query_arg, 'nonce field' );
}
function sanitize_key( $value ) { return preg_replace( '/[^a-z_]/', '', strtolower( $value ) ); }
function wp_unslash( $value ) { return $value; }
function absint( $value ) { return abs( (int) $value ); }
$GLOBALS['mock_options'] = array();
$GLOBALS['last_autoload'] = null;
function get_option( $name, $fallback = false ) { return $GLOBALS['mock_options'][ $name ] ?? $fallback; }
function update_option( $name, $value, $autoload = null ) {
    $GLOBALS['mock_options'][ $name ] = $value;
    $GLOBALS['last_autoload'] = $autoload;
    return true;
}
function wp_date( $format, $timestamp ) { return gmdate( $format, $timestamp ); }
function admin_url( $path ) { return 'https://example.test/wp-admin/' . $path; }
function wp_nonce_url( $url, $action ) { return $url . '&_wpnonce=' . rawurlencode( $action ); }
function sanitize_text_field( $value ) { return strip_tags( (string) $value ); }
function wp_strip_all_tags( $html ) { return strip_tags( (string) $html ); }
function add_query_arg( $args, $url ) { return $url . '?' . http_build_query( $args ); }
function esc_html( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function esc_html__( $value, $domain ) { return esc_html( $value ); }
function __( $value, $domain ) { return $value; }
function number_format_i18n( $value ) { return number_format( (float) $value ); }
function wp_send_json_success( $payload ) {
    $GLOBALS['last_response'] = array( 'success' => true, 'data' => $payload );
    throw new Insights_Stop_Response();
}
function wp_send_json_error( $payload, $status ) {
    $GLOBALS['last_response'] = array( 'success' => false, 'status' => $status, 'data' => $payload );
    throw new Insights_Stop_Response();
}
function wp_remote_get() { throw new RuntimeException( 'Unexpected remote request' ); }
function wp_remote_post() { throw new RuntimeException( 'Unexpected remote request' ); }
function seo_analista_lazy_reports() {
    return array(
        'directrices' => array( 'label' => 'Qué hacer', 'description' => 'Comercial' ),
        'trabajo' => array( 'label' => 'Plan de acción', 'description' => 'Cartera' ),
        'resumen' => array( 'label' => 'Resumen', 'description' => 'Fuentes' ),
    );
}
function seo_analista_lazy_render_partial( $report, $days ) {
    if ( 'directrices' === $report ) {
        $GLOBALS['invocations']['directrices']++;
        echo '<section><h2>Qué hacer para vender más</h2><p>Diagnóstico comercial histórico</p></section>';
    } else {
        $GLOBALS['invocations']['other']++;
        echo '<section>Informe especializado ' . esc_html( $report ) . '</section>';
    }
}
function seo_analista_decision_plan( $days, $limit ) {
    $GLOBALS['invocations']['decision_plan']++;
    insights_check( 40 === $limit, 'full portfolio limit preserved' );
    return array( array( 'work_bucket' => 'INVESTIGAR', 'topic' => 'Prueba', 'reason' => 'Datos insuficientes', 'issues' => array( 'Sin asociación fiable' ), 'recommended_changes' => array( 'Revisar la asociación' ) ) );
}
function seo_analista_google_snapshot( $days, $refresh ) {
    $GLOBALS['invocations']['google_snapshot']++;
    insights_check( false === $refresh, 'read-only GA4 snapshot' );
    return array( 'ga4' => array(
        'available' => true,
        'period' => array( 'start' => '2026-10-15' ),
        'traffic_quality' => array( 'ai_sessions' => 5 ),
        'sources' => array( array( 'channel' => 'ai', 'source_medium' => 'chatgpt.com / referral', 'sessions' => 5 ) ),
        'sessions' => 100, 'purchases' => 2,
    ) );
}
function seo_analista_ga4_snapshot( $days, $end_date = '' ) {
    insights_check( '2026-10-14' === $end_date, 'preceding equal-length GA4 period' );
    return array( 'available' => true, 'traffic_quality' => array( 'ai_sessions' => 2 ) );
}
function seo_analista_get_data( $days ) {
    return array(
        'queries' => array( array( 'query_text' => 'grapadoras', 'impressions' => 40, 'clicks' => 2, 'position' => 8 ) ),
        'previous_queries' => array( array( 'query_text' => 'grapadoras', 'impressions' => 15 ) ),
    );
}
function seo_analista_normalize_text( $text ) { return strtolower( trim( $text ) ); }
function seo_analista_query_is_actionable( $query ) { return true; }
function taxocommerce_google_bing_geo_render_summary( $days, array $ga4 = array(), $plan = null ) {
    $GLOBALS['invocations']['geo']++;
    insights_check( is_array( $plan ) && 1 === count( $plan ), 'GEO receives same decision portfolio' );
    insights_check( 3 === $ga4['traffic_quality']['ai_sessions'], 'GA4 snapshot reused' );
    echo '<section>Hasta cinco mejoras prioritarias verificables</section>';
}
function seo_analista_plan_consistency_report( $plan ) {
    return array( 'ok' => false, 'issues' => array( array( 'code' => 'TEST' ) ) );
}
function seo_analista_task_contract( $row ) { return array( 'task_id' => 'task-example', 'final_bucket' => $row['work_bucket'], 'primary_action' => array( 'type' => 'INVESTIGAR', 'executable' => false ) ); }
function seo_analista_plan_summary( $plan ) {
    return array( 'investigar' => count( $plan ), 'hacer_ahora' => 0 );
}
function seo_analista_supplier_snapshot( $limit ) { return array( 'issues' => array() ); }
function seo_analista_internal_search_snapshot( $days, $limit ) { return array( 'gaps' => array() ); }
function seo_analista_render_roadmap( $plan, $summary, $suppliers, $search ) {
    $GLOBALS['invocations']['roadmap']++;
    insights_check( 0 === $summary['hacer_ahora'], 'do not fabricate executable tasks' );
    echo '<section>Plan conservado: ' . (int) $summary['investigar'] . ' para investigar</section>';
}
function add_action( $tag, $handler ) { return true; }

require dirname( __DIR__ ) . '/includes/analysis/analista-geo-bridge.php';

$reports = taxocommerce_google_bing_analysis_reports();
insights_check( 2 === count( $reports ), 'existing unified report catalog retained' );
insights_check( ! isset( $reports['trabajo'] ), 'duplicate work button remains absent' );
insights_check( 'Plan de trabajo' === $reports['directrices']['label'], 'combined report label retained' );

$_POST = array( 'report' => 'directrices', 'days' => '28' );
try {
    taxocommerce_insights_analysis_ajax();
} catch ( Insights_Stop_Response $stop ) {
}
$result = $GLOBALS['last_response'];
insights_check( true === $result['success'], 'market report response succeeds' );
$html = $result['data']['html'];
insights_check( false !== strpos( $html, 'Qué hacer para vender más' ), 'existing commercial advice retained' );
insights_check( false !== strpos( $html, 'Oportunidades de mercado' ), 'executive market view rendered' );
insights_check( false !== strpos( $html, 'Visitas referidas desde IA' ), 'AI referral comparison rendered' );
insights_check( false !== strpos( $html, 'grapadoras' ), 'GSC demand opportunity included' );
insights_check( false === strpos( $html, 'Plan de acción incoherente' ), 'legacy entity audit gate not shown' );
$export = $result['data']['plan_export'] ?? array();
insights_check( 'taxocommerce-insights-plan-accion' === (string) ( $export['schema']['name'] ?? '' ), 'existing JSON contract name' );
insights_check( 28 === (int) ( $export['period_days'] ?? 0 ), 'correct reporting period' );
insights_check( 2 === (int) ( $export['total'] ?? 0 ), 'one demand and one AI growth opportunity' );
insights_check( ! empty( $export['plan_accion'][0]['market_signal'] ), 'plan exports market evidence, not editorial errors' );
insights_check( 'Google Search Console' === (string) ( $export['plan_accion'][0]['source'] ?? '' ), 'source attribution preserved' );
insights_check( 2 === (int) ( $export['summary']['hacer_despues'] ?? 0 ), 'market triage retained' );
insights_check( 0 === $GLOBALS['invocations']['decision_plan'], 'legacy editorial validation not executed' );
insights_check( 0 === $GLOBALS['invocations']['roadmap'], 'legacy editorial roadmap not executed' );
insights_check( 0 === $GLOBALS['invocations']['geo'], 'legacy GEO editorial selection not executed' );
insights_check( false === $GLOBALS['last_autoload'], 'latest market plan is never autoloaded' );
insights_check( $export === taxocommerce_insights_last_plan_export(), 'market plan stored for standalone download' );
insights_check( ! empty( $result['data']['plan_saved_at_display'] ), 'latest JSON date returned' );
insights_check( false !== strpos( taxocommerce_insights_plan_json_download_url(), 'admin-post.php?action=taxocommerce_insights_plan_json_download' ), 'signed download retained' );

$_POST = array( 'report' => 'resumen', 'days' => '60' );
try {
    taxocommerce_insights_analysis_ajax();
} catch ( Insights_Stop_Response $stop ) {
}
insights_check( true === $GLOBALS['last_response']['success'], 'specialist report still available' );
insights_check( 60 === taxocommerce_insights_last_report_export( 'resumen', 60 )['period_days'], 'specialist report saved' );
insights_check( ! isset( $GLOBALS['last_response']['data']['plan_export'] ), 'specialist report does not export market plan' );
insights_check( $export === taxocommerce_insights_last_plan_export(), 'specialist does not replace last market plan' );
insights_check( 0 === $GLOBALS['invocations']['decision_plan'], 'specialist report did not trigger editorial plan' );

$_POST = array( 'report' => 'trabajo', 'days' => '28' );
try {
    taxocommerce_insights_analysis_ajax();
} catch ( Insights_Stop_Response $stop ) {
}
insights_check( 400 === $GLOBALS['last_response']['status'], 'duplicate work action remains rejected' );
$GLOBALS['mock_options'] = array();
insights_check( array() === taxocommerce_insights_last_plan_export(), 'empty snapshot handled' );
echo "Insights market unified Analysis regression: OK\n";
