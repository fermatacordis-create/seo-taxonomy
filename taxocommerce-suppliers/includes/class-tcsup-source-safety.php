<?php
/**
 * Keep explicitly supplier-linked WooCommerce products out of sale when their
 * only verified catalogue source was discarded.
 *
 * A supplier CSV decision is not, by itself, a reliable WooCommerce identity.
 * Never infer identity from a title or SKU alone, and never delete stock/orders.
 *
 * @package TaxoCommerceSuppliers
 */

defined( 'ABSPATH' ) || exit;

/**
 * Supplier-source publication safety. Does not depend on other TaxoCommerce plugins.
 */
final class TCSUP_Source_Safety {
	/**
	 * Find products with an explicit identity; ignore ambiguous matches.
	 *
	 * @param array $row Supplier catalogue row.
	 * @return int A verified WooCommerce product ID, or 0.
	 */
	public static function product_for_row( $row ) {
		$id = absint( $row['object_id'] ?? 0 );
		if ( $id && self::matches( $id, $row ) ) {
			return $id;
		}
		$catalog_id = absint( $row['id'] ?? 0 );
		if ( $catalog_id ) {
			$posts = get_posts(
				array(
					'post_type'      => 'product',
					'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
					'posts_per_page' => 2,
					'fields'         => 'ids',
					'no_found_rows'  => true,
					// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Bounded identity lookup.
					'meta_key'       => '_seo_proveedor_catalogo_id',
					// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Exact supplier row identity.
					'meta_value'     => $catalog_id,
				)
			);
			if ( 1 === count( $posts ) && self::matches( absint( $posts[0] ), $row ) ) {
				return absint( $posts[0] );
			}
			if ( count( $posts ) > 1 ) {
				return 0;
			}
		}
		$provider = trim( (string) ( $row['proveedor'] ?? '' ) );
		$external = trim( (string) ( $row['proveedor_id_externo'] ?? '' ) );
		if ( '' === $provider || '' === $external ) {
			return 0;
		}
		$posts = get_posts(
			array(
				'post_type'      => 'product',
				'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
				'posts_per_page' => 20,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Exact external product identity.
				'meta_key'       => '_seo_proveedor_id_externo',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Bounded match, provider verified below.
				'meta_value'     => $external,
			)
		);
		$matches = array();
		foreach ( $posts as $candidate ) {
			$candidate = absint( $candidate );
			if ( self::matches( $candidate, $row ) ) {
				$matches[] = $candidate;
			}
		}
		if ( 1 !== count( $matches ) || count( $posts ) >= 20 ) {
			return 0;
		}
		$matched_row = self::source_for_product( $matches[0] );
		return $matched_row && absint( $matched_row['id'] ?? 0 ) === $catalog_id ? $matches[0] : 0;
	}

	/**
	 * Never adopt a WooCommerce product just because its SKU resembles a feed row.
	 *
	 * @param int   $product_id WC product post ID.
	 * @param array $row Supplier catalogue row.
	 * @return bool
	 */
	private static function matches( $product_id, $row ) {
		if ( 'product' !== get_post_type( $product_id ) || 'trash' === get_post_status( $product_id ) ) {
			return false;
		}
		$row_id      = absint( $row['id'] ?? 0 );
		$catalog_id  = absint( get_post_meta( $product_id, '_seo_proveedor_catalogo_id', true ) );
		if ( $catalog_id ) {
			$mapped_id = absint( $row['object_id'] ?? 0 );
			return $catalog_id === $row_id && ( ! $mapped_id || $mapped_id === $product_id );
		}
		$provider    = trim( (string) get_post_meta( $product_id, '_seo_proveedor', true ) );
		$external    = trim( (string) get_post_meta( $product_id, '_seo_proveedor_id_externo', true ) );
		$row_country = strtoupper( trim( (string) ( $row['pais_origen'] ?? '' ) ) );
		$country     = strtoupper( trim( (string) get_post_meta( $product_id, '_seo_proveedor_pais', true ) ) );
		return '' !== $provider && '' !== $external &&
			0 === strcasecmp( $provider, trim( (string) ( $row['proveedor'] ?? '' ) ) ) &&
			$external === trim( (string) ( $row['proveedor_id_externo'] ?? '' ) ) &&
			( '' === $row_country || ( '' !== $country && $row_country === $country ) );
	}

	/**
	 * Match a supplier-linked WooCommerce product back to its authoritative row.
	 *
	 * @param int $product_id WooCommerce product ID.
	 * @return array|null
	 */
	private static function source_for_product( $product_id ) {
		$catalog_id = absint( get_post_meta( $product_id, '_seo_proveedor_catalogo_id', true ) );
		if ( $catalog_id ) {
			$row = TCSUP_Products::row( $catalog_id );
			return $row && self::matches( $product_id, $row ) ? $row : null;
		}
		$provider = trim( (string) get_post_meta( $product_id, '_seo_proveedor', true ) );
		$external = trim( (string) get_post_meta( $product_id, '_seo_proveedor_id_externo', true ) );
		if ( '' === $provider || '' === $external ) {
			return null;
		}
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Must use current source decision to block an unsafe sale.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE proveedor=%s AND proveedor_id_externo=%s ORDER BY id DESC LIMIT %d',
				TCSUP_Schema::catalog_table(),
				$provider,
				$external,
				20
			),
			ARRAY_A
		);
		if ( ! is_array( $rows ) || count( $rows ) >= 20 ) {
			return null;
		}
		$matches = array_values( array_filter( $rows, static function ( $row ) use ( $product_id ) {
			return self::matches( $product_id, $row ) && ( empty( $row['object_id'] ) || absint( $row['object_id'] ) === $product_id );
		} ) );
		return 1 === count( $matches ) ? $matches[0] : null;
	}

	/**
	 * A rejected source blocks sales only when it is the sole linked source.
	 *
	 * @param int $product_id WooCommerce product ID.
	 * @return bool
	 */
	public static function is_blocked( $product_id ) {
		$product_id = absint( $product_id );
		if ( ! $product_id || 'product' !== get_post_type( $product_id ) ) {
			return false;
		}
		$row = self::source_for_product( $product_id );
		if ( ! $row || 'descartado' !== (string) ( $row['estado_seleccion'] ?? '' ) ) {
			return false;
		}
		foreach ( TCSUP_Products::sources_for_product( $product_id ) as $source ) {
			if ( absint( $source['id'] ?? 0 ) !== absint( $row['id'] ?? 0 ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * WooCommerce storefront safety also applies to already-discarded rows.
	 *
	 * @param bool       $purchasable Existing WooCommerce decision.
	 * @param WC_Product $product Product.
	 * @return bool
	 */
	public static function purchasable( $purchasable, $product ) {
		return $purchasable && ( ! is_object( $product ) || ! method_exists( $product, 'get_id' ) || ! self::is_blocked( $product->get_id() ) );
	}

	/**
	 * Hide only positively linked discarded products from WooCommerce listings.
	 *
	 * @param bool $visible Current WooCommerce visibility.
	 * @param int  $product_id Product ID.
	 * @return bool
	 */
	public static function visible( $visible, $product_id ) {
		return $visible && ! self::is_blocked( $product_id );
	}

	/**
	 * When the administrator discards a positively linked source, unpublish if
	 * it has no other accepted source. Stock and existing orders are untouched.
	 * Re-acceptance does not automatically republish a product.
	 *
	 * @param array $discarded_row Catalogue row already marked discarded.
	 * @return bool Whether operation was safe/successful (including no match).
	 */
	public static function unpublish_discarded( $discarded_row ) {
		$id = self::product_for_row( $discarded_row );
		if ( ! $id || ! self::is_blocked( $id ) ) {
			return true;
		}
		$product = function_exists( 'wc_get_product' ) ? wc_get_product( $id ) : false;
		if ( ! $product ) {
			return false;
		}
		if ( 'publish' !== $product->get_status() ) {
			return true;
		}
		try {
			$product->set_status( 'draft' );
			if ( ! $product->save() ) {
				return false;
			}
			update_post_meta( $id, '_tcsup_supplier_discarded_catalog_id', absint( $discarded_row['id'] ?? 0 ) );
		} catch ( Throwable $e ) {
			return false;
		}
		return true;
	}

	/**
	 * Surface a legacy WooCommerce SKU collision for manual reconciliation,
	 * without making any unsafe assumption about its actual supplier.
	 *
	 * @param array $row Discarded, unlinked catalogue record.
	 * @return int Published product ID for a possible exact SKU match, or zero.
	 */
	public static function possible_orphan( $row ) {
		if ( 'descartado' !== (string) ( $row['estado_seleccion'] ?? '' ) || absint( $row['object_id'] ?? 0 ) ) {
			return 0;
		}
		$sku = trim( (string) ( $row['sku'] ?? '' ) );
		if ( '' === $sku || ! function_exists( 'wc_get_product_id_by_sku' ) ) {
			return 0;
		}
		$id = absint( wc_get_product_id_by_sku( $sku ) );
		return $id && 'product' === get_post_type( $id ) && 'publish' === get_post_status( $id ) &&
			! self::matches( $id, $row ) ? $id : 0;
	}
}
