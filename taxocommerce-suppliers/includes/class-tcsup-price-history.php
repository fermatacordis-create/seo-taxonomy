<?php
/**
 * Append-only supplier catalogue and WooCommerce selling-price history.
 *
 * @package TaxoCommerceSuppliers
 */
defined( 'ABSPATH' ) || exit;

final class TCSUP_Price_History {
	/**
	 * Record the current price state only when it differs from the last snapshot.
	 * The first snapshot is dated when captured, not retroactively.
	 *
	 * @param int    $catalog_id Catalogue row ID.
	 * @param string $source     Source of the snapshot.
	 * @param object $product    Optional WC_Product with latest saved values.
	 * @return bool
	 */
	public static function capture( $catalog_id, $source = 'catalog', $product = null ) {
		global $wpdb;
		$catalog_id = absint( $catalog_id );
		if ( ! $catalog_id ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Read current prices for append-only audit log.
		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT id,object_id,precio_sin_iva,precio_con_iva,moneda FROM %i WHERE id=%d', TCSUP_Schema::catalog_table(), $catalog_id ),
			ARRAY_A
		);
		if ( ! $row ) {
			return false;
		}
		$product_id = absint( $row['object_id'] ?? 0 );
		if ( ! $product && $product_id && function_exists( 'wc_get_product' ) ) {
			$product = wc_get_product( $product_id );
		}
		$values = array(
			'catalog_id'            => $catalog_id,
			'product_id'            => $product_id,
			'precio_proveedor_sin_iva' => self::decimal_or_null( $row['precio_sin_iva'] ?? null ),
			'precio_proveedor_con_iva' => self::decimal_or_null( $row['precio_con_iva'] ?? null ),
			'precio_normal'         => $product ? self::decimal_or_null( $product->get_regular_price() ) : null,
			'precio_oferta'         => $product ? self::decimal_or_null( $product->get_sale_price() ) : null,
			'precio_venta'          => $product ? self::decimal_or_null( $product->get_price() ) : null,
			'moneda'                => sanitize_text_field( (string) ( $row['moneda'] ?: get_woocommerce_currency() ) ),
		);

		$table = TCSUP_Schema::history_table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Latest persisted snapshot must not be cached.
		$previous = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM %i WHERE catalog_id=%d ORDER BY fecha DESC, id DESC LIMIT 1', $table, $catalog_id ),
			ARRAY_A
		);
		if ( $previous ) {
			$changed = false;
			foreach ( $values as $field => $value ) {
				$old = $previous[ $field ] ?? null;
				if ( ( null === $value ) !== ( null === $old ) || ( null !== $value && ( 'moneda' === $field ? (string) $value !== (string) $old : ( 'catalog_id' === $field || 'product_id' === $field ? (int) $value !== (int) $old : (float) $value !== (float) $old ) ) ) ) {
					$changed = true;
					break;
				}
			}
			if ( ! $changed ) {
				return true;
			}
		}
		$values['origen'] = sanitize_key( $source );
		$values['fecha'] = current_time( 'mysql', true );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Append a price movement in the plugin-owned history table.
		return false !== $wpdb->insert( $table, $values );
	}

	private static function decimal_or_null( $value ) {
		return null === $value || '' === (string) $value || ! is_numeric( $value ) ? null : (string) $value;
	}

	/**
	 * Read a limited price history for one catalogue row.
	 */
	public static function recent( $catalog_id, $limit = 100 ) {
		global $wpdb;
		$catalog_id = absint( $catalog_id );
		if ( ! $catalog_id ) {
			return array();
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Current audit history, read-only.
		return (array) $wpdb->get_results(
			$wpdb->prepare( 'SELECT * FROM %i WHERE catalog_id=%d ORDER BY fecha DESC, id DESC LIMIT %d', TCSUP_Schema::history_table(), $catalog_id, min( 500, max( 1, absint( $limit ) ) ) ),
			ARRAY_A
		);
	}

	/**
	 * Capture effective WooCommerce prices for all linked supplier rows.
	 */
	public static function on_product_save( $product ) {
		if ( ! is_object( $product ) || ! method_exists( $product, 'get_id' ) ) {
			return;
		}
		$product_id = absint( $product->get_id() );
		if ( ! $product_id ) {
			return;
		}
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Product-to-catalogue link is current.
		$ids = (array) $wpdb->get_col(
			$wpdb->prepare( 'SELECT id FROM %i WHERE object_id=%d', TCSUP_Schema::catalog_table(), $product_id )
		);
		foreach ( $ids as $id ) {
			self::capture( $id, 'woocommerce', $product );
		}
	}
}
