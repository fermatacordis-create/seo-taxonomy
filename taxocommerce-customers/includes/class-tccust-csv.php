<?php
/**
 * Private CSV backup/import for customer CRM fields, never WooCommerce orders.
 *
 * @package TaxoCommerceCustomers
 */
defined( 'ABSPATH' ) || exit;

final class TCCUST_CSV {
	const NONCE = 'tccust_csv_transfer';
	const MAX_BATCH = 350;
	const MAX_FILE = 52428800; // 50 MiB; WordPress/PHP server limits may be smaller.
	const HEADERS = array(
		'id', 'source', 'woo_user_id', 'email', 'nombre', 'apellidos', 'empresa',
		'telefono', 'whatsapp', 'direccion', 'codigo_postal', 'localidad',
		'provincia', 'pais', 'canal_preferido', 'etiquetas', 'observaciones',
	);

	public static function boot() {
		add_action( 'wp_ajax_tccust_csv_start', array( __CLASS__, 'start' ) );
		add_action( 'wp_ajax_tccust_csv_batch', array( __CLASS__, 'batch' ) );
		add_action( 'wp_ajax_tccust_csv_cancel', array( __CLASS__, 'cancel' ) );
	}

	private static function authorize() {
		if ( ! TCCUST_Admin::can_manage() || ! current_user_can( 'import_taxocommerce_customers' ) || ! current_user_can( 'view_taxocommerce_private_notes' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'taxocommerce-customers' ) ), 403 );
		}
		check_ajax_referer( self::NONCE );
	}

	public static function render() {
		?>
		<section class="tccust-panel" id="tccust-transfer"
			data-ajax="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"
			data-nonce="<?php echo esc_attr( wp_create_nonce( self::NONCE ) ); ?>">
			<h2><?php esc_html_e( 'Bulk customer import and export', 'taxocommerce-customers' ); ?></h2>
			<p><?php esc_html_e( 'Export all customer records and private notes, then edit the CSV and import it in small batches. Import never creates WordPress accounts, orders or invoices.', 'taxocommerce-customers' ); ?></p>
			<div class="tccust-actions">
				<button type="button" id="tccust-csv-export" class="button button-primary" <?php disabled( ! current_user_can( 'export_taxocommerce_customers' ) || ! current_user_can( 'view_taxocommerce_private_notes' ) ); ?>><?php esc_html_e( 'Export customers CSV', 'taxocommerce-customers' ); ?></button>
				<form id="tccust-csv-import" enctype="multipart/form-data">
					<label><?php esc_html_e( 'Customers CSV', 'taxocommerce-customers' ); ?>
						<input type="file" name="customers_csv" accept=".csv,text/csv" required>
					</label>
					<label><?php esc_html_e( 'Processing speed', 'taxocommerce-customers' ); ?>
						<select name="size">
							<option value="25"><?php esc_html_e( '1 - Very low', 'taxocommerce-customers' ); ?></option>
							<option value="50"><?php esc_html_e( '2 - Low', 'taxocommerce-customers' ); ?></option>
							<option value="100" selected><?php esc_html_e( '3 - Normal', 'taxocommerce-customers' ); ?></option>
							<option value="200"><?php esc_html_e( '4 - High', 'taxocommerce-customers' ); ?></option>
							<option value="350"><?php esc_html_e( '5 - Maximum', 'taxocommerce-customers' ); ?></option>
						</select>
					</label>
					<button type="submit" class="button" <?php disabled( ! current_user_can( 'import_taxocommerce_customers' ) || ! current_user_can( 'view_taxocommerce_private_notes' ) ); ?>><?php esc_html_e( 'Import customers CSV', 'taxocommerce-customers' ); ?></button>
				</form>
				<button type="button" id="tccust-csv-stop" class="button" hidden><?php esc_html_e( 'Stop import', 'taxocommerce-customers' ); ?></button>
			</div>
			<p class="description"><?php esc_html_e( 'Export a backup first. Existing rows use their internal ID; a blank ID creates a manual contact. The source and WooCommerce user ID are read-only. Empty cells overwrite CRM values; linked buyer emails are protected. Files up to 50 MB (or your server limit).', 'taxocommerce-customers' ); ?></p>
			<p id="tccust-csv-status" role="status" aria-live="polite"></p>
			<progress id="tccust-csv-progress" hidden max="100" value="0"></progress>
			<p id="tccust-csv-issues" role="status" aria-live="polite"></p>
		</section>
		<?php
	}


	private static function key() {
		return 'tccust_csv_' . get_current_user_id();
	}

	/**
	 * Never store customer CSV files below the public WordPress document root.
	 * get_temp_dir() can fall back to wp-content on restricted shared hosting.
	 */
	public static function secure_temp_dir() {
		$temp = realpath( get_temp_dir() );
		if ( ! $temp || ! wp_is_writable( $temp ) ) {
			return null;
		}
		foreach ( array( ABSPATH, WP_CONTENT_DIR ) as $root ) {
			$root = realpath( $root );
			if ( $root && ( $temp === $root || 0 === strpos( $temp, $root . DIRECTORY_SEPARATOR ) ) ) {
				return null;
			}
		}
		return $temp;
	}

	public static function private_upload_dir( $directory ) {
		$temp = untrailingslashit( self::secure_temp_dir() );
		$directory['path'] = $temp;
		$directory['basedir'] = $temp;
		$directory['subdir'] = '';
		$directory['url'] = '';
		$directory['baseurl'] = '';
		return $directory;
	}

	private static function cleanup( $state ) {
		if ( is_array( $state ) && ! empty( $state['path'] ) ) {
			$path = realpath( $state['path'] );
			$temp = realpath( get_temp_dir() );
			if ( $path && $temp && dirname( $path ) === untrailingslashit( $temp ) ) {
				wp_delete_file( $path );
			}
		}
		delete_transient( self::key() );
	}

	private static function columns( $headers ) {
		if ( ! is_array( $headers ) ) {
			return array();
		}
		return array_map( static function ( $value ) {
			return strtolower( trim( preg_replace( '/^\xEF\xBB\xBF/', '', (string) $value ) ) );
		}, $headers );
	}

	private static function valid_columns( $cols ) {
		return count( $cols ) === count( self::HEADERS )
			&& ! array_diff( $cols, self::HEADERS )
			&& ! array_diff( self::HEADERS, $cols )
			&& count( $cols ) === count( array_unique( $cols ) );
	}

	public static function start() {
		self::authorize();
		if ( ! self::secure_temp_dir() ) {
			wp_send_json_error( array( 'message' => __( 'Cannot store temporary CSV.', 'taxocommerce-customers' ) ), 500 );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce was validated via authorize().
		if ( ! isset( $_FILES['customers_csv'] ) || ! is_array( $_FILES['customers_csv'] ) ) {
			wp_send_json_error( array( 'message' => __( 'CSV file missing.', 'taxocommerce-customers' ) ), 400 );
		}
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.NonceVerification.Missing -- WordPress upload API validates origin, MIME and file name after a nonce and capability check.
		$file = $_FILES['customers_csv'];
		if ( UPLOAD_ERR_OK !== (int) ( $file['error'] ?? -1 )
			|| ! is_uploaded_file( $file['tmp_name'] ?? '' )
			|| (int) ( $file['size'] ?? 0 ) < 1
			|| (int) ( $file['size'] ?? 0 ) > self::MAX_FILE
			|| 'csv' !== strtolower( pathinfo( sanitize_file_name( (string) ( $file['name'] ?? '' ) ), PATHINFO_EXTENSION ) )
		) {
			wp_send_json_error( array( 'message' => __( 'Invalid CSV or file exceeds 50 MB.', 'taxocommerce-customers' ) ), 400 );
		}
		add_filter( 'upload_dir', array( __CLASS__, 'private_upload_dir' ) );
		$uploaded = wp_handle_upload( $file, array(
			'test_form' => false,
			'mimes' => array( 'csv' => 'text/csv|text/plain|application/vnd.ms-excel' ),
		) );
		remove_filter( 'upload_dir', array( __CLASS__, 'private_upload_dir' ) );
		$path = $uploaded['file'] ?? '';
		if ( ! $path ) {
			wp_send_json_error( array( 'message' => __( 'Cannot store temporary CSV.', 'taxocommerce-customers' ) ), 500 );
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- Sensitive CRM upload is kept in private temp directory.
		chmod( $path, 0600 );
		$handle = fopen( $path, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Seekable file needed for bounded CSV chunks.
		if ( ! $handle ) {
			wp_delete_file( $path );
			wp_send_json_error( array( 'message' => __( 'Cannot read CSV.', 'taxocommerce-customers' ) ), 400 );
		}
		$line = (string) fgets( $handle );
		$separator = ',';
		$longest = 0;
		foreach ( array( ',', ';', "\t" ) as $candidate ) {
			$count = count( str_getcsv( $line, $candidate, '"', '' ) );
			if ( $count > $longest ) {
				$separator = $candidate;
				$longest = $count;
			}
		}
		rewind( $handle );
		$headers = self::columns( fgetcsv( $handle, 0, $separator, '"', '' ) );
		if ( ! self::valid_columns( $headers ) ) {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- End invalid CSV stream.
			wp_delete_file( $path );
			wp_send_json_error( array( 'message' => __( 'CSV headers do not match the customer export format.', 'taxocommerce-customers' ) ), 400 );
		}
		$offset = ftell( $handle );
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Close uploaded CSV before jobs start.
		$prior = get_transient( self::key() );
		if ( is_array( $prior ) ) {
			self::cleanup( $prior );
		}
		$token = wp_generate_password( 24, false, false );
		set_transient( self::key(), array(
			'token' => $token, 'path' => $path, 'offset' => $offset, 'size' => (int) filesize( $path ),
			'delimiter' => $separator, 'headers' => $headers,
			'processed' => 0, 'created' => 0, 'updated' => 0,
			'unchanged' => 0, 'rejected' => 0, 'problems' => array(),
		), HOUR_IN_SECONDS );
		wp_send_json_success( array( 'token' => $token ) );
	}

	private static function unguard( $value ) {
		$value = (string) $value;
		return preg_match( "/^'[[:space:]]*[=+\-@]/u", $value ) ? substr( $value, 1 ) : $value;
	}

	private static function row( $data ) {
		$id_raw = trim( (string) ( $data['id'] ?? '' ) );
		if ( '' !== $id_raw && ( ! ctype_digit( $id_raw ) || strlen( $id_raw ) > 19 || '0' === $id_raw ) ) {
			return 'rejected';
		}
		$id = absint( $id_raw );
		$existing = $id ? TCCUST_DB::find( $id ) : null;
		if ( $id && ! $existing ) {
			return 'rejected';
		}
		if ( $existing && ! empty( $existing['erased_at'] ) ) {
			return 'rejected';
		}
		$incoming = array();
		foreach ( self::HEADERS as $column ) {
			$incoming[ $column ] = self::unguard( $data[ $column ] ?? '' );
		}
		if ( $existing ) {
			if ( $existing['source'] !== $incoming['source']
				|| (int) $existing['woo_user_id'] !== absint( $incoming['woo_user_id'] ) ) {
				return 'rejected';
			}
			if ( 'manual' !== $existing['source']
				&& (string) $existing['email'] !== sanitize_email( $incoming['email'] ) ) {
				return 'rejected';
			}
		} elseif ( ! in_array( $incoming['source'], array( '', 'manual' ), true ) || ( '' !== $incoming['woo_user_id'] && '0' !== $incoming['woo_user_id'] ) ) {
			return 'rejected'; // CSV must never impersonate a WooCommerce buyer.
		}
		$fields = TCCUST_Admin::valid_data( $incoming, $existing );
		if ( ! is_array( $fields ) ) {
			return 'rejected';
		}
		$duplicate = null !== $fields['email_key'] ? TCCUST_DB::find_email( $fields['email_key'] ) : null;
		if ( ! $existing && null !== $fields['email_key'] && TCCUST_DB::find_erased_email( $fields['email_key'] ) ) {
			return 'rejected';
		}
		if ( $duplicate && ( ! $existing || absint( $duplicate['id'] ) !== $id ) ) {
			return 'rejected';
		}
		if ( $existing ) {
			$changed = false;
			foreach ( $fields as $field => $value ) {
				if ( (string) ( $existing[ $field ] ?? '' ) !== (string) ( $value ?? '' ) ) {
					$changed = true;
					break;
				}
			}
			if ( ! $changed ) {
				return 'unchanged';
			}
			return TCCUST_DB::save( $fields, $existing ) ? 'updated' : 'rejected';
		}
		$fields['source'] = 'manual';
		$fields['woo_user_id'] = null;
		return TCCUST_DB::save( $fields ) ? 'created' : 'rejected';
	}

	public static function batch() {
		self::authorize();
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified via authorize().
		$input = map_deep( wp_unslash( $_POST ), 'sanitize_text_field' );
		$token = isset( $input['token'] ) && is_string( $input['token'] ) ? sanitize_key( $input['token'] ) : '';
		$state = get_transient( self::key() );
		if ( ! is_array( $state ) || ! preg_match( '/^[a-zA-Z0-9]{24}$/', $token ) || ! hash_equals( (string) $state['token'], $token ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid or expired CSV session.', 'taxocommerce-customers' ) ), 400 );
		}
		$handle = fopen( $state['path'], 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Private seekable CSV.
		if ( ! $handle || ! flock( $handle, LOCK_EX | LOCK_NB ) ) {
			wp_send_json_error( array( 'message' => __( 'CSV session is already processing.', 'taxocommerce-customers' ) ), 409 );
		}
		fseek( $handle, (int) $state['offset'] );
		$limit = isset( $input['size'] ) ? min( self::MAX_BATCH, max( 1, absint( $input['size'] ) ) ) : 100;
		$start = microtime( true );
		$done = false;
		for ( $i = 0; $i < $limit && ( microtime( true ) - $start ) < 7; $i++ ) {
			$values = fgetcsv( $handle, 0, $state['delimiter'], '"', '' );
			if ( false === $values ) {
				$done = true;
				break;
			}
			if ( array( null ) === $values ) {
				continue;
			}
			++$state['processed'];
			$record = count( $values ) === count( $state['headers'] )
				? array_combine( $state['headers'], $values ) : null;
			$status = is_array( $record ) ? self::row( $record ) : 'rejected';
			++$state[ $status ];
			if ( 'rejected' === $status && count( $state['problems'] ) < 20 ) {
				$state['problems'][] = absint( $state['processed'] + 1 );
			}
		}
		$state['offset'] = ftell( $handle );
		flock( $handle, LOCK_UN );
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Close stream between batches.
		if ( ! $done && $state['offset'] >= $state['size'] ) {
			$done = true;
		}
		if ( $done ) {
			self::cleanup( $state );
		} else {
			set_transient( self::key(), $state, HOUR_IN_SECONDS );
		}
		wp_send_json_success( array(
			'done' => $done,
			'progress' => $state['size'] ? min( 100, (int) ( 100 * $state['offset'] / $state['size'] ) ) : 100,
			'processed' => $state['processed'],
			'created' => $state['created'],
			'updated' => $state['updated'],
			'unchanged' => $state['unchanged'],
			'rejected' => $state['rejected'],
			'problems' => $state['problems'],
		) );
	}

	public static function cancel() {
		self::authorize();
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce checked in authorize().
		$input = map_deep( wp_unslash( $_POST ), 'sanitize_text_field' );
		$token = isset( $input['token'] ) && is_string( $input['token'] ) ? sanitize_key( $input['token'] ) : '';
		$state = get_transient( self::key() );
		if ( ! is_array( $state ) || ! hash_equals( (string) $state['token'], $token ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid or expired CSV session.', 'taxocommerce-customers' ) ), 400 );
		}
		self::cleanup( $state );
		wp_send_json_success( array( 'cancelled' => true ) );
	}
}
