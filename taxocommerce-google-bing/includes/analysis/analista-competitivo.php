<?php
/**
 * Market competition intelligence from official, read-only provider reports.
 *
 * No scraping, external SERP subscriptions or scans of competing domains.
 * Existing saved reports and export endpoint are retained for compatibility.
 *
 * @package TaxoCommerce_Insights
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'SEO_ANALISTA_COMPETITIVE_REPORT_OPTION' ) ) {
    define( 'SEO_ANALISTA_COMPETITIVE_REPORT_OPTION', 'seo_analista_competitive_report_v1' );
}
if ( ! defined( 'SEO_ANALISTA_COMPETITIVE_REPORT_HISTORY_OPTION' ) ) {
    define( 'SEO_ANALISTA_COMPETITIVE_REPORT_HISTORY_OPTION', 'seo_analista_competitive_report_history_v1' );
}

/**
 * Turn existing provider snapshots into a report without initiating requests
 * to the websites of competitors.
 *
 * @param int $days Reporting window.
 * @return array
 */
function taxocommerce_insights_competitive_build_report( $days = 28 ) {
    $days = in_array( absint( $days ), array( 28, 90 ), true ) ? absint( $days ) : 28;
    $settings = function_exists( 'seo_analista_get_settings' ) ? (array) seo_analista_get_settings() : array();
    $configured = function_exists( 'seo_analista_sanitize_domains' )
        ? seo_analista_sanitize_domains( (array) ( $settings['competitors'] ?? array() ) )
        : array();
    $data = function_exists( 'seo_analista_get_data' ) ? (array) seo_analista_get_data( $days ) : array();
    $market = function_exists( 'seo_analista_market_signals' ) ? (array) seo_analista_market_signals( 25 ) : array();
    $acceleration = function_exists( 'seo_analista_search_acceleration' )
        ? (array) seo_analista_search_acceleration( $days, 30 )
        : array();
    $bing = function_exists( 'seo_analista_bing_snapshot' ) ? (array) seo_analista_bing_snapshot( $days, 20 ) : array();
    $ga4 = function_exists( 'seo_analista_ga4_snapshot' ) ? (array) seo_analista_ga4_snapshot($days) : array();

    $merchant = class_exists( 'TaxoCommerce_Google_Bing_Merchant', false )
        ? (array) TaxoCommerce_Google_Bing_Merchant::snapshots()
        : array();
    $popular = array_values( (array) ( $merchant['popular']['rows'] ?? array() ) );
    $category_counts = array();
    foreach ( $popular as $product ) {
        if ( ! is_array( $product ) ) {
            continue;
        }
        $category_id = absint( $product['category_id'] ?? 0 );
        if ( $category_id > 0 ) {
            $category_counts[ $category_id ] = ( $category_counts[ $category_id ] ?? 0 ) + 1;
        }
    }
    arsort( $category_counts );
    $categories = array_slice( array_keys( $category_counts ), 0, 3 );

    // This method is a bounded, official Merchant Reports API call, invoked
    // only by the explicit "Generate competitive report" admin action.
    $visibility = array( 'status' => 'unavailable', 'rows' => array(), 'errors' => array() );
    if ( $categories && class_exists( 'TaxoCommerce_Google_Bing_Merchant', false ) &&
        method_exists( 'TaxoCommerce_Google_Bing_Merchant', 'competitive_visibility' ) ) {
        $visibility = (array) TaxoCommerce_Google_Bing_Merchant::competitive_visibility( $categories, $days );
    }
    $competitors = array();
    foreach ( (array) ( $visibility['rows'] ?? array() ) as $row ) {
        $domain = sanitize_text_field( (string) ( $row['domain'] ?? '' ) );
        if ( '' === $domain || ! empty( $row['is_own'] ) ) {
            continue;
        }
        $competitors[] = $row;
    }
    usort( $competitors, static function ( $a, $b ) {
        return (int) ( $a['rank'] ?? 9999 ) <=> (int) ( $b['rank'] ?? 9999 );
    } );

    $missing = array();
    $brands = array();
    foreach ( $popular as $product ) {
        if ( ! is_array( $product ) ) {
            continue;
        }
        $brand = trim( (string) ( $product['brand'] ?? '' ) );
        if ( '' !== $brand ) {
            $brands[ $brand ] = ( $brands[ $brand ] ?? 0 ) + 1;
        }
        if ( 'not_in_inventory' === strtolower( (string) ( $product['inventory_status'] ?? '' ) ) ) {
            $missing[] = $product;
        }
    }
    uasort( $brands, static function ( $a, $b ) { return $b <=> $a; } );
    usort( $missing, static function ( $a, $b ) {
        return (int) ( $a['rank'] ?? 9999 ) <=> (int) ( $b['rank'] ?? 9999 );
    } );
    $opportunities = array();
    foreach ( array_slice( $missing, 0, 15 ) as $row ) {
        $opportunities[] = array(
            'type' => 'product_gap',
            'source' => 'Google Merchant Center · Best Sellers',
            'topic' => (string) ( $row['title'] ?? '' ),
            'brand' => (string) ( $row['brand'] ?? '' ),
            'category' => (string) ( $row['category'] ?? '' ),
            'rank' => absint( $row['rank'] ?? 0 ),
            'recommendation' => 'Comprobar si el producto existe en WooCommerce, si se ofrece en Merchant y si un proveedor puede suministrarlo. No crear artículos automáticamente.',
        );
    }
    foreach ( array_slice( $market, 0, 8 ) as $row ) {
        $topic = trim( (string) ( $row['query'] ?? '' ) );
        if ( '' === $topic ) {
            continue;
        }
        $opportunities[] = array(
            'type' => 'external_trend',
            'source' => 'Google Trends',
            'topic' => $topic,
            'growth' => (float) ( $row['max_growth'] ?? 0 ),
            'recommendation' => 'Evaluar demanda exterior y surtido disponible antes de ampliar una familia.',
        );
    }
    foreach ( array_slice( $acceleration, 0, 12 ) as $row ) {
        $topic = trim( (string) ( $row['query'] ?? '' ) );
        if ( '' === $topic ) {
            continue;
        }
        $opportunities[] = array(
            'type' => 'own_demand',
            'source' => 'Google Search Console · evolución propia',
            'topic' => $topic,
            'impressions' => (int) ( $row['impressions'] ?? 0 ),
            'previous_impressions' => (int) ( $row['previous_impressions'] ?? 0 ),
            'position' => (float) ( $row['position'] ?? 0 ),
            'recommendation' => 'Estudiar si se puede captar más demanda con el surtido y las páginas existentes.',
        );
    }

    $merchant_available = ! empty( $popular );
    $visibility_available = ! empty( $competitors );
    $summary = array(
        'statement' => $visibility_available
            ? 'Merchant identifica comerciantes con visibilidad en categorías disponibles. Contrastar oportunidades con productos populares y demanda propia.'
            : 'No hay clasificaciones verificables de competidores disponibles. Se muestran demanda propia, Trends y Merchant cuando aportan datos; no se asignan rankings ficticios.',
        'merchant_popular_count' => count( $popular ),
        'merchant_missing_count' => count( $missing ),
        'competitor_count' => count( $competitors ),
        'trends_count' => count( $market ),
        'gsc_accelerations' => count( $acceleration ),
    );
    return array(
        'schema' => array( 'name' => 'seo_analista_competitive_report', 'version' => 2 ),
        'generated_at' => current_time( 'mysql' ),
        'period_days' => $days,
        'own_domain' => (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ),
        'competitors' => $configured,
        'summary' => $summary,
        'sources' => array(
            'merchant_best_sellers' => array(
                'status' => $merchant_available ? 'available' : 'unavailable',
                'updated_at' => (string) ( $merchant['popular']['updated_at'] ?? '' ),
                'complete' => ! empty( $merchant['popular']['complete'] ),
            ),
            'merchant_competitive_visibility' => array(
                'status' => (string) ( $visibility['status'] ?? 'unavailable' ),
                'errors' => (array) ( $visibility['errors'] ?? array() ),
            ),
            'google_trends' => array( 'status' => $market ? 'available' : 'unavailable' ),
            'search_console' => array( 'status' => ! empty( $data['queries'] ) ? 'available' : 'unavailable' ),
            'bing_own_site' => array( 'status' => ! empty( $bing['available'] ) ? 'available' : 'unavailable' ),
            'ga4_own_site' => array( 'status' => ! empty( $ga4['available'] ) ? 'available' : 'unavailable' ),
        ),
        'market_competitors' => array_slice( $competitors, 0, 50 ),
        'popular_products' => array_slice( $missing, 0, 80 ),
        'popular_brands' => array_slice( $brands, 0, 30, true ),
        'market_context' => array_slice( $market, 0, 25 ),
        'own_acceleration' => array_slice( $acceleration, 0, 30 ),
        'opportunities' => array_slice( $opportunities, 0, 40 ),
        // Do not silently reclassify historical crawl results as fresh evidence.
        'matrix' => array(),
        'plan' => array(),
        'limitations' => array(
            'Merchant identifica tiendas visibles por categoría, pero no asigna sus productos concretos a un dominio.',
            'La ausencia de un producto en el feed de Merchant no demuestra que falte en WooCommerce.',
            'Search Console, Bing Webmaster y GA4 sólo describen el dominio conectado.',
            'Los datos de Trends representan interés relativo y no ventas medidas.',
            'Sin datos elegibles de Merchant, no se publica una clasificación de competidores.',
        ),
    );
}

/** Persist new snapshots and the established capped history. */
function taxocommerce_insights_competitive_save_report( $report ) {
    if ( ! is_array( $report ) ) {
        return false;
    }
    update_option( SEO_ANALISTA_COMPETITIVE_REPORT_OPTION, $report, false );
    $history = get_option( SEO_ANALISTA_COMPETITIVE_REPORT_HISTORY_OPTION, array() );
    $history = is_array( $history ) ? $history : array();
    $history[] = array(
        'generated_at' => (string) ( $report['generated_at'] ?? '' ),
        'summary' => (array) ( $report['summary'] ?? array() ),
        'competitors' => (array) ( $report['competitors'] ?? array() ),
    );
    update_option( SEO_ANALISTA_COMPETITIVE_REPORT_HISTORY_OPTION, array_slice( $history, -8 ), false );
    return true;
}

/** Explicit, nonce-protected refresh: never run from rendering. */
function taxocommerce_insights_competitive_generate_handler() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'No tienes permisos para generar el informe competitivo.', 'taxocommerce-google-bing' ) );
    }
    check_admin_referer( 'seo_analista_generate_competitive_report', 'seo_analista_competitive_nonce' );
    $days = isset( $_POST['analista_days'] ) ? absint( wp_unslash( $_POST['analista_days'] ) ) : 28;
    $days = in_array( $days, array( 28, 90 ), true ) ? $days : 28;
    $report = taxocommerce_insights_competitive_build_report( $days );
    taxocommerce_insights_competitive_save_report( $report );
    wp_safe_redirect( seo_analista_admin_url( array(
        'analista_view' => 'comparacion',
        'analista_days' => $days,
        'analista_notice' => 'competitive_generated',
    ) ) );
    exit;
}
add_action( 'admin_post_taxocommerce_insights_competition_generate', 'taxocommerce_insights_competitive_generate_handler' );

/** Preserve existing secure JSON export contract. */
function taxocommerce_insights_competitive_export_handler() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'No tienes permisos para exportar el informe competitivo.', 'taxocommerce-google-bing' ) );
    }
    check_admin_referer( 'seo_analista_export_competitive_report' );
    $report = get_option( SEO_ANALISTA_COMPETITIVE_REPORT_OPTION, array() );
    if ( ! is_array( $report ) || ! $report ) {
        wp_die( esc_html__( 'Todavía no existe un informe competitivo guardado.', 'taxocommerce-google-bing' ) );
    }
    $filename = 'analista-informe-competitivo-' . current_time( 'Ymd-His' ) . '.json';
    nocache_headers();
    header( 'Content-Type: application/json; charset=UTF-8' );
    header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $filename ) . '"' );
    header( 'X-Content-Type-Options: nosniff' );
    echo wp_json_encode( $report, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT );
    exit;
}
add_action( 'admin_post_taxocommerce_insights_competition_export', 'taxocommerce_insights_competitive_export_handler' );

/**
 * The deep panel remains manual; older version-1 crawls are historic only.
 *
 * @param int $days Period in days.
 * @return void
 */
function taxocommerce_insights_competitive_render_panel( $days = 28 ) {
    $days = in_array( absint( $days ), array( 28, 90 ), true ) ? absint( $days ) : 28;
    $report = get_option( SEO_ANALISTA_COMPETITIVE_REPORT_OPTION, array() );
    $report = is_array( $report ) ? $report : array();
    echo '<section class="seo-analista-section seo-analista-forms">';
    echo '<h2>' . esc_html__( 'Inteligencia competitiva · fuentes oficiales', 'taxocommerce-google-bing' ) . '</h2>';
    echo '<p>' . esc_html__( 'Genera un informe bajo demanda usando Merchant Center, Tendencias, Search Console y Bing. No consulta scrapers, sitios de terceros ni servicios SERP de pago.', 'taxocommerce-google-bing' ) . '</p>';
    echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
    echo '<input type="hidden" name="action" value="taxocommerce_insights_competition_generate">';
    echo '<input type="hidden" name="analista_days" value="' . esc_attr( (string) $days ) . '">';
    wp_nonce_field( 'seo_analista_generate_competitive_report', 'seo_analista_competitive_nonce' );
    submit_button( __( 'Generar informe competitivo', 'taxocommerce-google-bing' ), 'primary', 'submit', false );
    echo '</form>';
    if ( $report ) {
        $export_url = wp_nonce_url(
            admin_url( 'admin-post.php?action=taxocommerce_insights_competition_export' ),
            'seo_analista_export_competitive_report'
        );
        echo ' <a class="button button-secondary" href="' . esc_url( $export_url ) . '">' . esc_html__( 'Exportar JSON', 'taxocommerce-google-bing' ) . '</a>';
    }
    if ( ! $report ) {
        echo '<p>' . esc_html__( 'Todavía no hay un informe generado.', 'taxocommerce-google-bing' ) . '</p></section>';
        return;
    }
    if ( (int) ( $report['schema']['version'] ?? 1 ) < 2 ) {
        echo '<p class="notice notice-warning inline">' . esc_html__( 'El informe almacenado es histórico y procede del antiguo rastreo. No constituye una comparación competitiva vigente. Genera uno nuevo para ver fuentes oficiales.', 'taxocommerce-google-bing' ) . '</p></section>';
        return;
    }
    $summary = (array) ( $report['summary'] ?? array() );
    echo '<p><strong>' . esc_html__( 'Última generación', 'taxocommerce-google-bing' ) . ':</strong> ' . esc_html( (string) ( $report['generated_at'] ?? '' ) ) . '</p>';
    echo '<p>' . esc_html( (string) ( $summary['statement'] ?? '' ) ) . '</p>';
    echo '<h3>' . esc_html__( 'Disponibilidad de fuentes', 'taxocommerce-google-bing' ) . '</h3><ul>';
    foreach ( (array) ( $report['sources'] ?? array() ) as $name => $source ) {
        echo '<li>' . esc_html( (string) $name ) . ': <strong>' . esc_html( (string) ( $source['status'] ?? 'unavailable' ) ) . '</strong>';
        if ( ! empty( $source['updated_at'] ) ) {
            echo ' · ' . esc_html( (string) $source['updated_at'] );
        }
        echo '</li>';
    }
    echo '</ul>';
    $competitors = (array) ( $report['market_competitors'] ?? array() );
    echo '<h3>' . esc_html__( 'Comercios visibles en Google Shopping por categoría', 'taxocommerce-google-bing' ) . '</h3>';
    if ( ! $competitors ) {
        echo '<p>' . esc_html__( 'N/D: Merchant no ha proporcionado datos verificables de visibilidad competitiva. No se calcula ningún ranking.', 'taxocommerce-google-bing' ) . '</p>';
    } else {
        echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Comercio', 'taxocommerce-google-bing' ) . '</th><th>' . esc_html__( 'Categoría Google', 'taxocommerce-google-bing' ) . '</th><th>' . esc_html__( 'Posición Merchant', 'taxocommerce-google-bing' ) . '</th><th>' . esc_html__( 'Solapamiento', 'taxocommerce-google-bing' ) . '</th><th>' . esc_html__( 'Por delante', 'taxocommerce-google-bing' ) . '</th></tr></thead><tbody>';
        foreach ( array_slice( $competitors, 0, 20 ) as $row ) {
            echo '<tr><td>' . esc_html( (string) ( $row['domain'] ?? '' ) ) . '</td><td>' . esc_html( (string) ( $row['category_id'] ?? '' ) ) . '</td><td>' . esc_html( (string) ( $row['rank'] ?? '' ) ) . '</td><td>' . esc_html( isset( $row['overlap'] ) ? number_format_i18n( 100 * (float) $row['overlap'], 1 ) . '%' : 'N/D' ) . '</td><td>' . esc_html( isset( $row['higher_position'] ) ? number_format_i18n( 100 * (float) $row['higher_position'], 1 ) . '%' : 'N/D' ) . '</td></tr>';
        }
        echo '</tbody></table>';
    }
    $missing = (array) ( $report['popular_products'] ?? array() );
    echo '<h3>' . esc_html__( 'Productos populares fuera del feed Merchant', 'taxocommerce-google-bing' ) . '</h3>';
    if ( ! $missing ) {
        echo '<p>' . esc_html__( 'No hay candidatos de Merchant confirmados; no implica que el catálogo esté completo.', 'taxocommerce-google-bing' ) . '</p>';
    } else {
        echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Producto', 'taxocommerce-google-bing' ) . '</th><th>' . esc_html__( 'Marca', 'taxocommerce-google-bing' ) . '</th><th>' . esc_html__( 'Popularidad', 'taxocommerce-google-bing' ) . '</th></tr></thead><tbody>';
        foreach ( array_slice( $missing, 0, 15 ) as $row ) {
            echo '<tr><td>' . esc_html( (string) ( $row['title'] ?? '' ) ) . '</td><td>' . esc_html( (string) ( $row['brand'] ?? '' ) ) . '</td><td>' . esc_html( (string) ( $row['rank'] ?? 'N/D' ) ) . '</td></tr>';
        }
        echo '</tbody></table>';
    }
    echo '<h3>' . esc_html__( 'Oportunidades de demanda y tendencias', 'taxocommerce-google-bing' ) . '</h3><ol>';
    foreach ( array_slice( (array) ( $report['opportunities'] ?? array() ), 0, 16 ) as $item ) {
        echo '<li><strong>' . esc_html( (string) ( $item['topic'] ?? '' ) ) . '</strong> · ' .
            esc_html( (string) ( $item['source'] ?? '' ) ) . '<p>' .
            esc_html( (string) ( $item['recommendation'] ?? '' ) ) . '</p></li>';
    }
    echo '</ol><p class="description">' .
        esc_html__( 'La ausencia de un producto en Merchant no confirma su ausencia en WooCommerce. Merchant no revela qué referencias vende cada competidor.', 'taxocommerce-google-bing' ) .
        '</p></section>';
}
