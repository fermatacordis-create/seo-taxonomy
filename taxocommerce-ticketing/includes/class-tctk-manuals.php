<?php
/**
 * Locally bundled, read-only user and technical manuals.
 *
 * @package TaxoCommerceTicketing
 */
defined( 'ABSPATH' ) || exit;

final class TCTK_Manuals {
    private static function content( $technical ) {
        if ( ! $technical ) {
            return array(
                array(
                    'title' => __( 'What Ticketing is for', 'taxocommerce-ticketing' ),
                    'lines' => array(
                        __( 'Ticketing accepts product questions, shipping incidents, technical requests and internal follow-up tasks. It is independent of WooCommerce, TaxoCommerce Core and Tasks.', 'taxocommerce-ticketing' ),
                        __( 'Customers do not need a WordPress login to use a published ticket form. All tickets remain private; an external ticket is not a public listing.', 'taxocommerce-ticketing' ),
                    ),
                ),
                array(
                    'title' => __( 'Publish the customer form', 'taxocommerce-ticketing' ),
                    'lines' => array(
                        __( 'Create an ordinary WordPress page, add [taxocommerce_ticket_form] and publish it. This is the customer request form, not the plugin product-information page.', 'taxocommerce-ticketing' ),
                        __( 'Under Tickets > Form settings, configure the heading, form descriptions, request categories, notification email, logo and blue/green/brown theme. Save changes.', 'taxocommerce-ticketing' ),
                        __( 'Enter the URL of the published form page in Public form page URL. This is required when creating an external ticket from the staff administration interface; the public shortcode can be used independently.', 'taxocommerce-ticketing' ),
                    ),
                ),
                array(
                    'title' => __( 'Send a request without an account', 'taxocommerce-ticketing' ),
                    'lines' => array(
                        __( 'A visitor enters their name, email address, subject and message. Order number and product reference are optional when enabled.', 'taxocommerce-ticketing' ),
                        __( 'One PDF, JPG, PNG or TXT attachment of up to 2 MB can be sent with each message when attachments are enabled.', 'taxocommerce-ticketing' ),
                        __( 'A successful submission creates a ticket ID and stores the first message. If the form rejects a submission, it must not claim the ticket was registered.', 'taxocommerce-ticketing' ),
                        __( 'The visitor receives a private tracking link by email. The site mail service must be configured and independently verified; a successful wp_mail() call is not proof of delivery.', 'taxocommerce-ticketing' ),
                    ),
                ),
                array(
                    'title' => __( 'Manage tickets and customer replies', 'taxocommerce-ticketing' ),
                    'lines' => array(
                        __( 'Open Tickets > Ticket inbox in WordPress or the corresponding Core extension entry. The list shows the newest activity first; select the subject to open a ticket.', 'taxocommerce-ticketing' ),
                        __( 'Choose Open, In progress, Waiting for customer, Resolved or Closed. Assign a priority and permitted staff member, then save the ticket.', 'taxocommerce-ticketing' ),
                        __( 'A public reply is emailed to the customer. Select Internal note to add a private message without notifying the customer. Customers cannot read internal notes.', 'taxocommerce-ticketing' ),
                        __( 'A customer can reply from their unique tracking link without signing in. A closed ticket is read-only for the customer.', 'taxocommerce-ticketing' ),
                    ),
                ),
                array(
                    'title' => __( 'Internal and external tickets', 'taxocommerce-ticketing' ),
                    'lines' => array(
                        __( 'Choose New ticket in the administration area. Internal tickets are visible only to staff who have ticket-management permission.', 'taxocommerce-ticketing' ),
                        __( 'For customer-facing external tickets, provide a valid email and configure the form page URL first. The recipient receives their tracking link.', 'taxocommerce-ticketing' ),
                        __( 'External means customer-accessible by a private unguessable link, not accessible by searching for a ticket number.', 'taxocommerce-ticketing' ),
                    ),
                ),
                array(
                    'title' => __( 'Diagnose missing tickets', 'taxocommerce-ticketing' ),
                    'lines' => array(
                        __( 'Open Tickets > Activity log to review recent successful creations, spam rejections, validation failures, database failures and mail failures. The log records ticket IDs and event codes, never message bodies or customer email addresses.', 'taxocommerce-ticketing' ),
                        __( 'Open Tickets > Autocheck > Functionality Check. This creates, reads and removes a synthetic private ticket, message and attachment; no emails are sent and no customer records are intentionally changed.', 'taxocommerce-ticketing' ),
                        __( 'The Code Check tab scans this plugin in bounded batches and groups PHP, WordPress and security observations. It does not replace the official WordPress Plugin Check.', 'taxocommerce-ticketing' ),
                        __( 'If the event log shows ticket_created, search that ticket ID in the inbox. If it shows a rejection or insertion error, the request may not have been stored. Check the email service separately.', 'taxocommerce-ticketing' ),
                    ),
                ),
            );
        }
        return array(
            array(
                'title' => __( 'Architecture and compatibility', 'taxocommerce-ticketing' ),
                'lines' => array(
                    __( 'Requires WordPress 6.7 or later and PHP 7.4 or later. Core, Tasks and WooCommerce are optional and must not be activated as dependencies.', 'taxocommerce-ticketing' ),
                    __( 'The plugin registers [taxocommerce_ticket_form] and [taxocommerce_tickets] and WordPress admin-post handlers. Public submission and reply handlers use admin_post_nopriv_ hooks.', 'taxocommerce-ticketing' ),
                    __( 'Administration is limited to the custom manage_tctk_tickets capability granted to administrators and shop managers on activation.', 'taxocommerce-ticketing' ),
                    __( 'Core can discover and activate the plugin using WordPress native plugin APIs. The optional tctk_ticket_admin_actions hook lets third parties offer actions without direct database coupling.', 'taxocommerce-ticketing' ),
                ),
            ),
            array(
                'title' => __( 'Data model and storage', 'taxocommerce-ticketing' ),
                'lines' => array(
                    __( 'The prefix-adjusted tables are {prefix}tctk_tickets, {prefix}tctk_messages and {prefix}tctk_files. All information is stored in the current WordPress database, not shared between sites.', 'taxocommerce-ticketing' ),
                    __( 'Tickets contain a status, priority, type (internal/external), assigned user and timestamps; messages contain staff/private flags. Attachment bytes are stored as private database BLOBs.', 'taxocommerce-ticketing' ),
                    __( 'Deactivating the plugin does not delete tickets. Back up the database as part of routine privacy and retention processes.', 'taxocommerce-ticketing' ),
                ),
            ),
            array(
                'title' => __( 'Request validation and private access', 'taxocommerce-ticketing' ),
                'lines' => array(
                    __( 'The public form requires a WordPress nonce, validates form fields, checks permitted attachment MIME types and limits submissions through an IP-derived transient key. A filled honeypot rejects the form without a success notice.', 'taxocommerce-ticketing' ),
                    __( 'On ticket creation, a random private token is sent only in the customer follow-up link. The stored hash is checked when reading messages, replying or downloading private attachments.', 'taxocommerce-ticketing' ),
                    __( 'Do not forward private tracking links or include them in diagnostic logs. These links act as bearer credentials for the individual ticket.', 'taxocommerce-ticketing' ),
                ),
            ),
            array(
                'title' => __( 'Submission sequence and events', 'taxocommerce-ticketing' ),
                'lines' => array(
                    __( 'Submission creates a ticket, inserts its initial message and captures the generated message ID before the ticket timestamp is updated. If message insertion fails, the new ticket is deleted and the form reports an error.', 'taxocommerce-ticketing' ),
                    __( 'After storage, the plugin attempts to email the customer and staff. A mail failure is recorded separately from a successful database insert.', 'taxocommerce-ticketing' ),
                    __( 'The tctk_event_log WordPress option stores the newest 150 diagnostic events without customer names, addresses, message text, IP addresses or tokens. This is not a full audit trail or delivery guarantee.', 'taxocommerce-ticketing' ),
                ),
            ),
            array(
                'title' => __( 'Functionality and Code Check', 'taxocommerce-ticketing' ),
                'lines' => array(
                    __( 'The functionality check validates three database tables, configured notification address, public shortcode and guest handlers. A controlled synthetic ticket/message/file insert-read-delete probe checks real persistence and cleanup.', 'taxocommerce-ticketing' ),
                    __( 'No actual public form browser session, email delivery, external payment system, WooCommerce order or customer account is tested by the synthetic probe.', 'taxocommerce-ticketing' ),
                    __( 'The independent Code Check scans bounded PHP sources of this plugin for static PHP, WordPress and security indicators using five configurable CPU speeds. It offers an annotated JSON report and does not run PHP shell execution.', 'taxocommerce-ticketing' ),
                    __( 'Both reports include execution time and explicit passed/warning/error or classified findings; never interpret an unchecked test as successful.', 'taxocommerce-ticketing' ),
                ),
            ),
            array(
                'title' => __( 'Operational verification', 'taxocommerce-ticketing' ),
                'lines' => array(
                    __( 'Test a real submission using a controlled email account, check that the event log records ticket_created and that the assigned ID appears in the staff inbox.', 'taxocommerce-ticketing' ),
                    __( 'Check the received tracking URL, customer replies, internal note privacy and file downloads. Confirm outbound email delivery independently of wp_mail() return values.', 'taxocommerce-ticketing' ),
                    __( 'The deployed version and official WordPress Plugin Check must be confirmed separately from the in-plugin static Code Check. Production deployment requires explicit approval.', 'taxocommerce-ticketing' ),
                ),
            ),
        );
    }

    public static function render( $type ) {
        if ( ! current_user_can( TCTK_Ticketing::CAP ) ) {
            return;
        }
        $technical = 'technical' === $type;
        echo '<div class="tctk-manual" style="max-width:990px">';
        echo '<h2>' . esc_html( $technical ? __( 'Technical Manual', 'taxocommerce-ticketing' ) : __( 'User Manual', 'taxocommerce-ticketing' ) ) . '</h2>';
        foreach ( self::content( $technical ) as $section ) {
            echo '<section class="card" style="max-width:950px;padding:16px 20px;margin:14px 0">';
            echo '<h3>' . esc_html( $section['title'] ) . '</h3>';
            foreach ( $section['lines'] as $text ) {
                echo '<p>' . esc_html( $text ) . '</p>';
            }
            echo '</section>';
        }
        echo '</div>';
    }
}
