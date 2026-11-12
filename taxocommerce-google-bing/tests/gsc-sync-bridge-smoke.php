<?php
/**
 * Offline regression for Google Search Console historical sync -> modern
 * OAuth / service-account credentials and GA4 standalone Analysis integration.
 * No WordPress instance and no real Google tokens/API calls required.
 */
define( 'ABSPATH', __DIR__ );
define( 'DAY_IN_SECONDS', 86400 );

class WP_Error {
    private $message;
    public function __construct( $code, $message ) { $this->message = $message; }
    public function get_error_message() { return $this->message; }
}
function is_wp_error( $v ) { return $v instanceof WP_Error; }
function get_option( $name, $default = false ) {
    return array_key_exists( $name, $GLOBALS['tgb_sync_options'] ) ? $GLOBALS['tgb_sync_options'][ $name ] : $default;
}
function update_option( $name, $value, $autoload = null ) {
    $GLOBALS['tgb_sync_options'][ $name ] = $value;
    $GLOBALS['tgb_sync_writes'][] = $name;
    return true;
}
function wp_parse_args( $args, $defaults ) { return array_merge( $defaults, (array) $args ); }
function wp_json_encode( $v ) { return json_encode( $v ); }
function sanitize_text_field( $v ) { return strip_tags( (string) $v ); }
function wp_remote_retrieve_response_code( $v ) { return (int) $v['code']; }
function wp_remote_retrieve_body( $v ) { return (string) $v['body']; }
function current_time( $type ) { return '2026-11-11 12:00:00'; }
function wp_remote_post( $url, $args ) {
    $header = $args['headers']['Authorization'] ?? '';
    $parts = explode( '/sites/', $url, 2 );
    $property = rawurldecode( explode( '/searchAnalytics/query', $parts[1] ?? '' )[0] );
    $GLOBALS['tgb_sync_requests'][] = array( 'auth' => $header, 'property' => $property );
    $allowed = $GLOBALS['tgb_sync_allowed'][ $header ] ?? array();
    if ( in_array( $property, $allowed, true ) ) {
        return array( 'code' => 200, 'body' => '{"rows":[]}' );
    }
    return array( 'code' => 403, 'body' => '{"error":{"message":"No permission"}}' );
}
final class TaxoCommerce_Google_Bing_OAuth {
    public static function settings() { return $GLOBALS['tgb_sync_oauth']; }
    public static function tokens() { return $GLOBALS['tgb_sync_oauth_tokens']; }
    public static function access_token() { return $GLOBALS['tgb_sync_tokens']['oauth']; }
}
final class TaxoCommerce_Google_Bing_Connections {
    public static function google_settings() { return $GLOBALS['tgb_sync_service']; }
    public static function access_token( $scope = '' ) {
        if ( 'https://www.googleapis.com/auth/webmasters.readonly' !== $scope ) {
            return new WP_Error( 'wrong_scope', 'Wrong GSC scope' );
        }
        return $GLOBALS['tgb_sync_tokens']['service_account'];
    }
}
require dirname( __DIR__ ) . '/includes/class-taxocommerce-google-bing-gsc-sync-bridge.php';

function tgb_sync_assert( $value, $message ) {
    if ( ! $value ) {
        fwrite( STDERR, 'FAIL GSC modern sync: ' . $message . PHP_EOL );
        exit( 1 );
    }
}
function tgb_sync_reset() {
    $GLOBALS['tgb_sync_options'] = array(
        'seo_google_intelligence_settings' => array( 'client_id' => 'OLD', 'property_id' => 'sc-domain:legacy.test' ),
        'seo_google_intelligence_tokens' => array( 'refresh_token' => 'DO-NOT-TOUCH' ),
    );
    $GLOBALS['tgb_sync_writes'] = array();
    $GLOBALS['tgb_sync_requests'] = array();
    $GLOBALS['tgb_sync_oauth'] = array( 'property_id' => 'sc-domain:new.test' );
    $GLOBALS['tgb_sync_oauth_tokens'] = array( 'refresh_token' => 'saved-token' );
    $GLOBALS['tgb_sync_service'] = array(
        'search_console_site_url' => 'https://other.test/',
        'service_account_json' => '{"private_key":"not-exposed"}',
    );
    $GLOBALS['tgb_sync_tokens'] = array( 'oauth' => 'oauth-token', 'service_account' => 'service-token' );
    $GLOBALS['tgb_sync_allowed'] = array();
}
$bridge = 'TaxoCommerce_Google_Bing_GSC_Sync_Bridge';
tgb_sync_reset();
$GLOBALS['tgb_sync_allowed'] = array( 'Bearer oauth-token' => array( 'sc-domain:new.test' ) );
$chosen = $bridge::verify_and_select();
tgb_sync_assert( ! is_wp_error( $chosen ), 'Modern OAuth GSC query must work' );
tgb_sync_assert( 'oauth' === $chosen['auth'], 'OAuth should be selected first when valid' );
tgb_sync_assert( 'sc-domain:new.test' === $bridge::settings( array( 'property_id' => 'sc-domain:legacy.test' ) )['property_id'], 'Analysis must use modern property' );
tgb_sync_assert( 'oauth-token' === $bridge::access_token(), 'Importer must use modern OAuth token' );
$bridge::record_completed();
tgb_sync_assert( '2026-11-11 12:00:00' === get_option( 'tgb_gsc_sync_last_v1' ), 'Last sync tracked in new option' );
tgb_sync_assert( ! in_array( 'seo_google_intelligence_settings', $GLOBALS['tgb_sync_writes'], true ), 'Legacy credentials must never be rewritten' );
tgb_sync_assert( ! in_array( 'seo_google_intelligence_tokens', $GLOBALS['tgb_sync_writes'], true ), 'Legacy OAuth token must never be rewritten' );

tgb_sync_reset();
$GLOBALS['tgb_sync_allowed'] = array( 'Bearer service-token' => array( 'https://other.test/' ) );
$chosen = $bridge::verify_and_select();
tgb_sync_assert( ! is_wp_error( $chosen ) && 'service_account' === $chosen['auth'], 'Service account must work when OAuth property denied' );
tgb_sync_assert( 'https://other.test/' === $bridge::settings( array() )['property_id'], 'Never mix OAuth property with service account' );
tgb_sync_assert( 'service-token' === $bridge::access_token(), 'Importer reuses verified service-account token' );
tgb_sync_assert( 2 === count( $GLOBALS['tgb_sync_requests'] ), 'Should probe OAuth first then service account' );

tgb_sync_reset();
$denied = $bridge::verify_and_select();
tgb_sync_assert( is_wp_error( $denied ), 'Must fail closed when neither Google credential/property pair works' );
tgb_sync_assert( ! isset( $GLOBALS['tgb_sync_options']['tgb_gsc_sync_selection_v1'] ), 'Failed connection cannot persist a verified property' );
tgb_sync_assert( 'DO-NOT-TOUCH' === $GLOBALS['tgb_sync_options']['seo_google_intelligence_tokens']['refresh_token'], 'Legacy saved refresh token remains intact' );

// Source-level regression: the old SQL importer must be loaded only once,
// and its API entry points must delegate to the current credential registry.
$root = dirname( __DIR__ );
$main = file_get_contents( $root . '/taxocommerce-google-bing.php' );
$sync = file_get_contents( $root . '/includes/google/seo-google-info.php' );
$service = file_get_contents( $root . '/includes/class-taxocommerce-google-bing-google-service.php' );
tgb_sync_assert( false !== strpos( $main, "if ( ! function_exists( 'seo_google_get_settings' ) )" ), 'Do not load duplicate Google Intelligence engine' );
tgb_sync_assert( false !== strpos( $main, "require_once TAXOCOMMERCE_GOOGLE_BING_PATH . 'includes/google/seo-google-info.php'" ), 'Standalone Insights must load the missing SQL importer' );
tgb_sync_assert( false !== strpos( $main, 'seo_google_render_sync_status();' ), 'Manual sync UI must be accessible in Insights' );
tgb_sync_assert( false !== strpos( $sync, "apply_filters('taxocommerce_google_bing_gsc_sync_verify_start', true)" ), 'Import must check actual Google access before writing' );
tgb_sync_assert( false !== strpos( $sync, "apply_filters('taxocommerce_google_bing_gsc_sync_access_token', null)" ), 'Importer must use current Google tokens' );
tgb_sync_assert( false !== strpos( $sync, "do_action('taxocommerce_google_bing_gsc_sync_completed')" ), 'Do not overwrite legacy credentials upon completion' );
tgb_sync_assert( false !== strpos( $service, "':runReport'" ), 'GA4 Analysis must use Data API runReport instead of missing method' );
tgb_sync_assert( false !== strpos( $service, "'https://www.googleapis.com/auth/analytics.readonly'" ), 'GA4 must use analytics scope' );
tgb_sync_assert( false === strpos( $service, '::google_analytics_report(' ), 'The old undefined Google Analytics method cannot remain' );
echo "PASS: historical GSC sync restored, modern OAuth -> service-account fallback, GA4 Data API and secret preservation.\n";
