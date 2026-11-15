<?php
/**
 * Store only private CRM fields and compact order links; WooCommerce owns orders.
 *
 * @package TaxoCommerceCustomers
 */
defined( 'ABSPATH' ) || exit;

final class TCCUST_DB {
	const VERSION = '1.1.0';

	public static function customers_table() {
		global $wpdb;
		return $wpdb->prefix . 'taxocommerce_customers';
	}

	public static function links_table() {
		global $wpdb;
		return $wpdb->prefix . 'taxocommerce_customer_orders';
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();
		$customers = self::customers_table();
		$links = self::links_table();

		$sql_customers = "CREATE TABLE {$customers} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			woo_user_id BIGINT UNSIGNED NULL,
			email VARCHAR(190) NOT NULL DEFAULT '',
			email_key VARCHAR(190) NULL,
			email_digest CHAR(64) NULL,
			erased_at DATETIME NULL,
			source VARCHAR(16) NOT NULL DEFAULT 'manual',
			nombre VARCHAR(150) NOT NULL DEFAULT '',
			apellidos VARCHAR(150) NOT NULL DEFAULT '',
			empresa VARCHAR(190) NOT NULL DEFAULT '',
			telefono VARCHAR(60) NOT NULL DEFAULT '',
			whatsapp VARCHAR(60) NOT NULL DEFAULT '',
			direccion VARCHAR(255) NOT NULL DEFAULT '',
			codigo_postal VARCHAR(20) NOT NULL DEFAULT '',
			localidad VARCHAR(120) NOT NULL DEFAULT '',
			provincia VARCHAR(120) NOT NULL DEFAULT '',
			pais VARCHAR(120) NOT NULL DEFAULT '',
			canal_preferido VARCHAR(20) NOT NULL DEFAULT '',
			etiquetas VARCHAR(255) NOT NULL DEFAULT '',
			observaciones LONGTEXT NULL,
			creado DATETIME NOT NULL,
			actualizado DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY woo_user_id (woo_user_id),
			UNIQUE KEY email_key (email_key),
			KEY email_digest (email_digest),
			KEY source (source),
			KEY nombre (nombre)
		) {$charset};";
		$sql_links = "CREATE TABLE {$links} (
			order_id BIGINT UNSIGNED NOT NULL,
			customer_id BIGINT UNSIGNED NOT NULL,
			purchased_at DATETIME NOT NULL,
			PRIMARY KEY  (order_id),
			KEY customer_id (customer_id),
			KEY purchased_at (purchased_at)
		) {$charset};";
		dbDelta( $sql_customers );
		dbDelta( $sql_links );
		// Grant granular CRM capabilities to standard privileged roles only.
		// Other roles may be delegated individual permissions by an administrator.
		foreach ( array( 'administrator', 'shop_manager' ) as $role_name ) {
			$role = get_role( $role_name );
			if ( ! $role ) {
				continue;
			}
			foreach ( array(
				'manage_taxocommerce_customers',
				'import_taxocommerce_customers',
				'export_taxocommerce_customers',
				'erase_taxocommerce_customer_data',
				'view_taxocommerce_private_notes',
			) as $capability ) {
				$role->add_cap( $capability );
			}
		}
		update_option( 'tccust_schema_version', self::VERSION, false );
	}

	public static function maybe_install() {
		if ( self::VERSION !== (string) get_option( 'tccust_schema_version', '' ) ) {
			self::install();
			return;
		}
		global $wpdb;
		foreach ( array( self::customers_table(), self::links_table() ) as $table ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Verify private tables after restores.
			$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
			if ( $exists !== $table ) {
				self::install();
				return;
			}
		}
	}

	/**
	 * Only exact e-mail identities are joined; phones and names never auto-merge.
	 */
	public static function email_key( $email ) {
		$email = strtolower( trim( sanitize_email( (string) $email ) ) );
		return '' === $email || ! is_email( $email ) ? null : $email;
	}

	/**
	 * A keyed one-way digest is used only to keep erased WooCommerce identities
	 * from being repopulated by future checkout events. It is not an email.
	 */
	public static function email_digest( $email ) {
		$key = self::email_key( $email );
		return null === $key ? null : hash_hmac( 'sha256', $key, wp_salt( 'auth' ) );
	}

	/**
	 * Look up an erased identity without storing its address in this CRM.
	 */
	public static function find_erased_email( $email ) {
		$digest = self::email_digest( $email );
		if ( null === $digest ) {
			return null;
		}
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Erased-identity lookup must use current state.
		return $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM %i WHERE email_digest=%s AND erased_at IS NOT NULL LIMIT 1', self::customers_table(), $digest ),
			ARRAY_A
		);
	}

	/**
	 * Remove every CRM-owned copy of personal data while retaining only
	 * the technical WooCommerce link and the non-reversible email digest.
	 */
	public static function redact( $customer ) {
		if ( ! is_array( $customer ) || empty( $customer['id'] ) ) {
			return 0;
		}
		$fields = array(
			'email' => '',
			'email_key' => null,
			'email_digest' => self::email_digest( $customer['email_key'] ?: $customer['email'] ),
			'erased_at' => current_time( 'mysql' ),
			'nombre' => '',
			'apellidos' => '',
			'empresa' => '',
			'telefono' => '',
			'whatsapp' => '',
			'direccion' => '',
			'codigo_postal' => '',
			'localidad' => '',
			'provincia' => '',
			'pais' => '',
			'canal_preferido' => '',
			'etiquetas' => '',
			'observaciones' => '',
		);
		return self::save( $fields, $customer );
	}

	public static function find( $id ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Private CRM record must be current.
		return $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM %i WHERE id=%d LIMIT 1', self::customers_table(), absint( $id ) ),
			ARRAY_A
		);
	}

	public static function find_user( $user_id ) {
		if ( ! $user_id ) {
			return null;
		}
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Read current customer account association.
		return $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM %i WHERE woo_user_id=%d LIMIT 1', self::customers_table(), absint( $user_id ) ),
			ARRAY_A
		);
	}

	public static function find_email( $key ) {
		if ( null === $key ) {
			return null;
		}
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Exact unique normalized email lookup.
		return $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM %i WHERE email_key=%s LIMIT 1', self::customers_table(), $key ),
			ARRAY_A
		);
	}

	public static function save( $fields, $existing = null ) {
		global $wpdb;
		$now = current_time( 'mysql' );
		$fields['actualizado'] = $now;
		if ( $existing && ! empty( $existing['id'] ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Authenticated private CRM edit only.
			$result = $wpdb->update( self::customers_table(), $fields, array( 'id' => absint( $existing['id'] ) ) );
			return false === $result ? 0 : absint( $existing['id'] );
		}
		$fields['creado'] = $now;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- New private CRM record.
		return false !== $wpdb->insert( self::customers_table(), $fields ) ? absint( $wpdb->insert_id ) : 0;
	}

	public static function delete( $id ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Explicit admin-only deletion of private CRM record, never WooCommerce orders.
		return $wpdb->delete( self::customers_table(), array( 'id' => absint( $id ) ) );
	}

	/**
	 * Prevent deletion of WooCommerce order links by a CRM operation.
	 * A buyer record remains discoverable while a paid order exists.
	 */
	public static function has_orders( $id ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Count only the CRM link table.
		return (bool) $wpdb->get_var(
			$wpdb->prepare( 'SELECT order_id FROM %i WHERE customer_id=%d LIMIT 1', self::links_table(), absint( $id ) )
		);
	}
}
