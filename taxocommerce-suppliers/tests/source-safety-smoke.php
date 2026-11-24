<?php
/**
 * Isolated regression for discarded supplier sources and WooCommerce purchasing.
 *
 * No WordPress or WooCommerce instance is modified.
 *
 * @package TaxoCommerceSuppliers
 */

define( 'ABSPATH', __DIR__ );
define( 'ARRAY_A', 'ARRAY_A' );

$GLOBALS['safety_rows']   = array();
$GLOBALS['safety_meta']   = array();
$GLOBALS['safety_posts']  = array();
$GLOBALS['safety_skus']   = array();
$GLOBALS['safety_other']  = array();
$GLOBALS['safety_saved']  = array();
$GLOBALS['safety_params'] = array();

function absint( $value ) { return abs( (int) $value ); }
function get_post_type( $id ) { return $GLOBALS['safety_posts'][ $id ]['type'] ?? ''; }
function get_post_status( $id ) { return $GLOBALS['safety_posts'][ $id ]['status'] ?? ''; }
function get_post_meta( $id, $key, $single = true ) { return $GLOBALS['safety_meta'][ $id ][ $key ] ?? ''; }
function update_post_meta( $id, $key, $value ) { $GLOBALS['safety_meta'][ $id ][ $key ] = $value; return true; }
function get_posts( $args ) {
	$found = array();
	foreach ( $GLOBALS['safety_posts'] as $id => $post ) {
		if ( 'product' !== $post['type'] || ! in_array( $post['status'], $args['post_status'], true ) ) {
			continue;
		}
		if ( (string) get_post_meta( $id, $args['meta_key'] ) === (string) $args['meta_value'] ) {
			$found[] = $id;
		}
	}
	return array_slice( $found, 0, $args['posts_per_page'] );
}
function wc_get_product_id_by_sku( $sku ) { return $GLOBALS['safety_skus'][ $sku ] ?? 0; }
class Safety_DB {
	public function prepare( $sql, ...$params ) {
		$GLOBALS['safety_params'] = $params;
		return 'MOCK';
	}
	public function get_results( $query, $format ) {
		$provider = $GLOBALS['safety_params'][1] ?? '';
		$external = $GLOBALS['safety_params'][2] ?? '';
		return array_values( array_filter( $GLOBALS['safety_rows'], static function ( $row ) use ( $provider, $external ) {
			return (string) $row['proveedor'] === $provider && (string) $row['proveedor_id_externo'] === $external;
		} ) );
	}
}
$GLOBALS['wpdb'] = new Safety_DB();
class TCSUP_Schema {
	public static function catalog_table() { return 'wp_suppliers'; }
}
class TCSUP_Products {
	public static function row( $id ) { return $GLOBALS['safety_rows'][ $id ] ?? false; }
	public static function sources_for_product( $id ) { return $GLOBALS['safety_other'][ $id ] ?? array(); }
}
class Safety_Product {
	private $id;
	public function __construct( $id ) { $this->id = $id; }
	public function get_id() { return $this->id; }
	public function get_status() { return get_post_status( $this->id ); }
	public function set_status( $status ) { $GLOBALS['safety_posts'][ $this->id ]['status'] = $status; }
	public function save() { $GLOBALS['safety_saved'][] = $this->id; return $this->id; }
}
function wc_get_product( $id ) { return new Safety_Product( $id ); }
function assert_safety( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, 'FAIL: ' . $message . PHP_EOL );
		exit( 1 );
	}
}
require dirname( __DIR__ ) . '/includes/class-tcsup-source-safety.php';

$row = array(
	'id' => 55, 'object_id' => 901, 'proveedor' => 'Proveedor A',
	'proveedor_id_externo' => 'EXT-1', 'sku' => 'A-EXT-1',
	'pais_origen' => 'ES', 'estado_seleccion' => 'descartado',
);
$GLOBALS['safety_rows'][55] = $row;
$GLOBALS['safety_posts'][901] = array( 'type' => 'product', 'status' => 'publish' );
$GLOBALS['safety_meta'][901] = array(
	'_seo_proveedor_catalogo_id' => 55,
	'_seo_proveedor' => 'Proveedor A',
	'_seo_proveedor_id_externo' => 'EXT-1',
	'_seo_proveedor_pais' => 'ES',
);
assert_safety( 901 === TCSUP_Source_Safety::product_for_row( $row ), 'Explicit catalogue identity matches exact WC product' );
assert_safety( TCSUP_Source_Safety::is_blocked( 901 ), 'Last discarded source prevents sales' );
assert_safety( ! TCSUP_Source_Safety::purchasable( true, new Safety_Product( 901 ) ), 'Add-to-cart is blocked' );
assert_safety( ! TCSUP_Source_Safety::visible( true, 901 ), 'Supplier-discarded product is hidden from Woo listings' );
assert_safety( ! TCSUP_Source_Safety::purchasable( false, new Safety_Product( 901 ) ), 'Existing disabled products stay disabled' );
assert_safety( TCSUP_Source_Safety::unpublish_discarded( $row ), 'Discard archives exact published product' );
assert_safety( 'draft' === get_post_status( 901 ), 'Product is unpublished, not deleted' );
assert_safety( 55 === $GLOBALS['safety_meta'][901]['_tcsup_supplier_discarded_catalog_id'], 'Product records source of withdrawal' );
assert_safety( array( 901 ) === $GLOBALS['safety_saved'], 'Product saved once, without editing other products' );

$GLOBALS['safety_posts'][901]['status'] = 'publish';
$GLOBALS['safety_saved'] = array();
$GLOBALS['safety_other'][901] = array( array( 'id' => 56, 'estado_seleccion' => 'aceptado' ) );
assert_safety( ! TCSUP_Source_Safety::is_blocked( 901 ), 'Another accepted source prevents blocking' );
assert_safety( TCSUP_Source_Safety::unpublish_discarded( $row ), 'Alternate accepted source is preserved' );
assert_safety( 'publish' === get_post_status( 901 ) && ! $GLOBALS['safety_saved'], 'No unintended unpublishing with alternate supplier' );
unset( $GLOBALS['safety_other'][901] );
$GLOBALS['safety_rows'][55]['estado_seleccion'] = 'aceptado';
assert_safety( ! TCSUP_Source_Safety::is_blocked( 901 ), 'Reaccepted supplier unblocks WC purchasing' );

$GLOBALS['safety_posts'][902] = array( 'type' => 'product', 'status' => 'publish' );
$GLOBALS['safety_meta'][902] = array(
	'_seo_proveedor' => 'Otro proveedor',
	'_seo_proveedor_id_externo' => 'EXT-1',
	'_seo_proveedor_pais' => 'ES',
);
$GLOBALS['safety_rows'][55]['estado_seleccion'] = 'descartado';
assert_safety( ! TCSUP_Source_Safety::is_blocked( 902 ), 'Unrelated supplier sharing an identifier is never blocked' );

$GLOBALS['safety_posts'][903] = array( 'type' => 'product', 'status' => 'publish' );
$GLOBALS['safety_skus']['A-EXT-1'] = 903;
$row_orphan = $row;
$row_orphan['object_id'] = null;
assert_safety( 903 === TCSUP_Source_Safety::possible_orphan( $row_orphan ), 'Legacy unlinked SKU collision is shown as warning' );
assert_safety( ! TCSUP_Source_Safety::is_blocked( 903 ), 'SKU-only match never hides unrelated WC product automatically' );
assert_safety( 901 === TCSUP_Source_Safety::product_for_row( $row_orphan ), 'Explicit catalogue metadata still resolves a row with missing object_id' );

$GLOBALS['safety_posts'][904] = array( 'type' => 'product', 'status' => 'publish' );
$GLOBALS['safety_meta'][904] = array(
	'_seo_proveedor' => 'Proveedor A',
	'_seo_proveedor_id_externo' => 'EXT-2',
	'_seo_proveedor_pais' => 'DE',
);
$GLOBALS['safety_rows'][58] = array(
	'id' => 58, 'object_id' => null, 'proveedor' => 'Proveedor A',
	'proveedor_id_externo' => 'EXT-2', 'sku' => 'V-2',
	'pais_origen' => 'DE', 'estado_seleccion' => 'descartado',
);
assert_safety( TCSUP_Source_Safety::is_blocked( 904 ), 'Unique provider/external identity blocks even when object_id is missing' );
$GLOBALS['safety_meta'][904]['_seo_proveedor_pais'] = 'FR';
assert_safety( ! TCSUP_Source_Safety::is_blocked( 904 ), 'Country-specific products cannot cross-link' );
$GLOBALS['safety_meta'][904]['_seo_proveedor_pais'] = 'DE';

$GLOBALS['safety_rows'][59] = array(
	'id' => 59, 'object_id' => null, 'proveedor' => 'Proveedor A',
	'proveedor_id_externo' => 'EXT-2', 'sku' => 'V-2-other-market',
	'pais_origen' => 'DE', 'estado_seleccion' => 'descartado',
);
assert_safety( ! TCSUP_Source_Safety::is_blocked( 904 ), 'Duplicate supplier identity is ambiguous and never blocked' );
assert_safety( 0 === TCSUP_Source_Safety::product_for_row( $GLOBALS['safety_rows'][58] ), 'Ambiguous direct source match refuses unpublishing' );

$GLOBALS['safety_posts'][905] = array( 'type' => 'product', 'status' => 'publish' );
assert_safety( ! TCSUP_Source_Safety::is_blocked( 905 ), 'Generic WooCommerce product without supplier link is not affected' );
$GLOBALS['safety_rows'][55]['object_id'] = 999;
assert_safety( ! TCSUP_Source_Safety::is_blocked( 901 ), 'Conflicting catalogue object_id does not withdraw another WooCommerce product' );
assert_safety( 0 === TCSUP_Source_Safety::product_for_row( $GLOBALS['safety_rows'][55] ), 'Conflicting row/product linkage is not considered verified' );
$GLOBALS['safety_rows'][55]['object_id'] = 901;

$bootstrap_source = file_get_contents( dirname( __DIR__ ) . '/taxocommerce-suppliers.php' );
$products_source  = file_get_contents( dirname( __DIR__ ) . '/includes/class-tcsup-products.php' );
$admin_source     = file_get_contents( dirname( __DIR__ ) . '/includes/class-tcsup-admin.php' );
assert_safety( false !== strpos( $bootstrap_source, "'woocommerce_is_purchasable'" ), 'WooCommerce purchase safety filter is actually registered' );
assert_safety( false !== strpos( $bootstrap_source, "'woocommerce_product_is_visible'" ), 'WooCommerce catalogue visibility filter is registered' );
assert_safety( false !== strpos( $products_source, 'TCSUP_Source_Safety::unpublish_discarded( $row )' ), 'Discard action reconciles the WooCommerce publication state' );
assert_safety( false !== strpos( $admin_source, 'TCSUP_Source_Safety::possible_orphan( $row )' ), 'Supplier inventory exposes unlinked published SKU warnings' );
echo "Suppliers rejected-source sale safety regression: PASS\n";
