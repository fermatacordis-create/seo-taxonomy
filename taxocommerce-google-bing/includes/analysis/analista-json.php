<?php
/**
 * JSON maestro de Analista.
 *
 * Es el unico export necesario para revisar la capa ejecutiva: donde estamos,
 * comparacion y hacia donde vamos. No incluye credenciales ni configuracion
 * tecnica sensible de las conexiones.
 */

defined('ABSPATH') || exit;

if (!function_exists('seo_analista_json_clean_value')) {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
    function seo_analista_json_clean_value($value, $depth = 0) {
        if ($depth > 8) return null;
        if (is_null($value) || is_bool($value) || is_int($value) || is_float($value)) return $value;
        if (is_string($value)) return sanitize_text_field($value);
        if (is_array($value)) {
            $out = array();
            foreach ($value as $key => $item) {
                $safe_key = is_int($key) ? $key : sanitize_key((string) $key);
                $clean = seo_analista_json_clean_value($item, $depth + 1);
                if (null !== $clean) $out[$safe_key] = $clean;
            }
            return $out;
        }
        if (is_object($value)) return seo_analista_json_clean_value((array) $value, $depth + 1);
        return null;
    }
}

if (!function_exists('seo_analista_json_query_row')) {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
    function seo_analista_json_query_row(array $row) {
        return array(
            'query' => seo_analista_clean_query($row['query_text'] ?? ''),
            'clicks' => (float) ($row['clicks'] ?? 0),
            'impressions' => (float) ($row['impressions'] ?? 0),
            'pages' => (int) ($row['pages'] ?? 0),
            'ctr' => (float) ($row['ctr'] ?? 0),
            'position' => (float) ($row['position'] ?? 0),
            'intent' => seo_analista_intent($row['query_text'] ?? ''),
        );
    }
}

if (!function_exists('seo_analista_json_page_row')) {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
    function seo_analista_json_page_row(array $row) {
        return array(
            'url' => esc_url_raw((string) ($row['page_url'] ?? '')),
            'type' => seo_analista_page_type($row['page_url'] ?? ''),
            'clicks' => (float) ($row['clicks'] ?? 0),
            'impressions' => (float) ($row['impressions'] ?? 0),
            'queries' => (int) ($row['queries'] ?? 0),
            'ctr' => (float) ($row['ctr'] ?? 0),
            'position' => (float) ($row['position'] ?? 0),
        );
    }
}

if (!function_exists('seo_analista_build_json_export')) {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
    function seo_analista_build_json_export($days = 28, $requested_section = '', $requested_part = '') {
        $days = seo_analista_days($days);
        // A requested fragment only evaluates its own sources. Calling without a
        // fragment retains the full historic JSON contract for existing clients.
        $wants = static function ($section, $parts = array()) use ($requested_section, $requested_part) {
            if ('' === $requested_section) return true;
            if ($section !== $requested_section) return false;
            if ('' === $requested_part) return true;
            return in_array($requested_part, (array) $parts, true);
        };

        $data = ($wants('meta') || $wants('donde_estamos') || $wants('comparacion', array('tracked_keywords', 'bing_google_queries')))
            ? seo_analista_get_data($days) : array();
        $google = $wants('donde_estamos') ? seo_analista_google_snapshot($days, false) : array();
        $evolution = $wants('donde_estamos') ? seo_analista_evolution_snapshot($days) : array();
        $competition = $wants('comparacion', array('competition')) ? seo_analista_competition_snapshot(100) : array();
        $needs_plan = $wants('estrategia', array('plan')) || $wants('hacia_donde_vamos');
        $plan = $needs_plan ? seo_analista_decision_plan($days, 40) : array();
        $plan_summary = $needs_plan ? seo_analista_plan_summary($plan) : array();
        $search = $wants('donde_estamos') ? seo_analista_internal_search_snapshot($days, 50) : array();
        $suppliers = $wants('comparacion', array('suppliers')) ? seo_analista_supplier_snapshot(50) : array();
        $market = $wants('comparacion', array('google_trends')) ? seo_analista_market_signals(80) : array();
        $literature = ($wants('directrices', array('literatura')) && function_exists('seo_analista_literature_work'))
            ? seo_analista_literature_work($days, 100) : array();
        $structure_work = ($wants('directrices', array('estructura')) && function_exists('seo_analista_structure_work'))
            ? seo_analista_structure_work($days, 120) : array();
        $trend_work = ($wants('directrices', array('tendencias')) && function_exists('seo_analista_trend_work'))
            ? seo_analista_trend_work($days, 100) : array();
        $search_acceleration = ($wants('directrices', array('search_console_acceleration')) && function_exists('seo_analista_search_acceleration'))
            ? seo_analista_search_acceleration($days, 80) : array();
        $catalog_guidance = $wants('comparacion', array('catalog_guidance')) ? seo_analista_catalog_guidance($days, 30) : array();
        $source_health = $wants('sources')
            ? (function_exists('seo_analista_source_health_all') ? seo_analista_source_health_all($days) : seo_analista_google_source_health($days))
            : array();
        $bing = ($wants('comparacion', array('bing', 'bing_google_queries')) && function_exists('seo_analista_bing_snapshot'))
            ? seo_analista_bing_snapshot($days, 50) : array();
        $bing_google = ($wants('comparacion', array('bing_google_queries')) && function_exists('seo_analista_bing_google_query_compare'))
            ? seo_analista_bing_google_query_compare((array) ($data['queries'] ?? array()), (array) ($bing['top_queries'] ?? array()), 40)
            : array();
        $catalog_structure = $wants('donde_estamos')
            ? seo_analista_catalog_structure_snapshot((array) ($data['pages'] ?? array()))
            : array();
        $architecture_advice = ($wants('arquitectura') && function_exists('seo_analista_architecture_advice'))
            ? seo_analista_architecture_advice($days, 100) : array();
        $tracked_keywords = $wants('comparacion', array('tracked_keywords'))
            ? seo_analista_tracked_keyword_snapshot((array) ($data['queries'] ?? array()), (array) ($data['previous_queries'] ?? array()))
            : array();
        $campaigns = ($wants('campanas') && function_exists('seo_analista_campaign_proposals'))
            ? seo_analista_campaign_proposals($days, 12, false)
            : array('available'=>false,'proposals'=>array(),'blocked_by_price'=>array());
        $task_contract = $wants('estrategia', array('plan'))
            ? (function_exists('seo_analista_task_contract') ? array_map('seo_analista_task_contract', $plan) : $plan)
            : array();
        $scheduler_health = $wants('operational_health') && function_exists('seo_analista_action_scheduler_health')
            ? seo_analista_action_scheduler_health()
            : array('available'=>false,'state'=>'unknown','overdue'=>0);
        $validation_tests = $wants('estrategia', array('validation'))
            ? (function_exists('seo_analista_383_self_tests')
                ? seo_analista_383_self_tests()
                : (function_exists('seo_analista_382_self_tests')
                    ? seo_analista_382_self_tests()
                    : (function_exists('seo_analista_381_self_tests') ? seo_analista_381_self_tests() : array())))
            : array();
        $gate_summary = ($wants('estrategia', array('plan')) && function_exists('seo_analista_action_gate_summary'))
            ? seo_analista_action_gate_summary($plan) : array();
        $plan_consistency = ($wants('estrategia', array('plan')) && function_exists('seo_analista_plan_consistency_report'))
            ? seo_analista_plan_consistency_report($plan) : array();

        $queries = array();
        foreach (array_slice((array) ($data['queries'] ?? array()), 0, 120) as $row) {
            if (!seo_analista_query_is_actionable($row['query_text'] ?? '')) continue;
            $queries[] = seo_analista_json_query_row((array) $row);
        }

        $pages = array();
        foreach (array_slice((array) ($data['pages'] ?? array()), 0, 80) as $row) {
            $pages[] = seo_analista_json_page_row((array) $row);
        }

        $payload = array(
            'schema' => array(
                'name' => 'seo-analista-unificado',
                'version' => 9,
            ),
            'generated_at' => gmdate('c'),
            'site' => array(
                'home_url' => home_url('/'),
                'plugin_version' => defined('TAXOCOMMERCE_GOOGLE_BING_VERSION') ? TAXOCOMMERCE_GOOGLE_BING_VERSION : '',
                'analista_version' => defined('SEO_ANALISTA_VERSION') ? SEO_ANALISTA_VERSION : '',
            ),
            'period' => (array) ($data['period'] ?? array()),
            'donde_estamos' => array(
                'ready' => !empty($data['ready']),
                'visibility_index' => (float) ($data['visibility_index'] ?? 0),
                'previous_visibility_index' => (float) ($data['previous_visibility_index'] ?? 0),
                'current' => (array) ($data['current'] ?? array()),
                'previous' => (array) ($data['previous'] ?? array()),
                'distribution' => (array) ($data['distribution'] ?? array()),
                'previous_distribution' => (array) ($data['previous_distribution'] ?? array()),
                'movement' => (array) ($evolution['movement'] ?? array()),
                'intent_distribution' => (array) ($data['intent_distribution'] ?? array()),
                'page_types' => (array) ($data['page_types'] ?? array()),
                'catalog_structure' => $catalog_structure,
                'ga4' => (array) ($google['ga4'] ?? array()),
                'internal_search' => array(
                    'available' => !empty($search['available']),
                    'total' => (int) ($search['total'] ?? 0),
                    'unique' => (int) ($search['unique'] ?? 0),
                    'zero_results' => (int) ($search['zero_results'] ?? 0),
                    'top' => array_slice((array) ($search['top'] ?? array()), 0, 30),
                ),
                'top_queries' => $queries,
                'top_pages' => $pages,
            ),
            'directrices' => array(
                'summary' => array(
                    'literatura' => count($literature),
                    'estructura' => count($structure_work),
                    'tendencias' => count($trend_work),
                    'aceleraciones_search_console' => count($search_acceleration),
                ),
                'literatura' => array_slice($literature, 0, 100),
                'estructura' => array_slice($structure_work, 0, 120),
                'tendencias' => array_slice($trend_work, 0, 100),
                'search_console_acceleration' => array_slice($search_acceleration, 0, 80),
            ),
            'comparacion' => array(
                'tracked_keywords' => $tracked_keywords,
                'competition' => $competition,
                'google_trends' => array_slice((array) $market, 0, 40),
                'bing' => array(
                    'available' => !empty($bing['available']),
                    'connected' => !empty($bing['connected']),
                    'configured' => !empty($bing['configured']),
                    'latest_date' => (string) ($bing['latest_date'] ?? ''),
                    'traffic' => (array) ($bing['traffic'] ?? array()),
                    'crawl' => (array) ($bing['crawl'] ?? array()),
                    'top_queries' => array_slice((array) ($bing['top_queries'] ?? array()), 0, 30),
                    'top_pages' => array_slice((array) ($bing['top_pages'] ?? array()), 0, 30),
                    'errors' => (array) ($bing['errors'] ?? array()),
                ),
                'bing_google_queries' => $bing_google,
                'catalog_guidance' => array_slice((array) ($catalog_guidance['items'] ?? array()), 0, 30),
                'suppliers' => array(
                    'available' => !empty($suppliers['available']),
                    'total' => (int) ($suppliers['total'] ?? 0),
                    'providers' => (int) ($suppliers['providers'] ?? 0),
                    'published_links' => (int) ($suppliers['published_links'] ?? 0),
                    'rows' => array_slice((array) ($suppliers['rows'] ?? array()), 0, 30),
                    'issues' => array_slice((array) ($suppliers['issues'] ?? array()), 0, 15),
                ),
            ),
            'estrategia' => array(
                'objetivos' => array('autoridad', 'visitas', 'ventas'),
                'regla' => 'task_decision es la única fuente de verdad para bucket, acción primaria, intervención y bloqueo. HACER_AHORA exige acción ejecutable con verbo, objeto, destino y responsable; si no existe, se reclasifica sin fabricar trabajo.',
                'summary' => $plan_summary,
                'gate_summary' => $gate_summary,
                'plan_consistency' => $plan_consistency,
                'plan_accion' => $plan,
                'geo_recommendations' => function_exists('taxocommerce_google_bing_geo_recommendations') ? taxocommerce_google_bing_geo_recommendations($plan, 5) : array(),
                'task_contract' => $task_contract,
                'validation' => array(
                    'version' => '3.8.3',
                    'settings' => function_exists('seo_analista_validation_settings') ? seo_analista_validation_settings() : array(),
                    'tests' => $validation_tests,
                ),
            ),
            'campanas' => $campaigns,
            'arquitectura' => $architecture_advice,
            'hacia_donde_vamos' => array(
                'summary' => $plan_summary,
                'plan' => $plan,
            ),
            'sources' => $source_health,
            'operational_health' => array(
                'action_scheduler' => $scheduler_health,
            ),
            'privacy' => array(
                'credentials_included' => false,
                'tokens_included' => false,
                'property_ids_included' => false,
                'users_customers_orders_included' => false,
                'technical_configuration_included' => false,
            ),
        );

        if ('meta' === $requested_section) {
            return seo_analista_json_clean_value(array_intersect_key($payload, array_flip(array('schema', 'generated_at', 'site', 'period'))));
        }
        if ('' !== $requested_section && array_key_exists($requested_section, $payload)) {
            $section = $payload[$requested_section];
            if ('estrategia' === $requested_section && 'plan' === $requested_part) {
                unset($section['validation']);
                return seo_analista_json_clean_value($section);
            }
            if ('' !== $requested_part && is_array($section) && array_key_exists($requested_part, $section)) {
                return seo_analista_json_clean_value($section[$requested_part]);
            }
            return seo_analista_json_clean_value($section);
        }
        return seo_analista_json_clean_value($payload);
    }
}

if (!function_exists('seo_analista_export_json_url')) {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
    function seo_analista_export_json_url($days = 28) {
        return wp_nonce_url(
            add_query_arg(
                array(
                    'action' => 'seo_analista_export_json',
                    'analista_days' => seo_analista_days($days),
                ),
                admin_url('admin-post.php')
            ),
            'seo_analista_export_json',
            'seo_analista_nonce'
        );
    }
}

if (!function_exists('seo_analista_export_json_handler')) {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
    function seo_analista_export_json_handler() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('No tienes permisos para exportar Analista.', 'taxocommerce-google-bing'));
        }
        check_admin_referer('seo_analista_export_json', 'seo_analista_nonce');

        $days = isset($_GET['analista_days']) ? seo_analista_days(absint(wp_unslash($_GET['analista_days']))) : 28;
        $payload = seo_analista_build_json_export($days);
        $json = wp_json_encode(
            $payload,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR
        );
        if (false === $json) {
            wp_die(esc_html__('No se ha podido generar el JSON de Analista.', 'taxocommerce-google-bing'));
        }

        nocache_headers();
        header('X-Content-Type-Options: nosniff');
        header('Content-Type: application/json; charset=utf-8');
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Download URL already verified by check_admin_referer above; parameter only controls browser presentation.
        $inline = isset($_GET['analista_view_json']) && '1' === sanitize_text_field(wp_unslash($_GET['analista_view_json']));
        header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="seo-analista-unificado-' . gmdate('Ymd-His') . '.json"');
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Raw JSON download generated by wp_json_encode(); HTML escaping would corrupt the payload.
        echo $json;
        exit;
    }
}
