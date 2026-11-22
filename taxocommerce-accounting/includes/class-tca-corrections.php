<?php
/**
 * Audited corrections of TaxoCommerce Accounting ledger entries.
 *
 * A correction never changes the original WooCommerce order or invoice PDF.
 * A changed entry returns to draft status for a fresh fiscal review.
 *
 * @package TaxoCommerce_Accounting
 */
defined( 'ABSPATH' ) || exit;

final class TCA_Corrections {

    public static function table() {
        global $wpdb;
        return $wpdb->prefix . 'taxocommerce_accounting_corrections';
    }

    public static function install_schema() {
        global $wpdb;
        $table = esc_sql( self::table() );
        $sql = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            entry_id bigint(20) unsigned NOT NULL,
            changed_at datetime NOT NULL,
            user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            reason text NOT NULL,
            before_data longtext NOT NULL,
            after_data longtext NOT NULL,
            PRIMARY KEY  (id),
            KEY entry_id (entry_id),
            KEY changed_at (changed_at)
        ) {$wpdb->get_charset_collate()};";
        dbDelta( $sql );
    }

    public static function tab_for( $kind ) {
        $tabs = array(
            'sale' => 'sales', 'refund' => 'sales',
            'purchase' => 'received', 'expense' => 'expenses',
            'asset' => 'assets', 'intra' => 'intra',
        );
        return isset( $tabs[ $kind ] ) ? $tabs[ $kind ] : 'summary';
    }

    /** Route historical fixed expenses to the assets screen without rewriting their ledger IDs. */
    public static function tab_for_entry( $entry ) {
        if ( 'expense' === $entry->kind && 'fixed_asset' === (string) ( $entry->expense_type ?? '' ) ) {
            return 'assets';
        }
        return self::tab_for( $entry->kind );
    }

    public static function snapshot( $entry ) {
        $result = array();
        foreach ( array( 'source', 'source_id', 'kind', 'fiscal_date', 'document_no',
            'party', 'tax_id', 'concept', 'expense_type', 'service_date', 'useful_life_years', 'amount_base', 'amount_tax',
            'amount_total', 'deductible_pct', 'currency', 'treatment',
            'status', 'notes' ) as $key ) {
            $value = isset( $entry->$key ) ? $entry->$key : '';
            $result[ $key ] = in_array( $key, array( 'amount_base', 'amount_tax', 'amount_total', 'deductible_pct' ), true )
                ? number_format( (float) $value, 2, '.', '' ) : (string) $value;
        }
        return $result;
    }

    /**
     * Validate allowed corrections, preserving document identity.
     * No user-supplied monetary total is trusted: base + VAT is calculated.
     *
     * @param object $entry Existing ledger row.
     * @param array  $input Sanitized user input.
     * @return array|WP_Error Allowed new values or error.
     */
    public static function prepare( $entry, $input ) {
        if ( ! in_array( $entry->kind, array( 'sale', 'refund', 'purchase', 'expense', 'asset', 'intra' ), true ) ||
            ! in_array( $entry->status, array( 'draft', 'validated' ), true ) ) {
            return new WP_Error( 'tca_correction_unsupported', __( 'This accounting record cannot be corrected.', 'taxocommerce-accounting' ) );
        }

        $date = isset( $input['fiscal_date'] ) ? (string) $input['fiscal_date'] : '';
        $base = tca_decimal( isset( $input['amount_base'] ) ? $input['amount_base'] : '' );
        $tax = tca_decimal( isset( $input['amount_tax'] ) ? $input['amount_tax'] : '' );
        $pct = tca_decimal( isset( $input['deductible_pct'] ) ? $input['deductible_pct'] : '' );
        $reason = isset( $input['reason'] ) ? trim( (string) $input['reason'] ) : '';
        $notes = isset( $input['notes'] ) ? (string) $input['notes'] : (string) $entry->notes;

        if ( ! tca_valid_date( $date ) || null === $base || null === $tax ||
            null === $pct || $pct < 0 || $pct > 100 || '' === $reason || strlen( $reason ) > 1000 || strlen( $notes ) > 5000 ) {
            return new WP_Error( 'tca_correction_invalid', __( 'Check the amounts, date, VAT deduction and correction reason.', 'taxocommerce-accounting' ) );
        }

        $refund = 'refund' === $entry->kind;
        if ( ( $refund && ( $base > 0 || $tax > 0 ) ) ||
            ( ! $refund && ( $base < 0 || $tax < 0 ) ) ) {
            return new WP_Error( 'tca_correction_sign', __( 'Amounts must match the sign of the sale or refund.', 'taxocommerce-accounting' ) );
        }

        $document = isset( $input['document_no'] ) ? trim( (string) $input['document_no'] ) : '';
        if ( 'woocommerce' === $entry->source && 'sale' === $entry->kind ) {
            // Read the invoice belonging to this order only, if Billing is installed.
            $issued = TCA_Issued_Invoice::for_order( $entry->source_id );
            if ( '' !== $issued ) {
                $document = $issued;
            }
        }
        if ( '' === $document || strlen( $document ) > 100 ) {
            return new WP_Error( 'tca_correction_document', __( 'A valid invoice or credit note reference is required.', 'taxocommerce-accounting' ) );
        }

        $party = isset( $input['party'] ) ? trim( (string) $input['party'] ) : '';
        $concept = isset( $input['concept'] ) ? trim( (string) $input['concept'] ) : '';
        $tax_id = isset( $input['tax_id'] ) ? trim( (string) $input['tax_id'] ) : '';
        if ( '' === $party || strlen( $party ) > 191 || strlen( $tax_id ) > 48 || strlen( $concept ) > 3000 ) {
            return new WP_Error( 'tca_correction_party', __( 'Check the supplier or customer and tax identification.', 'taxocommerce-accounting' ) );
        }

        // Preserve classification for all non-expense record kinds.
        $expense_type = 'asset' === $entry->kind || ( 'expense' === $entry->kind && 'fixed_asset' === (string) ( $entry->expense_type ?? '' ) ) ? 'fixed_asset' : 'current';
        $service_date = 'fixed_asset' === $expense_type ? (string) ( $input['service_date'] ?? '' ) : '';
        $years = 'fixed_asset' === $expense_type ? (string) ( $input['useful_life_years'] ?? '' ) : '0';
        if ( 'fixed_asset' === $expense_type && ! TCA_Depreciation::valid( $expense_type, $service_date, $years, $date ) ) {
            return new WP_Error( 'tca_correction_asset', __( 'Check the asset category, in-service date and useful life.', 'taxocommerce-accounting' ) );
        }
        // Keep the prior tax treatment until a separate fiscal validation.
        return array(
            'fiscal_date' => $date,
            'document_no' => $document,
            'party' => $party,
            'tax_id' => $tax_id,
            'concept' => $concept,
            'expense_type' => $expense_type,
            'service_date' => 'fixed_asset' === $expense_type ? $service_date : null,
            'useful_life_years' => 'fixed_asset' === $expense_type ? (int) $years : 0,
            'amount_base' => $base,
            'amount_tax' => $tax,
            'amount_total' => round( $base + $tax, 2 ),
            'deductible_pct' => in_array( $entry->kind, array( 'sale', 'refund' ), true ) ? 0 : $pct,
            'notes' => $notes,
            'status' => 'draft',
            'updated_at' => current_time( 'mysql' ),
        );
    }

    public static function handle() {
        if ( ! tca_capable() ) {
            wp_die( esc_html__( 'Insufficient permissions.', 'taxocommerce-accounting' ) );
        }
        $id = absint( tca_input( 'entry_id' ) );
        check_admin_referer( 'tca_correct_' . $id );
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Correction requires a fresh, locked ledger view.
        $entry = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d LIMIT 1', tca_table(), $id ) );
        if ( ! $entry ) {
            self::redirect( 'summary', 'correction_invalid' );
        }
        $tab = self::tab_for_entry( $entry );
        $input = array();
        foreach ( array( 'fiscal_date', 'document_no', 'party', 'tax_id', 'concept',
            'amount_base', 'amount_tax', 'deductible_pct', 'expense_type', 'service_date', 'useful_life_years', 'reason' ) as $key ) {
            $input[ $key ] = tca_input( $key );
        }

        $input['notes'] = isset( $_POST['notes'] ) && is_string( $_POST['notes'] )
            ? sanitize_textarea_field( wp_unslash( $_POST['notes'] ) ) : '';
        $new = self::prepare( $entry, $input );
        if ( is_wp_error( $new ) ) {
            self::redirect( $tab, 'correction_invalid' );
        }

        $reason = $input['reason'];
        $before = self::snapshot( $entry );
        $after = self::snapshot( (object) array_merge( (array) $entry, $new ) );
        if ( self::same_values( $before, $after ) ) {
            self::redirect( $tab, 'correction_unchanged' );
        }

        if ( ! self::save( $entry, $new, $reason ) ) {
            self::redirect( $tab, 'correction_failed' );
        }
        self::redirect( $tab, 'correction_saved' );
    }

    /**
     * Update the own ledger and append an audit event atomically.
     * Never write to WooCommerce or Billing.
     *
     * @param object $entry Previous row.
     * @param array $new Allowed values returned by prepare().
     * @param string $reason Non-empty reason.
     * @return bool True only if ledger, log and commit succeeded.
     */
    public static function save( $entry, $new, $reason ) {
        global $wpdb;
        if ( '' === trim( (string) $reason ) ) {
            return false;
        }
        $id = absint( $entry->id );
        $before = self::snapshot( $entry );
        $after = self::snapshot( (object) array_merge( (array) $entry, $new ) );

        // Both changes must succeed, or neither may persist on transactional WP tables.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Ledger correction transaction.
        if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
            return false;
        }
        // Lock the same entry as settlements and retain all earlier payments.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Same transaction lock as payment registration.
        $locked = $wpdb->get_row( $wpdb->prepare( 'SELECT id FROM %i WHERE id = %d FOR UPDATE', tca_table(), $id ) );
        if ( ! $locked || TCA_Settlements::sum( $id ) > abs( (float) $after['amount_total'] ) ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Reject a correction that would overpay the invoice.
            $wpdb->query( 'ROLLBACK' );
            return false;
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Conditional update of own ledger.
        $updated = $wpdb->update(
            tca_table(),
            $new,
            array( 'id' => $id, 'status' => $entry->status, 'updated_at' => $entry->updated_at )
        );
        if ( 1 !== $updated ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Roll back when record changed concurrently.
            $wpdb->query( 'ROLLBACK' );
            return false;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Immutable correction log in own database.
        $logged = $wpdb->insert(
            self::table(),
            array(
                'entry_id' => $id,
                'changed_at' => current_time( 'mysql' ),
                'user_id' => get_current_user_id(),
                'reason' => $reason,
                'before_data' => wp_json_encode( $before ),
                'after_data' => wp_json_encode( $after ),
            )
        );
        if ( ! $logged ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Keep original if audit cannot be written.
            $wpdb->query( 'ROLLBACK' );
            return false;
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Commit corrected draft and audit together.
        if ( false === $wpdb->query( 'COMMIT' ) ) {
            return false;
        }
        return true;
    }

    private static function same_values( $before, $after ) {
        // Status is a meaningful fiscal change: validated -> draft must be audited,
        // even if invoice amounts and other values have not changed.
        unset( $before['updated_at'], $after['updated_at'] );
        return wp_json_encode( $before ) === wp_json_encode( $after );
    }

    private static function redirect( $tab, $notice ) {
        wp_safe_redirect( tca_admin_url( $tab, array( 'notice' => $notice ) ) );
        exit;
    }

    public static function render_row_action( $entry ) {
        $tab = self::tab_for_entry( $entry );
        if ( 'summary' === $tab ) {
            return;
        }
        $year = substr( $entry->fiscal_date, 0, 4 );
        $url = tca_admin_url( $tab, array( 'year' => $year, 'edit_entry' => absint( $entry->id ) ) );
        echo ' <a class="button button-small" href="' . esc_url( $url ) . '">' .
            esc_html__( 'Correct entry', 'taxocommerce-accounting' ) . '</a>';
        $history = tca_admin_url( $tab, array( 'year' => $year, 'history_entry' => absint( $entry->id ) ) );
        echo ' <a href="' . esc_url( $history ) . '">' . esc_html__( 'History', 'taxocommerce-accounting' ) . '</a>';
    }

    public static function render_editor( $tab ) {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display lookup; writes use a per-entry nonce.
        $edit_id = isset( $_GET['edit_entry'] ) ? absint( $_GET['edit_entry'] ) : 0;
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only audit-history display.
        $history_id = isset( $_GET['history_entry'] ) ? absint( $_GET['history_entry'] ) : 0;
        $id = $edit_id ? $edit_id : $history_id;
        if ( ! $id ) {
            return;
        }
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Read fresh record for authorized admin.
        $entry = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d LIMIT 1', tca_table(), $id ) );
        if ( ! $entry || $tab !== self::tab_for_entry( $entry ) ) {
            return;
        }
        if ( $history_id ) {
            self::render_history( $id );
            return;
        }

        echo '<h3 id="tca-edit-entry-' . esc_attr( (string) $id ) . '">' . esc_html__( 'Correct accounting entry', 'taxocommerce-accounting' ) . ' #' . esc_html( $id ) . '</h3>';
        echo '<p class="notice notice-warning inline">' .
            esc_html__( 'This changes only the accounting record, not the WooCommerce order, issued invoice, or supplier PDF. If an issued invoice is wrong, follow the required rectifying-invoice procedure before fiscal approval.', 'taxocommerce-accounting' ) . '</p>';
        echo '<p>' . esc_html__( 'Saving a correction moves this record back to draft and removes it from validated tax totals until it is reviewed and approved again.', 'taxocommerce-accounting' ) . '</p>';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        echo '<input type="hidden" name="action" value="tca_correct_entry"><input type="hidden" name="entry_id" value="' . esc_attr( $id ) . '">';
        wp_nonce_field( 'tca_correct_' . $id );
        echo '<table class="form-table" role="presentation">';
        foreach ( array(
            'fiscal_date' => array( __( 'Fiscal date', 'taxocommerce-accounting' ), 'date' ),
            'document_no' => array( __( 'Invoice reference', 'taxocommerce-accounting' ), 'text' ),
            'party' => array( __( 'Customer or supplier', 'taxocommerce-accounting' ), 'text' ),
            'tax_id' => array( __( 'Tax identification', 'taxocommerce-accounting' ), 'text' ),
            'concept' => array( __( 'Concept', 'taxocommerce-accounting' ), 'text' ),
            'amount_base' => array( __( 'Taxable base', 'taxocommerce-accounting' ), 'number' ),
            'amount_tax' => array( __( 'VAT amount', 'taxocommerce-accounting' ), 'number' ),
        ) as $key => $config ) {
            echo '<tr><th scope="row"><label for="tca-correct-' . esc_attr( $key ) . '">' . esc_html( $config[0] ) . '</label></th><td>';
            echo '<input class="regular-text" id="tca-correct-' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" type="' . esc_attr( $config[1] ) . '" value="' . esc_attr( $entry->$key ) . '"';
            if ( 'number' === $config[1] ) {
                echo ' step="0.01"';
            }
            if ( ! in_array( $key, array( 'tax_id', 'concept' ), true ) ) {
                echo ' required';
            }
            if ( 'woocommerce' === $entry->source && 'document_no' === $key && '' !== TCA_Issued_Invoice::for_order( $entry->source_id ) ) {
                echo ' readonly';
            }
            echo '></td></tr>';
        }
        if ( 'asset' === $entry->kind || ( 'expense' === $entry->kind && 'fixed_asset' === (string) ( $entry->expense_type ?? '' ) ) ) {
            echo '<tr><th><label for="tca-service-date">' . esc_html__( 'Date put into service', 'taxocommerce-accounting' ) . '</label></th><td>';
            echo '<input type="date" id="tca-service-date" name="service_date" value="' . esc_attr( $entry->service_date ?: $entry->fiscal_date ) . '" required></td></tr>';
            echo '<tr><th><label for="tca-useful-life">' . esc_html__( 'Useful life (years)', 'taxocommerce-accounting' ) . '</label></th><td>';
            echo '<input type="number" min="1" max="50" step="1" id="tca-useful-life" name="useful_life_years" value="' . esc_attr( (string) ( (int) $entry->useful_life_years ?: 5 ) ) . '" required></td></tr>';
        }
        echo '<tr><th><label for="tca-correct-pct">' . esc_html__( 'Deductible VAT percentage', 'taxocommerce-accounting' ) . '</label></th>';
        echo '<td><input id="tca-correct-pct" name="deductible_pct" type="number" min="0" max="100" step="0.01" value="' . esc_attr( $entry->deductible_pct ) . '" required></td></tr>';
        echo '<tr><th><label for="tca-correct-notes">' . esc_html__( 'Notes', 'taxocommerce-accounting' ) . '</label></th>';
        echo '<td><textarea id="tca-correct-notes" name="notes" maxlength="5000" rows="4" class="large-text">' . esc_textarea( $entry->notes ) . '</textarea></td></tr>';
        echo '<tr><th><label for="tca-correct-reason">' . esc_html__( 'Correction reason', 'taxocommerce-accounting' ) . '</label></th>';
        echo '<td><textarea id="tca-correct-reason" name="reason" maxlength="1000" rows="3" class="large-text" required></textarea></td></tr>';
        echo '</table>';
        submit_button( __( 'Save correction as draft', 'taxocommerce-accounting' ), 'primary', 'submit', false );
        echo ' <a href="' . esc_url( tca_admin_url( $tab ) ) . '">' . esc_html__( 'Cancel', 'taxocommerce-accounting' ) . '</a>';
        echo '</form>';
    }

    private static function render_history( $id ) {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Authorized audit view, bounded to latest changes.
        $logs = (array) $wpdb->get_results( $wpdb->prepare(
            'SELECT changed_at, user_id, reason, before_data, after_data FROM %i WHERE entry_id = %d ORDER BY id DESC LIMIT 50',
            self::table(), $id
        ) );
        echo '<h3>' . esc_html__( 'Accounting correction history (latest 50)', 'taxocommerce-accounting' ) . ' #' . esc_html( $id ) . '</h3>';
        if ( ! $logs ) {
            echo '<p>' . esc_html__( 'No corrections recorded.', 'taxocommerce-accounting' ) . '</p>';
            return;
        }
        echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Date', 'taxocommerce-accounting' ) . '</th><th>' .
            esc_html__( 'Administrator ID', 'taxocommerce-accounting' ) . '</th><th>' .
            esc_html__( 'Reason', 'taxocommerce-accounting' ) . '</th><th>' .
            esc_html__( 'Before: date / invoice / base / VAT / total', 'taxocommerce-accounting' ) . '</th><th>' .
            esc_html__( 'After: date / invoice / base / VAT / total', 'taxocommerce-accounting' ) . '</th></tr></thead><tbody>';
        foreach ( $logs as $log ) {
            $before = json_decode( $log->before_data, true );
            $after = json_decode( $log->after_data, true );
            echo '<tr><td>' . esc_html( $log->changed_at ) . '</td><td>' . esc_html( (string) $log->user_id ) . '</td><td>' .
                esc_html( $log->reason ) . '</td><td>' . esc_html( self::format_snapshot( $before ) ) . '</td><td>' .
                esc_html( self::format_snapshot( $after ) ) . '</td></tr>';
        }
        echo '</tbody></table>';
    }

    private static function format_snapshot( $data ) {
        if ( ! is_array( $data ) ) {
            return '';
        }
        return implode( ' / ', array(
            isset( $data['fiscal_date'] ) ? (string) $data['fiscal_date'] : '',
            isset( $data['document_no'] ) ? (string) $data['document_no'] : '',
            isset( $data['amount_base'] ) ? (string) $data['amount_base'] : '',
            isset( $data['amount_tax'] ) ? (string) $data['amount_tax'] : '',
            isset( $data['amount_total'] ) ? (string) $data['amount_total'] : '',
        ) );
    }
}
add_action( 'admin_post_tca_correct_entry', array( 'TCA_Corrections', 'handle' ) );
