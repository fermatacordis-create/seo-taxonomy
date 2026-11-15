<?php
/**
 * On-demand, read-only diagnostics for the independent Suppliers module.
 *
 * @package TaxoCommerceSuppliers
 */
defined( 'ABSPATH' ) || exit;

final class TCSUP_Autotest {
	private static function check( &$rows, $group, $name, $status, $detail ) {
		$rows[] = array( 'group' => $group, 'name' => $name, 'status' => $status, 'detail' => $detail );
	}

	public static function run() {
		global $wpdb;
		$rows = array();
		self::check( $rows, __( 'Sistema', 'taxocommerce-suppliers' ), __( 'Plugin y versión', 'taxocommerce-suppliers' ), defined( 'TAXOCOMMERCE_SUPPLIERS_VERSION' ) ? 'ok' : 'error', defined( 'TAXOCOMMERCE_SUPPLIERS_VERSION' ) ? 'Suppliers ' . TAXOCOMMERCE_SUPPLIERS_VERSION : __( 'Constante de versión ausente.', 'taxocommerce-suppliers' ) );
		$woo = class_exists( 'WooCommerce', false ) && function_exists( 'wc_get_orders' ) && function_exists( 'wc_get_product' );
		self::check( $rows, __( 'Sistema', 'taxocommerce-suppliers' ), 'WooCommerce', $woo ? 'ok' : 'error', $woo ? __( 'API de pedidos y productos disponible.', 'taxocommerce-suppliers' ) : __( 'WooCommerce no está disponible para productos o compras.', 'taxocommerce-suppliers' ) );

		$classes = array( 'TCSUP_Schema', 'TCSUP_Price_History', 'TCSUP_CSV', 'TCSUP_Products', 'TCSUP_Purchasing', 'TCSUP_Admin', 'TCSUP_Manual' );
		$missing = array();
		foreach ( $classes as $class ) {
			if ( ! class_exists( $class, false ) ) {
				$missing[] = $class;
			}
		}
		self::check( $rows, __( 'Componentes', 'taxocommerce-suppliers' ), __( 'Clases del plugin', 'taxocommerce-suppliers' ), $missing ? 'error' : 'ok', $missing ? __( 'Faltan: ', 'taxocommerce-suppliers' ) . implode( ', ', $missing ) : __( 'Todos los componentes principales están cargados.', 'taxocommerce-suppliers' ) );

		$hooks = array(
			'admin_post_tcsup_analyze_csv'      => array( 'TCSUP_CSV', 'handle_analyze' ),
			'admin_post_tcsup_import_csv'       => array( 'TCSUP_CSV', 'handle_import' ),
			'admin_post_tcsup_save_template'    => array( 'TCSUP_CSV', 'handle_save_template' ),
			'admin_post_tcsup_delete_template'  => array( 'TCSUP_CSV', 'handle_delete_template' ),
			'admin_post_tcsup_download_template'=> array( 'TCSUP_CSV', 'handle_download_template' ),
			'admin_post_tcsup_row_action'       => array( 'TCSUP_Products', 'handle_row_action' ),
			'admin_post_tcsup_bulk_action'      => array( 'TCSUP_Products', 'handle_bulk_action' ),
			'admin_post_tcsup_price_update'     => array( 'TCSUP_Products', 'handle_price_update' ),
			'admin_post_tcsup_images_update'    => array( 'TCSUP_Products', 'handle_images_update' ),
			'admin_post_tcsup_settings'         => array( 'TCSUP_Admin', 'handle_settings' ),
		);
		$missing = array();
		foreach ( $hooks as $hook => $callback ) {
			if ( false === has_action( $hook, $callback ) ) {
				$missing[] = $hook;
			}
		}
		self::check( $rows, __( 'Administración', 'taxocommerce-suppliers' ), __( 'Controladores registrados', 'taxocommerce-suppliers' ), $missing ? 'error' : 'ok', $missing ? __( 'No registrados: ', 'taxocommerce-suppliers' ) . implode( ', ', $missing ) : __( 'Los 10 controladores están registrados; ninguno se ejecuta.', 'taxocommerce-suppliers' ) );
		self::check( $rows, __( 'Servicios', 'taxocommerce-suppliers' ), __( 'Funciones públicas', 'taxocommerce-suppliers' ), function_exists( 'taxocommerce_suppliers_get_sources' ) && function_exists( 'taxocommerce_suppliers_get_external_images' ) ? 'ok' : 'error', __( 'Consulta de fuentes e imágenes de proveedor (sin invocar operaciones).', 'taxocommerce-suppliers' ) );
		$templates = get_option( 'taxocommerce_suppliers_csv_templates', array() );
		self::check( $rows, __( 'Configuración', 'taxocommerce-suppliers' ), __( 'Plantillas CSV personalizadas', 'taxocommerce-suppliers' ), is_array( $templates ) ? 'ok' : 'error', is_array( $templates ) ? __( 'Estructura válida; ', 'taxocommerce-suppliers' ) . count( $templates ) . __( ' plantillas almacenadas.', 'taxocommerce-suppliers' ) : __( 'La opción no contiene un array.', 'taxocommerce-suppliers' ) );
		$builtin = class_exists( 'TCSUP_CSV', false ) ? TCSUP_CSV::builtin_templates() : array();
		$expected = array( 'canonical', 'price_update' );
		$generic_only = array_keys( $builtin ) === $expected;
		self::check( $rows, __( 'Configuración', 'taxocommerce-suppliers' ), __( 'Formatos CSV genéricos', 'taxocommerce-suppliers' ), $generic_only ? 'ok' : 'error', $generic_only ? __( 'Solo los dos formatos de importación, sin plantillas de proveedores precargadas.', 'taxocommerce-suppliers' ) : __( 'Se han detectado perfiles de proveedor precargados.', 'taxocommerce-suppliers' ) );
		foreach ( array( 'taxocommerce_suppliers_sale_margin' => __( 'Margen de venta', 'taxocommerce-suppliers' ), 'taxocommerce_suppliers_regular_margin' => __( 'Margen regular', 'taxocommerce-suppliers' ) ) as $option => $label ) {
			$value = get_option( $option, false );
			self::check( $rows, __( 'Configuración', 'taxocommerce-suppliers' ), $label, false !== $value && is_numeric( $value ) && is_finite( (float) $value ) ? 'ok' : 'warning', false !== $value && is_numeric( $value ) ? __( 'Valor numérico guardado.', 'taxocommerce-suppliers' ) : __( 'Opción ausente o no numérica; revisar ajustes.', 'taxocommerce-suppliers' ) );
		}
		$db_version = get_option( 'taxocommerce_suppliers_db_version', '' );
		self::check( $rows, __( 'Persistencia', 'taxocommerce-suppliers' ), __( 'Versión del esquema', 'taxocommerce-suppliers' ), class_exists( 'TCSUP_Schema', false ) && TCSUP_Schema::DB_VERSION === (string) $db_version ? 'ok' : 'warning', __( 'Versión guardada: ', 'taxocommerce-suppliers' ) . ( $db_version ? $db_version : __( 'sin registrar', 'taxocommerce-suppliers' ) ) . __( '. No se ejecutan migraciones.', 'taxocommerce-suppliers' ) );
		if ( ! isset( $wpdb ) || ! is_object( $wpdb ) || ! method_exists( $wpdb, 'prepare' ) || ! class_exists( 'TCSUP_Schema', false ) ) {
			self::check( $rows, __( 'Persistencia', 'taxocommerce-suppliers' ), __( 'Base de datos', 'taxocommerce-suppliers' ), 'error', __( 'Conexión o esquema no disponibles.', 'taxocommerce-suppliers' ) );
		} else {
			$tables = array(
				__( 'Catálogo intermedio', 'taxocommerce-suppliers' ) => array( TCSUP_Schema::catalog_table(), array( 'id', 'proveedor', 'proveedor_id_externo', 'pais_origen', 'source_row_key', 'estado_seleccion', 'object_id' ) ),
				__( 'Imágenes externas', 'taxocommerce-suppliers' )   => array( TCSUP_Schema::images_table(), array( 'id', 'supplier', 'supplier_sku', 'product_id', 'image_url', 'status' ) ),
				__( 'Historial de precios', 'taxocommerce-suppliers' ) => array( TCSUP_Schema::history_table(), array( 'id', 'catalog_id', 'product_id', 'fecha', 'precio_venta', 'origen' ) ),
				__( 'Supplier management', 'taxocommerce-suppliers' ) => array( TCSUP_Schema::profiles_table(), array( 'id', 'proveedor', 'telefono', 'contacto_nombre', 'observaciones', 'actualizado' ) ),
			);
			foreach ( $tables as $label => $spec ) {
				$table = $spec[0];
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Explicit manual inspection, read-only.
				$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
				if ( $exists !== $table ) {
					self::check( $rows, __( 'Persistencia', 'taxocommerce-suppliers' ), $label, 'error', __( 'No se encuentra la tabla ', 'taxocommerce-suppliers' ) . $table . '.' );
					continue;
				}
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Explicit manual schema inspection.
				$columns = $wpdb->get_col( $wpdb->prepare( 'SHOW COLUMNS FROM %i', $table ) );
				$missing_columns = array_diff( $spec[1], is_array( $columns ) ? $columns : array() );
				self::check( $rows, __( 'Persistencia', 'taxocommerce-suppliers' ), $label, $missing_columns ? 'error' : 'ok', $missing_columns ? __( 'Columnas ausentes: ', 'taxocommerce-suppliers' ) . implode( ', ', $missing_columns ) : __( 'Tabla y columnas esenciales disponibles.', 'taxocommerce-suppliers' ) );
			}
		}
		self::check( $rows, __( 'Compras', 'taxocommerce-suppliers' ), __( 'Consulta de estados', 'taxocommerce-suppliers' ), class_exists( 'TCSUP_Purchasing', false ) && is_array( TCSUP_Purchasing::active_order_statuses() ) ? 'ok' : 'error', __( 'Filtro de estados cargado; no se consultan ni modifican pedidos.', 'taxocommerce-suppliers' ) );
		return $rows;
	}

	public static function render() {
        if ( \TaxoCommerce_Suppliers_Autotest_Code::render_subtabs() ) { return; }
		if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'No tienes permisos para ejecutar el autotest.', 'taxocommerce-suppliers' ) );
		}
		echo '<section class="tcsup-card">' . '<h2>' . esc_html__( 'Autocheck de Suppliers', 'taxocommerce-suppliers' ) . '</h2>' . '<p>' . esc_html__( 'Diagnóstico manual y de solo lectura. No importa CSV, no modifica productos ni precios, no descarga imágenes, no crea pedidos y no hace solicitudes HTTP.', 'taxocommerce-suppliers' ) . '</p>';
		echo '<form method="post">';
		wp_nonce_field( 'tcsup_autotest' );
		echo '<input type="hidden" name="tcsup_run_autotest" value="1">';
		submit_button( __( 'Ejecutar Autocheck', 'taxocommerce-suppliers' ), 'secondary', 'submit', false );
		echo '</form>';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified before executing the diagnostics.
		if ( ! isset( $_POST['tcsup_run_autotest'] ) ) {
			echo '<p class="description">' . esc_html__( 'Los resultados se generan al pulsar el botón. Se conserva el último durante una hora para descargarlo en JSON.', 'taxocommerce-suppliers' ) . '</p>' . '</section>';
			return;
		}
		check_admin_referer( 'tcsup_autotest' );
		$rows = self::run();
        $autocheck_totals = array();
        foreach ( $rows as $autocheck_item ) {
            $autocheck_status = (string) ( $autocheck_item['status'] ?? 'unknown' );
            $autocheck_totals[ $autocheck_status ] = ( $autocheck_totals[ $autocheck_status ] ?? 0 ) + 1;
        }
        $autocheck_report = array(
            'schema' => 1,
            'plugin' => 'taxocommerce-suppliers',
            'version' => defined( 'TAXOCOMMERCE_SUPPLIERS_VERSION' ) ? constant( 'TAXOCOMMERCE_SUPPLIERS_VERSION' ) : '',
            'generated_at' => gmdate( 'c' ), 'executed_at' => gmdate( 'c' ),
            'totals' => $autocheck_totals,
            'rows' => $rows,
        );
        if ( function_exists( 'set_transient' ) && function_exists( 'get_current_blog_id' ) && function_exists( 'get_current_user_id' ) ) {
            set_transient( 'taxocommerce_suppliers_autocheck_latest_' . get_current_blog_id() . '_' . get_current_user_id(), $autocheck_report, HOUR_IN_SECONDS );
        }
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        echo '<input type="hidden" name="action" value="taxocommerce_suppliers_autocheck_json">';
        wp_nonce_field( 'taxocommerce_suppliers_autocheck_json' );
        submit_button( __( 'Descargar resultados (JSON)', 'taxocommerce-suppliers' ), 'secondary', 'submit', false );
        echo '<p><strong>' . esc_html__( 'Fecha de ejecución:', 'taxocommerce-suppliers' ) . '</strong> ' . esc_html( ( function_exists( 'wp_date' ) ? wp_date( 'd/m/Y H:i:s T', strtotime( (string) $autocheck_report['executed_at'] ) ) : gmdate( 'd/m/Y H:i:s', strtotime( (string) $autocheck_report['executed_at'] ) ) . ' UTC' ) ) . '</p>';
        echo '</form>';
		$counts = array( 'ok' => 0, 'warning' => 0, 'error' => 0 );
		foreach ( $rows as $row ) {
			$counts[ $row['status'] ]++;
		}
		/* translators: 1: checks run, 2: passed checks, 3: warnings, 4: errors. */
		$summary_format = __( '%1$d comprobaciones: %2$d correctas, %3$d avisos y %4$d errores.', 'taxocommerce-suppliers' );
		echo '<h3>' . esc_html__( 'Resumen', 'taxocommerce-suppliers' ) . '</h3><p>' . esc_html( sprintf( $summary_format, absint( count( $rows ) ), absint( $counts['ok'] ), absint( $counts['warning'] ), absint( $counts['error'] ) ) ) . '</p>';
		$group = '';
		foreach ( $rows as $row ) {
			if ( $group !== $row['group'] ) {
				if ( $group ) {
					echo '</tbody></table>';
				}
				$group = $row['group'];
				echo '<h3>' . esc_html( $group ) . '</h3><table class="widefat striped"><thead><tr><th>' . esc_html__( 'Comprobación', 'taxocommerce-suppliers' ) . '</th><th>' . esc_html__( 'Estado', 'taxocommerce-suppliers' ) . '</th><th>' . esc_html__( 'Detalle', 'taxocommerce-suppliers' ) . '</th></tr></thead><tbody>';
			}
			$names = array( 'ok' => __( 'Correcto', 'taxocommerce-suppliers' ), 'warning' => __( 'Aviso', 'taxocommerce-suppliers' ), 'error' => __( 'Error', 'taxocommerce-suppliers' ) );
			echo '<tr><th scope="row">' . esc_html( $row['name'] ) . '</th><td>' . esc_html( $names[ $row['status'] ] ) . '</td><td>' . esc_html( $row['detail'] ) . '</td></tr>';
		}
		if ( $group ) {
			echo '</tbody></table>';
		}
		echo '<p class="description">' . esc_html__( 'Autocheck de disponibilidad, no de resultados comerciales o de importación. No repara ni modifica tablas.', 'taxocommerce-suppliers' ) . '</p>' . '</section>';
	}
}


/**
 * Download the current user's most recent explicit diagnostic run.
 * Never execute an import, export, connection or worker during download.
 */
function taxocommerce_suppliers_autocheck_export_json() {
    if ( ! ( current_user_can( 'manage_woocommerce' ) || current_user_can( 'manage_options' ) ) ) {
        wp_die( esc_html__( 'Sin permisos para descargar el Autocheck.', 'taxocommerce-suppliers' ) );
    }
    check_admin_referer( 'taxocommerce_suppliers_autocheck_json' );
    $report = get_transient( 'taxocommerce_suppliers_autocheck_latest_' . get_current_blog_id() . '_' . get_current_user_id() );
    if ( ! is_array( $report ) || ! isset( $report['rows'], $report['totals'] ) ) {
        wp_die( esc_html__( 'Ejecuta el Autocheck antes de descargar el JSON.', 'taxocommerce-suppliers' ) );
    }
    $json = wp_json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE );
    if ( ! is_string( $json ) ) {
        wp_die( esc_html__( 'No se pudo generar el informe JSON.', 'taxocommerce-suppliers' ) );
    }
    nocache_headers();
    header( 'Content-Type: application/json; charset=utf-8' );
    header( 'X-Content-Type-Options: nosniff' );
    header( 'Content-Disposition: attachment; filename="taxocommerce-suppliers-autocheck-' . gmdate( 'Ymd-His' ) . '.json"' );
    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The response is a JSON attachment.
    echo $json;
    exit;
}
if ( function_exists( 'add_action' ) ) {
    add_action( 'admin_post_taxocommerce_suppliers_autocheck_json', 'taxocommerce_suppliers_autocheck_export_json' );
}
