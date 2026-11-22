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
    'class-tca-expense-csv.php',
    "TCA_Treasury::install_schema()",
    'TCA_Treasury::render( $year )',
    "'treasury' => 'Treasury'",
    "'Treasury' => __( 'Treasury', 'taxocommerce-accounting' )",
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
$css = file_get_contents( dirname( __DIR__ ) . '/assets/admin.css' );
if ( false === $css
    || false === strpos( $source, "add_action( 'admin_enqueue_scripts', 'tca_enqueue_admin_styles' )" )
    || false === strpos( $source, "plugin_dir_url( __FILE__ ) . 'assets/admin.css'" )
    || false === strpos( $source, 'wrap tca-accounting-admin' )
    || false === strpos( $css, '.tca-accounting-admin .nav-tab-wrapper .nav-tab.nav-tab-active' )
    || false === strpos( $css, 'color: #fff;' ) ) {
    fwrite( STDERR, "Accounting active tab style contract failed.\n" );
    exit( 1 );
}

$preview = file_get_contents( dirname( __DIR__ ) . '/assets/purchase-preview.js' );
$required = array(
    "'deductible_pct' => array( 'Deductible VAT percentage (review first)', 'number', '' )",
    "'amount_tax', 'deductible_pct' ), true ) ) echo ' required'",
    'tca-purchase-total',
    'tca-purchase-deductible',
    'tca_needs_zero_vat_confirmation( $entry )',
    "'1' !== tca_input( 'zero_vat_reviewed' )",
    'WooCommerce imported this sale with zero VAT',
    "plugin_dir_url( __FILE__ ) . 'assets/purchase-preview.js'",
);
foreach ( $required as $contract ) {
    if ( false === strpos( $source, $contract ) ) {
        fwrite( STDERR, "Accounting purchase VAT guidance missing: " . $contract . "\n" );
        exit( 1 );
    }
}
if ( false === $preview || false === strpos( $preview, 'Math.round' ) || false === strpos( $preview, 'vat * percent / 100' ) ) {
    fwrite( STDERR, "Accounting purchase invoice preview contract failed.\n" );
    exit( 1 );
}
require __DIR__ . '/treasury-smoke.php';
require __DIR__ . '/expense-csv-smoke.php';
echo "TaxoCommerce Accounting isolated smoke: PASS\n";

