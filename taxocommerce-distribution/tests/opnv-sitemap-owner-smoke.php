<?php
/**
 * Regression for the shared sitemap contract with OPNV, without WordPress.
 *
 * Default: Distribution owns /sitemap.xml. When OPNV claims it through
 * `taxocommerce_sitemap_owner`, Distribution stops serving, editing robots.txt,
 * disabling the core sitemap and scheduling builds, and hands its product
 * category URLs to OPNV through `opnv_sitemap_groups`.
 * Run normally and with "legacy" to verify the legacy coexistence mode.
 */
define( 'ABSPATH', __DIR__ );
define( 'TCDIST_LEGACY_SITEMAP_ACTIVE', in_array( 'legacy', $argv ?? array(), true ) );
$GLOBALS['hooks']  = array();
$GLOBALS['owner']  = 'taxocommerce';
$GLOBALS['events'] = array();
$GLOBALS['builds'] = 0;
function add_action( $name, $callback, $priority = 10, $args = 1 ) { $GLOBALS['hooks'][ $name ][] = $callback; }
function add_filter( $name, $callback, $priority = 10, $args = 1 ) { $GLOBALS['hooks'][ $name ][] = $callback; }
function apply_filters( $name, $value ) {
    if ( 'taxocommerce_sitemap_owner' === $name ) { return $GLOBALS['owner']; }
    foreach ( $GLOBALS['hooks'][ $name ] ?? array() as $cb ) { $value = call_user_func( $cb, $value ); }
    return $value;
}
function sanitize_key( $key ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) ); }
function home_url( $path = '/' ) { return 'https://example.test' . $path; }
function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
function get_query_var( $name ) { throw new RuntimeException( 'The external owner must return before reading the request.' ); }
function wp_next_scheduled( $name ) { return $GLOBALS['events'][ $name ] ?? false; }
function wp_schedule_event( $time, $recurrence, $name ) { $GLOBALS['events'][ $name ] = $time; return true; }
function wp_clear_scheduled_hook( $name ) { unset( $GLOBALS['events'][ $name ] ); }
function tcdist_sitemap_job_state() { return array( 'status' => '' ); }
function tcdist_sitemap_start_background_build() { ++$GLOBALS['builds']; return array( 'type' => 'success' ); }
function check_owner( $test, $label ) {
    if ( ! $test ) { fwrite( STDERR, 'FAIL: ' . $label . PHP_EOL ); exit( 1 ); }
}

require dirname( __DIR__ ) . '/includes/sitemaps/engine.php';
require dirname( __DIR__ ) . '/includes/sitemaps/daily.php';
$hook = TCDIST_SITEMAP_DAILY_HOOK;

if ( TCDIST_LEGACY_SITEMAP_ACTIVE ) {
    $GLOBALS['owner'] = 'opnv';
    check_owner( '' === tcdist_sitemap_external_owner(), 'Legacy mode keeps its own contract' );
    check_owner( empty( $GLOBALS['hooks']['opnv_sitemap_groups'] ), 'Legacy mode does not offer groups to OPNV' );
    echo "OPNV sitemap owner (legacy): PASS\n";
    exit;
}

// 1) Default: Distribution owns the public sitemap.
check_owner( '' === tcdist_sitemap_external_owner(), 'Distribution owns the sitemap by default' );
check_owner( false === tcdist_sitemap_disable_wordpress_core_sitemaps( true ), 'Default owner disables the core sitemap' );
check_owner( array() === tcdist_sitemap_offer_groups_to_opnv( array() ), 'Nothing is offered while Distribution owns the sitemap' );
tcdist_sitemap_ensure_daily_refresh();
check_owner( isset( $GLOBALS['events'][ $hook ] ), 'Default owner schedules the daily build' );

// 2) OPNV claims it.
$GLOBALS['owner'] = 'opnv';
check_owner( 'opnv' === tcdist_sitemap_external_owner(), 'OPNV claim is recognised' );
check_owner( true === tcdist_sitemap_disable_wordpress_core_sitemaps( true ), 'Core sitemap decision is left to the owner' );
$robots = "User-agent: *\nDisallow: /wp-admin/\n\nSitemap: https://example.test/opnv-sitemap.xml\n";
check_owner( $robots === tcdist_sitemap_add_sitemap_to_robots( $robots, true ), 'robots.txt is left untouched' );
tcdist_sitemap_maybe_serve_sitemap(); // Must return before touching the request.
tcdist_sitemap_register_sitemap_rewrite_rules(); // Must not call add_rewrite_rule (undefined here).
$groups = apply_filters( 'opnv_sitemap_groups', array( 'other' => 'strlen' ) );
check_owner( isset( $groups['other'] ), 'Groups from other plugins are preserved' );
check_owner( 'tcdist_sitemap_get_product_category_sitemap_urls' === ( $groups['product-categories'] ?? '' ), 'Product categories are offered to OPNV' );
check_owner( is_callable( $groups['product-categories'] ), 'Offered group is callable' );
tcdist_sitemap_ensure_daily_refresh();
check_owner( ! isset( $GLOBALS['events'][ $hook ] ), 'Daily build is unscheduled when OPNV owns the sitemap' );
tcdist_sitemap_daily_refresh();
check_owner( 0 === $GLOBALS['builds'], 'No sitemap files are built for an external owner' );

// 3) Unknown or empty values fall back to Distribution.
$GLOBALS['owner'] = '';
check_owner( '' === tcdist_sitemap_external_owner(), 'Empty owner falls back to Distribution' );

echo "OPNV sitemap owner: PASS\n";
