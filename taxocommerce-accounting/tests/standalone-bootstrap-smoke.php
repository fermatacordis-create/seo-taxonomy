<?php
/**
 * Isolated static regression smoke for Accounting.
 * No live WordPress, stores, external APIs or order writes.
 */
$source = file_get_contents( dirname( __DIR__ ) . '/taxocommerce-accounting.php' );
if ( false === $source ) { exit( 1 ); }
$contracts = array(
    'Plugin Name: TaxoCommerce Accounting',
    'function tca_install()',
    'function tca_sync_sales(',
    'function tca_summary(',
    'function tca_autocheck(',
    'function tca_export()',
    'check_admin_referer( ',
    'manage_woocommerce',
    'wc_get_orders( ',
    "'status' => 'draft'",
    'function tca_valid_date(',
    'get_date_created()',
    'get_refunds()',
);
foreach ( $contracts as $contract ) {
    if ( false === strpos( $source, $contract ) ) {
        fwrite( STDERR, 'Missing required accounting contract: ' . $contract . PHP_EOL );
        exit( 1 );
    }
}
foreach ( array( 'wp_remote_post(', 'wp_remote_get(', 'wp_delete_post(', 'wc_delete_order(', 'woocommerce_order_status_changed' ) as $unsafe ) {
    if ( false !== strpos( $source, $unsafe ) ) {
        fwrite( STDERR, 'Unexpected operation: ' . $unsafe . PHP_EOL );
        exit( 1 );
    }
}
echo "TaxoCommerce Accounting isolated smoke: PASS\n";
