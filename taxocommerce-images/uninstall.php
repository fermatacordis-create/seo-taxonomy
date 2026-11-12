<?php
/**
 * TaxoCommerce Images uninstall.
 *
 * @package TaxoCommerceImages
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/*
 * Only plugin-owned configuration and derived audit state are removed.
 * WordPress Media attachments and files are never deleted on uninstall.
 */
delete_option( 'taxocommerce_images_version' );
delete_option( 'taxocommerce_images_settings' );
delete_option( 'taxocommerce_images_audit_state' );
delete_option( 'taxocommerce_images_audit_lock' );
delete_option( 'taxocommerce_images_disabled_thumbnail_sizes' );
delete_option( 'taxocommerce_images_cpu_limit' );
delete_option( 'taxocommerce_images_cpu_sample' );
delete_option( 'taxocommerce_images_cpu_last' );
delete_option( 'taxocommerce_images_cleanup_usage_window' );

delete_option( 'taxocommerce_images_work_priority' );
foreach ( array( 'cleanup', 'labels' ) as $taxocommerce_images_process ) {
	delete_option( 'taxocommerce_images_adaptive_batch_' . $taxocommerce_images_process );
	delete_option( 'taxocommerce_images_load_sample_' . $taxocommerce_images_process );
}
