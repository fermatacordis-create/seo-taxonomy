<?php
/**
 * Read-only functional verification and JSON export.
 *
 * @package TaxoCommerceCustomers
 */
defined( 'ABSPATH' ) || exit;

final class TCCUST_Autotest {
	const NONCE = 'tccust_functional_check';

	public static function boot() {
		add_action( 'admin_post_tccust_autotest_run', array( __CLASS__, 'handle' ) );
		add_action( 'admin_post_tccust_autotest_json', array( __CLASS__, 'download' ) );
	}

	private static function cache_key() {
		return 'tccust_autotest_' . get_current_user_id();
	}

	public static function report() {
		$rows = array();
		$rows[] = array(
			'check' => 'plugin_version',
			'status' => 'pass',
			'detail' => TCCUST_VERSION,
		);
		$rows[] = array(
			'check' => 'woocommerce_api',
			'status' => TCCUST_Sync::available() ? 'pass' : 'info',
			'detail' => TCCUST_Sync::available() ? 'WooCommerce order API available' : 'Manual mode (WooCommerce optional)',
		);
		$rows[] = array(
			'check' => 'backfill',
			'status' => ! TCCUST_Sync::available() || TCCUST_Sync::is_backfill_complete() ? 'pass' : 'info',
			'detail' => TCCUST_Sync::is_backfill_complete() ? 'Historical paid orders synchronized' : 'Historical scan not completed yet',
		);
		global $wpdb;
		foreach ( array(
			'customers_table' => TCCUST_DB::customers_table(),
			'order_links_table' => TCCUST_DB::links_table(),
		) as $name => $table ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Live read-only schema health test.
			$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
			$rows[] = array( 'check' => $name, 'status' => $exists === $table ? 'pass' : 'error', 'detail' => $table );
		}
		$rows[] = array( 'check' => 'csv_schema', 'status' => 17 === count( TCCUST_CSV::HEADERS ) ? 'pass' : 'error', 'detail' => count( TCCUST_CSV::HEADERS ) . ' CSV columns' );
		foreach ( array( 'es_ES', 'en_US', 'fr_FR', 'de_DE', 'nl_NL' ) as $locale ) {
			$path = TCCUST_DIR . 'languages/taxocommerce-customers-' . $locale . '.mo';
			$rows[] = array( 'check' => 'language_' . $locale, 'status' => is_readable( $path ) ? 'pass' : 'error', 'detail' => $locale );
		}
		return array(
			'executed_at' => gmdate( 'c' ),
			'plugin' => 'taxocommerce-customers',
			'version' => TCCUST_VERSION,
			'rows' => $rows,
			'totals' => array(
				'pass' => count( array_filter( $rows, static function ( $row ) { return 'pass' === $row['status']; } ) ),
				'info' => count( array_filter( $rows, static function ( $row ) { return 'info' === $row['status']; } ) ),
				'error' => count( array_filter( $rows, static function ( $row ) { return 'error' === $row['status']; } ) ),
			),
			'summary' => array(
				'pass' => count( array_filter( $rows, static function ( $row ) { return 'pass' === $row['status']; } ) ),
				'info' => count( array_filter( $rows, static function ( $row ) { return 'info' === $row['status']; } ) ),
				'error' => count( array_filter( $rows, static function ( $row ) { return 'error' === $row['status']; } ) ),
			),
			'checks' => $rows,
		);
	}

	public static function handle() {
		TCCUST_Admin::guard();
		check_admin_referer( self::NONCE );
		set_transient( self::cache_key(), self::report(), DAY_IN_SECONDS );
		wp_safe_redirect( admin_url( 'admin.php?page=taxocommerce-customers&tab=autotest' ) );
		exit;
	}

	public static function download() {
		TCCUST_Admin::guard();
		check_admin_referer( self::NONCE );
		$report = get_transient( self::cache_key() );
		if ( ! is_array( $report ) ) {
			wp_die( esc_html__( 'Run the functional Autotest first.', 'taxocommerce-customers' ) );
		}
		$json = wp_json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
		if ( ! is_string( $json ) ) {
			wp_die( esc_html__( 'Unable to produce JSON.', 'taxocommerce-customers' ) );
		}
		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="taxocommerce-customers-autotest-' . gmdate( 'Ymd-His' ) . '.json"' );
		echo $json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Valid JSON attachment, not HTML.
		exit;
	}

	public static function render() {
		$report = get_transient( self::cache_key() );
		echo '<section class="tccust-panel"><h2>' . esc_html__( 'Functionality Check', 'taxocommerce-customers' ) . '</h2>';
		echo '<p>' . esc_html__( 'Read-only schema, WooCommerce connection, CSV and translation checks. No customer records are altered.', 'taxocommerce-customers' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="tccust_autotest_run">';
		wp_nonce_field( self::NONCE );
		submit_button( __( 'Run Autotest', 'taxocommerce-customers' ), 'primary', 'submit', false );
		echo '</form>';
		if ( is_array( $report ) ) {
			echo '<p><strong>' . esc_html__( 'Fecha de ejecución:', 'taxocommerce-customers' ) . ': ' . esc_html( $report['executed_at'] )
				. '</strong> · ' . esc_html( $report['summary']['pass'] ) . ' ' . esc_html__( 'Passed', 'taxocommerce-customers' )
				. ' · ' . esc_html( $report['summary']['info'] ) . ' ' . esc_html__( 'Information', 'taxocommerce-customers' )
				. ' · ' . esc_html( $report['summary']['error'] ) . ' ' . esc_html__( 'Errors', 'taxocommerce-customers' ) . '</p>';
			echo '<div class="tccust-table"><table class="widefat striped"><thead><tr><th>' . esc_html__( 'Check', 'taxocommerce-customers' )
				. '</th><th>' . esc_html__( 'Status', 'taxocommerce-customers' ) . '</th><th>' . esc_html__( 'Detail', 'taxocommerce-customers' ) . '</th></tr></thead><tbody>';
			foreach ( $report['checks'] as $row ) {
				echo '<tr><td>' . esc_html( $row['check'] ) . '</td><td>' . esc_html( $row['status'] )
					. '</td><td>' . esc_html( $row['detail'] ) . '</td></tr>';
			}
			echo '</tbody></table></div>';
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			echo '<input type="hidden" name="action" value="tccust_autotest_json">';
			wp_nonce_field( self::NONCE );
			submit_button( __( 'Download JSON', 'taxocommerce-customers' ), 'secondary', 'submit', false );
			echo '</form>';
		}
		echo '</section>';
	}
}
