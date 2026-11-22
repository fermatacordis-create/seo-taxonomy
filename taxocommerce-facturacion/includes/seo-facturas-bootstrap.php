<?php
/**
 * Bootstrap del modulo de facturas, proformas y presupuestos.
 */

defined('ABSPATH') || exit;

if (!defined('SEO_FACTURAS_VERSION')) {
    define('SEO_FACTURAS_VERSION', '0.4.0');
}

if (!defined('SEO_FACTURAS_DB_VERSION')) {
    define('SEO_FACTURAS_DB_VERSION', '0.2.0');
}

if (!defined('SEO_FACTURAS_PATH')) {
    define('SEO_FACTURAS_PATH', __DIR__ . '/');
}

if (!defined('SEO_FACTURAS_URL')) {
    define('SEO_FACTURAS_URL', plugins_url( '/', __FILE__ ));
}

require_once SEO_FACTURAS_PATH . 'class-seo-facturas-install.php';
require_once SEO_FACTURAS_PATH . 'class-seo-facturas-settings.php';
require_once SEO_FACTURAS_PATH . 'class-seo-facturas-tax.php';
require_once SEO_FACTURAS_PATH . 'class-seo-facturas-special-rates.php';
require_once SEO_FACTURAS_PATH . 'class-seo-facturas-vies.php';
require_once SEO_FACTURAS_PATH . 'class-seo-facturas-eu-vat.php';
require_once SEO_FACTURAS_PATH . 'class-seo-facturas-snapshot.php';
require_once SEO_FACTURAS_PATH . 'class-seo-facturas-pdf.php';
require_once SEO_FACTURAS_PATH . 'class-seo-facturas-documents.php';
require_once SEO_FACTURAS_PATH . 'class-seo-facturas-rectifications.php';
require_once SEO_FACTURAS_PATH . 'class-seo-facturas-woocommerce.php';
require_once SEO_FACTURAS_PATH . 'class-seo-facturas-quotes.php';
require_once SEO_FACTURAS_PATH . 'class-seo-facturas-customer-documents.php';
require_once SEO_FACTURAS_PATH . 'class-seo-facturas-admin.php';

if (!function_exists('seo_facturas_boot_module')) {
    function seo_facturas_boot_module() {
        SEO_Facturas_Install::maybe_upgrade();
        SEO_Facturas_Settings::init();
        SEO_Facturas_Admin::init();

        if (class_exists('WooCommerce') && function_exists('wc_get_order')) {
            SEO_Facturas_Tax::init();
            SEO_Facturas_Eu_Vat::init();
            SEO_Facturas_WooCommerce::init();
            SEO_Facturas_Quotes::init();
            SEO_Facturas_Customer_Documents::init();
        }
    }
}

if (did_action('plugins_loaded')) {
    seo_facturas_boot_module();
} else {
    add_action('plugins_loaded', 'seo_facturas_boot_module', 20);
}
