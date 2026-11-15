<?php
/**
 * Protected supplier invoice PDFs associated with manually entered purchases.
 *
 * The uploaded PDF is encrypted before it is stored in the uploads directory.
 * Only an authorised WordPress administrator can retrieve the original through
 * admin-post.php; no public media attachment or plaintext URL is created.
 *
 * @package TaxoCommerceAccounting
 */

defined( 'ABSPATH' ) || exit;

/**
 * Supplier invoice archive, independent of TaxoCommerce Suppliers and Billing.
 */
final class TCA_Provider_Invoices {
    const MAX_BYTES = 10485760; // 10 MiB per PDF.
    const MAGIC = 'TCAPDF1';

    /**
     * Documents belong to the accounting ledger, never to WooCommerce orders.
     *
     * @return string
     */
    public static function table() {
        global $wpdb;
        return $wpdb->prefix . 'taxocommerce_accounting_documents';
    }

    /**
     * Upgrade only the plugin-owned document table.
     *
     * @return void
     */
    public static function install_schema() {
        global $wpdb;
        $table = esc_sql( self::table() );
        $sql = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            entry_id bigint(20) unsigned NOT NULL,
            stored_file varchar(90) NOT NULL,
            original_name varchar(191) NOT NULL,
            sha256 char(64) NOT NULL,
            size_bytes bigint(20) unsigned NOT NULL,
            uploaded_at datetime NOT NULL,
            uploaded_by bigint(20) unsigned NOT NULL,
            PRIMARY KEY  (id),
            KEY entry_id (entry_id)
        ) {$wpdb->get_charset_collate()};";
        dbDelta( $sql );
    }

    /**
     * The storage directory contains ciphertext only. Never use direct URLs.
     *
     * @param bool $create Whether to create it for an upload.
     * @return string|WP_Error
     */
    private static function directory( $create = false ) {
        $uploads = wp_upload_dir();
        if ( ! empty( $uploads['error'] ) || empty( $uploads['basedir'] ) ) {
            return new WP_Error( 'tca_invoice_storage', __( 'Invoice storage is unavailable.', 'taxocommerce-accounting' ) );
        }
        $base = trailingslashit( (string) $uploads['basedir'] );
        $parent = $base . 'taxocommerce-accounting';
        $directory = trailingslashit( $parent ) . 'encrypted-invoices';
        if ( is_link( $parent ) || is_link( $directory ) ) {
            return new WP_Error( 'tca_invoice_storage', __( 'Invoice storage is unavailable.', 'taxocommerce-accounting' ) );
        }
        if ( $create && ! is_dir( $directory ) ) {
            if ( ! wp_mkdir_p( $directory ) ) {
                return new WP_Error( 'tca_invoice_storage', __( 'Invoice storage is unavailable.', 'taxocommerce-accounting' ) );
            }
            // Extra defence for Apache/IIS. On other servers, files remain encrypted.
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Access-denial files in the plugin-owned uploads directory.
            file_put_contents( $parent . '/index.php', '<?php // Silence is golden.' );
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Defence in depth, no public originals.
            file_put_contents( $directory . '/.htaccess', "Require all denied\nDeny from all\n" );
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Defence in depth for IIS.
            file_put_contents( $directory . '/web.config', '<?xml version="1.0"?><configuration><system.webServer><security><requestFiltering><hiddenSegments><add segment="encrypted-invoices"/></hiddenSegments></requestFiltering></security></system.webServer></configuration>' );
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Access-denial index for directory browsing.
            file_put_contents( $directory . '/index.php', '<?php // Silence is golden.' );
        }
        if ( ! is_dir( $directory ) || ! is_readable( $directory ) || ( $create && ! wp_is_writable( $directory ) ) ) {
            return new WP_Error( 'tca_invoice_storage', __( 'Invoice storage is unavailable.', 'taxocommerce-accounting' ) );
        }
        return $directory;
    }

    /**
     * Derive a site-specific AES key without persisting a second secret.
     * Backups must preserve WordPress salts or archived documents become unreadable.
     *
     * @return string
     */
    private static function key() {
        return hash( 'sha256', wp_salt( 'auth' ) . 'taxocommerce-accounting-invoice-v1' . wp_salt( 'secure_auth' ), true );
    }

    /**
     * PDF signature is checked independently of the untrusted browser MIME.
     *
     * @param string $bytes Candidate file contents.
     * @return bool
     */
    public static function is_pdf( $bytes ) {
        return is_string( $bytes ) && strlen( $bytes ) >= 5 && '%PDF-' === substr( $bytes, 0, 5 );
    }

    /**
     * Encrypt one whole bounded PDF using authenticated AES-256-GCM.
     *
     * @param string $bytes Original file content.
     * @return string|WP_Error
     */
    public static function encrypt( $bytes ) {
        if ( ! function_exists( 'openssl_encrypt' ) || ! function_exists( 'random_bytes' ) ) {
            return new WP_Error( 'tca_invoice_crypto', __( 'Secure invoice encryption is unavailable.', 'taxocommerce-accounting' ) );
        }
        $iv = random_bytes( 12 );
        $tag = '';
        $ciphertext = openssl_encrypt( $bytes, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag );
        if ( false === $ciphertext || 16 !== strlen( $tag ) ) {
            return new WP_Error( 'tca_invoice_crypto', __( 'Secure invoice encryption is unavailable.', 'taxocommerce-accounting' ) );
        }
        return self::MAGIC . $iv . $tag . $ciphertext;
    }

    /**
     * Reject tampered, truncated or wrongly-keyed stored files.
     *
     * @param string $stored Encrypted file contents.
     * @return string|WP_Error
     */
    public static function decrypt( $stored ) {
        if ( ! function_exists( 'openssl_decrypt' ) || strlen( $stored ) < 36 || self::MAGIC !== substr( $stored, 0, 7 ) ) {
            return new WP_Error( 'tca_invoice_corrupt', __( 'Invoice cannot be decrypted or has been modified.', 'taxocommerce-accounting' ) );
        }
        $plaintext = openssl_decrypt( substr( $stored, 35 ), 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, substr( $stored, 7, 12 ), substr( $stored, 19, 16 ) );
        if ( false === $plaintext || ! self::is_pdf( $plaintext ) ) {
            return new WP_Error( 'tca_invoice_corrupt', __( 'Invoice cannot be decrypted or has been modified.', 'taxocommerce-accounting' ) );
        }
        return $plaintext;
    }

    /**
     * Only manually entered purchase-side entries can receive supplier files.
     *
     * @param int $entry_id Ledger row ID.
     * @return object|null
     */
    private static function purchase_entry( $entry_id ) {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Authorisation must check the current private ledger row.
        $row = $wpdb->get_row( $wpdb->prepare(
            'SELECT id, source, kind, fiscal_date FROM %i WHERE id = %d',
            tca_table(), absint( $entry_id )
        ) );
        if ( ! $row || 'manual' !== $row->source || ! in_array( $row->kind, array( 'purchase', 'expense', 'asset', 'intra' ), true ) ) {
            return null;
        }
        return $row;
    }

    /**
     * Upload is an explicit action against a saved purchase, not a fiscal edit.
     *
     * @return void
     */
    public static function upload() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( esc_html__( 'Insufficient permissions.', 'taxocommerce-accounting' ) );
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is checked below before file access.
        $id = isset( $_POST['entry_id'] ) ? absint( wp_unslash( $_POST['entry_id'] ) ) : 0;
        check_admin_referer( 'tca_invoice_upload_' . $id );
        $entry = self::purchase_entry( $id );
        if ( ! $entry ) {
            wp_die( esc_html__( 'Purchase entry not found.', 'taxocommerce-accounting' ) );
        }
        $tab = array( 'purchase' => 'received', 'expense' => 'expenses', 'asset' => 'assets', 'intra' => 'intra' )[ $entry->kind ];
        $year = (int) substr( $entry->fiscal_date, 0, 4 );
        // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Validated upload metadata (name, size, type, magic signature and is_uploaded_file); raw temporary upload paths must not be sanitized.
        $file = isset( $_FILES['tca_invoice_pdf'] ) && is_array( $_FILES['tca_invoice_pdf'] ) ? $_FILES['tca_invoice_pdf'] : array();
        $result = self::save_pdf( $id, $file );
        $notice = is_wp_error( $result ) ? $result->get_error_code() : 'invoice_uploaded';
        wp_safe_redirect( tca_admin_url( $tab, array( 'year' => $year, 'notice' => $notice ) ) );
        exit;
    }

    /**
     * Validate and archive an uploaded PDF. No OCR or automatic VAT classification.
     *
     * @param int   $entry_id Existing supplier purchase row.
     * @param array $file     PHP upload array.
     * @return int|WP_Error
     */
    public static function save_pdf( $entry_id, $file ) {
        global $wpdb;
        if ( ! self::purchase_entry( $entry_id ) ) {
            return new WP_Error( 'invoice_not_found', __( 'Purchase entry not found.', 'taxocommerce-accounting' ) );
        }
        if ( ! isset( $file['error'], $file['name'], $file['tmp_name'], $file['size'] )
            || UPLOAD_ERR_OK !== (int) $file['error']
            || ! is_string( $file['name'] ) || ! is_string( $file['tmp_name'] )
            || ! is_numeric( $file['size'] ) || (int) $file['size'] < 5
            || (int) $file['size'] > self::MAX_BYTES ) {
            return new WP_Error( 'invoice_invalid_upload', __( 'Select a PDF smaller than 10 MB.', 'taxocommerce-accounting' ) );
        }
        $original = sanitize_file_name( wp_basename( $file['name'] ) );
        if ( 'pdf' !== strtolower( (string) pathinfo( $original, PATHINFO_EXTENSION ) )
            || ! is_uploaded_file( $file['tmp_name'] ) ) {
            return new WP_Error( 'invoice_invalid_upload', __( 'Select a valid PDF invoice.', 'taxocommerce-accounting' ) );
        }
        $checked = wp_check_filetype_and_ext( $file['tmp_name'], $original, array( 'pdf' => 'application/pdf' ) );
        if ( 'application/pdf' !== (string) ( $checked['type'] ?? '' ) ) {
            return new WP_Error( 'invoice_invalid_upload', __( 'Select a valid PDF invoice.', 'taxocommerce-accounting' ) );
        }
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- PHP-authenticated temporary upload, bounded at 10 MiB.
        $bytes = file_get_contents( $file['tmp_name'], false, null, 0, self::MAX_BYTES + 1 );
        if ( false === $bytes || strlen( $bytes ) !== (int) $file['size'] || ! self::is_pdf( $bytes ) ) {
            return new WP_Error( 'invoice_invalid_upload', __( 'Select a valid PDF invoice.', 'taxocommerce-accounting' ) );
        }
        $directory = self::directory( true );
        if ( is_wp_error( $directory ) ) {
            return $directory;
        }
        $encrypted = self::encrypt( $bytes );
        if ( is_wp_error( $encrypted ) ) {
            return $encrypted;
        }
        $stored_name = wp_generate_uuid4() . '.bin';
        $path = trailingslashit( $directory ) . $stored_name;
        // Exclusive creation avoids overwriting previously archived evidence.
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Exclusive binary write of authenticated encrypted archival content.
        $stream = fopen( $path, 'xb' );
        if ( false === $stream ) {
            return new WP_Error( 'invoice_storage', __( 'Invoice storage is unavailable.', 'taxocommerce-accounting' ) );
        }
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- Write ciphertext only; no original invoice is stored publicly.
        $written = fwrite( $stream, $encrypted );
        fclose( $stream ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Release exclusive archive handle.
        if ( false === $written || strlen( $encrypted ) !== $written ) {
            wp_delete_file( $path );
            return new WP_Error( 'invoice_storage', __( 'Invoice storage is unavailable.', 'taxocommerce-accounting' ) );
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Own invoice-document table.
        $saved = $wpdb->insert( self::table(), array(
            'entry_id'      => absint( $entry_id ),
            'stored_file'   => $stored_name,
            'original_name' => $original,
            'sha256'        => hash( 'sha256', $bytes ),
            'size_bytes'    => strlen( $bytes ),
            'uploaded_at'   => current_time( 'mysql' ),
            'uploaded_by'   => get_current_user_id(),
        ) );
        if ( ! $saved ) {
            wp_delete_file( $path );
            return new WP_Error( 'invoice_storage', __( 'Invoice storage is unavailable.', 'taxocommerce-accounting' ) );
        }
        return (int) $wpdb->insert_id;
    }

    /**
     * Fetch attachments in one query, keyed by private ledger entry ID.
     *
     * @param array $rows Current-year ledger entries.
     * @return array
     */
    public static function documents_for_rows( $rows ) {
        global $wpdb;
        $ids = array_values( array_unique( array_filter( array_map( 'absint', wp_list_pluck( $rows, 'id' ) ) ) ) );
        if ( ! $ids ) {
            return array();
        }
        $placeholders = implode( ', ', array_fill( 0, count( $ids ), '%d' ) );
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Placeholder string is generated internally from integer IDs.
        $sql = "SELECT id, entry_id, original_name, size_bytes, uploaded_at FROM %i WHERE entry_id IN ({$placeholders}) ORDER BY id ASC";
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- Query contains only placeholders generated from integer IDs and is fully prepared by wpdb.
        $files = $wpdb->get_results( $wpdb->prepare( $sql, array_merge( array( self::table() ), $ids ) ) );
        $result = array();
        foreach ( (array) $files as $file ) {
            $result[ (int) $file->entry_id ][] = $file;
        }
        return $result;
    }

    /**
     * Add invoice actions without modifying fiscal amounts or existing forms.
     *
     * @param object $row Ledger entry.
     * @param array  $documents Documents keyed by ledger ID.
     * @return void
     */
    public static function render_row_controls( $row, $documents ) {
        if ( 'manual' !== $row->source || ! in_array( $row->kind, array( 'purchase', 'expense', 'asset', 'intra' ), true ) ) {
            return;
        }
        $files = isset( $documents[ (int) $row->id ] ) ? $documents[ (int) $row->id ] : array();
        echo '<div class="tca-invoice-documents"><strong>' . esc_html__( 'Supplier invoice PDF', 'taxocommerce-accounting' ) . ':</strong> ';
        if ( ! $files ) {
            echo esc_html__( 'Document pending', 'taxocommerce-accounting' );
        }
        foreach ( $files as $file ) {
            $url = wp_nonce_url(
                add_query_arg( array( 'action' => 'tca_download_provider_invoice', 'document_id' => (int) $file->id ), admin_url( 'admin-post.php' ) ),
                'tca_invoice_download_' . (int) $file->id
            );
            echo '<p><a href="' . esc_url( $url ) . '">' . esc_html( $file->original_name ) . '</a></p>';
        }
        echo '<form method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        echo '<input type="hidden" name="action" value="tca_upload_provider_invoice">';
        echo '<input type="hidden" name="entry_id" value="' . esc_attr( (string) $row->id ) . '">';
        wp_nonce_field( 'tca_invoice_upload_' . (int) $row->id );
        echo '<label for="tca-provider-invoice-' . esc_attr( (string) $row->id ) . '">' . esc_html__( 'Attach supplier PDF (up to 10 MB)', 'taxocommerce-accounting' ) . '</label>';
        echo ' <input id="tca-provider-invoice-' . esc_attr( (string) $row->id ) . '" type="file" name="tca_invoice_pdf" accept="application/pdf,.pdf" required>';
        echo ' <button class="button button-small" type="submit">' . esc_html__( 'Upload PDF', 'taxocommerce-accounting' ) . '</button></form></div>';
    }

    /**
     * Download requires a fresh nonce, permission and record-to-file linkage.
     *
     * @return void
     */
    public static function download() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( esc_html__( 'Insufficient permissions.', 'taxocommerce-accounting' ) );
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Checked immediately below.
        $id = isset( $_GET['document_id'] ) ? absint( wp_unslash( $_GET['document_id'] ) ) : 0;
        check_admin_referer( 'tca_invoice_download_' . $id );
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Authorised document-to-ledger lookup.
        $file = $wpdb->get_row( $wpdb->prepare(
            'SELECT d.* FROM %i d INNER JOIN %i e ON d.entry_id = e.id WHERE d.id = %d AND e.source = %s AND e.kind IN (%s, %s, %s, %s)',
            self::table(), tca_table(), $id, 'manual', 'purchase', 'expense', 'asset', 'intra'
        ) );
        if ( ! $file || ! preg_match( '/^[a-f0-9-]{36}\.bin$/', (string) $file->stored_file ) ) {
            wp_die( esc_html__( 'Invoice document not found.', 'taxocommerce-accounting' ) );
        }
        $directory = self::directory();
        if ( is_wp_error( $directory ) ) {
            wp_die( esc_html( $directory->get_error_message() ) );
        }
        $path = trailingslashit( $directory ) . $file->stored_file;
        if ( is_link( $path ) || ! is_file( $path ) || ! is_readable( $path )
            || realpath( dirname( $path ) ) !== realpath( $directory ) ) {
            wp_die( esc_html__( 'Invoice document not found.', 'taxocommerce-accounting' ) );
        }
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Authenticated encrypted document read; bounded at 10 MiB plus envelope.
        $stored = file_get_contents( $path, false, null, 0, self::MAX_BYTES + 64 );
        if ( false === $stored || strlen( $stored ) > self::MAX_BYTES + 35 ) {
            wp_die( esc_html__( 'Invoice document not found.', 'taxocommerce-accounting' ) );
        }
        $pdf = self::decrypt( $stored );
        if ( is_wp_error( $pdf ) || strlen( $pdf ) !== (int) $file->size_bytes
            || ! hash_equals( (string) $file->sha256, hash( 'sha256', $pdf ) ) ) {
            wp_die( esc_html__( 'Invoice cannot be decrypted or has been modified.', 'taxocommerce-accounting' ) );
        }
        $filename = sanitize_file_name( wp_basename( $file->original_name ) );
        nocache_headers();
        header( 'Content-Type: application/pdf' );
        header( 'X-Content-Type-Options: nosniff' );
        header( 'Content-Disposition: attachment; filename="' . str_replace( '"', '', $filename ) . '"' );
        header( 'Content-Length: ' . strlen( $pdf ) );
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Authenticated binary download, never HTML output.
        echo $pdf;
        exit;
    }
}
add_action( 'admin_post_tca_upload_provider_invoice', array( 'TCA_Provider_Invoices', 'upload' ) );
add_action( 'admin_post_tca_download_provider_invoice', array( 'TCA_Provider_Invoices', 'download' ) );
