<?php
/**
 * Standalone read-only Auto Test and User Guide regression contract.
 *
 * Run: php taxocommerce-system-check/tests/auto-test-guide-smoke.php
 */
if ( 'cli' !== PHP_SAPI ) {
    exit;
}
define( 'ABSPATH', '/' );
define( 'TAXOCOMMERCE_SYSTEM_CHECK_VERSION', '0.2.17' );
define( 'TAXOCOMMERCE_SYSTEM_CHECK_PATH', dirname( __DIR__ ) . '/' );
$GLOBALS['tcs_hooks'] = array();
$GLOBALS['tcs_nonce_calls'] = 0;
$GLOBALS['tcs_mutations'] = 0;
$GLOBALS['tcs_allowed'] = true;
$GLOBALS['tcs_reports'] = array();
$GLOBALS['tcs_server_snapshot'] = array();
$GLOBALS['tcs_modules_broken'] = false;

function add_action( $hook, $callback ) { $GLOBALS['tcs_hooks'][ $hook ] = $callback; }
function add_filter( $hook, $callback ) { $GLOBALS['tcs_hooks'][ $hook ] = $callback; }
function has_action( $hook, $callback = false ) {
    if ( ! isset( $GLOBALS['tcs_hooks'][ $hook ] ) ) { return false; }
    return false === $callback || $callback === $GLOBALS['tcs_hooks'][ $hook ];
}
function current_user_can( $capability ) { return $GLOBALS['tcs_allowed'] && 'manage_options' === $capability; }
function wp_die( $message ) { throw new RuntimeException( (string) $message ); }
function admin_url( $path = '' ) { return 'https://example.test/wp-admin/' . $path; }
function add_query_arg( $args, $url ) { return $url . '?' . http_build_query( $args ); }
function wp_nonce_url( $url, $action ) { return $url . '&_wpnonce=test'; }
function wp_nonce_field( $action, $name = '_wpnonce' ) { echo '<input type="hidden" name="' . $name . '" value="test">'; }
function submit_button( $label, $type = 'primary', $name = 'submit', $wrap = true ) { echo '<button type="submit">' . esc_html( $label ) . '</button>'; }
function check_admin_referer( $action ) {
    ++$GLOBALS['tcs_nonce_calls'];
    if ( 'tcs_auto_test_run' !== $action || ( $_GET['_wpnonce'] ?? '' ) !== 'test' ) { throw new RuntimeException( 'Missing nonce.' ); }
}
function esc_html( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function __( $value, $domain = '' ) { return $value; }
function esc_html__( $value, $domain = '' ) { return esc_html( $value ); }
function esc_attr( $value ) { return esc_html( $value ); }
function esc_attr__( $value, $domain = '' ) { return esc_attr( $value ); }
function esc_url( $value ) { return esc_html( $value ); }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_\\-]/', '', strtolower( (string) $value ) ); }
function wp_unslash( $value ) { return stripslashes( $value ); }
function taxocommerce_system_check_home() {}
function taxocommerce_system_check_active_tab() { return 'auto-test'; }
function taxocommerce_system_check_register_menu() {}
function taxocommerce_system_check_modules_json() {}
function seo_server_status() {}
function seo_server_status_load_snapshot() { return $GLOBALS['tcs_server_snapshot']; }
function wp_json_encode( $value, $flags = 0 ) { return json_encode( $value, $flags ); }
function taxocommerce_system_check_module_results() {
    $ok = ! $GLOBALS['tcs_modules_broken'];
    return array( 'system_check' => array(
        'name' => 'TaxoCommerce System Check',
        'version' => TAXOCOMMERCE_SYSTEM_CHECK_VERSION,
        'status' => $ok ? 'ok' : 'warning',
        'checks' => array( 'server' => array( 'ok' => $ok, 'label' => 'Motor de servidor' ) ),
    ) );
}
function taxocommerce_system_check_tab_url( $tab, $args = array() ) {
    return add_query_arg( array_merge( array( 'page' => 'taxocommerce-system-check', 'tcs_tab' => $tab ), $args ), admin_url( 'admin.php' ) );
}
function wp_remote_get() { ++$GLOBALS['tcs_mutations']; throw new RuntimeException( 'Remote calls prohibited.' ); }
function wp_mail() { ++$GLOBALS['tcs_mutations']; throw new RuntimeException( 'Emails prohibited.' ); }
function update_option() { ++$GLOBALS['tcs_mutations']; throw new RuntimeException( 'DB writes prohibited.' ); }
function get_current_blog_id() { return 5; }
function get_current_user_id() { return 12; }
function get_transient( $key ) { return $GLOBALS['tcs_reports'][ $key ] ?? false; }
function set_transient( $key, $value, $expiration ) {
    tcs_smoke_expect( $expiration === HOUR_IN_SECONDS, 'Stored report must expire.' );
    $GLOBALS['tcs_reports'][ $key ] = $value;
    return true;
}
define( 'HOUR_IN_SECONDS', 3600 );
function wp_schedule_event() { ++$GLOBALS['tcs_mutations']; throw new RuntimeException( 'Cron writes prohibited.' ); }
function tcs_smoke_expect( $condition, $message ) {
    if ( ! $condition ) { fwrite( STDERR, $message . "\n" ); exit( 1 ); }
}
require_once dirname( __DIR__ ) . '/includes/class-taxocommerce-system-check-php.php';
require_once dirname( __DIR__ ) . '/includes/class-taxocommerce-system-check-url-audit.php';
require_once dirname( __DIR__ ) . '/includes/class-taxocommerce-system-check-auto-test.php';
require_once dirname( __DIR__ ) . '/includes/class-taxocommerce-system-check-user-guide.php';

TaxoCommerce_System_Check_URL_Audit::register_hooks();
add_action( 'admin_menu', 'taxocommerce_system_check_register_menu' );
add_action( 'admin_post_taxocommerce_system_check_modules_json', 'taxocommerce_system_check_modules_json' );
$rows = TaxoCommerce_System_Check_Auto_Test::run();
tcs_smoke_expect( isset( $rows['coverage'] ) && $rows['coverage']['executed'] + $rows['totals']['skip'] === $rows['coverage']['total'], 'Coverage must count skipped checks as not executed.' );
tcs_smoke_expect( $rows['totals']['skip'] >= 1, 'Absent historical server snapshots are not a passed test.' );
$indexed = array();
foreach ( $rows['rows'] as $row ) { $indexed[$row['id']] = $row; }
tcs_smoke_expect( 'skip' === $indexed['server_snapshot']['status'], 'Missing server snapshot must be skipped.' );
tcs_smoke_expect( 'pass' === $indexed['report_json']['status'], 'Actual JSON exporter and serialization must work.' );
tcs_smoke_expect( 'pass' === $indexed['native_php']['status'] && $indexed['native_php']['evidence']['checks'] >= 10, 'Native PHP result data must be validated, not merely checked for class existence.' );
tcs_smoke_expect( 'pass' === $indexed['modules']['status'] && 'pass' === $indexed['url_hooks']['status'], 'Modules and HTTP schedule callbacks must be checked.' );
$GLOBALS['tcs_server_snapshot'] = array(
    'generated_at' => time(),
    'checks' => array(
        array( 'code' => 'TEST-SRV', 'label' => 'Servidor', 'status' => 'warning' ),
        array( 'code' => 'TEST-DB', 'label' => 'Base de datos', 'status' => 'ok' ),
    ),
);
$fresh = TaxoCommerce_System_Check_Auto_Test::run();
$fresh_by_id = array();
foreach ( $fresh['rows'] as $row ) { $fresh_by_id[ $row['id'] ] = $row; }
tcs_smoke_expect( 'pass' === $fresh_by_id['server_snapshot']['status'] && 1 === $fresh_by_id['server_snapshot']['evidence']['statuses']['warning'], 'A valid snapshot with environment warnings must not fail the plugin integration contract.' );
$GLOBALS['tcs_server_snapshot']['generated_at'] -= 172800;
$stale = TaxoCommerce_System_Check_Auto_Test::run();
$stale_by_id = array();
foreach ( $stale['rows'] as $row ) { $stale_by_id[ $row['id'] ] = $row; }
tcs_smoke_expect( 'skip' === $stale_by_id['server_snapshot']['status'], 'Stale server data must never count as passing.' );
$GLOBALS['tcs_server_snapshot']['checks'][] = array( 'code' => 'TEST-SRV', 'label' => 'Duplicada', 'status' => 'ok' );
$bad_snapshot = TaxoCommerce_System_Check_Auto_Test::run();
$bad_by_id = array();
foreach ( $bad_snapshot['rows'] as $row ) { $bad_by_id[ $row['id'] ] = $row; }
tcs_smoke_expect( 'fail' === $bad_by_id['server_snapshot']['status'], 'Malformed historical snapshots must fail validation.' );
$GLOBALS['tcs_server_snapshot'] = array();
$GLOBALS['tcs_modules_broken'] = true;
$bad_modules = TaxoCommerce_System_Check_Auto_Test::run();
$bad_modules_by_id = array();
foreach ( $bad_modules['rows'] as $row ) { $bad_modules_by_id[ $row['id'] ] = $row; }
tcs_smoke_expect( 'fail' === $bad_modules_by_id['modules']['status'], 'Registered modules with failing checks must not be marked green.' );
$GLOBALS['tcs_modules_broken'] = false;
tcs_smoke_expect( count( $rows['rows'] ) === count( TaxoCommerce_System_Check_Auto_Test::cases() ), 'All self-tests must appear.' );
tcs_smoke_expect( $rows['totals']['fail'] === 0 && $rows['totals']['skip'] >= 3, 'Optional and destructive tests must not appear green.' );
tcs_smoke_expect( $GLOBALS['tcs_mutations'] === 0, 'Self-test must not modify data, schedule jobs or call external services.' );

unset( $_GET['tcs_run'], $_GET['_wpnonce'] );
ob_start();
TaxoCommerce_System_Check_Auto_Test::render();
$initial = ob_get_clean();
tcs_smoke_expect( strpos( $initial, 'Run Functionality Check' ) !== false && $GLOBALS['tcs_nonce_calls'] === 0, 'Opening tab must not run self-test.' );
tcs_smoke_expect( strpos( $initial, 'taxocommerce_system_check_autocheck_json' ) === false, 'No JSON download must appear before the first execution.' );
$_GET['tcs_run'] = '1';
$_GET['_wpnonce'] = 'test';
ob_start();
TaxoCommerce_System_Check_Auto_Test::render();
$html = ob_get_clean();
tcs_smoke_expect( $GLOBALS['tcs_nonce_calls'] === 1 && strpos( $html, 'No ejecutado / N/A' ) !== false && strpos( $html, 'taxocommerce_system_check_autocheck_json' ) !== false, 'Explicit signed test must render skip evidence.' );
tcs_smoke_expect( strpos( $html, 'Cobertura del Autocheck:' ) !== false, 'Report must show real coverage excluding skipped checks.' );
tcs_smoke_expect( $GLOBALS['tcs_mutations'] === 0, 'Rendering must not trigger mutations.' );
tcs_smoke_expect( isset( $GLOBALS['tcs_reports']['taxocommerce_system_check_autocheck_latest_5_12'] ), 'Completed run must save the real report for the current site and user.' );
$nonce_count = $GLOBALS['tcs_nonce_calls'];
unset( $_GET['tcs_run'], $_GET['_wpnonce'] );
ob_start();
TaxoCommerce_System_Check_Auto_Test::render();
$return_visit = ob_get_clean();
tcs_smoke_expect( strpos( $return_visit, 'taxocommerce_system_check_autocheck_json' ) !== false, 'Returning administrator must still see JSON download.' );
tcs_smoke_expect( strpos( $return_visit, 'Fecha de ejecución:' ) !== false, 'The stored report execution timestamp must be shown.' );
tcs_smoke_expect( $nonce_count === $GLOBALS['tcs_nonce_calls'] && $GLOBALS['tcs_mutations'] === 0, 'Returning to Autocheck must not execute tests or external actions.' );
$GLOBALS['tcs_reports'] = array();
ob_start();
TaxoCommerce_System_Check_Auto_Test::render();
$expired_visit = ob_get_clean();
tcs_smoke_expect( strpos( $expired_visit, 'taxocommerce_system_check_autocheck_json' ) === false, 'Expired report must not have a download button.' );

unset( $_GET['tcs_run'], $_GET['_wpnonce'] );
foreach ( array( 'user' => 'Manual de usuario', 'technical' => 'Manual técnico' ) as $kind => $title ) {
    $_GET['tcs_manual'] = $kind;
    ob_start();
    TaxoCommerce_System_Check_User_Guide::render();
    $guide = ob_get_clean();
    tcs_smoke_expect( strpos( $guide, $title ) !== false && substr_count( $guide, '<h2>' ) >= 7 && substr_count( $guide, '<h3>' ) >= 7, 'Both manuals need indexed H2/H3 content.' );
    tcs_smoke_expect( strpos( $guide, 'tcs-guide-' . $kind . '-' ) !== false, 'Manual index and anchor missing.' );
}
$GLOBALS['tcs_allowed'] = false;
try {
    TaxoCommerce_System_Check_Auto_Test::render();
    tcs_smoke_expect( false, 'Unauthorised Auto Test must be blocked.' );
} catch ( RuntimeException $error ) {
    tcs_smoke_expect( strpos( $error->getMessage(), 'Acceso' ) !== false, 'Unexpected permissions error.' );
}
tcs_smoke_expect( $GLOBALS['tcs_mutations'] === 0, 'No test may send mail, modify options or schedule tasks.' );

// i18n regression: key administrative controls must be available in all five
// native WordPress locales, not just in a partial POT.
$tcs_required_i18n = array(
    'Estado del sistema',
    'Tablas más grandes',
    'Motor original no disponible.',
    'Comprobación básica',
    'Rendimiento de consultas MySQL',
    'Procesos',
    'Autocheck',
    'Último diagnóstico del servidor',
    'Version history and improvements',
);
foreach ( array( 'es_ES', 'en_US', 'fr_FR', 'de_DE', 'nl_NL' ) as $tcs_locale ) {
    $tcs_po_path = dirname( __DIR__ ) . '/languages/taxocommerce-system-check-' . $tcs_locale . '.po';
    $tcs_po_text = file_get_contents( $tcs_po_path );
    tcs_smoke_expect( false !== $tcs_po_text, 'PO catalog missing for ' . $tcs_locale );
    $tcs_found = array();
    foreach ( preg_split( '/\R\s*\R/u', $tcs_po_text ) as $tcs_block ) {
        if ( ! preg_match( '/^msgid "((?:\\\\.|[^"])*)"$/m', $tcs_block, $tcs_id_match ) ||
            ! preg_match( '/^msgstr "((?:\\\\.|[^"])*)"$/m', $tcs_block, $tcs_value_match ) ) {
            continue;
        }
        $tcs_id = json_decode( '"' . $tcs_id_match[1] . '"' );
        $tcs_value = json_decode( '"' . $tcs_value_match[1] . '"' );
        if ( in_array( $tcs_id, $tcs_required_i18n, true ) && is_string( $tcs_value ) && '' !== $tcs_value ) {
            $tcs_found[ $tcs_id ] = true;
        }
    }
    foreach ( $tcs_required_i18n as $tcs_key ) {
        tcs_smoke_expect( ! empty( $tcs_found[ $tcs_key ] ), 'Missing ' . $tcs_locale . ' translation: ' . $tcs_key );
    }
}

// Verify every gettext message in every locale, including multi-line PO strings,
// against the binary MO that WordPress actually loads.
function tcs_i18n_parse_message( $block ) {
    $values = array( 'msgid' => null, 'msgstr' => null );
    $current = null;
    foreach ( preg_split( '/\R/u', $block ) as $line ) {
        if ( preg_match( '/^(msgid|msgstr) (".*")$/', $line, $match ) ) {
            $current = $match[1];
            $decoded = json_decode( $match[2], true );
            if ( ! is_string( $decoded ) ) {
                throw new RuntimeException( 'Invalid PO quoted string.' );
            }
            $values[ $current ] = $decoded;
        } elseif ( null !== $current && preg_match( '/^(".*")$/', $line, $match ) ) {
            $decoded = json_decode( $match[1], true );
            if ( ! is_string( $decoded ) ) {
                throw new RuntimeException( 'Invalid PO continuation.' );
            }
            $values[ $current ] .= $decoded;
        } else {
            $current = null;
        }
    }
    return $values;
}
$tcs_expected_locales = array( 'es_ES', 'en_US', 'fr_FR', 'de_DE', 'nl_NL' );
foreach ( $tcs_expected_locales as $tcs_locale ) {
    $tcs_prefix = dirname( __DIR__ ) . '/languages/taxocommerce-system-check-' . $tcs_locale;
    $tcs_po = file_get_contents( $tcs_prefix . '.po' );
    $tcs_bytes = file_get_contents( $tcs_prefix . '.mo' );
    tcs_smoke_expect( false !== $tcs_po && false !== $tcs_bytes && strlen( $tcs_bytes ) > 28, 'Missing locale catalog: ' . $tcs_locale );
    $tcs_pairs = array();
    foreach ( preg_split( '/\R\s*\R/u', $tcs_po ) as $tcs_block ) {
        $tcs_entry = tcs_i18n_parse_message( $tcs_block );
        if ( null === $tcs_entry['msgid'] ) {
            continue;
        }
        tcs_smoke_expect( null !== $tcs_entry['msgstr'], 'Missing gettext translation: ' . $tcs_locale );
        tcs_smoke_expect( '' !== $tcs_entry['msgstr'] || '' === $tcs_entry['msgid'], 'Untranslated gettext entry: ' . $tcs_locale . ': ' . $tcs_entry['msgid'] );
        tcs_smoke_expect( ! array_key_exists( $tcs_entry['msgid'], $tcs_pairs ), 'Duplicate msgid in ' . $tcs_locale );
        $tcs_pairs[ $tcs_entry['msgid'] ] = $tcs_entry['msgstr'];
    }
    tcs_smoke_expect( count( $tcs_pairs ) >= 421, 'Translation catalog regressed below original 420 strings in ' . $tcs_locale );
    $tcs_header = unpack( 'Vmagic/Vrevision/Vcount/Vorig/Vtrans/Vhashsize/Vhash', substr( $tcs_bytes, 0, 28 ) );
    tcs_smoke_expect( 0x950412de === $tcs_header['magic'] && count( $tcs_pairs ) === $tcs_header['count'], 'Invalid gettext MO header/count for ' . $tcs_locale );
    tcs_smoke_expect( $tcs_header['orig'] + $tcs_header['count'] * 8 <= strlen( $tcs_bytes ), 'Invalid MO source index for ' . $tcs_locale );
    tcs_smoke_expect( $tcs_header['trans'] + $tcs_header['count'] * 8 <= strlen( $tcs_bytes ), 'Invalid MO translation index for ' . $tcs_locale );
    for ( $tcs_n = 0; $tcs_n < $tcs_header['count']; ++$tcs_n ) {
        $tcs_original = unpack( 'Vlen/Voffset', substr( $tcs_bytes, $tcs_header['orig'] + 8 * $tcs_n, 8 ) );
        $tcs_translation = unpack( 'Vlen/Voffset', substr( $tcs_bytes, $tcs_header['trans'] + 8 * $tcs_n, 8 ) );
        tcs_smoke_expect( $tcs_original['offset'] + $tcs_original['len'] < strlen( $tcs_bytes ), 'MO source offset out of range.' );
        tcs_smoke_expect( $tcs_translation['offset'] + $tcs_translation['len'] < strlen( $tcs_bytes ), 'MO translation offset out of range.' );
        $tcs_key = substr( $tcs_bytes, $tcs_original['offset'], $tcs_original['len'] );
        $tcs_value = substr( $tcs_bytes, $tcs_translation['offset'], $tcs_translation['len'] );
        tcs_smoke_expect( array_key_exists( $tcs_key, $tcs_pairs ), 'MO contains unknown key: ' . $tcs_locale );
        tcs_smoke_expect( $tcs_pairs[ $tcs_key ] === $tcs_value, 'MO value differs from PO in ' . $tcs_locale . ': ' . $tcs_key );
    }
}
// Checkout must never receive early gettext notices from System Check's
// optional Core metadata. The registry must be deferred beyond plugins_loaded.
$tcs_system_entry = file_get_contents( dirname( __DIR__ ) . '/taxocommerce-system-check.php' );
$tcs_registry_start = strpos( $tcs_system_entry, 'function taxocommerce_system_check_register_with_core( $core ) {' );
$tcs_registry_end = strpos( $tcs_system_entry, 'function taxocommerce_system_check_register_menu()', $tcs_registry_start );
tcs_smoke_expect( false !== $tcs_registry_start && false !== $tcs_registry_end, 'System Check Core registry missing.' );
$tcs_registry = substr( $tcs_system_entry, $tcs_registry_start, $tcs_registry_end - $tcs_registry_start );
$tcs_defer = strpos( $tcs_registry, "if ( ! did_action( 'init' ) ) {" );
$tcs_init_hook = strpos( $tcs_registry, 'static function () use ( $core ) {' );
$tcs_early_return = strpos( $tcs_registry, 'return;', $tcs_init_hook );
$tcs_gettext = strpos( $tcs_registry, "__( 'System diagnostics, plugin checks and reports.', 'taxocommerce-system-check' )" );
tcs_smoke_expect(
    false !== $tcs_defer && false !== $tcs_init_hook && false !== $tcs_early_return && false !== $tcs_gettext
        && $tcs_defer < $tcs_init_hook && $tcs_init_hook < $tcs_early_return && $tcs_early_return < $tcs_gettext,
    'Core metadata must wait for init before using gettext (WooCommerce AJAX JSON must remain clean).'
);
tcs_smoke_expect(
    false !== strpos( $tcs_registry, 'taxocommerce_system_check_register_with_core( $core );' )
        && false !== strpos( $tcs_registry, '$core->register_module(' ),
    'Deferring gettext must keep the Core registry functional.'
);

echo "System Check Auto Test and User/Technical Guide: OK\n";
