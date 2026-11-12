<?php
/**
 * Capa de orquestacion Google para Analista.
 *
 * Google Intelligence conserva sus conectores y motores como backend, pero
 * Analista pasa a ser la capa visible que resume Search Console, Analytics y
 * Trends y los convierte en contexto de decision.
 */

defined('ABSPATH') || exit;

if (!function_exists('seo_analista_google_snapshot')) {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
    function seo_analista_google_snapshot($days = 28, $include_results = false) {
        $days = seo_analista_days($days);
        $payload = function_exists('seo_google_opportunity_build')
            ? (array) seo_google_opportunity_build($days, (bool) $include_results)
            : array();

        $sources = (array) ($payload['sources'] ?? array());
        // GA4 has its own processing calendar and must not be limited by stale GSC SQL data.
        $ga4 = seo_analista_ga4_snapshot($days);

        // La fuente Analytics de Google Intelligence puede figurar como no
        // comprobada aunque GA4 responda. Analista usa la comprobacion real.
        $sources['analytics'] = array(
            'connected' => !empty($ga4['available']),
            'detail' => !empty($ga4['available'])
                ? 'GA4 responde: ' . number_format_i18n((int) ($ga4['sessions'] ?? 0)) . ' sesiones entre ' . ($ga4['period']['start'] ?? '') . ' y ' . ($ga4['period']['end'] ?? '') . '.'
                : (!empty($ga4['error']) ? (string) $ga4['error'] : 'GA4 no disponible.'),
        );

        if (!isset($sources['search_console'])) {
            $sources['search_console'] = array(
                'connected' => seo_analista_is_ready(),
                'detail' => seo_analista_is_ready() ? 'Datos locales disponibles.' : 'Sin datos disponibles.',
            );
        }

        if (!isset($sources['trends'])) {
            $market = function_exists('seo_google_trends_market_summary')
                ? (array) seo_google_trends_market_summary(50)
                : array();
            $sources['trends'] = array(
                'connected' => function_exists('seo_google_trends_market_summary'),
                'detail' => $market ? count($market) . ' senales almacenadas.' : 'Sin senales de mercado utilizables.',
            );
        }

        return array(
            'available' => !empty($payload),
            'days' => $days,
            'sources' => $sources,
            'summary' => (array) ($payload['summary'] ?? array()),
            'actions' => (array) ($payload['rows'] ?? array()),
            'market' => (array) ($payload['market'] ?? array()),
            'guidance' => (array) ($payload['guidance'] ?? array()),
            'results' => (array) ($payload['results'] ?? array()),
            'ga4' => $ga4,
        );
    }
}

if (!function_exists('seo_analista_google_source_health')) {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
    function seo_analista_google_source_health($days = 28) {
        $snapshot = seo_analista_google_snapshot($days, false);
        $sources = (array) ($snapshot['sources'] ?? array());
        $out = array();

        foreach (array('search_console' => 'Search Console', 'analytics' => 'Google Analytics', 'trends' => 'Google Trends') as $key => $label) {
            $row = (array) ($sources[$key] ?? array());
            $connected = !empty($row['connected']);
            $detail = trim((string) ($row['detail'] ?? ''));
            $state = $connected ? 'ok' : 'pending';

            if ('trends' === $key && $connected && preg_match('/(404|no se pudo|sin senales|0 senales)/i', $detail)) {
                $state = 'partial';
            }
            if ('search_console' === $key && function_exists('seo_google_get_summary_metrics')) {
                $property_id = seo_analista_resolve_property_id();
                $metrics = $property_id ? seo_google_get_summary_metrics($property_id, $days) : array();
                if ($metrics && ($metrics['source'] ?? '') !== 'gsc_property_web_final') {
                    $state = 'partial';
                    $detail = 'Datos diagnósticos locales; ' . (int) ($metrics['days_available'] ?? 0)
                        . ' de ' . (int) ($metrics['days_expected'] ?? 0) . ' días con totales de propiedad.';
                }
            }

            $out[$key] = array(
                'label' => $label,
                'state' => $state,
                'connected' => $connected,
                'detail' => $detail,
            );
        }

        return $out;
    }
}
