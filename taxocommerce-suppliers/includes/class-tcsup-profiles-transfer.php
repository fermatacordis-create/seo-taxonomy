<?php
/**
 * CPU-batched CSV import/export of PRIVATE supplier contact profiles.
 *
 * The product catalogue is the only supplier identity source. This class never
 * imports catalogue products, changes stock/prices or creates supplier names.
 *
 * @package TaxoCommerceSuppliers
 */
defined( 'ABSPATH' ) || exit;

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound -- Consistent TCSUP_ prefix.
final class TCSUP_Profiles_Transfer {

	const ACTION_NONCE = 'tcsup_profiles_csv';
	const MAX_BATCH    = 350;
	const MAX_FILE     = 52428800; // 50 MiB; WordPress/server upload limits may be lower.
	const CSV_FIELDS   = array(
		'proveedor', 'nombre_comercial', 'razon_social', 'nif', 'telefono',
		'email', 'web', 'contacto_nombre', 'contacto_cargo',
		'contacto_telefono', 'contacto_email', 'direccion', 'codigo_postal',
		'localidad', 'provincia', 'pais', 'plazo_entrega', 'observaciones',
	);
	const MAX_LENGTHS = array(
		'nombre_comercial' => 190, 'razon_social' => 190, 'nif' => 40,
		'telefono' => 60, 'email' => 190, 'web' => 255,
		'contacto_nombre' => 190, 'contacto_cargo' => 100,
		'contacto_telefono' => 60, 'contacto_email' => 190,
		'direccion' => 255, 'codigo_postal' => 20, 'localidad' => 120,
		'provincia' => 120, 'pais' => 120, 'plazo_entrega' => 120,
		'observaciones' => 10000,
	);

	public static function boot() {
		add_action( 'wp_ajax_tcsup_profiles_export_batch', array( __CLASS__, 'export_batch' ) );
		add_action( 'wp_ajax_tcsup_profiles_import_start', array( __CLASS__, 'import_start' ) );
		add_action( 'wp_ajax_tcsup_profiles_import_batch', array( __CLASS__, 'import_batch' ) );
		add_action( 'wp_ajax_tcsup_profiles_import_cancel', array( __CLASS__, 'import_cancel' ) );
	}

	private static function authorize() {
		if ( ! current_user_can( TCSUP_Admin::capability() ) && ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'taxocommerce-suppliers' ) ), 403 );
		}
		check_ajax_referer( self::ACTION_NONCE );
	}

	public static function render_controls() {
		$nonce = wp_create_nonce( self::ACTION_NONCE );
		?>
		<div class="tcsup-profiles-transfer" id="tcsup-profiles-transfer"
			data-ajax="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"
			data-nonce="<?php echo esc_attr( $nonce ); ?>">
			<h3><?php esc_html_e( 'Bulk import / export of supplier records', 'taxocommerce-suppliers' ); ?></h3>
			<p><?php esc_html_e( 'Export all inventory suppliers as an editable CSV, including blank records. Import the same CSV to create or replace their private contact details; products, prices and orders never change.', 'taxocommerce-suppliers' ); ?></p>
			<div class="tcsup-history-controls">
				<button type="button" id="tcsup-profiles-export" class="button button-primary"><?php esc_html_e( 'Export supplier records CSV', 'taxocommerce-suppliers' ); ?></button>
				<form id="tcsup-profiles-import" enctype="multipart/form-data">
					<label><?php esc_html_e( 'Supplier records CSV file', 'taxocommerce-suppliers' ); ?>
						<input type="file" name="profiles_csv" accept=".csv,text/csv" required>
					</label>
					<label><?php esc_html_e( 'Processing speed', 'taxocommerce-suppliers' ); ?>
						<select name="batch_size" id="tcsup-profiles-batch">
							<option value="25"><?php esc_html_e( '1 - Very low', 'taxocommerce-suppliers' ); ?></option>
							<option value="50"><?php esc_html_e( '2 - Low', 'taxocommerce-suppliers' ); ?></option>
							<option value="100" selected><?php esc_html_e( '3 - Normal', 'taxocommerce-suppliers' ); ?></option>
							<option value="200"><?php esc_html_e( '4 - High', 'taxocommerce-suppliers' ); ?></option>
							<option value="350"><?php esc_html_e( '5 - Maximum', 'taxocommerce-suppliers' ); ?></option>
						</select>
					</label>
					<button type="submit" class="button"><?php esc_html_e( 'Import supplier records CSV', 'taxocommerce-suppliers' ); ?></button>
				</form>
				<button type="button" class="button" id="tcsup-profiles-stop" hidden><?php esc_html_e( 'Cancel import', 'taxocommerce-suppliers' ); ?></button>
			</div>
			<p class="description"><?php esc_html_e( 'Only suppliers already present in Inventory can be imported. Blank CSV cells replace existing values. Export a backup first. Files up to 50 MB (or the server limit) are processed in small batches without cron.', 'taxocommerce-suppliers' ); ?></p>
			<p id="tcsup-profiles-status" role="status" aria-live="polite"></p>
			<progress id="tcsup-profiles-progress" max="100" value="0" hidden></progress>
			<div id="tcsup-profiles-errors" role="status" aria-live="polite"></div>
		</div>
		<?php
	}

	/**
	 * Read only the next group of names from the real inventory, then enrich
	 * them from the optional private profile table. Keyset pagination avoids
	 * losing rows when intermediate batches run for a long time.
	 */
	private static function export_rows( $cursor, $limit ) {
		global $wpdb;
		$limit = min( self::MAX_BATCH, max( 1, absint( $limit ) ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Live, bounded read of unique suppliers from the current imported catalogue.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT c.proveedor,p.nombre_comercial,p.razon_social,p.nif,p.telefono,
				 p.email,p.web,p.contacto_nombre,p.contacto_cargo,p.contacto_telefono,
				 p.contacto_email,p.direccion,p.codigo_postal,p.localidad,
				 p.provincia,p.pais,p.plazo_entrega,p.observaciones
				 FROM (
					SELECT DISTINCT proveedor FROM %i
					WHERE proveedor<>%s AND (%s=%s OR proveedor>%s)
					ORDER BY proveedor ASC LIMIT %d
				 ) AS c LEFT JOIN %i AS p ON p.proveedor=c.proveedor
				 ORDER BY c.proveedor ASC",
				TCSUP_Schema::catalog_table(),
				'',
				$cursor,
				'',
				$cursor,
				$limit,
				TCSUP_Schema::profiles_table()
			),
			ARRAY_A
		);
		return is_array( $rows ) ? $rows : array();
	}

	public static function export_batch() {
		self::authorize();
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified by authorize().
		$post = map_deep( wp_unslash( $_POST ), 'sanitize_text_field' );
		$limit = isset( $post['batch_size'] ) ? min( self::MAX_BATCH, max( 1, absint( $post['batch_size'] ) ) ) : 100;
		$cursor = isset( $post['cursor'] ) && is_string( $post['cursor'] ) ? trim( $post['cursor'] ) : '';
		$rows = self::export_rows( $cursor, $limit );
		$result = array();
		foreach ( $rows as $row ) {
			$record = array();
			foreach ( self::CSV_FIELDS as $field ) {
				$record[] = (string) ( $row[ $field ] ?? '' );
			}
			$result[] = $record;
		}
		$last = $rows ? end( $rows ) : array();
		$total = null;
		if ( '' === $cursor ) {
			global $wpdb;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Report current supplier directory total for the progress display.
			$total = absint( $wpdb->get_var(
				$wpdb->prepare(
					'SELECT COUNT(DISTINCT proveedor) FROM %i WHERE proveedor<>%s',
					TCSUP_Schema::catalog_table(),
					''
				)
			) );
		}
		wp_send_json_success( array(
			'headers' => self::CSV_FIELDS,
			'rows'    => $result,
			'cursor'  => $last['proveedor'] ?? '',
			'total'   => $total,
			'done'    => count( $rows ) < $limit,
		) );
	}

	private static function session_key() {
		return 'tcsup_profiles_csv_' . get_current_user_id();
	}

	public static function temporary_upload_dir( $uploads ) {
		$path = untrailingslashit( get_temp_dir() );
		$uploads['path'] = $path;
		$uploads['basedir'] = $path;
		$uploads['url'] = '';
		$uploads['baseurl'] = '';
		$uploads['subdir'] = '';
		return $uploads;
	}

	/**
	 * Remove the prior user's unfinished upload without touching any
	 * imported supplier data.
	 */
	private static function close_session( $state ) {
		if ( is_array( $state ) && ! empty( $state['path'] ) && is_string( $state['path'] ) ) {
			$real = realpath( $state['path'] );
			$temp = realpath( get_temp_dir() );
			if ( $real && $temp && dirname( $real ) === untrailingslashit( $temp ) ) {
				wp_delete_file( $real );
			}
		}
		delete_transient( self::session_key() );
	}

	private static function normalize_headers( $headers ) {
		if ( ! is_array( $headers ) ) {
			return array();
		}
		return array_map( static function ( $header ) {
			return strtolower( trim( preg_replace( '/^\xEF\xBB\xBF/', '', (string) $header ) ) );
		}, $headers );
	}

	private static function valid_headers( $headers ) {
		return count( $headers ) === count( self::CSV_FIELDS )
			&& ! array_diff( self::CSV_FIELDS, $headers )
			&& ! array_diff( $headers, self::CSV_FIELDS )
			&& count( $headers ) === count( array_unique( $headers ) );
	}

	public static function import_start() {
		self::authorize();
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified via authorize().
		if ( ! isset( $_FILES['profiles_csv'] ) || ! is_array( $_FILES['profiles_csv'] ) ) {
			wp_send_json_error( array( 'message' => __( 'CSV file missing.', 'taxocommerce-suppliers' ) ), 400 );
		}
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.NonceVerification.Missing -- Core wp_handle_upload verifies the uploaded temp file, MIME and filename.
		$file = $_FILES['profiles_csv'];
		if ( UPLOAD_ERR_OK !== (int) ( $file['error'] ?? -1 )
			|| ! is_uploaded_file( $file['tmp_name'] ?? '' )
			|| (int) ( $file['size'] ?? 0 ) < 1
			|| (int) ( $file['size'] ?? 0 ) > self::MAX_FILE
			|| 'csv' !== strtolower( pathinfo( sanitize_file_name( (string) ( $file['name'] ?? '' ) ), PATHINFO_EXTENSION ) )
		) {
			wp_send_json_error( array( 'message' => __( 'Invalid supplier records CSV or the file exceeds 50 MB.', 'taxocommerce-suppliers' ) ), 400 );
		}

		// An existing session is replaced only after the new file is accepted.
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
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- Temporary uploaded contacts are kept private.
		chmod( $path, 0600 );
		$handle = fopen( $path, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- A seekable stream is required for chunked CSV imports.
		if ( ! $handle ) {
			wp_delete_file( $path );
			wp_send_json_error( array( 'message' => __( 'Cannot open the CSV file.', 'taxocommerce-suppliers' ) ), 400 );
		}
		$first = (string) fgets( $handle );
		$detected = ',';
		$maxColumns = 0;
		foreach ( array( ',', ';', "\t" ) as $separator ) {
			$count = count( str_getcsv( $first, $separator, '"', '' ) );
			if ( $count > $maxColumns ) {
				$maxColumns = $count;
				$detected = $separator;
			}
		}
		rewind( $handle );
		$headers = self::normalize_headers( fgetcsv( $handle, 0, $detected, '"', '' ) );
		if ( ! self::valid_headers( $headers ) ) {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Close invalid CSV file.
			wp_delete_file( $path );
			wp_send_json_error( array( 'message' => __( 'The CSV columns do not match the supplier record export. Download a fresh CSV first.', 'taxocommerce-suppliers' ) ), 400 );
		}
		$offset = ftell( $handle );
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Close upload stream before batched processing.

		$previous = get_transient( self::session_key() );
		if ( is_array( $previous ) ) {
			self::close_session( $previous );
		}
		$token = strtolower( wp_generate_password( 24, false, false ) );
		set_transient( self::session_key(), array(
			'token' => $token,
			'path' => $path,
			'size' => (int) filesize( $path ),
			'offset' => $offset,
			'delimiter' => $detected,
			'headers' => $headers,
			'processed' => 0,
			'inserted' => 0,
			'updated' => 0,
			'unchanged' => 0,
			'unmatched' => 0,
			'invalid' => 0,
			'problems' => array(),
		), HOUR_IN_SECONDS );
		wp_send_json_success( array( 'token' => $token ) );
	}

	/**
	 * Revert only our spreadsheet formula guard, not arbitrary apostrophes.
	 */
	private static function ungard_cell( $value ) {
		$value = (string) $value;
		return preg_match( "/^'[[:space:]]*[=+\\-@]/u", $value ) ? substr( $value, 1 ) : $value;
	}

	private static function char_length( $value ) {
		return function_exists( 'mb_strlen' ) ? mb_strlen( $value, 'UTF-8' ) : strlen( $value );
	}

	/**
	 * Sanitize, validate and persist only private contact fields.
	 *
	 * @return string One of inserted, updated, unchanged, unmatched, invalid.
	 */
	private static function import_row( $data ) {
		global $wpdb;
		$supplier = trim( sanitize_text_field( self::ungard_cell( $data['proveedor'] ?? '' ) ) );
		if ( '' === $supplier || self::char_length( $supplier ) > 190 ) {
			return 'invalid';
		}
		$canonical = TCSUP_Profiles::supplier_in_catalogue( $supplier );
		if ( '' === $canonical || $supplier !== $canonical ) {
			return 'unmatched';
		}
		$payload = array();
		$nonempty = false;
		foreach ( self::MAX_LENGTHS as $field => $max ) {
			$raw = self::ungard_cell( $data[ $field ] ?? '' );
			if ( 'observaciones' === $field ) {
				$value = sanitize_textarea_field( $raw );
			} elseif ( in_array( $field, array( 'email', 'contacto_email' ), true ) ) {
				$value = sanitize_email( $raw );
				if ( '' !== $raw && ! is_email( $value ) ) {
					return 'invalid';
				}
			} elseif ( 'web' === $field ) {
				$value = esc_url_raw( $raw );
				if ( '' !== $raw && ( '' === $value || ! preg_match( '#^https?://#i', $value ) ) ) {
					return 'invalid';
				}
			} else {
				$value = sanitize_text_field( $raw );
			}
			if ( self::char_length( $value ) > $max ) {
				return 'invalid';
			}
			$payload[ $field ] = $value;
			if ( '' !== $value ) {
				$nonempty = true;
			}
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- An exact supplier match is required to update private contact records.
		$existing = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM %i WHERE proveedor=%s LIMIT 1', TCSUP_Schema::profiles_table(), $supplier ),
			ARRAY_A
		);
		if ( ! is_array( $existing ) ) {
			if ( ! $nonempty ) {
				return 'unchanged'; // Do not create empty records for every supplier on an unedited export.
			}
			$payload['proveedor'] = $supplier;
			$payload['creado'] = current_time( 'mysql' );
			$payload['actualizado'] = current_time( 'mysql' );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Only create private profiles for existing inventory suppliers.
			return false !== $wpdb->insert( TCSUP_Schema::profiles_table(), $payload ) ? 'inserted' : 'invalid';
		}
		$same = true;
		foreach ( self::MAX_LENGTHS as $field => $max ) {
			if ( (string) ( $existing[ $field ] ?? '' ) !== $payload[ $field ] ) {
				$same = false;
				break;
			}
		}
		if ( $same ) {
			return 'unchanged';
		}
		$payload['actualizado'] = current_time( 'mysql' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Only change the existing private supplier profile fields.
		return false !== $wpdb->update( TCSUP_Schema::profiles_table(), $payload, array( 'proveedor' => $supplier ) ) ? 'updated' : 'invalid';
	}

	public static function import_batch() {
		self::authorize();
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified by authorize().
		$post = map_deep( wp_unslash( $_POST ), 'sanitize_text_field' );
		$token = isset( $post['token'] ) && is_string( $post['token'] ) ? sanitize_key( $post['token'] ) : '';
		$state = get_transient( self::session_key() );
		if ( ! preg_match( '/^[a-z0-9]{24}$/', $token ) || ! is_array( $state ) || ! hash_equals( (string) ( $state['token'] ?? '' ), $token ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid or expired supplier import session.', 'taxocommerce-suppliers' ) ), 400 );
		}
		$handle = fopen( $state['path'], 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Seekable private temporary CSV.
		if ( ! $handle || ! flock( $handle, LOCK_EX | LOCK_NB ) ) {
			wp_send_json_error( array( 'message' => __( 'Supplier import is already running.', 'taxocommerce-suppliers' ) ), 409 );
		}
		fseek( $handle, (int) $state['offset'] );
		$limit = isset( $post['batch_size'] ) ? min( self::MAX_BATCH, max( 1, absint( $post['batch_size'] ) ) ) : 100;
		$done = false;
		$started = microtime( true );
		for ( $i = 0; $i < $limit && ( microtime( true ) - $started ) < 7; $i++ ) {
			$row = fgetcsv( $handle, 0, $state['delimiter'], '"', '' );
			if ( false === $row ) {
				$done = true;
				break;
			}
			if ( array( null ) === $row ) {
				continue;
			}
			$state['processed']++;
			if ( count( $row ) !== count( $state['headers'] ) ) {
				$status = 'invalid';
				$supplier = '';
			} else {
				$data = array_combine( $state['headers'], $row );
				$supplier = (string) ( $data['proveedor'] ?? '' );
				$status = self::import_row( $data );
			}
			$state[ $status ]++;
			if ( in_array( $status, array( 'invalid', 'unmatched' ), true ) && count( $state['problems'] ) < 20 ) {
				$state['problems'][] = array(
					'row' => $state['processed'] + 1,
					'provider' => substr( sanitize_text_field( $supplier ), 0, 190 ),
					'reason' => $status,
				);
			}
		}
		$state['offset'] = ftell( $handle );
		flock( $handle, LOCK_UN );
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Release CSV stream after each bounded chunk.
		if ( ! $done && $state['offset'] >= $state['size'] ) {
			$done = true;
		}
		if ( $done ) {
			self::close_session( $state );
		} else {
			set_transient( self::session_key(), $state, HOUR_IN_SECONDS );
		}
		wp_send_json_success( array(
			'done' => $done,
			'progress' => $state['size'] ? min( 100, (int) ( 100 * $state['offset'] / $state['size'] ) ) : 100,
			'processed' => $state['processed'],
			'inserted' => $state['inserted'],
			'updated' => $state['updated'],
			'unchanged' => $state['unchanged'],
			'unmatched' => $state['unmatched'],
			'invalid' => $state['invalid'],
			'problems' => $state['problems'],
		) );
	}

	public static function import_cancel() {
		self::authorize();
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified by authorize().
		$post = map_deep( wp_unslash( $_POST ), 'sanitize_text_field' );
		$token = isset( $post['token'] ) && is_string( $post['token'] ) ? sanitize_key( $post['token'] ) : '';
		$state = get_transient( self::session_key() );
		if ( ! is_array( $state ) || ! hash_equals( (string) ( $state['token'] ?? '' ), $token ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid or expired supplier import session.', 'taxocommerce-suppliers' ) ), 400 );
		}
		self::close_session( $state );
		wp_send_json_success( array( 'cancelled' => true ) );
	}
}
