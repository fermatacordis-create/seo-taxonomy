<?php
/**
 * Plugin Name: TaxoCommerce Insights
 * Plugin URI: https://asociacionfocazul.com/taxocommerce-google-bing/
 * Description: External connections, provider data downloads, and the restored legacy Analysis dashboard.
 * Version: 1.1.49
 * Release Date: 2026-11-11
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Author: David Perez
 * License: GPLv2 or later
 * Text Domain: taxocommerce-google-bing
 * Domain Path: /languages
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/includes/admin-branding.php';
require_once __DIR__ . '/includes/class-taxocommerce-autotest-code.php';
\TaxoCommerce_Insights_Autotest_Code::boot();

define( 'TAXOCOMMERCE_GOOGLE_BING_VERSION', '1.1.49' );
define( 'TAXOCOMMERCE_GOOGLE_BING_FILE', __FILE__ );
define( 'TAXOCOMMERCE_GOOGLE_BING_PATH', plugin_dir_path( __FILE__ ) );
define( 'TAXOCOMMERCE_GOOGLE_BING_URL', plugin_dir_url( __FILE__ ) );

if ( ! defined( 'TAXOCOMMERCE_CONNECTIONS_VERSION' ) ) {
    define( 'TAXOCOMMERCE_CONNECTIONS_VERSION', TAXOCOMMERCE_GOOGLE_BING_VERSION );
}

// Keep the historical class names and option keys so other TaxoCommerce modules
// continue to work after the standalone Connections plugin is disabled.
if ( ! class_exists( 'TaxoCommerce_Connections_Amazon', false ) ) {
    require_once TAXOCOMMERCE_GOOGLE_BING_PATH . 'includes/class-taxocommerce-connections-amazon.php';
}
if ( ! class_exists( 'TaxoCommerce_Connections_Pinterest', false ) ) {
    require_once TAXOCOMMERCE_GOOGLE_BING_PATH . 'includes/class-taxocommerce-connections-pinterest.php';
}
if ( ! class_exists( 'TaxoCommerce_Connections_Google_Bing', false ) ) {
    require_once TAXOCOMMERCE_GOOGLE_BING_PATH . 'includes/class-taxocommerce-connections-google-bing.php';
}
if ( ! class_exists( 'TaxoCommerce_Connections_Admin', false ) ) {
    require_once TAXOCOMMERCE_GOOGLE_BING_PATH . 'includes/class-taxocommerce-connections-admin.php';
}

require_once TAXOCOMMERCE_GOOGLE_BING_PATH . 'includes/class-taxocommerce-google-bing-indexnow.php';
require_once TAXOCOMMERCE_GOOGLE_BING_PATH . 'includes/class-taxocommerce-connections-guide.php';
require_once TAXOCOMMERCE_GOOGLE_BING_PATH . 'includes/class-taxocommerce-google-bing-connections.php';
require_once TAXOCOMMERCE_GOOGLE_BING_PATH . 'includes/class-taxocommerce-google-bing-oauth.php';
require_once TAXOCOMMERCE_GOOGLE_BING_PATH . 'includes/class-taxocommerce-google-bing-gsc.php';
require_once TAXOCOMMERCE_GOOGLE_BING_PATH . 'includes/class-taxocommerce-google-bing-google-service.php';
require_once TAXOCOMMERCE_GOOGLE_BING_PATH . 'includes/class-taxocommerce-google-bing-reports.php';
require_once TAXOCOMMERCE_GOOGLE_BING_PATH . 'includes/class-taxocommerce-google-bing-trends.php';
require_once TAXOCOMMERCE_GOOGLE_BING_PATH . 'includes/class-taxocommerce-google-bing-trends-hierarchy.php';
require_once TAXOCOMMERCE_GOOGLE_BING_PATH . 'includes/class-taxocommerce-connections-status.php';
require_once TAXOCOMMERCE_GOOGLE_BING_PATH . 'includes/class-taxocommerce-google-bing-user-guide.php';
require_once TAXOCOMMERCE_GOOGLE_BING_PATH . 'includes/class-taxocommerce-google-bing-auto-test.php';
require_once TAXOCOMMERCE_GOOGLE_BING_PATH . 'includes/class-taxocommerce-insights-head-tags.php';
require_once TAXOCOMMERCE_GOOGLE_BING_PATH . 'includes/class-taxocommerce-insights-consent.php';
require_once TAXOCOMMERCE_GOOGLE_BING_PATH . 'includes/class-taxocommerce-insights-consent-ledger.php';
require_once TAXOCOMMERCE_GOOGLE_BING_PATH . 'includes/class-taxocommerce-insights-consent-seed.php';
require_once TAXOCOMMERCE_GOOGLE_BING_PATH . 'includes/class-taxocommerce-insights-ecommerce.php';
require_once TAXOCOMMERCE_GOOGLE_BING_PATH . 'includes/connections/cloudflare.php';
require_once TAXOCOMMERCE_GOOGLE_BING_PATH . 'includes/class-taxocommerce-insights-cloudflare-ui.php';
require_once TAXOCOMMERCE_GOOGLE_BING_PATH . 'includes/ecommerce/ga4-events.php';
require_once TAXOCOMMERCE_GOOGLE_BING_PATH . 'includes/analysis/analista-bootstrap.php';
require_once TAXOCOMMERCE_GOOGLE_BING_PATH . 'includes/analysis/analista-geo-bridge.php';

function taxocommerce_google_bing_boot() {
    TaxoCommerce_Google_Bing_Connections::register();
    TaxoCommerce_Google_Bing_OAuth::register();

    // The historical Google Intelligence tables feed Analysis. Restore their
    // manual importer only when no other plugin already owns these functions.
    // Credentials are read from modern Connections without rewriting them.
    if ( ! function_exists( 'seo_google_get_settings' ) ) {
        require_once TAXOCOMMERCE_GOOGLE_BING_PATH . 'includes/class-taxocommerce-google-bing-gsc-sync-bridge.php';
        add_filter( 'taxocommerce_google_bing_gsc_sync_settings', array( 'TaxoCommerce_Google_Bing_GSC_Sync_Bridge', 'settings' ) );
        add_filter( 'taxocommerce_google_bing_gsc_sync_status', array( 'TaxoCommerce_Google_Bing_GSC_Sync_Bridge', 'status' ) );
        add_filter( 'taxocommerce_google_bing_gsc_sync_access_token', array( 'TaxoCommerce_Google_Bing_GSC_Sync_Bridge', 'access_token' ) );
        add_filter( 'taxocommerce_google_bing_gsc_sync_verify_start', array( 'TaxoCommerce_Google_Bing_GSC_Sync_Bridge', 'verify_and_select' ) );
        add_action( 'taxocommerce_google_bing_gsc_sync_completed', array( 'TaxoCommerce_Google_Bing_GSC_Sync_Bridge', 'record_completed' ) );
        require_once TAXOCOMMERCE_GOOGLE_BING_PATH . 'includes/google/seo-google-info.php';
    }

    // Connections now belongs to Insights. These boot methods preserve the
    // historical public helpers and admin-post contracts for Amazon/Pinterest.
    TaxoCommerce_Connections_Amazon::boot();
    TaxoCommerce_Connections_Pinterest::boot();
    TaxoCommerce_Connections_Google_Bing::boot();
    TaxoCommerce_Insights_IndexNow::register();
    TaxoCommerce_Connections_Admin::boot();
    TaxoCommerce_Connections_Status::register();
    TaxoCommerce_Google_Bing_Reports::register();
    TaxoCommerce_Google_Bing_Trends::register();
    TaxoCommerce_Google_Bing_User_Guide::register();
    TaxoCommerce_Google_Bing_Auto_Test::register();
    TaxoCommerce_Insights_Head_Tags::register();
    TaxoCommerce_Insights_Consent::register();
    TaxoCommerce_Insights_Consent_Ledger::register();
    TaxoCommerce_Insights_Consent_Seed::register();
    TaxoCommerce_Insights_Ecommerce::register();
    TaxoCommerce_Insights_Cloudflare_UI::register();

    add_action( 'taxocommerce_core_register_modules', 'taxocommerce_google_bing_register_with_core' );
    if ( function_exists( 'taxocommerce_core' ) && did_action( 'taxocommerce_core_loaded' ) ) {
        taxocommerce_google_bing_register_with_core( taxocommerce_core() );
    }

    add_action( 'admin_menu', 'taxocommerce_google_bing_register_menu', 65 );
}
add_action( 'plugins_loaded', 'taxocommerce_google_bing_boot', 100 );
register_activation_hook( __FILE__, array( 'TaxoCommerce_Insights_Consent', 'install' ) );
register_activation_hook( __FILE__, array( 'TaxoCommerce_Insights_Consent_Ledger', 'install' ) );

function taxocommerce_google_bing_register_with_core( $core ) {
    if ( ! is_object( $core ) || ! method_exists( $core, 'register_module' ) ) {
        return;
    }

    $core->register_module(
        'google_bing',
        array(
            'name'        => 'TaxoCommerce Insights',
            'version'     => TAXOCOMMERCE_GOOGLE_BING_VERSION,
            'status'      => 'active',
            'description' => 'External connections, provider data downloads, and the restored legacy Analysis dashboard.',
            'admin_url'   => admin_url( 'admin.php?page=taxocommerce-google-bing' ),
        )
    );
}

function taxocommerce_google_bing_register_menu() {
    $in_core = function_exists( 'taxocommerce_core' ) || defined( 'TAXOCOMMERCE_CORE_VERSION' );

    if ( $in_core ) {
        add_submenu_page(
            'taxocommerce-core',
            __( 'TaxoCommerce Insights', 'taxocommerce-google-bing' ),
            __( 'Insights', 'taxocommerce-google-bing' ),
            'manage_options',
            'taxocommerce-google-bing',
            'taxocommerce_google_bing_screen'
        );
        return;
    }

    add_menu_page(
        __( 'TaxoCommerce Insights', 'taxocommerce-google-bing' ),
        __( 'Insights', 'taxocommerce-google-bing' ),
        'manage_options',
        'taxocommerce-google-bing',
        'taxocommerce_google_bing_screen',
        'dashicons-chart-area',
        63
    );
}

function taxocommerce_google_bing_screen() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Unauthorized access.', 'taxocommerce-google-bing' ) );
    }

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only tab selector.
    $tab = isset( $_GET['tgb_tab'] ) ? sanitize_key( wp_unslash( $_GET['tgb_tab'] ) ) : 'connections';
    if ( 'reports' === $tab || 'analytics' === $tab ) {
        $tab = 'google_analytics';
    } elseif ( 'search' === $tab ) {
        $tab = 'google_search';
    }
    if ( ! in_array( $tab, array( 'connections', 'analysis', 'google_search', 'google_analytics', 'bing', 'google_trends', 'head_tags', 'ecommerce', 'consent', 'user_guide', 'auto_test' ), true ) ) {
        $tab = 'connections';
    }

    echo '<div class="wrap tgb-wrap">';
    echo '<h1>' . esc_html__( 'TaxoCommerce Insights', 'taxocommerce-google-bing' ) . '</h1>';
    echo '<nav class="nav-tab-wrapper" aria-label="TaxoCommerce Insights">';
    foreach (
        array(
            'connections'      => 'Connections',
            'analysis'         => 'Analysis',
            'google_search'    => 'Google Search',
            'google_analytics' => 'Google Analytics',
            'bing'             => 'Bing',
            'google_trends'    => 'Google Trends',
            'head_tags'        => __( 'Etiquetas de cabecera', 'taxocommerce-google-bing' ),
            'ecommerce'        => __( 'Comercio electrónico', 'taxocommerce-google-bing' ),
            'consent'          => __( 'Consentimientos', 'taxocommerce-google-bing' ),
            'user_guide'       => 'User Guide',
            'auto_test'        => __( 'Autocheck', 'taxocommerce-google-bing' ),
        ) as $key => $label
    ) {
        $url = add_query_arg(
            array(
                'page'    => 'taxocommerce-google-bing',
                'tgb_tab' => $key,
            ),
            admin_url( 'admin.php' )
        );
        echo '<a class="nav-tab ' . ( $tab === $key ? 'nav-tab-active' : '' ) . '" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
    }
    echo '</nav>';

    if ( 'connections' === $tab ) {
        wp_enqueue_style( 'tgb-connections-guide', TAXOCOMMERCE_GOOGLE_BING_URL . 'assets/connections-guide.css', array(), TAXOCOMMERCE_GOOGLE_BING_VERSION );
        TaxoCommerce_Connections_Admin::render();
    } elseif ( 'head_tags' === $tab ) {
        TaxoCommerce_Insights_Head_Tags::render();
    } elseif ( 'ecommerce' === $tab ) {
        TaxoCommerce_Insights_Ecommerce::render();
    } elseif ( 'consent' === $tab ) {
        TaxoCommerce_Insights_Consent::render();
    } elseif ( 'auto_test' === $tab ) {
        TaxoCommerce_Google_Bing_Auto_Test::render();
    } elseif ( 'user_guide' === $tab ) {
        TaxoCommerce_Google_Bing_User_Guide::render();
    } elseif ( 'analysis' === $tab ) {
        taxocommerce_google_bing_analysis_screen();
    } elseif ( 'google_trends' === $tab ) {
        TaxoCommerce_Google_Bing_Trends::render();
    } else {
        $source = 'google_search' === $tab ? 'search' : ( 'google_analytics' === $tab ? 'analytics' : 'bing' );
        TaxoCommerce_Google_Bing_Reports::render( $source );
        if ( 'google_search' === $tab && function_exists( 'seo_google_render_sync_status' ) ) {
            echo '<hr><h2>' . esc_html__( 'Sincronización de Search Console para Analysis', 'taxocommerce-google-bing' ) . '</h2>';
            echo '<p>' . esc_html__( 'Actualiza las tablas históricas que utiliza Analysis. Es independiente de los informes guardados y utiliza las credenciales actuales de Connections.', 'taxocommerce-google-bing' ) . '</p>';
            seo_google_render_sync_status();
        }
    }

    echo '</div>';
}


/**
 * Render the historical Analysis reports from Insights' bundled engine.
 *
 * The included analysis/analista-bootstrap.php owns the report definitions.
 * Legacy function names are retained for stored settings and compatibility;
 * no CSS, JS, or runtime constant is loaded from the former SEO monolith.
 * Heavy reports are only generated after an authenticated AJAX click.
 */
function taxocommerce_google_bing_analysis_screen() {
    // seo_analista_* is a historical API bundled in Insights/analysis.
    // No function or constant from the SEO monolith is required here.
    $analysis_available = function_exists( 'seo_analista_lazy_reports' )
        && function_exists( 'seo_analista_lazy_render_partial' )
        && function_exists( 'taxocommerce_google_bing_analysis_reports' )
        && function_exists( 'taxocommerce_insights_analysis_ajax' );

    if ( ! $analysis_available ) {
        echo '<div class="notice notice-warning inline"><p>'
            . esc_html__( 'El motor de informes incluido en Insights no está disponible. Comprueba los archivos de Insights y el registro de errores.', 'taxocommerce-google-bing' )
            . '</p></div>';
        return;
    }

    // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only reporting-period selector.
    $days = isset( $_GET['analysis_days'] )
        ? absint( wp_unslash( $_GET['analysis_days'] ) )
        : 28;
    // phpcs:enable WordPress.Security.NonceVerification.Recommended
    $days = in_array( $days, array( 28, 60, 90 ), true ) ? $days : 28;

    // Use the Insights-owned AJAX client even when SEO is deactivated.
    $version = TAXOCOMMERCE_GOOGLE_BING_VERSION;
        wp_enqueue_script(
            'tgb-legacy-analysis-js',
            TAXOCOMMERCE_GOOGLE_BING_URL . 'includes/analysis/assets/analista-lazy.js',
            array( 'jquery' ),
            $version,
            true
        );
        wp_add_inline_script(
            'tgb-legacy-analysis-js',
            'window.seoAnalistaLazyConfig = ' . wp_json_encode( array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'taxocommerce_insights_analysis' ),
                'days'    => $days,
                'planDownloadLabel' => __( 'Descargar JSON del plan de acción', 'taxocommerce-google-bing' ),
                'planDownloadUrl' => taxocommerce_insights_plan_json_download_url(),
                'planOpenLabel' => __( 'Abrir JSON del plan (móvil)', 'taxocommerce-google-bing' ),
            ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ) . ';',
            'before'
        );

    echo '<div class="seo-analista-wrap">';
    echo '<div class="seo-analista-toolbar">';
    echo '<div>' . '<h2 style="margin:0 0 4px">' . esc_html__( 'Analysis', 'taxocommerce-google-bing' ) . '</h2>';
    echo '<p style="margin:0;color:#646970">' . esc_html__( 'El Plan de acción reúne recomendaciones y tareas priorizadas; el resto de informes sigue bajo demanda.', 'taxocommerce-google-bing' ) . '</p>' . '</div>';
    echo '<form method="get" action="' . esc_url( admin_url( 'admin.php' ) ) . '">';
    echo '<input type="hidden" name="page" value="taxocommerce-google-bing">';
    echo '<input type="hidden" name="tgb_tab" value="analysis">';
    echo '<label for="tgb-analysis-days">' . '<strong>' . esc_html__( 'Periodo', 'taxocommerce-google-bing' ) . '</strong>' . '</label>';
    echo '<select id="tgb-analysis-days" name="analysis_days">';
    foreach ( array( 28, 60, 90 ) as $option ) {
        echo '<option value="' . esc_attr( (string) $option ) . '" ' . selected( $days, $option, false ) . '>'
            . esc_html( (string) $option ) . ' días</option>';
    }
    echo '</select>' . '<button type="submit" class="button">' . esc_html__( 'Cambiar periodo', 'taxocommerce-google-bing' ) . '</button>';
    if ( function_exists( 'seo_analista_export_json_url' ) ) {
        $json_url = seo_analista_export_json_url( $days );
        echo ' <a class="button" href="' . esc_url( $json_url ) . '">' . esc_html__( 'Descargar JSON completo', 'taxocommerce-google-bing' ) . '</a>';
        echo ' <a class="button" href="' . esc_url( add_query_arg( 'analista_view_json', '1', $json_url ) ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Abrir JSON en el navegador (móvil)', 'taxocommerce-google-bing' ) . '</a>';
    }
    echo '</form></div>';

    // Do not call seo_analista_lazy_render_shell: it would enqueue the same
    // click handler again and issue two AJAX requests per button click.
    $reports = taxocommerce_google_bing_analysis_reports();
    $last_plan = taxocommerce_insights_last_plan_export();
    echo '<div class="seo-analista-lazy-controls seo-analista-toolbar-actions">';
    echo '<button type="button" id="tgb-analysis-run-all" class="button button-primary">' . esc_html__( 'Ejecutar informe completo', 'taxocommerce-google-bing' ) . '</button>';
    echo '<button type="button" id="tgb-analysis-cancel" class="button" disabled>' . esc_html__( 'Detener', 'taxocommerce-google-bing' ) . '</button>';
    echo '</div>';
    echo '<div class="seo-analista-lazy-intro"><strong>Analista '
        . esc_html( defined( 'SEO_ANALISTA_VERSION' ) ? SEO_ANALISTA_VERSION : '' )
        . '</strong> · carga bajo demanda<p>Ejecuta únicamente el informe elegido.</p></div>';
    echo '<div class="seo-analista-lazy-controls seo-analista-report-grid">';
    foreach ( $reports as $key => $report ) {
        $is_plan = 'directrices' === $key;
        $stored = $is_plan ? $last_plan : taxocommerce_insights_last_report_export( $key, $days );
        $saved_date = $stored
            ? wp_date( 'd/m/Y H:i', strtotime( (string) $stored['generated_at'] ) )
            : '';
        $download_url = $is_plan
            ? taxocommerce_insights_plan_json_download_url()
            : taxocommerce_insights_report_json_download_url( $key, $days );

        echo '<article class="seo-analista-report-card" data-report="' . esc_attr( (string) $key ) . '">';
        echo '<h3>' . esc_html( (string) ( $report['label'] ?? '' ) ) . '</h3>';
        echo '<p>' . esc_html( (string) ( $report['description'] ?? '' ) ) . '</p>';
        echo '<div class="seo-analista-report-meta"><strong>' . esc_html__( 'Último informe:', 'taxocommerce-google-bing' )
            . '</strong> <time class="tgb-report-date">' . esc_html( $saved_date ) . '</time></div>';
        echo '<div class="seo-analista-report-actions">';
        echo '<button type="button" class="button button-primary seo-analista-lazy-button" data-report="'
            . esc_attr( (string) $key ) . '">' . esc_html__( 'Crear informe', 'taxocommerce-google-bing' ) . '</button>';
        $download_class = $stored ? 'button tgb-report-json-button' : 'button tgb-report-json-button disabled';
        echo '<a ' . ( $is_plan ? 'id="tgb-plan-json-download" ' : '' ) . 'class="' . esc_attr( $download_class )
            . '" data-report="' . esc_attr( (string) $key ) . '"'
            . ( $stored ? ' href="' . esc_url( $download_url ) . '"' : '' )
            . ' aria-disabled="' . ( $stored ? 'false' : 'true' ) . '">'
            . esc_html__( 'Descargar JSON', 'taxocommerce-google-bing' ) . '</a>';
        echo '</div></article>';
    }
    echo '</div>';
    echo '<div id="seo-analista-lazy-status" class="seo-analista-lazy-status" aria-live="polite"></div>';
    echo '<div id="seo-analista-lazy-result" class="seo-analista-lazy-result">';
    echo '<div class="seo-analista-lazy-empty">' . '<strong>' . esc_html__( 'Ningún informe cargado.', 'taxocommerce-google-bing' ) . '</strong>' . '<p>' . esc_html__( 'Pulsa uno de los botones anteriores para ejecutar solo ese análisis.', 'taxocommerce-google-bing' ) . '</p>' . '</div>';
    echo '</div></div>';
}

/* Native local gettext catalog fallback (#2062). */
add_action( 'init', static function () {
    $domain = 'taxocommerce-google-bing';
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
