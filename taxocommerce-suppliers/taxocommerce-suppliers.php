<?php
/**
 * Plugin Name: TaxoCommerce Suppliers
 * Plugin URI: https://asociacionfocazul.com/taxocommerce-suppliers/
 * Description: Supplier catalogue intake, purchasing, product decisions, supplier prices, WooCommerce linking, and external image references.
 * Version: 0.2.18
 * Release Date: 2026-11-10
 * Requires at least: 6.5
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * Author: David Perez
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: taxocommerce-suppliers
 * Domain Path: /languages
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/includes/admin-branding.php';
require_once __DIR__ . '/includes/class-taxocommerce-autotest-code.php';
\TaxoCommerce_Suppliers_Autotest_Code::boot();

define( 'TAXOCOMMERCE_SUPPLIERS_VERSION', '0.2.18' );
define( 'TAXOCOMMERCE_SUPPLIERS_FILE', __FILE__ );
define( 'TAXOCOMMERCE_SUPPLIERS_DIR', plugin_dir_path( __FILE__ ) );
define( 'TAXOCOMMERCE_SUPPLIERS_URL', plugin_dir_url( __FILE__ ) );

require_once TAXOCOMMERCE_SUPPLIERS_DIR . 'includes/class-tcsup-schema.php';
require_once TAXOCOMMERCE_SUPPLIERS_DIR . 'includes/class-tcsup-csv.php';
require_once TAXOCOMMERCE_SUPPLIERS_DIR . 'includes/class-tcsup-products.php';
require_once TAXOCOMMERCE_SUPPLIERS_DIR . 'includes/class-tcsup-purchasing.php';
require_once TAXOCOMMERCE_SUPPLIERS_DIR . 'includes/class-tcsup-admin.php';
require_once TAXOCOMMERCE_SUPPLIERS_DIR . 'includes/class-tcsup-manual.php';
require_once TAXOCOMMERCE_SUPPLIERS_DIR . 'includes/class-tcsup-autotest.php';

function taxocommerce_suppliers_activate() {
	TCSUP_Schema::install();
	if ( false === get_option( 'taxocommerce_suppliers_sale_margin', false ) ) {
		add_option( 'taxocommerce_suppliers_sale_margin', 20, '', false );
	}
	if ( false === get_option( 'taxocommerce_suppliers_regular_margin', false ) ) {
		add_option( 'taxocommerce_suppliers_regular_margin', 45, '', false );
	}
}
register_activation_hook( __FILE__, 'taxocommerce_suppliers_activate' );

function taxocommerce_suppliers_boot() {
	TCSUP_Schema::maybe_install();
	TCSUP_Admin::boot();
}
add_action( 'plugins_loaded', 'taxocommerce_suppliers_boot', 30 );

function taxocommerce_suppliers_register_core( $core ) {
	if ( ! is_object( $core ) || ! method_exists( $core, 'register_module' ) ) {
		return;
	}
	$core->register_module(
		'suppliers',
		array(
			'name'        => 'TaxoCommerce Suppliers',
			'version'     => TAXOCOMMERCE_SUPPLIERS_VERSION,
			'status'      => 'active',
			'description' => 'Supplier catalogue, purchasing, prices, and external images.',
			'admin_url'   => admin_url( 'admin.php?page=taxocommerce-suppliers' ),
		)
	);
}
add_action( 'taxocommerce_core_register_modules', 'taxocommerce_suppliers_register_core' );
if ( function_exists( 'taxocommerce_core' ) && did_action( 'taxocommerce_core_loaded' ) ) {
	taxocommerce_suppliers_register_core( taxocommerce_core() );
}

function taxocommerce_suppliers_family_catalog( $extensions ) {
	$extensions = is_array( $extensions ) ? $extensions : array();
	$extensions['suppliers'] = array(
		'id'            => 'suppliers',
		'name'          => 'TaxoCommerce Suppliers',
		'plugin_file'   => 'taxocommerce-suppliers/taxocommerce-suppliers.php',
		'org_slug'      => 'taxocommerce-suppliers',
		'search_term'   => 'TaxoCommerce Suppliers',
		'wordpress_org' => false,
		'admin_url'     => admin_url( 'admin.php?page=taxocommerce-suppliers' ),
		'description'   => 'Supplier catalogue, purchasing, prices, and external images.',
	);
	return $extensions;
}
add_filter( 'taxocommerce_core_family_extensions', 'taxocommerce_suppliers_family_catalog' );

function taxocommerce_suppliers_get_sources( $product_id ) {
	return TCSUP_Products::sources_for_product( absint( $product_id ) );
}

function taxocommerce_suppliers_get_external_images( $product_id ) {
	global $wpdb;
	$table = TCSUP_Schema::images_table();
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Read the current supplier inventory/schema for this admin operation; stale cache would misreport changes.
	return $wpdb->get_results(
		$wpdb->prepare(
			"SELECT supplier,supplier_product_id,supplier_sku,product_id,catalog_row_id,image_url,position,is_primary
			 FROM %i
			 WHERE product_id=%d AND status='active'
			 ORDER BY is_primary DESC,position ASC,id ASC",
			$table,
			absint( $product_id )
		),
		ARRAY_A
	);
}

/* Native local gettext catalog fallback (#2062). */
add_action( 'init', static function () {
    $domain = 'taxocommerce-suppliers';
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
