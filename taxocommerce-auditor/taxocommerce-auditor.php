<?php
/**
 * Plugin Name: TaxoCommerce Auditor - Content Quality for WooCommerce
 * Plugin URI: https://asociacionfocazul.com/taxocommerce-auditor/
 * Description: Read-only WordPress and WooCommerce content quality audits with detailed KPIs and full JSON exports.
 * Version: 0.3.20
 * Release Date: 2026-11-10
 * Requires at least: 6.5
 * Requires PHP: 7.4
 * Author: David Perez
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: taxocommerce-auditor
 * Domain Path: /languages
 */

namespace TaxoCommerce\Auditor;

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/includes/admin-branding.php';
require_once __DIR__ . '/includes/class-taxocommerce-autotest-code.php';
\TaxoCommerce_Auditor_Autotest_Code::boot();

require_once __DIR__ . '/includes/class-rules.php';
require_once __DIR__ . '/includes/class-editorial-specialist.php';
require_once __DIR__ . '/includes/class-category-review.php';
require_once __DIR__ . '/includes/class-priority.php';
require_once __DIR__ . '/includes/class-faq-source.php';
require_once __DIR__ . '/includes/class-export-store.php';
require_once __DIR__ . '/includes/class-decision-context.php';
require_once __DIR__ . '/includes/class-runner.php';
require_once __DIR__ . '/includes/class-workload.php';
require_once __DIR__ . '/includes/class-admin.php';
require_once __DIR__ . '/includes/class-manual.php';
require_once __DIR__ . '/includes/class-auto-test.php';

/**
 * Standalone entry point. No SEO_SYSTEM_* globals or legacy source files.
 */
final class Plugin {
    const VERSION = '0.3.20';
    const MENU_SLUG = 'taxocommerce-auditor';

    public static function boot() {
        Runner::register();
        Admin::register();
        Auto_Test::register();

        add_action( 'taxocommerce_core_register_modules', array( __CLASS__, 'register_core_module' ) );
        if ( function_exists( 'taxocommerce_core' ) && did_action( 'taxocommerce_core_loaded' ) ) {
            self::register_core_module( \taxocommerce_core() );
        }
    }

    public static function register_core_module( $core ) {
        if ( is_object( $core ) && method_exists( $core, 'register_module' ) ) {
            // Core can request module metadata before init. Translate only
            // after the catalog is ready; keep the module registration intact.
            if ( ! did_action( 'init' ) ) {
                add_action( 'init', static function () use ( $core ) {
                    self::register_core_module( $core );
                }, 11 );
                return;
            }

            $core->register_module(
                'auditor',
                array(
                    'name'        => 'TaxoCommerce Auditor',
                    'version'     => self::VERSION,
                    'status'      => 'active',
                    'description' => __( 'Auditoria de calidad editorial por bloques, solo lectura.', 'taxocommerce-auditor' ),
                    'admin_url'   => admin_url( 'admin.php?page=' . self::MENU_SLUG ),
                )
            );
        }
    }
}

add_action( 'plugins_loaded', array( __NAMESPACE__ . '\\Plugin', 'boot' ), 30 );
register_deactivation_hook( __FILE__, array( __NAMESPACE__ . '\\Runner', 'deactivate' ) );

/* Native local gettext catalog fallback (#2062). */
add_action( 'init', static function () {
    $domain = 'taxocommerce-auditor';
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
