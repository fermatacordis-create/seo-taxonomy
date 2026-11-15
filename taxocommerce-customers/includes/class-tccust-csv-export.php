<?php
/**
 * Bounded, server-side CSV export. Never accumulates the customer database in
 * browser memory; the temporary file is outside the public uploads directory.
 *
 * @package TaxoCommerceCustomers
 */
defined( 'ABSPATH' ) || exit;

final class TCCUST_CSV_Export {
	const NONCE = 'tccust_csv_transfer';
	const BATCH = 350;

	public static function boot() {
		add_action( 'wp_ajax_tccust_csv_export_start', array( __CLASS__, 'start' ) );
		add_action( 'wp_ajax_tccust_csv_export', array( __CLASS__, 'batch' ) );
		add_action( 'wp_ajax_tccust_csv_export_cancel', array( __CLASS__, 'cancel' ) );
		add_action( 'admin_post_tccust_csv_download', array( __CLASS__, 'download' ) );
		add_action( 'tccust_csv_export_expire', array( __CLASS__, 'expire' ), 10, 1 );
	}

	private static function authorized() {
		return TCCUST_Admin::can_manage()
			&& current_user_can( 'export_taxocommerce_customers' )
			&& current_user_can( 'view_taxocommerce_private_notes' );
	}

	private static function ajax_guard() {
		if ( ! self::authorized() ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'taxocommerce-customers' ) ), 403 );
		}
		check_ajax_referer( self::NONCE );
	}

	private static function session_key() {
		return 'tccust_export_' . get_current_user_id();
	}

	private static function private_path( $path ) {
		$temp = TCCUST_CSV::secure_temp_dir();
		$file = realpath( $path );
		return $temp && $file
			&& dirname( $file ) === untrailingslashit( $temp )
			&& 0 === strpos( basename( $file ), 'tccust-export-' );
	}

	/**
	 * Best-effort private-file expiry if the administrator abandons a download.
	 * This is housekeeping only: export and download never depend on WP-Cron.
	 */
	public static function expire( $path ) {
		if ( ! self::private_path( $path ) ) {
			return;
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_filemtime -- Check private temp file age before removing.
		if ( filemtime( $path ) <= time() - HOUR_IN_SECONDS ) {
			wp_delete_file( $path );
		}
	}

	private static function cleanup( $state ) {
		if ( is_array( $state ) && ! empty( $state['path'] ) && self::private_path( $state['path'] ) ) {
			wp_delete_file( $state['path'] );
		}
		delete_transient( self::session_key() );
	}

	private static function session( $token ) {
		$state = get_transient( self::session_key() );
		return is_array( $state ) && is_string( $token )
			&& preg_match( '/^[a-zA-Z0-9]{24}$/', $token )
			&& isset( $state['token'] ) && hash_equals( (string) $state['token'], $token )
			&& ! empty( $state['path'] ) && self::private_path( $state['path'] )
			? $state : null;
	}

	public static function start() {
		self::ajax_guard();
		$old = get_transient( self::session_key() );
		if ( $old ) {
			self::cleanup( $old );
		}
		$temp = TCCUST_CSV::secure_temp_dir();
		if ( ! $temp ) {
			wp_send_json_error( array( 'message' => __( 'Cannot create private export file.', 'taxocommerce-customers' ) ), 500 );
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_tempnam -- Atomic private temporary file name outside document root.
		$path = tempnam( $temp, 'tccust-export-' );
		if ( false === $path ) {
			wp_send_json_error( array( 'message' => __( 'Cannot create private export file.', 'taxocommerce-customers' ) ), 500 );
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- CRM export contains personal information.
		chmod( $path, 0600 );
		$handle = fopen( $path, 'wb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Streamed CSV in private temporary file.
		if ( ! $handle ) {
			wp_delete_file( $path );
			wp_send_json_error( array( 'message' => __( 'Cannot write private export file.', 'taxocommerce-customers' ) ), 500 );
		}
		fwrite( $handle, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- UTF-8 BOM for spreadsheet clients.
		fputcsv( $handle, TCCUST_CSV::HEADERS, ',', '"', '' );
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- End initial write.
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Live private CRM total for progress.
		$total = absint( $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', TCCUST_DB::customers_table() ) ) );
		$token = wp_generate_password( 24, false, false );
		set_transient( self::session_key(), array(
			'token' => $token, 'path' => $path, 'cursor' => 0,
			'processed' => 0, 'total' => $total, 'ready' => false,
		), HOUR_IN_SECONDS );
		// Recover abandoned private exports even when no second export is opened.
		wp_schedule_single_event( time() + HOUR_IN_SECONDS + MINUTE_IN_SECONDS, 'tccust_csv_export_expire', array( $path ) );
		wp_send_json_success( array( 'token' => $token, 'total' => $total ) );
	}

	private static function safe_cell( $value ) {
		$value = (string) $value;
		// Keep Excel/LibreOffice from evaluating externally supplied formulas.
		return preg_match( '/^\s*[=+\-@]/u', $value ) ? "'" . $value : $value;
	}

	public static function batch() {
		self::ajax_guard();
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- AJAX nonce checked in ajax_guard().
		$posted = map_deep( wp_unslash( $_POST ), 'sanitize_text_field' );
		$state = self::session( $posted['token'] ?? '' );
		if ( ! $state ) {
			wp_send_json_error( array( 'message' => __( 'Invalid or expired CSV session.', 'taxocommerce-customers' ) ), 400 );
		}
		if ( $state['ready'] ) {
			wp_send_json_success( self::progress( $state ) );
		}
		$limit = isset( $posted['size'] ) ? min( self::BATCH, max( 1, absint( $posted['size'] ) ) ) : 100;
		$handle = fopen( $state['path'], 'ab' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Private streamed CSV append.
		if ( ! $handle || ! flock( $handle, LOCK_EX | LOCK_NB ) ) {
			if ( $handle ) {
				fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Release failed lock handle.
			}
			wp_send_json_error( array( 'message' => __( 'Export session is already processing.', 'taxocommerce-customers' ) ), 409 );
		}
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Keyset page only; bounded private export.
		$rows = $wpdb->get_results(
			$wpdb->prepare( 'SELECT * FROM %i WHERE id>%d ORDER BY id ASC LIMIT %d', TCCUST_DB::customers_table(), $state['cursor'], $limit ),
			ARRAY_A
		);
		if ( ! is_array( $rows ) ) {
			flock( $handle, LOCK_UN );
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Release lock on database error.
			wp_send_json_error( array( 'message' => __( 'Cannot query CRM records.', 'taxocommerce-customers' ) ), 500 );
		}
		foreach ( $rows as $row ) {
			$values = array();
			foreach ( TCCUST_CSV::HEADERS as $header ) {
				$values[] = self::safe_cell( $row[ $header ] ?? '' );
			}
			fputcsv( $handle, $values, ',', '"', '' );
			$state['cursor'] = absint( $row['id'] );
			++$state['processed'];
		}
		flock( $handle, LOCK_UN );
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Finish CSV page.
		$state['ready'] = count( $rows ) < $limit || $state['processed'] >= $state['total'];
		set_transient( self::session_key(), $state, HOUR_IN_SECONDS );
		wp_send_json_success( self::progress( $state ) );
	}

	private static function progress( $state ) {
		$data = array(
			'done' => (bool) $state['ready'],
			'processed' => absint( $state['processed'] ),
			'progress' => $state['ready'] ? 100 : min( 99, (int) ( 100 * $state['processed'] / max( 1, $state['total'] ) ) ),
		);
		if ( $state['ready'] ) {
			$data['download_url'] = wp_nonce_url(
				add_query_arg( array(
					'action' => 'tccust_csv_download',
					'token' => $state['token'],
				), admin_url( 'admin-post.php' ) ),
				'tccust_csv_download'
			);
		}
		return $data;
	}

	public static function cancel() {
		self::ajax_guard();
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- AJAX nonce checked in ajax_guard().
		$posted = map_deep( wp_unslash( $_POST ), 'sanitize_text_field' );
		$state = self::session( $posted['token'] ?? '' );
		if ( ! $state ) {
			wp_send_json_error( array( 'message' => __( 'Invalid or expired CSV session.', 'taxocommerce-customers' ) ), 400 );
		}
		self::cleanup( $state );
		wp_send_json_success( array( 'cancelled' => true ) );
	}

	public static function download() {
		if ( ! self::authorized() ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'taxocommerce-customers' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'tccust_csv_download' );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- check_admin_referer validates the signed download URL.
		$token = isset( $_GET['token'] ) && is_string( $_GET['token'] ) ? sanitize_key( wp_unslash( $_GET['token'] ) ) : '';
		$state = self::session( $token );
		if ( ! $state || ! $state['ready'] ) {
			wp_die( esc_html__( 'Invalid or expired CSV session.', 'taxocommerce-customers' ), '', array( 'response' => 400 ) );
		}
		$handle = fopen( $state['path'], 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Read private file in streaming mode.
		if ( ! $handle ) {
			wp_die( esc_html__( 'Cannot read private export file.', 'taxocommerce-customers' ), '', array( 'response' => 500 ) );
		}
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Content-Disposition: attachment; filename="taxocommerce-customers-' . gmdate( 'Ymd' ) . '.csv"' );
		header( 'Content-Length: ' . (string) filesize( $state['path'] ) );
		fpassthru( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fpassthru -- Stream download without PHP/browser accumulation.
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- End download stream.
		self::cleanup( $state );
		exit;
	}
}
