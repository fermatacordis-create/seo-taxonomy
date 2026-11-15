<?php
/**
 * Bulk CSV review, import and export of supplier and WooCommerce price history.
 *
 * @package TaxoCommerceSuppliers
 */
defined( 'ABSPATH' ) || exit;

final class TCSUP_History_Transfer {
	const ACTION_NONCE = 'tcsup_history_csv';
	const BATCH_MAX    = 500;
	const CSV_FIELDS   = array(
		'catalog_id', 'product_id', 'proveedor', 'proveedor_id_externo', 'pais_origen',
		'source_row_key', 'sku', 'nombre', 'fecha', 'precio_proveedor_sin_iva',
		'precio_proveedor_con_iva', 'precio_normal', 'precio_oferta',
		'precio_venta', 'moneda', 'origen',
	);
	const PRICE_FIELDS = array(
		'precio_proveedor_sin_iva', 'precio_proveedor_con_iva',
		'precio_normal', 'precio_oferta', 'precio_venta',
	);

	public static function boot() {
		add_action( 'wp_ajax_tcsup_history_export_batch', array( __CLASS__, 'export_batch' ) );
		add_action( 'wp_ajax_tcsup_history_import_start', array( __CLASS__, 'import_start' ) );
		add_action( 'wp_ajax_tcsup_history_import_batch', array( __CLASS__, 'import_batch' ) );
	}

	private static function authorize() {
		if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'taxocommerce-suppliers' ) ), 403 );
		}
		check_ajax_referer( self::ACTION_NONCE );
	}

	private static function date_filter( $value ) {
		$value = sanitize_text_field( (string) $value );
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
			return '';
		}
		$parts = explode( '-', $value );
		return checkdate( (int) $parts[1], (int) $parts[2], (int) $parts[0] ) ? $value : '';
	}

	private static function filters( $input ) {
		return array(
			'supplier' => isset( $input['ph_supplier'] ) && is_scalar( $input['ph_supplier'] ) ? substr( sanitize_text_field( (string) $input['ph_supplier'] ), 0, 190 ) : '',
			'search'   => isset( $input['ph_search'] ) && is_scalar( $input['ph_search'] ) ? substr( sanitize_text_field( (string) $input['ph_search'] ), 0, 150 ) : '',
			'from'     => isset( $input['ph_from'] ) && is_scalar( $input['ph_from'] ) ? self::date_filter( $input['ph_from'] ) : '',
			'to'       => isset( $input['ph_to'] ) && is_scalar( $input['ph_to'] ) ? self::date_filter( $input['ph_to'] ) : '',
		);
	}

	private static function list_rows( $filters, $limit, $offset = 0, $cursor = null ) {
		global $wpdb;
		$limit  = min( self::BATCH_MAX, max( 1, absint( $limit ) ) );
		$offset = min( 1000000, max( 0, absint( $offset ) ) );
		$like   = '%' . $wpdb->esc_like( $filters['search'] ) . '%';
		$from   = '' !== $filters['from'] ? $filters['from'] . ' 00:00:00' : '';
		$to     = '' !== $filters['to'] ? $filters['to'] . ' 23:59:59' : '';
		$cursor_date = isset( $cursor['fecha'] ) ? $cursor['fecha'] : '';
		$cursor_id   = isset( $cursor['id'] ) ? absint( $cursor['id'] ) : 0;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Paginated read-only price history; each value is prepared.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT h.*,c.proveedor,c.proveedor_id_externo,c.pais_origen,c.source_row_key,c.sku,c.nombre,
				(SELECT prev.precio_venta FROM %i prev
				 WHERE prev.catalog_id=h.catalog_id AND (prev.fecha<h.fecha OR (prev.fecha=h.fecha AND prev.id<h.id))
				 ORDER BY prev.fecha DESC,prev.id DESC LIMIT 1) AS precio_anterior,
				(SELECT COALESCE(prev.precio_proveedor_con_iva,prev.precio_proveedor_sin_iva) FROM %i prev
				 WHERE prev.catalog_id=h.catalog_id AND (prev.fecha<h.fecha OR (prev.fecha=h.fecha AND prev.id<h.id))
				 ORDER BY prev.fecha DESC,prev.id DESC LIMIT 1) AS proveedor_anterior
				FROM %i h JOIN %i c ON c.id=h.catalog_id
				WHERE (%s='' OR c.proveedor=%s)
				AND (%s='' OR c.nombre LIKE %s OR c.sku LIKE %s OR c.proveedor_id_externo LIKE %s)
				AND (%s='' OR h.fecha >= %s)
				AND (%s='' OR h.fecha <= %s)
				AND (%s='' OR h.fecha < %s OR (h.fecha=%s AND h.id < %d))
				ORDER BY h.fecha DESC,h.id DESC LIMIT %d OFFSET %d",
				TCSUP_Schema::history_table(),
				TCSUP_Schema::history_table(),
				TCSUP_Schema::history_table(),
				TCSUP_Schema::catalog_table(),
				$filters['supplier'],
				$filters['supplier'],
				$filters['search'],
				$like,
				$like,
				$like,
				$from,
				$from,
				$to,
				$to,
				$cursor_date,
				$cursor_date,
				$cursor_date,
				$cursor_id,
				$limit,
				$offset
			),
			ARRAY_A
		);
		return is_array( $rows ) ? $rows : array();
	}

	private static function count_rows( $filters ) {
		global $wpdb;
		$like = '%' . $wpdb->esc_like( $filters['search'] ) . '%';
		$from = '' !== $filters['from'] ? $filters['from'] . ' 00:00:00' : '';
		$to   = '' !== $filters['to'] ? $filters['to'] . ' 23:59:59' : '';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Read-only count of supplier price movements.
		return absint( $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM %i h JOIN %i c ON c.id=h.catalog_id
				WHERE (%s='' OR c.proveedor=%s)
				AND (%s='' OR c.nombre LIKE %s OR c.sku LIKE %s OR c.proveedor_id_externo LIKE %s)
				AND (%s='' OR h.fecha >= %s)
				AND (%s='' OR h.fecha <= %s)",
				TCSUP_Schema::history_table(),
				TCSUP_Schema::catalog_table(),
				$filters['supplier'],
				$filters['supplier'],
				$filters['search'],
				$like,
				$like,
				$like,
				$from,
				$from,
				$to,
				$to
			)
		) );
	}

	private static function value( $value ) {
		return null === $value || '' === (string) $value ? '—' : (string) $value;
	}

	private static function difference( $new, $old ) {
		if ( null === $old || null === $new || '' === (string) $new || '' === (string) $old ) {
			return '';
		}
		return number_format( (float) $new - (float) $old, 2, '.', '' );
	}

	public static function render() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display filters never write to storage.
		$filters = self::filters( wp_unslash( $_GET ) );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only pagination.
		$page   = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		$limit  = 50;
		$total  = self::count_rows( $filters );
		$rows   = self::list_rows( $filters, $limit, ( $page - 1 ) * $limit );
		$nonce  = wp_create_nonce( self::ACTION_NONCE );
		?>
		<section class="tcsup-card" id="tcsup-history-bulk" data-nonce="<?php echo esc_attr( $nonce ); ?>" data-ajax="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>">
			<h2><?php esc_html_e( 'Price history inventory', 'taxocommerce-suppliers' ); ?></h2>
			<p><?php esc_html_e( 'Review price movements across all products. CSV import only adds historical observations; it never changes current catalogue or WooCommerce prices.', 'taxocommerce-suppliers' ); ?></p>
			<form method="get" class="tcsup-history-filters">
				<input type="hidden" name="page" value="taxocommerce-suppliers">
				<input type="hidden" name="tab" value="history">
				<label><?php esc_html_e( 'Supplier', 'taxocommerce-suppliers' ); ?> <input type="text" name="ph_supplier" value="<?php echo esc_attr( $filters['supplier'] ); ?>"></label>
				<label><?php esc_html_e( 'Product or SKU', 'taxocommerce-suppliers' ); ?> <input type="search" name="ph_search" value="<?php echo esc_attr( $filters['search'] ); ?>"></label>
				<label><?php esc_html_e( 'From date', 'taxocommerce-suppliers' ); ?> <input type="date" name="ph_from" value="<?php echo esc_attr( $filters['from'] ); ?>"></label>
				<label><?php esc_html_e( 'To date', 'taxocommerce-suppliers' ); ?> <input type="date" name="ph_to" value="<?php echo esc_attr( $filters['to'] ); ?>"></label>
				<button class="button"><?php esc_html_e( 'Filter', 'taxocommerce-suppliers' ); ?></button>
			</form>
			<p><strong><?php echo esc_html( number_format_i18n( $total ) ); ?></strong> <?php esc_html_e( 'historical observations matching the filters', 'taxocommerce-suppliers' ); ?></p>
			<div class="tcsup-scroll"><table class="widefat striped">
				<thead><tr>
					<th><?php esc_html_e( 'Date', 'taxocommerce-suppliers' ); ?></th>
					<th><?php esc_html_e( 'Product', 'taxocommerce-suppliers' ); ?></th>
					<th><?php esc_html_e( 'Supplier', 'taxocommerce-suppliers' ); ?></th>
					<th><?php esc_html_e( 'Previous supplier cost', 'taxocommerce-suppliers' ); ?></th>
					<th><?php esc_html_e( 'Supplier cost', 'taxocommerce-suppliers' ); ?></th>
					<th><?php esc_html_e( 'Previous selling price', 'taxocommerce-suppliers' ); ?></th>
					<th><?php esc_html_e( 'Selling price', 'taxocommerce-suppliers' ); ?></th>
					<th><?php esc_html_e( 'Change', 'taxocommerce-suppliers' ); ?></th>
					<th><?php esc_html_e( 'Source', 'taxocommerce-suppliers' ); ?></th>
				</tr></thead>
				<tbody>
				<?php if ( ! $rows ) : ?><tr><td colspan="9"><?php esc_html_e( 'No historical prices match these filters.', 'taxocommerce-suppliers' ); ?></td></tr><?php endif; ?>
				<?php foreach ( $rows as $row ) : ?>
					<tr>
						<td><?php echo esc_html( $row['fecha'] ); ?></td>
						<td><strong><?php echo esc_html( $row['nombre'] ); ?></strong><br><code><?php echo esc_html( $row['sku'] ); ?></code></td>
						<td><?php echo esc_html( $row['proveedor'] ); ?></td>
						<td><?php echo esc_html( self::value( $row['proveedor_anterior'] ) ); ?></td>
						<td><?php echo esc_html( self::value( null !== $row['precio_proveedor_con_iva'] ? $row['precio_proveedor_con_iva'] : $row['precio_proveedor_sin_iva'] ) ); ?></td>
						<td><?php echo esc_html( self::value( $row['precio_anterior'] ) ); ?></td>
						<td><?php echo esc_html( self::value( $row['precio_venta'] ) ); ?> <?php echo esc_html( $row['moneda'] ); ?></td>
						<td><?php echo esc_html( self::value( self::difference( $row['precio_venta'], $row['precio_anterior'] ) ) ); ?></td>
						<td><?php echo esc_html( $row['origen'] ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table></div>
			<?php
			$pages = (int) ceil( $total / $limit );
			if ( $pages > 1 ) {
				$args = array_merge( array( 'page' => 'taxocommerce-suppliers', 'tab' => 'history' ), array_filter( array(
					'ph_supplier' => $filters['supplier'],
					'ph_search'   => $filters['search'],
					'ph_from'     => $filters['from'],
					'ph_to'       => $filters['to'],
				) ) );
				$base = add_query_arg( $args, admin_url( 'admin.php' ) );
				$base = str_replace( '999999999', '%#%', add_query_arg( 'paged', 999999999, $base ) );
				echo '<div class="tablenav"><div class="tablenav-pages">';
				echo wp_kses_post( paginate_links( array( 'base' => $base, 'current' => $page, 'total' => $pages, 'prev_text' => '‹', 'next_text' => '›' ) ) );
				echo '</div></div>';
			}
			?>
			<hr>
			<h3><?php esc_html_e( 'Import / export historical prices', 'taxocommerce-suppliers' ); ?></h3>
			<p><?php esc_html_e( 'Export the filtered history as CSV or import an exported CSV in batches. Existing prices and margins remain unchanged.', 'taxocommerce-suppliers' ); ?></p>
			<div class="tcsup-history-controls">
				<button type="button" id="tcsup-history-export" class="button button-primary"><?php esc_html_e( 'Export history CSV', 'taxocommerce-suppliers' ); ?></button>
				<form id="tcsup-history-import" enctype="multipart/form-data">
					<label><?php esc_html_e( 'History CSV file', 'taxocommerce-suppliers' ); ?> <input type="file" name="history_file" accept=".csv,text/csv" required></label>
					<label><?php esc_html_e( 'Processing speed', 'taxocommerce-suppliers' ); ?>
						<select name="batch_size" id="tcsup-history-batch">
							<option value="50"><?php esc_html_e( '1 - Very low', 'taxocommerce-suppliers' ); ?></option>
							<option value="100"><?php esc_html_e( '2 - Low', 'taxocommerce-suppliers' ); ?></option>
							<option value="200" selected><?php esc_html_e( '3 - Normal', 'taxocommerce-suppliers' ); ?></option>
							<option value="350"><?php esc_html_e( '4 - High', 'taxocommerce-suppliers' ); ?></option>
							<option value="500"><?php esc_html_e( '5 - Maximum', 'taxocommerce-suppliers' ); ?></option>
						</select>
					</label>
					<button type="submit" class="button"><?php esc_html_e( 'Import history CSV', 'taxocommerce-suppliers' ); ?></button>
				</form>
			</div>
			<p class="description"><?php esc_html_e( 'CSV identity uses supplier, external ID, country and source row key. Unmatched rows are skipped; duplicates are not re-imported. File limit: 15 MB.', 'taxocommerce-suppliers' ); ?></p>
			<p id="tcsup-history-status" role="status" aria-live="polite"></p>
			<progress id="tcsup-history-progress" max="100" value="0" hidden></progress>
		</section>
		<?php
	}

	public static function export_batch() {
		self::authorize();
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified via authorize().
		$input   = wp_unslash( $_POST );
		$filters = self::filters( $input );
		$limit   = isset( $input['batch_size'] ) ? absint( $input['batch_size'] ) : 200;
		$cursor  = null;
		if ( ! empty( $input['cursor_fecha'] ) && ! empty( $input['cursor_id'] ) ) {
			$fecha = sanitize_text_field( (string) $input['cursor_fecha'] );
			if ( preg_match( '/^\d{4}-\d\d-\d\d \d\d:\d\d:\d\d$/', $fecha ) ) {
				$cursor = array( 'fecha' => $fecha, 'id' => absint( $input['cursor_id'] ) );
			}
		}
		$rows    = self::list_rows( $filters, $limit, 0, $cursor ?? array( 'fecha' => '', 'id' => 0 ) );
		$payload = array();
		foreach ( $rows as $row ) {
			$item = array();
			foreach ( self::CSV_FIELDS as $field ) {
				$item[] = isset( $row[ $field ] ) ? (string) $row[ $field ] : '';
			}
			$item[]  = null === $row['precio_anterior'] ? '' : (string) $row['precio_anterior'];
			$item[]  = self::difference( $row['precio_venta'], $row['precio_anterior'] );
			$payload[] = $item;
		}
		$last = $rows ? end( $rows ) : array();
		wp_send_json_success( array(
			'headers' => array_merge( self::CSV_FIELDS, array( 'precio_anterior', 'variacion_pvp' ) ),
			'rows'    => $payload,
			'cursor_fecha' => $last['fecha'] ?? '',
			'cursor_id'    => $last['id'] ?? 0,
			'done' => count( $rows ) < min( self::BATCH_MAX, max( 1, $limit ) ),
		) );
	}

	private static function temp_key( $token ) {
		return 'tcsup_his_csv_' . get_current_user_id() . '_' . $token;
	}

	/**
	 * Route this single validated CSV upload into the private server temp directory.
	 */
	public static function temporary_upload_dir( $uploads ) {
		$path = untrailingslashit( get_temp_dir() );
		$uploads['path'] = $path;
		$uploads['basedir'] = $path;
		$uploads['url'] = '';
		$uploads['baseurl'] = '';
		$uploads['subdir'] = '';
		return $uploads;
	}

	public static function import_start() {
		self::authorize();
		check_ajax_referer( self::ACTION_NONCE );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce checked above.
		if ( ! isset( $_FILES['history_file'] ) || ! is_array( $_FILES['history_file'] ) ) {
			wp_send_json_error( array( 'message' => __( 'CSV file missing.', 'taxocommerce-suppliers' ) ), 400 );
		}
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.NonceVerification.Missing -- Core wp_handle_upload checks upload origin, MIME and sanitized filename; permission/nonce verified.
		$file = $_FILES['history_file'];
		if ( UPLOAD_ERR_OK !== (int) ( $file['error'] ?? -1 )
			|| ! is_uploaded_file( $file['tmp_name'] ?? '' )
			|| (int) ( $file['size'] ?? 0 ) < 1
			|| (int) ( $file['size'] ?? 0 ) > 15 * 1024 * 1024
			|| 'csv' !== strtolower( pathinfo( sanitize_file_name( (string) ( $file['name'] ?? '' ) ), PATHINFO_EXTENSION ) )
		) {
			wp_send_json_error( array( 'message' => __( 'Invalid CSV file or file is larger than 15 MB.', 'taxocommerce-suppliers' ) ), 400 );
		}
		add_filter( 'upload_dir', array( __CLASS__, 'temporary_upload_dir' ) );
		$uploaded = wp_handle_upload( $file, array(
			'test_form' => false,
			'mimes' => array( 'csv' => 'text/csv|text/plain|application/vnd.ms-excel' ),
		) );
		remove_filter( 'upload_dir', array( __CLASS__, 'temporary_upload_dir' ) );
		$path = isset( $uploaded['file'] ) ? $uploaded['file'] : '';
		if ( ! $path ) {
			wp_send_json_error( array( 'message' => __( 'Cannot store temporary CSV file.', 'taxocommerce-suppliers' ) ), 500 );
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- Uploaded supplier prices remain private in the server temp directory.
		chmod( $path, 0600 );
		$handle = fopen( $path, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Binary offset streaming requires a real seekable local file.
		if ( false === $handle ) {
			wp_delete_file( $path );
			wp_send_json_error( array( 'message' => __( 'Cannot open the CSV file.', 'taxocommerce-suppliers' ) ), 400 );
		}
		$first = (string) fgets( $handle );
		$comma = str_getcsv( $first, ',', '"', '' );
		$semi  = str_getcsv( $first, ';', '"', '' );
		$delimiter = count( $semi ) > count( $comma ) ? ';' : ',';
		rewind( $handle );
		$headers = fgetcsv( $handle, 0, $delimiter, '"', '' );
		if ( ! is_array( $headers ) ) {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Close temporary import stream.
			wp_delete_file( $path );
			wp_send_json_error( array( 'message' => __( 'CSV header is missing.', 'taxocommerce-suppliers' ) ), 400 );
		}
		$headers = array_map( static function ( $field ) {
			return trim( strtolower( preg_replace( '/^\xEF\xBB\xBF/', '', (string) $field ) ) );
		}, $headers );
		$required = array( 'proveedor', 'proveedor_id_externo', 'pais_origen', 'source_row_key', 'fecha', 'moneda' );
		$required = array_merge( $required, self::PRICE_FIELDS );
		if ( array_diff( $required, $headers ) || count( $headers ) !== count( array_unique( $headers ) ) ) {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Close temporary import stream.
			wp_delete_file( $path );
			wp_send_json_error( array( 'message' => __( 'CSV columns do not match the history export format.', 'taxocommerce-suppliers' ) ), 400 );
		}
		$offset = ftell( $handle );
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Close temporary import stream.
		$token = strtolower( wp_generate_password( 24, false, false ) );
		set_transient( self::temp_key( $token ), array(
			'path' => $path, 'offset' => $offset, 'headers' => $headers,
			'delimiter' => $delimiter, 'size' => filesize( $path ),
			'inserted' => 0, 'duplicates' => 0, 'unmatched' => 0, 'invalid' => 0,
		), HOUR_IN_SECONDS );
		wp_send_json_success( array( 'token' => $token ) );
	}

	private static function decimal( $value ) {
		if ( null === $value || '' === trim( (string) $value ) ) {
			return null;
		}
		$value = trim( (string) $value );
		return preg_match( '/^\d{1,12}(?:\.\d{1,6})?$/', $value ) ? number_format( (float) $value, 6, '.', '' ) : false;
	}

	private static function valid_date( $value ) {
		if ( ! preg_match( '/^\d{4}-\d\d-\d\d \d\d:\d\d:\d\d$/', $value ) ) {
			return false;
		}
		$parsed = DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', $value, new DateTimeZone( 'UTC' ) );
		return $parsed && $parsed->format( 'Y-m-d H:i:s' ) === $value;
	}

	private static function find_catalog( $data ) {
		global $wpdb;
		$provider = sanitize_text_field( (string) ( $data['proveedor'] ?? '' ) );
		$external = sanitize_text_field( (string) ( $data['proveedor_id_externo'] ?? '' ) );
		$country  = strtoupper( sanitize_text_field( (string) ( $data['pais_origen'] ?? '' ) ) );
		$key      = sanitize_text_field( (string) ( $data['source_row_key'] ?? '' ) );
		$sku      = sanitize_text_field( (string) ( $data['sku'] ?? '' ) );
		if ( '' === $provider || '' === $external ) {
			return null;
		}
		$table = TCSUP_Schema::catalog_table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Verify strong supplier identity before appending history.
		$matches = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT id,object_id,proveedor,proveedor_id_externo,pais_origen,source_row_key,sku FROM %i WHERE proveedor=%s AND proveedor_id_externo=%s AND pais_origen=%s AND source_row_key=%s LIMIT 2',
				$table, $provider, $external, $country, $key
			),
			ARRAY_A
		);
		if ( 1 !== count( (array) $matches ) ) {
			return null;
		}
		$match = $matches[0];
		// Require exact identity after retrieval, irrespective of database collation.
		if ( $match['proveedor'] !== $provider || $match['proveedor_id_externo'] !== $external
			|| $match['pais_origen'] !== $country || $match['source_row_key'] !== $key
			|| ( '' !== $sku && '' !== $match['sku'] && $sku !== $match['sku'] )
		) {
			return null;
		}
		return $match;
	}

	private static function import_row( $data ) {
		global $wpdb;
		$catalog = self::find_catalog( $data );
		if ( ! $catalog ) {
			return 'unmatched';
		}
		$date = trim( (string) ( $data['fecha'] ?? '' ) );
		if ( ! self::valid_date( $date ) ) {
			return 'invalid';
		}
		$currency = strtoupper( sanitize_text_field( (string) ( $data['moneda'] ?? '' ) ) );
		$origin   = sanitize_key( (string) ( $data['origen'] ?? 'csv_import' ) );
		if ( ! preg_match( '/^[A-Z]{3,12}$/', $currency ) || strlen( $origin ) > 30 ) {
			return 'invalid';
		}
		$values = array(
			'catalog_id' => absint( $catalog['id'] ),
			'product_id' => absint( $catalog['object_id'] ),
			'fecha'      => $date,
			'moneda'     => $currency,
			'origen'     => $origin,
		);
		foreach ( self::PRICE_FIELDS as $field ) {
			$decimal = self::decimal( $data[ $field ] ?? null );
			if ( false === $decimal ) {
				return 'invalid';
			}
			$values[ $field ] = $decimal;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Detect duplicate snapshots at a given timestamp before insert.
		$existing = (array) $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE catalog_id=%d AND fecha=%s',
				TCSUP_Schema::history_table(), $values['catalog_id'], $date
			),
			ARRAY_A
		);
		foreach ( $existing as $old ) {
			if ( (string) $old['moneda'] !== $currency || (string) $old['origen'] !== $origin ) {
				continue;
			}
			$identical = true;
			foreach ( self::PRICE_FIELDS as $field ) {
				if ( self::decimal( $old[ $field ] ) !== $values[ $field ] ) {
					$identical = false;
					break;
				}
			}
			if ( $identical ) {
				return 'duplicates';
			}
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Append price-history observation; never touch current product or catalogue tables.
		return false !== $wpdb->insert( TCSUP_Schema::history_table(), $values ) ? 'inserted' : 'invalid';
	}

	public static function import_batch() {
		self::authorize();
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified via authorize().
		$token = isset( $_POST['token'] ) && is_scalar( $_POST['token'] ) ? sanitize_key( wp_unslash( $_POST['token'] ) ) : '';
		if ( ! preg_match( '/^[a-z0-9]{24}$/', $token ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid import session.', 'taxocommerce-suppliers' ) ), 400 );
		}
		$key   = self::temp_key( $token );
		$state = get_transient( $key );
		if ( ! is_array( $state ) || ! is_readable( $state['path'] ?? '' ) ) {
			wp_send_json_error( array( 'message' => __( 'Import session expired. Upload the CSV again.', 'taxocommerce-suppliers' ) ), 400 );
		}
		$handle = fopen( $state['path'], 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Read seekable temporary CSV by byte offset.
		if ( ! $handle || ! flock( $handle, LOCK_EX | LOCK_NB ) ) {
			wp_send_json_error( array( 'message' => __( 'Import is already processing.', 'taxocommerce-suppliers' ) ), 409 );
		}
		fseek( $handle, (int) $state['offset'] );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified via authorize().
		$limit = isset( $_POST['batch_size'] ) ? min( self::BATCH_MAX, max( 1, absint( $_POST['batch_size'] ) ) ) : 200;
		$done  = false;
		for ( $i = 0; $i < $limit; $i++ ) {
			$record = fgetcsv( $handle, 0, $state['delimiter'], '"', '' );
			if ( false === $record ) {
				$done = true;
				break;
			}
			if ( array( null ) === $record ) {
				continue;
			}
			if ( count( $record ) !== count( $state['headers'] ) ) {
				++$state['invalid'];
				continue;
			}
			$data   = array_combine( $state['headers'], $record );
			$status = self::import_row( $data );
			++$state[ $status ];
		}
		$state['offset'] = ftell( $handle );
		flock( $handle, LOCK_UN );
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Close temporary CSV after chunk.
		if ( ! $done && $state['offset'] >= $state['size'] ) {
			$done = true;
		}
		if ( $done ) {
			wp_delete_file( $state['path'] );
			delete_transient( $key );
		} else {
			set_transient( $key, $state, HOUR_IN_SECONDS );
		}
		wp_send_json_success( array(
			'done' => $done,
			'progress' => $state['size'] ? min( 100, (int) ( 100 * $state['offset'] / $state['size'] ) ) : 100,
			'inserted' => $state['inserted'],
			'duplicates' => $state['duplicates'],
			'unmatched' => $state['unmatched'],
			'invalid' => $state['invalid'],
		) );
	}
}
