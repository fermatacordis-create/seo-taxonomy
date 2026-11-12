<?php
/**
 * Guard Core's installed-domain identity when WordPress.org uses a different
 * assigned public directory name. Read-only; no WordPress boot or database.
 */
declare( strict_types=1 );

$root = dirname( __DIR__ );
$slug = basename( $root );
if ( 'taxocommerce-core' !== $slug ) {
    fwrite( STDERR, "FAIL: installed Core folder changed\n" );
    exit( 1 );
}
$main = file_get_contents( $root . '/taxocommerce-core.php' );
$readme = file_get_contents( $root . '/readme.txt' );
if ( false === $main || false === $readme ) {
    throw new RuntimeException( 'Missing installed Core main file or readme' );
}
if ( ! preg_match( '/^\s*\*\s*Text Domain:\s*taxocommerce-core\s*$/m', $main ) ) {
    throw new RuntimeException( 'Installed Core must use its own directory gettext domain' );
}
if ( ! preg_match( '/^\s*\*\s*Version:\s*([0-9.]+)\s*$/m', $main, $version ) ||
     ! preg_match( '/^Stable tag:\s*([0-9.]+)\s*$/m', $readme, $stable ) ||
     $version[1] !== $stable[1] ) {
    throw new RuntimeException( 'Core release header and stable tag differ' );
}
foreach ( array( 'es_ES', 'en_US', 'fr_FR', 'de_DE', 'nl_NL' ) as $locale ) {
    foreach ( array( '.po', '.mo' ) as $extension ) {
        $catalog = $root . '/languages/taxocommerce-core-' . $locale . $extension;
        if ( ! is_readable( $catalog ) ) {
            throw new RuntimeException( 'Missing installed translation catalog: ' . $catalog );
        }
    }
}
echo "PASS: installed Core directory, release metadata and five gettext catalogs\n";
