<?php
/**
 * Plugin Name: TaxoCommerce Database
 * Plugin URI: https://asociacionfocazul.com/taxocommerce-database/
 * Description: Original Clean Database and SEO Data Table migrated into one independent plugin, without Data Layer.
 * Version: 0.4.19
 * Release Date: 2026-11-10
 * Requires at least: 6.5
 * Requires PHP: 7.4
 * Author: David Perez
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: taxocommerce-database
 * Domain Path: /languages
 */
defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/includes/admin-branding.php';
require_once __DIR__ . '/includes/class-taxocommerce-autotest-code.php';
\TaxoCommerce_Database_Autotest_Code::boot();


define( 'TAXOCOMMERCE_DATABASE_VERSION', '0.4.19' );
define( 'TAXOCOMMERCE_DATABASE_FILE', __FILE__ );
define( 'TAXOCOMMERCE_DATABASE_PATH', plugin_dir_path( __FILE__ ) );
define( 'TAXOCOMMERCE_DATABASE_URL', plugin_dir_url( __FILE__ ) );

// The only two original Database services. Namespacing prevents collisions
// with the former monolith while keeping their independent functionality.
require_once TAXOCOMMERCE_DATABASE_PATH . 'includes/legacy/seo-database-clean.php';
require_once TAXOCOMMERCE_DATABASE_PATH . 'includes/legacy/seo-database-report.php';
require_once TAXOCOMMERCE_DATABASE_PATH . 'includes/class-tcdb-manual.php';
require_once TAXOCOMMERCE_DATABASE_PATH . 'includes/class-tcdb-auto-test.php';

final class TaxoCommerce_Database {
    public static function boot() {
        add_action( 'admin_menu', array( __CLASS__, 'menu' ), 60 );
        add_action( 'taxocommerce_core_register_modules', array( __CLASS__, 'register_core' ) );
        if ( function_exists( 'taxocommerce_core' ) && did_action( 'taxocommerce_core_loaded' ) ) {
            self::register_core( taxocommerce_core() );
        }
    }

    public static function render() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'No tienes permisos para esta herramienta.', 'taxocommerce-database' ) );
        }
        // Read-only tab routing; writes remain nonce-protected in the original services.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Navigation only.
        $screen = isset( $_GET['tcdb_screen'] ) && is_string( $_GET['tcdb_screen'] )
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin navigation and filtering; GET cannot change data.
            ? sanitize_key( wp_unslash( $_GET['tcdb_screen'] ) ) : 'clean';
        if ( ! in_array( $screen, array( 'clean', 'table', 'manual', 'auto-test' ), true ) ) {
            $screen = 'clean';
        }
        // Existing routing parameters remain unchanged for filters, POST actions and export links.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only tab selector.
        $clean_tab = isset( $_GET['seo_clean_db_tab'] ) && is_string( $_GET['seo_clean_db_tab'] )
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin navigation and filtering; GET cannot change data.
            ? sanitize_key( wp_unslash( $_GET['seo_clean_db_tab'] ) ) : 'data_errors';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only tab selector.
        $table_section = isset( $_GET['section'] ) && is_string( $_GET['section'] )
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin navigation and filtering; GET cannot change data.
            ? sanitize_key( wp_unslash( $_GET['section'] ) ) : 'overview';
        // The old table export route remains an alias for existing bookmarks.
        if ( 'table' === $screen && 'export' === $table_section ) {
            $screen = 'clean';
            $clean_tab = 'export';
        }
        $tabs = array(
            'clean:data_errors'     => array( __( 'Errores en datos', 'taxocommerce-database' ), 'clean', 'seo_clean_db_tab', 'data_errors' ),
            'clean:database_cleanup'=> array( __( 'Limpieza BBDD', 'taxocommerce-database' ), 'clean', 'seo_clean_db_tab', 'database_cleanup' ),
            'clean:export'          => array( __( 'Exportar BBDD', 'taxocommerce-database' ), 'clean', 'seo_clean_db_tab', 'export' ),
            'table:overview'        => array( __( 'Resumen tablas', 'taxocommerce-database' ), 'table', 'section', 'overview' ),
            'table:explorer'        => array( __( 'Explorar tablas', 'taxocommerce-database' ), 'table', 'section', 'explorer' ),
            'manual'                => array( __( 'Manual', 'taxocommerce-database' ), 'manual', 'section', 'manual' ),
            'auto-test'             => array( __( 'Autocheck', 'taxocommerce-database' ), 'auto-test', 'section', 'auto-test' ),
        );
        $active = in_array( $screen, array( 'manual', 'auto-test' ), true ) ? $screen : ( 'table' === $screen ? 'table:' . $table_section : 'clean:' . $clean_tab );
        if ( ! isset( $tabs[ $active ] ) ) {
            $active = 'table' === $screen ? 'table:overview' : 'clean:data_errors';
        }
        $base = admin_url( 'admin.php?page=taxocommerce-database' );
        echo '<div class="wrap">' . '<h1>' . esc_html__( 'TaxoCommerce Database', 'taxocommerce-database' ) . '</h1>';
        echo '<style>.tcdb-flat-tabs{display:flex;flex-wrap:nowrap;overflow-x:auto;overflow-y:hidden;white-space:nowrap;gap:2px;max-width:100%;scrollbar-width:thin}.tcdb-flat-tabs .nav-tab{flex:0 0 auto;margin-left:0}@media(max-width:782px){.tcdb-flat-tabs{padding-bottom:4px}.tcdb-flat-tabs .nav-tab{font-size:13px;padding:9px 11px}}</style>';
        echo '<nav class="nav-tab-wrapper tcdb-flat-tabs" aria-label="Herramientas de base de datos">';
        foreach ( $tabs as $key => $tab ) {
            $url = add_query_arg( array( 'tcdb_screen' => $tab[1], $tab[2] => $tab[3] ), $base );
            echo '<a class="nav-tab' . ( $active === $key ? ' nav-tab-active' : '' ) . '" href="' . esc_url( $url ) . '"' . ( $active === $key ? ' aria-current="page"' : '' ) . '>' . esc_html( $tab[0] ) . '</a>';
        }
        echo '</nav></div>';
        if ( 'auto-test' === $screen ) {
            TCDB_Auto_Test::render();
        } elseif ( 'manual' === $screen ) {
            TCDB_Manual::render();
        } elseif ( 'clean' === $screen && 'export' === $clean_tab ) {
            // Preserve both existing export engines, but display their actions together.
            // The original admin-post handlers and nonce-protected SQL URLs do not change.
            echo '<div class="wrap seo-clean-db-wrap">';
            \TCDB_Legacy\seo_data_render_styles();
            \TCDB_Legacy\seo_clean_db_render_export_tab();
            \TCDB_Legacy\seo_data_render_export( \TCDB_Legacy\seo_data_get_tables() );
            echo '</div>';
        } elseif ( 'table' === $screen ) {
            \TCDB_Legacy\seo_data_table_page();
        } else {
            \TCDB_Legacy\seo_clean_db_page();
        }
    }

    public static function menu() {
        if ( function_exists( 'taxocommerce_core' ) ) {
            add_submenu_page(
                'taxocommerce-core', __( 'TaxoCommerce Database', 'taxocommerce-database' ), __( 'Database', 'taxocommerce-database' ),
                'manage_options', 'taxocommerce-database', array( __CLASS__, 'render' )
            );
        } else {
            add_menu_page(
                __( 'TaxoCommerce Database', 'taxocommerce-database' ), __( 'TaxoCommerce DB', 'taxocommerce-database' ), 'manage_options',
                'taxocommerce-database', array( __CLASS__, 'render' ),
                'dashicons-database', 63
            );
        }
    }

    public static function register_core( $core ) {
        if ( is_object( $core ) && method_exists( $core, 'register_module' ) ) {
            // WordPress 6.7+ warns when gettext is called in plugins_loaded.
            // Postpone only the Core presentation metadata until init.
            if ( ! did_action( 'init' ) ) {
                add_action( 'init', static function () use ( $core ) {
                    self::register_core( $core );
                }, 11 );
                return;
            }

            $core->register_module( 'database', array(
                'name' => 'TaxoCommerce Database',
                'version' => TAXOCOMMERCE_DATABASE_VERSION,
                'status' => 'active',
                'description' => __( 'Clean Database y SEO Data Table originales, sin Data Layer.', 'taxocommerce-database' ),
                'admin_url' => admin_url( 'admin.php?page=taxocommerce-database' ),
            ) );
        }
    }
}
add_action( 'plugins_loaded', array( 'TaxoCommerce_Database', 'boot' ), 40 );

/* Native local gettext catalog fallback (#2062). */
add_action( 'init', static function () {
    $domain = 'taxocommerce-database';
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
