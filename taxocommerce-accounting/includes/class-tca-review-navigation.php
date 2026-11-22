<?php
/**
 * Read-only Accounting review navigation.
 *
 * Pending fiscal approval is not an application error; verified invoice
 * mismatches and document/VAT warnings are shown separately.
 *
 * @package TaxoCommerce_Accounting
 */
defined( 'ABSPATH' ) || exit;

final class TCA_Review_Navigation {
    /**
     * A validated non-domestic treatment is not automatically pending.
     *
     * @param object $entry Accounting ledger row.
     * @return bool Whether explicit review or approval is outstanding.
     */
    public static function pending( $entry ) {
        return 'validated' !== (string) $entry->status ||
            'review' === (string) $entry->treatment;
    }

    /**
     * Describe an actionable record without guessing whether its VAT is wrong.
     *
     * @param object $entry Accounting ledger row.
     * @return array{pending:bool,incident:bool,error:bool,message:string}
     */
    public static function inspect( $entry ) {
        $pending = self::pending( $entry );
        if ( 'sale' === (string) $entry->kind &&
            'woocommerce' === (string) $entry->source &&
            tca_sale_invoice_mismatch( $entry ) ) {
            return array(
                'pending' => $pending,
                'incident' => true,
                'error' => true,
                'message' => __( 'Issued invoice and accounting totals disagree.', 'taxocommerce-accounting' ),
            );
        }
        if ( 'validated' === (string) $entry->status &&
            'domestic' === (string) $entry->treatment &&
            tca_needs_zero_vat_confirmation( $entry ) ) {
            return array(
                'pending' => $pending,
                'incident' => true,
                'error' => false,
                'message' => __( 'Check the domestic sale with zero VAT.', 'taxocommerce-accounting' ),
            );
        }
        if ( in_array( (string) $entry->source, array( 'woocommerce', 'wc_refund' ), true ) &&
            in_array( (string) $entry->kind, array( 'sale', 'refund' ), true ) &&
            '' === trim( (string) $entry->document_no ) &&
            ( 'sale' !== (string) $entry->kind || '' === TCA_Issued_Invoice::for_order( absint( $entry->source_id ) ) ) ) {
            return array(
                'pending' => $pending,
                'incident' => true,
                'error' => false,
                'message' => __( 'Check the missing invoice reference.', 'taxocommerce-accounting' ),
            );
        }
        return array(
            'pending' => $pending,
            'incident' => false,
            'error' => false,
            'message' => 'review' === (string) $entry->treatment
                ? __( 'Tax treatment requires review.', 'taxocommerce-accounting' )
                : __( 'Pending validation.', 'taxocommerce-accounting' ),
        );
    }

    /**
     * Point to the existing correction form for errors, otherwise the inline
     * validation row. Never carry raw input into URL construction.
     *
     * @param object $entry Ledger row.
     * @param array|null $state Precomputed issue details.
     * @return string Internal administration URL.
     */
    public static function entry_url( $entry, $state = null ) {
        $state = null === $state ? self::inspect( $entry ) : $state;
        $tab = TCA_Corrections::tab_for( (string) $entry->kind );
        $id = absint( $entry->id );
        $args = array( 'year' => absint( substr( (string) $entry->fiscal_date, 0, 4 ) ) );
        if ( $state['error'] || 'validated' === (string) $entry->status ) {
            $args['edit_entry'] = $id;
            return tca_admin_url( $tab, $args ) . '#tca-edit-entry-' . $id;
        }
        $args['focus_entry'] = $id;
        return tca_admin_url( $tab, $args ) . '#tca-entry-' . $id;
    }

    /**
     * Count observed incidents, not ordinary unvalidated records.
     *
     * @param array $rows Bounded ledger rows for the selected financial year.
     * @return array Month to list of affected entries.
     */
    public static function incidents_by_month( $rows ) {
        $months = array();
        foreach ( $rows as $entry ) {
            $state = self::inspect( $entry );
            if ( $state['incident'] ) {
                $month = substr( (string) $entry->fiscal_date, 0, 7 );
                $months[ $month ][] = array( 'entry' => $entry, 'state' => $state );
            }
        }
        return $months;
    }

    /**
     * Link single records immediately; open a scoped list for multiple rows.
     *
     * @param string $month YYYY-MM.
     * @param string $type pending or incident.
     * @param array $matches List of entries with precomputed states.
     * @return string Escaped URL for use in an HTML anchor.
     */
    public static function summary_url( $month, $type, $matches ) {
        if ( 1 === count( $matches ) ) {
            return self::entry_url( $matches[0]['entry'], $matches[0]['state'] );
        }
        return tca_admin_url(
            'summary',
            array( 'year' => absint( substr( $month, 0, 4 ) ), 'review_month' => $month, 'review_type' => $type )
        ) . '#tca-review-queue';
    }

    /**
     * Render the selected monthly subset, with direct existing edit/approval links.
     *
     * @param array $rows Bounded ledger rows for financial year.
     * @param int $year Financial year.
     * @return void
     */
    public static function render_queue( $rows, $year ) {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only navigation; changing records requires existing protected actions.
        $month = isset( $_GET['review_month'] ) && is_scalar( $_GET['review_month'] ) ? sanitize_text_field( wp_unslash( $_GET['review_month'] ) ) : '';
        if ( ! preg_match( '/^\d{4}-(0[1-9]|1[0-2])$/', $month ) || (int) substr( $month, 0, 4 ) !== (int) $year ) {
            return;
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only navigation filter.
        $type = isset( $_GET['review_type'] ) && is_scalar( $_GET['review_type'] ) ? sanitize_key( wp_unslash( $_GET['review_type'] ) ) : 'pending';
        if ( ! in_array( $type, array( 'pending', 'incident' ), true ) ) {
            $type = 'pending';
        }
        $matches = array();
        foreach ( $rows as $entry ) {
            if ( $month !== substr( (string) $entry->fiscal_date, 0, 7 ) ) {
                continue;
            }
            $state = self::inspect( $entry );
            if ( $state[ $type ] ) {
                $matches[] = array( 'entry' => $entry, 'state' => $state );
            }
        }
        echo '<section id="tca-review-queue" aria-labelledby="tca-review-heading">';
        echo '<h3 id="tca-review-heading">' . esc_html( 'incident' === $type
            ? __( 'Incidents requiring review', 'taxocommerce-accounting' )
            : __( 'Pending review', 'taxocommerce-accounting' ) ) . ': ' . esc_html( $month ) . '</h3>';
        if ( ! $matches ) {
            echo '<p>' . esc_html__( 'No matching records remain for this month.', 'taxocommerce-accounting' ) . '</p>';
        } else {
            echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Date', 'taxocommerce-accounting' ) . '</th><th>' .
                esc_html__( 'Document', 'taxocommerce-accounting' ) . '</th><th>' .
                esc_html__( 'Concept', 'taxocommerce-accounting' ) . '</th><th>' .
                esc_html__( 'Status', 'taxocommerce-accounting' ) . '</th><th>' .
                esc_html__( 'Action', 'taxocommerce-accounting' ) . '</th></tr></thead><tbody>';
            foreach ( $matches as $match ) {
                $entry = $match['entry'];
                $state = $match['state'];
                $target = self::entry_url( $entry, $state );
                echo '<tr><td>' . esc_html( $entry->fiscal_date ) . '</td><td>' .
                    esc_html( $entry->document_no ) . '</td><td>' . esc_html( $entry->concept ) .
                    '</td><td>' . esc_html( $state['message'] ) . '</td><td><a class="button button-small" href="' .
                    esc_url( $target ) . '">' . esc_html__( 'Review', 'taxocommerce-accounting' ) . '</a></td></tr>';
            }
            echo '</tbody></table>';
        }
        echo '</section>';
    }
}
