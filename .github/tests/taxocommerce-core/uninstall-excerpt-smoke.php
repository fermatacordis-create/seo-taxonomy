<?php
/**
 * Core uninstall must delete its own official excerpt and no other options.
 *
 * @package TaxoCommerceCore
 */
define( 'WP_UNINSTALL_PLUGIN', 'taxocommerce-core/taxocommerce-core.php' );
$GLOBALS['options'] = array();
$GLOBALS['transients'] = array();
$uri = 'https://asociacionfocazul.com/taxocommerce-core/';
$hash = md5( $uri );
$GLOBALS['options']['taxocommerce_core_version'] = '0.4.70';
$GLOBALS['options']['tc_official_excerpt_good_' . $hash] = 'verified';
$GLOBALS['options']['other_plugin_setting'] = 'keep';
$GLOBALS['transients']['tc_official_excerpt_v2_' . $hash] = array( 'text' => 'verified' );
$GLOBALS['transients']['tc_official_excerpt_' . $hash] = array( 'text' => 'old' );
$GLOBALS['transients']['tc_official_excerpt_v2_' . md5( 'https://example.com/other/' )] = array( 'text' => 'untouched' );
function get_file_data( $file, $fields, $context = '' ) {
    return array( 'uri' => 'https://asociacionfocazul.com/taxocommerce-core/' );
}
function esc_url_raw( $uri, $protocols = array() ) { return $uri; }
function delete_option( $name ) { unset( $GLOBALS['options'][ $name ] ); return true; }
function delete_transient( $name ) { unset( $GLOBALS['transients'][ $name ] ); return true; }
require dirname( __DIR__, 3 ) . '/taxocommerce-core/uninstall.php';
if ( isset( $GLOBALS['options']['taxocommerce_core_version'] ) ) {
    throw new RuntimeException( 'Core options were not cleared.' );
}
if ( isset( $GLOBALS['options']['tc_official_excerpt_good_' . $hash] )
    || isset( $GLOBALS['transients']['tc_official_excerpt_v2_' . $hash] )
    || isset( $GLOBALS['transients']['tc_official_excerpt_' . $hash] ) ) {
    throw new RuntimeException( 'Developer excerpt retained after uninstall.' );
}
if ( 'keep' !== $GLOBALS['options']['other_plugin_setting']
    || ! isset( $GLOBALS['transients']['tc_official_excerpt_v2_' . md5( 'https://example.com/other/' )] ) ) {
    throw new RuntimeException( 'Core uninstall removed other plugin data.' );
}
echo "Core excerpt uninstall cleanup: PASS\n";
