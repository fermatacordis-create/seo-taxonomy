<?php
/**
 * Read-only JSON export of the same structural anomalies shown by Core > Content.
 *
 * The normal report remains the only source of detection logic. Each rendered
 * finding section contributes its complete human-readable evidence here; no
 * second set of SQL rules or fixed category IDs is maintained.
 *
 * @package TaxoCommerceCore
 */
defined( 'ABSPATH' ) || exit;

add_action( 'admin_post_taxocommerce_core_anomalies_full_json_export', 'taxocommerce_core_anomalies_full_json_export_handler' );

/**
 * Extract a complete, plain-text section from the actual report output.
 * HTML attributes, forms, nonces, buttons and links are not included in JSON.
 */
function taxocommerce_core_anomalies_full_json_normalize_section( $html ) {
    $html = (string) $html;
    $heading = '';
    if ( preg_match( '/<h[2-6]\\b[^>]*>(.*?)<\\/h[2-6]>/is', $html, $match ) ) {
        $heading = trim( html_entity_decode( wp_strip_all_tags( $match[1] ), ENT_QUOTES, 'UTF-8' ) );
    }

    // Suppress executable and styling elements before converting report to text.
    $html = preg_replace( '/<(script|style)\\b[^>]*>.*?<\\/\\1>/is', '', $html );
    $html = preg_replace( '/<input\\b[^>]*>/is', '', $html );
    $html = preg_replace( '/<button\\b[^>]*>.*?<\\/button>/is', '', $html );
    $html = preg_replace( '/<\\/?(?:br|div|p|li|tr|td|th|ul|ol|table|h[2-6])\\b[^>]*>/i', "\n", $html );
    $plain = html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES, 'UTF-8' );
    $plain = str_replace( "\xc2\xa0", ' ', $plain );
    $lines = preg_split( '/\\R/u', $plain );

    $evidence = array();
    foreach ( (array) $lines as $line ) {
        $line = trim( preg_replace( '/[ \\t]+/u', ' ', (string) $line ) );
        if ( '' === $line || $line === $heading ) {
            continue;
        }
        $evidence[] = $line;
    }

    // Preserve every line: no sample limit, no screen-pagination limit.
    return array(
        'section'        => $heading,
        'code'           => sanitize_title( $heading ),
        'evidence_lines' => $evidence,
    );
}

/**
 * Capture only sections that the existing Core report considers anomalous.
 * No capture is performed during ordinary page rendering.
 */
function taxocommerce_core_anomalies_full_json_capture_section( $html ) {
    if ( ! isset( $GLOBALS['taxocommerce_core_anomalies_full_json_sections'] )
        || ! is_array( $GLOBALS['taxocommerce_core_anomalies_full_json_sections'] ) ) {
        return;
    }
    $section = taxocommerce_core_anomalies_full_json_normalize_section( $html );
    if ( '' !== $section['section'] ) {
        $GLOBALS['taxocommerce_core_anomalies_full_json_sections'][] = $section;
    }
}

/** Build the export by executing the exact same report as the admin screen. */
function taxocommerce_core_anomalies_full_json_build_document() {
    // The caller already verified the canonical hierarchy is configured.
    $GLOBALS['taxocommerce_core_anomalies_full_json_sections'] = array();
    ob_start();
    try {
        taxocommerce_core_anomalies_render_report();
    } finally {
        ob_end_clean();
    }
    $sections = $GLOBALS['taxocommerce_core_anomalies_full_json_sections'];
    unset( $GLOBALS['taxocommerce_core_anomalies_full_json_sections'] );

    return array(
        'schema' => array(
            'name'    => 'taxocommerce-core-anomalies-complete',
            'version' => 1,
        ),
        'generated_at' => gmdate( 'c' ),
        'source' => array(
            'module'         => 'taxocommerce-core',
            'view'           => 'content/anomalies',
            'plugin_version' => TAXOCOMMERCE_CORE_VERSION,
            'home_url'       => home_url( '/' ),
            'read_only'      => true,
        ),
        'summary' => array(
            'sections_with_findings' => count( $sections ),
            'note' => 'All finding sections reported by Core; evidence_lines are the full visible diagnostics, not independent or estimated audit rules.',
        ),
        'sections' => $sections,
    );
}

/** Authenticated, non-mutating JSON download. */
function taxocommerce_core_anomalies_full_json_export_handler() {
    if ( ! current_user_can( apply_filters( 'taxocommerce_core_manage_capability', 'manage_options' ) ) ) {
        wp_die( esc_html__( 'No tienes permisos para descargar las anomalías.', 'taxocommerce-core' ) );
    }
    check_admin_referer( 'taxocommerce_core_anomalies_full_json_export' );

    if ( ! taxocommerce_core_anomalies_table_exists( 'seo_relations' ) ) {
        wp_die( esc_html__( 'No está disponible la tabla SEO de relaciones.', 'taxocommerce-core' ) );
    }

    $configured_nodes = 0;
    foreach ( array( 'cluster', 'hub_primary', 'hub_secondary', 'landing' ) as $role ) {
        $configured_nodes += count( TaxoCommerce_Core_Hierarchy_Classic::role_pages( $role ) );
    }
    if ( 0 === $configured_nodes ) {
        wp_die( esc_html__( 'La arquitectura SEO todavía no está configurada.', 'taxocommerce-core' ) );
    }

    $payload = taxocommerce_core_anomalies_full_json_build_document();
    $json = wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
    if ( false === $json ) {
        wp_die( esc_html__( 'No se pudo codificar el informe de anomalías.', 'taxocommerce-core' ) );
    }

    nocache_headers();
    header( 'X-Content-Type-Options: nosniff' );
    header( 'Content-Type: application/json; charset=utf-8' );
    header( 'Content-Disposition: attachment; filename="taxocommerce-core-anomalias-' . gmdate( 'Ymd-His' ) . '.json"' );
    echo $json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Pre-encoded application/json attachment, never rendered as HTML.
    exit;
}
