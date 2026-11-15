<?php
/**
 * Offline transfer validation; does not modify any database or product.
 */
defined( 'ABSPATH' ) || define( 'ABSPATH', dirname( __DIR__, 3 ) . '/' );
require_once dirname( __DIR__ ) . '/includes/class-tcsup-history-transfer.php';

$fields = TCSUP_History_Transfer::CSV_FIELDS;
tcsup_assert( count( $fields ) === count( array_unique( $fields ) ), 'CSV export field names are unique' );
foreach ( array( 'catalog_id', 'proveedor', 'proveedor_id_externo', 'pais_origen', 'source_row_key', 'fecha', 'precio_venta', 'origen' ) as $required ) {
	tcsup_assert( in_array( $required, $fields, true ), 'CSV includes identity and snapshot field: ' . $required );
}

$decimal = new ReflectionMethod( 'TCSUP_History_Transfer', 'decimal' );
$decimal->setAccessible( true );
tcsup_assert( '1.200000' === $decimal->invoke( null, '1.2' ), 'prices preserve six decimal places' );
tcsup_assert( null === $decimal->invoke( null, '' ), 'empty price means NULL' );
tcsup_assert( false === $decimal->invoke( null, '1.2x' ), 'invalid numeric values rejected' );
tcsup_assert( false === $decimal->invoke( null, '-10' ), 'negative prices rejected' );

$date = new ReflectionMethod( 'TCSUP_History_Transfer', 'valid_date' );
$date->setAccessible( true );
tcsup_assert( true === $date->invoke( null, '2026-11-11 16:05:10' ), 'valid historical timestamp' );
tcsup_assert( false === $date->invoke( null, '2026-02-30 10:00:00' ), 'invalid calendar date rejected' );
tcsup_assert( false === $date->invoke( null, '2026-11-11' ), 'full timestamp is mandatory' );

echo "Bulk price history transfer smoke passed\n";
