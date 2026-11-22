<?php
/**
 * Offline Treasury regression: no WordPress, bank account, database or writes.
 *
 * @package TaxoCommerceAccounting
 */
defined( 'ABSPATH' ) || define( 'ABSPATH', __DIR__ . '/' );
if ( ! function_exists( 'add_action' ) ) {
    function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {}
}
require_once dirname( __DIR__ ) . '/includes/class-tca-treasury.php';

function tca_treasury_assert( $condition, $reason ) {
    if ( ! $condition ) {
        fwrite( STDERR, 'Treasury FAIL: ' . $reason . PHP_EOL );
        exit( 1 );
    }
}

$accounts = array(
    (object) array( 'id' => 1, 'kind' => 'cash', 'name' => 'Till', 'opened_on' => '2026-01-01', 'opening_amount' => 100.0 ),
    (object) array( 'id' => 2, 'kind' => 'bank', 'name' => 'Account 1', 'opened_on' => '2026-01-01', 'opening_amount' => 500.0 ),
    (object) array( 'id' => 3, 'kind' => 'bank', 'name' => 'Future account', 'opened_on' => '2026-12-01', 'opening_amount' => 1000.0 ),
);
$movements = array(
    (object) array( 'account_id' => 1, 'month' => '2026-11', 'direction' => 'in', 'total' => 70.0 ),
    (object) array( 'account_id' => 1, 'month' => '2026-11', 'direction' => 'out', 'total' => 30.0 ),
    (object) array( 'account_id' => 1, 'month' => '2026-11', 'direction' => 'transfer_out', 'total' => 20.0 ),
    (object) array( 'account_id' => 2, 'month' => '2026-11', 'direction' => 'transfer_in', 'total' => 20.0 ),
    (object) array( 'account_id' => 2, 'month' => '2026-11', 'direction' => 'in', 'total' => 100.0 ),
    (object) array( 'account_id' => 2, 'month' => '2026-11', 'direction' => 'out', 'total' => 40.0 ),
);
$report = TCA_Treasury::calculate( $accounts, $movements, 2026, '2026-11-17' );
tca_treasury_assert( 120.0 === $report['current']['cash'], 'Cash opening plus receipts minus outflows and transfer' );
tca_treasury_assert( 580.0 === $report['current']['bank'], 'Multiple bank account balances and transfer receipt' );
tca_treasury_assert( 700.0 === array_sum( $report['current'] ), 'Internal transfer does not increase total liquidity' );
tca_treasury_assert( 100.0 === $report['months']['2026-10']['cash_balance'], 'Opening funds exist in prior months' );
tca_treasury_assert( 500.0 === $report['months']['2026-10']['bank_balance'], 'Opening bank balance before monthly activity' );
tca_treasury_assert( 70.0 === $report['months']['2026-11']['cash_in'], 'Cash receipts grouped by month' );
tca_treasury_assert( 50.0 === $report['months']['2026-11']['cash_out'], 'Cash payments and outgoing transfers grouped by month' );
tca_treasury_assert( 120.0 === $report['months']['2026-11']['bank_in'], 'Bank receipt and incoming transfer' );
tca_treasury_assert( 40.0 === $report['months']['2026-11']['bank_out'], 'Bank payments' );
tca_treasury_assert( 580.0 === $report['months']['2026-11']['bank_balance'], 'Month closing bank balance' );

// The STAGING 2026 case: two 500 EUR January opening balances do not
// represent monthly receipts; confirmed November settlements do.
$opening_accounts = array(
    (object) array( 'id' => 11, 'kind' => 'cash', 'name' => 'Cash', 'opened_on' => '2026-01-01', 'opening_amount' => 500.0 ),
    (object) array( 'id' => 12, 'kind' => 'bank', 'name' => 'Bank', 'opened_on' => '2026-01-01', 'opening_amount' => 500.0 ),
);
$before = TCA_Treasury::calculate( $opening_accounts, array(), 2026, '2026-11-20' );
tca_treasury_assert( 0.0 === $before['months']['2026-01']['cash_in'] && 0.0 === $before['months']['2026-01']['bank_in'], 'Opening balances are not double-counted as receipts' );
tca_treasury_assert( 500.0 === $before['current']['cash'] && 500.0 === $before['current']['bank'], 'Opening balances remain available until an actual movement' );
$confirmed_movements = array(
    (object) array( 'account_id' => 12, 'month' => '2026-11', 'direction' => 'in', 'total' => 1043.06 ),
    (object) array( 'account_id' => 12, 'month' => '2026-11', 'direction' => 'out', 'total' => 939.46 ),
);
$settled = TCA_Treasury::calculate( $opening_accounts, $confirmed_movements, 2026, '2026-11-20' );
tca_treasury_assert( 603.60 === round( $settled['current']['bank'], 2 ), 'Confirmed sales less paid supplier invoices update bank balance' );
tca_treasury_assert( 500.0 === $settled['current']['cash'], 'Bank-linked invoices do not change cash register' );
tca_treasury_assert( 1043.06 === $settled['months']['2026-11']['bank_in'], 'Confirmed WooCommerce receipts appear in Bank in' );
tca_treasury_assert( 939.46 === $settled['months']['2026-11']['bank_out'], 'Confirmed invoice payments appear in Bank out' );
$receipt_accounts = array(
    (object) array( 'id' => 11, 'kind' => 'cash', 'name' => 'Cash', 'opened_on' => '2026-01-01', 'opening_amount' => 0.0 ),
    (object) array( 'id' => 12, 'kind' => 'bank', 'name' => 'Bank', 'opened_on' => '2026-01-01', 'opening_amount' => 0.0 ),
);
$receipt_movements = array(
    (object) array( 'account_id' => 11, 'month' => '2026-01', 'direction' => 'in', 'total' => 500.0 ),
    (object) array( 'account_id' => 12, 'month' => '2026-01', 'direction' => 'in', 'total' => 500.0 ),
);
$converted = TCA_Treasury::calculate( $receipt_accounts, $receipt_movements, 2026, '2026-11-20' );
tca_treasury_assert( 500.0 === $converted['current']['cash'] && 500.0 === $converted['current']['bank'], 'Converting both openings to receipts preserves available cash' );
tca_treasury_assert( 500.0 === $converted['months']['2026-01']['cash_in'] && 500.0 === $converted['months']['2026-01']['bank_in'], 'January loan proceeds appear as receipts after explicit conversion' );

// STAGING regression: a loan sourced from an existing 362.57 EUR ING
// opening balance is visible as January funding without creating any money.
$ing = array( (object) array(
    'id' => 99, 'kind' => 'bank', 'name' => 'ING',
    'opened_on' => '2026-01-01', 'opening_amount' => 362.57,
) );
$ing_movements = array(
    (object) array( 'account_id' => 99, 'month' => '2026-01', 'direction' => 'out', 'total' => 191.17 ),
    (object) array( 'account_id' => 99, 'month' => '2026-07', 'direction' => 'out', 'total' => 24.20 ),
    (object) array( 'account_id' => 99, 'month' => '2026-08', 'direction' => 'out', 'total' => 24.20 ),
    (object) array( 'account_id' => 99, 'month' => '2026-09', 'direction' => 'out', 'total' => 47.50 ),
    (object) array( 'account_id' => 99, 'month' => '2026-10', 'direction' => 'out', 'total' => 61.70 ),
    (object) array( 'account_id' => 99, 'month' => '2026-11', 'direction' => 'in', 'total' => 16.01 ),
    (object) array( 'account_id' => 99, 'month' => '2026-11', 'direction' => 'out', 'total' => 12.55 ),
);
$sponsor_loan = (object) array(
    'id' => 1, 'name' => 'Initial sponsor loan',
    'start_date' => '2026-01-01', 'funding_source' => 'opening_balance',
    'opening_account_id' => 99, 'principal' => 362.57, 'remaining' => 362.57,
);
$loan_report = TCA_Treasury::calculate( $ing, $ing_movements, 2026, '2026-11-21', array( $sponsor_loan ) );
tca_treasury_assert( 362.57 === $loan_report['months']['2026-01']['bank_in'], 'January shows the real 362.57 initial financing in Bank in' );
tca_treasury_assert( 362.57 === $loan_report['months']['2026-01']['opening_loan_in'], 'Financing is explicitly identified as existing opening money' );
tca_treasury_assert( 171.40 === round( $loan_report['months']['2026-01']['bank_balance'], 2 ), 'January closing bank balance is unchanged (171.40)' );
tca_treasury_assert( 16.01 === $loan_report['months']['2026-11']['bank_in'], 'Normal November receipts are not duplicated' );
tca_treasury_assert( 17.26 === round( $loan_report['current']['bank'], 2 ), 'Current ING balance is unchanged (17.26)' );
$overview = TCA_Treasury::financial_overview_from_records( $ing, $ing_movements, array( $sponsor_loan ), 2026, '2026-11-21' );
tca_treasury_assert( 362.57 === $overview['opening_loan_funding_total'] && 362.57 === $overview['loan_debt_current'], 'Financial overview reports both loan financing and remaining debt, not taxable sales' );
tca_treasury_assert( 17.26 === $overview['bank_balance_current'], 'Summary and Treasury use the same actual bank balance' );
$old_year = TCA_Treasury::calculate( $ing, $ing_movements, 2025, '2026-11-21', array( $sponsor_loan ) );
tca_treasury_assert( 0.0 === $old_year['months']['2025-01']['bank_in'], '2026 loan never appears as 2025 income' );
$without_loan = TCA_Treasury::calculate( $ing, $ing_movements, 2026, '2026-11-21' );
tca_treasury_assert( 0.0 === $without_loan['months']['2026-01']['bank_in'], 'Unlinked opening balance is never invented as monthly receipt' );
$invalid_loan = clone $sponsor_loan;
$invalid_loan->principal = 200.0;
$invalid = TCA_Treasury::calculate( $ing, $ing_movements, 2026, '2026-11-21', array( $invalid_loan ) );
tca_treasury_assert( 0.0 === $invalid['months']['2026-01']['bank_in'], 'Mismatched loan principal cannot be used as a virtual receipt' );
$invalid_loan->principal = 362.57;
$invalid_loan->funding_source = 'pending';
$invalid = TCA_Treasury::calculate( $ing, $ing_movements, 2026, '2026-11-21', array( $invalid_loan ) );
tca_treasury_assert( 0.0 === $invalid['months']['2026-01']['bank_in'], 'Unfunded loan is not a bank inflow' );
$invalid_loan->funding_source = 'opening_balance';
$invalid_loan->start_date = '2026-12-01';
$invalid = TCA_Treasury::calculate( $ing, $ing_movements, 2026, '2026-11-21', array( $invalid_loan ) );
tca_treasury_assert( 0.0 === $invalid['months']['2026-12']['bank_in'], 'Future loan funding is not counted before receipt' );
$deduped = TCA_Treasury::calculate( $ing, $ing_movements, 2026, '2026-11-21', array( $sponsor_loan, $sponsor_loan ) );
tca_treasury_assert( 362.57 === $deduped['months']['2026-01']['bank_in'], 'Duplicate linked loan records cannot double-count the funds' );

$source = file_get_contents( dirname( __DIR__ ) . '/includes/class-tca-treasury.php' );
tca_treasury_assert( false !== $source, 'Treasury source readable' );
tca_treasury_assert( false !== strpos( $source, "'opening_receipt' === \$operation" ), 'Opening receipt conversion is an explicit action' );
tca_treasury_assert( false !== strpos( $source, "name=\"opening_verified\"" ), 'Conversion requires confirmation' );
tca_treasury_assert( false !== strpos( $source, "'opening_amount' => 0" ), 'Conversion zeroes old opening amount to prevent duplication' );
tca_treasury_assert( false !== strpos( $source, "check_admin_referer( 'tca_treasury_action' )" ), 'All treasury writes verify nonce' );
tca_treasury_assert( false !== strpos( $source, "current_user_can( 'manage_woocommerce' )" ), 'Treasury writes require WooCommerce role' );
tca_treasury_assert( false !== strpos( $source, "START TRANSACTION" ) && false !== strpos( $source, "ROLLBACK" ), 'Transfers are atomic' );
tca_treasury_assert( false !== strpos( $source, "'remaining' => \$principal" ), 'New loans start at their principal' );
tca_treasury_assert( false !== strpos( $source, "next_due_date" ), 'Loan due date is tracked' );
tca_treasury_assert( false === strpos( $source, "get_total_tax(" ), 'Treasury cash is not derived from VAT' );
tca_treasury_assert( false === strpos( $source, "wc_get_orders(" ), 'Treasury cash is not invented from invoices' );
// Fixed-rate financing regression: 1,120.34 EUR, 10% nominal annual TIN, 12 months.
$loan = TCA_Loan_Schedule::calculate( 1120.34, 10, 12, '2026-12-20' );
tca_treasury_assert( 12 === count( $loan ), 'A 12-month loan has twelve instalments' );
tca_treasury_assert( 98.50 === $loan[0]['payment'] && 9.34 === $loan[0]['interest'] && 89.16 === $loan[0]['principal'], 'First instalment splits interest and capital' );
tca_treasury_assert( 98.44 === $loan[11]['payment'] && 0.00 === $loan[11]['remaining'], 'Last instalment is adjusted to clear the debt exactly' );
tca_treasury_assert( 61.60 === round( array_sum( array_column( $loan, 'interest' ) ), 2 ), 'Interest is calculated each month, not treated as VAT' );
tca_treasury_assert( 1181.94 === round( array_sum( array_column( $loan, 'payment' ) ), 2 ), 'Total payments reconcile with capital plus interest' );
tca_treasury_assert( '2027-11-20' === $loan[11]['due_date'], 'Maturity is calculated from first due date and term' );

$no_interest = TCA_Loan_Schedule::calculate( 120, 0, 12, '2026-12-20' );
tca_treasury_assert( 10.0 === $no_interest[0]['payment'] && 0.0 === $no_interest[11]['remaining'], 'Zero-interest loan works' );
$balloon = TCA_Loan_Schedule::calculate( 120, 0, 12, '2026-12-20', 100 );
tca_treasury_assert( $balloon[11]['payment'] > $balloon[0]['payment'] && 0.0 === $balloon[11]['remaining'], 'Extra final principal is financed without inventing VAT' );
$month_end = TCA_Loan_Schedule::calculate( 90, 0, 3, '2027-01-31' );
tca_treasury_assert( '2027-02-28' === $month_end[1]['due_date'] && '2027-03-31' === $month_end[2]['due_date'], 'Month-end due dates do not roll into March' );
foreach ( array(
    array( 0, 10, 12, '2026-12-20', 0 ),
    array( 120, 101, 12, '2026-12-20', 0 ),
    array( 120, 10, 0, '2026-12-20', 0 ),
    array( 120, 10, 12, '2026-02-30', 0 ),
    array( 120, 10, 12, '2026-12-20', 121 ),
) as $invalid ) {
    tca_treasury_assert( array() === TCA_Loan_Schedule::calculate( ...$invalid ), 'Invalid financing terms must be refused' );
}
tca_treasury_assert( false !== strpos( $source, "'loan_payment' === \$operation" ), 'Loan repayments require an explicit action' );
tca_treasury_assert( false !== strpos( $source, "'opening_account_id' => $funding_existing" ), 'Existing ING opening balance is linked to its lender without posting a new receipt' );
tca_treasury_assert( false !== strpos( $source, "opening_account_id = %d" ), 'Linked opening funds cannot be reused, converted or cancelled' );
tca_treasury_assert( false !== strpos( $source, "'funding_source' => $funding_mode" ), 'Loan financing origin is recorded independently from cash' );
tca_treasury_assert( false !== strpos( $source, "'Sponsor (repayable loan)'" ), 'Sponsors are identified as repayable lenders' );
tca_treasury_assert( false !== strpos( $source, "name=\"lender_tax_id\"" ), 'Lender identity can include tax ID' );
tca_treasury_assert( false !== strpos( $source, "'new_receipt' === $funding_mode" ), 'Only explicitly new funding creates another bank receipt' );
tca_treasury_assert( false !== strpos( $source, "'opening_verified' " ), 'Existing loan funding must be explicitly confirmed' );
tca_treasury_assert( false !== strpos( $source, "'funding_received' " ), 'Loan funds enter bank only after explicit confirmation' );
tca_treasury_assert( false !== strpos( $source, "loan_payments" ), 'Loan instalments have a linked audit ledger' );
tca_treasury_assert( false !== strpos( $source, "first_due_date" ), 'Original first due date stays immutable across payments' );
tca_treasury_assert( false !== strpos( $source, "'interest_amount'" ), 'Actual interest component is stored separately from principal' );
tca_treasury_assert( false !== strpos( $source, "'term_months' => \$months" ), 'New financing mode stores its duration' );
tca_treasury_assert( false !== strpos( $source, "return self::table( 'movements' )" ), 'Payments use the standard Treasury movements ledger' );

// Flexible 0% sponsor loan: funding is already in ING and is not added again.
tca_treasury_assert( false !== strpos( $source, "repayment_mode varchar(16)" ), 'Repayment mode is persisted in the loan register' );
tca_treasury_assert( false !== strpos( $source, "'repayment_mode' => " ), 'Flexible loans are tagged separately from legacy loans' );
tca_treasury_assert( false !== strpos( $source, "'loan_flexible_payment' === $operation" ), 'Partial repayments have a separate action' );
tca_treasury_assert( false !== strpos( $source, "'remaining' => round( (float) $loan->remaining - $amount, 2 )" ), 'Confirmed repayments reduce debt by actual capital paid' );
tca_treasury_assert( false !== strpos( $source, "'interest_amount' => 0.0" ), 'Flexible interest-free repayments have no interest' );
tca_treasury_assert( false !== strpos( $source, "loan_payment_confirmed" ), 'Flexible repayments require administrator confirmation' );
tca_treasury_assert( false !== strpos( $source, "Loan funds" ), 'Funding mode keeps opening-balance loan separate from new bank receipt' );
tca_treasury_assert( false !== strpos( $source, "Repayment when agreed, without fixed instalments." ), 'Flexible terms are shown without a false monthly instalment' );
echo "TaxoCommerce Accounting Treasury isolated smoke: PASS\n";
