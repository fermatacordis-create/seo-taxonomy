<?php

defined( 'ABSPATH' ) || exit;

final class TCV_Admin {
    public static function boot() {
        add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
        add_action( 'admin_post_tcv_analyze_product', array( __CLASS__, 'analyze_product' ) );
        add_action( 'admin_post_tcv_analyze_filtered', array( __CLASS__, 'analyze_filtered' ) );
        add_action( 'admin_post_tcv_export_classifier_json', array( __CLASS__, 'export_classifier_json' ) );
        add_action( 'admin_post_tcv_analyze_category', array( __CLASS__, 'analyze_category' ) );
        add_action( 'admin_post_tcv_create_term', array( __CLASS__, 'create_term' ) );
    }

    public static function menu() {
        if ( function_exists( 'taxocommerce_core' ) || defined( 'TAXOCOMMERCE_CORE_VERSION' ) ) {
            add_submenu_page(
                'taxocommerce-core',
                __( 'TaxoCommerce Vocabulary', 'taxocommerce-vocabulary' ),
                __( 'Vocabulary', 'taxocommerce-vocabulary' ),
                'manage_options',
                'taxocommerce-vocabulary',
                array( __CLASS__, 'render' )
            );
            return;
        }

        add_menu_page(
            __( 'TaxoCommerce Vocabulary', 'taxocommerce-vocabulary' ),
            __( 'Vocabulary', 'taxocommerce-vocabulary' ),
            'manage_options',
            'taxocommerce-vocabulary',
            array( __CLASS__, 'render' ),
            'dashicons-tag',
            56
        );
    }

    public static function assets( $hook ) {
        if ( false === strpos( $hook, 'taxocommerce-vocabulary' ) ) {
            return;
        }
        wp_enqueue_style( 'tcv-admin', TAXOCOMMERCE_VOCABULARY_URL . 'assets/admin.css', array(), TAXOCOMMERCE_VOCABULARY_VERSION );
    }

    private static function tab() {
        // GET only selects the view; mutations use separate POST actions with nonces.
        // phpcs:disable WordPress.Security.NonceVerification.Recommended
        $tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'labels';
        // phpcs:enable WordPress.Security.NonceVerification.Recommended
        return in_array( $tab, array( 'labels','attributes','classifier','io','control','auto-test','manual' ), true ) ? $tab : 'labels';
    }

    public static function render() {
        if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) {
            return;
        }
        $tab = self::tab();
        echo '<div class="wrap tcv-wrap"><h1>' . esc_html__( 'TaxoCommerce Vocabulary', 'taxocommerce-vocabulary' ) . ' <small>v' . esc_html( TAXOCOMMERCE_VOCABULARY_VERSION ) . '</small></h1><p class="description">' . esc_html__( 'Vocabulario canónico y atributos técnicos compartidos. Clasificador propone; Vocabulary conserva el dato canónico.', 'taxocommerce-vocabulary' ) . '</p>';
        // These GET parameters display an escaped notice; no state changes.
        // phpcs:disable WordPress.Security.NonceVerification.Recommended
        if ( ! empty( $_GET['tcv_message'] ) ) {
            $class = ( isset( $_GET['tcv_status'] ) && 'success' === $_GET['tcv_status'] ) ? 'notice-success' : 'notice-error';
            echo '<div class="notice ' . esc_attr( $class ) . '"><p>' . esc_html( rawurldecode( sanitize_text_field( wp_unslash( $_GET['tcv_message'] ) ) ) ) . '</p></div>';
        }
        // phpcs:enable WordPress.Security.NonceVerification.Recommended
        self::nav( $tab );
        if ( 'labels' === $tab ) {
            self::labels();
        } elseif ( 'attributes' === $tab ) {
            self::attributes();
        } elseif ( 'classifier' === $tab ) {
            self::classifier();
        } elseif ( 'io' === $tab ) {
            self::io();
        } elseif ( 'control' === $tab ) {
            self::control();
        } elseif ( 'auto-test' === $tab ) {
            TCV_Auto_Test::render();
        } else {
            TCV_Manual::render();
        }
        echo '</div>';
    }

    private static function nav( $active ) {
        $tabs = array( 'labels'=>__( 'Etiquetas', 'taxocommerce-vocabulary' ),'attributes'=>__( 'Atributos', 'taxocommerce-vocabulary' ),'classifier'=>__( 'Clasificador', 'taxocommerce-vocabulary' ),'io'=>__( 'Importar / Exportar', 'taxocommerce-vocabulary' ),'control'=>__( 'Control', 'taxocommerce-vocabulary' ),'auto-test'=>__( 'Autocheck', 'taxocommerce-vocabulary' ),'manual'=>__( 'Manual', 'taxocommerce-vocabulary' ) );
        echo '<nav class="nav-tab-wrapper">';
        foreach ( $tabs as $key => $label ) {
            $url = add_query_arg( array( 'page'=>'taxocommerce-vocabulary','tab'=>$key ), admin_url( 'admin.php' ) );
            echo '<a class="nav-tab ' . ( $active === $key ? 'nav-tab-active' : '' ) . '" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
        }
        echo '</nav>';
    }

    // Administrator displays and integrity checks read shared canonical tables live; other modules can change them independently.
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    private static function labels() {
        global $wpdb;
        $t = TCV_Schema::tables();
        $counts = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT semantic_group, COUNT(*) n FROM %i WHERE active=1 GROUP BY semantic_group',
                $t['vocabulary']
            ),
            OBJECT_K
        );
        echo '<div class="tcv-kpis">';
        foreach ( TCV_Classifier::GROUPS as $g ) {
            echo '<div class="tcv-card"><strong>' . esc_html( strtoupper( $g ) ) . '</strong><span>' . esc_html( number_format_i18n( isset( $counts[$g] ) ? $counts[$g]->n : 0 ) ) . '</span></div>';
        }
        echo '</div>';
        echo '<div class="tcv-panel"><h2>' . esc_html__( 'Alta de etiqueta', 'taxocommerce-vocabulary' ) . '</h2><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="tcv_create_term">';
        wp_nonce_field( 'tcv_create_term' );
        echo '<select name="group">';
        foreach ( TCV_Classifier::GROUPS as $g ) {
            echo '<option value="' . esc_attr( $g ) . '">' . esc_html( strtoupper( $g ) ) . '</option>';
        }
        echo '</select>' . ' ' . '<input name="label" class="regular-text" placeholder="' . esc_attr__( 'Nombre visible', 'taxocommerce-vocabulary' ) . '" required>' . ' ' . '<button class="button button-primary">' . esc_html__( 'Dar de alta', 'taxocommerce-vocabulary' ) . '</button>' . '</form>' . '</div>';
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT semantic_group,slug,label,source,active FROM %i ORDER BY semantic_group,label LIMIT %d',
                $t['vocabulary'],
                300
            ),
            ARRAY_A
        );
        echo '<div class="tcv-panel">' . '<h2>' . esc_html__( 'Diccionario', 'taxocommerce-vocabulary' ) . '</h2>' . '<table class="widefat striped">' . '<thead>' . '<tr>' . '<th>' . esc_html__( 'Grupo', 'taxocommerce-vocabulary' ) . '</th>' . '<th>' . esc_html__( 'Etiqueta', 'taxocommerce-vocabulary' ) . '</th>' . '<th>' . esc_html__( 'Slug', 'taxocommerce-vocabulary' ) . '</th>' . '<th>' . esc_html__( 'Origen', 'taxocommerce-vocabulary' ) . '</th>' . '<th>' . esc_html__( 'Estado', 'taxocommerce-vocabulary' ) . '</th>' . '</tr>' . '</thead>' . '<tbody>';
        foreach ( $rows as $r ) {
            echo '<tr><td>' . esc_html( strtoupper( $r['semantic_group'] ) ) . '</td><td>' . esc_html( $r['label'] ) . '</td><td><code>' . esc_html( $r['slug'] ) . '</code></td><td>' . esc_html( $r['source'] ) . '</td><td>' . ( $r['active'] ? esc_html__( 'Activa', 'taxocommerce-vocabulary' ) : esc_html__( 'Inactiva', 'taxocommerce-vocabulary' ) ) . '</td></tr>';
        }
        echo '</tbody>' . '</table>' . '<p class="description">' . esc_html__( 'Se muestran como máximo 300 filas en esta vista.', 'taxocommerce-vocabulary' ) . '</p>' . '</div>';
    }

    // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    // Administrator displays and integrity checks read shared canonical tables live; other modules can change them independently.
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    private static function attributes() {
        global $wpdb;
        $t = TCV_Schema::tables();
        $attrs = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT a.*,
                    (SELECT COUNT(*) FROM %i t WHERE t.atributo_id=a.id AND t.activo=1) term_count,
                    (SELECT COUNT(*) FROM %i x WHERE x.atributo_id=a.id) alias_count
                 FROM %i a ORDER BY a.orden,a.nombre',
                $t['terms'],
                $t['aliases'],
                $t['attributes']
            ),
            ARRAY_A
        );
        echo '<div class="tcv-panel">' . '<h2>' . esc_html__( 'Atributos técnicos canónicos', 'taxocommerce-vocabulary' ) . '</h2>' . '<p>' . esc_html__( 'Definiciones propias de TaxoCommerce. No se convierten automáticamente en atributos WooCommerce.', 'taxocommerce-vocabulary' ) . '</p>' . '<table class="widefat striped">' . '<thead>' . '<tr>' . '<th>' . esc_html__( 'Atributo', 'taxocommerce-vocabulary' ) . '</th>' . '<th>' . esc_html__( 'Tipo', 'taxocommerce-vocabulary' ) . '</th>' . '<th>' . esc_html__( 'Unidad', 'taxocommerce-vocabulary' ) . '</th>' . '<th>' . esc_html__( 'Términos', 'taxocommerce-vocabulary' ) . '</th>' . '<th>' . esc_html__( 'Aliases', 'taxocommerce-vocabulary' ) . '</th>' . '<th>' . esc_html__( 'Estado', 'taxocommerce-vocabulary' ) . '</th>' . '</tr>' . '</thead>' . '<tbody>';
        foreach ( $attrs as $a ) {
            echo '<tr><td><strong>' . esc_html( $a['nombre'] ) . '</strong><br><code>' . esc_html( $a['slug'] ) . '</code></td><td>' . esc_html( $a['tipo'] ) . '</td><td>' . esc_html( $a['unidad_base'] ) . '</td><td>' . esc_html( $a['term_count'] ) . '</td><td>' . esc_html( $a['alias_count'] ) . '</td><td>' . ( $a['activo'] ? esc_html__( 'Activo', 'taxocommerce-vocabulary' ) : esc_html__( 'Inactivo', 'taxocommerce-vocabulary' ) ) . '</td></tr>';
        }
        echo '</tbody></table></div>';
    }

    // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    private static function classifier() {
        TCV_Classifier_Batch::render();
        // GET controls only which results are displayed; POST analysis is nonce-protected.
        // phpcs:disable WordPress.Security.NonceVerification.Recommended
        $filters = TCV_Classifier::sanitize_filters(
            array(
                'search'      => isset( $_GET['search'] ) ? sanitize_text_field( wp_unslash( $_GET['search'] ) ) : '',
                'category_id' => isset( $_GET['category_id'] ) ? absint( wp_unslash( $_GET['category_id'] ) ) : 0,
                'coverage'    => isset( $_GET['coverage'] ) ? sanitize_key( wp_unslash( $_GET['coverage'] ) ) : 'missing_any',
                'priority'    => isset( $_GET['priority'] ) ? sanitize_key( wp_unslash( $_GET['priority'] ) ) : 'all',
                'rows'        => isset( $_GET['rows'] ) ? absint( wp_unslash( $_GET['rows'] ) ) : 25,
            )
        );
        // phpcs:enable WordPress.Security.NonceVerification.Recommended
        $categories = get_terms( array( 'taxonomy'=>'product_cat', 'hide_empty'=>false, 'number'=>250, 'orderby'=>'name', 'order'=>'ASC' ) );
        if ( is_wp_error( $categories ) ) {
            $categories = array();
        }

        echo '<div class="tcv-panel">' . '<h2>' . esc_html__( 'Clasificador', 'taxocommerce-vocabulary' ) . '</h2>' . '<p>' . esc_html__( 'El análisis filtrado es puntual y limitado. Para recorrer el catálogo completo utiliza el control por bloques de la sección anterior.', 'taxocommerce-vocabulary' ) . '</p>';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="tcv-filter">';
        echo '<input type="hidden" name="action" value="tcv_analyze_filtered">';
        wp_nonce_field( 'tcv_analyze_filtered' );
        echo '<label>' . esc_html__( 'Buscar', 'taxocommerce-vocabulary' ) . '<input type="text" name="search" value="' . esc_attr( $filters['search'] ) . '" placeholder="' . esc_attr__( 'Producto, SKU o ID', 'taxocommerce-vocabulary' ) . '"></label>';
        echo '<label>' . esc_html__( 'Categoría', 'taxocommerce-vocabulary' ) . '<select name="category_id">' . '<option value="0">' . esc_html__( 'Todas', 'taxocommerce-vocabulary' ) . '</option>';
        foreach ( $categories as $category ) {
            echo '<option value="' . esc_attr( $category->term_id ) . '" ' . selected( $filters['category_id'], $category->term_id, false ) . '>' . esc_html( $category->name ) . '</option>';
        }
        echo '</select></label>';
        $coverage_labels = array(
            'missing_any'=>__( 'Cobertura incompleta (falta alguna)', 'taxocommerce-vocabulary' ),
            'without_any'=>__( 'Sin etiquetas semánticas', 'taxocommerce-vocabulary' ),
            'without_type'=>__( 'Sin TIPO', 'taxocommerce-vocabulary' ),
            'without_role'=>__( 'Sin ROL', 'taxocommerce-vocabulary' ),
            'without_application'=>__( 'Sin APLICACIÓN', 'taxocommerce-vocabulary' ),
            'without_platform'=>__( 'Sin PLATAFORMA', 'taxocommerce-vocabulary' ),
            'without_subtype'=>__( 'Sin SUBTIPO', 'taxocommerce-vocabulary' ),
        );
        echo '<label>' . esc_html__( 'Cobertura', 'taxocommerce-vocabulary' ) . '<select name="coverage">';
        foreach ( $coverage_labels as $value=>$label ) {
            echo '<option value="' . esc_attr( $value ) . '" ' . selected( $filters['coverage'], $value, false ) . '>' . esc_html( $label ) . '</option>';
        }
        echo '</select></label>';
        $priority_labels = array( 'all'=>__( 'Todas las anomalías', 'taxocommerce-vocabulary' ),'p1'=>__( 'P1 · falta TIPO', 'taxocommerce-vocabulary' ),'p2'=>__( 'P2 · falta ROL', 'taxocommerce-vocabulary' ),'p3'=>__( 'P3 · falta APLICACIÓN', 'taxocommerce-vocabulary' ),'p4'=>__( 'P4 · falta SUBTIPO', 'taxocommerce-vocabulary' ),'p5'=>__( 'P5 · falta PLATAFORMA', 'taxocommerce-vocabulary' ) );
        echo '<label>' . esc_html__( 'Prioridad', 'taxocommerce-vocabulary' ) . '<select name="priority">';
        foreach ( $priority_labels as $value=>$label ) {
            echo '<option value="' . esc_attr( $value ) . '" ' . selected( $filters['priority'], $value, false ) . '>' . esc_html( $label ) . '</option>';
        }
        echo '</select></label>';
        echo '<label>' . esc_html__( 'Filas', 'taxocommerce-vocabulary' ) . '<select name="rows">';
        foreach ( array(10,25,50,100) as $n ) {
            echo '<option value="' . esc_attr( $n ) . '" ' . selected( $filters['rows'], $n, false ) . '>' . esc_html( $n ) . '</option>';
        }
        echo '</select></label>';
        echo '<div class="tcv-filter-actions">' . '<button class="button button-primary">' . esc_html__( 'Analizar filtro', 'taxocommerce-vocabulary' ) . '</button>' . '</div>';
        echo '</form></div>';

        $report = TCV_Classifier::last_report();
        if ( $report ) {
            $export = wp_nonce_url( admin_url( 'admin-post.php?action=tcv_export_classifier_json' ), 'tcv_export_classifier_json' );
            echo '<div class="tcv-panel"><h2>' . esc_html__( 'Último análisis filtrado', 'taxocommerce-vocabulary' ) . '</h2><p>' . esc_html__( 'Procesados:', 'taxocommerce-vocabulary' ) . ' <strong>' . esc_html( absint( $report['processed'] ?? 0 ) ) . '</strong> · ' . esc_html__( 'Generado:', 'taxocommerce-vocabulary' ) . ' ' . esc_html( (string) ( $report['generated_gmt'] ?? '' ) ) . ' UTC.</p>';
            echo '<p><a class="button" href="' . esc_url( $export ) . '" >' . esc_html__( 'Exportar JSON del análisis', 'taxocommerce-vocabulary' ) . '</a></p>';
            echo '<pre class="tcv-json">' . esc_html( wp_json_encode( (array) ( $report['filters'] ?? array() ), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) ) . '</pre></div>';
        }

        $rows = TCV_Classifier::recent_proposals( 100 );
        // Load titles in a single bounded WordPress query instead of one query
        // per proposal. No product data or classifier jobs are modified.
        $titles = array();
        $product_ids = array_values( array_unique( array_filter( array_map( 'absint', array_column( $rows, 'product_id' ) ) ) ) );
        if ( $product_ids ) {
            $products = get_posts( array(
                'post_type' => 'product',
                'post_status' => 'any',
                'post__in' => $product_ids,
                'posts_per_page' => count( $product_ids ),
                'orderby' => 'post__in',
                'no_found_rows' => true,
                'update_post_meta_cache' => false,
                'update_post_term_cache' => false,
            ) );
            foreach ( $products as $product ) {
                $titles[ (int) $product->ID ] = (string) $product->post_title;
            }
        }
        echo '<div class="tcv-panel">' . '<h2>' . esc_html__( 'Propuestas recientes', 'taxocommerce-vocabulary' ) . '</h2>' . '<table class="widefat striped">' . '<thead>' . '<tr>' . '<th>' . esc_html__( 'Producto', 'taxocommerce-vocabulary' ) . '</th>' . '<th>' . esc_html__( 'Título', 'taxocommerce-vocabulary' ) . '</th>' . '<th>' . esc_html__( 'Grupo', 'taxocommerce-vocabulary' ) . '</th>' . '<th>' . esc_html__( 'Propuesta', 'taxocommerce-vocabulary' ) . '</th>' . '<th>' . esc_html__( 'Estado', 'taxocommerce-vocabulary' ) . '</th>' . '<th>' . esc_html__( 'Confianza', 'taxocommerce-vocabulary' ) . '</th>' . '<th>' . esc_html__( 'Actualizado', 'taxocommerce-vocabulary' ) . '</th>' . '<th>' . esc_html__( 'Revisión', 'taxocommerce-vocabulary' ) . '</th>' . '</tr>' . '</thead>' . '<tbody>';
        foreach ( $rows as $r ) {
            $product_id = absint( $r['product_id'] );
            $edit = get_edit_post_link( $product_id );
            $title = ! empty( $titles[ $product_id ] ) ? $titles[ $product_id ] : __( '(Producto sin título o no disponible)', 'taxocommerce-vocabulary' );
            echo '<tr><td><a href="' . esc_url( $edit ) . '">#' . esc_html( $r['product_id'] ) . '</a></td><td style="min-width:260px;max-width:480px;white-space:normal;">' . esc_html( $title ) . '</td><td>' . esc_html( $r['semantic_group'] ) . '</td><td>' . esc_html( $r['label'] ) . '</td><td>' . esc_html( $r['state'] ) . '</td><td>' . esc_html( number_format_i18n( (float) $r['confidence'] * 100, 1 ) ) . '%</td><td>' . esc_html( $r['updated_at'] ) . '</td>';
            $payload = json_decode( (string) ( $r['payload_json'] ?? '' ), true );
            $conflict = is_array( $payload ) && 'conflicts_with_existing_value' === ( $payload['evidence']['reason'] ?? '' );
            $can_approve = ! $conflict && in_array( $r['state'], array( 'safe', 'review', 'derived' ), true )
                && ( absint( $r['vocabulary_id'] ) > 0 || 0 === strpos( (string) $r['semantic_group'], 'attribute:' ) );
            echo '<td>';
            if ( $can_approve ) {
                echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
                echo '<input type="hidden" name="action" value="tcv_classifier_approve">';
                echo '<input type="hidden" name="proposal_id" value="' . esc_attr( (string) absint( $r['id'] ) ) . '">';
                wp_nonce_field( 'tcv_classifier_approve_' . absint( $r['id'] ) );
                echo '<button type="submit" class="button button-small">' . esc_html__( 'Aprobar y aplicar', 'taxocommerce-vocabulary' ) . '</button></form>';
            } elseif ( $conflict ) {
                echo esc_html__( 'Conflicto con etiqueta actual: revisar manualmente', 'taxocommerce-vocabulary' );
            } elseif ( empty( $r['vocabulary_id'] ) && ! in_array( $r['state'], array( 'applied', 'unresolved' ), true ) ) {
                echo esc_html__( 'Revisar el vocabulario', 'taxocommerce-vocabulary' );
            } else {
                echo '—';
            }
            echo '</td></tr>';
        }
        echo '</tbody></table></div>';

        $novel = TCV_Classifier::unmapped_terms( 25 );
        if ( $novel ) {
            echo '<div class="tcv-panel"><h2>' . esc_html__( 'Conceptos nuevos para normalizar', 'taxocommerce-vocabulary' ) . '</h2>';
            echo '<p>' . esc_html__( 'Agrupación orientativa de las últimas 500 propuestas. Al aprobar se crea únicamente el término maestro; para clasificar productos se vuelve a analizar el catálogo. Nunca se da de alta automáticamente.', 'taxocommerce-vocabulary' ) . '</p>';
            echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Grupo', 'taxocommerce-vocabulary' ) . '</th><th>' . esc_html__( 'Candidato', 'taxocommerce-vocabulary' ) . '</th><th>' . esc_html__( 'Productos', 'taxocommerce-vocabulary' ) . '</th><th>' . esc_html__( 'Decisión', 'taxocommerce-vocabulary' ) . '</th></tr></thead><tbody>';
            foreach ( $novel as $item ) {
                echo '<tr><td>' . esc_html( $item['group'] ) . '</td><td>' . esc_html( $item['label'] ) . '</td><td>' . esc_html( number_format_i18n( $item['count'] ) ) . '</td><td>';
                echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="tcv_create_term">';
                echo '<input type="hidden" name="group" value="' . esc_attr( $item['group'] ) . '"><input type="hidden" name="label" value="' . esc_attr( $item['label'] ) . '">';
                wp_nonce_field( 'tcv_create_term' );
                echo '<button class="button button-small" type="submit">' . esc_html__( 'Incorporar al maestro', 'taxocommerce-vocabulary' ) . '</button></form></td></tr>';
            }
            echo '</tbody></table></div>';
        }

        echo '<div class="tcv-panel"><h2>' . esc_html__( 'Análisis puntual de categoría', 'taxocommerce-vocabulary' ) . '</h2><p>' . esc_html__( 'Para revisar una categoría concreta sin recorrer el catálogo completo.', 'taxocommerce-vocabulary' ) . '</p><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="tcv_analyze_category">';
        wp_nonce_field( 'tcv_analyze_category' );
        echo '<label>' . esc_html__( 'Categoría ID', 'taxocommerce-vocabulary' ) . ' ' . '<input type="number" name="term_id" min="1" required>' . '</label>' . ' ' . '<button class="button">' . esc_html__( 'Analizar categoría', 'taxocommerce-vocabulary' ) . '</button>' . '</form>' . '</div>';

        $cats = TCV_Classifier::recent_category_proposals( 100 );
        echo '<div class="tcv-panel">' . '<h2>' . esc_html__( 'Propuestas recientes de categorías', 'taxocommerce-vocabulary' ) . '</h2>' . '<table class="widefat striped">' . '<thead>' . '<tr>' . '<th>' . esc_html__( 'Categoría', 'taxocommerce-vocabulary' ) . '</th>' . '<th>' . esc_html__( 'Grupo', 'taxocommerce-vocabulary' ) . '</th>' . '<th>' . esc_html__( 'Propuesta', 'taxocommerce-vocabulary' ) . '</th>' . '<th>' . esc_html__( 'Estado', 'taxocommerce-vocabulary' ) . '</th>' . '<th>' . esc_html__( 'Confianza', 'taxocommerce-vocabulary' ) . '</th>' . '</tr>' . '</thead>' . '<tbody>';
        foreach ( $cats as $r ) {
            $term = get_term( absint( $r['term_id'] ), 'product_cat' );
            $name = ( $term && ! is_wp_error( $term ) ) ? $term->name : '#' . $r['term_id'];
            echo '<tr><td>' . esc_html( $name ) . '</td><td>' . esc_html( $r['semantic_group'] ) . '</td><td>' . esc_html( $r['label'] ) . '</td><td>' . esc_html( $r['state'] ) . '</td><td>' . esc_html( number_format_i18n( (float) $r['confidence'] * 100, 1 ) ) . '%</td></tr>';
        }
        echo '</tbody></table></div>';
    }

    private static function io() {
        $labels = wp_nonce_url( admin_url( 'admin-post.php?action=tcv_export_labels' ), 'tcv_export_labels' );
        $attrs = wp_nonce_url( admin_url( 'admin-post.php?action=tcv_export_attributes' ), 'tcv_export_attributes' );

        echo '<div class="tcv-grid">';
        echo '<div class="tcv-panel"><h2>' . esc_html__( 'Etiquetas', 'taxocommerce-vocabulary' ) . '</h2><p>' . esc_html__( 'Exporta/importa términos ROL, TIPO, APLICACIÓN, PLATAFORMA, SUBTIPO y la correspondencia TIPO → ROL.', 'taxocommerce-vocabulary' ) . '</p><a class="button button-primary" href="' . esc_url( $labels ) . '" >' . esc_html__( 'Exportar CSV de etiquetas', 'taxocommerce-vocabulary' ) . '</a>';
        self::render_import_form( 'labels' );
        echo '</div>';

        echo '<div class="tcv-panel"><h2>' . esc_html__( 'Atributos', 'taxocommerce-vocabulary' ) . '</h2><p>' . esc_html__( 'Exporta/importa definiciones de atributos, términos controlados y aliases. No incluye valores asignados a productos.', 'taxocommerce-vocabulary' ) . '</p><a class="button button-primary" href="' . esc_url( $attrs ) . '" >' . esc_html__( 'Exportar CSV de atributos', 'taxocommerce-vocabulary' ) . '</a>';
        self::render_import_form( 'attributes' );
        echo '</div></div>';

        TCV_Portable::render_panel();
    }

    private static function render_import_form( $domain ) {
        ?>
        <form class="tcv-import" method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <input type="hidden" name="action" value="tcv_import">
            <input type="hidden" name="domain" value="<?php echo esc_attr( $domain ); ?>">
            <?php wp_nonce_field( 'tcv_import' ); ?>
            <input type="file" name="csv" accept=".csv,text/csv" required>
            <button class="button"><?php esc_html_e( 'Importar CSV', 'taxocommerce-vocabulary' ); ?></button>
        </form>
        <?php
    }

    // Administrator displays and integrity checks read shared canonical tables live; other modules can change them independently.
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    private static function control() {
        global $wpdb;
        $t = TCV_Schema::tables();
        echo '<div class="tcv-panel">' . '<h2>' . esc_html__( 'Control de integridad', 'taxocommerce-vocabulary' ) . '</h2>' . '<table class="widefat striped">' . '<thead>' . '<tr>' . '<th>' . esc_html__( 'Tabla', 'taxocommerce-vocabulary' ) . '</th>' . '<th>' . esc_html__( 'Estado', 'taxocommerce-vocabulary' ) . '</th>' . '<th>' . esc_html__( 'Filas', 'taxocommerce-vocabulary' ) . '</th>' . '</tr>' . '</thead>' . '<tbody>';
        foreach ( $t as $table ) {
            $exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
            $count = 0;
            if ( $exists === $table ) {
                $count = (int) $wpdb->get_var(
                    $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $table )
                );
            }
            echo '<tr><td><code>' . esc_html( $table ) . '</code></td><td>' . ( $exists === $table ? esc_html__( 'OK', 'taxocommerce-vocabulary' ) : esc_html__( 'Falta', 'taxocommerce-vocabulary' ) ) . '</td><td>' . esc_html( number_format_i18n( $count ) ) . '</td></tr>';
        }
        echo '</tbody></table></div>';
    }

    // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    public static function analyze_filtered() {
        if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Sin permisos.' );
        }
        check_admin_referer( 'tcv_analyze_filtered' );
        $filters = TCV_Classifier::sanitize_filters( array(
            'search'      => isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '',
            'category_id' => isset( $_POST['category_id'] ) ? absint( wp_unslash( $_POST['category_id'] ) ) : 0,
            'coverage'    => isset( $_POST['coverage'] ) ? sanitize_key( wp_unslash( $_POST['coverage'] ) ) : 'missing_any',
            'priority'    => isset( $_POST['priority'] ) ? sanitize_key( wp_unslash( $_POST['priority'] ) ) : 'all',
            'rows'        => isset( $_POST['rows'] ) ? absint( wp_unslash( $_POST['rows'] ) ) : 25,
        ) );
        $report = TCV_Classifier::analyze_filtered_products( $filters );
        $args = array_merge( array( 'page'=>'taxocommerce-vocabulary','tab'=>'classifier','tcv_status'=>'success','tcv_message'=>rawurlencode( sprintf( 'Análisis completado: %d productos procesados.', absint( $report['processed'] ?? 0 ) ) ) ), $filters );
        wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
        exit;
    }

    public static function export_classifier_json() {
        if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Sin permisos.' );
        }
        check_admin_referer( 'tcv_export_classifier_json' );
        $report = TCV_Classifier::last_report();
        if ( ! $report ) {
            wp_die( 'No existe un análisis del Clasificador para exportar.', '', array( 'response'=>404 ) );
        }
        nocache_headers();
        header( 'X-Content-Type-Options: nosniff' );
        header( 'Content-Type: application/json; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="taxocommerce_clasificador.json"' );
        echo wp_json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE );
        exit;
    }

    public static function analyze_product() {
        if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Sin permisos.' );
        }
        check_admin_referer( 'tcv_analyze_product' );
        $id = absint( $_POST['product_id'] ?? 0 );
        $r = TCV_Classifier::analyze_product( $id );
        $msg = is_wp_error( $r ) ? $r->get_error_message() : sprintf( 'Producto #%d analizado: %d etiquetas y %d atributos propuestos.', $id, count( $r['labels'] ), count( $r['attributes'] ) );
        wp_safe_redirect( add_query_arg( array( 'page'=>'taxocommerce-vocabulary','tab'=>'classifier','tcv_status'=>is_wp_error( $r )?'error':'success','tcv_message'=>rawurlencode( $msg ) ), admin_url( 'admin.php' ) ) );
        exit;
    }

    public static function analyze_category() {
        if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Sin permisos.' );
        }
        check_admin_referer( 'tcv_analyze_category' );
        $id = absint( $_POST['term_id'] ?? 0 );
        $r = TCV_Classifier::analyze_category( $id );
        $msg = is_wp_error( $r ) ? $r->get_error_message() : sprintf( 'Categoría #%d analizada: %d etiquetas propuestas.', $id, count( $r['labels'] ) );
        wp_safe_redirect( add_query_arg( array( 'page'=>'taxocommerce-vocabulary','tab'=>'classifier','tcv_status'=>is_wp_error( $r )?'error':'success','tcv_message'=>rawurlencode( $msg ) ), admin_url( 'admin.php' ) ) );
        exit;
    }

    // Administrator displays and integrity checks read shared canonical tables live; other modules can change them independently.
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    public static function create_term() {
        if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Sin permisos.' );
        }
        check_admin_referer( 'tcv_create_term' );
        global $wpdb;
        $t = TCV_Schema::tables();
        $g = sanitize_key( $_POST['group'] ?? '' );
        $label = sanitize_text_field( wp_unslash( $_POST['label'] ?? '' ) );
        if ( in_array( $g, TCV_Classifier::GROUPS, true ) && $label ) {
            $slug = sanitize_title( $label );
            $wpdb->query( $wpdb->prepare( "INSERT INTO %i (semantic_group,slug,label,source,active) VALUES (%s,%s,%s,'manual',1) ON DUPLICATE KEY UPDATE label=VALUES(label),active=1,source='manual'", $t['vocabulary'], $g, $slug, $label ) );
        }
        wp_safe_redirect( add_query_arg( array( 'page'=>'taxocommerce-vocabulary','tab'=>'labels' ), admin_url( 'admin.php' ) ) );
        exit;
    }

    // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
}
