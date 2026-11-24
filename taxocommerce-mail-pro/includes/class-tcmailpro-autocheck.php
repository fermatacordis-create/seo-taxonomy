<?php
/**
 * Read-only functionality and code checks. Does not connect to mail
 * servers or change messages unless administrator separately selects Test IMAP.
 *
 * @package TaxoCommerceMailPro
 */
defined( 'ABSPATH' ) || exit;
final class TCMAILPRO_Autocheck {
    public static function boot() {
        add_action( 'admin_post_tcmailpro_autocheck', array( __CLASS__, 'run' ) );
        add_action( 'admin_post_tcmailpro_autocheck_export', array( __CLASS__, 'export' ) );
    }

    private static function report( $mode ) {
        $rows = array();
        $add = static function ( $name, $ok, $detail ) use ( &$rows ) {
            $rows[] = array( 'check' => $name, 'status' => $ok ? 'ok' : 'error', 'details' => $detail );
        };
        if ( 'code' === $mode ) {
            $iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( TCMAILPRO_PATH, FilesystemIterator::SKIP_DOTS ) );
            foreach ( $iterator as $file ) {
                if ( ! $file->isFile() || $file->isLink() || 'php' !== strtolower( $file->getExtension() ) ) {
                    continue;
                }
                $path = $file->getRealPath();
                if ( ! $path || 0 !== strpos( $path, realpath( TCMAILPRO_PATH ) . DIRECTORY_SEPARATOR ) || $file->getSize() > 524288 ) {
                    $add( 'file_scope', false, 'Unexpected plugin file.' );
                    continue;
                }
                try {
                    // Token parsing is read-only, no PHP source is evaluated or executed.
                    token_get_all( file_get_contents( $path ), TOKEN_PARSE );
                    $add( 'php:' . basename( $path ), true, 'PHP token syntax parsed.' );
                } catch ( ParseError $parse_error ) {
                    $add( 'php:' . basename( $path ), false, 'PHP parsing failed.' );
                }
            }
            $rows[] = array(
                'check' => 'official_plugin_check',
                'status' => 'warning',
                'details' => 'This is an internal static syntax scan, not an official WordPress Plugin Check or security audit.',
            );
        } else {
            $add( 'encrypted_credentials', TCMAILPRO_Accounts::ready(), 'AES-256-GCM available for account secrets.' );
            $add( 'imap_transport', TCMAILPRO_IMAP::available(), 'PHP OpenSSL and secure socket functions available.' );
            $add( 'account_configuration', count( TCMAILPRO_Accounts::public_list() ) > 0, 'At least one mailbox is configured.' );
            $add( 'wp_mail_transport_isolated', class_exists( 'TCMAILPRO_SMTP' ), 'Plugin SMTP uses a new PHPMailer instance; does not modify wp_mail().' );
            $rows[] = array(
                'check' => 'live_imap_and_smtp',
                'status' => 'warning',
                'details' => 'No remote connections or emails were sent by Autocheck. Use Test IMAP per mailbox and send one controlled SMTP email.',
            );
        }
        $totals = array( 'ok' => 0, 'warning' => 0, 'error' => 0 );
        foreach ( $rows as $item ) {
            $totals[ $item['status'] ]++;
        }
        return array(
            'plugin' => 'taxocommerce-mail-pro',
            'version' => TCMAILPRO_VERSION,
            'type' => $mode,
            'executed_at' => gmdate( 'c' ),
            'rows' => $rows,
            'totals' => $totals,
            'remote_connections_tested' => false,
            'outbound_messages_sent' => false,
        );
    }

    public static function run() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Permission denied.', 'taxocommerce-mail-pro' ), '', array( 'response' => 403 ) );
        }
        check_admin_referer( 'tcmailpro_autocheck' );
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce checked above.
        $mode = isset( $_POST['check_mode'] ) && 'code' === sanitize_key( wp_unslash( $_POST['check_mode'] ) ) ? 'code' : 'functionality';
        update_user_meta( get_current_user_id(), '_tcmailpro_check_' . $mode, self::report( $mode ) );
        wp_safe_redirect( add_query_arg( array( 'page' => 'taxocommerce-mail-pro', 'tab' => 'autocheck', 'check_mode' => $mode ), admin_url( 'admin.php' ) ) );
        exit;
    }

    public static function export() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Permission denied.', 'taxocommerce-mail-pro' ), '', array( 'response' => 403 ) );
        }
        check_admin_referer( 'tcmailpro_autocheck_export' );
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce checked above.
        $mode = isset( $_POST['check_mode'] ) && 'code' === sanitize_key( wp_unslash( $_POST['check_mode'] ) ) ? 'code' : 'functionality';
        $report = get_user_meta( get_current_user_id(), '_tcmailpro_check_' . $mode, true );
        if ( ! is_array( $report ) ) {
            wp_die( esc_html__( 'Run the check before exporting.', 'taxocommerce-mail-pro' ), '', array( 'response' => 400 ) );
        }
        $json = wp_json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
        if ( ! is_string( $json ) ) {
            wp_die( esc_html__( 'Unable to create JSON.', 'taxocommerce-mail-pro' ) );
        }
        nocache_headers();
        header( 'Content-Type: application/json; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="taxocommerce-mail-pro-' . $mode . '-check.json"' );
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Raw JSON download.
        echo $json;
        exit;
    }

    public static function render() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only check selector.
        $mode = isset( $_GET['check_mode'] ) && 'code' === sanitize_key( wp_unslash( $_GET['check_mode'] ) ) ? 'code' : 'functionality';
        echo '<h2>' . esc_html__( 'Autocheck', 'taxocommerce-mail-pro' ) . '</h2>';
        echo '<p><a class="button" href="' . esc_url( add_query_arg( array( 'page' => 'taxocommerce-mail-pro', 'tab' => 'autocheck', 'check_mode' => 'functionality' ), admin_url( 'admin.php' ) ) ) . '">' . esc_html__( 'Functionality Check', 'taxocommerce-mail-pro' ) . '</a> ';
        echo '<a class="button" href="' . esc_url( add_query_arg( array( 'page' => 'taxocommerce-mail-pro', 'tab' => 'autocheck', 'check_mode' => 'code' ), admin_url( 'admin.php' ) ) ) . '">' . esc_html__( 'Code Check', 'taxocommerce-mail-pro' ) . '</a></p>';
        echo '<p>' . esc_html__( 'Checks do not access your inbox or send messages. Each connection must be tested separately.', 'taxocommerce-mail-pro' ) . '</p>';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="tcmailpro_autocheck"><input type="hidden" name="check_mode" value="' . esc_attr( $mode ) . '">';
        wp_nonce_field( 'tcmailpro_autocheck' );
        submit_button( 'code' === $mode ? __( 'Run Code Check', 'taxocommerce-mail-pro' ) : __( 'Run Functionality Check', 'taxocommerce-mail-pro' ) );
        echo '</form>';
        $report = get_user_meta( get_current_user_id(), '_tcmailpro_check_' . $mode, true );
        if ( ! is_array( $report ) ) {
            return;
        }
        echo '<p>' . esc_html__( 'Last executed', 'taxocommerce-mail-pro' ) . ': ' . esc_html( $report['executed_at'] ) . '</p>';
        echo '<p><strong>' . esc_html( 'OK ' . (int) $report['totals']['ok'] . ' / WARN ' . (int) $report['totals']['warning'] . ' / ERROR ' . (int) $report['totals']['error'] ) . '</strong></p>';
        echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Check', 'taxocommerce-mail-pro' ) . '</th><th>' . esc_html__( 'Status', 'taxocommerce-mail-pro' ) . '</th><th>' . esc_html__( 'Details', 'taxocommerce-mail-pro' ) . '</th></tr></thead><tbody>';
        foreach ( $report['rows'] as $row ) {
            echo '<tr><td>' . esc_html( $row['check'] ) . '</td><td>' . esc_html( $row['status'] ) . '</td><td>' . esc_html( $row['details'] ) . '</td></tr>';
        }
        echo '</tbody></table>';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="tcmailpro_autocheck_export"><input type="hidden" name="check_mode" value="' . esc_attr( $mode ) . '">';
        wp_nonce_field( 'tcmailpro_autocheck_export' );
        submit_button( __( 'Download JSON report', 'taxocommerce-mail-pro' ), 'secondary' );
        echo '</form>';
    }

    public static function manual( $technical ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        echo '<div style="max-width:920px"><h2>' . esc_html( $technical ? __( 'Technical manual', 'taxocommerce-mail-pro' ) : __( 'User manual', 'taxocommerce-mail-pro' ) ) . '</h2>';
        $sections = $technical ? array(
            array( __( 'Architecture', 'taxocommerce-mail-pro' ), __( 'Accounts and encrypted credentials are kept as a non-autoloaded WordPress option; no message copies or mail attachments are saved. Only manage_options can access mail.', 'taxocommerce-mail-pro' ) ),
            array( __( 'Security', 'taxocommerce-mail-pro' ), __( 'Credential encryption uses AES-256-GCM and keys derived from WordPress authentication salts. Changing salts invalidates stored passwords. IMAP uses port 993 with TLS peer verification. SMTP uses only 465/587 and authenticates directly with the selected account.', 'taxocommerce-mail-pro' ) ),
            array( __( 'Supported protocol', 'taxocommerce-mail-pro' ), __( 'Read operations use IMAP LOGIN, EXAMINE, UID SEARCH and BODY.PEEK without marking mail as read. Explicit administrator actions use SELECT, UID MOVE (RFC 6851), CREATE, RENAME and APPEND; there is no EXPUNGE or permanent deletion. Message viewing is bounded to 2 MB and complex encrypted/signed messages may not display.', 'taxocommerce-mail-pro' ) ),
            array( __( 'SMTP', 'taxocommerce-mail-pro' ), __( 'Uses a new WordPress-bundled PHPMailer instance, never changes wp_mail(), WooCommerce notifications or global mail filters. SMTP accepted does not guarantee delivery.', 'taxocommerce-mail-pro' ) ),
            array( __( 'Diagnostics', 'taxocommerce-mail-pro' ), __( 'Functionality Check verifies local encryption, IMAP and SMTP prerequisites and configuration. Code Check only checks PHP parsing and is not a security audit. Neither test performs remote mailbox changes; explicit IMAP connection and SMTP test are required.', 'taxocommerce-mail-pro' ) ),
            array( __( 'Optional extensions', 'taxocommerce-mail-pro' ), __( 'Mail Pro keeps independent account options, encrypted credentials and distinct PHP classes, admin URLs and text domain. It does not need Lite, Core or WooCommerce to operate, and it never alters their stored settings.', 'taxocommerce-mail-pro' ) ),
        ) : array(
            array( __( 'Add accounts', 'taxocommerce-mail-pro' ), __( 'Create the email address at your hosting provider, then open Mail Pro > Accounts. Enter the full mailbox address, hidden password and secure IMAP host. Additional accounts have separate settings; SMTP host, port and sender details are optional advanced fields.', 'taxocommerce-mail-pro' ) ),
            array( __( 'Hosting provider settings', 'taxocommerce-mail-pro' ), __( 'In your hosting control panel, find email client connection details. Use the exact SSL/TLS host and ports provided: IMAP 993, SMTP 465 or STARTTLS 587.', 'taxocommerce-mail-pro' ) ),
            array( __( 'Read mail', 'taxocommerce-mail-pro' ), __( 'Select Mail Pro > Inbox, Sent, Folders or Trash. Use Search and Next/Previous to find older mail, read messages without changing their read status and select up to 20 messages per page for an explicit MOVE action.', 'taxocommerce-mail-pro' ) ),
            array( __( 'Send and reply', 'taxocommerce-mail-pro' ), __( 'Use Compose for plain-text SMTP sending; Pro attempts to APPEND a copy to the server Sent folder when SMTP accepts it. If no Sent folder exists or the append fails, a specific notice warns you. One recipient per message; no attachments.', 'taxocommerce-mail-pro' ) ),
            array( __( 'Check accounts', 'taxocommerce-mail-pro' ), __( 'Use Test IMAP and a test SMTP message on a dedicated mailbox. Check that the server supports RFC MOVE and has a Trash and Sent folder. Folder operations change the server mailbox; no destructive action is run by Autocheck.', 'taxocommerce-mail-pro' ) ),
            array( __( 'Pro capabilities', 'taxocommerce-mail-pro' ), __( 'Mail Pro is a standalone advanced edition. It manages Inbox, Sent and Trash folders, text search, folder creation and renaming, bulk move and restore. No permanent deletion is implemented; Mail Lite remains a separate free plugin and is not required.', 'taxocommerce-mail-pro' ) ),
        );
        foreach ( $sections as $section ) {
            echo '<section class="card" style="max-width:880px;margin:14px 0;padding:15px"><h3>' . esc_html( $section[0] ) . '</h3><p>' . esc_html( $section[1] ) . '</p></section>';
        }
        echo '</div>';
    }
}
