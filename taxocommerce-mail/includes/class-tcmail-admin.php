<?php
/**
 * Administrator-only independent mailbox UI and form handlers.
 *
 * @package TaxoCommerceMail
 */
defined( 'ABSPATH' ) || exit;
final class TCMAIL_Admin {
    const CAP = 'manage_options';

    public static function activate() {
        // No schema or inbox tables: mail stays at its original provider.
        if ( false === get_option( TCMAIL_Accounts::OPTION, false ) ) {
            update_option( TCMAIL_Accounts::OPTION, array(), false );
        }
    }

    public static function boot() {
        add_action( 'admin_menu', array( __CLASS__, 'menu' ), 50 );
        add_action( 'admin_post_tcmail_save', array( __CLASS__, 'save' ) );
        add_action( 'admin_post_tcmail_delete', array( __CLASS__, 'delete' ) );
        add_action( 'admin_post_tcmail_send', array( __CLASS__, 'send' ) );
        add_action( 'admin_post_tcmail_test', array( __CLASS__, 'test' ) );
        add_filter( 'plugin_action_links_' . plugin_basename( TCMAIL_PATH . 'taxocommerce-mail.php' ), array( __CLASS__, 'links' ) );
    }

    public static function links( $links ) {
        array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=taxocommerce-mail&tab=accounts' ) ) . '">' . esc_html__( 'Settings', 'taxocommerce-mail' ) . '</a>' );
        return $links;
    }

    public static function menu() {
        $parent = function_exists( 'taxocommerce_core' ) || defined( 'TAXOCOMMERCE_CORE_VERSION' ) ? 'taxocommerce-core' : '';
        if ( $parent ) {
            add_submenu_page( $parent, __( 'Mail', 'taxocommerce-mail' ), __( 'Mail', 'taxocommerce-mail' ), self::CAP, 'taxocommerce-mail', array( __CLASS__, 'render' ) );
        } else {
            add_menu_page( __( 'Mail', 'taxocommerce-mail' ), __( 'Mail', 'taxocommerce-mail' ), self::CAP, 'taxocommerce-mail', array( __CLASS__, 'render' ), 'dashicons-email-alt', 55 );
        }
    }

    private static function require_admin( $nonce ) {
        if ( ! current_user_can( self::CAP ) ) {
            wp_die( esc_html__( 'Permission denied.', 'taxocommerce-mail' ), '', array( 'response' => 403 ) );
        }
        check_admin_referer( $nonce );
    }

    private static function location( $tab = 'accounts', $notice = '' ) {
        $args = array( 'page' => 'taxocommerce-mail', 'tab' => $tab );
        if ( $notice ) {
            $args['tcmail_notice'] = $notice;
        }
        wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
        exit;
    }

    public static function save() {
        self::require_admin( 'tcmail_save' );
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Verified above.
        $input = isset( $_POST['account'] ) && is_array( $_POST['account'] )
            ? map_deep( wp_unslash( $_POST['account'] ), 'sanitize_text_field' ) : array();
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Opaque password is never output or logged and is encrypted immediately; sanitizing could change its actual bytes.
        $password = isset( $_POST['password'] ) && is_string( $_POST['password'] ) ? wp_unslash( $_POST['password'] ) : '';
        $id = isset( $_POST['id'] ) && is_string( $_POST['id'] ) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : '';
        // phpcs:enable WordPress.Security.NonceVerification.Missing
        $input = TCMAIL_Accounts::basic_defaults( $input );
        self::location( 'accounts', TCMAIL_Accounts::save( $input, $password, $id ) ? 'saved' : 'invalid' );
    }

    public static function delete() {
        self::require_admin( 'tcmail_delete' );
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified above.
        $id = isset( $_POST['id'] ) && is_string( $_POST['id'] ) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : '';
        self::location( 'accounts', TCMAIL_Accounts::remove( $id ) ? 'removed' : 'invalid' );
    }

    public static function test() {
        self::require_admin( 'tcmail_test' );
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified above.
        $id = isset( $_POST['id'] ) && is_string( $_POST['id'] ) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : '';
        $account = TCMAIL_Accounts::credentials( $id );
        if ( ! $account ) {
            self::location( 'accounts', 'invalid' );
        }
        $imap = new TCMAIL_IMAP();
        try {
            $imap->connect( $account );
            $notice = 'connected';
        } catch ( RuntimeException $error ) {
            $notice = 'connection_failed';
        } finally {
            $imap->disconnect();
        }
        self::location( 'accounts', $notice );
    }

    public static function send() {
        self::require_admin( 'tcmail_send' );
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Verified above.
        $id = isset( $_POST['id'] ) && is_string( $_POST['id'] ) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : '';
        $to = isset( $_POST['to'] ) && is_string( $_POST['to'] ) ? sanitize_email( wp_unslash( $_POST['to'] ) ) : '';
        $subject = isset( $_POST['subject'] ) && is_string( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : '';
        $body = isset( $_POST['message'] ) && is_string( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';
        // phpcs:enable WordPress.Security.NonceVerification.Missing
        $account = TCMAIL_Accounts::credentials( $id );
        if ( ! $account ) {
            self::location( 'compose', 'invalid' );
        }
        $success = TCMAIL_SMTP::send( $account, $to, $subject, $body );
        self::location( 'compose', $success ? 'sent' : 'send_failed' );
    }

    private static function account_selector( $tab, $selected ) {
        $accounts = TCMAIL_Accounts::public_list();
        echo '<form method="get" action="' . esc_url( admin_url( 'admin.php' ) ) . '">';
        echo '<input type="hidden" name="page" value="taxocommerce-mail"><input type="hidden" name="tab" value="' . esc_attr( $tab ) . '">';
        echo '<label for="tcmail_active">' . esc_html__( 'Mailbox', 'taxocommerce-mail' ) . '</label> ';
        echo '<select id="tcmail_active" name="account">';
        foreach ( $accounts as $account ) {
            echo '<option value="' . esc_attr( $account['id'] ) . '" ' . selected( $selected, $account['id'], false ) . '>' . esc_html( $account['name'] . ' (' . $account['email'] . ')' ) . '</option>';
        }
        echo '</select> ';
        submit_button( __( 'Open', 'taxocommerce-mail' ), 'secondary', 'submit', false );
        echo '</form>';
    }

    private static function selected_account() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only account selector.
        $id = isset( $_GET['account'] ) && is_string( $_GET['account'] ) ? sanitize_text_field( wp_unslash( $_GET['account'] ) ) : '';
        $list = TCMAIL_Accounts::public_list();
        if ( ! $id && $list ) {
            $id = $list[0]['id'];
        }
        return $id ? TCMAIL_Accounts::credentials( $id ) : false;
    }

    private static function list_accounts() {
        echo '<h2>' . esc_html__( 'Mail accounts', 'taxocommerce-mail' ) . '</h2>';
        echo '<p>' . esc_html__( 'Add existing hosting mailboxes. Mailbox creation and passwords are managed by your provider, not WordPress. Enter the exact secure hosts from your provider settings.', 'taxocommerce-mail' ) . '</p>';
        $accounts = TCMAIL_Accounts::public_list();
        echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Name', 'taxocommerce-mail' ) . '</th><th>' . esc_html__( 'Address', 'taxocommerce-mail' ) . '</th><th>' . esc_html__( 'IMAP server', 'taxocommerce-mail' ) . '</th><th>' . esc_html__( 'SMTP server', 'taxocommerce-mail' ) . '</th><th>' . esc_html__( 'Actions', 'taxocommerce-mail' ) . '</th></tr></thead><tbody>';
        foreach ( $accounts as $item ) {
            echo '<tr><td>' . esc_html( $item['name'] ) . '</td><td>' . esc_html( $item['email'] ) . '</td><td>' . esc_html( $item['imap_host'] ) . ':993</td><td>' . esc_html( $item['smtp_host'] ) . ':' . esc_html( (string) $item['smtp_port'] ) . '</td><td>';
            echo '<a class="button button-small" href="' . esc_url( add_query_arg( array( 'page' => 'taxocommerce-mail', 'tab' => 'accounts', 'edit' => $item['id'] ), admin_url( 'admin.php' ) ) ) . '">' . esc_html__( 'Edit', 'taxocommerce-mail' ) . '</a> ';
            echo '<a class="button button-small" href="' . esc_url( add_query_arg( array( 'page' => 'taxocommerce-mail', 'tab' => 'inbox', 'account' => $item['id'] ), admin_url( 'admin.php' ) ) ) . '">' . esc_html__( 'Inbox', 'taxocommerce-mail' ) . '</a> ';
            echo '<form method="post" style="display:inline" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="tcmail_test"><input type="hidden" name="id" value="' . esc_attr( $item['id'] ) . '">';
            wp_nonce_field( 'tcmail_test' );
            echo '<button class="button button-small" type="submit">' . esc_html__( 'Test IMAP', 'taxocommerce-mail' ) . '</button></form> ';
            echo '<form method="post" style="display:inline" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="tcmail_delete"><input type="hidden" name="id" value="' . esc_attr( $item['id'] ) . '">';
            wp_nonce_field( 'tcmail_delete' );
            echo '<button class="button button-small" type="submit" onclick="return confirm(' . esc_attr( wp_json_encode( __( 'Remove this account from WordPress? Messages remain on the mail server.', 'taxocommerce-mail' ) ) ) . ')">' . esc_html__( 'Remove', 'taxocommerce-mail' ) . '</button></form>';
            echo '</td></tr>';
        }
        if ( ! $accounts ) {
            echo '<tr><td colspan="5">' . esc_html__( 'No mailboxes configured.', 'taxocommerce-mail' ) . '</td></tr>';
        }
        echo '</tbody></table>';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only edit selector.
        $edit = isset( $_GET['edit'] ) && is_string( $_GET['edit'] ) ? sanitize_text_field( wp_unslash( $_GET['edit'] ) ) : '';
        $selected = array();
        foreach ( $accounts as $item ) {
            if ( $edit === $item['id'] ) {
                $selected = $item;
                break;
            }
        }
        echo '<h3>' . esc_html( $selected ? __( 'Edit mailbox', 'taxocommerce-mail' ) : __( 'Add mailbox', 'taxocommerce-mail' ) ) . '</h3>';
        if ( ! TCMAIL_Accounts::ready() ) {
            echo '<div class="notice notice-error"><p>' . esc_html__( 'OpenSSL AES-256-GCM is required for encrypted credential storage. No account can be saved.', 'taxocommerce-mail' ) . '</p></div>';
            return;
        }
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="tcmail_save"><input type="hidden" name="id" value="' . esc_attr( $selected ? $selected['id'] : '' ) . '">';
        wp_nonce_field( 'tcmail_save' );
        echo '<p>' . esc_html__( 'Quick setup: enter your full mailbox email address, password and the secure IMAP server from your hosting provider.', 'taxocommerce-mail' ) . '</p>';
        echo '<table class="form-table"><tbody>';
        echo '<tr><th scope="row"><label for="tcmail_username">' . esc_html__( 'Email / username', 'taxocommerce-mail' ) . '</label></th><td><input id="tcmail_username" class="regular-text" type="email" name="account[username]" maxlength="190" autocomplete="username" value="' . esc_attr( $selected ? $selected['username'] : '' ) . '" required></td></tr>';
        echo '<tr><th scope="row"><label for="tcmail_pass">' . esc_html__( 'Mailbox password', 'taxocommerce-mail' ) . '</label></th><td><input id="tcmail_pass" type="password" name="password" autocomplete="new-password" class="regular-text" ' . ( $selected ? '' : 'required' ) . '>';
        echo '<p class="description">' . esc_html__( 'The password remains hidden. Leave blank when editing to keep the saved encrypted password.', 'taxocommerce-mail' ) . '</p></td></tr>';
        echo '<tr><th scope="row"><label for="tcmail_imap_host">' . esc_html__( 'Incoming mail server (IMAP SSL 993)', 'taxocommerce-mail' ) . '</label></th><td><input id="tcmail_imap_host" class="regular-text" type="text" name="account[imap_host]" maxlength="253" value="' . esc_attr( $selected ? $selected['imap_host'] : '' ) . '" required>';
        echo '<p class="description">' . esc_html__( 'Copy the SSL/TLS host name exactly as shown by your mail hosting provider. Do not include https:// or a port.', 'taxocommerce-mail' ) . '</p></td></tr>';
        echo '</tbody></table>';
        echo '<details><summary>' . esc_html__( 'Advanced details (optional)', 'taxocommerce-mail' ) . '</summary>';
        echo '<p>' . esc_html__( 'These values are optional for receiving messages. SMTP defaults to the same server, port 465. Change them only if your provider gives different settings.', 'taxocommerce-mail' ) . '</p>';
        echo '<table class="form-table"><tbody>';
        foreach ( array(
            'name' => __( 'Display name', 'taxocommerce-mail' ),
            'email' => __( 'Sender email (defaults to username)', 'taxocommerce-mail' ),
            'smtp_host' => __( 'Outgoing mail server (SMTP)', 'taxocommerce-mail' ),
        ) as $key => $label ) {
            echo '<tr><th scope="row"><label for="tcmail_' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label></th><td><input id="tcmail_' . esc_attr( $key ) . '" class="regular-text" type="' . ( 'email' === $key ? 'email' : 'text' ) . '" name="account[' . esc_attr( $key ) . ']" maxlength="253" value="' . esc_attr( $selected && isset( $selected[ $key ] ) ? $selected[ $key ] : '' ) . '"></td></tr>';
        }
        echo '<tr><th scope="row"><label for="tcmail_port">' . esc_html__( 'SMTP encrypted port', 'taxocommerce-mail' ) . '</label></th><td><select name="account[smtp_port]" id="tcmail_port">';
        foreach ( array( 465 => '465 — SSL/TLS', 587 => '587 — STARTTLS' ) as $port => $label ) {
            echo '<option value="' . esc_attr( (string) $port ) . '" ' . selected( $selected ? (int) $selected['smtp_port'] : 465, $port, false ) . '>' . esc_html( $label ) . '</option>';
        }
        echo '</select></td></tr></tbody></table></details>';
        submit_button( $selected ? __( 'Update mailbox', 'taxocommerce-mail' ) : __( 'Add mailbox', 'taxocommerce-mail' ) );
        echo '</form>';
    }

    private static function inbox() {
        $account = self::selected_account();
        if ( ! $account ) {
            echo '<p>' . esc_html__( 'Add and select a mailbox first.', 'taxocommerce-mail' ) . '</p>';
            return;
        }
        self::account_selector( 'inbox', $account['id'] );
        echo '<h2>' . esc_html__( 'Inbox', 'taxocommerce-mail' ) . ' — ' . esc_html( $account['email'] ) . '</h2>';
        echo '<p>' . esc_html__( 'Showing the latest 12 messages without changing their read status. Emails remain on your provider server.', 'taxocommerce-mail' ) . '</p>';
        $imap = new TCMAIL_IMAP();
        try {
            $imap->connect( $account );
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only message UID, no mutations.
            $uid = isset( $_GET['uid'] ) ? absint( $_GET['uid'] ) : 0;
            if ( $uid ) {
                $message = $imap->message( $uid );
                echo '<p><a href="' . esc_url( add_query_arg( array( 'page' => 'taxocommerce-mail', 'tab' => 'inbox', 'account' => $account['id'] ), admin_url( 'admin.php' ) ) ) . '">' . esc_html__( 'Back to inbox', 'taxocommerce-mail' ) . '</a></p>';
                echo '<h3>' . esc_html( $message['subject'] ) . '</h3>';
                echo '<p><strong>' . esc_html__( 'From', 'taxocommerce-mail' ) . ':</strong> ' . esc_html( $message['from'] ) . ' · ' . esc_html( $message['date'] ) . '</p>';
                echo '<div style="max-width:900px;white-space:pre-wrap;word-wrap:break-word;background:white;padding:18px;border:1px solid #ccc">' . esc_html( $message['body'] ) . '</div>';
                $reply = self::address_from_header( $message['reply_to'] );
                if ( $reply ) {
                    echo '<p><a class="button button-primary" href="' . esc_url( add_query_arg( array( 'page' => 'taxocommerce-mail', 'tab' => 'compose', 'account' => $account['id'], 'to' => $reply, 're' => $message['subject'] ), admin_url( 'admin.php' ) ) ) . '">' . esc_html__( 'Reply', 'taxocommerce-mail' ) . '</a></p>';
                }
            } else {
                $messages = $imap->inbox();
                echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'From', 'taxocommerce-mail' ) . '</th><th>' . esc_html__( 'Subject', 'taxocommerce-mail' ) . '</th><th>' . esc_html__( 'Date', 'taxocommerce-mail' ) . '</th></tr></thead><tbody>';
                foreach ( $messages as $row ) {
                    $link = add_query_arg( array( 'page' => 'taxocommerce-mail', 'tab' => 'inbox', 'account' => $account['id'], 'uid' => $row['uid'] ), admin_url( 'admin.php' ) );
                    echo '<tr><td>' . esc_html( $row['from'] ) . '</td><td><a href="' . esc_url( $link ) . '">' . esc_html( $row['subject'] ) . '</a></td><td>' . esc_html( $row['date'] ) . '</td></tr>';
                }
                if ( ! $messages ) {
                    echo '<tr><td colspan="3">' . esc_html__( 'No messages found.', 'taxocommerce-mail' ) . '</td></tr>';
                }
                echo '</tbody></table>';
            }
        } catch ( RuntimeException $error ) {
            echo '<div class="notice notice-error"><p>' . esc_html__( 'Unable to read this inbox. Check the server, credentials and TLS certificate using Test IMAP. Message details are not stored.', 'taxocommerce-mail' ) . '</p></div>';
        } finally {
            $imap->disconnect();
        }
    }

    private static function address_from_header( $header ) {
        if ( preg_match( '/<([^<>]+)>/', $header, $matches ) ) {
            $header = $matches[1];
        }
        $email = sanitize_email( trim( $header ) );
        return is_email( $email ) ? $email : '';
    }

    private static function compose() {
        $account = self::selected_account();
        if ( ! $account ) {
            echo '<p>' . esc_html__( 'Add and select a mailbox first.', 'taxocommerce-mail' ) . '</p>';
            return;
        }
        self::account_selector( 'compose', $account['id'] );
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Prefill from a GET link only; values are escaped in the HTML form.
        $to = isset( $_GET['to'] ) && is_string( $_GET['to'] ) ? sanitize_email( wp_unslash( $_GET['to'] ) ) : '';
        $subject = isset( $_GET['re'] ) && is_string( $_GET['re'] ) ? sanitize_text_field( wp_unslash( $_GET['re'] ) ) : '';
        // phpcs:enable WordPress.Security.NonceVerification.Recommended
        if ( $subject && 0 !== stripos( $subject, 'Re:' ) ) {
            $subject = 'Re: ' . $subject;
        }
        echo '<h2>' . esc_html__( 'Compose email', 'taxocommerce-mail' ) . '</h2>';
        echo '<p>' . esc_html__( 'Sending from:', 'taxocommerce-mail' ) . ' <strong>' . esc_html( $account['email'] ) . '</strong></p>';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="tcmail_send"><input type="hidden" name="id" value="' . esc_attr( $account['id'] ) . '">';
        wp_nonce_field( 'tcmail_send' );
        echo '<table class="form-table"><tr><th scope="row"><label for="tcmail_to">' . esc_html__( 'To', 'taxocommerce-mail' ) . '</label></th><td><input id="tcmail_to" name="to" type="email" class="regular-text" maxlength="190" required value="' . esc_attr( $to ) . '"></td></tr>';
        echo '<tr><th scope="row"><label for="tcmail_subject">' . esc_html__( 'Subject', 'taxocommerce-mail' ) . '</label></th><td><input id="tcmail_subject" name="subject" class="large-text" maxlength="250" required value="' . esc_attr( $subject ) . '"></td></tr>';
        echo '<tr><th scope="row"><label for="tcmail_body">' . esc_html__( 'Message (plain text)', 'taxocommerce-mail' ) . '</label></th><td><textarea id="tcmail_body" name="message" rows="12" maxlength="40000" class="large-text" required></textarea></td></tr></table>';
        submit_button( __( 'Send email', 'taxocommerce-mail' ) );
        echo '</form>';
    }

    public static function render() {
        if ( ! current_user_can( self::CAP ) ) {
            wp_die( esc_html__( 'Permission denied.', 'taxocommerce-mail' ), '', array( 'response' => 403 ) );
        }
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only navigation and display of notices.
        $tab = isset( $_GET['tab'] ) && is_string( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'inbox';
        $notice = isset( $_GET['tcmail_notice'] ) && is_string( $_GET['tcmail_notice'] ) ? sanitize_key( wp_unslash( $_GET['tcmail_notice'] ) ) : '';
        // phpcs:enable WordPress.Security.NonceVerification.Recommended
        echo '<div class="wrap"><h1>' . esc_html__( 'TaxoCommerce Mail', 'taxocommerce-mail' ) . '</h1>';
        $tabs = array( 'inbox' => __( 'Inbox', 'taxocommerce-mail' ), 'compose' => __( 'Compose', 'taxocommerce-mail' ), 'accounts' => __( 'Accounts', 'taxocommerce-mail' ), 'autocheck' => __( 'Autocheck', 'taxocommerce-mail' ), 'manual' => __( 'User manual', 'taxocommerce-mail' ), 'technical' => __( 'Technical manual', 'taxocommerce-mail' ) );
        echo '<nav class="nav-tab-wrapper" aria-label="' . esc_attr__( 'Mail sections', 'taxocommerce-mail' ) . '">';
        foreach ( $tabs as $key => $label ) {
            echo '<a href="' . esc_url( add_query_arg( array( 'page' => 'taxocommerce-mail', 'tab' => $key ), admin_url( 'admin.php' ) ) ) . '" class="nav-tab' . ( $key === $tab ? ' nav-tab-active' : '' ) . '">' . esc_html( $label ) . '</a>';
        }
        echo '</nav>';
        $notices = array(
            'saved' => __( 'Mailbox settings saved.', 'taxocommerce-mail' ),
            'removed' => __( 'Mailbox disconnected from WordPress. Server messages were not deleted.', 'taxocommerce-mail' ),
            'invalid' => __( 'Invalid data, missing password or encryption unavailable. Account was not saved.', 'taxocommerce-mail' ),
            'connected' => __( 'IMAP connection succeeded. SMTP delivery has not been tested.', 'taxocommerce-mail' ),
            'connection_failed' => __( 'IMAP connection failed. Check your host, credentials, certificate and server access.', 'taxocommerce-mail' ),
            'sent' => __( 'SMTP server accepted the message. Delivery to the recipient cannot be guaranteed.', 'taxocommerce-mail' ),
            'send_failed' => __( 'SMTP send failed. Check account settings and SMTP restrictions.', 'taxocommerce-mail' ),
        );
        if ( isset( $notices[ $notice ] ) ) {
            echo '<div class="notice notice-' . ( in_array( $notice, array( 'saved', 'removed', 'connected', 'sent' ), true ) ? 'success' : 'error' ) . '"><p>' . esc_html( $notices[ $notice ] ) . '</p></div>';
        }
        if ( 'accounts' === $tab ) {
            self::list_accounts();
        } elseif ( 'compose' === $tab ) {
            self::compose();
        } elseif ( 'autocheck' === $tab ) {
            TCMAIL_Autocheck::render();
        } elseif ( 'manual' === $tab || 'technical' === $tab ) {
            TCMAIL_Autocheck::manual( 'technical' === $tab );
        } else {
            self::inbox();
        }
        echo '</div>';
    }
}
