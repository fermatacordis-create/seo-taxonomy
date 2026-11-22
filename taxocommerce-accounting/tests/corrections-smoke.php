<?php
/**
 * Standalone safety regression for already validated accounting entries.
 * No WordPress, database, order, invoice, or remote service is changed.
 */
define( 'ABSPATH', __DIR__ . '/' );
function absint( $value ) { return abs( (int) $value ); }
function __( $message, $domain = '' ) { return $message; }
function add_action( $hook, $callback ) {}
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_\\-]/', '', strtolower( (string) $value ) ); }
function tca_valid_date( $value ) {
    if ( ! is_string( $value ) || ! preg_match( '/^(\\d{4})-(\\d{2})-(\\d{2})$/', $value, $parts ) ) return false;
    return checkdate( (int) $parts[2], (int) $parts[3], (int) $parts[1] );
}
function tca_decimal( $value ) {
    return is_scalar( $value ) && preg_match( '/^-?\\d{1,13}(?:\\.\\d{1,2})?$/', trim( (string) $value ) )
        ? round( (float) $value, 2 ) : null;
}
function current_time( $format ) { return '2026-11-17 15:00:00'; }
function wp_json_encode( $data ) { return json_encode( $data ); }
function get_current_user_id() { return 15; }
function tca_table() { return 'wp_taxocommerce_accounting_entries'; }
class WP_Error {
    public $code;
    public function __construct( $code, $message ) { $this->code = $code; }
}
function is_wp_error( $value ) { return $value instanceof WP_Error; }
class TCA_Issued_Invoice {
    public static $invoice = 'FAC-2026-000001';
    public static function for_order( $id ) {
        return 274750 === (int) $id ? self::$invoice : '';
    }
}
class TCA_Settlements {
    public static $paid = 0.0;
    public static function sum( $entry_id ) { return self::$paid; }
}
class TCA_Mock_DB {
    public $prefix = 'wp_';
    public $queries = array();
    public $update_data;
    public $updated_where;
    public $insert_data;
    public $update_ok = 1;
    public $insert_ok = 1;
    public function query( $sql ) { $this->queries[] = $sql; return 1; }
    public function prepare( $sql, ...$values ) { return $sql; }
    public function get_row( $sql ) { return (object) array( 'id' => 2 ); }
    public function update( $table, $values, $where ) {
        $this->update_data = $values;
        $this->updated_where = $where;
        return $this->update_ok;
    }
    public function insert( $table, $values ) { $this->insert_data = $values; return $this->insert_ok; }
}
$GLOBALS['wpdb'] = new TCA_Mock_DB();
require_once dirname( __DIR__ ) . '/includes/class-tca-corrections.php';

class TCA_Depreciation {
    public static function valid( $type, $date, $years, $fiscal_date ) { return 'current' === $type; }
}

function tca_test( $ok, $message ) {
    if ( ! $ok ) {
        fwrite( STDERR, "FAIL: " . $message . "\n" );
        exit( 1 );
    }
}
$entry = (object) array(
    'id' => 2, 'source' => 'woocommerce', 'source_id' => 274750,
    'kind' => 'sale', 'fiscal_date' => '2026-11-16',
    'document_no' => 'FAC-2026-000001', 'party' => 'Cliente de prueba',
    'tax_id' => '', 'concept' => 'WooCommerce #274750',
    'amount_base' => '621.60', 'amount_tax' => '0.00',
    'amount_total' => '621.60', 'deductible_pct' => '0.00',
    'currency' => 'EUR', 'treatment' => 'domestic',
    'status' => 'validated', 'notes' => 'original',
    'updated_at' => '2026-11-17 14:29:31',
);
$input = array(
    'fiscal_date' => '2026-11-16', 'document_no' => 'MANIPULATED',
    'party' => 'Cliente de prueba', 'tax_id' => '',
    'concept' => 'WooCommerce #274750', 'amount_base' => '513.72',
    'amount_tax' => '107.88', 'deductible_pct' => '0',
    'reason' => 'Error detected in the sale VAT',
);
$new = TCA_Corrections::prepare( $entry, $input );
tca_test( ! is_wp_error( $new ), 'Corrected sale accepts valid amounts' );
tca_test( 513.72 === $new['amount_base'] && 107.88 === $new['amount_tax'] && 621.60 === $new['amount_total'], 'Recalculates total from base plus tax' );
tca_test( 'draft' === $new['status'], 'Previously validated entry returns to draft' );
tca_test( 'FAC-2026-000001' === $new['document_no'], 'Server never accepts tampered Billing invoice number' );
tca_test( 'domestic' === $entry->treatment && 'validated' === $entry->status && '0.00' === $entry->amount_tax, 'Original in-memory entry remains unchanged' );
tca_test( true === TCA_Corrections::save( $entry, $new, $input['reason'] ), 'Atomic save and log succeed' );
tca_test( array( 'START TRANSACTION', 'COMMIT' ) === $GLOBALS['wpdb']->queries, 'Correction uses a single transaction' );
tca_test( 2 === $GLOBALS['wpdb']->updated_where['id'] && 'validated' === $GLOBALS['wpdb']->updated_where['status'], 'Concurrency-safe update targets original status' );
tca_test( 15 === $GLOBALS['wpdb']->insert_data['user_id'] && 2 === $GLOBALS['wpdb']->insert_data['entry_id'], 'Audit identifies user and entry' );
tca_test( 'Error detected in the sale VAT' === $GLOBALS['wpdb']->insert_data['reason'], 'Audit retains mandatory reason' );
$old = json_decode( $GLOBALS['wpdb']->insert_data['before_data'], true );
$after = json_decode( $GLOBALS['wpdb']->insert_data['after_data'], true );
tca_test( '0.00' === $old['amount_tax'] && '107.88' === $after['amount_tax'] && 'draft' === $after['status'], 'Audit retains old and new values and status' );
TCA_Settlements::$paid = 700.0;
$GLOBALS['wpdb'] = new TCA_Mock_DB();
tca_test( false === TCA_Corrections::save( $entry, $new, $input['reason'] ), 'Reject correction below already received money' );
tca_test( array( 'START TRANSACTION', 'ROLLBACK' ) === $GLOBALS['wpdb']->queries, 'Overpaid correction rolls back before editing fiscal entry' );
TCA_Settlements::$paid = 0.0;
$GLOBALS['wpdb'] = new TCA_Mock_DB();
$GLOBALS['wpdb']->insert_ok = 0;
tca_test( false === TCA_Corrections::save( $entry, $new, $input['reason'] ), 'Refuses unlogged ledger corrections' );
tca_test( array( 'START TRANSACTION', 'ROLLBACK' ) === $GLOBALS['wpdb']->queries, 'Failed audit rolls back ledger update' );
$GLOBALS['wpdb'] = new TCA_Mock_DB();
$GLOBALS['wpdb']->update_ok = 0;
tca_test( false === TCA_Corrections::save( $entry, $new, $input['reason'] ), 'Refuses concurrent update' );
tca_test( array( 'START TRANSACTION', 'ROLLBACK' ) === $GLOBALS['wpdb']->queries, 'Concurrent update is rolled back' );
$input['reason'] = '';
tca_test( is_wp_error( TCA_Corrections::prepare( $entry, $input ) ), 'Reason is compulsory' );
$input['reason'] = 'Correct VAT';
$input['amount_tax'] = '-5';
tca_test( is_wp_error( TCA_Corrections::prepare( $entry, $input ) ), 'Positive sales cannot have negative VAT' );
$input['amount_tax'] = '107.88';
$input['amount_base'] = '513.723';
tca_test( is_wp_error( TCA_Corrections::prepare( $entry, $input ) ), 'Rejects 3-decimal monetary input' );
$input['amount_base'] = '513.72';
$input['fiscal_date'] = '2026-02-31';
tca_test( is_wp_error( TCA_Corrections::prepare( $entry, $input ) ), 'Rejects invalid dates' );
$input['fiscal_date'] = '2026-11-16';
$entry->kind = 'purchase';
$entry->source = 'manual';
$input['document_no'] = 'T-111';
$input['amount_tax'] = '89.90';
$input['amount_base'] = '428.10';
$input['deductible_pct'] = '100';
$purchase = TCA_Corrections::prepare( $entry, $input );
tca_test( ! is_wp_error( $purchase ) && 'T-111' === $purchase['document_no'] && 518.00 === $purchase['amount_total'], 'Supplier invoices can be corrected independently' );

// Regression: an already validated future provisional expense can be reopened
// without changing totals; the mandatory reason remains in the audit history.
$expense = (object) array(
    'id' => 9, 'source' => 'manual', 'source_id' => 116809749924714,
    'kind' => 'expense', 'fiscal_date' => '2026-12-01',
    'document_no' => 'PREV-CHATGPT-2026-12', 'party' => 'OpenAI',
    'tax_id' => '', 'concept' => 'ChatGPT December provisional',
    'amount_base' => '17.36', 'amount_tax' => '3.64',
    'amount_total' => '21.00', 'deductible_pct' => '100.00',
    'expense_type' => 'current', 'service_date' => null, 'useful_life_years' => 0,
    'currency' => 'EUR', 'treatment' => 'domestic',
    'status' => 'validated', 'notes' => 'Forecast: no issued invoice and no payment',
    'updated_at' => '2026-11-20 22:00:00',
);
$expense_input = array(
    'fiscal_date' => $expense->fiscal_date,
    'document_no' => $expense->document_no,
    'party' => $expense->party,
    'tax_id' => $expense->tax_id,
    'concept' => $expense->concept,
    'amount_base' => $expense->amount_base,
    'amount_tax' => $expense->amount_tax,
    'deductible_pct' => $expense->deductible_pct,
    'expense_type' => 'current',
    'notes' => $expense->notes,
    'reason' => 'Validated accidentally before invoice was issued',
);
$reopened = TCA_Corrections::prepare( $expense, $expense_input );
tca_test( ! is_wp_error( $reopened ), 'Existing validated expense can be prepared for reopening' );
tca_test( 'draft' === $reopened['status'], 'Reopening changes status' );
tca_test( 21.00 === $reopened['amount_total'] && '17.36' === $expense->amount_base, 'Reopening retains original values' );
tca_test( $expense->notes === $reopened['notes'], 'Notes are retained unless explicitly modified' );
$compare = new ReflectionMethod( 'TCA_Corrections', 'same_values' );
$compare->setAccessible( true );
$original_snapshot = TCA_Corrections::snapshot( $expense );
$reopened_snapshot = TCA_Corrections::snapshot( (object) array_merge( (array) $expense, $reopened ) );
tca_test( false === $compare->invoke( null, $original_snapshot, $reopened_snapshot ), 'Validated-to-draft is a real change even with identical fields' );
$GLOBALS['wpdb'] = new TCA_Mock_DB();
tca_test( TCA_Corrections::save( $expense, $reopened, $expense_input['reason'] ), 'Reopening saves atomically with audit' );
tca_test( 'draft' === $GLOBALS['wpdb']->update_data['status'], 'Ledger status becomes draft' );
tca_test( $expense_input['reason'] === $GLOBALS['wpdb']->insert_data['reason'], 'Reopening reason is logged' );
tca_test( 'validated' === json_decode( $GLOBALS['wpdb']->insert_data['before_data'], true )['status'], 'Old validation status retained in audit' );
tca_test( 'draft' === json_decode( $GLOBALS['wpdb']->insert_data['after_data'], true )['status'], 'New draft status retained in audit' );
$expense_input['notes'] = "December forecast only\\nWaiting for actual invoice and charge";
$updated_notes = TCA_Corrections::prepare( $expense, $expense_input );
tca_test( $expense_input['notes'] === $updated_notes['notes'], 'Editable notes with line breaks are persisted' );
tca_test( false === $compare->invoke( null, $original_snapshot, TCA_Corrections::snapshot( (object) array_merge( (array) $expense, $updated_notes ) ) ), 'Updated notes are an auditable correction' );
$draft = clone $expense;
$draft->status = 'draft';
$unchanged_draft = TCA_Corrections::prepare( $draft, array_merge( $expense_input, array( 'notes' => $draft->notes ) ) );
tca_test( true === $compare->invoke( null, TCA_Corrections::snapshot( $draft ), TCA_Corrections::snapshot( (object) array_merge( (array) $draft, $unchanged_draft ) ) ), 'Already-draft record with no data edits is still unchanged' );
$expense_input['notes'] = str_repeat( 'N', 5001 );
tca_test( is_wp_error( TCA_Corrections::prepare( $expense, $expense_input ) ), 'Rejects oversized notes' );
echo "Accounting audited fiscal correction regression: PASS\\n";

