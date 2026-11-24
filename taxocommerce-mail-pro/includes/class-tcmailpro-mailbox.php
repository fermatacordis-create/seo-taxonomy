<?php
/**
 * Pro mailbox browser and explicit IMAP mutation handlers.
 *
 * @package TaxoCommerceMailPro
 */
defined( 'ABSPATH' ) || exit;

final class TCMAILPRO_Mailbox {
    const CAP = 'manage_options';

    public static function boot() {
        add_action( 'admin_post_tcmailpro_bulk', array( __CLASS__, 'bulk' ) );
        add_action( 'admin_post_tcmailpro_create_folder', array( __CLASS__, 'create_folder' ) );
        add_action( 'admin_post_tcmailpro_rename_folder', array( __CLASS__, 'rename_folder' ) );
    }

    private static function guard( $action ) {
        if ( ! current_user_can( self::CAP ) ) {
            wp_die( esc_html__( 'Permission denied.', 'taxocommerce-mail-pro' ), '', array( 'response' => 403 ) );
        }
        check_admin_referer( $action );
    }

    private static function url( $account, $tab, $folder = '', $extra = array() ) {
        $args = array(
            'page' => 'taxocommerce-mail-pro',
            'tab' => $tab,
            'account' => $account,
        );
        if ( '' !== $folder ) {
            $args['folder'] = $folder;
        }
        return add_query_arg( array_merge( $args, $extra ), admin_url( 'admin.php' ) );
    }

    private static function go( $id, $tab, $folder, $notice ) {
        wp_safe_redirect( self::url( $id, $tab, $folder, array( 'tcmailpro_notice' => $notice ) ) );
        exit;
    }

    public static function bulk() {
        self::guard( 'tcmailpro_bulk' );
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Guarded immediately above.
        $id = isset( $_POST['account'] ) && is_string( $_POST['account'] ) ? sanitize_text_field( wp_unslash( $_POST['account'] ) ) : '';
        $folder = isset( $_POST['folder'] ) && is_string( $_POST['folder'] ) ? sanitize_text_field( wp_unslash( $_POST['folder'] ) ) : '';
        $tab = isset( $_POST['tab'] ) && is_string( $_POST['tab'] ) ? sanitize_key( wp_unslash( $_POST['tab'] ) ) : 'inbox';
        $action = isset( $_POST['bulk_action'] ) && is_string( $_POST['bulk_action'] ) ? sanitize_key( wp_unslash( $_POST['bulk_action'] ) ) : '';
        $target = isset( $_POST['destination'] ) && is_string( $_POST['destination'] ) ? sanitize_text_field( wp_unslash( $_POST['destination'] ) ) : '';
        $uids = isset( $_POST['uid'] ) && is_array( $_POST['uid'] ) ? TCMAILPRO_IMAP::safe_uids( map_deep( wp_unslash( $_POST['uid'] ), 'sanitize_text_field' ) ) : array();
        // phpcs:enable WordPress.Security.NonceVerification.Missing
        if ( ! in_array( $tab, array( 'inbox', 'sent', 'trash', 'folders' ), true ) ) {
            $tab = 'inbox';
        }
        $account = TCMAILPRO_Accounts::credentials( $id );
        if ( ! $account || ! $uids || ! in_array( $action, array( 'move', 'trash', 'restore' ), true ) ) {
            self::go( $id, $tab, $folder, 'invalid' );
        }
        $imap = new TCMAILPRO_IMAP();
        try {
            $imap->connect( $account );
            $folders = $imap->folders();
            if ( ! isset( $folders[ $folder ] ) ) {
                throw new RuntimeException( 'invalid_source' );
            }
            $trash = TCMAILPRO_IMAP::special_folder( $folders, 'trash' );
            if ( 'trash' === $action ) {
                if ( '' === $trash || $folder === $trash ) {
                    throw new RuntimeException( 'trash_unavailable' );
                }
                $target = $trash;
            } elseif ( 'restore' === $action ) {
                if ( '' === $trash || $folder !== $trash ) {
                    throw new RuntimeException( 'restore_only_from_trash' );
                }
                $target = 'INBOX';
            }
            if ( ! isset( $folders[ $target ] ) || $folder === $target ) {
                throw new RuntimeException( 'invalid_target' );
            }
            $imap->move( $folder, $target, $uids );
            $notice = 'moved';
        } catch ( RuntimeException $error ) {
            $notice = 'imap_move_unsupported' === $error->getMessage() ? 'unsupported' : 'operation_failed';
        } finally {
            $imap->disconnect();
        }
        self::go( $id, $tab, $folder, $notice );
    }

    public static function create_folder() {
        self::guard( 'tcmailpro_create_folder' );
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Guarded immediately above.
        $id = isset( $_POST['account'] ) && is_string( $_POST['account'] ) ? sanitize_text_field( wp_unslash( $_POST['account'] ) ) : '';
        $name = isset( $_POST['name'] ) && is_string( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
        // phpcs:enable WordPress.Security.NonceVerification.Missing
        $account = TCMAILPRO_Accounts::credentials( $id );
        if ( ! $account || ! TCMAILPRO_IMAP::valid_folder_name( $name ) ) {
            self::go( $id, 'folders', '', 'invalid' );
        }
        $imap = new TCMAILPRO_IMAP();
        try {
            $imap->connect( $account );
            $folders = $imap->folders();
            if ( isset( $folders[ $name ] ) ) {
                throw new RuntimeException( 'exists' );
            }
            $imap->create_folder( $name );
            $notice = 'folder_created';
        } catch ( RuntimeException $error ) {
            $notice = 'operation_failed';
        } finally {
            $imap->disconnect();
        }
        self::go( $id, 'folders', '', $notice );
    }

    public static function rename_folder() {
        self::guard( 'tcmailpro_rename_folder' );
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Guarded immediately above.
        $id = isset( $_POST['account'] ) && is_string( $_POST['account'] ) ? sanitize_text_field( wp_unslash( $_POST['account'] ) ) : '';
        $old = isset( $_POST['old'] ) && is_string( $_POST['old'] ) ? sanitize_text_field( wp_unslash( $_POST['old'] ) ) : '';
        $name = isset( $_POST['name'] ) && is_string( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
        // phpcs:enable WordPress.Security.NonceVerification.Missing
        $account = TCMAILPRO_Accounts::credentials( $id );
        if ( ! $account || ! TCMAILPRO_IMAP::valid_folder_name( $name ) ) {
            self::go( $id, 'folders', '', 'invalid' );
        }
        $imap = new TCMAILPRO_IMAP();
        try {
            $imap->connect( $account );
            $imap->rename_folder( $old, $name, $imap->folders() );
            $notice = 'folder_renamed';
        } catch ( RuntimeException $error ) {
            $notice = 'operation_failed';
        } finally {
            $imap->disconnect();
        }
        self::go( $id, 'folders', '', $notice );
    }

    private static function account() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only selector.
        $id = isset( $_GET['account'] ) && is_string( $_GET['account'] ) ? sanitize_text_field( wp_unslash( $_GET['account'] ) ) : '';
        $accounts = TCMAILPRO_Accounts::public_list();
        if ( '' === $id && $accounts ) {
            $id = $accounts[0]['id'];
        }
        return $id ? TCMAILPRO_Accounts::credentials( $id ) : false;
    }

    private static function selector( $id, $tab ) {
        echo '<form method="get" action="' . esc_url( admin_url( 'admin.php' ) ) . '">';
        echo '<input type="hidden" name="page" value="taxocommerce-mail-pro"><input type="hidden" name="tab" value="' . esc_attr( $tab ) . '">';
        echo '<label for="tcmailpro_selected">' . esc_html__( 'Mailbox', 'taxocommerce-mail-pro' ) . '</label> ';
        echo '<select id="tcmailpro_selected" name="account">';
        foreach ( TCMAILPRO_Accounts::public_list() as $account ) {
            echo '<option value="' . esc_attr( $account['id'] ) . '" ' . selected( $account['id'], $id, false ) . '>' . esc_html( $account['email'] ) . '</option>';
        }
        echo '</select> ';
        submit_button( __( 'Open', 'taxocommerce-mail-pro' ), 'secondary', 'submit', false );
        echo '</form>';
    }

    private static function reply_address( $from ) {
        if ( preg_match( '/<([^<>]+)>/', $from, $match ) ) {
            $from = $match[1];
        }
        $address = sanitize_email( trim( $from ) );
        return is_email( $address ) ? $address : '';
    }

    public static function render( $tab ) {
        $account = self::account();
        if ( ! $account ) {
            echo '<p>' . esc_html__( 'Add and select a mailbox first.', 'taxocommerce-mail-pro' ) . '</p>';
            return;
        }
        self::selector( $account['id'], $tab );
        $imap = new TCMAILPRO_IMAP();
        try {
            $imap->connect( $account );
            $folders = $imap->folders();
            $trash = TCMAILPRO_IMAP::special_folder( $folders, 'trash' );
            $sent = TCMAILPRO_IMAP::special_folder( $folders, 'sent' );
            // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only navigation.
            $chosen = isset( $_GET['folder'] ) && is_string( $_GET['folder'] ) ? sanitize_text_field( wp_unslash( $_GET['folder'] ) ) : '';
            $uid = isset( $_GET['uid'] ) ? absint( $_GET['uid'] ) : 0;
            $page = isset( $_GET['mail_page'] ) ? max( 1, min( 10000, absint( $_GET['mail_page'] ) ) ) : 1;
            $search = isset( $_GET['search'] ) && is_string( $_GET['search'] ) ? sanitize_text_field( wp_unslash( $_GET['search'] ) ) : '';
            // phpcs:enable WordPress.Security.NonceVerification.Recommended
            $folder = 'inbox' === $tab ? 'INBOX' : ( 'sent' === $tab ? $sent : ( 'trash' === $tab ? $trash : $chosen ) );
            if ( '' === $folder && 'folders' === $tab ) {
                $folder = 'INBOX';
            }
            if ( ! isset( $folders[ $folder ] ) ) {
                echo '<p>' . esc_html__( 'This special folder is not available on the mail server.', 'taxocommerce-mail-pro' ) . '</p>';
                return;
            }
            echo '<div style="display:flex;gap:22px;flex-wrap:wrap;align-items:flex-start">';
            echo '<aside style="flex:0 0 210px;max-width:100%"><h3>' . esc_html__( 'Mail folders', 'taxocommerce-mail-pro' ) . '</h3><ul>';
            foreach ( $folders as $name => $entry ) {
                echo '<li><a href="' . esc_url( self::url( $account['id'], 'folders', $name ) ) . '">' . esc_html( $name ) . '</a></li>';
            }
            echo '</ul></aside><main style="flex:1;min-width:300px">';
            echo '<h2>' . esc_html( $folder ) . ' — ' . esc_html( $account['email'] ) . '</h2>';
            if ( 'folders' === $tab ) {
                self::folder_forms( $account['id'], $folders, $folder );
            }
            if ( $uid ) {
                $imap->select_folder( $folder );
                $message = $imap->message( $uid );
                echo '<p><a href="' . esc_url( self::url( $account['id'], $tab, $folder ) ) . '">' . esc_html__( 'Back to messages', 'taxocommerce-mail-pro' ) . '</a></p>';
                echo '<h3>' . esc_html( $message['subject'] ) . '</h3>';
                echo '<p><strong>' . esc_html__( 'From', 'taxocommerce-mail-pro' ) . ':</strong> ' . esc_html( $message['from'] ) . ' — ' . esc_html( $message['date'] ) . '</p>';
                echo '<div style="white-space:pre-wrap;overflow-wrap:anywhere;background:#fff;padding:16px;border:1px solid #ccd0d4">' . esc_html( $message['body'] ) . '</div>';
                $reply = self::reply_address( $message['reply_to'] );
                if ( $reply ) {
                    echo '<p><a class="button button-primary" href="' . esc_url( self::url( $account['id'], 'compose', '', array( 'to' => $reply, 're' => $message['subject'] ) ) ) . '">' . esc_html__( 'Reply', 'taxocommerce-mail-pro' ) . '</a></p>';
                }
            } else {
                self::message_list( $imap, $account['id'], $tab, $folder, $folders, $trash, $page, $search );
            }
            echo '</main></div>';
        } catch ( RuntimeException $error ) {
            echo '<div class="notice notice-error"><p>' . esc_html__( 'Unable to access the mailbox. Check IMAP settings and permissions or test on a dedicated mailbox.', 'taxocommerce-mail-pro' ) . '</p></div>';
        } finally {
            $imap->disconnect();
        }
    }

    private static function message_list( $imap, $id, $tab, $folder, $folders, $trash, $page, $search ) {
        echo '<form method="get" action="' . esc_url( admin_url( 'admin.php' ) ) . '">';
        echo '<input type="hidden" name="page" value="taxocommerce-mail-pro"><input type="hidden" name="tab" value="' . esc_attr( $tab ) . '"><input type="hidden" name="account" value="' . esc_attr( $id ) . '"><input type="hidden" name="folder" value="' . esc_attr( $folder ) . '">';
        echo '<label for="tcmailpro_search">' . esc_html__( 'Search messages', 'taxocommerce-mail-pro' ) . '</label> ';
        echo '<input type="search" id="tcmailpro_search" name="search" maxlength="80" value="' . esc_attr( $search ) . '"> ';
        submit_button( __( 'Search', 'taxocommerce-mail-pro' ), 'secondary', 'submit', false );
        echo '</form>';
        $result = $imap->messages( $folder, $page, $search );
        /* translators: %d: number of messages matching the current IMAP search. */
        echo '<p>' . esc_html( sprintf( __( '%d matching messages', 'taxocommerce-mail-pro' ), $result['total'] ) ) . '</p>';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        echo '<input type="hidden" name="action" value="tcmailpro_bulk"><input type="hidden" name="account" value="' . esc_attr( $id ) . '"><input type="hidden" name="folder" value="' . esc_attr( $folder ) . '"><input type="hidden" name="tab" value="' . esc_attr( $tab ) . '">';
        wp_nonce_field( 'tcmailpro_bulk' );
        echo '<table class="widefat striped"><thead><tr><th scope="col">' . esc_html__( 'Select', 'taxocommerce-mail-pro' ) . '</th><th scope="col">' . esc_html__( 'From', 'taxocommerce-mail-pro' ) . '</th><th scope="col">' . esc_html__( 'Subject', 'taxocommerce-mail-pro' ) . '</th><th scope="col">' . esc_html__( 'Date', 'taxocommerce-mail-pro' ) . '</th></tr></thead><tbody>';
        foreach ( $result['items'] as $message ) {
            $link = self::url( $id, $tab, $folder, array( 'uid' => $message['uid'] ) );
            echo '<tr><td><label><input type="checkbox" name="uid[]" value="' . esc_attr( (string) $message['uid'] ) . '"><span class="screen-reader-text">' . esc_html__( 'Select message', 'taxocommerce-mail-pro' ) . '</span></label></td><td>' . esc_html( 'sent' === $tab ? $message['to'] : $message['from'] ) . '</td><td><a href="' . esc_url( $link ) . '">' . esc_html( $message['subject'] ) . '</a></td><td>' . esc_html( $message['date'] ) . '</td></tr>';
        }
        if ( ! $result['items'] ) {
            echo '<tr><td colspan="4">' . esc_html__( 'No messages found.', 'taxocommerce-mail-pro' ) . '</td></tr>';
        }
        echo '</tbody></table>';
        echo '<p><label for="tcmailpro_bulk_action">' . esc_html__( 'Selected messages', 'taxocommerce-mail-pro' ) . '</label> ';
        echo '<select name="bulk_action" id="tcmailpro_bulk_action"><option value="move">' . esc_html__( 'Move to folder', 'taxocommerce-mail-pro' ) . '</option>';
        if ( '' !== $trash && $folder !== $trash ) {
            echo '<option value="trash">' . esc_html__( 'Move to Trash', 'taxocommerce-mail-pro' ) . '</option>';
        }
        if ( $folder === $trash && '' !== $trash ) {
            echo '<option value="restore">' . esc_html__( 'Restore to Inbox', 'taxocommerce-mail-pro' ) . '</option>';
        }
        echo '</select> <label for="tcmailpro_destination">' . esc_html__( 'Destination', 'taxocommerce-mail-pro' ) . '</label> ';
        echo '<select name="destination" id="tcmailpro_destination">';
        foreach ( $folders as $name => $entry ) {
            if ( $name !== $folder ) {
                echo '<option value="' . esc_attr( $name ) . '">' . esc_html( $name ) . '</option>';
            }
        }
        echo '</select> ';
        echo '<button class="button button-secondary" type="submit" onclick="return confirm(' . esc_attr( wp_json_encode( __( 'Apply this action to the selected messages?', 'taxocommerce-mail-pro' ) ) ) . ')">' . esc_html__( 'Apply', 'taxocommerce-mail-pro' ) . '</button></p></form>';
        if ( $page > 1 ) {
            echo '<a class="button" href="' . esc_url( self::url( $id, $tab, $folder, array( 'mail_page' => $page - 1, 'search' => $search ) ) ) . '">' . esc_html__( 'Previous', 'taxocommerce-mail-pro' ) . '</a> ';
        }
        if ( $page * 20 < $result['total'] ) {
            echo '<a class="button" href="' . esc_url( self::url( $id, $tab, $folder, array( 'mail_page' => $page + 1, 'search' => $search ) ) ) . '">' . esc_html__( 'Next', 'taxocommerce-mail-pro' ) . '</a>';
        }
    }

    private static function folder_forms( $id, $folders, $selected ) {
        echo '<h3>' . esc_html__( 'Manage personal folders', 'taxocommerce-mail-pro' ) . '</h3>';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="tcmailpro_create_folder"><input type="hidden" name="account" value="' . esc_attr( $id ) . '">';
        wp_nonce_field( 'tcmailpro_create_folder' );
        echo '<label for="tcmailpro_create">' . esc_html__( 'New folder name', 'taxocommerce-mail-pro' ) . '</label> <input type="text" name="name" id="tcmailpro_create" maxlength="60" pattern="[A-Za-z0-9][A-Za-z0-9 _.-]*" required> ';
        submit_button( __( 'Create folder', 'taxocommerce-mail-pro' ), 'secondary', 'submit', false );
        echo '</form>';
        if ( isset( $folders[ $selected ] ) && empty( $folders[ $selected ]['system'] ) ) {
            echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="tcmailpro_rename_folder"><input type="hidden" name="account" value="' . esc_attr( $id ) . '"><input type="hidden" name="old" value="' . esc_attr( $selected ) . '">';
            wp_nonce_field( 'tcmailpro_rename_folder' );
            echo '<label for="tcmailpro_rename">' . esc_html__( 'Rename selected folder', 'taxocommerce-mail-pro' ) . '</label> <input type="text" name="name" id="tcmailpro_rename" maxlength="60" required> ';
            submit_button( __( 'Rename folder', 'taxocommerce-mail-pro' ), 'secondary', 'submit', false );
            echo '</form>';
        }
    }
}
