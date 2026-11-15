<?php
/**
 * Static regression contract for bounded full Insights JSON export.
 *
 * @package TaxoCommerce
 */
$root = dirname( __DIR__ );
$plugin = file_get_contents( $root . '/taxocommerce-google-bing.php' );
$bootstrap = file_get_contents( $root . '/includes/analysis/analista-bootstrap.php' );
$builder = file_get_contents( $root . '/includes/analysis/analista-json.php' );
$worker = file_get_contents( $root . '/includes/analysis/analista-chunked-export.php' );
$client = file_get_contents( $root . '/includes/analysis/assets/analista-lazy.js' );

function tgb_chunked_assert( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, 'FAIL: ' . $message . PHP_EOL );
        exit( 1 );
    }
}
tgb_chunked_assert( false !== strpos( $bootstrap, "'/analista-chunked-export.php'" ), 'Worker loaded in bootstrap.' );
preg_match( '/Version:\\s*(\\d+\\.\\d+\\.\\d+)/', $plugin, $plugin_version );
tgb_chunked_assert( ! empty( $plugin_version[1] ) && version_compare( $plugin_version[1], '1.1.51', '>=' ), 'Version 1.1.51 or later.' );
tgb_chunked_assert( false === strpos( $plugin, 'id="tgb-chunked-json-start"' ), 'Duplicate export button removed.' );
tgb_chunked_assert( false !== strpos( $plugin, 'id="tgb-analysis-run-all"' ), 'One full report button remains.' );
tgb_chunked_assert( false === strpos( $plugin, 'id="tgb-chunked-json-stop"' ), 'Duplicate stop button removed.' );
tgb_chunked_assert( false !== strpos( $plugin, 'id="tgb-analysis-cancel"' ), 'Unified execution can be stopped.' );
tgb_chunked_assert( false !== strpos( $plugin, 'id="tgb-chunked-json-download"' ), 'Completed download link exists.' );
tgb_chunked_assert( false === strpos( $plugin, 'analista_view_json' ), 'One-shot export no longer used in UI.' );
tgb_chunked_assert( false !== strpos( $builder, "function seo_analista_build_json_export(\$days = 28, \$requested_section = '', \$requested_part = '')" ), 'Builder accepts per-section requests.' );
tgb_chunked_assert( false !== strpos( $builder, "if ('' === \$requested_section) return true;" ), 'Legacy whole-report builder remains possible.' );
tgb_chunked_assert( false !== strpos( $worker, "current_user_can( 'manage_options' )" ), 'Only administrators can export.' );
tgb_chunked_assert( false !== strpos( $worker, 'check_ajax_referer( self::NONCE' ), 'Each block verifies nonce.' );
tgb_chunked_assert( false !== strpos( $worker, "check_admin_referer( self::NONCE, 'nonce' )" ), 'Download verifies nonce.' );
tgb_chunked_assert( false !== strpos( $worker, "flock( \$lock, LOCK_EX | LOCK_NB )" ), 'Prevent duplicate concurrent steps.' );
tgb_chunked_assert( false !== strpos( $worker, "tempnam( get_temp_dir(), 'tgb-insights-' )" ), 'JSON assembled in private temp storage.' );
tgb_chunked_assert( false !== strpos( $worker, 'const COMPLETE_TTL = 86400;' ), 'Completed report persists for 24 hours.' );
tgb_chunked_assert( false !== strpos( $worker, "'updated_at' => time()" ), 'Active export has sliding expiry.' );
tgb_chunked_assert( false !== strpos( $worker, "'nonce'  => wp_create_nonce( self::NONCE )" ), 'AJAX download URL has valid literal nonce parameter.' );
tgb_chunked_assert( false === strpos( $worker, 'return wp_nonce_url(' ), 'AJAX download URL is not HTML-escaped.' );
tgb_chunked_assert( false !== strpos( $worker, "json_decode( \$contents, true );" ), 'Only valid JSON is downloadable.' );
tgb_chunked_assert( false !== strpos( $worker, "getrusage" ), 'Load controller can observe PHP process CPU time.' );
tgb_chunked_assert( false !== strpos( $worker, "memory_get_peak_usage" ), 'Load controller observes memory.' );
tgb_chunked_assert( false !== strpos( $client, "action: 'taxocommerce_insights_chunked_export'" ), 'Browser uses dedicated AJAX action.' );
tgb_chunked_assert( false !== strpos( $client, 'var exportStart = fullButton;' ), 'Full run button starts chunked worker.' );
tgb_chunked_assert( false !== strpos( $client, "exportAjax('status')" ), 'Latest completed file is restored after refresh.' );
tgb_chunked_assert( false !== strpos( $client, "state.complete && state.download_url" ), 'Download only enabled after completed job.' );
tgb_chunked_assert( false !== strpos( $client, "setTimeout(exportNext" ), 'Pacing between independent requests.' );
tgb_chunked_assert( false === strpos( $client, "new Blob([content]" ), 'No client-side full JSON Blob.' );
echo "PASS: Insights chunked JSON export regression contracts.\n";
