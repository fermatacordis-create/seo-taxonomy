<?php
/**
 * Standalone locale smoke for the 19 independent TaxoCommerce plugins.
 * No WordPress installation, network, database, or plugin code execution.
 * Only the extracted locale-loader closures are executed with WP stubs.
 */
declare(strict_types=1);

$root = dirname( __DIR__, 2 );
$slugs = array(
    'auditor', 'campaigns', 'core', 'database', 'dependiente',
    'distribution', 'facturacion', 'faqs', 'google-bing', 'images',
    'import-export', 'logistica', 'redirects', 'scrappers', 'social',
    'suppliers', 'system-check', 'templates', 'vocabulary',
);
$temporary = sys_get_temp_dir() . '/taxocommerce-locale-' . getmypid();
if ( ! is_dir( $temporary . '/plugins' ) && ! mkdir( $temporary . '/plugins', 0700, true ) ) {
    fwrite( STDERR, "FAIL: temporary language-pack directory unavailable\n" );
    exit( 1 );
}
define( 'WP_LANG_DIR', $temporary );
define( 'TAXOCOMMERCE_CORE_PATH', $root . '/taxocommerce-core/' );

$hooks = array();
$calls = array();
$current_locale = 'en_GB';
$already_loaded = false;

function add_action( $hook, $callback, $priority = 10 ) {
    global $hooks;
    $hooks[ $hook ][] = $callback;
}
function is_textdomain_loaded( $domain ) {
    global $already_loaded;
    return $already_loaded;
}
function determine_locale() {
    global $current_locale;
    return $current_locale;
}
function sanitize_file_name( $name ) {
    return preg_replace( '/[^A-Za-z0-9_.-]/', '', (string) $name );
}
function load_textdomain( $domain, $path ) {
    global $calls;
    $calls[] = array( $domain, $path );
    return true;
}
function verify( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, 'FAIL: ' . $message . "\n" );
        exit( 1 );
    }
}

$cases = array(
    'en_GB' => 'en_US',
    'fr_CA' => 'fr_FR',
    'de_AT' => 'de_DE',
    'nl_BE' => 'nl_NL',
    'es_MX' => 'es_ES',
    'en_US' => 'en_US',
    'fr_FR' => 'fr_FR',
    'pt_BR' => null,
);
$total_cases = 0;

foreach ( $slugs as $slug ) {
    $folder_slug = 'taxocommerce-' . $slug;
    $domain = $folder_slug;
    $directory = $root . '/' . $folder_slug;
    $source = file_get_contents( $directory . '/' . $folder_slug . '.php' );
    verify( false !== $source, $domain . ' main PHP file missing' );
    verify( false !== strpos( $source, 'Text Domain: ' . $domain ), $domain . ' text domain missing' );

    if ( 'core' === $slug ) {
        $start = strpos( $source, 'function taxocommerce_core_load_textdomain() {' );
        $ending = "add_action( 'init', 'taxocommerce_core_load_textdomain', 1 );";
    } else {
        $anchor = strpos( $source, '$locale = sanitize_file_name( determine_locale() );' );
        verify( false !== $anchor, $domain . ' determine_locale missing' );
        $start = strrpos( substr( $source, 0, $anchor ), "add_action( 'init', static function () {" );
        $ending = '}, 1 );';
    }
    verify( false !== $start, $domain . ' loader not found' );
    $end = strpos( $source, $ending, $start );
    verify( false !== $end, $domain . ' loader termination not found' );
    $chunk = substr( $source, $start, $end - $start + strlen( $ending ) );
    verify( false !== strpos( $chunk, "'.l10n.php'" ) || false !== strpos( $chunk, ".l10n.php" ), $domain . ' PHP language pack preference missing' );
    foreach ( array( 'es_ES', 'en_US', 'fr_FR', 'de_DE', 'nl_NL' ) as $canonical ) {
        verify( is_file( $directory . '/languages/' . $domain . '-' . $canonical . '.mo' ),
            $domain . ' missing MO for ' . $canonical );
    }
    // In eval, __DIR__ belongs to this test file; bind it to the actual plugin directory.
    $chunk = str_replace( '__DIR__', var_export( $directory, true ), $chunk );
    $hooks = array();
    eval( $chunk );
    verify( isset( $hooks['init'] ) && 1 === count( $hooks['init'] ),
        $domain . ' expected exactly one translation loader' );
    $loader = $hooks['init'][0];

    foreach ( $cases as $locale => $expected ) {
        $current_locale = $locale;
        $already_loaded = false;
        $calls = array();
        $loader();
        if ( null === $expected ) {
            verify( 0 === count( $calls ), $domain . ' should not load another language for ' . $locale );
        } else {
            verify( 1 === count( $calls ), $domain . ' should load one catalog for ' . $locale );
            verify( $domain === $calls[0][0], $domain . ' loaded incorrect domain' );
            verify( basename( $calls[0][1] ) === $domain . '-' . $expected . '.mo',
                $domain . ' incorrect fallback for ' . $locale );
        }
        ++$total_cases;
    }

    foreach ( array( '.mo', '.l10n.php' ) as $extension ) {
        $official = WP_LANG_DIR . '/plugins/' . $domain . '-en_GB' . $extension;
        file_put_contents( $official, 'test-only' );
        $current_locale = 'en_GB';
        $already_loaded = false;
        $calls = array();
        $loader();
        verify( 0 === count( $calls ), $domain . ' overrode official language pack ' . $extension );
        unlink( $official );
        ++$total_cases;
    }
    $current_locale = 'en_GB';
    $already_loaded = true;
    $calls = array();
    $loader();
    verify( 0 === count( $calls ), $domain . ' reloaded an existing text domain' );
    ++$total_cases;
}

rmdir( WP_LANG_DIR . '/plugins' );
rmdir( WP_LANG_DIR );
echo 'PASS: ' . count( $slugs ) . ' TaxoCommerce plugins, ' . $total_cases
    . " locale/official-pack cases, no WordPress writes.\n";
