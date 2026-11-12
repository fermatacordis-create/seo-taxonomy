<?php
/**
 * Native, read-only content checks adapted from the legacy five-block Auditor.
 *
 * Findings are review signals, not automatic editorial changes or Google scores.
 */

namespace TaxoCommerce\Auditor;

defined( 'ABSPATH' ) || exit;

final class Rules {
    const DIMENSIONS = array(
        'utility'         => 25,
        'trust'           => 20,
        'differentiation' => 20,
        'architecture'    => 20,
        'technical'       => 15,
    );

    public static function band( $score ) {
        if ( null === $score ) {
            return 'no_aplica';
        }
        if ( $score >= 85 ) {
            return 'fuerte';
        }
        if ( $score >= 70 ) {
            return 'correcta';
        }
        return $score >= 55 ? 'mejorable' : 'debil';
    }

    /**
     * Consistent exact-intent key for cross-object duplicate review.
     */
    public static function identity_key( $title ) {
        $title = mb_strtolower( remove_accents( wp_strip_all_tags( (string) $title ) ), 'UTF-8' );
        $title = preg_replace( '/[^\p{L}\p{N}]+/u', ' ', $title );
        return trim( preg_replace( '/\s+/u', ' ', (string) $title ) );
    }

    private static function text( $value ) {
        return trim( wp_strip_all_tags( strip_shortcodes( (string) $value ) ) );
    }

    private static function tokens( $value ) {
        $stop = array( 'para', 'como', 'con', 'sin', 'sobre', 'este', 'esta', 'estos', 'estas', 'producto', 'productos', 'herramientas', 'herramienta', 'the', 'and', 'for', 'with', 'und', 'der', 'die', 'das', 'pour', 'avec', 'voor' );
        $words = preg_split( '/\s+/u', self::identity_key( $value ) );
        $out = array();
        foreach ( (array) $words as $word ) {
            if ( mb_strlen( $word, 'UTF-8' ) >= 4 && ! in_array( $word, $stop, true ) ) {
                $out[ $word ] = true;
                if ( count( $out ) >= 25 ) {
                    break;
                }
            }
        }
        return array_keys( $out );
    }

    /**
     * Measures how much of the specific title is supported by a description.
     * It is a heuristic only: weak alignment requires human verification.
     */
    public static function title_alignment( $title, $description ) {
        $a = self::tokens( $title );
        $b = self::tokens( $description );
        if ( count( $a ) < 2 || ! $b ) {
            return null;
        }
        return count( array_intersect( $a, $b ) ) / count( $a );
    }

    /**
     * Preserve the legacy Auditor's content-template fingerprint. Differences
     * in the title do not conceal mechanically duplicated long descriptions.
     */
    public static function template_signature( $body, $title = '' ) {
        $body = self::identity_key( self::text( $body ) );
        if ( '' === $body ) {
            return '';
        }
        $title_tokens = array_filter( explode( ' ', self::identity_key( $title ) ) );
        $tokens = array_values(
            array_filter(
                explode( ' ', $body ),
                static function ( $token ) use ( $title_tokens ) {
                    return mb_strlen( (string) $token, 'UTF-8' ) >= 3
                        && ! in_array( $token, $title_tokens, true )
                        && ! preg_match( '/^[0-9]+(?:[.,][0-9]+)?$/', $token );
                }
            )
        );
        return count( $tokens ) < 10 ? '' : implode( ' ', array_slice( $tokens, 0, 28 ) );
    }

    /**
     * Copy legacy checks for malformed attribute values and conflicting
     * numeric technical specifications without altering WooCommerce data.
     */
    private static function suspicious_attribute( $value ) {
        $value = trim( (string) $value );
        return $value !== ''
            && (
                mb_strlen( $value, 'UTF-8' ) > 240
                || false !== stripos( $value, '<html' )
                || false !== stripos( $value, '<div' )
                || false !== stripos( $value, 'http://' )
                || false !== stripos( $value, 'https://' )
                || preg_match( '/(^|[^a-z])(array|object|serialized)\s*[:(]/i', $value )
                || substr_count( $value, ';' ) >= 6
                || substr_count( $value, '|' ) >= 8
            );
    }

    private static function numeric_specs( $value ) {
        $out = array();
        $text = mb_strtolower( str_replace( ',', '.', remove_accents( self::text( $value ) ) ), 'UTF-8' );
        if ( ! preg_match_all( '/\b([0-9]+(?:\.[0-9]+)?)\s*(kw|w|v|ah|mah|bar|psi|mpa|rpm|nm|kg|t)\b/i', $text, $matches, PREG_SET_ORDER ) ) {
            return $out;
        }
        foreach ( $matches as $match ) {
            $unit = strtolower( $match[2] );
            $num = (float) $match[1];
            if ( 'kw' === $unit ) {
                $unit = 'w';
                $num *= 1000;
            } elseif ( 'mah' === $unit ) {
                $unit = 'ah';
                $num /= 1000;
            }
            $out[ $unit ][] = round( $num, 4 );
        }
        foreach ( $out as $unit => $values ) {
            $out[ $unit ] = array_values( array_unique( $values, SORT_REGULAR ) );
        }
        return $out;
    }

    private static function attribute_conflicts( $title, $attribute_text ) {
        $title_specs = self::numeric_specs( $title );
        $attr_specs = self::numeric_specs( $attribute_text );
        $conflicts = array();
        foreach ( $title_specs as $unit => $expected ) {
            if ( empty( $attr_specs[ $unit ] ) ) {
                continue;
            }
            $found_match = false;
            foreach ( $expected as $title_num ) {
                foreach ( $attr_specs[ $unit ] as $attr_num ) {
                    if ( abs( $title_num - $attr_num ) <= max( 0.01, abs( $title_num ) * 0.01 ) ) {
                        $found_match = true;
                        break 2;
                    }
                }
            }
            if ( ! $found_match ) {
                $conflicts[] = $unit . ': título ' . implode( '/', $expected ) . ', atributos ' . implode( '/', $attr_specs[ $unit ] );
            }
        }
        return array_slice( $conflicts, 0, 6 );
    }

    private static function tag_supported( $tag, $identity ) {
        $normalized_tag = self::identity_key( $tag );
        $normalized_identity = self::identity_key( $identity );
        if ( '' === $normalized_tag || '' === $normalized_identity ) {
            return true;
        }
        if ( mb_strlen( $normalized_tag, 'UTF-8' ) < 3
            || in_array( $normalized_tag, array( 'vevor', 'satkit', 'herramienta', 'herramientas', 'accesorio', 'accesorios', 'producto', 'productos' ), true ) ) {
            return true;
        }
        if ( false !== strpos( ' ' . $normalized_identity . ' ', ' ' . $normalized_tag . ' ' ) ) {
            return true;
        }
        foreach ( self::tokens( $tag ) as $token ) {
            if ( false !== strpos( ' ' . $normalized_identity . ' ', ' ' . $token . ' ' ) ) {
                return true;
            }
        }
        return false;
    }

    private static function finding( $code, $severity, $type, $id, $title, $evidence, $recommendation ) {
        return array(
            'code'           => $code,
            'severity'       => $severity,
            'entity_type'    => $type,
            'entity_id'      => absint( $id ),
            'title'          => (string) $title,
            'evidence'       => (string) $evidence,
            'recommendation' => (string) $recommendation,
        );
    }

    /**
     * The new Vocabulary owns tc_semantic_tag assignments. A missing plugin is
     * unavailable evidence, never treated as a missing assignment.
     */
    private static function vocabulary_count( $type, $id ) {
        if ( ! taxonomy_exists( 'tc_semantic_tag' ) ) {
            return null;
        }
        if ( 'product_cat' === $type || 'category' === $type ) {
            $ids = get_term_meta( $id, '_taxocommerce_vocabulary_semantic_terms', true );
            return is_array( $ids ) ? count( array_filter( array_map( 'absint', $ids ) ) ) : 0;
        }
        $ids = wp_get_object_terms( $id, 'tc_semantic_tag', array( 'fields' => 'ids' ) );
        return is_wp_error( $ids ) ? null : count( (array) $ids );
    }

    private static function quality( $type, $title, $body, $excerpt, $has_identity, $has_structure, $vocab_count, $slug, $extra = array() ) {
        $len = mb_strlen( (string) $body, 'UTF-8' );
        $excerpt_len = mb_strlen( (string) $excerpt, 'UTF-8' );
        $alignment = self::title_alignment( $title, $excerpt ?: $body );
        $utility = ( $title !== '' ? 5 : 0 ) + ( $excerpt_len >= 45 ? 5 : ( $excerpt_len > 0 ? 3 : 0 ) )
            + ( $len >= 250 ? 10 : ( $len >= 100 ? 7 : ( $len > 0 ? 3 : 0 ) ) )
            + ( count( self::tokens( $body ) ) >= 12 ? 5 : ( $len >= 100 ? 2 : 0 ) );
        if ( in_array( $type, array( 'post', 'page' ), true ) && $excerpt_len === 0 && $len >= 120 ) {
            $utility += 3; // Native post excerpts are optional.
        }
        $trust = ( $has_identity ? 10 : 3 ) + ( null === $alignment ? 5 : ( $alignment >= 0.2 ? 10 : ( $alignment >= 0.1 ? 6 : 2 ) ) );
        $architecture = ( $has_structure ? 12 : 4 ) + ( null === $vocab_count ? 8 : ( $vocab_count > 0 ? 8 : 3 ) );
        $dimensions = array(
            'utility'         => min( 25, $utility ),
            'trust'           => min( 20, $trust ),
            'differentiation' => 15, // Exact duplication is scored separately as a finding.
            'architecture'    => min( 20, $architecture ),
            'technical'       => ( $slug !== '' ? 12 : 2 ) + ( ! empty( $extra['technical'] ) ? 3 : 0 ),
        );
        $score = array_sum( $dimensions );
        return array(
            'score'      => $score,
            'band'       => self::band( $score ),
            'dimensions' => $dimensions,
        );
    }

    private static function inspect_product( \WP_Post $post ) {
        $id = $post->ID;
        $title = trim( (string) $post->post_title );
        $findings = array();
        $product = function_exists( 'wc_get_product' ) ? wc_get_product( $id ) : false;

        if ( ! $product ) {
            $findings[] = self::finding( 'product_unreadable', 'high', 'product', $id, $title, __( 'WooCommerce no pudo cargar el producto.', 'taxocommerce-auditor' ), __( 'Revisar integridad del producto antes de editar sus datos.', 'taxocommerce-auditor' ) );
            return array( 'findings' => $findings, 'quality' => null, 'title_key' => self::identity_key( $title ) );
        }

        $excerpt = self::text( $product->get_short_description() );
        $body = self::text( $product->get_description() );
        $slug = trim( (string) $post->post_name );
        $cats = $product->get_category_ids();
        $sku = trim( (string) $product->get_sku() );
        $vocab = self::vocabulary_count( 'product', $id );

        if ( '' === $title ) {
            $findings[] = self::finding( 'product_missing_title', 'critical', 'product', $id, $title, __( 'Título vacío.', 'taxocommerce-auditor' ), __( 'Asignar un título identificativo.', 'taxocommerce-auditor' ) );
        }
        if ( '' === $slug ) {
            $findings[] = self::finding( 'product_missing_slug', 'high', 'product', $id, $title, __( 'Slug vacío.', 'taxocommerce-auditor' ), __( 'Revisar la URL canónica.', 'taxocommerce-auditor' ) );
        }
        if ( ! $cats ) {
            $findings[] = self::finding( 'product_without_category', 'high', 'product', $id, $title, __( 'Ninguna categoría WooCommerce asignada.', 'taxocommerce-auditor' ), __( 'Asignar una categoría existente apropiada.', 'taxocommerce-auditor' ) );
        }
        if ( '' === $excerpt ) {
            $findings[] = self::finding( 'product_missing_excerpt', 'medium', 'product', $id, $title, __( 'Descripción corta vacía.', 'taxocommerce-auditor' ), __( 'Redactar un resumen específico del producto.', 'taxocommerce-auditor' ) );
        }
        if ( '' === $body ) {
            $findings[] = self::finding( 'product_missing_description', 'medium', 'product', $id, $title, __( 'Descripción larga vacía.', 'taxocommerce-auditor' ), __( 'Añadir datos técnicos y comerciales comprobables.', 'taxocommerce-auditor' ) );
        }
        $alignment = self::title_alignment( $title, $excerpt );
        if ( null !== $alignment && mb_strlen( $excerpt, 'UTF-8' ) >= 35 && $alignment < 0.2 ) {
            $findings[] = self::finding( 'product_title_excerpt_drift', 'high', 'product', $id, $title, __( 'Muy pocas palabras del título aparecen en el resumen.', 'taxocommerce-auditor' ), __( 'Contrastar título y resumen con la fuente original; posible desplazamiento de contenido.', 'taxocommerce-auditor' ) );
        }
        // The legacy auditor distinguishes a weak textual match (review signal)
        // from demonstrable displacement to another product (not inferred here).
        if ( '' !== $body && mb_strlen( $body, 'UTF-8' ) >= 80 ) {
            $long_alignment = self::title_alignment( $title, $body );
            if ( null !== $long_alignment && $long_alignment < 0.08 ) {
                $findings[] = self::finding(
                    'product_description_alignment_weak', 'low', 'product', $id, $title,
                    __( 'La descripción presenta muy poca coincidencia literal con el título.', 'taxocommerce-auditor' ),
                    __( 'Revisar la fuente original; esta señal por sí sola no prueba que se haya copiado otro producto.', 'taxocommerce-auditor' )
                );
            }
        }

        $attribute_text = '';
        foreach ( (array) $product->get_attributes() as $attribute ) {
            if ( ! $attribute instanceof \WC_Product_Attribute ) {
                continue;
            }
            $name = $attribute->is_taxonomy() ? wc_attribute_label( $attribute->get_name() ) : $attribute->get_name();
            $values = $attribute->is_taxonomy()
                ? wc_get_product_terms( $id, $attribute->get_name(), array( 'fields' => 'names' ) )
                : $attribute->get_options();
            $values = is_wp_error( $values ) ? array() : array_values( array_filter( array_map( 'strval', (array) $values ), 'strlen' ) );
            if ( '' === trim( (string) $name ) && $values ) {
                $findings[] = self::finding( 'attribute_without_name', 'medium', 'product', $id, $title, __( 'Atributo con valor pero sin nombre.', 'taxocommerce-auditor' ), __( 'Corregir el nombre del atributo mediante WooCommerce.', 'taxocommerce-auditor' ) );
            } elseif ( '' !== trim( (string) $name ) && ! $values ) {
                $findings[] = self::finding( 'attribute_without_value', 'medium', 'product', $id, $title, 'Atributo ' . $name . ' sin valor.', __( 'Completar el valor o retirar el atributo vacío.', 'taxocommerce-auditor' ) );
            }
            foreach ( array_slice( $values, 0, 30 ) as $value ) {
                if ( self::suspicious_attribute( $value ) ) {
                    $findings[] = self::finding( 'attribute_value_malformed', 'medium', 'product', $id, $title, 'Formato sospechoso en atributo ' . $name . ': ' . mb_substr( $value, 0, 120, 'UTF-8' ), __( 'Verificar si hay texto o datos desplazados procedentes del proveedor.', 'taxocommerce-auditor' ) );
                    break;
                }
            }
            $attribute_text .= ' ' . $name . ' ' . implode( ' ', array_slice( $values, 0, 30 ) );
        }

        $conflicts = self::attribute_conflicts( $title, $attribute_text );
        if ( $conflicts ) {
            $findings[] = self::finding(
                'product_attribute_title_conflict', 'high', 'product', $id, $title,
                implode( '; ', $conflicts ),
                __( 'El título y los atributos muestran valores técnicos incompatibles; contrastar con el fabricante.', 'taxocommerce-auditor' )
            );
        }

        // A tag without meaningful support is a review signal, never a deletion.
        if ( taxonomy_exists( 'product_tag' ) ) {
            $tags = wp_get_object_terms( $id, 'product_tag', array( 'fields' => 'names' ) );
            if ( ! is_wp_error( $tags ) ) {
                $category_names = function_exists( 'wc_get_product_terms' )
                    ? wc_get_product_terms( $id, 'product_cat', array( 'fields' => 'names' ) )
                    : array();
                $category_names = is_wp_error( $category_names ) ? array() : (array) $category_names;
                $identity = $title . ' ' . $excerpt . ' ' . $body . ' ' . $attribute_text . ' ' . implode( ' ', $category_names );
                foreach ( array_slice( (array) $tags, 0, 30 ) as $tag ) {
                    if ( ! self::tag_supported( $tag, $identity ) ) {
                        $findings[] = self::finding(
                            'product_tag_identity_drift', 'low', 'product', $id, $title,
                            'La etiqueta "' . $tag . '" no aparece sustentada por título, contenido, categorías o atributos.',
                            __( 'Comprobar si la etiqueta describe realmente el producto.', 'taxocommerce-auditor' )
                        );
                        break;
                    }
                }
            }
        }

        if ( '' === $sku ) {
            $findings[] = self::finding( 'product_no_stable_sku', 'low', 'product', $id, $title, 'SKU vacío; puede ser legítimo para algunos productos.', __( 'Comprobar si existe otro identificador estable en el proveedor.', 'taxocommerce-auditor' ) );
        }
        if ( 0 === $vocab ) {
            $findings[] = self::finding( 'product_without_native_vocabulary', 'low', 'product', $id, $title, __( 'No hay términos tc_semantic_tag asignados.', 'taxocommerce-auditor' ), __( 'Revisar las propuestas del Clasificador sin asignar etiquetas automáticamente.', 'taxocommerce-auditor' ) );
        }

        return array(
            'findings'  => $findings,
            'sku_key'   => self::identity_key( $sku ),
            'template_key' => self::template_signature( $body, $title ),
            'title_key' => self::identity_key( $title ),
            'quality'   => self::quality( 'product', $title, $body, $excerpt, '' !== $sku, (bool) $cats, $vocab, $slug, array( 'technical' => $product->get_image_id() > 0 ) ),
        );
    }

    /**
     * Read canonical SEO category assignments from seo_relations once per PHP
     * request. The cache lives only for this request: subsequent AJAX batches
     * read the current database, not a persisted snapshot or term_meta.
     *
     * Null means that the canonical table cannot be queried; do not infer
     * missing relationships from unavailable evidence.
     *
     * @return array<int, bool>|null
     */
    private static function category_secondary_hub_map() {
        static $assignments = null;
        static $queried = false;

        if ( $queried ) {
            return $assignments;
        }
        $queried = true;
        global $wpdb;

        if ( ! isset( $wpdb ) || ! is_object( $wpdb ) || ! method_exists( $wpdb, 'prepare' ) || ! method_exists( $wpdb, 'get_col' ) ) {
            return null;
        }

        $table = $wpdb->prefix . 'seo_relations';
        // Canonical relation direction: hub_secondary -> product_cat.
        // One bounded query for the entire category scope, never one query per term.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Read-only canonical SEO relationship table; request-scoped cache avoids per-term queries.
        $targets = $wpdb->get_col(
            $wpdb->prepare(
                'SELECT DISTINCT target_id FROM %i WHERE source_type = %s AND target_type = %s AND relation_type = %s',
                $table,
                'hub_secondary',
                'product_cat',
                'hub_secondary_to_category'
            )
        );
        if ( ! is_array( $targets ) || ! empty( $wpdb->last_error ) ) {
            return null;
        }

        $assignments = array_fill_keys( array_filter( array_map( 'absint', $targets ) ), true );
        return $assignments;
    }

    private static function inspect_category( \WP_Term $term ) {
        $id = $term->term_id;
        $title = trim( (string) $term->name );
        $body = self::text( $term->description );
        $slug = trim( (string) $term->slug );
        $count = absint( $term->count );
        $findings = array();
        $secondary_hubs = self::category_secondary_hub_map();
        $vocab = self::vocabulary_count( 'product_cat', $id );

        if ( $count > 0 && $body === '' ) {
            $findings[] = self::finding( 'category_missing_description', 'medium', 'product_cat', $id, $title, __( 'Categoría con productos y sin descripción.', 'taxocommerce-auditor' ), __( 'Explicar qué productos comprende y cuáles no.', 'taxocommerce-auditor' ) );
        }
        if ( 0 === $count ) {
            $findings[] = self::finding( 'category_empty', 'low', 'product_cat', $id, $title, __( 'Recuento nativo de productos: 0.', 'taxocommerce-auditor' ), __( 'Revisar si está reservada, debe poblarse o retirarse con redirección.', 'taxocommerce-auditor' ) );
        }
        // Category counts and semantically supported split/merge proposals are
        // assessed in Category_Review, not duplicated as unrelated rules here.
        if ( 1 === $count ) {
            $findings[] = self::finding( 'category_single_product', 'low', 'product_cat', $id, $title, __( 'Una única referencia publicada.', 'taxocommerce-auditor' ), __( 'Revisar si la categoría aporta navegación o merece una familia más amplia.', 'taxocommerce-auditor' ) );
        }
        $parent_term = $term->parent > 0 ? get_term( $term->parent, 'product_cat' ) : null;
        if ( $term->parent > 0 && ( ! $parent_term || is_wp_error( $parent_term ) ) ) {
            $findings[] = self::finding( 'category_parent_unavailable', 'high', 'product_cat', $id, $title, __( 'Categoría padre no encontrada.', 'taxocommerce-auditor' ), __( 'Comprobar la jerarquía nativa de producto.', 'taxocommerce-auditor' ) );
        }
        if ( $count > 0 && taxonomy_exists( 'tc_semantic_tag' ) && 0 === $vocab ) {
            $findings[] = self::finding( 'category_without_native_vocabulary', 'low', 'product_cat', $id, $title, __( 'Sin términos semánticos asignados.', 'taxocommerce-auditor' ), __( 'Revisar asignaciones aprobadas en Vocabulary.', 'taxocommerce-auditor' ) );
        }
        // A WordPress term has no standard excerpt field. A registered
        // native-meta provider can supply one; absence is unknown, not empty.
        $excerpt = apply_filters( 'taxocommerce_auditor_category_excerpt', null, $term );
        if ( is_string( $excerpt ) ) {
            $excerpt = self::text( $excerpt );
            if ( $count > 0 && '' === $excerpt ) {
                $findings[] = self::finding(
                    'category_missing_excerpt', 'low', 'product_cat', $id, $title,
                    __( 'El campo de resumen nativo suministrado está vacío.', 'taxocommerce-auditor' ),
                    __( 'Redactar un resumen específico para esta familia.', 'taxocommerce-auditor' )
                );
            }
            $excerpt_alignment = self::title_alignment( $title, $excerpt );
            if ( mb_strlen( $excerpt, 'UTF-8' ) >= 35 && null !== $excerpt_alignment && $excerpt_alignment < 0.16 ) {
                $findings[] = self::finding(
                    'category_excerpt_identity_drift', 'medium', 'product_cat', $id, $title,
                    __( 'El resumen no parece corresponder al nombre de la categoría.', 'taxocommerce-auditor' ),
                    __( 'Contrastar el resumen con la familia correcta.', 'taxocommerce-auditor' )
                );
            }
        } else {
            $excerpt = '';
        }

        $description_alignment = self::title_alignment( $title, $body );
        if ( mb_strlen( $body, 'UTF-8' ) >= 80 && null !== $description_alignment && $description_alignment < 0.08 ) {
            $findings[] = self::finding(
                'category_description_alignment_weak', 'low', 'product_cat', $id, $title,
                __( 'La descripción está poco alineada literalmente con el título.', 'taxocommerce-auditor' ),
                __( 'Verificar su pertinencia; una explicación técnica válida puede no repetir el título.', 'taxocommerce-auditor' )
            );
        }
        $slug_alignment = self::title_alignment( $title, str_replace( '-', ' ', $slug ) );
        if ( null !== $slug_alignment && $slug_alignment < 0.34 ) {
            $findings[] = self::finding(
                'category_name_slug_drift', 'low', 'product_cat', $id, $title,
                __( 'Nombre y slug describen términos distintos.', 'taxocommerce-auditor' ),
                __( 'Revisar si el slug sigue representando la categoría.', 'taxocommerce-auditor' )
            );
        }

        if ( is_array( $secondary_hubs ) && ! isset( $secondary_hubs[ $id ] ) ) {
            $findings[] = self::finding( 'category_no_native_secondary_hub', 'low', 'product_cat', $id, $title, __( 'No existe relación hub_secondary_to_category en seo_relations.', 'taxocommerce-auditor' ), __( 'Vincular la categoría con un Hub secundario mediante la arquitectura SEO canónica.', 'taxocommerce-auditor' ) );
        }

        return array(
            'findings'  => $findings,
            'title_key' => self::identity_key( $title ),
            'template_key' => self::template_signature( $body, $title ),
            'quality'   => self::quality( 'product_cat', $title, $body, $excerpt, $count > 0, $count > 0 || $term->parent > 0, $vocab, $slug ),
        );
    }

    private static function inspect_editorial( \WP_Post $post, $scope ) {
        $type = 'pages' === $scope ? 'page' : 'post';
        $id = $post->ID;
        $title = trim( (string) $post->post_title );
        $slug = trim( (string) $post->post_name );
        $role = sanitize_key( (string) get_post_meta( $id, '_taxocommerce_core_seo_role', true ) );
        // Preserve the comprehensive functional/corporate exclusions from
        // the original editorial specialist; do not assess them as SEO content.
        if ( 'page' === $type && in_array( Editorial_Specialist::page_kind( $post ), array( 'functional', 'corporate' ), true ) ) {
            return array( 'excluded' => true, 'reason' => 'Página funcional o corporativa.' );
        }

        $body = self::text( $post->post_content );
        $excerpt = self::text( $post->post_excerpt );
        $findings = array();
        $vocab = self::vocabulary_count( $type, $id );
        $has_structure = true;

        if ( '' === $title ) {
            $findings[] = self::finding( 'editorial_missing_title', 'critical', $type, $id, $title, __( 'Título vacío.', 'taxocommerce-auditor' ), __( 'Definir un título útil para la intención real.', 'taxocommerce-auditor' ) );
        }
        if ( '' === $slug ) {
            $findings[] = self::finding( 'editorial_missing_slug', 'high', $type, $id, $title, __( 'Slug vacío.', 'taxocommerce-auditor' ), __( 'Comprobar la URL de esta publicación.', 'taxocommerce-auditor' ) );
        }
        if ( mb_strlen( $body, 'UTF-8' ) < 120 ) {
            $findings[] = self::finding( 'editorial_thin_content', 'low', $type, $id, $title, 'Texto corto: ' . mb_strlen( $body, 'UTF-8' ) . ' caracteres.', __( 'Revisar utilidad y respuesta a la intención; no existe un mínimo de palabras SEO.', 'taxocommerce-auditor' ) );
        }
        if ( 0 === $vocab && mb_strlen( $body, 'UTF-8' ) >= 120 ) {
            $findings[] = self::finding( 'editorial_without_native_vocabulary', 'low', $type, $id, $title, __( 'Sin etiquetas semánticas asignadas.', 'taxocommerce-auditor' ), __( 'Revisar Vocabulary y añadir solo términos pertinentes.', 'taxocommerce-auditor' ) );
        }
        if ( 'page' === $type ) {
            // A missing role is evaluated once by Editorial_Specialist.
            $parent_meta = array(
                'hub_primary'   => '_taxocommerce_core_parent_cluster',
                'hub_secondary' => '_taxocommerce_core_parent_hub_primary',
            );
            if ( isset( $parent_meta[ $role ] ) && ! array_filter( array_map( 'absint', (array) get_post_meta( $id, $parent_meta[ $role ], false ) ) ) ) {
                $has_structure = false;
                $findings[] = self::finding( 'page_missing_native_parent', 'medium', $type, $id, $title, __( 'Hub sin padre identificado en post_meta de Core.', 'taxocommerce-auditor' ), __( 'Verificar el vínculo nativo con el nivel superior antes de modificar la jerarquía.', 'taxocommerce-auditor' ) );
            }
        }
        if ( $vocab > 0 ) {
            $native_terms = wp_get_object_terms( $id, 'tc_semantic_tag', array( 'fields' => 'names' ) );
            if ( ! is_wp_error( $native_terms ) ) {
                $matched = false;
                foreach ( (array) $native_terms as $name ) {
                    if ( self::tag_supported( $name, $title . ' ' . $excerpt ) ) {
                        $matched = true;
                        break;
                    }
                }
                if ( $native_terms && ! $matched ) {
                    $findings[] = self::finding(
                        'editorial_vocab_title_drift', 'low', $type, $id, $title,
                        __( 'Las etiquetas asignadas no encuentran apoyo en el título o resumen.', 'taxocommerce-auditor' ),
                        __( 'Revisar las etiquetas y la intención antes de modificar el contenido.', 'taxocommerce-auditor' )
                    );
                }
            }
        }

        $alignment = self::title_alignment( $title, $body );
        if ( null !== $alignment && mb_strlen( $body, 'UTF-8' ) >= 140 && $alignment < 0.1 ) {
            $findings[] = self::finding( 'editorial_title_content_drift', 'medium', $type, $id, $title, __( 'Poca coincidencia entre palabras específicas del título y texto.', 'taxocommerce-auditor' ), __( 'Revisar si la publicación satisface su intención original.', 'taxocommerce-auditor' ) );
        }

        return array(
            'findings'  => $findings,
            'title_key' => self::identity_key( $title ),
            'template_key' => self::template_signature( $body, $title ),
            'quality'   => self::quality( $type, $title, $body, $excerpt, true, $has_structure, $vocab, $slug, array( 'technical' => '' !== $post->post_name ) ),
        );
    }

    /**
     * No universal FAQ CPT exists in WordPress. Owner mapping is explicit and
     * filterable; an unknown schema must not be reported as a broken owner.
     */
    /**
     * The original FAQ check requires an explicit owner and compares repeated
     * questions only within that owner. A missing schema is unavailable data.
     */
    private static function inspect_faq( $post ) {
        $title = trim( (string) ( $post->post_title ?? '' ) );
        $answer = self::text( $post->post_content ?? '' );
        $findings = array();
        $id = absint( $post->ID ?? 0 );
        $owner_key = '';
        $category_id = 0;

        if ( '' === $title ) {
            $findings[] = self::finding( 'faq_empty_question', 'high', 'faq', $id, $title, 'FAQ sin pregunta.', __( 'Completar o retirar la pregunta.', 'taxocommerce-auditor' ) );
        }
        if ( mb_strlen( $answer, 'UTF-8' ) < 30 ) {
            $findings[] = self::finding( 'faq_thin_answer', 'medium', 'faq', $id, $title, __( 'Respuesta ausente o demasiado escueta.', 'taxocommerce-auditor' ), __( 'Aportar una respuesta concreta y verificable.', 'taxocommerce-auditor' ) );
        }

        $owner = null;
        if ( $post instanceof FAQ_Record ) {
            $category_id = absint( $post->product_cat_id );
            $owner_type = absint( $post->object_type );
            $owner_id = absint( $post->object_id );
            if ( 1 === $owner_type ) {
                $owner = array( 'type' => 'page', 'id' => $owner_id );
            } elseif ( 2 === $owner_type ) {
                $owner = array( 'type' => 'product_cat', 'id' => $owner_id ?: $category_id );
            } elseif ( 3 === $owner_type ) {
                $owner = array( 'type' => 'product', 'id' => $owner_id );
            } else {
                $owner = array( 'type' => 'invalid', 'id' => $owner_id );
            }

            if ( ! $category_id || ! term_exists( $category_id, 'product_cat' ) ) {
                $findings[] = self::finding(
                    'faq_missing_product_category',
                    'medium',
                    'faq',
                    $id,
                    $title,
                    'FAQ sin product_cat_id válido en el almacén canónico.',
                    __( 'Asignar una categoría real de WooCommerce para conservar contexto comercial y semántico.', 'taxocommerce-auditor' )
                );
            }
        } else {
            // Generic FAQ CPT integration remains available for third-party
            // providers. Never infer an owner from the question text.
            $owner = apply_filters( 'taxocommerce_auditor_faq_owner', null, $post );
        }

        if ( is_array( $owner ) && isset( $owner['type'], $owner['id'] ) ) {
            $type = sanitize_key( (string) $owner['type'] );
            $owner_id = absint( $owner['id'] );
            $valid_type = in_array( $type, array( 'product', 'product_cat', 'page' ), true );
            if ( ! $valid_type ) {
                $findings[] = self::finding(
                    'faq_invalid_owner_type', 'critical', 'faq', $id, $title,
                    __( 'El owner de FAQ no pertenece a los tipos canónicos página/hub, categoría o producto.', 'taxocommerce-auditor' ),
                    __( 'Asignar un owner canónico del tipo correcto.', 'taxocommerce-auditor' )
                );
            } else {
                if ( 'product' === $type ) {
                    $valid = 'product' === get_post_type( $owner_id ) && 'publish' === get_post_status( $owner_id );
                } elseif ( 'product_cat' === $type ) {
                    $valid = (bool) term_exists( $owner_id, 'product_cat' );
                } else {
                    $owner_post = get_post( $owner_id );
                    $valid = $owner_post instanceof \WP_Post
                        && 'publish' === (string) $owner_post->post_status
                        && in_array( (string) $owner_post->post_type, array( 'page', 'seo_landing' ), true );
                }

                if ( ! $valid ) {
                    $findings[] = self::finding(
                        'faq_orphan_owner', 'critical', 'faq', $id, $title,
                        __( 'No se encuentra el owner publicado o su tipo no corresponde.', 'taxocommerce-auditor' ),
                        'Revisar la relación canónica por ID, sin inferirla mediante similitud.'
                    );
                } else {
                    $owner_key = $type . ':' . $owner_id;
                    $owner_text = '';
                    if ( 'product' === $type ) {
                        $owner_text = (string) get_the_title( $owner_id );
                        if ( function_exists( 'wc_get_product' ) ) {
                            $owner_product = wc_get_product( $owner_id );
                            if ( $owner_product ) {
                                $owner_text .= ' ' . self::text( $owner_product->get_short_description() )
                                    . ' ' . self::text( $owner_product->get_description() );
                            }
                        }
                    } elseif ( 'product_cat' === $type ) {
                        $owner_term = get_term( $owner_id, 'product_cat' );
                        if ( $owner_term instanceof \WP_Term ) {
                            $owner_text = $owner_term->name . ' ' . self::text( $owner_term->description );
                        }
                    } else {
                        $owner_post = get_post( $owner_id );
                        if ( $owner_post instanceof \WP_Post ) {
                            $owner_text = $owner_post->post_title . ' '
                                . self::text( $owner_post->post_excerpt ) . ' '
                                . self::text( $owner_post->post_content );
                        }
                    }

                    // Product category is useful supporting context even when
                    // the direct owner is a page/hub or product.
                    if ( $category_id ) {
                        $context_term = get_term( $category_id, 'product_cat' );
                        if ( $context_term instanceof \WP_Term ) {
                            $owner_text .= ' ' . $context_term->name . ' ' . self::text( $context_term->description );
                        }
                    }

                    if ( count( self::tokens( $title ) ) >= 2 ) {
                        $alignment = self::title_alignment( $title, $owner_text );
                        if ( null !== $alignment && $alignment < 0.08 ) {
                            $findings[] = self::finding(
                                'faq_owner_semantic_drift', 'medium', 'faq', $id, $title,
                                'La pregunta parece semánticamente alejada del owner ' . $owner_key . '.',
                                'Comprobar contenido y owner; la relación técnica por ID puede ser válida.'
                            );
                        }
                    }
                }
            }
        }

        return array(
            'findings'   => $findings,
            'title_key'  => self::identity_key( $title ),
            'owner_key'  => $owner_key,
            'quality'    => self::quality( 'faq', $title, $answer, '', true, true, null, trim( (string) ( $post->post_name ?? '' ) ) ),
        );
    }

    public static function inspect( $scope, $object ) {
        if ( 'products' === $scope && $object instanceof \WP_Post ) {
            return self::inspect_product( $object );
        }
        if ( 'categories' === $scope && $object instanceof \WP_Term ) {
            return self::inspect_category( $object );
        }
        if ( in_array( $scope, array( 'posts', 'pages' ), true ) && $object instanceof \WP_Post ) {
            return self::inspect_editorial( $object, $scope );
        }
        if ( 'faqs' === $scope && ( $object instanceof FAQ_Record || $object instanceof \WP_Post ) ) {
            return self::inspect_faq( $object );
        }
        return array( 'excluded' => true, 'reason' => 'Tipo de objeto no reconocido.' );
    }
}
