<?php
/**
 * Analista 3.6 - informes parciales bajo demanda.
 *
 * La pantalla principal no ejecuta calculos SEO pesados. Cada bloque se
 * genera exclusivamente cuando el administrador pulsa su boton y llega una
 * peticion AJAX autenticada.
 */

defined('ABSPATH') || exit;

if (!function_exists('seo_analista_lazy_reports')) {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
    function seo_analista_lazy_reports() {
        return array(
            'directrices' => array(
                'label' => 'Qué hacer',
                'description' => 'Diagnóstico comercial sencillo: visitas, compras y siguientes pasos.',
            ),
            'resumen' => array(
                'label' => 'Resumen',
                'description' => 'Visibilidad, evolución, GA4, búsqueda interna y arquitectura general.',
            ),
            'search_console' => array(
                'label' => 'Search Console',
                'description' => 'Consultas, páginas, posiciones, distribución y movimiento orgánico.',
            ),
            'contenido' => array(
                'label' => 'Contenido',
                'description' => 'Posts, páginas y productos que conviene mejorar o impulsar.',
            ),
            'estructura' => array(
                'label' => 'Estructura',
                'description' => 'Categorías, hubs y clusters que necesitan literatura, relaciones o impulso.',
            ),
            'catalogo' => array(
                'label' => 'Catálogo',
                'description' => 'Huecos de surtido, familias y oportunidades detectadas contra la demanda.',
            ),
            'tendencias' => array(
                'label' => 'Tendencias',
                'description' => 'Google Trends y aceleraciones de Search Console, solo cuando lo solicitas.',
            ),
            'trabajo' => array(
                'label' => 'Plan de acción',
                'description' => 'Cola validada: máximo 10 HACER AHORA y, cuando falta evidencia, INVESTIGAR / VIGILAR / ESPERAR DATOS.',
            ),
            'competencia' => array(
                'label' => 'Competencia',
                'description' => 'Seguimiento propio y comparación externa cuando exista fuente conectada.',
            ),
            'proveedores' => array(
                'label' => 'Proveedores',
                'description' => 'Estado de feeds, sincronización y avisos que pueden afectar al catálogo.',
            ),
            'fuentes' => array(
                'label' => 'Fuentes',
                'description' => 'Diagnóstico de disponibilidad y calidad de las fuentes de datos.',
            ),
        );
    }
}

if (!function_exists('seo_analista_lazy_render_shell')) {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
    function seo_analista_lazy_render_shell($days = 28) {
        $days = seo_analista_days($days);
        $reports = seo_analista_lazy_reports();
        $nonce = wp_create_nonce('seo_analista_partial_report');
        $ajax_url = admin_url('admin-ajax.php');

        echo '<div class="seo-analista-lazy-intro">';
        echo '<div><strong>Analista ' . esc_html(defined('SEO_ANALISTA_VERSION') ? SEO_ANALISTA_VERSION : '') . '</strong> · carga bajo demanda</div>';
        echo '<p>' . esc_html__( 'Al abrir esta página no se ejecuta ningún informe pesado. Elige un bloque y Analista calculará únicamente ese informe.', 'taxocommerce-google-bing' ) . '</p>';
        echo '</div>';

        echo '<div class="seo-analista-lazy-controls">';
        foreach ($reports as $key => $report) {
            echo '<button type="button" class="button seo-analista-lazy-button" data-report="' . esc_attr($key) . '">';
            echo '<strong>' . esc_html($report['label']) . '</strong>';
            echo '<span>' . esc_html($report['description']) . '</span>';
            echo '</button>';
        }
        echo '</div>';

        echo '<div id="seo-analista-lazy-status" class="seo-analista-lazy-status" aria-live="polite"></div>';
        echo '<div id="seo-analista-lazy-result" class="seo-analista-lazy-result">';
        echo '<div class="seo-analista-lazy-empty">' . '<strong>' . esc_html__( 'Ningún informe cargado.', 'taxocommerce-google-bing' ) . '</strong>' . '<p>' . esc_html__( 'Pulsa uno de los botones anteriores para ejecutar solo ese análisis.', 'taxocommerce-google-bing' ) . '</p>' . '</div>';
        echo '</div>';

        // Register the report UI for the footer: assets must not be printed directly inside admin HTML.
        wp_enqueue_script(
            'seo-analista-lazy',
            plugins_url('assets/analista-lazy.js', __FILE__),
            array('jquery'),
            TAXOCOMMERCE_GOOGLE_BING_VERSION,
            true
        );
        wp_add_inline_script(
            'seo-analista-lazy',
            'window.seoAnalistaLazyConfig = ' . wp_json_encode(
                array(
                    'ajaxUrl' => $ajax_url,
                    'nonce'   => $nonce,
                    'days'    => absint($days),
                ),
                JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
            ) . ';',
            'before'
        );
    }
}

if (!function_exists('seo_analista_lazy_render_directrices')) {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
    function seo_analista_lazy_render_directrices($days) {
        $google = seo_analista_google_snapshot($days, false);
        $ga4 = (array) ($google['ga4'] ?? array());
        $funnel = (array) ($ga4['funnel'] ?? array());
        $sessions = (int) ($ga4['sessions'] ?? 0);
        $purchases = (int) ($ga4['purchases'] ?? 0);
        $carts = (int) ($funnel['add_to_cart'] ?? 0);
        $checkouts = (int) ($funnel['begin_checkout'] ?? 0);
        echo '<section class="seo-analista-section">' . '<h2>' . esc_html__( 'Qué hacer para vender más', 'taxocommerce-google-bing' ) . '</h2>' . '<p>' . esc_html__( 'Directrices basadas en datos disponibles. No confundir visibilidad con compradores ni compras externas de afiliación con WooCommerce.', 'taxocommerce-google-bing' ) . '</p>';
        if (empty($ga4['available'])) {
            echo '<p>' . esc_html__( 'Analytics no está disponible. Comprueba la conexión antes de interpretar ventas o conversión.', 'taxocommerce-google-bing' ) . '</p>' . '</section>';
            return;
        }
        echo '<div class="seo-analista-grid">';
        seo_analista_render_metric_card('Sesiones', number_format_i18n($sessions));
        seo_analista_render_metric_card('Compras WooCommerce', number_format_i18n($purchases));
        seo_analista_render_metric_card('Conversión por sesión', $sessions > 0 ? number_format_i18n(100 * $purchases / $sessions, 2) . '%' : 'N/D');
        seo_analista_render_metric_card('Añadidos al carrito', number_format_i18n($carts));
        seo_analista_render_metric_card('Inicios de compra', number_format_i18n($checkouts));
        echo '</div><ol>';
        if ($sessions === 0) {
            echo '<li>' . '<strong>' . esc_html__( 'Comprobar medición.', 'taxocommerce-google-bing' ) . '</strong>' . ' ' . esc_html__( 'No hay sesiones suficientes para diagnosticar ventas.', 'taxocommerce-google-bing' ) . '</li>';
        } else {
            if ($checkouts > $purchases) echo '<li>' . '<strong>' . esc_html__( 'Revisar el checkout.', 'taxocommerce-google-bing' ) . '</strong>' . ' ' . esc_html__( 'Hay más inicios de compra que compras registradas. Realiza una compra de prueba y comprueba transporte, pagos y eventos GA4; no se puede atribuir el abandono sin datos por usuario.', 'taxocommerce-google-bing' ) . '</li>';
            if ($carts > $checkouts) echo '<li>' . '<strong>' . esc_html__( 'Revisar carrito y gastos de envío.', 'taxocommerce-google-bing' ) . '</strong>' . ' ' . esc_html__( 'Los eventos de carrito superan los inicios de compra. Verifica los costes finales y los enlaces al checkout.', 'taxocommerce-google-bing' ) . '</li>';
            if ($sessions > 0 && $purchases === 0) echo '<li>' . '<strong>' . esc_html__( 'Comprobar intención de compra y precios.', 'taxocommerce-google-bing' ) . '</strong>' . ' ' . esc_html__( 'Hay visitas pero ninguna compra registrada. Contrasta productos visitados con stock, precios y competencia antes de modificar el catálogo.', 'taxocommerce-google-bing' ) . '</li>';
            echo '<li>' . '<strong>' . esc_html__( 'Separar ventas y afiliación.', 'taxocommerce-google-bing' ) . '</strong>' . ' ' . esc_html__( 'Analiza España y mercados internacionales por separado. Los clics hacia proveedores y las comisiones confirmadas requieren su propia medición: no están representados por las compras WooCommerce.', 'taxocommerce-google-bing' ) . '</li>';
            echo '<li>' . '<strong>' . esc_html__( 'Priorizar oportunidades reales.', 'taxocommerce-google-bing' ) . '</strong>' . ' ' . esc_html__( 'Revisa en Plan de acción las tareas con destino validado; no publiques contenido nuevo solo por aumentar impresiones.', 'taxocommerce-google-bing' ) . '</li>';
        }
        if (array_key_exists('add_shipping_info', $funnel) && (int) $funnel['add_shipping_info'] === 0 && (int) ($funnel['add_payment_info'] ?? 0) > 0) {
            echo '<li>' . '<strong>' . esc_html__( 'Verificar eventos de Analytics.', 'taxocommerce-google-bing' ) . '</strong>' . ' ' . esc_html__( 'Se registra información de pago pero no de envío; puede ser una configuración legítima o una medición incompleta.', 'taxocommerce-google-bing' ) . '</li>';
        }
        echo '</ol>' . '<p class="description">' . esc_html__( 'Los recuentos del embudo son eventos agregados, no necesariamente los mismos usuarios. Las directrices son verificaciones, no causas de pérdida demostradas.', 'taxocommerce-google-bing' ) . '</p>' . '</section>';
    }
}

if (!function_exists('seo_analista_lazy_render_search_console')) {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
    function seo_analista_lazy_render_search_console($days) {
        $data = seo_analista_get_data($days);
        $evolution = seo_analista_evolution_snapshot($days);
        $current = (array) ($data['current'] ?? array());
        $previous = (array) ($data['previous'] ?? array());

        if (empty($data['ready'])) {
            echo '<div class="notice notice-warning inline">' . '<p>' . esc_html__( 'Search Console no tiene datos utilizables para este periodo.', 'taxocommerce-google-bing' ) . '</p>' . '</div>';
            return;
        }

        if (function_exists('seo_google_metrics_coverage_notice')) {
            $notice = seo_google_metrics_coverage_notice($current);
            $previous_notice = seo_google_metrics_coverage_notice($previous);
            if ($notice || $previous_notice) echo '<div class="notice notice-warning inline"><p>' . esc_html(($notice ?: $previous_notice) . ' Comparación provisional hasta completar ambos periodos.') . '</p></div>';
        }

        echo '<div class="seo-analista-grid">';
        seo_analista_render_metric_card('Impresiones', number_format_i18n((float) ($current['impressions'] ?? 0), 0), seo_analista_metric_delta($current['impressions'] ?? 0, $previous['impressions'] ?? 0));
        seo_analista_render_metric_card('Clics', number_format_i18n((float) ($current['clicks'] ?? 0), 0), seo_analista_metric_delta($current['clicks'] ?? 0, $previous['clicks'] ?? 0));
        seo_analista_render_metric_card('Posición media', number_format_i18n((float) ($current['position'] ?? 0), 1), seo_analista_metric_delta($current['position'] ?? 0, $previous['position'] ?? 0, true));
        seo_analista_render_metric_card('Consultas', number_format_i18n((int) ($current['queries'] ?? 0)), seo_analista_metric_delta($current['queries'] ?? 0, $previous['queries'] ?? 0));
        seo_analista_render_metric_card('Páginas', number_format_i18n((int) ($current['pages'] ?? 0)), seo_analista_metric_delta($current['pages'] ?? 0, $previous['pages'] ?? 0));
        seo_analista_render_metric_card('CTR', number_format_i18n(((float) ($current['ctr'] ?? 0)) * 100, 2) . '%', seo_analista_metric_delta($current['ctr'] ?? 0, $previous['ctr'] ?? 0));
        echo '</div>';

        seo_analista_render_distribution((array) ($data['distribution'] ?? array()), (array) ($data['previous_distribution'] ?? array()));
        seo_analista_render_movement((array) ($evolution['movement'] ?? array()));
        seo_analista_render_page_types((array) ($data['page_types'] ?? array()));

        $queries = array_slice((array) ($data['queries'] ?? array()), 0, 40);
        if ($queries) {
            echo '<section class="seo-analista-section">' . '<div class="seo-analista-section-head">' . '<div>' . '<h2>' . esc_html__( 'Consultas principales', 'taxocommerce-google-bing' ) . '</h2>' . '<p>' . esc_html__( 'Vista parcial de Search Console. No ejecuta Contenido, Catálogo, Trends ni Competencia.', 'taxocommerce-google-bing' ) . '</p>' . '</div>' . '</div>';
            echo '<div class="seo-analista-table-wrap">' . '<table class="widefat striped">' . '<thead>' . '<tr>' . '<th>' . esc_html__( 'Consulta', 'taxocommerce-google-bing' ) . '</th>' . '<th>' . esc_html__( 'Impresiones', 'taxocommerce-google-bing' ) . '</th>' . '<th>' . esc_html__( 'Clics', 'taxocommerce-google-bing' ) . '</th>' . '<th>' . esc_html__( 'Posición', 'taxocommerce-google-bing' ) . '</th>' . '<th>' . esc_html__( 'Intento', 'taxocommerce-google-bing' ) . '</th>' . '</tr>' . '</thead>' . '<tbody>';
            foreach ($queries as $row) {
                $query = seo_analista_clean_query($row['query_text'] ?? $row['query'] ?? '');
                if ($query === '') continue;
                echo '<tr><td><strong>' . esc_html($query) . '</strong></td><td>' . esc_html(number_format_i18n((float) ($row['impressions'] ?? 0), 0)) . '</td><td>' . esc_html(number_format_i18n((float) ($row['clicks'] ?? 0), 0)) . '</td><td>' . esc_html(number_format_i18n((float) ($row['position'] ?? 0), 1)) . '</td><td>' . esc_html(seo_analista_intent($query)) . '</td></tr>';
            }
            echo '</tbody></table></div></section>';
        }
    }
}

if (!function_exists('seo_analista_lazy_render_summary')) {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
    function seo_analista_lazy_render_summary($days) {
        $data = seo_analista_get_data($days);
        $google = seo_analista_google_snapshot($days, false);
        $evolution = seo_analista_evolution_snapshot($days);
        $search = seo_analista_internal_search_snapshot($days, 40);
        $catalog_structure = seo_analista_catalog_structure_snapshot((array) ($data['pages'] ?? array()));
        seo_analista_render_where_we_are($data, $google, $evolution, $search, $catalog_structure);
    }
}

if (!function_exists('seo_analista_lazy_render_catalog')) {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
    function seo_analista_lazy_render_catalog($days) {
        $guidance = seo_analista_catalog_guidance($days, 30);
        $items = (array) ($guidance['items'] ?? array());

        echo '<div class="seo-analista-grid">';
        seo_analista_render_metric_card('Oportunidades', number_format_i18n(count($items)), '', 'Solo se ha ejecutado el análisis de catálogo/demanda.');
        echo '</div>';

        if (!$items) {
            echo '<div class="notice notice-info inline">' . '<p>' . esc_html__( 'No hay directrices de catálogo disponibles para este periodo.', 'taxocommerce-google-bing' ) . '</p>' . '</div>';
            return;
        }

        echo '<section class="seo-analista-section">' . '<div class="seo-analista-section-head">' . '<div>' . '<h2>' . esc_html__( 'Catálogo y demanda', 'taxocommerce-google-bing' ) . '</h2>' . '<p>' . esc_html__( 'Familias, categorías o huecos de surtido detectados. Este bloque no ejecuta los informes de contenido, estructura ni competencia.', 'taxocommerce-google-bing' ) . '</p>' . '</div>' . '</div>';
        echo '<div class="seo-analista-table-wrap">' . '<table class="widefat striped">' . '<thead>' . '<tr>' . '<th>' . esc_html__( 'Prioridad', 'taxocommerce-google-bing' ) . '</th>' . '<th>' . esc_html__( 'Tipo', 'taxocommerce-google-bing' ) . '</th>' . '<th>' . esc_html__( 'Oportunidad', 'taxocommerce-google-bing' ) . '</th>' . '<th>' . esc_html__( 'Decisión', 'taxocommerce-google-bing' ) . '</th>' . '<th>' . esc_html__( 'Impresiones', 'taxocommerce-google-bing' ) . '</th>' . '<th>' . esc_html__( 'Posición', 'taxocommerce-google-bing' ) . '</th>' . '<th>' . esc_html__( 'Productos', 'taxocommerce-google-bing' ) . '</th>' . '<th>' . esc_html__( 'Lectura', 'taxocommerce-google-bing' ) . '</th>' . '</tr>' . '</thead>' . '<tbody>';
        foreach ($items as $row) {
            echo '<tr>';
            echo '<td>' . wp_kses_post(seo_analista_score_badge((int) ($row['score'] ?? 0))) . '</td>';
            echo '<td>' . esc_html((string) ($row['kind'] ?? '')) . '</td>';
            echo '<td><strong>' . esc_html((string) ($row['label'] ?? '')) . '</strong></td>';
            echo '<td>' . esc_html((string) ($row['decision'] ?? '')) . '</td>';
            echo '<td>' . esc_html(number_format_i18n((float) ($row['impressions'] ?? 0), 0)) . '</td>';
            echo '<td>' . esc_html(number_format_i18n((float) ($row['position'] ?? 0), 1)) . '</td>';
            echo '<td>' . esc_html(isset($row['products']) ? number_format_i18n((int) $row['products']) : '—') . '</td>';
            echo '<td>' . esc_html((string) ($row['note'] ?? '')) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table></div></section>';
    }
}

if (!function_exists('seo_analista_lazy_render_competition')) {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
    function seo_analista_lazy_render_competition($days) {
        // The market competition report is manually generated and cached.
        // Do not run legacy rankings adapters or scrapers when opening this tab.
        taxocommerce_insights_competitive_render_panel($days);
    }
}

if (!function_exists('seo_analista_lazy_render_suppliers')) {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
    function seo_analista_lazy_render_suppliers($days) {
        unset($days);
        $suppliers = seo_analista_supplier_snapshot(100);
        echo '<div class="seo-analista-grid">';
        seo_analista_render_metric_card('Proveedores', number_format_i18n((int) ($suppliers['providers'] ?? 0)));
        seo_analista_render_metric_card('Registros', number_format_i18n((int) ($suppliers['total'] ?? 0)));
        seo_analista_render_metric_card('Enlaces publicados', number_format_i18n((int) ($suppliers['published_links'] ?? 0)));
        seo_analista_render_metric_card('Con aviso', number_format_i18n(count((array) ($suppliers['issues'] ?? array()))));
        echo '</div>';

        $rows = (array) ($suppliers['rows'] ?? array());
        if (!$rows) {
            echo '<div class="notice notice-info inline">' . '<p>' . esc_html__( 'No hay datos de proveedores disponibles.', 'taxocommerce-google-bing' ) . '</p>' . '</div>';
            return;
        }

        echo '<section class="seo-analista-section">' . '<div class="seo-analista-section-head">' . '<div>' . '<h2>' . esc_html__( 'Estado de proveedores', 'taxocommerce-google-bing' ) . '</h2>' . '<p>' . esc_html__( 'Este informe consulta únicamente la fuente local de proveedores.', 'taxocommerce-google-bing' ) . '</p>' . '</div>' . '</div>';
        echo '<div class="seo-analista-table-wrap">' . '<table class="widefat striped">' . '<thead>' . '<tr>' . '<th>' . esc_html__( 'Proveedor', 'taxocommerce-google-bing' ) . '</th>' . '<th>' . esc_html__( 'Total', 'taxocommerce-google-bing' ) . '</th>' . '<th>' . esc_html__( 'Vinculados', 'taxocommerce-google-bing' ) . '</th>' . '<th>' . esc_html__( 'Pendientes', 'taxocommerce-google-bing' ) . '</th>' . '<th>' . esc_html__( 'Errores', 'taxocommerce-google-bing' ) . '</th>' . '<th>' . esc_html__( 'Última importación', 'taxocommerce-google-bing' ) . '</th>' . '<th>' . esc_html__( 'Última sincronización', 'taxocommerce-google-bing' ) . '</th>' . '</tr>' . '</thead>' . '<tbody>';
        foreach ($rows as $row) {
            echo '<tr><td><strong>' . esc_html((string) ($row['proveedor'] ?? '')) . '</strong></td><td>' . esc_html(number_format_i18n((int) ($row['total'] ?? 0))) . '</td><td>' . esc_html(number_format_i18n((int) ($row['linked'] ?? 0))) . '</td><td>' . esc_html(number_format_i18n((int) ($row['pending'] ?? 0))) . '</td><td>' . esc_html(number_format_i18n((int) ($row['errors'] ?? 0))) . '</td><td>' . esc_html((string) ($row['last_import'] ?? '—')) . '</td><td>' . esc_html((string) ($row['last_sync'] ?? '—')) . '</td></tr>';
        }
        echo '</tbody></table></div></section>';
    }
}

if (!function_exists('seo_analista_lazy_render_work')) {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
    function seo_analista_lazy_render_work($days) {
        $plan = seo_analista_decision_plan($days, 40);
        $summary = seo_analista_plan_summary($plan);
        $suppliers = seo_analista_supplier_snapshot(40);
        $search = seo_analista_internal_search_snapshot($days, 40);
        $consistency = function_exists('seo_analista_plan_consistency_report')
            ? seo_analista_plan_consistency_report($plan)
            : array('ok'=>true,'issues'=>array());
        if (empty($consistency['ok'])) {
            echo '<div class="notice notice-error inline"><p><strong>Plan de acción incoherente:</strong> ' . esc_html(number_format_i18n(count((array) ($consistency['issues'] ?? array())))) . ' discrepancias entre bucket, decisión o acción ejecutable. No debe darse por válido hasta resolverlas.</p></div>';
        }
        seo_analista_render_roadmap($plan, $summary, $suppliers, $search);
    }
}

if (!function_exists('seo_analista_lazy_render_sources')) {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
    function seo_analista_lazy_render_sources($days) {
        $health = function_exists('seo_analista_source_health_all') ? seo_analista_source_health_all($days) : seo_analista_google_source_health($days);
        $competition = seo_analista_competition_snapshot(20);
        $suppliers = seo_analista_supplier_snapshot(20);
        seo_analista_render_sources($health, $competition, $suppliers);

        if (function_exists('seo_analista_action_scheduler_health')) {
            $scheduler = seo_analista_action_scheduler_health();
            echo '<section class="seo-analista-section">' . '<div class="seo-analista-section-head">' . '<div>' . '<h2>' . esc_html__( 'Frescura operativa · Action Scheduler', 'taxocommerce-google-bing' ) . '</h2>' . '<p>' . esc_html__( 'Se revisa por separado porque una cola vencida puede dejar fuentes desactualizadas sin que el dato parezca erróneo.', 'taxocommerce-google-bing' ) . '</p>' . '</div>' . '</div>';
            echo '<p><strong>Estado:</strong> ' . esc_html(strtoupper((string) ($scheduler['state'] ?? 'unknown'))) . ' · <strong>vencidas detectadas:</strong> ' . esc_html(number_format_i18n(absint($scheduler['overdue'] ?? 0))) . '</p>';
            echo '<p class="description">' . esc_html((string) ($scheduler['detail'] ?? '')) . '</p></section>';
        }

        if (function_exists('seo_analista_383_self_tests') || function_exists('seo_analista_382_self_tests') || function_exists('seo_analista_381_self_tests')) {
            $tests = function_exists('seo_analista_383_self_tests')
                ? seo_analista_383_self_tests()
                : (function_exists('seo_analista_382_self_tests') ? seo_analista_382_self_tests() : seo_analista_381_self_tests());
            echo '<section class="seo-analista-section"><div class="seo-analista-section-head"><div><h2>Pruebas Analista ' . esc_html((string) ($tests['version'] ?? '3.8.3')) . '</h2><p>Incluye T01-T06: coherencia HACER AHORA, acción ejecutable, etiquetas separadas, contadores/JSON, trazabilidad y regresiones.</p></div></div>';
            echo '<p><strong>' . esc_html(number_format_i18n(absint($tests['passed'] ?? 0))) . '/' . esc_html(number_format_i18n(absint($tests['total'] ?? 0))) . '</strong> pruebas superadas.</p>';
            echo '<div class="seo-analista-table-wrap">' . '<table class="widefat striped">' . '<thead>' . '<tr>' . '<th>' . esc_html__( 'Prueba', 'taxocommerce-google-bing' ) . '</th>' . '<th>' . esc_html__( 'Estado', 'taxocommerce-google-bing' ) . '</th>' . '<th>' . esc_html__( 'Regla', 'taxocommerce-google-bing' ) . '</th>' . '</tr>' . '</thead>' . '<tbody>';
            foreach ((array) ($tests['tests'] ?? array()) as $test) {
                echo '<tr><td><code>' . esc_html((string) ($test['code'] ?? '')) . '</code></td><td>' . (!empty($test['passed']) ? '<strong style="color:#0a7a37">OK</strong>' : '<strong style="color:#b42318">FALLO</strong>') . '</td><td>' . esc_html((string) ($test['detail'] ?? '')) . '</td></tr>';
            }
            echo '</tbody></table></div></section>';
        }
    }
}

if (!function_exists('seo_analista_lazy_render_partial')) {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
    function seo_analista_lazy_render_partial($report, $days) {
        switch ($report) {
            case 'directrices':
                seo_analista_lazy_render_directrices($days);
                break;
            case 'resumen':
                seo_analista_lazy_render_summary($days);
                break;
            case 'search_console':
                seo_analista_lazy_render_search_console($days);
                break;
            case 'contenido':
                seo_analista_render_content_view($days);
                break;
            case 'estructura':
                seo_analista_render_structure_view($days);
                break;
            case 'catalogo':
                seo_analista_lazy_render_catalog($days);
                break;
            case 'tendencias':
                seo_analista_render_trends_view($days);
                break;
            case 'trabajo':
                seo_analista_lazy_render_work($days);
                break;
            case 'competencia':
                seo_analista_lazy_render_competition($days);
                break;
            case 'proveedores':
                seo_analista_lazy_render_suppliers($days);
                break;
            case 'fuentes':
                seo_analista_lazy_render_sources($days);
                break;
        }
    }
}

if (!function_exists('seo_analista_ajax_partial_report')) {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
    function seo_analista_ajax_partial_report() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'No tienes permisos suficientes.'), 403);
        }

        check_ajax_referer('seo_analista_partial_report', 'nonce');

        $report = isset($_POST['report']) ? sanitize_key(wp_unslash($_POST['report'])) : '';
        $days = isset($_POST['days']) ? seo_analista_days(absint(wp_unslash($_POST['days']))) : 28;
        $reports = seo_analista_lazy_reports();

        if (!isset($reports[$report])) {
            wp_send_json_error(array('message' => 'Informe parcial no reconocido.'), 400);
        }

        $started = microtime(true);
        ob_start();

        try {
            echo '<div class="seo-analista-partial" data-report="' . esc_attr($report) . '">';
            echo '<div class="seo-analista-partial-head"><h2>' . esc_html($reports[$report]['label']) . '</h2><p>' . esc_html($reports[$report]['description']) . '</p></div>';
            seo_analista_lazy_render_partial($report, $days);
            echo '</div>';
            $html = ob_get_clean();
        } catch (Throwable $e) {
            ob_end_clean();
            wp_send_json_error(array('message' => 'Error en ' . sanitize_text_field((string) $reports[$report]['label']) . ': ' . sanitize_text_field($e->getMessage())), 500);
        }

        wp_send_json_success(array(
            'report' => $report,
            'html' => $html,
            'seconds' => round(microtime(true) - $started, 2),
            'version' => defined('SEO_ANALISTA_VERSION') ? SEO_ANALISTA_VERSION : '',
        ));
    }
}
