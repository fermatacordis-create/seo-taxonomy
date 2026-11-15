<?php
/**
 * Supplier catalogue storage.
 *
 * @package TaxoCommerceSuppliers
 */

defined( 'ABSPATH' ) || exit;

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound -- Stable TCSUP_ public class names are an existing namespace/API contract; renaming would break extensions.
final class TCSUP_Schema {

	const DB_VERSION = '1.2.0';

	public static function catalog_table() {
		global $wpdb;
		return $wpdb->prefix . 'seo_proveedores_productos';
	}

	public static function history_table() {
		global $wpdb;
		return $wpdb->prefix . 'seo_proveedores_productos_historico';
	}

	public static function images_table() {
		global $wpdb;
		return $wpdb->prefix . 'seo_supplier_images';
	}

	public static function profiles_table() {
		global $wpdb;
		return $wpdb->prefix . 'seo_proveedores_fichas';
	}

	public static function install() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		$catalog = self::catalog_table();
		$images  = self::images_table();
		$history = self::history_table();
		$profiles = self::profiles_table();

		$sql_catalog = "CREATE TABLE {$catalog} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			proveedor VARCHAR(190) NOT NULL,
			proveedor_id_externo VARCHAR(190) NOT NULL,
			source_row_key VARCHAR(64) NOT NULL DEFAULT '',
			sku VARCHAR(190) NOT NULL DEFAULT '',
			pais_origen VARCHAR(2) NOT NULL DEFAULT '',
			idioma_origen VARCHAR(10) NOT NULL DEFAULT '',
			mpn VARCHAR(190) NOT NULL DEFAULT '',
			url_origen TEXT NULL,
			url_canonica TEXT NULL,
			nombre VARCHAR(255) NOT NULL,
			descripcion LONGTEXT NULL,
			marca VARCHAR(190) NOT NULL DEFAULT '',
			categoria_proveedor TEXT NULL,
			precio_sin_iva DECIMAL(18,6) NULL,
			precio_con_iva DECIMAL(18,6) NULL,
			iva_porcentaje DECIMAL(8,4) NULL,
			moneda VARCHAR(12) NOT NULL DEFAULT 'EUR',
			stock_estado VARCHAR(50) NOT NULL DEFAULT '',
			stock_cantidad DECIMAL(18,4) NULL,
			stock_texto VARCHAR(255) NOT NULL DEFAULT '',
			imagenes LONGTEXT NULL,
			hash_producto CHAR(64) NOT NULL DEFAULT '',
			hash_aplicado CHAR(64) NULL,
			snapshot_aplicado LONGTEXT NULL,
			raw_json LONGTEXT NULL,
			estado_seleccion VARCHAR(30) NOT NULL DEFAULT 'pendiente',
			estado_sincronizacion VARCHAR(30) NOT NULL DEFAULT 'legacy',
			object_id BIGINT UNSIGNED NULL,
			modo_imagenes VARCHAR(20) NOT NULL DEFAULT 'inherit',
			last_seen_run_id BIGINT UNSIGNED NULL,
			last_applied_run_id BIGINT UNSIGNED NULL,
			missing_since_run_id BIGINT UNSIGNED NULL,
			primera_importacion DATETIME NULL,
			ultima_importacion DATETIME NULL,
			ultima_sincronizacion DATETIME NULL,
			ultimo_error_sync TEXT NULL,
			cambios_detectados TEXT NULL,
			creado DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			actualizado DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY proveedor_pais_producto (proveedor,pais_origen,proveedor_id_externo,source_row_key),
			KEY source_row_key (source_row_key),
			KEY proveedor_externo (proveedor,proveedor_id_externo),
			KEY proveedor_sku (proveedor,sku),
			KEY proveedor_pais_sku (proveedor,pais_origen,sku),
			KEY object_id (object_id),
			KEY estado_seleccion (estado_seleccion),
			KEY estado_sincronizacion (estado_sincronizacion),
			KEY last_seen_run_id (last_seen_run_id),
			KEY hash_producto (hash_producto)
		) {$charset};";

		$sql_history = "CREATE TABLE {$history} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			catalog_id BIGINT UNSIGNED NOT NULL,
			product_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			fecha DATETIME NOT NULL,
			precio_proveedor_sin_iva DECIMAL(18,6) NULL,
			precio_proveedor_con_iva DECIMAL(18,6) NULL,
			precio_normal DECIMAL(18,6) NULL,
			precio_oferta DECIMAL(18,6) NULL,
			precio_venta DECIMAL(18,6) NULL,
			moneda VARCHAR(12) NOT NULL DEFAULT 'EUR',
			origen VARCHAR(30) NOT NULL DEFAULT '',
			PRIMARY KEY  (id),
			KEY catalog_fecha (catalog_id,fecha),
			KEY product_fecha (product_id,fecha)
		) {$charset};";

		$sql_images = "CREATE TABLE {$images} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			supplier VARCHAR(190) NOT NULL,
			supplier_product_id VARCHAR(190) DEFAULT NULL,
			supplier_sku VARCHAR(190) NOT NULL,
			product_id BIGINT UNSIGNED DEFAULT NULL,
			catalog_row_id BIGINT UNSIGNED DEFAULT NULL,
			source_url TEXT DEFAULT NULL,
			source_file VARCHAR(255) DEFAULT NULL,
			image_url TEXT NOT NULL,
			image_hash CHAR(64) NOT NULL,
			position INT UNSIGNED NOT NULL DEFAULT 0,
			is_primary TINYINT(1) NOT NULL DEFAULT 0,
			status VARCHAR(20) NOT NULL DEFAULT 'active',
			last_checked DATETIME DEFAULT NULL,
			http_status SMALLINT UNSIGNED DEFAULT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY uniq_supplier_image (supplier,supplier_sku,image_hash),
			KEY idx_supplier_sku (supplier,supplier_sku),
			KEY idx_product_id (product_id),
			KEY idx_catalog_row_id (catalog_row_id),
			KEY idx_status (status)
		) {$charset};";

		// The imported catalogue remains the sole source of supplier identities.
		// This table stores optional private contact information only.
		$sql_profiles = "CREATE TABLE {$profiles} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			proveedor VARCHAR(190) NOT NULL,
			nombre_comercial VARCHAR(190) NOT NULL DEFAULT '',
			razon_social VARCHAR(190) NOT NULL DEFAULT '',
			nif VARCHAR(40) NOT NULL DEFAULT '',
			telefono VARCHAR(60) NOT NULL DEFAULT '',
			email VARCHAR(190) NOT NULL DEFAULT '',
			web VARCHAR(255) NOT NULL DEFAULT '',
			contacto_nombre VARCHAR(190) NOT NULL DEFAULT '',
			contacto_cargo VARCHAR(100) NOT NULL DEFAULT '',
			contacto_telefono VARCHAR(60) NOT NULL DEFAULT '',
			contacto_email VARCHAR(190) NOT NULL DEFAULT '',
			direccion VARCHAR(255) NOT NULL DEFAULT '',
			codigo_postal VARCHAR(20) NOT NULL DEFAULT '',
			localidad VARCHAR(120) NOT NULL DEFAULT '',
			provincia VARCHAR(120) NOT NULL DEFAULT '',
			pais VARCHAR(120) NOT NULL DEFAULT '',
			plazo_entrega VARCHAR(120) NOT NULL DEFAULT '',
			observaciones LONGTEXT NULL,
			creado DATETIME NOT NULL,
			actualizado DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY proveedor (proveedor)
		) {$charset};";

		dbDelta( $sql_catalog );
		dbDelta( $sql_images );
		dbDelta( $sql_history );
		dbDelta( $sql_profiles );

		update_option( 'taxocommerce_suppliers_db_version', self::DB_VERSION, false );
	}

	public static function maybe_install() {
		if ( self::DB_VERSION !== (string) get_option( 'taxocommerce_suppliers_db_version', '' ) ) {
			self::install();
			return;
		}

		global $wpdb;
		foreach ( array( self::catalog_table(), self::images_table(), self::history_table(), self::profiles_table() ) as $table ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Read the current supplier inventory/schema for this admin operation; stale cache would misreport changes.
			$exists = $wpdb->get_var(
				$wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) )
			);
			if ( $exists !== $table ) {
				self::install();
				return;
			}
		}
	}
}
