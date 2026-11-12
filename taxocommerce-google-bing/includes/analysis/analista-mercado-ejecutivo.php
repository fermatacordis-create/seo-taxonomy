<?php
/**
 * Executive market intelligence. Read-only signals; no editorial audit.
 *
 * Traffic from AI assistants is GA4 referral attribution, not AI citations.
 *
 * @package TaxoCommerce_Insights
 */

defined( 'ABSPATH' ) || exit;

/**
 * Compare GA4 assistant referrals with the preceding equal-length period.
 *
 * @param int   $days Report window.
 * @param array $ga4  Already fetched current GA4 snapshot.
 * @return array
 */
function taxocommerce_insights_market_ai_trend( $days, array $ga4 ) {
    $result = array(
        'available' => ! empty( $ga4['available'] ),
        'current'   => null,
        'previous'  => null,
        'delta'     => null,
        'growth_pct'=> null,
        'sources'   => array(),
        'limitation'=> 'Solo sesiones que GA4 atribuye a asistentes IA; no mide menciones, citas ni AI Overviews.',
    );
    if ( ! $result['available'] ) {
        return $result;
    }
    $result['current'] = (int) ( $ga4['traffic_quality']['ai_sessions'] ?? 0 );
    foreach ( (array) ( $ga4['sources'] ?? array() ) as $source ) {
        if ( 'ai' === ( $source['channel'] ?? '' ) ) {
            $result['sources'][] = array(
                'source_medium' => (string) ( $source['source_medium'] ?? '' ),
                'sessions'      => (int) ( $source['sessions'] ?? 0 ),
            );
        }
    }
    $period_start = (string) ( $ga4['period']['start'] ?? '' );
    if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $period_start ) || ! function_exists( 'seo_analista_ga4_snapshot' ) ) {
        return $result;
    }
    try {
        $previous_end = ( new DateTimeImmutable( $period_start ) )->modify( '-1 day' )->format( 'Y-m-d' );
    } catch ( Exception $exception ) {
        return $result;
    }
    $previous = (array) seo_analista_ga4_snapshot( $days, $previous_end );
    if ( empty( $previous['available'] ) || isset( $previous['errors']['sources'] ) ) {
        return $result;
    }
    $result['previous'] = (int) ( $previous['traffic_quality']['ai_sessions'] ?? 0 );
    $result['delta'] = $result['current'] - $result['previous'];
    if ( $result['previous'] > 0 ) {
        $result['growth_pct'] = round( 100 * $result['delta'] / $result['previous'], 1 );
    }
    return $result;
}

/**
 * Build an evidence-based opportunity without claiming catalog defects.
 *
 * @param string $topic Topic or query.
 * @param string $source Origin of the measurement.
 * @param string $reason Evidence.
 * @param string $recommendation Commercial investigation.
 * @param int    $score Opportunity score.
 * @param string $bucket Triage bucket.
 * @param array  $extra Extra measured fields.
 * @return array
 */
function taxocommerce_insights_market_item( $topic, $source, $reason, $recommendation, $score, $bucket = 'HACER_DESPUES', array $extra = array() ) {
    $key = function_exists( 'seo_analista_normalize_text' ) ? seo_analista_normalize_text( $topic ) : strtolower( $topic );
    return array_merge(
        array(
            'market_signal'       => true,
            'task_id'             => 'market_' . substr( hash( 'sha256', $source . '|' . $key ), 0, 20 ),
            'topic'               => $topic,
            'query'               => $topic,
            'source'              => $source,
            'sources'             => array( $source ),
            'reason'              => $reason,
            'issues'              => array(),
            'recommended_changes' => array( $recommendation ),
            'priority_score'      => max( 0, min( 99, (int) $score ) ),
            'work_bucket'         => $bucket,
            'final_bucket'        => $bucket,
            'action_type'         => 'INVESTIGAR_OPORTUNIDAD',
            'owner'               => 'Comercial / marketing',
            'target_url'          => '',
            'action_executed'     => '',
            'primary_action'      => array( 'type' => 'INVESTIGAR_OPORTUNIDAD', 'executable' => false, 'instruction' => $recommendation ),
        ),
        $extra
    );
}

/**
 * Build a market-only plan from measured external signals.
 *
 * No product/category quality checks, implicit landing creation, or entity
 * validation are needed to identify demand. Existing legacy reports remain.
 *
 * @param int   $days Reporting window.
 * @param array $google Already fetched Google/GA4 snapshot, when available.
 * @return array
 */
function taxocommerce_insights_market_plan( $days, array $google = array() ) {
    $days = in_array( (int) $days, array( 28, 60, 90 ), true ) ? (int) $days : 28;
    $ga4 = (array) ( $google['ga4'] ?? array() );
    if ( ! $google && function_exists( 'seo_analista_google_snapshot' ) ) {
        $google = (array) seo_analista_google_snapshot( $days, false );
        $ga4 = (array) ( $google['ga4'] ?? array() );
    }
    $ai = taxocommerce_insights_market_ai_trend( $days, $ga4 );
    $plan = array();
    $data = function_exists( 'seo_analista_get_data' ) ? (array) seo_analista_get_data( $days ) : array();
    $previous_queries = array();
    foreach ( (array) ( $data['previous_queries'] ?? array() ) as $row ) {
        $query = (string) ( $row['query_text'] ?? $row['query'] ?? '' );
        $key = function_exists( 'seo_analista_normalize_text' ) ? seo_analista_normalize_text( $query ) : strtolower( trim( $query ) );
        if ( '' !== $key ) {
            $previous_queries[ $key ] = (float) ( $row['impressions'] ?? 0 );
        }
    }
    foreach ( (array) ( $data['queries'] ?? array() ) as $row ) {
        $query = trim( (string) ( $row['query_text'] ?? $row['query'] ?? '' ) );
        if ( '' === $query || ( function_exists( 'seo_analista_query_is_actionable' ) && ! seo_analista_query_is_actionable( $query ) ) ) {
            continue;
        }
        $impressions = (float) ( $row['impressions'] ?? 0 );
        $clicks = (float) ( $row['clicks'] ?? 0 );
        $position = (float) ( $row['position'] ?? 0 );
        if ( $impressions < 10 ) {
            continue;
        }
        $key = function_exists( 'seo_analista_normalize_text' ) ? seo_analista_normalize_text( $query ) : strtolower( $query );
        $previous = (float) ( $previous_queries[ $key ] ?? 0 );
        $delta = $impressions - $previous;
        $growth = $previous > 0 ? ( 100 * $delta / $previous ) : null;
        $is_growth = $delta >= 8 && ( 0.25 * $previous <= $delta );
        $is_click_gap = $impressions >= 30 && $position > 0 && $position <= 20 && $clicks / $impressions < 0.02;
        if ( ! $is_growth && ! $is_click_gap ) {
            continue;
        }
        $reason = sprintf(
            '%s: %.0f impresiones, %.0f clics, posición %.1f; periodo anterior %.0f impresiones.',
            $query,
            $impressions,
            $clicks,
            $position,
            $previous
        );
        $recommendation = $is_growth
            ? 'Estudiar si la demanda creciente justifica potenciar la oferta, la visibilidad o las campañas. Consultar al Auditor solo si se decide revisar contenidos.'
            : 'Estudiar la captación comercial: hay visibilidad con pocos clics. Evaluar oferta y competencia antes de proponer cambios.';
        $plan[] = taxocommerce_insights_market_item(
            $query,
            'Google Search Console',
            $reason,
            $recommendation,
            $is_growth ? 70 : 55,
            'HACER_DESPUES',
            array( 'impressions' => $impressions, 'clicks' => $clicks, 'position' => $position, 'previous_impressions' => $previous, 'growth_pct' => $growth )
        );
    }
    if ( ! empty( $ai['available'] ) && null !== $ai['previous'] && $ai['delta'] >= 3 ) {
        $plan[] = taxocommerce_insights_market_item(
            'Tráfico referido desde asistentes IA',
            'Google Analytics 4',
            (int) $ai['current'] . ' sesiones frente a ' . (int) $ai['previous'] . ' en el periodo anterior.',
            'Analizar los asistentes que remiten visitas y estudiar su contribución a la captación. No confundir tráfico referido con menciones de marca.',
            65,
            'HACER_DESPUES',
            array( 'ai_referrals' => $ai )
        );
    }
    if ( ! empty( $ga4['available'] ) && (int) ( $ga4['sessions'] ?? 0 ) >= 30 && (int) ( $ga4['purchases'] ?? 0 ) === 0 ) {
        $plan[] = taxocommerce_insights_market_item(
            'Conversión comercial',
            'Google Analytics 4',
            (int) $ga4['sessions'] . ' sesiones y ninguna compra atribuida en GA4.',
            'Contrastar las ventas con WooCommerce y analizar oferta, precios, pagos y atribución; no deducir abandonos individuales.',
            60
        );
    }
    if ( function_exists( 'seo_analista_market_signals' ) ) {
        foreach ( array_slice( (array) seo_analista_market_signals( 12 ), 0, 5 ) as $row ) {
            $query = trim( (string) ( $row['query'] ?? '' ) );
            if ( '' === $query || empty( $row['breakout'] ) && (float) ( $row['max_growth'] ?? 0 ) <= 0 ) {
                continue;
            }
            $plan[] = taxocommerce_insights_market_item(
                $query,
                'Google Trends',
                ! empty( $row['breakout'] ) ? 'Término emergente (breakout) en Trends.' : 'Crecimiento señalado por Google Trends: ' . (float) $row['max_growth'] . '%.',
                'Investigar tendencia, demanda y encaje comercial antes de ampliar el surtido o plantear contenidos nuevos.',
                62,
                'INVESTIGAR'
            );
        }
    }
    // Competitive keyword gaps are external market opportunities, not
    // assertions that any local product/category has defective content.
    if ( function_exists( 'seo_analista_competition_snapshot' ) ) {
        $competition = (array) seo_analista_competition_snapshot( 12 );
        if ( ! empty( $competition['available'] ) ) {
            foreach ( array_slice( (array) ( $competition['keyword_gaps'] ?? array() ), 0, 3 ) as $gap ) {
                $keyword = trim( (string) ( $gap['keyword'] ?? '' ) );
                $competitor = (array) ( $gap['best_competitor'] ?? array() );
                if ( '' === $keyword || (float) ( $competitor['position'] ?? 0 ) <= 0 ) {
                    continue;
                }
                $plan[] = taxocommerce_insights_market_item(
                    $keyword,
                    'Competencia: posiciones externas',
                    'Competidor ' . (string) ( $competitor['domain'] ?? '' ) . ' en posición ' . (float) ( $competitor['position'] ?? 0 ) . '; posición propia: ' . (float) ( $gap['own_position'] ?? 0 ) . '.',
                    'Comparar catálogo, precio, demanda y presencia en buscadores antes de decidir cómo competir.',
                    (int) ( $gap['priority'] ?? 50 ),
                    'INVESTIGAR'
                );
            }
        }
    }
    usort( $plan, static function ( $left, $right ) {
        return (int) ( $right['priority_score'] ?? 0 ) <=> (int) ( $left['priority_score'] ?? 0 );
    } );
    $seen = array();
    $unique = array();
    foreach ( $plan as $item ) {
        if ( isset( $seen[ $item['task_id'] ] ) ) {
            continue;
        }
        $seen[ $item['task_id'] ] = true;
        $unique[] = $item;
        if ( count( $unique ) >= 40 ) {
            break;
        }
    }
    $summary = array( 'total' => count( $unique ), 'hacer_ahora' => 0, 'hacer_despues' => 0, 'investigar' => 0, 'vigilar' => 0, 'esperar_datos' => 0 );
    foreach ( $unique as $item ) {
        if ( 'HACER_DESPUES' === $item['final_bucket'] ) {
            ++$summary['hacer_despues'];
        } elseif ( 'INVESTIGAR' === $item['final_bucket'] ) {
            ++$summary['investigar'];
        }
    }
    return array( 'plan' => $unique, 'summary' => $summary, 'ai' => $ai, 'ga4' => $ga4 );
}

/**
 * Render the executive commercial opportunities without running legacy audit.
 *
 * @param array $market Market snapshot.
 * @return void
 */
function taxocommerce_insights_market_render( array $market ) {
    $ai = (array) ( $market['ai'] ?? array() );
    echo '<section class="seo-analista-section"><h2>' . esc_html__( 'Oportunidades de mercado', 'taxocommerce-google-bing' ) . '</h2>';
    echo '<p>' . esc_html__( 'Señales de demanda, conversión y competencia. No es una auditoría de títulos, productos ni categorías.', 'taxocommerce-google-bing' ) . '</p>';
    if ( empty( $ai['available'] ) ) {
        echo '<p>' . esc_html__( 'Visitas desde IA: N/D (GA4 no disponible).', 'taxocommerce-google-bing' ) . '</p>';
    } else {
        $comparison = null === ( $ai['previous'] ?? null ) ? 'N/D' : number_format_i18n( (int) $ai['previous'] );
        echo '<p><strong>' . esc_html__( 'Visitas referidas desde IA', 'taxocommerce-google-bing' ) . ':</strong> ' .
            esc_html( number_format_i18n( (int) ( $ai['current'] ?? 0 ) ) ) . ' · ' .
            esc_html__( 'periodo anterior', 'taxocommerce-google-bing' ) . ': ' . esc_html( $comparison ) . '.</p>';
        foreach ( array_slice( (array) ( $ai['sources'] ?? array() ), 0, 8 ) as $source ) {
            echo '<p>' . esc_html( (string) ( $source['source_medium'] ?? '' ) ) . ': ' .
                esc_html( number_format_i18n( (int) ( $source['sessions'] ?? 0 ) ) ) . '</p>';
        }
    }
    echo '<p class="description">' . esc_html( (string) ( $ai['limitation'] ?? '' ) ) . '</p>';
    echo '<p class="description">' . esc_html__( 'Microsoft Clarity: no se dispone de métricas de sesiones integradas en este informe. Su configuración de seguimiento no prueba que se hayan obtenido datos.', 'taxocommerce-google-bing' ) . '</p>';
    $plan = (array) ( $market['plan'] ?? array() );
    if ( ! $plan ) {
        echo '<p>' . esc_html__( 'Sin señales comerciales suficientes para recomendar actuaciones en este periodo.', 'taxocommerce-google-bing' ) . '</p>';
    } else {
        echo '<ol>';
        foreach ( $plan as $item ) {
            echo '<li><strong>' . esc_html( (string) ( $item['topic'] ?? '' ) ) . '</strong> · ' . esc_html( (string) ( $item['source'] ?? '' ) );
            echo '<p>' . esc_html( (string) ( $item['reason'] ?? '' ) ) . '</p>';
            echo '<p>' . esc_html( (string) ( $item['recommended_changes'][0] ?? '' ) ) . '</p></li>';
        }
        echo '</ol>';
    }
    echo '</section>';
}
