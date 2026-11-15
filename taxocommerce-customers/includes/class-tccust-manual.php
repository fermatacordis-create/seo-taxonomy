<?php
/**
 * User and technical manuals stored locally and rendered through gettext.
 *
 * @package TaxoCommerceCustomers
 */
defined( 'ABSPATH' ) || exit;

final class TCCUST_Manual {

	private static function chapters( $technical ) {
		if ( ! $technical ) {
			return array(
				array(
					__( 'Access and customer directory', 'taxocommerce-customers' ),
					array(
						__( 'Open TaxoCommerce > Customers when Core is active, or the Customers menu in a standalone installation.', 'taxocommerce-customers' ),
						__( 'The directory includes WooCommerce customers with paid orders, guest buyers identified by billing email, and contacts added manually.', 'taxocommerce-customers' ),
						__( 'Use the search box and source selector to find customers. The Purchases column counts paid orders linked to each record.', 'taxocommerce-customers' ),
					),
				),
				array(
					__( 'Create and edit manual contacts', 'taxocommerce-customers' ),
					array(
						__( 'Choose Add contact, enter a name and at least one email, phone or WhatsApp number, and save the record. No WordPress account is created.', 'taxocommerce-customers' ),
						__( 'Use Edit record to update company, address, contact preferences, internal labels and private notes. CRM changes never rewrite WooCommerce billing data.', 'taxocommerce-customers' ),
						__( 'If a manual contact later buys using the same exact email, it can be linked to the WooCommerce order without losing its notes. Names and phone numbers are never matched automatically.', 'taxocommerce-customers' ),
						__( 'Delete contact removes an unlinked manual record. For buyers with orders, Clear CRM-only details removes optional CRM notes and labels without touching WooCommerce orders.', 'taxocommerce-customers' ),
					),
				),
				array(
					__( 'Synchronize past purchases', 'taxocommerce-customers' ),
					array(
						__( 'On the first visit with WooCommerce enabled, synchronization of historical paid orders starts automatically in small batches. The Synchronize WooCommerce buyers button starts a new scan.', 'taxocommerce-customers' ),
						__( 'Processing and completed paid orders are included according to WooCommerce paid-status settings. Unpaid orders and guest orders without an email are excluded.', 'taxocommerce-customers' ),
						__( 'New paid orders are picked up by WooCommerce events. Stop pauses a historical scan; it does not delete customers already imported.', 'taxocommerce-customers' ),
					),
				),
				array(
					__( 'Large CSV imports and exports', 'taxocommerce-customers' ),
					array(
						__( 'From Import / Export, export a CSV backup before editing customer records in a spreadsheet.', 'taxocommerce-customers' ),
						__( 'Existing records are updated by local ID. A new row with no ID creates a manual contact, not a WooCommerce account. Source and WooCommerce user ID are read-only.', 'taxocommerce-customers' ),
						__( 'CSV columns must match the export. Empty cells overwrite CRM fields; existing buyer emails cannot be changed by import. Import uses five speeds, progress and Stop.', 'taxocommerce-customers' ),
					),
				),
				array(
					__( 'Personal data and permissions', 'taxocommerce-customers' ),
					array(
						__( 'These records are private to store managers and administrators. Personal notes are not shown on the public site and are not sent to marketing services.', 'taxocommerce-customers' ),
						__( 'Only record customer contact preferences you are permitted to retain. A contact method is not proof of marketing consent.', 'taxocommerce-customers' ),
						__( 'WooCommerce remains responsible for account details, orders and fiscal retention. The WordPress personal-data tools include CRM export and erasure support.', 'taxocommerce-customers' ),
					),
				),
			);
		}
		return array(
			array(
				__( 'Architecture and dependencies', 'taxocommerce-customers' ),
				array(
					__( 'This plugin is independent of TaxoCommerce Core, Suppliers, Billing, Campaigns and Tasks. WooCommerce is required only for buyer synchronization.', 'taxocommerce-customers' ),
					__( 'WordPress custom tables store private CRM fields and compact order ID references; WooCommerce HPOS APIs remain the source of orders and payment status.', 'taxocommerce-customers' ),
				),
			),
			array(
				__( 'Database and identity', 'taxocommerce-customers' ),
				array(
					__( 'The customers table contains an optional unique WooCommerce user ID and an optional normalized unique email; contacts without email can still be entered manually using a phone number.', 'taxocommerce-customers' ),
					__( 'Manual contacts and guest purchases with the same exact email can merge. Records with different registered user IDs never merge automatically.', 'taxocommerce-customers' ),
					__( 'A second table maps each WooCommerce paid order ID to one CRM record, preventing duplicated purchase counts on repeated hooks.', 'taxocommerce-customers' ),
				),
			),
			array(
				__( 'WooCommerce synchronization', 'taxocommerce-customers' ),
				array(
					__( 'The adapter uses wc_get_orders(), wc_get_order() and order hooks; it never queries or writes WooCommerce order tables directly.', 'taxocommerce-customers' ),
					__( 'Historical scans run in bounded AJAX requests without a cron dependency. New paid-order events refresh links immediately.', 'taxocommerce-customers' ),
				),
			),
			array(
				__( 'Security and CSV operations', 'taxocommerce-customers' ),
				array(
					__( 'Every administrative mutation checks a capability and a WordPress nonce. Input is validated and output escaped.', 'taxocommerce-customers' ),
					__( 'Import sessions use short-lived tokens and private temporary files. Only CRM contact fields are writable. CSV export protects spreadsheet cells against formula execution.', 'taxocommerce-customers' ),
					__( 'The export/import flow is chunked and has five speeds; notes can contain line breaks inside quoted CSV fields. Data is not sent to any third-party API.', 'taxocommerce-customers' ),
				),
			),
			array(
				__( 'Autotest and data retention', 'taxocommerce-customers' ),
				array(
					__( 'Autotest has separate Functionality Check and Code Check tabs; the latter is a static analysis aid and does not replace the official WordPress Plugin Check.', 'taxocommerce-customers' ),
					__( 'Deactivate the plugin without deleting user accounts or orders. CRM data persists unless explicitly removed using WordPress privacy tools or database administration.', 'taxocommerce-customers' ),
				),
			),
		);
	}

	public static function render() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only manual selection.
		$tech = isset( $_GET['manual'] ) && 'technical' === sanitize_key( wp_unslash( $_GET['manual'] ) );
		$base = admin_url( 'admin.php?page=taxocommerce-customers&tab=manual' );
		echo '<section class="tccust-panel"><h2>' . esc_html__( 'Customer management manuals', 'taxocommerce-customers' ) . '</h2>';
		echo '<p><a class="button ' . ( $tech ? '' : 'button-primary' ) . '" href="' . esc_url( $base ) . '">' . esc_html__( 'User manual', 'taxocommerce-customers' ) . '</a> ';
		echo '<a class="button ' . ( $tech ? 'button-primary' : '' ) . '" href="' . esc_url( add_query_arg( 'manual', 'technical', $base ) ) . '">' . esc_html__( 'Technical manual', 'taxocommerce-customers' ) . '</a></p>';
		foreach ( self::chapters( $tech ) as $index => $chapter ) {
			echo '<details class="tccust-manual-details"' . ( 0 === $index ? ' open' : '' ) . '><summary>' . esc_html( $chapter[0] ) . '</summary><ul>';
			foreach ( $chapter[1] as $line ) {
				echo '<li>' . esc_html( $line ) . '</li>';
			}
			echo '</ul></details>';
		}
		echo '</section>';
	}
}
