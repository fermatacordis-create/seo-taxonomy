<?php

defined( 'ABSPATH' ) || exit;

final class TCV_Classifier {
    const VERSION = '0.3.9';
    const GROUPS = array( 'tipo', 'rol', 'aplicacion', 'plataforma', 'subtipo' );

    private static function normalize( $text ) {
        $text = html_entity_decode( (string) $text, ENT_QUOTES, 'UTF-8' );
        $text = wp_strip_all_tags( $text );
        $text = remove_accents( mb_strtolower( $text, 'UTF-8' ) );
        $text = preg_replace( '/[^a-z0-9º°+\/._-]+/u', ' ', $text );
        return trim( preg_replace( '/\s+/u', ' ', $text ) );
    }

    /**
     * Optional read-only access to the original canonical supplier catalogue.
     * Fresh WooCommerce stores work without the table or Suppliers plugin.
     */
    private static function supplier_source_context( $product_id ) {
        global $wpdb;
        static $table_exists = null;
        static $rows = array();

        $product_id = absint( $product_id );
        if ( array_key_exists( $product_id, $rows ) ) {
            return $rows[ $product_id ];
        }
        $table = $wpdb->prefix . 'seo_proveedores_productos';
        // WordPress migrations may still hold the canonical supplier table.
        // Live direct reads are necessary; this module never writes to it.
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        if ( null === $table_exists ) {
            $found = $wpdb->get_var(
                $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) )
            );
            $table_exists = $found === $table;
        }
        if ( ! $table_exists ) {
            return $rows[ $product_id ] = '';
        }
        $reference = absint( get_post_meta( $product_id, '_seo_proveedor_catalogo_id', true ) );
        if ( $reference ) {
            $row = $wpdb->get_row(
                $wpdb->prepare( 'SELECT * FROM %i WHERE id=%d LIMIT 1', $table, $reference ),
                ARRAY_A
            );
        } else {
            $row = $wpdb->get_row(
                $wpdb->prepare(
                    'SELECT * FROM %i WHERE object_id=%d ORDER BY id DESC LIMIT 1',
                    $table,
                    $product_id
                ),
                ARRAY_A
            );
        }
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        if ( ! is_array( $row ) ) {
            return $rows[ $product_id ] = '';
        }
        $parts = array();
        foreach ( array( 'nombre', 'descripcion', 'categoria_proveedor', 'marca',
            'fabricante', 'mpn', 'sku' ) as $field ) {
            if ( isset( $row[ $field ] ) && is_scalar( $row[ $field ] ) ) {
                $parts[] = (string) $row[ $field ];
            }
        }
        return $rows[ $product_id ] = implode( ' ', $parts );
    }

    private static function product_context( $product_id ) {
        $post = get_post( $product_id );
        if ( ! $post || 'product' !== $post->post_type ) {
            return new WP_Error( 'tcv_product_missing', __( 'Product not found.', 'taxocommerce-vocabulary' ) );
        }
        $cats = wp_get_post_terms( $product_id, 'product_cat', array( 'fields' => 'names' ) );
        if ( is_wp_error( $cats ) ) {
            $cats = array();
        }
        $tags = array();
        if ( taxonomy_exists( 'product_tag' ) ) {
            $tags = wp_get_post_terms( $product_id, 'product_tag', array( 'fields' => 'names' ) );
            if ( is_wp_error( $tags ) ) {
                $tags = array();
            }
        }
        $brands = array();
        // Use installed WordPress/WooCommerce brand taxonomies, without a
        // dependency on a brand or supplier plugin.
        foreach ( array( 'product_brand', 'pwb-brand', 'yith_product_brand', 'pa_marca', 'pa_brand' ) as $taxonomy ) {
            if ( ! taxonomy_exists( $taxonomy ) ) {
                continue;
            }
            $values = wp_get_post_terms( $product_id, $taxonomy, array( 'fields' => 'names' ) );
            if ( ! is_wp_error( $values ) ) {
                $brands = array_merge( $brands, (array) $values );
            }
        }
        foreach ( array( '_brand', '_manufacturer', '_seo_fabricante' ) as $key ) {
            $value = get_post_meta( $product_id, $key, true );
            if ( is_scalar( $value ) && '' !== trim( (string) $value ) ) {
                $brands[] = (string) $value;
            }
        }
        $supplier = array();
        foreach ( array( '_supplier_name', '_supplier_description', '_supplier_category',
            '_seo_proveedor', '_seo_marca_proveedor' ) as $key ) {
            $value = get_post_meta( $product_id, $key, true );
            if ( is_scalar( $value ) && '' !== trim( (string) $value ) ) {
                $supplier[] = (string) $value;
            }
        }
        $supplier[] = self::supplier_source_context( $product_id );

        // Native WooCommerce attributes are read as evidence, never modified.
        // New installations without WooCommerce attribute objects still work.
        $native_attributes = array();
        $wc_product = function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : false;
        if ( $wc_product && method_exists( $wc_product, 'get_attributes' ) ) {
            foreach ( (array) $wc_product->get_attributes() as $attribute ) {
                if ( ! is_object( $attribute ) || ! method_exists( $attribute, 'get_name' )
                    || ! method_exists( $attribute, 'get_options' ) || ! method_exists( $attribute, 'is_taxonomy' ) ) {
                    continue;
                }
                $name = (string) $attribute->get_name();
                $values = $attribute->is_taxonomy() && taxonomy_exists( $name )
                    ? wp_get_post_terms( $product_id, $name, array( 'fields' => 'names' ) )
                    : $attribute->get_options();
                if ( is_wp_error( $values ) || ! is_array( $values ) ) {
                    continue;
                }
                $texts = array();
                foreach ( array_slice( $values, 0, 12 ) as $value ) {
                    if ( is_scalar( $value ) && '' !== trim( (string) $value ) ) {
                        $texts[] = mb_substr( sanitize_text_field( (string) $value ), 0, 150, 'UTF-8' );
                    }
                }
                if ( $texts ) {
                    $label = function_exists( 'wc_attribute_label' ) ? wc_attribute_label( $name, $wc_product ) : $name;
                    $native_attributes[] = sanitize_text_field( $label ) . ': ' . implode( ', ', $texts );
                }
                if ( count( $native_attributes ) >= 40 ) {
                    break;
                }
            }
        }
        $values = array(
            'title' => $post->post_title,
            'excerpt' => $post->post_excerpt,
            'content' => $post->post_content,
            'categories' => implode( ' ', $cats ),
            'tags' => implode( ' ', $tags ),
            'brands' => implode( ' ', array_unique( $brands ) ),
            'sku' => (string) get_post_meta( $product_id, '_sku', true ),
            'supplier' => implode( ' ', $supplier ),
            'attributes' => implode( '; ', $native_attributes ),
        );
        // Optional read-only integration for any supplier/catalog provider;
        // no vendor, plugin, or external service is required.
        $values = apply_filters( 'taxocommerce_vocabulary_classifier_sources', $values, $product_id );
        return TCV_Extractor::context( is_array( $values ) ? $values : array() );
    }

    // Classifier must read and update shared canonical vocabulary and proposal tables immediately; external writers cannot invalidate a local cache.
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    private static function current_groups( $product_id ) {
        global $wpdb;
        $t = TCV_Schema::tables();
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT v.semantic_group,v.id,v.label FROM %i ov INNER JOIN %i v ON v.id=ov.vocabulary_id WHERE ov.object_type='product' AND ov.object_id=%d AND ov.status=1 AND v.active=1", $t['objects'], $t['vocabulary'],
                $product_id
            ),
            ARRAY_A
        );
        $out = array_fill_keys( self::GROUPS, array() );
        foreach ( (array) $rows as $row ) {
            $g = sanitize_key( $row['semantic_group'] );
            if ( isset( $out[ $g ] ) ) {
                $out[ $g ][] = absint( $row['id'] );
            }
        }
        return $out;
    }

    // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    private static function score_term( $term, $ctx ) {
        $matched = TCV_Extractor::score( $term, $ctx );
        return (float) $matched['score'];
    }

    // Classifier must read and update shared canonical vocabulary and proposal tables immediately; external writers cannot invalidate a local cache.
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    private static function vocabulary_candidates( $group, $ctx, $limit = 4 ) {
        global $wpdb;
        $t = TCV_Schema::tables();
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id,slug,label FROM %i WHERE semantic_group=%s AND active=1 ORDER BY label ASC", $t['vocabulary'],
                $group
            ),
            ARRAY_A
        );
        return TCV_Extractor::rank( $rows, $ctx, $limit );
    }

    // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    // Classifier must read and update shared canonical vocabulary and proposal tables immediately; external writers cannot invalidate a local cache.
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    private static function derive_role( $type_id ) {
        global $wpdb;
        $t = TCV_Schema::tables();
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT rv.id,rv.label FROM %i m INNER JOIN %i rv ON rv.id=m.role_vocabulary_id AND rv.semantic_group='rol' AND rv.active=1 WHERE m.type_vocabulary_id=%d AND m.active=1 LIMIT 1", $t['type_role'], $t['vocabulary'],
                $type_id
            ),
            ARRAY_A
        );
    }

    // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    // Classifier must read and update shared canonical vocabulary and proposal tables immediately; external writers cannot invalidate a local cache.
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    private static function attribute_candidates( $ctx ) {
        global $wpdb;
        $tables = TCV_Schema::tables();
        // Canonical, local tables: a live read is required to see the
        // vocabulary updated by other ordinary WordPress administrators.
        $attrs = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT id,slug,nombre,tipo,unidad_base FROM %i WHERE activo=1 ORDER BY orden,nombre',
                $tables['attributes']
            ),
            ARRAY_A
        );
        $terms = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT id,atributo_id,slug,nombre FROM %i WHERE activo=1',
                $tables['terms']
            ),
            ARRAY_A
        );
        $aliases = $wpdb->get_results(
            $wpdb->prepare( 'SELECT atributo_id,termino_id,alias FROM %i', $tables['aliases'] ),
            ARRAY_A
        );
        $term_map = array();
        $alias_map = array();
        foreach ( (array) $terms as $term ) {
            $term_map[ absint( $term['atributo_id'] ) ][] = array(
                'id' => absint( $term['id'] ),
                'label' => (string) $term['nombre'],
            );
        }
        foreach ( (array) $aliases as $alias ) {
            $id = absint( $alias['atributo_id'] );
            $term_id = absint( $alias['termino_id'] );
            if ( $id && $term_id && '' !== trim( (string) $alias['alias'] ) ) {
                $alias_map[ $id ][ $term_id ][] = (string) $alias['alias'];
            }
        }
        $found = array();
        foreach ( (array) $attrs as $attribute ) {
            $id = absint( $attribute['id'] );
            if ( 'termino' === $attribute['tipo'] ) {
                $proposal = TCV_Extractor::controlled(
                    $attribute,
                    isset( $term_map[ $id ] ) ? $term_map[ $id ] : array(),
                    isset( $alias_map[ $id ] ) ? $alias_map[ $id ] : array(),
                    $ctx
                );
            } elseif ( in_array( $attribute['tipo'], array( 'numero', 'rango' ), true ) ) {
                $proposal = TCV_Extractor::numeric( $attribute, $ctx );
            } else {
                $proposal = null;
            }
            if ( $proposal ) {
                $found[] = $proposal;
            }
        }
        return array_slice( $found, 0, 20 );
    }


    // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

    /**
     * Small, per-request cached category profiles from existing canonical
     * assignments. A category only adds a bounded bonus to a textual match;
     * it can never impose a tag without corroborating product evidence.
     */
    private static function category_profile( $category_id ) {
        static $cache = array();
        $category_id = absint( $category_id );
        if ( isset( $cache[ $category_id ] ) ) {
            return $cache[ $category_id ];
        }
        global $wpdb;
        $tables = TCV_Schema::tables();
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Own canonical assignments and indexed WP category relations; result cached for the current block.
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT v.semantic_group, ov.vocabulary_id, COUNT(DISTINCT ov.object_id) AS supporters
             FROM %i ov INNER JOIN %i v ON v.id=ov.vocabulary_id
             INNER JOIN %i tr ON tr.object_id=ov.object_id
             INNER JOIN %i tt ON tt.term_taxonomy_id=tr.term_taxonomy_id
             WHERE tt.taxonomy='product_cat' AND tt.term_id=%d
             AND ov.object_type='product' AND ov.status=1 AND ov.source<>%s
             AND v.active=1 AND v.semantic_group IN ('tipo','aplicacion','plataforma','subtipo')
             GROUP BY v.semantic_group, ov.vocabulary_id",
            $tables['objects'], $tables['vocabulary'], $wpdb->term_relationships,
            $wpdb->term_taxonomy, $category_id, 'classifier_auto'
        ), ARRAY_A );
        $by_group = array();
        foreach ( (array) $rows as $row ) {
            $group = sanitize_key( $row['semantic_group'] );
            $by_group[ $group ][] = array( 'id' => absint( $row['vocabulary_id'] ), 'n' => absint( $row['supporters'] ) );
        }
        $result = array();
        foreach ( $by_group as $group => $counts ) {
            usort( $counts, static function ( $a, $b ) { return $b['n'] <=> $a['n']; } );
            $total = array_sum( array_column( $counts, 'n' ) );
            if ( $counts[0]['n'] >= 5 && $total > 0 && ( $counts[0]['n'] / $total ) >= 0.72 ) {
                $result[ $group ] = $counts[0];
            }
        }
        $cache[ $category_id ] = $result;
        return $result;
    }

    private static function improve_with_learned_profiles( $product_id, $group, $candidates ) {
        if ( ! $candidates ) {
            return $candidates;
        }
        $categories = wp_get_post_terms( $product_id, 'product_cat', array( 'fields' => 'ids' ) );
        if ( is_wp_error( $categories ) || ! is_array( $categories ) ) {
            return $candidates;
        }
        foreach ( array_slice( $categories, 0, 3 ) as $category_id ) {
            $profile = self::category_profile( $category_id );
            if ( empty( $profile[ $group ] ) ) {
                continue;
            }
            foreach ( $candidates as &$candidate ) {
                if ( absint( $candidate['id'] ) !== $profile[ $group ]['id'] || (float) $candidate['score'] < 0.50 ) {
                    continue;
                }
                $candidate['score'] = round( min( 1.0, (float) $candidate['score'] + 0.05 ), 4 );
                $candidate['evidence'][] = array(
                    'source' => 'reviewed_category_pattern',
                    'category_id' => absint( $category_id ),
                    'supporters' => $profile[ $group ]['n'],
                );
                break;
            }
            unset( $candidate );
        }
        usort( $candidates, static function ( $a, $b ) { return $b['score'] <=> $a['score']; } );
        return $candidates;
    }

    public static function analyze_product( $product_id ) {
        $product_id = absint( $product_id );
        $ctx = self::product_context( $product_id );
        if ( is_wp_error( $ctx ) ) {
            return $ctx;
        }
        $current = self::current_groups( $product_id );
        $result = array( 'product_id' => $product_id, 'labels' => array(), 'attributes' => array() );
        $groups = array( 'tipo', 'aplicacion', 'plataforma', 'subtipo' );
        $type_choice = null;
        if ( 1 === count( $current['tipo'] ) ) {
            // A reviewed canonical type may support a missing derived role.
            $type_choice = array( 'vocabulary_id' => $current['tipo'][0], 'confidence' => 1.0, 'label' => '' );
        }
        foreach ( $groups as $group ) {
            $candidates = self::improve_with_learned_profiles(
                $product_id, $group, self::vocabulary_candidates( $group, $ctx )
            );
            if ( ! empty( $current[ $group ] ) ) {
                // Review conflicts without replacing any pre-existing assignment.
                if ( $candidates && ! in_array( absint( $candidates[0]['id'] ), $current[ $group ], true )
                    && (float) $candidates[0]['score'] >= 0.94
                    && ( (float) $candidates[0]['score'] - (float) ( $candidates[1]['score'] ?? 0 ) ) >= 0.20 ) {
                    self::persist_proposal( $product_id, $group, array(
                        'group' => $group, 'vocabulary_id' => absint( $candidates[0]['id'] ),
                        'label' => $candidates[0]['label'], 'confidence' => (float) $candidates[0]['score'],
                        'state' => 'review', 'evidence' => array( 'reason' => 'conflicts_with_existing_value', 'matches' => $candidates ),
                    ) );
                }
                continue;
            }
            if ( ! $candidates || (float) $candidates[0]['score'] < 0.52 ) {
                // Legacy discovery is review-only. It does not insert terms
                // into WooCommerce or the canonical vocabulary.
                $new_term = TCV_Extractor::discover( $group, $ctx, $candidates );
                if ( $new_term ) {
                    $result['labels'][] = $new_term;
                    self::persist_proposal( $product_id, $group, $new_term );
                }
                continue;
            }
            $top = $candidates[0];
            $second = isset( $candidates[1] ) ? (float) $candidates[1]['score'] : 0.0;
            $state = ( $top['score'] >= 0.82 && ( $top['score'] - $second ) >= 0.12 ) ? 'safe' : 'review';
            $proposal = array( 'group' => $group, 'vocabulary_id' => absint( $top['id'] ), 'label' => $top['label'], 'confidence' => (float) $top['score'], 'state' => $state, 'evidence' => $candidates );
            $result['labels'][] = $proposal;
            self::persist_proposal( $product_id, $group, $proposal );
            if ( 'tipo' === $group && 'safe' === $state ) {
                $type_choice = $proposal;
            }
        }
        if ( empty( $current['rol'] ) && $type_choice ) {
            $role = self::derive_role( $type_choice['vocabulary_id'] );
            if ( $role ) {
                $proposal = array( 'group' => 'rol', 'vocabulary_id' => absint( $role['id'] ), 'label' => $role['label'], 'confidence' => $type_choice['confidence'], 'state' => 'derived', 'evidence' => array( 'derived_from_type' => $type_choice['label'] ) );
                $result['labels'][] = $proposal;
                self::persist_proposal( $product_id, 'rol', $proposal );
            }
        }

        $result['attributes'] = self::attribute_candidates( $ctx );
        // The classifier must never propose the same technical attribute twice.
        global $wpdb;
        $tables = TCV_Schema::tables();
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Own canonical product attribute assignments.
        $assigned = array_map( 'absint', (array) $wpdb->get_col( $wpdb->prepare(
            'SELECT DISTINCT atributo_id FROM %i WHERE product_id=%d', $tables['product_att'], $product_id
        ) ) );
        $result['attributes'] = array_values( array_filter( $result['attributes'], static function ( $item ) use ( $assigned ) {
            return ! in_array( absint( $item['attribute_id'] ), $assigned, true );
        } ) );
        foreach ( $result['attributes'] as $proposal ) {
            self::persist_proposal( $product_id, 'attribute:' . absint( $proposal['attribute_id'] ), array(
                'group' => 'attribute', 'vocabulary_id' => null, 'label' => $proposal['attribute'] . ': ' . $proposal['value'], 'confidence' => $proposal['confidence'], 'state' => 'review', 'evidence' => $proposal,
            ) );
        }
        return $result;
    }

    // Classifier must read and update shared canonical vocabulary and proposal tables immediately; external writers cannot invalidate a local cache.
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    private static function persist_proposal( $product_id, $group, $proposal ) {
        global $wpdb;
        $t = TCV_Schema::tables();
        $payload = wp_json_encode( $proposal, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
        $wpdb->query(
            $wpdb->prepare(
                "INSERT INTO %i (product_id,semantic_group,state,vocabulary_id,label,confidence,payload_json,context_hash,classifier_version,active,updated_at) VALUES (%d,%s,%s,%d,%s,%f,%s,'',%s,1,%s) ON DUPLICATE KEY UPDATE state=VALUES(state),vocabulary_id=VALUES(vocabulary_id),label=VALUES(label),confidence=VALUES(confidence),payload_json=VALUES(payload_json),classifier_version=VALUES(classifier_version),active=1,updated_at=VALUES(updated_at)", $t['proposals'],
                $product_id,
                $group,
                sanitize_key( $proposal['state'] ),
                empty( $proposal['vocabulary_id'] ) ? 0 : absint( $proposal['vocabulary_id'] ),
                sanitize_text_field( $proposal['label'] ),
                (float) $proposal['confidence'],
                $payload,
                self::VERSION,
                current_time( 'mysql', true )
            )
        );
    }

    // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

    /**
     * Assign only an active existing canonical term to a product with no value
     * in that group. Never replace a manually/imported assignment.
     *
     * @return true|WP_Error
     */
    private static function assign_canonical( $product_id, $group, $vocabulary_id, $confidence, $origin ) {
        global $wpdb;
        $product_id = absint( $product_id );
        $vocabulary_id = absint( $vocabulary_id );
        $group = sanitize_key( $group );
        $product = get_post( $product_id );
        if ( ! $product || 'product' !== $product->post_type || ! in_array( $group, self::GROUPS, true ) || ! $vocabulary_id ) {
            return new WP_Error( 'tcv_invalid_assignment', __( 'Asignación canónica no válida.', 'taxocommerce-vocabulary' ) );
        }
        $t = TCV_Schema::tables();
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Canonical vocabulary and assignments are authoritative and must be read immediately.
        $valid = $wpdb->get_var( $wpdb->prepare(
            'SELECT id FROM %i WHERE id=%d AND semantic_group=%s AND active=1',
            $t['vocabulary'], $vocabulary_id, $group
        ) );
        if ( ! $valid ) {
            return new WP_Error( 'tcv_missing_canonical_term', __( 'El término canónico ya no está activo.', 'taxocommerce-vocabulary' ) );
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Guard against overwriting an existing reviewed product label.
        $existing = $wpdb->get_var( $wpdb->prepare(
            "SELECT ov.id FROM %i ov INNER JOIN %i v ON v.id=ov.vocabulary_id
             WHERE ov.object_type='product' AND ov.object_id=%d AND ov.status=1 AND v.semantic_group=%s LIMIT 1",
            $t['objects'], $t['vocabulary'], $product_id, $group
        ) );
        if ( $existing ) {
            return new WP_Error( 'tcv_group_filled', __( 'El producto ya tiene una etiqueta de ese grupo; no se sobrescribe.', 'taxocommerce-vocabulary' ) );
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Authorised insertion into own canonical assignment table.
        $inserted = $wpdb->insert( $t['objects'], array(
            'object_type' => 'product', 'object_id' => $product_id, 'vocabulary_id' => $vocabulary_id,
            'source' => $origin, 'confidence' => max( 0, min( 1, (float) $confidence ) ),
            'status' => 1,
        ), array( '%s', '%d', '%d', '%s', '%f', '%d' ) );
        if ( false === $inserted ) {
            return new WP_Error( 'tcv_assignment_failed', __( 'No se pudo guardar la asignación canónica.', 'taxocommerce-vocabulary' ) );
        }
        return true;
    }

    /**
     * Optional automatic application is stricter than the review/safe display
     * threshold and only writes existing canonical labels, never attributes.
     *
     * @return int Number of new assignments.
     */
    public static function apply_safe_labels( $product_id, $labels ) {
        $added = 0;
        foreach ( (array) $labels as $proposal ) {
            if ( ! is_array( $proposal ) || ( $proposal['state'] ?? '' ) !== 'safe'
                || (float) ( $proposal['confidence'] ?? 0 ) < 0.90 || empty( $proposal['vocabulary_id'] ) ) {
                continue;
            }
            $result = self::assign_canonical(
                $product_id, $proposal['group'] ?? '', $proposal['vocabulary_id'],
                $proposal['confidence'], 'classifier_auto'
            );
            if ( ! is_wp_error( $result ) ) {
                ++$added;
                global $wpdb;
                $table = TCV_Schema::tables()['proposals'];
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Mark successful own proposal as applied without deleting its audit evidence.
                $wpdb->update(
                    $table, array( 'state' => 'applied', 'updated_at' => current_time( 'mysql', true ) ),
                    array( 'product_id' => absint( $product_id ), 'semantic_group' => sanitize_key( $proposal['group'] ) ),
                    array( '%s', '%s' ), array( '%d', '%s' )
                );
            }
        }
        return $added;
    }

    /**
     * Apply one administrator-reviewed proposal, verifying master identifiers
     * and preserving the existing assignment if a value is already present.
     *
     * @return true|WP_Error
     */
    public static function apply_proposal( $proposal_id ) {
        global $wpdb;
        $t = TCV_Schema::tables();
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Immediate read of our own review queue.
        $row = $wpdb->get_row( $wpdb->prepare(
            'SELECT * FROM %i WHERE id=%d AND active=1', $t['proposals'], absint( $proposal_id )
        ), ARRAY_A );
        if ( ! $row || ! in_array( $row['state'], array( 'safe', 'review', 'derived' ), true ) ) {
            return new WP_Error( 'tcv_proposal_not_reviewable', __( 'La propuesta no está disponible para aprobar.', 'taxocommerce-vocabulary' ) );
        }
        $product_id = absint( $row['product_id'] );
        $product = get_post( $product_id );
        if ( ! $product || 'product' !== $product->post_type ) {
            return new WP_Error( 'tcv_product_missing', __( 'El producto ya no existe.', 'taxocommerce-vocabulary' ) );
        }
        $group = (string) $row['semantic_group'];
        if ( in_array( $group, self::GROUPS, true ) ) {
            $result = self::assign_canonical(
                $product_id, $group, absint( $row['vocabulary_id'] ), (float) $row['confidence'], 'classifier_reviewed'
            );
            if ( is_wp_error( $result ) ) {
                return $result;
            }
        } elseif ( 0 === strpos( $group, 'attribute:' ) ) {
            $attribute_id = absint( substr( $group, 10 ) );
            $payload = json_decode( (string) $row['payload_json'], true );
            $evidence = is_array( $payload ) && is_array( $payload['evidence'] ?? null ) ? $payload['evidence'] : array();
            if ( ! $attribute_id || absint( $evidence['attribute_id'] ?? 0 ) !== $attribute_id ) {
                return new WP_Error( 'tcv_invalid_attribute', __( 'La propuesta de atributo no tiene evidencia válida.', 'taxocommerce-vocabulary' ) );
            }
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Own canonical attribute master.
            $attribute = $wpdb->get_row( $wpdb->prepare(
                'SELECT id,tipo FROM %i WHERE id=%d AND activo=1', $t['attributes'], $attribute_id
            ), ARRAY_A );
            if ( ! $attribute ) {
                return new WP_Error( 'tcv_attribute_missing', __( 'El atributo canónico ya no está activo.', 'taxocommerce-vocabulary' ) );
            }
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Never overwrite an already recorded technical attribute.
            $existing = $wpdb->get_var( $wpdb->prepare(
                'SELECT id FROM %i WHERE product_id=%d AND atributo_id=%d LIMIT 1',
                $t['product_att'], $product_id, $attribute_id
            ) );
            if ( $existing ) {
                return new WP_Error( 'tcv_attribute_exists', __( 'El producto ya tiene ese atributo; no se sobrescribe.', 'taxocommerce-vocabulary' ) );
            }
            $data = array( 'product_id' => $product_id, 'atributo_id' => $attribute_id );
            $formats = array( '%d', '%d' );
            if ( 'termino' === $attribute['tipo'] ) {
                $term_id = absint( $evidence['term_id'] ?? 0 );
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Ensure that the exact controlled term belongs to the active attribute.
                $term = $wpdb->get_var( $wpdb->prepare(
                    'SELECT id FROM %i WHERE id=%d AND atributo_id=%d AND activo=1',
                    $t['terms'], $term_id, $attribute_id
                ) );
                if ( ! $term ) {
                    return new WP_Error( 'tcv_term_missing', __( 'El valor controlado ya no existe.', 'taxocommerce-vocabulary' ) );
                }
                $data['termino_id'] = $term_id;
                $formats[] = '%d';
            } elseif ( in_array( $attribute['tipo'], array( 'numero', 'rango' ), true ) ) {
                if ( ! isset( $evidence['value'] ) || ! is_numeric( $evidence['value'] ) ) {
                    return new WP_Error( 'tcv_numeric_invalid', __( 'El valor numérico no es válido.', 'taxocommerce-vocabulary' ) );
                }
                $data['valor_numero'] = (float) $evidence['value'];
                $data['unidad'] = mb_substr( sanitize_text_field( $evidence['unit'] ?? '' ), 0, 30, 'UTF-8' );
                $formats[] = '%f';
                $formats[] = '%s';
            } else {
                return new WP_Error( 'tcv_attribute_unsupported', __( 'Este tipo de atributo requiere revisión manual.', 'taxocommerce-vocabulary' ) );
            }
            $data['valor_original'] = sanitize_text_field( (string) ( $evidence['value'] ?? '' ) );
            $formats[] = '%s';
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Explicit approval writes only to the own canonical attribute table.
            if ( false === $wpdb->insert( $t['product_att'], $data, $formats ) ) {
                return new WP_Error( 'tcv_attribute_insert_failed', __( 'No se pudo guardar el atributo.', 'taxocommerce-vocabulary' ) );
            }
        } else {
            return new WP_Error( 'tcv_unrecognized_group', __( 'La propuesta no tiene un grupo válido.', 'taxocommerce-vocabulary' ) );
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Keep the original evidence as an audit trail.
        $wpdb->update( $t['proposals'], array(
            'state' => 'applied', 'updated_at' => current_time( 'mysql', true )
        ), array( 'id' => absint( $proposal_id ) ), array( '%s', '%s' ), array( '%d' ) );
        return true;
    }

    private static function category_context( $term_id ) {
        $term = get_term( $term_id, 'product_cat' );
        if ( ! $term || is_wp_error( $term ) ) {
            return new WP_Error( 'tcv_category_missing', 'Categoría no encontrada.' );
        }
        $ids = get_posts( array(
            'post_type' => 'product', 'post_status' => 'publish', 'fields' => 'ids',
            'posts_per_page' => 50, 'orderby' => 'ID', 'order' => 'DESC',
            // Bounded to 50 IDs; the taxonomy relationship is required to analyse this category.
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
            'tax_query' => array( array( 'taxonomy' => 'product_cat', 'field' => 'term_id', 'terms' => array( absint( $term_id ) ) ) ),
        ) );
        $sample = array();
        foreach ( $ids as $id ) {
            $p = get_post( $id );
            if ( $p ) {
                $sample[] = $p->post_title . ' ' . $p->post_excerpt;
            }
        }
        return TCV_Extractor::context( array(
            'title' => (string) $term->name,
            'excerpt' => (string) $term->description,
            'content' => implode( ' ', $sample ),
            'categories' => '',
        ) );
    }

    // Classifier must read and update shared canonical vocabulary and proposal tables immediately; external writers cannot invalidate a local cache.
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    public static function analyze_category( $term_id ) {
        global $wpdb;
        $term_id = absint( $term_id );
        $ctx = self::category_context( $term_id );
        if ( is_wp_error( $ctx ) ) {
            return $ctx;
        }
        $t = TCV_Schema::tables();
        $current_rows = $wpdb->get_col( $wpdb->prepare(
            "SELECT vocabulary_id FROM %i WHERE object_type='product_cat' AND object_id=%d AND status=1", $t['objects'],
            $term_id
        ) );
        $current = array_map( 'absint', (array) $current_rows );
        $result = array( 'term_id' => $term_id, 'labels' => array() );
        foreach ( array( 'tipo','aplicacion','plataforma','subtipo' ) as $group ) {
            $candidates = self::vocabulary_candidates( $group, $ctx );
            if ( ! $candidates ) {
                continue;
            }
            $top = $candidates[0];
            if ( in_array( absint( $top['id'] ), $current, true ) || (float) $top['score'] < 0.52 ) {
                continue;
            }
            $second = isset( $candidates[1] ) ? (float) $candidates[1]['score'] : 0.0;
            $state = ( $top['score'] >= 0.82 && ( $top['score'] - $second ) >= 0.12 ) ? 'safe' : 'review';
            $proposal = array( 'group'=>$group, 'vocabulary_id'=>absint($top['id']), 'label'=>$top['label'], 'confidence'=>(float)$top['score'], 'state'=>$state, 'evidence'=>$candidates );
            $result['labels'][] = $proposal;
            self::persist_category_proposal( $term_id, $group, $proposal );
            if ( 'tipo' === $group ) {
                $role = self::derive_role( absint( $top['id'] ) );
                if ( $role ) {
                    $rp = array( 'group'=>'rol', 'vocabulary_id'=>absint($role['id']), 'label'=>$role['label'], 'confidence'=>(float)$top['score'], 'state'=>'derived', 'evidence'=>array('derived_from_type'=>$top['label']) );
                    $result['labels'][] = $rp;
                    self::persist_category_proposal( $term_id, 'rol', $rp );
                }
            }
        }
        return $result;
    }

    // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    // Classifier must read and update shared canonical vocabulary and proposal tables immediately; external writers cannot invalidate a local cache.
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    private static function persist_category_proposal( $term_id, $group, $proposal ) {
        global $wpdb;
        $t = TCV_Schema::tables();
        $wpdb->query( $wpdb->prepare(
            "INSERT INTO %i (term_id,semantic_group,state,vocabulary_id,label,confidence,payload_json,classifier_version,active,updated_at) VALUES (%d,%s,%s,%d,%s,%f,%s,%s,1,%s) ON DUPLICATE KEY UPDATE state=VALUES(state),vocabulary_id=VALUES(vocabulary_id),label=VALUES(label),confidence=VALUES(confidence),payload_json=VALUES(payload_json),classifier_version=VALUES(classifier_version),active=1,updated_at=VALUES(updated_at)", $t['cat_proposals'],
            $term_id,
            $group,
            sanitize_key( $proposal['state'] ),
            empty( $proposal['vocabulary_id'] ) ? 0 : absint( $proposal['vocabulary_id'] ),
            sanitize_text_field( $proposal['label'] ),
            (float) $proposal['confidence'],
            wp_json_encode( $proposal, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
            self::VERSION,
            current_time( 'mysql', true )
        ) );
    }

    // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    public static function sanitize_filters( $filters ) {
        $coverage_allowed = array( 'missing_any','without_any','without_type','without_role','without_application','without_platform','without_subtype' );
        $priority_allowed = array( 'all','p1','p2','p3','p4','p5' );
        $coverage = sanitize_key( (string) ( $filters['coverage'] ?? 'missing_any' ) );
        $priority = sanitize_key( (string) ( $filters['priority'] ?? 'all' ) );
        $rows = absint( $filters['rows'] ?? 25 );
        if ( ! in_array( $rows, array( 10,25,50,100 ), true ) ) {
            $rows = 25;
        }
        return array(
            'search'      => sanitize_text_field( (string) ( $filters['search'] ?? '' ) ),
            'category_id' => absint( $filters['category_id'] ?? 0 ),
            'coverage'    => in_array( $coverage, $coverage_allowed, true ) ? $coverage : 'missing_any',
            'priority'    => in_array( $priority, $priority_allowed, true ) ? $priority : 'all',
            'rows'        => $rows,
        );
    }

    private static function missing_groups_for_product( $product_id ) {
        $current = self::current_groups( $product_id );
        $missing = array();
        foreach ( self::GROUPS as $group ) {
            if ( empty( $current[ $group ] ) ) {
                $missing[] = $group;
            }
        }
        return $missing;
    }

    private static function product_matches_filters( $product_id, $filters ) {
        $missing = self::missing_groups_for_product( $product_id );
        $coverage = $filters['coverage'];
        if ( 'missing_any' === $coverage && ! $missing ) {
            return false;
        }
        if ( 'without_any' === $coverage && count( $missing ) < count( self::GROUPS ) ) {
            return false;
        }
        $coverage_group = array(
            'without_type' => 'tipo',
            'without_role' => 'rol',
            'without_application' => 'aplicacion',
            'without_platform' => 'plataforma',
            'without_subtype' => 'subtipo',
        );
        if ( isset( $coverage_group[ $coverage ] ) && ! in_array( $coverage_group[ $coverage ], $missing, true ) ) {
            return false;
        }
        $priority_group = array( 'p1'=>'tipo','p2'=>'rol','p3'=>'aplicacion','p4'=>'subtipo','p5'=>'plataforma' );
        if ( isset( $priority_group[ $filters['priority'] ] ) && ! in_array( $priority_group[ $filters['priority'] ], $missing, true ) ) {
            return false;
        }
        return true;
    }

    public static function analyze_filtered_products( $filters ) {
        $filters = self::sanitize_filters( $filters );
        $query_args = array(
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'fields'         => 'ids',
            'posts_per_page' => min( 500, max( $filters['rows'] * 8, 50 ) ),
            'orderby'        => 'ID',
            'order'          => 'ASC',
            'no_found_rows'  => true,
        );
        if ( '' !== $filters['search'] ) {
            $query_args['s'] = $filters['search'];
        }
        if ( $filters['category_id'] ) {
            // Bounded to at most 500 IDs; indexed product_cat relationship is intentional.
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
            $query_args['tax_query'] = array(
                array(
                    'taxonomy' => 'product_cat',
                    'field'    => 'term_id',
                    'terms'    => array( $filters['category_id'] ),
                ),
            );
        }
        $query = new WP_Query( $query_args );
        $results = array();
        foreach ( (array) $query->posts as $product_id ) {
            $product_id = absint( $product_id );
            if ( ! self::product_matches_filters( $product_id, $filters ) ) {
                continue;
            }
            $analysis = self::analyze_product( $product_id );
            if ( is_wp_error( $analysis ) ) {
                $results[] = array( 'product_id'=>$product_id, 'error'=>$analysis->get_error_message() );
            } else {
                $results[] = $analysis;
            }
            if ( count( $results ) >= $filters['rows'] ) {
                break;
            }
        }
        $report = array(
            'schema'       => 'taxocommerce_vocabulary_classifier_v1',
            'version'      => self::VERSION,
            'generated_gmt'=> current_time( 'mysql', true ),
            'filters'      => $filters,
            'processed'    => count( $results ),
            'results'      => $results,
        );
        update_option( 'taxocommerce_vocabulary_classifier_last_report', $report, false );
        return $report;
    }

    public static function last_report() {
        return (array) get_option( 'taxocommerce_vocabulary_classifier_last_report', array() );
    }

    // Classifier must read and update shared canonical vocabulary and proposal tables immediately; external writers cannot invalidate a local cache.
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    /**
     * Group recent missing concepts for human vocabulary normalization.
     * Bounded to 500 proposal records; no master writes.
     */
    public static function unmapped_terms( $limit = 25 ) {
        global $wpdb;
        $tables = TCV_Schema::tables();
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Own bounded proposal queue, grouped without scanning the entire catalogue.
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT product_id,semantic_group,label FROM %i WHERE active=1 AND state='review'
             AND vocabulary_id=0 AND label<>'' ORDER BY updated_at DESC LIMIT %d",
            $tables['proposals'], 500
        ), ARRAY_A );
        $grouped = array();
        foreach ( (array) $rows as $row ) {
            $group = sanitize_key( $row['semantic_group'] );
            $label = sanitize_text_field( $row['label'] );
            if ( ! in_array( $group, array( 'tipo', 'aplicacion', 'plataforma', 'subtipo' ), true ) || '' === $label ) {
                continue;
            }
            $key = $group . ':' . sanitize_title( $label );
            if ( ! isset( $grouped[ $key ] ) ) {
                $grouped[ $key ] = array( 'group' => $group, 'label' => $label, 'count' => 0 );
            }
            ++$grouped[ $key ]['count'];
        }
        uasort( $grouped, static function ( $a, $b ) { return $b['count'] <=> $a['count']; } );
        return array_slice( array_values( $grouped ), 0, min( 50, max( 1, absint( $limit ) ) ) );
    }

    public static function recent_category_proposals( $limit = 100 ) {
        global $wpdb;
        $t = TCV_Schema::tables();
        $limit = max( 1, min( 500, absint( $limit ) ) );
        return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i WHERE active=1 ORDER BY updated_at DESC LIMIT %d", $t['cat_proposals'], $limit ), ARRAY_A );
    }

    // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    // Classifier must read and update shared canonical vocabulary and proposal tables immediately; external writers cannot invalidate a local cache.
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    public static function recent_proposals( $limit = 100 ) {
        global $wpdb;
        $t = TCV_Schema::tables();
        $limit = max( 1, min( 500, absint( $limit ) ) );
        return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i WHERE active=1 AND label<>'' ORDER BY updated_at DESC LIMIT %d", $t['proposals'], $limit ), ARRAY_A );
    }

    // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
}
