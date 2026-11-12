<?php
/**
 * Analista 3.8.1 - capa de validacion antes de priorizar.
 *
 * Reglas:
 * - destino y entidad antes que accion editorial;
 * - prioridad y confianza son dimensiones independientes;
 * - muestras pequenas nunca convierten por si solas una posicion en oportunidad;
 * - Analista diagnostica y deriva, no ejecuta cambios editoriales.
 */

defined('ABSPATH') || exit;

require_once __DIR__ . '/analista-segunda-revision.php';

if (!defined('SEO_ANALISTA_TASK_HISTORY_OPTION')) {
    define('SEO_ANALISTA_TASK_HISTORY_OPTION', 'seo_analista_task_history_381');
}

if (!function_exists('seo_analista_validation_settings')) {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
    function seo_analista_validation_settings() {
        $settings = function_exists('seo_analista_get_settings') ? (array) seo_analista_get_settings() : array();
        $raw = (array) ($settings['validation'] ?? array());
        return array(
            'small_sample_max' => max(1, min(20, absint($raw['small_sample_max'] ?? 4))),
            'reliable_impressions' => max(5, min(100, absint($raw['reliable_impressions'] ?? 20))),
            'high_confidence_impressions' => max(10, min(500, absint($raw['high_confidence_impressions'] ?? 50))),
            'minimum_previous_base' => max(1, min(50, absint($raw['minimum_previous_base'] ?? 5))),
            'partial_match_min' => max(40, min(90, absint($raw['partial_match_min'] ?? 58))),
            'exact_match_min' => max(60, min(99, absint($raw['exact_match_min'] ?? 82))),
        );
    }
}

if (!function_exists('seo_analista_primary_query')) {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
    function seo_analista_primary_query(array $row) {
        foreach (array('query','query_text') as $key) {
            $value = trim((string) ($row[$key] ?? ''));
            if ($value !== '') return function_exists('seo_analista_clean_query') ? seo_analista_clean_query($value) : sanitize_text_field($value);
        }
        foreach (array('keywords','evidence','related_topics') as $key) {
            foreach ((array) ($row[$key] ?? array()) as $value) {
                $value = trim((string) $value);
                if ($value !== '') return function_exists('seo_analista_clean_query') ? seo_analista_clean_query($value) : sanitize_text_field($value);
            }
        }
        return function_exists('seo_analista_clean_query')
            ? seo_analista_clean_query((string) ($row['topic'] ?? ''))
            : sanitize_text_field((string) ($row['topic'] ?? ''));
    }
}

if (!function_exists('seo_analista_model_tokens')) {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
    function seo_analista_model_tokens($text) {
        $text = remove_accents(strtolower(wp_strip_all_tags((string) $text)));
        $tokens = array();
        if (preg_match_all('/\b[a-z0-9]+(?:[-\/]?[a-z0-9]+)*\b/u', $text, $matches)) {
            foreach ((array) ($matches[0] ?? array()) as $token) {
                $token = trim((string) $token);
                if ($token === '' || !preg_match('/\d/', $token)) continue;
                if (preg_match('/^(\d+|\d+(mm|cm|m|kg|g|w|v|a|ah|nm|l|ml))$/', $token)) continue;
                if (strlen($token) < 4) continue;
                $tokens[] = $token;
            }
        }
        return array_values(array_unique($tokens));
    }
}

if (!function_exists('seo_analista_identity_words')) {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
    function seo_analista_identity_words($text) {
        $text = function_exists('seo_analista_normalize_text')
            ? seo_analista_normalize_text($text)
            : sanitize_text_field($text);
        $stop = array(
            'profesional','professional','producto','productos','herramienta','herramientas',
            'maquina','maquinas','equipo','equipos','para','con','sin','kit','juego','set',
            'bateria','cargador','electrico','electrica','inalambrico','inalambrica'
        );
        $out = array();
        foreach (preg_split('/\s+/', $text) as $token) {
            if (strlen($token) < 3 || in_array($token, $stop, true) || ctype_digit($token)) continue;
            $out[] = $token;
        }
        return array_values(array_unique($out));
    }
}

if (!function_exists('seo_analista_resolve_target_url')) {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
    function seo_analista_resolve_target_url(array $row) {
        $entity = (array) ($row['entity'] ?? array());
        $target = (array) ($row['target'] ?? array());
        $url = trim((string) ($entity['url'] ?? $target['url'] ?? ''));
        $type = sanitize_key((string) ($entity['type'] ?? ''));
        $id = absint($entity['id'] ?? 0);

        if ($url === '' && $id > 0) {
            if ($type === 'category') {
                $term_url = get_term_link($id, 'product_cat');
                if (!is_wp_error($term_url)) $url = (string) $term_url;
            } elseif (in_array($type, array('post','page','product','cluster','hub_primary','hub_secondary'), true)) {
                $candidate = get_permalink($id);
                if ($candidate) $url = (string) $candidate;
            }
        }

        if ($url === '') {
            return array('resolved'=>false,'url'=>'','canonical'=>false,'reason'=>'Destino sin resolver.');
        }

        $host = strtolower((string) wp_parse_url($url, PHP_URL_HOST));
        $own = strtolower((string) wp_parse_url(home_url('/'), PHP_URL_HOST));
        $host = preg_replace('/^www\./', '', $host);
        $own = preg_replace('/^www\./', '', $own);
        if ($host === '' || $own === '' || $host !== $own) {
            return array('resolved'=>false,'url'=>esc_url_raw($url),'canonical'=>false,'reason'=>'La URL atribuida no pertenece al sitio canónico.');
        }

        return array('resolved'=>true,'url'=>esc_url_raw($url),'canonical'=>true,'reason'=>'URL canónica local resuelta.');
    }
}

if (!function_exists('seo_analista_entity_identity_text')) {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
    function seo_analista_entity_identity_text(array $row) {
        $entity = (array) ($row['entity'] ?? array());
        $parts = array((string) ($entity['title'] ?? $row['topic'] ?? ''));
        $type = sanitize_key((string) ($entity['type'] ?? ''));
        $id = absint($entity['id'] ?? 0);

        if ($type === 'product' && $id > 0 && function_exists('wc_get_product')) {
            $product = wc_get_product($id);
            if ($product && is_a($product, 'WC_Product')) {
                $parts[] = (string) $product->get_name();
                $parts[] = (string) $product->get_sku();
                foreach (array('_mpn','mpn','_gtin','_ean','ean','_supplier_sku') as $meta_key) {
                    $parts[] = (string) get_post_meta($id, $meta_key, true);
                }
                foreach (array('pa_marca','pa_brand','marca','brand') as $taxonomy) {
                    if (!taxonomy_exists($taxonomy)) continue;
                    $terms = wp_get_post_terms($id, $taxonomy, array('fields'=>'names'));
                    if (!is_wp_error($terms)) $parts = array_merge($parts, (array) $terms);
                }
            }
        }

        return trim(implode(' ', array_filter(array_map('strval', $parts))));
    }
}

if (!function_exists('seo_analista_intent_fit_for_entity')) {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
    function seo_analista_intent_fit_for_entity($query, array $row) {
        $query_intent = function_exists('seo_analista_intent')
            ? sanitize_key((string) seo_analista_intent($query))
            : 'unknown';
        $declared_intent = sanitize_key((string) ($row['intent'] ?? ''));
        $entity_type = sanitize_key((string) ($row['entity']['type'] ?? ''));

        $fit = 'medium';
        if ($declared_intent !== '' && $query_intent !== 'unknown') {
            $fit = $declared_intent === $query_intent ? 'high' : 'medium';
        }

        if (in_array($entity_type, array('product','category','cluster','hub_primary','hub_secondary'), true)) {
            if (in_array($query_intent, array('transaccional','comercial'), true)) $fit = 'high';
            elseif ($query_intent === 'informativa') $fit = $declared_intent === 'informativa' ? 'high' : 'medium';
        } elseif (in_array($entity_type, array('post','page'), true)) {
            if ($query_intent === 'informativa') $fit = 'high';
            elseif ($query_intent === 'transaccional') $fit = 'low';
        }

        return array(
            'query_intent'=>$query_intent,
            'declared_intent'=>$declared_intent,
            'fit'=>$fit,
        );
    }
}


if (!function_exists('seo_analista_entity_row_from_term')) {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
    function seo_analista_entity_row_from_term($term_id) {
        $term_id = absint($term_id);
        if ($term_id < 1) return array();
        $term = get_term($term_id, 'product_cat');
        if (!$term instanceof WP_Term || is_wp_error($term)) return array();
        $url = get_term_link($term, 'product_cat');
        if (is_wp_error($url) || !$url) return array();
        return array(
            'entity'=>array(
                'type'=>'category',
                'type_label'=>'Categoría',
                'id'=>$term_id,
                'title'=>(string) $term->name,
                'url'=>(string) $url,
                'edit_url'=>admin_url('term.php?taxonomy=product_cat&tag_ID=' . $term_id . '&post_type=product'),
            ),
            'target'=>array(
                'title'=>(string) $term->name,
                'url'=>(string) $url,
            ),
            'catalog'=>array(
                'term_id'=>$term_id,
                'products'=>(int) $term->count,
            ),
        );
    }
}

if (!function_exists('seo_analista_try_auto_resolve_target')) {
    /**
     * Intenta resolver un destino local antes de devolver INVESTIGAR.
     * Solo acepta candidatos que pasan de nuevo por el validador 3.8.1.
     */
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
    function seo_analista_try_auto_resolve_target(array $row, $query, $days = 28) {
        $query = trim((string) $query);
        $result = array(
            'attempted'=>false,
            'resolved'=>false,
            'method'=>'',
            'score'=>0.0,
            'candidate'=>array(),
            'reason'=>'',
        );
        if ($query === '') return array($row, $result);

        $current_validation = seo_analista_validate_query_entity($query, $row);
        if (!empty($current_validation['target']['resolved'])
            && in_array((string) ($current_validation['match_type'] ?? ''), array('exact','partial'), true)
            && empty($current_validation['model_conflict'])) {
            $result['reason'] = 'El destino actual ya está suficientemente validado.';
            return array($row, $result);
        }

        $result['attempted'] = true;
        $query_models = seo_analista_model_tokens($query);
        $candidates = array();

        // 1) Taxonomía de producto: fuente preferente para consultas de familia.
        if (function_exists('seo_analista_local_category_context')) {
            $local = (array) seo_analista_local_category_context($query);
            $term_id = absint($local['term_id'] ?? $local['category_id'] ?? 0);
            $score = (float) ($local['match_score'] ?? 0);
            if ($term_id > 0 && $score >= 0.58) {
                $candidate = seo_analista_entity_row_from_term($term_id);
                if ($candidate) {
                    $candidate['topic'] = (string) ($row['topic'] ?? $query);
                    $candidate['intent'] = (string) ($row['intent'] ?? '');
                    $candidate['metrics'] = (array) ($row['metrics'] ?? array());
                    $candidate['objective'] = (array) ($row['objective'] ?? array());
                    $candidate['issues'] = (array) ($row['issues'] ?? array());
                    $candidate['sources'] = (array) ($row['sources'] ?? array());
                    $candidate['source'] = (string) ($row['source'] ?? '');
                    $candidate['action'] = (string) ($row['action'] ?? '');
                    $candidate['recommended_changes'] = (array) ($row['recommended_changes'] ?? array());
                    $candidates[] = array(
                        'row'=>array_replace_recursive($row, $candidate),
                        'method'=>'taxonomy_product_cat',
                        'score'=>$score,
                    );
                }
            }
        }

        // 2) Resto de entidades locales ya conocidas por Analista.
        if (function_exists('seo_analista_find_best_local_target')) {
            $local_target = (array) seo_analista_find_best_local_target($query, $days);
            if (!empty($local_target['row']) && is_array($local_target['row'])) {
                $local_row = (array) $local_target['row'];
                $candidate_row = $row;
                foreach (array('entity','target','catalog') as $replace_key) {
                    if (!empty($local_row[$replace_key]) && is_array($local_row[$replace_key])) {
                        $candidate_row[$replace_key] = $local_row[$replace_key];
                    }
                }
                // La señal, score y bucket pertenecen a la consulta actual,
                // nunca al registro histórico usado solo como candidato.
                $candidate_row['metrics'] = (array) ($row['metrics'] ?? array());
                $candidate_row['objective'] = (array) ($row['objective'] ?? array());
                $candidate_row['issues'] = (array) ($row['issues'] ?? array());
                $candidate_row['sources'] = (array) ($row['sources'] ?? array());
                $candidate_row['source'] = (string) ($row['source'] ?? '');
                $candidate_row['action'] = (string) ($row['action'] ?? '');
                $candidate_row['recommended_changes'] = (array) ($row['recommended_changes'] ?? array());
                $candidate_row['priority'] = (int) ($row['priority'] ?? 0);
                $candidate_row['work_bucket'] = (string) ($row['work_bucket'] ?? 'VIGILAR');
                $candidate_row['confidence'] = (int) ($row['confidence'] ?? 0);
                $candidate_row['priority_breakdown'] = (array) ($row['priority_breakdown'] ?? array());
                $candidate_row['reason'] = (string) ($row['reason'] ?? '');
                $candidate_row['why_now'] = (array) ($row['why_now'] ?? array());
                $candidate_row['measurement'] = (array) ($row['measurement'] ?? array());
                $candidate_row['growth_quality'] = (array) ($row['growth_quality'] ?? array());
                $candidates[] = array(
                    'row'=>$candidate_row,
                    'method'=>'local_content_index',
                    'score'=>(float) ($local_target['score'] ?? 0),
                );
            }
        }

        usort($candidates, static function($left, $right) {
            return ((float) ($right['score'] ?? 0)) <=> ((float) ($left['score'] ?? 0));
        });

        foreach ($candidates as $candidate) {
            $candidate_row = (array) ($candidate['row'] ?? array());
            $entity = (array) ($candidate_row['entity'] ?? array());
            $type = sanitize_key((string) ($entity['type'] ?? ''));
            $score = (float) ($candidate['score'] ?? 0);
            $candidate_models = seo_analista_model_tokens(seo_analista_entity_identity_text($candidate_row));

            // Consultas con modelo no se degradan a categorías genéricas.
            if ($query_models && in_array($type, array('category','cluster','hub_primary','hub_secondary'), true) && $score < 0.90) {
                continue;
            }
            if ($type === 'product' && $query_models && $candidate_models && !array_intersect($query_models, $candidate_models)) {
                continue;
            }

            $candidate_validation = seo_analista_validate_query_entity($query, $candidate_row);
            if (empty($candidate_validation['target']['resolved'])
                || !in_array((string) ($candidate_validation['match_type'] ?? ''), array('exact','partial'), true)
                || !empty($candidate_validation['model_conflict'])) {
                continue;
            }

            $minimum = $type === 'product' ? 0.72 : (in_array($type, array('category','cluster','hub_primary','hub_secondary'), true) ? 0.64 : 0.70);
            if ($score < $minimum && (float) ($candidate_validation['similarity'] ?? 0) < $minimum) {
                continue;
            }

            $result['resolved'] = true;
            $result['method'] = (string) ($candidate['method'] ?? '');
            $result['score'] = max($score, (float) ($candidate_validation['similarity'] ?? 0));
            $result['candidate'] = array(
                'type'=>$type,
                'id'=>absint($entity['id'] ?? 0),
                'title'=>(string) ($entity['title'] ?? ''),
                'url'=>(string) ($candidate_validation['target']['url'] ?? ''),
                'match_type'=>(string) ($candidate_validation['match_type'] ?? ''),
                'match_confidence'=>absint($candidate_validation['match_confidence'] ?? 0),
            );
            $result['reason'] = 'Destino local resuelto automáticamente y revalidado contra la consulta.';
            return array($candidate_row, $result);
        }

        $result['reason'] = 'No se encontró un candidato local suficientemente fuerte y seguro.';
        return array($row, $result);
    }
}

if (!function_exists('seo_analista_validate_query_entity')) {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
    function seo_analista_validate_query_entity($query, array $row) {
        $query = trim((string) $query);
        $entity = (array) ($row['entity'] ?? array());
        $intent_profile = seo_analista_intent_fit_for_entity($query, $row);
        $type = sanitize_key((string) ($entity['type'] ?? ''));
        $title = trim((string) ($entity['title'] ?? $row['topic'] ?? ''));
        $target = seo_analista_resolve_target_url($row);

        if ($query === '') {
            return array(
                'match_type'=>'unproven','match_confidence'=>20,'intent_fit'=>(string) ($intent_profile['fit'] ?? 'unknown'),'query_intent'=>(string) ($intent_profile['query_intent'] ?? 'unknown'),
                'model_conflict'=>false,'target'=>$target,'reason'=>'No hay una consulta concreta para validar la atribución.'
            );
        }

        if (!$target['resolved'] || $title === '') {
            return array(
                'match_type'=>'unproven','match_confidence'=>15,'intent_fit'=>(string) ($intent_profile['fit'] ?? 'unknown'),'query_intent'=>(string) ($intent_profile['query_intent'] ?? 'unknown'),
                'model_conflict'=>false,'target'=>$target,'reason'=>'La consulta existe, pero el destino no está resuelto de forma verificable.'
            );
        }

        $identity = seo_analista_entity_identity_text($row);
        $query_models = seo_analista_model_tokens($query);
        $entity_models = seo_analista_model_tokens($identity);
        $query_words = seo_analista_identity_words($query);
        $entity_words = seo_analista_identity_words($identity);
        $shared_words = array_intersect($query_words, $entity_words);

        $model_conflict = false;
        if ($query_models && $entity_models && !array_intersect($query_models, $entity_models) && $shared_words) {
            $model_conflict = true;
        }

        if ($model_conflict) {
            return array(
                'match_type'=>'conflict','match_confidence'=>5,'intent_fit'=>'low','query_intent'=>(string) ($intent_profile['query_intent'] ?? 'unknown'),
                'model_conflict'=>true,'target'=>$target,
                'reason'=>'Conflicto de modelo o variante: la consulta contiene identificadores distintos de los de la entidad atribuida.',
                'query_models'=>$query_models,'entity_models'=>$entity_models
            );
        }

        $similarity = function_exists('seo_analista_text_similarity')
            ? (float) seo_analista_text_similarity($query, $identity)
            : 0.0;

        if ($type === 'product' && $query_models && $entity_models && array_intersect($query_models, $entity_models)) {
            return array(
                'match_type'=>'exact','match_confidence'=>96,'intent_fit'=>(string) ($intent_profile['fit'] ?? 'medium'),'query_intent'=>(string) ($intent_profile['query_intent'] ?? 'unknown'),
                'model_conflict'=>false,'target'=>$target,'reason'=>'Modelo/identificador compatible con la entidad.',
                'similarity'=>$similarity,'query_models'=>$query_models,'entity_models'=>$entity_models
            );
        }

        // Una consulta específica de modelo no se transfiere a una categoría/hub
        // genéricos solo por compartir taxonomía.
        if (in_array($type, array('category','cluster','hub_primary','hub_secondary'), true) && $query_models && $similarity < 0.58) {
            return array(
                'match_type'=>'unproven','match_confidence'=>28,'intent_fit'=>(string) ($intent_profile['fit'] ?? 'low'),'query_intent'=>(string) ($intent_profile['query_intent'] ?? 'unknown'),
                'model_conflict'=>false,'target'=>$target,
                'reason'=>'La consulta es específica de modelo/variante y la entidad es demasiado amplia para asumir que sea su destino SEO.',
                'similarity'=>$similarity,'query_models'=>$query_models
            );
        }

        $settings = seo_analista_validation_settings();
        $exact_min = $settings['exact_match_min'] / 100;
        $partial_min = $settings['partial_match_min'] / 100;

        if ($similarity >= $exact_min) {
            $type_label = 'exact';
            $confidence = min(94, max(82, (int) round($similarity * 100)));
            $intent_fit = 'high';
        } elseif ($similarity >= $partial_min) {
            $type_label = 'partial';
            $confidence = min(81, max(58, (int) round($similarity * 100)));
            $intent_fit = 'medium';
        } else {
            $type_label = 'unproven';
            $confidence = min(50, max(18, (int) round($similarity * 100)));
            $intent_fit = 'low';
        }

        return array(
            'match_type'=>$type_label,
            'match_confidence'=>$confidence,
            'intent_fit'=>(string) ($intent_profile['fit'] ?? $intent_fit),
            'query_intent'=>(string) ($intent_profile['query_intent'] ?? 'unknown'),
            'model_conflict'=>false,
            'target'=>$target,
            'reason'=>$type_label === 'unproven'
                ? 'La afinidad semántica/taxonómica no basta para demostrar que esta entidad sea el destino correcto.'
                : 'La consulta y la entidad muestran una correspondencia ' . ($type_label === 'exact' ? 'fuerte' : 'parcial') . '.',
            'similarity'=>$similarity,
            'query_models'=>$query_models,
            'entity_models'=>$entity_models
        );
    }
}

if (!function_exists('seo_analista_evidence_profile')) {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
    function seo_analista_evidence_profile(array $row) {
        $settings = seo_analista_validation_settings();
        $metrics = (array) ($row['metrics'] ?? array());
        $impressions = max(0, (int) round((float) ($metrics['impressions'] ?? 0)));
        $clicks = max(0, (int) round((float) ($metrics['clicks'] ?? 0)));
        $queries = max(0, (int) ($metrics['queries'] ?? count((array) ($row['keywords'] ?? array()))));
        $previous = max(0, (int) round((float) ($metrics['previous_impressions'] ?? 0)));
        $delta = isset($metrics['impressions_delta']) ? (float) $metrics['impressions_delta'] : ($impressions - $previous);
        $sources = array_values(array_unique(array_filter((array) ($row['sources'] ?? array()))));
        $legacy_source = trim((string) ($row['source'] ?? ''));
        if ($legacy_source !== '') {
            foreach (preg_split('/\s*[\/|,+]\s*/', $legacy_source) as $source_name) {
                $source_name = trim((string) $source_name);
                if ($source_name !== '') $sources[] = $source_name;
            }
            $sources = array_values(array_unique($sources));
        }
        $independent = max(1, count($sources));
        if ($queries > 0) $independent += min(3, $queries);

        $small = $impressions > 0 && $impressions <= $settings['small_sample_max'];
        $growth_unstable = $previous < $settings['minimum_previous_base'] && abs($delta) > 0;
        $penalty = 0;
        if ($small) $penalty += 24;
        elseif ($impressions > 0 && $impressions < $settings['reliable_impressions']) $penalty += 10;
        if ($growth_unstable) $penalty += 12;

        if ($impressions >= $settings['high_confidence_impressions'] || ($clicks >= 5 && $queries >= 3)) $level = 'high';
        elseif ($impressions >= $settings['reliable_impressions'] || ($clicks >= 2 && $queries >= 2)) $level = 'medium';
        else $level = 'low';

        return array(
            'level'=>$level,
            'impressions'=>$impressions,
            'clicks'=>$clicks,
            'queries'=>$queries,
            'previous_impressions'=>$previous,
            'absolute_delta'=>$delta,
            'evidence_count'=>$independent,
            'sources'=>$sources,
            'small_sample'=>$small,
            'growth_unstable'=>$growth_unstable,
            'priority_penalty'=>$penalty,
            'settings'=>$settings
        );
    }
}

if (!function_exists('seo_analista_category_commercial_snapshot')) {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
    function seo_analista_category_commercial_snapshot($term_id, $sample_limit = 30) {
        static $cache = array();
        $term_id = absint($term_id);
        $sample_limit = max(5, min(50, absint($sample_limit)));
        $cache_key = $term_id . ':' . $sample_limit;
        if (isset($cache[$cache_key])) return $cache[$cache_key];

        $empty = array(
            'available'=>false,
            'total_products'=>0,
            'sampled_products'=>0,
            'sellable_products'=>0,
            'priced_products'=>0,
            'provider_known'=>0,
            'margin_known'=>0,
            'min_price'=>null,
            'max_price'=>null,
            'average_margin'=>null,
            'providers'=>array(),
        );
        if ($term_id < 1 || !function_exists('wc_get_products')) return $cache[$cache_key] = $empty;

        $term = get_term($term_id, 'product_cat');
        if (!$term instanceof WP_Term || is_wp_error($term)) return $cache[$cache_key] = $empty;

        $ids = wc_get_products(array(
            'status'=>'publish',
            'category'=>array((string) $term->slug),
            'limit'=>$sample_limit,
            'return'=>'ids',
            'orderby'=>'date',
            'order'=>'DESC',
        ));
        if (!is_array($ids)) $ids = array();

        $out = $empty;
        $out['available'] = true;
        $out['total_products'] = (int) $term->count;
        $prices = array();
        $margins = array();
        $providers = array();

        foreach ($ids as $product_id) {
            $product_id = absint($product_id);
            $product = $product_id > 0 ? wc_get_product($product_id) : null;
            if (!$product || !is_a($product, 'WC_Product')) continue;
            $out['sampled_products']++;

            $price = (float) $product->get_price();
            if ($price > 0) {
                $out['priced_products']++;
                $prices[] = $price;
            }
            if ($price > 0 && $product->is_in_stock() && $product->is_purchasable()) {
                $out['sellable_products']++;
            }

            $provider = trim((string) get_post_meta($product_id, '_seo_proveedor', true));
            if ($provider !== '') {
                $out['provider_known']++;
                $providers[$provider] = true;
            }

            $cost = null;
            foreach (array('_seo_precio_proveedor','_cost_price','_purchase_price','_supplier_cost','cost_price') as $cost_key) {
                $raw_cost = get_post_meta($product_id, $cost_key, true);
                if ($raw_cost !== '' && is_numeric($raw_cost) && (float) $raw_cost > 0) {
                    $cost = (float) $raw_cost;
                    break;
                }
            }
            if ($cost !== null && $price > 0) {
                $margin = (($price - $cost) / $price) * 100;
                $margins[] = $margin;
                $out['margin_known']++;
            }
        }

        if ($prices) {
            $out['min_price'] = min($prices);
            $out['max_price'] = max($prices);
        }
        if ($margins) $out['average_margin'] = round(array_sum($margins) / count($margins), 2);
        $out['providers'] = array_keys($providers);
        return $cache[$cache_key] = $out;
    }
}

if (!function_exists('seo_analista_commercial_readiness')) {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
    function seo_analista_commercial_readiness(array $row) {
        $entity = (array) ($row['entity'] ?? array());
        $type = sanitize_key((string) ($entity['type'] ?? ''));
        $id = absint($entity['id'] ?? 0);
        $out = array(
            'status'=>'unknown',
            'label'=>'Comercial no verificado',
            'stock'=>null,
            'price'=>null,
            'supplier'=>null,
            'margin_or_commission'=>null,
            'available'=>null,
            'missing_fields'=>array(),
            'blocking_fields'=>array(),
            'informational_fields'=>array(),
            'category_sample'=>array(),
            'ga4_signals'=>array(
                'available'=>false,
                'sessions'=>null,
                'pageviews'=>null,
                'conversions'=>null,
                'revenue'=>null,
            ),
        );

        $metrics = (array) ($row['metrics'] ?? array());
        $ga4_available = array_key_exists('sessions', $metrics)
            || array_key_exists('pageviews', $metrics)
            || array_key_exists('conversions', $metrics)
            || array_key_exists('revenue', $metrics);
        if ($ga4_available) {
            $out['ga4_signals'] = array(
                'available'=>true,
                'sessions'=>array_key_exists('sessions', $metrics) ? max(0, (float) $metrics['sessions']) : null,
                'pageviews'=>array_key_exists('pageviews', $metrics) ? max(0, (float) $metrics['pageviews']) : null,
                'conversions'=>array_key_exists('conversions', $metrics) ? max(0, (float) $metrics['conversions']) : null,
                'revenue'=>array_key_exists('revenue', $metrics) ? max(0, (float) $metrics['revenue']) : null,
            );
        }

        if ($type === 'product' && $id > 0 && function_exists('wc_get_product')) {
            $product = wc_get_product($id);
            if ($product && is_a($product, 'WC_Product')) {
                $out['stock'] = $product->is_in_stock();
                $out['available'] = $product->is_purchasable();
                $out['price'] = (float) $product->get_price();
                foreach (array('_seo_proveedor','_supplier','supplier','proveedor') as $key) {
                    $value = trim((string) get_post_meta($id, $key, true));
                    if ($value !== '') { $out['supplier'] = $value; break; }
                }
                foreach (array('_seo_precio_proveedor','_cost_price','_purchase_price','_supplier_cost','cost_price') as $key) {
                    $cost = get_post_meta($id, $key, true);
                    if ($cost !== '' && is_numeric($cost) && (float) $cost > 0 && $out['price'] > 0) {
                        $out['margin_or_commission'] = round((($out['price'] - (float) $cost) / $out['price']) * 100, 2);
                        break;
                    }
                }
                $blocking = array();
                if (!$out['stock']) $blocking[] = 'stock';
                if (!$out['available']) $blocking[] = 'disponibilidad';
                if ($out['price'] <= 0) $blocking[] = 'precio_final';
                if ($out['supplier'] === null) $blocking[] = 'proveedor';
                if ($out['margin_or_commission'] === null) $blocking[] = 'margen_comision';
                $informational = empty($out['ga4_signals']['available']) ? array('ga4') : array();
                $out['blocking_fields'] = $blocking;
                $out['informational_fields'] = $informational;
                $out['missing_fields'] = array_values(array_unique(array_merge($blocking, $informational)));

                if (!$out['stock'] || !$out['available'] || $out['price'] <= 0) {
                    $out['status'] = 'not_ready';
                    $out['label'] = 'Oferta no preparada · bloquea ' . implode(', ', $blocking);
                } elseif ($blocking) {
                    $out['status'] = 'partial';
                    $out['label'] = 'Oferta parcialmente verificada · bloquea ' . implode(', ', $blocking);
                } else {
                    $out['status'] = 'verified';
                    $out['label'] = 'Oferta comercial verificada'
                        . ($informational ? ' · GA4 no disponible para esta tarea (no bloqueante)' : '');
                }
            }
        } elseif ($type === 'category') {
            $products = isset($row['catalog']['products']) ? (int) $row['catalog']['products'] : null;
            $sample = $id > 0 ? seo_analista_category_commercial_snapshot($id, 30) : array();
            $out['category_sample'] = $sample;
            if (!empty($sample['available'])) {
                $products = (int) ($sample['total_products'] ?? $products ?? 0);
                $out['available'] = ((int) ($sample['sellable_products'] ?? 0)) > 0;
                $out['stock'] = $out['available'];
                if (isset($sample['min_price']) && $sample['min_price'] !== null) {
                    $out['price'] = array(
                        'min'=>(float) $sample['min_price'],
                        'max'=>(float) ($sample['max_price'] ?? $sample['min_price']),
                    );
                }
                $providers = array_values(array_filter((array) ($sample['providers'] ?? array())));
                $out['supplier'] = $providers ? implode(', ', array_slice($providers, 0, 5)) : null;
                $out['margin_or_commission'] = $sample['average_margin'] ?? null;
            }

            $blocking = array();
            if ($products === null || $products <= 0) $blocking[] = 'surtido';
            if (empty($out['stock'])) $blocking[] = 'stock_categoria';
            if ($out['price'] === null) $blocking[] = 'precio_categoria';
            if ($out['supplier'] === null) $blocking[] = 'proveedor';
            if ($out['margin_or_commission'] === null) $blocking[] = 'margen_comision';
            $informational = empty($out['ga4_signals']['available']) ? array('ga4') : array();

            $out['blocking_fields'] = array_values(array_unique($blocking));
            $out['informational_fields'] = $informational;
            $out['missing_fields'] = array_values(array_unique(array_merge($blocking, $informational)));

            if ($products !== null && $products <= 0) {
                $out['status'] = 'not_ready';
                $out['label'] = 'Categoría sin surtido acreditado';
            } elseif ($blocking) {
                $sampled = absint($sample['sampled_products'] ?? 0);
                $out['status'] = 'partial';
                $out['label'] = 'Oferta de categoría parcialmente verificada'
                    . ($sampled > 0 ? ' · muestra ' . $sampled . ' productos' : '')
                    . ' · bloquea ' . implode(', ', $blocking);
            } else {
                $sampled = absint($sample['sampled_products'] ?? 0);
                $out['status'] = 'verified';
                $out['label'] = 'Oferta de categoría verificada'
                    . ($sampled > 0 ? ' · muestra ' . $sampled . ' productos' : '')
                    . ($informational ? ' · GA4 no disponible para esta tarea (no bloqueante)' : '');
            }
        }

        return $out;
    }
}

if (!function_exists('seo_analista_editorial_coverage')) {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
    function seo_analista_editorial_coverage(array $row) {
        global $wpdb;
        static $cache = array();

        $entity = (array) ($row['entity'] ?? array());
        $type = sanitize_key((string) ($entity['type'] ?? ''));
        $id = absint($entity['id'] ?? 0);
        $category_ids = array();
        $existing_entity = array(
            'available'=>false,
            'type'=>$type,
            'id'=>$id,
            'title'=>(string) ($entity['title'] ?? ''),
            'url'=>(string) ($entity['url'] ?? $row['target_url'] ?? $row['target']['url'] ?? ''),
            'has_content'=>false,
        );

        if ($id > 0 && in_array($type, array('post','page','product','cluster','hub_primary','hub_secondary'), true)) {
            $existing_post = get_post($id);
            if ($existing_post instanceof WP_Post && $existing_post->post_status === 'publish') {
                $existing_entity['available'] = true;
                $existing_entity['title'] = (string) get_the_title($id);
                $existing_entity['url'] = (string) get_permalink($id);
                $existing_entity['has_content'] = trim(wp_strip_all_tags((string) $existing_post->post_content)) !== ''
                    || trim((string) $existing_post->post_excerpt) !== '';
            }
        } elseif ($type === 'category' && $id > 0) {
            $existing_term = get_term($id, 'product_cat');
            if ($existing_term instanceof WP_Term && !is_wp_error($existing_term)) {
                $existing_entity['available'] = true;
                $term_url = get_term_link($existing_term);
                if (!is_wp_error($term_url)) $existing_entity['url'] = (string) $term_url;
                $existing_entity['title'] = (string) $existing_term->name;
                $existing_entity['has_content'] = trim(wp_strip_all_tags((string) $existing_term->description)) !== ''
                    || trim((string) get_term_meta($id, 'excerpt', true)) !== '';
            }
        }

        if ($type === 'category' && $id > 0) {
            $category_ids[] = $id;
        } elseif ($type === 'product' && $id > 0) {
            $terms = wp_get_post_terms($id, 'product_cat', array('fields'=>'ids'));
            if (!is_wp_error($terms)) $category_ids = array_map('absint', (array) $terms);
        }
        if (!empty($row['catalog']['term_id'])) $category_ids[] = absint($row['catalog']['term_id']);
        $category_ids = array_values(array_unique(array_filter($category_ids)));
        sort($category_ids);
        if (!$category_ids) {
            return array(
                'available'=>(bool) $existing_entity['available'],
                'existing_entity'=>$existing_entity,
                'posts'=>array(),
                'roles'=>array(),
            );
        }

        $key = $type . ':' . $id . '|cats:' . implode('-', $category_ids);
        if (isset($cache[$key])) return $cache[$key];

        $relations = $wpdb->prefix . 'seo_relations';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Native live read/write of plugin-owned tables, retaining immediate reporting consistency; query values are prepared.
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($relations)));
        if ($exists !== $relations) {
            return $cache[$key] = array(
                'available'=>(bool) $existing_entity['available'],
                'existing_entity'=>$existing_entity,
                'posts'=>array(),
                'roles'=>array(),
            );
        }

        $placeholders = implode(',', array_fill(0, count($category_ids), '%d'));
        // Los identificadores proceden exclusivamente de wpdb; %i no es compatible con el WordPress minimo soportado.
        $relations_table = esc_sql($relations);
        $posts_table = esc_sql($wpdb->posts);
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Tablas internas sanitizadas, categorias solo mediante placeholders %d.
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Trusted internal table identifiers; generated value placeholders are bound using wpdb::prepare (WordPress 5.8 compatibility).
        $sql = $wpdb->prepare(
            "SELECT DISTINCT r.source_id
             FROM {$relations_table} r
             INNER JOIN {$posts_table} p ON p.ID = r.source_id
             WHERE r.source_type = 'post'
               AND r.target_type = 'product_cat'
               AND r.relation_type = 'post_to_category'
               AND r.target_id IN ({$placeholders})
               AND p.post_type = 'post'
               AND p.post_status = 'publish'
             LIMIT 20",
            $category_ids
        );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Query is fully prepared above and cached per request by category key.
        $post_ids = $wpdb->get_col($sql);

        $roles = array();
        $posts = array();
        foreach ((array) $post_ids as $post_id) {
            $post_id = absint($post_id);
            if (!$post_id) continue;
            $role = sanitize_key((string) get_post_meta($post_id, '_seo_solucionador_content_role', true));
            if (!in_array($role, array('dependiente_qa_basic','ingeniero_qa_specialized','comparison'), true)) continue;
            $roles[] = $role;
            $posts[] = array('id'=>$post_id,'title'=>(string) get_the_title($post_id),'role'=>$role,'url'=>(string) get_permalink($post_id));
        }

        return $cache[$key] = array(
            'available'=>(bool) ($existing_entity['available'] || $posts),
            'existing_entity'=>$existing_entity,
            'posts'=>$posts,
            'roles'=>array_values(array_unique($roles))
        );
    }
}


if (!function_exists('seo_analista_execution_gate')) {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
    function seo_analista_execution_gate(array $row, array $validation, array $evidence, array $commercial) {
        $base_bucket = (string) ($row['work_bucket'] ?? 'VIGILAR');
        $candidate_bucket = $base_bucket;
        $confidence = max(0, min(100, (int) ($row['confidence'] ?? 0)));
        if ($confidence < 50 && $candidate_bucket === 'HACER_AHORA') {
            $candidate_bucket = 'HACER_DESPUES';
        }

        $gate = array(
            'state'=>'READY',
            'blocker_type'=>'none',
            'hard'=>false,
            'bucket'=>$candidate_bucket,
            'bucket_if_unblocked'=>$candidate_bucket,
            'unlock_condition'=>'Sin dependencia bloqueante.',
            'missing'=>array(),
        );

        if (($validation['match_type'] ?? '') === 'conflict') {
            $gate['state'] = 'BLOCKED_ENTITY_CONFLICT';
            $gate['blocker_type'] = 'entity';
            $gate['hard'] = true;
            $gate['bucket'] = 'INVESTIGAR';
            $gate['unlock_condition'] = 'Resolver el conflicto de modelo/variante y obtener match exacto o parcial sin contradicción.';
            $gate['missing'] = array('entidad_correcta');
            return $gate;
        }

        if (empty($validation['target']['resolved'])) {
            $gate['state'] = 'BLOCKED_TARGET';
            $gate['blocker_type'] = 'entity';
            $gate['hard'] = true;
            $gate['bucket'] = 'INVESTIGAR';
            $gate['unlock_condition'] = 'Resolver una URL local/canónica existente para la consulta y revalidar la correspondencia.';
            $gate['missing'] = array('target_url');
            return $gate;
        }

        if (($validation['match_type'] ?? '') === 'unproven') {
            $gate['state'] = 'BLOCKED_ENTITY_MATCH';
            $gate['blocker_type'] = 'entity';
            $gate['hard'] = true;
            $gate['bucket'] = 'INVESTIGAR';
            $gate['unlock_condition'] = 'Elevar la correspondencia consulta-entidad a exacta o parcial por encima del umbral configurado.';
            $gate['missing'] = array('match_consulta_entidad');
            return $gate;
        }

        if (!empty($evidence['small_sample']) && !seo_analista_has_independent_critical_issue($row)) {
            $settings = (array) ($evidence['settings'] ?? seo_analista_validation_settings());
            $gate['state'] = 'BLOCKED_EVIDENCE';
            $gate['blocker_type'] = 'evidence';
            $gate['hard'] = false;
            $gate['bucket'] = 'ESPERAR_DATOS';
            $gate['unlock_condition'] = 'Alcanzar al menos ' . absint($settings['reliable_impressions'] ?? 20) . ' impresiones o detectar un defecto crítico independiente.';
            $gate['missing'] = array('volumen_evidencia');
            return $gate;
        }

        $commercial_blocking = array_values(array_unique(array_filter((array) ($commercial['blocking_fields'] ?? array()))));
        if ((string) ($row['objective']['primary'] ?? '') === 'sales' && $commercial_blocking) {
            $gate['state'] = 'BLOCKED_COMMERCIAL';
            $gate['blocker_type'] = 'commercial';
            $gate['hard'] = false;
            $gate['bucket'] = $candidate_bucket === 'HACER_AHORA' ? 'HACER_DESPUES' : $candidate_bucket;
            $gate['bucket_if_unblocked'] = $candidate_bucket;
            $gate['missing'] = $commercial_blocking;
            $gate['unlock_condition'] = 'Validar: ' . implode(', ', $commercial_blocking) . '. Al desaparecer el bloqueo se recupera el bucket ' . $candidate_bucket . ' sin modificar el score.';
            return $gate;
        }

        return $gate;
    }
}

if (!function_exists('seo_analista_investigation_steps')) {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
    function seo_analista_investigation_steps(array $row, array $validation, array $evidence, array $commercial, array $gate, array $auto_resolution = array()) {
        $steps = array();
        $query = (string) ($row['query'] ?? seo_analista_primary_query($row));
        $entity = (array) ($row['entity'] ?? array());
        $entity_label = trim((string) ($entity['title'] ?? ''));
        $match_type = (string) ($validation['match_type'] ?? 'unproven');
        $match_confidence = absint($validation['match_confidence'] ?? 0);

        if (($gate['blocker_type'] ?? '') === 'entity') {
            if (($gate['state'] ?? '') === 'BLOCKED_ENTITY_CONFLICT') {
                $steps[] = array(
                    'code'=>'CHECK_MODEL_ENTITY',
                    'check'=>'Comparar los identificadores de la consulta con modelo/MPN/SKU de la entidad atribuida y buscar el producto exacto en el catálogo.',
                    'current'=>'Consulta: ' . $query . ' · entidad: ' . ($entity_label !== '' ? $entity_label : 'sin entidad') . ' · match ' . $match_type . ' ' . $match_confidence . '/100.',
                    'source'=>'Catálogo WooCommerce + SKU/MPN/atributos del producto + Search Console.',
                    'unlock'=>'Sin conflicto de modelo y match exacto/parcial con la URL correcta.',
                    'owner'=>'SEO/taxonomía',
                );
            } elseif (($gate['state'] ?? '') === 'BLOCKED_TARGET') {
                $steps[] = array(
                    'code'=>'RESOLVE_TARGET_URL',
                    'check'=>'Buscar primero categoría product_cat equivalente; después producto/hub/página local ya existente. No crear URL desde Analista.',
                    'current'=>'Consulta: ' . $query . ' · no hay URL local/canónica validada.',
                    'source'=>'Taxonomía product_cat + índice local de entidades de WordPress.',
                    'unlock'=>'URL local existente resuelta y validada contra la consulta.',
                    'owner'=>'SEO/taxonomía',
                );
            } else {
                $steps[] = array(
                    'code'=>'CONFIRM_ENTITY_MATCH',
                    'check'=>'Revisar título, slug, categoría, intención y términos específicos compartidos entre la consulta y la entidad actual.',
                    'current'=>'Consulta: ' . $query . ' · entidad: ' . ($entity_label !== '' ? $entity_label : 'sin entidad') . ' · match ' . $match_type . ' ' . $match_confidence . '/100.',
                    'source'=>'Search Console + título/slug/taxonomía/entidad local de WordPress.',
                    'unlock'=>'Match exacto o parcial por encima del umbral configurado y sin conflicto de modelo.',
                    'owner'=>'SEO/taxonomía',
                );
            }
            if (!empty($auto_resolution['attempted']) && empty($auto_resolution['resolved'])) {
                $steps[] = array(
                    'code'=>'REVIEW_REJECTED_LOCAL_CANDIDATE',
                    'check'=>'Revisar manualmente si existe una entidad local que el resolver automático rechazó por baja afinidad o riesgo de modelo.',
                    'current'=>(string) ($auto_resolution['reason'] ?? 'No hubo candidato seguro.'),
                    'source'=>'Taxonomía product_cat + índice local de Analista.',
                    'unlock'=>'Candidato local confirmado con evidencia suficiente o descarte explícito de la señal.',
                    'owner'=>'SEO/taxonomía',
                );
            }
        } elseif (($gate['blocker_type'] ?? '') === 'evidence') {
            $settings = (array) ($evidence['settings'] ?? seo_analista_validation_settings());
            $steps[] = array(
                'code'=>'WAIT_FOR_EVIDENCE',
                'check'=>'Mantener la línea base y volver a medir la misma consulta/URL sin cambiar contenido solo por la posición actual.',
                'current'=>absint($evidence['impressions'] ?? 0) . ' impresiones · ' . absint($evidence['clicks'] ?? 0) . ' clics.',
                'source'=>'Google Search Console + línea base persistida del Plan de acción.',
                'unlock'=>'Alcanzar ' . absint($settings['reliable_impressions'] ?? 20) . ' impresiones o disponer de un defecto crítico independiente.',
                'owner'=>'SEO/taxonomía',
            );
        } elseif (($gate['blocker_type'] ?? '') === 'commercial') {
            $labels = array(
                'surtido'=>'Confirmar que la categoría tiene productos vendibles.',
                'stock'=>'Confirmar stock del producto.',
                'stock_categoria'=>'Calcular cuántos productos de la categoría están realmente en stock.',
                'disponibilidad'=>'Confirmar que el producto es comprable.',
                'precio_final'=>'Confirmar precio final de venta.',
                'precio_categoria'=>'Comprobar rango de precios real de la categoría.',
                'proveedor'=>'Confirmar proveedor responsable.',
                'margen_comision'=>'Confirmar margen o comisión disponible.',
                'ga4'=>'Comprobar señales GA4 de la URL/categoría sin mezclarlas con GSC.',
            );
            $sources = array(
                'surtido'=>'WooCommerce: categoría y productos publicados.',
                'stock'=>'WooCommerce: estado de stock del producto.',
                'stock_categoria'=>'WooCommerce: muestra de productos de la categoría.',
                'disponibilidad'=>'WooCommerce: is_purchasable / disponibilidad.',
                'precio_final'=>'WooCommerce: precio actual del producto.',
                'precio_categoria'=>'WooCommerce: rango de precios de la muestra de categoría.',
                'proveedor'=>'Metadato de proveedor del producto (_seo_proveedor).',
                'margen_comision'=>'Precio de venta + coste/proveedor (_seo_precio_proveedor o coste disponible).',
                'ga4'=>'Google Analytics 4 para la URL/categoría.',
            );
            foreach ((array) ($gate['missing'] ?? array()) as $field) {
                $field = sanitize_key((string) $field);
                $steps[] = array(
                    'code'=>'CHECK_COMMERCIAL_' . strtoupper($field),
                    'check'=>(string) ($labels[$field] ?? ('Validar el dato comercial ' . $field . '.')),
                    'current'=>'Dato no verificado en el Plan de acción.',
                    'source'=>(string) ($sources[$field] ?? 'Catálogo/proveedores.'),
                    'unlock'=>'El campo ' . $field . ' queda validado y deja de figurar como dependencia.',
                    'owner'=>'Catálogo/proveedores',
                );
            }
        }

        return $steps;
    }
}

if (!function_exists('seo_analista_action_gate_summary')) {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
    function seo_analista_action_gate_summary(array $plan) {
        $out = array(
            'total'=>0,
            'auto_resolved'=>0,
            'ready'=>0,
            'blocked_entity'=>0,
            'blocked_evidence'=>0,
            'blocked_commercial'=>0,
            'high_value_blocked'=>0,
        );
        foreach ($plan as $row) {
            if (!is_array($row)) continue;
            $out['total']++;
            if (!empty($row['auto_resolution']['resolved'])) $out['auto_resolved']++;
            $blocker = (string) ($row['blocker_type'] ?? 'none');
            if ($blocker === 'entity') $out['blocked_entity']++;
            elseif ($blocker === 'evidence') $out['blocked_evidence']++;
            elseif ($blocker === 'commercial') $out['blocked_commercial']++;
            else $out['ready']++;
            $final_bucket = function_exists('seo_analista_task_bucket')
                ? seo_analista_task_bucket((array) $row)
                : (string) ($row['work_bucket'] ?? '');
            if ((int) ($row['priority_score'] ?? 0) >= 72 && $final_bucket !== 'HACER_AHORA') {
                $out['high_value_blocked']++;
            }
        }
        return $out;
    }
}


if (!function_exists('seo_analista_signal_state')) {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
    function seo_analista_signal_state(array $row, array $evidence = array()) {
        if (!empty($evidence['small_sample'])) {
            return array(
                'code'=>'LOW_SAMPLE',
                'label'=>'Muestra insuficiente',
                'detail'=>'La señal existe, pero el volumen todavía no permite convertirla por sí solo en una instrucción.'
            );
        }

        $growth = (array) ($row['growth_quality'] ?? array());
        $signal = (float) ($growth['signal'] ?? 0);
        $movement = (array) ($row['position_movement'] ?? array());
        $gain = (float) ($movement['gain'] ?? 0);

        if ($signal >= 25.0) {
            return array('code'=>'ACCELERATING','label'=>'Aceleración','detail'=>'La demanda/visibilidad crece frente al periodo anterior.');
        }
        if ($signal <= -25.0) {
            return array('code'=>'DECLINING','label'=>'Descenso','detail'=>'La señal pierde fuerza frente al periodo anterior.');
        }
        if ($gain >= 2.0) {
            return array('code'=>'POSITION_IMPROVING','label'=>'Mejora de posición','detail'=>'La posición media mejora frente al periodo anterior.');
        }
        if ($gain <= -2.0) {
            return array('code'=>'POSITION_DECLINING','label'=>'Pérdida de posición','detail'=>'La posición media empeora frente al periodo anterior.');
        }
        return array('code'=>'STABLE','label'=>'Señal estable','detail'=>'No hay aceleración o deterioro suficiente para cambiar por sí solos la prioridad de trabajo.');
    }
}

if (!function_exists('seo_analista_action_execution_profile')) {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
    function seo_analista_action_execution_profile(array $action, array $row, array $validation, array $gate) {
        $type = sanitize_key((string) ($action['type'] ?? ''));
        $owner = trim((string) ($action['owner'] ?? ''));
        $destination = trim((string) ($action['destination'] ?? $action['target_url'] ?? ($validation['target']['url'] ?? '')));
        $query = trim((string) ($row['query'] ?? seo_analista_primary_query($row)));

        $map = array(
            'REVISAR_TECNICO'=>array('verb'=>'Corregir','object'=>'la incidencia técnica/canónica detectada'),
            'REVISAR_META'=>array('verb'=>'Ajustar','object'=>'el title/meta efectivo de la entidad'),
            'REVISAR_ENLAZADO'=>array('verb'=>'Añadir','object'=>'enlaces internos relevantes hacia la entidad'),
            'MEJORAR_COBERTURA'=>array('verb'=>'Ampliar','object'=>'la cobertura de la entidad para la consulta'),
            'MEJORAR_FICHA'=>array('verb'=>'Corregir','object'=>'los defectos concretos de la ficha'),
            'ACTUALIZAR_CONTENIDO'=>array('verb'=>'Actualizar','object'=>'el contenido existente relacionado'),
            'CREAR_CONTENIDO'=>array('verb'=>'Crear','object'=>'un contenido nuevo'),
            'CREAR_POST'=>array('verb'=>'Proponer','object'=>'un post nuevo y específico'),
            'CREAR_LANDING'=>array('verb'=>'Proponer','object'=>'una landing transversal nueva'),
            'REVISAR_OFERTA_PRECIO'=>array('verb'=>'Validar','object'=>'los datos comerciales bloqueantes'),
            'INVESTIGAR_SURTIDO'=>array('verb'=>'Comprobar','object'=>'el surtido y sus variantes vendibles'),
            'INVESTIGAR_COBERTURA'=>array('verb'=>'Resolver','object'=>'la entidad o URL que debe atender la consulta'),
            'CORREGIR_ASOCIACION'=>array('verb'=>'Resolver','object'=>'el conflicto de asociación/modelo'),
            'ESPERAR_DATOS'=>array('verb'=>'Esperar','object'=>'un volumen de evidencia suficiente'),
            'VIGILAR'=>array('verb'=>'Observar','object'=>'la evolución de la misma señal'),
        );
        $parts = (array) ($map[$type] ?? array('verb'=>'','object'=>''));
        $verb = (string) ($action['verb'] ?? $parts['verb']);
        $object = (string) ($action['object'] ?? $parts['object']);

        $non_executable = array(
            'VIGILAR',
            'ESPERAR_DATOS',
            'INVESTIGAR_COBERTURA',
            'CORREGIR_ASOCIACION',
            'REVISAR_OFERTA_PRECIO',
            'INVESTIGAR_SURTIDO',
            'CREAR_CONTENIDO',
            'CREAR_POST',
            'CREAR_LANDING',
        );
        $executable = !in_array($type, $non_executable, true)
            && !empty($validation['target']['resolved'])
            && (string) ($gate['state'] ?? 'READY') === 'READY'
            && $owner !== ''
            && $destination !== '';

        $detail = trim((string) ($action['detail'] ?? ''));
        if ($executable) {
            $instruction = trim($verb . ' ' . $object)
                . ($destination !== '' ? ' en ' . $destination : '')
                . ($query !== '' ? ' para responder a «' . $query . '»' : '')
                . '.';
            if ($detail !== '') $instruction .= ' Motivo concreto: ' . $detail;
        } else {
            $instruction = $detail !== ''
                ? $detail
                : trim($verb . ' ' . $object . ($destination !== '' ? ' en ' . $destination : '') . '.');
        }

        return array_merge($action, array(
            'verb'=>$verb,
            'object'=>$object,
            'destination'=>$destination,
            'instruction'=>$instruction,
            'executable'=>$executable,
        ));
    }
}

if (!function_exists('seo_analista_measurement_for_primary_action')) {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
    function seo_analista_measurement_for_primary_action(array $action, array $row) {
        $type = sanitize_key((string) ($action['type'] ?? ''));
        $query = (string) ($row['query'] ?? '');
        $url = (string) ($action['destination'] ?? $row['target_url'] ?? '');
        $period = absint($row['period']['days'] ?? 28);
        $period = $period > 0 ? $period : 28;

        if (in_array($type, array('INVESTIGAR_COBERTURA','CORREGIR_ASOCIACION','REVISAR_OFERTA_PRECIO','INVESTIGAR_SURTIDO'), true)) {
            return array(
                'Éxito inmediato: resolver la dependencia indicada y volver a ejecutar el Plan de acción sin duplicar la tarea.',
                'No atribuir mejora SEO a la comprobación; solo cambia la ejecutabilidad de la oportunidad.'
            );
        }
        if ($type === 'ESPERAR_DATOS' || $type === 'VIGILAR') {
            return array(
                'Comparar en ' . $period . ' días impresiones, clics y posición de la misma consulta' . ($url !== '' ? ' y URL' : '') . '.',
                'No hay intervención causal que evaluar: se mide evolución natural de la señal.'
            );
        }

        $metric = in_array($type, array('REVISAR_META'), true)
            ? 'CTR, clics e impresiones'
            : 'impresiones, clics y posición';
        return array(
            'Comparar ' . $metric . ' de la misma consulta' . ($query !== '' ? ' «' . $query . '»' : '') . ($url !== '' ? ' sobre ' . $url : '') . ' contra la línea base a 28/60/90 días.',
            'Interpretar cualquier cambio como asociación temporal; el Plan de acción no promete causalidad automática.'
        );
    }
}

if (!function_exists('seo_analista_reconcile_task_decision')) {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
    function seo_analista_reconcile_task_decision(array $row, array $atomic, array $validation, array $evidence, array $gate) {
        $normalized = array();
        foreach ($atomic as $action) {
            if (!is_array($action)) continue;
            $normalized[] = seo_analista_action_execution_profile($action, $row, $validation, $gate);
        }
        if (!$normalized) {
            $normalized[] = seo_analista_action_execution_profile(
                array(
                    'type'=>'VIGILAR',
                    'detail'=>'No hay una acción atómica suficientemente demostrada; mantener la línea base y observar la misma señal.',
                    'owner'=>'SEO/taxonomía'
                ),
                $row,
                $validation,
                $gate
            );
        }

        $primary = $normalized[0];
        if ((string) ($gate['state'] ?? 'READY') === 'READY') {
            foreach ($normalized as $candidate) {
                if (!empty($candidate['executable'])) {
                    $primary = $candidate;
                    break;
                }
            }
        }

        $bucket = (string) ($gate['bucket'] ?? $row['work_bucket'] ?? 'VIGILAR');
        $primary_type = (string) ($primary['type'] ?? '');
        if ($bucket === 'HACER_AHORA' && empty($primary['executable'])) {
            if (in_array($primary_type,array('CREAR_CONTENIDO','CREAR_POST','CREAR_LANDING'),true)) {
                $bucket = 'HACER_DESPUES';
            } elseif (in_array($primary_type, array('INVESTIGAR_COBERTURA','CORREGIR_ASOCIACION','INVESTIGAR_SURTIDO'), true)
                || (string) ($gate['blocker_type'] ?? '') === 'entity') {
                $bucket = 'INVESTIGAR';
            } elseif ($primary_type === 'ESPERAR_DATOS' || (string) ($gate['blocker_type'] ?? '') === 'evidence') {
                $bucket = 'ESPERAR_DATOS';
            } elseif ((string) ($gate['blocker_type'] ?? '') === 'commercial'
                || in_array($primary_type,array('CREAR_CONTENIDO','CREAR_POST','CREAR_LANDING'),true)) {
                $bucket = 'HACER_DESPUES';
            } else {
                $bucket = 'VIGILAR';
            }
        }

        // R05: una instrucción de observación/espera/investigación conserva
        // su bucket semántico aunque el score previo fuera alto. En cambio una
        // tarea válida de segunda prioridad no se degrada solo por no ser
        // ejecutable inmediatamente.
        if ((string) ($gate['state'] ?? 'READY') === 'READY') {
            if ($primary_type === 'VIGILAR') {
                $bucket = 'VIGILAR';
            } elseif ($primary_type === 'ESPERAR_DATOS') {
                $bucket = 'ESPERAR_DATOS';
            } elseif (in_array($primary_type, array('INVESTIGAR_COBERTURA','CORREGIR_ASOCIACION','INVESTIGAR_SURTIDO'), true)) {
                $bucket = 'INVESTIGAR';
            } elseif (in_array($primary_type,array('CREAR_CONTENIDO','CREAR_POST','CREAR_LANDING'),true)) {
                $bucket = 'HACER_DESPUES';
            }
        }

        $signal = seo_analista_signal_state($row, $evidence);
        $blocker_type = (string) ($gate['blocker_type'] ?? 'none');
        $blocker_labels = array(
            'none'=>'Sin bloqueo',
            'entity'=>'Entidad / destino',
            'evidence'=>'Evidencia insuficiente',
            'commercial'=>'Dependencia comercial',
        );
        $blocker_label = (string) ($blocker_labels[$blocker_type] ?? strtoupper($blocker_type));
        $intervention_type = (string) ($primary['type'] ?? 'VIGILAR');
        $intervention_meta = function_exists('seo_analista_action_meta')
            ? seo_analista_action_meta($intervention_type)
            : array('label'=>$intervention_type);

        return array(
            'version'=>'3.8.3',
            'signal_state'=>$signal,
            'work_bucket'=>$bucket,
            'intervention_type'=>$intervention_type,
            'intervention_label'=>(string) ($intervention_meta['label'] ?? $intervention_type),
            'blocker'=>array(
                'type'=>$blocker_type,
                'label'=>$blocker_label,
                'state'=>(string) ($gate['state'] ?? 'READY'),
                'unlock_condition'=>(string) ($gate['unlock_condition'] ?? ''),
            ),
            'primary_action'=>$primary,
            'actions'=>$normalized,
            'execution_ready'=>!empty($primary['executable']) && $bucket === 'HACER_AHORA',
            'owner'=>(string) ($primary['owner'] ?? ''),
            'target_url'=>(string) ($primary['destination'] ?? $validation['target']['url'] ?? ''),
            'decision_reason'=>!empty($primary['executable'])
                ? 'La acción tiene verbo, objeto, destino, responsable y no existe un gate bloqueante.'
                : 'La tarea no tiene todavía una acción ejecutable compatible con HACER AHORA.',
        );
    }
}

if (!function_exists('seo_analista_apply_history_state')) {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
    function seo_analista_apply_history_state(array $row, array $history) {
        $status = sanitize_key((string) ($history['status'] ?? ''));
        if (!in_array($status, array('queued','in_progress','executed','dismissed'), true)) return $row;

        $row['status'] = $status;
        $row['action_executed'] = sanitize_text_field((string) ($history['action_executed'] ?? ''));
        if (in_array($status, array('queued','in_progress','executed','dismissed'), true)) {
            $row['work_bucket'] = 'SIN_ACCION';
            $message = $status === 'executed'
                ? 'No repetir la acción: ya fue ejecutada. Conservar la trazabilidad y medir contra la línea base antes de proponer otra intervención.'
                : 'No duplicar la acción: la tarea ya está gestionada en el flujo de trabajo.';
            if (!empty($row['primary_action']) && is_array($row['primary_action'])) {
                $row['primary_action']['executable'] = false;
                $row['primary_action']['instruction'] = $message;
            }
            if (!empty($row['execution_gate']) && is_array($row['execution_gate'])) {
                $row['execution_gate']['bucket'] = 'SIN_ACCION';
                $row['execution_gate']['final_bucket'] = 'SIN_ACCION';
                $row['execution_gate']['state'] = $status === 'executed' ? 'TASK_EXECUTED' : 'TASK_MANAGED';
            }
            $row['recommended_changes'] = array($message);
            if (!empty($row['task_decision']) && is_array($row['task_decision'])) {
                $row['task_decision']['work_bucket'] = 'SIN_ACCION';
                $row['task_decision']['execution_ready'] = false;
                $row['task_decision']['decision_reason'] = $status === 'executed'
                    ? 'La tarea ya fue ejecutada; conservar la trazabilidad y medir antes de proponer otra intervención.'
                    : 'La tarea ya está gestionada en el flujo de trabajo; no duplicar la ejecución.';
                $row['task_decision']['primary_action'] = (array) ($row['primary_action'] ?? array());
            }
        }
        return $row;
    }
}


if (!function_exists('seo_analista_task_bucket')) {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
    function seo_analista_task_bucket(array $row) {
        $decision = (array) ($row['task_decision'] ?? array());
        $bucket = (string) ($decision['work_bucket'] ?? $row['work_bucket'] ?? 'SIN_ACCION');
        $allowed = array('HACER_AHORA','HACER_DESPUES','INVESTIGAR','VIGILAR','ESPERAR_DATOS','SIN_ACCION');
        return in_array($bucket, $allowed, true) ? $bucket : 'SIN_ACCION';
    }
}

if (!function_exists('seo_analista_plan_consistency_report')) {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
    function seo_analista_plan_consistency_report(array $plan) {
        $counts = array(
            'HACER_AHORA'=>0,
            'HACER_DESPUES'=>0,
            'INVESTIGAR'=>0,
            'VIGILAR'=>0,
            'ESPERAR_DATOS'=>0,
            'SIN_ACCION'=>0,
        );
        $issues = array();

        foreach ($plan as $index => $row) {
            if (!is_array($row)) continue;
            $bucket = (string) ($row['work_bucket'] ?? 'SIN_ACCION');
            if (!isset($counts[$bucket])) $bucket = 'SIN_ACCION';
            $counts[$bucket]++;

            $decision = (array) ($row['task_decision'] ?? array());
            $decision_bucket = (string) ($decision['work_bucket'] ?? $bucket);
            $primary = (array) ($decision['primary_action'] ?? $row['primary_action'] ?? array());
            $task_id = (string) ($row['task_id'] ?? ('row_' . $index));

            if ($decision_bucket !== $bucket) {
                $issues[] = array(
                    'task_id'=>$task_id,
                    'code'=>'BUCKET_MISMATCH',
                    'detail'=>'work_bucket y task_decision.work_bucket no coinciden.',
                );
            }
            $gate_bucket = (string) ($row['execution_gate']['final_bucket'] ?? $row['execution_gate']['bucket'] ?? $bucket);
            if ($gate_bucket !== $bucket) {
                $issues[] = array(
                    'task_id'=>$task_id,
                    'code'=>'GATE_BUCKET_MISMATCH',
                    'detail'=>'execution_gate y decisión final no coinciden en el bucket.',
                );
            }

            $decision_intervention = (string) ($decision['intervention_type'] ?? '');
            $row_intervention = (string) ($row['action_type'] ?? $row['action'] ?? '');
            if ($decision_intervention !== '' && $row_intervention !== '' && $decision_intervention !== $row_intervention) {
                $issues[] = array(
                    'task_id'=>$task_id,
                    'code'=>'INTERVENTION_MISMATCH',
                    'detail'=>'action_type y task_decision.intervention_type no coinciden.',
                );
            }

            if ($bucket === 'HACER_AHORA') {
                $required = array(
                    'target_resolved'=>!empty($row['target_resolved']),
                    'executable'=>!empty($primary['executable']),
                    'verb'=>trim((string) ($primary['verb'] ?? '')) !== '',
                    'object'=>trim((string) ($primary['object'] ?? '')) !== '',
                    'destination'=>trim((string) ($primary['destination'] ?? '')) !== '',
                    'owner'=>trim((string) ($primary['owner'] ?? $row['recommended_owner'] ?? '')) !== '',
                    'instruction'=>trim((string) ($primary['instruction'] ?? '')) !== '',
                );
                foreach ($required as $field => $ok) {
                    if (!$ok) {
                        $issues[] = array(
                            'task_id'=>$task_id,
                            'code'=>'HACER_AHORA_' . strtoupper($field),
                            'detail'=>'HACER AHORA carece de ' . $field . '.',
                        );
                    }
                }
                if (empty($decision['execution_ready'])) {
                    $issues[] = array(
                        'task_id'=>$task_id,
                        'code'=>'HACER_AHORA_NOT_READY',
                        'detail'=>'HACER AHORA no está autorizado por la decisión única.',
                    );
                }
                if ((string) ($decision['blocker']['type'] ?? 'none') !== 'none') {
                    $issues[] = array(
                        'task_id'=>$task_id,
                        'code'=>'HACER_AHORA_BLOCKED',
                        'detail'=>'HACER AHORA conserva un bloqueo activo.',
                    );
                }
                if (count((array) ($row['recommended_changes'] ?? array())) !== 1) {
                    $issues[] = array(
                        'task_id'=>$task_id,
                        'code'=>'HACER_AHORA_MULTI_ACTION',
                        'detail'=>'HACER AHORA debe exponer una única acción primaria.',
                    );
                }
            }

            if ($bucket === 'INVESTIGAR' && empty($row['investigation_steps'])) {
                $issues[] = array(
                    'task_id'=>$task_id,
                    'code'=>'INVESTIGAR_SIN_COMPROBACION',
                    'detail'=>'INVESTIGAR no incluye una comprobación concreta.',
                );
            }
        }

        return array(
            'ok'=>empty($issues),
            'total'=>count($plan),
            'counts'=>$counts,
            'issues'=>$issues,
        );
    }
}

if (!function_exists('seo_analista_atomic_actions')) {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
    function seo_analista_atomic_actions(array $row, array $validation, array $evidence, array $commercial, array $coverage, array $gate = array(), array $steps = array()) {
        $actions = array();
        $issues = array_map('strval', (array) ($row['issues'] ?? array()));
        $issue_text = function_exists('seo_analista_normalize_text') ? seo_analista_normalize_text(implode(' ', $issues)) : strtolower(implode(' ', $issues));
        $query = (string) ($row['query'] ?? seo_analista_primary_query($row));
        $target_url = (string) ($validation['target']['url'] ?? '');
        $step_detail = '';
        if ($steps) {
            $first = (array) reset($steps);
            $step_detail = trim((string) ($first['check'] ?? ''));
            if (!empty($first['current'])) $step_detail .= ' Actual: ' . trim((string) $first['current']);
            if (!empty($first['unlock'])) $step_detail .= ' Desbloquea cuando: ' . trim((string) $first['unlock']);
        }

        if (($gate['blocker_type'] ?? '') === 'entity') {
            $proposed_action = (string) ($row['action'] ?? '');
            if (in_array($proposed_action,array('CREAR_POST','CREAR_LANDING'),true)
                && (string) ($row['coverage_decision']['status'] ?? '') === 'new') {
                $actions[] = array(
                    'type'=>$proposed_action,
                    'detail'=>'No existe un propietario local suficientemente fuerte para «' . $query . '». La propuesta nace después de revisar categorías, clusters, hubs, landings y posts; requiere validación editorial antes de crear la URL.',
                    'owner'=>'Editora',
                    'checklist'=>$steps,
                    'unlock_condition'=>'Confirmar que ninguna URL existente puede absorber la intención sin canibalización.',
                );
                return $actions;
            }
            $type = ($gate['state'] ?? '') === 'BLOCKED_ENTITY_CONFLICT' ? 'CORREGIR_ASOCIACION' : 'INVESTIGAR_COBERTURA';
            $actions[] = array(
                'type'=>$type,
                'detail'=>$step_detail !== '' ? $step_detail : 'Resolver la correspondencia consulta-entidad antes de modificar contenido.',
                'owner'=>'SEO/taxonomía',
                'checklist'=>$steps,
                'unlock_condition'=>(string) ($gate['unlock_condition'] ?? ''),
            );
            return $actions;
        }

        if (($gate['blocker_type'] ?? '') === 'evidence') {
            $actions[] = array(
                'type'=>'ESPERAR_DATOS',
                'detail'=>$step_detail !== '' ? $step_detail : 'Esperar volumen suficiente antes de ejecutar cambios.',
                'owner'=>'SEO/taxonomía',
                'checklist'=>$steps,
                'unlock_condition'=>(string) ($gate['unlock_condition'] ?? ''),
            );
            return $actions;
        }

        if (($gate['blocker_type'] ?? '') === 'commercial') {
            $missing = array_values(array_filter((array) ($gate['missing'] ?? array())));
            $actions[] = array(
                'type'=>'REVISAR_OFERTA_PRECIO',
                'detail'=>'Oportunidad SEO validada, pero bloqueada por datos comerciales: ' . ($missing ? implode(', ', $missing) : 'validación comercial pendiente') . '. ' . ($step_detail !== '' ? $step_detail : ''),
                'owner'=>'Catálogo/proveedores',
                'checklist'=>$steps,
                'unlock_condition'=>(string) ($gate['unlock_condition'] ?? ''),
            );
        }

        if (strpos($issue_text, '404') !== false || strpos($issue_text, 'no indexable') !== false || strpos($issue_text, 'canonical') !== false) {
            $actions[] = array(
                'type'=>'REVISAR_TECNICO',
                'detail'=>'Corregir la incidencia técnica/canónica detectada en ' . ($target_url !== '' ? $target_url : 'la URL objetivo') . ': ' . implode('; ', $issues) . '.',
                'owner'=>'Técnico'
            );
        }
        if (strpos($issue_text, 'seo title') !== false || strpos($issue_text, 'meta description') !== false || strpos($issue_text, 'ctr bajo') !== false) {
            $actions[] = array(
                'type'=>'REVISAR_META',
                'detail'=>'Ajustar el title/meta efectivo de ' . ($target_url !== '' ? $target_url : 'la URL objetivo') . ' para la consulta «' . $query . '». Incidencia observada: ' . implode('; ', $issues) . '.',
                'owner'=>'Editora'
            );
        }
        if (strpos($issue_text, 'enlazado interno') !== false) {
            $actions[] = array(
                'type'=>'REVISAR_ENLAZADO',
                'detail'=>'Añadir enlaces internos relevantes hacia ' . ($target_url !== '' ? $target_url : 'la entidad') . ' desde categorías, hubs o posts semánticamente relacionados con «' . $query . '».',
                'owner'=>'SEO/taxonomía'
            );
        }
        if (strpos($issue_text, 'cobertura textual') !== false || strpos($issue_text, 'excerpt') !== false || strpos($issue_text, 'vocabulary') !== false) {
            $actions[] = array(
                'type'=>'MEJORAR_COBERTURA',
                'detail'=>'Ampliar la cobertura de ' . ($target_url !== '' ? $target_url : 'la entidad validada') . ' para «' . $query . '» corrigiendo estos huecos: ' . implode('; ', $issues) . '. No incorporar temas ajenos a la intención validada.',
                'owner'=>'Editora'
            );
        }
        if ((strpos($issue_text, 'descripcion corta') !== false || sanitize_key((string) ($row['entity']['type'] ?? '')) === 'product') && $issues) {
            $actions[] = array(
                'type'=>'MEJORAR_FICHA',
                'detail'=>'Corregir en ' . ($target_url !== '' ? $target_url : 'la ficha validada') . ' estos defectos concretos: ' . implode('; ', $issues) . '. Consulta asociada: «' . $query . '».',
                'owner'=>'Editora'
            );
        }

        $legacy_action = (string) ($row['action'] ?? '');
        if (in_array($legacy_action, array('AMPLIAR_PRODUCTOS','MEJORAR_CATEGORIA_SURTIDO','REVISAR_CATEGORIA_SURTIDO','INVESTIGAR_CATALOGO'), true)) {
            $actions[] = array(
                'type'=>'INVESTIGAR_SURTIDO',
                'detail'=>'Comprobar para la entidad validada el número de productos reales, variantes vendibles y proveedor antes de ampliar catálogo. Productos conocidos: ' . (isset($row['catalog']['products']) ? absint($row['catalog']['products']) : 'no medido') . '.',
                'owner'=>'Catálogo/proveedores'
            );
        }

        if (strpos($legacy_action, 'CREAR_') === 0) {
            $existing_coverage = (array) ($coverage['existing_entity'] ?? array());
            if (!empty($existing_coverage['available']) || !empty($coverage['posts'])) {
                $titles = array();
                $coverage_target_url = '';
                foreach ((array) ($coverage['posts'] ?? array()) as $post) {
                    if (!empty($post['title'])) $titles[] = (string) $post['title'];
                    if ($coverage_target_url === '' && !empty($post['url'])) $coverage_target_url = (string) $post['url'];
                }
                if ($coverage_target_url === '') {
                    $coverage_target_url = (string) ($existing_coverage['url'] ?? $target_url);
                }
                $actions[] = array(
                    'type'=>'ACTUALIZAR_CONTENIDO',
                    'target_url'=>$coverage_target_url,
                    'detail'=>'Actualizar ' . ($titles ? '«' . (string) reset($titles) . '»' : 'el contenido existente') . ' para cubrir «' . $query . '» y enlazarlo con la entidad canónica cuando proceda.',
                    'owner'=>'Editora'
                );
            } else {
                $create_type = in_array($legacy_action,array('CREAR_POST','CREAR_LANDING'),true)
                    ? $legacy_action
                    : 'CREAR_CONTENIDO';
                $kind_label = 'CREAR_LANDING' === $create_type ? 'landing transversal' : ('CREAR_POST' === $create_type ? 'post específico' : 'contenido nuevo');
                $actions[] = array(
                    'type'=>$create_type,
                    'detail'=>'No se ha encontrado cobertura equivalente para «' . $query . '». Proponer ' . $kind_label . ' solo tras confirmar que cluster, hubs, categorías, landings y posts existentes no pueden absorber la intención.',
                    'owner'=>'Editora'
                );
            }
        }

        if (!$actions) {
            $actions[] = array(
                'type'=>'VIGILAR',
                'detail'=>'No hay un cambio atómico suficientemente demostrado para «' . $query . '». Mantener la línea base y revisar con más evidencia.',
                'owner'=>'SEO/taxonomía'
            );
        }

        $seen = array();
        $unique = array();
        foreach ($actions as $action) {
            $key = (string) ($action['type'] ?? '');
            if ($key === '' || isset($seen[$key])) continue;
            $seen[$key] = true;
            $unique[] = $action;
        }
        return $unique;
    }
}

if (!function_exists('seo_analista_has_independent_critical_issue')) {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
    function seo_analista_has_independent_critical_issue(array $row) {
        $text = function_exists('seo_analista_normalize_text')
            ? seo_analista_normalize_text(implode(' ', (array) ($row['issues'] ?? array())))
            : strtolower(implode(' ', (array) ($row['issues'] ?? array())));
        foreach (array('producto sin categoria','nodo estructural sin descendencia','url rota','error 404','no indexable') as $needle) {
            if (strpos($text, $needle) !== false) return true;
        }
        return false;
    }
}

if (!function_exists('seo_analista_hydrate_entity_from_target')) {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
    function seo_analista_hydrate_entity_from_target(array $row) {
        $entity = (array) ($row['entity'] ?? array());
        if (!empty($entity['type']) && (!empty($entity['id']) || !empty($entity['url']))) return $row;

        $term_id = absint($row['catalog']['term_id'] ?? 0);
        if ($term_id > 0) {
            $term = get_term($term_id, 'product_cat');
            if ($term instanceof WP_Term && !is_wp_error($term)) {
                $url = get_term_link($term);
                if (!is_wp_error($url)) {
                    $row['entity'] = array(
                        'type'=>'category',
                        'type_label'=>'Categoría',
                        'id'=>$term_id,
                        'title'=>(string) $term->name,
                        'url'=>(string) $url,
                        'edit_url'=>admin_url('term.php?taxonomy=product_cat&tag_ID=' . $term_id . '&post_type=product'),
                    );
                    $row['target'] = array(
                        'title'=>(string) $term->name,
                        'url'=>(string) $url,
                    );
                    return $row;
                }
            }
        }

        $target_url = trim((string) ($row['target']['url'] ?? ''));
        if ($target_url !== '') {
            $post_id = url_to_postid($target_url);
            if ($post_id > 0) {
                $post_type = get_post_type($post_id);
                $type = $post_type === 'product' ? 'product' : ($post_type === 'page' ? 'page' : ($post_type === 'post' ? 'post' : ''));
                $role = '';
                if ($post_type === 'page' && function_exists('seo_analista_page_role_map')) {
                    $role_map = seo_analista_page_role_map();
                    $role = sanitize_key((string) ($role_map[$post_id] ?? ''));
                    if (in_array($role,array('cluster','hub_primary','hub_secondary','landing'),true)) $type = $role;
                    if ('corporate_page' === $role) return $row;
                }
                if ($type !== '') {
                    $row['entity'] = array(
                        'type'=>$type,
                        'type_label'=>function_exists('seo_analista_entity_label') ? seo_analista_entity_label($type) : ucfirst($type),
                        'id'=>$post_id,
                        'title'=>(string) get_the_title($post_id),
                        'url'=>(string) get_permalink($post_id),
                        'edit_url'=>(string) get_edit_post_link($post_id, ''),
                        'role'=>$role,
                    );
                }
            }
        }
        return $row;
    }
}

if (!function_exists('seo_analista_validate_and_finalize_task')) {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
    function seo_analista_validate_and_finalize_task(array $row, $days = 28, $gsc_data_to = '') {
        $row = seo_analista_hydrate_entity_from_target($row);
        $query = seo_analista_primary_query($row);
        $row['query'] = $query;

        $validation = seo_analista_validate_query_entity($query, $row);
        list($resolved_row, $auto_resolution) = seo_analista_try_auto_resolve_target($row, $query, $days);
        if (!empty($auto_resolution['resolved'])) {
            $row = $resolved_row;
            $row['query'] = $query;
            $validation = seo_analista_validate_query_entity($query, $row);
        }

        /*
         * 3.9.0: toda señal pasa por una decisión explícita EXISTENTE vs NUEVO.
         * Si hay propietario local, se mejora esa entidad; si no lo hay,
         * Analista distingue post, landing o investigación de cobertura.
         */
        $entity_after_resolution = (array) ($row['entity'] ?? array());
        if (!empty($entity_after_resolution['type']) && (!empty($entity_after_resolution['id']) || !empty($entity_after_resolution['url']))) {
            $entity_type = sanitize_key((string) ($entity_after_resolution['type'] ?? ''));
            $row['coverage_decision'] = array(
                'status'=>'existing',
                'entity_type'=>$entity_type,
                'entity_id'=>absint($entity_after_resolution['id'] ?? 0),
                'target_url'=>(string) ($entity_after_resolution['url'] ?? ($row['target']['url'] ?? '')),
                'reason'=>'Existe una entidad local validada; reforzarla antes de abrir otra URL.',
            );
            if (function_exists('seo_analista_entity_action')) {
                $row['action'] = seo_analista_entity_action($entity_type, !empty($row['issues']));
            }
        } elseif (function_exists('seo_analista_new_destination_decision')) {
            $destination = seo_analista_new_destination_decision($query);
            $row['coverage_decision'] = array_merge(array('status'=>'new'), (array) $destination);
            $row['action'] = (string) ($destination['action'] ?? 'INVESTIGAR_COBERTURA');
            if (!empty($destination['reason'])) {
                $row['reason'] = trim((string) ($row['reason'] ?? '') . ' ' . (string) $destination['reason']);
            }
        }

        $evidence = seo_analista_evidence_profile($row);
        $commercial = seo_analista_commercial_readiness($row);
        $coverage = seo_analista_editorial_coverage($row);

        $priority = max(0, min(99, (int) ($row['priority'] ?? 0)));
        $confidence_adjustments = array();
        $confidence = max(0, min(100, (int) ($row['confidence'] ?? 0)));

        if (!empty($evidence['priority_penalty'])) {
            $confidence_adjustments['evidence'] = -absint($evidence['priority_penalty']);
            $confidence -= absint($evidence['priority_penalty']);
        }
        if (($validation['match_type'] ?? '') === 'partial') {
            $confidence_adjustments['partial_match'] = -8;
            $confidence -= 8;
        } elseif (($validation['match_type'] ?? '') === 'unproven') {
            $confidence_adjustments['unproven_match'] = -25;
            $confidence -= 25;
        } elseif (($validation['match_type'] ?? '') === 'conflict') {
            $confidence_adjustments['model_conflict_cap'] = 15;
            $confidence = min($confidence, 15);
        }
        $confidence = max(0, min(100, $confidence));
        $confidence_level = $confidence >= 75 ? 'high' : ($confidence >= 50 ? 'medium' : 'low');

        $pre_validation_bucket = (string) ($row['work_bucket'] ?? 'VIGILAR');
        $gate_row = $row;
        $gate_row['confidence'] = $confidence;
        $gate = seo_analista_execution_gate($gate_row, $validation, $evidence, $commercial);
        $steps = seo_analista_investigation_steps($row, $validation, $evidence, $commercial, $gate, $auto_resolution);

        $atomic = seo_analista_atomic_actions($row, $validation, $evidence, $commercial, $coverage, $gate, $steps);
        $decision = seo_analista_reconcile_task_decision($row, $atomic, $validation, $evidence, $gate);
        $primary_action = (array) ($decision['primary_action'] ?? array());
        $bucket = (string) ($decision['work_bucket'] ?? 'VIGILAR');
        $gate['pre_reconciliation_bucket'] = (string) ($gate['bucket'] ?? $pre_validation_bucket);
        $gate['bucket'] = $bucket;
        $gate['final_bucket'] = $bucket;
        $action_type = (string) ($decision['intervention_type'] ?? $primary_action['type'] ?? 'VIGILAR');
        $owner = (string) ($decision['owner'] ?? $primary_action['owner'] ?? 'SEO/taxonomía');

        $dependencies = array();
        foreach ($steps as $step) {
            $unlock = trim((string) ($step['unlock'] ?? ''));
            if ($unlock !== '') $dependencies[] = $unlock;
        }
        if (!empty($coverage['available']) && strpos((string) ($row['action'] ?? ''), 'CREAR_') === 0) {
            $dependencies[] = 'Revisar la cobertura editorial existente antes de abrir una nueva URL.';
        }

        $period = array(
            'days'=>absint($days),
            'label'=>absint($days) . ' días',
        );
        if (!empty($row['period']) && is_array($row['period'])) $period = array_merge($period, $row['period']);

        $baseline = array(
            'captured_at'=>current_time('mysql'),
            'impressions'=>(float) ($row['metrics']['impressions'] ?? 0),
            'clicks'=>(float) ($row['metrics']['clicks'] ?? 0),
            'ctr'=>(float) ($row['metrics']['ctr'] ?? 0),
            'position'=>(float) ($row['metrics']['position'] ?? 0),
            'previous_impressions'=>(float) ($evidence['previous_impressions'] ?? 0),
            'absolute_delta'=>(float) ($evidence['absolute_delta'] ?? 0),
        );

        /*
         * 3.8.3: la identidad de seguimiento se basa en consulta + tema, no
         * en el bucket, acción o URL final. Así resolver un bloqueo no crea
         * otra tarea distinta y se conserva la trazabilidad.
         */
        $trace_seed = seo_analista_normalize_text($query) . '|' . seo_analista_normalize_text((string) ($row['topic'] ?? ''));
        if ($trace_seed === '|') {
            $trace_seed = sanitize_key((string) ($row['entity']['type'] ?? 'unknown')) . '|' . absint($row['entity']['id'] ?? 0);
        }
        $task_id = 'ana383_' . substr(hash('sha256', $trace_seed), 0, 20);

        // Compatibilidad con el ID usado por 3.8.1/3.8.2 para recuperar estado.
        $entity = (array) ($row['entity'] ?? array());
        $legacy_seed = implode('|', array(
            sanitize_key((string) ($entity['type'] ?? 'unknown')),
            absint($entity['id'] ?? 0),
            (string) ($validation['target']['url'] ?? ''),
            $query,
            $action_type,
        ));
        $legacy_task_id = 'ana381_' . substr(hash('sha256', $legacy_seed), 0, 20);

        $row['priority'] = $priority;
        $row['priority_score'] = $priority;
        $row['work_bucket'] = $bucket;
        $row['pre_validation_bucket'] = $pre_validation_bucket;
        $row['bucket_if_unblocked'] = (string) ($gate['bucket_if_unblocked'] ?? $bucket);
        $row['confidence'] = $confidence;
        $row['confidence_level'] = $confidence_level;
        $row['query'] = $query;
        $row['match_type'] = (string) ($validation['match_type'] ?? 'unproven');
        $row['match_confidence'] = absint($validation['match_confidence'] ?? 0);
        $row['intent_fit'] = (string) ($validation['intent_fit'] ?? 'unknown');
        $row['query_intent'] = (string) ($validation['query_intent'] ?? (function_exists('seo_analista_intent') ? seo_analista_intent($query) : 'unknown'));
        $row['target_url'] = (string) ($validation['target']['url'] ?? '');
        $row['target_resolved'] = !empty($validation['target']['resolved']);
        $row['match_reason'] = (string) ($validation['reason'] ?? '');
        $row['auto_resolution'] = $auto_resolution;
        $row['evidence_volume'] = $evidence;
        $row['commercial_readiness'] = $commercial;
        $row['editorial_coverage'] = $coverage;
        $row['execution_gate'] = $gate;
        $row['blocker_type'] = (string) ($gate['blocker_type'] ?? 'none');
        $row['unlock_condition'] = (string) ($gate['unlock_condition'] ?? '');
        $row['investigation_steps'] = $steps;
        $row['task_decision'] = $decision;
        $row['signal_state'] = (array) ($decision['signal_state'] ?? array());
        $row['primary_action'] = $primary_action;
        $row['action_type'] = $action_type;
        $row['action_details'] = (array) ($decision['actions'] ?? $atomic);
        $row['recommended_owner'] = $owner;
        $row['owner'] = $owner;
        $row['dependencies'] = array_values(array_unique($dependencies));
        $row['task_id'] = $task_id;
        $row['trace_id'] = $task_id;
        $row['legacy_task_id'] = $legacy_task_id;
        $row['period'] = $period;
        $row['baseline'] = $baseline;
        $row['verification_date'] = gmdate('Y-m-d', strtotime('+28 days'));
        $row['review_dates'] = array(
            '28d'=>gmdate('Y-m-d', strtotime('+28 days')),
            '60d'=>gmdate('Y-m-d', strtotime('+60 days')),
            '90d'=>gmdate('Y-m-d', strtotime('+90 days')),
        );

        $strategy_breakdown = (array) ($row['priority_breakdown'] ?? array());
        $strategy_breakdown['pre_validation_score'] = $priority;
        $strategy_breakdown['validation_adjustments'] = $confidence_adjustments;
        $strategy_breakdown['validation_adjustments_applied_to_priority'] = false;
        $strategy_breakdown['final_score'] = $priority;
        $strategy_breakdown['confidence_after_validation'] = $confidence;
        $strategy_breakdown['rule'] = 'El score de oportunidad permanece estable; la fuente única task_decision decide bucket, acción ejecutable, intervención y bloqueo.';
        $row['priority_breakdown'] = $strategy_breakdown;

        $row['legacy_action'] = (string) ($row['action'] ?? '');
        $row['action'] = $action_type;
        if (function_exists('seo_analista_action_meta')) {
            $meta = seo_analista_action_meta($action_type);
            $row['action_label'] = (string) ($decision['intervention_label'] ?? $meta['label'] ?? $action_type);
            $row['channel'] = (string) ($meta['channel'] ?? ($row['channel'] ?? 'seo'));
        } else {
            $row['action_label'] = (string) ($decision['intervention_label'] ?? $action_type);
        }

        $instruction = trim((string) ($primary_action['instruction'] ?? $primary_action['detail'] ?? ''));
        $row['recommended_changes'] = $instruction !== '' ? array($instruction) : array();
        $row['measurement'] = seo_analista_measurement_for_primary_action($primary_action, $row);

        $row['status'] = 'proposed';
        $row['action_executed'] = '';
        $existing_tracking = array();
        if (function_exists('seo_analista_task_history')) {
            $existing_tracking = seo_analista_task_history($task_id);
            if (!$existing_tracking && $legacy_task_id !== $task_id) {
                $existing_tracking = seo_analista_task_history($legacy_task_id);
                if ($existing_tracking) $row['migrated_from_task_id'] = $legacy_task_id;
            }
        }
        if ($existing_tracking) {
            $row = seo_analista_apply_history_state($row, $existing_tracking);
        }

        // Second check: a local editorial update newer than GSC evidence is
        // monitored, not proposed again. No second content scan is performed.
        if (function_exists('seo_analista_editorial_date_review')) {
            $row = seo_analista_editorial_date_review($row, $gsc_data_to);
        }

        // La decisión final es la única fuente de verdad para contador, tarjeta y JSON.
        if (!empty($row['task_decision']) && is_array($row['task_decision'])) {
            $row['task_decision']['work_bucket'] = (string) ($row['work_bucket'] ?? 'VIGILAR');
            $row['task_decision']['primary_action'] = (array) ($row['primary_action'] ?? array());
            $row['task_decision']['execution_ready'] = !empty($row['primary_action']['executable'])
                && (string) ($row['work_bucket'] ?? '') === 'HACER_AHORA'
                && (string) ($row['status'] ?? 'proposed') === 'proposed';
        }

        return $row;
    }
}

if (!function_exists('seo_analista_task_contract')) {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
    function seo_analista_task_contract(array $row) {
        $entity = (array) ($row['entity'] ?? array());
        return array(
            'task_id'=>(string) ($row['task_id'] ?? ''),
            'trace_id'=>(string) ($row['trace_id'] ?? $row['task_id'] ?? ''),
            'legacy_task_id'=>(string) ($row['legacy_task_id'] ?? ''),
            'entity_type'=>(string) ($entity['type'] ?? ''),
            'entity_id'=>absint($entity['id'] ?? 0),
            'target_url'=>(string) ($row['target_url'] ?? ''),
            'query'=>(string) ($row['query'] ?? ''),
            'match_type'=>(string) ($row['match_type'] ?? 'unproven'),
            'match_confidence'=>absint($row['match_confidence'] ?? 0),
            'intent_fit'=>(string) ($row['intent_fit'] ?? 'unknown'),
            'query_intent'=>(string) ($row['query_intent'] ?? 'unknown'),
            'evidence_count'=>absint($row['evidence_volume']['evidence_count'] ?? 0),
            'impressions'=>(float) ($row['metrics']['impressions'] ?? 0),
            'clicks'=>(float) ($row['metrics']['clicks'] ?? 0),
            'ctr'=>(float) ($row['metrics']['ctr'] ?? 0),
            'position'=>(float) ($row['metrics']['position'] ?? 0),
            'period'=>(array) ($row['period'] ?? array()),
            'priority_score'=>absint($row['priority_score'] ?? $row['priority'] ?? 0),
            'priority_breakdown'=>(array) ($row['priority_breakdown'] ?? array()),
            'pre_validation_bucket'=>(string) ($row['pre_validation_bucket'] ?? ''),
            'final_bucket'=>function_exists('seo_analista_task_bucket') ? seo_analista_task_bucket($row) : (string) ($row['work_bucket'] ?? ''),
            'bucket_if_unblocked'=>(string) ($row['bucket_if_unblocked'] ?? ''),
            'task_decision'=>(array) ($row['task_decision'] ?? array()),
            'signal_state'=>(array) ($row['signal_state'] ?? array()),
            'primary_action'=>(array) ($row['primary_action'] ?? array()),
            'execution_gate'=>(array) ($row['execution_gate'] ?? array()),
            'blocker_type'=>(string) ($row['blocker_type'] ?? 'none'),
            'unlock_condition'=>(string) ($row['unlock_condition'] ?? ''),
            'investigation_steps'=>(array) ($row['investigation_steps'] ?? array()),
            'auto_resolution'=>(array) ($row['auto_resolution'] ?? array()),
            'coverage_decision'=>(array) ($row['coverage_decision'] ?? array()),
            'date_review'=>(array) ($row['date_review'] ?? array()),
            'confidence_level'=>(string) ($row['confidence_level'] ?? 'low'),
            'confidence'=>absint($row['confidence'] ?? 0),
            'objective'=>(array) ($row['objective'] ?? array()),
            'commercial_readiness'=>(array) ($row['commercial_readiness'] ?? array()),
            'action_type'=>(string) ($row['action_type'] ?? $row['action'] ?? ''),
            'action_details'=>(array) ($row['action_details'] ?? array()),
            'dependencies'=>(array) ($row['dependencies'] ?? array()),
            'owner'=>(string) ($row['recommended_owner'] ?? $row['owner'] ?? ''),
            'status'=>(string) ($row['status'] ?? 'proposed'),
            'action_executed'=>(string) ($row['action_executed'] ?? ''),
            'baseline'=>(array) ($row['baseline'] ?? array()),
            'measurement'=>(array) ($row['measurement'] ?? array()),
            'verification_date'=>(string) ($row['verification_date'] ?? ''),
            'review_dates'=>(array) ($row['review_dates'] ?? array()),
        );
    }
}

if (!function_exists('seo_analista_persist_task_baselines')) {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
    function seo_analista_persist_task_baselines(array $plan) {
        $history = get_option(SEO_ANALISTA_TASK_HISTORY_OPTION, array());
        $history = is_array($history) ? $history : array();
        $now = current_time('mysql');

        foreach ($plan as $row) {
            $task = seo_analista_task_contract((array) $row);
            $id = (string) ($task['task_id'] ?? '');
            if ($id === '') continue;
            $existing = (array) ($history[$id] ?? array());
            $legacy_id = sanitize_key((string) ($task['legacy_task_id'] ?? ''));
            if (!$existing && $legacy_id !== '' && $legacy_id !== $id && !empty($history[$legacy_id]) && is_array($history[$legacy_id])) {
                $existing = (array) $history[$legacy_id];
                $existing['migrated_from_task_id'] = $legacy_id;
                unset($history[$legacy_id]);
            }
            if (empty($existing['first_seen'])) {
                $existing['first_seen'] = $now;
                $existing['baseline'] = (array) ($task['baseline'] ?? array());
                $existing['status'] = 'proposed';
                $existing['action_executed'] = '';
            }

            /*
             * Analista no administra tareas: Auditor u otra capa de workflow
             * puede aportar estado/accion ejecutada mediante este filtro.
             */
            $tracking = apply_filters(
                // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Existing public hook name is retained to preserve connected callbacks.
                'seo_analista_task_tracking_state',
                array(
                    'status'=>(string) ($existing['status'] ?? 'proposed'),
                    'action_executed'=>(string) ($existing['action_executed'] ?? ''),
                ),
                $task,
                $existing
            );
            if (is_array($tracking)) {
                $candidate_status = sanitize_key((string) ($tracking['status'] ?? 'proposed'));
                if (in_array($candidate_status, array('proposed','queued','in_progress','executed','dismissed'), true)) {
                    $existing['status'] = $candidate_status;
                }
                $existing['action_executed'] = sanitize_text_field((string) ($tracking['action_executed'] ?? ''));
            }

            $existing['last_seen'] = $now;
            $existing['last_task'] = $task;
            $existing['review_dates'] = (array) ($task['review_dates'] ?? array());
            $history[$id] = $existing;
        }

        if (count($history) > 500) {
            uasort($history, static function($a,$b){
                return strcmp((string) ($b['last_seen'] ?? ''), (string) ($a['last_seen'] ?? ''));
            });
            $history = array_slice($history, 0, 500, true);
        }
        update_option(SEO_ANALISTA_TASK_HISTORY_OPTION, $history, false);
        return $history;
    }
}

if (!function_exists('seo_analista_task_history')) {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
    function seo_analista_task_history($task_id = '') {
        $history = get_option(SEO_ANALISTA_TASK_HISTORY_OPTION, array());
        $history = is_array($history) ? $history : array();
        if ($task_id === '') return $history;
        return (array) ($history[sanitize_key($task_id)] ?? array());
    }
}

if (!function_exists('seo_analista_action_scheduler_health')) {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
    function seo_analista_action_scheduler_health() {
        $out = array('available'=>false,'overdue'=>0,'state'=>'unknown','detail'=>'Action Scheduler no disponible.');
        if (!class_exists('ActionScheduler') || !class_exists('ActionScheduler_Store')) return $out;
        try {
            $store = ActionScheduler::store();
            if (!$store || !method_exists($store, 'query_actions')) return $out;
            $ids = $store->query_actions(array(
                'status'=>ActionScheduler_Store::STATUS_PENDING,
                'per_page'=>50,
                'orderby'=>'date',
                'order'=>'ASC',
            ));
            $now = time();
            $overdue = 0;
            foreach ((array) $ids as $id) {
                $action = $store->fetch_action($id);
                if (!$action || !method_exists($action, 'get_schedule')) continue;
                $schedule = $action->get_schedule();
                if (!$schedule || !method_exists($schedule, 'get_date')) continue;
                $date = $schedule->get_date();
                if ($date && method_exists($date, 'getTimestamp') && $date->getTimestamp() < $now) $overdue++;
            }
            $out['available'] = true;
            $out['overdue'] = $overdue;
            $out['state'] = $overdue > 0 ? 'warning' : 'ok';
            $out['detail'] = $overdue > 0
                ? $overdue . ' acciones pendientes aparecen vencidas; revisar si afectan a la frescura de fuentes.'
                : 'No se observan acciones pendientes vencidas en la muestra consultada.';
        } catch (Throwable $e) {
            $out['detail'] = 'No se pudo comprobar Action Scheduler: ' . $e->getMessage();
        }
        return $out;
    }
}
