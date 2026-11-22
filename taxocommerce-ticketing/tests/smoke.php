<?php
/**
 * Static smoke checks for the standalone plugin bootstrap.
 * Run from repository root: php taxocommerce-ticketing/tests/smoke.php
 */
$base = dirname( __DIR__ );
$entry = file_get_contents( $base . '/taxocommerce-ticketing.php' );
$core  = file_get_contents( $base . '/includes/class-tctk-ticketing.php' );
$checks = array(
    'plugin header' => false !== strpos( $entry, 'Plugin Name: TaxoCommerce Ticketing' ),
    'separate plugin' => false === strpos( $entry, "require_once __DIR__ . '/../taxocommerce-tasks" ),
    'standalone shortcode' => false !== strpos( $core, "'taxocommerce_ticket_form'" ),
    'private downloads' => false !== strpos( $core, 'public static function download()' ),
    'customer token' => false !== strpos( $core, "hash_hmac( 'sha256'" ),
    'message history' => false !== strpos( $core, 'CREATE TABLE {$tables[\'messages\']}' ),
    'settings' => false !== strpos( $core, 'public static function save_settings()' ),
    'staff interface' => false !== strpos( $core, 'public static function admin()' ),
    'optional tasks hook' => false !== strpos( $core, "'tctk_ticket_admin_actions'" ),
);
foreach ( $checks as $label => $passes ) {
    if ( ! $passes ) {
        fwrite( STDERR, "FAIL: {$label}\n" );
        exit( 1 );
    }
}
echo "Ticketing static smoke: OK\n";
