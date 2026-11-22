<?php
/**
 * Regression for the append-only page CSV import.
 *
 * Run: php taxocommerce-import-export/tests/page-append-csv-smoke.php
 */
define( 'ABSPATH', __DIR__ . '/' );
class WP_Error {
	private $code;
	public function __construct( $code ) { $this->code = $code; }
	public function get_error_code() { return $this->code; }
}
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function __( $text, $domain = '' ) { return $text; }
function absint( $value ) { return abs( (int) $value ); }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_-]/', '', strtolower( (string) $value ) ); }
function sanitize_title( $value ) { return strtolower( trim( (string) $value ) ); }
function wp_kses_post( $value ) { return strip_tags( $value, '<p><a><strong>' ); }
function get_post( $id ) { return $GLOBALS['append_posts'][ $id ] ?? null; }
function wp_update_post( $data, $wp_error = false ) {
	$GLOBALS['append_updates'][] = $data;
	$GLOBALS['append_posts'][ $data['ID'] ]->post_content = $data['post_content'];
	return $data['ID'];
}
require_once dirname( __DIR__ ) . '/includes/class-taxocommerce-import-export-csv.php';
$csv = new TaxoCommerce_Import_Export_CSV();
$slug = 'taxocommerce-system-check';
$url = 'https://asociacionfocazul.com/wp-content/uploads/taxocommerce-core/plugins/taxocommerce-system-check.zip';
$fragment = '<p><strong>Descargar plugin:</strong> <a href="' . $url . '">ZIP instalable (versión 0.2.15)</a></p>';
$GLOBALS['append_posts'] = array( 20822 => (object) array(
	'ID' => 20822, 'post_type' => 'page', 'post_name' => $slug,
	'post_title' => 'Protected title', 'post_content' => '<h2>Original features</h2>',
	'post_status' => 'publish', 'post_excerpt' => 'Untouched',
) );
$GLOBALS['append_updates'] = array();
$row = array( 'record_type' => 'page', 'id' => '20822', 'slug' => $slug, 'content_append' => $fragment );
function tc_assert_append( $condition, $message ) {
	if ( ! $condition ) { fwrite( STDERR, $message . "\n" ); exit( 1 ); }
}
tc_assert_append( true === $csv->import_row_for_batch( $row, array( 'type' => 'page' ) ), 'Expected first append to pass' );
tc_assert_append( 1 === count( $GLOBALS['append_updates'] ), 'Expected one post write' );
tc_assert_append( array( 'ID', 'post_content' ) === array_keys( $GLOBALS['append_updates'][0] ), 'Changed fields beyond content' );
tc_assert_append( false !== strpos( $GLOBALS['append_posts'][20822]->post_content, 'Original features' ), 'Lost previous page content' );
tc_assert_append( true === $csv->import_row_for_batch( $row, array( 'type' => 'page' ) ), 'Expected idempotent retry' );
tc_assert_append( 1 === count( $GLOBALS['append_updates'] ), 'Retry wrote a duplicate' );
$GLOBALS['append_posts'][20822]->post_content = '<h2>Original features</h2><p><a href="' . $url . '">Download ZIP</a></p>';
tc_assert_append( true === $csv->import_row_for_batch( $row, array( 'type' => 'page' ) ), 'Existing link should pass' );
tc_assert_append( 1 === count( $GLOBALS['append_updates'] ), 'Existing URL was duplicated' );
tc_assert_append( is_wp_error( $csv->import_row_for_batch( array_merge( $row, array( 'slug' => 'wrong-slug' ) ), array( 'type' => 'page' ) ) ), 'Mismatched slug accepted' );
tc_assert_append( is_wp_error( $csv->import_row_for_batch( array_merge( $row, array( 'title' => 'Do not touch' ) ), array( 'type' => 'page' ) ) ), 'Unexpected column accepted' );
tc_assert_append( is_wp_error( $csv->import_row_for_batch( array_merge( $row, array( 'content_append' => '  ' ) ), array( 'type' => 'page' ) ) ), 'Empty fragment accepted' );
tc_assert_append( 1 === count( $GLOBALS['append_updates'] ), 'Validation failure wrote changes' );
echo "PASS: safe append page CSV, idempotence and field protection\n";
