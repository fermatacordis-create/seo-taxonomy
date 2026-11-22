<?php
/**
 * Simple, independent cash/bank and loan register for Accounting.
 *
 * Actual balances are based solely on opening funds and confirmed
 * cash movements. Unpaid invoices and projected loan instalments never imply cash.
 *
 * @package TaxoCommerceAccounting
 */
defined( 'ABSPATH' ) || exit;
require_once __DIR__ . '/class-tca-loan-schedule.php';

final class TCA_Treasury {
    private static function table( $suffix ) {
        global $wpdb;
        return $wpdb->prefix . 'tca_treasury_' . $suffix;
    }

    /** Owned tables shared with the invoice-settlement component. */
    public static function accounts_table() {
        return self::table( 'accounts' );
    }

    public static function movements_table() {
        return self::table( 'movements' );
    }

    public static function install_schema() {
        global $wpdb;
        $collation = $wpdb->get_charset_collate();
        $accounts = esc_sql( self::table( 'accounts' ) );
        $movements = esc_sql( self::table( 'movements' ) );
        $loans = esc_sql( self::table( 'loans' ) );
        $corrections = esc_sql( self::table( 'corrections' ) );
        dbDelta( "CREATE TABLE {$accounts} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            kind varchar(8) NOT NULL,
            name varchar(191) NOT NULL,
            opened_on date NOT NULL,
            opening_amount decimal(18,2) NOT NULL DEFAULT 0.00,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY kind (kind)
        ) {$collation};" );
        dbDelta( "CREATE TABLE {$movements} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            account_id bigint(20) unsigned NOT NULL,
            posted_on date NOT NULL,
            direction varchar(16) NOT NULL,
            amount decimal(18,2) NOT NULL,
            reference varchar(191) NOT NULL DEFAULT '',
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY account_date (account_id,posted_on),
            KEY posted_on (posted_on)
        ) {$collation};" );
        dbDelta( "CREATE TABLE {$corrections} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            account_id bigint(20) unsigned NOT NULL,
            movement_id bigint(20) unsigned NOT NULL DEFAULT 0,
            action varchar(32) NOT NULL,
            amount decimal(18,2) NOT NULL,
            reason varchar(191) NOT NULL,
            user_id bigint(20) unsigned NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY account_id (account_id),
            KEY movement_id (movement_id)
        ) {$collation};" );
        dbDelta( "CREATE TABLE {$loans} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(191) NOT NULL,
            lender varchar(191) NOT NULL DEFAULT '',
            lender_tax_id varchar(48) NOT NULL DEFAULT '',
            lender_relation varchar(24) NOT NULL DEFAULT 'other',
            repayment_mode varchar(16) NOT NULL DEFAULT 'legacy',
            agreement_reference varchar(191) NOT NULL DEFAULT '',
            funding_source varchar(24) NOT NULL DEFAULT 'pending',
            opening_account_id bigint(20) unsigned NULL DEFAULT NULL,
            principal decimal(18,2) NOT NULL,
            remaining decimal(18,2) NOT NULL,
            monthly_payment decimal(18,2) NOT NULL,
            annual_rate decimal(7,4) NOT NULL DEFAULT 0,
            term_months smallint(5) unsigned NOT NULL DEFAULT 0,
            upfront_payment decimal(18,2) NOT NULL DEFAULT 0.00,
            balloon_payment decimal(18,2) NOT NULL DEFAULT 0.00,
            funding_movement_id bigint(20) unsigned NOT NULL DEFAULT 0,
            request_key varchar(64) NULL DEFAULT NULL,
            start_date date NOT NULL,
            next_due_date date NOT NULL,
            first_due_date date NOT NULL DEFAULT '1970-01-01',
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY request_key (request_key),
            UNIQUE KEY opening_account_id (opening_account_id),
            KEY next_due_date (next_due_date)
        ) {$collation};" );
        $payments = esc_sql( self::table( 'loan_payments' ) );
        dbDelta( "CREATE TABLE {$payments} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            loan_id bigint(20) unsigned NOT NULL,
            instalment_number smallint(5) unsigned NOT NULL,
            movement_id bigint(20) unsigned NOT NULL,
            principal_amount decimal(18,2) NOT NULL,
            interest_amount decimal(18,2) NOT NULL,
            paid_amount decimal(18,2) NOT NULL,
            paid_on date NOT NULL,
            request_key varchar(64) NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY loan_period (loan_id,instalment_number),
            UNIQUE KEY movement_id (movement_id),
            UNIQUE KEY request_key (request_key),
            KEY loan_id (loan_id)
        ) {$collation};" );
    }

    private static function posted( $key ) {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- This helper is called only after verifying the action nonce in handle().
        if ( ! isset( $_POST[ $key ] ) || ! is_scalar( $_POST[ $key ] ) ) {
            return '';
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- The action nonce was checked in handle().
        return sanitize_text_field( wp_unslash( $_POST[ $key ] ) );
    }

    private static function money( $value, $allow_negative = false ) {
        $normalized = str_replace( ',', '.', trim( (string) $value ) );
        $number = tca_decimal( $normalized );
        if ( null === $number || abs( $number ) > 999999999999.99 ||
            ( ! $allow_negative && $number < 0 ) ) {
            return null;
        }
        return $number;
    }

    private static function date( $date, $allow_future = false ) {
        return tca_valid_date( $date ) &&
            ( $allow_future || $date <= current_time( 'Y-m-d' ) );
    }

    private static function redirect( $notice ) {
        wp_safe_redirect( tca_admin_url( 'treasury', array( 'notice' => 'treasury_' . $notice ) ) );
        exit;
    }

    private static function get_account( $account_id ) {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Own cash account table, latest data required.
        return $wpdb->get_row( $wpdb->prepare(
            'SELECT * FROM %i WHERE id = %d LIMIT 1',
            self::table( 'accounts' ), absint( $account_id )
        ) );
    }

    private static function insert_movement( $account_id, $date, $direction, $amount, $reference ) {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Ledger writes to an owned table.
        return $wpdb->insert( self::table( 'movements' ), array(
            'account_id' => absint( $account_id ),
            'posted_on' => $date,
            'direction' => $direction,
            'amount' => $amount,
            'reference' => $reference,
            'created_at' => current_time( 'mysql' ),
        ), array( '%d', '%s', '%s', '%f', '%s', '%s' ) );
    }

    public static function handle() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( esc_html__( 'Insufficient permissions.', 'taxocommerce-accounting' ) );
        }
        check_admin_referer( 'tca_treasury_action' );
        $operation = sanitize_key( self::posted( 'treasury_action' ) );
        global $wpdb;
        if ( 'account' === $operation ) {
            $kind = sanitize_key( self::posted( 'kind' ) );
            $name = self::posted( 'name' );
            $date = self::posted( 'opened_on' );
            $opening = self::money( self::posted( 'opening_amount' ), true );
            if ( ! in_array( $kind, array( 'cash', 'bank' ), true ) ||
                '' === $name || ! self::date( $date ) || null === $opening ) {
                self::redirect( 'invalid' );
            }
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Own account table.
            $ok = $wpdb->insert( self::table( 'accounts' ), array(
                'kind' => $kind, 'name' => $name, 'opened_on' => $date,
                'opening_amount' => $opening, 'created_at' => current_time( 'mysql' ),
            ), array( '%s', '%s', '%s', '%f', '%s' ) );
            self::redirect( $ok ? 'saved' : 'error' );
        }
        if ( 'cancel_opening' === $operation ) {
            $id = absint( self::posted( 'account_id' ) );
            $reason = self::posted( 'reason' );
            if ( ! $id || '' === $reason || '1' !== self::posted( 'confirmed' ) ) self::redirect( 'invalid' );
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Transaction protects a correction to our own account and audit tables.
            $wpdb->query( 'START TRANSACTION' );
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Live row lock prevents duplicate account adjustments.
            $account = $wpdb->get_row( $wpdb->prepare(
                'SELECT * FROM %i WHERE id = %d FOR UPDATE', self::table( 'accounts' ), $id
            ) );
            if ( ! $account || (float) $account->opening_amount <= 0 ) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Roll back the uncommitted transaction.
                $wpdb->query( 'ROLLBACK' );
                self::redirect( 'invalid' );
            }
            // A loan linked to this opening amount must never lose its funding source.
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Financial loan link must be checked before an opening correction.
            $linked_loan = $wpdb->get_var( $wpdb->prepare(
                'SELECT id FROM %i WHERE opening_account_id = %d LIMIT 1', self::table( 'loans' ), $id
            ) );
            if ( $linked_loan ) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Reject cancelling an assigned opening balance.
                $wpdb->query( 'ROLLBACK' );
                self::redirect( 'invalid' );
            }
            $amount = (float) $account->opening_amount;
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Our own financial account table; a direct write is necessary.
            $ok = $wpdb->update( self::table( 'accounts' ),
                array( 'opening_amount' => 0 ), array( 'id' => $id ),
                array( '%f' ), array( '%d' ) );
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Our own immutable Treasury correction ledger.
            $logged = 1 === $ok && $wpdb->insert( self::table( 'corrections' ), array(
                'account_id' => $id, 'movement_id' => 0, 'action' => 'cancel_opening',
                'amount' => $amount, 'reason' => $reason,
                'user_id' => get_current_user_id(), 'created_at' => current_time( 'mysql' ),
            ), array( '%d', '%d', '%s', '%f', '%s', '%d', '%s' ) );
            if ( $logged ) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Commit a validated account/audit correction.
                $wpdb->query( 'COMMIT' );
            } else {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Never save a partial financial correction.
                $wpdb->query( 'ROLLBACK' );
            }
            self::redirect( $logged ? 'saved' : 'error' );
        }
        if ( 'edit_account' === $operation ) {
            $account = self::get_account( self::posted( 'account_id' ) );
            $name = self::posted( 'name' );
            if ( ! $account || '' === $name ) self::redirect( 'invalid' );
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Rename an existing account in our own ledger.
            $ok = $wpdb->update( self::table( 'accounts' ),
                array( 'name' => $name ), array( 'id' => $account->id ),
                array( '%s' ), array( '%d' ) );
            self::redirect( false !== $ok ? 'saved' : 'error' );
        }
        if ( 'edit_movement' === $operation ) {
            $id = absint( self::posted( 'movement_id' ) );
            $account = self::get_account( self::posted( 'account_id' ) );
            $date = self::posted( 'posted_on' );
            $amount = self::money( self::posted( 'amount' ) );
            $direction = sanitize_key( self::posted( 'direction' ) );
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Read current financial movement before editing it.
            $movement = $wpdb->get_row( $wpdb->prepare(
                'SELECT * FROM %i WHERE id = %d', self::table( 'movements' ), $id
            ) );
            if ( ! $movement || ! in_array( $movement->direction, array( 'in', 'out' ), true )
                || ! in_array( $direction, array( 'in', 'out' ), true ) || ! $account
                || ! self::date( $date ) || $date < $account->opened_on
                || null === $amount || $amount <= 0 ) self::redirect( 'invalid' );
            // Do not edit invoice settlements, loan payments or paired transfers.
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Live settlement relation required to prevent desynchronization.
            $settled = $wpdb->get_var( $wpdb->prepare(
                'SELECT movement_id FROM %i WHERE movement_id = %d',
                $wpdb->prefix . 'tca_treasury_settlements', $id
            ) );
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Live loan payment relation required to prevent desynchronization.
            $loan = $wpdb->get_var( $wpdb->prepare(
                'SELECT movement_id FROM %i WHERE movement_id = %d',
                self::table( 'loan_payments' ), $id
            ) );
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Loan principal receipts cannot be edited independently.
            $loan_funding = $wpdb->get_var( $wpdb->prepare(
                'SELECT id FROM %i WHERE funding_movement_id = %d LIMIT 1', self::table( 'loans' ), $id
            ) );
            if ( $settled || $loan || $loan_funding ) self::redirect( 'invalid' );
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Update a standalone movement in our own Treasury ledger.
            $ok = $wpdb->update( self::table( 'movements' ),
                array( 'account_id' => $account->id, 'posted_on' => $date,
                    'direction' => $direction, 'amount' => $amount, 'reference' => self::posted( 'reference' ) ),
                array( 'id' => $id ), array( '%d', '%s', '%s', '%f', '%s' ), array( '%d' ) );
            self::redirect( false !== $ok ? 'saved' : 'error' );
        }
        if ( 'opening_receipt' === $operation ) {
            $account_id = absint( self::posted( 'account_id' ) );
            $date = self::posted( 'posted_on' );
            $reference = self::posted( 'reference' );
            if ( '1' !== self::posted( 'opening_verified' ) || ! $account_id ||
                ! self::date( $date ) || '' === $reference ) {
                self::redirect( 'invalid' );
            }
            // Move money from the opening-balance column to a dated receipt.
            // Exactly one cash/bank receipt is created; available funds do not change.
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Own account and ledger need one transaction.
            $wpdb->query( 'START TRANSACTION' );
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Lock account so repeated requests cannot duplicate the opening contribution.
            $account = $wpdb->get_row( $wpdb->prepare(
                'SELECT * FROM %i WHERE id = %d FOR UPDATE', self::table( 'accounts' ), $account_id
            ) );
            if ( ! $account || ! in_array( $account->kind, array( 'cash', 'bank' ), true ) ||
                $date < $account->opened_on || (float) $account->opening_amount <= 0 ) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Reject duplicate conversion.
                $wpdb->query( 'ROLLBACK' );
                self::redirect( 'invalid' );
            }
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Reject conversion if a lender has already been linked to this opening balance.
            $linked_loan = $wpdb->get_var( $wpdb->prepare(
                'SELECT id FROM %i WHERE opening_account_id = %d LIMIT 1',
                self::table( 'loans' ), $account_id
            ) );
            if ( $linked_loan ) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Leave linked opening funds unchanged.
                $wpdb->query( 'ROLLBACK' );
                self::redirect( 'invalid' );
            }
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Own opening balance conversion under lock.
            $changed = $wpdb->update( self::table( 'accounts' ),
                array( 'opening_amount' => 0 ),
                array( 'id' => $account_id, 'opening_amount' => $account->opening_amount ),
                array( '%f' ), array( '%d', '%f' )
            );
            $movement = 1 === $changed && self::insert_movement( $account_id, $date, 'in',
                (float) $account->opening_amount, $reference );
            if ( ! $movement ) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Never remove money without a corresponding receipt.
                $wpdb->query( 'ROLLBACK' );
                self::redirect( 'error' );
            }
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Commit opening balance conversion once.
            $wpdb->query( 'COMMIT' );
            self::redirect( 'saved' );
        }
        if ( 'movement' === $operation || 'transfer' === $operation ) {
            $date = self::posted( 'posted_on' );
            $amount = self::money( self::posted( 'amount' ) );
            $from = self::get_account( self::posted( 'account_id' ) );
            $reference = self::posted( 'reference' );
            if ( ! self::date( $date ) || null === $amount || $amount <= 0 ||
                ! $from || $date < $from->opened_on ) {
                self::redirect( 'invalid' );
            }
            if ( 'movement' === $operation ) {
                $direction = sanitize_key( self::posted( 'direction' ) );
                if ( ! in_array( $direction, array( 'in', 'out' ), true ) ) {
                    self::redirect( 'invalid' );
                }
                self::redirect( self::insert_movement( $from->id, $date, $direction, $amount, $reference ) ? 'saved' : 'error' );
            }
            $to = self::get_account( self::posted( 'destination_id' ) );
            if ( ! $to || $from->id === $to->id || $date < $to->opened_on ) {
                self::redirect( 'invalid' );
            }
            // A transfer is atomic: never post money out without posting it in.
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Own ledger transfer transaction.
            $wpdb->query( 'START TRANSACTION' );
            $out = self::insert_movement( $from->id, $date, 'transfer_out', $amount, $reference );
            $in = $out ? self::insert_movement( $to->id, $date, 'transfer_in', $amount, $reference ) : false;
            if ( ! $out || ! $in ) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Roll back incomplete ledger transfer.
                $wpdb->query( 'ROLLBACK' );
                self::redirect( 'error' );
            }
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Commit balanced transfer.
            $wpdb->query( 'COMMIT' );
            self::redirect( 'saved' );
        }
        if ( 'loan' === $operation ) {
            $name = self::posted( 'name' );
            $lender = self::posted( 'lender' );
            $lender_tax_id = self::posted( 'lender_tax_id' );
            $lender_relation = sanitize_key( self::posted( 'lender_relation' ) );
            $agreement_reference = self::posted( 'agreement_reference' );
            $start = self::posted( 'start_date' );
            $due = self::posted( 'next_due_date' );
            $principal = self::money( self::posted( 'principal' ) );
            $months = absint( self::posted( 'term_months' ) );
            $rate = self::money( self::posted( 'annual_rate' ) );
            $upfront = self::money( self::posted( 'upfront_payment' ) );
            $balloon = self::money( self::posted( 'balloon_payment' ) );
            $monthly = self::money( self::posted( 'monthly_payment' ) );
            $request_key = self::posted( 'loan_request_key' );
            // Existing opening balance is funding already held by the bank; no new receipt.
            $funding_mode = sanitize_key( self::posted( 'funding_mode' ) );
            if ( '' === $funding_mode && '1' === self::posted( 'funding_received' ) ) {
                $funding_mode = 'new_receipt'; // Compatibility with the previous form.
            }
            $funding_received = 'new_receipt' === $funding_mode;
            $funding_existing = 'opening_balance' === $funding_mode;
            $flexible = 0 === $months;
            if ( $flexible && '' === $due ) {
                $due = '1970-01-01'; // Internal sentinel: no fixed due date agreed.
            }
            $funding_account = ( $funding_received || $funding_existing )
                ? self::get_account( self::posted( 'funding_account_id' ) ) : false;
            if ( '' === $name || '' === $lender ||
                ! in_array( $lender_relation, array( 'sponsor', 'other' ), true ) ||
                ! in_array( $funding_mode, array( 'pending', 'new_receipt', 'opening_balance' ), true ) ||
                ! self::date( $start ) ||
                ( ! $flexible && ( ! self::date( $due, true ) || $due < $start ) ) ||
                ( $flexible && '1970-01-01' !== $due &&
                    ( ! self::date( $due, true ) || $due < $start ) ) ||
                null === $principal || $principal <= 0 ||
                ! preg_match( '/^[a-f0-9-]{36}$/', $request_key ) ||
                null === $rate || $rate < 0 || $rate > 100 ||
                null === $upfront || null === $balloon || $balloon > $principal ||
                $months > 360 ||
                ( ( $funding_received || $funding_existing ) && ( ! $funding_account ||
                    'bank' !== $funding_account->kind || $start < $funding_account->opened_on ) ) ||
                ( $funding_existing && '1' !== self::posted( 'opening_verified' ) ) ) {
                self::redirect( 'invalid' );
            }
            if ( $months > 0 ) {
                $schedule = TCA_Loan_Schedule::calculate( $principal, $rate, $months, $due, $balloon );
                if ( ! $schedule ) {
                    self::redirect( 'invalid' );
                }
                // Calculated loans derive the instalment from TIN and term.
                $monthly = (float) $schedule[0]['payment'];
            } elseif ( 0.0 !== (float) $rate || 0.0 !== (float) $balloon ) {
                // Flexible loans have no fixed instalments and must carry 0% interest.
                self::redirect( 'invalid' );
            } else {
                $monthly = 0.0; // Repayment is recorded only when actually made.
            }
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Replayed request must never generate a second loan or bank receipt.
            $existing_loan = $wpdb->get_var( $wpdb->prepare(
                'SELECT id FROM %i WHERE request_key = %s LIMIT 1', self::table( 'loans' ), $request_key
            ) );
            if ( $existing_loan ) {
                self::redirect( 'saved' );
            }
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Own loan/funding transaction.
            $wpdb->query( 'START TRANSACTION' );
            if ( $funding_existing ) {
                // Lock and attribute the opening balance exactly once. No money moves.
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Lock financial opening amount before assigning the lender.
                $opening_account = $wpdb->get_row( $wpdb->prepare(
                    'SELECT * FROM %i WHERE id = %d FOR UPDATE', self::table( 'accounts' ), $funding_account->id
                ) );
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Prevent two sponsors claiming the same opening balance.
                $already_linked = $wpdb->get_var( $wpdb->prepare(
                    'SELECT id FROM %i WHERE opening_account_id = %d LIMIT 1',
                    self::table( 'loans' ), $funding_account->id
                ) );
                if ( ! $opening_account || 'bank' !== $opening_account->kind ||
                    (float) $opening_account->opening_amount <= 0 ||
                    abs( round( (float) $opening_account->opening_amount, 2 ) - round( $principal, 2 ) ) > 0.001 ||
                    $already_linked ) {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Do not create an unmatched loan.
                    $wpdb->query( 'ROLLBACK' );
                    self::redirect( 'invalid' );
                }
            }
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Own loan register.
            $saved = $wpdb->insert( self::table( 'loans' ), array(
                'name' => $name, 'lender' => $lender,
                'lender_tax_id' => $lender_tax_id, 'lender_relation' => $lender_relation,
                'repayment_mode' => $flexible ? 'flexible' : 'scheduled',
                'agreement_reference' => $agreement_reference,
                'funding_source' => $funding_mode,
                'opening_account_id' => $funding_existing ? (int) $funding_account->id : null,
                'principal' => $principal, 'remaining' => $principal,
                'monthly_payment' => $monthly, 'annual_rate' => $rate,
                'term_months' => $months, 'request_key' => $request_key,
                'upfront_payment' => $upfront, 'balloon_payment' => $balloon,
                'start_date' => $start, 'next_due_date' => $due, 'first_due_date' => $due,
                'created_at' => current_time( 'mysql' ), 'updated_at' => current_time( 'mysql' ),
            ), array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d',
                '%f', '%f', '%f', '%f', '%d', '%s', '%f', '%f',
                '%s', '%s', '%s', '%s', '%s' ) );
            $loan_id = $saved ? (int) $wpdb->insert_id : 0;
            if ( $saved && $funding_received ) {
                // The purchase invoice/payment must be recorded separately.
                $saved = self::insert_movement( $funding_account->id, $start, 'in', $principal, 'Loan #' . $loan_id . ': ' . $name );
                $movement_id = $saved ? (int) $wpdb->insert_id : 0;
                if ( $saved ) {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Own loan funding link.
                    $saved = 1 === $wpdb->update( self::table( 'loans' ), array(
                        'funding_movement_id' => $movement_id,
                    ), array( 'id' => $loan_id ), array( '%d' ), array( '%d' ) );
                }
            }
            if ( $saved ) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Atomic loan/funding write.
                $wpdb->query( 'COMMIT' );
            } else {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Atomic loan/funding write.
                $wpdb->query( 'ROLLBACK' );
            }
            self::redirect( $saved ? 'saved' : 'error' );
        }
        if ( 'loan_flexible_payment' === $operation ) {
            if ( '1' !== self::posted( 'loan_payment_confirmed' ) ) {
                self::redirect( 'invalid' );
            }
            $id = absint( self::posted( 'loan_id' ) );
            $date = self::posted( 'posted_on' );
            $key = self::posted( 'request_key' );
            $amount = self::money( self::posted( 'amount' ) );
            $account = self::get_account( self::posted( 'account_id' ) );
            if ( ! $id || ! self::date( $date ) || ! $account ||
                ! in_array( $account->kind, array( 'bank', 'cash' ), true ) ||
                null === $amount || $amount <= 0 ||
                ! preg_match( '/^[a-f0-9-]{36}$/', $key ) ||
                $date < $account->opened_on ) {
                self::redirect( 'invalid' );
            }
            // Financial movements and principal reductions must commit together.
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Atomic loan repayment and bank movement.
            $wpdb->query( 'START TRANSACTION' );
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Lock outstanding principal to prevent double repayment.
            $loan = $wpdb->get_row( $wpdb->prepare(
                'SELECT * FROM %i WHERE id = %d FOR UPDATE', self::table( 'loans' ), $id
            ) );
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Exactly-once payment token.
            $previous = $wpdb->get_var( $wpdb->prepare(
                'SELECT id FROM %i WHERE request_key = %s LIMIT 1', self::table( 'loan_payments' ), $key
            ) );
            if ( $previous ) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Idempotent replay.
                $wpdb->query( 'COMMIT' );
                self::redirect( 'saved' );
            }
            if ( ! $loan || 'flexible' !== $loan->repayment_mode ||
                (float) $loan->annual_rate !== 0.0 || $date < $loan->start_date ||
                (float) $loan->remaining < $amount ||
                (float) $loan->remaining <= 0 ) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Reject overpayments or non-flexible loans.
                $wpdb->query( 'ROLLBACK' );
                self::redirect( 'invalid' );
            }
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Own instalments register.
            $last = (int) $wpdb->get_var( $wpdb->prepare(
                'SELECT COALESCE(MAX(instalment_number),0) FROM %i WHERE loan_id = %d',
                self::table( 'loan_payments' ), $id
            ) );
            $saved = self::insert_movement( $account->id, $date, 'out', $amount,
                'Loan #' . $id . ' capital repayment ' . ( $last + 1 ) );
            $movement_id = $saved ? (int) $wpdb->insert_id : 0;
            if ( $saved ) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- One audit record for each actual repayment.
                $saved = $wpdb->insert( self::table( 'loan_payments' ), array(
                    'loan_id' => $id, 'instalment_number' => $last + 1,
                    'movement_id' => $movement_id, 'principal_amount' => $amount,
                    'interest_amount' => 0.0, 'paid_amount' => $amount,
                    'paid_on' => $date, 'request_key' => $key,
                    'created_at' => current_time( 'mysql' ),
                ), array( '%d', '%d', '%d', '%f', '%f', '%f', '%s', '%s', '%s' ) );
            }
            if ( $saved ) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Reduce debt by the capital actually repaid.
                $saved = 1 === $wpdb->update( self::table( 'loans' ), array(
                    'remaining' => round( (float) $loan->remaining - $amount, 2 ),
                    'updated_at' => current_time( 'mysql' ),
                ), array( 'id' => $id ), array( '%f', '%s' ), array( '%d' ) );
            }
            if ( $saved ) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Commit bank outflow with debt reduction.
                $wpdb->query( 'COMMIT' );
            } else {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Never post only half of the repayment.
                $wpdb->query( 'ROLLBACK' );
            }
            self::redirect( $saved ? 'saved' : 'error' );
        }
        if ( 'loan_payment' === $operation ) {
            if ( '1' !== self::posted( 'loan_payment_confirmed' ) ) {
                self::redirect( 'invalid' );
            }
            $id = absint( self::posted( 'loan_id' ) );
            $date = self::posted( 'posted_on' );
            $key = self::posted( 'request_key' );
            $account = self::get_account( self::posted( 'account_id' ) );
            if ( ! $id || ! self::date( $date ) || ! $account ||
                ! in_array( $account->kind, array( 'bank', 'cash' ), true ) ||
                ! preg_match( '/^[a-f0-9-]{36}$/', $key ) ) {
                self::redirect( 'invalid' );
            }
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Own instalment transaction.
            $wpdb->query( 'START TRANSACTION' );
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Lock the loan while reconciling an instalment.
            $loan = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d FOR UPDATE', self::table( 'loans' ), $id ) );
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Idempotent request key.
            $previous = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE request_key = %s', self::table( 'loan_payments' ), $key ) );
            if ( $previous ) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Idempotent replay.
                $wpdb->query( 'COMMIT' );
                self::redirect( 'saved' );
            }
            if ( ! $loan || (int) $loan->term_months < 1 || $date < $loan->start_date || $date < $account->opened_on ) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Reject invalid payment.
                $wpdb->query( 'ROLLBACK' );
                self::redirect( 'invalid' );
            }
            $schedule = TCA_Loan_Schedule::calculate( $loan->principal, $loan->annual_rate,
                $loan->term_months, $loan->first_due_date, $loan->balloon_payment );
            // A managed loan stores the original first due date independently from
            // the updated next due date, so later instalments remain deterministic.
            if ( ! $schedule ) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- No cash posted.
                $wpdb->query( 'ROLLBACK' );
                self::redirect( 'invalid' );
            }
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Latest settled instalment.
            $last = (int) $wpdb->get_var( $wpdb->prepare(
                'SELECT COALESCE(MAX(instalment_number),0) FROM %i WHERE loan_id = %d', self::table( 'loan_payments' ), $id
            ) );
            if ( ! isset( $schedule[ $last ] ) ||
                abs( (float) $loan->remaining - ( $last ? $schedule[ $last - 1 ]['remaining'] : (float) $loan->principal ) ) > 0.02 ) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Prevent inconsistent repayment.
                $wpdb->query( 'ROLLBACK' );
                self::redirect( 'invalid' );
            }
            $instalment = $schedule[ $last ];
            $saved = self::insert_movement( $account->id, $date, 'out', $instalment['payment'],
                'Loan #' . $id . ' instalment ' . $instalment['number'] );
            $movement_id = $saved ? (int) $wpdb->insert_id : 0;
            if ( $saved ) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Own repayment audit linked to one movement.
                $saved = $wpdb->insert( self::table( 'loan_payments' ), array(
                    'loan_id' => $id, 'instalment_number' => $instalment['number'],
                    'movement_id' => $movement_id, 'principal_amount' => $instalment['principal'],
                    'interest_amount' => $instalment['interest'], 'paid_amount' => $instalment['payment'],
                    'paid_on' => $date, 'request_key' => $key, 'created_at' => current_time( 'mysql' ),
                ), array( '%d', '%d', '%d', '%f', '%f', '%f', '%s', '%s', '%s' ) );
            }
            if ( $saved ) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Principal moves only when the actual instalment is paid.
                $saved = 1 === $wpdb->update( self::table( 'loans' ), array(
                    'remaining' => $instalment['remaining'],
                    'next_due_date' => isset( $schedule[ $last + 1 ] ) ? $schedule[ $last + 1 ]['due_date'] : $instalment['due_date'],
                    'updated_at' => current_time( 'mysql' ),
                ), array( 'id' => $id ), array( '%f', '%s', '%s' ), array( '%d' ) );
            }
            if ( $saved ) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- No half-paid instalments.
                $wpdb->query( 'COMMIT' );
            } else {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- No half-paid instalments.
                $wpdb->query( 'ROLLBACK' );
            }
            self::redirect( $saved ? 'saved' : 'error' );
        }
        if ( 'loan_update' === $operation ) {
            $id = absint( self::posted( 'loan_id' ) );
            $remaining = self::money( self::posted( 'remaining' ) );
            $monthly = self::money( self::posted( 'monthly_payment' ) );
            $due = self::posted( 'next_due_date' );
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Own loan table.
            $loan = $wpdb->get_row( $wpdb->prepare(
                'SELECT * FROM %i WHERE id = %d LIMIT 1', self::table( 'loans' ), $id
            ) );
            if ( ! $loan || (int) $loan->term_months > 0 ||
                ( isset( $loan->repayment_mode ) && 'flexible' === $loan->repayment_mode ) ||
                null === $remaining || $remaining > (float) $loan->principal ||
                null === $monthly || $monthly <= 0 || ! self::date( $due, true ) ) {
                self::redirect( 'invalid' );
            }
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Only own loan status.
            $ok = $wpdb->update( self::table( 'loans' ), array(
                'remaining' => $remaining, 'monthly_payment' => $monthly,
                'next_due_date' => $due, 'updated_at' => current_time( 'mysql' ),
            ), array( 'id' => $id ), array( '%f', '%f', '%s', '%s' ), array( '%d' ) );
            self::redirect( false !== $ok ? 'saved' : 'error' );
        }
        self::redirect( 'invalid' );
    }

    /**
     * Read-only attribution of an existing bank opening balance to its lender.
     * Never posts a cash movement. A loan must cover the entire opening amount.
     *
     * @param array  $accounts Treasury account rows.
     * @param array  $loans Loan register rows.
     * @param string $today WordPress local current date (YYYY-MM-DD).
     * @return array Eligible dated opening-loan funding records.
     */
    public static function opening_loan_funding( $accounts, $loans, $today ) {
        $bank_accounts = array();
        foreach ( $accounts as $account ) {
            if ( 'bank' === (string) $account->kind ) {
                $bank_accounts[ (int) $account->id ] = $account;
            }
        }
        $used_accounts = array();
        $funding = array();
        foreach ( $loans as $loan ) {
            $account_id = (int) ( $loan->opening_account_id ?? 0 );
            $date = (string) ( $loan->start_date ?? '' );
            if ( 'opening_balance' !== (string) ( $loan->funding_source ?? '' ) ||
                ! $account_id || isset( $used_accounts[ $account_id ] ) ||
                ! isset( $bank_accounts[ $account_id ] ) ||
                ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ||
                $date > $today ) {
                continue;
            }
            $account = $bank_accounts[ $account_id ];
            $amount = round( (float) $loan->principal, 2 );
            if ( $date < (string) $account->opened_on || $amount <= 0 ||
                abs( $amount - round( (float) $account->opening_amount, 2 ) ) > 0.005 ) {
                continue;
            }
            $used_accounts[ $account_id ] = true;
            $funding[] = array(
                'loan_id' => (int) $loan->id,
                'loan_name' => (string) $loan->name,
                'loan_date' => $date,
                'account_id' => $account_id,
                'account_name' => (string) $account->name,
                'amount' => $amount,
                'remaining' => round( (float) $loan->remaining, 2 ),
            );
        }
        return $funding;
    }

    /**
     * Build a financial overview from real records; no fiscal entries or money writes.
     */
    public static function financial_overview_from_records( $accounts, $movements, $loans, $year, $today ) {
        $report = self::calculate( $accounts, $movements, $year, $today, $loans );
        $funding_year = array();
        $funding_total = 0.0;
        foreach ( self::opening_loan_funding( $accounts, $loans, $today ) as $funding ) {
            if ( substr( $funding['loan_date'], 0, 4 ) !== (string) $year ) {
                continue;
            }
            $funding_year[] = $funding;
            $funding_total += $funding['amount'];
        }
        $outstanding = 0.0;
        foreach ( $loans as $loan ) {
            $outstanding += (float) $loan->remaining;
        }
        return array(
            'year' => (int) $year,
            'opening_loan_funding_total' => round( $funding_total, 2 ),
            'opening_loan_funding' => $funding_year,
            'bank_balance_current' => round( (float) $report['current']['bank'], 2 ),
            'loan_debt_current' => round( $outstanding, 2 ),
            'monthly_cash_flow' => $report['months'],
        );
    }

    /** Current Treasury figures for the summary and additive general JSON export. */
    public static function financial_overview( $year ) {
        global $wpdb;
        $today = current_time( 'Y-m-d' );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Read-only financial summary from plugin-owned accounts.
        $accounts = (array) $wpdb->get_results( $wpdb->prepare(
            'SELECT * FROM %i ORDER BY id', self::table( 'accounts' )
        ) );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Read-only loan register.
        $loans = (array) $wpdb->get_results( $wpdb->prepare(
            'SELECT * FROM %i ORDER BY id', self::table( 'loans' )
        ) );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Read-only cash flow aggregate.
        $movements = (array) $wpdb->get_results( $wpdb->prepare(
            'SELECT account_id, DATE_FORMAT(posted_on, %s) AS month, direction, SUM(amount) AS total FROM %i WHERE posted_on <= %s GROUP BY account_id, month, direction ORDER BY month',
            '%Y-%m', self::table( 'movements' ), $today
        ) );
        return self::financial_overview_from_records( $accounts, $movements, $loans, $year, $today );
    }

    /** Keep fiscal totals and financing visibly separate in the main Summary tab. */
    public static function render_financial_overview( $year ) {
        $overview = self::financial_overview( $year );
        echo '<h2>' . esc_html__( 'Financial overview (not sales or VAT)', 'taxocommerce-accounting' ) .
            ' — ' . esc_html( (string) $year ) . '</h2>';
        echo '<div class="tca-treasury-kpis">';
        foreach ( array(
            __( 'Initial loan funding (already in opening balance)', 'taxocommerce-accounting' ) => $overview['opening_loan_funding_total'],
            __( 'Current bank balance', 'taxocommerce-accounting' ) => $overview['bank_balance_current'],
            __( 'Loans outstanding (reported)', 'taxocommerce-accounting' ) => $overview['loan_debt_current'],
        ) as $label => $value ) {
            echo '<div class="tca-treasury-kpi"><span>' . esc_html( $label ) . '</span><strong>' .
                esc_html( tca_money( $value ) ) . '</strong></div>';
        }
        echo '</div>';
        if ( $overview['opening_loan_funding'] ) {
            echo '<table class="widefat striped"><thead><tr>';
            foreach ( array(
                __( 'Loan date', 'taxocommerce-accounting' ),
                __( 'Loan', 'taxocommerce-accounting' ),
                __( 'Bank accounts', 'taxocommerce-accounting' ),
                __( 'Original loan', 'taxocommerce-accounting' ),
                __( 'Outstanding', 'taxocommerce-accounting' ),
            ) as $label ) {
                echo '<th>' . esc_html( $label ) . '</th>';
            }
            echo '</tr></thead><tbody>';
            foreach ( $overview['opening_loan_funding'] as $funding ) {
                echo '<tr><td>' . esc_html( $funding['loan_date'] ) .
                    '</td><td>' . esc_html( $funding['loan_name'] ) .
                    '</td><td>' . esc_html( $funding['account_name'] ) .
                    '</td><td>' . esc_html( tca_money( $funding['amount'] ) ) .
                    '</td><td>' . esc_html( tca_money( $funding['remaining'] ) ) . '</td></tr>';
            }
            echo '</tbody></table>';
            echo '<p class="description">' . esc_html__(
                'The opening loan is shown as a bank inflow in its start month for reporting. It is already included in the opening bank balance: no second bank movement, sale or VAT is created.',
                'taxocommerce-accounting'
            ) . '</p>';
        }
    }

    /**
     * Pure calculations for monthly bank/cash reports; testable without WP.
     * Opening-loan money may be reclassified as a dated inflow for display:
     * balances stay unchanged and no additional cash movement is created.
     */
    public static function calculate( $accounts, $movements, $year, $today, $loans = array() ) {
        $opening_funding = self::opening_loan_funding( $accounts, $loans, $today );
        $opening_by_month = array();
        $opening_by_account = array();
        foreach ( $opening_funding as $funding ) {
            $month = substr( $funding['loan_date'], 0, 7 );
            $opening_by_month[ $month ] = ( $opening_by_month[ $month ] ?? 0.0 ) + $funding['amount'];
            $opening_by_account[ $funding['account_id'] ] = $funding;
        }
        $accounts_by_id = array();
        foreach ( $accounts as $account ) {
            $accounts_by_id[ (int) $account->id ] = $account;
        }
        $current = array( 'cash' => 0.0, 'bank' => 0.0 );
        $months = array();
        for ( $month = 1; $month <= 12; ++$month ) {
            $key = sprintf( '%04d-%02d', $year, $month );
            $asof = min( $today, gmdate( 'Y-m-t', strtotime( $key . '-01' ) ) );
            $row = array(
                'month' => $key, 'cash_in' => 0.0, 'cash_out' => 0.0,
                'bank_in' => 0.0, 'bank_out' => 0.0,
                'opening_loan_in' => 0.0,
                'cash_balance' => 0.0, 'bank_balance' => 0.0,
            );
            foreach ( $accounts as $account ) {
                if ( $account->opened_on <= $asof ) {
                    // The loan was dated after opening: do not show its funds
                    // in earlier closing balances, before the recorded loan date.
                    $funding = $opening_by_account[ (int) $account->id ] ?? null;
                    if ( ! $funding || $funding['loan_date'] <= $asof ) {
                        $row[ $account->kind . '_balance' ] += (float) $account->opening_amount;
                    }
                }
            }
            // Reclassify the existing opening funds as a dated inflow for
            // reporting only. The balance already contains the same money.
            $row['opening_loan_in'] = (float) ( $opening_by_month[ $key ] ?? 0.0 );
            $row['bank_in'] += $row['opening_loan_in'];
            foreach ( $movements as $movement ) {
                $id = (int) $movement->account_id;
                if ( ! isset( $accounts_by_id[ $id ] ) ) {
                    continue;
                }
                $kind = $accounts_by_id[ $id ]->kind;
                $positive = in_array( $movement->direction, array( 'in', 'transfer_in' ), true );
                $value = (float) $movement->total;
                if ( $movement->month <= $key ) {
                    $row[ $kind . '_balance' ] += $positive ? $value : -$value;
                }
                if ( $movement->month === $key ) {
                    $row[ $kind . ( $positive ? '_in' : '_out' ) ] += $value;
                }
            }
            $months[ $key ] = $row;
        }
        foreach ( $accounts as $account ) {
            if ( $account->opened_on <= $today ) {
                $current[ $account->kind ] += (float) $account->opening_amount;
            }
        }
        foreach ( $movements as $movement ) {
            if ( ! isset( $accounts_by_id[ (int) $movement->account_id ] ) ||
                $movement->month > substr( $today, 0, 7 ) ) {
                continue;
            }
            $kind = $accounts_by_id[ (int) $movement->account_id ]->kind;
            $current[ $kind ] += in_array( $movement->direction, array( 'in', 'transfer_in' ), true )
                ? (float) $movement->total : -(float) $movement->total;
        }
        return array( 'current' => $current, 'months' => $months );
    }

    private static function start_form( $action ) {
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        wp_nonce_field( 'tca_treasury_action' );
        echo '<input type="hidden" name="action" value="tca_treasury_action">';
        echo '<input type="hidden" name="treasury_action" value="' . esc_attr( $action ) . '">';
    }

    private static function account_select( $accounts, $name, $selected = 0 ) {
        echo '<select name="' . esc_attr( $name ) . '" required>';
        foreach ( $accounts as $account ) {
            echo '<option value="' . esc_attr( (string) $account->id ) . '"' . selected( (int) $account->id, $selected, false ) . '>' . esc_html( $account->name ) . ' (' .
                esc_html( 'cash' === $account->kind ? __( 'Cash', 'taxocommerce-accounting' ) : __( 'Bank', 'taxocommerce-accounting' ) ) . ')</option>';
        }
        echo '</select>';
    }

    public static function render( $year ) {
        global $wpdb;
        $today = current_time( 'Y-m-d' );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Treasury dashboard needs current owned account balances.
        $accounts = (array) $wpdb->get_results( $wpdb->prepare(
            'SELECT * FROM %i ORDER BY kind ASC, name ASC, id ASC', self::table( 'accounts' )
        ) );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Monthly aggregate of the plugin's own movements.
        $movements = (array) $wpdb->get_results( $wpdb->prepare(
            'SELECT account_id, DATE_FORMAT(posted_on, %s) AS month, direction, SUM(amount) AS total FROM %i WHERE posted_on <= %s GROUP BY account_id, month, direction ORDER BY month',
            '%Y-%m', self::table( 'movements' ), $today
        ) );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Own loan balance register.
        $loans = (array) $wpdb->get_results( $wpdb->prepare(
            'SELECT * FROM %i ORDER BY next_due_date ASC, id ASC', self::table( 'loans' )
        ) );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Aggregated owned loan payments are read-only.
        $loan_payments = (array) $wpdb->get_results( $wpdb->prepare(
            'SELECT loan_id, COUNT(*) AS paid_count, COALESCE(SUM(interest_amount),0) AS interest_paid, COALESCE(SUM(principal_amount),0) AS capital_paid FROM %i GROUP BY loan_id',
            self::table( 'loan_payments' )
        ) );
        $payments_by_loan = array();
        foreach ( $loan_payments as $paid ) {
            $payments_by_loan[ (int) $paid->loan_id ] = $paid;
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Recent recorded transfers and cash movements.
        $recent = (array) $wpdb->get_results( $wpdb->prepare(
            'SELECT m.*, s.entry_id AS settlement_entry_id, lp.loan_id AS loan_payment_id, l.id AS funding_loan_id FROM %i AS m LEFT JOIN %i AS s ON s.movement_id = m.id LEFT JOIN %i AS lp ON lp.movement_id = m.id LEFT JOIN %i AS l ON l.funding_movement_id = m.id ORDER BY m.posted_on DESC, m.id DESC LIMIT 50',
            self::table( 'movements' ), TCA_Settlements::table(), self::table( 'loan_payments' ), self::table( 'loans' )
        ) );
        $report = self::calculate( $accounts, $movements, $year, $today, $loans );
        $cash = $report['current']['cash'];
        $bank = $report['current']['bank'];
        $debt = 0.0;
        $monthly = 0.0;
        foreach ( $loans as $loan ) {
            $debt += (float) $loan->remaining;
            if ( (float) $loan->remaining > 0 ) {
                $monthly += (float) $loan->monthly_payment;
            }
        }
        echo '<h2>' . esc_html__( 'Treasury: cash, bank and loans', 'taxocommerce-accounting' ) . '</h2>';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only result message after a nonce-protected settlement form.
        if ( isset( $_GET['notice'] ) && 'bulk_settlement_result' === sanitize_key( wp_unslash( $_GET['notice'] ) ) ) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- These GET numbers are displayed only, not used for writes.
            $posted = isset( $_GET['posted'] ) ? absint( wp_unslash( $_GET['posted'] ) ) : 0;
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only counts.
            $skipped = isset( $_GET['skipped'] ) ? absint( wp_unslash( $_GET['skipped'] ) ) : 0;
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only counts.
            $errors = isset( $_GET['errors'] ) ? absint( wp_unslash( $_GET['errors'] ) ) : 0;
            echo '<div class="notice notice-info inline"><p>' . esc_html(
                sprintf(
                    /* translators: 1: payments posted, 2: payments skipped, 3: payments with errors. */
                    __( 'Historical reconciliation: %1$d payments posted, %2$d skipped, %3$d errors. Review the bank balance before posting anything again.', 'taxocommerce-accounting' ),
                    $posted, $skipped, $errors
                )
            ) . '</p></div>';
        }
        echo '<p class="description">' . esc_html__( 'Balances use opening funds and confirmed payments/receipts (including invoice-linked settlements). An opening balance is not a monthly Cash in / Bank in. If it actually represents a contribution received this year, you can convert it to a dated receipt below without changing the balance. Unpaid invoices and future loan instalments never move money.', 'taxocommerce-accounting' ) . '</p>';
        echo '<div class="tca-treasury-kpis">';
        $indicators = array(
            __( 'Cash on hand', 'taxocommerce-accounting' ) => $cash,
            __( 'Bank accounts', 'taxocommerce-accounting' ) => $bank,
            __( 'Total available funds', 'taxocommerce-accounting' ) => $cash + $bank,
            __( 'Loans outstanding (reported)', 'taxocommerce-accounting' ) => $debt,
            __( 'Scheduled monthly loan instalments', 'taxocommerce-accounting' ) => $monthly,
        );
        foreach ( $indicators as $label => $value ) {
            echo '<div class="tca-treasury-kpi"><span>' . esc_html( $label ) . '</span><strong>' . esc_html( tca_money( $value ) ) . '</strong></div>';
        }
        echo '</div>';

        echo '<h3>' . esc_html__( 'Cash and bank accounts', 'taxocommerce-accounting' ) . '</h3>';
        echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Account', 'taxocommerce-accounting' ) . '</th><th>' . esc_html__( 'Type', 'taxocommerce-accounting' ) . '</th><th>' . esc_html__( 'Opening balance', 'taxocommerce-accounting' ) . '</th><th>' . esc_html__( 'Current balance', 'taxocommerce-accounting' ) . '</th><th>' . esc_html__( 'Funding entry', 'taxocommerce-accounting' ) . '</th><th>' . esc_html__( 'Edit', 'taxocommerce-accounting' ) . '</th></tr></thead><tbody>';
        $by_id = array();
        $opening_loans_by_account = array();
        foreach ( $loans as $loan ) {
            if ( ! empty( $loan->opening_account_id ) ) {
                $opening_loans_by_account[ (int) $loan->opening_account_id ] = $loan;
            }
        }
        foreach ( $accounts as $account ) {
            $by_id[ (int) $account->id ] = $account;
            $balance = $account->opened_on <= $today ? (float) $account->opening_amount : 0.0;
            foreach ( $movements as $movement ) {
                if ( (int) $movement->account_id === (int) $account->id ) {
                    $balance += in_array( $movement->direction, array( 'in', 'transfer_in' ), true )
                        ? (float) $movement->total : -(float) $movement->total;
                }
            }
            $account_state = (float) $account->opening_amount > 0
                ? ( isset( $opening_loans_by_account[ (int) $account->id ] )
                    ? TCA_Status_Colors::loan( $opening_loans_by_account[ (int) $account->id ] )
                    : 'pending' )
                : 'neutral';
            echo '<tr class="tca-state-' . esc_attr( $account_state ) . '"><td>' .
                esc_html( $account->name ) . '</td><td>' . esc_html( 'cash' === $account->kind ? __( 'Cash', 'taxocommerce-accounting' ) : __( 'Bank', 'taxocommerce-accounting' ) ) . '</td><td>' . esc_html( tca_money( $account->opening_amount ) ) . '</td><td>' . esc_html( tca_money( $balance ) ) . '</td><td>';
            if ( (float) $account->opening_amount > 0 &&
                isset( $opening_loans_by_account[ (int) $account->id ] ) ) {
                $linked = $opening_loans_by_account[ (int) $account->id ];
                echo '<strong>' . esc_html__( 'Linked to a repayable loan from', 'taxocommerce-accounting' ) . ' ' .
                    esc_html( $linked->lender ) . '</strong> ';
                echo '<span class="description">' .
                    esc_html__( 'The opening funds are already present. Conversion or cancellation is blocked while this loan is linked.', 'taxocommerce-accounting' ) .
                    '</span>';
            } elseif ( (float) $account->opening_amount > 0 ) {
                self::start_form( 'opening_receipt' );
                echo '<input type="hidden" name="account_id" value="' . esc_attr( (string) $account->id ) . '">';
                echo '<label>' . esc_html__( 'Actual contribution date', 'taxocommerce-accounting' ) . ' <input name="posted_on" type="date" min="' . esc_attr( $account->opened_on ) . '" max="' . esc_attr( $today ) . '" value="' . esc_attr( $account->opened_on ) . '" required></label> ';
                echo '<label>' . esc_html__( 'Funding reference', 'taxocommerce-accounting' ) . ' <input name="reference" maxlength="191" required placeholder="' . esc_attr__( 'Initial loan / contribution', 'taxocommerce-accounting' ) . '"></label> ';
                echo '<label><input type="checkbox" name="opening_verified" value="1" required> ' . esc_html__( 'Convert the opening balance, without adding funds twice', 'taxocommerce-accounting' ) . '</label> ';
                submit_button( __( 'Convert to receipt', 'taxocommerce-accounting' ), 'secondary small', 'submit', false );
                echo '</form>';
                self::start_form( 'cancel_opening' );
                echo '<input type="hidden" name="account_id" value="' . esc_attr( (string) $account->id ) . '">';
                echo '<label>' . esc_html__( 'Correction reason', 'taxocommerce-accounting' ) . ' <input name="reason" maxlength="191" required placeholder="' . esc_attr__( 'Opening amount entered in error', 'taxocommerce-accounting' ) . '"></label> ';
                echo '<label><input type="checkbox" name="confirmed" value="1" required> ' . esc_html__( 'Confirm cancellation of this opening balance', 'taxocommerce-accounting' ) . '</label> ';
                submit_button( __( 'Cancel incorrect opening balance', 'taxocommerce-accounting' ), 'secondary small', 'submit', false );
                echo '</form>';
            } else {
                echo '&mdash;';
            }
            echo '</td><td>';
            self::start_form( 'edit_account' );
            echo '<input type="hidden" name="account_id" value="' . esc_attr( (string) $account->id ) . '">';
            echo '<input type="text" name="name" maxlength="191" value="' . esc_attr( $account->name ) . '" required>';
            submit_button( __( 'Rename account', 'taxocommerce-accounting' ), 'secondary small', 'submit', false );
            echo '</form></td></tr>';
        }
        if ( ! $accounts ) {
            echo '<tr><td colspan="6">' . esc_html__( 'Add your cash register or first bank account to start tracking funds.', 'taxocommerce-accounting' ) . '</td></tr>';
        }
        echo '</tbody></table>';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Always show the latest correction audit records.
        $corrections = (array) $wpdb->get_results( $wpdb->prepare(
            'SELECT * FROM %i ORDER BY id DESC LIMIT 30', self::table( 'corrections' )
        ) );
        if ( $corrections ) {
            echo '<h4>' . esc_html__( 'Opening balance correction history', 'taxocommerce-accounting' ) . '</h4>';
            echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Date', 'taxocommerce-accounting' ) . '</th><th>' . esc_html__( 'Account', 'taxocommerce-accounting' ) . '</th><th>' . esc_html__( 'Amount', 'taxocommerce-accounting' ) . '</th><th>' . esc_html__( 'Reason', 'taxocommerce-accounting' ) . '</th></tr></thead><tbody>';
            foreach ( $corrections as $correction ) {
                echo '<tr><td>' . esc_html( $correction->created_at ) . '</td><td>' . esc_html( isset( $by_id[ (int) $correction->account_id ] ) ? $by_id[ (int) $correction->account_id ]->name : '' ) . '</td><td>' . esc_html( tca_money( $correction->amount ) ) . '</td><td>' . esc_html( $correction->reason ) . '</td></tr>';
            }
            echo '</tbody></table>';
        }
        TCA_Settlements::render_bulk( $year );

        echo '<div class="tca-treasury-forms"><div>';
        echo '<h4>' . esc_html__( 'Create a new cash or bank account (not a receipt)', 'taxocommerce-accounting' ) . '</h4>';
        echo '<p class="description">' . esc_html__( 'Use a descriptive account name, such as Main bank or Cash register. Record donations, initial contributions and customer receipts as movements into an existing account.', 'taxocommerce-accounting' ) . '</p>';
        self::start_form( 'account' );
        echo '<p><label>' . esc_html__( 'Name', 'taxocommerce-accounting' ) . ' <input type="text" name="name" maxlength="191" required></label></p>';
        echo '<p><label>' . esc_html__( 'Type', 'taxocommerce-accounting' ) . ' <select name="kind"><option value="cash">' . esc_html__( 'Cash', 'taxocommerce-accounting' ) . '</option><option value="bank">' . esc_html__( 'Bank', 'taxocommerce-accounting' ) . '</option></select></label></p>';
        echo '<p><label>' . esc_html__( 'Opening date', 'taxocommerce-accounting' ) . ' <input type="date" name="opened_on" value="' . esc_attr( $today ) . '" required></label></p>';
        echo '<p><label>' . esc_html__( 'Opening balance (€)', 'taxocommerce-accounting' ) . ' <input type="number" name="opening_amount" step="0.01" value="0" required></label></p>';
        submit_button( __( 'Add account', 'taxocommerce-accounting' ), 'secondary', 'submit', false );
        echo '</form></div>';
        if ( $accounts ) {
            echo '<div><h4>' . esc_html__( 'Record a payment or receipt', 'taxocommerce-accounting' ) . '</h4>';
            self::start_form( 'movement' );
            echo '<p><label>' . esc_html__( 'Account', 'taxocommerce-accounting' ) . ' ';
            self::account_select( $accounts, 'account_id' );
            echo '</label></p><p><label>' . esc_html__( 'Movement', 'taxocommerce-accounting' ) . ' <select name="direction"><option value="in">' . esc_html__( 'Money in', 'taxocommerce-accounting' ) . '</option><option value="out">' . esc_html__( 'Money out', 'taxocommerce-accounting' ) . '</option></select></label></p>';
            echo '<p><label>' . esc_html__( 'Date', 'taxocommerce-accounting' ) . ' <input type="date" name="posted_on" value="' . esc_attr( $today ) . '" required></label></p>';
            echo '<p><label>' . esc_html__( 'Amount (€)', 'taxocommerce-accounting' ) . ' <input type="number" min="0.01" step="0.01" name="amount" required></label></p>';
            echo '<p><label>' . esc_html__( 'Reference / concept', 'taxocommerce-accounting' ) . ' <input type="text" maxlength="191" name="reference"></label></p>';
            submit_button( __( 'Save movement', 'taxocommerce-accounting' ), 'secondary', 'submit', false );
            echo '</form></div>';
        }
        if ( count( $accounts ) > 1 ) {
            echo '<div><h4>' . esc_html__( 'Transfer between accounts', 'taxocommerce-accounting' ) . '</h4>';
            self::start_form( 'transfer' );
            echo '<p><label>' . esc_html__( 'From', 'taxocommerce-accounting' ) . ' ';
            self::account_select( $accounts, 'account_id' );
            echo '</label></p><p><label>' . esc_html__( 'To', 'taxocommerce-accounting' ) . ' ';
            self::account_select( $accounts, 'destination_id' );
            echo '</label></p>';
            echo '<p><label>' . esc_html__( 'Date', 'taxocommerce-accounting' ) . ' <input type="date" name="posted_on" value="' . esc_attr( $today ) . '" required></label></p>';
            echo '<p><label>' . esc_html__( 'Amount (€)', 'taxocommerce-accounting' ) . ' <input type="number" min="0.01" step="0.01" name="amount" required></label></p>';
            echo '<p><label>' . esc_html__( 'Reference / concept', 'taxocommerce-accounting' ) . ' <input type="text" maxlength="191" name="reference"></label></p>';
            submit_button( __( 'Transfer funds', 'taxocommerce-accounting' ), 'secondary', 'submit', false );
            echo '</form></div>';
        }
        echo '</div>';

        echo '<h3>' . esc_html__( 'Monthly cash flow', 'taxocommerce-accounting' ) . ' — ' . esc_html( (string) $year ) . '</h3>';
        echo '<div class="tca-treasury-scroll"><table class="widefat striped"><thead><tr>';
        foreach ( array(
            __( 'Month', 'taxocommerce-accounting' ),
            __( 'Cash in', 'taxocommerce-accounting' ), __( 'Cash out', 'taxocommerce-accounting' ),
            __( 'Bank in', 'taxocommerce-accounting' ), __( 'Bank out', 'taxocommerce-accounting' ),
            __( 'Cash balance', 'taxocommerce-accounting' ), __( 'Bank balance', 'taxocommerce-accounting' ),
        ) as $heading ) {
            echo '<th>' . esc_html( $heading ) . '</th>';
        }
        echo '</tr></thead><tbody>';
        foreach ( $report['months'] as $row ) {
            echo '<tr><td>' . esc_html( $row['month'] ) . '</td>';
            foreach ( array( 'cash_in', 'cash_out', 'bank_in', 'bank_out', 'cash_balance', 'bank_balance' ) as $key ) {
                $value = $row['month'] > substr( $today, 0, 7 ) ? '—' : tca_money( $row[ $key ] );
                echo '<td>' . esc_html( $value ) . '</td>';
            }
            echo '</tr>';
        }
        echo '</tbody></table></div>';
        foreach ( $report['months'] as $month_row ) {
            if ( $month_row['opening_loan_in'] > 0 ) {
                echo '<p class="description">' . esc_html__(
                    'The opening loan is shown as a bank inflow in its start month for reporting. It is already included in the opening bank balance: no second bank movement, sale or VAT is created.',
                    'taxocommerce-accounting'
                ) . '</p>';
                break;
            }
        }

        echo '<h3>' . esc_html__( 'Recent cash and bank movements', 'taxocommerce-accounting' ) . '</h3>';
        echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Date', 'taxocommerce-accounting' ) . '</th><th>' . esc_html__( 'Account', 'taxocommerce-accounting' ) . '</th><th>' . esc_html__( 'Movement', 'taxocommerce-accounting' ) . '</th><th>' . esc_html__( 'Amount', 'taxocommerce-accounting' ) . '</th><th>' . esc_html__( 'Reference', 'taxocommerce-accounting' ) . '</th><th>' . esc_html__( 'Edit', 'taxocommerce-accounting' ) . '</th></tr></thead><tbody>';
        foreach ( $recent as $movement ) {
            $positive = in_array( $movement->direction, array( 'in', 'transfer_in' ), true );
            $linked = ! empty( $movement->settlement_entry_id ) ||
                ! empty( $movement->loan_payment_id ) || ! empty( $movement->funding_loan_id );
            $movement_state = (float) $movement->amount <= 0 || ! isset( $by_id[ (int) $movement->account_id ] )
                ? 'error'
                : ( $linked || in_array( $movement->direction, array( 'transfer_in', 'transfer_out' ), true )
                    ? 'closed' : 'pending' );
            echo '<tr class="tca-state-' . esc_attr( $movement_state ) .
                '"><td>' . esc_html( $movement->posted_on ) . '</td><td>' .
                esc_html( isset( $by_id[ (int) $movement->account_id ] ) ? $by_id[ (int) $movement->account_id ]->name : '' ) . '</td><td>' .
                esc_html( $positive ? __( 'Money in', 'taxocommerce-accounting' ) : __( 'Money out', 'taxocommerce-accounting' ) ) . '</td><td>' .
                esc_html( ( $positive ? '+' : '−' ) . tca_money( $movement->amount ) ) . '</td><td>' . esc_html( $movement->reference );
            if ( 'pending' === $movement_state ) {
                echo '<p class="description">' .
                    esc_html__( 'Recorded movement without an invoice or loan link. Check before adding another payment.', 'taxocommerce-accounting' ) .
                    '</p>';
            }
            echo '</td><td>';
            if ( in_array( $movement->direction, array( 'in', 'out' ), true ) ) {
                self::start_form( 'edit_movement' );
                echo '<input type="hidden" name="movement_id" value="' . esc_attr( (string) $movement->id ) . '">';
                self::account_select( $accounts, 'account_id', (int) $movement->account_id );
                echo '<select name="direction"><option value="in"' . selected( $movement->direction, 'in', false ) . '>' . esc_html__( 'Money in', 'taxocommerce-accounting' ) . '</option><option value="out"' . selected( $movement->direction, 'out', false ) . '>' . esc_html__( 'Money out', 'taxocommerce-accounting' ) . '</option></select>';
                echo '<input type="date" name="posted_on" value="' . esc_attr( $movement->posted_on ) . '" required>';
                echo '<input type="number" name="amount" min="0.01" step="0.01" value="' . esc_attr( (string) $movement->amount ) . '" required>';
                echo '<input type="text" name="reference" maxlength="191" value="' . esc_attr( $movement->reference ) . '">';
                submit_button( __( 'Save changes', 'taxocommerce-accounting' ), 'secondary small', 'submit', false );
                echo '</form>';
            } else { echo '&mdash;'; }
            echo '</td></tr>';
        }
        if ( ! $recent ) {
            echo '<tr><td colspan="6">' . esc_html__( 'No movements recorded yet.', 'taxocommerce-accounting' ) . '</td></tr>';
        }
        echo '</tbody></table>';

        echo '<h3>' . esc_html__( 'Loans', 'taxocommerce-accounting' ) . '</h3>';
        echo '<p class="description">' . esc_html__(
            'New fixed-rate loans show monthly capital/interest forecasts. Interest on ordinary money loans is generally VAT-exempt; the schedule never changes model 303. Neither a future instalment nor an unconfirmed loan disbursement changes bank balances. Older manual loans retain their original update controls.',
            'taxocommerce-accounting'
        ) . '</p>';
        echo '<div class="tca-treasury-scroll"><table class="widefat striped"><thead><tr>';
        foreach ( array(
            __( 'Loan', 'taxocommerce-accounting' ), __( 'Lender', 'taxocommerce-accounting' ),
            __( 'Original loan', 'taxocommerce-accounting' ), __( 'Monthly payment', 'taxocommerce-accounting' ),
            __( 'Outstanding', 'taxocommerce-accounting' ), __( 'Next payment date', 'taxocommerce-accounting' ),
            __( 'Payment / updates', 'taxocommerce-accounting' ),
        ) as $heading ) {
            echo '<th>' . esc_html( $heading ) . '</th>';
        }
        echo '</tr></thead><tbody>';
        foreach ( $loans as $loan ) {
            $is_flexible = isset( $loan->repayment_mode ) && 'flexible' === $loan->repayment_mode;
            $managed = (int) $loan->term_months > 0;
            $paid_count = isset( $payments_by_loan[ (int) $loan->id ] ) ? (int) $payments_by_loan[ (int) $loan->id ]->paid_count : 0;
            $schedule = $managed
                ? TCA_Loan_Schedule::calculate( $loan->principal, $loan->annual_rate, $loan->term_months, $loan->first_due_date, $loan->balloon_payment )
                : array();
            $next = $schedule[ $paid_count ] ?? null;
            $lender_details = $loan->lender;
            if ( isset( $loan->lender_relation ) && 'sponsor' === $loan->lender_relation ) {
                $lender_details .= ' (' . __( 'Sponsor / lender', 'taxocommerce-accounting' ) . ')';
            }
            $source_details = '';
            if ( ! empty( $loan->opening_account_id ) ) {
                $source_details = __( 'Funded by existing opening balance; no duplicate receipt', 'taxocommerce-accounting' );
                if ( isset( $by_id[ (int) $loan->opening_account_id ] ) ) {
                    $source_details .= ': ' . $by_id[ (int) $loan->opening_account_id ]->name;
                }
            }
            $loan_state = TCA_Status_Colors::loan( $loan );
            echo '<tr class="tca-state-' . esc_attr( $loan_state ) . '"><td>' .
                esc_html( $loan->name ) . '</td><td>' . esc_html( $lender_details );
            if ( '' !== $source_details ) {
                echo '<p class="description">' . esc_html( $source_details ) . '</p>';
            }
            echo '</td><td>' . esc_html( tca_money( $loan->principal ) ) .
                '</td><td>' . esc_html( tca_money( $next ? $next['payment'] : $loan->monthly_payment ) ) .
                '</td><td>' . esc_html( tca_money( $loan->remaining ) ) .
                '</td><td>' . esc_html( (float) $loan->remaining <= 0 || $is_flexible && '1970-01-01' === $loan->next_due_date
                    ? '—' : $loan->next_due_date ) . '</td><td>';
            if ( $managed ) {
                echo '<p>' . esc_html( (string) $loan->term_months ) . ' ' .
                    esc_html__( 'months; annual nominal rate', 'taxocommerce-accounting' ) . ': ' .
                    esc_html( number_format_i18n( (float) $loan->annual_rate, 2 ) ) .
                    '%; ' . esc_html__( 'Down payment', 'taxocommerce-accounting' ) . ': ' .
                    esc_html( tca_money( $loan->upfront_payment ) ) . '</p>';
                if ( isset( $payments_by_loan[ (int) $loan->id ] ) ) {
                    echo '<p>' . esc_html__( 'Interest already paid', 'taxocommerce-accounting' ) . ': ' .
                        esc_html( tca_money( $payments_by_loan[ (int) $loan->id ]->interest_paid ) ) . '</p>';
                }
                if ( $next && $accounts ) {
                    echo '<p><strong>' . esc_html__( 'Next instalment', 'taxocommerce-accounting' ) . ': ' .
                        esc_html( tca_money( $next['payment'] ) ) . '</strong> (' .
                        esc_html__( 'Capital', 'taxocommerce-accounting' ) . ' ' . esc_html( tca_money( $next['principal'] ) ) .
                        ' + ' . esc_html__( 'Interest', 'taxocommerce-accounting' ) . ' ' . esc_html( tca_money( $next['interest'] ) ) . ')</p>';
                    self::start_form( 'loan_payment' );
                    echo '<input type="hidden" name="loan_id" value="' . esc_attr( (string) $loan->id ) . '">';
                    echo '<input type="hidden" name="request_key" value="' . esc_attr( wp_generate_uuid4() ) . '">';
                    echo '<label>' . esc_html__( 'Pay from', 'taxocommerce-accounting' ) . ' ';
                    self::account_select( $accounts, 'account_id' );
                    echo '</label> ';
                    echo '<label>' . esc_html__( 'Actual payment date', 'taxocommerce-accounting' ) .
                        ' <input type="date" name="posted_on" value="' . esc_attr( $today ) . '" required></label> ';
                    echo '<p><label><input type="checkbox" name="loan_payment_confirmed" value="1" required> ' .
                        esc_html__( 'I confirm this instalment was actually paid from this account.', 'taxocommerce-accounting' ) . '</label></p>';
                    submit_button( __( 'Confirm instalment paid', 'taxocommerce-accounting' ), 'secondary small', 'submit', false );
                    echo '</form>';
                }
                if ( $schedule ) {
                    echo '<details><summary>' . esc_html__( 'Amortisation schedule', 'taxocommerce-accounting' ) . '</summary>';
                    echo '<table class="widefat striped"><thead><tr><th>#</th><th>' .
                        esc_html__( 'Due date', 'taxocommerce-accounting' ) . '</th><th>' .
                        esc_html__( 'Capital', 'taxocommerce-accounting' ) . '</th><th>' .
                        esc_html__( 'Interest', 'taxocommerce-accounting' ) . '</th><th>' .
                        esc_html__( 'Instalment', 'taxocommerce-accounting' ) . '</th><th>' .
                        esc_html__( 'Outstanding', 'taxocommerce-accounting' ) . '</th><th>' .
                        esc_html__( 'Status', 'taxocommerce-accounting' ) . '</th></tr></thead><tbody>';
                    foreach ( $schedule as $row ) {
                        echo '<tr><td>' . esc_html( (string) $row['number'] ) . '</td><td>' .
                            esc_html( $row['due_date'] ) . '</td><td>' . esc_html( tca_money( $row['principal'] ) ) .
                            '</td><td>' . esc_html( tca_money( $row['interest'] ) ) .
                            '</td><td>' . esc_html( tca_money( $row['payment'] ) ) .
                            '</td><td>' . esc_html( tca_money( $row['remaining'] ) ) .
                            '</td><td>' . esc_html( $row['number'] <= $paid_count
                                ? __( 'Paid', 'taxocommerce-accounting' ) : __( 'Forecast', 'taxocommerce-accounting' ) ) . '</td></tr>';
                    }
                    echo '</tbody></table></details>';
                }
            } elseif ( $is_flexible ) {
                echo '<p>' . esc_html__( 'Repayment when agreed, without fixed instalments. Interest: 0%.', 'taxocommerce-accounting' ) . '</p>';
                if ( isset( $payments_by_loan[ (int) $loan->id ] ) ) {
                    $paid = $payments_by_loan[ (int) $loan->id ];
                    echo '<p>' . esc_html__( 'Capital already returned', 'taxocommerce-accounting' ) . ': ' .
                        esc_html( tca_money( $paid->capital_paid ) ) . ' (' .
                        esc_html( (string) $paid->paid_count ) . ' ' .
                        esc_html__( 'repayments', 'taxocommerce-accounting' ) . ')</p>';
                }
                if ( (float) $loan->remaining > 0 && $accounts ) {
                    self::start_form( 'loan_flexible_payment' );
                    echo '<input type="hidden" name="loan_id" value="' . esc_attr( (string) $loan->id ) . '">';
                    echo '<input type="hidden" name="request_key" value="' . esc_attr( wp_generate_uuid4() ) . '">';
                    echo '<label>' . esc_html__( 'Repay from', 'taxocommerce-accounting' ) . ' ';
                    self::account_select( $accounts, 'account_id' );
                    echo '</label> ';
                    echo '<label>' . esc_html__( 'Actual repayment date', 'taxocommerce-accounting' ) .
                        ' <input name="posted_on" type="date" value="' . esc_attr( $today ) . '" min="' . esc_attr( $loan->start_date ) . '" required></label> ';
                    echo '<label>' . esc_html__( 'Capital to repay (€)', 'taxocommerce-accounting' ) .
                        ' <input name="amount" type="number" min="0.01" max="' . esc_attr( (string) $loan->remaining ) .
                        '" step="0.01" value="' . esc_attr( (string) $loan->remaining ) . '" required></label> ';
                    echo '<p><label><input type="checkbox" name="loan_payment_confirmed" value="1" required> ' .
                        esc_html__( 'I confirm that this amount was actually transferred to the lender.', 'taxocommerce-accounting' ) .
                        '</label></p>';
                    submit_button( __( 'Record actual repayment', 'taxocommerce-accounting' ), 'secondary small', 'submit', false );
                    echo '</form>';
                }
            } else {
                // Legacy loans retain manual adjustment; new flexible loans
                // must always be repaid through bank movements.
                self::start_form( 'loan_update' );
                echo '<input type="hidden" name="loan_id" value="' . esc_attr( (string) $loan->id ) . '">';
                echo '<label>' . esc_html__( 'Outstanding', 'taxocommerce-accounting' ) . ' <input type="number" min="0" step="0.01" name="remaining" value="' . esc_attr( (string) $loan->remaining ) . '" required></label> ';
                echo '<label>' . esc_html__( 'Monthly payment', 'taxocommerce-accounting' ) . ' <input type="number" min="0.01" step="0.01" name="monthly_payment" value="' . esc_attr( (string) $loan->monthly_payment ) . '" required></label> ';
                echo '<label>' . esc_html__( 'Next payment date', 'taxocommerce-accounting' ) . ' <input type="date" name="next_due_date" value="' . esc_attr( $loan->next_due_date ) . '" required></label> ';
                submit_button( __( 'Update loan', 'taxocommerce-accounting' ), 'secondary', 'submit', false );
                echo '</form>';
            }
            echo '</td></tr>';
        }
        if ( ! $loans ) {
            echo '<tr><td colspan="7">' . esc_html__( 'No loans entered.', 'taxocommerce-accounting' ) . '</td></tr>';
        }
        echo '</tbody></table></div>';

        echo '<h4>' . esc_html__( 'Add a loan', 'taxocommerce-accounting' ) . '</h4>';
        self::start_form( 'loan' );
        echo '<input type="hidden" name="loan_request_key" value="' . esc_attr( wp_generate_uuid4() ) . '">';
        echo '<p class="description">' . esc_html__(
            'Enter the amount borrowed. For a 0% sponsor loan repayable later without fixed instalments, choose 0 months, 0% interest, and leave the first repayment date empty. Record partial or total repayments only when actually made. For scheduled financing, specify term and first instalment date.',
            'taxocommerce-accounting'
        ) . '</p>';
        echo '<div class="tca-treasury-forms"><div><p><label>' . esc_html__( 'Loan name', 'taxocommerce-accounting' ) . ' <input type="text" maxlength="191" name="name" required></label></p>';
        echo '<p><label>' . esc_html__( 'Sponsor / lender name', 'taxocommerce-accounting' ) . ' <input type="text" maxlength="191" name="lender" required></label></p>';
        echo '<p><label>' . esc_html__( 'Sponsor / lender tax ID (optional)', 'taxocommerce-accounting' ) . ' <input type="text" maxlength="48" name="lender_tax_id"></label></p>';
        echo '<p><label>' . esc_html__( 'Lender relationship', 'taxocommerce-accounting' ) . ' <select name="lender_relation">';
        echo '<option value="sponsor">' . esc_html__( 'Sponsor (repayable loan)', 'taxocommerce-accounting' ) . '</option>';
        echo '<option value="other">' . esc_html__( 'Other lender', 'taxocommerce-accounting' ) . '</option></select></label></p>';
        echo '<p><label>' . esc_html__( 'Loan agreement reference', 'taxocommerce-accounting' ) . ' <input type="text" maxlength="191" name="agreement_reference"></label></p>';
        echo '<p><label>' . esc_html__( 'Annual nominal interest (TIN %)', 'taxocommerce-accounting' ) . ' <input type="number" min="0" max="100" step="0.0001" name="annual_rate" value="0" required></label></p></div>';
        echo '<div><p><label>' . esc_html__( 'Financed principal (€)', 'taxocommerce-accounting' ) . ' <input type="number" min="0.01" step="0.01" name="principal" required></label></p>';
        echo '<p><label>' . esc_html__( 'Term in months (0 = repay later without fixed instalments)', 'taxocommerce-accounting' ) . ' <input type="number" min="0" max="360" step="1" name="term_months" value="0" required></label></p>';
        echo '<p><label>' . esc_html__( 'Down payment from own funds (€)', 'taxocommerce-accounting' ) . ' <input type="number" min="0" step="0.01" name="upfront_payment" value="0" required></label></p>';
        echo '<p><label>' . esc_html__( 'Extra principal on final instalment (€)', 'taxocommerce-accounting' ) . ' <input type="number" min="0" step="0.01" name="balloon_payment" value="0" required></label></p>';
        echo '</div>';
        echo '<div><p><label>' . esc_html__( 'Loan date', 'taxocommerce-accounting' ) . ' <input type="date" name="start_date" value="' . esc_attr( $today ) . '" required></label></p>';
        echo '<p><label>' . esc_html__( 'First instalment date (optional for flexible 0% loans)', 'taxocommerce-accounting' ) . ' <input type="date" name="next_due_date"></label></p>';
        echo '<p><label>' . esc_html__( 'Loan funds', 'taxocommerce-accounting' ) . ' <select name="funding_mode">';
        echo '<option value="pending">' . esc_html__( 'Not received yet (do not change bank)', 'taxocommerce-accounting' ) . '</option>';
        echo '<option value="opening_balance">' . esc_html__( 'Already included in bank opening balance (do not duplicate)', 'taxocommerce-accounting' ) . '</option>';
        echo '<option value="new_receipt">' . esc_html__( 'New confirmed bank receipt', 'taxocommerce-accounting' ) . '</option>';
        echo '</select></label></p>';
        if ( $accounts ) {
            echo '<p><label>' . esc_html__( 'Bank receiving or holding loan funds', 'taxocommerce-accounting' ) . ' <select name="funding_account_id">';
            echo '<option value="0">' . esc_html__( 'Select bank', 'taxocommerce-accounting' ) . '</option>';
            foreach ( $accounts as $account ) {
                if ( 'bank' === $account->kind ) {
                    echo '<option value="' . esc_attr( (string) $account->id ) . '">' .
                        esc_html( $account->name . ' (' . tca_money( $account->opening_amount ) . ' ' . __( 'opening', 'taxocommerce-accounting' ) . ')' ) . '</option>';
                }
            }
            echo '</select></label></p>';
            echo '<p><label><input type="checkbox" name="opening_verified" value="1"> ' .
                esc_html__( 'I confirm that the entire opening balance belongs to this loan and is already in this bank. Do not add money again.', 'taxocommerce-accounting' ) .
                '</label></p>';
            echo '<p class="description">' .
                esc_html__( 'When linking an opening balance, the loan principal must equal that opening amount exactly. An opening balance can fund only one loan. No bank movement is created.', 'taxocommerce-accounting' ) .
                '</p>';
        }
        echo '</div></div>';
        echo '<p class="description">' . esc_html__(
            'Select opening balance when funds from the lender are already included in the bank opening balance: no second receipt is created. Select new receipt only when funds have newly arrived. This is a repayable loan, not a donation or VAT sale. Instalments remain forecasts until confirmed.',
            'taxocommerce-accounting'
        ) . '</p>';
        submit_button( __( 'Save loan', 'taxocommerce-accounting' ), 'secondary', 'submit', false );
        echo '</form>';
        TCA_Expense_CSV::render( $year );
    }
}
add_action( 'admin_post_tca_treasury_action', array( 'TCA_Treasury', 'handle' ) );
