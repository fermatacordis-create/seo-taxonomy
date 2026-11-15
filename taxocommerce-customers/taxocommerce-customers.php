<?php
/**
 * Plugin Name: TaxoCommerce Customers
 * Plugin URI: https://github.com/distribuidordeherramientases-wq/seo-taxonomy/tree/staging/taxocommerce-customers
 * Description: Private customer directory and CRM for WooCommerce buyers, guests and manually added contacts, with batch CSV import/export.
 * Version: 0.1.1
 * Release Date: 2026-11-14
 * Requires at least: 6.7
 * Requires PHP: 7.4
 * Author: David Perez
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: taxocommerce-customers
 * Domain Path: /languages
 */
defined( 'ABSPATH' ) || exit;

define( 'TCCUST_VERSION', '0.1.1' );
define( 'TCCUST_DIR', plugin_dir_path( __FILE__ ) );
define( 'TCCUST_URL', plugin_dir_url( __FILE__ ) );

require_once TCCUST_DIR . 'includes/class-tccust-db.php';
require_once TCCUST_DIR . 'includes/class-tccust-sync.php';
require_once TCCUST_DIR . 'includes/class-tccust-admin.php';
require_once TCCUST_DIR . 'includes/class-tccust-csv.php';
require_once TCCUST_DIR . 'includes/class-tccust-csv-export.php';
require_once TCCUST_DIR . 'includes/class-tccust-manual.php';
require_once TCCUST_DIR . 'includes/class-tccust-autotest.php';
require_once TCCUST_DIR . 'includes/class-tccust-privacy.php';
require_once TCCUST_DIR . 'includes/admin-branding.php';
require_once TCCUST_DIR . 'includes/class-taxocommerce-autotest-code.php';

register_activation_hook( __FILE__, array( 'TCCUST_DB', 'install' ) );

// Upgrade existing stores once, before menu capability checks; no per-request
// table scan or dependency on a TaxoCommerce Core activation hook.
add_action( 'init', static function () {
	if ( TCCUST_DB::VERSION !== (string) get_option( 'tccust_schema_version', '' ) ) {
		TCCUST_DB::install();
	}
}, 20 );

/* Prefer official WordPress language packs; load bundled PO/MO catalogs locally. */
add_action( 'init', static function () {
	$domain = 'taxocommerce-customers';
	if ( is_textdomain_loaded( $domain ) ) {
		return;
	}
	$locale = sanitize_file_name( determine_locale() );
	$official_pack = WP_LANG_DIR . '/plugins/' . $domain . '-' . $locale;
	if ( is_readable( $official_pack . '.mo' ) || is_readable( $official_pack . '.l10n.php' ) ) {
		return;
	}
	$catalog = __DIR__ . '/languages/' . $domain . '-' . $locale . '.mo';
	if ( ! is_readable( $catalog ) ) {
		$fallback = array( 'es' => 'es_ES', 'en' => 'en_US', 'fr' => 'fr_FR', 'de' => 'de_DE', 'nl' => 'nl_NL' );
		$lang = strtolower( substr( $locale, 0, 2 ) );
		if ( isset( $fallback[ $lang ] ) ) {
			$catalog = __DIR__ . '/languages/' . $domain . '-' . $fallback[ $lang ] . '.mo';
		}
	}
	if ( is_readable( $catalog ) ) {
		load_textdomain( $domain, $catalog );
	}
}, 1 );

/* Core integration is optional and never owns Customers' private data. */
add_action( 'taxocommerce_core_register_modules', array( 'TCCUST_Admin', 'register_module' ) );
if ( function_exists( 'taxocommerce_core' ) && did_action( 'taxocommerce_core_loaded' ) ) {
	TCCUST_Admin::register_module( taxocommerce_core() );
}
add_filter( 'taxocommerce_core_family_extensions', static function ( $extensions ) {
	$extensions = is_array( $extensions ) ? $extensions : array();
	$extensions['customers'] = array(
		'id' => 'customers',
		'name' => 'TaxoCommerce Customers',
		'plugin_file' => 'taxocommerce-customers/taxocommerce-customers.php',
		'org_slug' => 'taxocommerce-customers',
		'wordpress_org' => false,
		'admin_url' => admin_url( 'admin.php?page=taxocommerce-customers' ),
		'description' => __( 'Private customer and purchaser management.', 'taxocommerce-customers' ),
	);
	return $extensions;
} );

add_action( 'plugins_loaded', static function () {
	TCCUST_Admin::boot();
	TCCUST_Sync::boot();
	TCCUST_CSV::boot();
	TCCUST_CSV_Export::boot();
	TCCUST_Privacy::boot();
	TCCUST_Autotest::boot();
	TaxoCommerce_Customers_Autotest_Code::boot();
} );
