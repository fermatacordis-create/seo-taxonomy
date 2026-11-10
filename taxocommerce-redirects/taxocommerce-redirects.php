<?php
/**
 * Plugin Name: TaxoCommerce Redirects - SEO Redirect Manager
 * Plugin URI: https://asociacionfocazul.com/taxocommerce-redirects/
 * Description: 301/302 redirect management and assisted deletion for WordPress and WooCommerce content, with optional TaxoCommerce Core integration.
 * Version: 0.1.18
 * Release Date: 2026-11-10
 * Requires at least: 6.5
 * Requires PHP: 7.4
 * Author: David Perez
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: taxocommerce-redirects
 * Domain Path: /languages
 */

namespace TaxoCommerce\Redirects;

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/includes/admin-branding.php';
require_once __DIR__ . '/includes/class-taxocommerce-autotest-code.php';
\TaxoCommerce_Redirects_Autotest_Code::boot();

require_once __DIR__ . '/includes/class-store.php';
require_once __DIR__ . '/includes/class-lifecycle.php';
require_once __DIR__ . '/includes/class-engine.php';
require_once __DIR__ . '/includes/class-admin.php';
require_once __DIR__ . '/includes/class-auto-test.php';
require_once __DIR__ . '/includes/class-user-guide.php';
require_once __DIR__ . '/includes/class-assisted-delete.php';

final class Plugin {
    const VERSION = '0.1.18';
    const SLUG = 'taxocommerce-redirects';
    const CONFIRM_SLUG = 'taxocommerce-redirects-confirm';

    public static function boot() {
        Engine::register();
        Admin::register();
        Auto_Test::register();
        User_Guide::register();
        Assisted_Delete::register();
        add_action( 'admin_notices', array( __NAMESPACE__ . '\\Lifecycle', 'removal_notice' ) );
        add_action( 'taxocommerce_core_register_modules', array( __CLASS__, 'register_core' ) );

        // Core boots at plugins_loaded priority 20; Redirects boots at 30.
        // Register now if Core already dispatched the registration hook.
        // This is optional: Redirects still works without Core.
        if ( function_exists( 'taxocommerce_core' ) && did_action( 'taxocommerce_core_register_modules' ) ) {
            self::register_core( taxocommerce_core() );
        }
    }

    public static function register_core( $core ) {
        if ( is_object( $core ) && method_exists( $core, 'register_module' ) ) {
            $core->register_module(
                'redirects',
                array(
                    'name' => 'TaxoCommerce Redirects',
                    'version' => self::VERSION,
                    'status' => 'active',
                    'description' => 'Redirecciones y borrado asistido con el gestor independiente.',
                    'admin_url' => admin_url( 'admin.php?page=' . self::SLUG ),
                )
            );
        }
    }
}

register_activation_hook( __FILE__, array( __NAMESPACE__ . '\Lifecycle', 'activate' ) );
add_action( 'plugins_loaded', array( __NAMESPACE__ . '\Plugin', 'boot' ), 30 );

/* Native local gettext catalog fallback (#2062). */
add_action( 'init', static function () {
    $domain = 'taxocommerce-redirects';
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
