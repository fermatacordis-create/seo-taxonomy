<?php
/**
 * Keep all modular TaxoCommerce Autotest screens consistent.
 *
 * @package TaxoCommerce
 */
if ( 'cli' !== PHP_SAPI ) {
    exit( 1 );
}
$repo = dirname( __DIR__, 2 );
$plugins = array(
    'auditor', 'campaigns', 'core', 'database', 'dependiente',
    'distribution', 'facturacion', 'faqs', 'google-bing', 'images',
    'import-export', 'logistica', 'redirects', 'scrappers', 'social',
    'suppliers', 'system-check', 'tasks', 'templates', 'vocabulary',
);
foreach ( $plugins as $slug ) {
    $root = $repo . '/taxocommerce-' . $slug;
    $main = $root . '/taxocommerce-' . $slug . '.php';
    if ( ! is_file( $main ) ) {
        fwrite( STDERR, 'Missing modular plugin: ' . $slug . PHP_EOL );
        exit( 1 );
    }
    if ( 'system-check' === $slug ) {
        $functional = file_get_contents( $root . '/includes/class-taxocommerce-system-check-auto-test.php' );
        $code = file_get_contents( $root . '/includes/class-taxocommerce-system-check-code-check.php' );
        $core_parity_rules = $root . '/includes/class-taxocommerce-system-check-code-review-rules.php';
        if ( ! is_file( $core_parity_rules )
            || false === strpos( $code, 'self::render_classified( $normalized )' )
            || false === strpos( $code, "['classified_findings']" )
            || false === strpos( $code, 'TaxoCommerce_System_Check_Code_Review_Rules::scan(' ) ) {
            fwrite( STDERR, 'System Check must retain existing rules and expose Core Code Check severity and JSON.' . PHP_EOL );
            exit( 1 );
        }
        if ( false === strpos( $functional, 'tcs_subtab' )
            || false === strpos( $functional, 'TaxoCommerce_System_Check_Code_Check::render()' )
            || false === strpos( $code, 'function render(' ) ) {
            fwrite( STDERR, 'System Check must contain the two Autocheck subtabs.' . PHP_EOL );
            exit( 1 );
        }
        continue;
    }
    $entry = file_get_contents( $main );
    $inspector = $root . '/includes/class-taxocommerce-autotest-code.php';
    if ( ! is_file( $inspector ) || ! is_file( $root . '/assets/autotest-code.js' ) ) {
        fwrite( STDERR, 'Missing native Code Check in ' . $slug . PHP_EOL );
        exit( 1 );
    }
    if ( false === strpos( $entry, 'class-taxocommerce-autotest-code.php' )
        || false === strpos( $entry, 'Autotest_Code::boot()' ) ) {
        fwrite( STDERR, 'Code Check is not bootstrapped in ' . $slug . PHP_EOL );
        exit( 1 );
    }
    $checker = file_get_contents( $inspector );
    if ( false === strpos( $checker, 'function render_subtabs(' )
        || false === strpos( $checker, "'Functionality Check'" )
        || false === strpos( $checker, "'Code Check'" )
        || false === strpos( $checker, 'function analyze_source(' )
        || false === strpos( $checker, "'tc_autotest_section'" ) ) {
        fwrite( STDERR, 'Autotest code engine incomplete in ' . $slug . PHP_EOL );
        exit( 1 );
    }
    if ( false !== strpos( $checker, 'self::DOMAIN )' ) ) {
        fwrite( STDERR, 'Dynamic gettext textdomain in ' . $slug . PHP_EOL );
        exit( 1 );
    }
    // Core must share one persistent hero and primary navigation across both
    // checks; sub-tabs must appear below it, never above the plugin heading.
    if ( 'core' === $slug ) {
        $core_screen = (string) file_get_contents( $root . '/includes/class-taxocommerce-core-auto-test.php' );
        $dashboard   = (string) file_get_contents( $root . '/includes/class-taxocommerce-core-dashboard.php' );
        $main_screen = (string) file_get_contents( $root . '/includes/class-taxocommerce-core.php' );
        $shell       = strpos( $core_screen, 'id="tc-core-autotest"' );
        $shared      = strpos( $core_screen, "TaxoCommerce_Core_Dashboard::render_header_nav( 'autocheck' )" );
        $subtabs     = strpos( $core_screen, 'Autotest_Code::render_subtabs()' );
        $functional  = strrpos( $core_screen, 'tc_autotest_manual_run' );
        if ( false === $shell || false === $shared || false === $subtabs || false === $functional
            || ! ( $shell < $shared && $shared < $subtabs && $subtabs < $functional )
            || false !== strpos( $core_screen, 'tcc-page-header' )
            || false === strpos( $core_screen, "if ( ! \\TaxoCommerce_Core_Autotest_Code::render_subtabs() ) :" )
            || false === strpos( $dashboard, 'function render_header_nav(' )
            || 1 !== substr_count( $dashboard, '<header class="tcc-hero"' )
            || false === strpos( $dashboard, "self::render_header_nav( 'overview' )" )
            || false === strpos( $dashboard, "'autocheck' === $active_tab" )
            || false === strpos( $main_screen, 'TaxoCommerce_Core_Auto_Test::render();' ) ) {
            fwrite( STDERR, 'Core must preserve its exact hero and primary navigation on both Code and Functional Check.' . PHP_EOL );
            exit( 1 );
        }
    }
    // Each plugin must actually call the subtab renderer in its functional screen.
    $candidates = array_merge(
        glob( $root . '/includes/*.php' ) ?: array(),
        array( $main )
    );
    $wired = false;
    foreach ( $candidates as $file ) {
        if ( false !== strpos( (string) file_get_contents( $file ), 'Autotest_Code::render_subtabs()' ) ) {
            $wired = true;
            break;
        }
    }
    if ( ! $wired ) {
        fwrite( STDERR, 'Functional Autotest missing Code Check navigation in ' . $slug . PHP_EOL );
        exit( 1 );
    }
}
echo 'PASS: 20 autonomous TaxoCommerce Autotest screens, functional and code tabs.' . PHP_EOL;
