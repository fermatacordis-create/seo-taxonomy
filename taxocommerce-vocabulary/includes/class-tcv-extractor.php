<?php
/**
 * Standalone local semantic extractor, adapted from the former classifier.
 *
 * @package TaxoCommerce_Vocabulary
 */
defined( 'ABSPATH' ) || exit;

final class TCV_Extractor {
    public static function normalize( $text ) {
        $text = html_entity_decode( wp_strip_all_tags( (string) $text ), ENT_QUOTES, 'UTF-8' );
        $text = remove_accents( $text );
        $text = mb_strtolower( $text, 'UTF-8' );
        $text = preg_replace( '/[^a-z0-9º°+\/._-]+/u', ' ', $text );
        return trim( preg_replace( '/\s+/u', ' ', (string) $text ) );
    }

    /** Source-specific normalized text and original text for numeric extraction. */
    public static function context( $values ) {
        $raw = array();
        $segments = array();
        foreach ( array( 'title', 'excerpt', 'content', 'categories', 'tags', 'brands', 'sku', 'supplier', 'attributes' ) as $field ) {
            $value = isset( $values[ $field ] ) ? (string) $values[ $field ] : '';
            $raw[ $field ] = mb_substr( $value, 0, 18000, 'UTF-8' );
            $segments[ $field ] = self::normalize( $raw[ $field ] );
        }
        $parts = preg_split( '/\s+[\x{2013}\x{2014}|-]\s+|:\s+/u', $raw['title'], 2 );
        $segments['identity'] = self::normalize( isset( $parts[0] ) ? $parts[0] : $raw['title'] );
        return array( 'raw' => $raw, 'segments' => $segments );
    }

    private static function concepts( $text ) {
        $stop = array_fill_keys( array( 'de', 'del', 'la', 'el', 'las', 'los', 'para',
            'por', 'con', 'sin', 'y', 'o', 'un', 'una', 'en', 'tipo', 'modelo',
            'producto', 'productos', 'profesional', 'nuevo', 'universal' ), true );
        $aliases = array(
            'coche' => 'vehiculo', 'coches' => 'vehiculo', 'automovil' => 'vehiculo',
            'vehiculos' => 'vehiculo', 'autos' => 'vehiculo', 'diagnosis' => 'diagnostico',
            'brocas' => 'broca', 'taladrado' => 'perforacion', 'taladrar' => 'perforacion',
            'perforar' => 'perforacion', 'concreto' => 'hormigon',
            'cristal' => 'vidrio', 'glass' => 'vidrio', 'radial' => 'amoladora',
            'esmeriladora' => 'amoladora', 'azulejos' => 'ceramica',
            'sds+' => 'sdsplus', 'sds-plus' => 'sdsplus', 'obd2' => 'obd',
            'obdii' => 'obd', 'voltaje' => 'tension', 'inóx' => 'inoxidable',
        );
        $text = str_replace( array( '/', '+', '.', '_' ), ' ', self::normalize( $text ) );
        $tokens = preg_split( '/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY );
        $out = array();
        foreach ( (array) $tokens as $token ) {
            if ( strlen( $token ) < 2 || isset( $stop[ $token ] ) ) {
                continue;
            }
            if ( isset( $aliases[ $token ] ) ) {
                $token = $aliases[ $token ];
            } elseif ( strlen( $token ) > 4 && preg_match( '/[aeiou]s$/', $token ) ) {
                $token = substr( $token, 0, -1 );
            }
            $out[] = $token;
        }
        return array_values( array_unique( $out ) );
    }

    /** Rank a canonical label; never create or assign a WordPress term. */
    public static function score( $label, $context ) {
        $needle = self::concepts( $label );
        if ( ! $needle ) {
            return array( 'score' => 0.0, 'evidence' => array() );
        }
        $weights = array( 'identity' => 1.0, 'title' => 1.0, 'brands' => 0.94,
            'supplier' => 0.9, 'tags' => 0.88, 'attributes' => 0.92, 'excerpt' => 0.8,
            'categories' => 0.76, 'content' => 0.64 );
        $segments = isset( $context['segments'] ) ? $context['segments'] : array();
        $sets = array();
        foreach ( $weights as $source => $weight ) {
            $sets[ $source ] = array_fill_keys( self::concepts( isset( $segments[ $source ] ) ? $segments[ $source ] : '' ), true );
        }
        $hits = 0;
        $sum = 0.0;
        $evidence = array();
        foreach ( $needle as $token ) {
            $best = 0.0;
            $source_name = '';
            foreach ( $weights as $source => $weight ) {
                if ( isset( $sets[ $source ][ $token ] ) && $weight > $best ) {
                    $best = $weight;
                    $source_name = $source;
                }
            }
            if ( $best > 0 ) {
                ++$hits;
                $sum += $best;
                $evidence[] = array( 'source' => $source_name, 'matched' => $token );
            }
        }
        if ( ! $hits ) {
            return array( 'score' => 0.0, 'evidence' => array() );
        }
        $exact = false;
        $phrase = self::normalize( $label );
        foreach ( $segments as $source => $text ) {
            if ( '' !== $phrase && false !== strpos( ' ' . $text . ' ', ' ' . $phrase . ' ' ) ) {
                $exact = true;
                $evidence[] = array( 'source' => $source, 'matched' => $phrase );
                break;
            }
        }
        $coverage = $sum / count( $needle );
        $title_hits = count( array_intersect( $needle, array_keys( $sets['title'] ) ) );
        $score = 0.08 + 0.68 * $coverage + 0.14 * ( $title_hits / count( $needle ) ) + ( $exact ? 0.10 : 0.0 );
        if ( $hits < count( $needle ) ) {
            $score *= $hits / count( $needle );
        }
        return array( 'score' => round( min( 1.0, $score ), 4 ), 'evidence' => $evidence );
    }

    /**
     * Controlled technical attributes: existing active terms and aliases only.
     * Keeps the matched source as auditable evidence.
     */
    public static function controlled( $attribute, $terms, $aliases, $context ) {
        $best = null;
        foreach ( (array) $terms as $term ) {
            $id = absint( $term['id'] );
            $variants = array_merge(
                array( $term['label'] ),
                isset( $aliases[ $id ] ) ? $aliases[ $id ] : array()
            );
            foreach ( $variants as $variant ) {
                $phrase = self::normalize( $variant );
                if ( '' === $phrase ) {
                    continue;
                }
                foreach ( array( 'title' => 0.98, 'brands' => 0.96, 'supplier' => 0.94,
                    'tags' => 0.92, 'attributes' => 0.96, 'excerpt' => 0.88, 'content' => 0.80,
                    'categories' => 0.76 ) as $source => $confidence ) {
                    $text = isset( $context['segments'][ $source ] ) ? $context['segments'][ $source ] : '';
                    if ( false === strpos( ' ' . $text . ' ', ' ' . $phrase . ' ' ) ) {
                        continue;
                    }
                    if ( null === $best || $confidence > $best['confidence'] ) {
                        $best = array(
                            'attribute_id' => absint( $attribute['id'] ),
                            'attribute' => (string) $attribute['nombre'],
                            'term_id' => $id,
                            'value' => (string) $term['label'],
                            'confidence' => $confidence,
                            'source' => $source,
                            'matched' => (string) $variant,
                        );
                    }
                }
            }
        }
        return $best;
    }

    /**
     * Explicit technical quantities, as defined by the original classifier.
     * Generic units such as kg/mm require an attribute name to avoid confusing
     * a weight, a load limit or an unrelated dimension.
     */
    public static function numeric( $attribute, $context ) {
        $slug = sanitize_key( (string) $attribute['slug'] );
        $rules = array(
            'tension' => array( array( 'voltaje', 'tensión', 'voltage' ), array( 'v' ) ),
            'potencia' => array( array( 'power' ), array( 'kw', 'w' ) ),
            'capacidad_bateria' => array( array( 'capacidad de batería', 'battery capacity' ), array( 'mah', 'ah' ) ),
            'par' => array( array( 'par máximo', 'torque' ), array( 'n·m', 'n-m', 'nm' ) ),
            'presion' => array( array( 'presión', 'pressure' ), array( 'mpa', 'bar', 'psi' ) ),
            'diametro' => array( array( 'diámetro', 'diametro' ), array( 'mm', 'cm', 'm' ) ),
            'longitud' => array( array( 'largo', 'length' ), array( 'mm', 'cm', 'm' ) ),
            'anchura' => array( array( 'ancho', 'width' ), array( 'mm', 'cm', 'm' ) ),
            'altura' => array( array( 'alto', 'height' ), array( 'mm', 'cm', 'm' ) ),
            'peso' => array( array( 'weight' ), array( 'kg', 'g', 'lb' ) ),
            'carga_maxima' => array( array( 'carga máxima', 'capacidad de carga' ), array( 'kg', 'g', 'lb', 't' ) ),
            'numero_piezas' => array( array( 'piezas', 'kit de', 'set de' ), array( 'piezas', 'ud', 'uds' ) ),
            'frecuencia' => array( array( 'frequency' ), array( 'ghz', 'mhz', 'khz', 'hz' ) ),
            'caudal' => array( array( 'flujo' ), array( 'l/min', 'cfm' ) ),
            'nivel_sonoro' => array( array( 'ruido' ), array( 'db' ) ),
        );
        $rule = isset( $rules[ $slug ] ) ? $rules[ $slug ] : array( array(), array() );
        $labels = array_merge( array( $attribute['nombre'], str_replace( '_', ' ', $slug ) ), $rule[0] );
        $unit_names = array_unique( array_filter( array_merge( $rule[1], array( $attribute['unidad_base'] ) ) ) );
        $unit_parts = array();
        foreach ( $unit_names as $unit ) {
            $unit_parts[] = preg_quote( (string) $unit, '/' );
        }
        usort( $unit_parts, static function ( $a, $b ) { return strlen( $b ) - strlen( $a ); } );
        $units = implode( '|', $unit_parts );
        if ( '' === $units ) {
            return null;
        }
        foreach ( array( 'title' => 0.99, 'attributes' => 0.98, 'supplier' => 0.95, 'excerpt' => 0.91,
            'content' => 0.84 ) as $source => $confidence ) {
            $text = isset( $context['raw'][ $source ] ) ? $context['raw'][ $source ] : '';
            if ( '' === $text ) {
                continue;
            }
            if ( 'diametro' === $slug &&
                preg_match( '/(?:Ø|ø|⌀|di[aá]metro)\s*[:=]?\s*(\d+(?:[.,]\d+)?)\s*(mm|cm|m)\b/iu', $text, $m ) ) {
                return self::quantity( $attribute, $m[1], $m[2], $source, $confidence );
            }
            if ( 'numero_piezas' === $slug &&
                preg_match( '/\b(?:set|juego|kit|pack)\s+(?:de\s+)?(\d{1,4})\b/iu', $text, $m ) ) {
                return self::quantity( $attribute, $m[1], 'ud', $source, $confidence );
            }
            foreach ( $labels as $label ) {
                $name = trim( (string) $label );
                if ( '' === $name ) {
                    continue;
                }
                if ( preg_match( '/(?:^|[^\p{L}])' . preg_quote( $name, '/' ) .
                    '\s*[:=]?\s*(\d+(?:[.,]\d+)?)\s*(' . $units . ')\b/iu', $text, $m ) ) {
                    return self::quantity( $attribute, $m[1], $m[2], $source, $confidence );
                }
            }
            $distinct = array( 'tension', 'potencia', 'capacidad_bateria', 'par',
                'presion', 'frecuencia', 'caudal', 'nivel_sonoro' );
            if ( in_array( $slug, $distinct, true ) &&
                preg_match( '/\b(\d+(?:[.,]\d+)?)\s*(' . $units . ')\b/iu', $text, $m ) ) {
                return self::quantity( $attribute, $m[1], $m[2], $source, max( 0.76, $confidence - 0.06 ) );
            }
        }
        return null;
    }

    private static function quantity( $attribute, $number, $unit, $source, $confidence ) {
        $number = str_replace( ',', '.', (string) $number );
        $unit = trim( (string) $unit );
        $display = array( 'v' => 'V', 'w' => 'W', 'kw' => 'kW', 'ah' => 'Ah',
            'mah' => 'mAh', 'nm' => 'N·m', 'db' => 'dB', 'hz' => 'Hz' );
        $key = strtolower( str_replace( array( ' ', '·', '-' ), '', $unit ) );
        if ( isset( $display[ $key ] ) ) {
            $unit = $display[ $key ];
        }
        return array(
            'attribute_id' => absint( $attribute['id'] ),
            'attribute' => (string) $attribute['nombre'],
            'value' => $number,
            'unit' => $unit,
            'confidence' => $confidence,
            'source' => $source,
            'evidence' => array( 'number' => $number, 'unit' => $unit, 'source' => $source ),
        );
    }


    /**
     * Detect a missing canonical concept, following the old novelty guard:
     * only one review proposal, never create a vocabulary term automatically.
     */
    public static function discover( $group, $context, $existing = array() ) {
        $raw = isset( $context['raw'] ) ? $context['raw'] : array();
        $title = isset( $raw['title'] ) ? trim( $raw['title'] ) : '';
        $candidate = '';
        $source = 'title';
        if ( 'tipo' === $group ) {
            $candidate = $title;
            $brand = isset( $raw['brands'] ) ? trim( $raw['brands'] ) : '';
            if ( '' !== $brand ) {
                foreach ( preg_split( '/\s*,\s*/u', $brand ) as $name ) {
                    $candidate = preg_replace( '/^' . preg_quote( $name, '/' ) . '\b[\s:–—-]*/iu', '', $candidate );
                }
            }
            $candidate = preg_replace( '/^(?:set|juego|pack|lote)\s+de\s+\d+\s+/iu', '', $candidate );
            $candidate = preg_replace( '/^(?:kit|set|juego|pack)\s+/iu', '', $candidate );
            $parts = preg_split( '/\s*[;,|]\s*|\s+[–—]\s+|\s+(?:con|para|hasta|incluye)\s+/iu', $candidate, 2 );
            $candidate = isset( $parts[0] ) ? $parts[0] : '';
            $candidate = preg_replace( '/\s+\d+(?:[.,]\d+)?\s*(?:mm|cm|kg|v|w|kw|ah|nm|rpm)\b.*$/iu', '', $candidate );
        } elseif ( 'plataforma' === $group ) {
            $source = 'technical_platform';
            $text = implode( ' ', array( $title, isset( $raw['supplier'] ) ? $raw['supplier'] : '' ) );
            if ( preg_match( '/\b(?:SDS\s*Plus|SDS\s*Max|Starlock(?:\s*Plus|\s*Max)?|X-?LOCK|AMPShare|FLEXVOLT|M12|M18|XGT|LXT)\b/iu', $text, $match ) ) {
                $candidate = $match[0];
            }
        } elseif ( 'aplicacion' === $group ) {
            $source = 'category';
            $category = isset( $raw['categories'] ) ? $raw['categories'] : '';
            if ( preg_match( '/\b(?:perforaci[oó]n|corte|soldadura|mantenimiento|carpinter[ií]a|pintura|limpieza|jardiner[ií]a|medici[oó]n|electricidad)\b/iu', $category, $match ) ) {
                $candidate = $match[0];
            }
        }
        $candidate = trim( preg_replace( '/\s+/u', ' ', (string) $candidate ), " \t\n\r\0\x0B,.;:|–—-_" );
        if ( '' === $candidate ) {
            return null;
        }
        $tokens = preg_split( '/\s+/u', $candidate, -1, PREG_SPLIT_NO_EMPTY );
        if ( 'tipo' === $group && ( count( $tokens ) < 2 || count( $tokens ) > 7 ) ) {
            return null;
        }
        if ( strlen( $candidate ) < 3 || strlen( $candidate ) > 90 ) {
            return null;
        }
        $concepts = self::concepts( $candidate );
        foreach ( (array) $existing as $term ) {
            $other = self::concepts( isset( $term['label'] ) ? $term['label'] : '' );
            $union = count( array_unique( array_merge( $concepts, $other ) ) );
            $similarity = $union ? count( array_intersect( $concepts, $other ) ) / $union : 0;
            if ( $similarity >= 0.72 ) {
                return null;
            }
        }
        return array(
            'group' => $group,
            'vocabulary_id' => 0,
            'label' => sanitize_text_field( $candidate ),
            'confidence' => 'plataforma' === $group ? 0.88 : ( 'tipo' === $group ? 0.82 : 0.78 ),
            'state' => 'review',
            'new_term' => true,
            'evidence' => array( 'source' => $source, 'reason' => 'missing_canonical_term' ),
        );
    }

}
