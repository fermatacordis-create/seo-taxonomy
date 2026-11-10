<?php
/**
 * TaxoCommerce Core uninstall.
 *
 * @package TaxoCommerceCore
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/*
 * Only Core-owned pilot options are removed.
 * Canonical WordPress/WooCommerce content is never deleted.
 */
delete_option( 'taxocommerce_core_version' );
delete_option( 'taxocommerce_core_log_limit' );
delete_option( 'taxocommerce_core_log_entries' );
delete_option( 'taxocommerce_core_menu_id' );
delete_option( 'taxocommerce_core_menu_location_backup' );


/*
 * Clean only Core's developer-excerpt cache. Never touch another TaxoCommerce
 * extension's URL or the store's posts, orders or navigation.
 */
$taxocommerce_core_headers = get_file_data(
    __DIR__ . '/taxocommerce-core.php',
    array( 'uri' => 'Plugin URI' ),
    'plugin'
);
$taxocommerce_core_uri = isset( $taxocommerce_core_headers['uri'] ) ? esc_url_raw( $taxocommerce_core_headers['uri'], array( 'https' ) ) : '';
if ( $taxocommerce_core_uri ) {
    $taxocommerce_core_hash = md5( $taxocommerce_core_uri );
    delete_option( 'tc_official_excerpt_good_' . $taxocommerce_core_hash );
    delete_transient( 'tc_official_excerpt_v2_' . $taxocommerce_core_hash );
    delete_transient( 'tc_official_excerpt_' . $taxocommerce_core_hash );
}
