<?php
/**
 * WooCommerce adapter: paid orders are the source of customer purchase history.
 * Never reads wc_orders/wp_posts directly, never modifies WooCommerce orders.
 *
 * @package TaxoCommerceCustomers
 */
defined( 'ABSPATH' ) || exit;

final class TCCUST_Sync {
	const NONCE = 'tccust_sync_orders';
	const MAX_BATCH = 150;

	public static function available() {
		return function_exists( 'wc_get_orders' ) && function_exists( 'wc_get_order' ) && function_exists( 'wc_get_is_paid_statuses' );
	}

	public static function boot() {
		add_action( 'woocommerce_payment_complete', array( __CLASS__, 'order_changed' ), 30 );
		add_action( 'woocommerce_order_status_changed', array( __CLASS__, 'order_changed' ), 30, 1 );
		add_action( 'woocommerce_update_order', array( __CLASS__, 'order_changed' ), 30, 1 );
		add_action( 'woocommerce_delete_order', array( __CLASS__, 'delete_order' ), 30 );
		add_action( 'wp_ajax_tccust_sync_start', array( __CLASS__, 'start' ) );
		add_action( 'wp_ajax_tccust_sync_batch', array( __CLASS__, 'batch' ) );
		add_action( 'wp_ajax_tccust_sync_cancel', array( __CLASS__, 'cancel' ) );
	}

	private static function authorize() {
		if ( ! TCCUST_Admin::can_manage() ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'taxocommerce-customers' ) ), 403 );
		}
		check_ajax_referer( self::NONCE );
	}

	/**
	 * Hooks may execute on public checkout; only paid orders enter the CRM.
	 */
	public static function order_changed( $order_id ) {
		if ( self::available() ) {
			$order = wc_get_order( absint( $order_id ) );
			if ( $order ) {
				self::sync_order( $order );
			}
		}
	}

	public static function delete_order( $order_id ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Cleanup a single private reference when WooCommerce removes an order.
		$wpdb->delete( TCCUST_DB::links_table(), array( 'order_id' => absint( $order_id ) ) );
	}

	/**
	 * Return status name without "wc-" prefix; WooCommerce provides the source.
	 */
	private static function paid( $order ) {
		return $order && $order->is_paid();
	}

	public static function sync_order( $order ) {
		if ( ! self::available() || ! is_a( $order, 'WC_Order' ) ) {
			return 'skipped';
		}
		$order_id = absint( $order->get_id() );
		global $wpdb;
		if ( ! self::paid( $order ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Remove cancelled/unpaid purchase association only.
			$wpdb->delete( TCCUST_DB::links_table(), array( 'order_id' => $order_id ) );
			return 'unpaid';
		}
		$user_id = absint( $order->get_customer_id() );
		$email = sanitize_email( (string) $order->get_billing_email() );
		$key = TCCUST_DB::email_key( $email );
		if ( ! $user_id && null === $key ) {
			return 'skipped';
		}
		$customer = $user_id ? TCCUST_DB::find_user( $user_id ) : null;
		if ( ! $customer && null !== $key ) {
			$candidate = TCCUST_DB::find_email( $key );
			if ( ! $candidate ) {
				$candidate = TCCUST_DB::find_erased_email( $key );
			}
			// Different registered user IDs are not merged automatically.
			if ( $candidate && ( ! $user_id || ! $candidate['woo_user_id'] || (int) $candidate['woo_user_id'] === $user_id ) ) {
				$customer = $candidate;
			} elseif ( $candidate ) {
				return 'conflict';
			}
		}
		$source = $user_id ? 'registered' : 'guest';
		$data = array();
		if ( $customer ) {
			if ( $user_id && ! $customer['woo_user_id'] ) {
				$data['woo_user_id'] = $user_id;
			}
			if ( 'registered' === $source || 'manual' === $customer['source'] ) {
				$data['source'] = $source;
			}
		} else {
			$data['woo_user_id'] = $user_id ? $user_id : null;
			$data['email'] = $key ?? '';
			$data['email_key'] = $key;
			$data['source'] = $source;
		}
		$incoming = array(
			'nombre' => (string) $order->get_billing_first_name(),
			'apellidos' => (string) $order->get_billing_last_name(),
			'empresa' => (string) $order->get_billing_company(),
			'telefono' => (string) $order->get_billing_phone(),
			'direccion' => (string) $order->get_billing_address_1(),
			'codigo_postal' => (string) $order->get_billing_postcode(),
			'localidad' => (string) $order->get_billing_city(),
			'provincia' => (string) $order->get_billing_state(),
			'pais' => (string) $order->get_billing_country(),
		);
		foreach ( $incoming as $column => $raw ) {
			// Human-entered CRM edits always take precedence.
			if ( ! $customer || ( empty( $customer['erased_at'] ) && '' === (string) ( $customer[ $column ] ?? '' ) ) ) {
				$data[ $column ] = sanitize_text_field( $raw );
			}
		}
		if ( $customer && empty( $customer['erased_at'] ) && '' === (string) ( $customer['email'] ?? '' ) && null !== $key ) {
			$data['email'] = $key;
			$data['email_key'] = $key;
		}
		$customer_id = ( $customer && ! $data ) ? absint( $customer['id'] ) : TCCUST_DB::save( $data, $customer );
		if ( ! $customer_id ) {
			return 'error';
		}
		$date = $order->get_date_paid();
		if ( ! $date ) {
			$date = $order->get_date_created();
		}
		$purchase_time = $date ? $date->date( 'Y-m-d H:i:s' ) : current_time( 'mysql' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Order ID is unique; repeat events must not double-count purchases.
		$ok = $wpdb->replace(
			TCCUST_DB::links_table(),
			array( 'order_id' => $order_id, 'customer_id' => $customer_id, 'purchased_at' => $purchase_time ),
			array( '%d', '%d', '%s' )
		);
		return false === $ok ? 'error' : 'linked';
	}

	private static function session_key() {
		return 'tccust_sync_' . get_current_user_id();
	}

	public static function is_backfill_complete() {
		return (bool) get_option( 'tccust_backfill_complete', false );
	}

	public static function start() {
		self::authorize();
		if ( ! self::available() ) {
			wp_send_json_error( array( 'message' => __( 'WooCommerce is not available. Manual contacts remain usable.', 'taxocommerce-customers' ) ), 400 );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Authorization and nonce verified in authorize().
		$input = map_deep( wp_unslash( $_POST ), 'sanitize_text_field' );
		$restart = isset( $input['restart'] ) && '1' === $input['restart'];
		$page = $restart ? 1 : max( 1, absint( get_option( 'tccust_backfill_page', 1 ) ) );
		$offset = $restart ? 0 : absint( get_option( 'tccust_backfill_offset', 0 ) );
		$requested = isset( $input['size'] ) ? min( self::MAX_BATCH, max( 1, absint( $input['size'] ) ) ) : 50;
		// A different page size mid-run could silently skip paid orders.
		$limit = $restart ? $requested : min( self::MAX_BATCH, max( 1, absint( get_option( 'tccust_backfill_limit', $requested ) ) ) );
		update_option( 'tccust_backfill_limit', $limit, false );
		if ( $restart ) {
			update_option( 'tccust_backfill_complete', false, false );
		}
		$token = wp_generate_password( 24, false, false );
		set_transient( self::session_key(), array(
			'token' => $token, 'page' => $page, 'offset' => $offset, 'limit' => $limit, 'linked' => 0, 'skipped' => 0, 'conflict' => 0, 'error' => 0
		), HOUR_IN_SECONDS );
		wp_send_json_success( array( 'token' => $token, 'page' => $page ) );
	}

	public static function batch() {
		self::authorize();
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- AJAX nonce validated in authorize().
		$input = map_deep( wp_unslash( $_POST ), 'sanitize_text_field' );
		$token = isset( $input['token'] ) && is_string( $input['token'] ) ? sanitize_key( $input['token'] ) : '';
		$state = get_transient( self::session_key() );
		if ( ! is_array( $state ) || ! preg_match( '/^[a-zA-Z0-9]{24}$/', $token ) || ! hash_equals( $state['token'], $token ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid or expired sync session.', 'taxocommerce-customers' ) ), 400 );
		}
		if ( ! self::available() ) {
			wp_send_json_error( array( 'message' => __( 'WooCommerce is not available. Manual contacts remain usable.', 'taxocommerce-customers' ) ), 400 );
		}
		$limit = min( self::MAX_BATCH, max( 1, absint( $state['limit'] ?? 50 ) ) );
		$orders = wc_get_orders( array(
			'status' => wc_get_is_paid_statuses(),
			'limit' => $limit,
			'page' => max( 1, absint( $state['page'] ) ),
			'paginate' => true,
			'return' => 'ids',
			'orderby' => 'ID',
			'order' => 'ASC',
		) );
		if ( ! is_object( $orders ) || ! isset( $orders->orders, $orders->max_num_pages ) || ! is_array( $orders->orders ) ) {
			wp_send_json_error( array( 'message' => __( 'WooCommerce did not return a paginated order list.', 'taxocommerce-customers' ) ), 500 );
		}
		$ids = $orders->orders;
		$start = microtime( true );
		$offset = min( count( $ids ), absint( $state['offset'] ?? 0 ) );
		for ( $i = $offset; $i < count( $ids ); ++$i ) {
			if ( $i > $offset && ( microtime( true ) - $start ) > 7.5 ) {
				break;
			}
			$order = wc_get_order( absint( $ids[ $i ] ) );
			$result = $order ? self::sync_order( $order ) : 'skipped';
			if ( ! isset( $state[ $result ] ) ) {
				$result = 'skipped';
			}
			++$state[ $result ];
		}
		$state['offset'] = $i;
		$done_page = $i >= count( $ids );
		$pages = max( 1, absint( $orders->max_num_pages ) );
		$done = $done_page && absint( $state['page'] ) >= $pages;
		$completed_page = absint( $state['page'] );
		$processed = $state['linked'] + $state['skipped'] + $state['conflict'] + $state['error'];
		if ( $done ) {
			update_option( 'tccust_backfill_complete', true, false );
			update_option( 'tccust_backfill_page', 1, false );
			update_option( 'tccust_backfill_offset', 0, false );
			delete_transient( self::session_key() );
		} else {
			if ( $done_page ) {
				++$state['page'];
				$state['offset'] = 0;
			}
			update_option( 'tccust_backfill_page', $state['page'], false );
			update_option( 'tccust_backfill_offset', $state['offset'], false );
			set_transient( self::session_key(), $state, HOUR_IN_SECONDS );
		}
		$progress = $done ? 100 : min( 99, (int) ( 100 * ( $completed_page - 1 + $i / max( 1, count( $ids ) ) ) / $pages ) );
		wp_send_json_success( array(
			'done' => $done,
			'page' => $completed_page,
			'pages' => $pages,
			'linked' => $state['linked'],
			'skipped' => $state['skipped'],
			'conflict' => $state['conflict'],
			'error' => $state['error'],
			'processed' => $processed,
			'progress' => $progress,
		) );
	}

	public static function cancel() {
		self::authorize();
		delete_transient( self::session_key() );
		wp_send_json_success( array( 'cancelled' => true ) );
	}
}
