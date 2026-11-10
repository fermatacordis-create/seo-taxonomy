<?php
/**
 * Plugin Name: TaxoCommerce Billing
 * Plugin URI: https://asociacionfocazul.com/taxocommerce-facturacion/
 * Description: WooCommerce invoices, proformas, and PDF quotations migrated 1:1 from TaxoCommerce SEO.
 * Version: 0.1.13
 * Release Date: 2026-11-10
 * Requires at least: 6.5
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * Author: David Pérez
 * License: MIT
 * Text Domain: taxocommerce-facturacion
 * Domain Path: /languages
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/includes/admin-branding.php';
require_once __DIR__ . '/includes/class-taxocommerce-autotest-code.php';
\TaxoCommerce_Facturacion_Autotest_Code::boot();


define( 'TAXOCOMMERCE_FACTURACION_VERSION', '0.1.13' );
define( 'TAXOCOMMERCE_FACTURACION_FILE', __FILE__ );
define( 'TAXOCOMMERCE_FACTURACION_DIR', plugin_dir_path( __FILE__ ) );
define( 'TAXOCOMMERCE_FACTURACION_URL', plugin_dir_url( __FILE__ ) );

require_once TAXOCOMMERCE_FACTURACION_DIR . 'includes/class-taxocommerce-billing-manual.php';
require_once TAXOCOMMERCE_FACTURACION_DIR . 'includes/class-taxocommerce-billing-autotest.php';

function taxocommerce_facturacion_boot_module() {
    if ( class_exists( 'SEO_Facturas_Admin', false ) ) {
        return;
    }

    if ( ! defined( 'SEO_FACTURAS_PATH' ) ) {
        define( 'SEO_FACTURAS_PATH', TAXOCOMMERCE_FACTURACION_DIR . 'includes/' );
    }
    if ( ! defined( 'SEO_FACTURAS_URL' ) ) {
        define( 'SEO_FACTURAS_URL', TAXOCOMMERCE_FACTURACION_URL . 'includes/' );
    }

    require_once TAXOCOMMERCE_FACTURACION_DIR . 'includes/seo-facturas-bootstrap.php';
}
add_action( 'plugins_loaded', 'taxocommerce_facturacion_boot_module', 5 );

/**
 * Billing owns its administration page and tab navigation.
 * The existing document controller retains responsibility for its six
 * financial workflows without registering any duplicate billing handlers.
 */
function taxocommerce_facturacion_render_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Sin permisos para acceder a Billing.', 'taxocommerce-facturacion' ) );
    }
    $tabs = array(
        'company'   => 'Datos empresa',
        'tax'       => 'Fiscalidad',
        'invoices'  => 'Facturas',
        'proformas' => 'Proformas',
        'quotes'    => 'Presupuestos',
        'documents' => 'Documentos',
        'manual'    => 'Manual',
        'autotest'  => 'Autotest',
    );
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only tab selector.
    $tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'company';
    if ( ! isset( $tabs[ $tab ] ) ) {
        $tab = 'company';
    }

    echo '<div class="wrap seo-facturas-wrap taxocommerce-billing-shell">';
    echo '<h1>' . esc_html__( 'TaxoCommerce Billing', 'taxocommerce-facturacion' ) . '</h1>';
    echo '<nav class="nav-tab-wrapper taxocommerce-billing-tabs" aria-label="Secciones de TaxoCommerce Billing">';
    foreach ( $tabs as $key => $label ) {
        $url = add_query_arg(
            array( 'page' => 'taxocommerce-facturacion', 'tab' => $key ),
            admin_url( 'admin.php' )
        );
        echo '<a class="nav-tab ' . ( $tab === $key ? 'nav-tab-active' : '' ) . '" href="' .
            esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
    }
    echo '</nav>';
    if ( 'manual' === $tab ) {
        TaxoCommerce_Billing_Manual::render();
    } elseif ( 'autotest' === $tab ) {
        TaxoCommerce_Billing_Autotest::render();
    } elseif ( class_exists( 'SEO_Facturas_Admin', false ) ) {
        // Preserve the original six workflows. Only hide their duplicated UI tabs.
        echo '<div class="taxocommerce-billing-existing-settings">';
        SEO_Facturas_Admin::render_page();
        echo '</div>';
    } else {
        echo '<div class="notice notice-error inline">' . '<p>' . esc_html__( 'El módulo documental de Billing no está disponible.', 'taxocommerce-facturacion' ) . '</p>' . '</div>';
    }
    echo '</div>';
}

/**
 * The standalone route needs its own stylesheet and media picker.
 */
function taxocommerce_facturacion_enqueue_admin_assets() {
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only page selector.
    $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
    if ( 'taxocommerce-facturacion' !== $page ) {
        return;
    }
    wp_enqueue_media();
    wp_enqueue_style(
        'taxocommerce-billing-admin',
        TAXOCOMMERCE_FACTURACION_URL . 'includes/assets/admin.css',
        array(),
        TAXOCOMMERCE_FACTURACION_VERSION
    );
}
add_action( 'admin_enqueue_scripts', 'taxocommerce_facturacion_enqueue_admin_assets' );

function taxocommerce_facturacion_register_menu() {
    if ( function_exists( 'taxocommerce_core' ) ) {
        add_submenu_page(
            'taxocommerce-core',
            __( 'Facturas y presupuestos', 'taxocommerce-facturacion' ),
            __( 'Billing', 'taxocommerce-facturacion' ),
            'manage_options',
            'taxocommerce-facturacion',
            'taxocommerce_facturacion_render_page'
        );
        return;
    }

    add_menu_page(
        __( 'Facturas y presupuestos', 'taxocommerce-facturacion' ),
        __( 'TaxoCommerce Billing', 'taxocommerce-facturacion' ),
        'manage_options',
        'taxocommerce-facturacion',
        'taxocommerce_facturacion_render_page',
        'dashicons-media-spreadsheet',
        60
    );
}
add_action( 'admin_menu', 'taxocommerce_facturacion_register_menu', 80 );

function taxocommerce_facturacion_register_core( $core ) {
    if ( ! is_object( $core ) || ! method_exists( $core, 'register_module' ) ) {
        return;
    }

    $core->register_module(
        'facturacion',
        array(
            'name'        => 'TaxoCommerce Billing',
            'version'     => TAXOCOMMERCE_FACTURACION_VERSION,
            'status'      => 'active',
            'description' => 'Facturas, proformas y presupuestos PDF conectados a WooCommerce.',
            'admin_url'   => admin_url( 'admin.php?page=taxocommerce-facturacion' ),
        )
    );
}
add_action( 'taxocommerce_core_register_modules', 'taxocommerce_facturacion_register_core' );
if ( function_exists( 'taxocommerce_core' ) && did_action( 'taxocommerce_core_loaded' ) ) {
    taxocommerce_facturacion_register_core( taxocommerce_core() );
}

function taxocommerce_facturacion_family_catalog( $extensions ) {
    $extensions = is_array( $extensions ) ? $extensions : array();
    $extensions['facturacion'] = array(
        'id'            => 'facturacion',
        'name'          => 'TaxoCommerce Billing',
        'plugin_file'   => 'taxocommerce-facturacion/taxocommerce-facturacion.php',
        'org_slug'      => 'taxocommerce-facturacion',
        'search_term'   => 'TaxoCommerce Billing',
        'wordpress_org' => false,
        'admin_url'     => admin_url( 'admin.php?page=taxocommerce-facturacion' ),
        'description'   => 'Facturas, proformas y presupuestos PDF conectados a WooCommerce.',
    );
    return $extensions;
}
add_filter( 'taxocommerce_core_family_extensions', 'taxocommerce_facturacion_family_catalog' );

function taxocommerce_facturacion_force_core_parent( $parent_file ) {
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin routing.
    $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
    if ( function_exists( 'taxocommerce_core' ) && 'taxocommerce-facturacion' === $page ) {
        return 'taxocommerce-core';
    }
    return $parent_file;
}
add_filter( 'parent_file', 'taxocommerce_facturacion_force_core_parent', 999 );

function taxocommerce_facturacion_force_core_submenu( $submenu_file ) {
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin routing.
    $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
    if ( function_exists( 'taxocommerce_core' ) && 'taxocommerce-facturacion' === $page ) {
        return 'taxocommerce-facturacion';
    }
    return $submenu_file;
}
add_filter( 'submenu_file', 'taxocommerce_facturacion_force_core_submenu', 999 );

/* Native local gettext catalog fallback (#2062). */
add_action( 'init', static function () {
    $domain = 'taxocommerce-facturacion';
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
