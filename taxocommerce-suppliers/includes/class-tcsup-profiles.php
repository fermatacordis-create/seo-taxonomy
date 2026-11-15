<?php
/**
 * Private supplier directory: names always come from the imported catalogue.
 *
 * @package TaxoCommerceSuppliers
 */

defined( 'ABSPATH' ) || exit;

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound -- TCSUP_ is the established plugin prefix.
final class TCSUP_Profiles {

	const TAB = 'providers';

	public static function boot() {
		add_action( 'admin_post_tcsup_profile_save', array( __CLASS__, 'handle_save' ) );
		add_action( 'admin_post_tcsup_profile_delete', array( __CLASS__, 'handle_delete' ) );
	}

	private static function guard() {
		if ( ! current_user_can( TCSUP_Admin::capability() ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage TaxoCommerce Suppliers.', 'taxocommerce-suppliers' ) );
		}
	}

	private static function url( $args = array() ) {
		return add_query_arg(
			array_merge( array( 'page' => TCSUP_Admin::PAGE, 'tab' => self::TAB ), $args ),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Resolve to the actual catalogue name. A profile can never invent a supplier.
	 *
	 * @param string $name Supplier sent by a read-only link or a form.
	 * @return string Canonical name from inventory, or an empty string.
	 */
	public static function supplier_in_catalogue( $name ) {
		global $wpdb;
		$name = trim( sanitize_text_field( (string) $name ) );
		if ( '' === $name ) {
			return '';
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Supplier names must reflect newly imported CSV rows immediately.
		$result = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT proveedor FROM %i WHERE proveedor=%s AND proveedor<>%s LIMIT 1',
				TCSUP_Schema::catalog_table(),
				$name,
				''
			)
		);
		return is_string( $result ) ? $result : '';
	}

	private static function profile( $supplier ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Read the up-to-date private contact record.
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE proveedor=%s LIMIT 1',
				TCSUP_Schema::profiles_table(),
				$supplier
			),
			ARRAY_A
		);
		return is_array( $row ) ? $row : array();
	}

	/**
	 * The inventory is authoritative; the profile table only enriches its names.
	 */
	private static function listing( $search, $page, $per_page = 30 ) {
		global $wpdb;
		$like   = '%' . $wpdb->esc_like( $search ) . '%';
		$offset = ( $page - 1 ) * $per_page;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Always display the actual inventory after a supplier import.
		$total = absint( $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT proveedor) FROM %i WHERE proveedor<>%s AND (%s=%s OR proveedor LIKE %s)",
				TCSUP_Schema::catalog_table(),
				'',
				$search,
				'',
				$like
			)
		) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- The directory must reflect current catalogue names and row totals.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT c.proveedor, c.productos, p.nombre_comercial, p.contacto_nombre,
						p.telefono, p.email, p.actualizado AS ficha_actualizada
				   FROM (
					 SELECT proveedor, COUNT(*) AS productos FROM %i
					 WHERE proveedor<>%s AND (%s=%s OR proveedor LIKE %s) GROUP BY proveedor
					 ORDER BY proveedor ASC LIMIT %d OFFSET %d
				   ) AS c
				   LEFT JOIN %i AS p ON p.proveedor=c.proveedor
				   ORDER BY c.proveedor ASC",
				TCSUP_Schema::catalog_table(),
				'',
				$search,
				'',
				$like,
				$per_page,
				$offset,
				TCSUP_Schema::profiles_table()
			),
			ARRAY_A
		);
		return array(
			'rows'  => is_array( $rows ) ? $rows : array(),
			'total' => $total,
			'pages' => max( 1, (int) ceil( $total / $per_page ) ),
		);
	}

	private static function fields() {
		return array(
			'nombre_comercial'  => array( __( 'Commercial name', 'taxocommerce-suppliers' ), 'text', 190 ),
			'razon_social'      => array( __( 'Legal business name', 'taxocommerce-suppliers' ), 'text', 190 ),
			'nif'               => array( __( 'Tax ID', 'taxocommerce-suppliers' ), 'text', 40 ),
			'telefono'          => array( __( 'Company phone', 'taxocommerce-suppliers' ), 'tel', 60 ),
			'email'             => array( __( 'Company email', 'taxocommerce-suppliers' ), 'email', 190 ),
			'web'               => array( __( 'Website', 'taxocommerce-suppliers' ), 'url', 255 ),
			'contacto_nombre'   => array( __( 'Sales contact', 'taxocommerce-suppliers' ), 'text', 190 ),
			'contacto_cargo'    => array( __( 'Contact role', 'taxocommerce-suppliers' ), 'text', 100 ),
			'contacto_telefono' => array( __( 'Sales contact phone', 'taxocommerce-suppliers' ), 'tel', 60 ),
			'contacto_email'    => array( __( 'Sales contact email', 'taxocommerce-suppliers' ), 'email', 190 ),
			'direccion'         => array( __( 'Street address', 'taxocommerce-suppliers' ), 'text', 255 ),
			'codigo_postal'     => array( __( 'Postal code', 'taxocommerce-suppliers' ), 'text', 20 ),
			'localidad'         => array( __( 'City', 'taxocommerce-suppliers' ), 'text', 120 ),
			'provincia'         => array( __( 'Province or region', 'taxocommerce-suppliers' ), 'text', 120 ),
			'pais'              => array( __( 'Country', 'taxocommerce-suppliers' ), 'text', 120 ),
			'plazo_entrega'     => array( __( 'Typical delivery time', 'taxocommerce-suppliers' ), 'text', 120 ),
		);
	}

	private static function field( $name, $record ) {
		$definition = self::fields()[ $name ];
		$label      = $definition[0];
		$type       = $definition[1];
		$max        = $definition[2];
		$value      = isset( $record[ $name ] ) ? (string) $record[ $name ] : '';
		?>
		<label>
			<span><?php echo esc_html( $label ); ?></span>
			<input type="<?php echo esc_attr( $type ); ?>" name="<?php echo esc_attr( $name ); ?>"
				value="<?php echo esc_attr( $value ); ?>" maxlength="<?php echo esc_attr( $max ); ?>">
		</label>
		<?php
	}

	public static function render() {
		self::guard();
		TCSUP_Schema::maybe_install();

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Filters, navigation and redirected notices are read-only.
		$search = isset( $_GET['profile_s'] ) && is_string( $_GET['profile_s'] )
			? sanitize_text_field( wp_unslash( $_GET['profile_s'] ) ) : '';
		$page = isset( $_GET['profile_page'] ) ? max( 1, absint( $_GET['profile_page'] ) ) : 1;
		$requested = isset( $_GET['provider'] ) && is_string( $_GET['provider'] )
			? sanitize_text_field( wp_unslash( $_GET['provider'] ) ) : '';
		$status = isset( $_GET['profile_status'] ) ? sanitize_key( wp_unslash( $_GET['profile_status'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended.
		$supplier = self::supplier_in_catalogue( $requested );
		$list     = self::listing( $search, $page );
		$record   = '' !== $supplier ? self::profile( $supplier ) : array();
		$notices  = array(
			'saved'   => __( 'Supplier record saved.', 'taxocommerce-suppliers' ),
			'deleted' => __( 'Contact record deleted. The supplier and its products remain in the catalogue.', 'taxocommerce-suppliers' ),
			'missing' => __( 'This supplier no longer exists in the catalogue.', 'taxocommerce-suppliers' ),
			'error'   => __( 'The supplier record could not be saved.', 'taxocommerce-suppliers' ),
		);
		?>
		<section class="tcsup-card">
			<h2><?php esc_html_e( 'Supplier management', 'taxocommerce-suppliers' ); ?></h2>
			<p><?php esc_html_e( 'Suppliers are discovered automatically from imported inventory. New suppliers appear after their first successful catalogue import; only their private contact details are edited here.', 'taxocommerce-suppliers' ); ?></p>
			<?php if ( isset( $notices[ $status ] ) ) : ?>
				<div class="notice <?php echo esc_attr( in_array( $status, array( 'error', 'missing' ), true ) ? 'notice-error' : 'notice-success' ); ?> inline">
					<p><?php echo esc_html( $notices[ $status ] ); ?></p>
				</div>
			<?php endif; ?>
			<?php if ( '' !== $requested && '' === $supplier ) : ?>
				<div class="notice notice-error inline"><p><?php esc_html_e( 'The requested supplier is not in the inventory.', 'taxocommerce-suppliers' ); ?></p></div>
			<?php endif; ?>
			<form method="get" class="tcsup-filter">
				<input type="hidden" name="page" value="<?php echo esc_attr( TCSUP_Admin::PAGE ); ?>">
				<input type="hidden" name="tab" value="<?php echo esc_attr( self::TAB ); ?>">
				<label for="tcsup-provider-search"><?php esc_html_e( 'Search suppliers', 'taxocommerce-suppliers' ); ?></label>
				<input id="tcsup-provider-search" type="search" name="profile_s" value="<?php echo esc_attr( $search ); ?>">
				<button class="button"><?php esc_html_e( 'Filter', 'taxocommerce-suppliers' ); ?></button>
			</form>
			<p>
				<?php
				/* translators: %d: count of suppliers currently in the imported catalogue. */
				printf( esc_html__( '%d suppliers in the catalogue', 'taxocommerce-suppliers' ), absint( $list['total'] ) );
				?>
			</p>
			<div class="tcsup-scroll">
				<table class="widefat striped">
					<thead><tr>
						<th><?php esc_html_e( 'Supplier', 'taxocommerce-suppliers' ); ?></th>
						<th><?php esc_html_e( 'Catalogue products', 'taxocommerce-suppliers' ); ?></th>
						<th><?php esc_html_e( 'Commercial name', 'taxocommerce-suppliers' ); ?></th>
						<th><?php esc_html_e( 'Sales contact', 'taxocommerce-suppliers' ); ?></th>
						<th><?php esc_html_e( 'Company phone', 'taxocommerce-suppliers' ); ?></th>
						<th><?php esc_html_e( 'Record', 'taxocommerce-suppliers' ); ?></th>
						<th><?php esc_html_e( 'Action', 'taxocommerce-suppliers' ); ?></th>
					</tr></thead>
					<tbody>
					<?php foreach ( $list['rows'] as $row ) : ?>
						<tr>
							<td><strong><?php echo esc_html( $row['proveedor'] ); ?></strong></td>
							<td><?php echo esc_html( absint( $row['productos'] ) ); ?></td>
							<td><?php echo esc_html( (string) ( $row['nombre_comercial'] ?? '' ) ); ?></td>
							<td><?php echo esc_html( (string) ( $row['contacto_nombre'] ?? '' ) ); ?></td>
							<td><?php echo esc_html( (string) ( $row['telefono'] ?? '' ) ); ?></td>
							<td><?php echo esc_html( empty( $row['ficha_actualizada'] ) ? __( 'Not completed', 'taxocommerce-suppliers' ) : __( 'Saved', 'taxocommerce-suppliers' ) ); ?></td>
							<td><a class="button button-small" href="<?php echo esc_url( self::url( array( 'provider' => $row['proveedor'], 'profile_s' => $search, 'profile_page' => $page ) ) ); ?>">
								<?php echo esc_html( empty( $row['ficha_actualizada'] ) ? __( 'Create record', 'taxocommerce-suppliers' ) : __( 'Edit', 'taxocommerce-suppliers' ) ); ?>
							</a></td>
						</tr>
					<?php endforeach; ?>
					<?php if ( ! $list['rows'] ) : ?>
						<tr><td colspan="7"><?php esc_html_e( 'No suppliers found in the imported catalogue.', 'taxocommerce-suppliers' ); ?></td></tr>
					<?php endif; ?>
					</tbody>
				</table>
			</div>
			<?php if ( $list['pages'] > 1 ) : ?>
				<div class="tablenav"><div class="tablenav-pages">
					<?php
					$base = add_query_arg( 'profile_page', 999999999, self::url( array( 'profile_s' => $search ) ) );
					echo wp_kses_post( paginate_links( array(
						'base'      => str_replace( '999999999', '%#%', $base ),
						'current'   => $page,
						'total'     => $list['pages'],
						'prev_text' => '‹',
						'next_text' => '›',
					) ) );
					?>
				</div></div>
			<?php endif; ?>
			<?php TCSUP_Profiles_Transfer::render_controls(); ?>
		</section>

		<?php if ( '' !== $supplier ) : ?>
			<section class="tcsup-card" id="tcsup-provider-record">
				<h2><?php echo esc_html( ( $record ? __( 'Edit supplier record', 'taxocommerce-suppliers' ) : __( 'Create supplier record', 'taxocommerce-suppliers' ) ) . ': ' . $supplier ); ?></h2>
				<p><?php esc_html_e( 'Supplier identity comes from the inventory and cannot be changed here. These details are private to store administrators.', 'taxocommerce-suppliers' ); ?></p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="tcsup_profile_save">
					<input type="hidden" name="provider" value="<?php echo esc_attr( $supplier ); ?>">
					<?php wp_nonce_field( 'tcsup_profile_save' ); ?>
					<h3><?php esc_html_e( 'Company details', 'taxocommerce-suppliers' ); ?></h3>
					<div class="tcsup-form-grid">
						<?php foreach ( array( 'nombre_comercial', 'razon_social', 'nif', 'telefono', 'email', 'web' ) as $key ) { self::field( $key, $record ); } ?>
					</div>
					<h3><?php esc_html_e( 'Sales contact details', 'taxocommerce-suppliers' ); ?></h3>
					<div class="tcsup-form-grid">
						<?php foreach ( array( 'contacto_nombre', 'contacto_cargo', 'contacto_telefono', 'contacto_email' ) as $key ) { self::field( $key, $record ); } ?>
					</div>
					<h3><?php esc_html_e( 'Address and logistics', 'taxocommerce-suppliers' ); ?></h3>
					<div class="tcsup-form-grid">
						<?php foreach ( array( 'direccion', 'codigo_postal', 'localidad', 'provincia', 'pais', 'plazo_entrega' ) as $key ) { self::field( $key, $record ); } ?>
					</div>
					<p><label for="tcsup-provider-notes"><strong><?php esc_html_e( 'Internal notes and incidents', 'taxocommerce-suppliers' ); ?></strong></label></p>
					<p><textarea id="tcsup-provider-notes" name="observaciones" rows="7" class="large-text" maxlength="10000" placeholder="<?php echo esc_attr__( 'Contact preferences, delivery delays, incorrect shipments or other notes...', 'taxocommerce-suppliers' ); ?>"><?php echo esc_textarea( $record['observaciones'] ?? '' ); ?></textarea></p>
					<?php submit_button( __( 'Save supplier record', 'taxocommerce-suppliers' ) ); ?>
				</form>
				<?php if ( $record ) : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm(<?php echo esc_attr( wp_json_encode( __( 'Delete only the contact record? Catalogue products will not be removed.', 'taxocommerce-suppliers' ) ) ); ?>);">
						<input type="hidden" name="action" value="tcsup_profile_delete">
						<input type="hidden" name="provider" value="<?php echo esc_attr( $supplier ); ?>">
						<?php wp_nonce_field( 'tcsup_profile_delete' ); ?>
						<button type="submit" class="button"><?php esc_html_e( 'Delete contact record', 'taxocommerce-suppliers' ); ?></button>
					</form>
				<?php endif; ?>
			</section>
		<?php endif;
	}

	private static function posted_supplier( $requested ) {
		$supplier = self::supplier_in_catalogue( $requested );
		if ( '' === $supplier ) {
			self::redirect( '', 'missing' );
		}
		return $supplier;
	}

	private static function redirect( $supplier, $status ) {
		$args = array( 'profile_status' => $status );
		if ( '' !== $supplier ) {
			$args['provider'] = $supplier;
		}
		wp_safe_redirect( self::url( $args ) );
		exit;
	}

	private static function clean_value( $value, $key, $max ) {
		$value = is_string( $value ) ? $value : '';
		if ( in_array( $key, array( 'email', 'contacto_email' ), true ) ) {
			$value = sanitize_email( $value );
		} elseif ( 'web' === $key ) {
			$value = esc_url_raw( $value );
		} else {
			$value = sanitize_text_field( $value );
		}
		return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $max, 'UTF-8' ) : substr( $value, 0, $max );
	}

	public static function handle_save() {
		self::guard();
		check_admin_referer( 'tcsup_profile_save' );
		$requested = isset( $_POST['provider'] ) && is_string( $_POST['provider'] )
			? sanitize_text_field( wp_unslash( $_POST['provider'] ) ) : '';
		$supplier = self::posted_supplier( $requested );

		// A single nonce-protected, unslashed and sanitised input snapshot.
		$submitted = map_deep( wp_unslash( $_POST ), 'sanitize_textarea_field' );
		$data      = array();
		foreach ( self::fields() as $key => $definition ) {
			$data[ $key ] = self::clean_value( $submitted[ $key ] ?? '', $key, $definition[2] );
		}
		$raw_notes = isset( $submitted['observaciones'] ) && is_string( $submitted['observaciones'] )
			? $submitted['observaciones'] : '';
		$data['observaciones'] = sanitize_textarea_field( $raw_notes );
		$data['observaciones'] = function_exists( 'mb_substr' ) ? mb_substr( $data['observaciones'], 0, 10000, 'UTF-8' ) : substr( $data['observaciones'], 0, 10000 );
		$data['actualizado'] = current_time( 'mysql' );

		global $wpdb;
		$existing = self::profile( $supplier );
		if ( $existing ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Private supplier record update after verified nonce/capabilities.
			$ok = $wpdb->update( TCSUP_Schema::profiles_table(), $data, array( 'proveedor' => $supplier ) );
		} else {
			$data['proveedor'] = $supplier;
			$data['creado']   = current_time( 'mysql' );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Private supplier record creation for an existing catalogue supplier.
			$ok = $wpdb->insert( TCSUP_Schema::profiles_table(), $data );
		}
		self::redirect( $supplier, false === $ok ? 'error' : 'saved' );
	}

	public static function handle_delete() {
		self::guard();
		check_admin_referer( 'tcsup_profile_delete' );
		$requested = isset( $_POST['provider'] ) && is_string( $_POST['provider'] )
			? sanitize_text_field( wp_unslash( $_POST['provider'] ) ) : '';
		$supplier = self::posted_supplier( $requested );
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Delete only profile data, never inventory or WooCommerce products.
		$ok = $wpdb->delete( TCSUP_Schema::profiles_table(), array( 'proveedor' => $supplier ) );
		self::redirect( $supplier, false === $ok ? 'error' : 'deleted' );
	}
}
