<?php
/**
 * Insights-owned Analysis endpoint. Legacy renderers remain unchanged and are
 * reused for evidence and presentation, without calling the old AJAX action.
 */
defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/analista-mercado-ejecutivo.php';

/**
 * The executive action and the detailed roadmap are one report in Insights.
 * Do not mutate the historical list used by the old SEO plugin.
 *
 * @return array
 */
function taxocommerce_google_bing_analysis_reports() {
    if ( ! function_exists( 'seo_analista_lazy_reports' ) ) {
        return array();
    }
    $reports = (array) seo_analista_lazy_reports();
    unset( $reports['trabajo'] );
    if ( isset( $reports['directrices'] ) ) {
        $reports['directrices']['label'] = __( 'Plan de trabajo', 'taxocommerce-google-bing' );
        $reports['directrices']['description'] = 'Ventas, mejoras SEO/GEO verificables y tareas priorizadas en un solo informe.';
    }
    return $reports;
}

/**
 * Preserve the original roadmap, validations, supplier indicators and search
 * gaps. $plan is computed once per combined AJAX request.
 *
 * @param int   $days Reporting period.
 * @param array $plan Already computed, validated decision plan.
 * @return void
 */
function taxocommerce_google_bing_analysis_render_work( $days, array $plan ) {
    echo '<section class="seo-analista-section">' . '<h2>' . esc_html__( 'Plan de acción', 'taxocommerce-google-bing' ) . '</h2>' . '<p>' . esc_html__( 'Cola validada: hacer ahora, hacer después e investigar cuando falta evidencia.', 'taxocommerce-google-bing' ) . '</p>' . '</section>';
    $consistency = function_exists( 'seo_analista_plan_consistency_report' )
        ? seo_analista_plan_consistency_report( $plan )
        : array( 'ok' => true, 'issues' => array() );
    if ( empty( $consistency['ok'] ) ) {
        echo '<div class="notice notice-error inline"><p><strong>Plan de acción incoherente:</strong> '
            . esc_html( number_format_i18n( count( (array) ( $consistency['issues'] ?? array() ) ) ) )
            . ' discrepancias entre bucket, decisión o acción ejecutable. No debe darse por válido hasta resolverlas.</p></div>';
    }
    seo_analista_render_roadmap(
        $plan,
        seo_analista_plan_summary( $plan ),
        seo_analista_supplier_snapshot( 40 ),
        seo_analista_internal_search_snapshot( $days, 40 )
    );
}


/**
 * JSON for the action plan already computed by the combined AJAX report.
 * No second decision-plan call, provider download or complete Insights export.
 *
 * @param array $plan The same validated plan rendered on screen.
 * @param int   $days Reporting window.
 * @return array
 */
function taxocommerce_insights_action_plan_export( array $plan, $days ) {
    $actions = array();
    foreach ( $plan as $row ) {
        if ( ! is_array( $row ) ) {
            continue;
        }
        // Market opportunities are evidence, not legacy editorial validation tasks.
        $contract = ! empty( $row['market_signal'] )
            ? $row
            : ( function_exists( 'seo_analista_task_contract' )
                ? (array) seo_analista_task_contract( $row )
                : array(
                    'work_bucket'   => (string) ( $row['work_bucket'] ?? '' ),
                    'primary_action'=> (array) ( $row['primary_action'] ?? array() ),
                ) );
        $actions[] = array_merge(
            array(
                'topic'               => (string) ( $row['topic'] ?? '' ),
                'reason'              => (string) ( $row['reason'] ?? '' ),
                'issues'              => (array) ( $row['issues'] ?? array() ),
                'source'              => (string) ( $row['source'] ?? '' ),
                'sources'             => (array) ( $row['sources'] ?? array() ),
                'recommended_changes' => (array) ( $row['recommended_changes'] ?? array() ),
            ),
            $contract
        );
    }

    $export = array(
        'schema'       => array( 'name' => 'taxocommerce-insights-plan-accion', 'version' => 1 ),
        'generated_at' => gmdate( 'c' ),
        'period_days'  => absint( $days ),
        'total'        => count( $actions ),
        'summary'      => ( ! empty( $plan ) && ! empty( $plan[0]['market_signal'] ) )
            ? array(
                'total'         => count( $plan ),
                'hacer_ahora'   => 0,
                'hacer_despues' => count( array_filter( $plan, static function ( $row ) { return 'HACER_DESPUES' === ( $row['final_bucket'] ?? '' ); } ) ),
                'investigar'    => count( array_filter( $plan, static function ( $row ) { return 'INVESTIGAR' === ( $row['final_bucket'] ?? '' ); } ) ),
            )
            : ( function_exists( 'seo_analista_plan_summary' ) ? seo_analista_plan_summary( $plan ) : array() ),
        'plan_accion'  => $actions,
    );

    // Existing sanitized JSON contract also protects the lightweight export.
    return function_exists( 'seo_analista_json_clean_value' )
        ? seo_analista_json_clean_value( $export )
        : $export;
}


/**
 * Latest completed, site-local action plan. Never regenerate reports on read.
 *
 * @return array Stored, validated plan or an empty array.
 */
function taxocommerce_insights_last_plan_export() {
    $export = get_option( 'taxocommerce_insights_last_plan_json', array() );
    if ( ! is_array( $export )
        || 'taxocommerce-insights-plan-accion' !== ( $export['schema']['name'] ?? '' )
        || ! isset( $export['generated_at'], $export['plan_accion'] )
        || ! is_array( $export['plan_accion'] )
        || false === strtotime( (string) $export['generated_at'] ) ) {
        return array();
    }
    return $export;
}

/**
 * Protected download URL, independent of the report-execution AJAX call.
 *
 * @return string
 */
function taxocommerce_insights_plan_json_download_url() {
    // This URL is also returned through JSON and assigned via JS .attr().
    // wp_nonce_url() HTML-escapes ampersands, making the nonce parameter
    // unreadable after direct assignment to href. Escape at HTML output only.
    return add_query_arg(
        array(
            'action'   => 'taxocommerce_insights_plan_json_download',
            '_wpnonce' => wp_create_nonce( 'taxocommerce_insights_plan_json_download' ),
        ),
        admin_url( 'admin-post.php' )
    );
}

/**
 * Download the last saved plan without running any SEO analysis.
 *
 * @return void
 */
function taxocommerce_insights_plan_json_download() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'No tienes permisos suficientes.', 'taxocommerce-google-bing' ), '', array( 'response' => 403 ) );
    }
    check_admin_referer( 'taxocommerce_insights_plan_json_download' );

    $export = taxocommerce_insights_last_plan_export();
    if ( empty( $export ) ) {
        wp_die( esc_html__( 'Ningún informe cargado.', 'taxocommerce-google-bing' ), '', array( 'response' => 404 ) );
    }

    $json = wp_json_encode( $export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
    if ( ! is_string( $json ) ) {
        wp_die( esc_html__( 'No se pudo preparar el JSON del plan de acción.', 'taxocommerce-google-bing' ), '', array( 'response' => 500 ) );
    }

    $stamp = gmdate( 'YmdHis', strtotime( (string) $export['generated_at'] ) );
    nocache_headers();
    header( 'Content-Type: application/json; charset=utf-8' );
    header( 'X-Content-Type-Options: nosniff' );
    header( 'Content-Disposition: attachment; filename="taxocommerce-plan-de-accion-' . $stamp . '.json"' );
    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- This is a JSON attachment, not HTML. All values use the existing sanitized export contract.
    echo $json;
    exit;
}
add_action( 'admin_post_taxocommerce_insights_plan_json_download', 'taxocommerce_insights_plan_json_download' );

/**
 * Read the last generated JSON snapshot of a single specialist report.
 * This is a cached presentation snapshot, not a fresh provider query.
 *
 * @param string $report Specialist report key.
 * @param int    $days   Reporting period.
 * @return array
 */
function taxocommerce_insights_last_report_export( $report, $days ) {
    $report = sanitize_key( $report );
    $days   = absint( $days );
    if ( 'directrices' === $report || ! in_array( $days, array( 28, 60, 90 ), true )
        || ! array_key_exists( $report, taxocommerce_google_bing_analysis_reports() ) ) {
        return array();
    }
    $stored = get_option( 'taxocommerce_insights_report_' . $report . '_' . $days, array() );
    if ( ! is_array( $stored )
        || 'taxocommerce-insights-report' !== ( $stored['schema']['name'] ?? '' )
        || $report !== ( $stored['report'] ?? '' )
        || $days !== ( $stored['period_days'] ?? 0 )
        || empty( $stored['generated_at'] ) || ! isset( $stored['html'] ) ) {
        return array();
    }
    return $stored;
}

/**
 * Download link for a previously generated specialist report.
 *
 * @param string $report Report key.
 * @param int    $days   Reporting period.
 * @return string
 */
function taxocommerce_insights_report_json_download_url( $report, $days ) {
    // Return an unescaped URL for both AJAX JSON and the initial page.
    // Callers writing HTML attributes must continue to use esc_url().
    return add_query_arg(
        array(
            'action'   => 'taxocommerce_insights_report_json_download',
            'report'   => sanitize_key( $report ),
            'days'     => absint( $days ),
            '_wpnonce' => wp_create_nonce( 'taxocommerce_insights_report_json_download' ),
        ),
        admin_url( 'admin-post.php' )
    );
}

/**
 * Return only the saved report. The JSON preserves the generated HTML and its
 * plain-text reading for clients which cannot render WordPress markup.
 *
 * @return void
 */
function taxocommerce_insights_report_json_download() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'No tienes permisos suficientes.', 'taxocommerce-google-bing' ), '', array( 'response' => 403 ) );
    }
    check_admin_referer( 'taxocommerce_insights_report_json_download' );
    // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Nonce verified above.
    $report = isset( $_GET['report'] ) ? sanitize_key( wp_unslash( $_GET['report'] ) ) : '';
    $days   = isset( $_GET['days'] ) ? absint( wp_unslash( $_GET['days'] ) ) : 28;
    // phpcs:enable WordPress.Security.NonceVerification.Recommended
    $export = taxocommerce_insights_last_report_export( $report, $days );
    if ( ! $export ) {
        wp_die( esc_html__( 'Ningún informe cargado.', 'taxocommerce-google-bing' ), '', array( 'response' => 404 ) );
    }
    $json = wp_json_encode( $export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
    if ( ! is_string( $json ) ) {
        wp_die( esc_html__( 'No se pudo preparar el JSON.', 'taxocommerce-google-bing' ), '', array( 'response' => 500 ) );
    }
    nocache_headers();
    header( 'Content-Type: application/json; charset=utf-8' );
    header( 'X-Content-Type-Options: nosniff' );
    header( 'Content-Disposition: attachment; filename="taxocommerce-' . $report . '-' . $days . 'd-' . gmdate( 'YmdHis', strtotime( $export['generated_at'] ) ) . '.json"' );
    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Encoded JSON attachment, not HTML.
    echo $json;
    exit;
}
add_action( 'admin_post_taxocommerce_insights_report_json_download', 'taxocommerce_insights_report_json_download' );

/**
 * This AJAX handler renders the merged Insights view only on click. All other
 * specialist reports retain their original renderer. Never create work when
 * the legacy plan has no tasks with executable/validated destinations.
 *
 * @return void
 */
function taxocommerce_insights_analysis_ajax() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => 'No tienes permisos suficientes.' ), 403 );
        return;
    }
    check_ajax_referer( 'taxocommerce_insights_analysis', 'nonce' );
    $report = isset( $_POST['report'] ) ? sanitize_key( wp_unslash( $_POST['report'] ) ) : '';
    $days = isset( $_POST['days'] ) ? absint( wp_unslash( $_POST['days'] ) ) : 28;
    $days = in_array( $days, array( 28, 60, 90 ), true ) ? $days : 28;
    $reports = taxocommerce_google_bing_analysis_reports();
    if ( ! function_exists( 'seo_analista_lazy_render_partial' ) ||
        ! isset( $reports[ $report ] ) ) {
        wp_send_json_error( array( 'message' => 'Informe no disponible.' ), 400 );
        return;
    }
    $started = microtime( true );
    $plan_export = null;
    $competitive_export = null;
    ob_start();
    try {
        // Original commercial and specialist reports, without changing SEO.
        // The old SEO monolith defines the same global lazy renderer. Never
        // dispatch Competition through it: Insights owns the market report.
        if ( 'competencia' === $report ) {
            // "Crear informe" is the explicit refresh. Calculate, persist and
            // display the same market evidence in this single AJAX request.
            // Do not require a second form submission or follow a stale nonce.
            $competitive_export = taxocommerce_insights_competitive_build_report( $days );
            taxocommerce_insights_competitive_save_report( $competitive_export );
            taxocommerce_insights_competitive_render_panel( $days );
        } else {
            seo_analista_lazy_render_partial( $report, $days );
        }

        if ( 'directrices' === $report ) {
            // Market view: external demand, commercial outcomes and GA4 assistant
            // referrals. Preserve the legacy editorial engine for specialist
            // reports, but never run it as the executive action plan.
            $google = function_exists( 'seo_analista_google_snapshot' )
                ? (array) seo_analista_google_snapshot( $days, false )
                : array();
            $market = taxocommerce_insights_market_plan( $days, $google );
            $plan = (array) ( $market['plan'] ?? array() );
            taxocommerce_insights_market_render( $market );
            $plan_export = taxocommerce_insights_action_plan_export( $plan, $days );
        }
        $html = ob_get_clean();
    } catch ( Throwable $error ) {
        ob_end_clean();
        wp_send_json_error( array(
            'message' => 'No se pudo generar el informe: ' . sanitize_text_field( $error->getMessage() ),
        ), 500 );
        return;
    }
    $response = array(
        'report' => $report,
        'html' => $html,
        'seconds' => round( microtime( true ) - $started, 2 ),
        'version' => defined( 'SEO_ANALISTA_VERSION' ) ? SEO_ANALISTA_VERSION : '',
    );
    if ( 'directrices' !== $report ) {
        $definition = $reports[ $report ];
        $saved_report = array(
            'schema'       => array( 'name' => 'taxocommerce-insights-report', 'version' => 1 ),
            'report'       => $report,
            'title'        => (string) ( $definition['label'] ?? '' ),
            'description'  => (string) ( $definition['description'] ?? '' ),
            'generated_at' => gmdate( 'c' ),
            'period_days'  => $days,
            'html'         => $html,
            'text'         => trim( wp_strip_all_tags( $html ) ),
        );
        if ( 'competencia' === $report && is_array( $competitive_export ) ) {
            // A single downloadable JSON now contains the actual structured
            // Merchant/Trends/GSC evidence, not just rendered admin HTML.
            $saved_report['competitive_data'] = $competitive_export;
        }
        update_option( 'taxocommerce_insights_report_' . $report . '_' . $days, $saved_report, false );
        if ( taxocommerce_insights_last_report_export( $report, $days ) === $saved_report ) {
            $response['report_saved_at_display'] = wp_date( 'd/m/Y H:i', strtotime( $saved_report['generated_at'] ) );
            $response['report_download_url'] = taxocommerce_insights_report_json_download_url( $report, $days );
        }
    }
    if ( null !== $plan_export ) {
        $response['plan_export'] = $plan_export;

        // One per-site snapshot, excluded from autoload. The full analysis
        // stays on demand and this export is not mixed with other reports.
        update_option( 'taxocommerce_insights_last_plan_json', $plan_export, false );
        $stored = taxocommerce_insights_last_plan_export();
        if ( $stored === $plan_export ) {
            $response['plan_saved_at_display'] = wp_date(
                'd/m/Y H:i',
                strtotime( (string) $stored['generated_at'] )
            );
        }
    }
    wp_send_json_success( $response );
}
add_action( 'wp_ajax_taxocommerce_insights_analysis', 'taxocommerce_insights_analysis_ajax' );
