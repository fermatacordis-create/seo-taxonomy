<?php
/**
 * Standalone regression check: rendering Google/Bing report tabs must not call
 * remote APIs, even when date filters change or local snapshots exist.
 */
define( 'ABSPATH', __DIR__ );
define( 'MINUTE_IN_SECONDS', 60 );

class WP_Error {
    private $message;
    public function __construct( $code, $message ) { $this->message = $message; }
    public function get_error_message() { return $this->message; }
}
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function wp_timezone() { return new DateTimeZone( 'Europe/Madrid' ); }
function wp_unslash( $value ) { return $value; }
function sanitize_text_field( $value ) { return trim( preg_replace( '/<[^>]*>/', '', (string) $value ) ); }
function wp_date( $format, $timestamp ) { return gmdate( $format, $timestamp ); }
function get_current_blog_id() { return 1; }
function get_current_user_id() { return 1; }
function get_transient( $key ) { return false; }
function delete_transient( $key ) { return true; }
function get_option( $key, $fallback = false ) {
    return isset( $GLOBALS['tgb_test_options'][ $key ] ) ? $GLOBALS['tgb_test_options'][ $key ] : $fallback;
}
function admin_url( $path = '' ) { return 'https://example.test/wp-admin/' . $path; }
function add_query_arg( $params, $url ) { return $url . '?' . http_build_query( $params ); }
function wp_nonce_field( $name ) { echo '<input type="hidden" name="_wpnonce" value="test">'; }
function wp_nonce_url( $url, $name ) { return $url . '&_wpnonce=test'; }
function esc_html( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $value ) { return esc_html( $value ); }
function esc_url( $value ) { return esc_html( $value ); }
function number_format_i18n( $number, $precision = 0 ) {
    return number_format( (float) $number, $precision, ',', '.' );
}
function wp_parse_url( $url, $part = -1 ) {
    // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- Standalone CLI mock of wp_parse_url(), not plugin runtime.
    return parse_url( $url, $part );
}
function wp_remote_get() { throw new RuntimeException( 'Unexpected HTTP GET on report render' ); }
function wp_remote_post() { throw new RuntimeException( 'Unexpected HTTP POST on report render' ); }

require dirname( __DIR__ ) . '/includes/class-taxocommerce-google-bing-reports.php';

foreach ( array( 'analytics', 'search', 'bing' ) as $source ) {
    $_GET = array( 'period' => '7' );
    ob_start();
    TaxoCommerce_Google_Bing_Reports::render( $source );
    $html = ob_get_clean();
    if ( false === strpos( $html, 'Actualizar datos' ) || false === strpos( $html, 'No hay datos guardados todavía' ) ) {
        print( 'Unexpected empty state: ' . $source . "\n" );
        exit( 1 );
    }
}

$_GET = array(
    'period' => 'custom',
    'start' => '2026-09-01',
    'end' => '2026-09-07',
);
ob_start();
TaxoCommerce_Google_Bing_Reports::render( 'search' );
$html = ob_get_clean();
if ( false === strpos( $html, '01/09/2026' ) || false === strpos( $html, '25/08/2026' ) ) {
    print( "Custom date range comparison failed\n" );
    exit( 1 );
}

$history = array(
    'source' => 'bing',
    'schema' => 1,
    'downloaded_at' => 1791500000,
    'current' => array( 'start' => '2026-08-01', 'end' => '2026-08-07', 'label' => '01/08/2026 – 07/08/2026' ),
    'previous' => array( 'start' => '2026-07-25', 'end' => '2026-07-31', 'label' => '25/07/2026 – 31/07/2026' ),
    'data' => array(
        'traffic' => array(
            'current' => array( 'impressions' => 9, 'clicks' => 3, 'ctr' => 1/3 ),
            'previous' => array( 'impressions' => 6, 'clicks' => 2, 'ctr' => 1/3 ),
        ),
        'crawl' => array(
            'current' => array( 'crawled_pages'=>1,'http_errors'=>0,'in_index'=>1,'code2xx'=>1,'code4xx'=>0,'code5xx'=>0,'crawl_errors'=>0,'blocked_robots'=>0,'in_links'=>1 ),
            'previous' => array( 'crawled_pages'=>1,'http_errors'=>0,'in_index'=>1 ),
        ),
        'daily' => array( 'labels'=>array('1','2'),'current_impressions'=>array(4,5),'previous_impressions'=>array(3,3),'current_clicks'=>array(1,2),'previous_clicks'=>array(1,1) ),
        'queries'=>array(),
        'pages'=>array(),
        'warnings'=>array(),
    ),
);
$GLOBALS['tgb_test_options']['tgb_manual_report_v1_bing'] = array(
    md5( '2026-08-01|2026-08-07' ) => $history,
);
$_GET = array( 'period' => '7' );
ob_start();
TaxoCommerce_Google_Bing_Reports::render( 'bing' );
$html = ob_get_clean();
if ( false === strpos( $html, 'latest available snapshot' ) || false === strpos( $html, '01/08/2026' ) || false === strpos( $html, 'Export JSON copy' ) ) {
    print( "Saved snapshot rendering failed\n" );
    exit( 1 );
}
$reports_source = file_get_contents( dirname( __DIR__ ) . '/includes/class-taxocommerce-google-bing-reports.php' );
if ( false === strpos( $reports_source, 'array_chunk( $requests, 5 )' )
    || false === strpos( $reports_source, 'array( \'requests\' => $request_chunk )' )
    || false === strpos( $reports_source, "'funnel' => array(" ) ) {
    print( "GA4 batching/funnel regression guard failed\n" );
    exit( 1 );
}
// The Analytics summary may contain at most ten metrics per GA4 request.
if ( false === strpos( $reports_source, 'count( $current_reports ) < 9' )
    || false === strpos( $reports_source, 'self::ga4_summary( $current_reports[0], $current_reports[8] )' )
    || false === strpos( $reports_source, "array( 'name' => 'ecommercePurchases' )" )
    || false !== strpos( $reports_source, 'row_metric( $row, 10 )' ) ) {
    print( "GA4 report shape regression failed\n" );
    exit( 1 );
}
$gsc_source = file_get_contents( dirname( __DIR__ ) . '/includes/class-taxocommerce-google-bing-gsc.php' );
if ( false === strpos( $gsc_source, 'Una conexión GA4 correcta no concede permisos de Search Console.' ) ) {
    print( "GSC permissions guidance regression failed\n" );
    exit( 1 );
}
// The new charts must render solely from snapshots and escape item names.
function __( $message, $domain = '' ) { return $message; }
function esc_html__( $message, $domain = '' ) { return esc_html( $message ); }
$chart = new ReflectionMethod( 'TaxoCommerce_Google_Bing_Reports', 'render_ga4_ecommerce' );
$chart->setAccessible( true );
ob_start();
$chart->invoke( null, array(
    'funnel' => array(
        'current' => array( 'view_item' => 15, 'add_to_cart' => 4, 'view_cart' => 3, 'begin_checkout' => 2, 'purchase' => 0 ),
        'previous' => array( 'view_item' => 8, 'add_to_cart' => 1, 'view_cart' => 1, 'begin_checkout' => 1, 'purchase' => 0 ),
    ),
    'cart_products' => array( 'available' => true, 'rows' => array(
        array( 'name' => '<unsafe>', 'id' => 'SKU-1', 'viewed' => 10, 'added' => 3, 'purchased' => 0 ),
    ) ),
) );
$cart_html = ob_get_clean();
if ( false === strpos( $cart_html, 'Productos añadidos al carrito' )
    || false === strpos( $cart_html, 'Interacciones de compra' )
    || false === strpos( $cart_html, '&lt;unsafe&gt;' )
    || false !== strpos( $cart_html, '<unsafe>' )
    || false === strpos( $cart_html, 'SKU-1' ) ) {
    throw new RuntimeException( 'GA4 ecommerce charts failed rendering or HTML escaping.' );
}
ob_start();
$chart->invoke( null, array( 'funnel' => array(), 'cart_products' => array( 'available' => false ) ) );
$empty_html = ob_get_clean();
if ( false === strpos( $empty_html, 'Actualizar datos de Google Analytics 4' ) ) {
    throw new RuntimeException( 'Legacy snapshot empty state failed.' );
}
echo "Report rendering smoke test: OK (no network requests, GA4 batches capped at five; product charts).\\n";
