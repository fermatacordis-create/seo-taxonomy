<?php
/**
 * Plugin Name: TaxoCommerce Core - Catalog & Content Management for WooCommerce
 * Plugin URI: https://asociacionfocazul.com/taxocommerce-core/
 * Description: Modular core for catalog hierarchy, unified content editing, logs, and extensions in WooCommerce stores.
 * Version: 0.4.72
 * Release Date: 2026-11-10
 * Requires at least: 6.5
 * Requires PHP: 7.4
 * Author: David Perez
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: taxocommerce-core
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'TAXOCOMMERCE_CORE_VERSION', '0.4.72' );
define( 'TAXOCOMMERCE_CORE_RELEASE_DATE', '2026-11-10' );
define( 'TAXOCOMMERCE_CORE_FILE', __FILE__ );
define( 'TAXOCOMMERCE_CORE_PATH', plugin_dir_path( __FILE__ ) );
define( 'TAXOCOMMERCE_CORE_BASENAME', plugin_basename( __FILE__ ) );

require_once TAXOCOMMERCE_CORE_PATH . 'includes/class-taxocommerce-core-logger.php';
require_once __DIR__ . '/includes/class-taxocommerce-autotest-code.php';
\TaxoCommerce_Core_Autotest_Code::boot();
require_once TAXOCOMMERCE_CORE_PATH . 'includes/class-taxocommerce-core-country.php';
require_once TAXOCOMMERCE_CORE_PATH . 'includes/class-taxocommerce-core-hierarchy.php';
require_once TAXOCOMMERCE_CORE_PATH . 'includes/class-taxocommerce-core-hierarchy-classic.php';
require_once TAXOCOMMERCE_CORE_PATH . 'includes/class-taxocommerce-core-menu.php';
require_once TAXOCOMMERCE_CORE_PATH . 'includes/class-taxocommerce-core-vocabulary.php';
require_once TAXOCOMMERCE_CORE_PATH . 'includes/class-taxocommerce-core-editorial-guide.php';
require_once TAXOCOMMERCE_CORE_PATH . 'includes/class-taxocommerce-core-editor-ui.php';
require_once TAXOCOMMERCE_CORE_PATH . 'includes/class-taxocommerce-core-architecture-report.php';
require_once TAXOCOMMERCE_CORE_PATH . 'includes/class-taxocommerce-core-content-inventory.php';
require_once TAXOCOMMERCE_CORE_PATH . 'includes/class-taxocommerce-core-content-manager.php';
require_once TAXOCOMMERCE_CORE_PATH . 'includes/class-taxocommerce-core-anomalies.php';
require_once TAXOCOMMERCE_CORE_PATH . 'includes/class-taxocommerce-core-anomalies-json.php';
require_once TAXOCOMMERCE_CORE_PATH . 'includes/class-taxocommerce-core-auto-test.php';
require_once TAXOCOMMERCE_CORE_PATH . 'includes/class-taxocommerce-core.php';
require_once TAXOCOMMERCE_CORE_PATH . 'includes/class-taxocommerce-core-dashboard.php';
require_once TAXOCOMMERCE_CORE_PATH . 'includes/class-taxocommerce-core-suite.php';
require_once TAXOCOMMERCE_CORE_PATH . 'includes/class-taxocommerce-core-user-guide.php';
require_once TAXOCOMMERCE_CORE_PATH . 'includes/class-taxocommerce-core-technical-guide.php';

/**
 * Returns the TaxoCommerce Core singleton.
 *
 * @return TaxoCommerce_Core
 */
function taxocommerce_core() {
	return TaxoCommerce_Core::instance();
}

/**
 * Boots TaxoCommerce Core after all plugins are loaded.
 *
 * @return void
 */
function taxocommerce_core_boot() {
	taxocommerce_core()->boot();
}
add_action( 'plugins_loaded', 'taxocommerce_core_boot', 20 );

/**
 * Prefer WordPress language packs and retain standalone bundled translations.
 * WordPress.org plugins do not need load_plugin_textdomain(); a local MO fallback
 * is still needed for the GitHub-distributed standalone ZIP and English menus.
 */
function taxocommerce_core_load_textdomain() {
    $domain = 'taxocommerce-core';
    if ( is_textdomain_loaded( $domain ) ) {
        return;
    }
    $locale = sanitize_file_name( determine_locale() );
    $official_pack = WP_LANG_DIR . '/plugins/' . $domain . '-' . $locale;
    if ( is_readable( $official_pack . '.mo' ) || is_readable( $official_pack . '.l10n.php' ) ) {
        // WordPress can load the globally installed language pack on demand.
        return;
    }
    $catalog = TAXOCOMMERCE_CORE_PATH . 'languages/' . $domain . '-' . $locale . '.mo';
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
            $catalog = TAXOCOMMERCE_CORE_PATH . 'languages/' . $domain . '-' . $fallback_locales[ $language ] . '.mo';
        }
    }
    if ( is_readable( $catalog ) ) {
        load_textdomain( $domain, $catalog );
    }
}
add_action( 'init', 'taxocommerce_core_load_textdomain', 1 );

/**
 * Activation tasks.
 *
 * @return void
 */
function taxocommerce_core_activate() {
	TaxoCommerce_Core_Hierarchy_Classic::install();
	update_option( 'taxocommerce_core_version', TAXOCOMMERCE_CORE_VERSION, false );

	if ( false === get_option( 'taxocommerce_core_log_limit', false ) ) {
		add_option( 'taxocommerce_core_log_limit', 100, '', false );
	}

	$logger = new TaxoCommerce_Core_Logger();
	$logger->info(
		'TaxoCommerce Core activated.',
		array( 'version' => TAXOCOMMERCE_CORE_VERSION )
	);
}
register_activation_hook( __FILE__, 'taxocommerce_core_activate' );
