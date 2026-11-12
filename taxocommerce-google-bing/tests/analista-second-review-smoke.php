<?php
/** Isolated no-network smoke tests for the editorial date second review. */
define( 'ABSPATH', __DIR__ );
function sanitize_key( $key ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) ); }
function absint( $value ) { return abs( (int) $value ); }
function get_post_modified_time( $format, $gmt, $id ) { return '2026-11-07'; }
function check_case( $condition, $name ) {
    if ( ! $condition ) { fwrite( STDERR, "FAIL: {$name}\n" ); exit( 1 ); }
}
require dirname( __DIR__ ) . '/includes/analysis/analista-segunda-revision.php';
$base = array(
    'entity'        => array( 'type' => 'product', 'id' => 129052 ),
    'issues'        => array( 'Cobertura textual baja: 90 palabras.', 'Descripción corta insuficiente.' ),
    'sources'       => array( 'WordPress', 'Search Console' ),
    'work_bucket'   => 'HACER_DESPUES',
    'action_type'   => 'MEJORAR_COBERTURA',
    'status'        => 'proposed',
    'primary_action'=> array( 'type' => 'MEJORAR_COBERTURA', 'executable' => true ),
    'task_decision' => array( 'work_bucket' => 'HACER_DESPUES', 'intervention_type' => 'MEJORAR_COBERTURA' ),
    'execution_gate'=> array( 'bucket' => 'HACER_DESPUES', 'final_bucket' => 'HACER_DESPUES' ),
);
$out = seo_analista_editorial_date_review( $base, '2026-10-30' );
check_case( 'VIGILAR' === $out['work_bucket'], 'edited after GSC data -> watch' );
check_case( 'VIGILAR' === $out['task_decision']['work_bucket'] && 'VIGILAR' === $out['execution_gate']['final_bucket'], 'same bucket everywhere' );
check_case( 'VIGILAR' === $out['action_type'] && 'VIGILAR' === $out['task_decision']['intervention_type'], 'same intervention everywhere' );
check_case( false === $out['primary_action']['executable'] && false === $out['task_decision']['execution_ready'], 'no duplicated executable action' );
check_case( false === $out['date_review']['verified_fix'], 'modification not falsely marked verified' );
check_case( false !== strpos( $out['reason'], '2026-10-30' ), 'reason includes GSC date' );
check_case( $base === seo_analista_editorial_date_review( $base, '2026-11-09' ), 'fresh GSC evidence not discarded' );
check_case( $base === seo_analista_editorial_date_review( $base, '' ), 'missing signal date not discarded' );
$unrelated = $base;
$unrelated['issues'] = array( 'Página 404' );
check_case( $unrelated === seo_analista_editorial_date_review( $unrelated, '2026-10-30' ), 'technical incident kept' );
$unrelated = $base;
$unrelated['sources'] = array( 'WordPress' );
check_case( $unrelated === seo_analista_editorial_date_review( $unrelated, '2026-10-30' ), 'no Google source kept' );
$unrelated = $base;
$unrelated['status'] = 'executed';
$unrelated['work_bucket'] = 'SIN_ACCION';
check_case( $unrelated === seo_analista_editorial_date_review( $unrelated, '2026-10-30' ), 'executed actions retain existing workflow state' );
$category = $base;
$category['entity'] = array( 'type' => 'category', 'id' => 57372 );
$category['issues'] = array( 'Excerpt de categoría insuficiente.', 'Categoría sin etiquetas SEO.' );
$category['content_modified_at'] = '2026-11-08 13:20:00';
check_case( 'VIGILAR' === seo_analista_editorial_date_review( $category, '2026-10-30' )['work_bucket'], 'category with trusted local editorial timestamp' );
unset( $category['content_modified_at'] );
check_case( $category === seo_analista_editorial_date_review( $category, '2026-10-30' ), 'category without modification date not presumed fixed' );
$noissue = $base;
$noissue['issues'] = array();
check_case( $noissue === seo_analista_editorial_date_review( $noissue, '2026-10-30' ), 'demand opportunity without defect still visible' );
echo "Analista editorial second review: OK\n";
