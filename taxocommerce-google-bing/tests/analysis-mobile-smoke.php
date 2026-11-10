<?php
/**
 * Static regression checks for the mobile Analysis route and full JSON export.
 *
 * @package TaxoCommerce
 */
$root = dirname( __DIR__ );
$main = file_get_contents( $root . '/taxocommerce-google-bing.php' );
$bridge = file_get_contents( $root . '/includes/analysis/analista-geo-bridge.php' );
$export = file_get_contents( $root . '/includes/analysis/analista-json.php' );
$client = file_get_contents( $root . '/includes/analysis/assets/analista-lazy.js' );
$workflow = file_get_contents( dirname( __DIR__, 2 ) . '/.github/workflows/deploy-staging.yml' );

function tgb_assert_mobile( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, 'FAIL: ' . $message . PHP_EOL );
        exit( 1 );
    }
}

tgb_assert_mobile( false !== strpos( $main, "taxocommerce_google_bing_analysis_screen();" ), 'Active tab renders standalone Analysis.' );
tgb_assert_mobile( false === strpos( $main, "seo_analista_render_report();" ), 'Legacy screen must not override the Insights nonce.' );
tgb_assert_mobile( false !== strpos( $main, "wp_create_nonce( 'taxocommerce_insights_analysis' )" ), 'Insights screen produces the matching nonce.' );
tgb_assert_mobile( false !== strpos( $bridge, "check_ajax_referer( 'taxocommerce_insights_analysis', 'nonce' )" ), 'Insights AJAX verifies same nonce.' );
tgb_assert_mobile( false !== strpos( $client, "action: 'taxocommerce_insights_analysis'" ), 'Browser uses Insights AJAX route.' );
tgb_assert_mobile( false !== strpos( $main, 'id="tgb-analysis-run-all"' ), 'Complete Analysis button present.' );
tgb_assert_mobile( false !== strpos( $main, 'id="tgb-analysis-cancel"' ), 'Cancel button present.' );
tgb_assert_mobile( false !== strpos( $client, 'finished === total' ), 'Complete report verifies every block.' );
tgb_assert_mobile( false !== strpos( $client, 'next();' ), 'Reports execute sequentially.' );
tgb_assert_mobile( false !== strpos( $client, "el informe NO está completo." ), 'Partial runs are not presented as complete.' );
tgb_assert_mobile( false !== strpos( $export, 'seo_analista_build_json_export($days)' ), 'JSON keeps full existing builder.' );
tgb_assert_mobile( false !== strpos( $main, 'analista_view_json' ), 'Mobile JSON browser fallback displayed.' );
tgb_assert_mobile( false !== strpos( $export, "check_admin_referer('seo_analista_export_json', 'seo_analista_nonce')" ), 'JSON requires signed administrator access.' );
tgb_assert_mobile( false !== strpos( $export, "'inline' : 'attachment'" ), 'Export can be viewed without overriding normal download.' );
tgb_assert_mobile( false === strpos( $main, "'executive'        =>" ), 'Redundant executive navigation removed.' );
tgb_assert_mobile( false === strpos( $main, 'class-taxocommerce-insights-executive.php' ), 'Executive component is not loaded.' );
tgb_assert_mobile( false === strpos( $workflow, '/tests/executive-smoke.php' ), 'Removed feature not required by deployment CI.' );
echo "PASS: unified mobile Analysis, matching AJAX nonce, sequential reports and full JSON export.\n";
