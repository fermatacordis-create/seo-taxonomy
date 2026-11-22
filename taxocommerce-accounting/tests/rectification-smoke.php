<?php
/**
 * Standalone Accounting contract: a verified fiscal rectification can replace
 * the invoice tax split, without Billing installed or a new payment.
 */
define( 'ABSPATH', __DIR__ );
function absint( $value ) { return abs( (int) $value ); }
$GLOBALS['rectification_enabled'] = false;
$GLOBALS['rectification_wrong_total'] = false;
function apply_filters( $name, $invoice, $order_id ) {
    if ( 'taxocommerce_accounting_effective_issued_invoice' !== $name ||
        ! $GLOBALS['rectification_enabled'] || 274750 !== (int) $order_id ) {
        return $invoice;
    }
    return array_merge( $invoice, array(
        'document_no' => 'RECT-2026-000001',
        'amount_base' => 513.72,
        'amount_tax' => 107.88,
        'amount_total' => $GLOBALS['rectification_wrong_total'] ? 622.60 : 621.60,
        'currency' => 'EUR',
    ) );
}
class SEO_Facturas_Documents {
    public static function get_for_order( $order_id, $type ) {
        if ( 274750 !== (int) $order_id || 'invoice' !== $type ) {
            return null;
        }
        return (object) array(
            'order_id' => 274750,
            'document_type' => 'invoice',
            'status' => 'issued',
            'document_number' => 'FAC-2026-000001',
            'snapshot' => json_encode( array(
                'document' => array( 'type' => 'invoice', 'number' => 'FAC-2026-000001' ),
                'order' => array( 'id' => 274750, 'currency' => 'EUR' ),
                'totals' => array( 'base_total' => 621.60, 'total_tax' => 0.00, 'total' => 621.60 ),
            ) ),
        );
    }
}
require dirname( __DIR__ ) . '/includes/class-tca-issued-invoice.php';
function check_invoice( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL accounting rectification: $message\n" );
        exit( 1 );
    }
}
$original = TCA_Issued_Invoice::amounts_for_order( 274750 );
check_invoice( $original['document_no'] === 'FAC-2026-000001' &&
    $original['amount_base'] === 621.60 && $original['amount_tax'] === 0.00,
    'without Billing rectification, Accounting sees original and stays autonomous' );
$GLOBALS['rectification_enabled'] = true;
$effective = TCA_Issued_Invoice::amounts_for_order( 274750 );
check_invoice( $effective['document_no'] === 'RECT-2026-000001' &&
    $effective['amount_base'] === 513.72 && $effective['amount_tax'] === 107.88 &&
    $effective['amount_total'] === 621.60, 'issued rectification replaces fiscal split without a second sale' );
check_invoice( 'RECT-2026-000001' === TCA_Issued_Invoice::for_order( 274750 ),
    'validated sale links to corrected document number' );
$GLOBALS['rectification_wrong_total'] = true;
$rejected = TCA_Issued_Invoice::amounts_for_order( 274750 );
check_invoice( 'FAC-2026-000001' === $rejected['document_no'] &&
    $rejected['amount_tax'] === 0.00, 'gross-changing document cannot overwrite original receipt' );
check_invoice( null === TCA_Issued_Invoice::amounts_for_order( 0 ),
    'unknown invoice is never inferred' );
echo "TaxoCommerce Accounting optional rectification regression: PASS\n";
