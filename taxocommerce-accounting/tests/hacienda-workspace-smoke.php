<?php
/**
 * Isolated TaxoCommerce Accounting Hacienda fiscal workspace smoke test.
 * No WordPress site, tax submission, external API, or database writes.
 */
define( 'ABSPATH', __DIR__ . '/' );
function __( $value, $domain = '' ) { return $value; }
function add_action( $hook, $handler ) { $GLOBALS['hooks'][] = $hook; }
function tca_table() { return 'wp_taxocommerce_accounting_entries'; }
function tca_test_assert( $ok, $message ) {
    if ( ! $ok ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}
class TCA_Hacienda_Test_DB {
    public $sql = '';
    public $args = array();
    public function prepare( $sql, ...$args ) {
        $this->sql = $sql;
        $this->args = isset( $args[0] ) && is_array( $args[0] ) ? $args[0] : $args;
        return $sql;
    }
    public function get_results( $sql ) {
        if ( false !== strpos( $sql, 'missing_document' ) ) {
            return array(
                (object) array( 'kind' => 'sale', 'status' => 'validated', 'treatment' => 'domestic', 'entry_count' => 4, 'missing_document' => 1, 'missing_tax_id' => 2 ),
                (object) array( 'kind' => 'refund', 'status' => 'draft', 'treatment' => 'review', 'entry_count' => 2, 'missing_document' => 2, 'missing_tax_id' => 1 ),
            );
        }
        return array(
            (object) array( 'period' => '2026-11', 'kind' => 'sale', 'status' => 'validated', 'treatment' => 'domestic', 'entry_count' => 600, 'amount_base' => 100, 'amount_total' => 121, 'amount_tax' => 21, 'deductible_tax' => 0 ),
            (object) array( 'period' => '2026-11', 'kind' => 'refund', 'status' => 'validated', 'treatment' => 'domestic', 'entry_count' => 1, 'amount_base' => -20, 'amount_total' => -25, 'amount_tax' => -5, 'deductible_tax' => 0 ),
            (object) array( 'period' => '2026-11', 'kind' => 'purchase', 'status' => 'validated', 'treatment' => 'domestic', 'entry_count' => 3, 'amount_base' => 100, 'amount_total' => 110, 'amount_tax' => 10, 'deductible_tax' => 5 ),
            (object) array( 'period' => '2026-11', 'kind' => 'sale', 'status' => 'draft', 'treatment' => 'domestic', 'entry_count' => 2, 'amount_total' => 121, 'amount_tax' => 21, 'deductible_tax' => 0 ),
            (object) array( 'period' => '2026-11', 'kind' => 'sale', 'status' => 'validated', 'treatment' => 'intra', 'entry_count' => 5, 'amount_total' => 121, 'amount_tax' => 21, 'deductible_tax' => 0 ),
        );
    }
    public function get_var( $query ) { return 15; }
}
$GLOBALS['wpdb'] = new TCA_Hacienda_Test_DB();
require_once dirname( __DIR__ ) . '/includes/class-tca-hacienda.php';
$coverage = TCA_Hacienda::readiness( 2026, 4 );
tca_test_assert( 6 === $coverage['total'], 'All ledger rows must count toward period coverage' );
tca_test_assert( 2 === $coverage['pending'], 'Unvalidated records require review' );
tca_test_assert( 2 === $coverage['other_treatments'], 'Special treatments are not silently treated as domestic' );
tca_test_assert( 3 === $coverage['missing_document'], 'Missing document references are counted' );
tca_test_assert( 3 === $coverage['missing_tax_id'], 'Missing tax IDs are informational, not a blanket tax blocker' );
tca_test_assert( in_array( '2026-10-01', $GLOBALS['wpdb']->args, true ), 'Readiness uses bound fiscal quarter start' );
tca_test_assert( false !== strpos( $GLOBALS['wpdb']->sql, 'GROUP BY' ), 'Readiness does not rely on a display-limited row set' );
$expected = array( 'issued', 'received', 'assets', 'intra', '303', '390', '347', '349', '200' );
$sections = TCA_Hacienda::sections();
tca_test_assert( array_map( 'strval', array_keys( $sections ) ) === $expected, 'All four fiscal books and five models must appear in Hacienda tabs' );
foreach ( $sections as $id => $section ) {
    tca_test_assert( 0 === strpos( $section['url'], 'https://sede.agenciatributaria.gob.es/' ), 'Official AEAT links only: ' . $id );
    tca_test_assert( isset( $section['scope'] ), 'Each tab has bounded data scope: ' . $id );
}
tca_test_assert( TCA_Hacienda::dates( 2026, 4 ) === array( '2026-10-01', '2026-12-31' ), 'Quarter 4 bounds' );
tca_test_assert( TCA_Hacienda::dates( 2024, 1 ) === array( '2024-01-01', '2024-03-31' ), 'Leap year quarter 1 bounds' );
tca_test_assert( TCA_Hacienda::dates( 2026 ) === array( '2026-01-01', '2026-12-31' ), 'Annual fiscal period' );
$data = TCA_Hacienda::vat_summary( 2026, 4 );
tca_test_assert( 1 === count( $data ), 'One month returned' );
tca_test_assert( 611 === $data[0]['entries'], 'Full group count is not capped to 400 ledger rows' );
tca_test_assert( 96.0 === $data[0]['sales'], 'Domestic sales include correctly signed refunds only' );
tca_test_assert( 80.0 === $data[0]['sales_base'], 'Real domestic sales taxable bases retain signed refunds' );
tca_test_assert( 100.0 === $data[0]['purchases_base'], 'Domestic purchase taxable bases are accumulated separately from VAT' );

tca_test_assert( 16.0 === $data[0]['output_vat'], 'Domestic output VAT offsets refunds' );
tca_test_assert( 5.0 === $data[0]['deductible_input_vat'], 'Deductible input VAT respects configured percentage' );
tca_test_assert( 11.0 === $data[0]['difference'], 'Indicative difference is not an official tax form' );
tca_test_assert( 7 === $data[0]['pending_review'], 'Drafts and non-domestic entries are not silently counted as payable VAT' );
tca_test_assert( false !== strpos( $GLOBALS['wpdb']->sql, 'GROUP BY' ), 'Aggregate ALL rows at database level' );
tca_test_assert( false === strpos( $GLOBALS['wpdb']->sql, 'LIMIT' ), 'No hidden 400-row or 20k-row cap in VAT aggregation' );
tca_test_assert( in_array( '2026-10-01', $GLOBALS['wpdb']->args, true ), 'Quarter start passed as bound date' );
tca_test_assert( in_array( '2026-12-31', $GLOBALS['wpdb']->args, true ), 'Quarter end passed as bound date' );
$count = TCA_Hacienda::ledger( 2026, 'issued', 0, 0, true );
tca_test_assert( 15 === $count, 'Tab-specific count through native wpdb' );
tca_test_assert( in_array( 'issued', $GLOBALS['wpdb']->args, true ), 'Scope parameter is bound, not injected into SQL' );
tca_test_assert( false !== strpos( $GLOBALS['wpdb']->sql, 'kind IN' ), 'Sale/refund selection preserved' );
$addon = file_get_contents( dirname( __DIR__ ) . '/includes/class-tca-hacienda.php' );
$main  = file_get_contents( dirname( __DIR__ ) . '/taxocommerce-accounting.php' );
tca_test_assert( false !== strpos( $main, "TCA_Hacienda::render( \$year )" ), 'Hacienda included under main plugin menu' );
tca_test_assert( false !== strpos( $main, "if ( 'models' === \$tab ) \$tab = 'hacienda';" ), 'Old tax returns tab URL remains valid' );
tca_test_assert( false !== strpos( $addon, "check_admin_referer( 'tca_hacienda_download' )" ), 'Downloads use nonce' );
tca_test_assert( false !== strpos( $addon, "current_user_can( 'manage_woocommerce' )" ), 'Downloads require merchant capability' );
tca_test_assert( false !== strpos( $addon, "'official_aeat_format' => false" ), 'JSON exports explicitly marked NOT official' );
tca_test_assert( false !== strpos( $addon, 'PREPARATORY WORKING DATA' ), 'CSV exports have not-official warning' );
tca_test_assert( false !== strpos( $addon, 'PLANTILLA_LIBROS_Pers_Juridicas.xlsx' ), 'AEAT legal-entities workbook is linked to the original official source' );
tca_test_assert( false !== strpos( $addon, 'PLANTILLA_LIBROS_UNIFICADOS.xlsx' ), 'AEAT general workbook is linked to the original official source' );
tca_test_assert( false === strpos( $addon, 'wp_remote_post(' ), 'No tax authority submission API' );
tca_test_assert( false === strpos( $addon, 'wp_insert_post(' ), 'No changes to WP content' );
tca_test_assert( in_array( 'admin_post_tca_hacienda_download', $GLOBALS['hooks'], true ), 'Protected download endpoint registered' );
echo "TaxoCommerce Accounting Hacienda: tabs, full-year VAT, refunds, filters, export safety PASS\n";
