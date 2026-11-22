<?php
/**
 * Minimal operational events for Ticketing. Never record message bodies,
 * names, email addresses, attachment data, access tokens or IP addresses.
 *
 * @package TaxoCommerceTicketing
 */
defined( 'ABSPATH' ) || exit;

final class TCTK_Event_Log {
    const OPTION = 'tctk_event_log';
    const LIMIT = 150;

    public static function boot() {
        add_action( 'admin_post_tctk_events_export', array( __CLASS__, 'export' ) );
    }

    /**
     * Record only fixed machine-readable event types and a ticket number.
     * This option is a capped non-autoloaded diagnostics ring buffer.
     *
     * @param string $event Event identifier, never free-form user data.
     * @param int    $ticket_id Existing ticket ID, or zero.
     */
    public static function record( $event, $ticket_id = 0 ) {
        $allowed = array(
            'honeypot_rejected',
            'rate_limited',
            'validation_rejected',
            'ticket_insert_failed',
            'message_insert_failed',
            'ticket_created',
            'customer_reply_saved',
            'customer_reply_failed',
            'staff_ticket_created',
            'staff_ticket_failed',
            'staff_action_saved',
            'staff_message_failed',
            'staff_update_failed',
            'ticket_update_failed',
            'mail_customer_failed',
            'mail_staff_failed',
            'attachment_rejected',
            'attachment_save_failed',
            'functional_probe_failed',
        );
        if ( ! in_array( $event, $allowed, true ) ) {
            return;
        }
        $list = get_option( self::OPTION, array() );
        $list = is_array( $list ) ? $list : array();
        $list[] = array(
            'at' => gmdate( 'c' ),
            'event' => $event,
            'ticket_id' => max( 0, absint( $ticket_id ) ),
        );
        update_option( self::OPTION, array_slice( $list, -self::LIMIT ), false );
    }

    public static function recent() {
        $events = get_option( self::OPTION, array() );
        return is_array( $events ) ? array_reverse( array_slice( $events, -self::LIMIT ) ) : array();
    }

    public static function export() {
        if ( ! current_user_can( TCTK_Ticketing::CAP ) ) {
            wp_die( esc_html__( 'Access denied.', 'taxocommerce-ticketing' ), '', array( 'response' => 403 ) );
        }
        check_admin_referer( 'tctk_events_export' );
        $report = array(
            'plugin' => 'taxocommerce-ticketing',
            'version' => TCTK_VERSION,
            'generated_at' => gmdate( 'c' ),
            'events' => self::recent(),
            'privacy' => 'Only event identifiers and ticket IDs are recorded; no customer text or access links.',
        );
        $json = wp_json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
        if ( ! is_string( $json ) ) {
            wp_die( esc_html__( 'Unable to create JSON.', 'taxocommerce-ticketing' ) );
        }
        nocache_headers();
        header( 'Content-Type: application/json; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="taxocommerce-ticketing-events.json"' );
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON download.
        echo $json;
        exit;
    }

    public static function render() {
        if ( ! current_user_can( TCTK_Ticketing::CAP ) ) {
            return;
        }
        echo '<h2>' . esc_html__( 'Ticket activity log', 'taxocommerce-ticketing' ) . '</h2>';
        echo '<p>' . esc_html__( 'Shows the latest 150 diagnostic events only. No names, email addresses, message text, attachment content, IPs or private access links are stored. It is not a record of all historical tickets.', 'taxocommerce-ticketing' ) . '</p>';
        $events = self::recent();
        echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Date (UTC)', 'taxocommerce-ticketing' ) . '</th><th>' . esc_html__( 'Event', 'taxocommerce-ticketing' ) . '</th><th>' . esc_html__( 'Ticket number', 'taxocommerce-ticketing' ) . '</th></tr></thead><tbody>';
        foreach ( $events as $row ) {
            echo '<tr><td>' . esc_html( isset( $row['at'] ) ? $row['at'] : '' ) . '</td><td><code>' . esc_html( isset( $row['event'] ) ? $row['event'] : '' ) . '</code></td><td>' . esc_html( isset( $row['ticket_id'] ) ? (string) absint( $row['ticket_id'] ) : '0' ) . '</td></tr>';
        }
        if ( ! $events ) {
            echo '<tr><td colspan="3">' . esc_html__( 'No events recorded yet.', 'taxocommerce-ticketing' ) . '</td></tr>';
        }
        echo '</tbody></table>';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="tctk_events_export">';
        wp_nonce_field( 'tctk_events_export' );
        submit_button( __( 'Download event log JSON', 'taxocommerce-ticketing' ), 'secondary' );
        echo '</form>';
    }
}
