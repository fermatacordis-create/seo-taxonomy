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

// Do not reuse the monolith's option keys: it must not overwrite an
// Insights-generated report when both products are installed.
if ( ! defined( 'TAXOCOMMERCE_INSIGHTS_COMPETITIVE_REPORT_OPTION' ) ) {
    define( 'TAXOCOMMERCE_INSIGHTS_COMPETITIVE_REPORT_OPTION', 'taxocommerce_insights_competitive_report_v2' );
}
if ( ! defined( 'TAXOCOMMERCE_INSIGHTS_COMPETITIVE_HISTORY_OPTION' ) ) {
    define( 'TAXOCOMMERCE_INSIGHTS_COMPETITIVE_HISTORY_OPTION', 'taxocommerce_insights_competitive_report_history_v2' );
}

/**
 * One official-source refresh per authenticated AJAX request. No cron, no
 * competitor website crawling, and no automatic remote request on page load.
 * Store diagnostic metadata only (never access tokens or credentials).
 */
function taxocommerce_insights_competitive_refresh_source_ajax() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => __( 'Permisos insuficientes.', 'taxocommerce-google-bing' ) ), 403 );
    }
    check_ajax_referer( 'taxocommerce_insights_competition_sources', 'nonce' );
    $source = isset( $_POST['source'] ) ? sanitize_key( wp_unslash( $_POST['source'] ) ) : '';
    if ( ! in_array( $source, array( 'merchant', 'trends', 'visibility' ), true ) ) {
        wp_send_json_error( array( 'message' => __( 'Fuente no reconocida.', 'taxocommerce-google-bing' ) ), 400 );
    }

    $settings = taxocommerce_insights_competitive_settings();
    $allowed = 'merchant' === $source
        ? array_intersect( $settings['signals'], array( 'popular', 'visibility' ) )
        : in_array( 'visibility' === $source ? 'visibility' : 'trends', $settings['signals'], true );
    if ( ! $allowed ) {
        wp_send_json_error( array( 'message' => __( 'Fuente no seleccionada.', 'taxocommerce-google-bing' ) ), 400 );
    }

    $status = 'error';
    $message = '';
    $count = 0;
    $complete = false;
    if ( 'merchant' === $source ) {
        if ( ! class_exists( 'TaxoCommerce_Google_Bing_Merchant', false ) ) {
            $status = 'not_configured';
            $message = __( 'El módulo Merchant no está disponible.', 'taxocommerce-google-bing' );
        } elseif ( '' === TaxoCommerce_Google_Bing_Merchant::account() ) {
            $status = 'not_configured';
            $message = __( 'Configura el ID de Merchant Center en la pestaña Merchant.', 'taxocommerce-google-bing' );
        } else {
            $previous = (array) ( TaxoCommerce_Google_Bing_Merchant::snapshots()['popular'] ?? array() );
            $last = strtotime( (string) ( $previous['updated_at'] ?? '' ) );
            // Refresh finished snapshots once per day. Every request fetches
            // at most one page of 500 results, retaining the existing cursor.
            $restart = ! empty( $previous['complete'] ) && ( ! $last || time() - $last > DAY_IN_SECONDS );
            $result = TaxoCommerce_Google_Bing_Merchant::sync( 'popular', $restart );
            if ( is_wp_error( $result ) ) {
                $message = $result->get_error_message();
                $status = 'error';
            } else {
                $count = absint( $result['count'] ?? 0 );
                $complete = ! empty( $result['complete'] );
                $status = $count ? ( $complete ? 'available' : 'partial' ) : ( $complete ? 'no_data' : 'partial' );
                $message = $complete
                    ? __( 'Lote de Merchant consultado.', 'taxocommerce-google-bing' )
                    : __( 'Merchant tiene más páginas pendientes. Vuelve a generar para continuar.', 'taxocommerce-google-bing' );
            }
        }
    } elseif ( 'visibility' === $source ) {
        // One Google taxonomy category per request. The browser queues at most
        // three requests, so no request performs multiple expensive API calls.
        $day_count = isset( $_POST['days'] ) ? absint( wp_unslash( $_POST['days'] ) ) : 28;
        $day_count = in_array( $day_count, array( 28, 90 ), true ) ? $day_count : 28;
        $step = isset( $_POST['step'] ) ? min( 2, absint( wp_unslash( $_POST['step'] ) ) ) : 0;
        $categories = taxocommerce_insights_competitive_google_categories();
        $account = class_exists( 'TaxoCommerce_Google_Bing_Merchant', false )
            ? TaxoCommerce_Google_Bing_Merchant::account() : '';
        $signature = md5( $account . '|' . $day_count . '|' . implode( ',', $categories ) );
        $previous = get_option( 'taxocommerce_insights_competitive_visibility_progress_v1', array() );
        if ( ! is_array( $previous ) || ( $previous['signature'] ?? '' ) !== $signature || 0 === $step ) {
            $previous = array( 'signature' => $signature, 'rows' => array(), 'errors' => array() );
        }
        if ( '' === $account ) {
            $status = 'not_configured';
            $message = __( 'La cuenta Merchant no está configurada.', 'taxocommerce-google-bing' );
        } elseif ( ! $categories ) {
            $status = 'blocked';
            $message = __( 'No hay categorías Google verificadas: consulta primero Best Sellers.', 'taxocommerce-google-bing' );
        } elseif ( isset( $categories[ $step ] ) ) {
            $result = TaxoCommerce_Google_Bing_Merchant::competitive_visibility( array( $categories[ $step ] ), $day_count );
            $previous['rows'] = array_merge( (array) $previous['rows'], (array) ( $result['rows'] ?? array() ) );
            $previous['errors'] = array_merge( (array) $previous['errors'], (array) ( $result['errors'] ?? array() ) );
            $count = count( $previous['rows'] );
            $status = $count ? 'available' : ( ! empty( $previous['errors'] ) ? 'error' : 'no_data' );
            $message = implode( ' · ', array_slice( (array) $previous['errors'], 0, 3 ) );
        } else {
            $status = 'no_data';
        }
        $complete = ! $categories || $step >= count( $categories ) - 1;
        $previous['updated_at'] = current_time( 'mysql' );
        $previous['status'] = $status;
        $previous['complete'] = $complete;
        update_option( 'taxocommerce_insights_competitive_visibility_progress_v1', $previous, false );
    } else {
        if ( ! function_exists( 'seo_google_trends_sync' ) ) {
            $status = 'not_configured';
            $message = __( 'El conector Trends no está disponible.', 'taxocommerce-google-bing' );
        } else {
            // Existing connector handles its own cache, request limits and
            // backoff. Do not use force=true to bypass API protections.
            $result = seo_google_trends_sync( false );
            $state = function_exists( 'seo_google_trends_last_sync' )
                ? (array) seo_google_trends_last_sync() : array();
            $count = absint( $state['signals'] ?? 0 ) + absint( $state['market_signals'] ?? 0 );
            if ( is_wp_error( $result ) ) {
                $status = 'error';
                $message = $result->get_error_message();
            } else {
                $radar = (string) ( $state['radar_status'] ?? '' );
                $market_status = (string) ( $state['market_provider_status'] ?? '' );
                $message = trim( (string) ( $state['market_provider_error'] ?? $state['error'] ?? '' ) );
                if ( $count > 0 ) {
                    $status = 'available';
                } elseif ( 'disabled' === $market_status && 'operational' !== $radar ) {
                    $status = 'not_configured';
                } elseif ( 'error' === $radar || in_array( $market_status, array( 'error', 'backoff', 'invalid' ), true ) ) {
                    $status = 'error';
                } else {
                    $status = 'no_data';
                }
                if ( '' === $message ) {
                    $message = __( 'Consulta de Trends finalizada.', 'taxocommerce-google-bing' );
                }
            }
        }
    }

    $diagnostics = get_option( 'taxocommerce_insights_competitive_source_diagnostics_v1', array() );
    $diagnostics = is_array( $diagnostics ) ? $diagnostics : array();
    $diagnostics[ $source ] = array(
        'status' => $status,
        'message' => sanitize_text_field( $message ),
        'checked_at' => current_time( 'mysql' ),
        'count' => $count,
        'complete' => $complete,
        'next_step' => 'visibility' === $source && ! $complete ? $step + 1 : null,
    );
    update_option( 'taxocommerce_insights_competitive_source_diagnostics_v1', $diagnostics, false );
    wp_send_json_success( $diagnostics[ $source ] );
}
add_action( 'wp_ajax_taxocommerce_insights_competitive_source_refresh', 'taxocommerce_insights_competitive_refresh_source_ajax' );

/** Up to three verified taxonomy category IDs already present in Merchant. */
function taxocommerce_insights_competitive_google_categories() {
    if ( ! class_exists( 'TaxoCommerce_Google_Bing_Merchant', false ) ) {
        return array();
    }
    $snapshots = TaxoCommerce_Google_Bing_Merchant::snapshots();
    $settings = taxocommerce_insights_competitive_settings();
    $terms = taxocommerce_insights_competitive_scope_terms( $settings );
    $counts = array();
    foreach ( (array) ( $snapshots['popular']['rows'] ?? array() ) as $row ) {
        if ( ! is_array( $row ) || ! taxocommerce_insights_competitive_in_scope( $row, $terms ) ) {
            continue;
        }
        $id = absint( $row['category_id'] ?? 0 );
        if ( $id ) {
            $counts[ $id ] = ( $counts[ $id ] ?? 0 ) + 1;
        }
    }
    arsort( $counts );
    return array_slice( array_keys( $counts ), 0, 3 );
}

/** Distinguish fresh data, prior snapshots and failed external requests. */
function taxocommerce_insights_competitive_source_status( $key, $has_rows, $diagnostics, $updated_at = '' ) {
    $entry = (array) ( $diagnostics[ $key ] ?? array() );
    $status = (string) ( $entry['status'] ?? '' );
    $result = array(
        'status' => $has_rows ? 'available' : ( $status ?: 'not_checked' ),
        'checked_at' => (string) ( $entry['checked_at'] ?? '' ),
        'updated_at' => (string) $updated_at,
        'detail' => (string) ( $entry['message'] ?? '' ),
    );
    if ( $has_rows && in_array( $status, array( 'error', 'not_configured' ), true ) ) {
        $result['status'] = 'cached';
    }
    return $result;
}

/**
 * Per-store configuration, separate from monolith/legacy Analista settings.
 * Only names and optional domains supplied by the store administrator are used.
 */
function taxocommerce_insights_competitive_settings() {
    $defaults = array(
        'mode' => 'both',
        'scope' => 'all',
        'category_ids' => array(),
        'competitors' => array(),
        'suppliers' => array(),
        'signals' => array( 'popular', 'trends', 'gsc', 'visibility' ),
    );
    $saved = get_option( 'taxocommerce_insights_competitive_settings_v1', array() );
    $saved = is_array( $saved ) ? $saved : array();
    $mode = sanitize_key( (string) ( $saved['mode'] ?? 'both' ) );
    $scope = sanitize_key( (string) ( $saved['scope'] ?? 'all' ) );
    $signals = array_intersect(
        array( 'popular', 'trends', 'gsc', 'visibility' ),
        array_map( 'sanitize_key', (array) ( $saved['signals'] ?? $defaults['signals'] ) )
    );
    return array(
        'mode' => in_array( $mode, array( 'both', 'current', 'new' ), true ) ? $mode : 'both',
        'scope' => in_array( $scope, array( 'all', 'selected' ), true ) ? $scope : 'all',
        'category_ids' => array_slice( array_values( array_unique( array_filter( array_map( 'absint', (array) ( $saved['category_ids'] ?? array() ) ) ) ) ), 0, 100 ),
        'competitors' => taxocommerce_insights_competitive_parse_actors( (array) ( $saved['competitors'] ?? array() ) ),
        'suppliers' => taxocommerce_insights_competitive_parse_actors( (array) ( $saved['suppliers'] ?? array() ) ),
        'signals' => array_values( $signals ),
    );
}

/** Normalize administrator-supplied company names (name | optional hostname). */
function taxocommerce_insights_competitive_parse_actors( $actors ) {
    $out = array();
    foreach ( array_slice( (array) $actors, 0, 40 ) as $actor ) {
        if ( is_array( $actor ) ) {
            $name = sanitize_text_field( (string) ( $actor['name'] ?? '' ) );
            $domain = (string) ( $actor['domain'] ?? '' );
        } else {
            $parts = explode( '|', (string) $actor, 2 );
            $name = sanitize_text_field( trim( $parts[0] ) );
            $domain = isset( $parts[1] ) ? trim( $parts[1] ) : '';
        }
        $domain = strtolower( preg_replace( '#^https?://#i', '', trim( $domain ) ) );
        $domain = preg_replace( '#/.*$#', '', $domain );
        $domain = preg_replace( '/^www\./', '', $domain );
        if ( '' !== $domain && ( ! filter_var( $domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME ) || false === strpos( $domain, '.' ) ) ) {
            $domain = '';
        }
        if ( '' === $name && '' === $domain ) {
            continue;
        }
        if ( '' === $name ) {
            $name = $domain;
        }
        $key = strtolower( $name . '|' . $domain );
        $out[ $key ] = array( 'name' => $name, 'domain' => $domain );
        if ( count( $out ) >= 20 ) {
            break;
        }
    }
    return array_values( $out );
}

/** Save preferences on explicit administrator request, without triggering an API call. */
function taxocommerce_insights_competitive_settings_handler() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'No tienes permisos para configurar Competidores.', 'taxocommerce-google-bing' ) );
    }
    check_admin_referer( 'taxocommerce_insights_competition_settings', 'taxocommerce_insights_competition_nonce' );
    $raw_competitors = isset( $_POST['market_competitors'] ) ? sanitize_textarea_field( wp_unslash( $_POST['market_competitors'] ) ) : '';
    $raw_suppliers = isset( $_POST['market_suppliers'] ) ? sanitize_textarea_field( wp_unslash( $_POST['market_suppliers'] ) ) : '';
    $raw_categories = isset( $_POST['market_category_ids'] ) && is_array( $_POST['market_category_ids'] )
        ? array_map( 'absint', wp_unslash( $_POST['market_category_ids'] ) ) : array();
    $raw_signals = isset( $_POST['market_signals'] ) && is_array( $_POST['market_signals'] )
        ? array_map( 'sanitize_key', wp_unslash( $_POST['market_signals'] ) ) : array();
    update_option( 'taxocommerce_insights_competitive_settings_v1', array(
        'mode' => sanitize_key( (string) wp_unslash( $_POST['market_mode'] ?? 'both' ) ),
        'scope' => sanitize_key( (string) wp_unslash( $_POST['market_scope'] ?? 'all' ) ),
        'category_ids' => array_slice( array_values( array_unique( array_filter( $raw_categories ) ) ), 0, 100 ),
        'competitors' => taxocommerce_insights_competitive_parse_actors( preg_split( '/\r\n|\r|\n/', $raw_competitors ) ),
        'suppliers' => taxocommerce_insights_competitive_parse_actors( preg_split( '/\r\n|\r|\n/', $raw_suppliers ) ),
        'signals' => array_values( array_intersect( array( 'popular', 'trends', 'gsc', 'visibility' ), $raw_signals ) ),
    ), false );
    $days = isset( $_POST['analysis_days'] ) ? absint( wp_unslash( $_POST['analysis_days'] ) ) : 28;
    $days = in_array( $days, array( 28, 90 ), true ) ? $days : 28;
    wp_safe_redirect( add_query_arg( array(
        'page' => 'taxocommerce-google-bing',
        'tgb_tab' => 'analysis',
        'analysis_days' => $days,
        'analysis_open' => 'competencia',
        'analista_notice' => 'market_settings_saved',
    ), admin_url( 'admin.php' ) ) . '#seo-analista-lazy-result' );
    exit;
}
add_action( 'admin_post_taxocommerce_insights_competition_settings', 'taxocommerce_insights_competitive_settings_handler' );

/**
 * Categories work in every WooCommerce store; a TaxoCommerce hierarchy is optional.
 * Match known titles/categories only; never interpret name matching as a Google category mapping.
 */
function taxocommerce_insights_competitive_scope_terms( $settings ) {
    if ( 'selected' !== $settings['scope'] || ! $settings['category_ids'] ) {
        return array();
    }
    $names = array();
    foreach ( $settings['category_ids'] as $id ) {
        $term = get_term( $id, 'product_cat' );
        if ( $term && ! is_wp_error( $term ) && isset( $term->name ) ) {
            $names[] = (string) $term->name;
        }
    }
    return $names;
}
function taxocommerce_insights_competitive_in_scope( $row, $terms ) {
    if ( ! $terms ) {
        return true;
    }
    $haystack = strtolower( implode( ' ', array(
        (string) ( $row['title'] ?? '' ),
        (string) ( $row['query'] ?? '' ),
        (string) ( $row['category'] ?? '' ),
    ) ) );
    foreach ( $terms as $term ) {
        if ( '' !== $term && false !== stripos( $haystack, $term ) ) {
            return true;
        }
    }
    return false;
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
    $settings = taxocommerce_insights_competitive_settings();
    $configured = array_values( array_filter( array_column( $settings['competitors'], 'domain' ) ) );
    $scope_terms = taxocommerce_insights_competitive_scope_terms( $settings );
    $data = function_exists( 'seo_analista_get_data' ) ? (array) seo_analista_get_data( $days ) : array();
    $market = function_exists( 'seo_analista_market_signals' ) ? (array) seo_analista_market_signals( 25 ) : array();
    $acceleration = function_exists( 'seo_analista_search_acceleration' )
        ? (array) seo_analista_search_acceleration( $days, 30 )
        : array();
    $bing = function_exists( 'seo_analista_bing_snapshot' ) ? (array) seo_analista_bing_snapshot( $days, 20 ) : array();
    $ga4 = function_exists( 'seo_analista_ga4_snapshot' ) ? (array) seo_analista_ga4_snapshot($days) : array();

    $diagnostics = get_option( 'taxocommerce_insights_competitive_source_diagnostics_v1', array() );
    $diagnostics = is_array( $diagnostics ) ? $diagnostics : array();
    $merchant = class_exists( 'TaxoCommerce_Google_Bing_Merchant', false )
        ? (array) TaxoCommerce_Google_Bing_Merchant::snapshots()
        : array();
    $popular = in_array( 'popular', $settings['signals'], true )
        ? array_values( array_filter( (array) ( $merchant['popular']['rows'] ?? array() ), static function ( $row ) use ( $scope_terms ) {
            return is_array( $row ) && taxocommerce_insights_competitive_in_scope( $row, $scope_terms );
        } ) ) : array();
    if ( ! in_array( 'trends', $settings['signals'], true ) ) {
        $market = array();
    } elseif ( 'selected' === $settings['scope'] ) {
        $market = array_values( array_filter( $market, static function ( $row ) use ( $scope_terms ) {
            return is_array( $row ) && taxocommerce_insights_competitive_in_scope( $row, $scope_terms );
        } ) );
    }
    if ( ! in_array( 'gsc', $settings['signals'], true ) || 'new' === $settings['mode'] ) {
        $acceleration = array();
    } elseif ( 'selected' === $settings['scope'] ) {
        $acceleration = array_values( array_filter( $acceleration, static function ( $row ) use ( $scope_terms ) {
            return is_array( $row ) && taxocommerce_insights_competitive_in_scope( $row, $scope_terms );
        } ) );
    }
    // Visibility uses verified Merchant categories even when the store chose
    // not to display the Best Sellers indicator in its final report.
    $categories = taxocommerce_insights_competitive_google_categories();

    // The AJAX flow fetched each category in a separate request. Rendering
    // never triggers a potentially 100-second chain of Merchant requests.
    $visibility = array(
        'status' => $categories ? 'not_checked' : 'blocked',
        'rows' => array(),
        'errors' => $categories ? array() : array( __( 'Antes se necesitan categorías verificadas de Merchant Best Sellers.', 'taxocommerce-google-bing' ) ),
    );
    if ( in_array( 'visibility', $settings['signals'], true ) && $categories ) {
        $progress = get_option( 'taxocommerce_insights_competitive_visibility_progress_v1', array() );
        $account = class_exists( 'TaxoCommerce_Google_Bing_Merchant', false )
            ? TaxoCommerce_Google_Bing_Merchant::account() : '';
        $signature = md5( $account . '|' . $days . '|' . implode( ',', $categories ) );
        if ( is_array( $progress ) && ( $progress['signature'] ?? '' ) === $signature ) {
            $visibility = array(
                'status' => (string) ( $progress['status'] ?? 'not_checked' ),
                'rows' => (array) ( $progress['rows'] ?? array() ),
                'errors' => (array) ( $progress['errors'] ?? array() ),
                'complete' => ! empty( $progress['complete'] ),
                'updated_at' => (string) ( $progress['updated_at'] ?? '' ),
            );
        }
    }
    $competitors = array();
    foreach ( (array) ( $visibility['rows'] ?? array() ) as $row ) {
        $domain = sanitize_text_field( (string) ( $row['domain'] ?? '' ) );
        if ( '' === $domain || ! empty( $row['is_own'] ) ) {
            continue;
        }
        if ( $configured && ! in_array( preg_replace( '/^www\\./', '', strtolower( $domain ) ), $configured, true ) ) {
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
        'configuration' => $settings,
        'scope_terms' => $scope_terms,
        'selected_competitors' => array_map( static function ( $actor ) use ( $competitors ) {
            $matched = false;
            foreach ( $competitors as $row ) {
                if ( $actor['domain'] && $actor['domain'] === preg_replace( '/^www\\./', '', strtolower( (string) ( $row['domain'] ?? '' ) ) ) ) {
                    $matched = true;
                    break;
                }
            }
            return array( 'name' => $actor['name'], 'domain' => $actor['domain'], 'status' => $matched ? 'available' : 'unavailable' );
        }, $settings['competitors'] ),
        'selected_suppliers' => array_map( static function ( $actor ) {
            return array( 'name' => $actor['name'], 'domain' => $actor['domain'], 'status' => 'not_connected' );
        }, $settings['suppliers'] ),
        'summary' => $summary,
        'sources' => array(
            'merchant_best_sellers' => array_merge(
                taxocommerce_insights_competitive_source_status( 'merchant', $merchant_available, $diagnostics, (string) ( $merchant['popular']['updated_at'] ?? '' ) ),
                array(
                    'status' => in_array( 'popular', $settings['signals'], true )
                        ? ( $merchant_available ? 'available' : (string) ( $diagnostics['merchant']['status'] ?? 'not_checked' ) )
                        : 'not_selected',
                    'complete' => ! empty( $merchant['popular']['complete'] ),
                )
            ),
            'merchant_competitive_visibility' => array(
                'status' => (string) ( $visibility['status'] ?? 'unavailable' ),
                'errors' => (array) ( $visibility['errors'] ?? array() ),
                'detail' => implode( ' · ', (array) ( $visibility['errors'] ?? array() ) ),
                'complete' => ! empty( $visibility['complete'] ),
                'updated_at' => (string) ( $visibility['updated_at'] ?? '' ),
            ),
            'google_trends' => taxocommerce_insights_competitive_source_status( 'trends', ! empty( $market ), $diagnostics ),
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
    update_option( TAXOCOMMERCE_INSIGHTS_COMPETITIVE_REPORT_OPTION, $report, false );
    $history = get_option( TAXOCOMMERCE_INSIGHTS_COMPETITIVE_HISTORY_OPTION, array() );
    $history = is_array( $history ) ? $history : array();
    $history[] = array(
        'generated_at' => (string) ( $report['generated_at'] ?? '' ),
        'summary' => (array) ( $report['summary'] ?? array() ),
        'competitors' => (array) ( $report['competitors'] ?? array() ),
    );
    update_option( TAXOCOMMERCE_INSIGHTS_COMPETITIVE_HISTORY_OPTION, array_slice( $history, -8 ), false );
    return true;
}

/** Explicit, nonce-protected refresh: never run from rendering. */
function taxocommerce_insights_competitive_generate_handler() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'No tienes permisos para generar el informe competitivo.', 'taxocommerce-google-bing' ) );
    }
    check_admin_referer( 'taxocommerce_insights_competition_generate', 'taxocommerce_insights_competition_nonce' );
    $days = isset( $_POST['analista_days'] ) ? absint( wp_unslash( $_POST['analista_days'] ) ) : 28;
    $days = in_array( $days, array( 28, 90 ), true ) ? $days : 28;
    $report = taxocommerce_insights_competitive_build_report( $days );
    taxocommerce_insights_competitive_save_report( $report );
    // Redirect to the real Insights screen, not the old Analista helper
    // (which can be supplied by the monolith and point at a different tab).
    wp_safe_redirect( add_query_arg(
        array(
            'page' => 'taxocommerce-google-bing',
            'tgb_tab' => 'analysis',
            'analysis_days' => $days,
            'analysis_open' => 'competencia',
            'analista_notice' => 'competitive_generated',
        ),
        admin_url( 'admin.php' )
    ) . '#seo-analista-lazy-result' );
    exit;
}
add_action( 'admin_post_taxocommerce_insights_competition_generate', 'taxocommerce_insights_competitive_generate_handler' );

/** Preserve existing secure JSON export contract. */
function taxocommerce_insights_competitive_export_handler() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'No tienes permisos para exportar el informe competitivo.', 'taxocommerce-google-bing' ) );
    }
    check_admin_referer( 'taxocommerce_insights_competition_export' );
    $report = get_option( TAXOCOMMERCE_INSIGHTS_COMPETITIVE_REPORT_OPTION, array() );
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
    $report = get_option( TAXOCOMMERCE_INSIGHTS_COMPETITIVE_REPORT_OPTION, array() );
    $report = is_array( $report ) ? $report : array();
    echo '<section class="seo-analista-section seo-analista-forms">';
    echo '<h2>' . esc_html__( 'Inteligencia competitiva · fuentes oficiales', 'taxocommerce-google-bing' ) . '</h2>';
    echo '<p>' . esc_html__( 'Genera un informe bajo demanda usando Merchant Center, Tendencias, Search Console y Bing. No consulta scrapers, sitios de terceros ni servicios SERP de pago.', 'taxocommerce-google-bing' ) . '</p>';
    $settings = taxocommerce_insights_competitive_settings();
    echo '<h3>' . esc_html__( 'Configurar análisis de mercado', 'taxocommerce-google-bing' ) . '</h3>';
    echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
    echo '<input type="hidden" name="action" value="taxocommerce_insights_competition_settings">';
    echo '<input type="hidden" name="analysis_days" value="' . esc_attr( (string) $days ) . '">';
    wp_nonce_field( 'taxocommerce_insights_competition_settings', 'taxocommerce_insights_competition_nonce' );
    echo '<p><label for="market_mode">' . esc_html__( 'Modalidad', 'taxocommerce-google-bing' ) . '</label> <select name="market_mode" id="market_mode">';
    foreach ( array( 'both' => __( 'Mercado actual y nuevos nichos', 'taxocommerce-google-bing' ), 'current' => __( 'Mercado actual', 'taxocommerce-google-bing' ), 'new' => __( 'Nuevos nichos', 'taxocommerce-google-bing' ) ) as $value => $label ) {
        echo '<option value="' . esc_attr( $value ) . '"' . selected( $settings['mode'], $value, false ) . '>' . esc_html( $label ) . '</option>';
    }
    echo '</select></p>';
    echo '<p><label for="market_scope">' . esc_html__( 'Ámbito', 'taxocommerce-google-bing' ) . '</label> <select name="market_scope" id="market_scope">';
    foreach ( array( 'all' => __( 'Toda la tienda', 'taxocommerce-google-bing' ), 'selected' => __( 'Categorías seleccionadas', 'taxocommerce-google-bing' ) ) as $value => $label ) {
        echo '<option value="' . esc_attr( $value ) . '"' . selected( $settings['scope'], $value, false ) . '>' . esc_html( $label ) . '</option>';
    }
    echo '</select></p>';
    if ( taxonomy_exists( 'product_cat' ) ) {
        $terms = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false, 'number' => 0 ) );
        if ( is_array( $terms ) && ! is_wp_error( $terms ) ) {
            echo '<details><summary>' . esc_html__( 'Seleccionar categorías de WooCommerce', 'taxocommerce-google-bing' ) . '</summary><div style="max-height:220px;overflow:auto;padding:8px;">';
            foreach ( $terms as $term ) {
                echo '<label style="display:block"><input type="checkbox" name="market_category_ids[]" value="' . esc_attr( (string) $term->term_id ) . '"' . checked( in_array( (int) $term->term_id, $settings['category_ids'], true ), true, false ) . '> ' . esc_html( $term->name ) . '</label>';
            }
            echo '</div></details>';
        }
    }
    echo '<p class="description">' . esc_html__( 'El filtro por categorías utiliza coincidencias de texto; no equivale a una clasificación oficial de Google. Sin categorías seleccionadas se muestra el mercado completo.', 'taxocommerce-google-bing' ) . '</p>';
    echo '<p><label for="market_competitors"><strong>' . esc_html__( 'Competidores', 'taxocommerce-google-bing' ) . '</strong></label></p>';
    echo '<textarea id="market_competitors" name="market_competitors" rows="4" class="large-text" placeholder="' . esc_attr__( 'Nombre comercial | dominio.example (uno por línea)', 'taxocommerce-google-bing' ) . '">' . esc_textarea( implode( "\n", array_map( static function ( $item ) { return $item['name'] . ( $item['domain'] ? ' | ' . $item['domain'] : '' ); }, $settings['competitors'] ) ) ) . '</textarea>';
    echo '<p><label for="market_suppliers"><strong>' . esc_html__( 'Proveedores que se quieren contrastar', 'taxocommerce-google-bing' ) . '</strong></label></p>';
    echo '<textarea id="market_suppliers" name="market_suppliers" rows="4" class="large-text" placeholder="' . esc_attr__( 'Nombre comercial | dominio.example (uno por línea)', 'taxocommerce-google-bing' ) . '">' . esc_textarea( implode( "\n", array_map( static function ( $item ) { return $item['name'] . ( $item['domain'] ? ' | ' . $item['domain'] : '' ); }, $settings['suppliers'] ) ) ) . '</textarea>';
    echo '<p class="description">' . esc_html__( 'Los nombres se configuran libremente. Merchant sólo aporta estadísticas de dominios identificados por su informe; no revela ventas particulares. Los proveedores se guardan para el contraste, pero no se les asignan datos de ventas no disponibles.', 'taxocommerce-google-bing' ) . '</p>';
    echo '<fieldset><legend><strong>' . esc_html__( 'Indicadores', 'taxocommerce-google-bing' ) . '</strong></legend>';
    foreach ( array( 'popular' => __( 'Productos populares (Merchant)', 'taxocommerce-google-bing' ), 'trends' => __( 'Tendencias (Trends)', 'taxocommerce-google-bing' ), 'gsc' => __( 'Demanda propia (Search Console)', 'taxocommerce-google-bing' ), 'visibility' => __( 'Visibilidad de comercios (Merchant)', 'taxocommerce-google-bing' ) ) as $value => $label ) {
        echo '<label style="display:inline-block;margin-right:14px"><input type="checkbox" name="market_signals[]" value="' . esc_attr( $value ) . '"' . checked( in_array( $value, $settings['signals'], true ), true, false ) . '> ' . esc_html( $label ) . '</label>';
    }
    echo '</fieldset>';
    submit_button( __( 'Guardar configuración', 'taxocommerce-google-bing' ), 'secondary', 'submit', false );
    echo '</form><hr>';
    echo '<p class="description">' . esc_html__( 'El informe utiliza la configuración guardada. No ejecuta conexiones al abrir esta pantalla.', 'taxocommerce-google-bing' ) . '</p>';
    echo '<form id="taxocommerce-insights-competition-generate" data-refresh-merchant="' . esc_attr( (string) (int) (bool) array_intersect( $settings['signals'], array( 'popular', 'visibility' ) ) ) . '" data-refresh-trends="' . esc_attr( (string) (int) in_array( 'trends', $settings['signals'], true ) ) . '" data-refresh-visibility="' . esc_attr( (string) (int) in_array( 'visibility', $settings['signals'], true ) ) . '" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
    echo '<input type="hidden" name="action" value="taxocommerce_insights_competition_generate">';
    echo '<input type="hidden" name="taxocommerce_competition_refresh_nonce" value="' . esc_attr( wp_create_nonce( 'taxocommerce_insights_competition_sources' ) ) . '">';
    echo '<input type="hidden" name="analista_days" value="' . esc_attr( (string) $days ) . '">';
    wp_nonce_field( 'taxocommerce_insights_competition_generate', 'taxocommerce_insights_competition_nonce' );
    submit_button( __( 'Generar informe competitivo', 'taxocommerce-google-bing' ), 'primary', 'submit', false );
    echo '<p id="taxocommerce-insights-competition-refresh-status" role="status" aria-live="polite"></p>';
    echo '</form>';
    if ( $report ) {
        $export_url = wp_nonce_url(
            admin_url( 'admin-post.php?action=taxocommerce_insights_competition_export' ),
            'taxocommerce_insights_competition_export'
        );
        echo ' <a class="button button-secondary" href="' . esc_url( $export_url ) . '">' . esc_html__( 'Exportar JSON', 'taxocommerce-google-bing' ) . '</a>';
    }
    if ( ! $report ) {
        // The former crawler saved under the monolith's option. Preserve it
        // untouched and never present it as current Merchant intelligence.
        $legacy = get_option( 'seo_analista_competitive_report_v1', array() );
        if ( is_array( $legacy ) && ! empty( $legacy ) ) {
            echo '<p class="notice notice-info inline">' . esc_html__( 'Existe un informe histórico del antiguo Analista. Se conserva sin cambios, pero no es evidencia vigente. Genera el informe nuevo con fuentes oficiales.', 'taxocommerce-google-bing' ) . '</p>';
        } else {
            echo '<p>' . esc_html__( 'Todavía no hay un informe generado.', 'taxocommerce-google-bing' ) . '</p>';
        }
        echo '</section>';
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
        if ( ! empty( $source['checked_at'] ) ) {
            echo ' · ' . esc_html__( 'Consulta', 'taxocommerce-google-bing' ) . ': ' . esc_html( (string) $source['checked_at'] );
        }
        if ( ! empty( $source['detail'] ) ) {
            echo ' · ' . esc_html( (string) $source['detail'] );
        }
        echo '</li>';
    }
    echo '</ul>';
    $selected = (array) ( $report['selected_competitors'] ?? array() );
    if ( $selected ) {
        echo '<h3>' . esc_html__( 'Competidores elegidos por la tienda', 'taxocommerce-google-bing' ) . '</h3><ul>';
        foreach ( $selected as $item ) {
            echo '<li>' . esc_html( (string) $item['name'] ) . ' · ' . esc_html( (string) $item['domain'] ) . ' · ' . esc_html( (string) $item['status'] ) . '</li>';
        }
        echo '</ul>';
    }
    $suppliers = (array) ( $report['selected_suppliers'] ?? array() );
    if ( $suppliers ) {
        echo '<h3>' . esc_html__( 'Proveedores seleccionados', 'taxocommerce-google-bing' ) . '</h3><ul>';
        foreach ( $suppliers as $item ) {
            echo '<li>' . esc_html( (string) $item['name'] ) . ' · ' . esc_html__( 'Sin datos comerciales verificados', 'taxocommerce-google-bing' ) . '</li>';
        }
        echo '</ul>';
    }
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
