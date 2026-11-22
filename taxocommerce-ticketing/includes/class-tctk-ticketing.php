<?php
/**
 * Ticketing storage, forms, staff console and private message delivery.
 *
 * @package TaxoCommerceTicketing
 */
defined( 'ABSPATH' ) || exit;

final class TCTK_Ticketing {
    const OPTION = 'tctk_settings';
    const CAP = 'manage_tctk_tickets';
    const SCHEMA = '1';
    const MAX_FILE = 2097152;
    const DOMAIN = 'taxocommerce-ticketing';

    public static function tables() {
        global $wpdb;
        return array(
            'tickets' => $wpdb->prefix . 'tctk_tickets',
            'messages' => $wpdb->prefix . 'tctk_messages',
            'files' => $wpdb->prefix . 'tctk_files',
        );
    }

    public static function defaults() {
        return array(
            'title' => __( 'Contact us', 'taxocommerce-ticketing' ),
            'intro' => __( 'Send us your question or report an issue.', 'taxocommerce-ticketing' ),
            'sender_label' => __( 'Your name', 'taxocommerce-ticketing' ),
            'email_label' => __( 'Email address', 'taxocommerce-ticketing' ),
            'subject_label' => __( 'Subject', 'taxocommerce-ticketing' ),
            'message_label' => __( 'How can we help?', 'taxocommerce-ticketing' ),
            'order_label' => __( 'Order number (optional)', 'taxocommerce-ticketing' ),
            'product_label' => __( 'Product reference (optional)', 'taxocommerce-ticketing' ),
            'button' => __( 'Send request', 'taxocommerce-ticketing' ),
            'categories' => "Product information\nShipping issue\nTechnical support\nOther",
            'palette' => 'blue',
            'logo_id' => 0,
            'notification_email' => get_option( 'admin_email' ),
            'form_page_url' => '',
            'show_order' => 1,
            'show_product' => 1,
            'allow_attachments' => 1,
        );
    }

    public static function settings() {
        return wp_parse_args( (array) get_option( self::OPTION, array() ), self::defaults() );
    }

    public static function activate() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $tables = self::tables();
        $collate = $wpdb->get_charset_collate();
        dbDelta( "CREATE TABLE {$tables['tickets']} (
id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
secret_hash char(64) NOT NULL,
customer_name varchar(190) NOT NULL,
customer_email varchar(190) NOT NULL,
subject varchar(255) NOT NULL,
category varchar(190) NOT NULL,
order_reference varchar(100) NOT NULL DEFAULT '',
product_reference varchar(190) NOT NULL DEFAULT '',
status varchar(24) NOT NULL DEFAULT 'open',
priority varchar(24) NOT NULL DEFAULT 'normal',
visibility varchar(16) NOT NULL DEFAULT 'external',
assigned_to bigint(20) unsigned NOT NULL DEFAULT 0,
task_reference bigint(20) unsigned NOT NULL DEFAULT 0,
created_at datetime NOT NULL,
updated_at datetime NOT NULL,
closed_at datetime DEFAULT NULL,
PRIMARY KEY  (id),
KEY status (status),
KEY visibility (visibility),
KEY assigned_to (assigned_to),
KEY customer_email (customer_email),
KEY updated_at (updated_at)
) $collate;" );
        dbDelta( "CREATE TABLE {$tables['messages']} (
id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
ticket_id bigint(20) unsigned NOT NULL,
author varchar(190) NOT NULL,
is_staff tinyint(1) NOT NULL DEFAULT 0,
is_private tinyint(1) NOT NULL DEFAULT 0,
body longtext NOT NULL,
created_at datetime NOT NULL,
PRIMARY KEY  (id),
KEY ticket_id (ticket_id),
KEY created_at (created_at)
) $collate;" );
        dbDelta( "CREATE TABLE {$tables['files']} (
id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
ticket_id bigint(20) unsigned NOT NULL,
message_id bigint(20) unsigned NOT NULL,
filename varchar(255) NOT NULL,
mime varchar(120) NOT NULL,
byte_count bigint(20) unsigned NOT NULL,
data longblob NOT NULL,
PRIMARY KEY  (id),
KEY ticket_id (ticket_id),
KEY message_id (message_id)
) $collate;" );
        update_option( 'tctk_schema_version', self::SCHEMA, false );
        $administrator = get_role( 'administrator' );
        if ( $administrator ) {
            $administrator->add_cap( self::CAP );
        }
        $manager = get_role( 'shop_manager' );
        if ( $manager ) {
            $manager->add_cap( self::CAP );
        }
        if ( ! get_option( self::OPTION ) ) {
            update_option( self::OPTION, self::defaults(), false );
        }
    }

    public static function boot() {
        add_action( 'init', array( __CLASS__, 'init' ) );
        add_action( 'admin_menu', array( __CLASS__, 'menu' ), 60 );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_assets' ) );
        add_action( 'admin_post_tctk_save_settings', array( __CLASS__, 'save_settings' ) );
        add_action( 'admin_post_tctk_staff', array( __CLASS__, 'staff_action' ) );
        add_action( 'admin_post_tctk_staff_create', array( __CLASS__, 'staff_create' ) );
        add_action( 'admin_post_tctk_submit', array( __CLASS__, 'submit' ) );
        add_action( 'admin_post_nopriv_tctk_submit', array( __CLASS__, 'submit' ) );
        add_action( 'admin_post_tctk_customer_reply', array( __CLASS__, 'customer_reply' ) );
        add_action( 'admin_post_nopriv_tctk_customer_reply', array( __CLASS__, 'customer_reply' ) );
        add_action( 'admin_post_tctk_file', array( __CLASS__, 'download' ) );
        add_action( 'admin_post_nopriv_tctk_file', array( __CLASS__, 'download' ) );
        add_shortcode( 'taxocommerce_ticket_form', array( __CLASS__, 'shortcode' ) );
        add_shortcode( 'taxocommerce_tickets', array( __CLASS__, 'shortcode' ) );
        add_filter( 'plugin_action_links_' . plugin_basename( TCTK_PATH . 'taxocommerce-ticketing.php' ), array( __CLASS__, 'links' ) );
    }

    public static function init() {
        if ( self::SCHEMA !== (string) get_option( 'tctk_schema_version', '' ) ) {
            self::activate();
        }
    }

    public static function admin_assets() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen and tab selection.
        $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen and tab selection.
        $tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';
        if ( 'taxocommerce-ticketing' !== $page || 'settings' !== $tab || ! current_user_can( self::CAP ) ) {
            return;
        }
        wp_enqueue_media();
        wp_enqueue_script( 'tctk-form-admin', TCTK_URL . 'assets/form-admin.js', array(), TCTK_VERSION, true );
    }

    public static function links( $links ) {
        array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=taxocommerce-ticketing&tab=settings' ) ) . '">' . esc_html__( 'Settings', 'taxocommerce-ticketing' ) . '</a>' );
        return $links;
    }

    public static function menu() {
        $parent = function_exists( 'taxocommerce_core' ) || defined( 'TAXOCOMMERCE_CORE_VERSION' ) ? 'taxocommerce-core' : '';
        if ( $parent ) {
            add_submenu_page( $parent, __( 'Tickets', 'taxocommerce-ticketing' ), __( 'Tickets', 'taxocommerce-ticketing' ), self::CAP, 'taxocommerce-ticketing', array( __CLASS__, 'admin' ) );
        } else {
            add_menu_page( __( 'Tickets', 'taxocommerce-ticketing' ), __( 'Tickets', 'taxocommerce-ticketing' ), self::CAP, 'taxocommerce-ticketing', array( __CLASS__, 'admin' ), 'dashicons-tickets-alt', 59 );
        }
    }

    private static function statuses() {
        return array(
            'open' => __( 'Open', 'taxocommerce-ticketing' ),
            'progress' => __( 'In progress', 'taxocommerce-ticketing' ),
            'waiting' => __( 'Waiting for customer', 'taxocommerce-ticketing' ),
            'resolved' => __( 'Resolved', 'taxocommerce-ticketing' ),
            'closed' => __( 'Closed', 'taxocommerce-ticketing' ),
        );
    }

    private static function priorities() {
        return array( 'low' => __( 'Low', 'taxocommerce-ticketing' ), 'normal' => __( 'Normal', 'taxocommerce-ticketing' ), 'high' => __( 'High', 'taxocommerce-ticketing' ), 'urgent' => __( 'Urgent', 'taxocommerce-ticketing' ) );
    }

    private static function palette( $value ) {
        $colors = array(
            'blue' => array( '#1959a6', '#edf4ff', '#17375d' ),
            'green' => array( '#27744d', '#edf9f0', '#1f4c34' ),
            'brown' => array( '#79523c', '#faf1e8', '#593b2d' ),
        );
        return isset( $colors[ $value ] ) ? $colors[ $value ] : $colors['blue'];
    }

    private static function page_url() {
        $link = get_permalink();
        return $link ? $link : home_url( '/' );
    }

    private static function notice( $key ) {
        $messages = array(
            'created' => __( 'Your request has been registered. Check your email for a private follow-up link.', 'taxocommerce-ticketing' ),
            'replied' => __( 'Your reply has been recorded.', 'taxocommerce-ticketing' ),
            'saved' => __( 'Changes saved.', 'taxocommerce-ticketing' ),
            'error' => __( 'Unable to save your request. Check the fields and attachment.', 'taxocommerce-ticketing' ),
            'mail' => __( 'Request saved, but email notification could not be confirmed. Please contact the site administrator.', 'taxocommerce-ticketing' ),
            'rate' => __( 'Too many requests. Please try again later.', 'taxocommerce-ticketing' ),
        );
        return isset( $messages[ $key ] ) ? $messages[ $key ] : '';
    }

    public static function shortcode() {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only route selection or authorization by ticket token.

        $settings = self::settings();
        $colors = self::palette( $settings['palette'] );
        $token = isset( $_GET['tctk_key'] ) ? sanitize_text_field( wp_unslash( $_GET['tctk_key'] ) ) : '';
        $ticket_id = isset( $_GET['tctk_ticket'] ) ? absint( $_GET['tctk_ticket'] ) : 0;
        $notice = isset( $_GET['tctk_notice'] ) ? sanitize_key( wp_unslash( $_GET['tctk_notice'] ) ) : '';
        $ticket = $ticket_id && $token ? self::authorized_ticket( $ticket_id, $token ) : null;
        ob_start();
        echo '<section class="tctk-public" style="--tctk-accent:' . esc_attr( $colors[0] ) . ';--tctk-light:' . esc_attr( $colors[1] ) . ';--tctk-ink:' . esc_attr( $colors[2] ) . '">';
        echo '<style>.tctk-public{max-width:740px;margin:1.5em auto;padding:28px;border:1px solid #dadde1;border-radius:14px;color:var(--tctk-ink);background:var(--tctk-light)}.tctk-public h2{margin:0 0 12px}.tctk-public label{display:block;font-weight:600;margin:15px 0 5px}.tctk-public input:not([type=checkbox]),.tctk-public textarea,.tctk-public select{display:block;box-sizing:border-box;width:100%;max-width:100%;padding:9px;border:1px solid #aab4bd;border-radius:6px;background:white;color:#18222e}.tctk-public button{background:var(--tctk-accent);color:white;border:0;border-radius:6px;padding:11px 20px;margin-top:16px;cursor:pointer}.tctk-public .tctk-message{border-top:1px solid #ccc;padding:12px 0}.tctk-public .tctk-alert{padding:12px;background:white;border-left:3px solid var(--tctk-accent)}.tctk-public .tctk-logo{max-height:80px;max-width:250px;object-fit:contain;margin-bottom:12px}.tctk-public .tctk-muted{opacity:.8;font-size:.9em}.tctk-public .tctk-files a{margin-right:12px}</style>';
        if ( $settings['logo_id'] ) {
            echo wp_get_attachment_image( absint( $settings['logo_id'] ), 'medium', false, array( 'class' => 'tctk-logo', 'alt' => '' ) );
        }
        echo '<h2>' . esc_html( $settings['title'] ) . '</h2><p>' . esc_html( $settings['intro'] ) . '</p>';
        if ( self::notice( $notice ) ) {
            echo '<p class="tctk-alert" role="status">' . esc_html( self::notice( $notice ) ) . '</p>';
        }
        if ( $ticket ) {
            self::render_customer_ticket( $ticket, $token );
        } elseif ( $ticket_id ) {
            echo '<p>' . esc_html__( 'This private ticket link is invalid or expired.', 'taxocommerce-ticketing' ) . '</p>';
        } else {
            self::render_form( $settings );
        }
        echo '</section>';
        return ob_get_clean();
    }

    // phpcs:enable WordPress.Security.NonceVerification.Recommended

    private static function render_form( $s ) {
        $categories = self::categories( $s['categories'] );
        echo '<form method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        echo '<input type="hidden" name="action" value="tctk_submit"/>';
        echo '<input type="hidden" name="return_url" value="' . esc_url( self::page_url() ) . '"/>';
        wp_nonce_field( 'tctk_submit', 'tctk_nonce' );
        echo '<label for="tctk_name">' . esc_html( $s['sender_label'] ) . '</label><input id="tctk_name" name="name" maxlength="190" required/>';
        echo '<label for="tctk_email">' . esc_html( $s['email_label'] ) . '</label><input id="tctk_email" type="email" name="email" maxlength="190" required/>';
        echo '<label for="tctk_subject">' . esc_html( $s['subject_label'] ) . '</label><input id="tctk_subject" name="subject" maxlength="255" required/>';
        if ( $categories ) {
            echo '<label for="tctk_category">' . esc_html__( 'Type of request', 'taxocommerce-ticketing' ) . '</label><select id="tctk_category" name="category" required>';
            foreach ( $categories as $cat ) {
                echo '<option value="' . esc_attr( $cat ) . '">' . esc_html( $cat ) . '</option>';
            }
            echo '</select>';
        }
        if ( $s['show_order'] ) {
            echo '<label for="tctk_order">' . esc_html( $s['order_label'] ) . '</label><input id="tctk_order" name="order_reference" maxlength="100"/>';
        }
        if ( $s['show_product'] ) {
            echo '<label for="tctk_product">' . esc_html( $s['product_label'] ) . '</label><input id="tctk_product" name="product_reference" maxlength="190"/>';
        }
        echo '<label for="tctk_body">' . esc_html( $s['message_label'] ) . '</label><textarea id="tctk_body" name="body" rows="7" maxlength="10000" required></textarea>';
        if ( $s['allow_attachments'] ) {
            self::file_field();
        }
        echo '<p class="tctk-muted">' . esc_html__( 'Your details will only be used to manage this request. Do not attach passwords or payment card information.', 'taxocommerce-ticketing' ) . '</p>';
        // Honeypot for common automated spam; invisible to human users.
        echo '<div aria-hidden="true" style="position:absolute;left:-10000px"><label for="tctk_website">Website</label><input id="tctk_website" name="website" tabindex="-1" autocomplete="off"/></div>';
        echo '<button type="submit">' . esc_html( $s['button'] ) . '</button></form>';
    }

    private static function file_field() {
        echo '<label for="tctk_upload">' . esc_html__( 'Attachment (optional, max. 2 MB: PDF, JPG, PNG or TXT)', 'taxocommerce-ticketing' ) . '</label><input type="file" id="tctk_upload" name="tctk_upload" accept=".pdf,.jpg,.jpeg,.png,.txt"/>';
    }

    private static function categories( $raw ) {
        $lines = preg_split( '/\r\n|\r|\n/', (string) $raw );
        $out = array();
        foreach ( $lines as $line ) {
            $name = sanitize_text_field( $line );
            if ( '' !== $name ) {
                $out[] = $name;
            }
        }
        return array_slice( array_unique( $out ), 0, 20 );
    }

    private static function signed_hash( $token ) {
        return hash_hmac( 'sha256', $token, wp_salt( 'auth' ) );
    }

    private static function authorized_ticket( $id, $token ) {
        if ( ! preg_match( '/^[a-f0-9]{64}$/', $token ) ) {
            return null;
        }
        global $wpdb;
        $t = self::tables();
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Ticket-owned private table; check current state.
        $ticket = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', $t['tickets'], $id ) );
        return $ticket && 'external' === $ticket->visibility && hash_equals( (string) $ticket->secret_hash, self::signed_hash( $token ) ) ? $ticket : null;
    }

    private static function redirect( $url, $notice, $id = 0, $token = '' ) {
        $params = array( 'tctk_notice' => $notice );
        if ( $id && $token ) {
            $params['tctk_ticket'] = $id;
            $params['tctk_key'] = $token;
        }
        wp_safe_redirect( add_query_arg( $params, $url ) );
        exit;
    }

    private static function return_url( $input ) {
        $url = esc_url_raw( (string) $input );
        return wp_validate_redirect( $url, home_url( '/' ) );
    }

    private static function public_rate_limit( $key ) {
        $ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
        $fingerprint = hash_hmac( 'sha256', $key . '|' . $ip, wp_salt( 'nonce' ) );
        $transient = 'tctk_rate_' . substr( $fingerprint, 0, 32 );
        $count = (int) get_transient( $transient );
        if ( $count >= 5 ) {
            return false;
        }
        set_transient( $transient, $count + 1, HOUR_IN_SECONDS );
        return true;
    }

    public static function submit() {
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Handler verifies nonce before processing user input.
        if ( ! isset( $_POST['tctk_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['tctk_nonce'] ) ), 'tctk_submit' ) ) {
            wp_die( esc_html__( 'Invalid form session.', 'taxocommerce-ticketing' ), '', array( 'response' => 403 ) );
        }
        $return = self::return_url( isset( $_POST['return_url'] ) ? esc_url_raw( wp_unslash( $_POST['return_url'] ) ) : '' );
        if ( ! empty( $_POST['website'] ) ) {
            TCTK_Event_Log::record( 'honeypot_rejected' );
            self::redirect( $return, 'error' );
        }
        if ( ! self::public_rate_limit( 'new' ) ) {
            TCTK_Event_Log::record( 'rate_limited' );
            self::redirect( $return, 'rate' );
        }
        $name = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
        $email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
        $subject = isset( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : '';
        $body = isset( $_POST['body'] ) ? sanitize_textarea_field( wp_unslash( $_POST['body'] ) ) : '';
        $category = isset( $_POST['category'] ) ? sanitize_text_field( wp_unslash( $_POST['category'] ) ) : '';
        $s = self::settings();
        $allowed = self::categories( $s['categories'] );
        if ( ! $name || ! is_email( $email ) || ! $subject || ! $body || strlen( $body ) > 40000 || strlen( $name ) > 190 || strlen( $subject ) > 255 || ( $allowed && ! in_array( $category, $allowed, true ) ) || ! self::valid_upload() ) {
            TCTK_Event_Log::record( 'validation_rejected' );
            self::redirect( $return, 'error' );
        }
        $order = $s['show_order'] && isset( $_POST['order_reference'] ) ? sanitize_text_field( wp_unslash( $_POST['order_reference'] ) ) : '';
        $product = $s['show_product'] && isset( $_POST['product_reference'] ) ? sanitize_text_field( wp_unslash( $_POST['product_reference'] ) ) : '';
        $token = bin2hex( random_bytes( 32 ) );
        global $wpdb;
        $tables = self::tables();
        $now = current_time( 'mysql' );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Ticket-owned table.
        $inserted = $wpdb->insert( $tables['tickets'], array(
            'secret_hash' => self::signed_hash( $token ), 'customer_name' => $name, 'customer_email' => $email,
            'subject' => $subject, 'category' => $category, 'order_reference' => substr( $order, 0, 100 ),
            'product_reference' => substr( $product, 0, 190 ), 'status' => 'open', 'priority' => 'normal',
            'visibility' => 'external', 'created_at' => $now, 'updated_at' => $now,
        ) );
        if ( ! $inserted ) {
            TCTK_Event_Log::record( 'ticket_insert_failed' );
            self::redirect( $return, 'error' );
        }
        $id = (int) $wpdb->insert_id;
        $message = self::add_message( $id, $name, $body, false, false );
        if ( ! $message ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Roll back ticket with no message.
            $wpdb->delete( $tables['tickets'], array( 'id' => $id ), array( '%d' ) );
            TCTK_Event_Log::record( 'message_insert_failed', $id );
            self::redirect( $return, 'error' );
        }
        if ( ! empty( $s['allow_attachments'] ) ) {
            self::save_upload( $id, $message );
        }
        $link = add_query_arg( array( 'tctk_ticket' => $id, 'tctk_key' => $token ), $return );
        $customer_sent = wp_mail( $email, sprintf( /* translators: 1: ticket number, 2: ticket subject. */
            __( 'Ticket #%1$d received: %2$s', 'taxocommerce-ticketing' ), $id, $subject ), sprintf( __( "We received your request.\n\nTrack and reply securely here:\n%s\n\nDo not share this private link.", 'taxocommerce-ticketing' ), $link ) );
        if ( ! $customer_sent ) {
            TCTK_Event_Log::record( 'mail_customer_failed', $id );
        }
        self::notify_staff( $id, $subject, $body );
        TCTK_Event_Log::record( 'ticket_created', $id );
        self::redirect( $return, $customer_sent ? 'created' : 'mail', $id, $token );
    }

    // phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Files are checked for upload error, size, MIME, extension, and uploaded-file provenance.
    // phpcs:enable WordPress.Security.NonceVerification.Missing

    // phpcs:disable WordPress.Security.NonceVerification.Missing -- Private upload helpers are only invoked after verified form handlers.
    private static function valid_upload() {
        if ( ! isset( $_FILES['tctk_upload']['error'] ) || UPLOAD_ERR_NO_FILE === (int) $_FILES['tctk_upload']['error'] ) {
            return true;
        }
        $f = $_FILES['tctk_upload'];
        if ( ! isset( $f['error'], $f['size'], $f['tmp_name'], $f['name'] ) || UPLOAD_ERR_OK !== (int) $f['error'] || $f['size'] > self::MAX_FILE || $f['size'] <= 0 || ! is_uploaded_file( $f['tmp_name'] ) ) {
            return false;
        }
        $mime = wp_check_filetype_and_ext( $f['tmp_name'], sanitize_file_name( $f['name'] ) );
        return in_array( $mime['ext'], array( 'pdf', 'jpg', 'jpeg', 'png', 'txt' ), true ) && in_array( $mime['type'], array( 'application/pdf', 'image/jpeg', 'image/png', 'text/plain' ), true );
    }

    private static function save_upload( $ticket_id, $message_id ) {
        if ( ! isset( $_FILES['tctk_upload']['error'] ) || UPLOAD_ERR_NO_FILE === (int) $_FILES['tctk_upload']['error'] ) {
            return;
        }
        if ( ! self::valid_upload() ) {
            TCTK_Event_Log::record( 'attachment_rejected', $ticket_id );
            return;
        }
        $f = $_FILES['tctk_upload'];
        $name = sanitize_file_name( $f['name'] );
        $mime = wp_check_filetype_and_ext( $f['tmp_name'], $name );
        $data = file_get_contents( $f['tmp_name'] );
        if ( false === $data || strlen( $data ) > self::MAX_FILE ) {
            TCTK_Event_Log::record( 'attachment_save_failed', $ticket_id );
            return;
        }
        global $wpdb;
        $tables = self::tables();
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Private binary storage avoids publicly served uploads.
        $ok = $wpdb->insert( $tables['files'], array(
            'ticket_id' => $ticket_id, 'message_id' => $message_id, 'filename' => $name,
            'mime' => $mime['type'], 'byte_count' => strlen( $data ), 'data' => $data,
        ), array( '%d', '%d', '%s', '%s', '%d', '%s' ) );
        if ( ! $ok ) {
            TCTK_Event_Log::record( 'attachment_save_failed', $ticket_id );
        }
    }

    // phpcs:enable WordPress.Security.NonceVerification.Missing
    // phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
    /**
     * Persist a message through the same path used by real submissions.
     * Caller must establish ticket access before invoking this internal service.
     *
     * @internal
     */
    public static function add_message( $ticket_id, $author, $body, $staff, $private ) {
        global $wpdb;
        $tables = self::tables();
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Ticket-owned message history.
        $done = $wpdb->insert( $tables['messages'], array(
            'ticket_id' => $ticket_id, 'author' => $author, 'is_staff' => $staff ? 1 : 0,
            'is_private' => $private ? 1 : 0, 'body' => $body, 'created_at' => current_time( 'mysql' ),
        ) );
        if ( $done ) {
            // Preserve the generated message ID before issuing a second SQL statement.
            $message_id = (int) $wpdb->insert_id;
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Ticket-owned timestamp.
            $updated = $wpdb->update( $tables['tickets'], array( 'updated_at' => current_time( 'mysql' ) ), array( 'id' => $ticket_id ) );
            if ( false === $updated ) {
                TCTK_Event_Log::record( 'ticket_update_failed', $ticket_id );
            }
            return $message_id;
        }
        return 0;
    }

    private static function notify_staff( $id, $subject, $body ) {
        $email = sanitize_email( self::settings()['notification_email'] );
        if ( is_email( $email ) ) {
            if ( ! wp_mail( $email, sprintf( /* translators: 1: ticket number, 2: ticket subject. */
            __( 'New activity on ticket #%1$d: %2$s', 'taxocommerce-ticketing' ), $id, $subject ), $body . "\n\n" . admin_url( 'admin.php?page=taxocommerce-ticketing&ticket=' . $id ) ) ) {
                TCTK_Event_Log::record( 'mail_staff_failed', $id );
            }
        }
    }

    private static function messages( $id, $staff ) {
        global $wpdb;
        $tables = self::tables();
        if ( $staff ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Read private ticket timeline.
            return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE ticket_id = %d ORDER BY id ASC', $tables['messages'], $id ) );
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Customer can only read non-private messages.
        return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE ticket_id = %d AND is_private = 0 ORDER BY id ASC', $tables['messages'], $id ) );
    }

    private static function render_messages( $ticket, $staff, $token = '' ) {
        global $wpdb;
        $tables = self::tables();
        foreach ( self::messages( (int) $ticket->id, $staff ) as $message ) {
            echo '<div class="tctk-message"><strong>' . esc_html( $message->author ) . '</strong> <small>' . esc_html( $message->created_at ) . '</small>';
            if ( $message->is_private ) {
                echo ' <em>' . esc_html__( 'Internal note', 'taxocommerce-ticketing' ) . '</em>';
            }
            echo '<p>' . nl2br( esc_html( $message->body ) ) . '</p>';
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Ticket-scoped attachment references.
            $files = $wpdb->get_results( $wpdb->prepare( 'SELECT id, filename FROM %i WHERE ticket_id = %d AND message_id = %d', $tables['files'], $ticket->id, $message->id ) );
            if ( $files ) {
                echo '<div class="tctk-files">';
                foreach ( $files as $file ) {
                    $args = array( 'action' => 'tctk_file', 'file' => $file->id );
                    if ( ! $staff ) {
                        $args['tctk_key'] = $token;
                        $args['tctk_ticket'] = $ticket->id;
                    }
                    echo '<a href="' . esc_url( add_query_arg( $args, admin_url( 'admin-post.php' ) ) ) . '">' . esc_html( $file->filename ) . '</a>';
                }
                echo '</div>';
            }
            echo '</div>';
        }
    }

    private static function render_customer_ticket( $ticket, $token ) {
        echo '<h3>' . esc_html( sprintf( /* translators: 1: ticket number, 2: ticket subject. */
            __( 'Ticket #%1$d — %2$s', 'taxocommerce-ticketing' ), $ticket->id, $ticket->subject ) ) . '</h3>';
        $statuses = self::statuses();
        echo '<p><strong>' . esc_html__( 'Status', 'taxocommerce-ticketing' ) . ':</strong> ' . esc_html( isset( $statuses[$ticket->status] ) ? $statuses[$ticket->status] : $ticket->status ) . '</p>';
        self::render_messages( $ticket, false, $token );
        if ( 'closed' === $ticket->status ) {
            echo '<p>' . esc_html__( 'This ticket is closed. Please open a new request.', 'taxocommerce-ticketing' ) . '</p>';
            return;
        }
        echo '<form method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        echo '<input type="hidden" name="action" value="tctk_customer_reply"/><input type="hidden" name="ticket" value="' . esc_attr( $ticket->id ) . '"/><input type="hidden" name="token" value="' . esc_attr( $token ) . '"/>';
        echo '<input type="hidden" name="return_url" value="' . esc_url( self::page_url() ) . '"/>';
        wp_nonce_field( 'tctk_reply_' . $ticket->id, 'tctk_nonce' );
        echo '<label for="tctk_reply">' . esc_html__( 'Your reply', 'taxocommerce-ticketing' ) . '</label><textarea id="tctk_reply" name="body" maxlength="10000" rows="5" required></textarea>';
        if ( self::settings()['allow_attachments'] ) {
            self::file_field();
        }
        echo '<button type="submit">' . esc_html__( 'Send reply', 'taxocommerce-ticketing' ) . '</button></form>';
    }

    public static function customer_reply() {
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Handler verifies nonce before processing user input.
        $id = isset( $_POST['ticket'] ) ? absint( $_POST['ticket'] ) : 0;
        $nonce = isset( $_POST['tctk_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['tctk_nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'tctk_reply_' . $id ) ) {
            wp_die( esc_html__( 'Invalid form session.', 'taxocommerce-ticketing' ), '', array( 'response' => 403 ) );
        }
        $token = isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : '';
        $ticket = self::authorized_ticket( $id, $token );
        if ( ! $ticket || 'closed' === $ticket->status ) {
            wp_die( esc_html__( 'Ticket access denied.', 'taxocommerce-ticketing' ), '', array( 'response' => 403 ) );
        }
        $return = self::return_url( isset( $_POST['return_url'] ) ? esc_url_raw( wp_unslash( $_POST['return_url'] ) ) : '' );
        if ( ! self::public_rate_limit( 'reply_' . $id ) ) {
            TCTK_Event_Log::record( 'rate_limited', $id );
            self::redirect( $return, 'rate', $id, $token );
        }
        $body = isset( $_POST['body'] ) ? sanitize_textarea_field( wp_unslash( $_POST['body'] ) ) : '';
        if ( ! $body || strlen( $body ) > 40000 || ! self::valid_upload() ) {
            TCTK_Event_Log::record( 'validation_rejected', $id );
            self::redirect( $return, 'error', $id, $token );
        }
        $mid = self::add_message( $id, $ticket->customer_name, $body, false, false );
        if ( ! $mid ) {
            TCTK_Event_Log::record( 'customer_reply_failed', $id );
            self::redirect( $return, 'error', $id, $token );
        }
        if ( self::settings()['allow_attachments'] ) {
            self::save_upload( $id, $mid );
        }
        self::notify_staff( $id, $ticket->subject, $body );
        TCTK_Event_Log::record( 'customer_reply_saved', $id );
        self::redirect( $return, 'replied', $id, $token );
    }

    // phpcs:enable WordPress.Security.NonceVerification.Missing

    public static function download() {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only route selection or authorization by ticket token.

        $id = isset( $_GET['file'] ) ? absint( $_GET['file'] ) : 0;
        global $wpdb;
        $tables = self::tables();
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Private attachment read with ACL below.
        $file = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', $tables['files'], $id ) );
        if ( ! $file ) {
            wp_die( esc_html__( 'File not found.', 'taxocommerce-ticketing' ), '', array( 'response' => 404 ) );
        }
        $staff = current_user_can( self::CAP );
        $ticket_id = isset( $_GET['tctk_ticket'] ) ? absint( $_GET['tctk_ticket'] ) : 0;
        $token = isset( $_GET['tctk_key'] ) ? sanitize_text_field( wp_unslash( $_GET['tctk_key'] ) ) : '';
        $ticket = ! $staff && $ticket_id === (int) $file->ticket_id ? self::authorized_ticket( $ticket_id, $token ) : null;
        if ( ! $staff && ! $ticket ) {
            wp_die( esc_html__( 'File access denied.', 'taxocommerce-ticketing' ), '', array( 'response' => 403 ) );
        }
        if ( ! $staff ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Check the attachment belongs to a public message.
            $public = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE id = %d AND ticket_id = %d AND is_private = 0', $tables['messages'], $file->message_id, $file->ticket_id ) );
            if ( ! $public ) {
                wp_die( esc_html__( 'File access denied.', 'taxocommerce-ticketing' ), '', array( 'response' => 403 ) );
            }
        }
        nocache_headers();
        header( 'X-Content-Type-Options: nosniff' );
        header( 'Content-Type: application/octet-stream' );
        header( 'Content-Disposition: attachment; filename="' . str_replace( array( '"', "\r", "\n" ), '', sanitize_file_name( $file->filename ) ) . '"' );
        header( 'Content-Length: ' . (int) $file->byte_count );
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Binary attachment, explicitly streamed after ACL.
        echo $file->data;
        exit;
    }

    // phpcs:enable WordPress.Security.NonceVerification.Recommended

    public static function save_settings() {
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Handler verifies nonce before processing user input.
        if ( ! current_user_can( self::CAP ) ) {
            wp_die( esc_html__( 'Access denied.', 'taxocommerce-ticketing' ), '', array( 'response' => 403 ) );
        }
        check_admin_referer( 'tctk_save_settings' );
        $input = isset( $_POST['settings'] ) && is_array( $_POST['settings'] ) ? map_deep( wp_unslash( $_POST['settings'] ), 'sanitize_text_field' ) : array();
        $default = self::defaults();
        $s = array();
        foreach ( $default as $key => $original ) {
            if ( in_array( $key, array( 'logo_id', 'show_order', 'show_product', 'allow_attachments' ), true ) ) {
                $s[$key] = 'logo_id' === $key ? absint( isset( $input[$key] ) ? $input[$key] : 0 ) : ( empty( $input[$key] ) ? 0 : 1 );
            } elseif ( 'notification_email' === $key ) {
                $candidate = isset( $input[$key] ) ? sanitize_email( $input[$key] ) : '';
                $s[$key] = is_email( $candidate ) ? $candidate : get_option( 'admin_email' );
            } elseif ( 'palette' === $key ) {
                $candidate = isset( $input[$key] ) ? sanitize_key( $input[$key] ) : 'blue';
                $s[$key] = in_array( $candidate, array( 'blue', 'green', 'brown' ), true ) ? $candidate : 'blue';
            } elseif ( 'form_page_url' === $key ) {
                $url = isset( $input[$key] ) ? esc_url_raw( $input[$key] ) : '';
                $s[$key] = $url && wp_validate_redirect( $url, false ) ? $url : '';
            } elseif ( 'categories' === $key ) {
                $s[$key] = implode( "\n", self::categories( isset( $input[$key] ) ? sanitize_textarea_field( $input[$key] ) : '' ) );
            } else {
                $s[$key] = substr( isset( $input[$key] ) ? sanitize_text_field( $input[$key] ) : '', 0, 300 );
            }
        }
        update_option( self::OPTION, $s, false );
        wp_safe_redirect( add_query_arg( array( 'page' => 'taxocommerce-ticketing', 'tab' => 'settings', 'notice' => 'saved' ), admin_url( 'admin.php' ) ) );
        exit;
    }


    // phpcs:enable WordPress.Security.NonceVerification.Missing

    public static function staff_create() {
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Handler verifies nonce before processing user input.
        if ( ! current_user_can( self::CAP ) ) {
            wp_die( esc_html__( 'Access denied.', 'taxocommerce-ticketing' ), '', array( 'response' => 403 ) );
        }
        check_admin_referer( 'tctk_staff_create' );
        $name = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
        $email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
        $subject = isset( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : '';
        $body = isset( $_POST['body'] ) ? sanitize_textarea_field( wp_unslash( $_POST['body'] ) ) : '';
        $visibility = isset( $_POST['visibility'] ) ? sanitize_key( wp_unslash( $_POST['visibility'] ) ) : 'internal';
        if ( ! $name || ! $subject || ! $body || strlen( $body ) > 40000 || strlen( $name ) > 190 || strlen( $subject ) > 255 || ! in_array( $visibility, array( 'internal', 'external' ), true ) || ( 'external' === $visibility && ! is_email( $email ) ) ) {
            wp_die( esc_html__( 'Invalid ticket data.', 'taxocommerce-ticketing' ), '', array( 'response' => 400 ) );
        }
        if ( 'external' === $visibility && ! self::settings()['form_page_url'] ) {
            wp_die( esc_html__( 'Set the public form page URL in Form settings before creating a customer-facing ticket.', 'taxocommerce-ticketing' ), '', array( 'response' => 400 ) );
        }
        global $wpdb;
        $tables = self::tables();
        $token = bin2hex( random_bytes( 32 ) );
        $now = current_time( 'mysql' );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Ticket-owned table.
        $created = $wpdb->insert( $tables['tickets'], array(
            'secret_hash' => self::signed_hash( $token ), 'customer_name' => $name,
            'customer_email' => 'external' === $visibility ? $email : '',
            'subject' => $subject, 'category' => '', 'status' => 'open',
            'priority' => 'normal', 'visibility' => $visibility,
            'assigned_to' => get_current_user_id(), 'created_at' => $now, 'updated_at' => $now,
        ) );
        if ( ! $created ) {
            TCTK_Event_Log::record( 'staff_ticket_failed' );
            wp_die( esc_html__( 'Unable to save the ticket.', 'taxocommerce-ticketing' ), '', array( 'response' => 500 ) );
        }
        $id = (int) $wpdb->insert_id;
        $mid = self::add_message( $id, $name, $body, false, false );
        if ( ! $mid ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Revert ticket without message.
            $wpdb->delete( $tables['tickets'], array( 'id' => $id ), array( '%d' ) );
            TCTK_Event_Log::record( 'message_insert_failed', $id );
            wp_die( esc_html__( 'Unable to save the message.', 'taxocommerce-ticketing' ), '', array( 'response' => 500 ) );
        }
        if ( self::valid_upload() ) {
            self::save_upload( $id, $mid );
        }
        if ( 'external' === $visibility ) {
            // Form URL is configured by the shop owner; never assume this admin page is the customer-facing page.
            $page = self::settings()['form_page_url'];
            if ( $page && wp_validate_redirect( $page, false ) ) {
                $link = add_query_arg( array( 'tctk_ticket' => $id, 'tctk_key' => $token ), $page );
                wp_mail( $email, sprintf( /* translators: 1: ticket number, 2: ticket subject. */
            __( 'Ticket #%1$d received: %2$s', 'taxocommerce-ticketing' ), $id, $subject ), sprintf( __( "We received your request.\n\nTrack and reply securely here:\n%s\n\nDo not share this private link.", 'taxocommerce-ticketing' ), $link ) );
            }
        }
        TCTK_Event_Log::record( 'staff_ticket_created', $id );
        wp_safe_redirect( add_query_arg( array( 'page' => 'taxocommerce-ticketing', 'ticket' => $id, 'notice' => 'saved' ), admin_url( 'admin.php' ) ) );
        exit;
    }

    // phpcs:enable WordPress.Security.NonceVerification.Missing

    private static function create_admin_form() {
        echo '<h2>' . esc_html__( 'Create ticket', 'taxocommerce-ticketing' ) . '</h2>';
        echo '<p>' . esc_html__( 'Create an internal ticket, or create a customer ticket on behalf of someone. Internal tickets stay visible only to staff.', 'taxocommerce-ticketing' ) . '</p>';
        echo '<form method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        echo '<input type="hidden" name="action" value="tctk_staff_create"/>';
        wp_nonce_field( 'tctk_staff_create' );
        echo '<table class="form-table"><tr><th scope="row"><label for="tctk_visibility">' . esc_html__( 'Visibility', 'taxocommerce-ticketing' ) . '</label></th><td><select id="tctk_visibility" name="visibility"><option value="internal">' . esc_html__( 'Internal', 'taxocommerce-ticketing' ) . '</option><option value="external">' . esc_html__( 'Customer-facing', 'taxocommerce-ticketing' ) . '</option></select></td></tr>';
        echo '<tr><th scope="row"><label for="tctk_name">' . esc_html__( 'Requester name', 'taxocommerce-ticketing' ) . '</label></th><td><input id="tctk_name" name="name" class="regular-text" required maxlength="190"/></td></tr>';
        echo '<tr><th scope="row"><label for="tctk_email">' . esc_html__( 'Customer email (required for external tickets)', 'taxocommerce-ticketing' ) . '</label></th><td><input id="tctk_email" name="email" type="email" class="regular-text"/></td></tr>';
        echo '<tr><th scope="row"><label for="tctk_subject">' . esc_html__( 'Subject', 'taxocommerce-ticketing' ) . '</label></th><td><input id="tctk_subject" name="subject" class="regular-text" required maxlength="255"/></td></tr>';
        echo '<tr><th scope="row"><label for="tctk_body">' . esc_html__( 'Message', 'taxocommerce-ticketing' ) . '</label></th><td><textarea id="tctk_body" name="body" rows="8" cols="70" required maxlength="10000"></textarea></td></tr></table>';
        self::file_field();
        submit_button( __( 'Create ticket', 'taxocommerce-ticketing' ) );
        echo '</form>';
    }

    public static function staff_action() {
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Handler verifies nonce before processing user input.
        if ( ! current_user_can( self::CAP ) ) {
            wp_die( esc_html__( 'Access denied.', 'taxocommerce-ticketing' ), '', array( 'response' => 403 ) );
        }
        check_admin_referer( 'tctk_staff' );
        global $wpdb;
        $tables = self::tables();
        $id = isset( $_POST['ticket'] ) ? absint( $_POST['ticket'] ) : 0;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Private ticket management.
        $ticket = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', $tables['tickets'], $id ) );
        if ( ! $ticket ) {
            wp_die( esc_html__( 'Ticket not found.', 'taxocommerce-ticketing' ), '', array( 'response' => 404 ) );
        }
        $status = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : $ticket->status;
        $priority = isset( $_POST['priority'] ) ? sanitize_key( wp_unslash( $_POST['priority'] ) ) : $ticket->priority;
        $assignee = isset( $_POST['assigned_to'] ) ? absint( $_POST['assigned_to'] ) : 0;
        if ( ! isset( self::statuses()[$status] ) || ! isset( self::priorities()[$priority] ) || ( $assignee && ! get_user_by( 'id', $assignee ) ) ) {
            wp_die( esc_html__( 'Invalid ticket data.', 'taxocommerce-ticketing' ), '', array( 'response' => 400 ) );
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Ticket-owned fields.
        $updated = $wpdb->update( $tables['tickets'], array(
            'status' => $status, 'priority' => $priority, 'assigned_to' => $assignee,
            'closed_at' => 'closed' === $status ? current_time( 'mysql' ) : null,
            'updated_at' => current_time( 'mysql' ),
        ), array( 'id' => $id ) );
        if ( false === $updated ) {
            TCTK_Event_Log::record( 'staff_update_failed', $id );
            wp_die( esc_html__( 'Unable to update the ticket.', 'taxocommerce-ticketing' ), '', array( 'response' => 500 ) );
        }
        $body = isset( $_POST['body'] ) ? sanitize_textarea_field( wp_unslash( $_POST['body'] ) ) : '';
        if ( $body && strlen( $body ) <= 40000 ) {
            $private = ! empty( $_POST['private'] );
            $user = wp_get_current_user();
            $mid = self::add_message( $id, $user->display_name, $body, true, $private );
            if ( $mid ) {
                if ( self::valid_upload() ) {
                    self::save_upload( $id, $mid );
                }
                if ( ! $private && 'external' === $ticket->visibility ) {
                    if ( ! wp_mail( $ticket->customer_email, sprintf( /* translators: 1: ticket number, 2: ticket subject. */
            __( 'Reply to ticket #%1$d: %2$s', 'taxocommerce-ticketing' ), $id, $ticket->subject ), $body . "\n\n" . __( 'Use the private ticket link from your original confirmation email to reply.', 'taxocommerce-ticketing' ) ) ) {
                        TCTK_Event_Log::record( 'mail_customer_failed', $id );
                    }
                }
            } else {
                TCTK_Event_Log::record( 'staff_message_failed', $id );
            }
        }
        TCTK_Event_Log::record( 'staff_action_saved', $id );
        wp_safe_redirect( add_query_arg( array( 'page' => 'taxocommerce-ticketing', 'ticket' => $id, 'notice' => 'saved' ), admin_url( 'admin.php' ) ) );
        exit;
    }

    // phpcs:enable WordPress.Security.NonceVerification.Missing

    private static function settings_admin() {
        $s = self::settings();
        echo '<h2>' . esc_html__( 'Public form designer', 'taxocommerce-ticketing' ) . '</h2>';
        echo '<p>' . esc_html__( 'Place [taxocommerce_ticket_form] on any WordPress page. Customize visible labels, logo, palette, categories and notifications. No other TaxoCommerce plugin is required.', 'taxocommerce-ticketing' ) . '</p>';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="tctk_save_settings"/>';
        wp_nonce_field( 'tctk_save_settings' );
        $labels = array(
            'title' => __( 'Form heading', 'taxocommerce-ticketing' ), 'intro' => __( 'Introduction', 'taxocommerce-ticketing' ),
            'sender_label' => __( 'Name field label', 'taxocommerce-ticketing' ), 'email_label' => __( 'Email field label', 'taxocommerce-ticketing' ),
            'subject_label' => __( 'Subject field label', 'taxocommerce-ticketing' ), 'message_label' => __( 'Message field label', 'taxocommerce-ticketing' ),
            'order_label' => __( 'Order field label', 'taxocommerce-ticketing' ), 'product_label' => __( 'Product field label', 'taxocommerce-ticketing' ),
            'button' => __( 'Submit button text', 'taxocommerce-ticketing' ), 'notification_email' => __( 'Staff notification email', 'taxocommerce-ticketing' ),
            'form_page_url' => __( 'Public page URL containing the ticket shortcode', 'taxocommerce-ticketing' ),
        );
        echo '<table class="form-table" role="presentation">';
        foreach ( $labels as $key => $label ) {
            echo '<tr><th scope="row"><label for="tctk_' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label></th><td><input class="regular-text" id="tctk_' . esc_attr( $key ) . '" name="settings[' . esc_attr( $key ) . ']" value="' . esc_attr( $s[$key] ) . '"/></td></tr>';
        }
        echo '<tr><th scope="row"><label for="tctk_categories">' . esc_html__( 'Categories (one per line)', 'taxocommerce-ticketing' ) . '</label></th><td><textarea id="tctk_categories" rows="6" cols="55" name="settings[categories]">' . esc_textarea( $s['categories'] ) . '</textarea></td></tr>';
        echo '<tr><th scope="row"><label for="tctk_palette">' . esc_html__( 'Color palette', 'taxocommerce-ticketing' ) . '</label></th><td><select id="tctk_palette" name="settings[palette]">';
        foreach ( array( 'blue' => __( 'Blue', 'taxocommerce-ticketing' ), 'green' => __( 'Green', 'taxocommerce-ticketing' ), 'brown' => __( 'Brown', 'taxocommerce-ticketing' ) ) as $key => $label ) {
            echo '<option value="' . esc_attr( $key ) . '" ' . selected( $key, $s['palette'], false ) . '>' . esc_html( $label ) . '</option>';
        }
        echo '</select></td></tr>';
        echo '<tr><th scope="row"><label for="tctk_logo">' . esc_html__( 'Form logo or icon', 'taxocommerce-ticketing' ) . '</label></th><td><input id="tctk_logo" type="hidden" name="settings[logo_id]" value="' . esc_attr( $s['logo_id'] ) . '"/>'; 
        echo '<img id="tctk-logo-preview" src="' . esc_url( $s['logo_id'] ? wp_get_attachment_image_url( absint( $s['logo_id'] ), 'medium' ) : '' ) . '" alt="" style="display:block;max-width:220px;max-height:90px;margin-bottom:12px" ' . ( $s['logo_id'] ? '' : 'hidden' ) . '/>';
        echo '<button type="button" class="button" id="tctk-select-logo" data-title="' . esc_attr__( 'Choose a form logo', 'taxocommerce-ticketing' ) . '" data-choose="' . esc_attr__( 'Use this image', 'taxocommerce-ticketing' ) . '">' . esc_html__( 'Choose image', 'taxocommerce-ticketing' ) . '</button> ';
        echo '<button type="button" class="button" id="tctk-remove-logo">' . esc_html__( 'Remove image', 'taxocommerce-ticketing' ) . '</button></td></tr>';
        foreach ( array( 'show_order' => __( 'Show order reference', 'taxocommerce-ticketing' ), 'show_product' => __( 'Show product reference', 'taxocommerce-ticketing' ), 'allow_attachments' => __( 'Allow attachments', 'taxocommerce-ticketing' ) ) as $key => $label ) {
            echo '<tr><th scope="row">' . esc_html( $label ) . '</th><td><label><input type="checkbox" name="settings[' . esc_attr( $key ) . ']" value="1" ' . checked( $s[$key], 1, false ) . ' />' . esc_html__( 'Enabled', 'taxocommerce-ticketing' ) . '</label></td></tr>';
        }
        echo '</table>';
        submit_button( __( 'Save form settings', 'taxocommerce-ticketing' ) );
        echo '</form>';
    }

    private static function one_ticket( $ticket ) {
        echo '<h2>' . esc_html( sprintf( /* translators: 1: ticket number, 2: ticket subject. */
            __( 'Ticket #%1$d: %2$s', 'taxocommerce-ticketing' ), $ticket->id, $ticket->subject ) ) . '</h2>';
        echo '<p>' . esc_html( $ticket->customer_name ) . ' &lt;' . esc_html( $ticket->customer_email ) . '&gt; · ' . esc_html( $ticket->created_at ) . ' · ' . esc_html( $ticket->category ) . ' · ' . esc_html( $ticket->visibility ) . '</p>';
        if ( $ticket->order_reference || $ticket->product_reference ) {
            echo '<p>' . esc_html__( 'Order:', 'taxocommerce-ticketing' ) . ' ' . esc_html( $ticket->order_reference ) . ' · ' . esc_html__( 'Product:', 'taxocommerce-ticketing' ) . ' ' . esc_html( $ticket->product_reference ) . '</p>';
        }
        echo '<div style="background:#fff;padding:15px;border:1px solid #ccd0d4;max-width:900px">';
        self::render_messages( $ticket, true );
        echo '</div>';
        echo '<h3>' . esc_html__( 'Manage ticket and reply', 'taxocommerce-ticketing' ) . '</h3>';
        echo '<form method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        echo '<input type="hidden" name="action" value="tctk_staff"/><input type="hidden" name="ticket" value="' . esc_attr( $ticket->id ) . '"/>';
        wp_nonce_field( 'tctk_staff' );
        echo '<p><label>' . esc_html__( 'Status', 'taxocommerce-ticketing' ) . ' <select name="status">';
        foreach ( self::statuses() as $key => $value ) {
            echo '<option value="' . esc_attr( $key ) . '" ' . selected( $ticket->status, $key, false ) . '>' . esc_html( $value ) . '</option>';
        }
        echo '</select></label> &nbsp; <label>' . esc_html__( 'Priority', 'taxocommerce-ticketing' ) . ' <select name="priority">';
        foreach ( self::priorities() as $key => $value ) {
            echo '<option value="' . esc_attr( $key ) . '" ' . selected( $ticket->priority, $key, false ) . '>' . esc_html( $value ) . '</option>';
        }
        echo '</select></label> &nbsp; <label>' . esc_html__( 'Assigned to', 'taxocommerce-ticketing' ) . ' <select name="assigned_to"><option value="0">' . esc_html__( 'Unassigned', 'taxocommerce-ticketing' ) . '</option>';
        foreach ( get_users( array( 'fields' => array( 'ID', 'display_name' ), 'orderby' => 'display_name' ) ) as $user ) {
            if ( user_can( $user->ID, self::CAP ) ) {
                echo '<option value="' . esc_attr( $user->ID ) . '" ' . selected( $ticket->assigned_to, $user->ID, false ) . '>' . esc_html( $user->display_name ) . '</option>';
            }
        }
        echo '</select></label></p>';
        echo '<p><label for="tctk_staff_body">' . esc_html__( 'Reply or internal note', 'taxocommerce-ticketing' ) . '</label></p><textarea id="tctk_staff_body" name="body" rows="6" cols="90" maxlength="10000"></textarea>';
        echo '<p><label><input type="checkbox" name="private" value="1"/> ' . esc_html__( 'Internal note (never sent to the customer)', 'taxocommerce-ticketing' ) . '</label></p>';
        self::file_field();
        submit_button( __( 'Save ticket / send reply', 'taxocommerce-ticketing' ) );
        echo '</form>';
        /**
         * Optional integration contract. A companion Tasks adapter can add actions
         * without Ticketing reaching into another plugin's database.
         */
        do_action( 'tctk_ticket_admin_actions', (int) $ticket->id, $ticket );
    }

    private static function list_tickets() {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only route selection or authorization by ticket token.

        global $wpdb;
        $t = self::tables();
        $status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
        $page = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
        $offset = ( $page - 1 ) * 30;
        if ( isset( self::statuses()[$status] ) ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Paginated ticket list.
            $tickets = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE status = %s ORDER BY updated_at DESC LIMIT %d OFFSET %d', $t['tickets'], $status, 30, $offset ) );
        } else {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Paginated ticket list.
            $tickets = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i ORDER BY updated_at DESC LIMIT %d OFFSET %d', $t['tickets'], 30, $offset ) );
        }
        echo '<h2>' . esc_html__( 'Tickets', 'taxocommerce-ticketing' ) . '</h2><p>';
        echo '<a href="' . esc_url( admin_url( 'admin.php?page=taxocommerce-ticketing' ) ) . '">' . esc_html__( 'All', 'taxocommerce-ticketing' ) . '</a> | ';
        foreach ( self::statuses() as $key => $label ) {
            echo '<a href="' . esc_url( add_query_arg( array( 'page' => 'taxocommerce-ticketing', 'status' => $key ), admin_url( 'admin.php' ) ) ) . '">' . esc_html( $label ) . '</a> | ';
        }
        echo '</p><table class="widefat striped"><thead><tr><th>ID</th><th>' . esc_html__( 'Subject', 'taxocommerce-ticketing' ) . '</th><th>' . esc_html__( 'Requester', 'taxocommerce-ticketing' ) . '</th><th>' . esc_html__( 'Status', 'taxocommerce-ticketing' ) . '</th><th>' . esc_html__( 'Priority', 'taxocommerce-ticketing' ) . '</th><th>' . esc_html__( 'Updated', 'taxocommerce-ticketing' ) . '</th></tr></thead><tbody>';
        foreach ( $tickets as $ticket ) {
            $link = add_query_arg( array( 'page' => 'taxocommerce-ticketing', 'ticket' => $ticket->id ), admin_url( 'admin.php' ) );
            $statuses = self::statuses();
            $priorities = self::priorities();
            echo '<tr><td>' . esc_html( $ticket->id ) . '</td><td><a href="' . esc_url( $link ) . '">' . esc_html( $ticket->subject ) . '</a></td><td>' . esc_html( $ticket->customer_name ) . '<br>' . esc_html( $ticket->customer_email ) . '</td><td>' . esc_html( isset( $statuses[$ticket->status] ) ? $statuses[$ticket->status] : $ticket->status ) . '</td><td>' . esc_html( isset( $priorities[$ticket->priority] ) ? $priorities[$ticket->priority] : $ticket->priority ) . '</td><td>' . esc_html( $ticket->updated_at ) . '</td></tr>';
        }
        if ( ! $tickets ) {
            echo '<tr><td colspan="6">' . esc_html__( 'No tickets found.', 'taxocommerce-ticketing' ) . '</td></tr>';
        }
        echo '</tbody></table><p>';
        if ( $page > 1 ) {
            echo '<a class="button" href="' . esc_url( add_query_arg( array( 'page' => 'taxocommerce-ticketing', 'paged' => $page - 1, 'status' => $status ), admin_url( 'admin.php' ) ) ) . '">' . esc_html__( 'Previous', 'taxocommerce-ticketing' ) . '</a> ';
        }
        if ( count( $tickets ) === 30 ) {
            echo '<a class="button" href="' . esc_url( add_query_arg( array( 'page' => 'taxocommerce-ticketing', 'paged' => $page + 1, 'status' => $status ), admin_url( 'admin.php' ) ) ) . '">' . esc_html__( 'Next', 'taxocommerce-ticketing' ) . '</a>';
        }
        echo '</p>';
    }

    // phpcs:enable WordPress.Security.NonceVerification.Recommended

    public static function admin() {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only route selection or authorization by ticket token.

        if ( ! current_user_can( self::CAP ) ) {
            return;
        }
        $tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'tickets';
        $id = isset( $_GET['ticket'] ) ? absint( $_GET['ticket'] ) : 0;
        $notice = isset( $_GET['notice'] ) ? sanitize_key( wp_unslash( $_GET['notice'] ) ) : '';
        echo '<div class="wrap"><h1>' . esc_html__( 'TaxoCommerce Ticketing', 'taxocommerce-ticketing' ) . '</h1>';
        $tabs = array(
            'tickets' => __( 'Ticket inbox', 'taxocommerce-ticketing' ),
            'create' => __( 'New ticket', 'taxocommerce-ticketing' ),
            'settings' => __( 'Form settings', 'taxocommerce-ticketing' ),
            'autocheck' => __( 'Autocheck', 'taxocommerce-ticketing' ),
            'logs' => __( 'Activity log', 'taxocommerce-ticketing' ),
            'manual-user' => __( 'User Manual', 'taxocommerce-ticketing' ),
            'manual-technical' => __( 'Technical Manual', 'taxocommerce-ticketing' ),
        );
        echo '<nav class="nav-tab-wrapper" aria-label="' . esc_attr__( 'Ticketing administration tabs', 'taxocommerce-ticketing' ) . '">';
        foreach ( $tabs as $key => $label ) {
            echo '<a class="nav-tab' . ( $key === $tab ? ' nav-tab-active' : '' ) . '" href="' . esc_url( add_query_arg( array( 'page' => 'taxocommerce-ticketing', 'tab' => $key ), admin_url( 'admin.php' ) ) ) . '">' . esc_html( $label ) . '</a>';
        }
        echo '</nav>';
        if ( self::notice( $notice ) ) {
            echo '<div class="notice notice-success"><p>' . esc_html( self::notice( $notice ) ) . '</p></div>';
        }
        if ( 'settings' === $tab ) {
            self::settings_admin();
        } elseif ( 'create' === $tab ) {
            self::create_admin_form();
        } elseif ( 'autocheck' === $tab ) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only selection of check type.
            $check = isset( $_GET['tctk_check'] ) ? sanitize_key( wp_unslash( $_GET['tctk_check'] ) ) : 'functionality';
            echo '<nav class="nav-tab-wrapper" aria-label="' . esc_attr__( 'Autocheck types', 'taxocommerce-ticketing' ) . '">';
            foreach ( array( 'functionality' => __( 'Functionality Check', 'taxocommerce-ticketing' ), 'code' => __( 'Code Check', 'taxocommerce-ticketing' ) ) as $key => $label ) {
                echo '<a class="nav-tab' . ( $check === $key ? ' nav-tab-active' : '' ) . '" href="' . esc_url( add_query_arg( array( 'page' => 'taxocommerce-ticketing', 'tab' => 'autocheck', 'tctk_check' => $key ), admin_url( 'admin.php' ) ) ) . '">' . esc_html( $label ) . '</a>';
            }
            echo '</nav>';
            if ( 'code' === $check ) {
                TCTK_Autotest_Code::render();
            } else {
                TCTK_Autocheck::render();
            }
        } elseif ( 'logs' === $tab ) {
            TCTK_Event_Log::render();
        } elseif ( 'manual-user' === $tab || 'manual-technical' === $tab ) {
            TCTK_Manuals::render( 'manual-technical' === $tab ? 'technical' : 'user' );
        } elseif ( $id ) {
            global $wpdb;
            $t = self::tables();
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Administrator sees current private ticket.
            $ticket = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', $t['tickets'], $id ) );
            if ( $ticket ) {
                self::one_ticket( $ticket );
            } else {
                echo '<p>' . esc_html__( 'Ticket not found.', 'taxocommerce-ticketing' ) . '</p>';
            }
        } else {
            self::list_tickets();
        }
        echo '</div>';
    }
    // phpcs:enable WordPress.Security.NonceVerification.Recommended

}
