<?php
/**
 * Administrator-only independent mailbox UI and form handlers.
 *
 * @package TaxoCommerceMailPro
 */
defined( 'ABSPATH' ) || exit;
final class TCMAILPRO_Admin {
    const CAP = 'manage_options';

    public static function activate() {
        // No schema or inbox tables: mail stays at its original provider.
        if ( false === get_option( TCMAILPRO_Accounts::OPTION, false ) ) {
            update_option( TCMAILPRO_Accounts::OPTION, array(), false );
        }
    }

    public static function boot() {
        add_action( 'admin_menu', array( __CLASS__, 'menu' ), 50 );
        add_action( 'admin_post_tcmailpro_save', array( __CLASS__, 'save' ) );
        add_action( 'admin_post_tcmailpro_delete', array( __CLASS__, 'delete' ) );
        add_action( 'admin_post_tcmailpro_send', array( __CLASS__, 'send' ) );
        add_action( 'admin_post_tcmailpro_test', array( __CLASS__, 'test' ) );
        add_filter( 'plugin_action_links_' . plugin_basename( TCMAILPRO_PATH . 'taxocommerce-mail-pro.php' ), array( __CLASS__, 'links' ) );
    }

    public static function links( $links ) {
        array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=taxocommerce-mail-pro&tab=accounts' ) ) . '">' . esc_html__( 'Settings', 'taxocommerce-mail-pro' ) . '</a>' );
        return $links;
    }

    public static function menu() {
        $parent = function_exists( 'taxocommerce_core' ) || defined( 'TAXOCOMMERCE_CORE_VERSION' ) ? 'taxocommerce-core' : '';
        $menu_label = __( 'Mail', 'taxocommerce-mail-pro' ) . ' Pro';
        if ( $parent ) {
            add_submenu_page( $parent, $menu_label, $menu_label, self::CAP, 'taxocommerce-mail-pro', array( __CLASS__, 'render' ) );
        } else {
            add_menu_page( $menu_label, $menu_label, self::CAP, 'taxocommerce-mail-pro', array( __CLASS__, 'render' ), 'dashicons-email-alt', 55 );
        }
    }

    private static function require_admin( $nonce ) {
        if ( ! current_user_can( self::CAP ) ) {
            wp_die( esc_html__( 'Permission denied.', 'taxocommerce-mail-pro' ), '', array( 'response' => 403 ) );
        }
        check_admin_referer( $nonce );
    }

    private static function location( $tab = 'accounts', $notice = '' ) {
        $args = array( 'page' => 'taxocommerce-mail-pro', 'tab' => $tab );
        if ( $notice ) {
            $args['tcmailpro_notice'] = $notice;
        }
        wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
        exit;
    }

    public static function save() {
        self::require_admin( 'tcmailpro_save' );
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Verified above.
        $input = isset( $_POST['account'] ) && is_array( $_POST['account'] )
            ? map_deep( wp_unslash( $_POST['account'] ), 'sanitize_text_field' ) : array();
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Opaque password is never output or logged and is encrypted immediately; sanitizing could change its actual bytes.
        $password = isset( $_POST['password'] ) && is_string( $_POST['password'] ) ? wp_unslash( $_POST['password'] ) : '';
        $id = isset( $_POST['id'] ) && is_string( $_POST['id'] ) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : '';
        // phpcs:enable WordPress.Security.NonceVerification.Missing
        $input = TCMAILPRO_Accounts::basic_defaults( $input );
        self::location( 'accounts', TCMAILPRO_Accounts::save( $input, $password, $id ) ? 'saved' : 'invalid' );
    }

    public static function delete() {
        self::require_admin( 'tcmailpro_delete' );
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified above.
        $id = isset( $_POST['id'] ) && is_string( $_POST['id'] ) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : '';
        self::location( 'accounts', TCMAILPRO_Accounts::remove( $id ) ? 'removed' : 'invalid' );
    }

    public static function test() {
        self::require_admin( 'tcmailpro_test' );
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified above.
        $id = isset( $_POST['id'] ) && is_string( $_POST['id'] ) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : '';
        $account = TCMAILPRO_Accounts::credentials( $id );
        if ( ! $account ) {
            self::location( 'accounts', 'invalid' );
        }
        $imap = new TCMAILPRO_IMAP();
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
        self::require_admin( 'tcmailpro_send' );
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Verified above.
        $id = isset( $_POST['id'] ) && is_string( $_POST['id'] ) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : '';
        $to = isset( $_POST['to'] ) && is_string( $_POST['to'] ) ? sanitize_email( wp_unslash( $_POST['to'] ) ) : '';
        $subject = isset( $_POST['subject'] ) && is_string( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : '';
        $body = isset( $_POST['message'] ) && is_string( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';
        // phpcs:enable WordPress.Security.NonceVerification.Missing
        $account = TCMAILPRO_Accounts::credentials( $id );
        if ( ! $account ) {
            self::location( 'compose', 'invalid' );
        }
        $mime = TCMAILPRO_SMTP::send( $account, $to, $subject, $body );
        if ( false === $mime ) {
            self::location( 'compose', 'send_failed' );
        }
        $imap = new TCMAILPRO_IMAP();
        try {
            $imap->connect( $account );
            $sent_folder = TCMAILPRO_IMAP::special_folder( $imap->folders(), 'sent' );
            if ( '' === $sent_folder ) {
                self::location( 'compose', 'sent_unarchived' );
            }
            $imap->append_sent( $sent_folder, $mime );
            $notice = 'sent';
        } catch ( RuntimeException $error ) {
            // SMTP was accepted; failure to file a copy cannot be reported as unsent.
            $notice = 'sent_unarchived';
        } finally {
            $imap->disconnect();
        }
        self::location( 'compose', $notice );
    }

    private static function account_selector( $tab, $selected ) {
        $accounts = TCMAILPRO_Accounts::public_list();
        echo '<form method="get" action="' . esc_url( admin_url( 'admin.php' ) ) . '">';
        echo '<input type="hidden" name="page" value="taxocommerce-mail-pro"><input type="hidden" name="tab" value="' . esc_attr( $tab ) . '">';
        echo '<label for="tcmailpro_active">' . esc_html__( 'Mailbox', 'taxocommerce-mail-pro' ) . '</label> ';
        echo '<select id="tcmailpro_active" name="account">';
        foreach ( $accounts as $account ) {
            echo '<option value="' . esc_attr( $account['id'] ) . '" ' . selected( $selected, $account['id'], false ) . '>' . esc_html( $account['name'] . ' (' . $account['email'] . ')' ) . '</option>';
        }
        echo '</select> ';
        submit_button( __( 'Open', 'taxocommerce-mail-pro' ), 'secondary', 'submit', false );
        echo '</form>';
    }

    private static function selected_account() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only account selector.
        $id = isset( $_GET['account'] ) && is_string( $_GET['account'] ) ? sanitize_text_field( wp_unslash( $_GET['account'] ) ) : '';
        $list = TCMAILPRO_Accounts::public_list();
        if ( ! $id && $list ) {
            $id = $list[0]['id'];
        }
        return $id ? TCMAILPRO_Accounts::credentials( $id ) : false;
    }

    private static function list_accounts() {
        echo '<h2>' . esc_html__( 'Mail accounts', 'taxocommerce-mail-pro' ) . '</h2>';
        echo '<p>' . esc_html__( 'Add existing hosting mailboxes. Mailbox creation and passwords are managed by your provider, not WordPress. Enter the exact secure hosts from your provider settings.', 'taxocommerce-mail-pro' ) . '</p>';
        $accounts = TCMAILPRO_Accounts::public_list();
        echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Name', 'taxocommerce-mail-pro' ) . '</th><th>' . esc_html__( 'Address', 'taxocommerce-mail-pro' ) . '</th><th>' . esc_html__( 'IMAP server', 'taxocommerce-mail-pro' ) . '</th><th>' . esc_html__( 'SMTP server', 'taxocommerce-mail-pro' ) . '</th><th>' . esc_html__( 'Actions', 'taxocommerce-mail-pro' ) . '</th></tr></thead><tbody>';
        foreach ( $accounts as $item ) {
            echo '<tr><td>' . esc_html( $item['name'] ) . '</td><td>' . esc_html( $item['email'] ) . '</td><td>' . esc_html( $item['imap_host'] ) . ':993</td><td>' . esc_html( $item['smtp_host'] ) . ':' . esc_html( (string) $item['smtp_port'] ) . '</td><td>';
            echo '<a class="button button-small" href="' . esc_url( add_query_arg( array( 'page' => 'taxocommerce-mail-pro', 'tab' => 'accounts', 'edit' => $item['id'] ), admin_url( 'admin.php' ) ) ) . '">' . esc_html__( 'Edit', 'taxocommerce-mail-pro' ) . '</a> ';
            echo '<a class="button button-small" href="' . esc_url( add_query_arg( array( 'page' => 'taxocommerce-mail-pro', 'tab' => 'inbox', 'account' => $item['id'] ), admin_url( 'admin.php' ) ) ) . '">' . esc_html__( 'Inbox', 'taxocommerce-mail-pro' ) . '</a> ';
            echo '<form method="post" style="display:inline" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="tcmailpro_test"><input type="hidden" name="id" value="' . esc_attr( $item['id'] ) . '">';
            wp_nonce_field( 'tcmailpro_test' );
            echo '<button class="button button-small" type="submit">' . esc_html__( 'Test IMAP', 'taxocommerce-mail-pro' ) . '</button></form> ';
            echo '<form method="post" style="display:inline" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="tcmailpro_delete"><input type="hidden" name="id" value="' . esc_attr( $item['id'] ) . '">';
            wp_nonce_field( 'tcmailpro_delete' );
            echo '<button class="button button-small" type="submit" onclick="return confirm(' . esc_attr( wp_json_encode( __( 'Remove this account from WordPress? Messages remain on the mail server.', 'taxocommerce-mail-pro' ) ) ) . ')">' . esc_html__( 'Remove', 'taxocommerce-mail-pro' ) . '</button></form>';
            echo '</td></tr>';
        }
        if ( ! $accounts ) {
            echo '<tr><td colspan="5">' . esc_html__( 'No mailboxes configured.', 'taxocommerce-mail-pro' ) . '</td></tr>';
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
        echo '<h3>' . esc_html( $selected ? __( 'Edit mailbox', 'taxocommerce-mail-pro' ) : __( 'Add mailbox', 'taxocommerce-mail-pro' ) ) . '</h3>';
        if ( ! TCMAILPRO_Accounts::ready() ) {
            echo '<div class="notice notice-error"><p>' . esc_html__( 'OpenSSL AES-256-GCM is required for encrypted credential storage. No account can be saved.', 'taxocommerce-mail-pro' ) . '</p></div>';
            return;
        }
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="tcmailpro_save"><input type="hidden" name="id" value="' . esc_attr( $selected ? $selected['id'] : '' ) . '">';
        wp_nonce_field( 'tcmailpro_save' );
        echo '<p>' . esc_html__( 'Quick setup: enter your full mailbox email address, password and the secure IMAP server from your hosting provider.', 'taxocommerce-mail-pro' ) . '</p>';
        echo '<table class="form-table"><tbody>';
        echo '<tr><th scope="row"><label for="tcmailpro_username">' . esc_html__( 'Email / username', 'taxocommerce-mail-pro' ) . '</label></th><td><input id="tcmailpro_username" class="regular-text" type="email" name="account[username]" maxlength="190" autocomplete="username" value="' . esc_attr( $selected ? $selected['username'] : '' ) . '" required></td></tr>';
        echo '<tr><th scope="row"><label for="tcmailpro_pass">' . esc_html__( 'Mailbox password', 'taxocommerce-mail-pro' ) . '</label></th><td><input id="tcmailpro_pass" type="password" name="password" autocomplete="new-password" class="regular-text" ' . ( $selected ? '' : 'required' ) . '>';
        echo '<p class="description">' . esc_html__( 'The password remains hidden. Leave blank when editing to keep the saved encrypted password.', 'taxocommerce-mail-pro' ) . '</p></td></tr>';
        echo '<tr><th scope="row"><label for="tcmailpro_imap_host">' . esc_html__( 'Incoming mail server (IMAP SSL 993)', 'taxocommerce-mail-pro' ) . '</label></th><td><input id="tcmailpro_imap_host" class="regular-text" type="text" name="account[imap_host]" maxlength="253" value="' . esc_attr( $selected ? $selected['imap_host'] : '' ) . '" required>';
        echo '<p class="description">' . esc_html__( 'Copy the SSL/TLS host name exactly as shown by your mail hosting provider. Do not include https:// or a port.', 'taxocommerce-mail-pro' ) . '</p></td></tr>';
        echo '</tbody></table>';
        echo '<details><summary>' . esc_html__( 'Advanced details (optional)', 'taxocommerce-mail-pro' ) . '</summary>';
        echo '<p>' . esc_html__( 'These values are optional for receiving messages. SMTP defaults to the same server, port 465. Change them only if your provider gives different settings.', 'taxocommerce-mail-pro' ) . '</p>';
        echo '<table class="form-table"><tbody>';
        foreach ( array(
            'name' => __( 'Display name', 'taxocommerce-mail-pro' ),
            'email' => __( 'Sender email (defaults to username)', 'taxocommerce-mail-pro' ),
            'smtp_host' => __( 'Outgoing mail server (SMTP)', 'taxocommerce-mail-pro' ),
        ) as $key => $label ) {
            echo '<tr><th scope="row"><label for="tcmailpro_' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label></th><td><input id="tcmailpro_' . esc_attr( $key ) . '" class="regular-text" type="' . ( 'email' === $key ? 'email' : 'text' ) . '" name="account[' . esc_attr( $key ) . ']" maxlength="253" value="' . esc_attr( $selected && isset( $selected[ $key ] ) ? $selected[ $key ] : '' ) . '"></td></tr>';
        }
        echo '<tr><th scope="row"><label for="tcmailpro_port">' . esc_html__( 'SMTP encrypted port', 'taxocommerce-mail-pro' ) . '</label></th><td><select name="account[smtp_port]" id="tcmailpro_port">';
        foreach ( array( 465 => '465 — SSL/TLS', 587 => '587 — STARTTLS' ) as $port => $label ) {
            echo '<option value="' . esc_attr( (string) $port ) . '" ' . selected( $selected ? (int) $selected['smtp_port'] : 465, $port, false ) . '>' . esc_html( $label ) . '</option>';
        }
        echo '</select></td></tr></tbody></table></details>';
        submit_button( $selected ? __( 'Update mailbox', 'taxocommerce-mail-pro' ) : __( 'Add mailbox', 'taxocommerce-mail-pro' ) );
        echo '</form>';
    }

    private static function compose() {
        $account = self::selected_account();
        if ( ! $account ) {
            echo '<p>' . esc_html__( 'Add and select a mailbox first.', 'taxocommerce-mail-pro' ) . '</p>';
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
        echo '<h2>' . esc_html__( 'Compose email', 'taxocommerce-mail-pro' ) . '</h2>';
        echo '<p>' . esc_html__( 'Sending from:', 'taxocommerce-mail-pro' ) . ' <strong>' . esc_html( $account['email'] ) . '</strong></p>';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="tcmailpro_send"><input type="hidden" name="id" value="' . esc_attr( $account['id'] ) . '">';
        wp_nonce_field( 'tcmailpro_send' );
        echo '<table class="form-table"><tr><th scope="row"><label for="tcmailpro_to">' . esc_html__( 'To', 'taxocommerce-mail-pro' ) . '</label></th><td><input id="tcmailpro_to" name="to" type="email" class="regular-text" maxlength="190" required value="' . esc_attr( $to ) . '"></td></tr>';
        echo '<tr><th scope="row"><label for="tcmailpro_subject">' . esc_html__( 'Subject', 'taxocommerce-mail-pro' ) . '</label></th><td><input id="tcmailpro_subject" name="subject" class="large-text" maxlength="250" required value="' . esc_attr( $subject ) . '"></td></tr>';
        echo '<tr><th scope="row"><label for="tcmailpro_body">' . esc_html__( 'Message (plain text)', 'taxocommerce-mail-pro' ) . '</label></th><td><textarea id="tcmailpro_body" name="message" rows="12" maxlength="40000" class="large-text" required></textarea></td></tr></table>';
        submit_button( __( 'Send email', 'taxocommerce-mail-pro' ) );
        echo '</form>';
    }

    public static function render() {
        if ( ! current_user_can( self::CAP ) ) {
            wp_die( esc_html__( 'Permission denied.', 'taxocommerce-mail-pro' ), '', array( 'response' => 403 ) );
        }
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only navigation and display of notices.
        $tab = isset( $_GET['tab'] ) && is_string( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'inbox';
        $notice = isset( $_GET['tcmailpro_notice'] ) && is_string( $_GET['tcmailpro_notice'] ) ? sanitize_key( wp_unslash( $_GET['tcmailpro_notice'] ) ) : '';
        // phpcs:enable WordPress.Security.NonceVerification.Recommended
        // The standalone three-zone family header renders the Pro name.
        echo '<div class="wrap">';
        $tabs = array( 'inbox' => __( 'Inbox', 'taxocommerce-mail-pro' ), 'sent' => __( 'Sent', 'taxocommerce-mail-pro' ), 'folders' => __( 'Folders', 'taxocommerce-mail-pro' ), 'trash' => __( 'Trash', 'taxocommerce-mail-pro' ), 'compose' => __( 'Compose', 'taxocommerce-mail-pro' ), 'accounts' => __( 'Accounts', 'taxocommerce-mail-pro' ), 'autocheck' => __( 'Autocheck', 'taxocommerce-mail-pro' ), 'manual' => __( 'User manual', 'taxocommerce-mail-pro' ), 'technical' => __( 'Technical manual', 'taxocommerce-mail-pro' ) );
        echo '<nav class="nav-tab-wrapper" aria-label="' . esc_attr__( 'Mail sections', 'taxocommerce-mail-pro' ) . '">';
        foreach ( $tabs as $key => $label ) {
            echo '<a href="' . esc_url( add_query_arg( array( 'page' => 'taxocommerce-mail-pro', 'tab' => $key ), admin_url( 'admin.php' ) ) ) . '" class="nav-tab' . ( $key === $tab ? ' nav-tab-active' : '' ) . '">' . esc_html( $label ) . '</a>';
        }
        echo '</nav>';
        $notices = array(
            'saved' => __( 'Mailbox settings saved.', 'taxocommerce-mail-pro' ),
            'removed' => __( 'Mailbox disconnected from WordPress. Server messages were not deleted.', 'taxocommerce-mail-pro' ),
            'invalid' => __( 'Invalid data, missing password or encryption unavailable. Account was not saved.', 'taxocommerce-mail-pro' ),
            'connected' => __( 'IMAP connection succeeded. SMTP delivery has not been tested.', 'taxocommerce-mail-pro' ),
            'connection_failed' => __( 'IMAP connection failed. Check your host, credentials, certificate and server access.', 'taxocommerce-mail-pro' ),
            'sent' => __( 'SMTP server accepted the message. Delivery to the recipient cannot be guaranteed.', 'taxocommerce-mail-pro' ),
            'send_failed' => __( 'SMTP send failed. Check account settings and SMTP restrictions.', 'taxocommerce-mail-pro' ),
            'sent_unarchived' => __( 'SMTP accepted the message, but the Sent-folder copy could not be saved. Check the folder settings.', 'taxocommerce-mail-pro' ),
            'moved' => __( 'Messages moved on the mail server.', 'taxocommerce-mail-pro' ),
            'unsupported' => __( 'This server does not support safe IMAP MOVE. Messages were not changed.', 'taxocommerce-mail-pro' ),
            'operation_failed' => __( 'Mail server rejected the requested action. No local message copies were changed.', 'taxocommerce-mail-pro' ),
            'folder_created' => __( 'Folder created on the mail server.', 'taxocommerce-mail-pro' ),
            'folder_renamed' => __( 'Folder renamed on the mail server.', 'taxocommerce-mail-pro' ),
        );
        if ( isset( $notices[ $notice ] ) ) {
            echo '<div class="notice notice-' . ( in_array( $notice, array( 'saved', 'removed', 'connected', 'sent', 'moved', 'folder_created', 'folder_renamed' ), true ) ? 'success' : 'error' ) . '"><p>' . esc_html( $notices[ $notice ] ) . '</p></div>';
        }
        if ( 'accounts' === $tab ) {
            self::list_accounts();
        } elseif ( 'compose' === $tab ) {
            self::compose();
        } elseif ( 'autocheck' === $tab ) {
            TCMAILPRO_Autocheck::render();
        } elseif ( 'manual' === $tab || 'technical' === $tab ) {
            TCMAILPRO_Autocheck::manual( 'technical' === $tab );
        } else {
            TCMAILPRO_Mailbox::render( in_array( $tab, array( 'inbox', 'sent', 'folders', 'trash' ), true ) ? $tab : 'inbox' );
        }
        echo '</div>';
    }
}
