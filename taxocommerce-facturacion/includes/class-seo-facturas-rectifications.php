<?php
/**
 * Immutable rectifying invoices by substitution (VAT or product prices).
 *
 * Neither the WooCommerce order, original invoice, receipt nor original PDF
 * is rewritten. Every correction has its own numbered fiscal document.
 *
 * @package TaxoCommerce_Facturacion
 */
defined( 'ABSPATH' ) || exit;

final class SEO_Facturas_Rectifications {
    public static function table_name() {
        global $wpdb;
        return $wpdb->prefix . 'seo_factura_rectifications';
    }

    public static function install_schema() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $table = esc_sql( self::table_name() );
        $collate = $wpdb->get_charset_collate();
        dbDelta( "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            original_id bigint(20) unsigned NOT NULL,
            order_id bigint(20) unsigned NOT NULL,
            document_number varchar(64) NOT NULL,
            issued_at datetime NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'rendering',
            reason text NOT NULL,
            snapshot longtext NOT NULL,
            rendered_html longtext NULL,
            pdf_path text NULL,
            file_hash char(64) NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY original_id (original_id),
            UNIQUE KEY document_number (document_number),
            KEY order_id (order_id)
        ) {$collate};" );
    }

    /**
     * A single standard VAT rate can replace the original tax split, not the
     * amount already charged to the customer. No order write or refund occurs.
     *
     * @return array|WP_Error New totals in euros, or rejection.
     */
    /**
     * Accounting's current fiscal register can only substitute an existing
     * invoice inside its original VAT quarter. Later corrections require a
     * separately dated VAT adjustment, not retroactive replacement.
     */
    public static function same_vat_quarter( $original_date, $new_date ) {
        $original_date = (string) $original_date;
        $new_date = (string) $new_date;
        if ( ! preg_match( '/^[0-9]{4}-(0[1-9]|1[0-2])-[0-9]{2}/', $original_date ) ||
            ! preg_match( '/^[0-9]{4}-(0[1-9]|1[0-2])-[0-9]{2}/', $new_date ) ) {
            return false;
        }
        return substr( $original_date, 0, 4 ) === substr( $new_date, 0, 4 ) &&
            intdiv( (int) substr( $original_date, 5, 2 ) - 1, 3 ) ===
                intdiv( (int) substr( $new_date, 5, 2 ) - 1, 3 );
    }

    public static function replacement_amounts( $gross, $rate ) {
        $gross = (float) $gross;
        $rate = (float) $rate;
        if ( ! is_finite( $gross ) || $gross <= 0 || ! in_array( $rate, array( 0.0, 4.0, 10.0, 21.0 ), true ) ) {
            return new WP_Error( 'sf_rectification_amounts', __( 'Select an allowed VAT rate for a positive invoice.', 'taxocommerce-facturacion' ) );
        }
        $gross = round( $gross, 2 );
        $base = round( $gross / ( 1.0 + $rate / 100.0 ), 2 );
        $tax = round( $gross - $base, 2 );
        return array( 'base_total' => $base, 'total_tax' => $tax, 'total' => $gross, 'rate' => $rate );
    }

    /**
     * Reprice existing immutable items at a single chosen VAT rate.
     * Differences caused by rounding are absorbed by the last item only.
     *
     * @return array|WP_Error Correct line amounts without changing the order.
     */
    public static function replacement_lines( $items, $rate, $corrected_base, $gross_total ) {
        if ( ! is_array( $items ) || ! $items ) {
            return new WP_Error( 'sf_rectification_no_items', __( 'Original invoice does not have itemised products.', 'taxocommerce-facturacion' ) );
        }
        $lines = array();
        $all_gross = 0.0;
        $base_sum = 0.0;
        $classes = array();
        foreach ( $items as $item ) {
            if ( ! is_array( $item ) || ! isset( $item['total'], $item['total_tax'], $item['quantity'] ) ||
                ! is_numeric( $item['total'] ) || ! is_numeric( $item['total_tax'] ) ||
                ! is_numeric( $item['quantity'] ) || (float) $item['quantity'] <= 0 ) {
                return new WP_Error( 'sf_rectification_bad_items', __( 'Original invoice items cannot be safely repriced.', 'taxocommerce-facturacion' ) );
            }
            $classes[] = (string) ( $item['tax_class'] ?? '' );
            $gross = round( (float) $item['total'] + (float) $item['total_tax'], 2 );
            if ( ! is_finite( $gross ) || $gross <= 0 ) {
                return new WP_Error( 'sf_rectification_bad_item_total', __( 'Negative or empty item amount is not supported.', 'taxocommerce-facturacion' ) );
            }
            $line_base = round( $gross / ( 1.0 + (float) $rate / 100.0 ), 2 );
            $lines[] = array(
                'name' => (string) ( $item['name'] ?? '' ),
                'sku' => (string) ( $item['sku'] ?? '' ),
                'quantity' => (float) $item['quantity'],
                'base' => $line_base,
                'tax' => round( $gross - $line_base, 2 ),
                'gross' => $gross,
            );
            $base_sum += $line_base;
            $all_gross += $gross;
        }
        if ( count( array_unique( $classes ) ) > 1 ||
            abs( $all_gross - (float) $gross_total ) > 0.02 ) {
            return new WP_Error( 'sf_rectification_mixed', __( 'Mixed tax classes or incomplete item totals need an itemised correction.', 'taxocommerce-facturacion' ) );
        }
        $last = count( $lines ) - 1;
        $adjustment = round( (float) $corrected_base - $base_sum, 2 );
        if ( abs( $adjustment ) > 0.02 ) {
            return new WP_Error( 'sf_rectification_rounding', __( 'Invoice cannot be reconciled to a single tax rate.', 'taxocommerce-facturacion' ) );
        }
        $lines[ $last ]['base'] = round( $lines[ $last ]['base'] + $adjustment, 2 );
        $lines[ $last ]['tax'] = round( $lines[ $last ]['gross'] - $lines[ $last ]['base'], 2 );
        foreach ( $lines as &$line ) {
            $line['unit_net'] = round( $line['base'] / $line['quantity'], 4 );
        }
        unset( $line );
        return $lines;
    }

    /**
     * Correct net product-line bases, with a single verified VAT rate.
     * Never infer which item changed or spread a total adjustment arbitrarily.
     *
     * @param array $items Original immutable invoice lines.
     * @param float $rate Correct VAT rate.
     * @param array $bases New net amount per original line, in original order.
     * @return array|WP_Error Verified totals and itemised lines.
     */
    public static function replacement_by_line_base( $items, $rate, $bases ) {
        if ( ! is_array( $items ) || ! $items || ! is_array( $bases ) ||
            count( $items ) !== count( $bases ) ||
            ! in_array( (float) $rate, array( 0.0, 4.0, 10.0, 21.0 ), true ) ) {
            return new WP_Error( 'sf_rectification_lines', __( 'Provide one valid taxable base per original product line.', 'taxocommerce-facturacion' ) );
        }
        $lines = array();
        $classes = array();
        $base_total = 0.0;
        $tax_total = 0.0;
        foreach ( array_values( $items ) as $index => $item ) {
            if ( ! is_array( $item ) || ! isset( $item['quantity'], $item['total'], $item['total_tax'] ) ||
                ! is_numeric( $item['quantity'] ) || (float) $item['quantity'] <= 0 ||
                ! is_numeric( $item['total'] ) || ! is_numeric( $item['total_tax'] ) ||
                ! array_key_exists( $index, $bases ) ||
                ! is_scalar( $bases[ $index ] ) ||
                ! preg_match( '/^\\d{1,12}(?:\\.\\d{1,2})?$/', (string) $bases[ $index ] ) ) {
                return new WP_Error( 'sf_rectification_line_amount', __( 'Check every corrected product taxable base (up to two decimals).', 'taxocommerce-facturacion' ) );
            }
            $classes[] = (string) ( $item['tax_class'] ?? '' );
            $base = round( (float) $bases[ $index ], 2 );
            $tax = round( $base * (float) $rate / 100, 2 );
            $gross = round( $base + $tax, 2 );
            $lines[] = array(
                'name' => (string) ( $item['name'] ?? '' ),
                'sku' => (string) ( $item['sku'] ?? '' ),
                'quantity' => (float) $item['quantity'],
                'base' => $base,
                'tax' => $tax,
                'gross' => $gross,
                'unit_net' => round( $base / (float) $item['quantity'], 4 ),
            );
            $base_total += $base;
            $tax_total += $tax;
        }
        if ( count( array_unique( $classes ) ) > 1 ) {
            return new WP_Error( 'sf_rectification_mixed', __( 'Mixed VAT classes require a separate itemised fiscal workflow.', 'taxocommerce-facturacion' ) );
        }
        $base_total = round( $base_total, 2 );
        $tax_total = round( $tax_total, 2 );
        if ( $base_total <= 0 || ! is_finite( $base_total ) || ! is_finite( $tax_total ) ||
            ! is_finite( $base_total + $tax_total ) ) {
            return new WP_Error( 'sf_rectification_amounts', __( 'Corrected invoice total must be positive.', 'taxocommerce-facturacion' ) );
        }
        return array(
            'base_total' => $base_total,
            'total_tax' => $tax_total,
            'total' => round( $base_total + $tax_total, 2 ),
            'rate' => (float) $rate,
            'lines' => $lines,
        );
    }

    public static function get( $id ) {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Immutable original/rectification records, no stale cache.
        return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d LIMIT 1', self::table_name(), absint( $id ) ) );
    }

    public static function for_original( $original_id ) {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Latest independent document state.
        return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE original_id = %d LIMIT 1', self::table_name(), absint( $original_id ) ) );
    }

    public static function for_order( $order_id ) {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Latest independent document state.
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM %i WHERE order_id = %d AND status = 'issued' LIMIT 1",
            self::table_name(), absint( $order_id )
        ) );
    }

    public static function all_recent( $limit = 150 ) {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Independent fiscal register, read fresh.
        return (array) $wpdb->get_results( $wpdb->prepare(
            'SELECT * FROM %i ORDER BY id DESC LIMIT %d', self::table_name(), min( 150, max( 1, absint( $limit ) ) )
        ) );
    }

    /**
     * Issue a separate, linked fiscal document with original snapshot copied
     * as evidence. A unique original_id prevents accidental double issuance.
     */
    public static function issue( $original_id, $rate, $reason, $mode = 'vat', $corrected_bases = array() ) {
        global $wpdb;
        $original = SEO_Facturas_Documents::get( absint( $original_id ) );
        $reason = trim( sanitize_textarea_field( (string) $reason ) );
        if ( ! in_array( $mode, array( 'vat', 'amounts' ), true ) ) {
            return new WP_Error( 'sf_rectification_mode', __( 'Choose a supported rectification type.', 'taxocommerce-facturacion' ) );
        }
        if ( ! $original || SEO_Facturas_Documents::TYPE_INVOICE !== $original->document_type ||
            'issued' !== $original->status || strlen( $reason ) < 15 || strlen( $reason ) > 1000 ) {
            return new WP_Error( 'sf_rectification_invalid', __( 'An issued invoice and a detailed reason (15–1000 characters) are required.', 'taxocommerce-facturacion' ) );
        }
        if ( ! self::same_vat_quarter( $original->issued_at, current_time( 'mysql' ) ) ) {
            return new WP_Error( 'sf_rectification_period', __(
                'This workflow only supports corrections within the original VAT quarter. Cross-quarter corrections need a separate fiscal adjustment.',
                'taxocommerce-facturacion'
            ) );
        }
        $existing = self::for_original( $original->id );
        if ( $existing ) {
            return 'issued' === $existing->status ? $existing : self::retry_pdf( $existing );
        }
        $original_snapshot = SEO_Facturas_Documents::decode_snapshot( $original );
        if ( ! is_array( $original_snapshot ) || ! isset( $original_snapshot['totals'], $original_snapshot['order'], $original_snapshot['seller'], $original_snapshot['billing'] ) ||
            ! is_array( $original_snapshot['totals'] ) || ! isset( $original_snapshot['totals']['total'], $original_snapshot['totals']['base_total'], $original_snapshot['totals']['total_tax'] ) ||
            absint( $original_snapshot['order']['id'] ?? 0 ) !== absint( $original->order_id ) ||
            (string) ( $original_snapshot['document']['number'] ?? '' ) !== (string) $original->document_number ) {
            return new WP_Error( 'sf_rectification_snapshot', __( 'The original issued invoice cannot be verified.', 'taxocommerce-facturacion' ) );
        }
        $old = $original_snapshot['totals'];
        $total = (float) $old['total'];
        if ( ! is_finite( $total ) || ! is_numeric( $old['base_total'] ) || ! is_numeric( $old['total_tax'] ) ||
            abs( (float) $old['base_total'] + (float) $old['total_tax'] - $total ) > 0.02 ) {
            return new WP_Error( 'sf_rectification_original', __( 'The original invoice has inconsistent totals.', 'taxocommerce-facturacion' ) );
        }
        // A safe correction requires a single tax rate and verifiable product
        // lines. Do not alter unrelated shipping, discounts, fees or refunds.
        if ( count( (array) ( $original_snapshot['shipping_lines'] ?? array() ) ) > 0 &&
            abs( (float) ( $old['shipping_total'] ?? 0 ) ) > 0.001 ) {
            return new WP_Error( 'sf_rectification_scope', __( 'Invoices with charged shipping need a separate itemised correction.', 'taxocommerce-facturacion' ) );
        }
        if ( abs( (float) ( $old['fee_total'] ?? 0 ) ) > 0.001 ||
            abs( (float) ( $old['discount_total'] ?? 0 ) ) > 0.001 ) {
            return new WP_Error( 'sf_rectification_scope', __( 'Invoices with discounts or fees need a separate itemised correction.', 'taxocommerce-facturacion' ) );
        }
        if ( count( (array) ( $original_snapshot['tax_lines'] ?? array() ) ) > 1 ) {
            return new WP_Error( 'sf_rectification_multiple_rates', __( 'Multiple historical tax rates need an itemised correction.', 'taxocommerce-facturacion' ) );
        }
        if ( 'amounts' === $mode ) {
            $calculated = self::replacement_by_line_base( $original_snapshot['items'] ?? array(),
                $rate, $corrected_bases );
            if ( is_wp_error( $calculated ) ) {
                return $calculated;
            }
            $corrected_lines = $calculated['lines'];
            $replacement = $calculated;
        } else {
            $replacement = self::replacement_amounts( $total, $rate );
            if ( is_wp_error( $replacement ) ) {
                return $replacement;
            }
            $corrected_lines = self::replacement_lines( $original_snapshot['items'] ?? array(),
                $rate, $replacement['base_total'], $total );
            if ( is_wp_error( $corrected_lines ) ) {
                return $corrected_lines;
            }
        }
        if ( abs( $replacement['total_tax'] - (float) $old['total_tax'] ) <= 0.005 &&
            abs( $replacement['base_total'] - (float) $old['base_total'] ) <= 0.005 &&
            abs( $replacement['total'] - $total ) <= 0.005 ) {
            return new WP_Error( 'sf_rectification_unchanged', __( 'The corrected tax split is identical to the original.', 'taxocommerce-facturacion' ) );
        }

        $reserved = SEO_Facturas_Documents::reserve_number( SEO_Facturas_Documents::TYPE_RECTIFICATION );
        if ( is_wp_error( $reserved ) ) {
            return $reserved;
        }
        $snapshot = $original_snapshot;
        $snapshot['document']['type'] = SEO_Facturas_Documents::TYPE_RECTIFICATION;
        $snapshot['document']['title'] = __( 'RECTIFYING INVOICE', 'taxocommerce-facturacion' );
        $snapshot['document']['number'] = $reserved['number'];
        $snapshot['document']['issued_at'] = $reserved['issued_at'];
        $snapshot['rectification'] = array(
            'method' => 'substitution',
            'mode' => $mode,
            'original_number' => (string) $original->document_number,
            'original_issued_at' => (string) $original->issued_at,
            'reason' => $reason,
            'old_base' => round( (float) $old['base_total'], 2 ),
            'old_tax' => round( (float) $old['total_tax'], 2 ),
            'old_total' => round( $total, 2 ),
            'delta_total' => round( $replacement['total'] - $total, 2 ),
            'rate' => $replacement['rate'],
        );
        $snapshot['rectification_items'] = $corrected_lines;
        $snapshot['totals']['base_total'] = $replacement['base_total'];
        $snapshot['totals']['total_tax'] = $replacement['total_tax'];
        $snapshot['totals']['total'] = $replacement['total'];
        // Do not reproduce the original per-product net amounts as corrected:
        // they were sourced from a tax-free order and can now be misleading.
        $html = SEO_Facturas_PDF::render_html( $snapshot );
        if ( is_wp_error( $html ) ) {
            return $html;
        }
        $now = current_time( 'mysql' );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Atomic unique fiscal document insertion (one rectification per original invoice).
        $saved = $wpdb->insert( self::table_name(), array(
            'original_id' => absint( $original->id ), 'order_id' => absint( $original->order_id ),
            'document_number' => $reserved['number'], 'issued_at' => $reserved['issued_at'],
            'status' => 'rendering', 'reason' => $reason,
            'snapshot' => wp_json_encode( $snapshot, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ),
            'rendered_html' => $html, 'created_at' => $now,
        ), array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ) );
        if ( ! $saved ) {
            $existing = self::for_original( $original->id );
            return $existing ? $existing : new WP_Error( 'sf_rectification_save', __( 'The rectifying invoice could not be saved.', 'taxocommerce-facturacion' ) );
        }
        return self::retry_pdf( self::get( $wpdb->insert_id ) );
    }

    public static function retry_pdf( $rectification ) {
        global $wpdb;
        if ( ! $rectification || ! in_array( $rectification->status, array( 'rendering', 'issued' ), true ) ) {
            return new WP_Error( 'sf_rectification_state', __( 'Invalid rectifying invoice.', 'taxocommerce-facturacion' ) );
        }
        if ( 'issued' === $rectification->status ) {
            return $rectification;
        }
        $pdf = SEO_Facturas_PDF::create_pdf( $rectification->id, $rectification->document_number,
            $rectification->issued_at, $rectification->rendered_html );
        if ( is_wp_error( $pdf ) ) {
            return $pdf;
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Finalise the original immutable fiscal document only when PDF is stored.
        $updated = $wpdb->update( self::table_name(), array(
            'status' => 'issued', 'pdf_path' => $pdf['path'], 'file_hash' => $pdf['hash'],
        ), array( 'id' => absint( $rectification->id ), 'status' => 'rendering' ),
            array( '%s', '%s', '%s' ), array( '%d', '%s' ) );
        if ( ! $updated ) {
            return new WP_Error( 'sf_rectification_pdf_update', __( 'The rectification PDF was generated but could not be finalised.', 'taxocommerce-facturacion' ) );
        }
        $issued = self::get( $rectification->id );
        do_action( 'taxocommerce_billing_rectification_issued', absint( $issued->order_id ), $issued );
        return $issued;
    }

    /**
     * Optional integration point for Accounting (never a required dependency).
     * A verified issued rectification supersedes fiscal totals (including
     * the corrected gross); the original and WooCommerce order stay intact.
     */
    public static function effective_invoice( $original, $order_id ) {
        if ( ! is_array( $original ) || empty( $original['document_no'] ) ) {
            return $original;
        }
        $issued = self::for_order( $order_id );
        if ( ! $issued ) {
            return $original;
        }
        $original_document = SEO_Facturas_Documents::get( $issued->original_id );
        if ( ! $original_document || (string) $original_document->document_number !== (string) $original['document_no'] ) {
            return $original;
        }
        $snapshot = json_decode( (string) $issued->snapshot, true );
        $totals = is_array( $snapshot ) ? ( $snapshot['totals'] ?? null ) : null;
        if ( ! is_array( $totals ) || ! isset( $totals['base_total'], $totals['total_tax'], $totals['total'] ) ) {
            return $original;
        }
        $base = (float) $totals['base_total'];
        $tax = (float) $totals['total_tax'];
        $gross = (float) $totals['total'];
        if ( ! is_finite( $base ) || ! is_finite( $tax ) || ! is_finite( $gross ) ||
            $base < 0 || $tax < 0 || $gross <= 0 || abs( $base + $tax - $gross ) > 0.02 ||
            ! isset( $snapshot['rectification']['old_total'] ) ||
            ! is_numeric( $snapshot['rectification']['old_total'] ) ||
            ( isset( $original['amount_total'] ) &&
                ( ! is_numeric( $original['amount_total'] ) ||
                    abs( (float) $snapshot['rectification']['old_total'] - (float) $original['amount_total'] ) > 0.02 ) ) ) {
            return $original;
        }
        $corrected = array(
            'document_no' => (string) $issued->document_number,
            'amount_base' => round( $base, 2 ),
            'amount_tax' => round( $tax, 2 ),
            'amount_total' => round( $gross, 2 ),
        );
        if ( isset( $original['amount_total'] ) && is_numeric( $original['amount_total'] ) ) {
            $corrected['rectification_original_total'] = round( (float) $original['amount_total'], 2 );
        }
        return array_merge( $original, $corrected );
    }

    /**
     * Inline administration control for an existing invoice row.
     *
     * @return string Form HTML.
     */
    public static function render_action( $original ) {
        if ( ! $original || 'issued' !== (string) $original->status ||
            SEO_Facturas_Documents::TYPE_INVOICE !== (string) $original->document_type ) {
            return '';
        }
        $existing = self::for_original( $original->id );
        if ( $existing ) {
            return '<span>' . esc_html__( 'Rectification', 'taxocommerce-facturacion' ) . ': <strong>' .
                esc_html( $existing->document_number ) . '</strong></span>';
        }
        $html = '<details class="sf-rectification-form"><summary>' .
            esc_html__( 'Rectify issued invoice', 'taxocommerce-facturacion' ) . '</summary>';
        $html .= '<p class="description">' .
            esc_html__( 'An issued invoice cannot be overwritten. Create a numbered rectifying invoice by substitution; the original document, WooCommerce order and received payments are retained.', 'taxocommerce-facturacion' ) .
            '</p><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        $html .= '<input type="hidden" name="action" value="seo_facturas_rectify">';
        $html .= '<input type="hidden" name="original_id" value="' . esc_attr( (string) $original->id ) . '">';
        $html .= wp_nonce_field( 'sf_rectify_' . $original->id, '_wpnonce', true, false );
        $html .= '<p><label>' . esc_html__( 'Correction type', 'taxocommerce-facturacion' ) .
            ' <select name="correction_mode"><option value="vat">' .
            esc_html__( 'Correct VAT only (same invoice total)', 'taxocommerce-facturacion' ) .
            '</option><option value="amounts">' .
            esc_html__( 'Correct product net amounts and VAT', 'taxocommerce-facturacion' ) .
            '</option></select></label></p>';
        $html .= '<p><label>' . esc_html__( 'Correct VAT rate', 'taxocommerce-facturacion' ) .
            ' <select name="rate" required>';
        foreach ( array( '21' => '21 %', '10' => '10 %', '4' => '4 %', '0' => '0 %' ) as $value => $label ) {
            $html .= '<option value="' . esc_attr( $value ) . '">' . esc_html( $label ) . '</option>';
        }
        $html .= '</select></label></p>';
        $snapshot = SEO_Facturas_Documents::decode_snapshot( $original );
        $items = is_array( $snapshot ) ? (array) ( $snapshot['items'] ?? array() ) : array();
        if ( $items ) {
            $html .= '<p class="description">' .
                esc_html__( 'For amount corrections, enter the NEW net taxable base for each original product line (not the difference). These values are ignored in VAT-only mode. The VAT and final invoice total are calculated automatically.', 'taxocommerce-facturacion' ) .
                '</p><table class="widefat striped"><thead><tr><th>' .
                esc_html__( 'Original product line', 'taxocommerce-facturacion' ) . '</th><th>' .
                esc_html__( 'Corrected net taxable base', 'taxocommerce-facturacion' ) .
                '</th></tr></thead><tbody>';
            foreach ( array_values( $items ) as $index => $item ) {
                $net = round( (float) ( $item['total'] ?? 0 ), 2 );
                $html .= '<tr><td>' . esc_html( (string) ( $item['name'] ?? '' ) ) .
                    '</td><td><input type="number" name="corrected_bases[' . esc_attr( (string) $index ) .
                    ']" min="0" step="0.01" value="' . esc_attr( number_format( $net, 2, '.', '' ) ) .
                    '" aria-label="' . esc_attr__( 'Corrected net taxable base', 'taxocommerce-facturacion' ) .
                    '"></td></tr>';
            }
            $html .= '</tbody></table>';
        }
        $html .= '<p><label>' .
            esc_html__( 'Mandatory reason for rectification', 'taxocommerce-facturacion' ) .
            ' <textarea name="reason" rows="3" minlength="15" maxlength="1000" required></textarea></label></p>';
        $html .= '<p><label><input type="checkbox" name="confirmed" value="1" required> ' .
            esc_html__( 'I have verified the tax treatment and authorise issuing a separate rectifying invoice.', 'taxocommerce-facturacion' ) .
            '</label></p>';
        $html .= '<button type="submit" class="button button-secondary">' .
            esc_html__( 'Issue rectifying invoice', 'taxocommerce-facturacion' ) . '</button></form></details>';
        return $html;
    }

    public static function render_register() {
        $rows = self::all_recent();
        echo '<h2>' . esc_html__( 'Issued rectifying invoices', 'taxocommerce-facturacion' ) . '</h2>';
        echo '<p class="description">' . esc_html__(
            'Rectifying invoices preserve the original invoice and WooCommerce order. A corrected tax split never creates a second payment.',
            'taxocommerce-facturacion'
        ) . '</p>';
        echo '<table class="widefat striped"><thead><tr><th>' .
            esc_html__( 'Rectifying invoice', 'taxocommerce-facturacion' ) . '</th><th>' .
            esc_html__( 'Original invoice', 'taxocommerce-facturacion' ) . '</th><th>' .
            esc_html__( 'Order', 'taxocommerce-facturacion' ) . '</th><th>' .
            esc_html__( 'Issue date', 'taxocommerce-facturacion' ) . '</th><th>' .
            esc_html__( 'Reason', 'taxocommerce-facturacion' ) . '</th><th>' .
            esc_html__( 'Actions', 'taxocommerce-facturacion' ) . '</th></tr></thead><tbody>';
        foreach ( $rows as $row ) {
            $original = SEO_Facturas_Documents::get( $row->original_id );
            echo '<tr><td>' . esc_html( $row->document_number ) . '</td><td>' .
                esc_html( $original ? $original->document_number : '' ) . '</td><td>#' .
                esc_html( $row->order_id ) . '</td><td>' . esc_html( $row->issued_at ) .
                '</td><td>' . esc_html( $row->reason ) . '</td><td>';
            if ( 'issued' === $row->status ) {
                $download = wp_nonce_url( admin_url( 'admin-post.php?action=seo_facturas_rectification_download&rectification_id=' . absint( $row->id ) ),
                    'sf_rectification_download_' . $row->id );
                echo '<a class="button button-small" href="' . esc_url( $download ) . '">' .
                    esc_html__( 'PDF', 'taxocommerce-facturacion' ) . '</a> ';
                $email = wp_nonce_url( admin_url( 'admin-post.php?action=seo_facturas_rectification_email&rectification_id=' . absint( $row->id ) ),
                    'sf_rectification_email_' . $row->id );
                echo '<a class="button button-small" href="' . esc_url( $email ) . '">' .
                    esc_html__( 'Email customer', 'taxocommerce-facturacion' ) . '</a>';
            } else {
                $retry = wp_nonce_url( admin_url( 'admin-post.php?action=seo_facturas_rectification_retry&rectification_id=' . absint( $row->id ) ),
                    'sf_rectification_retry_' . $row->id );
                echo '<a class="button button-small" href="' . esc_url( $retry ) . '">' .
                    esc_html__( 'Retry PDF generation', 'taxocommerce-facturacion' ) . '</a>';
            }
            echo '</td></tr>';
        }
        if ( ! $rows ) {
            echo '<tr><td colspan="6">' . esc_html__( 'No rectifying invoices have been issued.', 'taxocommerce-facturacion' ) . '</td></tr>';
        }
        echo '</tbody></table>';
    }

    private static function capable() {
        return current_user_can( 'manage_woocommerce' ) || current_user_can( 'manage_options' );
    }

    private static function redirect( $args ) {
        wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php?page=taxocommerce-facturacion&tab=documents' ) ) );
        exit;
    }

    public static function handle_issue() {
        if ( ! self::capable() ) {
            wp_die( esc_html__( 'Access denied.', 'taxocommerce-facturacion' ) );
        }
        $original_id = isset( $_POST['original_id'] ) ? absint( wp_unslash( $_POST['original_id'] ) ) : 0;
        check_admin_referer( 'sf_rectify_' . $original_id );
        $rate = isset( $_POST['rate'] ) ? sanitize_text_field( wp_unslash( $_POST['rate'] ) ) : '';
        $mode = isset( $_POST['correction_mode'] ) ? sanitize_key( wp_unslash( $_POST['correction_mode'] ) ) : 'vat';
        $bases = isset( $_POST['corrected_bases'] ) && is_array( $_POST['corrected_bases'] )
            ? array_map( 'sanitize_text_field', wp_unslash( $_POST['corrected_bases'] ) ) : array();
        $reason = isset( $_POST['reason'] ) ? sanitize_textarea_field( wp_unslash( $_POST['reason'] ) ) : '';
        $confirmed = isset( $_POST['confirmed'] ) && '1' === sanitize_key( wp_unslash( $_POST['confirmed'] ) );
        if ( ! $confirmed || ! in_array( $mode, array( 'vat', 'amounts' ), true ) ||
            ! in_array( $rate, array( '0', '4', '10', '21' ), true ) ) {
            self::redirect( array( 'sf_error' => __( 'Confirm the correction and select a supported VAT rate.', 'taxocommerce-facturacion' ) ) );
        }
        $issued = self::issue( $original_id, (float) $rate, $reason, $mode, $bases );
        if ( is_wp_error( $issued ) ) {
            self::redirect( array( 'sf_error' => $issued->get_error_message() ) );
        }
        self::redirect( array( 'sf_notice' => 'rectification_issued' ) );
    }

    public static function handle_pdf() {
        if ( ! self::capable() ) {
            wp_die( esc_html__( 'Access denied.', 'taxocommerce-facturacion' ) );
        }
        $id = isset( $_GET['rectification_id'] ) ? absint( wp_unslash( $_GET['rectification_id'] ) ) : 0;
        check_admin_referer( 'sf_rectification_download_' . $id );
        $document = self::get( $id );
        $uploads = wp_upload_dir( null, false );
        $base = ! empty( $uploads['basedir'] ) && empty( $uploads['error'] )
            ? realpath( trailingslashit( $uploads['basedir'] ) . 'seo-facturas' ) : false;
        $path = $document && 'issued' === $document->status ? realpath( (string) $document->pdf_path ) : false;
        if ( ! $base || ! $path || 0 !== strpos( $path, $base . DIRECTORY_SEPARATOR ) ||
            ! is_readable( $path ) || ! hash_equals( (string) $document->file_hash, (string) hash_file( 'sha256', $path ) ) ) {
            wp_die( esc_html__( 'The rectifying invoice PDF is unavailable or invalid.', 'taxocommerce-facturacion' ) );
        }
        nocache_headers();
        header( 'Content-Type: application/pdf' );
        header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $document->document_number . '.pdf' ) . '"' );
        header( 'Content-Length: ' . filesize( $path ) );
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- Authenticated, path-restricted streaming of verified private fiscal PDF.
        readfile( $path );
        exit;
    }

    public static function handle_retry() {
        if ( ! self::capable() ) {
            wp_die( esc_html__( 'Access denied.', 'taxocommerce-facturacion' ) );
        }
        $id = isset( $_GET['rectification_id'] ) ? absint( wp_unslash( $_GET['rectification_id'] ) ) : 0;
        check_admin_referer( 'sf_rectification_retry_' . $id );
        $issued = self::retry_pdf( self::get( $id ) );
        self::redirect( is_wp_error( $issued )
            ? array( 'sf_error' => $issued->get_error_message() )
            : array( 'sf_notice' => 'rectification_issued' ) );
    }

    public static function handle_email() {
        global $wpdb;
        if ( ! self::capable() ) {
            wp_die( esc_html__( 'Access denied.', 'taxocommerce-facturacion' ) );
        }
        $id = isset( $_GET['rectification_id'] ) ? absint( wp_unslash( $_GET['rectification_id'] ) ) : 0;
        check_admin_referer( 'sf_rectification_email_' . $id );
        $document = self::get( $id );
        $uploads = wp_upload_dir( null, false );
        $base = ! empty( $uploads['basedir'] ) && empty( $uploads['error'] )
            ? realpath( trailingslashit( $uploads['basedir'] ) . 'seo-facturas' ) : false;
        $path = $document && 'issued' === $document->status ? realpath( (string) $document->pdf_path ) : false;
        $order = $document && function_exists( 'wc_get_order' ) ? wc_get_order( absint( $document->order_id ) ) : false;
        if ( ! $base || ! $path || 0 !== strpos( $path, $base . DIRECTORY_SEPARATOR ) ||
            ! is_readable( $path ) || ! hash_equals( (string) $document->file_hash, (string) hash_file( 'sha256', $path ) ) ||
            ! $order || ! is_email( $order->get_billing_email() ) ) {
            self::redirect( array( 'sf_error' => __( 'Cannot deliver rectifying invoice without its verified PDF and customer email.', 'taxocommerce-facturacion' ) ) );
        }
        $original = SEO_Facturas_Documents::get( $document->original_id );
        $sent = wp_mail( sanitize_email( $order->get_billing_email() ),
            /* translators: %s: rectifying invoice number. */
            sprintf( __( 'Rectifying invoice %s', 'taxocommerce-facturacion' ), $document->document_number ),
            /* translators: 1: rectifying invoice number, 2: original invoice number. */
            sprintf( __( 'Attached is rectifying invoice %1$s relating to original invoice %2$s. Check the corrected amount and any outstanding balance; existing receipts are not changed.', 'taxocommerce-facturacion' ),
                $document->document_number, $original ? $original->document_number : '' ),
            array( 'Content-Type: text/plain; charset=UTF-8' ), array( $path ) );
        if ( ! $sent ) {
            self::redirect( array( 'sf_error' => __( 'WordPress could not send the rectifying invoice.', 'taxocommerce-facturacion' ) ) );
        }
        self::redirect( array( 'sf_notice' => 'rectification_emailed' ) );
    }

}
add_filter( 'taxocommerce_accounting_effective_issued_invoice', array( 'SEO_Facturas_Rectifications', 'effective_invoice' ), 10, 2 );
add_action( 'admin_post_seo_facturas_rectify', array( 'SEO_Facturas_Rectifications', 'handle_issue' ) );
add_action( 'admin_post_seo_facturas_rectification_download', array( 'SEO_Facturas_Rectifications', 'handle_pdf' ) );
add_action( 'admin_post_seo_facturas_rectification_retry', array( 'SEO_Facturas_Rectifications', 'handle_retry' ) );
add_action( 'admin_post_seo_facturas_rectification_email', array( 'SEO_Facturas_Rectifications', 'handle_email' ) );
