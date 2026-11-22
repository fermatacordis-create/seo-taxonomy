<?php
/**
 * Optional read-only lookup of the invoice issued for a WooCommerce order.
 *
 * Billing is not a required plugin. Independent shops can keep entering
 * external invoice references manually when Billing is not installed.
 *
 * @package TaxoCommerce_Accounting
 */

defined( 'ABSPATH' ) || exit;

final class TCA_Issued_Invoice {

    /**
     * Return the actual issued invoice number for one order, if available.
     * Never associate a proforma, quotation or credit note with a sale.
     *
     * @param int $order_id WooCommerce order ID.
     * @return string Issued invoice number, or an empty string.
     */
    public static function for_order( $order_id ) {
        $order_id = absint( $order_id );
        if ( ! $order_id || ! class_exists( 'SEO_Facturas_Documents' ) ||
            ! method_exists( 'SEO_Facturas_Documents', 'get_for_order' ) ) {
            return '';
        }

        $invoice = SEO_Facturas_Documents::get_for_order( $order_id, 'invoice' );

        if ( ! is_object( $invoice ) ||
            ! isset( $invoice->order_id, $invoice->document_type, $invoice->status, $invoice->document_number ) ||
            $order_id !== absint( $invoice->order_id ) ||
            'invoice' !== (string) $invoice->document_type ||
            'issued' !== (string) $invoice->status ) {
            return '';
        }

        $number = trim( (string) $invoice->document_number );
        if ( '' === $number || strlen( $number ) > 100 ) {
            return '';
        }
        // Optional filter: another independent invoicing plugin may return an
        // issued rectification linked to this original, without altering it.
        $effective = function_exists( 'apply_filters' )
            ? apply_filters( 'taxocommerce_accounting_effective_issued_invoice',
                array( 'document_no' => $number ), $order_id )
            : array( 'document_no' => $number );
        $candidate = is_array( $effective ) ? trim( (string) ( $effective['document_no'] ?? '' ) ) : '';
        return '' !== $candidate && strlen( $candidate ) <= 100 ? $candidate : $number;
    }
    /**
     * Read immutable fiscal amounts from an already issued invoice.
     * Billing is optional: an absent or invalid snapshot returns null.
     * Do not recalculate taxes from a presumed VAT rate.
     *
     * @param int $order_id WooCommerce order ID.
     * @return array|null Invoice amounts, or null if unavailable/invalid.
     */
    public static function amounts_for_order( $order_id ) {
        $order_id = absint( $order_id );
        if ( ! $order_id || ! class_exists( 'SEO_Facturas_Documents' ) ||
            ! method_exists( 'SEO_Facturas_Documents', 'get_for_order' ) ) {
            return null;
        }

        $invoice = SEO_Facturas_Documents::get_for_order( $order_id, 'invoice' );
        if ( ! is_object( $invoice ) ||
            ! isset( $invoice->order_id, $invoice->document_type, $invoice->status, $invoice->document_number, $invoice->snapshot ) ||
            $order_id !== absint( $invoice->order_id ) ||
            'invoice' !== (string) $invoice->document_type ||
            'issued' !== (string) $invoice->status ) {
            return null;
        }

        $number = trim( (string) $invoice->document_number );
        if ( '' === $number || strlen( $number ) > 100 ) {
            return null;
        }

        $snapshot = json_decode( (string) $invoice->snapshot, true );
        if ( ! is_array( $snapshot ) ||
            ! isset( $snapshot['document'], $snapshot['order'], $snapshot['totals'] ) ||
            ! is_array( $snapshot['document'] ) ||
            ! is_array( $snapshot['order'] ) ||
            ! is_array( $snapshot['totals'] ) ||
            $order_id !== absint( isset( $snapshot['order']['id'] ) ? $snapshot['order']['id'] : 0 ) ||
            'invoice' !== (string) ( $snapshot['document']['type'] ?? '' ) ||
            $number !== (string) ( $snapshot['document']['number'] ?? '' ) ) {
            return null;
        }

        $totals = $snapshot['totals'];
        foreach ( array( 'base_total', 'total_tax', 'total' ) as $field ) {
            if ( ! isset( $totals[ $field ] ) || ! is_numeric( $totals[ $field ] ) ||
                ! is_finite( (float) $totals[ $field ] ) ) {
                return null;
            }
        }
        $base = round( (float) $totals['base_total'], 2 );
        $tax = round( (float) $totals['total_tax'], 2 );
        $total = round( (float) $totals['total'], 2 );
        if ( $base < 0 || $tax < 0 || $total < 0 || abs( $base + $tax - $total ) > 0.02 ) {
            return null;
        }
        $currency = isset( $snapshot['order']['currency'] ) ? (string) $snapshot['order']['currency'] : '';
        if ( ! preg_match( '/^[A-Z]{3}$/', $currency ) ) {
            return null;
        }
        $original = array(
            'document_no' => $number,
            'amount_base' => $base,
            'amount_tax' => $tax,
            'amount_total' => $total,
            'currency' => $currency,
        );
        $effective = function_exists( 'apply_filters' )
            ? apply_filters( 'taxocommerce_accounting_effective_issued_invoice', $original, $order_id )
            : $original;
        if ( ! is_array( $effective ) ) {
            return $original;
        }
        foreach ( array( 'amount_base', 'amount_tax', 'amount_total' ) as $field ) {
            if ( ! isset( $effective[ $field ] ) || ! is_numeric( $effective[ $field ] ) ||
                ! is_finite( (float) $effective[ $field ] ) || (float) $effective[ $field ] < 0 ) {
                return $original;
            }
        }
        $corrected_number = trim( (string) ( $effective['document_no'] ?? '' ) );
        // A corrected gross is accepted only when the independent issuer
        // explicitly verifies which original invoice amount it supersedes.
        // Legacy VAT-only integrations may omit the extra marker.
        $gross_changed = abs( (float) $effective['amount_total'] - $total ) > 0.02;
        if ( '' === $corrected_number || strlen( $corrected_number ) > 100 ||
            (string) ( $effective['currency'] ?? '' ) !== $currency ||
            ( $gross_changed && ( ! isset( $effective['rectification_original_total'] ) ||
                ! is_numeric( $effective['rectification_original_total'] ) ||
                abs( (float) $effective['rectification_original_total'] - $total ) > 0.02 ) ) ||
            abs( (float) $effective['amount_base'] + (float) $effective['amount_tax'] -
                (float) $effective['amount_total'] ) > 0.02 ) {
            return $original;
        }
        $verified_rectification = isset( $effective['rectification_original_total'] ) &&
            is_numeric( $effective['rectification_original_total'] ) &&
            abs( (float) $effective['rectification_original_total'] - $total ) <= 0.02;
        $result = array(
            'document_no' => $corrected_number,
            'amount_base' => round( (float) $effective['amount_base'], 2 ),
            'amount_tax' => round( (float) $effective['amount_tax'], 2 ),
            'amount_total' => round( (float) $effective['amount_total'], 2 ),
            'currency' => $currency,
        );
        if ( $verified_rectification ) {
            $result['rectification_original_total'] = $total;
        }
        return $result;
    }

}
