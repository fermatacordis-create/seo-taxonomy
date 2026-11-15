<?php
/**
 * Standalone admin branding regression tests: no WordPress runtime required.
 * Example: php .github/tests/family-admin-header-smoke.php
 */
error_reporting( E_ALL );
define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );
$tc_test_actions = array();
$tc_test_filters = array();
$tc_test_styles = array();

function add_action( $hook, $callback, $priority = 10 ) {
    global $tc_test_actions;
    $tc_test_actions[ $hook ][] = $callback;
    return true;
}
function add_filter( $hook, $callback, $priority = 10 ) {
    global $tc_test_filters;
    $tc_test_filters[ $hook ][] = $callback;
    return true;
}
function sanitize_key( $value ) {
    return preg_replace( '/[^a-z0-9_-]/', '', strtolower( (string) $value ) );
}
function wp_unslash( $value ) { return $value; }

function esc_attr( $value ) { return esc_html( $value ); }
function esc_url( $value ) { return esc_html( $value ); }
function esc_url_raw( $value, $protocols = array() ) { return (string) $value; }
function admin_url( $path ) { return 'https://example.test/wp-admin/' . $path; }
function current_user_can( $capability ) { return 'manage_options' === $capability; }
function wp_nonce_field( $name ) { echo '<input type="hidden" name="_wpnonce" value="test">'; }
function get_option( $key, $default = '' ) { return $GLOBALS['tc_test_options'][ $key ] ?? $default; }
function update_option( $key, $value, $autoload = null ) {
    $GLOBALS['tc_test_options'][ $key ] = $value;
    return true;
}
function wp_safe_remote_get( $url, $args = array() ) {
    ++$GLOBALS['tc_test_remote_calls'];
    throw new RuntimeException( 'Plugin admin header must never send an HTTP request.' );
}
function wp_strip_all_tags( $text ) { return strip_tags( $text ); }
function wp_html_excerpt( $text, $max, $more = '' ) { return strlen( $text ) > $max ? substr( $text, 0, $max ) . $more : $text; }
function absint( $value ) { return abs( (int) $value ); }
define( 'HOUR_IN_SECONDS', 3600 );
define( 'MINUTE_IN_SECONDS', 60 );
$GLOBALS['tc_test_remote_calls'] = array();
$GLOBALS['tc_test_transients'] = array();

function __( $text, $domain = '' ) { return $text; }
function translate( $text, $domain = 'default' ) { return $text; }
function determine_locale() { return $GLOBALS['tc_test_locale'] ?? 'es_ES'; }
function esc_html( $value ) {
    return htmlspecialchars( (string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
}
function esc_html_e( $value, $domain = '' ) {
    echo esc_html( $value );
}
function wp_register_style( $handle, $src, $deps, $version ) {
    global $tc_test_styles;
    $tc_test_styles[ $handle ] = array( 'src' => $src, 'version' => $version, 'css' => '' );
}
function wp_enqueue_style( $handle ) {
    global $tc_test_styles;
    if ( ! isset( $tc_test_styles[ $handle ] ) ) {
        throw new RuntimeException( 'Nonregistered stylesheet: ' . $handle );
    }
}
function wp_add_inline_style( $handle, $css ) {
    global $tc_test_styles;
    $tc_test_styles[ $handle ]['css'] = $css;
}
function get_file_data( $file, $fields, $context = '' ) {
    $source = file_get_contents( $file );
    $result = array();
    foreach ( $fields as $field => $name ) {
        $value = '';
        if ( preg_match( '/^\s*\*\s*' . preg_quote( $name, '/' ) . ':\s*(.+)$/mi', $source, $matches ) ) {
            $value = trim( $matches[1] );
        }
        $result[ $field ] = $value;
    }
    return $result;
}

function tc_test_assert( $passed, $message ) {
    if ( ! $passed ) {
        fwrite( STDERR, "FAIL: " . $message . PHP_EOL );
        exit( 1 );
    }
}

$slugs = array(
    'auditor', 'redirects', 'faqs', 'images', 'social', 'database',
    'campaigns', 'suppliers', 'scrappers', 'logistica', 'vocabulary',
    'facturacion', 'dependiente', 'google-bing', 'distribution',
    'import-export', 'system-check', 'templates', 'tasks', 'customers',
);
$root = dirname( __DIR__, 2 );
foreach ( $slugs as $slug ) {
    $path = $root . '/taxocommerce-' . $slug . '/includes/admin-branding.php';
    tc_test_assert( is_file( $path ), 'Missing self-contained header: ' . $slug );
    require $path;
}
tc_test_assert( count( $tc_test_actions['admin_notices'] ?? array() ) === count( $slugs ), 'One header registration per standalone plugin.' );
tc_test_assert( count( $tc_test_actions['admin_enqueue_scripts'] ?? array() ) === count( $slugs ), 'One style registration per standalone plugin.' );
foreach ( $slugs as $slug ) {
    $_GET['page'] = 'taxocommerce-' . $slug;
    ob_start();
    foreach ( $tc_test_actions['admin_notices'] as $callback ) {
        $callback();
    }
    $html = ob_get_clean();
    tc_test_assert( 1 === substr_count( $html, '<header class="tc-family-hero">' ), 'Exactly one hero on ' . $slug );
    tc_test_assert( 1 === substr_count( $html, 'tc-family-hero__version' ), 'Exactly one version on ' . $slug );
    tc_test_assert( 1 === substr_count( $html, 'tc-family-hero__updated' ), 'Exactly one release date on ' . $slug );
    tc_test_assert( strpos( $html, 'tc-family-hero__copy' ) !== false, 'Title and description on ' . $slug );
    tc_test_assert( strpos( $html, 'dashicons-' ) !== false, 'Native WordPress icon on ' . $slug );
    tc_test_assert( 1 === substr_count( $html, '<details class="tc-family-history">' ), 'Exactly one collapsed version history: ' . $slug );
    tc_test_assert( false !== strpos( $html, '<summary>Historial de versiones y mejoras</summary>' ), 'Translated history title: ' . $slug );
    tc_test_assert( false === strpos( $html, '<details class="tc-family-history" open' ), 'History must be closed by default: ' . $slug );
    tc_test_assert( 0 === substr_count( $html, 'tc-family-info' ), 'No duplicated information panel: ' . $slug );
    tc_test_assert( 1 === substr_count( $html, '<div class="tc-family-hero__history-zone">' ), 'One wide centered history zone: ' . $slug );
    tc_test_assert( 1 === substr_count( $html, 'class="tc-family-hero__link"' ), 'One direct developer link under description: ' . $slug );
    tc_test_assert( 1 === substr_count( $html, '<details' ), 'Only history is collapsible: ' . $slug );
    tc_test_assert( false === strpos( $html, 'name="tc_official_url"' ), 'The developer-owned link cannot be changed by a shop administrator: ' . $slug );
    $headers = get_file_data( $root . '/taxocommerce-' . $slug . '/taxocommerce-' . $slug . '.php', array( 'uri' => 'Plugin URI', 'description' => 'Description' ) );
    tc_test_assert( 1 === substr_count( $html, esc_html( wp_strip_all_tags( $headers['description'] ) ) ), 'Local plugin Description appears exactly once: ' . $slug );
    tc_test_assert( false !== strpos( $html, '<p class="tc-family-hero__desc">' . esc_html( wp_strip_all_tags( $headers['description'] ) ) . '</p>' ), 'Visible subtitle follows local plugin Description: ' . $slug );
    tc_test_assert( false !== strpos( $html, esc_html( $headers['uri'] ) ), 'Local Plugin URI linked: ' . $slug );
    $description_end = strpos( $html, '</p>', strpos( $html, 'tc-family-hero__desc' ) );
    $link_at = strpos( $html, 'class="tc-family-hero__link"' );
    $history_at = strpos( $html, '<details class="tc-family-history">' );
    tc_test_assert( false !== $link_at && false !== $description_end && $description_end < $link_at && $link_at < $history_at, 'Developer link directly below Description and before the centered history: ' . $slug );
    tc_test_assert( false !== strpos( $html, '>Ver ficha oficial</a>' ), 'Official link localized in Spanish: ' . $slug );
    tc_test_assert( is_readable( $root . '/taxocommerce-' . $slug . '/includes/official-excerpt.php' ), 'Independent local plugin metadata renderer: ' . $slug );
    $changelog = $root . '/taxocommerce-' . $slug . '/CHANGELOG.md';
    tc_test_assert( is_readable( $changelog ), 'Bundled history is required: ' . $slug );
    $first_line = strtok( file_get_contents( $changelog ), "\n" );
    tc_test_assert( false !== strpos( $html, esc_html( $first_line ) ), 'Header reads the current plugin history: ' . $slug );
    tc_test_assert( false === strpos( $html, '__TC_DOMAIN__' ), 'No unresolved gettext placeholder: ' . $slug );
    $version = get_file_data( $root . '/taxocommerce-' . $slug . '/taxocommerce-' . $slug . '.php', array( 'version' => 'Version', 'released' => 'Release Date' ) );
    tc_test_assert( 1 === preg_match( '/^\d{4}-\d{2}-\d{2}$/', $version['released'] ), 'Valid release date on ' . $slug );
    tc_test_assert( false !== strpos( $html, $version['released'] ), 'Visible release date matches plugin header on ' . $slug );
    tc_test_assert( false !== strpos( $html, $version['version'] ), 'Visible version follows current plugin header: ' . $slug );
    $class = 'wp-admin';
    foreach ( $tc_test_filters['admin_body_class'] as $callback ) {
        $class = $callback( $class );
    }
    tc_test_assert( 1 === substr_count( $class, 'tc-family-screen' ), 'Only owning plugin marks admin body on ' . $slug );
    $tc_test_styles = array();
    foreach ( $tc_test_actions['admin_enqueue_scripts'] as $callback ) {
        $callback();
    }
    tc_test_assert( 1 === count( $tc_test_styles ), 'No cross-plugin styles enqueued on ' . $slug );
    $styles = reset( $tc_test_styles );
    tc_test_assert( false !== strpos( $styles['css'], '@media(max-width:700px)' ), 'Responsive header on ' . $slug );
    tc_test_assert( false !== strpos( $styles['css'], '.nav-tab-wrapper' ), 'Existing tabs retained on ' . $slug );
    tc_test_assert( false !== strpos( $styles['css'], 'grid-template-columns:74px minmax(' ), 'Three-zone centered header: ' . $slug );
}
// The same standalone header must respect each WordPress administrator locale.
$_GET['page'] = 'taxocommerce-auditor';
foreach ( array(
    'es_ES' => 'Última actualización',
    'en_US' => 'Last updated',
    'fr_FR' => 'Dernière mise à jour',
    'de_DE' => 'Zuletzt aktualisiert',
    'nl_NL' => 'Laatst bijgewerkt',
) as $locale => $expected ) {
    $GLOBALS['tc_test_locale'] = $locale;
    ob_start();
    foreach ( $tc_test_actions['admin_notices'] as $callback ) {
        $callback();
    }
    $header = ob_get_clean();
    tc_test_assert( false !== strpos( $header, $expected ), 'Translated release label for ' . $locale );
}
// The history control responds to all five administrator language variants.
$_GET['page'] = 'taxocommerce-auditor';
foreach ( array(
    'es_ES' => 'Historial de versiones y mejoras',
    'en_US' => 'Version history and improvements',
    'fr_FR' => 'Historique des versions et améliorations',
    'de_DE' => 'Versionsverlauf und Verbesserungen',
    'nl_NL' => 'Versiegeschiedenis en verbeteringen',
) as $locale => $label ) {
    $GLOBALS['tc_test_locale'] = $locale;
    ob_start();
    foreach ( $tc_test_actions['admin_notices'] as $callback ) {
        $callback();
    }
    $markup = ob_get_clean();
    tc_test_assert( false !== strpos( $markup, '<summary>' . esc_html( $label ) . '</summary>' ), 'History respects site locale: ' . $locale );
}
$GLOBALS['tc_test_remote_calls'] = array();
// The client cannot override the official local developer link, including
// on a fresh installation or when stale remote cache options still exist.
$GLOBALS['tc_test_options']['tc_official_page_taxocommerce-auditor'] = 'https://client.example/override/';
$GLOBALS['tc_test_options']['tc_official_excerpt_good_fake'] = 'Stale remote content';
$_GET['page'] = 'taxocommerce-auditor';
$GLOBALS['tc_test_locale'] = 'es_ES';
$official = get_file_data(
    $root . '/taxocommerce-auditor/taxocommerce-auditor.php',
    array( 'uri' => 'Plugin URI', 'description' => 'Description' )
);
ob_start();
foreach ( $tc_test_actions['admin_notices'] as $callback ) {
    $callback();
}
$static_markup = ob_get_clean();
tc_test_assert( 0 === count( $GLOBALS['tc_test_remote_calls'] ), 'No HTTP requests for plugin information.' );
tc_test_assert( false !== strpos( $static_markup, esc_html( $official['description'] ) ), 'The PHP plugin Description is authoritative.' );
tc_test_assert( false !== strpos( $static_markup, esc_html( $official['uri'] ) ), 'The official developer URL is linked.' );
tc_test_assert( false === strpos( $static_markup, 'https://client.example' ), 'Shop-specific legacy options cannot override the developer URL.' );
tc_test_assert( false === strpos( $static_markup, 'Stale remote content' ), 'Legacy network caches are never used.' );
tc_test_assert( false === strpos( $static_markup, 'tc_official_url' ), 'No legacy editable URL control.' );
ob_start();
foreach ( $tc_test_actions['admin_notices'] as $callback ) {
    $callback();
}
ob_end_clean();
tc_test_assert( 0 === count( $GLOBALS['tc_test_remote_calls'] ), 'Repeated admin visits cannot contact the developer website.' );

unset( $GLOBALS['tc_test_locale'] );
unset( $_GET['page'] );
ob_start();
foreach ( $tc_test_actions['admin_notices'] as $callback ) {
    $callback();
}
tc_test_assert( '' === ob_get_clean(), 'No branding outside plugin admin pages.' );
// Core, Tasks and the compatibility bridge have independent admin rendering.
foreach ( array( 'core' ) as $other ) {
    $source = $root . '/taxocommerce-' . $other . '/taxocommerce-' . $other . '.php';
    $data = get_file_data( $source, array( 'version' => 'Version', 'released' => 'Release Date' ) );
    tc_test_assert( 1 === preg_match( '/^\\d+\\.\\d+\\.\\d+$/', $data['version'] ), 'Valid plugin version: ' . $other );
    tc_test_assert( 1 === preg_match( '/^\d{4}-\d{2}-\d{2}$/', $data['released'] ), 'Valid release date present: ' . $other );
    $readme = file_get_contents( $root . '/taxocommerce-' . $other . '/readme.txt' );
    tc_test_assert( false !== strpos( $readme, 'Stable tag: ' . $data['version'] ), 'Stable tag current: ' . $other );
    $main = file_get_contents( $source );
    tc_test_assert( false !== strpos( $main, 'TAXOCOMMERCE_' . strtoupper( $other ) . '_RELEASE_DATE' ), 'Release-date constant: ' . $other );
}
$core_ui = file_get_contents( $root . '/taxocommerce-core/includes/class-taxocommerce-core-dashboard.php' );
$tasks_ui = file_get_contents( $root . '/taxocommerce-tasks/taxocommerce-tasks.php' );
tc_test_assert( false !== strpos( $core_ui, 'tcc-hero__metadata' ) && false !== strpos( $core_ui, 'TAXOCOMMERCE_CORE_RELEASE_DATE' ), 'Core hero version and date' );
tc_test_assert( false !== strpos( $core_ui, 'tcc-hero__history-zone' ), 'Core presents one wide centered history zone.' );
tc_test_assert( false === strpos( $core_ui, 'tcc-hero__panels' ), 'Core has no duplicated information panel.' );
tc_test_assert( 1 === substr_count( $core_ui, "\$tc_official_info->render( 'taxocommerce-core', \$tc_locale )" ), 'Core renders one developer link under its Description.' );
tc_test_assert( false !== strpos( $core_ui, '<details class="tcc-hero__history">' ), 'Core has a collapsed history on its own dashboard.' );
tc_test_assert( false !== strpos( $core_ui, "dirname( __DIR__ ) . '/CHANGELOG.md'" ), 'Core reads its own local changelog.' );
tc_test_assert( false !== strpos( $core_ui, "'description' => 'Description'" ), 'Core title subtitle reads bundled plugin Description.' );
tc_test_assert( false !== strpos( $core_ui, 'esc_html( $tc_plugin_description )' ), 'Core renders local Description under the title.' );
tc_test_assert( false !== strpos( $core_ui, 'esc_html( $tc_history )' ), 'Core history is HTML-escaped.' );
tc_test_assert( is_readable( $root . '/taxocommerce-core/CHANGELOG.md' ), 'Core changelog must be included in the release.' );
tc_test_assert( false !== strpos( $tasks_ui, "require_once __DIR__ . '/includes/admin-branding.php';" ), 'Tasks loads the shared autonomous visual header' );
tc_test_assert( false === strpos( $tasks_ui, '<div class="wrap"><h1>' ), 'Tasks old duplicate heading removed' );
tc_test_assert( false !== strpos( $tasks_ui, '<div class="wrap"><nav class="nav-tab-wrapper">' ), 'Tasks keeps its seven-tab navigation below the hero' );
$tasks_data = get_file_data( $root . '/taxocommerce-tasks/taxocommerce-tasks.php', array( 'version' => 'Version', 'released' => 'Release Date' ) );
tc_test_assert( false !== strpos( file_get_contents( $root . '/taxocommerce-tasks/readme.txt' ), 'Stable tag: ' . $tasks_data['version'] ), 'Tasks release version matches readme' );
tc_test_assert( false !== strpos( $core_ui, "'/official-excerpt.php'" ), 'Core must load standalone developer metadata.' );
echo "PASS: 20 three-zone headers, Description once, single developer link, wide collapsed history, version and date." . PHP_EOL;
