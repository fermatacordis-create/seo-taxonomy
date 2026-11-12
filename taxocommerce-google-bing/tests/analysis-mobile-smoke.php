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
tgb_assert_mobile( false !== strpos( $bridge, 'taxocommerce_insights_action_plan_export( $plan, $days )' ), 'Plan export reuses same decision plan.' );
tgb_assert_mobile( false !== strpos( $client, 'showSavedPlanDate(response.data)' ), 'Plan download date is updated after report succeeds.' );
tgb_assert_mobile( false === strpos( $client, 'showPlanJson(' ), 'Plan download must not be appended after the report.' );
tgb_assert_mobile( false === strpos( $client, 'new Blob([content]' ), 'Plan download uses a saved server-side JSON without a browser Blob.' );
tgb_assert_mobile( false !== strpos( $main, 'id="tgb-plan-json-download"' ), 'The plan has a JSON download button inside its card.' );
tgb_assert_mobile( false !== strpos( $main, 'tgb-report-date' ), 'Each report card displays the last generated report timestamp.' );
tgb_assert_mobile( false !== strpos( $main, "'planDownloadUrl' => taxocommerce_insights_plan_json_download_url()" ), 'Signed download URL supplied to client.' );
tgb_assert_mobile( false !== strpos( $bridge, "add_action( 'admin_post_taxocommerce_insights_plan_json_download'" ), 'Separate authenticated admin-post download endpoint.' );
tgb_assert_mobile( false !== strpos( $bridge, "check_admin_referer( 'taxocommerce_insights_plan_json_download' )" ), 'Plan download requires its own nonce.' );
tgb_assert_mobile( false !== strpos( $bridge, "get_option( 'taxocommerce_insights_last_plan_json', array() )" ), 'Plan download reads saved JSON, not a new analysis.' );
tgb_assert_mobile( false !== strpos( $main, "'planDownloadLabel' =>" ), 'Download label passed to client.' );
tgb_assert_mobile( false !== strpos( $main, 'id="tgb-analysis-run-all"' ), 'Complete Analysis button present.' );
tgb_assert_mobile( false !== strpos( $main, 'id="tgb-analysis-cancel"' ), 'Cancel button present.' );
tgb_assert_mobile( false !== strpos( $client, 'finished === total' ), 'Complete report verifies every block.' );
tgb_assert_mobile( false !== strpos( $client, 'next();' ), 'Reports execute sequentially.' );
tgb_assert_mobile( false !== strpos( $client, "el informe NO está completo." ), 'Partial runs are not presented as complete.' );
tgb_assert_mobile( false !== strpos( $export, 'function seo_analista_build_json_export($days = 28, $requested_section = \'\', $requested_part = \'\')' ), 'Builder accepts bounded sections while retaining the complete legacy mode.' );
tgb_assert_mobile( false !== strpos( $main, 'id="tgb-chunked-json-start"' ), 'Complete JSON uses chunked export control.' );
tgb_assert_mobile( false !== strpos( $main, 'id="tgb-chunked-json-download"' ), 'JSON download appears after server-side assembly.' );
tgb_assert_mobile( false !== strpos( $client, "exportAjax('step')" ), 'Complete JSON requests independent blocks.' );
tgb_assert_mobile( false !== strpos( $client, 'exportTimer = setTimeout(exportNext' ), 'Client respects server workload backoff.' );
tgb_assert_mobile( false === strpos( $main, 'analista_view_json' ), 'One-shot mobile JSON route is no longer exposed in Insights UI.' );
tgb_assert_mobile( false !== strpos( $export, "check_admin_referer('seo_analista_export_json', 'seo_analista_nonce')" ), 'JSON requires signed administrator access.' );
tgb_assert_mobile( false !== strpos( $export, "'inline' : 'attachment'" ), 'Export can be viewed without overriding normal download.' );
tgb_assert_mobile( false === strpos( $main, "'executive'        =>" ), 'Redundant executive navigation removed.' );
tgb_assert_mobile( false === strpos( $main, 'class-taxocommerce-insights-executive.php' ), 'Executive component is not loaded.' );
tgb_assert_mobile( false === strpos( $workflow, '/tests/executive-smoke.php' ), 'Removed feature not required by deployment CI.' );
tgb_assert_mobile( false !== strpos( $main, 'seo-analista-report-grid' ), 'All ten reports share a single card grid.' );
tgb_assert_mobile( false !== strpos( $main, '<article class="seo-analista-report-card"' ), 'Cards are non-interactive articles, not oversized buttons.' );
tgb_assert_mobile( false !== strpos( $main, 'Crear informe' ), 'Every report has its own create button.' );
tgb_assert_mobile( false !== strpos( $main, 'tgb-report-json-button' ), 'Every report has its own JSON download link.' );
tgb_assert_mobile( false !== strpos( $bridge, 'taxocommerce_insights_last_report_export' ), 'Last generated specialist reports are cached separately.' );
tgb_assert_mobile( false !== strpos( $bridge, "add_action( 'admin_post_taxocommerce_insights_report_json_download'" ), 'Individual JSON downloads use an authenticated WordPress endpoint.' );
tgb_assert_mobile( false !== strpos( $bridge, "check_admin_referer( 'taxocommerce_insights_report_json_download' )" ), 'Individual downloads check their nonce.' );
tgb_assert_mobile( false !== strpos( $client, 'showSavedReport(item, response.data)' ), 'Individual creation updates its saved download without rerunning reports.' );
$styles = file_get_contents( $root . '/includes/analysis/assets/analista-informe.css' );
tgb_assert_mobile( false !== strpos( $styles, 'grid-template-columns:repeat(5,minmax(0,1fr))' ), 'Desktop displays five report columns.' );
tgb_assert_mobile( false !== strpos( $main, 'seo-analista-toolbar-actions' ), 'Full run and stop controls use compact action layout.' );
tgb_assert_mobile( false !== strpos( $styles, '.seo-analista-wrap .seo-analista-report-actions .button.button-primary{background:#2271b1;border-color:#2271b1;color:#fff}' ), 'Create report text is readable against a visible primary background.' );
tgb_assert_mobile( false !== strpos( $styles, '.seo-analista-wrap .seo-analista-toolbar-actions .button.button-primary' ), 'Full-report button also overrides the white-on-white legacy style.' );
tgb_assert_mobile( false !== strpos( $styles, '.seo-analista-wrap .seo-analista-report-actions .button.disabled{color:#646970;background:#f6f7f7;border-color:#c3c4c7;opacity:1}' ), 'Disabled JSON downloads remain readable.' );
tgb_assert_mobile( false !== strpos( $client, "label: button.closest('.seo-analista-report-card').find('h3').first().text()" ), 'Running an individual report uses its visible card heading for status.' );
tgb_assert_mobile( false === strpos( $client, "label: button.find('strong').first().text()" ), 'No stale title lookup from the old full-card buttons.' );
echo "PASS: unified mobile Analysis, matching AJAX nonce, sequential reports and chunked full JSON export.\n";
