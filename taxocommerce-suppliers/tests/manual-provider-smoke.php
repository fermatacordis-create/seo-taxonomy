<?php
/**
 * Offline supplier-directory behavior using a minimal WordPress DB stub.
 *
 * @package TaxoCommerceSuppliers
 */
defined( 'ABSPATH' ) || define( 'ABSPATH', dirname( __DIR__ ) . '/' );
defined( 'ARRAY_A' ) || define( 'ARRAY_A', 'ARRAY_A' );
if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $value ) {
		return trim( strip_tags( (string) $value ) );
	}
}

if ( ! function_exists( 'absint' ) ) {
	function absint( $value ) {
		return abs( (int) $value );
	}
}

final class TCSUP_Manual_Provider_Test_DB {
	public $prefix = 'wp_';
	public $catalogue = array( 'Imported Supplier' => true );
	public $manual = array( 'Manual Supplier' => array( 'proveedor' => 'Manual Supplier' ) );
	public $last_query = '';
	public function prepare( $query, ...$values ) {
		return array( $query, $values );
	}
	public function get_var( $prepared ) {
		$this->last_query = $prepared[0];
		if ( false !== strpos( $prepared[0], 'COUNT(*)' ) ) {
			return 2;
		}
		$name = $prepared[1][1] ?? '';
		return isset( $this->catalogue[ $name ] ) ? $name : null;
	}
	public function get_row( $prepared, $mode ) {
		$name = $prepared[1][1] ?? '';
		return $this->manual[ $name ] ?? null;
	}
	public function get_results( $prepared, $mode ) {
		$this->last_query = $prepared[0];
		return array(
			array( 'proveedor' => 'Imported Supplier', 'productos' => 5 ),
			array( 'proveedor' => 'Manual Supplier', 'productos' => 0 ),
		);
	}
	public function esc_like( $value ) {
		return addcslashes( $value, '_%\\' );
	}
}

require_once dirname( __DIR__ ) . '/includes/class-tcsup-schema.php';
require_once dirname( __DIR__ ) . '/includes/class-tcsup-profiles.php';
global $wpdb;
$wpdb = new TCSUP_Manual_Provider_Test_DB();

tcsup_assert( 'Imported Supplier' === TCSUP_Profiles::supplier_in_directory( 'Imported Supplier' ), 'catalogue supplier stays accessible' );
tcsup_assert( 'Manual Supplier' === TCSUP_Profiles::supplier_in_directory( 'Manual Supplier' ), 'manual supplier accessible without imported products' );
tcsup_assert( '' === TCSUP_Profiles::supplier_in_catalogue( 'Manual Supplier' ), 'manual supplier has no catalogue entry' );
tcsup_assert( '' === TCSUP_Profiles::supplier_in_directory( 'Unknown Supplier' ), 'unknown suppliers are not implicitly created' );
tcsup_assert( '' === TCSUP_Profiles::supplier_in_directory( str_repeat( 'a', 191 ) ), 'overlong identity rejected' );

$listing = new ReflectionMethod( 'TCSUP_Profiles', 'listing' );
$listing->setAccessible( true );
$rows = $listing->invoke( null, '', 1 );
tcsup_assert( 2 === $rows['total'] && 2 === count( $rows['rows'] ), 'directory includes both sources' );
tcsup_assert( 0 === $rows['rows'][1]['productos'], 'manual supplier appears with zero products' );
tcsup_assert( false !== strpos( $wpdb->last_query, 'UNION SELECT proveedor FROM %i' ), 'directory SQL combines imported and manual suppliers' );
echo "Manual supplier identity smoke passed\n";
