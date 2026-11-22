<?php
/**
 * Self-contained CSV expense parser and export security regressions.
 * It does not connect to any WordPress installation or write entries.
 *
 * @package TaxoCommerceAccounting
 */
if ( ! function_exists( 'add_action' ) ) {
    function add_action( $name, $callback, $priority = 10, $args = 1 ) {}
}
if ( ! function_exists( 'sanitize_text_field' ) ) {
    function sanitize_text_field( $text ) { return trim( strip_tags( (string) $text ) ); }
}
if ( ! function_exists( 'sanitize_textarea_field' ) ) {
    function sanitize_textarea_field( $text ) { return trim( strip_tags( (string) $text ) ); }
}
if ( ! function_exists( 'sanitize_key' ) ) {
    function sanitize_key( $text ) { return preg_replace( '/[^a-z0-9_-]/', '', strtolower( (string) $text ) ); }
}
if ( ! function_exists( 'tca_valid_date' ) ) {
    function tca_valid_date( $date ) {
        if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $date ) ) { return false; }
        $parts = explode( '-', $date );
        return checkdate( (int) $parts[1], (int) $parts[2], (int) $parts[0] );
    }
}
if ( ! function_exists( 'tca_decimal' ) ) {
    function tca_decimal( $number ) {
        if ( ! is_scalar( $number ) || ! preg_match( '/^-?\d{1,13}(?:\.\d{1,2})?$/', trim( (string) $number ) ) ) { return null; }
        return round( (float) $number, 2 );
    }
}
if ( ! function_exists( 'tca_csv_guard' ) ) {
    function tca_csv_guard( $string ) {
        return preg_match( '/^[=\+\-@\t\r]/', ltrim( (string) $string ) ) ? "'" . $string : (string) $string;
    }
}
require_once dirname( __DIR__ ) . '/includes/class-tca-expense-csv.php';

function tca_expense_csv_assert( $check, $message ) {
    if ( ! $check ) {
        fwrite( STDERR, 'Expense CSV FAIL: ' . $message . PHP_EOL );
        exit( 1 );
    }
}
$fields = TCA_Expense_CSV::columns();
tca_expense_csv_assert( 11 === count( $fields ), 'Fixed CSV header' );

$raw = array(
    '2026-08-20', 'HOST-120', 'Hosting Company', 'B12345678',
    'Hosting for the store', '100,00', '21,00', '0', 'review',
    'commercial', 'Service paid before first sale',
);
$entry = TCA_Expense_CSV::parse_row( $raw, 'EUR' );
tca_expense_csv_assert( is_array( $entry ), 'Valid prior-activity expense accepted' );
tca_expense_csv_assert( '2026-08-20' === $entry['fiscal_date'], 'Original date preserved' );
tca_expense_csv_assert( 121.0 === $entry['amount_total'], 'VAT and taxable base do not overlap' );
tca_expense_csv_assert( 0.0 === $entry['deductible_pct'], 'Tax deductibility is never guessed' );
tca_expense_csv_assert( 'draft' === $entry['status'] && 'expense' === $entry['kind'], 'No automatic validation or supplier purchase' );
tca_expense_csv_assert( 'manual' === $entry['source'], 'Existing secure manual-PDF attachment flow remains available' );
$purchase = TCA_Expense_CSV::parse_row( $raw, 'EUR', 'purchase' );
tca_expense_csv_assert( 'purchase' === $purchase['kind'] && 'draft' === $purchase['status'], 'Supplier CSV creates unapproved purchase, not expense' );
tca_expense_csv_assert( 'manual' === $purchase['source'] && 121.0 === $purchase['amount_total'], 'Supplier invoice preserves total and secure document relation' );
tca_expense_csv_assert( 'commercial' === TCA_Expense_CSV::export_values( (object) $purchase )[9], 'Purchase CSV uses existing export format and scope' );
tca_expense_csv_assert( null === TCA_Expense_CSV::parse_row( $raw, 'EUR', 'sale' ), 'CSV cannot create a sale or alter WooCommerce' );
$original = TCA_Expense_CSV::parse_row( $raw, 'EUR' );
tca_expense_csv_assert( 'expense' === $original['kind'], 'Existing service-expense CSV remains unchanged' );

tca_expense_csv_assert( '[scope=commercial] Service paid before first sale' === $entry['notes'], 'Expense scope preserved without extra schema' );

$encoded = TCA_Expense_CSV::export_values( (object) $entry );
tca_expense_csv_assert( 'commercial' === $encoded[9] && 'Service paid before first sale' === $encoded[10], 'Export reverses scope marker' );
tca_expense_csv_assert( '0' === $encoded[7], 'Export retains VAT deductible percentage' );

$hacked = $raw;
$hacked[2] = '=SUM(1+1)';
$stored = TCA_Expense_CSV::parse_row( $hacked, 'EUR' );
$exported = TCA_Expense_CSV::export_values( (object) $stored );
tca_expense_csv_assert( "'=SUM(1+1)" === $exported[2], 'CSV output guards spreadsheet formula injection' );

$invalid_cases = array(
    array( 0, '2026-02-30' ),
    array( 1, '' ),
    array( 2, '' ),
    array( 3, str_repeat( 'X', 50 ) ),
    array( 4, '' ),
    array( 5, '-10' ),
    array( 6, '-21' ),
    array( 7, '101' ),
    array( 8, 'unknown' ),
    array( 9, 'unrelated' ),
);
foreach ( $invalid_cases as $case ) {
    $bad = $raw;
    $bad[ $case[0] ] = $case[1];
    tca_expense_csv_assert( null === TCA_Expense_CSV::parse_row( $bad, 'EUR' ), 'Invalid row field rejected: ' . $case[0] );
}
tca_expense_csv_assert( null === TCA_Expense_CSV::parse_row( array_slice( $raw, 0, 7 ), 'EUR' ), 'Truncated CSV row rejected' );
tca_expense_csv_assert( array( 'review', 'Old memo' ) === TCA_Expense_CSV::split_notes( 'Old memo' ), 'Legacy expenses export as review' );

$source = file_get_contents( dirname( __DIR__ ) . '/includes/class-tca-expense-csv.php' );
foreach ( array(
    'check_admin_referer( \'tca_expense_csv_import\' )',
    'check_admin_referer( \'tca_expense_csv_export\' )',
    'check_admin_referer( \'tca_expense_csv_template\' )',
    "current_user_can( 'manage_woocommerce' )",
    "'status' => 'draft'",
    "'kind' => 'expense'",
    "self::MAX_ROWS",
    "self::MAX_BYTES",
    "START TRANSACTION",
    "ROLLBACK",
    "SELECT id FROM %i WHERE kind IN",
    "tca_csv_guard( \$row->document_no )",
    "fgetcsv(",
) as $contract ) {
    tca_expense_csv_assert( false !== strpos( $source, $contract ), 'Missing contract ' . $contract );
}
tca_expense_csv_assert( false === strpos( $source, 'wc_get_orders(' ), 'No WooCommerce order mutation' );
tca_expense_csv_assert( false === strpos( $source, 'insert_movement(' ), 'Cash is not inferred from expense imports' );
echo "TaxoCommerce Accounting expense CSV isolated smoke: PASS\n";

$actions = file_get_contents( dirname( __DIR__ ) . '/includes/class-tca-expense-csv.php' );
foreach ( array(
    "admin_post_tca_purchase_csv_import",
    "admin_post_tca_purchase_csv_template",
    "admin_post_tca_purchase_csv_export",
    "check_admin_referer( 'tca_purchase_csv_import' )",
    "check_admin_referer( 'tca_purchase_csv_template' )",
    "check_admin_referer( 'tca_purchase_csv_export' )",
    "'purchase' === \$kind ? 'received' : 'treasury'",
    "tca_table(), \$kind, \$year . '-01-01'",
) as $contract ) {
    tca_expense_csv_assert( false !== strpos( $actions, $contract ), 'Purchase CSV security/ledger contract: ' . $contract );
}
