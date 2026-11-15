<?php
/**
 * Tests family discovery with WordPress-like plugin inventory, WITHOUT WP.
 * No shops, plugins, external APIs, or credentials are modified.
 */
define( 'ABSPATH', __DIR__ . '/' );
function __( $text, $domain = '' ) { return $text; }
function admin_url( $path = '' ) { return 'https://example.test/wp-admin/' . $path; }
function sanitize_key( $key ) { return preg_replace( '/[^a-z0-9_-]/', '', strtolower( (string) $key ) ); }
function trailingslashit( $path ) { return rtrim( $path, '/' ) . '/'; }
function apply_filters( $hook, $value ) { return $value; }
function wp_upload_dir() { return array( 'basedir' => $GLOBALS['tcc_test_uploads'], 'error' => false ); }
function get_plugins() { return $GLOBALS['tcc_test_plugins']; }
function tcc_assert( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}
$GLOBALS['tcc_test_plugins'] = array(
    'taxocommerce-core/taxocommerce-core.php' => array(
        'Name' => 'TaxoCommerce Core', 'Version' => '0.4.85', 'Description' => 'Core',
    ),
    'taxocommerce-suppliers/taxocommerce-suppliers.php' => array(
        'Name' => 'TaxoCommerce Suppliers', 'Version' => '0.2.27', 'Description' => 'Suppliers',
    ),
    'taxocommerce-customers/taxocommerce-customers.php' => array(
        'Name' => 'TaxoCommerce Customers', 'Version' => '0.1.1', 'Description' => 'Customer management',
    ),
    'taxocommerce-accounting/taxocommerce-accounting.php' => array(
        'Name' => 'TaxoCommerce Accounting', 'Version' => '0.1.0', 'Description' => 'Accounting',
    ),
    'taxocommerce-strange-other/taxocommerce-strange-other.php' => array(
        'Name' => 'Unrelated Vendor', 'Version' => '1.0', 'Description' => 'Not family',
    ),
    'unrelated-taxocommerce/other.php' => array(
        'Name' => 'TaxoCommerce Impostor', 'Version' => '1.0', 'Description' => 'Outside prefix',
    ),
);
$GLOBALS['tcc_test_uploads'] = sys_get_temp_dir() . '/tcc-extensions-' . getmypid();
$package_dir = $GLOBALS['tcc_test_uploads'] . '/taxocommerce-core/plugins';
if ( ! is_dir( $package_dir ) && ! mkdir( $package_dir, 0700, true ) ) exit( 1 );
file_put_contents( $package_dir . '/taxocommerce-brand-new.zip', 'ZIP-fixture' );
file_put_contents( $package_dir . '/other-company.zip', 'ZIP-fixture' );
file_put_contents( $package_dir . '/taxocommerce-suppliers.zip', 'ZIP-fixture' );

require_once dirname( __DIR__ ) . '/includes/class-taxocommerce-core.php';
$reflection = new ReflectionClass( 'TaxoCommerce_Core' );
$core = $reflection->newInstanceWithoutConstructor();
$catalog = $reflection->getMethod( 'family_extensions' );
$result = $catalog->invoke( $core );
tcc_assert( isset( $result['customers'] ), 'Inactive Customers must be listed automatically' );
tcc_assert( isset( $result['accounting'] ), 'Inactive Accounting must be listed automatically' );
tcc_assert( 'taxocommerce-customers/taxocommerce-customers.php' === $result['customers']['plugin_file'], 'Customers basename must match WordPress' );
tcc_assert( 'taxocommerce-accounting/taxocommerce-accounting.php' === $result['accounting']['plugin_file'], 'Accounting basename must match WordPress' );
tcc_assert( isset( $result['suppliers'] ), 'Existing built-in Suppliers must be preserved' );
tcc_assert( 1 === count( array_filter( $result, static function ( $entry ) {
    return 'taxocommerce-suppliers' === ( $entry['org_slug'] ?? '' );
} ) ), 'Suppliers must not be duplicated by ZIP or plugin discovery' );
tcc_assert( isset( $result['brand_new'] ), 'Not-yet-installed family ZIP must be discoverable' );
tcc_assert( 'taxocommerce-brand-new' === $result['brand_new']['org_slug'], 'ZIP slug must be derived from filename' );
tcc_assert( ! isset( $result['strange_other'] ), 'Unrelated vendor in family-looking folder is excluded' );
tcc_assert( ! isset( $result['core'] ), 'Core itself must not appear as a separate extension card' );
tcc_assert( ! isset( $result['other_company'] ), 'Non-family ZIP must be excluded' );
tcc_assert( count( $result ) === count( array_unique( array_column( $result, 'org_slug' ) ) ), 'No duplicate family package slugs' );

$source = file_get_contents( dirname( __DIR__ ) . '/includes/class-taxocommerce-core.php' );
tcc_assert( false !== strpos( $source, "'activate-plugin_' . $plugin_file" ), 'Inactive plugins use protected native WP activation' );
tcc_assert( false !== strpos( $source, "admin_url( 'plugin-install.php?tab=upload' )" ), 'ZIP installation uses native WordPress upload screen' );
tcc_assert( false !== strpos( $source, "'taxocommerce_core_download_extension'" ), 'Family ZIPs can be downloaded from the Core card' );
tcc_assert( false === strpos( $source, 'new Plugin_Upgrader(' ), 'No custom installer is reintroduced after WordPress.org review' );

foreach ( glob( $package_dir . '/*.zip' ) as $file ) unlink( $file );
rmdir( $package_dir );
rmdir( dirname( $package_dir ) );
rmdir( $GLOBALS['tcc_test_uploads'] );
echo "PASS: dynamic Core family discovery, deduplication, ZIP discovery and native WordPress action links\n";
