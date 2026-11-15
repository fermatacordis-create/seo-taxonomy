<?php
/**
 * Offline contracts and behavior for private supplier CSV transfer.
 *
 * @package TaxoCommerceSuppliers
 */
$root = dirname( __DIR__ );
require_once $root . '/includes/class-tcsup-profiles-transfer.php';
$transfer = file_get_contents( $root . '/includes/class-tcsup-profiles-transfer.php' );
$profile = file_get_contents( $root . '/includes/class-tcsup-profiles.php' );
$admin = file_get_contents( $root . '/includes/class-tcsup-admin.php' );
$main = file_get_contents( $root . '/taxocommerce-suppliers.php' );
$javascript = file_get_contents( $root . '/assets/profiles-transfer.js' );
$fields = TCSUP_Profiles_Transfer::CSV_FIELDS;
tcsup_assert( 18 === count( $fields ), 'CSV carries 18 fields including supplier identity' );
tcsup_assert( 'proveedor' === $fields[0], 'supplier identity is first CSV column' );
tcsup_assert( in_array( 'observaciones', $fields, true ), 'CSV includes long private observations' );
tcsup_assert( count( $fields ) === count( array_unique( $fields ) ), 'CSV field names are unique' );
tcsup_assert( array_keys( TCSUP_Profiles_Transfer::MAX_LENGTHS ) === array_slice( $fields, 1 ), 'all fields have matching length validation' );
tcsup_assert( false !== strpos( $transfer, 'TCSUP_Profiles::supplier_in_catalogue( $supplier )' ), 'catalogue provider is verified before any profile change' );
tcsup_assert( false !== strpos( $transfer, "'unchanged'; // Do not create empty records" ), 'an untouched exported CSV does not create empty records' );
tcsup_assert( false !== strpos( $transfer, 'COUNT(DISTINCT proveedor)' ), 'export counts suppliers from the actual inventory' );
tcsup_assert( false !== strpos( $transfer, 'SELECT DISTINCT proveedor FROM %i' ), 'export pages distinct suppliers from inventory' );
tcsup_assert( false !== strpos( $transfer, 'LOCK_EX | LOCK_NB' ), 'only one upload batch may write at a time' );
tcsup_assert( false !== strpos( $transfer, 'microtime( true ) - $started' ), 'CPU work is bounded by elapsed request time' );
tcsup_assert( false !== strpos( $transfer, 'check_ajax_referer( self::ACTION_NONCE )' ), 'AJAX requests need nonce' );
tcsup_assert( false !== strpos( $transfer, 'current_user_can( TCSUP_Admin::capability() )' ), 'AJAX requests check WooCommerce admin privileges' );
tcsup_assert( false !== strpos( $transfer, 'sanitize_textarea_field' ), 'imported comments are sanitised' );
tcsup_assert( false !== strpos( $transfer, 'wp_delete_file( $real )' ), 'temporary uploads are deleted on completion or cancellation' );
tcsup_assert( false !== strpos( $transfer, "\$wpdb->update( TCSUP_Schema::profiles_table()" ), 'only the private contact table is updated' );
tcsup_assert( false !== strpos( $transfer, "\$wpdb->insert( TCSUP_Schema::profiles_table()" ), 'only the private contact table is inserted into' );
tcsup_assert( false === strpos( $transfer, 'wp_delete_post(' ), 'CSV never deletes WooCommerce products' );
tcsup_assert( false !== strpos( $profile, 'TCSUP_Profiles_Transfer::render_controls()' ), 'controls are in supplier management' );
tcsup_assert( false !== strpos( $admin, 'TCSUP_Profiles_Transfer::boot()' ), 'AJAX endpoints are registered' );
tcsup_assert( false !== strpos( $main, 'class-tcsup-profiles-transfer.php' ), 'transfer service is loaded' );
tcsup_assert( false !== strpos( $javascript, 'tcsup_profiles_import_start' ), 'browser starts private upload session' );
tcsup_assert( false !== strpos( $javascript, 'tcsup_profiles_import_batch' ), 'browser processes batches' );
tcsup_assert( false !== strpos( $javascript, 'tcsup_profiles_import_cancel' ), 'browser cancels pending batches' );
tcsup_assert( false !== strpos( $javascript, 'tcsup_profiles_export_batch' ), 'browser exports keyset batches' );
$header = new ReflectionMethod( 'TCSUP_Profiles_Transfer', 'valid_headers' );
$header->setAccessible( true );
tcsup_assert( true === $header->invoke( null, $fields ), 'exact canonical header accepted' );
tcsup_assert( false === $header->invoke( null, array_merge( $fields, array( 'invented_field' ) ) ), 'unexpected CSV column rejected' );
tcsup_assert( false === $header->invoke( null, array_slice( $fields, 1 ) ), 'missing supplier identity rejected' );
tcsup_assert( false === $header->invoke( null, array_merge( array_slice( $fields, 0, -1 ), array( 'proveedor' ) ) ), 'duplicate supplier column rejected' );
echo "Supplier bulk profiles CSV smoke passed\n";
