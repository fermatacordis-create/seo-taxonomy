<?php
/**
 * Isolated regression: WordPress sidebar shows plugins, while all Core routes
 * remain registered and reachable from the Core dashboard.
 *
 * @package TaxoCommerceCore
 */
define( 'ABSPATH', __DIR__ . '/' );

class TaxoCommerce_Core_Content_Manager {
    const PAGE_SLUG = 'taxocommerce-core-content';
}
class TaxoCommerce_Core_User_Guide {
    const PAGE_SLUG = 'taxocommerce-core-user-guide';
}
function __( $message, $domain = '' ) { return $message; }
function apply_filters( $hook, $default ) { return $default; }
function get_current_screen() { return (object) array( 'id' => 'toplevel_page_taxocommerce-core' ); }

$GLOBALS['menu'] = array();
$GLOBALS['submenu'] = array();
$GLOBALS['tc_registered_pages'] = array();
function add_menu_page( $title, $menu_title, $capability, $slug, $callback, $icon, $position ) {
    $GLOBALS['menu'][] = array( $menu_title, $capability, $slug, $title, $icon );
    $GLOBALS['submenu'][ $slug ] = array();
    $GLOBALS['tc_registered_pages'][ $slug ] = $callback;
}
function add_submenu_page( $parent, $title, $menu_title, $capability, $slug, $callback ) {
    $GLOBALS['submenu'][ $parent ][] = array( $menu_title, $capability, $slug );
    $GLOBALS['tc_registered_pages'][ $slug ] = $callback;
}

require_once dirname( __DIR__, 3 ) . '/taxocommerce-core/includes/class-taxocommerce-core.php';

function check( $ok, $reason ) {
    if ( ! $ok ) {
        throw new RuntimeException( $reason );
    }
}
function menu_slugs() {
    return array_map(
        static function ( $item ) { return $item[2]; },
        $GLOBALS['submenu']['taxocommerce-core']
    );
}

$core = TaxoCommerce_Core::instance();
$core->register_menu();
// Simulate a plugin or WordPress repaint incorrectly renaming the parent.
$GLOBALS['menu'][0][0] = 'Marketing campaigns';
$core->normalize_family_submenu_labels();
check( 'TaxoCommerce Core' === $GLOBALS['menu'][0][0], 'Core parent title must not become Marketing campaigns' );
check( 'taxocommerce-core' === $GLOBALS['menu'][0][2], 'Core parent slug must remain unchanged' );
$initial = menu_slugs();
$internal = array(
    TaxoCommerce_Core_Content_Manager::PAGE_SLUG,
    'taxocommerce-core-hierarchy',
    'taxocommerce-core-menu',
    TaxoCommerce_Core_User_Guide::PAGE_SLUG,
);
foreach ( $internal as $slug ) {
    check( in_array( $slug, $initial, true ), 'Core route not registered: ' . $slug );
    check( isset( $GLOBALS['tc_registered_pages'][ $slug ] ), 'WordPress callback missing: ' . $slug );
}

// Preserve all registered submenu entries: WordPress needs them to route
// admin.php?page=... links and validate user capabilities.
$core->hide_core_internal_submenus();
check( $initial === menu_slugs(), 'Core submenu registry must remain intact' );
$core->hide_sidebar_submenu();
check( false !== strpos( $core->family_body_class( '' ), 'tcc-family-no-extension-menu' ), 'Only Core pages hide the entire empty submenu' );
$css = file_get_contents( dirname( __DIR__, 3 ) . '/taxocommerce-core/assets/admin.css' );
check( false !== strpos( $css, 'page=taxocommerce-core-content' ), 'Core submenu uses static stylesheet rules' );
check( false !== strpos( $css, 'tcc-family-no-extension-menu' ), 'Empty Core submenu remains hidden via CSS' );
// With other plugins active, their menu items stay visible.
$core->register_menu();
foreach ( array( 'taxocommerce-images', 'taxocommerce-vocabulary', 'seo-facturas' ) as $slug ) {
    $GLOBALS['submenu']['taxocommerce-core'][] = array( $slug, 'manage_options', $slug );
}
$core->normalize_family_submenu_labels();
$core->hide_core_internal_submenus();
$core->hide_core_internal_submenus(); // Idempotent on repeated hook runs.
$expected = array_merge( $initial, array( 'taxocommerce-images', 'taxocommerce-vocabulary', 'seo-facturas' ) );
check( $expected === array_values( menu_slugs() ), 'WordPress route registry retains plugins and internal Core pages' );
foreach ( $internal as $slug ) {
    check( isset( $GLOBALS['tc_registered_pages'][ $slug ] ), 'Hiding sidebar must not unregister Core routes: ' . $slug );
}
$core->hide_sidebar_submenu();
check( false === strpos( $core->family_body_class( '' ), 'tcc-family-no-extension-menu' ), 'Other plugins keep submenu visible' );
check( false !== strpos( $css, 'page=taxocommerce-core-content' ), 'Core internal links are visually hidden without removing routes' );
$dashboard = file_get_contents( dirname( __DIR__, 3 ) . '/taxocommerce-core/includes/class-taxocommerce-core-dashboard.php' );
foreach ( array(
    'TaxoCommerce_Core_Content_Manager::PAGE_SLUG',
    "'taxocommerce-core-hierarchy'",
    "'taxocommerce-core-menu'",
    'TaxoCommerce_Core_User_Guide::PAGE_SLUG',
) as $link ) {
    check( false !== strpos( $dashboard, $link ), 'Internal Core navigation missing: ' . $link );
}
echo "Core sidebar plugin-only smoke: OK\n";
