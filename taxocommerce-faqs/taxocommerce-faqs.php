<?php
/**
 * Plugin Name: TaxoCommerce FAQs
 * Plugin URI: https://asociacionfocazul.com/taxocommerce-faqs/
 * Description: Manage WordPress and WooCommerce FAQs with CSV exchange, KPIs and semantic labels. Uninstalling deletes stored FAQ data.
 * Version: 0.1.17
 * Release Date: 2026-11-10
 * Requires at least: 6.5
 * Requires PHP: 7.4
 * Author: David Perez
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: taxocommerce-faqs
 * Domain Path: /languages
 */
defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/includes/admin-branding.php';
require_once __DIR__ . '/includes/class-taxocommerce-autotest-code.php';
\TaxoCommerce_FAQs_Autotest_Code::boot();
define( 'TAXOCOMMERCE_FAQS_VERSION', '0.1.17' );
define( 'TAXOCOMMERCE_FAQS_PATH', plugin_dir_path( __FILE__ ) );
require_once TAXOCOMMERCE_FAQS_PATH . 'includes/class-taxocommerce-faqs.php';
require_once TAXOCOMMERCE_FAQS_PATH . 'includes/class-taxocommerce-faqs-lifecycle.php';
require_once TAXOCOMMERCE_FAQS_PATH . 'includes/class-taxocommerce-faqs-auto-test.php';
require_once TAXOCOMMERCE_FAQS_PATH . 'includes/class-taxocommerce-faqs-manual.php';
register_activation_hook( __FILE__, array( 'TaxoCommerce_FAQs_Lifecycle', 'activate' ) );
add_action( 'admin_notices', array( 'TaxoCommerce_FAQs_Lifecycle', 'removal_notice' ) );
add_action( 'plugins_loaded', array( 'TaxoCommerce_FAQs', 'boot' ), 25 );
add_action( 'taxocommerce_core_register_modules', array( 'TaxoCommerce_FAQs', 'register_core' ) );
TaxoCommerce_FAQs_Auto_Test::register();

/* Native local gettext catalog fallback (#2062). */
add_action( 'init', static function () {
    $domain = 'taxocommerce-faqs';
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
