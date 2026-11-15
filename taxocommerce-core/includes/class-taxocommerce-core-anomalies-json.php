<?php
/**
 * Exportación JSON de anomalías editoriales para Core > Anomalías.
 * Diagnóstico de solo lectura: las correcciones se realizan mediante importadores especializados.
 */

defined('ABSPATH') || exit;

add_action('admin_post_taxocommerce_core_anomalies_content_json_export', 'taxocommerce_core_anomalies_content_json_export_handler');

if (!function_exists('taxocommerce_core_anomalies_json_excluded_post_slugs')) {
    function taxocommerce_core_anomalies_json_excluded_post_slugs() {
        return array_values(array_unique(array_filter(array_map(
            'sanitize_title',
            (array) apply_filters(
                'taxocommerce_core_non_editorial_post_slugs',
                array(
                    'carrito',
                    'finalizar-compra',
                    'mi-cuenta',
                    'terminos-y-condiciones',
                    'privacidad-de-datos',
                    'devoluciones-y-reembolsos',
                    'contacto',
                    'blog',
                    'tienda',
                    'dependiente',
                    'inicio',
                    'nosotros',
                    'nuestro-servicio',
                    'soluciones',
                    'productos-genericos-de-ferreteria',
                )
            )
        ))));
    }
}

if (!function_exists('taxocommerce_core_anomalies_json_collect_targets')) {
    function taxocommerce_core_anomalies_json_collect_targets() {
        global $wpdb;

        $targets = array();
        $add = static function ($object_type, $object_id, $code) use (&$targets) {
            $object_type = sanitize_key((string) $object_type);
            $object_id = absint($object_id);
            $code = sanitize_key((string) $code);
            if (!$object_id || !in_array($object_type, array('post', 'page'), true) || $code === '') {
                return;
            }
            $key = $object_type . ':' . $object_id;
            if (!isset($targets[$key])) {
                $targets[$key] = array(
                    'object_type' => $object_type,
                    'object_id'   => $object_id,
                    'anomalies'   => array(),
                );
            }
            if (!in_array($code, $targets[$key]['anomalies'], true)) {
                $targets[$key]['anomalies'][] = $code;
            }
        };

        // Landings publicadas/programadas sin relacion comercial.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Diagnostic JSON export must report current canonical data; avoid stale cache.
        $landing_rows = $wpdb->get_results(
            "SELECT DISTINCT n.object_id
             FROM {$wpdb->prefix}seo_nodes n
             INNER JOIN {$wpdb->posts} p ON p.ID=n.object_id AND p.post_type='page'
             WHERE n.object_type='page'
               AND n.seo_role='landing'
               AND n.status=1
               AND p.post_status IN ('publish','future')
               AND NOT EXISTS (
                    SELECT 1
                    FROM {$wpdb->prefix}seo_relations r
                    WHERE r.source_type='landing'
                      AND r.source_id=n.object_id
                      AND r.target_type='product_cat'
                      AND r.relation_type='landing_to_category'
               )"
        );
        foreach ((array) $landing_rows as $row) {
            $add('page', $row->object_id ?? 0, 'landing_without_product_category');
        }

        // Guias/Comparativas sin relacion comercial.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Diagnostic JSON export must report current canonical data; avoid stale cache.
        $post_relation_rows = $wpdb->get_results(
            "SELECT DISTINCT p.ID, p.post_name
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->term_relationships} tr ON tr.object_id=p.ID
             INNER JOIN {$wpdb->term_taxonomy} tt
                ON tt.term_taxonomy_id=tr.term_taxonomy_id
               AND tt.taxonomy='category'
             INNER JOIN {$wpdb->terms} t ON t.term_id=tt.term_id
             WHERE p.post_type='post'
               AND p.post_status IN ('publish','future')
               AND t.slug IN ('guias-y-comparativas','guias','comparativas')
               AND NOT EXISTS (
                    SELECT 1
                    FROM {$wpdb->prefix}seo_relations r
                    WHERE r.source_type='post'
                      AND r.source_id=p.ID
                      AND r.target_type='product_cat'
                      AND r.relation_type='post_to_category'
               )"
        );

        $excluded = taxocommerce_core_anomalies_json_excluded_post_slugs();
        foreach ((array) $post_relation_rows as $row) {
            $slug = sanitize_title((string) ($row->post_name ?? ''));
            if (!in_array($slug, $excluded, true)) {
                $add('post', $row->ID ?? 0, 'post_without_product_category');
            }
        }

        // Entradas editoriales sin Vocabulary canonico activo.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Diagnostic JSON export must report current canonical data; avoid stale cache.
        $post_vocab_rows = $wpdb->get_results(
            "SELECT p.ID, p.post_name
             FROM {$wpdb->posts} p
             WHERE p.post_type='post'
               AND p.post_status IN ('publish','future')
               AND NOT EXISTS (
                    SELECT 1
                    FROM {$wpdb->prefix}seo_object_vocabulary ov
                    INNER JOIN {$wpdb->prefix}seo_vocabulary v
                       ON v.id=ov.vocabulary_id
                      AND v.active=1
                    WHERE ov.object_type='post'
                      AND ov.object_id=p.ID
                      AND ov.status=1
                      AND v.semantic_group IN ('rol','tipo','aplicacion','plataforma','subtipo')
               )"
        );
        foreach ((array) $post_vocab_rows as $row) {
            $slug = sanitize_title((string) ($row->post_name ?? ''));
            if (!in_array($slug, $excluded, true)) {
                $add('post', $row->ID ?? 0, 'post_without_vocabulary');
            }
        }

        uasort($targets, static function ($a, $b) {
            $type_cmp = strcmp((string) $a['object_type'], (string) $b['object_type']);
            return $type_cmp !== 0 ? $type_cmp : ((int) $a['object_id'] <=> (int) $b['object_id']);
        });

        return array_values($targets);
    }
}

if (!function_exists('taxocommerce_core_anomalies_json_term_slugs')) {
    function taxocommerce_core_anomalies_json_term_slugs($object_id, $taxonomy) {
        $terms = wp_get_object_terms(absint($object_id), sanitize_key($taxonomy), array('fields' => 'slugs'));
        return is_wp_error($terms) ? array() : array_values(array_unique(array_map('sanitize_title', (array) $terms)));
    }
}

if (!function_exists('taxocommerce_core_anomalies_json_product_cat_slugs')) {
    function taxocommerce_core_anomalies_json_product_cat_slugs($object_type, $object_id) {
        global $wpdb;

        $object_type = sanitize_key((string) $object_type);
        $object_id = absint($object_id);
        if (!$object_id || !in_array($object_type, array('post', 'page'), true)) {
            return array();
        }

        $source_type = $object_type === 'page' ? 'landing' : 'post';
        $relation_type = $object_type === 'page' ? 'landing_to_category' : 'post_to_category';

        return array_values(array_unique(array_filter(array_map(
            'sanitize_title',
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Diagnostic JSON export must report current canonical data; avoid stale cache.
            (array) $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT t.slug
                     FROM {$wpdb->prefix}seo_relations r
                     INNER JOIN {$wpdb->terms} t ON t.term_id=r.target_id
                     INNER JOIN {$wpdb->term_taxonomy} tt
                        ON tt.term_id=r.target_id
                       AND tt.taxonomy='product_cat'
                     WHERE r.source_type=%s
                       AND r.source_id=%d
                       AND r.target_type='product_cat'
                       AND r.relation_type=%s
                     ORDER BY t.slug ASC",
                    $source_type,
                    $object_id,
                    $relation_type
                )
            )
        ))));
    }
}

if (!function_exists('taxocommerce_core_anomalies_json_vocabulary')) {
    function taxocommerce_core_anomalies_json_vocabulary($object_type, $object_id) {
        global $wpdb;

        $out = array(
            'rol'        => array(),
            'tipo'       => array(),
            'aplicacion' => array(),
            'plataforma' => array(),
            'subtipo'    => array(),
        );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Diagnostic JSON export must report current canonical data; avoid stale cache.
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT v.semantic_group, v.slug
                 FROM {$wpdb->prefix}seo_object_vocabulary ov
                 INNER JOIN {$wpdb->prefix}seo_vocabulary v
                    ON v.id=ov.vocabulary_id
                   AND v.active=1
                 WHERE ov.object_type=%s
                   AND ov.object_id=%d
                   AND ov.status=1
                   AND v.semantic_group IN ('rol','tipo','aplicacion','plataforma','subtipo')
                 ORDER BY FIELD(v.semantic_group,'rol','tipo','aplicacion','plataforma','subtipo'), v.slug ASC",
                sanitize_key((string) $object_type),
                absint($object_id)
            )
        );

        foreach ((array) $rows as $row) {
            $group = sanitize_key((string) ($row->semantic_group ?? ''));
            $slug = sanitize_title((string) ($row->slug ?? ''));
            if ($slug !== '' && isset($out[$group]) && !in_array($slug, $out[$group], true)) {
                $out[$group][] = $slug;
            }
        }

        return $out;
    }
}

if (!function_exists('taxocommerce_core_anomalies_json_page_role')) {
    function taxocommerce_core_anomalies_json_page_role($page_id) {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Diagnostic JSON export must report current canonical data; avoid stale cache.
        return sanitize_key((string) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT seo_role
                 FROM {$wpdb->prefix}seo_nodes
                 WHERE object_type='page' AND object_id=%d AND status=1
                 ORDER BY updated_at DESC, id DESC
                 LIMIT 1",
                absint($page_id)
            )
        ));
    }
}

if (!function_exists('taxocommerce_core_anomalies_json_build_document')) {
    function taxocommerce_core_anomalies_json_build_document() {
        $items = array();

        foreach (taxocommerce_core_anomalies_json_collect_targets() as $target) {
            $object_type = (string) $target['object_type'];
            $object_id = absint($target['object_id']);
            $post = get_post($object_id);
            if (!$post || $post->post_type !== $object_type) {
                continue;
            }

            $item = array(
                'object_type'       => $object_type,
                'object_id'         => $object_id,
                'anomalies'         => array_values((array) $target['anomalies']),
                'title'             => (string) $post->post_title,
                'slug'              => (string) $post->post_name,
                'status'            => (string) $post->post_status,
                'excerpt'           => (string) $post->post_excerpt,
                'content'           => (string) $post->post_content,
                'url'               => (string) get_permalink($object_id),
                'product_cat_slugs' => taxocommerce_core_anomalies_json_product_cat_slugs($object_type, $object_id),
                'vocabulary'        => taxocommerce_core_anomalies_json_vocabulary($object_type, $object_id),
            );

            if ($object_type === 'post') {
                $item['wordpress_category_slugs'] = taxocommerce_core_anomalies_json_term_slugs($object_id, 'category');
                $item['wordpress_tag_slugs'] = taxocommerce_core_anomalies_json_term_slugs($object_id, 'post_tag');
            } else {
                $item['seo_role'] = taxocommerce_core_anomalies_json_page_role($object_id);
            }

            $items[] = $item;
        }

        return array(
            'schema' => array(
                'name'    => 'seo-anomalias-contenidos',
                'version' => 1,
            ),
            'generated_at' => gmdate('c'),
            'site' => array(
                'home_url'       => home_url('/'),
                'plugin_version' => defined('TAXOCOMMERCE_CORE_VERSION') ? TAXOCOMMERCE_CORE_VERSION : '',
            ),
            'rules' => array(
                'existing_objects_only' => true,
                'creates_content'       => false,
                'deletes_content'       => false,
                'portable_terms'        => 'slug',
                'vocabulary_policy'     => 'protected_non_manual_assignments_are_preserved',
            ),
            'items' => $items,
        );
    }
}

if (!function_exists('taxocommerce_core_anomalies_content_json_export_handler')) {
    function taxocommerce_core_anomalies_content_json_export_handler() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('No tienes permisos para exportar contenidos de anomalías.', 'taxocommerce-core'));
        }
        check_admin_referer('taxocommerce_core_anomalies_content_json_export');

        foreach (array('seo_nodes', 'seo_relations', 'seo_vocabulary', 'seo_object_vocabulary') as $suffix) {
            if (!taxocommerce_core_anomalies_table_exists($suffix)) {
                wp_die(esc_html__('Falta una tabla SEO del esquema original. No se puede exportar.', 'taxocommerce-core'));
            }
        }
        $payload = taxocommerce_core_anomalies_json_build_document();

        nocache_headers();
        header('X-Content-Type-Options: nosniff');
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="seo-anomalias-contenidos-' . gmdate('Ymd-His') . '.json"');
        echo wp_json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

if (!function_exists('taxocommerce_core_anomalies_content_json_render_panel')) {
    function taxocommerce_core_anomalies_content_json_render_panel() {
        $export_url = wp_nonce_url(
            admin_url('admin-post.php?action=taxocommerce_core_anomalies_content_json_export'),
            'taxocommerce_core_anomalies_content_json_export'
        );
        $count = count(taxocommerce_core_anomalies_json_collect_targets());

        echo '<div style="background:#f6f7f7;border:1px solid #dcdcde;border-left:4px solid #2271b1;padding:14px 16px;margin:16px 0 22px;">';
        echo '<div style="display:flex;justify-content:space-between;gap:18px;align-items:flex-start;flex-wrap:wrap;">';
        echo '<div style="max-width:780px;">';
        echo '<h3 style="margin:0 0 6px;">' . esc_html__( 'Exportación JSON de anomalías editoriales', 'taxocommerce-core' ) . '</h3>';
        echo '<p style="margin:0;color:#50575e;">Descarga para análisis externo los posts y landings con anomalías editoriales, incluyendo contenido, categorías WordPress, relaciones con <code>product_cat</code> y Vocabulary. Este JSON no incluye todos los tipos de anomalía estructural del informe y no se importa desde esta pantalla.</p>';
        echo '<p style="margin:7px 0 0;"><strong>' . esc_html__( 'Objetos incluidos ahora:', 'taxocommerce-core' ) . '</strong> ' . esc_html(number_format_i18n($count)) . '</p>';
        echo '</div>';
        echo '<div style="display:flex;gap:10px;align-items:flex-start;flex-wrap:wrap;">';
        echo '<a class="button button-secondary" href="' . esc_url($export_url) . '">' . esc_html__( 'Descargar JSON', 'taxocommerce-core' ) . '</a>';

        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
}
