<?php
/**
 * Fuentes complementarias del Analista: GA4, busqueda interna, proveedores y
 * datos competitivos importados desde cualquier fuente de posiciones compatible.
 */

defined('ABSPATH') || exit;

if (!defined('SEO_ANALISTA_OPTION_COMPETITION')) {
    define('SEO_ANALISTA_OPTION_COMPETITION', 'seo_analista_competition_source_snapshot');
}
if (!defined('SEO_ANALISTA_OPTION_COMPETITION_HISTORY')) {
    define('SEO_ANALISTA_OPTION_COMPETITION_HISTORY', 'seo_analista_competition_history');
}

if (!function_exists('seo_analista_table_exists')) {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
    function seo_analista_table_exists($table) {
        global $wpdb;
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $table);
        if ($table === '') return false;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Native live read/write of plugin-owned tables, retaining immediate reporting consistency; query values are prepared.
        return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))) === $table;
    }
}

if (!function_exists('seo_analista_ga4_snapshot')) {
    /**
     * Snapshot GA4 de negocio y calidad de tráfico.
     *
     * Mantiene el resumen ligero original, pero añade señales que sí ayudan a
     * interpretar una tienda: embudo ecommerce, procedencia de sesiones,
     * mercado geográfico, últimas fechas con datos y vistas 404.
     *
     * Importante: eventos del embudo son recuentos de eventos, no usuarios
     * únicos. Por eso las tasas derivadas se etiquetan como tasas de eventos.
     */
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
    function seo_analista_ga4_snapshot($days = 28, $end_date = '') {
        $days = seo_analista_days($days);
        $start_offset = max(0, $days - 1);
        $end_date = (string) $end_date;
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date)) {
            $end_date = (new DateTimeImmutable('yesterday', wp_timezone()))->format('Y-m-d');
        }
        try {
            $start_date = (new DateTimeImmutable($end_date))->modify('-' . $start_offset . ' days')->format('Y-m-d');
        } catch (Exception $exception) {
            $end_date = (new DateTimeImmutable('yesterday', wp_timezone()))->format('Y-m-d');
            $start_date = (new DateTimeImmutable($end_date, wp_timezone()))->modify('-' . $start_offset . ' days')->format('Y-m-d');
        }

        $empty = array(
            'available' => false,
            'sessions' => 0,
            'users' => 0,
            'pageviews' => 0,
            'purchases' => 0,
            'revenue' => 0.0,
            'period' => array(
                'days' => $days,
                'start' => $start_date,
                'end' => $end_date,
                'latest_date' => '',
            ),
            'funnel' => array(
                'view_item' => 0,
                'add_to_cart' => 0,
                'view_cart' => 0,
                'begin_checkout' => 0,
                'add_shipping_info' => 0,
                'add_payment_info' => 0,
                'purchase' => 0,
                'view_search_results' => 0,
                'view_item_to_cart_pct' => null,
                'cart_to_checkout_pct' => null,
                'checkout_to_purchase_pct' => null,
            ),
            'sources' => array(),
            'channels' => array(
                'direct' => 0,
                'organic' => 0,
                'ai' => 0,
                'social' => 0,
                'referral' => 0,
                'other' => 0,
            ),
            'traffic_quality' => array(
                'direct_sessions' => 0,
                'direct_share_pct' => null,
                'organic_sessions' => 0,
                'ai_sessions' => 0,
            ),
            'countries' => array(),
            'target_market' => array(
                'country_id' => 'ES',
                'label' => 'España',
                'users' => 0,
                'sessions' => 0,
                'user_share_pct' => null,
            ),
            'not_found' => array(
                'views' => 0,
                'active_users_rows' => 0,
                'rows' => array(),
            ),
            'errors' => array(),
            'error' => '',
        );

        if (!function_exists('seo_google_analytics_run_report')) return $empty;

        $cache_key = 'seo_analista_ga4_v4_' . get_current_blog_id() . '_' . $days . '_' . str_replace('-', '', $end_date);
        $cached = get_transient($cache_key);
        if (is_array($cached)) return $cached;

        $date_ranges = array(array('startDate' => $start_date, 'endDate' => $end_date));
        $metric_value = static function(array $row, $index) {
            return isset($row['metricValues'][$index]['value']) ? (float) $row['metricValues'][$index]['value'] : 0.0;
        };
        $dimension_value = static function(array $row, $index) {
            return isset($row['dimensionValues'][$index]['value']) ? (string) $row['dimensionValues'][$index]['value'] : '';
        };

        $report = seo_google_analytics_run_report(array(
            'dateRanges' => $date_ranges,
            'metrics' => array(
                array('name'=>'sessions'),
                array('name'=>'activeUsers'),
                array('name'=>'screenPageViews'),
                array('name'=>'ecommercePurchases'),
                array('name'=>'purchaseRevenue'),
            ),
            'limit' => 1,
        ));
        if (is_wp_error($report)) {
            $empty['error'] = $report->get_error_message();
            set_transient($cache_key, $empty, 5 * MINUTE_IN_SECONDS);
            return $empty;
        }

        $summary_row = (array) ($report['rows'][0] ?? array());
        $out = $empty;
        $out['available'] = true;
        $out['sessions'] = (int) $metric_value($summary_row, 0);
        $out['users'] = (int) $metric_value($summary_row, 1);
        $out['pageviews'] = (int) $metric_value($summary_row, 2);
        $out['purchases'] = (int) $metric_value($summary_row, 3);
        $out['revenue'] = (float) $metric_value($summary_row, 4);

        // Ultimo día con sesiones: sirve para detectar retrasos entre fuentes.
        $latest = seo_google_analytics_run_report(array(
            'dateRanges' => $date_ranges,
            'dimensions' => array(array('name'=>'date')),
            'metrics' => array(array('name'=>'sessions')),
            'orderBys' => array(array('dimension'=>array('dimensionName'=>'date'), 'desc'=>true)),
            'limit' => 1,
        ));
        if (!is_wp_error($latest)) {
            $raw_date = $dimension_value((array) ($latest['rows'][0] ?? array()), 0);
            if (preg_match('/^\\d{8}$/', $raw_date)) {
                $out['period']['latest_date'] = substr($raw_date, 0, 4) . '-' . substr($raw_date, 4, 2) . '-' . substr($raw_date, 6, 2);
            }
        } else {
            $out['errors']['latest_date'] = $latest->get_error_message();
        }

        // Embudo ecommerce medido con los eventos que ya envía TaxoCommerce SEO.
        $events_report = seo_google_analytics_run_report(array(
            'dateRanges' => $date_ranges,
            'dimensions' => array(array('name'=>'eventName')),
            'metrics' => array(array('name'=>'eventCount')),
            'limit' => 250,
        ));
        if (!is_wp_error($events_report)) {
            $wanted = array_fill_keys(array(
                'view_item','add_to_cart','view_cart','begin_checkout',
                'add_shipping_info','add_payment_info','purchase','view_search_results'
            ), true);
            foreach ((array) ($events_report['rows'] ?? array()) as $row) {
                $row = (array) $row;
                $event = $dimension_value($row, 0);
                if ($event === '' || !isset($wanted[$event])) continue;
                $out['funnel'][$event] = (int) $metric_value($row, 0);
            }
            if ($out['funnel']['purchase'] <= 0 && $out['purchases'] > 0) {
                $out['funnel']['purchase'] = $out['purchases'];
            }
            if ($out['funnel']['view_item'] > 0) {
                $out['funnel']['view_item_to_cart_pct'] = round(($out['funnel']['add_to_cart'] / $out['funnel']['view_item']) * 100, 2);
            }
            if ($out['funnel']['add_to_cart'] > 0) {
                $out['funnel']['cart_to_checkout_pct'] = round(($out['funnel']['begin_checkout'] / $out['funnel']['add_to_cart']) * 100, 2);
            }
            if ($out['funnel']['begin_checkout'] > 0) {
                $out['funnel']['checkout_to_purchase_pct'] = round(($out['funnel']['purchase'] / $out['funnel']['begin_checkout']) * 100, 2);
            }
        } else {
            $out['errors']['events'] = $events_report->get_error_message();
        }

        // Fuente/medio de sesión. No equiparar sesiones GA4 con clics GSC/Bing:
        // son métricas distintas y pueden diferir por consentimiento/atribución.
        $sources_report = seo_google_analytics_run_report(array(
            'dateRanges' => $date_ranges,
            'dimensions' => array(array('name'=>'sessionSourceMedium')),
            'metrics' => array(array('name'=>'sessions'), array('name'=>'activeUsers')),
            'orderBys' => array(array('metric'=>array('metricName'=>'sessions'), 'desc'=>true)),
            'limit' => 100,
        ));
        if (!is_wp_error($sources_report)) {
            foreach ((array) ($sources_report['rows'] ?? array()) as $row) {
                $row = (array) $row;
                $label = trim($dimension_value($row, 0));
                $sessions = (int) $metric_value($row, 0);
                $users = (int) $metric_value($row, 1);
                if ($label === '' || $sessions <= 0) continue;

                $lower = strtolower($label);
                $channel = 'other';
                if (strpos($lower, '(direct)') !== false) {
                    $channel = 'direct';
                } elseif (
                    strpos($lower, 'ai-assistant') !== false ||
                    preg_match('/chatgpt|openai|perplexity|claude|copilot|gemini/', $lower)
                ) {
                    $channel = 'ai';
                } elseif (preg_match('#/\\s*organic$#', $lower)) {
                    $channel = 'organic';
                } elseif (preg_match('#/\\s*(organic social|social|paid social)$#', $lower)) {
                    $channel = 'social';
                } elseif (preg_match('#/\\s*referral$#', $lower)) {
                    $channel = 'referral';
                }

                $out['channels'][$channel] += $sessions;
                $out['sources'][] = array(
                    'source_medium' => $label,
                    'sessions' => $sessions,
                    'users' => $users,
                    'channel' => $channel,
                );
            }
            $out['sources'] = array_slice($out['sources'], 0, 20);
            $out['traffic_quality']['direct_sessions'] = (int) $out['channels']['direct'];
            $out['traffic_quality']['organic_sessions'] = (int) $out['channels']['organic'];
            $out['traffic_quality']['ai_sessions'] = (int) $out['channels']['ai'];
            if ($out['sessions'] > 0) {
                $out['traffic_quality']['direct_share_pct'] = round(($out['channels']['direct'] / $out['sessions']) * 100, 2);
            }
        } else {
            $out['errors']['sources'] = $sources_report->get_error_message();
        }

        // Mercado objetivo: España se separa del resto para no interpretar
        // automáticamente tráfico internacional como avance comercial nacional.
        $countries_report = seo_google_analytics_run_report(array(
            'dateRanges' => $date_ranges,
            'dimensions' => array(array('name'=>'countryId'), array('name'=>'country')),
            'metrics' => array(array('name'=>'activeUsers'), array('name'=>'sessions')),
            'orderBys' => array(array('metric'=>array('metricName'=>'activeUsers'), 'desc'=>true)),
            'limit' => 100,
        ));
        if (!is_wp_error($countries_report)) {
            foreach ((array) ($countries_report['rows'] ?? array()) as $row) {
                $row = (array) $row;
                $country_id = strtoupper(trim($dimension_value($row, 0)));
                $country = trim($dimension_value($row, 1));
                $users = (int) $metric_value($row, 0);
                $sessions = (int) $metric_value($row, 1);
                if ($country_id === '' && $country === '') continue;
                $item = array(
                    'country_id' => $country_id,
                    'country' => $country,
                    'users' => $users,
                    'sessions' => $sessions,
                );
                $out['countries'][] = $item;
                $country_key = strtolower(remove_accents($country));
                if ($country_id === 'ES' || $country_key === 'spain' || $country_key === 'espana') {
                    $out['target_market']['users'] = $users;
                    $out['target_market']['sessions'] = $sessions;
                }
            }
            $out['countries'] = array_slice($out['countries'], 0, 20);
            if ($out['users'] > 0) {
                $out['target_market']['user_share_pct'] = round(($out['target_market']['users'] / $out['users']) * 100, 2);
            }
        } else {
            $out['errors']['countries'] = $countries_report->get_error_message();
        }

        // Páginas no encontradas. Se usa título + ruta porque la plantilla 404
        // conserva el título "Página no encontrada" pero la URL solicitada varía.
        $pages_report = seo_google_analytics_run_report(array(
            'dateRanges' => $date_ranges,
            'dimensions' => array(array('name'=>'pageTitle'), array('name'=>'pagePath')),
            'metrics' => array(array('name'=>'screenPageViews'), array('name'=>'activeUsers')),
            'orderBys' => array(array('metric'=>array('metricName'=>'screenPageViews'), 'desc'=>true)),
            'limit' => 500,
        ));
        if (!is_wp_error($pages_report)) {
            foreach ((array) ($pages_report['rows'] ?? array()) as $row) {
                $row = (array) $row;
                $title = trim($dimension_value($row, 0));
                $page_path = trim($dimension_value($row, 1));
                $haystack = strtolower(remove_accents($title . ' ' . $page_path));
                if (
                    strpos($haystack, 'pagina no encontrada') === false &&
                    strpos($haystack, 'page not found') === false &&
                    !preg_match('/(^|[^0-9])404([^0-9]|$)/', $haystack)
                ) {
                    continue;
                }
                $views = (int) $metric_value($row, 0);
                $users = (int) $metric_value($row, 1);
                $out['not_found']['views'] += $views;
                $out['not_found']['active_users_rows'] += $users;
                $out['not_found']['rows'][] = array(
                    'title' => $title,
                    'path' => $page_path,
                    'views' => $views,
                    'users' => $users,
                );
            }
            $out['not_found']['rows'] = array_slice($out['not_found']['rows'], 0, 20);
        } else {
            $out['errors']['not_found'] = $pages_report->get_error_message();
        }

        set_transient($cache_key, $out, 15 * MINUTE_IN_SECONDS);
        return $out;
    }
}

if (!function_exists('seo_analista_internal_search_snapshot')) {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
    function seo_analista_internal_search_snapshot($days = 28, $limit = 20) {
        global $wpdb;
        $days = seo_analista_days($days);
        $table = $wpdb->prefix . 'seo_search_log';
        $out = array('available'=>false,'total'=>0,'unique'=>0,'zero_results'=>0,'top'=>array(),'gaps'=>array());
        if (!seo_analista_table_exists($table)) return $out;

        $since = wp_date('Y-m-d H:i:s', current_time('timestamp') - ($days * DAY_IN_SECONDS));
        // El registro guarda user_id: excluir también las pruebas históricas
        // de quienes actualmente administran el sitio.
        $admin_ids = array_values(array_filter(array_map('absint', (array) get_users(array('capability'=>'manage_options','fields'=>'ID')))));
        $exclude_admins = '';
        $summary_args = array($since);
        if ($admin_ids) {
            $admin_placeholders = implode(',', array_fill(0, count($admin_ids), '%d'));
            $exclude_admins = " AND user_id NOT IN ({$admin_placeholders})";
            $summary_args = array_merge($summary_args, $admin_ids);
        }
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Internal table and generated admin ID placeholders; all values are bound via $wpdb->prepare().
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Trusted internal table identifiers; generated value placeholders are bound using wpdb::prepare (WordPress 5.8 compatibility).
        $summary_sql = $wpdb->prepare(
            "SELECT COUNT(*) total, COUNT(DISTINCT normalized_term) unique_terms, SUM(CASE WHEN results_count=0 THEN 1 ELSE 0 END) zero_results FROM {$table} WHERE searched_at >= %s{$exclude_admins}",
            $summary_args
        );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- $summary_sql is the result of $wpdb->prepare(). Native live read/write of plugin-owned tables, retaining immediate reporting consistency; query values are prepared.
        $summary = $wpdb->get_row($summary_sql, ARRAY_A);
        $out['available'] = true;
        $out['total'] = (int) ($summary['total'] ?? 0);
        $out['unique'] = (int) ($summary['unique_terms'] ?? 0);
        $out['zero_results'] = (int) ($summary['zero_results'] ?? 0);

        $sql = "SELECT MAX(search_term) search_term, normalized_term, COUNT(*) searches, AVG(results_count) avg_results, SUM(CASE WHEN results_count=0 THEN 1 ELSE 0 END) zero_count, MAX(searched_at) last_search FROM {$table} WHERE searched_at >= %s{$exclude_admins} GROUP BY normalized_term ORDER BY searches DESC, zero_count DESC LIMIT %d";
        $top_args = array($since);
        if ($admin_ids) {
            $top_args = array_merge($top_args, $admin_ids);
        }
        $top_args[] = max(5, min(100, absint($limit)));
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Internal table and generated admin ID placeholders; all values/limit are bound via $wpdb->prepare().
        $sql = $wpdb->prepare($sql, $top_args);
        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- $sql is the result of $wpdb->prepare(). Native live read/write of plugin-owned tables, retaining immediate reporting consistency; query values are prepared.
        $out['top'] = (array) $wpdb->get_results($sql, ARRAY_A);
        foreach ($out['top'] as $row) {
            if ((int) ($row['zero_count'] ?? 0) > 0 || (float) ($row['avg_results'] ?? 0) < 2.0) $out['gaps'][] = $row;
        }
        return $out;
    }
}

if (!function_exists('seo_analista_supplier_snapshot')) {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
    function seo_analista_supplier_snapshot($limit = 20) {
        global $wpdb;
        $table = $wpdb->prefix . 'seo_proveedores_productos';
        $out = array('available'=>false,'total'=>0,'providers'=>0,'published_links'=>0,'rows'=>array(),'issues'=>array());
        if (!seo_analista_table_exists($table)) return $out;

        $out['available'] = true;
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Trusted internal table identifiers; generated value placeholders are bound using wpdb::prepare (WordPress 5.8 compatibility).
        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Native live read/write of plugin-owned tables, retaining immediate reporting consistency; query values are prepared.
        $out['total'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Native live read/write of plugin-owned tables, retaining immediate reporting consistency; query values are prepared.
        $out['providers'] = (int) $wpdb->get_var("SELECT COUNT(DISTINCT proveedor) FROM {$table}");
        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Native live read/write of plugin-owned tables, retaining immediate reporting consistency; query values are prepared.
        $out['published_links'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE object_id IS NOT NULL AND object_id > 0");
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber

        $sql = "SELECT proveedor,
                    COUNT(*) total,
                    SUM(CASE WHEN object_id IS NOT NULL AND object_id>0 THEN 1 ELSE 0 END) linked,
                    SUM(CASE WHEN estado_seleccion='descartado' THEN 1 ELSE 0 END) discarded,
                    SUM(CASE WHEN estado_seleccion='pendiente' THEN 1 ELSE 0 END) pending,
                    SUM(CASE WHEN ultimo_error_sync IS NOT NULL AND ultimo_error_sync<>'' THEN 1 ELSE 0 END) errors,
                    SUM(CASE WHEN stock_estado IN ('instock','in_stock','disponible') OR stock_cantidad>0 THEN 1 ELSE 0 END) with_stock,
                    MAX(ultima_importacion) last_import,
                    MAX(ultima_sincronizacion) last_sync
                FROM {$table}
                GROUP BY proveedor
                ORDER BY total DESC
                LIMIT %d";
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Internal supplier table; limit is bound via $wpdb->prepare().
        $sql = $wpdb->prepare($sql, max(5, min(100, absint($limit))));
        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- $sql is the result of $wpdb->prepare(). Native live read/write of plugin-owned tables, retaining immediate reporting consistency; query values are prepared.
        $out['rows'] = (array) $wpdb->get_results($sql, ARRAY_A);
        $now = current_time('timestamp');
        foreach ($out['rows'] as $row) {
            $problems = array();
            if ((int) ($row['errors'] ?? 0) > 0) $problems[] = number_format_i18n((int)$row['errors']) . ' errores de sincronización';
            $last = !empty($row['last_import']) ? strtotime((string)$row['last_import']) : 0;
            if ($last && ($now - $last) > 7 * DAY_IN_SECONDS) $problems[] = 'importación sin actualizar > 7 días';
            $total = max(1, (int) ($row['total'] ?? 0));
            if (((int)($row['pending'] ?? 0) / $total) > 0.50) $problems[] = 'más del 50% pendiente de revisar';
            if ($problems) {
                $out['issues'][] = array('provider'=>(string)$row['proveedor'],'problems'=>$problems,'row'=>$row);
            }
        }
        return $out;
    }
}

if (!function_exists('seo_analista_competition_source_snapshot')) {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
    function seo_analista_competition_source_snapshot() {
        $data = get_option(SEO_ANALISTA_OPTION_COMPETITION, array());
        return is_array($data) ? $data : array();
    }
}

if (!function_exists('seo_analista_competition_history')) {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
    function seo_analista_competition_history() {
        $history = get_option(SEO_ANALISTA_OPTION_COMPETITION_HISTORY, array());
        return is_array($history) ? $history : array();
    }
}

if (!function_exists('seo_analista_store_competition_history')) {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
    function seo_analista_store_competition_history() {
        if (!function_exists('seo_analista_competition_snapshot')) return;
        $competition = seo_analista_competition_snapshot(20);
        if (empty($competition['available'])) return;

        $entry = array(
            'imported_at' => (string) ($competition['imported_at'] ?? current_time('mysql')),
            'row_count' => (int) ($competition['row_count'] ?? 0),
            'own_domain' => (string) ($competition['own_domain'] ?? ''),
            'domains' => array_values((array) ($competition['domains'] ?? array())),
        );
        $history = seo_analista_competition_history();
        $history[] = $entry;

        $unique = array();
        foreach (array_reverse($history) as $item) {
            $key = (string) ($item['imported_at'] ?? '');
            if ($key === '' || isset($unique[$key])) continue;
            $unique[$key] = $item;
            if (count($unique) >= 24) break;
        }
        update_option(SEO_ANALISTA_OPTION_COMPETITION_HISTORY, array_reverse(array_values($unique)), false);
    }
}
