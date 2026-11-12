<?php
/**
 * Copia de lectura de Informes > Anomalías del TaxoCommerce SEO anterior.
 * Reutiliza las tablas existentes, sin crear tablas adicionales ni cambiar el original.
 */
defined('ABSPATH') || exit;

add_action('admin_post_taxocommerce_core_delete_orphan_category_faqs', 'taxocommerce_core_anomalies_delete_orphan_category_faqs');
add_action('admin_post_taxocommerce_core_delete_orphan_product_faqs', 'taxocommerce_core_anomalies_delete_orphan_product_faqs');
add_action('admin_post_taxocommerce_core_delete_empty_product_categories', 'taxocommerce_core_anomalies_delete_empty_categories');
add_action('admin_post_taxocommerce_core_recount_product_categories', 'taxocommerce_core_anomalies_recount_categories');

/** Consultar si una tabla original existe antes de generar el informe. */
function taxocommerce_core_anomalies_table_exists($suffix) {
    global $wpdb;
    $name = $wpdb->prefix . sanitize_key($suffix);
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Anomalies audit and cleanup preflight must read current canonical tables.
    return $name === $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($name)));
}

/** Enlazar al editor del propio Core. */
function taxocommerce_core_anomalies_edit_url($type, $id) {
    return add_query_arg(array('page' => 'taxocommerce-core-content', 'tc_type' => $type,
        'tc_action' => 'edit', 'item_id' => absint($id)), admin_url('admin.php'));
}

/** Encolar el JS de selección de categorías vacío sin depender del plugin antiguo. */
function taxocommerce_core_anomalies_inline_script($script) {
    $handle = 'taxocommerce-core-anomalies-inline';
    if (!wp_script_is($handle, 'registered')) {
        wp_register_script($handle, false, array(), TAXOCOMMERCE_CORE_VERSION, true);
    }
    wp_enqueue_script($handle);
    wp_add_inline_script($handle, (string) $script);
}

/** Informe integrado en la pestaña Anomalías de Core > Contenidos. */
function taxocommerce_core_anomalies_render() {
    if (!current_user_can(apply_filters('taxocommerce_core_manage_capability', 'manage_options'))) {
        wp_die(esc_html__('No tienes permisos para ver Anomalías.', 'taxocommerce-core'));
    }
    echo '<section class="tc-anomalies" aria-label="' . esc_attr__('Anomalías', 'taxocommerce-core') . '">';
    if (!taxocommerce_core_anomalies_table_exists('seo_relations')) {
        echo '<p class="description">' . esc_html__( 'La jerarquía SEO no está disponible o no se ha configurado. No se pueden comprobar anomalías estructurales.', 'taxocommerce-core' ) . '</p></section>';
        return;
    }
    // An unconfigured store does not have a broken SEO hierarchy.
    $configured_nodes = 0;
    foreach ( array( 'cluster', 'hub_primary', 'hub_secondary', 'landing' ) as $role ) {
        $configured_nodes += count( TaxoCommerce_Core_Hierarchy_Classic::role_pages( $role ) );
    }
    // Existing relationship rows are not proof of a configured hierarchy.
    if ( 0 === $configured_nodes ) {
        echo '<p class="description">' . esc_html__( 'Jerarquía SEO no configurada. No hay anomalías estructurales que comprobar.', 'taxocommerce-core' ) . '</p></section>';
        return;
    }
    taxocommerce_core_anomalies_render_report();
    echo '</section>';
}

function taxocommerce_core_anomalies_faq_table() {
    global $wpdb;

    $faq_table = esc_sql( $wpdb->prefix . 'seo_faq' );
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Anomalies audit and cleanup preflight must read current canonical tables.
    $faq_exists = $wpdb->get_var(
        $wpdb->prepare('SHOW TABLES LIKE %s', $faq_table)
    );

    return $faq_exists === $faq_table ? $faq_table : false;
}

function taxocommerce_core_anomalies_data_layer_delete_ids($table_key, $ids, $type, $label, $metadata = array()) {
    $ids = array_values(array_unique(array_filter(array_map('absint', (array) $ids))));
    if (empty($ids)) return 0;

    if (!class_exists('SEO_Data_Layer') || !class_exists('SEO_Data_Operation')) {
        throw new RuntimeException('SEO Data Layer no está disponible. No se ha eliminado ninguna fila SEO.');
    }

    SEO_Data_Layer::table($table_key);
    $operation = SEO_Data_Layer::operation(array(
        'type'          => sanitize_key($type),
        'label'         => sanitize_text_field($label),
        'source_module' => 'taxocommerce_core_anomalies',
        'rollbackable'  => true,
        'risk_level'    => 'medium',
        'audit_level'   => 'full',
        'metadata'      => array_merge((array) $metadata, array('table_key' => $table_key, 'rows' => count($ids))),
    ));
    $operation->mark_validated(array('validated_rows' => count($ids)));
    $operation->mark_previewed(count($ids));

    return (int) $operation->execute(
        static function (SEO_Data_Operation $op) use ($table_key, $ids, $metadata) {
            $deleted = 0;
            foreach ($ids as $id) {
                $op->delete($table_key, array('id' => $id), (array) $metadata);
                $deleted++;
            }
            return $deleted;
        }
    );
}

function taxocommerce_core_anomalies_resolve_secondary_hub($term_id) {
    global $wpdb;

    $term_id = absint($term_id);

    if ($term_id < 1) {
        return new WP_Error('seo_category_delete_invalid_id', 'ID de categoría no válido.');
    }

    // Native metadata determines the source page role.
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Read-only hierarchy relation lookup.
    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT DISTINCT r.source_id AS hub_id, p.post_title, p.post_status
             FROM {$wpdb->prefix}seo_relations r
             INNER JOIN {$wpdb->postmeta} pm
                ON pm.post_id = r.source_id
               AND pm.meta_key = %s
               AND pm.meta_value = 'hub_secondary'
             INNER JOIN {$wpdb->posts} p
                ON p.ID=r.source_id
               AND p.post_type='page'
               AND p.post_status='publish'
             WHERE r.source_type IN ('hub_secondary','hub_secundario')
               AND r.target_type='product_cat'
               AND r.target_id=%d
               AND r.relation_type='hub_secondary_to_category'
             ORDER BY r.source_id ASC",
            TaxoCommerce_Core_Hierarchy::ROLE_META,
            $term_id
        ),
        ARRAY_A
    );

    if (1 !== count((array) $rows)) {
        if (empty($rows)) {
            return new WP_Error(
                'seo_category_delete_no_secondary_hub',
                'La categoría no tiene un Hub secundario publicado único. No se puede garantizar el redirect 301.'
            );
        }

        return new WP_Error(
            'seo_category_delete_multiple_secondary_hubs',
            'La categoría está vinculada a varios Hubs secundarios. Revisa la estructura antes de borrarla.'
        );
    }

    $hub_id = absint($rows[0]['hub_id'] ?? 0);
    $hub_url = $hub_id > 0 ? get_permalink($hub_id) : '';

    if (!$hub_id || !$hub_url) {
        return new WP_Error(
            'seo_category_delete_invalid_secondary_hub',
            'No se pudo resolver la URL pública del Hub secundario.'
        );
    }

    return array(
        'hub_id'    => $hub_id,
        'hub_title' => sanitize_text_field((string) ($rows[0]['post_title'] ?? '')),
        'hub_url'   => esc_url_raw((string) $hub_url),
    );
}

function taxocommerce_core_anomalies_delete_orphan_category_faqs() {
    global $wpdb;

    if (!current_user_can('manage_options')) {
        wp_die('No tienes permisos para realizar esta acción.');
    }

    check_admin_referer(
        'taxocommerce_core_delete_orphan_category_faqs',
        'seo_faq_orphan_nonce'
    );

    $faq_table = esc_sql( taxocommerce_core_anomalies_faq_table() );

    if (!$faq_table) {
        wp_die('No se encuentra la tabla de FAQs.');
    }

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Anomalies audit and cleanup preflight must read current canonical tables.
    $orphan_ids = $wpdb->get_col(
        $wpdb->prepare( "SELECT f.id
         FROM %i f
         LEFT JOIN {$wpdb->term_taxonomy} tt
            ON tt.term_id = f.object_id
           AND tt.taxonomy = 'product_cat'
         WHERE f.object_type = 2
           AND tt.term_id IS NULL
         ORDER BY f.id ASC", $faq_table )
    );

    try {
        $deleted = taxocommerce_core_anomalies_data_layer_delete_ids(
            'faqs',
            $orphan_ids,
            'delete_orphan_category_faqs',
            'Eliminar FAQs huérfanas de categorías',
            array('object_type' => 2, 'reason' => 'orphan_category')
        );
    } catch (Throwable $e) {
        wp_die('No se pudieron eliminar las FAQs huérfanas de categorías: ' . esc_html($e->getMessage()));
    }

    $redirect_url = add_query_arg(
        array(
            'page' => 'taxocommerce-core-content',
            'tc_type' => 'anomalies',
            
            'faq_category_orphans_deleted' => (int) $deleted,
        ),
        admin_url('admin.php')
    );

    wp_safe_redirect($redirect_url);
    exit;
}

/**
 * Elimina únicamente FAQs marcadas como producto cuyo producto ya no existe.
 * No elimina FAQs de productos que simplemente estén en draft/private/trash:
 * esos casos siguen apareciendo como incidencia separada para revisión manual.
 */
function taxocommerce_core_anomalies_delete_orphan_product_faqs() {
    global $wpdb;

    if (!current_user_can('manage_options')) {
        wp_die('No tienes permisos para realizar esta acción.');
    }

    check_admin_referer(
        'taxocommerce_core_delete_orphan_product_faqs',
        'seo_faq_orphan_nonce'
    );

    $faq_table = esc_sql( taxocommerce_core_anomalies_faq_table() );

    if (!$faq_table) {
        wp_die('No se encuentra la tabla de FAQs.');
    }

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Anomalies audit and cleanup preflight must read current canonical tables.
    $orphan_ids = $wpdb->get_col(
        $wpdb->prepare( "SELECT f.id
         FROM %i f
         LEFT JOIN {$wpdb->posts} p
            ON p.ID = f.object_id
         WHERE f.object_type = 3
           AND (p.ID IS NULL OR p.post_type <> 'product')
         ORDER BY f.id ASC", $faq_table )
    );

    try {
        $deleted = taxocommerce_core_anomalies_data_layer_delete_ids(
            'faqs',
            $orphan_ids,
            'delete_orphan_product_faqs',
            'Eliminar FAQs huérfanas de productos',
            array('object_type' => 3, 'reason' => 'orphan_product')
        );
    } catch (Throwable $e) {
        wp_die('No se pudieron eliminar las FAQs huérfanas de productos: ' . esc_html($e->getMessage()));
    }

    $redirect_url = add_query_arg(
        array(
            'page' => 'taxocommerce-core-content',
            'tc_type' => 'anomalies',
            
            'faq_product_orphans_deleted' => (int) $deleted,
        ),
        admin_url('admin.php')
    );

    wp_safe_redirect($redirect_url);
    exit;
}

function taxocommerce_core_anomalies_render_faq_orphans() {
    global $wpdb;

    $faq_table = esc_sql( taxocommerce_core_anomalies_faq_table() );

    // An optional FAQ module without a table is not an anomaly.
    if ( ! $faq_table ) {
        return;
    }
    ob_start();
    echo '<h3 style="color:#d63638; border-bottom:1px solid #ccd0d4; padding-bottom:5px; margin-top:40px;">' . esc_html__( '🧩 FAQs sin elemento relacionado', 'taxocommerce-core' ) . '</h3>';
    echo '<p>' . esc_html__( 'Detecta FAQs que quedaron vivas después de borrar, mover o enviar a papelera categorías, productos o páginas/hubs.', 'taxocommerce-core' ) . '</p>';

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin notice counter.
    if (isset($_GET['faq_category_orphans_deleted'])) {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only numeric redirect notice, no state mutation.
        $deleted = absint(wp_unslash($_GET['faq_category_orphans_deleted']));
        echo '<div class="notice notice-success inline"><p>FAQs de categorías desaparecidas eliminadas: <strong>' . esc_html(number_format_i18n($deleted)) . '</strong>.</p></div>';
    }

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin notice counter.
    if (isset($_GET['faq_product_orphans_deleted'])) {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only numeric redirect notice, no state mutation.
        $deleted = absint(wp_unslash($_GET['faq_product_orphans_deleted']));
        echo '<div class="notice notice-success inline"><p>FAQs de productos desaparecidos eliminadas: <strong>' . esc_html(number_format_i18n($deleted)) . '</strong>.</p></div>';
    }

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Anomalies audit and cleanup preflight must read current canonical tables.
    $category_total = (int) $wpdb->get_var(
        $wpdb->prepare( "SELECT COUNT(*)
         FROM %i f
         LEFT JOIN {$wpdb->term_taxonomy} tt
            ON tt.term_id = f.object_id
           AND tt.taxonomy = 'product_cat'
         WHERE f.object_type = 2
           AND tt.term_id IS NULL", $faq_table )
    );

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Anomalies audit and cleanup preflight must read current canonical tables.
    $category_objects = (int) $wpdb->get_var(
        $wpdb->prepare( "SELECT COUNT(DISTINCT f.object_id)
         FROM %i f
         LEFT JOIN {$wpdb->term_taxonomy} tt
            ON tt.term_id = f.object_id
           AND tt.taxonomy = 'product_cat'
         WHERE f.object_type = 2
           AND tt.term_id IS NULL", $faq_table )
    );

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Anomalies audit and cleanup preflight must read current canonical tables.
    $product_orphans = (int) $wpdb->get_var(
        $wpdb->prepare( "SELECT COUNT(*)
         FROM %i f
         LEFT JOIN {$wpdb->posts} p
            ON p.ID = f.object_id
         WHERE f.object_type = 3
           AND (p.ID IS NULL OR p.post_type <> 'product')", $faq_table )
    );

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Anomalies audit and cleanup preflight must read current canonical tables.
    $product_objects = (int) $wpdb->get_var(
        $wpdb->prepare( "SELECT COUNT(DISTINCT f.object_id)
         FROM %i f
         LEFT JOIN {$wpdb->posts} p
            ON p.ID = f.object_id
         WHERE f.object_type = 3
           AND (p.ID IS NULL OR p.post_type <> 'product')", $faq_table )
    );

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Anomalies audit and cleanup preflight must read current canonical tables.
    $product_not_published = (int) $wpdb->get_var(
        $wpdb->prepare( "SELECT COUNT(*)
         FROM %i f
         INNER JOIN {$wpdb->posts} p
            ON p.ID = f.object_id
         WHERE f.object_type = 3
           AND p.post_type = 'product'
           AND p.post_status <> 'publish'", $faq_table )
    );

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Anomalies audit and cleanup preflight must read current canonical tables.
    $hub_orphans = (int) $wpdb->get_var(
        $wpdb->prepare( "SELECT COUNT(*)
         FROM %i f
         LEFT JOIN {$wpdb->posts} p
            ON p.ID = f.object_id
         WHERE f.object_type = 1
           AND p.ID IS NULL", $faq_table )
    );

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Anomalies audit and cleanup preflight must read current canonical tables.
    $hub_trash = (int) $wpdb->get_var(
        $wpdb->prepare( "SELECT COUNT(*)
         FROM %i f
         INNER JOIN {$wpdb->posts} p
            ON p.ID = f.object_id
         WHERE f.object_type = 1
           AND p.post_status = 'trash'", $faq_table )
    );

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Anomalies audit and cleanup preflight must read current canonical tables.
    $unknown_type = (int) $wpdb->get_var(
        $wpdb->prepare( "SELECT COUNT(*)
         FROM %i
         WHERE object_type NOT IN (1, 2, 3)", $faq_table )
    );

    $faq_findings = $category_total + $product_orphans + $product_not_published + $hub_orphans + $hub_trash + $unknown_type;
    if ( 0 === $faq_findings && ! taxocommerce_core_anomalies_show_all()
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- These read-only notice flags do not change data.
        && ! isset( $_GET['faq_category_orphans_deleted'] ) && ! isset( $_GET['faq_product_orphans_deleted'] ) ) {
        ob_end_clean();
        return;
    }
    echo ob_get_clean(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped section heading and notices.
    echo '<table class="widefat striped" style="margin:10px 0 20px;">';
    echo '<thead>' . '<tr>' . '<th>' . esc_html__( 'Chequeo', 'taxocommerce-core' ) . '</th>' . '<th>' . esc_html__( 'Resultado', 'taxocommerce-core' ) . '</th>' . '<th>' . esc_html__( 'Lectura', 'taxocommerce-core' ) . '</th>' . '</tr>' . '</thead>' . '<tbody>';
    taxocommerce_core_anomalies_faq_row('FAQs en categorías inexistentes', $category_total, 'Afecta a ' . $category_objects . ' object_id de categoría.');
    taxocommerce_core_anomalies_faq_row('FAQs en productos inexistentes', $product_orphans, 'Afecta a ' . $product_objects . ' object_id de producto.');
    taxocommerce_core_anomalies_faq_row('FAQs en productos no publicados', $product_not_published, 'FAQs asociadas a productos draft, private, trash u otro estado no publicado. No se borran con la limpieza de productos desaparecidos.');
    taxocommerce_core_anomalies_faq_row('FAQs en hubs/páginas inexistentes', $hub_orphans, 'FAQs asociadas a páginas/hubs eliminados.');
    taxocommerce_core_anomalies_faq_row('FAQs en hubs/páginas en papelera', $hub_trash, 'FAQs asociadas a páginas/hubs existentes pero en papelera.');
    taxocommerce_core_anomalies_faq_row('FAQs con object_type desconocido', $unknown_type, 'FAQs cuyo object_type no es 1, 2 ni 3.');
    echo '</tbody></table>';

    if ($category_total === 0 && $product_orphans === 0 && $product_not_published === 0 && $hub_orphans === 0 && $hub_trash === 0 && $unknown_type === 0) {
        echo '<p style="color:#2e7d32; font-style:italic;">' . esc_html__( 'No se encontraron incidencias estructurales en FAQs.', 'taxocommerce-core' ) . '</p>';
        return;
    }

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Anomalies audit and cleanup preflight must read current canonical tables.
    $category_summary = $wpdb->get_results(
        $wpdb->prepare( "SELECT
            f.object_id AS lost_object_id,
            COUNT(*) AS total_faqs,
            SUM(f.active = 1) AS active_faqs,
            SUM(f.active = 0) AS inactive_faqs,
            MIN(f.created_at) AS first_created_at,
            MAX(f.updated_at) AS last_updated_at
         FROM %i f
         LEFT JOIN {$wpdb->term_taxonomy} tt
            ON tt.term_id = f.object_id
           AND tt.taxonomy = 'product_cat'
         WHERE f.object_type = 2
           AND tt.term_id IS NULL
         GROUP BY f.object_id
         ORDER BY total_faqs DESC, f.object_id ASC
         LIMIT 30", $faq_table )
    );

    if (!empty($category_summary)) {
        echo '<div style="margin-top:20px;padding:14px;background:#fff;border:1px solid #dcdcde;border-left:4px solid #d63638;">';
        echo '<h4 style="margin:0 0 10px;">' . esc_html__( 'Resumen de categorías perdidas con FAQs', 'taxocommerce-core' ) . '</h4>';
        echo '<p style="margin:0 0 12px;">Hay <strong>' . esc_html(number_format_i18n($category_total)) . ' FAQs</strong> ligadas a <strong>' . esc_html(number_format_i18n($category_objects)) . ' categorías desaparecidas</strong>. Se muestran hasta 30 object_id.</p>';
        echo '<table class="widefat striped" style="margin-top:10px;">';
        echo '<thead>' . '<tr>' . '<th>' . esc_html__( 'Object ID perdido', 'taxocommerce-core' ) . '</th>' . '<th>' . esc_html__( 'FAQs', 'taxocommerce-core' ) . '</th>' . '<th>' . esc_html__( 'Activas', 'taxocommerce-core' ) . '</th>' . '<th>' . esc_html__( 'Inactivas', 'taxocommerce-core' ) . '</th>' . '<th>' . esc_html__( 'Primera creación', 'taxocommerce-core' ) . '</th>' . '<th>' . esc_html__( 'Última actualización', 'taxocommerce-core' ) . '</th>' . '</tr>' . '</thead>' . '<tbody>';

        foreach ($category_summary as $row) {
            echo '<tr>';
            echo '<td><code>' . esc_html((string) $row->lost_object_id) . '</code></td>';
            echo '<td>' . esc_html((string) (int) $row->total_faqs) . '</td>';
            echo '<td>' . esc_html((string) (int) $row->active_faqs) . '</td>';
            echo '<td>' . esc_html((string) (int) $row->inactive_faqs) . '</td>';
            echo '<td>' . esc_html((string) $row->first_created_at) . '</td>';
            echo '<td>' . esc_html((string) $row->last_updated_at) . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin-top:12px;" onsubmit="return confirm(\'Se eliminarán definitivamente todas las FAQs cuya categoría de producto ya no exista. La condición se comprobará de nuevo al ejecutar el borrado. ¿Continuar?\');">';
        echo '<input type="hidden" name="action" value="taxocommerce_core_delete_orphan_category_faqs">';
        wp_nonce_field('taxocommerce_core_delete_orphan_category_faqs', 'seo_faq_orphan_nonce');
        if (class_exists('SEO_Data_Layer') && class_exists('SEO_Data_Operation')) { echo '<button type="submit" class="button button-primary" style="background:#d63638;border-color:#d63638;">' . esc_html__( 'Borrar FAQs de categorías desaparecidas', 'taxocommerce-core' ) . '</button>'; } else { echo '<p>' . esc_html__( 'El borrado requiere SEO Data Layer. Informe disponible en modo consulta.', 'taxocommerce-core' ) . '</p>'; }
        echo '</form>';
        echo '</div>';
    }

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Anomalies audit and cleanup preflight must read current canonical tables.
    $product_summary = $wpdb->get_results(
        $wpdb->prepare( "SELECT
            f.object_id AS lost_object_id,
            COUNT(*) AS total_faqs,
            SUM(f.active = 1) AS active_faqs,
            SUM(f.active = 0) AS inactive_faqs,
            MIN(f.created_at) AS first_created_at,
            MAX(f.updated_at) AS last_updated_at
         FROM %i f
         LEFT JOIN {$wpdb->posts} p
            ON p.ID = f.object_id
         WHERE f.object_type = 3
           AND (p.ID IS NULL OR p.post_type <> 'product')
         GROUP BY f.object_id
         ORDER BY total_faqs DESC, f.object_id ASC
         LIMIT 30", $faq_table )
    );

    if (!empty($product_summary)) {
        echo '<div style="margin-top:20px;padding:14px;background:#fff;border:1px solid #dcdcde;border-left:4px solid #d63638;">';
        echo '<h4 style="margin:0 0 10px;">' . esc_html__( 'Resumen de productos perdidos con FAQs', 'taxocommerce-core' ) . '</h4>';
        echo '<p style="margin:0 0 12px;">Hay <strong>' . esc_html(number_format_i18n($product_orphans)) . ' FAQs</strong> ligadas a <strong>' . esc_html(number_format_i18n($product_objects)) . ' productos desaparecidos</strong>. Se muestran hasta 30 object_id.</p>';
        echo '<table class="widefat striped" style="margin-top:10px;">';
        echo '<thead>' . '<tr>' . '<th>' . esc_html__( 'Object ID perdido', 'taxocommerce-core' ) . '</th>' . '<th>' . esc_html__( 'FAQs', 'taxocommerce-core' ) . '</th>' . '<th>' . esc_html__( 'Activas', 'taxocommerce-core' ) . '</th>' . '<th>' . esc_html__( 'Inactivas', 'taxocommerce-core' ) . '</th>' . '<th>' . esc_html__( 'Primera creación', 'taxocommerce-core' ) . '</th>' . '<th>' . esc_html__( 'Última actualización', 'taxocommerce-core' ) . '</th>' . '</tr>' . '</thead>' . '<tbody>';

        foreach ($product_summary as $row) {
            echo '<tr>';
            echo '<td><code>' . esc_html((string) $row->lost_object_id) . '</code></td>';
            echo '<td>' . esc_html((string) (int) $row->total_faqs) . '</td>';
            echo '<td>' . esc_html((string) (int) $row->active_faqs) . '</td>';
            echo '<td>' . esc_html((string) (int) $row->inactive_faqs) . '</td>';
            echo '<td>' . esc_html((string) $row->first_created_at) . '</td>';
            echo '<td>' . esc_html((string) $row->last_updated_at) . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin-top:12px;" onsubmit="return confirm(\'Se eliminarán definitivamente todas las FAQs cuyo producto ya no exista. No se borrarán FAQs de productos que sigan existiendo en draft, private o trash. ¿Continuar?\');">';
        echo '<input type="hidden" name="action" value="taxocommerce_core_delete_orphan_product_faqs">';
        wp_nonce_field('taxocommerce_core_delete_orphan_product_faqs', 'seo_faq_orphan_nonce');
        if (class_exists('SEO_Data_Layer') && class_exists('SEO_Data_Operation')) { echo '<button type="submit" class="button button-primary" style="background:#d63638;border-color:#d63638;">' . esc_html__( 'Borrar FAQs de productos desaparecidos', 'taxocommerce-core' ) . '</button>'; } else { echo '<p>' . esc_html__( 'El borrado requiere SEO Data Layer. Informe disponible en modo consulta.', 'taxocommerce-core' ) . '</p>'; }
        echo '</form>';
        echo '</div>';
    }

    echo '<p style="margin-top:16px;">' . '<strong>' . esc_html__( 'Nota:', 'taxocommerce-core' ) . '</strong>' . ' ' . esc_html__( 'estas acciones limpian solo FAQs verdaderamente huérfanas. Los productos que todavía existen pero no están publicados permanecen fuera del borrado automático para revisión manual.', 'taxocommerce-core' ) . '</p>';
}

/**
 * Renderiza una fila de estado para el bloque de FAQs huérfanas.
 */
function taxocommerce_core_anomalies_faq_row($label, $count, $description) {
    $count = (int) $count;
    $is_warning = $count > 0;
    $color = $is_warning ? '#b32d2e' : '#2e7d32';
    $state = $is_warning ? 'Revisar' : 'OK';

    echo '<tr>';
    echo '<td><strong>' . esc_html($label) . '</strong></td>';
    echo '<td><strong style="color:' . esc_attr($color) . ';">' . esc_html($state) . '</strong> · ' . esc_html(number_format_i18n($count)) . '</td>';
    echo '<td>' . esc_html($description) . '</td>';
    echo '</tr>';
}

function taxocommerce_core_anomalies_empty_category_state($term_id) {

    global $wpdb;

    $term_id = absint($term_id);

    $state = array(
        'eligible'      => false,
        'reason'        => '',
        'term'          => null,
        'product_count' => 0,
        'landing_count' => 0,
        'hub_id'        => 0,
        'hub_title'     => '',
        'hub_url'       => '',
    );

    if ($term_id <= 0) {
        $state['reason'] = 'ID de categoría no válido.';
        return $state;
    }

    $term = get_term($term_id, 'product_cat');

    if (!$term || is_wp_error($term)) {
        $state['reason'] = 'La categoría ya no existe.';
        return $state;
    }

    $state['term'] = $term;

    $default_product_cat = absint(get_option('default_product_cat'));

    if ($default_product_cat > 0 && $default_product_cat === $term_id) {
        $state['reason'] = 'Es la categoría predeterminada de WooCommerce.';
        return $state;
    }

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Anomalies audit and cleanup preflight must read current canonical tables.
    $term_taxonomy_id = (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT term_taxonomy_id
             FROM {$wpdb->term_taxonomy}
             WHERE term_id = %d
               AND taxonomy = 'product_cat'
             LIMIT 1",
            $term_id
        )
    );

    if ($term_taxonomy_id <= 0) {
        $state['reason'] = 'No existe como taxonomía product_cat.';
        return $state;
    }

    // No dependemos solo de term_taxonomy.count: comprobamos relaciones reales
    // con productos en cualquier estado para evitar borrar una categoría usada.
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Anomalies audit and cleanup preflight must read current canonical tables.
    $state['product_count'] = (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(DISTINCT tr.object_id)
             FROM {$wpdb->term_relationships} tr
             INNER JOIN {$wpdb->posts} p
                ON p.ID = tr.object_id
               AND p.post_type IN ('product', 'product_variation')
             WHERE tr.term_taxonomy_id = %d",
            $term_taxonomy_id
        )
    );

    if ($state['product_count'] > 0) {
        $state['reason'] = 'Tiene productos relacionados actualmente.';
        return $state;
    }

    // Una categoría vacía puede seguir siendo un destino comercial válido de
    // una landing. Borrarla crearía inmediatamente una relación rota y una
    // nueva anomalía, por lo que se protege aunque no tenga productos todavía.
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Anomalies audit and cleanup preflight must read current canonical tables.
    $state['landing_count'] = (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*)
             FROM {$wpdb->prefix}seo_relations
             WHERE target_type = 'product_cat'
               AND target_id = %d
               AND relation_type = 'landing_to_category'",
            $term_id
        )
    );

    if ($state['landing_count'] > 0) {
        $state['reason'] = 'Tiene landing pages asociadas.';
        return $state;
    }

    $hub = function_exists('taxocommerce_core_anomalies_resolve_secondary_hub')
        ? taxocommerce_core_anomalies_resolve_secondary_hub($term_id)
        : new WP_Error('seo_category_delete_hub_service', 'No está disponible la resolución del Hub secundario.');

    if (is_wp_error($hub)) {
        $state['reason'] = $hub->get_error_message();
        return $state;
    }

    $state['hub_id'] = absint($hub['hub_id'] ?? 0);
    $state['hub_title'] = sanitize_text_field((string) ($hub['hub_title'] ?? ''));
    $state['hub_url'] = esc_url_raw((string) ($hub['hub_url'] ?? ''));
    $state['eligible'] = true;
    $state['reason'] = '0 productos, sin landings y con Hub secundario válido para redirect 301.';

    return $state;
}

function taxocommerce_core_anomalies_delete_empty_categories() {

    global $wpdb;

    if (!current_user_can('manage_options')) {
        wp_die('No tienes permisos para realizar esta acción.');
    }

    check_admin_referer(
        'taxocommerce_core_delete_empty_product_categories',
        'seo_empty_categories_nonce'
    );

    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Every category ID is normalized with absint immediately below.
    $category_ids = isset($_POST['category_ids'])
        ? array_map('absint', (array) wp_unslash($_POST['category_ids']))
        : array();

    $category_ids = array_values(
        array_unique(
            array_filter(
                array_map('absint', $category_ids)
            )
        )
    );

    $deleted = 0;
    $skipped = 0;
    $relations_deleted = 0;
    $nodes_deleted = 0;
    $faqs_deleted = 0;
    $vocabulary_deleted = 0;

    $faq_table       = function_exists('taxocommerce_core_anomalies_faq_table')
        ? taxocommerce_core_anomalies_faq_table()
        : false;

    foreach ($category_ids as $term_id) {

        $state = taxocommerce_core_anomalies_empty_category_state($term_id);

        if (empty($state['eligible'])) {
            $skipped++;
            continue;
        }

        /*
         * Borrado canónico:
         * - exige Hub secundario publicado único;
         * - elimina la product_cat mediante WordPress/WooCommerce;
         * - crea/actualiza el 301 al Hub secundario por Data Layer;
         * - limpia relaciones, nodos, Vocabulary y FAQs por Data Layer.
         */
        if (!function_exists('seo_delete_product_category_with_hub_redirect') || !class_exists('SEO_Data_Layer')) {
            wp_die(esc_html__('No disponible sin el servicio original de redirecciones/Data Layer. No se ha borrado ninguna categoría.', 'taxocommerce-core'));
        }
        $result = seo_delete_product_category_with_hub_redirect(
            $term_id,
            'seo_reports'
        );

        if (is_wp_error($result)) {
            $skipped++;
            if (function_exists('seo_system_private_log')) {
                seo_system_private_log('error', '[SEO Reports] Categoría #' . $term_id . ' no eliminada: ' . $result->get_error_message());
            }
            continue;
        }

        $deleted++;
    }

    $redirect_url = add_query_arg(
        array(
            'page' => 'taxocommerce-core-content',
            'tc_type' => 'anomalies',
            
            'empty_categories_deleted'     => $deleted,
            'empty_categories_skipped'     => $skipped,
            'empty_relations_deleted'      => $relations_deleted,
            'empty_nodes_deleted'          => $nodes_deleted,
            'empty_vocabulary_deleted'     => $vocabulary_deleted,
            'empty_category_faqs_deleted'  => $faqs_deleted,
        ),
        admin_url('admin.php')
    );

    wp_safe_redirect($redirect_url);
    exit;
}


/**
 * Recalcula los contadores de todas las categorías de producto utilizando
 * la API de WordPress/WooCommerce.
 *
 * No mueve, publica, elimina ni modifica productos o categorías.
 * Solo reconstruye los contadores almacenados de product_cat.
 */
function taxocommerce_core_anomalies_recount_categories() {

    global $wpdb;

    if (!current_user_can('manage_options')) {
        wp_die('No tienes permisos para realizar esta acción.');
    }

    check_admin_referer(
        'taxocommerce_core_recount_product_categories',
        'seo_recount_categories_nonce'
    );

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Anomalies audit and cleanup preflight must read current canonical tables.
    $term_taxonomy_ids = $wpdb->get_col(
        "SELECT term_taxonomy_id
         FROM {$wpdb->term_taxonomy}
         WHERE taxonomy = 'product_cat'"
    );

    $term_taxonomy_ids = array_values(
        array_unique(
            array_filter(
                array_map('intval', $term_taxonomy_ids)
            )
        )
    );

    if (!empty($term_taxonomy_ids)) {
        wp_update_term_count_now(
            $term_taxonomy_ids,
            'product_cat'
        );

        clean_taxonomy_cache('product_cat');
    }

    $redirect_url = add_query_arg(
        array(
            'page' => 'taxocommerce-core-content',
            'tc_type' => 'anomalies',
            
            'product_categories_recounted' => count($term_taxonomy_ids),
        ),
        admin_url('admin.php')
    );

    wp_safe_redirect($redirect_url);
    exit;
}

/**
 * True only when the administrator explicitly wants successful audit sections too.
 */
function taxocommerce_core_anomalies_show_all() {
    // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only report display preference; no data is changed.
    $requested = isset( $_GET['tc_anomalies_all'] ) && is_string( $_GET['tc_anomalies_all'] )
        ? sanitize_text_field( wp_unslash( $_GET['tc_anomalies_all'] ) ) : '';
    // phpcs:enable WordPress.Security.NonceVerification.Recommended
    return '1' === $requested;
}

/**
 * Emit a section only if it contains a real finding, unless the full view was requested.
 * The original queries and actions remain unchanged; only their presentation is filtered.
 */
function taxocommerce_core_anomalies_finish_section( $has_findings, $show_all, &$visible_findings ) {
    $html = ob_get_clean();
    if ( $has_findings ) {
        ++$visible_findings;
        if ( function_exists( 'taxocommerce_core_anomalies_full_json_capture_section' ) ) {
            taxocommerce_core_anomalies_full_json_capture_section( $html );
        }
    }
    if ( $has_findings || $show_all ) {
        echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by the original report renderers.
    }
}

function taxocommerce_core_anomalies_render_report() {
    global $wpdb;
    $show_all = taxocommerce_core_anomalies_show_all();
    $visible_findings = 0;

    $map = array();
    foreach ( array( 'cluster', 'hub_primary', 'hub_secondary' ) as $role ) {
        foreach ( TaxoCommerce_Core_Hierarchy_Classic::role_pages( $role ) as $page ) {
            $map[ $page->ID ] = $role;
        }
    }
    
    $clusters = [];
    $hubs_primary = [];
    $hubs_secondary = [];
    
    foreach ($map as $id => $role) {
        if ($role === 'cluster') $clusters[] = $id;
        if ($role === 'hub_primary') $hubs_primary[] = $id;
        if ($role === 'hub_secondary') $hubs_secondary[] = $id;
    }

    echo '<h2>' . esc_html__( '🚨 Reporte de Auditoría y Control de Estructura', 'taxocommerce-core' ) . '</h2>';
    echo '<p>' . esc_html__( 'Monitoreo global de la integridad estructural de Nodos y relaciones semánticas en la base de datos.', 'taxocommerce-core' ) . '</p>';
    $report_url = admin_url( 'admin.php?page=taxocommerce-core-content&tc_type=anomalies' );
    $complete_export_url = wp_nonce_url(
        admin_url( 'admin-post.php?action=taxocommerce_core_anomalies_full_json_export' ),
        'taxocommerce_core_anomalies_full_json_export'
    );
    echo '<p style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;"><a class="button button-secondary" href="' .
        esc_url( $show_all ? $report_url : add_query_arg( 'tc_anomalies_all', '1', $report_url ) ) .
        '">' . esc_html( $show_all ? __( 'Mostrar solo incidencias', 'taxocommerce-core' ) :
        __( 'Mostrar todas las comprobaciones', 'taxocommerce-core' ) ) . '</a>';
    echo '<a class="button button-secondary" href="' . esc_url( $complete_export_url ) . '">' .
        esc_html__( 'Descargar JSON de todas las anomalías', 'taxocommerce-core' ) . '</a></p>';

    if ($show_all && function_exists('taxocommerce_core_anomalies_content_json_render_panel') && taxocommerce_core_anomalies_table_exists('seo_object_vocabulary') && taxocommerce_core_anomalies_table_exists('seo_vocabulary')) {
        taxocommerce_core_anomalies_content_json_render_panel();
    }
    
    echo "<div style='background: #fff; border: 1px solid #ccd0d4; padding: 20px; margin-top: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);'>";

    ob_start(); // Audit section buffered to suppress irrelevant success messages.
    echo '<h3 style="color:#d63638; border-bottom:1px solid #ccd0d4; padding-bottom:5px;">' . esc_html__( 'Clusters sin hubs primarios', 'taxocommerce-core' ) . '</h3>';
    $has_clusters_anomalies = false;
    foreach ($clusters as $cluster_id) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Anomalies audit and cleanup preflight must read current canonical tables.
        $count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}seo_relations WHERE source_type='cluster' AND source_id=%d AND relation_type='cluster_to_primary'", $cluster_id));
        if ($count == 0) {
            echo "• " . esc_html(get_the_title($cluster_id)) . " <code style='font-size:12px;'>(" . esc_html((string) $cluster_id) . ")</code><br>";
            $has_clusters_anomalies = true;
        }
    }
    if (!$has_clusters_anomalies) echo '<p style="color:#2e7d32; font-style:italic;">' . esc_html__( 'No se encontraron incidencias en esta sección.', 'taxocommerce-core' ) . '</p>';
    
    taxocommerce_core_anomalies_finish_section( $has_clusters_anomalies, $show_all, $visible_findings );
    ob_start(); // Audit section buffered to suppress irrelevant success messages.
    echo '<h3 style="color:#d63638; border-bottom:1px solid #ccd0d4; padding-bottom:5px; margin-top:30px;">' . esc_html__( 'Hubs primarios sin cluster', 'taxocommerce-core' ) . '</h3>';
    $has_hp_anomalies = false;
    foreach ($hubs_primary as $hp_id) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Anomalies audit and cleanup preflight must read current canonical tables.
        $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}seo_relations WHERE target_id=%d AND relation_type='cluster_to_primary'", $hp_id));
        if ($exists == 0) {
            echo "• " . esc_html(get_the_title($hp_id)) . " <code style='font-size:12px;'>(" . esc_html((string) $hp_id) . ")</code><br>";
            $has_hp_anomalies = true;
        }
    }
    if (!$has_hp_anomalies) echo '<p style="color:#2e7d32; font-style:italic;">' . esc_html__( 'No se encontraron incidencias en esta sección.', 'taxocommerce-core' ) . '</p>';
    
    taxocommerce_core_anomalies_finish_section( $has_hp_anomalies, $show_all, $visible_findings );
    ob_start(); // Audit section buffered to suppress irrelevant success messages.
    echo '<h3 style="color:#d63638; border-bottom:1px solid #ccd0d4; padding-bottom:5px; margin-top:30px;">' . esc_html__( 'Hubs secundarios sin hub primary', 'taxocommerce-core' ) . '</h3>';
    $has_hs_anomalies = false;
    foreach ($hubs_secondary as $hs_id) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Anomalies audit and cleanup preflight must read current canonical tables.
        $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}seo_relations WHERE target_id=%d AND relation_type='hub_primary_to_hub_secondary'", $hs_id));
        if ($exists == 0) {
            echo "• " . esc_html(get_the_title($hs_id)) . " <code style='font-size:12px;'>(" . esc_html((string) $hs_id) . ")</code><br>";
            $has_hs_anomalies = true;
        }
    }
    if (!$has_hs_anomalies) echo '<p style="color:#2e7d32; font-style:italic;">' . esc_html__( 'No se encontraron incidencias en esta sección.', 'taxocommerce-core' ) . '</p>';
    
    
    taxocommerce_core_anomalies_finish_section( $has_hs_anomalies, $show_all, $visible_findings );
    ob_start(); // Audit section buffered to suppress irrelevant success messages.
    echo '<h3 style="
    color:#b57d00;
    border-bottom:1px solid #ccd0d4;
    padding-bottom:5px;
    margin-top:30px;
    ">' . '
        ' . esc_html__( 'Hubs secundarios sin categorías', 'taxocommerce-core' ) . '
    ' . '</h3>';
    
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Anomalies audit and cleanup preflight must read current canonical tables.
    $secondary_without_categories = $wpdb->get_results("
        SELECT
            n.post_id AS hub_secondary_id,
            p.post_title AS hub_secondary_title,
            p.post_status,
            parent_rel.source_id AS hub_primary_id,
            parent_post.post_title AS hub_primary_title
        FROM {$wpdb->postmeta} n

        INNER JOIN {$wpdb->posts} p
            ON p.ID = n.post_id
           AND p.post_type = 'page'
    
        LEFT JOIN {$wpdb->prefix}seo_relations category_rel
            ON category_rel.source_id = n.post_id
           AND category_rel.source_type = 'hub_secondary'
           AND category_rel.target_type = 'product_cat'
           AND category_rel.relation_type = 'hub_secondary_to_category'
    
        LEFT JOIN {$wpdb->prefix}seo_relations parent_rel
            ON parent_rel.target_id = n.post_id
           AND parent_rel.source_type = 'hub_primary'
           AND parent_rel.target_type = 'hub_secondary'
           AND parent_rel.relation_type = 'hub_primary_to_hub_secondary'
    
        LEFT JOIN {$wpdb->posts} parent_post
            ON parent_post.ID = parent_rel.source_id
    
        WHERE n.meta_key = '_taxocommerce_core_seo_role'
          AND n.meta_value = 'hub_secondary'
          AND category_rel.id IS NULL
    
        GROUP BY
            n.post_id,
            p.post_title,
            p.post_status,
            parent_rel.source_id,
            parent_post.post_title
    
        ORDER BY
            p.post_title ASC
    ");
    
    if (!empty($secondary_without_categories)) {
    
        echo '<p style="color:#646970;">';
        echo esc_html__( 'Estos hubs secundarios pertenecen a la estructura, pero no conducen a ninguna categoría de WooCommerce.', 'taxocommerce-core' );
        echo '</p>';
    
        foreach ($secondary_without_categories as $hub) {
    
            $hub_id       = (int) $hub->hub_secondary_id;
            $hub_title    = $hub->hub_secondary_title ?: '(Sin título)';
            $parent_id    = (int) $hub->hub_primary_id;
            $parent_title = $hub->hub_primary_title ?: 'Sin hub primario';
            $edit_url     = get_edit_post_link($hub_id, 'raw');
            $view_url     = get_permalink($hub_id);
    
            echo '<div style="
                margin:0 0 12px;
                padding:10px 12px;
                background:#fff8e5;
                border-left:4px solid #dba617;
            ">';
    
            echo '<strong>' . esc_html($hub_title) . '</strong> ';
            echo '<code>(' . esc_html($hub_id) . ')</code>';
    
            echo '<br>';
    
            echo 'Hub primario: <strong>' .
                esc_html($parent_title) .
                '</strong>';
    
            if ($parent_id > 0) {
                echo ' <code>(' . esc_html($parent_id) . ')</code>';
            }
    
            echo '<br>';
    
            echo 'Estado de la página: <strong>' .
                esc_html($hub->post_status) .
                '</strong>';
    
            echo '<br>';
    
            echo '<span style="color:#8a5a00;">';
            echo esc_html__( 'Revisar si debe recibir categorías, mantenerse como página informativa o eliminarse de la estructura.', 'taxocommerce-core' );
            echo '</span>';
    
            if ($view_url || $edit_url) {
                echo '<div style="margin-top:7px;">';
    
                if ($view_url) {
                    echo '<a href="' . esc_url($view_url) . '" ';
                    echo 'target="_blank" rel="noopener">Ver</a>';
                }
    
                if ($view_url && $edit_url) {
                    echo ' · ';
                }
    
                if ($edit_url) {
                    echo '<a href="' . esc_url($edit_url) . '">Editar</a>';
                }
    
                echo '</div>';
            }
    
            echo '</div>';
        }
    
    } else {
    
        echo '<p style="color:#2e7d32;font-style:italic;">';
        echo esc_html__( 'Todos los hubs secundarios tienen al menos una categoría vinculada.', 'taxocommerce-core' );
        echo '</p>';
    }
    
    
    
    taxocommerce_core_anomalies_finish_section( ! empty( $secondary_without_categories ), $show_all, $visible_findings );
    ob_start(); // Audit section buffered to suppress irrelevant success messages.
    echo '<h3 style="color:#d63638; border-bottom:1px solid #ccd0d4; padding-bottom:5px; margin-top:30px;">' . esc_html__( 'Hubs primarios en múltiples clusters', 'taxocommerce-core' ) . '</h3>';
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Anomalies audit and cleanup preflight must read current canonical tables.
    $multi = $wpdb->get_results("SELECT target_id, COUNT(*) as c FROM {$wpdb->prefix}seo_relations WHERE relation_type='cluster_to_primary' GROUP BY target_id HAVING c > 1");
    if (!empty($multi)) {
        foreach ($multi as $m) {
            echo "• " . esc_html(get_the_title($m->target_id)) . " <code style='font-size:12px;'>(" . esc_html((string) $m->target_id) . ")</code> - <strong>" . esc_html((string) $m->c) . " clusters</strong><br>";
        }
    } else {
        echo '<p style="color:#2e7d32; font-style:italic;">' . esc_html__( 'No se encontraron incidencias en esta sección.', 'taxocommerce-core' ) . '</p>';
    }
    
    taxocommerce_core_anomalies_finish_section( ! empty( $multi ), $show_all, $visible_findings );
    ob_start(); // Audit section buffered to suppress irrelevant success messages.
    echo '<h3 style="color:#d63638; border-bottom:1px solid #ccd0d4; padding-bottom:5px; margin-top:30px;">' . esc_html__( 'Landings sin hub secondary', 'taxocommerce-core' ) . '</h3>';
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Anomalies audit and cleanup preflight must read current canonical tables.
    $landings = $wpdb->get_results("SELECT DISTINCT target_id FROM {$wpdb->prefix}seo_relations WHERE target_type='landing_page'");
    $has_landings_anomalies = false;
    foreach ($landings as $l) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Anomalies audit and cleanup preflight must read current canonical tables.
        $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}seo_relations WHERE target_id=%d AND relation_type='hub_secondary_to_landing'", $l->target_id));
        if ($exists == 0) {
            echo "• " . esc_html(get_the_title($l->target_id)) . " <code style='font-size:12px;'>(" . esc_html((string) $l->target_id) . ")</code><br>";
            $has_landings_anomalies = true;
        }
    }
    if (!$has_landings_anomalies) echo '<p style="color:#2e7d32; font-style:italic;">' . esc_html__( 'No se encontraron incidencias en esta sección.', 'taxocommerce-core' ) . '</p>';

    /*
     * Landings sin relación comercial directa con una categoría de producto.
     * Solo se auditan páginas con seo_role=landing: clusters, hubs y páginas
     * corporativas siguen otras reglas estructurales y no deben marcarse aquí.
     */
    taxocommerce_core_anomalies_finish_section( $has_landings_anomalies, $show_all, $visible_findings );
    ob_start(); // Audit section buffered to suppress irrelevant success messages.
    echo '<h3 style="color:#d63638; border-bottom:1px solid #ccd0d4; padding-bottom:5px; margin-top:40px;">' . esc_html__( '🧭 Landing pages sin categoría de producto asociada', 'taxocommerce-core' ) . '</h3>';

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Anomalies audit and cleanup preflight must read current canonical tables.
    $landings_without_product_category = $wpdb->get_results("
        SELECT DISTINCT
            n.post_id AS page_id,
            p.post_title,
            p.post_status
        FROM {$wpdb->postmeta} n
        INNER JOIN {$wpdb->posts} p
            ON p.ID = n.post_id
           AND p.post_type = 'page'
        WHERE n.meta_key = '_taxocommerce_core_seo_role'
          AND n.meta_value = 'landing'
          AND p.post_status IN ('publish', 'future')
          AND NOT EXISTS (
              SELECT 1
              FROM {$wpdb->prefix}seo_relations r
              WHERE r.source_type = 'landing'
                AND r.source_id = n.post_id
                AND r.target_type = 'product_cat'
                AND r.relation_type = 'landing_to_category'
          )
        ORDER BY p.post_status ASC, p.post_title ASC
    " );

    if (!empty($landings_without_product_category)) {
        echo '<p style="color:#646970;">';
        echo 'Estas landing pages tienen rol SEO en WordPress, pero no tienen ninguna relación <code>landing_to_category</code> hacia <code>product_cat</code>.';
        echo '</p>';
        echo '<div style="background:#fcf0f1;border-left:4px solid #d63638;padding:10px 12px;margin-bottom:12px;">';
        echo 'Total detectadas: <strong>' . esc_html(number_format_i18n(count($landings_without_product_category))) . '</strong>';
        echo '</div>';

        foreach ($landings_without_product_category as $landing) {
            $page_id   = (int) $landing->page_id;
            $title     = $landing->post_title ?: '(Sin título)';
            $edit_url  = taxocommerce_core_anomalies_edit_url('pages', $page_id);
            $wp_edit_url = get_edit_post_link($page_id, 'raw');
            $view_url  = get_permalink($page_id);

            echo '<div style="margin:0 0 10px;padding:10px 12px;background:#fff;border-left:4px solid #d63638;">';
            echo '<strong>' . esc_html($title) . '</strong> ';
            echo '<code>(' . esc_html($page_id) . ')</code><br>';
            echo 'Estado: <strong>' . esc_html($landing->post_status) . '</strong><br>';
            echo '<span style="color:#b32d2e;">' . esc_html__( 'Sin relación comercial landing_to_category.', 'taxocommerce-core' ) . '</span>';

            if ($view_url || $edit_url) {
                echo '<div style="margin-top:6px;">';
                if ($view_url) {
                    echo '<a href="' . esc_url($view_url) . '" target="_blank" rel="noopener">Ver</a>';
                }
                if ($view_url && $edit_url) {
                    echo ' · ';
                }
                if ($edit_url) {
                    echo '<a href="' . esc_url($edit_url) . '"><strong>Editar en Core</strong></a>';
                }
                if ($wp_edit_url) {
                    echo ' · <a href="' . esc_url($wp_edit_url) . '">Editor WordPress</a>';
                }
                echo '</div>';
            }

            echo '</div>';
        }
    } else {
        echo '<p style="color:#2e7d32;font-style:italic;">' . esc_html__( 'Todas las landing pages publicadas o programadas tienen al menos una categoría de producto asociada.', 'taxocommerce-core' ) . '</p>';
    }

    /*
     * Posts editoriales sin relación comercial con una categoría de producto.
     *
     * Regla editorial actual:
     * - Guías y Comparativas deben enlazar al menos una product_cat mediante post_to_category.
     * - Noticias y contenidos funcionales/corporativos se excluyen de esta anomalía.
     * - Solo se revisan posts publicados o programados.
     */
    taxocommerce_core_anomalies_finish_section( ! empty( $landings_without_product_category ), $show_all, $visible_findings );
    ob_start(); // Audit section buffered to suppress irrelevant success messages.
    echo '<h3 style="color:#d63638; border-bottom:1px solid #ccd0d4; padding-bottom:5px; margin-top:40px;">' . esc_html__( '🔗 Posts editoriales sin categoría de producto relacionada', 'taxocommerce-core' ) . '</h3>';

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Anomalies audit and cleanup preflight must read current canonical tables.
    $posts_without_product_category_raw = $wpdb->get_results("
        SELECT DISTINCT
            p.ID AS post_id,
            p.post_title,
            p.post_name,
            p.post_status,
            p.post_date
        FROM {$wpdb->posts} p
        INNER JOIN {$wpdb->term_relationships} tr_editorial
            ON tr_editorial.object_id = p.ID
        INNER JOIN {$wpdb->term_taxonomy} tt_editorial
            ON tt_editorial.term_taxonomy_id = tr_editorial.term_taxonomy_id
           AND tt_editorial.taxonomy = 'category'
        INNER JOIN {$wpdb->terms} t_editorial
            ON t_editorial.term_id = tt_editorial.term_id
        WHERE p.post_type = 'post'
          AND p.post_status IN ('publish', 'future')
          AND t_editorial.slug IN ('guias-y-comparativas', 'guias', 'comparativas')
          AND NOT EXISTS (
              SELECT 1
              FROM {$wpdb->prefix}seo_relations r
              WHERE r.source_type = 'post'
                AND r.source_id = p.ID
                AND r.target_type = 'product_cat'
                AND r.relation_type = 'post_to_category'
          )
        ORDER BY p.post_status ASC, p.post_date DESC, p.ID DESC
    " );

    $non_commercial_post_slugs = (array) apply_filters(
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Retained legacy hook for backwards compatibility with existing extensions.
        'seo_reports_non_commercial_post_slugs',
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
            'proveedores-de-distribuidor-de-herramientas-es',
            'densl-suministro-profesional-de-equipamiento-de-seguridad-vial-bajo-presupuesto',
            'soluciones',
            'productos-genericos-de-ferreteria',
        )
    );

    $non_commercial_post_slugs = array_values(
        array_unique(
            array_filter(
                array_map('sanitize_title', $non_commercial_post_slugs)
            )
        )
    );

    $posts_without_product_category = array();
    $posts_without_product_category_excluded = 0;

    foreach ((array) $posts_without_product_category_raw as $post_row) {
        $post_slug = sanitize_title((string) ($post_row->post_name ?? ''));
        if (in_array($post_slug, $non_commercial_post_slugs, true)) {
            $posts_without_product_category_excluded++;
            continue;
        }
        $posts_without_product_category[] = $post_row;
    }

    if (!empty($posts_without_product_category_excluded)) {
        echo '<p style="color:#646970;">';
        echo 'Se han excluido <strong>' . esc_html(number_format_i18n($posts_without_product_category_excluded)) . '</strong> entradas funcionales o corporativas que no necesitan relación comercial.';
        echo '</p>';
    }

    if (!empty($posts_without_product_category)) {
        echo '<p style="color:#646970;">';
        echo 'Estas Guías o Comparativas están publicadas o programadas, pero no tienen ninguna relación <code>post_to_category</code> hacia una categoría de producto.';
        echo '</p>';
        echo '<div style="background:#fcf0f1;border-left:4px solid #d63638;padding:10px 12px;margin-bottom:12px;">';
        echo 'Total detectados: <strong>' . esc_html(number_format_i18n(count($posts_without_product_category))) . '</strong>';
        echo '</div>';

        foreach ($posts_without_product_category as $post_row) {
            $post_id  = absint($post_row->post_id ?? 0);
            $title    = $post_row->post_title ?: '(Sin título)';
            $edit_url = taxocommerce_core_anomalies_edit_url('posts', $post_id);
            $wp_edit_url = get_edit_post_link($post_id, 'raw');
            $view_url = get_permalink($post_id);

            echo '<div style="margin:0 0 10px;padding:10px 12px;background:#fff;border-left:4px solid #d63638;">';
            echo '<strong>' . esc_html($title) . '</strong> ';
            echo '<code>(' . esc_html($post_id) . ')</code><br>';
            echo 'Estado: <strong>' . esc_html($post_row->post_status) . '</strong>';
            if (!empty($post_row->post_date)) {
                echo ' · Fecha: <strong>' . esc_html($post_row->post_date) . '</strong>';
            }
            echo '<br>' . '<span style="color:#b32d2e;">' . esc_html__( 'Sin relación comercial post_to_category.', 'taxocommerce-core' ) . '</span>';

            if ($view_url || $edit_url || $wp_edit_url) {
                echo '<div style="margin-top:6px;">';
                if ($view_url) {
                    echo '<a href="' . esc_url($view_url) . '" target="_blank" rel="noopener">Ver</a>';
                }
                if ($edit_url) {
                    if ($view_url) echo ' · ';
                    echo '<a href="' . esc_url($edit_url) . '"><strong>Editar relación comercial</strong></a>';
                }
                if ($wp_edit_url) {
                    echo ' · <a href="' . esc_url($wp_edit_url) . '">Editor WordPress</a>';
                }
                echo '</div>';
            }

            echo '</div>';
        }
    } else {
        echo '<p style="color:#2e7d32;font-style:italic;">' . esc_html__( 'Todas las Guías y Comparativas publicadas o programadas tienen al menos una categoría de producto relacionada.', 'taxocommerce-core' ) . '</p>';
    }
    taxocommerce_core_anomalies_finish_section( ! empty( $posts_without_product_category ), $show_all, $visible_findings );

    /*
     * Entradas editoriales sin Vocabulary semantico activo.
     *
     * Modelo actual:
     * - Vocabulary describe la semantica canonica del post;
     * - la relacion comercial con el catalogo se audita por separado mediante post_to_category;
     * - solo se consideran asignaciones activas a terminos de Vocabulary tambien activos;
     * - se auditan los grupos canonicos usados por el sistema semantico;
     * - las entradas funcionales, legales, corporativas o de navegacion no necesitan
     *   Vocabulary de catalogo y se excluyen para evitar falsos positivos.
     *
     * Incluye publicados y programados; excluye borradores, papelera y revisiones.
     */
    $has_core_vocabulary = taxocommerce_core_anomalies_table_exists('seo_object_vocabulary') && taxocommerce_core_anomalies_table_exists('seo_vocabulary');
    if ( $has_core_vocabulary ) {
    ob_start(); // Audit section buffered to suppress irrelevant success messages.
    echo '<h3 style="color:#d63638; border-bottom:1px solid #ccd0d4; padding-bottom:5px; margin-top:40px;">' . esc_html__( '📰 Entradas editoriales sin Vocabulary semántico activo', 'taxocommerce-core' ) . '</h3>';
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Anomalies audit and cleanup preflight must read current canonical tables.
    $posts_without_vocabulary_raw = $has_core_vocabulary ? $wpdb->get_results("
        SELECT
            p.ID AS post_id,
            p.post_title,
            p.post_name,
            p.post_status,
            p.post_date
        FROM {$wpdb->posts} p
        WHERE p.post_type = 'post'
          AND p.post_status IN ('publish', 'future')
          AND NOT EXISTS (
              SELECT 1
              FROM {$wpdb->prefix}seo_object_vocabulary ov
              INNER JOIN {$wpdb->prefix}seo_vocabulary v
                  ON v.id = ov.vocabulary_id
              WHERE ov.object_type = 'post'
                AND ov.object_id = p.ID
                AND ov.status = 1
                AND v.active = 1
                AND v.semantic_group IN ('rol','tipo','aplicacion','plataforma','subtipo')
          )
        ORDER BY p.post_status ASC, p.post_date DESC, p.ID DESC
    " ) : array();

    /*
     * Inventario real de contenido que actualmente vive como post pero cumple
     * una función de navegación, cuenta, legal, corporativa, servicio o índice.
     * No se le debe forzar ROL/TIPO/APLICACION solo para dejar el informe a cero.
     *
     * El filtro permite ampliar o reducir este inventario en otras instalaciones
     * sin modificar el núcleo del informe.
     */
    $non_editorial_post_slugs = (array) apply_filters(
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Retained legacy hook for backwards compatibility with existing extensions.
        'seo_reports_non_editorial_post_slugs',
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
            'proveedores-de-distribuidor-de-herramientas-es',
            'densl-suministro-profesional-de-equipamiento-de-seguridad-vial-bajo-presupuesto',
            'soluciones',
            'productos-genericos-de-ferreteria',
        )
    );

    $non_editorial_post_slugs = array_values(
        array_unique(
            array_filter(
                array_map('sanitize_title', $non_editorial_post_slugs)
            )
        )
    );

    $functional_post_ids = array();
    if (function_exists('wc_get_page_id')) {
        foreach (array('cart', 'checkout', 'myaccount', 'shop', 'terms') as $wc_page_key) {
            $wc_page_id = absint(wc_get_page_id($wc_page_key));
            if ($wc_page_id > 0) {
                $functional_post_ids[$wc_page_id] = true;
            }
        }
    }

    foreach (array('page_on_front', 'page_for_posts') as $option_key) {
        $option_id = absint(get_option($option_key));
        if ($option_id > 0) {
            $functional_post_ids[$option_id] = true;
        }
    }

    $posts_without_vocabulary = array();
    $posts_without_vocabulary_excluded = 0;

    foreach ((array) $posts_without_vocabulary_raw as $post_row) {
        $post_id   = absint($post_row->post_id ?? 0);
        $post_slug = sanitize_title((string) ($post_row->post_name ?? ''));

        $is_non_editorial = isset($functional_post_ids[$post_id])
            || in_array($post_slug, $non_editorial_post_slugs, true);

        $is_non_editorial = (bool) apply_filters(
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Retained legacy hook for backwards compatibility with existing extensions.
            'seo_reports_is_non_editorial_post',
            $is_non_editorial,
            $post_id,
            $post_slug,
            $post_row
        );

        if ($is_non_editorial) {
            $posts_without_vocabulary_excluded++;
            continue;
        }

        $posts_without_vocabulary[] = $post_row;
    }

    if (!empty($posts_without_vocabulary_excluded)) {
        echo '<p style="color:#646970;">';
        echo 'Se han excluido <strong>' . esc_html(number_format_i18n($posts_without_vocabulary_excluded)) . '</strong> entradas funcionales, legales, corporativas o de navegación que no necesitan Vocabulary de catálogo.';
        echo '</p>';
    }

    if (!$has_core_vocabulary) {
        echo '<p>' . esc_html__( 'Sin datos: faltan tablas de Vocabulary; este bloque no se ha comprobado.', 'taxocommerce-core' ) . '</p>';
    } elseif (!empty($posts_without_vocabulary)) {
        echo '<p style="color:#646970;">';
        echo 'Estas entradas editoriales están publicadas o programadas, pero no tienen ninguna asignación activa de Vocabulary canónico. La relación comercial <code>post_to_category</code> se comprueba en el bloque independiente anterior.';
        echo '</p>';
        echo '<div style="background:#fcf0f1;border-left:4px solid #d63638;padding:10px 12px;margin-bottom:12px;">';
        echo 'Total detectados: <strong>' . esc_html(number_format_i18n(count($posts_without_vocabulary))) . '</strong>';
        echo '</div>';

        foreach ($posts_without_vocabulary as $post_row) {
            $post_id  = (int) $post_row->post_id;
            $title    = $post_row->post_title ?: '(Sin título)';
            // Abrir el editor SEO del plugin, donde se gestiona la semantica del post.
            $edit_url = taxocommerce_core_anomalies_edit_url('posts', $post_id);
            $view_url = get_permalink($post_id);

            echo '<div style="margin:0 0 10px;padding:10px 12px;background:#fff;border-left:4px solid #d63638;">';
            echo '<strong>' . esc_html($title) . '</strong> ';
            echo '<code>(' . esc_html($post_id) . ')</code><br>';
            echo 'Estado: <strong>' . esc_html($post_row->post_status) . '</strong>';
            if (!empty($post_row->post_date)) {
                echo ' · Fecha: <strong>' . esc_html($post_row->post_date) . '</strong>';
            }
            echo '<br>' . '<span style="color:#b32d2e;">' . esc_html__( 'Sin Vocabulary semántico activo en rol, tipo, aplicación, plataforma o subtipo.', 'taxocommerce-core' ) . '</span>';

            if ($view_url || $edit_url) {
                echo '<div style="margin-top:6px;">';
                if ($view_url) {
                    echo '<a href="' . esc_url($view_url) . '" target="_blank" rel="noopener">Ver</a>';
                }
                if ($view_url && $edit_url) {
                    echo ' · ';
                }
                if ($edit_url) {
                    echo '<a href="' . esc_url($edit_url) . '">Editar en Core</a>';
                }
                echo '</div>';
            }

            echo '</div>';
        }
    } else {
        echo '<p style="color:#2e7d32;font-style:italic;">' . esc_html__( 'Todas las entradas editoriales publicadas o programadas tienen al menos un término de Vocabulary canónico activo.', 'taxocommerce-core' ) . '</p>';
    }
    taxocommerce_core_anomalies_finish_section( ! empty( $posts_without_vocabulary ), $show_all, $visible_findings );
    } // End Vocabulary-specific checks. No installed tables means no configured Vocabulary to audit.

    if ( ! empty( $hubs_secondary ) ) {
    ob_start(); // Audit section buffered to suppress irrelevant success messages.
    echo '<h3 style="color:#ff9800; border-bottom:1px solid #ccd0d4; padding-bottom:5px; margin-top:40px;">' . esc_html__( '⚠️ Categorías sin asignación estructural', 'taxocommerce-core' ) . '</h3>';
    $cats = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false]);
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Anomalies audit and cleanup preflight must read current canonical tables.
    $used = $wpdb->get_col("SELECT DISTINCT target_id FROM {$wpdb->prefix}seo_relations WHERE target_type='product_cat'");
    
    $has_cats_anomalies = false;
    $default_product_cat = absint( get_option( 'default_product_cat', 0 ) );
    foreach ($cats as $c) {
        if ( $default_product_cat === (int) $c->term_id ) { continue; }
        if (!in_array($c->term_id, $used)) {
            echo "• " . esc_html($c->name) . " <code style='font-size:12px;'>(" . esc_html((string) $c->term_id) . ")</code><br>";
            $has_cats_anomalies = true;
        }
    }
    if (!$has_cats_anomalies) echo '<p style="color:#2e7d32; font-style:italic;">' . esc_html__( 'Todas las categorías de WooCommerce están correctamente vinculadas en la tabla de relaciones SEO.', 'taxocommerce-core' ) . '</p>';
    taxocommerce_core_anomalies_finish_section( $has_cats_anomalies, $show_all, $visible_findings );
    } // Do not require structural assignment until a secondary hub exists.

        // Empty product categories are structural findings only when a secondary-hub structure exists.
        if ( ! empty( $hubs_secondary ) ) {
        ob_start(); // Audit section buffered to suppress irrelevant success messages.
        // Categorías sin productos + limpieza segura desde el propio informe.
        echo '<div style="display:flex;align-items:center;justify-content:space-between;gap:15px;border-bottom:1px solid #ccd0d4;margin-top:40px;margin-bottom:15px;flex-wrap:wrap;">';

        echo '<h3 style="color:#b57d00;margin:0;padding-bottom:5px;">' . esc_html__( '📦 Categorías de producto con 0 productos', 'taxocommerce-core' ) . '</h3>';

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin:0 0 6px 0;" onsubmit="return confirm(\'Esta acción recalculará los contadores de TODAS las categorías de producto utilizando WordPress y WooCommerce. No se borrará, moverá ni publicará ningún producto y no se eliminará ninguna categoría. Puede tardar unos segundos. ¿Quieres continuar?\');">';
        echo '<input type="hidden" name="action" value="taxocommerce_core_recount_product_categories">';
        wp_nonce_field('taxocommerce_core_recount_product_categories', 'seo_recount_categories_nonce');
        echo '<button type="submit" class="button button-secondary">' . esc_html__( '🔄 Recalcular contadores', 'taxocommerce-core' ) . '</button>';
        echo '</form>';

        echo '</div>';

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only recount notice.
        if (isset($_GET['product_categories_recounted'])) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only numeric redirect notice, no state mutation.
            $recounted_categories = absint(wp_unslash($_GET['product_categories_recounted']));

            echo '<div class="notice notice-success inline"><p>';
            echo '<strong>Contadores recalculados.</strong> Se han procesado <strong>' . esc_html(number_format_i18n($recounted_categories)) . '</strong> categorías de producto. ';
            echo esc_html__( 'La acción solo reconstruye los contadores; no mueve, publica ni elimina productos o categorías.', 'taxocommerce-core' );
            echo '</p></div>';
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only cleanup notice.
        if (isset($_GET['empty_categories_deleted'])) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only numeric redirect notice, no state mutation.
            $deleted_empty = absint(wp_unslash($_GET['empty_categories_deleted']));
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only cleanup notice counter.
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only numeric redirect notice, no state mutation.
            $skipped_empty = isset( $_GET['empty_categories_skipped'] ) ? absint( wp_unslash( $_GET['empty_categories_skipped'] ) ) : 0;
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only cleanup notice counter.
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only numeric redirect notice, no state mutation.
            $relations_empty = isset( $_GET['empty_relations_deleted'] ) ? absint( wp_unslash( $_GET['empty_relations_deleted'] ) ) : 0;
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only cleanup notice counter.
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only numeric redirect notice, no state mutation.
            $nodes_empty = isset( $_GET['empty_nodes_deleted'] ) ? absint( wp_unslash( $_GET['empty_nodes_deleted'] ) ) : 0;
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only cleanup notice counter.
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only numeric redirect notice, no state mutation.
            $faqs_empty = isset( $_GET['empty_category_faqs_deleted'] ) ? absint( wp_unslash( $_GET['empty_category_faqs_deleted'] ) ) : 0;

            echo '<div class="notice notice-success inline"><p>';
            echo 'Limpieza de categorías vacías completada. Categorías eliminadas: <strong>' . esc_html(number_format_i18n($deleted_empty)) . '</strong>.';

            if ($skipped_empty > 0) {
                echo ' Omitidas por seguridad: <strong>' . esc_html(number_format_i18n($skipped_empty)) . '</strong>.';
            }

            echo ' Relaciones SEO eliminadas: <strong>' . esc_html(number_format_i18n($relations_empty)) . '</strong>.';

            if ($nodes_empty > 0) {
                echo ' Registros SEO asociados: <strong>' . esc_html(number_format_i18n($nodes_empty)) . '</strong>.';
            }

            if ($faqs_empty > 0) {
                echo ' FAQs asociadas: <strong>' . esc_html(number_format_i18n($faqs_empty)) . '</strong>.';
            }

            echo '</p></div>';
        }

        $cats_without_products = get_terms(array(
            'taxonomy'   => 'product_cat',
            'hide_empty' => false,
            'orderby'    => 'name',
            'order'      => 'ASC',
        ));

        $empty_categories = array();
        $counter_anomalies = array();
        $eligible_empty_categories = 0;

        /*
         * Obtenemos en una sola consulta dos medidas diferentes:
         *
         * - published_count: productos publicados realmente relacionados.
         *   Es la referencia adecuada para comprobar si term_taxonomy.count
         *   está desactualizado.
         *
         * - related_count: cualquier producto o variación relacionada,
         *   independientemente de su estado. Esta es la referencia segura
         *   para decidir si una categoría está realmente vacía.
         *
         * De este modo una categoría que solo tenga productos en draft no
         * se considera vacía ni se propone para borrado.
         */
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Anomalies audit and cleanup preflight must read current canonical tables.
        $real_count_rows = $wpdb->get_results("
            SELECT
                tt.term_id,
                COUNT(DISTINCT CASE
                    WHEN p.post_type = 'product'
                     AND p.post_status = 'publish'
                    THEN p.ID
                END) AS published_count,
                COUNT(DISTINCT CASE
                    WHEN p.post_type IN ('product', 'product_variation')
                    THEN p.ID
                END) AS related_count
            FROM {$wpdb->term_taxonomy} tt
            LEFT JOIN {$wpdb->term_relationships} tr
                ON tr.term_taxonomy_id = tt.term_taxonomy_id
            LEFT JOIN {$wpdb->posts} p
                ON p.ID = tr.object_id
            WHERE tt.taxonomy = 'product_cat'
            GROUP BY tt.term_id
        ");

        $real_counts = array();

        foreach ($real_count_rows as $row) {
            $real_counts[(int) $row->term_id] = array(
                'published' => (int) $row->published_count,
                'related'   => (int) $row->related_count,
            );
        }

        if (!is_wp_error($cats_without_products)) {

            foreach ($cats_without_products as $cat) {

                $term_id = (int) $cat->term_id;
                // WooCommerce's fallback category is mandatory, not an orphan or deletion candidate.
                if ( $term_id === absint( get_option( 'default_product_cat', 0 ) ) ) {
                    continue;
                }
                $stored_count = (int) $cat->count;

                $published_count = isset($real_counts[$term_id])
                    ? (int) $real_counts[$term_id]['published']
                    : 0;

                $related_count = isset($real_counts[$term_id])
                    ? (int) $real_counts[$term_id]['related']
                    : 0;

                /*
                 * El contador almacenado debe reflejar los productos publicados.
                 * Si no coincide, lo mostramos como anomalía de recuento,
                 * pero nunca como categoría vacía por ese solo motivo.
                 */
                if ($stored_count !== $published_count) {
                    $counter_anomalies[] = array(
                        'term'            => $cat,
                        'stored_count'    => $stored_count,
                        'published_count' => $published_count,
                        'related_count'   => $related_count,
                    );
                }

                /*
                 * Solo es candidata a la sección "Categorías sin productos"
                 * cuando no existe ninguna relación real con productos.
                 */
                if ($related_count !== 0) {
                    continue;
                }

                $state = taxocommerce_core_anomalies_empty_category_state($term_id);

                $empty_categories[] = array(
                    'term'  => $cat,
                    'state' => $state,
                );

                if (!empty($state['eligible'])) {
                    $eligible_empty_categories++;
                }
            }
        }

        /*
         * Las discrepancias de contador se informan aparte.
         * No se ofrece borrado porque estas categorías sí tienen productos.
         */
        if (!empty($counter_anomalies)) {

            echo '<div style="background:#f0f6fc;border-left:4px solid #2271b1;padding:12px 14px;margin:0 0 18px;line-height:1.6;">';
            echo '<strong>' . esc_html__( 'Contadores de categorías desactualizados', 'taxocommerce-core' ) . '</strong>' . '<br>';
            echo 'Se han detectado <strong>' . esc_html(number_format_i18n(count($counter_anomalies))) . ' categorías</strong> cuyo contador almacenado no coincide con los productos publicados realmente relacionados. ';
            echo '<strong>' . esc_html__( 'No se consideran categorías vacías y no se ofrece su borrado.', 'taxocommerce-core' ) . '</strong>';
            echo '</div>';

            echo '<div style="border:1px solid #c3c4c7;background:#fff;border-radius:4px;overflow:hidden;margin-bottom:20px;">';

            foreach ($counter_anomalies as $item) {

                $cat = $item['term'];

                echo '<div style="padding:9px 12px;border-bottom:1px solid #f0f0f1;">';
                echo '<strong>' . esc_html($cat->name) . '</strong> ';
                echo '<code style="font-size:12px;">(' . esc_html((int) $cat->term_id) . ')</code>';
                echo '<div style="font-size:12px;color:#50575e;margin-top:3px;">';
                echo 'Contador guardado: <strong>' . esc_html(number_format_i18n((int) $item['stored_count'])) . '</strong> · ';
                echo 'Productos publicados reales: <strong>' . esc_html(number_format_i18n((int) $item['published_count'])) . '</strong> · ';
                echo 'Productos relacionados en cualquier estado: <strong>' . esc_html(number_format_i18n((int) $item['related_count'])) . '</strong>';
                echo '</div>';
                echo '</div>';
            }

            echo '</div>';
        }

        if (empty($empty_categories)) {
            echo '<p style="color:#2e7d32; font-style:italic;">' . esc_html__( 'No se encontraron categorías realmente vacías.', 'taxocommerce-core' ) . '</p>';
        } else {

            echo '<div style="background:#fff8e5;border-left:4px solid #dba617;padding:12px 14px;margin:0 0 14px;line-height:1.6;">';
            echo 'Se han detectado <strong>' . esc_html(number_format_i18n(count($empty_categories))) . ' categorías WooCommerce con 0 productos relacionados</strong>. ';
            echo esc_html__( 'El borrado vuelve a comprobar el estado real justo antes de actuar y solo permite eliminar categorías con 0 productos, sin relaciones funcionales protegidas y con un Hub secundario publicado único. Al borrar se crea automáticamente un redirect 301 hacia ese Hub.', 'taxocommerce-core' ) . ' ';
            echo '<strong>' . esc_html__( 'No se elimina ningún producto.', 'taxocommerce-core' ) . '</strong>';
            echo '</div>';

            echo '<form id="seo-empty-categories-form" method="post" action="' . esc_url(admin_url('admin-post.php')) . '" onsubmit="return confirm(\'Se eliminarán definitivamente las categorías seleccionadas que sigan con 0 productos y sin relaciones funcionales protegidas. También se limpiarán sus relaciones y datos SEO asociados y sus FAQs. No se eliminará ningún producto. La jerarquía product_cat no se usa como criterio. ¿Continuar?\');">';
            echo '<input type="hidden" name="action" value="taxocommerce_core_delete_empty_product_categories">';
            wp_nonce_field('taxocommerce_core_delete_empty_product_categories', 'seo_empty_categories_nonce');

            echo '<div style="border:1px solid #dcdcde;background:#fff;border-radius:4px;overflow:hidden;">';

            foreach ($empty_categories as $item) {

                $cat = $item['term'];
                $state = $item['state'];
                $eligible = !empty($state['eligible']);

                echo '<div style="display:flex;align-items:flex-start;gap:10px;padding:9px 12px;border-bottom:1px solid #f0f0f1;">';

                echo '<div style="padding-top:1px;">';
                echo '<input class="seo-empty-category-checkbox" type="checkbox" name="category_ids[]" value="' . esc_attr((int) $cat->term_id) . '"' . ($eligible ? '' : ' disabled') . '>';
                echo '</div>';

                echo '<div style="flex:1;">';
                echo '<strong>' . esc_html($cat->name) . '</strong> ';
                echo '<code style="font-size:12px;">(' . esc_html((int) $cat->term_id) . ')</code>';

                if ($eligible) {
                    echo '<div style="font-size:12px;color:#2e7d32;margin-top:2px;">Eliminable: 0 productos. Redirect 301 → Hub secundario <strong>' . esc_html((string) ($state['hub_title'] ?? '')) . '</strong> <code>#' . esc_html((string) absint($state['hub_id'] ?? 0)) . '</code>.</div>';
                } else {
                    echo '<div style="font-size:12px;color:#b32d2e;margin-top:2px;">Protegida: ' . esc_html($state['reason']) . '</div>';
                }

                echo '</div>';
                echo '</div>';
            }

            echo '</div>';

            if ($eligible_empty_categories > 0 && function_exists('seo_delete_product_category_with_hub_redirect') && class_exists('SEO_Data_Layer')) {
                echo '<div style="display:flex;align-items:center;gap:10px;margin-top:12px;flex-wrap:wrap;">';
                echo '<button type="button" id="seo-toggle-empty-categories" class="button">' . esc_html__( 'Seleccionar todas las eliminables', 'taxocommerce-core' ) . '</button>';
                echo '<button type="submit" class="button button-primary" style="background:#d63638;border-color:#d63638;">' . esc_html__( 'Eliminar categorías seleccionadas', 'taxocommerce-core' ) . '</button>';
                echo '<span style="color:#646970;font-size:12px;">Eliminables ahora: <strong>' . esc_html(number_format_i18n($eligible_empty_categories)) . '</strong></span>';
                echo '</div>';
            } else {
                echo '<p style="color:#646970;margin:12px 0 0;">' . esc_html__( 'La limpieza automática requiere el servicio original de redirecciones y Data Layer. Esta vista permite revisar las categorías sin borrarlas.', 'taxocommerce-core' ) . '</p>';
            }

            echo '</form>';

            taxocommerce_core_anomalies_inline_script(
                'document.addEventListener("DOMContentLoaded",function(){var b=document.getElementById("seo-toggle-empty-categories");if(!b){return;}b.addEventListener("click",function(){var boxes=Array.prototype.slice.call(document.querySelectorAll("#seo-empty-categories-form .seo-empty-category-checkbox:not(:disabled)"));var allChecked=boxes.length>0&&boxes.every(function(box){return box.checked;});boxes.forEach(function(box){box.checked=!allChecked;});b.textContent=allChecked?"Seleccionar todas las eliminables":"Deseleccionar todas";});});'
            );
        }





        taxocommerce_core_anomalies_finish_section( ! empty( $empty_categories ) || ! empty( $counter_anomalies ), $show_all, $visible_findings );
        } // End structurally relevant category audit.

        ob_start(); // Audit section buffered to suppress irrelevant success messages.
        echo '<h3 style="color:#d63638; border-bottom:1px solid #ccd0d4; padding-bottom:5px; margin-top:30px;">' . '
            ' . esc_html__( 'Hubs secundarios en múltiples hubs primarios', 'taxocommerce-core' ) . '
        ' . '</h3>';
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Anomalies audit and cleanup preflight must read current canonical tables.
        $secondary_multiple_parents = $wpdb->get_results("
            SELECT
                target_id AS hub_secondary_id,
                COUNT(DISTINCT source_id) AS parent_count
            FROM {$wpdb->prefix}seo_relations
            WHERE relation_type = 'hub_primary_to_hub_secondary'
              AND source_type = 'hub_primary'
              AND target_type = 'hub_secondary'
            GROUP BY target_id
            HAVING COUNT(DISTINCT source_id) > 1
            ORDER BY parent_count DESC, target_id ASC
        ");
        
        if (!empty($secondary_multiple_parents)) {
        
            foreach ($secondary_multiple_parents as $duplicate) {
        
                $secondary_id    = (int) $duplicate->hub_secondary_id;
                $secondary_title = get_the_title($secondary_id);
        
                echo '<div style="margin-bottom:14px; padding:10px 12px; background:#fcf0f1; border-left:4px solid #d63638;">';
        
                echo '• El hub secundario <strong>' .
                    esc_html($secondary_title ?: '(Sin título)') .
                    '</strong> ';
        
                echo '<code style="font-size:12px;">(' .
                    esc_html($secondary_id) .
                    ')</code> ';
        
                echo 'está relacionado con <strong>' .
                    esc_html((int) $duplicate->parent_count) .
                    ' hubs primarios</strong>:';
        
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Anomalies audit and cleanup preflight must read current canonical tables.
                $parents = $wpdb->get_results(
                    $wpdb->prepare(
                        "
                        SELECT
                            r.id,
                            r.source_id AS hub_primary_id,
                            p.post_title AS hub_primary_title
                        FROM {$wpdb->prefix}seo_relations r
                        LEFT JOIN {$wpdb->posts} p
                            ON p.ID = r.source_id
                        WHERE r.relation_type = 'hub_primary_to_hub_secondary'
                          AND r.source_type = 'hub_primary'
                          AND r.target_type = 'hub_secondary'
                          AND r.target_id = %d
                        ORDER BY r.id ASC
                        ",
                        $secondary_id
                    )
                );
        
                echo '<ul style="margin:8px 0 0 24px; list-style:disc;">';
        
                foreach ($parents as $parent) {
        
                    echo '<li>';
        
                    echo esc_html(
                        $parent->hub_primary_title ?: '(Hub primario sin título)'
                    );
        
                    echo ' <code style="font-size:11px;">';
                    echo 'Hub ID: ' . esc_html((int) $parent->hub_primary_id);
                    echo ' · Relación ID: ' . esc_html((int) $parent->id);
                    echo '</code>';
        
                    echo '</li>';
                }
        
                echo '</ul>';
                echo '</div>';
            }
        
        } else {
        
            echo '<p style="color:#2e7d32; font-style:italic;">' . '
                ' . esc_html__( 'No se encontraron hubs secundarios asociados a múltiples hubs primarios.', 'taxocommerce-core' ) . '
            ' . '</p>';
        }

        taxocommerce_core_anomalies_finish_section( ! empty( $secondary_multiple_parents ), $show_all, $visible_findings );
    ob_start(); // Audit section buffered to suppress irrelevant success messages.
    echo '<h3 style="color:#b57d00; border-bottom:1px solid #ccd0d4; padding-bottom:5px; margin-top:40px;">' . esc_html__( '💥 Categorías DUPLICADAS en múltiples Hubs Secundarios', 'taxocommerce-core' ) . '</h3>';
    
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Anomalies audit and cleanup preflight must read current canonical tables.
    $duplicate_cats = $wpdb->get_results("
        SELECT r.target_id as cat_id, COUNT(*) as repetido_veces
        FROM {$wpdb->prefix}seo_relations r 
        WHERE r.source_type = 'hub_secondary' 
          AND r.target_type = 'product_cat'
        GROUP BY r.target_id 
        HAVING repetido_veces > 1
    ");

    if (!empty($duplicate_cats)) {
        foreach ($duplicate_cats as $dup) {
            $term = get_term($dup->cat_id, 'product_cat');
            
            if ($term && !is_wp_error($term)) {
                echo "<div style='margin-bottom:12px; padding-left:5px;'>";
                echo "• 🚨 La categoría <strong>" . esc_html($term->name) . "</strong> <code style='font-size:12px;'> (Cat ID: " . esc_html((string) $dup->cat_id) . ")</code> está asignada en <strong>" . esc_html((string) $dup->repetido_veces) . " Hubs Secundarios</strong> diferentes:";
                
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Anomalies audit and cleanup preflight must read current canonical tables.
                $associated_hubs = $wpdb->get_results($wpdb->prepare("
                    SELECT source_id FROM {$wpdb->prefix}seo_relations 
                    WHERE source_type = 'hub_secondary' 
                      AND target_type = 'product_cat' 
                      AND target_id = %d
                ", $dup->cat_id));
                
                echo "<ul style='margin: 4px 0 0 20px; list-style-type: circle; color: #50575e;'>";
                foreach ($associated_hubs as $hub) {
                    $hub_title = get_the_title($hub->source_id);
                    echo "<li>➡️ " . esc_html($hub_title) . " <code style='font-size:11px;'> (Hub ID: " . esc_html((string) $hub->source_id) . ")</code></li>";
                }
                echo "</ul>";
                echo "</div>";
            }
        }
    } else {
        echo '<p style="color:#2e7d32; font-style:italic;">' . esc_html__( '¡Excelente! No existen categorías duplicadas. Cada categoría de WooCommerce pertenece a un único Hub Secundario.', 'taxocommerce-core' ) . '</p>';
    }

    taxocommerce_core_anomalies_finish_section( ! empty( $duplicate_cats ), $show_all, $visible_findings );
    // FAQs que han perdido el enlace con su categoría, producto o página.
    ob_start();
    taxocommerce_core_anomalies_render_faq_orphans();
    $faq_html = ob_get_clean();
    if ( '' !== trim( $faq_html ) ) {
        ++$visible_findings;
        if ( function_exists( 'taxocommerce_core_anomalies_full_json_capture_section' ) ) {
            taxocommerce_core_anomalies_full_json_capture_section( $faq_html );
        }
        echo $faq_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in FAQ renderer.
    }

    if ( 0 === $visible_findings && ! $show_all ) {
        echo '<p class="description">' . esc_html__( 'No se han detectado incidencias en la estructura configurada.', 'taxocommerce-core' ) . '</p>';
    }
    echo "</div>";
}
