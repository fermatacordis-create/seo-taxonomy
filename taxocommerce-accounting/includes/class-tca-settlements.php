<?php
/**
 * Explicit settlement records link fiscal entries to real cash or bank movements.
 *
 * @package TaxoCommerceAccounting
 */
defined( 'ABSPATH' ) || exit;

final class TCA_Settlements {
    public static function table() {
        global $wpdb;
        return $wpdb->prefix . 'tca_treasury_settlements';
    }

    public static function install_schema() {
        global $wpdb;
        $table = esc_sql( self::table() );
        $collation = $wpdb->get_charset_collate();
        dbDelta( "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            entry_id bigint(20) unsigned NOT NULL,
            movement_id bigint(20) unsigned NOT NULL,
            request_key varchar(64) NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY movement_id (movement_id),
            UNIQUE KEY request_key (request_key),
            KEY entry_id (entry_id)
        ) {$collation};" );
    }

    public static function direction( $kind ) {
        return 'sale' === $kind ? 'in' : 'out';
    }

    public static function outstanding( $total, $already_paid ) {
        return max( 0.0, round( abs( (float) $total ) - (float) $already_paid, 2 ) );
    }

    public static function sum( $entry_id ) {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Real-time settlements of plugin-owned tables.
        return (float) $wpdb->get_var( $wpdb->prepare(
            'SELECT COALESCE(SUM(m.amount),0) FROM %i AS s INNER JOIN %i AS m ON m.id = s.movement_id WHERE s.entry_id = %d',
            self::table(), TCA_Treasury::movements_table(), absint( $entry_id )
        ) );
    }

    public static function accounts() {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Needed to select an actual cash or bank account.
        return (array) $wpdb->get_results( $wpdb->prepare(
            'SELECT id, name, kind, opened_on FROM %i ORDER BY kind DESC, name, id', TCA_Treasury::accounts_table()
        ) );
    }

    public static function settle( $entry_id, $account_id, $date, $amount, $key ) {
        global $wpdb;
        $entry_id = absint( $entry_id );
        $account_id = absint( $account_id );
        $amount = tca_decimal( $amount );
        if ( ! $entry_id || ! $account_id || ! is_string( $key ) || ! preg_match( '/^[a-f0-9-]{36}$/', $key ) ||
            ! tca_valid_date( $date ) || $date > current_time( 'Y-m-d' ) || null === $amount || $amount <= 0 ) {
            return new WP_Error( 'settlement_invalid', __( 'Check payment date, account and amount.', 'taxocommerce-accounting' ) );
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Atomic settlement and duplicate-request check.
        $wpdb->query( 'START TRANSACTION' );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Lock entry to prevent concurrent overpayment.
        $entry = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d FOR UPDATE', tca_table(), $entry_id ) );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Idempotency key unique across all payments.
        $existing = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE request_key = %s', self::table(), $key ) );
        if ( $existing ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- End idempotent request.
            $wpdb->query( 'COMMIT' );
            return true;
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Validate chosen account still exists.
        $account = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', TCA_Treasury::accounts_table(), $account_id ) );
        if ( ! $entry || ! in_array( $entry->kind, array( 'sale', 'refund', 'purchase', 'expense', 'asset', 'intra' ), true ) ||
            ! $account || ! in_array( $account->kind, array( 'cash', 'bank' ), true ) ||
            $date < $account->opened_on ||
            $amount > self::outstanding( $entry->amount_total, self::sum( $entry_id ) ) ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Invalid settlement never posts movement.
            $wpdb->query( 'ROLLBACK' );
            return new WP_Error( 'settlement_invalid', __( 'Payment exceeds the outstanding amount or has invalid dates/account.', 'taxocommerce-accounting' ) );
        }
        $direction = self::direction( $entry->kind );
        // Refund entries are negative totals in some WooCommerce installations; use absolute amount and money out.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Atomic write to the owned treasury ledger.
        $saved = $wpdb->insert( TCA_Treasury::movements_table(), array(
            'account_id' => $account_id,
            'posted_on' => $date,
            'direction' => $direction,
            'amount' => $amount,
            'reference' => substr( $entry->document_no ?: (string) $entry->id, 0, 191 ),
            'created_at' => current_time( 'mysql' ),
        ), array( '%d', '%s', '%s', '%f', '%s', '%s' ) );
        $movement_id = $saved ? (int) $wpdb->insert_id : 0;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Unique settlement link within the same transaction.
        $link = $saved && $wpdb->insert( self::table(), array(
            'entry_id' => $entry_id, 'movement_id' => $movement_id,
            'request_key' => $key, 'created_at' => current_time( 'mysql' ),
        ), array( '%d', '%d', '%s', '%s' ) );
        if ( ! $link ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- No orphan movement.
            $wpdb->query( 'ROLLBACK' );
            return new WP_Error( 'settlement_failed', __( 'Payment could not be recorded.', 'taxocommerce-accounting' ) );
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Commit the payment and ledger link together.
        $wpdb->query( 'COMMIT' );
        return true;
    }

    public static function handle() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( esc_html__( 'Insufficient permissions.', 'taxocommerce-accounting' ) );
        }
        $entry_id = isset( $_POST['entry_id'] ) ? absint( wp_unslash( $_POST['entry_id'] ) ) : 0;
        check_admin_referer( 'tca_settle_' . $entry_id );
        $account_id = isset( $_POST['account_id'] ) ? absint( wp_unslash( $_POST['account_id'] ) ) : 0;
        $date = isset( $_POST['posted_on'] ) ? sanitize_text_field( wp_unslash( $_POST['posted_on'] ) ) : '';
        $amount = isset( $_POST['amount'] ) ? sanitize_text_field( wp_unslash( $_POST['amount'] ) ) : '';
        $key = isset( $_POST['request_key'] ) ? sanitize_text_field( wp_unslash( $_POST['request_key'] ) ) : '';
        $result = self::settle( $entry_id, $account_id, $date, $amount, $key );
        $tab = isset( $_POST['return_tab'] ) ? sanitize_key( wp_unslash( $_POST['return_tab'] ) ) : 'expenses';
        if ( ! in_array( $tab, array( 'sales', 'received', 'expenses', 'assets', 'intra' ), true ) ) {
            $tab = 'expenses';
        }
        wp_safe_redirect( tca_admin_url( $tab, array( 'notice' => is_wp_error( $result ) ? $result->get_error_code() : 'settlement_saved' ) ) );
        exit;
    }

    public static function render( $entry, $paid_override = null ) {
        $total = abs( (float) $entry->amount_total );
        $paid = null === $paid_override ? self::sum( (int) $entry->id ) : (float) $paid_override;
        $due = self::outstanding( $total, $paid );
        echo '<p><strong>' . esc_html__( 'Paid/received:', 'taxocommerce-accounting' ) . '</strong> ' . esc_html( tca_money( $paid ) ) . ' / ' . esc_html( tca_money( $total ) ) . '</p>';
        if ( $due <= 0 ) {
            return;
        }
        $accounts = self::accounts();
        if ( ! $accounts ) {
            echo '<p class="description">' . esc_html__( 'Add a bank or cash account in Treasury to record an actual payment.', 'taxocommerce-accounting' ) . '</p>';
            return;
        }
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        wp_nonce_field( 'tca_settle_' . (int) $entry->id );
        echo '<input type="hidden" name="action" value="tca_settle_entry">';
        echo '<input type="hidden" name="entry_id" value="' . esc_attr( (string) $entry->id ) . '">';
        echo '<input type="hidden" name="request_key" value="' . esc_attr( wp_generate_uuid4() ) . '">';
        $tab = array( 'sale' => 'sales', 'refund' => 'sales', 'purchase' => 'received', 'expense' => 'expenses', 'asset' => 'assets', 'intra' => 'intra' );
        echo '<input type="hidden" name="return_tab" value="' . esc_attr( $tab[ $entry->kind ] ?? 'expenses' ) . '">';
        echo '<label>' . esc_html__( 'Paid from / received into', 'taxocommerce-accounting' ) . ' <select name="account_id" required>';
        foreach ( $accounts as $account ) {
            echo '<option value="' . esc_attr( (string) $account->id ) . '">' . esc_html( $account->name . ' (' . $account->kind . ')' ) . '</option>';
        }
        echo '</select></label> ';
        echo '<label>' . esc_html__( 'Payment date', 'taxocommerce-accounting' ) . ' <input type="date" name="posted_on" value="' . esc_attr( $entry->fiscal_date <= current_time( 'Y-m-d' ) ? $entry->fiscal_date : current_time( 'Y-m-d' ) ) . '" required></label> ';
        echo '<label>' . esc_html__( 'Amount', 'taxocommerce-accounting' ) . ' <input type="number" step="0.01" min="0.01" max="' . esc_attr( number_format( $due, 2, '.', '' ) ) . '" name="amount" value="' . esc_attr( number_format( $due, 2, '.', '' ) ) . '" required></label> ';
        submit_button( 'sale' === $entry->kind ? __( 'Record receipt', 'taxocommerce-accounting' ) : __( 'Record payment', 'taxocommerce-accounting' ), 'secondary small', 'submit', false );
        echo '</form>';
        echo '<p class="description">' . esc_html__( 'Record only actual bank/cash payments. Importing or validating an invoice never assumes it was paid.', 'taxocommerce-accounting' ) . '</p>';
    }

    /**
     * Confirm a real payment when saving or validating an invoice.
     * No fiscal validation or CSV import by itself implies a payment.
     */
    public static function confirmed_from_form( $entry_id ) {
        if ( '1' !== tca_input( 'tca_payment_confirmed' ) ) {
            return true;
        }
        return self::settle(
            absint( $entry_id ),
            absint( tca_input( 'tca_payment_account_id' ) ),
            tca_input( 'tca_payment_date' ),
            tca_input( 'tca_payment_amount' ),
            tca_input( 'tca_payment_key' )
        );
    }

    /** Optional confirmation in the existing invoice editor; no extra payment action. */
    public static function render_confirmation_fields( $entry_or_total, $date, $kind = 'expense' ) {
        $accounts = self::accounts();
        if ( ! $accounts ) {
            echo '<p class="description">' . esc_html__( 'Configure a bank or cash account in Treasury before linking a payment.', 'taxocommerce-accounting' ) . '</p>';
            return;
        }
        $new_invoice = null === $entry_or_total;
        $amount = is_object( $entry_or_total ) ? self::outstanding( $entry_or_total->amount_total, self::sum( (int) $entry_or_total->id ) ) : abs( (float) $entry_or_total );
        if ( $amount <= 0 && ! $new_invoice ) {
            return;
        }
        echo '<div class="tca-payment-confirm"><p><label><input type="checkbox" name="tca_payment_confirmed" value="1"> <strong>' .
            esc_html( 'sale' === $kind ? __( 'Payment received and verified', 'taxocommerce-accounting' ) : __( 'Invoice already paid (confirmed)', 'taxocommerce-accounting' ) ) .
            '</strong></label></p>';
        echo '<p class="description">' . esc_html__( 'Select this only if the payment actually occurred. It immediately creates a linked cash/bank movement when this form is saved; leave unchecked for unpaid invoices.', 'taxocommerce-accounting' ) . '</p>';
        echo '<input type="hidden" name="tca_payment_key" value="' . esc_attr( wp_generate_uuid4() ) . '">';
        echo '<label>' . esc_html__( 'Bank or cash account', 'taxocommerce-accounting' ) . ' <select name="tca_payment_account_id">';
        echo '<option value="0">' . esc_html__( 'Select an account', 'taxocommerce-accounting' ) . '</option>';
        foreach ( $accounts as $account ) {
            echo '<option value="' . esc_attr( (string) $account->id ) . '">' . esc_html( $account->name . ' (' . $account->kind . ')' ) . '</option>';
        }
        echo '</select></label> ';
        echo '<label>' . esc_html__( 'Actual payment date', 'taxocommerce-accounting' ) .
            ' <input type="date" name="tca_payment_date" value="' . esc_attr( $date <= current_time( 'Y-m-d' ) ? $date : current_time( 'Y-m-d' ) ) . '"></label> ';
        echo '<label>' . esc_html__( 'Paid amount', 'taxocommerce-accounting' ) .
            ' <input type="number" name="tca_payment_amount" min="0.01" step="0.01"' .
            ( $new_invoice ? '' : ' max="' . esc_attr( number_format( $amount, 2, '.', '' ) ) . '"' ) .
            ' value="' . esc_attr( $new_invoice ? '' : number_format( $amount, 2, '.', '' ) ) . '" placeholder="' . esc_attr__( 'Invoice total including VAT', 'taxocommerce-accounting' ) . '"></label></div>';
    }

    /**
     * Historical reconciliation is opt-in: never generate payments from invoices
     * without an explicit user confirmation and account selection.
     */
    public static function candidates( $year, $limit = 100 ) {
        global $wpdb;
        $year = absint( $year );
        $limit = min( 100, max( 1, absint( $limit ) ) );
        if ( $year < 2000 || $year > 2100 ) {
            return array();
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Live reconciliation must read approved entries and linked settlements.
        return (array) $wpdb->get_results( $wpdb->prepare(
            'SELECT e.id,e.kind,e.fiscal_date,e.document_no,e.party,e.amount_total,e.status FROM %i AS e WHERE e.fiscal_date >= %s AND e.fiscal_date <= %s AND e.status = %s AND e.kind IN (%s,%s,%s,%s,%s,%s) AND ABS(e.amount_total) > COALESCE((SELECT SUM(m.amount) FROM %i AS s INNER JOIN %i AS m ON m.id = s.movement_id WHERE s.entry_id = e.id),0) ORDER BY e.fiscal_date ASC,e.id ASC LIMIT %d',
            tca_table(), $year . '-01-01',
            $year . '-12-31', 'validated', 'purchase', 'expense', 'asset', 'intra', 'sale', 'refund',
            self::table(), TCA_Treasury::movements_table(), $limit
        ) );
    }

    /** Keep already-entered manual cash/bank payments from being duplicated. */
    private static function has_unlinked_manual_movement( $entry, $account_id ) {
        global $wpdb;
        if ( '' === (string) $entry->document_no ) {
            return false;
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Detect existing unlinked bank payment by identical account and invoice reference, including partial manual payments.
        $movement_id = $wpdb->get_var( $wpdb->prepare(
            'SELECT m.id FROM %i AS m LEFT JOIN %i AS s ON m.id = s.movement_id WHERE m.account_id = %d AND m.direction = %s AND m.reference = %s AND s.movement_id IS NULL LIMIT 1',
            TCA_Treasury::movements_table(), self::table(), absint( $account_id ), self::direction( $entry->kind ),
            (string) $entry->document_no
        ) );
        return ! empty( $movement_id );
    }

    /**
     * Optional cash receipt for a paid WooCommerce order. This never changes the
     * fiscal entry and never assumes that an unpaid or COD order was collected.
     * The merchant must explicitly choose an account and minimum payment date.
     */
    public static function maybe_auto_receipt( $order, $entry, $settings ) {
        if ( ! is_object( $entry ) || 'sale' !== $entry->kind || 'woocommerce' !== $entry->source ||
            ! is_object( $order ) || ! method_exists( $order, 'get_date_paid' ) ||
            ! method_exists( $order, 'is_paid' ) || ! $order->is_paid() ) {
            return false;
        }
        $account_id = absint( $settings['auto_receipts_account_id'] ?? 0 );
        $since = (string) ( $settings['auto_receipts_since'] ?? '' );
        $paid_at = $order->get_date_paid();
        if ( ! $account_id || ! tca_valid_date( $since ) || ! $paid_at ||
            ! method_exists( $paid_at, 'date' ) || '' === (string) $entry->document_no ) {
            return false;
        }
        $date = $paid_at->date( 'Y-m-d' );
        if ( ! tca_valid_date( $date ) || $date < $since || $date > current_time( 'Y-m-d' ) ||
            abs( (float) $order->get_total() - (float) $entry->amount_total ) > 0.01 ||
            0 < self::sum( (int) $entry->id ) || tca_sale_invoice_mismatch( $entry ) ) {
            return false;
        }
        $account = null;
        foreach ( self::accounts() as $candidate ) {
            if ( (int) $candidate->id === $account_id && 'bank' === $candidate->kind ) {
                $account = $candidate;
                break;
            }
        }
        if ( ! $account || $date < $account->opened_on ||
            self::has_unlinked_manual_movement( $entry, $account_id ) ) {
            return false;
        }
        $result = self::settle( (int) $entry->id, $account_id, $date,
            number_format( abs( (float) $entry->amount_total ), 2, '.', '' ), wp_generate_uuid4() );
        return ! is_wp_error( $result );
    }

    public static function render_bulk( $year ) {
        $accounts = self::accounts();
        if ( ! $accounts ) {
            return;
        }
        $entries = self::candidates( $year );
        if ( ! $entries ) {
            return;
        }
        echo '<h3>' . esc_html__( 'Reconcile confirmed payments and receipts', 'taxocommerce-accounting' ) . '</h3>';
        echo '<p class="description">' . esc_html__( 'Only for invoices paid or customer sales actually collected and not yet linked to Treasury. Review each item and its actual payment date. Purchases are bank/cash out; sales are bank/cash in. No movements are generated until confirmed.', 'taxocommerce-accounting' ) . '</p>';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        wp_nonce_field( 'tca_bulk_paid_' . absint( $year ) );
        echo '<input type="hidden" name="action" value="tca_bulk_paid">';
        echo '<input type="hidden" name="year" value="' . esc_attr( (string) absint( $year ) ) . '">';
        echo '<p><label>' . esc_html__( 'Account used for the confirmed payments or receipts', 'taxocommerce-accounting' ) . ' <select name="account_id" required>';
        echo '<option value="0">' . esc_html__( 'Select bank or cash account', 'taxocommerce-accounting' ) . '</option>';
        foreach ( $accounts as $account ) {
            echo '<option value="' . esc_attr( (string) $account->id ) . '">' . esc_html( $account->name . ' (' . $account->kind . ')' ) . '</option>';
        }
        echo '</select></label></p><div class="tca-treasury-scroll"><table class="widefat striped"><thead><tr>';
        foreach ( array( __( 'Confirmed?', 'taxocommerce-accounting' ), __( 'Invoice', 'taxocommerce-accounting' ), __( 'Supplier', 'taxocommerce-accounting' ), __( 'Outstanding', 'taxocommerce-accounting' ), __( 'Actual bank payment date', 'taxocommerce-accounting' ) ) as $label ) {
            echo '<th>' . esc_html( $label ) . '</th>';
        }
        echo '</tr></thead><tbody>';
        foreach ( $entries as $entry ) {
            $due = self::outstanding( $entry->amount_total, self::sum( $entry->id ) );
            echo '<tr><td><input type="checkbox" name="entry_ids[]" value="' . esc_attr( (string) $entry->id ) . '"></td><td>' .
                esc_html( ( 'sale' === $entry->kind ? __( 'Sale', 'taxocommerce-accounting' ) : ( 'refund' === $entry->kind ? __( 'Refund', 'taxocommerce-accounting' ) : __( 'Purchase / expense', 'taxocommerce-accounting' ) ) ) . ' — ' . $entry->document_no ) . '</td><td>' . esc_html( $entry->party ) . '</td><td>' . esc_html( tca_money( $due ) ) . '</td><td>';
            echo '<input type="date" name="payment_dates[' . esc_attr( (string) $entry->id ) . ']" value="' .
                esc_attr( $entry->fiscal_date ) . '" max="' . esc_attr( current_time( 'Y-m-d' ) ) . '" aria-label="' .
                esc_attr__( 'Actual payment date', 'taxocommerce-accounting' ) . '"></td></tr>';
        }
        echo '</tbody></table></div>';
        echo '<p><label><input type="checkbox" name="payments_verified" value="1" required> ' .
            esc_html__( 'I confirm each selected purchase was paid or sale was received using this account on the dates shown, with no matching Treasury movement already recorded.', 'taxocommerce-accounting' ) . '</label></p>';
        submit_button( __( 'Post confirmed historical payments', 'taxocommerce-accounting' ), 'secondary', 'submit', false );
        echo '</form>';
        echo '<p class="description">' . esc_html__( 'At most 100 invoices are shown at once. Refresh to reconcile more. This does not change invoices, VAT deductions or financing/loan balances.', 'taxocommerce-accounting' ) . '</p>';
    }

    /** Reconcile explicitly selected historical payments; safe to retry. */
    public static function handle_bulk() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( esc_html__( 'Insufficient permissions.', 'taxocommerce-accounting' ) );
        }
        $year = isset( $_POST['year'] ) ? absint( wp_unslash( $_POST['year'] ) ) : 0;
        check_admin_referer( 'tca_bulk_paid_' . $year );
        $account_id = isset( $_POST['account_id'] ) ? absint( wp_unslash( $_POST['account_id'] ) ) : 0;
        $verified = isset( $_POST['payments_verified'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['payments_verified'] ) );
        $selected = isset( $_POST['entry_ids'] ) && is_array( $_POST['entry_ids'] ) ? array_map( 'absint', wp_unslash( $_POST['entry_ids'] ) ) : array();
        $dates = isset( $_POST['payment_dates'] ) && is_array( $_POST['payment_dates'] ) ? map_deep( wp_unslash( $_POST['payment_dates'] ), 'sanitize_text_field' ) : array();
        $account_exists = false;
        foreach ( self::accounts() as $account ) {
            if ( (int) $account->id === $account_id && in_array( $account->kind, array( 'bank', 'cash' ), true ) ) {
                $account_exists = true;
                break;
            }
        }
        if ( ! $verified || ! $account_exists || ! $selected || count( $selected ) > 100 ) {
            wp_safe_redirect( tca_admin_url( 'treasury', array( 'notice' => 'settlement_invalid' ) ) );
            exit;
        }
        $allowed_ids = array();
        foreach ( self::candidates( $year ) as $entry ) {
            $allowed_ids[ (int) $entry->id ] = $entry;
        }
        $posted = 0;
        $skipped = 0;
        $errors = 0;
        foreach ( array_unique( array_map( 'absint', $selected ) ) as $id ) {
            if ( ! isset( $allowed_ids[ $id ] ) ) {
                ++$skipped;
                continue;
            }
            $entry = $allowed_ids[ $id ];
            $date = isset( $dates[ $id ] ) && is_scalar( $dates[ $id ] ) ? sanitize_text_field( $dates[ $id ] ) : '';
            $amount = self::outstanding( $entry->amount_total, self::sum( $id ) );
            if ( ! tca_valid_date( $date ) || $date > current_time( 'Y-m-d' ) ||
                self::has_unlinked_manual_movement( $entry, $account_id ) ) {
                ++$skipped;
                continue;
            }
            $result = self::settle( $id, $account_id, $date, number_format( $amount, 2, '.', '' ), wp_generate_uuid4() );
            if ( is_wp_error( $result ) ) {
                ++$errors;
            } else {
                ++$posted;
            }
        }
        wp_safe_redirect( tca_admin_url( 'treasury', array(
            'notice' => 'bulk_settlement_result',
            'posted' => $posted, 'skipped' => $skipped, 'errors' => $errors,
        ) ) );
        exit;
    }
}
add_action( 'admin_post_tca_settle_entry', array( 'TCA_Settlements', 'handle' ) );
add_action( 'admin_post_tca_bulk_paid', array( 'TCA_Settlements', 'handle_bulk' ) );
