<?php
/**
 * Core official information stays local, even if POST requests or legacy
 * network caches exist. No WordPress installation or HTTP connection needed.
 *
 * @package TaxoCommerceCore
 */
define( 'ABSPATH', __DIR__ . '/' );
$GLOBALS['tc_official_calls'] = 0;
function __( $message, $domain = '' ) { return $message; }
function get_file_data( $file, $headers, $context = '' ) {
    $content = file_get_contents( $file );
    $data = array();
    foreach ( $headers as $field => $label ) {
        preg_match( '/^\s*\*\s*' . preg_quote( $label, '/' ) . ':\s*(.*?)\s*$/mi', $content, $m );
        $data[ $field ] = isset( $m[1] ) ? trim( $m[1] ) : '';
    }
    return $data;
}
function wp_strip_all_tags( $value ) { return strip_tags( $value ); }
function esc_html( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $value ) { return esc_html( $value ); }
function esc_url_raw( $value, $schemes = array() ) { return (string) $value; }
function wp_safe_remote_get( $url, $args = array() ) {
    ++$GLOBALS['tc_official_calls'];
    throw new RuntimeException( 'Core official panel must never call the network.' );
}

$path = dirname( __DIR__, 3 ) . '/taxocommerce-core/';
$info = require $path . 'includes/official-excerpt.php';
$plugin = get_file_data( $path . 'taxocommerce-core.php', array( 'uri' => 'Plugin URI', 'description' => 'Description' ), 'plugin' );
foreach ( array( 'es' => 'Información del plugin', 'en' => 'Plugin information', 'fr' => 'Informations sur le plugin', 'de' => 'Plugininformationen', 'nl' => 'Plugininformatie' ) as $locale => $label ) {
    ob_start();
    $info->render( 'taxocommerce-core', $locale );
    $markup = ob_get_clean();
    if ( false === strpos( $markup, esc_html( $plugin['description'] ) )
        || false === strpos( $markup, esc_html( $plugin['uri'] ) )
        || false === strpos( $markup, '<summary>' . $label . '</summary>' ) ) {
        throw new RuntimeException( 'Core must show local Description and Plugin URI in locale ' . $locale );
    }
}
if ( 0 !== $GLOBALS['tc_official_calls'] ) {
    throw new RuntimeException( 'Unexpected network activity.' );
}
$dashboard = file_get_contents( $path . 'includes/class-taxocommerce-core-dashboard.php' );
if ( false === strpos( $dashboard, "\$tc_official_info->render( 'taxocommerce-core', \$tc_locale )" )
    || false !== strpos( $dashboard, 'render_official_info(' )
    || false !== strpos( $dashboard, 'tc_core_load_official_excerpt' ) ) {
    throw new RuntimeException( 'Core dashboard must use independent local renderer directly.' );
}
echo "Core static developer information: PASS\n";
