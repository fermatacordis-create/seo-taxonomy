<?php
/**
 * Isolated regression of protected invoice bytes and upload/download contracts.
 * No WordPress bootstrap, no external provider, no DB writes.
 */
define( 'ABSPATH', __DIR__ . '/' );
class WP_Error {
    private $code;
    public function __construct( $code, $message = '' ) { $this->code = $code; }
    public function get_error_code() { return $this->code; }
}
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function __( $value, $domain = '' ) { return $value; }
function wp_salt( $scheme ) { return 'unit-test-wordpress-' . $scheme; }
function add_action( $name, $cb ) { $GLOBALS['action_names'][] = $name; }
function tca_invoice_assert( $ok, $label ) {
    if ( ! $ok ) {
        fwrite( STDERR, "FAIL: {$label}\n" );
        exit( 1 );
    }
}
require_once dirname( __DIR__ ) . '/includes/class-tca-provider-invoices.php';

$pdf = "%PDF-1.7\n1 0 obj << /Type /Catalog >> endobj\n%%EOF\n";
tca_invoice_assert( TCA_Provider_Invoices::is_pdf( $pdf ), 'Accept genuine PDF signature' );
tca_invoice_assert( ! TCA_Provider_Invoices::is_pdf( "<script>php</script>" ), 'Reject non-PDF' );
tca_invoice_assert( ! TCA_Provider_Invoices::is_pdf( " %PDF-1.7" ), 'Reject disguised PDF header' );
tca_invoice_assert( TCA_Provider_Invoices::MAX_BYTES === 10485760, 'Enforce 10 MB limit' );
$encrypted = TCA_Provider_Invoices::encrypt( $pdf );
tca_invoice_assert( ! is_wp_error( $encrypted ), 'Encrypt works with OpenSSL' );
tca_invoice_assert( false === strpos( $encrypted, $pdf ), 'Never store plaintext' );
tca_invoice_assert( $pdf === TCA_Provider_Invoices::decrypt( $encrypted ), 'Original PDF can be restored exactly' );
$modified = $encrypted;
$modified[ strlen( $modified ) - 1 ] = chr( ord( $modified[ strlen( $modified ) - 1 ] ) ^ 1 );
tca_invoice_assert( is_wp_error( TCA_Provider_Invoices::decrypt( $modified ) ), 'Reject tampered ciphertext' );
tca_invoice_assert( is_wp_error( TCA_Provider_Invoices::decrypt( '%PDF-' ) ), 'Reject unencrypted payload' );
tca_invoice_assert( in_array( 'admin_post_tca_upload_provider_invoice', $GLOBALS['action_names'], true ), 'Upload protected by admin-post' );
tca_invoice_assert( in_array( 'admin_post_tca_download_provider_invoice', $GLOBALS['action_names'], true ), 'Download protected by admin-post' );
$main = file_get_contents( dirname( __DIR__ ) . '/taxocommerce-accounting.php' );
$addon = file_get_contents( dirname( __DIR__ ) . '/includes/class-tca-provider-invoices.php' );
tca_invoice_assert( false !== strpos( $main, "TCA_Provider_Invoices::install_schema()" ), 'Own table upgraded without replacing ledger' );
tca_invoice_assert( false !== strpos( $main, "TCA_Provider_Invoices::render_row_controls( \$row, \$documents )" ), 'Attachments visible in existing purchase rows' );
tca_invoice_assert( false !== strpos( $addon, "current_user_can( 'manage_woocommerce' )" ), 'Admin-only access' );
tca_invoice_assert( false !== strpos( $addon, "check_admin_referer( 'tca_invoice_upload_' . \$id )" ), 'Upload nonce' );
tca_invoice_assert( false !== strpos( $addon, "check_admin_referer( 'tca_invoice_download_' . \$id )" ), 'Download nonce' );
tca_invoice_assert( false !== strpos( $addon, "is_uploaded_file( \$file['tmp_name'] )" ), 'Only real PHP uploads' );
tca_invoice_assert( false !== strpos( $addon, "wp_check_filetype_and_ext(" ), 'Core MIME validation' );
tca_invoice_assert( false !== strpos( $addon, "'source' !== \$row->source" ) || false !== strpos( $addon, "'manual' !== \$row->source" ), 'No attachments to sale orders' );
tca_invoice_assert( false === strpos( $addon, "wp_insert_attachment(" ), 'Do not publish invoice through WP Media Library' );
tca_invoice_assert( false === strpos( $addon, "wp_get_attachment_url(" ), 'No public file URLs' );
tca_invoice_assert( false !== strpos( $addon, "hash_equals(" ), 'Check original content fingerprint on download' );
echo "Accounting supplier PDF: crypto, upload/download hooks, access, size, and ledger contracts PASS\n";
