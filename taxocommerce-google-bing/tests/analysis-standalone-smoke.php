<?php
/**
 * Regression: Insights -> Analysis must not rely on the SEO monolith's
 * entrypoint, constants, CSS/JS assets, or AJAX dispatcher.
 * This is a no-network, no-WordPress test of the bundled source contract.
 */
$root = dirname( __DIR__ );

function tci_analysis_check( $ok, $message ) {
    if ( ! $ok ) {
        fwrite( STDERR, 'FAIL standalone Analysis: ' . $message . PHP_EOL );
        exit( 1 );
    }
}

function tci_analysis_source( $root, $relative ) {
    $path = $root . '/' . $relative;
    tci_analysis_check( is_file( $path ), 'Missing own file: ' . $relative );
    $body = file_get_contents( $path );
    tci_analysis_check( false !== $body, 'Unreadable own file: ' . $relative );
    return $body;
}

$main = tci_analysis_source( $root, 'taxocommerce-google-bing.php' );
$bootstrap = tci_analysis_source( $root, 'includes/analysis/analista-bootstrap.php' );
$lazy = tci_analysis_source( $root, 'includes/analysis/analista-lazy.php' );
$bridge = tci_analysis_source( $root, 'includes/analysis/analista-geo-bridge.php' );
$css_owner = tci_analysis_source( $root, 'includes/analysis/analista-informe.php' );
$js = tci_analysis_source( $root, 'includes/analysis/assets/analista-lazy.js' );
tci_analysis_source( $root, 'includes/analysis/assets/analista-informe.css' );

tci_analysis_check(
    false === strpos( $main, 'SEO_SYSTEM_URL' ) &&
    false === strpos( $main, 'SEO_SYSTEM_VERSION' ),
    'Old SEO plugin constants still control Insights'
);
tci_analysis_check(
    false === strpos( $main, 'requiere el motor de informes del antiguo' ),
    'Old monolith dependency notice remains'
);
tci_analysis_check(
    false !== strpos( $main, "require_once TAXOCOMMERCE_GOOGLE_BING_PATH . 'includes/analysis/analista-bootstrap.php'" ) &&
    false !== strpos( $main, "require_once TAXOCOMMERCE_GOOGLE_BING_PATH . 'includes/analysis/analista-geo-bridge.php'" ),
    'Analysis engine or own dispatcher not included'
);
tci_analysis_check(
    false !== strpos( $bootstrap, "'/analista-lazy.php'" ) &&
    false !== strpos( $bootstrap, "'/analista-informe.php'" ) &&
    false !== strpos( $lazy, 'function seo_analista_lazy_reports()' ) &&
    false !== strpos( $lazy, 'function seo_analista_lazy_render_partial(' ) &&
    false !== strpos( $bridge, 'function taxocommerce_google_bing_analysis_reports()' ),
    'Own historical report engine not bundled'
);
$reports = array(
    'directrices', 'resumen', 'search_console', 'contenido', 'estructura',
    'catalogo', 'tendencias', 'trabajo', 'competencia', 'proveedores', 'fuentes',
);
foreach ( $reports as $report ) {
    tci_analysis_check(
        false !== strpos( $lazy, "'" . $report . "' => array(" ),
        'Missing source report: ' . $report
    );
}
tci_analysis_check(
    false !== strpos( $bridge, "unset( \$reports['trabajo'] )" ) &&
    false !== strpos( $bridge, "'wp_ajax_taxocommerce_insights_analysis'" ),
    'Insights merged dashboard or AJAX dispatcher is missing'
);
tci_analysis_check(
    false !== strpos( $main, "TAXOCOMMERCE_GOOGLE_BING_URL . 'includes/analysis/assets/analista-lazy.js'" ) &&
    false !== strpos( $main, "wp_create_nonce( 'taxocommerce_insights_analysis' )" ) &&
    false !== strpos( $js, "action: 'taxocommerce_insights_analysis'" ),
    'Insights JS does not use its own authenticated AJAX action'
);
tci_analysis_check(
    false !== strpos( $css_owner, "TAXOCOMMERCE_GOOGLE_BING_URL . 'includes/analysis/assets/analista-informe.css'" ) &&
    false !== strpos( $css_owner, "add_action('admin_enqueue_scripts', 'taxocommerce_insights_enqueue_analysis_styles')" ),
    'Insights CSS is not enqueued from the included module'
);

$stylesheet = tci_analysis_source( $root, 'includes/analysis/assets/analista-informe.css' );
tci_analysis_check( false !== strpos( $css_owner, "'taxocommerce-insights-analysis'" ), 'Insights stylesheet handle must not collide with legacy Analista' );
tci_analysis_check( false === strpos( $css_owner, "if (!function_exists('seo_analista_enqueue_report_assets'))" ), 'Legacy callback collision remains' );
tci_analysis_check( false !== strpos( $stylesheet, '.seo-analista-chart .line{fill:none;stroke:#2271b1' ), 'Blue SVG line style absent' );
tci_analysis_check( false !== strpos( $stylesheet, '.seo-analista-card,.seo-analista-section,.seo-analista-chart{background:#fff' ), 'White cards style absent' );

$ga4_analysis = tci_analysis_source( $root, 'includes/analysis/analista-google.php' );
$ga4_strategy = tci_analysis_source( $root, 'includes/analysis/analista-estrategia.php' );
$ga4_competitive = tci_analysis_source( $root, 'includes/analysis/analista-competitivo.php' );
$ga4_source = tci_analysis_source( $root, 'includes/analysis/analista-fuentes.php' );
tci_analysis_check(
    false !== strpos( $ga4_analysis, 'seo_analista_ga4_snapshot($days);' ) &&
    false !== strpos( $ga4_strategy, 'seo_analista_ga4_snapshot($days) : array();' ) &&
    false !== strpos( $ga4_competitive, 'seo_analista_ga4_snapshot($days) : array();' ),
    'GA4 Analysis callers must not be anchored to local GSC dates'
);
tci_analysis_check(
    false !== strpos( $ga4_source, "new DateTimeImmutable('yesterday', wp_timezone())" ) &&
    false !== strpos( $ga4_source, "'seo_analista_ga4_v4_'" ),
    'GA4 default range/cache regression'
);

$market_competition = tci_analysis_source( $root, 'includes/analysis/analista-competitivo.php' );
$merchant_source = tci_analysis_source( $root, 'includes/class-taxocommerce-google-bing-merchant.php' );
tci_analysis_check(
    false !== strpos( $lazy, 'taxocommerce_insights_competitive_render_panel($days);' ) &&
    false === strpos( $market_competition, 'seo_analista_competitive_public_audit' ) &&
    false === strpos( $market_competition, 'seo_analista_competitive_serp_rows' ) &&
    false === strpos( $market_competition, 'wp_safe_remote_get(' ) &&
    false !== strpos( $market_competition, 'TaxoCommerce_Google_Bing_Merchant::snapshots()' ) &&
    false !== strpos( $merchant_source, 'competitive_visibility( array $category_ids' ),
    'Competition must use official Merchant sources without paid SERP/crawler dependency'
);

// Execute the new market report regression under the existing CI preflight.
require_once __DIR__ . '/competition-market-smoke.php';

echo "Standalone Insights Analysis: 11 report definitions, own CSS, JS, AJAX, no SEO monolith references: OK\n";
