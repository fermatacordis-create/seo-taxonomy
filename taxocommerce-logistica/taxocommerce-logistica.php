<?php
/**
 * Plugin Name: TaxoCommerce Logistics
 * Plugin URI: https://asociacionfocazul.com/taxocommerce-logistica/
 * Description: WooCommerce shipping weight, dimensions, and delivery-cost management.
 * Version: 0.2.21
 * Release Date: 2026-11-14
 * Requires at least: 6.5
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * Author: David Pérez
 * License: MIT
 * Text Domain: taxocommerce-logistica
 * Domain Path: /languages
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/includes/admin-branding.php';
require_once __DIR__ . '/includes/class-taxocommerce-autotest-code.php';
\TaxoCommerce_Logistica_Autotest_Code::boot();


define( 'TAXOCOMMERCE_LOGISTICA_VERSION', '0.2.21' );
define( 'TAXOCOMMERCE_LOGISTICA_FILE', __FILE__ );
define( 'TAXOCOMMERCE_LOGISTICA_DIR', plugin_dir_path( __FILE__ ) );
define( 'TAXOCOMMERCE_LOGISTICA_URL', plugin_dir_url( __FILE__ ) );

require_once TAXOCOMMERCE_LOGISTICA_DIR . 'includes/class-supplier-purchasing.php';
require_once TAXOCOMMERCE_LOGISTICA_DIR . 'includes/seo-transporte.php';
require_once TAXOCOMMERCE_LOGISTICA_DIR . 'includes/class-auto-test.php';
TaxoCommerce_Logistica_Auto_Test::register();
require_once TAXOCOMMERCE_LOGISTICA_DIR . 'includes/class-user-guide.php';
TaxoCommerce_Logistica_User_Guide::register();

function taxocommerce_logistica_register_menu() {
    $capability = class_exists( 'WooCommerce' ) ? 'manage_woocommerce' : 'manage_options';

    // Core owns the family sidebar. Preserve this plugin's unique page slug
    // and callback; the SEO monolith keeps its separate legacy order screen.
    if ( function_exists( 'taxocommerce_core' ) ) {
        add_submenu_page(
            'taxocommerce-core',
            __( 'TaxoCommerce Logistics', 'taxocommerce-logistica' ),
            __( 'Logistics', 'taxocommerce-logistica' ),
            $capability,
            'taxocommerce-logistica',
            array( 'TaxoCommerce_Logistica_Transporte_Costes', 'render_page' )
        );
        return;
    }

    // Standalone fallback when the family Core plugin is not active.
    add_menu_page(
        __( 'TaxoCommerce Logistics', 'taxocommerce-logistica' ),
        __( 'TaxoCommerce Logistics', 'taxocommerce-logistica' ),
        $capability,
        'taxocommerce-logistica',
        array( 'TaxoCommerce_Logistica_Transporte_Costes', 'render_page' ),
        'dashicons-cart',
        59
    );
}
add_action( 'admin_menu', 'taxocommerce_logistica_register_menu', 80 );

function taxocommerce_logistica_register_core( $core ) {
    if ( ! is_object( $core ) || ! method_exists( $core, 'register_module' ) ) {
        return;
    }

    $core->register_module(
        'logistica',
        array(
            'name'        => 'TaxoCommerce Logistics',
            'version'     => TAXOCOMMERCE_LOGISTICA_VERSION,
            'status'      => 'active',
            'description' => 'Shipping weight, dimensions, and delivery-cost rules for WooCommerce.',
            'admin_url'   => admin_url( 'admin.php?page=taxocommerce-logistica' ),
        )
    );
}
add_action( 'taxocommerce_core_register_modules', 'taxocommerce_logistica_register_core' );
if ( function_exists( 'taxocommerce_core' ) && did_action( 'taxocommerce_core_loaded' ) ) {
    taxocommerce_logistica_register_core( taxocommerce_core() );
}

function taxocommerce_logistica_family_catalog( $extensions ) {
    $extensions = is_array( $extensions ) ? $extensions : array();
    $extensions['logistica'] = array(
        'id'            => 'logistica',
        'name'          => 'TaxoCommerce Logistics',
        'plugin_file'   => 'taxocommerce-logistica/taxocommerce-logistica.php',
        'org_slug'      => 'taxocommerce-logistica',
        'search_term'   => 'TaxoCommerce Logistics',
        'wordpress_org' => false,
        'admin_url'     => admin_url( 'admin.php?page=taxocommerce-logistica' ),
        'description'   => 'Shipping weight, dimensions, and delivery-cost rules for WooCommerce.',
    );
    return $extensions;
}
add_filter( 'taxocommerce_core_family_extensions', 'taxocommerce_logistica_family_catalog' );

/* Native local gettext catalog fallback (#2062). */
add_action( 'init', static function () {
    $domain = 'taxocommerce-logistica';
    if ( is_textdomain_loaded( $domain ) ) {
        return;
    }
    $locale = sanitize_file_name( determine_locale() );
    // Prefer official WordPress language packs when installed.
    $official_pack = WP_LANG_DIR . '/plugins/' . $domain . '-' . $locale;
    if ( is_readable( $official_pack . '.mo' ) || is_readable( $official_pack . '.l10n.php' ) ) {
        return;
    }
    $catalog = __DIR__ . '/languages/' . $domain . '-' . $locale . '.mo';
    if ( ! is_readable( $catalog ) ) {
        // Use a bundled base-language catalog for WordPress locale variants.
        $language = strtolower( substr( $locale, 0, 2 ) );
        $fallback_locales = array(
            'es' => 'es_ES',
            'en' => 'en_US',
            'fr' => 'fr_FR',
            'de' => 'de_DE',
            'nl' => 'nl_NL',
        );
        if ( isset( $fallback_locales[ $language ] ) ) {
            $catalog = __DIR__ . '/languages/' . $domain . '-' . $fallback_locales[ $language ] . '.mo';
        }
    }
    if ( is_readable( $catalog ) ) {
        load_textdomain( $domain, $catalog );
    }
}, 1 );
