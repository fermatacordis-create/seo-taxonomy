<?php
/**
 * Independent private CRM UI and manual contact CRUD.
 *
 * @package TaxoCommerceCustomers
 */
defined( 'ABSPATH' ) || exit;

final class TCCUST_Admin {
	const PAGE = 'taxocommerce-customers';

	public static function can_manage() {
		return current_user_can( 'manage_taxocommerce_customers' ) || current_user_can( 'manage_options' );
	}

	public static function guard() {
		if ( ! self::can_manage() ) {
			wp_die( esc_html__( 'You do not have permission to manage customer records.', 'taxocommerce-customers' ) );
		}
	}

	public static function boot() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 70 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_post_tccust_save', array( __CLASS__, 'save' ) );
		add_action( 'admin_post_tccust_delete', array( __CLASS__, 'delete' ) );
	}

	public static function register_module( $core ) {
		if ( is_object( $core ) && method_exists( $core, 'register_module' ) ) {
			$core->register_module( 'customers', array(
				'name' => 'TaxoCommerce Customers',
				'version' => TCCUST_VERSION,
				'status' => 'active',
				'description' => __( 'Private customer and purchaser management.', 'taxocommerce-customers' ),
				'admin_url' => admin_url( 'admin.php?page=taxocommerce-customers' ),
			) );
		}
	}

	public static function menu() {
		$capability = 'manage_taxocommerce_customers';
		if ( function_exists( 'taxocommerce_core' ) ) {
			add_submenu_page(
				'taxocommerce-core',
				__( 'Customers', 'taxocommerce-customers' ),
				__( 'Customers', 'taxocommerce-customers' ),
				$capability,
				self::PAGE,
				array( __CLASS__, 'render' )
			);
		} else {
			add_menu_page(
				__( 'TaxoCommerce Customers', 'taxocommerce-customers' ),
				__( 'TaxoCommerce Customers', 'taxocommerce-customers' ),
				$capability,
				self::PAGE,
				array( __CLASS__, 'render' ),
				'dashicons-groups',
				59
			);
		}
	}

	public static function assets( $hook ) {
		if ( false === strpos( (string) $hook, self::PAGE ) ) {
			return;
		}
		wp_enqueue_style( 'tccust-admin', TCCUST_URL . 'assets/admin.css', array(), TCCUST_VERSION );
		wp_enqueue_script( 'tccust-admin', TCCUST_URL . 'assets/admin.js', array(), TCCUST_VERSION, true );
		wp_localize_script( 'tccust-admin', 'tccustStrings', array(
			'exporting' => __( 'Exporting contacts...', 'taxocommerce-customers' ),
			'exported' => __( 'Contacts exported:', 'taxocommerce-customers' ),
			'importing' => __( 'Importing contacts...', 'taxocommerce-customers' ),
			'imported' => __( 'Import complete.', 'taxocommerce-customers' ),
			'syncing' => __( 'Syncing paid WooCommerce orders...', 'taxocommerce-customers' ),
			'synced' => __( 'Customer synchronization complete.', 'taxocommerce-customers' ),
			'stopped' => __( 'Operation stopped. Completed batches are retained.', 'taxocommerce-customers' ),
			'error' => __( 'Unexpected server response.', 'taxocommerce-customers' ),
			'created' => __( 'Created', 'taxocommerce-customers' ),
			'updated' => __( 'Updated', 'taxocommerce-customers' ),
			'unchanged' => __( 'Unchanged', 'taxocommerce-customers' ),
			'rejected' => __( 'Rejected', 'taxocommerce-customers' ),
		) );
	}

	private static function url( $params = array() ) {
		return add_query_arg( array_merge( array( 'page' => self::PAGE ), $params ), admin_url( 'admin.php' ) );
	}

	public static function render() {
		self::guard();
		TCCUST_DB::maybe_install();
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only navigation and filters.
		$tab = isset( $_GET['tab'] ) && is_string( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'directory';
		$id = isset( $_GET['customer_id'] ) ? absint( $_GET['customer_id'] ) : 0;
		$notice = isset( $_GET['tccust_notice'] ) && is_string( $_GET['tccust_notice'] ) ? sanitize_key( wp_unslash( $_GET['tccust_notice'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended.
		if ( ! in_array( $tab, array( 'directory', 'transfer', 'manual', 'autotest' ), true ) ) {
			$tab = 'directory';
		}
		echo '<div class="wrap tccust-wrap">';
		$tabs = array(
			'directory' => __( 'Customer directory', 'taxocommerce-customers' ),
			'transfer' => __( 'Import / Export', 'taxocommerce-customers' ),
			'manual' => __( 'Manuals', 'taxocommerce-customers' ),
			'autotest' => __( 'Autocheck', 'taxocommerce-customers' ),
		);
		echo '<nav class="nav-tab-wrapper" aria-label="' . esc_attr__( 'Customer management tabs', 'taxocommerce-customers' ) . '">';
		foreach ( $tabs as $key => $label ) {
			echo '<a class="nav-tab' . ( $key === $tab ? ' nav-tab-active' : '' ) . '" href="' . esc_url( self::url( array( 'tab' => $key ) ) ) . '">' . esc_html( $label ) . '</a>';
		}
		echo '</nav>';
		$messages = array(
			'saved' => __( 'Customer record saved.', 'taxocommerce-customers' ),
			'deleted' => __( 'Manual customer deleted.', 'taxocommerce-customers' ),
			'cleared' => __( 'CRM-only details cleared; WooCommerce orders remain unchanged.', 'taxocommerce-customers' ),
			'duplicate' => __( 'This email is already associated with another customer.', 'taxocommerce-customers' ),
			'invalid' => __( 'Enter a name and at least one valid email, phone or WhatsApp number.', 'taxocommerce-customers' ),
			'failed' => __( 'Unable to save this customer record.', 'taxocommerce-customers' ),
		);
		if ( isset( $messages[ $notice ] ) ) {
			echo '<div class="notice ' . ( in_array( $notice, array( 'failed', 'duplicate', 'invalid' ), true ) ? 'notice-error' : 'notice-success' ) . ' inline"><p>' . esc_html( $messages[ $notice ] ) . '</p></div>';
		}
		switch ( $tab ) {
			case 'transfer':
				TCCUST_CSV::render();
				break;
			case 'manual':
				TCCUST_Manual::render();
				break;
			case 'autotest':
				if ( ! TaxoCommerce_Customers_Autotest_Code::render_subtabs() ) {
					TCCUST_Autotest::render();
				}
				break;
			default:
				self::directory( $id );
		}
		echo '</div>';
	}

	private static function directory( $id ) {
		global $wpdb;
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Search and list navigation are read-only.
		$search = isset( $_GET['s'] ) && is_string( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$source = isset( $_GET['source'] ) && is_string( $_GET['source'] ) ? sanitize_key( wp_unslash( $_GET['source'] ) ) : '';
		$page = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		// phpcs:enable WordPress.Security.NonceVerification.Recommended.
		if ( ! in_array( $source, array( 'manual', 'guest', 'registered' ), true ) ) {
			$source = '';
		}
		$like = '%' . $wpdb->esc_like( $search ) . '%';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Live directory search with prepared parameters.
		$count = absint( $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM %i WHERE (%s=%s OR source=%s) AND (%s=%s OR nombre LIKE %s OR apellidos LIKE %s OR email LIKE %s OR telefono LIKE %s OR whatsapp LIKE %s OR empresa LIKE %s)',
				TCCUST_DB::customers_table(),
				$source, '', $source,
				$search, '', $like, $like, $like, $like, $like, $like
			)
		) );
		$per = 30;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Paged private customer directory, joined only to locally tracked order IDs.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT c.id,c.woo_user_id,c.email,c.source,c.nombre,c.apellidos,c.empresa,c.telefono,c.whatsapp,c.actualizado,
				 COALESCE(p.purchases,0) AS purchases,p.last_purchase
				 FROM %i c
				 LEFT JOIN (SELECT customer_id,COUNT(*) AS purchases,MAX(purchased_at) AS last_purchase FROM %i GROUP BY customer_id) p ON p.customer_id=c.id
				 WHERE (%s=%s OR c.source=%s)
				 AND (%s=%s OR c.nombre LIKE %s OR c.apellidos LIKE %s OR c.email LIKE %s OR c.telefono LIKE %s OR c.whatsapp LIKE %s OR c.empresa LIKE %s)
				 ORDER BY c.actualizado DESC,c.id DESC LIMIT %d OFFSET %d",
				TCCUST_DB::customers_table(), TCCUST_DB::links_table(),
				$source, '', $source, $search, '', $like, $like, $like, $like, $like, $like,
				$per, ( $page - 1 ) * $per
			),
			ARRAY_A
		);
		$rows = is_array( $rows ) ? $rows : array();
		echo '<section class="tccust-panel">';
		echo '<div class="tccust-top"><div><h2>' . esc_html__( 'Customers and contacts', 'taxocommerce-customers' ) . '</h2>';
		echo '<p>' . esc_html__( 'Paid buyers are read from WooCommerce, including guest checkouts. Email and WhatsApp contacts can also be added without creating store accounts.', 'taxocommerce-customers' ) . '</p></div>';
		echo '<a class="button button-primary" href="' . esc_url( self::url( array( 'customer_id' => 0, 'new' => 1 ) ) ) . '#tccust-record">' . esc_html__( 'Add contact', 'taxocommerce-customers' ) . '</a></div>';
		if ( TCCUST_Sync::available() ) {
			echo '<div id="tccust-sync" data-auto="' . ( TCCUST_Sync::is_backfill_complete() ? '0' : '1' )
				. '" data-ajax="' . esc_url( admin_url( 'admin-ajax.php' ) )
				. '" data-nonce="' . esc_attr( wp_create_nonce( TCCUST_Sync::NONCE ) ) . '">';
			echo '<p><button type="button" class="button" id="tccust-sync-start">' . esc_html__( 'Synchronize WooCommerce buyers', 'taxocommerce-customers' ) . '</button> ';
			echo '<label for="tccust-sync-speed">' . esc_html__( 'Processing speed', 'taxocommerce-customers' ) . '</label> ';
			echo '<select id="tccust-sync-speed"><option value="10">' . esc_html__( '1 - Very low', 'taxocommerce-customers' ) . '</option>';
			echo '<option value="25">' . esc_html__( '2 - Low', 'taxocommerce-customers' ) . '</option>';
			echo '<option value="50" selected>' . esc_html__( '3 - Normal', 'taxocommerce-customers' ) . '</option>';
			echo '<option value="75">' . esc_html__( '4 - High', 'taxocommerce-customers' ) . '</option>';
			echo '<option value="100">' . esc_html__( '5 - Maximum', 'taxocommerce-customers' ) . '</option></select> ';
			echo '<button type="button" class="button" id="tccust-sync-stop" hidden>' . esc_html__( 'Stop', 'taxocommerce-customers' ) . '</button></p>';
			echo '<p id="tccust-sync-status" role="status" aria-live="polite"></p><progress id="tccust-sync-progress" hidden max="100" value="0"></progress></div>';
		} else {
			echo '<div class="notice notice-info inline"><p>' . esc_html__( 'WooCommerce is inactive. Manual customer management remains available.', 'taxocommerce-customers' ) . '</p></div>';
		}
		echo '<form method="get" class="tccust-search">';
		echo '<input type="hidden" name="page" value="' . esc_attr( self::PAGE ) . '">';
		echo '<input type="search" name="s" value="' . esc_attr( $search ) . '" placeholder="' . esc_attr__( 'Search name, email or phone', 'taxocommerce-customers' ) . '">';
		echo '<select name="source"><option value="">' . esc_html__( 'All sources', 'taxocommerce-customers' ) . '</option>';
		foreach ( array( 'manual' => __( 'Manual', 'taxocommerce-customers' ), 'guest' => __( 'Guest', 'taxocommerce-customers' ), 'registered' => __( 'Registered', 'taxocommerce-customers' ) ) as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '"' . selected( $source, $value, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select><button class="button">' . esc_html__( 'Filter', 'taxocommerce-customers' ) . '</button></form>';
		/* translators: %d: number of customer directory records. */
		echo '<p><strong>' . esc_html( sprintf( __( '%d customer records', 'taxocommerce-customers' ), $count ) ) . '</strong></p>';
		echo '<div class="tccust-table"><table class="widefat striped"><thead><tr>';
		foreach ( array( __( 'Customer', 'taxocommerce-customers' ), __( 'Email', 'taxocommerce-customers' ), __( 'Phone', 'taxocommerce-customers' ), __( 'Source', 'taxocommerce-customers' ), __( 'Purchases', 'taxocommerce-customers' ), __( 'Last purchase', 'taxocommerce-customers' ), __( 'Action', 'taxocommerce-customers' ) ) as $column ) {
			echo '<th>' . esc_html( $column ) . '</th>';
		}
		echo '</tr></thead><tbody>';
		foreach ( $rows as $row ) {
			$full_name = trim( $row['nombre'] . ' ' . $row['apellidos'] );
			$url = self::url( array( 'customer_id' => $row['id'], 's' => $search, 'source' => $source, 'paged' => $page ) ) . '#tccust-record';
			echo '<tr><td><strong>' . esc_html( $full_name ?: $row['empresa'] ?: __( 'Unnamed contact', 'taxocommerce-customers' ) ) . '</strong></td>';
			echo '<td>' . esc_html( $row['email'] ) . '</td><td>' . esc_html( $row['telefono'] ?: $row['whatsapp'] ) . '</td>';
			$labels = array( 'manual' => __( 'Manual', 'taxocommerce-customers' ), 'guest' => __( 'Guest', 'taxocommerce-customers' ), 'registered' => __( 'Registered', 'taxocommerce-customers' ) );
			echo '<td>' . esc_html( $labels[ $row['source'] ] ?? $row['source'] ) . '</td>';
			echo '<td>' . esc_html( absint( $row['purchases'] ) ) . '</td><td>' . esc_html( $row['last_purchase'] ?? '' ) . '</td>';
			echo '<td><a class="button button-small" href="' . esc_url( $url ) . '">' . esc_html__( 'Edit record', 'taxocommerce-customers' ) . '</a></td></tr>';
		}
		if ( ! $rows ) {
			echo '<tr><td colspan="7">' . esc_html__( 'No customer records match these filters.', 'taxocommerce-customers' ) . '</td></tr>';
		}
		echo '</tbody></table></div>';
		$pages = (int) ceil( $count / $per );
		if ( $pages > 1 ) {
			$base = add_query_arg( 'paged', 999999999, self::url( array( 's' => $search, 'source' => $source ) ) );
			echo '<div class="tablenav"><div class="tablenav-pages">'
				. wp_kses_post( paginate_links( array( 'base' => str_replace( '999999999', '%#%', $base ), 'current' => $page, 'total' => $pages ) ) )
				. '</div></div>';
		}
		echo '</section>';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only "new" navigation.
		$new = isset( $_GET['new'] );
		if ( $id || $new ) {
			self::form( $id );
		}
	}

	public static function fields() {
		return array(
			'nombre' => array( __( 'First name', 'taxocommerce-customers' ), 'text', 150 ),
			'apellidos' => array( __( 'Last name', 'taxocommerce-customers' ), 'text', 150 ),
			'empresa' => array( __( 'Company', 'taxocommerce-customers' ), 'text', 190 ),
			'email' => array( __( 'Email', 'taxocommerce-customers' ), 'email', 190 ),
			'telefono' => array( __( 'Phone', 'taxocommerce-customers' ), 'tel', 60 ),
			'whatsapp' => array( __( 'WhatsApp', 'taxocommerce-customers' ), 'tel', 60 ),
			'direccion' => array( __( 'Street address', 'taxocommerce-customers' ), 'text', 255 ),
			'codigo_postal' => array( __( 'Postal code', 'taxocommerce-customers' ), 'text', 20 ),
			'localidad' => array( __( 'City', 'taxocommerce-customers' ), 'text', 120 ),
			'provincia' => array( __( 'Province or region', 'taxocommerce-customers' ), 'text', 120 ),
			'pais' => array( __( 'Country', 'taxocommerce-customers' ), 'text', 120 ),
			'etiquetas' => array( __( 'Internal labels', 'taxocommerce-customers' ), 'text', 255 ),
		);
	}

	private static function form( $id ) {
		$customer = $id ? TCCUST_DB::find( $id ) : null;
		if ( $id && ! $customer ) {
			echo '<div class="notice notice-error inline"><p>' . esc_html__( 'Customer not found.', 'taxocommerce-customers' ) . '</p></div>';
			return;
		}
		if ( $customer && ! empty( $customer['erased_at'] ) ) {
			echo '<section id="tccust-record" class="tccust-panel"><p>' . esc_html__( 'This CRM record has been anonymized following a privacy request. The WooCommerce order references are retained.', 'taxocommerce-customers' ) . '</p></section>';
			return;
		}
		$locked = $customer && 'manual' !== $customer['source'];
		echo '<section id="tccust-record" class="tccust-panel"><h2>';
		echo esc_html( $customer ? __( 'Edit customer record', 'taxocommerce-customers' ) : __( 'New manual contact', 'taxocommerce-customers' ) );
		echo '</h2><p>' . esc_html__( 'These are private CRM details. Editing them does not change WooCommerce orders, customer accounts or billing records.', 'taxocommerce-customers' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="tccust_save"><input type="hidden" name="id" value="' . esc_attr( $id ) . '">';
		wp_nonce_field( 'tccust_save' );
		echo '<div class="tccust-fields">';
		foreach ( self::fields() as $field => $definition ) {
			$value = $customer[ $field ] ?? '';
			echo '<label><span>' . esc_html( $definition[0] ) . '</span><input type="' . esc_attr( $definition[1] ) . '" name="' . esc_attr( $field ) . '" value="' . esc_attr( $value ) . '" maxlength="' . esc_attr( $definition[2] ) . '"' . ( 'email' === $field && $locked ? ' readonly' : '' ) . '></label>';
		}
		echo '<label><span>' . esc_html__( 'Preferred contact channel', 'taxocommerce-customers' ) . '</span><select name="canal_preferido">';
		$channels = array( '' => __( 'Not specified', 'taxocommerce-customers' ), 'email' => __( 'Email', 'taxocommerce-customers' ), 'phone' => __( 'Phone', 'taxocommerce-customers' ), 'whatsapp' => __( 'WhatsApp', 'taxocommerce-customers' ) );
		foreach ( $channels as $key => $label ) {
			echo '<option value="' . esc_attr( $key ) . '"' . selected( $customer['canal_preferido'] ?? '', $key, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select></label></div>';
		if ( current_user_can( 'view_taxocommerce_private_notes' ) ) {
			echo '<p><label for="tccust-notes"><strong>' . esc_html__( 'Private notes and incidents', 'taxocommerce-customers' ) . '</strong></label></p>';
			echo '<textarea class="large-text" id="tccust-notes" rows="7" maxlength="10000" name="observaciones">' . esc_textarea( $customer['observaciones'] ?? '' ) . '</textarea>';
		}
		submit_button( __( 'Save record', 'taxocommerce-customers' ) );
		echo '</form>';
		if ( $customer && current_user_can( 'erase_taxocommerce_customer_data' ) ) {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			echo '<input type="hidden" name="action" value="tccust_delete"><input type="hidden" name="id" value="' . esc_attr( $customer['id'] ) . '">';
			wp_nonce_field( 'tccust_delete' );
			echo '<button type="submit" class="button">' . esc_html( TCCUST_DB::has_orders( $id ) ? __( 'Clear CRM-only details', 'taxocommerce-customers' ) : __( 'Delete contact', 'taxocommerce-customers' ) ) . '</button></form>';
		}
		echo '</section>';
	}

	public static function valid_data( $input, $existing = null ) {
		$data = array();
		foreach ( self::fields() as $key => $definition ) {
			$raw = isset( $input[ $key ] ) && is_string( $input[ $key ] ) ? $input[ $key ] : '';
			$value = 'email' === $key ? sanitize_email( $raw ) : sanitize_text_field( $raw );
			if ( 'email' === $key && $existing && 'manual' !== $existing['source'] ) {
				$value = $existing['email']; // WooCommerce email is not writable from CRM.
			}
			$length = function_exists( 'mb_strlen' ) ? mb_strlen( $value, 'UTF-8' ) : strlen( $value );
			if ( $length > $definition[2] ) {
				return false;
			}
			$data[ $key ] = $value;
		}
		$key = TCCUST_DB::email_key( $data['email'] );
		if ( '' !== $data['email'] && null === $key ) {
			return false;
		}
		$data['email_key'] = $key;
		$channel = isset( $input['canal_preferido'] ) && is_string( $input['canal_preferido'] ) ? sanitize_key( $input['canal_preferido'] ) : '';
		if ( ! in_array( $channel, array( '', 'email', 'phone', 'whatsapp' ), true ) ) {
			return false;
		}
		$data['canal_preferido'] = $channel;
		$notes = isset( $input['observaciones'] ) && is_string( $input['observaciones'] ) ? sanitize_textarea_field( $input['observaciones'] ) : '';
		$data['observaciones'] = function_exists( 'mb_substr' ) ? mb_substr( $notes, 0, 10000, 'UTF-8' ) : substr( $notes, 0, 10000 );
		if ( ! $existing && ( '' === trim( $data['nombre'] . ' ' . $data['apellidos'] . ' ' . $data['empresa'] )
			|| ( null === $key && '' === $data['telefono'] && '' === $data['whatsapp'] ) ) ) {
			return false;
		}
		return $data;
	}

	private static function redirect( $notice, $id = 0 ) {
		$args = array( 'tccust_notice' => $notice );
		if ( $id ) {
			$args['customer_id'] = $id;
		}
		wp_safe_redirect( self::url( $args ) );
		exit;
	}

	public static function save() {
		self::guard();
		check_admin_referer( 'tccust_save' );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified above.
		$posted = map_deep( wp_unslash( $_POST ), 'sanitize_textarea_field' );
		$id = isset( $posted['id'] ) ? absint( $posted['id'] ) : 0;
		$existing = $id ? TCCUST_DB::find( $id ) : null;
		if ( $id && ! $existing ) {
			self::redirect( 'invalid' );
		}
		if ( $existing && ! empty( $existing['erased_at'] ) ) {
			wp_die( esc_html__( 'Anonymized CRM records cannot be repopulated.', 'taxocommerce-customers' ) );
		}
		if ( ! current_user_can( 'view_taxocommerce_private_notes' ) ) {
			$posted['observaciones'] = $existing['observaciones'] ?? '';
		}
		$data = self::valid_data( $posted, $existing );
		if ( false === $data ) {
			self::redirect( 'invalid', $id );
		}
		$duplicate = null !== $data['email_key'] ? TCCUST_DB::find_email( $data['email_key'] ) : null;
		if ( ! $existing && null !== $data['email_key'] && TCCUST_DB::find_erased_email( $data['email_key'] ) ) {
			self::redirect( 'duplicate', $id );
		}
		if ( $duplicate && ( ! $existing || absint( $duplicate['id'] ) !== $id ) ) {
			self::redirect( 'duplicate', $id );
		}
		if ( ! $existing ) {
			$data['source'] = 'manual';
			$data['woo_user_id'] = null;
		}
		$saved = TCCUST_DB::save( $data, $existing );
		self::redirect( $saved ? 'saved' : 'failed', $saved ?: $id );
	}

	public static function delete() {
		self::guard();
		if ( ! current_user_can( 'erase_taxocommerce_customer_data' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'taxocommerce-customers' ) );
		}
		check_admin_referer( 'tccust_delete' );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce checked above.
		$posted = map_deep( wp_unslash( $_POST ), 'sanitize_text_field' );
		$id = isset( $posted['id'] ) ? absint( $posted['id'] ) : 0;
		$customer = TCCUST_DB::find( $id );
		if ( ! $customer ) {
			self::redirect( 'invalid' );
		}
		if ( TCCUST_DB::has_orders( $id ) || 'manual' !== $customer['source'] ) {
			// Keep WooCommerce buyer identity and purchase references.
			$clear = array( 'whatsapp' => '', 'canal_preferido' => '', 'etiquetas' => '', 'observaciones' => '' );
			$result = TCCUST_DB::save( $clear, $customer );
			self::redirect( $result ? 'cleared' : 'failed', $id );
		}
		$result = TCCUST_DB::delete( $id );
		self::redirect( false !== $result ? 'deleted' : 'failed' );
	}
}
