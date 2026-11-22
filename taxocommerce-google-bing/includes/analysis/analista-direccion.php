<?php
/**
 * Read-only executive evidence, collected from existing Insights sources.
 *
 * No strategic decision engine, editorial mutations or dependency on other
 * TaxoCommerce plugins. Every ranking is tied to its own measurable source.
 *
 * @package TaxoCommerce_Insights
 */

defined( 'ABSPATH' ) || exit;

/**
 * Run a bounded GA4 breakdown for an already closed calendar period.
 *
 * The existing Connections adapter supplies authentication. Empty or failed
 * responses are explicit; no assumed zeros for missing reports.
 *
 * @param string $type   sources, pages or items.
 * @param array  $period Exact period boundaries.
 * @return array
 */
function taxocommerce_insights_executive_ga4_breakdown( $type, array $period ) {
    $unavailable = array( 'state' => 'unavailable', 'rows' => array(), 'period' => $period );
    if ( ! function_exists( 'seo_google_analytics_run_report' )
        || empty( $period['date_from'] ) || empty( $period['date_to'] )
        || ! in_array( $type, array( 'sources', 'pages', 'items' ), true ) ) {
        return $unavailable;
    }
    $cache_key = 'tgb_exec_v1_' . md5( $type . '|' . $period['date_from'] . '|' . $period['date_to'] );
    if ( function_exists( 'get_transient' ) ) {
        $cache = get_transient( $cache_key );
        if ( is_array( $cache ) ) {
            return $cache;
        }
    }
    $args = array(
        'dateRanges' => array( array( 'startDate' => $period['date_from'], 'endDate' => $period['date_to'] ) ),
        'limit' => 'pages' === $type ? 100 : ( 'items' === $type ? 100 : 30 ),
    );
    if ( 'sources' === $type ) {
        $args['dimensions'] = array( array( 'name' => 'sessionSourceMedium' ) );
        $args['metrics'] = array( array( 'name' => 'sessions' ) );
        $metric = 'sessions';
    } elseif ( 'pages' === $type ) {
        $args['dimensions'] = array( array( 'name' => 'pagePath' ), array( 'name' => 'pageTitle' ) );
        $args['metrics'] = array( array( 'name' => 'screenPageViews' ) );
        $metric = 'screenPageViews';
    } else {
        $args['dimensions'] = array( array( 'name' => 'itemName' ), array( 'name' => 'itemId' ) );
        $args['metrics'] = array(
            array( 'name' => 'itemsViewed' ),
            array( 'name' => 'itemsAddedToCart' ),
            array( 'name' => 'itemsPurchased' ),
        );
        $metric = 'itemsViewed';
    }
    $args['orderBys'] = array( array( 'metric' => array( 'metricName' => $metric ), 'desc' => true ) );
    try {
        $response = seo_google_analytics_run_report( $args );
    } catch ( Throwable $error ) {
        return $unavailable;
    }
    if ( ! is_array( $response ) || is_wp_error( $response ) ) {
        return $unavailable;
    }
    $result = array( 'state' => 'available', 'rows' => array(), 'period' => $period );
    foreach ( (array) ( $response['rows'] ?? array() ) as $row ) {
        $dimensions = (array) ( $row['dimensionValues'] ?? array() );
        $values = (array) ( $row['metricValues'] ?? array() );
        $value = static function ( $i ) use ( $values ) {
            return max( 0, (int) ( $values[ $i ]['value'] ?? 0 ) );
        };
        if ( 'sources' === $type ) {
            $name = trim( (string) ( $dimensions[0]['value'] ?? '' ) );
            if ( '' !== $name ) {
                $result['rows'][] = array( 'name' => $name, 'sessions' => $value( 0 ) );
            }
        } elseif ( 'pages' === $type ) {
            $path = (string) ( $dimensions[0]['value'] ?? '' );
            if ( '' === $path || '/' !== substr( $path, 0, 1 ) ) {
                continue;
            }
            $result['rows'][] = array(
                'path' => $path,
                'title' => (string) ( $dimensions[1]['value'] ?? '' ),
                'pageviews' => $value( 0 ),
            );
        } else {
            $name = trim( (string) ( $dimensions[0]['value'] ?? '' ) );
            $id = trim( (string) ( $dimensions[1]['value'] ?? '' ) );
            if ( '' === $name && '' === $id ) {
                continue;
            }
            $result['rows'][] = array(
                'name' => $name,
                'item_id' => $id,
                'views' => $value( 0 ),
                'added_to_cart' => $value( 1 ),
                'purchases' => $value( 2 ),
            );
        }
    }
    if ( function_exists( 'set_transient' ) ) {
        set_transient( $cache_key, $result, 15 * MINUTE_IN_SECONDS );
    }
    return $result;
}

/**
 * Resolve a URL only against actual WordPress entities. Unknown pages remain
 * unclassified; we do not infer category or product types from keywords.
 *
 * @param string $url Site URL.
 * @return array
 */
function taxocommerce_insights_executive_entity( $url ) {
    $result = array( 'type' => 'other', 'id' => null, 'title' => '' );
    if ( function_exists( 'taxocommerce_insights_indicators_category' ) ) {
        $term = taxocommerce_insights_indicators_category( $url );
        if ( $term ) {
            return array( 'type' => 'category', 'id' => absint( $term->term_id ), 'title' => (string) $term->name );
        }
    }
    if ( function_exists( 'url_to_postid' ) && function_exists( 'get_post_type' ) ) {
        $id = (int) url_to_postid( $url );
        if ( $id > 0 ) {
            $type = get_post_type( $id );
            if ( in_array( $type, array( 'product', 'post', 'page' ), true ) ) {
                return array(
                    'type' => $type,
                    'id' => $id,
                    'title' => function_exists( 'get_the_title' ) ? (string) get_the_title( $id ) : '',
                );
            }
        }
    }
    return $result;
}

/**
 * GSC page evidence grouped by verified WordPress type, rather than assuming
 * organic search clicks are equivalent to visits.
 *
 * @param array $current GSC pages for the main period.
 * @param array $previous Previous GSC pages, or empty when unavailable.
 * @return array
 */
function taxocommerce_insights_executive_gsc_rankings( array $current, array $previous ) {
    $old = array();
    foreach ( $previous as $page ) {
        $key = strtolower( rtrim( (string) ( $page['page_url'] ?? '' ), '/' ) );
        if ( '' !== $key ) {
            $old[ $key ] = $page;
        }
    }
    $rankings = array( 'categories' => array(), 'products' => array(), 'posts' => array(), 'pages' => array(), 'other' => array() );
    foreach ( array_slice( $current, 0, 100 ) as $page ) {
        $url = (string) ( $page['page_url'] ?? '' );
        if ( '' === $url ) {
            continue;
        }
        $entity = taxocommerce_insights_executive_entity( $url );
        $key = strtolower( rtrim( $url, '/' ) );
        $old_row = isset( $old[ $key ] ) ? (array) $old[ $key ] : null;
        $impressions = max( 0, (int) ( $page['impressions'] ?? 0 ) );
        $clicks = max( 0, (int) ( $page['clicks'] ?? 0 ) );
        $row = array(
            'type' => $entity['type'],
            'id' => $entity['id'],
            'name' => $entity['title'] ?: (string) wp_parse_url( $url, PHP_URL_PATH ),
            'url' => $url,
            'impressions' => $impressions,
            'clicks' => $clicks,
            'position' => $impressions > 0 ? round( (float) ( $page['position'] ?? 0 ), 1 ) : null,
            'previous_impressions' => $old_row ? (int) ( $old_row['impressions'] ?? 0 ) : null,
            'change' => $old_row
                ? taxocommerce_insights_indicators_delta( $impressions, (float) ( $old_row['impressions'] ?? 0 ) )
                : null,
        );
        $group = $entity['type'] === 'product' ? 'products' : ( $entity['type'] === 'category' ? 'categories' : ( $entity['type'] === 'post' ? 'posts' : ( $entity['type'] === 'page' ? 'pages' : 'other' ) ) );
        $rankings[ $group ][] = $row;
    }
    foreach ( $rankings as &$rows ) {
        usort( $rows, static function ( $a, $b ) {
            return $b['clicks'] <=> $a['clicks'] ?: $b['impressions'] <=> $a['impressions'];
        } );
        $rows = array_slice( $rows, 0, 10 );
    }
    unset( $rows );
    return $rankings;
}

/**
 * Short GA4 page/product ranks, with a previous value only if the same item
 * occurs in both ranked responses. Missing top-list entries are unknown.
 *
 * @param array  $current Current breakdown.
 * @param array  $previous Previous breakdown.
 * @param string $type Either items or pages.
 * @return array
 */
function taxocommerce_insights_executive_ga4_rankings( array $current, array $previous, $type ) {
    $out = array( 'state' => $current['state'] ?? 'unavailable', 'rows' => array() );
    if ( 'available' !== $out['state'] ) {
        return $out;
    }
    $previous_map = array();
    if ( 'available' === ( $previous['state'] ?? '' ) ) {
        foreach ( (array) ( $previous['rows'] ?? array() ) as $row ) {
            $key = 'items' === $type
                ? ( (string) ( $row['item_id'] ?? '' ) ?: (string) ( $row['name'] ?? '' ) )
                : (string) ( $row['path'] ?? '' );
            if ( '' !== $key ) {
                $previous_map[ $key ] = $row;
            }
        }
    }
    foreach ( array_slice( (array) ( $current['rows'] ?? array() ), 0, 40 ) as $row ) {
        $key = 'items' === $type
            ? ( (string) ( $row['item_id'] ?? '' ) ?: (string) ( $row['name'] ?? '' ) )
            : (string) ( $row['path'] ?? '' );
        $old = isset( $previous_map[ $key ] ) ? (array) $previous_map[ $key ] : null;
        $metric = 'items' === $type ? 'views' : 'pageviews';
        $row['previous_' . $metric] = $old ? (int) ( $old[ $metric ] ?? 0 ) : null;
        $row['change'] = $old
            ? taxocommerce_insights_indicators_delta( (int) $row[ $metric ], (int) ( $old[ $metric ] ?? 0 ) )
            : null;
        if ( 'pages' === $type ) {
            $url = function_exists( 'home_url' ) ? home_url( (string) $row['path'] ) : '';
            $entity = $url ? taxocommerce_insights_executive_entity( $url ) : array( 'type' => 'other', 'id' => null, 'title' => '' );
            $row['url'] = $url ?: null;
            $row['type'] = $entity['type'];
            $row['entity_id'] = $entity['id'];
            if ( $entity['title'] ) {
                $row['title'] = $entity['title'];
            }
        }
        $out['rows'][] = $row;
    }
    return $out;
}

/**
 * Pull only existing supplier/Trends/external competitor evidence. These
 * snapshots have their own freshness, unrelated to the closed GA4/GSC period.
 *
 * @return array
 */
function taxocommerce_insights_executive_market_context() {
    $suppliers = function_exists( 'seo_analista_supplier_snapshot' )
        ? (array) seo_analista_supplier_snapshot( 10 ) : array();
    $trends = function_exists( 'seo_analista_market_signals' )
        ? (array) seo_analista_market_signals( 10 ) : array();
    $competition = function_exists( 'seo_analista_competition_snapshot' )
        ? (array) seo_analista_competition_snapshot( 10 ) : array();
    $internal = function_exists( 'seo_analista_internal_search_snapshot' )
        ? (array) seo_analista_internal_search_snapshot( 28, 10 ) : array();
    $rows = array();
    foreach ( array_slice( (array) ( $suppliers['rows'] ?? array() ), 0, 10 ) as $row ) {
        $rows[] = array(
            'name' => (string) ( $row['proveedor'] ?? '' ),
            'products' => (int) ( $row['total'] ?? 0 ),
            'linked_products' => (int) ( $row['linked'] ?? 0 ),
            'sync_errors' => (int) ( $row['errors'] ?? 0 ),
            'stock_known' => (int) ( $row['with_stock'] ?? 0 ),
            'last_import' => (string) ( $row['last_import'] ?? '' ),
        );
    }
    $trend_rows = array();
    foreach ( array_slice( $trends, 0, 8 ) as $row ) {
        $trend_rows[] = array(
            'query' => (string) ( $row['query'] ?? '' ),
            'growth' => isset( $row['max_growth'] ) ? (float) $row['max_growth'] : null,
            'breakout' => ! empty( $row['breakout'] ),
            'observed_at' => (string) ( $row['updated_at'] ?? $row['last_seen'] ?? '' ),
        );
    }
    return array(
        'suppliers' => array(
            'state' => ! empty( $suppliers['available'] ) ? 'available' : 'unavailable',
            'providers' => ! empty( $suppliers['available'] ) ? (int) ( $suppliers['providers'] ?? 0 ) : null,
            'linked_products' => ! empty( $suppliers['available'] ) ? (int) ( $suppliers['published_links'] ?? 0 ) : null,
            'rows' => $rows,
        ),
        'trends' => array(
            'state' => $trend_rows ? 'available' : 'unavailable',
            'rows' => $trend_rows,
            'note' => __( 'Instantánea de Google Trends con periodo propio; no equivale a los clics de GSC ni demuestra demanda de compra.', 'taxocommerce-google-bing' ),
        ),
        'competition' => array(
            'state' => ! empty( $competition['available'] ) ? 'available' : 'unavailable',
            'positions_count' => ! empty( $competition['available'] ) ? (int) ( $competition['row_count'] ?? 0 ) : null,
            'keyword_gaps' => ! empty( $competition['available'] ) ? array_slice( (array) ( $competition['keyword_gaps'] ?? array() ), 0, 5 ) : array(),
        ),
        'internal_search' => array(
            'state' => ! empty( $internal['available'] ) ? 'available' : 'unavailable',
            'period_days' => 28,
            'total' => ! empty( $internal['available'] ) ? (int) ( $internal['total'] ?? 0 ) : null,
            'zero_results' => ! empty( $internal['available'] ) ? (int) ( $internal['zero_results'] ?? 0 ) : null,
            'top' => ! empty( $internal['available'] ) ? array_slice( (array) ( $internal['top'] ?? array() ), 0, 8 ) : array(),
        ),
    );
}

/**
 * Management summary, leaving decisions to the board.
 *
 * @param array $report Current indicator report.
 * @return array
 */
function taxocommerce_insights_executive_build( array $report ) {
    $periods = (array) ( $report['periods'] ?? array() );
    $now = (array) ( $periods[0] ?? array() );
    $before = (array) ( $periods[1] ?? array() );
    $gsc = (array) ( $now['gsc']['metrics'] ?? array() );
    $old_gsc = (array) ( $before['gsc']['metrics'] ?? array() );
    $ga4 = (array) ( $now['ga4']['metrics'] ?? array() );
    $old_ga4 = (array) ( $before['ga4']['metrics'] ?? array() );

    $summary = array();
    foreach ( array(
        array( 'sessions', __( 'Visitas a la web (sesiones)', 'taxocommerce-google-bing' ), $ga4, $old_ga4 ),
        array( 'impressions', __( 'Apariciones en Google', 'taxocommerce-google-bing' ), $gsc, $old_gsc ),
        array( 'clicks', __( 'Clics orgánicos desde Google', 'taxocommerce-google-bing' ), $gsc, $old_gsc ),
        array( 'pageviews', __( 'Páginas vistas', 'taxocommerce-google-bing' ), $ga4, $old_ga4 ),
        array( 'purchases', __( 'Compras registradas en GA4', 'taxocommerce-google-bing' ), $ga4, $old_ga4 ),
    ) as $item ) {
        $code = $item[0];
        $summary[] = array(
            'key' => $code,
            'label' => $item[1],
            'current' => $item[2][ $code ] ?? null,
            'previous' => $item[3][ $code ] ?? null,
            'change' => taxocommerce_insights_indicators_delta( $item[2][ $code ] ?? null, $item[3][ $code ] ?? null ),
        );
    }

    $notes = array();
    if ( isset( $ga4['sessions'], $old_ga4['sessions'] ) ) {
        $notes[] = sprintf(
            /* translators: 1: current sessions, 2: prior sessions */
            __( 'La web registra %1$d sesiones frente a %2$d en el periodo anterior.', 'taxocommerce-google-bing' ),
            (int) $ga4['sessions'],
            (int) $old_ga4['sessions']
        );
    }
    if ( isset( $gsc['clicks'], $old_gsc['clicks'] ) ) {
        $notes[] = sprintf(
            /* translators: 1: current organic clicks, 2: prior organic clicks */
            __( 'Google aporta %1$d clics orgánicos frente a %2$d antes; no son el total de visitas.', 'taxocommerce-google-bing' ),
            (int) $gsc['clicks'],
            (int) $old_gsc['clicks']
        );
    }
    $events = (array) ( $now['ga4']['events'] ?? array() );
    $old_events = (array) ( $before['ga4']['events'] ?? array() );
    if ( isset( $events['view_item'], $events['add_to_cart'] ) ) {
        $notes[] = sprintf(
            /* translators: 1: product view events, 2: cart addition events */
            __( 'GA4 registró %1$d consultas de producto y %2$d incorporaciones al carrito (eventos, no compradores únicos).', 'taxocommerce-google-bing' ),
            (int) $events['view_item'],
            (int) $events['add_to_cart']
        );
    }
    $checks = array();
    if ( isset( $ga4['sessions'], $ga4['purchases'] )
        && (int) $ga4['sessions'] >= 30 && 0 === (int) $ga4['purchases'] ) {
        $checks[] = array(
            'department' => 'IT',
            'subject' => __( 'Medición de ventas', 'taxocommerce-google-bing' ),
            'evidence' => sprintf(
                /* translators: 1: sessions, 2: checkout events */
                __( 'GA4 registra %1$d sesiones, %2$d inicios de compra y cero compras medidas. No demuestra ausencia de pedidos reales.', 'taxocommerce-google-bing' ),
                (int) $ga4['sessions'],
                (int) ( $events['begin_checkout'] ?? 0 )
            ),
            'check' => __( 'Comparar los eventos purchase con los pedidos de WooCommerce antes de interpretar el embudo.', 'taxocommerce-google-bing' ),
        );
    }
    foreach ( array_slice( (array) ( $report['findings'] ?? array() ), 0, 5 ) as $finding ) {
        if ( ! empty( $finding['suggestion'] ) ) {
            $checks[] = array(
                'department' => 'Marketing',
                'subject' => (string) ( $finding['name'] ?? '' ),
                'evidence' => (string) ( $finding['observation'] ?? '' ),
                'check' => (string) $finding['suggestion'],
            );
        }
    }

    $current = array( 'date_from' => $now['date_from'] ?? '', 'date_to' => $now['date_to'] ?? '' );
    $prior = array( 'date_from' => $before['date_from'] ?? '', 'date_to' => $before['date_to'] ?? '' );
    $sources = taxocommerce_insights_executive_ga4_breakdown( 'sources', $current );
    $pages = taxocommerce_insights_executive_ga4_rankings(
        taxocommerce_insights_executive_ga4_breakdown( 'pages', $current ),
        taxocommerce_insights_executive_ga4_breakdown( 'pages', $prior ),
        'pages'
    );
    $items = taxocommerce_insights_executive_ga4_rankings(
        taxocommerce_insights_executive_ga4_breakdown( 'items', $current ),
        taxocommerce_insights_executive_ga4_breakdown( 'items', $prior ),
        'items'
    );
    $top_items = (array) $items['rows'];
    usort( $top_items, static function ( $a, $b ) {
        return $b['added_to_cart'] <=> $a['added_to_cart'];
    } );
    $items['most_added_to_cart'] = array_slice( array_filter( $top_items, static function ( $row ) {
        return $row['added_to_cart'] > 0;
    } ), 0, 10 );
    $page_growth = array_values( array_filter( (array) $pages['rows'], static function ( $row ) {
        return isset( $row['change']['delta'] ) && $row['pageviews'] >= 5 && $row['change']['delta'] >= 3;
    } ) );
    usort( $page_growth, static function ( $a, $b ) { return $b['change']['delta'] <=> $a['change']['delta']; } );
    $item_growth = array_values( array_filter( (array) $items['rows'], static function ( $row ) {
        return isset( $row['change']['delta'] ) && $row['views'] >= 5 && $row['change']['delta'] >= 3;
    } ) );
    usort( $item_growth, static function ( $a, $b ) { return $b['change']['delta'] <=> $a['change']['delta']; } );

    $gsc_pages = (array) ( $report['evidence']['top_pages'] ?? array() );
    $old_pages = (array) ( $report['evidence']['previous_pages'] ?? array() );
    $rankings = taxocommerce_insights_executive_gsc_rankings(
        $gsc_pages,
        ! empty( $report['evidence']['gsc_pages_previous_complete'] ) ? $old_pages : array()
    );
    $by_type = array( 'category' => array(), 'product' => array(), 'post' => array(), 'page' => array() );
    foreach ( (array) $pages['rows'] as $page_row ) {
        $type = (string) ( $page_row['type'] ?? '' );
        if ( isset( $by_type[ $type ] ) && count( $by_type[ $type ] ) < 10 ) {
            $by_type[ $type ][] = $page_row;
        }
    }
    $market = taxocommerce_insights_executive_market_context();
    foreach ( (array) ( $market['suppliers']['rows'] ?? array() ) as $provider ) {
        if ( (int) ( $provider['sync_errors'] ?? 0 ) > 0 ) {
            $checks[] = array(
                'department' => __( 'Comercial / operaciones', 'taxocommerce-google-bing' ),
                'subject' => (string) ( $provider['name'] ?? '' ),
                'evidence' => sprintf(
                    /* translators: %d: supplier sync errors */
                    __( 'La fuente local registra %d errores de sincronización para este proveedor.', 'taxocommerce-google-bing' ),
                    (int) $provider['sync_errors']
                ),
                'check' => __( 'Verificar la fecha y el estado de la sincronización del catálogo del proveedor.', 'taxocommerce-google-bing' ),
            );
            break;
        }
    }
    if ( (int) ( $market['internal_search']['zero_results'] ?? 0 ) > 0 ) {
        $checks[] = array(
            'department' => __( 'Comercial / marketing', 'taxocommerce-google-bing' ),
            'subject' => __( 'Búsquedas internas sin resultados', 'taxocommerce-google-bing' ),
            'evidence' => sprintf(
                /* translators: %d: internal searches with zero results */
                __( 'Se registraron %d búsquedas internas sin productos encontrados durante los últimos 28 días.', 'taxocommerce-google-bing' ),
                (int) $market['internal_search']['zero_results']
            ),
            'check' => __( 'Consultar qué términos buscaron los visitantes antes de valorar cambios de oferta.', 'taxocommerce-google-bing' ),
        );
    }
    if ( ! empty( $report['source_cutoffs']['gsc_latest_imported_date'] ) ) {
        $last_gsc = strtotime( (string) $report['source_cutoffs']['gsc_latest_imported_date'] );
        if ( $last_gsc && time() - $last_gsc > 7 * DAY_IN_SECONDS ) {
            $checks[] = array(
                'department' => 'IT',
                'subject' => __( 'Actualización de Search Console', 'taxocommerce-google-bing' ),
                'evidence' => sprintf(
                    /* translators: %s: last imported GSC day */
                    __( 'Los datos importados de GSC llegan hasta el %s.', 'taxocommerce-google-bing' ),
                    (string) $report['source_cutoffs']['gsc_latest_imported_date']
                ),
                'check' => __( 'Verificar la sincronización antes de concluir que esta última semana representa el tráfico actual.', 'taxocommerce-google-bing' ),
            );
        }
    }
    return array(
        'summary' => $summary,
        'narrative' => $notes,
        'reviews' => $checks,
        'ga4_traffic_sources' => $sources,
        'ga4_pages' => $pages,
        'ga4_pages_by_type' => $by_type,
        'ga4_items' => $items,
        'growing_pages' => array_slice( $page_growth, 0, 10 ),
        'growing_items' => array_slice( $item_growth, 0, 10 ),
        'gsc_rankings' => $rankings,
        'market' => $market,
        'period' => $current,
        'limitations' => array(
            __( 'Los rankings son parciales (máximo 100 filas por origen); un elemento ausente no equivale a cero visitas ni a ausencia de demanda.', 'taxocommerce-google-bing' ),
            __( 'Las páginas vistas de GA4, clics GSC y eventos de producto son métricas distintas y no deben sumarse.', 'taxocommerce-google-bing' ),
            __( 'Las variaciones por artículo solo existen si se encuentra en ambos listados limitados; en caso contrario el dato anterior es desconocido.', 'taxocommerce-google-bing' ),
            __( 'Tendencias y proveedores son instantáneas con periodos independientes; su ausencia se indica como fuente no disponible.', 'taxocommerce-google-bing' ),
        ),
    );
}

/**
 * Compact, readable business presentation. The structured counterpart is
 * attached to the downloaded JSON under executive.
 *
 * @param array $report Complete indicator and executive report.
 * @return void
 */
function taxocommerce_insights_executive_render( array $report ) {
    $executive = (array) ( $report['executive'] ?? array() );
    if ( ! $executive ) {
        return;
    }
    echo '<section class="seo-analista-section"><h2>' . esc_html__( 'Resumen para dirección', 'taxocommerce-google-bing' ) . '</h2>';
    echo '<p>' . esc_html__( 'Qué mejora, qué empeora, qué productos y páginas atraen interés, y qué datos requieren comprobación.', 'taxocommerce-google-bing' ) . '</p>';
    echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Indicador', 'taxocommerce-google-bing' ) . '</th><th>' . esc_html__( 'Periodo actual', 'taxocommerce-google-bing' ) . '</th><th>' . esc_html__( 'Anterior', 'taxocommerce-google-bing' ) . '</th><th>' . esc_html__( 'Variación', 'taxocommerce-google-bing' ) . '</th></tr></thead><tbody>';
    foreach ( (array) ( $executive['summary'] ?? array() ) as $row ) {
        $change = (array) ( $row['change'] ?? array() );
        $delta = isset( $change['growth_pct'] ) ? number_format_i18n( $change['growth_pct'], 1 ) . ' %' : __( 'Sin comparación', 'taxocommerce-google-bing' );
        echo '<tr><td>' . esc_html( (string) $row['label'] ) . '</td><td>' . esc_html( isset( $row['current'] ) ? number_format_i18n( $row['current'], 0 ) : __( 'No disponible', 'taxocommerce-google-bing' ) ) . '</td><td>' . esc_html( isset( $row['previous'] ) ? number_format_i18n( $row['previous'], 0 ) : __( 'No disponible', 'taxocommerce-google-bing' ) ) . '</td><td>' . esc_html( $delta ) . '</td></tr>';
    }
    echo '</tbody></table>';
    foreach ( (array) ( $executive['narrative'] ?? array() ) as $note ) {
        echo '<p>' . esc_html( (string) $note ) . '</p>';
    }
    echo '</section>';

    $rankings = (array) ( $executive['gsc_rankings'] ?? array() );
    echo '<section class="seo-analista-section"><h2>' . esc_html__( 'Qué interesa desde Google', 'taxocommerce-google-bing' ) . '</h2>';
    foreach ( array(
        'categories' => __( 'Categorías con más clics y visibilidad', 'taxocommerce-google-bing' ),
        'products' => __( 'Productos encontrados desde Google', 'taxocommerce-google-bing' ),
        'posts' => __( 'Entradas y artículos', 'taxocommerce-google-bing' ),
        'pages' => __( 'Páginas comerciales e informativas', 'taxocommerce-google-bing' ),
    ) as $key => $label ) {
        echo '<h3>' . esc_html( $label ) . '</h3>';
        taxocommerce_insights_executive_render_rows( (array) ( $rankings[ $key ] ?? array() ), 'gsc' );
    }
    echo '<h3>' . esc_html__( 'Consultas de búsqueda más frecuentes', 'taxocommerce-google-bing' ) . '</h3>';
    taxocommerce_insights_executive_render_rows( array_slice( (array) ( $report['evidence']['top_queries'] ?? array() ), 0, 10 ), 'query' );
    echo '</section>';

    echo '<section class="seo-analista-section"><h2>' . esc_html__( 'Qué se consulta dentro de la tienda', 'taxocommerce-google-bing' ) . '</h2>';
    echo '<p>' . esc_html__( 'Datos registrados por Google Analytics 4; no deben confundirse con impresiones o clics orgánicos.', 'taxocommerce-google-bing' ) . '</p>';
    echo '<h3>' . esc_html__( 'Categorías más visitadas (GA4)', 'taxocommerce-google-bing' ) . '</h3>';
    taxocommerce_insights_executive_render_rows( (array) ( $executive['ga4_pages_by_type']['category'] ?? array() ), 'ga4_pages' );
    echo '<h3>' . esc_html__( 'Entradas más leídas (GA4)', 'taxocommerce-google-bing' ) . '</h3>';
    taxocommerce_insights_executive_render_rows( (array) ( $executive['ga4_pages_by_type']['post'] ?? array() ), 'ga4_pages' );
    echo '<h3>' . esc_html__( 'Productos más visitados (GA4)', 'taxocommerce-google-bing' ) . '</h3>';
    taxocommerce_insights_executive_render_rows( (array) ( $executive['ga4_pages_by_type']['product'] ?? array() ), 'ga4_pages' );
    echo '<h3>' . esc_html__( 'Páginas con más visualizaciones', 'taxocommerce-google-bing' ) . '</h3>';
    taxocommerce_insights_executive_render_rows( (array) ( $executive['ga4_pages']['rows'] ?? array() ), 'ga4_pages' );
    echo '<h3>' . esc_html__( 'Páginas que más han crecido', 'taxocommerce-google-bing' ) . '</h3>';
    taxocommerce_insights_executive_render_rows( (array) ( $executive['growing_pages'] ?? array() ), 'ga4_pages' );
    echo '<h3>' . esc_html__( 'Productos más consultados', 'taxocommerce-google-bing' ) . '</h3>';
    taxocommerce_insights_executive_render_rows( (array) ( $executive['ga4_items']['rows'] ?? array() ), 'items' );
    echo '<h3>' . esc_html__( 'Productos que más crecen en consultas', 'taxocommerce-google-bing' ) . '</h3>';
    taxocommerce_insights_executive_render_rows( (array) ( $executive['growing_items'] ?? array() ), 'items' );
    echo '<h3>' . esc_html__( 'Productos más añadidos al carrito', 'taxocommerce-google-bing' ) . '</h3>';
    taxocommerce_insights_executive_render_rows( (array) ( $executive['ga4_items']['most_added_to_cart'] ?? array() ), 'items' );
    echo '<h3>' . esc_html__( 'De dónde llegan las visitas', 'taxocommerce-google-bing' ) . '</h3>';
    taxocommerce_insights_executive_render_rows( (array) ( $executive['ga4_traffic_sources']['rows'] ?? array() ), 'sources' );
    echo '</section>';

    $market = (array) ( $executive['market'] ?? array() );
    echo '<section class="seo-analista-section"><h2>' . esc_html__( 'Tendencias, proveedores y competencia', 'taxocommerce-google-bing' ) . '</h2>';
    echo '<h3>' . esc_html__( 'Tendencias observadas', 'taxocommerce-google-bing' ) . '</h3>';
    taxocommerce_insights_executive_render_rows( (array) ( $market['trends']['rows'] ?? array() ), 'trends' );
    echo '<p class="description">' . esc_html( (string) ( $market['trends']['note'] ?? '' ) ) . '</p>';
    echo '<h3>' . esc_html__( 'Búsquedas dentro de la tienda (últimos 28 días)', 'taxocommerce-google-bing' ) . '</h3>';
    $internal = (array) ( $market['internal_search'] ?? array() );
    if ( 'available' === ( $internal['state'] ?? '' ) ) {
        echo '<p>' . esc_html( sprintf(
            /* translators: 1: searches, 2: searches without results */
            __( '%1$d búsquedas registradas; %2$d sin resultados.', 'taxocommerce-google-bing' ),
            (int) ( $internal['total'] ?? 0 ), (int) ( $internal['zero_results'] ?? 0 )
        ) ) . '</p>';
        taxocommerce_insights_executive_render_rows( (array) ( $internal['top'] ?? array() ), 'internal' );
    } else {
        echo '<p>' . esc_html__( 'Búsqueda interna no disponible.', 'taxocommerce-google-bing' ) . '</p>';
    }
    echo '<h3>' . esc_html__( 'Catálogo de proveedores', 'taxocommerce-google-bing' ) . '</h3>';
    taxocommerce_insights_executive_render_rows( (array) ( $market['suppliers']['rows'] ?? array() ), 'suppliers' );
    echo '<p>' . esc_html__( 'Posiciones verificadas de competidores:', 'taxocommerce-google-bing' ) . ' ';
    echo esc_html( 'available' === ( $market['competition']['state'] ?? '' ) ? number_format_i18n( (int) ( $market['competition']['positions_count'] ?? 0 ) ) : __( 'No disponibles', 'taxocommerce-google-bing' ) ) . '</p>';
    echo '</section>';

    echo '<section class="seo-analista-section"><h2>' . esc_html__( 'Asuntos para contrastar antes de decidir', 'taxocommerce-google-bing' ) . '</h2>';
    $reviews = (array) ( $executive['reviews'] ?? array() );
    if ( ! $reviews ) {
        echo '<p>' . esc_html__( 'Sin incidencias con comprobación claramente justificada; consultar los datos por producto y fuente antes de decidir.', 'taxocommerce-google-bing' ) . '</p>';
    }
    foreach ( $reviews as $item ) {
        echo '<p><strong>' . esc_html( (string) ( $item['department'] ?? '' ) . ' · ' . (string) ( $item['subject'] ?? '' ) ) . '</strong>: ';
        echo esc_html( (string) ( $item['evidence'] ?? '' ) . ' ' . (string) ( $item['check'] ?? '' ) ) . '</p>';
    }
    echo '</section>';
}

/**
 * Reusable, accessible and deliberately bounded ranking.
 *
 * @param array  $rows Ranked evidence.
 * @param string $type Source-specific row format.
 * @return void
 */
function taxocommerce_insights_executive_render_rows( array $rows, $type ) {
    if ( ! $rows ) {
        echo '<p>' . esc_html__( 'No hay datos verificables en esta fuente para el periodo seleccionado.', 'taxocommerce-google-bing' ) . '</p>';
        return;
    }
    echo '<ul>';
    foreach ( array_slice( $rows, 0, 8 ) as $row ) {
        $name = (string) ( $row['name'] ?? $row['title'] ?? $row['query_text'] ?? '' );
        $url = (string) ( $row['url'] ?? '' );
        if ( 'sources' === $type ) {
            $detail = sprintf(
                /* translators: %d: number of measured sessions from this source */
                __( '%d sesiones.', 'taxocommerce-google-bing' ),
                (int) ( $row['sessions'] ?? 0 )
            );
        } elseif ( 'items' === $type ) {
            $detail = sprintf(
                /* translators: 1: GA4 item view events, 2: GA4 item added-to-cart events */
                __( '%1$d consultas del artículo; %2$d añadidos al carrito.', 'taxocommerce-google-bing' ),
                (int) ( $row['views'] ?? 0 ), (int) ( $row['added_to_cart'] ?? 0 )
            );
        } elseif ( 'ga4_pages' === $type ) {
            $detail = sprintf(
                /* translators: %d: GA4 pageview events */
                __( '%d páginas vistas.', 'taxocommerce-google-bing' ),
                (int) ( $row['pageviews'] ?? 0 )
            );
        } elseif ( 'internal' === $type ) {
            $name = (string) ( $row['search_term'] ?? $row['normalized_term'] ?? '' );
            $detail = sprintf(
                /* translators: 1: total internal searches, 2: internal searches without results */
                __( '%1$d búsquedas; %2$d veces sin resultados.', 'taxocommerce-google-bing' ),
                (int) ( $row['searches'] ?? 0 ), (int) ( $row['zero_count'] ?? 0 )
            );
        } elseif ( 'suppliers' === $type ) {
            $detail = sprintf(
                /* translators: 1: supplier catalog products, 2: linked products, 3: synchronization errors */
                __( '%1$d productos de catálogo; %2$d vinculados; %3$d errores de sincronización.', 'taxocommerce-google-bing' ),
                (int) ( $row['products'] ?? 0 ), (int) ( $row['linked_products'] ?? 0 ), (int) ( $row['sync_errors'] ?? 0 )
            );
        } elseif ( 'trends' === $type ) {
            $detail = ! empty( $row['breakout'] ) ? __( 'Término emergente en la fuente.', 'taxocommerce-google-bing' )
                : ( isset( $row['growth'] ) ? sprintf(
                    /* translators: %s: Google Trends measured growth percentage */
                    __( 'Crecimiento indicado: %s %% (fuente externa).', 'taxocommerce-google-bing' ),
                    number_format_i18n( $row['growth'], 1 )
                ) : __( 'Señal externa.', 'taxocommerce-google-bing' ) );
        } else {
            $detail = sprintf(
                /* translators: 1: GSC organic clicks, 2: GSC search impressions */
                __( '%1$d clics orgánicos; %2$d apariciones en Google.', 'taxocommerce-google-bing' ),
                (int) ( $row['clicks'] ?? 0 ), (int) ( $row['impressions'] ?? 0 )
            );
        }
        echo '<li>';
        if ( $url && function_exists( 'esc_url' ) ) {
            echo '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $name ?: $url ) . '</a>';
        } else {
            echo '<strong>' . esc_html( $name ?: __( 'Sin nombre', 'taxocommerce-google-bing' ) ) . '</strong>';
        }
        echo ' · ' . esc_html( $detail );
        if ( isset( $row['change']['delta'] ) ) {
            $delta_key = 'ga4_pages' === $type ? 'pageviews' : ( 'items' === $type ? 'views' : 'impressions' );
            echo ' · ' . esc_html( sprintf(
                /* translators: 1: signed absolute difference, 2: source metric */
                __( 'Variación: %1$+d %2$s frente al periodo anterior.', 'taxocommerce-google-bing' ),
                (int) $row['change']['delta'],
                $delta_key
            ) );
        }
        echo '</li>';
    }
    echo '</ul>';
}
