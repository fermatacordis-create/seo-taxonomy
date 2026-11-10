<?php
/**
 * Plugin Name: TaxoCommerce Social
 * Plugin URI: https://asociacionfocazul.com/taxocommerce-social/
 * Description: Social network connections, publishing templates and calendar for the TaxoCommerce family.
 * Version: 0.1.14
 * Release Date: 2026-11-10
 * Requires at least: 6.7
 * Requires PHP: 7.4
 * Author: David Perez
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: taxocommerce-social
 * Domain Path: /languages
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/includes/admin-branding.php';
require_once __DIR__ . '/includes/class-taxocommerce-autotest-code.php';
\TaxoCommerce_Social_Autotest_Code::boot();

define( 'TAXOCOMMERCE_SOCIAL_VERSION', '0.1.14' );
define( 'TAXOCOMMERCE_SOCIAL_PATH', plugin_dir_path( __FILE__ ) );
define( 'TAXOCOMMERCE_SOCIAL_URL', plugin_dir_url( __FILE__ ) );

require_once TAXOCOMMERCE_SOCIAL_PATH . 'includes/core.php';
require_once TAXOCOMMERCE_SOCIAL_PATH . 'includes/facebook.php';
require_once TAXOCOMMERCE_SOCIAL_PATH . 'includes/instagram.php';
require_once TAXOCOMMERCE_SOCIAL_PATH . 'includes/linkedin.php';
require_once TAXOCOMMERCE_SOCIAL_PATH . 'includes/pinterest.php';
require_once TAXOCOMMERCE_SOCIAL_PATH . 'includes/x.php';
require_once TAXOCOMMERCE_SOCIAL_PATH . 'includes/programador.php';
require_once TAXOCOMMERCE_SOCIAL_PATH . 'includes/calendar.php';
require_once TAXOCOMMERCE_SOCIAL_PATH . 'includes/admin.php';
require_once TAXOCOMMERCE_SOCIAL_PATH . 'includes/class-manual.php';
require_once TAXOCOMMERCE_SOCIAL_PATH . 'includes/class-auto-test.php';

register_activation_hook(
    __FILE__,
    static function () {
        if ( function_exists( 'tcsocial_network_maybe_install_tables' ) ) {
            tcsocial_network_maybe_install_tables();
        }
        if ( function_exists( 'tcsocial_programador_maybe_install_table' ) ) {
            tcsocial_programador_maybe_install_table();
        }
    }
);

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Punto de extension publico prefijado con taxocommerce_social_; su nombre conserva compatibilidad con Core.
function taxocommerce_social_register_with_core( $core ) {
    if ( ! is_object( $core ) || ! method_exists( $core, 'register_module' ) ) {
        return;
    }

    $core->register_module(
        'social',
        array(
            'name'        => 'TaxoCommerce Social',
            'version'     => TAXOCOMMERCE_SOCIAL_VERSION,
            'status'      => 'active',
            'description' => 'Connections, templates and a standalone social publishing calendar.',
            'admin_url'   => admin_url( 'admin.php?page=taxocommerce-social' ),
        )
    );
}
add_action( 'taxocommerce_core_register_modules', 'taxocommerce_social_register_with_core' );

if ( function_exists( 'taxocommerce_core' ) && did_action( 'taxocommerce_core_loaded' ) ) {
    taxocommerce_social_register_with_core( taxocommerce_core() );
}

/* Native local gettext catalog fallback (#2062). */
add_action( 'init', static function () {
    $domain = 'taxocommerce-social';
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
