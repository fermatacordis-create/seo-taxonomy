<?php
/**
 * Supplier inventory decisions, WooCommerce linking, prices, and external images.
 *
 * @package TaxoCommerceSuppliers
 */

defined( 'ABSPATH' ) || exit;

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound -- Stable TCSUP_ public class names are an existing namespace/API contract; renaming would break extensions.
final class TCSUP_Products {

	private static function guard() {
		if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage supplier products.', 'taxocommerce-suppliers' ) );
		}
	}

	public static function row( $id ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Read the current supplier inventory/schema for this admin operation; stale cache would misreport changes.
		return $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE id=%d LIMIT 1',
				TCSUP_Schema::catalog_table(),
				absint( $id )
			),
			ARRAY_A
		);
	}

	private static function redirect( $tab, $args = array() ) {
		wp_safe_redirect(
			add_query_arg(
				array_merge( array( 'page' => 'taxocommerce-suppliers', 'tab' => $tab ), $args ),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	private static function default_category_id() {
		$term_id = absint( get_option( 'default_product_cat', 0 ) );
		if ( $term_id && term_exists( $term_id, 'product_cat' ) ) {
			return $term_id;
		}
		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
				'number'     => 1,
				'orderby'    => 'term_id',
				'order'      => 'ASC',
			)
		);
		return ! is_wp_error( $terms ) && ! empty( $terms[0]->term_id ) ? absint( $terms[0]->term_id ) : 0;
	}

	private static function existing_product( $row ) {
		$product_id = absint( $row['object_id'] ?? 0 );
		if ( $product_id && 'product' === get_post_type( $product_id ) && 'trash' !== get_post_status( $product_id ) ) {
			return $product_id;
		}

		$catalog_id = absint( $row['id'] ?? 0 );
		if ( $catalog_id ) {
			$posts = get_posts(
				array(
					'post_type'      => 'product',
					'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
					'fields'         => 'ids',
					'posts_per_page' => 1,
					'meta_key'       => '_seo_proveedor_catalogo_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Bounded exact supplier identity lookup; no unbounded meta scans.
					'meta_value'     => $catalog_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Supplier identity lookup restricted to bounded product IDs.
				)
			);
			if ( $posts ) {
				return absint( $posts[0] );
			}
		}

		$provider = sanitize_text_field( (string) ( $row['proveedor'] ?? '' ) );
		$external = sanitize_text_field( (string) ( $row['proveedor_id_externo'] ?? '' ) );
		if ( $provider && $external ) {
			$posts = get_posts(
				array(
					'post_type'      => 'product',
					'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
					'fields'         => 'ids',
					'posts_per_page' => 20,
					'meta_key'       => '_seo_proveedor_id_externo', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Bounded exact supplier identity lookup; no unbounded meta scans.
					'meta_value'     => $external, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Supplier identity lookup restricted to bounded product IDs.
				)
			);
			foreach ( $posts as $candidate ) {
				if ( 0 === strcasecmp( (string) get_post_meta( $candidate, '_seo_proveedor', true ), $provider ) ) {
					$country = strtoupper( (string) ( $row['pais_origen'] ?? '' ) );
					$linked_country = strtoupper( (string) get_post_meta( $candidate, '_seo_proveedor_pais', true ) );
					if ( '' === $country || '' === $linked_country || $country === $linked_country ) {
						return absint( $candidate );
					}
				}
			}
		}

		$provider = sanitize_text_field( (string) ( $row['proveedor'] ?? '' ) );
		$sku      = trim( sanitize_text_field( (string) ( $row['sku'] ?? '' ) ) );
		$country  = strtoupper( trim( (string) ( $row['pais_origen'] ?? '' ) ) );

		// VEVOR can reuse supplier SKUs across market-specific variants, so SKU
		// alone is never a safe WooCommerce identity for that provider.
		if ( $sku && function_exists( 'wc_get_product_id_by_sku' ) && ! ( 0 === strcasecmp( $provider, 'VEVOR' ) && $country ) ) {
			$candidate = absint( wc_get_product_id_by_sku( $sku ) );
			if ( $candidate && 'product' === get_post_type( $candidate ) && 'trash' !== get_post_status( $candidate ) ) {
				$linked_provider = sanitize_text_field( (string) get_post_meta( $candidate, '_seo_proveedor', true ) );
				if ( $linked_provider && 0 !== strcasecmp( $linked_provider, $provider ) ) {
					return new WP_Error(
						'tcsup_sku_provider_conflict',
						__( 'Supplier SKU is already linked in WooCommerce to a different supplier.', 'taxocommerce-suppliers' )
					);
				}
				return $candidate;
			}
		}

		return 0;
	}

	private static function internal_sku( $row, $is_new ) {
		$supplier_sku = trim( (string) ( $row['sku'] ?? '' ) );
		if ( ! function_exists( 'wc_get_product_id_by_sku' ) ) {
			return $supplier_sku;
		}

		$provider = strtoupper( trim( (string) ( $row['proveedor'] ?? '' ) ) );
		$country  = strtolower( trim( (string) ( $row['pais_origen'] ?? '' ) ) );
		if ( 'VEVOR' === $provider && '' !== $country ) {
			return 'vevor-' . $country . '-row-' . absint( $row['id'] ?? 0 );
		}

		if ( '' === $supplier_sku ) {
			return '';
		}
		$owner = absint( wc_get_product_id_by_sku( $supplier_sku ) );
		if ( ! $owner || ! $is_new ) {
			return $supplier_sku;
		}
		return '';
	}

	private static function brand_taxonomy() {
		$candidates = array( 'product_brand', 'pwb-brand', 'yith_product_brand', 'pa_marca', 'pa_brand' );
		foreach ( $candidates as $taxonomy ) {
			if ( taxonomy_exists( $taxonomy ) ) {
				return $taxonomy;
			}
		}

		$taxonomies = get_object_taxonomies( 'product', 'objects' );
		foreach ( (array) $taxonomies as $taxonomy => $object ) {
			$label = remove_accents(
				strtolower(
					trim( (string) ( $object->labels->singular_name ?? $object->label ?? '' ) )
				)
			);
			if ( in_array( $label, array( 'marca', 'brand' ), true ) ) {
				return (string) $taxonomy;
			}
		}
		return '';
	}

	private static function assign_brand( $product_id, $brand ) {
		$product_id = absint( $product_id );
		$brand      = sanitize_text_field( trim( (string) $brand ) );
		if ( ! $product_id || '' === $brand ) {
			return true;
		}

		$taxonomy = self::brand_taxonomy();
		if ( '' === $taxonomy ) {
			return new WP_Error( 'tcsup_brand_taxonomy', __( 'No product brand taxonomy is available.', 'taxocommerce-suppliers' ) );
		}

		$term = term_exists( $brand, $taxonomy );
		if ( ! $term ) {
			$term = wp_insert_term( $brand, $taxonomy );
		}
		if ( is_wp_error( $term ) ) {
			return $term;
		}

		$term_id = is_array( $term ) ? absint( $term['term_id'] ) : absint( $term );
		$assigned = wp_set_object_terms( $product_id, array( $term_id ), $taxonomy, false );
		if ( is_wp_error( $assigned ) ) {
			return $assigned;
		}

		update_post_meta( $product_id, '_seo_marca_proveedor', $brand );
		update_post_meta( $product_id, '_seo_taxonomia_marca', $taxonomy );
		return true;
	}

	private static function base_price( $row ) {
		foreach ( array( 'precio_con_iva', 'precio_sin_iva' ) as $field ) {
			if ( isset( $row[ $field ] ) && '' !== (string) $row[ $field ] && is_numeric( $row[ $field ] ) ) {
				return (float) $row[ $field ];
			}
		}
		return null;
	}

	private static function acceptance_lock_name( $row ) {
		$identity = implode(
			'|',
			array(
				strtoupper( trim( (string) ( $row['proveedor'] ?? '' ) ) ),
				strtoupper( trim( (string) ( $row['pais_origen'] ?? '' ) ) ),
				trim( (string) ( $row['proveedor_id_externo'] ?? '' ) ),
				trim( (string) ( $row['source_row_key'] ?? '' ) ),
				(string) absint( $row['id'] ?? 0 ),
			)
		);
		return 'tcsup_' . substr( hash( 'sha256', $identity ), 0, 48 );
	}

	private static function acquire_acceptance_lock( $row ) {
		global $wpdb;
		$name = self::acceptance_lock_name( $row );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Current MySQL supplier acceptance locks or inventory status must be read directly.
		$locked = (int) $wpdb->get_var(
			$wpdb->prepare( 'SELECT GET_LOCK(%s,%d)', $name, 10 )
		);
		return 1 === $locked
			? $name
			: new WP_Error( 'tcsup_accept_lock', __( 'Another acceptance process is already working on this supplier product.', 'taxocommerce-suppliers' ) );
	}

	private static function release_acceptance_lock( $name ) {
		if ( ! $name ) {
			return;
		}
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Current MySQL supplier acceptance locks or inventory status must be read directly.
		$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $name ) );
	}

	public static function accept( $id ) {
		$row = self::row( $id );
		if ( ! $row ) {
			return new WP_Error( 'tcsup_missing_row', __( 'Supplier catalogue row was not found.', 'taxocommerce-suppliers' ) );
		}

		$lock = self::acquire_acceptance_lock( $row );
		if ( is_wp_error( $lock ) ) {
			return $lock;
		}

		try {
			return self::accept_unlocked( $id );
		} finally {
			self::release_acceptance_lock( $lock );
		}
	}

	private static function accept_unlocked( $id ) {
		if ( ! class_exists( 'WooCommerce' ) || ! class_exists( 'WC_Product_Simple' ) ) {
			return new WP_Error( 'tcsup_woocommerce', __( 'WooCommerce is required to accept supplier products.', 'taxocommerce-suppliers' ) );
		}

		$row = self::row( $id );
		if ( ! $row ) {
			return new WP_Error( 'tcsup_missing_row', __( 'Supplier catalogue row was not found.', 'taxocommerce-suppliers' ) );
		}

		$product_match = self::existing_product( $row );
		if ( is_wp_error( $product_match ) ) {
			return $product_match;
		}
		$product_id = absint( $product_match );
		$is_new     = ! $product_id;
		$product    = $product_id ? wc_get_product( $product_id ) : new WC_Product_Simple();
		if ( ! $product ) {
			return new WP_Error( 'tcsup_product', __( 'WooCommerce product could not be loaded.', 'taxocommerce-suppliers' ) );
		}

		if ( $is_new ) {
			$category_id = self::default_category_id();
			if ( ! $category_id ) {
				return new WP_Error( 'tcsup_category', __( 'A default WooCommerce product category is required before accepting new products.', 'taxocommerce-suppliers' ) );
			}
			$product->set_status( 'draft' );
			$product->set_catalog_visibility( 'hidden' );
			$product->set_category_ids( array( $category_id ) );
			$product->set_name( sanitize_text_field( (string) ( $row['nombre'] ?? '' ) ) );
			$product->set_description( wp_kses_post( (string) ( $row['descripcion'] ?? '' ) ) );
		} elseif ( '' === (string) $product->get_name() ) {
			$product->set_name( sanitize_text_field( (string) ( $row['nombre'] ?? '' ) ) );
		}

		$sku = self::internal_sku( $row, $is_new );
		if ( '' !== $sku ) {
			$current_sku = (string) $product->get_sku();
			if ( $is_new || '' === $current_sku || $current_sku === $sku ) {
				$product->set_sku( $sku );
			}
		}

		$base = self::base_price( $row );
		if ( null !== $base ) {
			$sale_margin    = (float) get_option( 'taxocommerce_suppliers_sale_margin', 20 );
			$regular_margin = (float) get_option( 'taxocommerce_suppliers_regular_margin', 45 );
			if ( $regular_margin < $sale_margin ) {
				$regular_margin = $sale_margin;
			}
			$sale    = round( $base * ( 1 + $sale_margin / 100 ), wc_get_price_decimals() );
			$regular = round( $base * ( 1 + $regular_margin / 100 ), wc_get_price_decimals() );
			$product->set_regular_price( wc_format_decimal( $regular ) );
			$product->set_sale_price( wc_format_decimal( $sale ) );
			$product->set_price( wc_format_decimal( $sale ) );
		}

		$qty = $row['stock_cantidad'] ?? null;
		if ( null !== $qty && '' !== (string) $qty && is_numeric( $qty ) ) {
			$product->set_manage_stock( true );
			$product->set_stock_quantity( max( 0, (float) $qty ) );
			$product->set_stock_status( (float) $qty > 0 ? 'instock' : 'outofstock' );
		} else {
			$product->set_manage_stock( false );
			$stock_state = sanitize_key( (string) ( $row['stock_estado'] ?? '' ) );
			$product->set_stock_status(
				in_array( $stock_state, array( 'out_of_stock', 'outofstock', 'agotado', 'sin_stock' ), true )
					? 'outofstock'
					: 'instock'
			);
		}

		$product_id = absint( $product->save() );
		if ( ! $product_id ) {
			return new WP_Error( 'tcsup_save', __( 'WooCommerce did not return a product ID.', 'taxocommerce-suppliers' ) );
		}

		$meta = array(
			'_seo_proveedor'                    => (string) ( $row['proveedor'] ?? '' ),
			'_seo_proveedor_id_externo'         => (string) ( $row['proveedor_id_externo'] ?? '' ),
			'_seo_proveedor_catalogo_id'        => absint( $row['id'] ?? 0 ),
			'_seo_proveedor_pais'               => (string) ( $row['pais_origen'] ?? '' ),
			'_seo_proveedor_sku'                => (string) ( $row['sku'] ?? '' ),
			'_seo_categoria_proveedor'          => (string) ( $row['categoria_proveedor'] ?? '' ),
			'_seo_precio_proveedor'             => null === $base ? '' : wc_format_decimal( $base ),
			'_seo_proveedor_mpn'                => (string) ( $row['mpn'] ?? '' ),
			'_seo_proveedor_url_origen'         => (string) ( $row['url_origen'] ?? '' ),
			'_seo_proveedor_url_canonica'       => (string) ( $row['url_canonica'] ?? '' ),
			'_seo_proveedor_precio_sin_iva'     => (string) ( $row['precio_sin_iva'] ?? '' ),
			'_seo_proveedor_precio_con_iva'     => (string) ( $row['precio_con_iva'] ?? '' ),
			'_seo_proveedor_iva_porcentaje'     => (string) ( $row['iva_porcentaje'] ?? '' ),
			'_seo_proveedor_moneda'             => (string) ( $row['moneda'] ?? '' ),
			'_seo_proveedor_stock_estado'       => (string) ( $row['stock_estado'] ?? '' ),
			'_seo_proveedor_stock_cantidad'     => (string) ( $row['stock_cantidad'] ?? '' ),
			'_seo_proveedor_stock_texto'        => (string) ( $row['stock_texto'] ?? '' ),
			'_seo_proveedor_hash_producto'      => (string) ( $row['hash_producto'] ?? '' ),
			'_seo_proveedor_ultima_importacion' => (string) ( $row['ultima_importacion'] ?? '' ),
		);
		foreach ( $meta as $key => $value ) {
			update_post_meta( $product_id, $key, $value );
		}

		$brand_result = self::assign_brand( $product_id, (string) ( $row['marca'] ?? '' ) );
		if ( is_wp_error( $brand_result ) ) {
			update_post_meta( $product_id, '_seo_marca_import_error', $brand_result->get_error_message() );
		} else {
			delete_post_meta( $product_id, '_seo_marca_import_error' );
		}

		$image_mode = sanitize_key( (string) ( $row['modo_imagenes'] ?? 'inherit' ) );
		if ( 'external' === $image_mode ) {
			$images_result = self::sync_external_images( $row, $product_id );
			if ( is_wp_error( $images_result ) ) {
				update_post_meta( $product_id, '_seo_imagen_import_error', $images_result->get_error_message() );
			} else {
				delete_post_meta( $product_id, '_seo_imagen_import_error' );
				update_post_meta( $product_id, '_seo_imagenes_externas', 1 );
				update_post_meta( $product_id, '_seo_imagenes_externas_total', absint( $images_result['count'] ?? 0 ) );
			}
		} elseif ( 'local' === $image_mode ) {
			delete_post_meta( $product_id, '_seo_imagenes_externas' );
			delete_post_meta( $product_id, '_seo_imagenes_externas_total' );
		}

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Synchronous write to Suppliers-owned inventory/lock state; caching writes is not applicable.
		$wpdb->update(
			TCSUP_Schema::catalog_table(),
			array(
				'object_id'               => $product_id,
				'estado_seleccion'        => 'aceptado',
				'estado_sincronizacion'   => 'sincronizado',
				'hash_aplicado'           => (string) ( $row['hash_producto'] ?? '' ),
				'snapshot_aplicado'       => wp_json_encode( $row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
				'ultima_sincronizacion'   => current_time( 'mysql' ),
				'ultimo_error_sync'       => '',
				'actualizado'             => current_time( 'mysql' ),
			),
			array( 'id' => absint( $row['id'] ) )
		);

		TCSUP_Price_History::capture( absint( $row['id'] ), 'sync' );
		return array( 'product_id' => $product_id, 'created' => $is_new );
	}

	public static function set_selection( $id, $selection ) {
		$allowed = array( 'pendiente', 'revisar', 'aceptado', 'descartado' );
		$selection = sanitize_key( $selection );
		if ( ! in_array( $selection, $allowed, true ) ) {
			return false;
		}
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Synchronous write to Suppliers-owned inventory/lock state; caching writes is not applicable.
		return false !== $wpdb->update(
			TCSUP_Schema::catalog_table(),
			array(
				'estado_seleccion'      => $selection,
				'estado_sincronizacion' => 'descartado' === $selection ? 'ignorado' : 'pendiente',
				'actualizado'           => current_time( 'mysql' ),
			),
			array( 'id' => absint( $id ) )
		);
	}

	private static function deactivate_external_images( $row ) {
		global $wpdb;
		$table          = TCSUP_Schema::images_table();
		$catalog_row_id = absint( $row['id'] ?? 0 );
		$supplier       = sanitize_text_field( (string) ( $row['proveedor'] ?? '' ) );
		$supplier_sku   = sanitize_text_field( (string) ( $row['sku'] ?? '' ) );
		if ( '' === $supplier_sku ) {
			$supplier_sku = sanitize_text_field( (string) ( $row['proveedor_id_externo'] ?? '' ) );
		}

		if ( $catalog_row_id && $supplier && $supplier_sku ) {
			$sql = $wpdb->prepare(
				"UPDATE %i
				 SET status=%s,updated_at=%s
				 WHERE catalog_row_id=%d OR (supplier=%s AND supplier_sku=%s)",
				$table,
				'inactive',
				current_time( 'mysql' ),
				$catalog_row_id,
				$supplier,
				$supplier_sku
			);
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Query is fully prepared above.
			$wpdb->query( $sql );
			return;
		}

		if ( $catalog_row_id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Synchronous write to Suppliers-owned inventory/lock state; caching writes is not applicable.
			$wpdb->update(
				$table,
				array( 'status' => 'inactive', 'updated_at' => current_time( 'mysql' ) ),
				array( 'catalog_row_id' => $catalog_row_id )
			);
		}
	}

	public static function sync_external_images( $row, $product_id = 0 ) {
		global $wpdb;

		$urls = TCSUP_CSV::image_urls( $row['imagenes'] ?? '' );
		$table = TCSUP_Schema::images_table();
		$catalog_row_id = absint( $row['id'] ?? 0 );
		$product_id = $product_id ? absint( $product_id ) : absint( $row['object_id'] ?? 0 );
		$supplier = sanitize_text_field( (string) ( $row['proveedor'] ?? '' ) );
		$supplier_sku = sanitize_text_field( (string) ( $row['sku'] ?? '' ) );
		if ( '' === $supplier_sku ) {
			$supplier_sku = sanitize_text_field( (string) ( $row['proveedor_id_externo'] ?? '' ) );
		}

		self::deactivate_external_images( $row );

		$count = 0;
		foreach ( $urls as $position => $url ) {
			$hash = hash( 'sha256', $url );
			$existing_id = absint(
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Current MySQL supplier acceptance locks or inventory status must be read directly.
				$wpdb->get_var(
					$wpdb->prepare(
						"SELECT id FROM %i WHERE supplier=%s AND supplier_sku=%s AND image_hash=%s LIMIT 1",
						$table,
						$supplier,
						$supplier_sku,
						$hash
					)
				)
			);
			$data = array(
				'supplier'            => $supplier,
				'supplier_product_id' => sanitize_text_field( (string) ( $row['proveedor_id_externo'] ?? '' ) ),
				'supplier_sku'        => $supplier_sku,
				'product_id'          => $product_id ?: null,
				'catalog_row_id'      => $catalog_row_id ?: null,
				'source_url'          => esc_url_raw( (string) ( $row['url_origen'] ?? '' ) ),
				'source_file'         => 'taxocommerce-suppliers-csv',
				'image_url'           => $url,
				'image_hash'          => $hash,
				'position'            => absint( $position ),
				'is_primary'          => 0 === $position ? 1 : 0,
				'status'              => 'active',
				'updated_at'          => current_time( 'mysql' ),
			);
			if ( $existing_id ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Synchronous write to Suppliers-owned inventory/lock state; caching writes is not applicable.
				$wpdb->update( $table, $data, array( 'id' => $existing_id ) );
			} else {
				$data['created_at'] = current_time( 'mysql' );
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Synchronous write to Suppliers-owned inventory/lock state; caching writes is not applicable.
				$wpdb->insert( $table, $data );
			}
			$count++;
		}

		return array( 'count' => $count );
	}

	public static function handle_row_action() {
		self::guard();
		check_admin_referer( 'tcsup_row_action' );
		$id     = isset( $_POST['row_id'] ) ? absint( $_POST['row_id'] ) : 0;
		$action = isset( $_POST['supplier_action'] ) ? sanitize_key( wp_unslash( $_POST['supplier_action'] ) ) : '';
		$result = true;

		if ( 'accept' === $action ) {
			$result = self::accept( $id );
		} elseif ( in_array( $action, array( 'discard', 'review', 'pending' ), true ) ) {
			$selection = array( 'discard' => 'descartado', 'review' => 'revisar', 'pending' => 'pendiente' );
			$result = self::set_selection( $id, $selection[ $action ] );
		}

		if ( is_wp_error( $result ) ) {
			self::redirect( 'inventory', array( 'tc_error_message' => rawurlencode( $result->get_error_message() ) ) );
		}
		self::redirect( 'inventory', array( 'row_updated' => 1 ) );
	}

	public static function handle_bulk_action() {
		self::guard();
		check_admin_referer( 'tcsup_bulk_action' );
		$ids = isset( $_POST['row_ids'] ) && is_array( $_POST['row_ids'] ) ? array_map( 'absint', wp_unslash( $_POST['row_ids'] ) ) : array();
		$action = isset( $_POST['bulk_supplier_action'] ) ? sanitize_key( wp_unslash( $_POST['bulk_supplier_action'] ) ) : '';

		$row_action = isset( $_POST['row_action'] ) ? sanitize_text_field( wp_unslash( $_POST['row_action'] ) ) : '';
		if ( $row_action && false !== strpos( $row_action, ':' ) ) {
			list( $single_action, $single_id ) = array_pad( explode( ':', $row_action, 2 ), 2, '' );
			$single_action = sanitize_key( $single_action );
			$single_id     = absint( $single_id );
			if ( $single_id && in_array( $single_action, array( 'accept', 'review', 'discard', 'pending' ), true ) ) {
				$ids    = array( $single_id );
				$action = $single_action;
			}
		}

		$done = 0;
		$errors = 0;

		foreach ( array_filter( $ids ) as $id ) {
			if ( 'accept' === $action ) {
				$result = self::accept( $id );
			} else {
				$map = array( 'discard' => 'descartado', 'review' => 'revisar', 'pending' => 'pendiente' );
				$result = isset( $map[ $action ] ) ? self::set_selection( $id, $map[ $action ] ) : false;
			}
			if ( is_wp_error( $result ) || false === $result ) {
				$errors++;
			} else {
				$done++;
			}
		}

		self::redirect( 'inventory', array( 'bulk_done' => $done, 'bulk_errors' => $errors ) );
	}

	public static function handle_price_update() {
		self::guard();
		check_admin_referer( 'tcsup_price_update' );
		$id = isset( $_POST['row_id'] ) ? absint( $_POST['row_id'] ) : 0;
		$row = self::row( $id );
		if ( ! $row ) {
			self::redirect( 'prices', array( 'tc_error' => 'missing' ) );
		}

		TCSUP_Price_History::capture( $id, 'baseline' );

		$without_tax = isset( $_POST['precio_sin_iva'] ) ? self::decimal( sanitize_text_field( wp_unslash( $_POST['precio_sin_iva'] ) ) ) : null;
		$with_tax    = isset( $_POST['precio_con_iva'] ) ? self::decimal( sanitize_text_field( wp_unslash( $_POST['precio_con_iva'] ) ) ) : null;
		$tax         = isset( $_POST['iva_porcentaje'] ) ? self::decimal( sanitize_text_field( wp_unslash( $_POST['iva_porcentaje'] ) ) ) : null;
		$currency    = isset( $_POST['moneda'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_POST['moneda'] ) ) ) : 'EUR';

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Synchronous write to Suppliers-owned inventory/lock state; caching writes is not applicable.
		$wpdb->update(
			TCSUP_Schema::catalog_table(),
			array(
				'precio_sin_iva'      => $without_tax,
				'precio_con_iva'      => $with_tax,
				'iva_porcentaje'      => $tax,
				'moneda'              => $currency,
				'estado_sincronizacion'=> 'modificado',
				'cambios_detectados'  => 'price',
				'actualizado'         => current_time( 'mysql' ),
			),
			array( 'id' => $id )
		);

		$product_id = absint( $row['object_id'] ?? 0 );
		if ( $product_id ) {
			$base = null !== $with_tax ? $with_tax : $without_tax;
			update_post_meta( $product_id, '_seo_precio_proveedor', null === $base ? '' : $base );
			update_post_meta( $product_id, '_seo_proveedor_precio_sin_iva', null === $without_tax ? '' : $without_tax );
			update_post_meta( $product_id, '_seo_proveedor_precio_con_iva', null === $with_tax ? '' : $with_tax );
			update_post_meta( $product_id, '_seo_proveedor_iva_porcentaje', null === $tax ? '' : $tax );
			update_post_meta( $product_id, '_seo_proveedor_moneda', $currency );
		}

		TCSUP_Price_History::capture( $id, 'manual' );

		if ( $product_id && ! empty( $_POST['apply_woo'] ) && class_exists( 'WooCommerce' ) ) {
			$product = wc_get_product( $product_id );
			$regular = isset( $_POST['woo_regular'] ) ? self::decimal( sanitize_text_field( wp_unslash( $_POST['woo_regular'] ) ) ) : null;
			$sale    = isset( $_POST['woo_sale'] ) ? self::decimal( sanitize_text_field( wp_unslash( $_POST['woo_sale'] ) ) ) : null;
			if ( $product ) {
				if ( null !== $regular ) {
					$product->set_regular_price( wc_format_decimal( $regular ) );
				}
				$product->set_sale_price( null === $sale ? '' : wc_format_decimal( $sale ) );
				$product->set_price( null !== $sale ? wc_format_decimal( $sale ) : wc_format_decimal( $regular ) );
				$product->save();
			}
		}

		self::redirect( 'prices', array( 'price_saved' => 1 ) );
	}

	private static function decimal( $value ) {
		$value = str_replace( ',', '.', trim( (string) $value ) );
		$value = preg_replace( '/[^0-9.\-]/', '', $value );
		return '' !== $value && is_numeric( $value ) ? (string) $value : null;
	}

	public static function handle_images_update() {
		self::guard();
		check_admin_referer( 'tcsup_images_update' );
		$id = isset( $_POST['row_id'] ) ? absint( $_POST['row_id'] ) : 0;
		$row = self::row( $id );
		if ( ! $row ) {
			self::redirect( 'images', array( 'tc_error' => 'missing' ) );
		}

		$mode = isset( $_POST['image_mode'] ) ? sanitize_key( wp_unslash( $_POST['image_mode'] ) ) : 'external';
		if ( ! in_array( $mode, array( 'external', 'local', 'inherit' ), true ) ) {
			$mode = 'external';
		}
		$urls = isset( $_POST['image_urls'] ) ? TCSUP_CSV::image_urls( sanitize_textarea_field( wp_unslash( $_POST['image_urls'] ) ) ) : array();
		$encoded = $urls ? wp_json_encode( $urls, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) : '';

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Synchronous write to Suppliers-owned inventory/lock state; caching writes is not applicable.
		$wpdb->update(
			TCSUP_Schema::catalog_table(),
			array(
				'imagenes'      => $encoded,
				'modo_imagenes' => $mode,
				'actualizado'   => current_time( 'mysql' ),
			),
			array( 'id' => $id )
		);

		$row['imagenes']      = $encoded;
		$row['modo_imagenes'] = $mode;
		if ( 'external' === $mode ) {
			self::sync_external_images( $row, absint( $row['object_id'] ?? 0 ) );
		} else {
			self::deactivate_external_images( $row );
		}

		self::redirect( 'images', array( 'images_saved' => 1, 'row_id' => $id ) );
	}

	public static function sources_for_product( $product_id ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Read the current supplier inventory/schema for this admin operation; stale cache would misreport changes.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE object_id=%d AND estado_seleccion IN (%s,%s,%s) ORDER BY actualizado DESC,id DESC',
				TCSUP_Schema::catalog_table(),
				absint( $product_id ),
				'aceptado',
				'publicado',
				'actualizar'
			),
			ARRAY_A
		);
		return is_array( $rows ) ? $rows : array();
	}
}
