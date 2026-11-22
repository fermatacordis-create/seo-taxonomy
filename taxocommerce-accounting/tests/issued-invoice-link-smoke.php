<?php
/**
 * Offline regression for optional WooCommerce sale-to-issued-invoice link.
 * No WordPress database, checkout or external services are used.
 *
 * @package TaxoCommerce_Accounting
 */

define( 'ABSPATH', __DIR__ . '/' );
function absint( $value ) {
    return abs( (int) $value );
}
require_once dirname( __DIR__ ) . '/includes/class-tca-issued-invoice.php';

function tca_invoice_assert( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, 'FAIL: ' . $message . "\n" );
        exit( 1 );
    }
}

// Accounting must function without the separate Billing plugin.
tca_invoice_assert( '' === TCA_Issued_Invoice::for_order( 274750 ), 'Billing remains optional' );
tca_invoice_assert( null === TCA_Issued_Invoice::amounts_for_order( 274750 ), 'Fiscal amount extraction remains optional' );

class TCA_Mock_Billing {
    public static $invoice = null;
    public static $asked_order = 0;
    public static $asked_type = '';
    public static function get_for_order( $order_id, $type ) {
        self::$asked_order = $order_id;
        self::$asked_type = $type;
        return self::$invoice;
    }
}
class_alias( 'TCA_Mock_Billing', 'SEO_Facturas_Documents' );

// A real invoice can be linked by the exact WooCommerce order ID.
TCA_Mock_Billing::$invoice = (object) array(
    'order_id' => 274750,
    'document_type' => 'invoice',
    'status' => 'issued',
    'document_number' => 'FAC-2026-000001',
);
tca_invoice_assert(
    'FAC-2026-000001' === TCA_Issued_Invoice::for_order( 274750 ),
    'A matching issued invoice pre-fills the order reference'
);
tca_invoice_assert(
    274750 === TCA_Mock_Billing::$asked_order &&
    'invoice' === TCA_Mock_Billing::$asked_type,
    'Only the invoice for this exact order is queried'
);

// Invoice from another order must never be assigned by mistake.
TCA_Mock_Billing::$invoice->order_id = 274751;
tca_invoice_assert( '' === TCA_Issued_Invoice::for_order( 274750 ), 'Reject another order invoice' );
TCA_Mock_Billing::$invoice->order_id = 274750;

// Proformas and unfinished documents are not tax invoices.
TCA_Mock_Billing::$invoice->document_type = 'proforma';
tca_invoice_assert( '' === TCA_Issued_Invoice::for_order( 274750 ), 'Reject proformas' );
TCA_Mock_Billing::$invoice->document_type = 'invoice';
TCA_Mock_Billing::$invoice->status = 'rendering';
tca_invoice_assert( '' === TCA_Issued_Invoice::for_order( 274750 ), 'Reject unfinished invoices' );
TCA_Mock_Billing::$invoice->status = 'issued';
TCA_Mock_Billing::$invoice->document_number = '';
tca_invoice_assert( '' === TCA_Issued_Invoice::for_order( 274750 ), 'Reject missing numbers' );
TCA_Mock_Billing::$invoice = null;
tca_invoice_assert( '' === TCA_Issued_Invoice::for_order( 274750 ), 'Reject absent invoices' );

// Read the exact, previously issued fiscal snapshot; never derive VAT from the order's total.
$snapshot = array(
    'document' => array( 'type' => 'invoice', 'number' => 'FAC-2026-000001' ),
    'order' => array( 'id' => 274750, 'currency' => 'EUR' ),
    'totals' => array( 'base_total' => 513.72, 'total_tax' => 107.88, 'total' => 621.60 ),
);
TCA_Mock_Billing::$invoice = (object) array(
    'order_id' => 274750, 'document_type' => 'invoice', 'status' => 'issued',
    'document_number' => 'FAC-2026-000001', 'snapshot' => json_encode( $snapshot ),
);
$amounts = TCA_Issued_Invoice::amounts_for_order( 274750 );
tca_invoice_assert( is_array( $amounts ), 'Issued fiscal snapshot is accepted' );
tca_invoice_assert( 513.72 === $amounts['amount_base'] && 107.88 === $amounts['amount_tax'] && 621.60 === $amounts['amount_total'], 'Amounts must match immutable snapshot, including 21 percent VAT example' );
tca_invoice_assert( 'EUR' === $amounts['currency'], 'Currency is preserved' );
$snapshot['order']['id'] = 274751;
TCA_Mock_Billing::$invoice->snapshot = json_encode( $snapshot );
tca_invoice_assert( null === TCA_Issued_Invoice::amounts_for_order( 274750 ), 'Reject snapshot from another order' );
$snapshot['order']['id'] = 274750;
$snapshot['totals']['total'] = 10.0;
TCA_Mock_Billing::$invoice->snapshot = json_encode( $snapshot );
tca_invoice_assert( null === TCA_Issued_Invoice::amounts_for_order( 274750 ), 'Reject inconsistent base + VAT' );
$snapshot['totals']['total'] = 621.60;
TCA_Mock_Billing::$invoice->snapshot = json_encode( $snapshot );
TCA_Mock_Billing::$invoice->status = 'rendering';
tca_invoice_assert( null === TCA_Issued_Invoice::amounts_for_order( 274750 ), 'Reject an unissued invoice snapshot' );
TCA_Mock_Billing::$invoice->status = 'issued';
TCA_Mock_Billing::$invoice->document_type = 'proforma';
tca_invoice_assert( null === TCA_Issued_Invoice::amounts_for_order( 274750 ), 'Reject a proforma snapshot' );
TCA_Mock_Billing::$invoice = null;

// The reference cannot be silently imported from a different document type.
$source = file_get_contents( dirname( __DIR__ ) . '/taxocommerce-accounting.php' );
tca_invoice_assert( false !== $source, 'Read Accounting source' );
tca_invoice_assert(
    false !== strpos( $source, "TCA_Issued_Invoice::amounts_for_order( \$order->get_id() )" ) &&
    false !== strpos( $source, "'document_no' => \$invoice ? \$invoice['document_no'] : TCA_Issued_Invoice::for_order( \$order->get_id() )" ),
    'New sales import the matching invoice number'
);
tca_invoice_assert(
    false !== strpos( $source, "TCA_Issued_Invoice::for_order( \$entry->source_id )" ),
    'Approving a sale resolves the actual invoice on the server'
);
tca_invoice_assert(
    false !== strpos( $source, "TCA_Issued_Invoice::for_order( \$row->source_id )" ),
    'Already-imported draft sales display current invoice without reimport'
);
tca_invoice_assert(
    false !== strpos( $source, "'wc_refund'" ),
    'Refunds remain separately reviewable; no original invoice auto-join'
);
tca_invoice_assert( false !== strpos( $source, 'function tca_sale_invoice_mismatch(' ), 'Existing ledger rows remain independent but discrepancies can be detected' );
tca_invoice_assert( false !== strpos( $source, "tca_redirect( 'sales', 'invoice_mismatch' )" ), 'Conflicting invoices must block approval' );
// Billing emits a single optional WordPress event only after issuing the PDF.
$billing = file_get_contents( dirname( dirname( __DIR__ ) ) . '/taxocommerce-facturacion/includes/class-seo-facturas-documents.php' );
tca_invoice_assert( false !== $billing, 'Billing source can be inspected' );
tca_invoice_assert(
    false !== strpos( $billing, "do_action('taxocommerce_billing_invoice_issued'" ),
    'Successful invoice issuance triggers an optional accounting event'
);
tca_invoice_assert(
    false !== strpos( $source, "add_action( 'taxocommerce_billing_invoice_issued', 'tca_on_billing_invoice_issued', 10, 2 )" ),
    'Accounting subscribes to the optional invoice-issued action'
);
tca_invoice_assert(
    false !== strpos( $source, "tca_find_source( 'woocommerce', \$order_id )" ) &&
    false !== strpos( $source, "'draft' === (string) \$existing->status" ),
    'Existing validated entries are not overwritten and imports deduplicate'
);
echo "Accounting issued-invoice optional link: PASS\n";
