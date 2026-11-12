<?php
/**
 * Plugin Name: TaxoCommerce Images - Image Management for WooCommerce
 * Plugin URI: https://asociacionfocazul.com/taxocommerce-images/
 * Description: Gestiona, audita y optimiza imágenes de WordPress/WooCommerce manteniendo Media como fuente de verdad.
 * Version: 0.2.28
 * Release Date: 2026-11-10
 * Requires at least: 6.5
 * Requires PHP: 7.4
 * Author: David Perez
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: taxocommerce-images
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/includes/admin-branding.php';

define( 'TAXOCOMMERCE_IMAGES_VERSION', '0.2.28' );
define( 'TAXOCOMMERCE_IMAGES_FILE', __FILE__ );
define( 'TAXOCOMMERCE_IMAGES_PATH', plugin_dir_path( __FILE__ ) );
define( 'TAXOCOMMERCE_IMAGES_BASENAME', plugin_basename( __FILE__ ) );

require_once TAXOCOMMERCE_IMAGES_PATH . 'includes/legacy-reference-audit.php';
require_once TAXOCOMMERCE_IMAGES_PATH . 'includes/legacy-optimizer-engine.php';
require_once TAXOCOMMERCE_IMAGES_PATH . 'includes/legacy-webp-engine.php';
require_once TAXOCOMMERCE_IMAGES_PATH . 'includes/class-taxocommerce-images-inventory.php';
require_once TAXOCOMMERCE_IMAGES_PATH . 'includes/class-taxocommerce-images-load.php';
require_once TAXOCOMMERCE_IMAGES_PATH . 'includes/legacy-optimizer-ajax.php';
require_once TAXOCOMMERCE_IMAGES_PATH . 'includes/class-taxocommerce-images-audit.php';
require_once TAXOCOMMERCE_IMAGES_PATH . 'includes/class-taxocommerce-images-actions.php';
require_once TAXOCOMMERCE_IMAGES_PATH . 'includes/class-taxocommerce-images-thumbnails.php';
require_once TAXOCOMMERCE_IMAGES_PATH . 'includes/class-taxocommerce-images-assignment.php';
require_once TAXOCOMMERCE_IMAGES_PATH . 'includes/class-taxocommerce-images-background.php';
require_once TAXOCOMMERCE_IMAGES_PATH . 'includes/class-taxocommerce-images-auto-test.php';
require_once TAXOCOMMERCE_IMAGES_PATH . 'includes/class-taxocommerce-autotest-code.php';
TaxoCommerce_Images_Autotest_Code::boot();
require_once TAXOCOMMERCE_IMAGES_PATH . 'includes/class-taxocommerce-images-manual.php';
require_once TAXOCOMMERCE_IMAGES_PATH . 'includes/class-taxocommerce-images.php';
require_once TAXOCOMMERCE_IMAGES_PATH . 'includes/class-taxocommerce-images-maintenance.php';

TaxoCommerce_Images_Thumbnails::boot();
TaxoCommerce_Images_Assignment::boot();
TaxoCommerce_Images_Maintenance::boot();
TaxoCommerce_Images_Background::boot();
TaxoCommerce_Images::instance()->boot();
TaxoCommerce_Images_Auto_Test::register_hooks();

register_activation_hook(
	__FILE__,
	static function () {
		update_option( 'taxocommerce_images_version', TAXOCOMMERCE_IMAGES_VERSION, false );
		if ( false === get_option( TaxoCommerce_Images_Actions::SETTINGS_OPTION, false ) ) {
			add_option( TaxoCommerce_Images_Actions::SETTINGS_OPTION, TaxoCommerce_Images_Actions::default_settings(), '', false );
		}
	}
);

/* Native local gettext catalog fallback (#2062). */
add_action( 'init', static function () {
    $domain = 'taxocommerce-images';
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
