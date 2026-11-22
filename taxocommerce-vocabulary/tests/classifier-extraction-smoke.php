<?php
/**
 * Offline regression: local product vocabulary extraction and numeric units.
 * Does not bootstrap WordPress, contact URLs or modify the database.
 */
define( 'ABSPATH', __DIR__ . '/' );
function wp_strip_all_tags( $s ) { return strip_tags( $s ); }
function remove_accents( $s ) {
    return strtr( $s, array( 'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n' ) );
}
function absint( $v ) { return abs( (int) $v ); }
function sanitize_key( $v ) { return preg_replace( '/[^a-z0-9_-]/', '', strtolower( (string) $v ) ); }
function sanitize_text_field( $v ) { return strip_tags( trim( (string) $v ) ); }
function tcv_extract_assert( $ok, $reason ) {
    if ( ! $ok ) {
        fwrite( STDERR, 'FAIL Vocabulary extractor: ' . $reason . "\n" );
        exit( 1 );
    }
}
require dirname( __DIR__ ) . '/includes/class-tcv-extractor.php';

$context = TCV_Extractor::context( array(
    'title' => 'Taladro percutor 18 V con broca Ø 6 mm',
    'excerpt' => 'Potencia: 600 W. Juego de 24 piezas.',
    'content' => 'Motor sin escobillas para perforar hormigón.',
    'categories' => 'Herramientas eléctricas',
    'tags' => 'brushless',
    'brands' => 'Bosch',
    'sku' => 'TEST-001',
    'supplier' => 'Kit de perforación 18V',
) );
$drill = TCV_Extractor::score( 'taladro', $context );
tcv_extract_assert( $drill['score'] > 0.85, 'title provides a strong semantic signal' );
tcv_extract_assert( count( $drill['evidence'] ) > 0, 'each proposal retains source evidence' );
tcv_extract_assert( TCV_Extractor::score( 'amoladora', $context )['score'] < 0.20, 'unrelated tool is not inferred' );
tcv_extract_assert( TCV_Extractor::score( 'vehículo', $context )['score'] < 0.20, 'foreign vocabulary not inferred' );
tcv_extract_assert( TCV_Extractor::score( 'bosch', $context )['score'] > 0.65, 'brand taxonomy is considered' );
tcv_extract_assert( TCV_Extractor::score( 'perforación', $context )['score'] > 0.45, 'lexical equivalence is recognized' );

$term = TCV_Extractor::controlled(
    array( 'id' => 4, 'nombre' => 'Tecnología del motor' ),
    array( array( 'id' => 12, 'label' => 'Sin escobillas' ) ),
    array( 12 => array( 'brushless' ) ),
    $context
);
tcv_extract_assert( 12 === $term['term_id'], 'stored alias resolves an existing controlled term' );
tcv_extract_assert( 'tags' === $term['source'], 'controlled value records the evidence source' );

$voltage = TCV_Extractor::numeric(
    array( 'id' => 5, 'nombre' => 'Tensión', 'slug' => 'tension', 'unidad_base' => 'V' ),
    $context
);
tcv_extract_assert( '18' === $voltage['value'] && 'V' === $voltage['unit'], 'voltage with technical unit' );
$diameter = TCV_Extractor::numeric(
    array( 'id' => 6, 'nombre' => 'Diámetro', 'slug' => 'diametro', 'unidad_base' => 'mm' ),
    $context
);
tcv_extract_assert( '6' === $diameter['value'] && 'mm' === $diameter['unit'], 'diameter Ø 6 mm' );
$power = TCV_Extractor::numeric(
    array( 'id' => 7, 'nombre' => 'Potencia', 'slug' => 'potencia', 'unidad_base' => 'W' ),
    $context
);
tcv_extract_assert( '600' === $power['value'] && 'W' === $power['unit'], 'power in excerpt' );
$pieces = TCV_Extractor::numeric(
    array( 'id' => 8, 'nombre' => 'Número piezas', 'slug' => 'numero_piezas', 'unidad_base' => 'ud' ),
    $context
);
tcv_extract_assert( '24' === $pieces['value'], 'quantity in kit/set' );
$unknown = TCV_Extractor::numeric(
    array( 'id' => 9, 'nombre' => 'Carga máxima', 'slug' => 'carga_maxima', 'unidad_base' => 'kg' ),
    $context
);
tcv_extract_assert( null === $unknown, 'never infer a load limit without evidence' );
tcv_extract_assert( 0.0 === TCV_Extractor::score( 'taladro', TCV_Extractor::context( array() ) )['score'], 'no false positives on an empty site' );

$native = TCV_Extractor::context( array( 'attributes' => 'Tensión: 36 V; Motor: brushless' ) );
$native_voltage = TCV_Extractor::numeric(
    array( 'id' => 5, 'nombre' => 'Tensión', 'slug' => 'tension', 'unidad_base' => 'V' ),
    $native
);
tcv_extract_assert( '36' === $native_voltage['value'] && 'attributes' === $native_voltage['source'], 'WooCommerce native attributes provide explicit numeric evidence' );
$native_term = TCV_Extractor::controlled(
    array( 'id' => 4, 'nombre' => 'Tecnología del motor' ),
    array( array( 'id' => 12, 'label' => 'Sin escobillas' ) ),
    array( 12 => array( 'brushless' ) ),
    $native
);
tcv_extract_assert( 12 === $native_term['term_id'] && 'attributes' === $native_term['source'], 'native controlled attribute aliases are considered' );

$new_type = TCV_Extractor::discover( 'tipo', TCV_Extractor::context( array(
    'title' => 'Bosch Taladro percutor 18 V con broca', 'brands' => 'Bosch',
) ) );
tcv_extract_assert( 'Taladro percutor' === $new_type['label'] && ! empty( $new_type['new_term'] ), 'missing canonical type is proposed conservatively for review' );
tcv_extract_assert( 'review' === $new_type['state'] && 0 === $new_type['vocabulary_id'], 'new canonical terms are not automatically assigned' );
tcv_extract_assert( null === TCV_Extractor::discover( 'tipo', TCV_Extractor::context( array(
    'title' => 'Taladro percutor 18 V',
) ), array( array( 'label' => 'Taladro percutor' ) ) ), 'never duplicate an existing canonical concept' );
tcv_extract_assert( null === TCV_Extractor::discover( 'tipo', TCV_Extractor::context( array() ) ), 'empty product never receives a fabricated type' );

$plugin = file_get_contents( dirname( __DIR__ ) . '/taxocommerce-vocabulary.php' );
$bridge = file_get_contents( dirname( __DIR__ ) . '/includes/class-tcv-classifier.php' );
tcv_extract_assert( false !== strpos( $plugin, "class-tcv-extractor.php" ), 'extractor is loaded independently' );
tcv_extract_assert( false !== strpos( $bridge, 'TCV_Extractor::controlled(' ), 'attribute extraction integrated in live classifier' );
tcv_extract_assert( false !== strpos( $bridge, 'TCV_Extractor::score(' ), 'evidence scoring integrated in live classifier' );
tcv_extract_assert( false === strpos( $bridge, 'seo_classifier_build_product_context(' ), 'no monolith coupling' );
echo "Vocabulary classifier extraction smoke passed\n";
