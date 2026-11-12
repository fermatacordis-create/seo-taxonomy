<?php
/**
 * Literatura, contenidos y estructura editorial para Analista.
 *
 * Convierte el contenido real de WordPress + la arquitectura SEO + Search
 * Console en directrices concretas: que URL mejorar, que parte revisar y que
 * entidad impulsar cuando ya existe una base correcta y aparece demanda.
 */

defined('ABSPATH') || exit;

if (!function_exists('seo_analista_word_count')) {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
    function seo_analista_word_count($text) {
        $text = wp_strip_all_tags(strip_shortcodes((string) $text));
        $text = trim(preg_replace('/\s+/u', ' ', $text));
        if ($text === '') return 0;
        $parts = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
        return is_array($parts) ? count($parts) : 0;
    }
}

if (!function_exists('seo_analista_first_meta')) {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
    function seo_analista_first_meta($post_id, array $keys) {
        foreach ($keys as $key) {
            $value = trim((string) get_post_meta(absint($post_id), $key, true));
            if ($value !== '') return $value;
        }
        return '';
    }
}

if (!function_exists('seo_analista_seo_meta_snapshot')) {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
    function seo_analista_seo_meta_snapshot($post_id) {
        $post_id = absint($post_id);
        $title = seo_analista_first_meta($post_id, array(
            '_yoast_wpseo_title', 'rank_math_title', '_rank_math_title',
            '_seopress_titles_title', '_aioseo_title', '_seo_title', 'seo_title',
        ));
        $description = seo_analista_first_meta($post_id, array(
            '_yoast_wpseo_metadesc', 'rank_math_description', '_rank_math_description',
            '_seopress_titles_desc', '_aioseo_description', '_seo_description', 'seo_description',
        ));

        $title_source = $title !== '' ? 'postmeta' : '';
        $description_source = $description !== '' ? 'postmeta' : '';
        $seo_plugin_active = defined('WPSEO_VERSION')
            || defined('RANK_MATH_VERSION')
            || defined('SEOPRESS_VERSION')
            || defined('AIOSEO_VERSION');

        /*
         * 3.8.1: ausencia de postmeta no equivale a ausencia del meta efectivo.
         * Los plugins SEO pueden generar title/description por plantilla global.
         * Cuando uno esta activo no fabricamos el valor: marcamos la gestion
         * como efectiva para impedir el falso positivo y dejamos la inspeccion
         * del snippet como accion separada si CTR/demanda lo justifican.
         */
        if ($seo_plugin_active) {
            if ($title === '') {
                $title = '[gestionado por plantilla del plugin SEO]';
                $title_source = 'seo_plugin_template';
            }
            if ($description === '') {
                $description = '[gestionada por plantilla del plugin SEO]';
                $description_source = 'seo_plugin_template';
            }
        } else {
            /*
             * La cabecera publica del plugin usa el titulo de WordPress y, como
             * fallback de description, excerpt o contenido. Replicamos ese
             * contrato sin lanzar una peticion HTTP a la URL.
             */
            if ($title === '' && $post_id > 0) {
                $fallback_title = trim((string) get_the_title($post_id));
                if ($fallback_title !== '') {
                    $title = $fallback_title;
                    $title_source = 'wordpress_template';
                }
            }
            if ($description === '' && $post_id > 0) {
                $fallback_description = trim((string) get_post_field('post_excerpt', $post_id));
                if ($fallback_description === '') {
                    $raw_content = (string) get_post_field('post_content', $post_id);
                    $fallback_description = wp_trim_words(
                        wp_strip_all_tags(strip_shortcodes($raw_content)),
                        30,
                        ''
                    );
                }
                // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Existing public hook name is retained to preserve connected callbacks.
                $fallback_description = trim((string) apply_filters('dht_meta_description', $fallback_description));
                if ($fallback_description !== '') {
                    $description = $fallback_description;
                    $description_source = 'dht_template_fallback';
                }
            }
        }

        return array(
            'title' => $title,
            'description' => $description,
            'title_source' => $title_source,
            'description_source' => $description_source,
            'effective_checked' => true,
        );
    }
}

if (!function_exists('seo_analista_internal_link_count')) {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
    function seo_analista_internal_link_count($html) {
        $html = (string) $html;
        if ($html === '') return 0;
        $host = strtolower((string) wp_parse_url(home_url('/'), PHP_URL_HOST));
        $count = 0;
        if (preg_match_all('/href\s*=\s*["\']([^"\']+)["\']/i', $html, $matches)) {
            foreach ((array) ($matches[1] ?? array()) as $href) {
                $href = trim((string) $href);
                if ($href === '') continue;
                if ($href[0] === '/' && (strlen($href) < 2 || $href[1] !== '/')) {
                    $count++;
                    continue;
                }
                $href_host = strtolower((string) wp_parse_url($href, PHP_URL_HOST));
                if ($href_host !== '' && $host !== '' && preg_replace('/^www\./', '', $href_host) === preg_replace('/^www\./', '', $host)) {
                    $count++;
                }
            }
        }
        return $count;
    }
}

if (!function_exists('seo_analista_path_key')) {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
    function seo_analista_path_key($url) {
        $path = (string) wp_parse_url((string) $url, PHP_URL_PATH);
        if ($path === '') $path = '/';
        $path = untrailingslashit('/' . ltrim($path, '/'));
        return $path === '' ? '/' : $path;
    }
}

if (!function_exists('seo_analista_page_metric_maps')) {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
    function seo_analista_page_metric_maps(array $data) {
        $current = array();
        $previous = array();
        foreach ((array) ($data['pages'] ?? array()) as $row) {
            $current[seo_analista_path_key($row['page_url'] ?? '')] = $row;
        }
        foreach ((array) ($data['previous_pages'] ?? array()) as $row) {
            $previous[seo_analista_path_key($row['page_url'] ?? '')] = $row;
        }
        return array($current, $previous);
    }
}

if (!function_exists('seo_analista_vocabulary_count_map')) {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
    function seo_analista_vocabulary_count_map() {
        global $wpdb;
        $table = esc_sql( $wpdb->prefix . 'seo_object_vocabulary' );
        if (!function_exists('seo_analista_table_exists') || !seo_analista_table_exists($table)) return array();
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Trusted internal table identifiers; generated value placeholders are bound using wpdb::prepare (WordPress 5.8 compatibility).
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Native live read/write of plugin-owned tables, retaining immediate reporting consistency; query values are prepared.
        $rows = (array) $wpdb->get_results(
            "SELECT object_type, object_id, COUNT(*) AS total\n             FROM {$table}\n             WHERE status=1\n             GROUP BY object_type, object_id",
            ARRAY_A
        );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
        $out = array();
        foreach ($rows as $row) {
            $key = sanitize_key((string) ($row['object_type'] ?? '')) . ':' . absint($row['object_id'] ?? 0);
            if ($key !== ':0') $out[$key] = (int) ($row['total'] ?? 0);
        }
        return $out;
    }
}

if (!function_exists('seo_analista_category_content_map')) {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
    function seo_analista_category_content_map() {
        global $wpdb;
        $table = esc_sql( $wpdb->prefix . 'seo_nodes' );
        if (!function_exists('seo_analista_table_exists') || !seo_analista_table_exists($table)) return array();
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Trusted internal table identifiers; generated value placeholders are bound using wpdb::prepare (WordPress 5.8 compatibility).
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Native live read/write of plugin-owned tables, retaining immediate reporting consistency; query values are prepared.
        $rows = (array) $wpdb->get_results(
            "SELECT object_id, seo_role, keywords, updated_at
             FROM {$table}
             WHERE object_type='category' AND status=1
               AND seo_role IN ('description','excerpt','category','ambito')
             ORDER BY object_id ASC, seo_role ASC, updated_at DESC, id DESC",
            ARRAY_A
        );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
        $out = array();
        foreach ($rows as $row) {
            $id = absint($row['object_id'] ?? 0);
            $role = sanitize_key((string) ($row['seo_role'] ?? ''));
            if (!$id || $role === '') continue;
            if (!isset($out[$id])) $out[$id] = array('description' => '', 'excerpt' => '', 'category' => '', 'ambito' => '', 'modified_at' => '');
            if (isset($out[$id][$role]) && $out[$id][$role] === '') $out[$id][$role] = (string) ($row['keywords'] ?? '');
            if (in_array($role, array('description', 'excerpt'), true)) {
                $updated_at = (string) ($row['updated_at'] ?? '');
                if ($updated_at > (string) $out[$id]['modified_at']) $out[$id]['modified_at'] = $updated_at;
            }
        }
        return $out;
    }
}

if (!function_exists('seo_analista_page_role_map')) {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
    function seo_analista_page_role_map() {
        global $wpdb;
        $table = esc_sql( $wpdb->prefix . 'seo_nodes' );
        if (!function_exists('seo_analista_table_exists') || !seo_analista_table_exists($table)) return array();
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Trusted internal table identifiers; generated value placeholders are bound using wpdb::prepare (WordPress 5.8 compatibility).
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Native live read/write of plugin-owned tables, retaining immediate reporting consistency; query values are prepared.
        $rows = (array) $wpdb->get_results(
            "SELECT object_id, seo_role
             FROM {$table}
             WHERE status=1 AND object_type='page'
               AND seo_role IN ('cluster','hub_primary','hub_secondary','landing','corporate_page')
             ORDER BY object_id ASC, updated_at DESC, id DESC",
            ARRAY_A
        );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
        $out = array();
        foreach ($rows as $row) {
            $id = absint($row['object_id'] ?? 0);
            $role = sanitize_key((string) ($row['seo_role'] ?? ''));
            if ($id && $role && !isset($out[$id])) $out[$id] = $role;
        }
        return $out;
    }
}

if (!function_exists('seo_analista_structural_role_map')) {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
    function seo_analista_structural_role_map() {
        global $wpdb;
        $table = esc_sql( $wpdb->prefix . 'seo_nodes' );
        if (!function_exists('seo_analista_table_exists') || !seo_analista_table_exists($table)) return array();
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Trusted internal table identifiers; generated value placeholders are bound using wpdb::prepare (WordPress 5.8 compatibility).
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Native live read/write of plugin-owned tables, retaining immediate reporting consistency; query values are prepared.
        $rows = (array) $wpdb->get_results(
            "SELECT object_id, seo_role FROM {$table}\n             WHERE status=1 AND object_type='page'\n               AND seo_role IN ('cluster','hub_primary','hub_secondary')",
            ARRAY_A
        );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
        $out = array();
        foreach ($rows as $row) {
            $id = absint($row['object_id'] ?? 0);
            $role = sanitize_key((string) ($row['seo_role'] ?? ''));
            if ($id && $role) $out[$id] = $role;
        }
        return $out;
    }
}

if (!function_exists('seo_analista_structural_child_count_map')) {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
    function seo_analista_structural_child_count_map() {
        global $wpdb;
        $table = esc_sql( $wpdb->prefix . 'seo_relations' );
        if (!function_exists('seo_analista_table_exists') || !seo_analista_table_exists($table)) return array();
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Trusted internal table identifiers; generated value placeholders are bound using wpdb::prepare (WordPress 5.8 compatibility).
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Native live read/write of plugin-owned tables, retaining immediate reporting consistency; query values are prepared.
        $rows = (array) $wpdb->get_results(
            "SELECT source_type, source_id, COUNT(DISTINCT target_id) AS total
             FROM {$table}
             WHERE (source_type='cluster' AND target_type='hub_primary' AND relation_type='cluster_to_primary')
                OR (source_type='hub_primary' AND target_type='hub_secondary' AND relation_type='hub_primary_to_hub_secondary')
                OR (source_type='hub_secondary' AND target_type='product_cat' AND relation_type='hub_secondary_to_category')
             GROUP BY source_type, source_id",
            ARRAY_A
        );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
        $out = array();
        foreach ($rows as $row) {
            $key = sanitize_key((string) ($row['source_type'] ?? '')) . ':' . absint($row['source_id'] ?? 0);
            if ($key !== ':0') $out[$key] = (int) ($row['total'] ?? 0);
        }
        return $out;
    }
}

if (!function_exists('seo_analista_functional_page_ids')) {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
    function seo_analista_functional_page_ids() {
        $ids = array();
        if (function_exists('wc_get_page_id')) {
            foreach (array('cart', 'checkout', 'myaccount', 'shop', 'terms') as $key) {
                $id = (int) wc_get_page_id($key);
                if ($id > 0) $ids[$id] = true;
            }
        }
        $front = (int) get_option('page_on_front');
        $posts = (int) get_option('page_for_posts');
        if ($front > 0) $ids[$front] = true;
        if ($posts > 0) $ids[$posts] = true;

        // Inventario real 2026-09: estas páginas son de navegación, cuenta,
        // legal o herramientas internas. No deben competir por tareas de
        // literatura SEO ni convertirse en destino de consultas de catálogo.
        foreach (array(
            'carrito', 'finalizar-compra', 'mi-cuenta', 'terminos-y-condiciones',
            'privacidad-de-datos', 'devoluciones-y-reembolsos', 'contacto',
            'blog', 'tienda', 'dependiente'
        ) as $slug) {
            // Durante migraciones/reclasificaciones una URL funcional puede
            // quedar temporalmente como page o post. Ninguna de las dos debe
            // competir con contenido editorial/comercial en Analista.
            foreach (array('page', 'post') as $post_type) {
                $page = get_page_by_path($slug, OBJECT, $post_type);
                if ($page && !empty($page->ID)) $ids[(int) $page->ID] = true;
            }
        }
        return $ids;
    }
}


if (!function_exists('seo_analista_token_stem')) {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
    function seo_analista_token_stem($token) {
        $token = (string) $token;
        $len = strlen($token);
        if ($len > 6 && substr($token, -2) === 'es') $token = substr($token, 0, -2);
        elseif ($len > 4 && substr($token, -1) === 's') $token = substr($token, 0, -1);

        // Canonicalizaciones pequeñas y seguras observadas en el inventario.
        // No son un diccionario general: solo evitan que singular/plural o
        // "tubo" frente a "tubería" rompan un match evidente.
        $aliases = array(
            'tubo' => 'tuberia',
            'tuberia' => 'tuberia',
        );
        return $aliases[$token] ?? $token;
    }
}

if (!function_exists('seo_analista_meaningful_tokens')) {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
    function seo_analista_meaningful_tokens($text) {
        $stop = array_flip(array(
            'a','al','algo','como','con','de','del','el','en','es','esta','este','la','las','lo','los','mas','o','para','por','que','se','sin','su','sus','un','una','uno','unas','unos','y','e','tu','tus','mi','mis','ya','muy','mejor','mejores','guia','comprar','venta','precio','precios',
            // Términos demasiado genéricos para justificar por sí solos un target.
            'pagina','inicio','blog','tienda','contacto','producto','productos','servicio','servicios',
            'profesional','profesionales','industrial','industriales','equipo','equipos','herramienta','herramientas',
            'maquina','maquinas','maquinaria','sistema','sistemas','accesorio','accesorios'
        ));
        $tokens = array();
        foreach (explode(' ', seo_analista_normalize_text($text)) as $token) {
            if ($token === '' || strlen($token) < 3 || isset($stop[$token])) continue;
            $token = seo_analista_token_stem($token);
            if ($token === '' || strlen($token) < 3) continue;
            $tokens[$token] = true;
        }
        return array_keys($tokens);
    }
}


if (!function_exists('seo_analista_text_similarity')) {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
    function seo_analista_text_similarity($left, $right) {
        $a = seo_analista_meaningful_tokens($left);
        $b = seo_analista_meaningful_tokens($right);
        if (!$a || !$b) return 0.0;

        sort($a); sort($b);
        if ($a === $b) return 1.0;

        $shared = array_values(array_intersect($a, $b));
        $intersection = count($shared);
        if ($intersection < 1) return 0.0;

        // Coincidir solo en una palabra transversal (p.ej. "agua") no es
        // suficiente para mapear "fugas" a "fuentes y estanques".
        $generic = array_flip(array(
            'agua','vehiculo','taller','material','componente','distribucion',
            'suministro','mantenimiento','tecnico','tecnica','uso','trabajo'
        ));
        $specific_shared = array_filter($shared, static function($token) use ($generic) {
            return !isset($generic[$token]);
        });
        if (!$specific_shared) return 0.12;

        $coverage = $intersection / max(1, min(count($a), count($b)));
        $jaccard = $intersection / max(1, count(array_unique(array_merge($a, $b))));
        $score = ($coverage * 0.68) + ($jaccard * 0.32);

        // Una coincidencia única recibe un techo salvo que el término corto
        // sea prácticamente el mismo concepto (singular/plural ya normalizado).
        if ($intersection === 1 && max(count($a), count($b)) >= 3) $score = min($score, 0.48);
        return min(1.0, max(0.0, $score));
    }
}


if (!function_exists('seo_analista_related_queries')) {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
    function seo_analista_related_queries($subject, array $queries, $limit = 8, $min_similarity = 0.30) {
        $ranked = array();
        foreach ($queries as $row) {
            $query = seo_analista_clean_query($row['query_text'] ?? $row['query'] ?? '');
            if ($query === '' || !seo_analista_query_is_actionable($query)) continue;
            $similarity = seo_analista_text_similarity($subject, $query);
            if ($similarity < (float) $min_similarity) continue;
            $impressions = max(0.0, (float) ($row['impressions'] ?? 0));
            $score = ($similarity * 100) + min(30, log(1 + $impressions) * 5);
            $ranked[] = array('query' => $query, 'score' => $score, 'impressions' => $impressions);
        }
        usort($ranked, static function ($a, $b) {
            return (float) $b['score'] <=> (float) $a['score'];
        });
        $out = array();
        $seen = array();
        foreach ($ranked as $row) {
            $key = seo_analista_normalize_text($row['query']);
            if ($key === '' || isset($seen[$key])) continue;
            $seen[$key] = true;
            $out[] = $row['query'];
            if (count($out) >= max(1, absint($limit))) break;
        }
        return $out;
    }
}


if (!function_exists('seo_analista_gsc_demand_score')) {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
    function seo_analista_gsc_demand_score(array $current, array $previous = array()) {
        $impressions = max(0.0, (float) ($current['impressions'] ?? 0));
        $position = max(0.0, (float) ($current['position'] ?? 0));
        $score = min(28, log(1 + $impressions) * 4.5);
        if ($position > 0 && $position <= 10) $score += 22;
        elseif ($position <= 20) $score += 18;
        elseif ($position <= 50) $score += 13;
        elseif ($position <= 80) $score += 7;
        elseif ($position <= 100) $score += 3;

        $prev_impressions = max(0.0, (float) ($previous['impressions'] ?? 0));
        $delta = $impressions - $prev_impressions;
        if ($impressions > 0 && $prev_impressions <= 0) {
            if ($impressions >= 50) $score += 10;
            elseif ($impressions >= 20) $score += 7;
            elseif ($impressions >= 8) $score += 4;
            else $score += 2;
        } elseif ($prev_impressions > 0) {
            $growth = ($delta / $prev_impressions) * 100;
            if ($growth >= 100 && $delta >= 20) $score += 12;
            elseif ($growth >= 40 && $delta >= 8) $score += 8;
            elseif ($growth >= 15 && $delta >= 5) $score += 4;
            elseif ($growth <= -40) $score -= 4;
        }
        return max(0, min(60, (int) round($score)));
    }
}


if (!function_exists('seo_analista_content_issue_profile')) {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
    function seo_analista_content_issue_profile($type, array $source, array $seo_meta, $vocabulary_count, $internal_links, $taxonomy_count = 0) {
        $title = trim((string) ($source['title'] ?? ''));
        $body = (string) ($source['content'] ?? '');
        $excerpt = (string) ($source['excerpt'] ?? '');
        $words = seo_analista_word_count($body);
        $excerpt_words = seo_analista_word_count($excerpt);
        $issues = array();
        $changes = array();
        $issue_score = 0;

        $body_min = 300;
        if ($type === 'post') $body_min = 500;
        elseif ($type === 'product') $body_min = 140;
        elseif ($type === 'category') $body_min = 120;
        elseif ($type === 'cluster') $body_min = 650;
        elseif ($type === 'hub_primary') $body_min = 500;
        elseif ($type === 'hub_secondary') $body_min = 400;

        if (seo_analista_word_count($title) < 2 || strlen($title) < 14) {
            $issues[] = 'Título poco descriptivo.';
            $changes[] = 'Reescribir el título para expresar con claridad la intención principal y el ámbito de la página.';
            $issue_score += 10;
        } elseif (strlen($title) > 95 && $type !== 'product') {
            $issues[] = 'Título demasiado largo para una lectura clara.';
            $changes[] = 'Acortar el título manteniendo el término principal y la promesa de la página.';
            $issue_score += 5;
        }

        if ($words < $body_min) {
            $issues[] = 'Cobertura textual baja: ' . number_format_i18n($words) . ' palabras.';
            $changes[] = 'Ampliar la literatura con uso, criterios de elección, variantes, limitaciones y preguntas reales del usuario.';
            $issue_score += $words < max(40, (int) ($body_min * 0.35)) ? 22 : 14;
        }

        if ($type === 'product' && $excerpt_words < 25) {
            $issues[] = 'Descripción corta insuficiente.';
            $changes[] = 'Mejorar la descripción corta con tipo de producto, capacidad/prestación clave, uso y diferenciador principal.';
            $issue_score += 10;
        }

        if ($type === 'category' && $excerpt_words < 18) {
            $issues[] = 'Excerpt de categoría insuficiente.';
            $changes[] = 'Redactar un excerpt breve que defina la familia, su uso y el criterio principal de elección.';
            $issue_score += 8;
        }

        if ($type !== 'category' && trim((string) ($seo_meta['title'] ?? '')) === '') {
            $issues[] = 'SEO title explícito no localizado.';
            $changes[] = 'Revisar el SEO title y alinearlo con la intención principal sin duplicar literalmente el H1.';
            $issue_score += 2;
        }
        if ($type !== 'category' && trim((string) ($seo_meta['description'] ?? '')) === '') {
            $issues[] = 'Meta description explícita no localizada.';
            $changes[] = 'Redactar una meta description específica que explique utilidad, alcance y motivo para entrar.';
            $issue_score += 2;
        }

        if (in_array($type, array('post','page','landing','product','cluster','hub_primary','hub_secondary','category'), true) && $vocabulary_count < 1) {
            $issues[] = 'Sin Vocabulary asociado.';
            $changes[] = 'Asignar Vocabulary principal y variantes para fijar el campo semántico que debe cubrir la entidad.';
            $issue_score += 9;
        }

        if (in_array($type, array('post','page','landing','cluster','hub_primary','hub_secondary'), true) && $internal_links < 2) {
            $issues[] = 'Enlazado interno escaso.';
            $changes[] = 'Añadir enlaces contextuales hacia categorías, hubs o productos directamente relacionados.';
            $issue_score += 8;
        }

        if ($type === 'product' && $taxonomy_count < 1) {
            $issues[] = 'Producto sin categoría útil detectada.';
            $changes[] = 'Revisar su clasificación antes de ampliar la literatura para que el contenido refuerce la familia correcta.';
            $issue_score += 16;
        }

        $tag_count = (int) ($source['tag_count'] ?? 0);
        if ($type === 'post' && $tag_count < 1) {
            $issues[] = 'Entrada sin etiquetas editoriales.';
            $changes[] = 'Asignar solo etiquetas útiles y coherentes con el Vocabulary; evitar etiquetas aisladas o casi duplicadas.';
            $issue_score += 4;
        }
        if ($type === 'category' && $tag_count < 1) {
            $issues[] = 'Categoría sin etiquetas SEO.';
            $changes[] = 'Definir etiquetas SEO coherentes con Vocabulary, variantes de búsqueda y alcance real de la categoría.';
            $issue_score += 7;
        }

        $child_count = isset($source['child_count']) ? (int) $source['child_count'] : null;
        if (in_array($type, array('cluster','hub_primary','hub_secondary'), true) && null !== $child_count && $child_count < 1) {
            $issues[] = 'Nodo estructural sin descendencia conectada.';
            $changes[] = 'Revisar sus relaciones estructurales: un cluster/hub sin hijos no distribuye autoridad ni organiza el catálogo.';
            $issue_score += 18;
        }

        return array(
            'word_count' => $words,
            'excerpt_word_count' => $excerpt_words,
            'issues' => array_values(array_unique($issues)),
            'recommended_changes' => array_values(array_unique($changes)),
            'issue_score' => min(60, $issue_score),
        );
    }
}

if (!function_exists('seo_analista_entity_action')) {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
    function seo_analista_entity_action($type, $has_issues) {
        $map = array(
            'post' => $has_issues ? 'MEJORAR_POST' : 'IMPULSAR_POST',
            'page' => $has_issues ? 'MEJORAR_PAGINA' : 'IMPULSAR_PAGINA',
            'product' => $has_issues ? 'MEJORAR_PRODUCTO' : 'IMPULSAR_PRODUCTO',
            'category' => $has_issues ? 'MEJORAR_CATEGORIA' : 'IMPULSAR_CATEGORIA',
            'cluster' => $has_issues ? 'MEJORAR_CLUSTER' : 'IMPULSAR_CLUSTER',
            'hub_primary' => $has_issues ? 'MEJORAR_HUB' : 'IMPULSAR_HUB',
            'hub_secondary' => $has_issues ? 'MEJORAR_HUB' : 'IMPULSAR_HUB',
            'landing' => $has_issues ? 'MEJORAR_LANDING' : 'POTENCIAR_LANDING',
        );
        return $map[$type] ?? ($has_issues ? 'MEJORAR_PAGINA' : 'IMPULSAR_PAGINA');
    }
}

if (!function_exists('seo_analista_entity_label')) {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
    function seo_analista_entity_label($type) {
        $labels = array(
            'post' => 'Post', 'page' => 'Página', 'product' => 'Producto', 'category' => 'Categoría',
            'landing' => 'Landing', 'cluster' => 'Cluster', 'hub_primary' => 'Hub primario', 'hub_secondary' => 'Hub secundario',
        );
        return $labels[$type] ?? ucfirst((string) $type);
    }
}

if (!function_exists('seo_analista_build_entity_work_row')) {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
    function seo_analista_build_entity_work_row($type, $id, $title, $url, array $source, array $gsc, array $previous_gsc, $vocabulary_count, $taxonomy_count = 0, $edit_url = '') {
        $seo_meta = isset($source['seo_meta']) ? (array) $source['seo_meta'] : array('title' => '', 'description' => '');
        $internal_links = seo_analista_internal_link_count($source['content'] ?? '');
        $profile = seo_analista_content_issue_profile($type, $source, $seo_meta, $vocabulary_count, $internal_links, $taxonomy_count);
        $demand_score = seo_analista_gsc_demand_score($gsc, $previous_gsc);
        $has_issues = !empty($profile['issues']);
        $action = seo_analista_entity_action($type, $has_issues);

        $impressions = (float) ($gsc['impressions'] ?? 0);
        $position = (float) ($gsc['position'] ?? 0);
        $previous_impressions = (float) ($previous_gsc['impressions'] ?? 0);
        $delta_impressions = $impressions - $previous_impressions;
        $growth = null;
        if ($previous_impressions > 0) $growth = ($delta_impressions / $previous_impressions) * 100;
        elseif ($impressions >= 8) $growth = 100.0;

        $page_role = sanitize_key((string) ($source['page_role'] ?? ''));
        $is_corporate = ($type === 'page' && $page_role === 'corporate_page');
        if ($is_corporate) $demand_score = min(8, $demand_score);

        $ctr = (float) ($gsc['ctr'] ?? 0);
        if (!$is_corporate && $impressions >= 20 && $position > 0 && $position <= 20 && $ctr < 0.01) {
            $profile['issues'][] = 'CTR bajo para una URL ya visible en posiciones aprovechables.';
            $profile['recommended_changes'][] = 'Revisar SEO title y meta description para responder mejor a las consultas que ya generan impresiones.';
            $profile['issue_score'] = min(60, (int) $profile['issue_score'] + 8);
            $has_issues = true;
            $action = seo_analista_entity_action($type, true);
        }

        $priority = ($is_corporate ? 12 : 18) + (int) $profile['issue_score'] + $demand_score;
        if (!$has_issues && $impressions < 5) $priority -= 20;
        $new_signal = !$is_corporate && $previous_impressions <= 0 && $impressions >= 10;
        $accelerating = !$is_corporate && $previous_impressions > 0 && $growth !== null && $growth >= 40 && $impressions >= 10 && $delta_impressions >= 8;
        if ($accelerating) $priority += 7;
        elseif ($new_signal) $priority += 3;
        if (!$is_corporate && $position > 0 && $position <= 20 && $impressions >= 5) $priority += 5;
        $priority = max(0, min(100, $priority));

        $reason_parts = array();
        if ($has_issues) $reason_parts[] = 'La entidad tiene problemas editoriales concretos que se pueden corregir.';
        if ($impressions > 0) $reason_parts[] = 'Google ya la muestra: ' . number_format_i18n($impressions, 0) . ' impresiones, posición ' . number_format_i18n($position, 1) . '.';
        if ($accelerating) $reason_parts[] = 'La demanda asociada está acelerando respecto al periodo anterior.';
        elseif ($new_signal) $reason_parts[] = 'Es una señal nueva del periodo: todavía no debe interpretarse como crecimiento consolidado.';
        if (!$has_issues && $impressions > 0) $reason_parts[] = 'La base editorial es razonable: conviene impulsarla, no rehacerla.';

        $meta = function_exists('seo_analista_action_meta') ? seo_analista_action_meta($action) : array('label' => $action, 'channel' => 'contenido');
        return array(
            'priority' => $priority,
            'action' => $action,
            'action_label' => (string) ($meta['label'] ?? $action),
            'channel' => (string) ($meta['channel'] ?? 'contenido'),
            'topic' => (string) $title,
            'content_modified_at' => (string) ($source['modified_at'] ?? ''),
            'reason' => implode(' ', $reason_parts),
            'sources' => array_values(array_filter(array('WordPress', $impressions > 0 ? 'Search Console' : '', $vocabulary_count > 0 ? 'Vocabulary' : ''))),
            'source' => '',
            'entity' => array(
                'type' => $type,
                'type_label' => seo_analista_entity_label($type),
                'id' => absint($id),
                'title' => (string) $title,
                'url' => (string) $url,
                'edit_url' => (string) $edit_url,
                'role' => $page_role,
            ),
            'target' => array('title' => (string) $title, 'url' => (string) $url),
            'issues' => array_values(array_unique((array) $profile['issues'])),
            'recommended_changes' => array_values(array_unique((array) $profile['recommended_changes'])),
            'seo_meta' => $seo_meta,
            'content' => array(
                'word_count' => (int) $profile['word_count'],
                'excerpt_word_count' => (int) $profile['excerpt_word_count'],
                'internal_links' => (int) $internal_links,
                'vocabulary_count' => (int) $vocabulary_count,
                'taxonomy_count' => (int) $taxonomy_count,
                'tag_count' => (int) ($source['tag_count'] ?? 0),
                'child_count' => isset($source['child_count']) ? (int) $source['child_count'] : null,
            ),
            'metrics' => array(
                'impressions' => $impressions,
                'clicks' => (float) ($gsc['clicks'] ?? 0),
                'ctr' => $ctr,
                'position' => $position,
                'queries' => (int) ($gsc['queries'] ?? 0),
                'impressions_growth_pct' => $growth,
                'impressions_delta' => $delta_impressions,
                'search_score' => $demand_score,
                'market_score' => 0,
            ),
            'evidence' => array(),
            'keywords' => array(),
            'catalog' => array(),
        );
    }
}


if (!function_exists('seo_analista_enrich_keyword_directive')) {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
    function seo_analista_enrich_keyword_directive(array &$row) {
        $keywords = array_values(array_filter((array) ($row['keywords'] ?? array())));
        if (!$keywords) return;
        $sample = array_slice($keywords, 0, 4);
        $row['recommended_changes'][] = 'Revisar la cobertura explícita de estas búsquedas reales: ' . implode(' · ', $sample) . '.';
        $row['recommended_changes'] = array_values(array_unique($row['recommended_changes']));
    }
}

if (!function_exists('seo_analista_content_work')) {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
    function seo_analista_content_work($days = 28, $limit = 120) {
        static $cache = array();
        $days = seo_analista_days($days);
        $requested_limit = max(20, min(250, absint($limit)));
        if (isset($cache[$days])) return array_slice($cache[$days], 0, $requested_limit);

        global $wpdb;
        $data = seo_analista_get_data($days);
        list($gsc_map, $previous_map) = seo_analista_page_metric_maps($data);
        $vocab_map = seo_analista_vocabulary_count_map();
        $category_content_map = seo_analista_category_content_map();
        $role_map = seo_analista_structural_role_map();
        $page_role_map = seo_analista_page_role_map();
        $period = (array) ($data['period'] ?? array());
        $property_id = function_exists('seo_analista_resolve_property_id') ? seo_analista_resolve_property_id() : '';
        $page_query_map = ($property_id && !empty($period['date_from']) && !empty($period['date_to']) && function_exists('seo_analista_page_query_map'))
            ? seo_analista_page_query_map($property_id, $period['date_from'], $period['date_to'], 40000)
            : array();
        $child_map = seo_analista_structural_child_count_map();
        $functional_ids = seo_analista_functional_page_ids();
        $rows = array();

        // Posts y páginas: se analizan completos porque el volumen es manejable.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Native live read/write of plugin-owned tables, retaining immediate reporting consistency; query values are prepared.
        $editorial = (array) $wpdb->get_results(
            "SELECT ID, post_type, post_title, post_content, post_excerpt, post_modified\n             FROM {$wpdb->posts}\n             WHERE post_status='publish' AND post_type IN ('post','page')",
            ARRAY_A
        );
        foreach ($editorial as $post) {
            $id = absint($post['ID'] ?? 0);
            if (!$id || isset($functional_ids[$id])) continue;
            $post_type = (string) ($post['post_type'] ?? 'page');
            $page_role = (string) ($page_role_map[$id] ?? '');
            if ($post_type === 'page' && $page_role === 'corporate_page') continue;
            $type = isset($role_map[$id])
                ? $role_map[$id]
                : ($post_type === 'page' && $page_role === 'landing' ? 'landing' : $post_type);
            $url = (string) get_permalink($id);
            if ($url === '') continue;
            $path = seo_analista_path_key($url);
            $gsc = (array) ($gsc_map[$path] ?? array());
            $previous = (array) ($previous_map[$path] ?? array());
            $vocab_key = $post_type . ':' . $id;
            $tags = $post_type === 'post' ? wp_get_post_tags($id, array('fields' => 'ids')) : array();
            $source = array(
                'title' => (string) ($post['post_title'] ?? ''),
                'content' => (string) ($post['post_content'] ?? ''),
                'modified_at' => (string) ($post['post_modified'] ?? ''),
                'excerpt' => (string) ($post['post_excerpt'] ?? ''),
                'seo_meta' => seo_analista_seo_meta_snapshot($id),
                'tag_count' => is_wp_error($tags) ? 0 : count((array) $tags),
                'child_count' => isset($role_map[$id]) ? (int) ($child_map[$role_map[$id] . ':' . $id] ?? 0) : null,
                'page_role' => (string) ($page_role_map[$id] ?? ''),
            );
            $row = seo_analista_build_entity_work_row(
                $type, $id, $source['title'], $url, $source, $gsc, $previous,
                (int) ($vocab_map[$vocab_key] ?? 0), 0, (string) get_edit_post_link($id, '')
            );
            $subject = $row['topic'];
            $row['keywords'] = seo_analista_related_queries($subject, (array) ($page_query_map[$path] ?? array()), 8, $post_type === 'page' ? 0.38 : 0.34);
            $row['evidence'] = $row['keywords'];
            seo_analista_enrich_keyword_directive($row);
            $row['source'] = implode(' + ', $row['sources']);
            if ($row['priority'] >= 45) $rows[] = $row;
        }

        // Productos: priorizamos los que Search Console ya ha mostrado. Asi la
        // lista de trabajo se mantiene accionable incluso con catalogos grandes.
        $product_slugs = array();
        foreach ((array) ($data['pages'] ?? array()) as $gsc_page) {
            $path = seo_analista_path_key($gsc_page['page_url'] ?? '');
            if (preg_match('#^/producto/([^/]+)$#', $path, $m)) $product_slugs[$m[1]] = true;
        }
        if ($product_slugs) {
            $slugs = array_keys($product_slugs);
            foreach (array_chunk($slugs, 300) as $chunk) {
                $placeholders = implode(',', array_fill(0, count($chunk), '%s'));
                // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Trusted internal table identifiers; generated value placeholders are bound using wpdb::prepare (WordPress 5.8 compatibility).
                $sql = $wpdb->prepare(
                    "SELECT ID, post_title, post_name, post_content, post_excerpt, post_modified\n                     FROM {$wpdb->posts}\n                     WHERE post_status='publish' AND post_type='product' AND post_name IN ({$placeholders})",
                    $chunk
                );
                // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
                // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- $sql is the direct result of $wpdb->prepare() with generated slug placeholders. Native live read/write of plugin-owned tables, retaining immediate reporting consistency; query values are prepared.
                $products = (array) $wpdb->get_results($sql, ARRAY_A);
                foreach ($products as $post) {
                    $id = absint($post['ID'] ?? 0);
                    $url = (string) get_permalink($id);
                    if (!$id || $url === '') continue;
                    $path = seo_analista_path_key($url);
                    $gsc = (array) ($gsc_map[$path] ?? array());
                    $previous = (array) ($previous_map[$path] ?? array());
                    $cats = wp_get_post_terms($id, 'product_cat', array('fields' => 'ids'));
                    $cat_count = is_wp_error($cats) ? 0 : count((array) $cats);
                    $source = array(
                        'title' => (string) ($post['post_title'] ?? ''),
                        'content' => (string) ($post['post_content'] ?? ''),
                'modified_at' => (string) ($post['post_modified'] ?? ''),
                        'excerpt' => (string) ($post['post_excerpt'] ?? ''),
                        'seo_meta' => seo_analista_seo_meta_snapshot($id),
                    );
                    $row = seo_analista_build_entity_work_row(
                        'product', $id, $source['title'], $url, $source, $gsc, $previous,
                        (int) ($vocab_map['product:' . $id] ?? 0), $cat_count, (string) get_edit_post_link($id, '')
                    );
                    $row['keywords'] = seo_analista_related_queries($row['topic'], (array) ($page_query_map[$path] ?? array()), 6, 0.52);
                    $row['evidence'] = $row['keywords'];
                    seo_analista_enrich_keyword_directive($row);
                    $row['source'] = implode(' + ', $row['sources']);
                    if ($row['priority'] >= 45) $rows[] = $row;
                }
            }
        }

        // Categorías: combinamos literatura, Vocabulary, profundidad de surtido
        // y rendimiento orgánico de la URL de archivo.
        if (taxonomy_exists('product_cat')) {
            $terms = get_terms(array('taxonomy' => 'product_cat', 'hide_empty' => false));
            if (!is_wp_error($terms)) {
                foreach ((array) $terms as $term) {
                    $term_id = absint($term->term_id ?? 0);
                    if (!$term_id) continue;
                    $url = get_term_link($term, 'product_cat');
                    if (is_wp_error($url)) $url = '';
                    $path = seo_analista_path_key($url);
                    $gsc = (array) ($gsc_map[$path] ?? array());
                    $previous = (array) ($previous_map[$path] ?? array());
                    $stored = (array) ($category_content_map[$term_id] ?? array());
                    $category_tags = array_values(array_filter(array_map('trim', preg_split('/[,;\n]+/u', (string) ($stored['category'] ?? '')))));
                    $source = array(
                        'title' => (string) ($term->name ?? ''),
                        'content' => (string) (($stored['description'] ?? '') !== '' ? $stored['description'] : ($term->description ?? '')),
                        'modified_at' => (string) ($stored['modified_at'] ?? ''),
                        'excerpt' => (string) ($stored['excerpt'] ?? ''),
                        'seo_meta' => array('title' => '', 'description' => ''),
                        'tag_count' => count($category_tags),
                        'scope' => (string) ($stored['ambito'] ?? ''),
                    );
                    $row = seo_analista_build_entity_work_row(
                        'category', $term_id, $source['title'], (string) $url, $source, $gsc, $previous,
                        (int) ($vocab_map['product_cat:' . $term_id] ?? 0), (int) ($term->count ?? 0),
                        (string) get_edit_term_link($term_id, 'product_cat')
                    );
                    $row['catalog'] = array(
                        'category' => $source['title'],
                        'products' => (int) ($term->count ?? 0),
                        'term_id' => $term_id,
                    );
                    $row['keywords'] = seo_analista_related_queries($row['topic'], (array) ($page_query_map[$path] ?? array()), 8, 0.32);
                    $row['evidence'] = $row['keywords'];
                    seo_analista_enrich_keyword_directive($row);
                    $row['source'] = implode(' + ', $row['sources']);
                    if ($row['priority'] >= 45) $rows[] = $row;
                }
            }
        }

        usort($rows, static function ($a, $b) {
            $diff = (int) ($b['priority'] ?? 0) <=> (int) ($a['priority'] ?? 0);
            if ($diff) return $diff;
            return (float) ($b['metrics']['impressions'] ?? 0) <=> (float) ($a['metrics']['impressions'] ?? 0);
        });

        $cache[$days] = $rows;
        return array_slice($cache[$days], 0, $requested_limit);
    }
}

if (!function_exists('seo_analista_literature_work')) {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
    function seo_analista_literature_work($days = 28, $limit = 80) {
        $rows = array();
        foreach (seo_analista_content_work($days, 220) as $row) {
            $type = (string) ($row['entity']['type'] ?? '');
            if (in_array($type, array('post','page','landing','product'), true)) $rows[] = $row;
        }
        return array_slice($rows, 0, max(10, min(150, absint($limit))));
    }
}

if (!function_exists('seo_analista_structure_work')) {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
    function seo_analista_structure_work($days = 28, $limit = 80) {
        $rows = array();
        foreach (seo_analista_content_work($days, 220) as $row) {
            $type = (string) ($row['entity']['type'] ?? '');
            if (in_array($type, array('category','cluster','hub_primary','hub_secondary'), true)) $rows[] = $row;
        }
        return array_slice($rows, 0, max(10, min(150, absint($limit))));
    }
}

if (!function_exists('seo_analista_local_category_context')) {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
    function seo_analista_local_category_context($topic) {
        static $terms_cache = null;
        $topic = seo_analista_clean_query($topic);
        if ($topic === '' || !taxonomy_exists('product_cat')) return array();
        if (null === $terms_cache) {
            $terms_cache = get_terms(array('taxonomy' => 'product_cat', 'hide_empty' => false));
            if (is_wp_error($terms_cache)) $terms_cache = array();
        }

        $best = array();
        $best_score = 0.0;
        $topic_norm = seo_analista_normalize_text($topic);
        foreach ((array) $terms_cache as $term) {
            $name = (string) ($term->name ?? '');
            if ($name === '') continue;
            $slug = str_replace('-', ' ', (string) ($term->slug ?? ''));
            $score = max(
                seo_analista_text_similarity($topic, $name),
                seo_analista_text_similarity($topic, $slug)
            );
            $name_norm = seo_analista_normalize_text($name);
            if ($topic_norm !== '' && $name_norm !== '') {
                if ($topic_norm === $name_norm) $score = 1.0;
                elseif (strpos($topic_norm, $name_norm) !== false || strpos($name_norm, $topic_norm) !== false) $score = max($score, 0.82);
            }
            if ($score > $best_score) {
                $url = get_term_link($term, 'product_cat');
                if (is_wp_error($url)) $url = '';
                $best_score = $score;
                $best = array(
                    'category' => $name,
                    'category_name' => $name,
                    'term_id' => absint($term->term_id ?? 0),
                    'category_id' => absint($term->term_id ?? 0),
                    'category_url' => (string) $url,
                    'products' => (int) ($term->count ?? 0),
                    'product_count' => (int) ($term->count ?? 0),
                    'match_score' => $score,
                    'match_source' => 'Analista · categorías locales',
                );
            }
        }
        return $best_score >= 0.58 ? $best : array();
    }
}

if (!function_exists('seo_analista_find_content_entity')) {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
    function seo_analista_find_content_entity($type, $id, $days = 28) {
        $id = absint($id);
        if (!$id) return array();
        foreach (seo_analista_content_work($days, 250) as $row) {
            $entity = (array) ($row['entity'] ?? array());
            if ((string) ($entity['type'] ?? '') === (string) $type && absint($entity['id'] ?? 0) === $id) return $row;
        }
        return array();
    }
}

if (!function_exists('seo_analista_target_allowed_for_topic')) {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
    function seo_analista_target_allowed_for_topic(array $row, $topic) {
        $entity = (array) ($row['entity'] ?? array());
        $type = (string) ($entity['type'] ?? '');
        $role = sanitize_key((string) ($entity['role'] ?? ''));
        $title = (string) ($entity['title'] ?? $row['topic'] ?? '');
        $score = seo_analista_text_similarity($topic, $title);

        // Páginas corporativas no son destino de demanda de catálogo.
        if ($type === 'page' && $role === 'corporate_page') return false;

        // Productos requieren mucha más afinidad que categorías/hubs: evita
        // que una marca compartida convierta otro SKU en el target elegido.
        if ($type === 'product' && $score < 0.62) return false;
        if (in_array($type, array('category','cluster','hub_primary','hub_secondary'), true) && $score < 0.40) return false;
        if ($type === 'landing' && $score < 0.44) return false;
        if (in_array($type, array('post','page'), true) && $score < 0.46) return false;
        return true;
    }
}

if (!function_exists('seo_analista_new_destination_decision')) {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
    function seo_analista_new_destination_decision($topic) {
        $topic = seo_analista_clean_query($topic);
        if ($topic === '') return array('action'=>'INVESTIGAR_COBERTURA','kind'=>'investigate','related_categories'=>array());

        $related = array();
        if (taxonomy_exists('product_cat')) {
            $terms = get_terms(array('taxonomy'=>'product_cat','hide_empty'=>false));
            if (!is_wp_error($terms)) {
                foreach ((array) $terms as $term) {
                    $score = seo_analista_text_similarity($topic, (string) ($term->name ?? ''));
                    if ($score < 0.28) continue;
                    $url = get_term_link($term, 'product_cat');
                    $related[] = array(
                        'id'=>absint($term->term_id ?? 0),
                        'title'=>(string) ($term->name ?? ''),
                        'url'=>is_wp_error($url) ? '' : (string) $url,
                        'score'=>round($score,3),
                    );
                }
            }
        }
        usort($related, static function($a,$b){ return (float) ($b['score'] ?? 0) <=> (float) ($a['score'] ?? 0); });
        $related = array_slice($related,0,8);
        $intent = seo_analista_intent($topic);

        if (count($related) >= 2) {
            return array(
                'action'=>'CREAR_LANDING',
                'kind'=>'landing',
                'related_categories'=>$related,
                'reason'=>'No hay propietario local claro y la demanda cruza varias familias: candidata a landing transversal, no a duplicar cluster/hub/categoría.',
            );
        }
        if (!in_array($intent,array('comercial','transaccional'),true)) {
            return array(
                'action'=>'CREAR_POST',
                'kind'=>'post',
                'related_categories'=>$related,
                'reason'=>'No hay cobertura local suficiente y la intención es informativa: candidata a post específico.',
            );
        }
        return array(
            'action'=>'INVESTIGAR_COBERTURA',
            'kind'=>'investigate',
            'related_categories'=>$related,
            'reason'=>'Demanda comercial sin propietario claro: resolver si falta categoría, surtido o landing antes de crear una URL.',
        );
    }
}

if (!function_exists('seo_analista_find_best_local_target')) {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
    function seo_analista_find_best_local_target($topic, $days = 28) {
        $topic = seo_analista_clean_query($topic);
        if ($topic === '') return array();

        // Una categoría local claramente equivalente tiene prioridad sobre
        // cualquier parecido léxico con hubs, páginas o productos.
        $local_category = seo_analista_local_category_context($topic);
        if ($local_category) {
            $category_row = seo_analista_find_content_entity('category', (int) ($local_category['term_id'] ?? 0), $days);
            if ($category_row) return array('score' => (float) ($local_category['match_score'] ?? 0.8), 'row' => $category_row);
        }

        $best = array();
        $best_score = 0.0;
        foreach (seo_analista_content_work($days, 250) as $row) {
            $entity = (array) ($row['entity'] ?? array());
            if (!$entity || !seo_analista_target_allowed_for_topic($row, $topic)) continue;

            $score = seo_analista_text_similarity($topic, $entity['title'] ?? '');
            foreach ((array) ($row['keywords'] ?? array()) as $keyword) {
                $score = max($score, seo_analista_text_similarity($topic, $keyword) * 0.94);
            }
            $type = (string) ($entity['type'] ?? '');
            if ($type === 'category') $score += 0.12;
            elseif ($type === 'hub_secondary') $score += 0.10;
            elseif ($type === 'hub_primary') $score += 0.08;
            elseif ($type === 'cluster') $score += 0.06;
            elseif ($type === 'landing') $score += 0.05;
            elseif ($type === 'product') $score -= 0.05;
            $score = min(1.0, max(0.0, $score));

            if ($score > $best_score) {
                $best_score = $score;
                $best = $row;
            }
        }
        if ($best_score < 0.46) return array();
        return array('score' => $best_score, 'row' => $best);
    }
}

