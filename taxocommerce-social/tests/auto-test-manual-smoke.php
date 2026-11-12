<?php
/** Standalone Social Auto Test/Manual smoke; no WordPress installation or remote access. */
define( 'ABSPATH', __DIR__ . '/' );
define( 'DAY_IN_SECONDS', 86400 );
$GLOBALS['social_hooks'] = array();
$GLOBALS['social_filters'] = array();
$GLOBALS['social_remote_calls'] = 0;
$GLOBALS['social_writes'] = 0;
$GLOBALS['social_transients'] = array();

function add_action( $name, $callback, $priority = 10, $accepted_args = 1 ) { $GLOBALS['social_hooks'][ $name ][] = $callback; }
function has_action( $name ) { return ! empty( $GLOBALS['social_hooks'][ $name ] ); }
function add_filter( $name, $callback, $priority = 10, $accepted_args = 1 ) { $GLOBALS['social_filters'][ $name ][] = $callback; }
function apply_filters( $name, $value ) {
    foreach ( $GLOBALS['social_filters'][ $name ] ?? array() as $callback ) $value = call_user_func( $callback, $value );
    return $value;
}
function register_activation_hook( $plugin, $callback ) {}
function did_action( $hook ) { return 0; }
function plugin_dir_path( $file ) { return dirname( $file ) . '/'; }
function plugin_dir_url( $file ) { return 'https://example.test/plugins/social/'; }
function current_user_can( $cap ) { return 'manage_options' === $cap; }
function get_option( $name, $default = false ) { return $default; }
function get_transient( $name ) { return $GLOBALS['social_transients'][ $name ] ?? false; }
function set_transient( $name, $value, $ttl ) { ++$GLOBALS['social_writes']; $GLOBALS['social_transients'][ $name ] = $value; return true; }
function get_current_user_id() { return 4; }
function current_time( $format ) { return '2026-10-31 23:52:00'; }
function wp_die( $message ) { throw new RuntimeException( $message ); }
function esc_html__( $value, $domain ) { return $value; }
function esc_html( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $value ) { return esc_html( $value ); }
function esc_url( $value ) { return esc_html( $value ); }
function admin_url( $path = '' ) { return '/wp-admin/' . $path; }
function remove_query_arg( $key, $url = '/wp-admin/admin.php?page=taxocommerce-social' ) { return $url; }
function add_query_arg( $key, $value = null, $url = '' ) { return ( $url ?: admin_url( 'admin.php?page=taxocommerce-social' ) ) . '&' . rawurlencode( $key ) . '=' . rawurlencode( (string) $value ); }
function esc_attr__( $message, $domain = '' ) { return esc_attr( $message ); }
function wp_nonce_field( $action, $name = '_wpnonce' ) { echo '<input type="hidden" name="' . esc_html( $name ) . '" value="test">'; }
function submit_button( $label, $type = 'primary', $name = 'submit', $wrap = true ) { echo '<button type="submit">' . esc_html( $label ) . '</button>'; }
function sanitize_key( $s ) { return strtolower( preg_replace( '/[^a-z0-9_-]/i', '', (string) $s ) ); }
function wp_unslash( $s ) { return $s; }
function wp_remote_get() { ++$GLOBALS['social_remote_calls']; throw new RuntimeException( 'Network forbidden' ); }
function wp_remote_post() { ++$GLOBALS['social_remote_calls']; throw new RuntimeException( 'Network forbidden' ); }
function wp_schedule_single_event() { throw new RuntimeException( 'Scheduling forbidden' ); }

class Social_Test_DB {
    public $prefix = 'wp_';
    public $last_error = '';
    public $missing = '';
    public $queries = array();
    public function esc_like( $s ) { return $s; }
    public function prepare( $sql ) {
        $args = array_slice( func_get_args(), 1 );
        return vsprintf( str_replace( '%i', '%s', $sql ), $args );
    }
    public function get_var( $sql ) {
        $this->queries[] = $sql;
        if ( 'SELECT 1' === $sql ) return '1';
        if ( preg_match( '/^SHOW TABLES LIKE (wp_[A-Za-z_]+)$/', $sql, $m ) ) return $m[1] === $this->missing ? null : $m[1];
        return null;
    }
    public function get_col( $sql ) {
        $this->queries[] = $sql;
        if ( preg_match( '/^SELECT id FROM wp_[a-z_]+ LIMIT 1$/', $sql ) ) return array();
        $this->last_error = 'Unexpected SQL';
        return false;
    }
}
$wpdb = new Social_Test_DB();
require dirname( __DIR__ ) . '/taxocommerce-social.php';
function social_assert( $condition, $message ) {
    if ( ! $condition ) { fwrite( STDERR, 'FAIL: ' . $message . "\n" ); exit( 1 ); }
}
$plugin_source = file_get_contents( dirname( __DIR__ ) . '/taxocommerce-social.php' );
preg_match( '/^ \* Version: (\d+\.\d+\.\d+)$/m', $plugin_source, $header_version );
social_assert( TAXOCOMMERCE_SOCIAL_VERSION === ( $header_version[1] ?? '' ), 'Social version matches plugin header' );
social_assert( has_action( 'admin_post_tcsocial_run_auto_test' ), 'Auto Test action registered' );
foreach ( array( 'tcsocial_network_scheduler_export', 'tcsocial_network_scheduler_import_preview', 'tcsocial_network_scheduler_import_confirm' ) as $route ) {
    social_assert( has_action( 'admin_post_' . $route ), 'CSV handler registered: ' . $route );
}
social_assert( function_exists( 'tcsocial_network_render_scheduler_csv_panel' ), 'CSV panel renderer available' );
ob_start(); tcsocial_network_render_scheduler_csv_panel(); $csv_panel = ob_get_clean();
foreach ( array( 'Descargar plantilla CSV', 'Exportar contenidos', 'Exportar agenda', 'Exportar historial', 'Validar CSV antes de importar' ) as $control ) {
    social_assert( strpos( $csv_panel, $control ) !== false, 'CSV action displayed: ' . $control );
}

social_assert( count( TCSocial_Auto_Test::cases() ) === 16, 'Exactly sixteen checks' );

$report = TCSocial_Auto_Test::run_suite();
if ( $report['totals']['fail'] ) {
    foreach ( $report['rows'] as $row ) if ( 'fail' === $row['status'] ) fwrite( STDERR, "CASE FAILED " . $row['id'] . ': ' . $row['message'] . "\n" );
}
social_assert( $report['totals'] === array( 'pass'=>15, 'fail'=>0, 'skip'=>1 ), '15 successful read-only checks, 1 deliberate skip' );
social_assert( $report['version'] === TAXOCOMMERCE_SOCIAL_VERSION, 'report version' );
social_assert( count( $report['rows'] ) === 16, 'all checks represented' );
social_assert( $GLOBALS['social_remote_calls'] === 0 && $GLOBALS['social_writes'] === 0, 'no outgoing requests or writes' );
foreach ( $wpdb->queries as $sql ) {
    social_assert( preg_match( '/^(SELECT|SHOW) /', $sql ) === 1, 'read-only query: ' . $sql );
    if ( strpos( $sql, ' FROM wp_' ) !== false ) social_assert( substr( $sql, -7 ) === 'LIMIT 1', 'query bounded: ' . $sql );
}

$wpdb->missing = 'wp_seo_social_schedule';
$absent = TCSocial_Auto_Test::run_suite();
social_assert( $absent['totals']['fail'] >= 1 && $absent['totals']['skip'] >= 2, 'missing table diagnosed without creation' );
$wpdb->missing = '';

ob_start(); TCSocial_Auto_Test::render(); $autotest = ob_get_clean();
social_assert( strpos( $autotest, 'tcsocial_auto_nonce' ) !== false, 'Auto Test nonce rendered' );
social_assert( strpos( $autotest, 'Ejecutar Autocheck' ) !== false, 'run button rendered' );
ob_start(); tcsocial_manual_render(); $manual = ob_get_clean();
foreach ( array( 'Manual de usuario', 'Manual técnico', 'Facebook', 'Instagram', 'LinkedIn', 'Pinterest', 'WP-Cron', 'CSV', 'Auto Test' ) as $value ) {
    social_assert( strpos( $manual, $value ) !== false, 'manual topic ' . $value );
}
// The manual must contain two independent guides with an index and tested contracts.
social_assert( preg_match_all( '/<h1\\b/', $manual ) === 2, 'two separate H1 manuals' );
social_assert( preg_match( '/<h1[^>]*>Manual de usuario<\\/h1>/', $manual ) === 1, 'standalone user manual' );
social_assert( preg_match( '/<h1[^>]*>Manual técnico para futuros desarrolladores<\\/h1>/', $manual ) === 1, 'developer technical manual' );
foreach ( array(
    'tcsocial-manual-index', 'tcsocial-user-connections', 'tcsocial-user-calendar',
    'tcsocial-dev-architecture', 'tcsocial-dev-storage', 'tcsocial-dev-providers',
    'tcsocial-dev-hooks', 'tcsocial-dev-cron', 'tcsocial-dev-csv', 'tcsocial-dev-autotest',
    'tcsocial-dev-workflow', 'seo_social_network_settings_v1',
    'tcsocial_programador_run_job', 'check_admin_referer', 'CHANGELOG.md',
    'Programación masiva con Excel / CSV',
) as $anchor ) {
    social_assert( strpos( $manual, $anchor ) !== false, 'developer and user manuals contain ' . $anchor );
}
social_assert( preg_match_all( '/<h2\\b/', $manual ) >= 14, 'detailed manuals with H2 hierarchy' );
social_assert( preg_match_all( '/<h3\\b/', $manual ) >= 17, 'detailed manuals with H3 procedures' );
$_GET['tab'] = 'manual';
social_assert( tcsocial_admin_current_tab() === 'manual', 'manual tab selected' );
$_GET['tab'] = 'auto-test';
social_assert( tcsocial_admin_current_tab() === 'auto-test', 'autotest tab selected' );
$_GET['tab'] = 'connections';
social_assert( tcsocial_admin_current_tab() === 'connections', 'legacy tab preserved' );
unset( $_GET['tab'] );
social_assert( $GLOBALS['social_remote_calls'] === 0 && $GLOBALS['social_writes'] === 0, 'manual and tabs do not mutate or connect' );
// Five native gettext catalogs must match the POT and have no blank translations.
$catalog_root = dirname( __DIR__ ) . '/languages/taxocommerce-social';
$pot_content = file_get_contents( $catalog_root . '.pot' );
social_assert( false !== $pot_content, 'gettext template exists' );
$pot_entries = preg_match_all( '/^msgid /m', $pot_content );
social_assert( 161 === $pot_entries, '160 translated catalog messages plus header' );
foreach ( array( 'es_ES', 'en_US', 'fr_FR', 'de_DE', 'nl_NL' ) as $locale ) {
    $po_path = $catalog_root . '-' . $locale . '.po';
    $mo_path = $catalog_root . '-' . $locale . '.mo';
    $po_content = file_get_contents( $po_path );
    social_assert( false !== $po_content, 'gettext PO exists: ' . $locale );
    social_assert( 161 === preg_match_all( '/^msgid /m', $po_content ), 'same gettext messages: ' . $locale );
    social_assert( 1 === preg_match_all( '/^msgstr ""$/m', $po_content ), 'no untranslated msgstr: ' . $locale );
    $header = file_get_contents( $mo_path, false, null, 0, 28 );
    social_assert( false !== $header && 28 === strlen( $header ), 'gettext MO exists: ' . $locale );
    $meta = unpack( 'Vmagic/Vrevision/Vcount', $header );
    social_assert( 0x950412de === $meta['magic'] && 161 === $meta['count'], 'valid GNU MO header: ' . $locale );
}
echo "TaxoCommerce Social i18n catalogs (ES/EN/FR/DE/NL) verified.\\n";
echo "TaxoCommerce Social Auto Test + Manual smoke passed.\n";
