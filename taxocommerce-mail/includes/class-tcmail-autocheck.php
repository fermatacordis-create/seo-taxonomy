<?php
/**
 * Read-only functionality and code checks. Does not connect to mail
 * servers or change messages unless administrator separately selects Test IMAP.
 *
 * @package TaxoCommerceMail
 */
defined( 'ABSPATH' ) || exit;
final class TCMAIL_Autocheck {
    public static function boot() {
        add_action( 'admin_post_tcmail_autocheck', array( __CLASS__, 'run' ) );
        add_action( 'admin_post_tcmail_autocheck_export', array( __CLASS__, 'export' ) );
    }

    private static function report( $mode ) {
        $rows = array();
        $add = static function ( $name, $ok, $detail ) use ( &$rows ) {
            $rows[] = array( 'check' => $name, 'status' => $ok ? 'ok' : 'error', 'details' => $detail );
        };
        if ( 'code' === $mode ) {
            $iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( TCMAIL_PATH, FilesystemIterator::SKIP_DOTS ) );
            foreach ( $iterator as $file ) {
                if ( ! $file->isFile() || $file->isLink() || 'php' !== strtolower( $file->getExtension() ) ) {
                    continue;
                }
                $path = $file->getRealPath();
                if ( ! $path || 0 !== strpos( $path, realpath( TCMAIL_PATH ) . DIRECTORY_SEPARATOR ) || $file->getSize() > 524288 ) {
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
            $add( 'encrypted_credentials', TCMAIL_Accounts::ready(), 'AES-256-GCM available for account secrets.' );
            $add( 'imap_transport', TCMAIL_IMAP::available(), 'PHP OpenSSL and secure socket functions available.' );
            $add( 'account_configuration', count( TCMAIL_Accounts::public_list() ) > 0, 'At least one mailbox is configured.' );
            $add( 'wp_mail_transport_isolated', class_exists( 'TCMAIL_SMTP' ), 'Plugin SMTP uses a new PHPMailer instance; does not modify wp_mail().' );
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
            'plugin' => 'taxocommerce-mail',
            'version' => TCMAIL_VERSION,
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
            wp_die( esc_html__( 'Permission denied.', 'taxocommerce-mail' ), '', array( 'response' => 403 ) );
        }
        check_admin_referer( 'tcmail_autocheck' );
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce checked above.
        $mode = isset( $_POST['check_mode'] ) && 'code' === sanitize_key( wp_unslash( $_POST['check_mode'] ) ) ? 'code' : 'functionality';
        update_user_meta( get_current_user_id(), '_tcmail_check_' . $mode, self::report( $mode ) );
        wp_safe_redirect( add_query_arg( array( 'page' => 'taxocommerce-mail', 'tab' => 'autocheck', 'check_mode' => $mode ), admin_url( 'admin.php' ) ) );
        exit;
    }

    public static function export() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Permission denied.', 'taxocommerce-mail' ), '', array( 'response' => 403 ) );
        }
        check_admin_referer( 'tcmail_autocheck_export' );
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce checked above.
        $mode = isset( $_POST['check_mode'] ) && 'code' === sanitize_key( wp_unslash( $_POST['check_mode'] ) ) ? 'code' : 'functionality';
        $report = get_user_meta( get_current_user_id(), '_tcmail_check_' . $mode, true );
        if ( ! is_array( $report ) ) {
            wp_die( esc_html__( 'Run the check before exporting.', 'taxocommerce-mail' ), '', array( 'response' => 400 ) );
        }
        $json = wp_json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
        if ( ! is_string( $json ) ) {
            wp_die( esc_html__( 'Unable to create JSON.', 'taxocommerce-mail' ) );
        }
        nocache_headers();
        header( 'Content-Type: application/json; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="taxocommerce-mail-' . $mode . '-check.json"' );
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
        echo '<h2>' . esc_html__( 'Autocheck', 'taxocommerce-mail' ) . '</h2>';
        echo '<p><a class="button" href="' . esc_url( add_query_arg( array( 'page' => 'taxocommerce-mail', 'tab' => 'autocheck', 'check_mode' => 'functionality' ), admin_url( 'admin.php' ) ) ) . '">' . esc_html__( 'Functionality Check', 'taxocommerce-mail' ) . '</a> ';
        echo '<a class="button" href="' . esc_url( add_query_arg( array( 'page' => 'taxocommerce-mail', 'tab' => 'autocheck', 'check_mode' => 'code' ), admin_url( 'admin.php' ) ) ) . '">' . esc_html__( 'Code Check', 'taxocommerce-mail' ) . '</a></p>';
        echo '<p>' . esc_html__( 'Checks do not access your inbox or send messages. Each connection must be tested separately.', 'taxocommerce-mail' ) . '</p>';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="tcmail_autocheck"><input type="hidden" name="check_mode" value="' . esc_attr( $mode ) . '">';
        wp_nonce_field( 'tcmail_autocheck' );
        submit_button( 'code' === $mode ? __( 'Run Code Check', 'taxocommerce-mail' ) : __( 'Run Functionality Check', 'taxocommerce-mail' ) );
        echo '</form>';
        $report = get_user_meta( get_current_user_id(), '_tcmail_check_' . $mode, true );
        if ( ! is_array( $report ) ) {
            return;
        }
        echo '<p>' . esc_html__( 'Last executed', 'taxocommerce-mail' ) . ': ' . esc_html( $report['executed_at'] ) . '</p>';
        echo '<p><strong>' . esc_html( 'OK ' . (int) $report['totals']['ok'] . ' / WARN ' . (int) $report['totals']['warning'] . ' / ERROR ' . (int) $report['totals']['error'] ) . '</strong></p>';
        echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Check', 'taxocommerce-mail' ) . '</th><th>' . esc_html__( 'Status', 'taxocommerce-mail' ) . '</th><th>' . esc_html__( 'Details', 'taxocommerce-mail' ) . '</th></tr></thead><tbody>';
        foreach ( $report['rows'] as $row ) {
            echo '<tr><td>' . esc_html( $row['check'] ) . '</td><td>' . esc_html( $row['status'] ) . '</td><td>' . esc_html( $row['details'] ) . '</td></tr>';
        }
        echo '</tbody></table>';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="tcmail_autocheck_export"><input type="hidden" name="check_mode" value="' . esc_attr( $mode ) . '">';
        wp_nonce_field( 'tcmail_autocheck_export' );
        submit_button( __( 'Download JSON report', 'taxocommerce-mail' ), 'secondary' );
        echo '</form>';
    }

    public static function manual( $technical ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        echo '<div style="max-width:920px"><h2>' . esc_html( $technical ? __( 'Technical manual', 'taxocommerce-mail' ) : __( 'User manual', 'taxocommerce-mail' ) ) . '</h2>';
        $sections = $technical ? array(
            array( __( 'Architecture', 'taxocommerce-mail' ), __( 'Accounts and encrypted credentials are kept as a non-autoloaded WordPress option; no message copies or mail attachments are saved. Only manage_options can access mail.', 'taxocommerce-mail' ) ),
            array( __( 'Security', 'taxocommerce-mail' ), __( 'Credential encryption uses AES-256-GCM and keys derived from WordPress authentication salts. Changing salts invalidates stored passwords. IMAP uses port 993 with TLS peer verification. SMTP uses only 465/587 and authenticates directly with the selected account.', 'taxocommerce-mail' ) ),
            array( __( 'Supported protocol', 'taxocommerce-mail' ), __( 'Uses read-only IMAP LOGIN, EXAMINE, UID SEARCH and BODY.PEEK; does not mark mail as read. Message size is limited to 2 MB for on-demand viewing. Supports basic plain-text and HTML MIME; complex encrypted/signed messages may not display.', 'taxocommerce-mail' ) ),
            array( __( 'SMTP', 'taxocommerce-mail' ), __( 'Uses a new WordPress-bundled PHPMailer instance, never changes wp_mail(), WooCommerce notifications or global mail filters. SMTP accepted does not guarantee delivery.', 'taxocommerce-mail' ) ),
            array( __( 'Diagnostics', 'taxocommerce-mail' ), __( 'Autocheck functionality checks local capabilities and configuration. Code Check parses plugin PHP files only. Live IMAP authentication and SMTP delivery are explicit administrator actions and are not falsely claimed by Autocheck.', 'taxocommerce-mail' ) ),
            array( __( 'Optional extensions', 'taxocommerce-mail' ), __( 'The Pro page is informational only. Lite includes no premium feature gates, licensing checks, external Pro code or mandatory connections to TaxoCommerce Core. The plugin basename, text domain and saved options remain unchanged.', 'taxocommerce-mail' ) ),
        ) : array(
            array( __( 'Add accounts', 'taxocommerce-mail' ), __( 'Create the email address at your hosting provider, then open Mail > Accounts in WordPress. Enter only the complete email address as username, masked password and secure IMAP hostname. Additional accounts can be configured separately. SMTP name, host and encrypted port are optional advanced settings for sending.', 'taxocommerce-mail' ) ),
            array( __( 'Hosting provider settings', 'taxocommerce-mail' ), __( 'In your hosting control panel, find email client connection details. Use the exact SSL/TLS host and ports provided: IMAP 993, SMTP 465 or STARTTLS 587.', 'taxocommerce-mail' ) ),
            array( __( 'Read mail', 'taxocommerce-mail' ), __( 'Select Mail > Inbox, choose a configured account and open a message. The plugin reads up to 12 recent messages from INBOX without marking them read. Messages remain on the mail server.', 'taxocommerce-mail' ) ),
            array( __( 'Send and reply', 'taxocommerce-mail' ), __( 'Use Compose to send plain-text mail from the selected account. Reply prefills the sender and subject. One recipient per message; no attachments in this initial version.', 'taxocommerce-mail' ) ),
            array( __( 'Check accounts', 'taxocommerce-mail' ), __( 'Use Test IMAP per account to verify inbound access. Send a test SMTP message to an address you control and verify it arrived. Autocheck alone cannot confirm external delivery.', 'taxocommerce-mail' ) ),
            array( __( 'Lite and Pro editions', 'taxocommerce-mail' ), __( 'Mail Lite is the free standalone edition. It can configure multiple accounts, read the 12 newest inbox messages, compose and reply; it does not provide folders, a Sent view or message deletion. Pro is a future separate add-on, not required to use Lite.', 'taxocommerce-mail' ) ),
        );
        foreach ( $sections as $section ) {
            echo '<section class="card" style="max-width:880px;margin:14px 0;padding:15px"><h3>' . esc_html( $section[0] ) . '</h3><p>' . esc_html( $section[1] ) . '</p></section>';
        }
        echo '</div>';
    }
}
