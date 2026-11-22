<?php
/**
 * Standalone review-navigation regression: pending vs real incidents and safe links.
 *
 * @package TaxoCommerce_Accounting
 */
define( 'ABSPATH', __DIR__ );
function __( $message, $domain = '' ) { return $message; }
function absint( $number ) { return abs( (int) $number ); }
function esc_html( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function esc_html__( $message, $domain = '' ) { return esc_html( __( $message, $domain ) ); }
function esc_url( $value ) { return esc_html( $value ); }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $value ) ); }
function sanitize_text_field( $value ) { return trim( (string) $value ); }
function wp_unslash( $value ) { return $value; }
function tca_sale_invoice_mismatch( $entry ) { return ! empty( $entry->mismatch ); }
function tca_needs_zero_vat_confirmation( $entry ) { return ! empty( $entry->zero_vat ); }
function tca_admin_url( $tab, $args = array() ) {
    return '/wp-admin/admin.php?' . http_build_query( array_merge( array( 'page' => 'taxocommerce-accounting', 'tab' => $tab ), $args ) );
}
class TCA_Issued_Invoice {
    public static function for_order( $id ) { return 99 === (int) $id ? 'FAC-99' : ''; }
}
class TCA_Corrections {
    public static function tab_for( $kind ) {
        return array( 'sale' => 'sales', 'refund' => 'sales', 'purchase' => 'received', 'expense' => 'expenses', 'asset' => 'assets', 'intra' => 'intra' )[ $kind ] ?? 'summary';
    }
}
require dirname( __DIR__ ) . '/includes/class-tca-review-navigation.php';
function tca_test_contains( $text, $fragment ) { return false !== strpos( $text, $fragment ); }
function verify_review( $condition, $label ) {
    if ( ! $condition ) {
        fwrite( STDERR, 'FAIL: ' . $label . PHP_EOL );
        exit( 1 );
    }
}
function make_entry( $id, $month, $kind, $status, $treatment, $props = array() ) {
    return (object) array_merge( array(
        'id' => $id,
        'source_id' => $id,
        'kind' => $kind,
        'source' => 'manual',
        'status' => $status,
        'treatment' => $treatment,
        'fiscal_date' => $month . '-15',
        'document_no' => 'DOC-' . $id,
        'concept' => 'Entry ' . $id,
        'mismatch' => false,
        'zero_vat' => false,
    ), $props );
}
$pending = make_entry( 10, '2027-01', 'sale', 'draft', 'domestic', array( 'source' => 'woocommerce', 'document_no' => '' ) );
$correct = make_entry( 11, '2027-02', 'sale', 'validated', 'domestic' );
$foreign = make_entry( 12, '2027-03', 'purchase', 'validated', 'intra' );
$zero = make_entry( 13, '2027-01', 'sale', 'validated', 'domestic', array( 'source' => 'woocommerce', 'zero_vat' => true ) );
$mismatch = make_entry( 14, '2027-01', 'sale', 'draft', 'domestic', array( 'source' => 'woocommerce', 'mismatch' => true ) );
$expense = make_entry( 15, '2027-02', 'expense', 'draft', 'review', array( 'concept' => '<script>alert(1)</script>' ) );
$review = make_entry( 16, '2027-03', 'expense', 'validated', 'review' );
$missing = make_entry( 17, '2027-04', 'sale', 'validated', 'domestic', array( 'source' => 'woocommerce', 'document_no' => '' ) );
$linked = make_entry( 18, '2027-04', 'sale', 'draft', 'domestic', array( 'source' => 'woocommerce', 'document_no' => '', 'source_id' => 99 ) );

verify_review( TCA_Review_Navigation::pending( $pending ), 'draft requires validation' );
verify_review( ! TCA_Review_Navigation::pending( $correct ), 'validated domestic is clear' );
verify_review( ! TCA_Review_Navigation::pending( $foreign ), 'validated non-domestic not false-pending' );
verify_review( TCA_Review_Navigation::pending( $review ), 'validated treatment review needs correction' );
verify_review( TCA_Review_Navigation::inspect( $mismatch )['error'], 'invoice mismatch is a real error' );
verify_review( TCA_Review_Navigation::inspect( $zero )['incident'] && ! TCA_Review_Navigation::inspect( $zero )['error'], 'zero VAT is warning, not inferred error' );
verify_review( TCA_Review_Navigation::inspect( $missing )['incident'], 'missing WooCommerce invoice is flagged' );
verify_review( ! TCA_Review_Navigation::inspect( $linked )['incident'], 'linked issued invoice is not a false missing-document warning' );
verify_review( ! TCA_Review_Navigation::inspect( $expense )['incident'], 'draft expense is not an error' );

$rows = array( $pending, $correct, $foreign, $zero, $mismatch, $expense, $review, $missing, $linked );
$issues = TCA_Review_Navigation::incidents_by_month( $rows );
verify_review( count( $issues['2027-01'] ) === 3, 'monthly incidents counted separately from pending' );
verify_review( ! isset( $issues['2027-02'] ), 'normal February records have no incidents' );
verify_review( tca_test_contains( TCA_Review_Navigation::entry_url( $expense ), 'focus_entry=15#tca-entry-15' ), 'draft opens inline validation' );
verify_review( tca_test_contains( TCA_Review_Navigation::entry_url( $review ), 'edit_entry=16#tca-edit-entry-16' ), 'validated tax review opens correction' );
verify_review( tca_test_contains( TCA_Review_Navigation::entry_url( $mismatch ), 'edit_entry=14#tca-edit-entry-14' ), 'mismatch opens correction' );
verify_review( tca_test_contains( TCA_Review_Navigation::summary_url( '2027-02', 'pending', array( array( 'entry' => $expense, 'state' => TCA_Review_Navigation::inspect( $expense ) ) ) ), 'focus_entry=15' ), 'single pending goes direct' );
verify_review( tca_test_contains( TCA_Review_Navigation::summary_url( '2027-01', 'incident', $issues['2027-01'] ), 'review_month=2027-01' ), 'multiple incidents opens month queue' );

$_GET['review_month'] = '2027-02';
$_GET['review_type'] = 'pending';
ob_start();
TCA_Review_Navigation::render_queue( $rows, 2027 );
$html = ob_get_clean();
verify_review( tca_test_contains( $html, 'focus_entry=15' ), 'queue links directly to expense' );
verify_review( ! tca_test_contains( $html, '<script>' ) && tca_test_contains( $html, '&lt;script&gt;' ), 'untrusted ledger labels escaped' );
verify_review( ! tca_test_contains( $html, 'DOC-10' ), 'queue scoped by month' );
$_GET['review_month'] = '2028-01';
ob_start();
TCA_Review_Navigation::render_queue( $rows, 2027 );
verify_review( '' === ob_get_clean(), 'wrong-year URL cannot expose queue' );
$_GET['review_month'] = '<script>';
ob_start();
TCA_Review_Navigation::render_queue( $rows, 2027 );
verify_review( '' === ob_get_clean(), 'invalid month rejected' );
echo "Accounting review navigation regression: PASS\n";
