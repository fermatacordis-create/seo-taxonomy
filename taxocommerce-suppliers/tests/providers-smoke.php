<?php
/**
 * Offline contract checks for inventory-backed supplier contacts.
 *
 * @package TaxoCommerceSuppliers
 */
$root = dirname( __DIR__ );
$profiles = file_get_contents( $root . '/includes/class-tcsup-profiles.php' );
$schema = file_get_contents( $root . '/includes/class-tcsup-schema.php' );
$admin = file_get_contents( $root . '/includes/class-tcsup-admin.php' );
$main = file_get_contents( $root . '/taxocommerce-suppliers.php' );

tcsup_assert( false !== strpos( $profiles, 'class TCSUP_Profiles' ), 'supplier profile service exists' );
tcsup_assert( false !== strpos( $schema, 'seo_proveedores_fichas' ), 'separate private supplier profiles table' );
tcsup_assert( false !== strpos( $schema, 'UNIQUE KEY proveedor (proveedor)' ), 'exactly one profile per catalogue supplier' );
tcsup_assert( false !== strpos( $schema, 'self::profiles_table()' ), 'profile installation and migration' );
tcsup_assert( false !== strpos( $profiles, 'SELECT proveedor FROM %i WHERE proveedor=%s' ), 'supplier identity must be checked against inventory' );
tcsup_assert( false !== strpos( $profiles, 'COUNT(DISTINCT proveedor)' ), 'dynamic inventory-based listing' );
tcsup_assert( false !== strpos( $profiles, 'LEFT JOIN %i AS p ON p.proveedor=c.proveedor' ), 'missing records remain visible after imports/deletion' );
tcsup_assert( false !== strpos( $profiles, 'GROUP BY proveedor' ), 'one directory entry per supplier' );
tcsup_assert( false !== strpos( $profiles, "check_admin_referer( 'tcsup_profile_save' )" ), 'profile update verifies nonce before reading payload' );
tcsup_assert( false !== strpos( $profiles, "check_admin_referer( 'tcsup_profile_delete' )" ), 'profile deletion verifies nonce before reading payload' );
tcsup_assert( false === strpos( $profiles, "private static function posted_supplier()" ), 'no unverified request in shared supplier helper' );
tcsup_assert( false !== strpos( $profiles, "TCSUP_Admin::capability()" ), 'profile administration permissions' );
tcsup_assert( false !== strpos( $profiles, 'sanitize_textarea_field' ), 'private notes sanitised' );
tcsup_assert( false !== strpos( $profiles, 'esc_textarea' ), 'private notes escaped' );
tcsup_assert( false !== strpos( $profiles, "\$wpdb->insert( TCSUP_Schema::profiles_table()" ), 'record creation limited to profile table' );
tcsup_assert( false !== strpos( $profiles, "\$wpdb->update( TCSUP_Schema::profiles_table()" ), 'record editing limited to profile table' );
tcsup_assert( false !== strpos( $profiles, "\$wpdb->delete( TCSUP_Schema::profiles_table()" ), 'record deletion limited to profile table' );
tcsup_assert( false === strpos( $profiles, 'DELETE FROM seo_proveedores_productos' ), 'no inventory deletion in supplier directory' );
tcsup_assert( false === strpos( $profiles, 'wp_delete_post(' ), 'no WooCommerce product deletion in directory' );
tcsup_assert( false !== strpos( $admin, "'providers' => __( 'Supplier management'" ), 'administration tab registered' );
tcsup_assert( false !== strpos( $admin, "TCSUP_Profiles::render()" ), 'directory view rendered' );
tcsup_assert( false !== strpos( $admin, "TCSUP_Profiles::boot()" ), 'record actions registered' );
tcsup_assert( false !== strpos( $main, 'class-tcsup-profiles.php' ), 'profile service loaded in plugin' );
