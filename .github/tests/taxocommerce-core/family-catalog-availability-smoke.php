<?php
/**
 * Run without WordPress: verify the full Core catalog and Tasks status cards.
 *
 * php .github/tests/taxocommerce-core/family-catalog-availability-smoke.php
 *
 * @package TaxoCommerceCore
 */

define( 'ABSPATH', dirname( __DIR__, 3 ) . '/' );

$GLOBALS['tcc_plugins'] = array();
$GLOBALS['tcc_active'] = array();
$GLOBALS['tcc_tasks_hook'] = false;

function __( $message, $domain = '' ) { return $message; }
function esc_html__( $message, $domain = '' ) { return $message; }
function esc_html_e( $message, $domain = '' ) { echo esc_html( $message ); }
function apply_filters( $hook, $value ) {
    if ( 'taxocommerce_core_family_extensions' === $hook && $GLOBALS['tcc_tasks_hook'] ) {
        // Simulate the same filter used by an active Tasks installation.
        $value['tasks'] = array_merge( $value['tasks'], array( 'name' => 'TaxoCommerce Tasks' ) );
    }
    return $value;
}
function get_plugins() { return $GLOBALS['tcc_plugins']; }
function is_plugin_active( $file ) { return in_array( $file, $GLOBALS['tcc_active'], true ); }
function current_user_can( $capability ) { return in_array( $capability, array( 'manage_options', 'install_plugins', 'activate_plugins' ), true ); }
function wp_die( $message ) { throw new RuntimeException( $message ); }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_-]/', '', strtolower( $value ) ); }
function sanitize_text_field( $value ) { return trim( $value ); }
function sanitize_file_name( $value ) { return basename( $value ); }
function wp_unslash( $value ) { return $value; }
function esc_html( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function wp_upload_dir() { return array( 'basedir' => '/nonexistent/taxocommerce-tests', 'error' => '' ); }
function trailingslashit( $value ) { return rtrim( $value, '/' ) . '/'; }
function admin_url( $path ) { return 'https://example.test/wp-admin/' . $path; }
function add_query_arg( $args, $url ) { return $url . ( false !== strpos( $url, '?' ) ? '&' : '?' ) . http_build_query( $args ); }
function wp_nonce_field( $action, $name ) { echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="test">'; }
function wp_nonce_url( $url, $action ) { return $url . '&_wpnonce=test'; }

require_once dirname( __DIR__, 3 ) . '/taxocommerce-core/includes/class-taxocommerce-core.php';

function tcc_check( $ok, $message ) {
    if ( ! $ok ) {
        throw new RuntimeException( 'Core family catalog: ' . $message );
    }
}

$instance = ( new ReflectionClass( 'TaxoCommerce_Core' ) )->newInstanceWithoutConstructor();
$method = new ReflectionMethod( 'TaxoCommerce_Core', 'family_extensions' );
$method->setAccessible( true );

$catalog = $method->invoke( $instance );
tcc_check( 19 === count( $catalog ), '19 current family extensions must be visible without loading optional plugins' );
tcc_check( isset( $catalog['tasks'] ), 'Tasks must be in the built-in family catalog' );
tcc_check( 'taxocommerce-tasks/taxocommerce-tasks.php' === $catalog['tasks']['plugin_file'], 'correct Tasks basename' );
tcc_check( false === $catalog['tasks']['wordpress_org'], 'Tasks is not yet available on WordPress.org' );
tcc_check( false === isset( $catalog['connections'] ), 'retired Connections must not reappear as a separate plugin' );

$slugs = array();
foreach ( $catalog as $extension ) {
    $plugin_file = $extension['plugin_file'];
    tcc_check( ! isset( $slugs[ $plugin_file ] ), 'duplicate plugin: ' . $plugin_file );
    $slugs[ $plugin_file ] = true;
    tcc_check( is_file( dirname( __DIR__, 3 ) . '/' . $plugin_file ), 'plugin basename does not exist in source: ' . $plugin_file );
}

$render = static function () use ( $instance ) {
    ob_start();
    $instance->render_extension_catalog();
    $html = ob_get_clean();
    tcc_check( 19 === substr_count( $html, '<article class="tcc-extension-card"' ), 'all 19 cards render' );
    tcc_check( 1 === substr_count( $html, '<h3>TaxoCommerce Tasks</h3>' ), 'one Tasks card per catalog' );
    preg_match( '/<article class="tcc-extension-card" aria-label="TaxoCommerce Tasks">.*?<\/article>/s', $html, $match );
    tcc_check( ! empty( $match ), 'Tasks card exists in the HTML' );
    return array( $match[0], $html );
};

list( $tasks, $all ) = $render();
tcc_check( false !== strpos( $tasks, 'No instalada' ), 'absent Tasks is explicitly announced' );
tcc_check( false !== strpos( $tasks, 'Instalar ZIP con WordPress' ), 'authorized ZIP upload is available to administrator' );
tcc_check( false === strpos( $tasks, 'action=activate' ), 'absent plugin cannot be activated' );
tcc_check( false !== strpos( $all, '19 no instaladas' ), 'missing summary includes the full count' );

$GLOBALS['tcc_plugins'] = array(
    'taxocommerce-tasks/taxocommerce-tasks.php' => array( 'Version' => '0.1.4' ),
);
list( $tasks, $all ) = $render();
tcc_check( false !== strpos( $tasks, 'Instalada · inactiva' ), 'installed but inactive Tasks is distinguished from missing' );
tcc_check( false !== strpos( $tasks, 'action=activate' ), 'inactive plugin has activation action' );
tcc_check( false === strpos( $tasks, 'Instalar ZIP con WordPress' ), 'installed plugin is not offered a redundant install' );

$GLOBALS['tcc_active'] = array( 'taxocommerce-tasks/taxocommerce-tasks.php' );
$GLOBALS['tcc_tasks_hook'] = true;
list( $tasks, $all ) = $render();
tcc_check( false !== strpos( $tasks, '>Activa<' ), 'active Tasks is clearly identified' );
tcc_check( false !== strpos( $tasks, 'action=deactivate' ), 'active Tasks has deactivation action' );
tcc_check( false !== strpos( $tasks, 'page=taxocommerce-tasks' ), 'active Tasks offers the existing admin page' );
tcc_check( false !== strpos( $all, '1 activas' ), 'active count reflects Tasks' );

echo "PASS: 19 family cards; Tasks absent, inactive and active; no duplicate or automatic install.\n";
