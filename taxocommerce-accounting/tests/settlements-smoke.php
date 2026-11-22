<?php
/**
 * Offline settlement checks: no real database, bank account or WordPress writes.
 *
 * @package TaxoCommerceAccounting
 */
defined( 'ABSPATH' ) || define( 'ABSPATH', __DIR__ . '/' );
if ( ! function_exists( 'add_action' ) ) {
    function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {}
}
function tca_input( $key ) { return ''; }
require_once dirname( __DIR__ ) . '/includes/class-tca-settlements.php';

function tca_settlement_assert( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, 'Settlement FAIL: ' . $message . PHP_EOL );
        exit( 1 );
    }
}
tca_settlement_assert( 'in' === TCA_Settlements::direction( 'sale' ), 'Received sale is a money-in movement' );
foreach ( array( 'expense', 'purchase', 'asset', 'intra', 'refund' ) as $kind ) {
    tca_settlement_assert( 'out' === TCA_Settlements::direction( $kind ), 'Outgoing entry is a money-out movement: ' . $kind );
}
tca_settlement_assert( 13.80 === TCA_Settlements::outstanding( 362.57, 348.77 ), 'Outstanding balance supports cents rounding' );
tca_settlement_assert( 1.25 === round( 362.57 - 348.77 - 12.55, 2 ), 'Confirmed Satkit payment follows earlier operating expenses' );
tca_settlement_assert( true === TCA_Settlements::confirmed_from_form( 123 ), 'An unchecked invoice never creates a payment' );
tca_settlement_assert( 24.20 === TCA_Settlements::outstanding( 24.20, 0 ), 'Unpaid invoice remains fully outstanding' );
tca_settlement_assert( 14.20 === TCA_Settlements::outstanding( 24.20, 10 ), 'Partial payment decreases outstanding' );
tca_settlement_assert( 0.0 === TCA_Settlements::outstanding( 24.20, 24.20 ), 'Fully paid invoice leaves zero outstanding' );
tca_settlement_assert( 0.0 === TCA_Settlements::outstanding( 24.20, 30 ), 'Overpayment does not create negative available payment' );
tca_settlement_assert( 24.20 === TCA_Settlements::outstanding( -24.20, 0 ), 'Signed WooCommerce refund uses absolute payout' );
$source = file_get_contents( dirname( __DIR__ ) . '/includes/class-tca-settlements.php' );
tca_settlement_assert( false !== strpos( $source, "check_admin_referer( 'tca_settle_'"), 'Payment handler verifies nonce' );
tca_settlement_assert( false !== strpos( $source, "current_user_can( 'manage_woocommerce' )" ), 'Payment handler requires WooCommerce capability' );
tca_settlement_assert( false !== strpos( $source, 'FOR UPDATE' ), 'Entry lock prevents concurrent overpayment' );
tca_settlement_assert( false !== strpos( $source, 'UNIQUE KEY request_key' ), 'Idempotency constraint prevents retry duplication' );
tca_settlement_assert( false !== strpos( $source, 'ROLLBACK' ) && false !== strpos( $source, 'COMMIT' ), 'Payment and link share one transaction' );
tca_settlement_assert( false === strpos( $source, "update( tca_table()" ), 'Settlement never edits approved fiscal entries' );
tca_settlement_assert( false !== strpos( $source, "check_admin_referer( 'tca_bulk_paid_'"), 'Bulk payments have a dedicated nonce' );
tca_settlement_assert( false !== strpos( $source, "name=\"payments_verified\""), 'Bulk posting requires explicit confirmation' );
tca_settlement_assert( false !== strpos( $source, "name=\"entry_ids[]\""), 'Historical payments can be selected individually' );
tca_settlement_assert( false !== strpos( $source, "payment_dates[" ), 'Real payment dates can be corrected before posting' );
tca_settlement_assert( false !== strpos( $source, "has_unlinked_manual_movement" ), 'Bulk reconciliation avoids matching old unlinked manual payment' );
tca_settlement_assert( false !== strpos( $source, 'self::outstanding( $entry->amount_total' ), 'Bulk reconciliation only posts outstanding balances' );
tca_settlement_assert( false !== strpos( $source, "'sale', 'refund'," ), 'Bulk reconciliation includes customer receipts and refund payouts' );
tca_settlement_assert( false !== strpos( $source, 'self::direction( $entry->kind )' ), 'Manual duplicate detection uses the correct cash direction' );
tca_settlement_assert( false !== strpos( $source, 'function maybe_auto_receipt(' ), 'WooCommerce receipt automation exists but remains opt-in' );
tca_settlement_assert( false !== strpos( $source, "get_date_paid" ), 'Automatic receipts require an actual WooCommerce payment date' );
tca_settlement_assert( false !== strpos( $source, 'tca_sale_invoice_mismatch( $entry )' ), 'Automatic receipt is blocked on invoice / order mismatch' );
tca_settlement_assert( false !== strpos( $source, "name=\"tca_payment_confirmed\""), 'New invoices have optional paid checkbox' );
$main = file_get_contents( dirname( __DIR__ ) . '/taxocommerce-accounting.php' );
tca_settlement_assert( false !== strpos( $main, 'TCA_Settlements::confirmed_from_form( $saved_id )' ), 'Manual invoices post confirmed payments on save' );
tca_settlement_assert( false !== strpos( $main, 'TCA_Settlements::confirmed_from_form( $id )' ), 'Validated invoices can post confirmed payments' );
tca_settlement_assert( false !== strpos( $main, 'TCA_Settlements::maybe_auto_receipt( $order, $entry, $settings )' ), 'Paid order import creates a linked bank receipt only when configured' );
tca_settlement_assert( false !== strpos( $main, "name=\"auto_receipts_account_id\"" ), 'Receipt account is selected explicitly' );
tca_settlement_assert( false !== strpos( $main, "name=\"auto_receipts_since\"" ), 'Receipt automation has a configurable date cutoff' );
$treasury = file_get_contents( dirname( __DIR__ ) . '/includes/class-tca-treasury.php' );
tca_settlement_assert( false !== strpos( $treasury, 'TCA_Settlements::render_bulk( $year );' ), 'Treasury exposes historical reconciliation' );
echo "TaxoCommerce Accounting settlement smoke: PASS\n";
