<?php
/**
 * Plugin Name: TaxoCommerce Scrappers
 * Plugin URI: https://asociacionfocazul.com/taxocommerce-scrappers/
 * Description: External market and product research using the legacy Ojeador and Ingeniero engines with shared SerpApi and ScraperAPI connections.
 * Version: 0.1.20
 * Release Date: 2026-11-10
 * Requires at least: 6.5
 * Requires PHP: 7.4
 * Author: David Perez
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: taxocommerce-scrappers
 * Domain Path: /languages
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/includes/admin-branding.php';
require_once __DIR__ . '/includes/class-taxocommerce-autotest-code.php';
\TaxoCommerce_Scrappers_Autotest_Code::boot();


define( 'TAXOCOMMERCE_SCRAPPERS_VERSION', '0.1.20' );
define( 'TAXOCOMMERCE_SCRAPPERS_FILE', __FILE__ );
define( 'TAXOCOMMERCE_SCRAPPERS_PATH', plugin_dir_path( __FILE__ ) );
define( 'TAXOCOMMERCE_SCRAPPERS_URL', plugin_dir_url( __FILE__ ) );

require_once TAXOCOMMERCE_SCRAPPERS_PATH . 'includes/class-taxocommerce-scrappers-auto-test.php';
require_once TAXOCOMMERCE_SCRAPPERS_PATH . 'includes/class-taxocommerce-scrappers-manual.php';

// During the migration the historical monolith may still be active. Reuse its
// already-loaded classes instead of declaring the same legacy class names twice.
if ( ! class_exists( 'SEO_Ojeador_Admin', false ) ) {
    require_once TAXOCOMMERCE_SCRAPPERS_PATH . 'includes/ojeador/ojeador-bootstrap.php';
}
if ( ! class_exists( 'SEO_Ingeniero', false ) ) {
    if ( ! interface_exists( 'SEO_Managed_Service_Process', false ) ) {
        require_once TAXOCOMMERCE_SCRAPPERS_PATH . 'includes/class-seo-managed-service-process.php';
    }
    require_once TAXOCOMMERCE_SCRAPPERS_PATH . 'includes/ingeniero/ingeniero-bootstrap.php';
}

final class TaxoCommerce_Scrappers {
    public static function boot() {
        // The standalone Ingeniero bootstrap does not initialize its admin
        // controller. Do it here only when its actions have not been registered.
        if ( class_exists( 'SEO_Ingeniero_Admin' ) && ! has_action( 'admin_post_seo_ingeniero_prepare', array( 'SEO_Ingeniero_Admin', 'handle_prepare' ) ) ) {
            SEO_Ingeniero_Admin::init();
        }

        add_action( 'admin_menu', array( __CLASS__, 'menu' ), 65 );
        add_action( 'taxocommerce_core_register_modules', array( __CLASS__, 'register_core' ) );

        if ( function_exists( 'taxocommerce_core' ) && did_action( 'taxocommerce_core_loaded' ) ) {
            self::register_core( taxocommerce_core() );
        }
    }

    public static function activate() {
        if ( class_exists( 'SEO_Ojeador_DB' ) ) {
            SEO_Ojeador_DB::maybe_install();
        }
        if ( class_exists( 'SEO_Ingeniero_DB' ) ) {
            SEO_Ingeniero_DB::maybe_install();
        }
    }

    public static function menu() {
        if ( function_exists( 'taxocommerce_core' ) ) {
            add_submenu_page(
                'taxocommerce-core',
                __( 'TaxoCommerce Scrappers', 'taxocommerce-scrappers' ),
                __( 'Scrappers', 'taxocommerce-scrappers' ),
                'manage_options',
                'taxocommerce-scrappers',
                array( __CLASS__, 'screen' )
            );
            return;
        }

        add_menu_page(
            __( 'TaxoCommerce Scrappers', 'taxocommerce-scrappers' ),
            __( 'Scrappers', 'taxocommerce-scrappers' ),
            'manage_options',
            'taxocommerce-scrappers',
            array( __CLASS__, 'screen' ),
            'dashicons-search',
            63
        );
    }

    public static function register_core( $core ) {
        if ( ! is_object( $core ) || ! method_exists( $core, 'register_module' ) ) {
            return;
        }

        // Registration can arrive from Core at plugins_loaded. Neither the
        // module name nor its description may be translated until init.
        if ( ! did_action( 'init' ) ) {
            add_action( 'init', static function () use ( $core ) {
                self::register_core( $core );
            }, 11 );
            return;
        }

        $core->register_module(
            'scrappers',
            array(
                'name'        => __( 'TaxoCommerce Scrappers', 'taxocommerce-scrappers' ),
                'version'     => TAXOCOMMERCE_SCRAPPERS_VERSION,
                'status'      => 'active',
                'description' => __( 'SerpApi/ScraperAPI connections, market price intelligence with Ojeador, and technical research with Ingeniero.', 'taxocommerce-scrappers' ),
                'admin_url'   => admin_url( 'admin.php?page=taxocommerce-scrappers' ),
            )
        );
    }

    private static function tab() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only navigation.
        $tab = isset( $_GET['tcs_tab'] ) ? sanitize_key( wp_unslash( $_GET['tcs_tab'] ) ) : '';

        // Legacy Ojeador links only carry ojeador_tab. Keep them inside this plugin.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only navigation.
        if ( '' === $tab && isset( $_GET['ojeador_tab'] ) ) {
            $tab = 'ojeador';
        }

        // Legacy Ingeniero links only carry tab=research/editorial/data/tests.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only navigation.
        if ( '' === $tab && isset( $_GET['tab'] ) ) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only navigation/filter parameter, sanitized by this handler; no state-changing action.
            $legacy = sanitize_key( wp_unslash( $_GET['tab'] ) );
            if ( in_array( $legacy, array( 'research', 'editorial', 'data', 'tests' ), true ) ) {
                $tab = 'ingeniero';
            }
        }

        return in_array( $tab, array( 'connections', 'ojeador', 'ingeniero', 'autotest', 'manual' ), true ) ? $tab : 'connections';
    }

    public static function screen() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to manage TaxoCommerce Scrappers.', 'taxocommerce-scrappers' ) );
        }

        $tab = self::tab();

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'TaxoCommerce Scrappers', 'taxocommerce-scrappers' ) . '</h1>';
        echo '<p>' . esc_html__( 'External research layer. Ojeador and Ingeniero reuse the proven legacy engines while SerpApi and ScraperAPI are configured once for both services.', 'taxocommerce-scrappers' ) . '</p>';
        echo '<nav class="nav-tab-wrapper" aria-label="' . esc_attr__( 'TaxoCommerce Scrappers', 'taxocommerce-scrappers' ) . '">';
        foreach ( array(
            'connections' => __( 'Connections', 'taxocommerce-scrappers' ),
            'ojeador'     => __( 'Ojeador', 'taxocommerce-scrappers' ),
            'ingeniero'   => __( 'Ingeniero', 'taxocommerce-scrappers' ),
            'autotest'   => __( 'Autocheck', 'taxocommerce-scrappers' ),
            'manual'     => __( 'Manual', 'taxocommerce-scrappers' ),
        ) as $key => $label ) {
            $url = add_query_arg(
                array(
                    'page'    => 'taxocommerce-scrappers',
                    'tcs_tab' => $key,
                ),
                admin_url( 'admin.php' )
            );
            echo '<a class="nav-tab ' . ( $tab === $key ? 'nav-tab-active' : '' ) . '" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
        }
        echo '</nav>';
        echo '</div>';

        if ( 'connections' === $tab ) {
            if ( class_exists( 'SEO_Ojeador_Admin' ) && is_callable( array( 'SEO_Ojeador_Admin', 'render_shared_google_connections' ) ) ) {
                SEO_Ojeador_Admin::render_shared_google_connections();
            }
            return;
        }

        if ( 'ojeador' === $tab ) {
            SEO_Ojeador_Admin::render();
            return;
        }

        if ( 'autotest' === $tab ) {
            TaxoCommerce_Scrappers_Auto_Test::render();
            return;
        }

        if ( 'manual' === $tab ) {
            TaxoCommerce_Scrappers_Manual::render();
            return;
        }

        SEO_Ingeniero_Admin::render();
    }
    /**
     * Stop this plugin's pending background jobs, without deleting store data.
     *
     * WordPress does not automatically cancel scheduled events on plugin
     * deactivation. Keep historical option keys and tables for reactivation.
     *
     * @return void
     */
    public static function deactivate() {
        // During a temporary coexistence, both implementations use these
        // historic hook names. Never cancel another active plugin's jobs.
        // This optional compatibility check is not required for clean installs.
        if ( function_exists( 'is_plugin_active' ) && is_plugin_active( 'seo-taxonomy/seo-taxonomy.php' ) ) {
            return;
        }

        $hooks = array(
            'seo_ojeador_market_tick',
            'seo_ingeniero_worker_tick',
            'seo_ingeniero_editorial_refresh',
            'seo_ingeniero_editorial_refresh_step',
            'seo_ingeniero_external_radar_tick',
        );
        foreach ( $hooks as $hook ) {
            wp_clear_scheduled_hook( $hook );
        }

        // The Ingeniero worker uses this exact Action Scheduler group.
        // Do not cancel unrelated WooCommerce or TaxoCommerce jobs.
        if ( function_exists( 'as_unschedule_all_actions' ) ) {
            as_unschedule_all_actions( 'seo_ingeniero_worker_tick', array(), 'seo-ingeniero' );
        }
    }

}

register_activation_hook( __FILE__, array( 'TaxoCommerce_Scrappers', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'TaxoCommerce_Scrappers', 'deactivate' ) );
add_action( 'plugins_loaded', array( 'TaxoCommerce_Scrappers', 'boot' ), 100 );

/* Native local gettext catalog fallback (#2062). */
add_action( 'init', static function () {
    $domain = 'taxocommerce-scrappers';
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
