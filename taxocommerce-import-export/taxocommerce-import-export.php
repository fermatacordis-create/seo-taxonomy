<?php
/**
 * Plugin Name: TaxoCommerce Import-Export - Import & Export for WooCommerce
 * Plugin URI: https://asociacionfocazul.com/taxocommerce-import-export/
 * Description: Importa y exporta productos, categorías de producto, categorías WordPress, entradas, páginas y Media, con integración opcional con TaxoCommerce Core.
 * Version: 0.4.32
 * Release Date: 2026-11-13
 * Requires at least: 6.5
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * Author: David Perez
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: taxocommerce-import-export
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/includes/admin-branding.php';
require_once __DIR__ . '/includes/class-taxocommerce-autotest-code.php';
\TaxoCommerce_Import_Export_Autotest_Code::boot();

define( 'TAXOCOMMERCE_IMPORT_EXPORT_VERSION', '0.4.32' );
define( 'TAXOCOMMERCE_IMPORT_EXPORT_FILE', __FILE__ );
define( 'TAXOCOMMERCE_IMPORT_EXPORT_PATH', plugin_dir_path( __FILE__ ) );
define( 'TAXOCOMMERCE_IMPORT_EXPORT_BASENAME', plugin_basename( __FILE__ ) );

// Guard against stale or third-party MO files with empty msgstr entries.
require_once TAXOCOMMERCE_IMPORT_EXPORT_PATH . 'includes/class-taxocommerce-ie-gettext-fallback.php';
TaxoCommerce_IE_Gettext_Fallback::register();

require_once TAXOCOMMERCE_IMPORT_EXPORT_PATH . 'includes/class-taxocommerce-ie-load.php';
require_once TAXOCOMMERCE_IMPORT_EXPORT_PATH . 'includes/class-taxocommerce-import-export.php';
require_once TAXOCOMMERCE_IMPORT_EXPORT_PATH . 'includes/class-taxocommerce-import-export-csv.php';
require_once TAXOCOMMERCE_IMPORT_EXPORT_PATH . 'includes/class-taxocommerce-import-export-exporter.php';
require_once TAXOCOMMERCE_IMPORT_EXPORT_PATH . 'includes/class-taxocommerce-import-export-media.php';
require_once TAXOCOMMERCE_IMPORT_EXPORT_PATH . 'includes/class-taxocommerce-import-export-batch.php';
require_once TAXOCOMMERCE_IMPORT_EXPORT_PATH . 'includes/class-taxocommerce-import-export-auto-test.php';
require_once TAXOCOMMERCE_IMPORT_EXPORT_PATH . 'includes/class-taxocommerce-import-export-manual.php';

/*
 * Register with Core without invoking translations before init.
 * The visible translated strings are rendered later in admin_menu/admin pages.
 */
add_action( 'taxocommerce_core_register_modules', array( TaxoCommerce_Import_Export::instance(), 'register_in_core' ) );

/**
 * Returns singleton.
 *
 * @return TaxoCommerce_Import_Export
 */
function taxocommerce_import_export() {
	return TaxoCommerce_Import_Export::instance();
}

/**
 * Boot plugin.
 *
 * @return void
 */
function taxocommerce_import_export_boot() {
	taxocommerce_import_export()->boot();
}
add_action( 'plugins_loaded', 'taxocommerce_import_export_boot', 25 );
TaxoCommerce_Import_Export_Auto_Test::register();

/* Native local gettext catalog fallback (#2062). */
add_action( 'init', static function () {
    $domain = 'taxocommerce-import-export';
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
