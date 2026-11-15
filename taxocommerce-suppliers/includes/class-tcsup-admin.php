<?php
/**
 * TaxoCommerce Suppliers administration.
 *
 * @package TaxoCommerceSuppliers
 */

defined( 'ABSPATH' ) || exit;

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound -- Stable TCSUP_ public class names are an existing namespace/API contract; renaming would break extensions.
final class TCSUP_Admin {

	const PAGE = 'taxocommerce-suppliers';

	public static function boot() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 70 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );

		add_action( 'admin_post_tcsup_analyze_csv', array( 'TCSUP_CSV', 'handle_analyze' ) );
		add_action( 'admin_post_tcsup_import_csv', array( 'TCSUP_CSV', 'handle_import' ) );
		add_action( 'admin_post_tcsup_save_template', array( 'TCSUP_CSV', 'handle_save_template' ) );
		add_action( 'admin_post_tcsup_create_template', array( 'TCSUP_CSV', 'handle_create_template' ) );
		add_action( 'admin_post_tcsup_export_template', array( 'TCSUP_CSV', 'handle_export_template' ) );
		add_action( 'admin_post_tcsup_delete_template', array( 'TCSUP_CSV', 'handle_delete_template' ) );
		add_action( 'admin_post_tcsup_download_template', array( 'TCSUP_CSV', 'handle_download_template' ) );

		add_action( 'admin_post_tcsup_row_action', array( 'TCSUP_Products', 'handle_row_action' ) );
		add_action( 'admin_post_tcsup_bulk_action', array( 'TCSUP_Products', 'handle_bulk_action' ) );
		add_action( 'admin_post_tcsup_price_update', array( 'TCSUP_Products', 'handle_price_update' ) );
		add_action( 'admin_post_tcsup_images_update', array( 'TCSUP_Products', 'handle_images_update' ) );
		add_action( 'admin_post_tcsup_settings', array( __CLASS__, 'handle_settings' ) );
		TCSUP_History_Transfer::boot();
		TCSUP_Profiles::boot();
		TCSUP_Profiles_Transfer::boot();
	}

	public static function capability() {
		return class_exists( 'WooCommerce' ) ? 'manage_woocommerce' : 'manage_options';
	}

	public static function menu() {
		$capability = self::capability();
		if ( function_exists( 'taxocommerce_core' ) ) {
			add_submenu_page(
				'taxocommerce-core',
				__( 'TaxoCommerce Suppliers', 'taxocommerce-suppliers' ),
				__( 'Suppliers', 'taxocommerce-suppliers' ),
				$capability,
				self::PAGE,
				array( __CLASS__, 'render' )
			);
		} else {
			add_menu_page(
				__( 'TaxoCommerce Suppliers', 'taxocommerce-suppliers' ),
				__( 'TaxoCommerce Suppliers', 'taxocommerce-suppliers' ),
				$capability,
				self::PAGE,
				array( __CLASS__, 'render' ),
				'dashicons-database-import',
				59
			);
		}
	}

	public static function assets( $hook ) {
		if ( false === strpos( (string) $hook, self::PAGE ) ) {
			return;
		}
		if ( 'history' === self::active_tab() ) {
			wp_enqueue_script( 'taxocommerce-suppliers-history', TAXOCOMMERCE_SUPPLIERS_URL . 'assets/price-history.js', array(), TAXOCOMMERCE_SUPPLIERS_VERSION, true );
			wp_localize_script( 'taxocommerce-suppliers-history', 'tcsupHistoryStrings', array(
				'exporting' => __( 'Exporting price history...', 'taxocommerce-suppliers' ),
				'exportDone' => __( 'CSV exported. Rows: ', 'taxocommerce-suppliers' ),
				'importing' => __( 'Importing historical prices...', 'taxocommerce-suppliers' ),
				'importDone' => __( 'Import finished. ', 'taxocommerce-suppliers' ),
				'inserted' => __( 'Added: ', 'taxocommerce-suppliers' ),
				'duplicates' => __( 'Duplicates: ', 'taxocommerce-suppliers' ),
				'unmatched' => __( 'Unmatched: ', 'taxocommerce-suppliers' ),
				'invalid' => __( 'Invalid: ', 'taxocommerce-suppliers' ),
				'serverError' => __( 'Server request failed.', 'taxocommerce-suppliers' ),
			) );
		}
		if ( 'providers' === self::active_tab() ) {
			wp_enqueue_script( 'tcsup-profiles-transfer', TAXOCOMMERCE_SUPPLIERS_URL . 'assets/profiles-transfer.js', array(), TAXOCOMMERCE_SUPPLIERS_VERSION, true );
			wp_localize_script( 'tcsup-profiles-transfer', 'tcsupProfilesStrings', array(
				'exporting' => __( 'Exporting supplier records...', 'taxocommerce-suppliers' ),
				'exportDone' => __( 'Exported supplier records: ', 'taxocommerce-suppliers' ),
				'importing' => __( 'Importing supplier records...', 'taxocommerce-suppliers' ),
				'importDone' => __( 'Import completed. ', 'taxocommerce-suppliers' ),
				'added' => __( 'Created: ', 'taxocommerce-suppliers' ),
				'updated' => __( 'Updated: ', 'taxocommerce-suppliers' ),
				'unchanged' => __( 'Unchanged: ', 'taxocommerce-suppliers' ),
				'unmatched' => __( 'Unknown: ', 'taxocommerce-suppliers' ),
				'invalid' => __( 'Invalid: ', 'taxocommerce-suppliers' ),
				'stopping' => __( 'Stopping import after the current batch...', 'taxocommerce-suppliers' ),
				'stopped' => __( 'Import stopped. Completed batches are retained.', 'taxocommerce-suppliers' ),
				'sampleErrors' => __( 'First invalid or unmatched rows: ', 'taxocommerce-suppliers' ),
				'serverError' => __( 'Server request failed.', 'taxocommerce-suppliers' ),
			) );
		}
		wp_enqueue_style(
			'taxocommerce-suppliers-admin',
			TAXOCOMMERCE_SUPPLIERS_URL . 'assets/admin.css',
			array(),
			TAXOCOMMERCE_SUPPLIERS_VERSION
		);
	}

	private static function guard() {
		if ( ! current_user_can( self::capability() ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage TaxoCommerce Suppliers.', 'taxocommerce-suppliers' ) );
		}
	}

	private static function tab_url( $tab, $args = array() ) {
		return add_query_arg(
			array_merge( array( 'page' => self::PAGE, 'tab' => $tab ), $args ),
			admin_url( 'admin.php' )
		);
	}

	private static function active_tab() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only navigation.
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'import';
		return in_array( $tab, array( 'import', 'templates', 'inventory', 'prices', 'history', 'images', 'purchasing', 'providers', 'manual', 'autotest' ), true ) ? $tab : 'import';
	}

	public static function render() {
		self::guard();
		TCSUP_Schema::maybe_install();

		$tab = self::active_tab();
		?>
		<div class="wrap tcsup-wrap">
			<header class="tcsup-header">
				<div>
					<p class="tcsup-eyebrow">TAXOCOMMERCE SUPPLIERS</p>
					<h1><?php esc_html_e( 'Supplier Catalogue', 'taxocommerce-suppliers' ); ?></h1>
					<p><?php esc_html_e( 'Supplier catalogue intake, product decisions, purchasing, price control, and external image references.', 'taxocommerce-suppliers' ); ?></p>
				</div>
			</header>
			<nav class="nav-tab-wrapper tcsup-tabs">
				<?php
				$tabs = array(
					'import'    => __( 'CSV Import', 'taxocommerce-suppliers' ),
					'templates' => __( 'CSV Templates', 'taxocommerce-suppliers' ),
					'inventory' => __( 'Inventory', 'taxocommerce-suppliers' ),
					'prices'    => __( 'Prices', 'taxocommerce-suppliers' ),
					'history'   => __( 'Price history', 'taxocommerce-suppliers' ),
					'images'     => __( 'External Images', 'taxocommerce-suppliers' ),
					'purchasing' => __( 'Purchasing', 'taxocommerce-suppliers' ),
					'providers' => __( 'Supplier management', 'taxocommerce-suppliers' ),
					'manual' => __( 'Manual', 'taxocommerce-suppliers' ),
					'autotest' => __( 'Autocheck', 'taxocommerce-suppliers' ),
				);
				foreach ( $tabs as $key => $label ) {
					printf(
						'<a class="nav-tab %1$s" href="%2$s">%3$s</a>',
						$key === $tab ? 'nav-tab-active' : '',
						esc_url( self::tab_url( $key ) ),
						esc_html( $label )
					);
				}
				?>
			</nav>
			<?php self::notices(); ?>
			<?php
			switch ( $tab ) {
				case 'templates':
					self::render_templates();
					break;
				case 'inventory':
					self::render_inventory();
					break;
				case 'prices':
					self::render_prices();
					break;
				case 'history':
					TCSUP_History_Transfer::render();
					break;
				case 'images':
					self::render_images();
					break;
				case 'purchasing':
					TCSUP_Purchasing::render();
					break;
				case 'providers':
					TCSUP_Profiles::render();
					break;
				case 'manual':
					TCSUP_Manual::render();
					break;
				case 'autotest':
					TCSUP_Autotest::render();
					break;
				default:
					self::render_import();
			}
			?>
		</div>
		<?php
	}

	private static function notices() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Redirect notices only.
		if ( isset( $_GET['tc_error_message'] ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html( rawurldecode( sanitize_text_field( wp_unslash( $_GET['tc_error_message'] ) ) ) ) . '</p></div>';
		}
		if ( isset( $_GET['tc_error'] ) ) {
			$codes = array(
				'upload'           => __( 'The CSV upload failed.', 'taxocommerce-suppliers' ),
				'csv_only'         => __( 'Only CSV files are accepted. XLS/XLSX and web scraping are intentionally excluded.', 'taxocommerce-suppliers' ),
				'too_large'        => __( 'The CSV exceeds the WordPress upload limit.', 'taxocommerce-suppliers' ),
				'storage'          => __( 'The supplier import storage directory is not writable.', 'taxocommerce-suppliers' ),
				'read'             => __( 'The CSV could not be read.', 'taxocommerce-suppliers' ),
				'header'           => __( 'The CSV header is not valid.', 'taxocommerce-suppliers' ),
				'state'            => __( 'The analyzed CSV session expired. Upload the file again.', 'taxocommerce-suppliers' ),
				'provider'         => __( 'A supplier name is required.', 'taxocommerce-suppliers' ),
				'required_mapping' => __( 'Supplier product ID and product name must be mapped before importing.', 'taxocommerce-suppliers' ),
				'vevor_headers'     => __( 'The VEVOR CSV is missing one or more required official feed columns.', 'taxocommerce-suppliers' ),
				'price_mapping'    => __( 'Price updates require an identity column and at least one price column. VEVOR also requires market country and external ID.', 'taxocommerce-suppliers' ),
				'template_name'    => __( 'A template name is required.', 'taxocommerce-suppliers' ),
				'template_file'    => __( 'Archivo de plantilla inválido. Utiliza JSON, CSV o TXT de hasta 64 KiB.', 'taxocommerce-suppliers' ),
				'tcsup_template_size' => __( 'La definición debe contener entre 1 y 65536 bytes.', 'taxocommerce-suppliers' ),
				'tcsup_template_json' => __( 'La definición JSON no es válida.', 'taxocommerce-suppliers' ),
				'tcsup_template_csv' => __( 'Cada fila debe tener dos columnas de correspondencia.', 'taxocommerce-suppliers' ),
				'tcsup_template_duplicate_field' => __( 'La plantilla tiene campos duplicados.', 'taxocommerce-suppliers' ),
				'tcsup_template_format' => __( 'Solo se admiten definiciones JSON, CSV o TXT.', 'taxocommerce-suppliers' ),
				'tcsup_template_empty' => __( 'La plantilla no contiene correspondencias.', 'taxocommerce-suppliers' ),
				'tcsup_template_fields' => __( 'La plantilla contiene campos canónicos desconocidos o valores inválidos.', 'taxocommerce-suppliers' ),
				'tcsup_template_column' => __( 'Una columna de origen está vacía o es demasiado larga.', 'taxocommerce-suppliers' ),
				'tcsup_template_required' => __( 'Asigna al menos el ID externo y el nombre del producto.', 'taxocommerce-suppliers' ),
				'tcsup_template_name' => __( 'Introduce un nombre de plantilla válido.', 'taxocommerce-suppliers' ),
				'tcsup_template_exists' => __( 'Ya existe una plantilla con ese nombre.', 'taxocommerce-suppliers' ),
				'missing'          => __( 'The supplier catalogue row no longer exists.', 'taxocommerce-suppliers' ),
			);
			$code = sanitize_key( wp_unslash( $_GET['tc_error'] ) );
			if ( isset( $codes[ $code ] ) ) {
				echo '<div class="notice notice-error"><p>' . esc_html( $codes[ $code ] ) . '</p></div>';
			}
		}
		if ( isset( $_GET['imported'] ) ) {
			/* translators: 1: processed CSV rows, 2: new, 3: updated, 4: unchanged, 5: failed. */
			$notice_format = __( 'CSV processed: %1$d rows; %2$d new, %3$d updated, %4$d unchanged, %5$d errors.', 'taxocommerce-suppliers' );
			printf(
				'<div class="notice notice-success"><p>' . esc_html( $notice_format ) . '</p></div>',
				absint( $_GET['processed'] ?? 0 ),
				absint( $_GET['new'] ?? 0 ),
				absint( $_GET['updated'] ?? 0 ),
				absint( $_GET['unchanged'] ?? 0 ),
				absint( $_GET['errors'] ?? 0 )
			);
		}
		if ( isset( $_GET['bulk_done'] ) ) {
			/* translators: 1: processed supplier rows, 2: errors. */
			$notice_format = __( '%1$d supplier rows processed. %2$d errors.', 'taxocommerce-suppliers' );
			printf(
				'<div class="notice notice-success"><p>' . esc_html( $notice_format ) . '</p></div>',
				absint( $_GET['bulk_done'] ),
				absint( $_GET['bulk_errors'] ?? 0 )
			);
		}
		if ( isset( $_GET['template_saved'] ) ) {
			echo '<div class="notice notice-success">' . '<p>' . esc_html__( 'CSV mapping template saved.', 'taxocommerce-suppliers' ) . '</p>' . '</div>';
		}
		if ( isset( $_GET['price_saved'] ) ) {
			echo '<div class="notice notice-success">' . '<p>' . esc_html__( 'Price data saved.', 'taxocommerce-suppliers' ) . '</p>' . '</div>';
		}
		if ( isset( $_GET['images_saved'] ) ) {
			echo '<div class="notice notice-success">' . '<p>' . esc_html__( 'External image references saved.', 'taxocommerce-suppliers' ) . '</p>' . '</div>';
		}
		// phpcs:enable
	}

	private static function render_import() {
		$templates = TCSUP_CSV::import_templates();
		$state     = TCSUP_CSV::state();
		$selected  = (string) ( $state['template_id'] ?? 'canonical' );
		?>
		<section class="tcsup-card">
			<h2><?php esc_html_e( '1. Upload supplier CSV', 'taxocommerce-suppliers' ); ?></h2>
			<p><?php esc_html_e( 'Only supplier-provided CSV files are processed. This plugin contains no scraper, crawler, remote runner, or automatic web extraction.', 'taxocommerce-suppliers' ); ?></p>
			<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="tcsup_analyze_csv">
				<?php wp_nonce_field( 'tcsup_analyze_csv' ); ?>
				<div class="tcsup-form-grid">
					<label><strong><?php esc_html_e( 'CSV template', 'taxocommerce-suppliers' ); ?></strong>
						<select name="template_id">
							<?php foreach ( $templates as $id => $template ) : ?>
								<option value="<?php echo esc_attr( $id ); ?>" <?php selected( $selected, $id ); ?>><?php echo esc_html( $template['name'] ); ?></option>
							<?php endforeach; ?>
						</select>
					</label>
					<label><strong><?php esc_html_e( 'Supplier', 'taxocommerce-suppliers' ); ?></strong>
						<input type="text" name="provider" value="<?php echo esc_attr( (string) ( $state['provider'] ?? '' ) ); ?>" placeholder="<?php echo esc_attr__( 'Supplier', 'taxocommerce-suppliers' ); ?>">
					</label>
					<label><strong><?php esc_html_e( 'CSV file', 'taxocommerce-suppliers' ); ?></strong>
						<input type="file" name="supplier_csv" accept=".csv,text/csv" required>
					</label>
				</div>
				<?php submit_button( __( 'Analyze CSV', 'taxocommerce-suppliers' ), 'primary', 'submit', false ); ?>
			</form>
		</section>
		<?php
		if ( ! empty( $state['header'] ) ) {
			self::render_mapping( $state, $templates );
		}
	}

	private static function template_for_state( $state, $templates ) {
		$id = sanitize_key( (string) ( $state['template_id'] ?? 'canonical' ) );
		return isset( $templates[ $id ] ) ? $templates[ $id ] : $templates['canonical'];
	}

	private static function suggested_index( $field, $header, $template ) {
		$mode = (string) ( $template['mode'] ?? 'mapping' );
		if ( 'canonical' === $mode ) {
			foreach ( $header as $index => $column ) {
				if ( sanitize_key( $column ) === $field ) {
					return $index;
				}
			}
		}
		$source = (string) ( $template['mapping'][ $field ] ?? '' );
		if ( $source ) {
			foreach ( $header as $index => $column ) {
				if ( $column === $source || sanitize_key( $column ) === sanitize_key( $source ) ) {
					return $index;
				}
			}
		}
		foreach ( $header as $index => $column ) {
			if ( TCSUP_CSV::suggest_field_for_header( $column ) === $field ) {
				return $index;
			}
		}
		return -1;
	}

	private static function render_mapping( $state, $templates ) {
		$template = self::template_for_state( $state, $templates );
		$header   = (array) $state['header'];
		$mode     = TCSUP_CSV::effective_mode( $template, $header, (string) ( $state['provider'] ?? '' ) );
		?>
		<section class="tcsup-card">
			<h2><?php esc_html_e( '2. Review normalization', 'taxocommerce-suppliers' ); ?></h2>
			<p><strong><?php esc_html_e( 'File:', 'taxocommerce-suppliers' ); ?></strong> <code><?php echo esc_html( (string) $state['filename'] ); ?></code> · <strong><?php esc_html_e( 'Delimiter:', 'taxocommerce-suppliers' ); ?></strong> <code><?php echo "	" === $state['delimiter'] ? 'TAB' : esc_html( $state['delimiter'] ); ?></code></p>
			<?php if ( 'vevor' === $mode ) : ?>
				<div class="notice notice-info inline"><p><?php esc_html_e( 'The selected regional feed is processed locally. No network request is made.', 'taxocommerce-suppliers' ); ?></p></div>
			<?php elseif ( 'price_update' === $mode ) : ?>
				<div class="notice notice-info inline"><p><?php esc_html_e( 'Price update mode never creates supplier catalogue rows. Map supplier external ID or SKU plus at least one price column; include the market country for regional products.', 'taxocommerce-suppliers' ); ?></p></div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="tcsup_import_csv">
				<input type="hidden" name="template_id" value="<?php echo esc_attr( (string) $state['template_id'] ); ?>">
				<?php wp_nonce_field( 'tcsup_import_csv' ); ?>
				<p><label><strong><?php esc_html_e( 'Supplier', 'taxocommerce-suppliers' ); ?></strong> <input type="text" name="provider" value="<?php echo esc_attr( (string) ( $state['provider'] ?? $template['provider'] ?? '' ) ); ?>" required></label></p>
				<?php if ( 'vevor' !== $mode ) : ?>
					<table class="widefat striped tcsup-map-table">
						<thead><tr><th><?php esc_html_e( 'TaxoCommerce field', 'taxocommerce-suppliers' ); ?></th><th><?php esc_html_e( 'CSV column', 'taxocommerce-suppliers' ); ?></th><th><?php esc_html_e( 'Required', 'taxocommerce-suppliers' ); ?></th></tr></thead>
						<tbody>
						<?php foreach ( TCSUP_CSV::fields() as $field => $definition ) : ?>
							<?php $suggested = self::suggested_index( $field, $header, $template ); ?>
							<tr>
								<td><code><?php echo esc_html( $field ); ?></code><br><?php echo esc_html( $definition['label'] ); ?></td>
								<td>
									<select name="mapping[<?php echo esc_attr( $field ); ?>]">
										<option value="-1"><?php esc_html_e( '— Do not import —', 'taxocommerce-suppliers' ); ?></option>
										<?php foreach ( $header as $index => $column ) : ?>
											<option value="<?php echo esc_attr( $index ); ?>" <?php selected( $suggested, $index ); ?>><?php echo esc_html( $column ); ?></option>
										<?php endforeach; ?>
									</select>
								</td>
								<td>
									<?php
									if ( 'price_update' === $mode ) {
										if ( in_array( $field, array( 'proveedor_id_externo', 'sku' ), true ) ) {
											echo esc_html( 'Identity' );
										} elseif ( in_array( $field, array( 'precio_sin_iva', 'precio_con_iva' ), true ) ) {
											echo esc_html( 'At least one price' );
										} elseif ( 'pais_origen' === $field ) {
											echo esc_html__( 'For regional products', 'taxocommerce-suppliers' );
										} else {
											echo esc_html( 'No' );
										}
									} else {
										echo esc_html( ! empty( $definition['required'] ) ? 'Yes' : 'No' );
									}
									?>
								</td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
				<?php if ( 'vevor' !== $mode ) : ?>
					<details class="tcsup-template-save">
						<summary><?php esc_html_e( 'Save this mapping as a reusable template', 'taxocommerce-suppliers' ); ?></summary>
						<p><input type="text" name="save_template_name" placeholder="<?php echo esc_attr__( 'Optional template name', 'taxocommerce-suppliers' ); ?>"></p>
					</details>
				<?php endif; ?>
				<p class="submit"><?php submit_button( __( 'Import into intermediate inventory', 'taxocommerce-suppliers' ), 'primary', 'submit', false ); ?></p>
			</form>

			<h3><?php esc_html_e( 'Preview', 'taxocommerce-suppliers' ); ?></h3>
			<div class="tcsup-scroll"><table class="widefat striped"><thead><tr>
				<?php foreach ( $header as $column ) : ?><th><?php echo esc_html( $column ); ?></th><?php endforeach; ?>
			</tr></thead><tbody>
				<?php foreach ( (array) $state['preview'] as $row ) : ?><tr>
					<?php foreach ( $header as $index => $column ) : ?><td><?php echo esc_html( (string) ( $row[ $index ] ?? '' ) ); ?></td><?php endforeach; ?>
				</tr><?php endforeach; ?>
			</tbody></table></div>
		</section>
		<?php
	}

	private static function render_templates() {
		$templates = TCSUP_CSV::templates();
		$download = wp_nonce_url( admin_url( 'admin-post.php?action=tcsup_download_template' ), 'tcsup_download_template' );
		?>
		<section class="tcsup-card">
			<h2><?php esc_html_e( 'CSV Templates', 'taxocommerce-suppliers' ); ?></h2>
			<p><?php esc_html_e( 'Las plantillas relacionan las columnas del CSV del proveedor con los campos de TaxoCommerce. No ejecutan código ni importan productos al guardarse.', 'taxocommerce-suppliers' ); ?></p>
			<details class="tcsup-template-create" style="margin:14px 0;padding:12px 16px;border:1px solid #c3c4c7;background:#fff;">
				<summary style="cursor:pointer;font-weight:600;"><?php esc_html_e( 'Crear nueva plantilla de proveedor', 'taxocommerce-suppliers' ); ?></summary>
				<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:14px;">
					<input type="hidden" name="action" value="tcsup_create_template">
					<?php wp_nonce_field( 'tcsup_create_template' ); ?>
					<p><label><strong><?php esc_html_e( 'Nombre de la plantilla', 'taxocommerce-suppliers' ); ?></strong><br><input type="text" name="template_name" maxlength="100" required class="regular-text" placeholder="Mi proveedor CSV"></label></p>
					<p><label><strong><?php esc_html_e( 'Proveedor (opcional)', 'taxocommerce-suppliers' ); ?></strong><br><input type="text" name="provider" class="regular-text" placeholder="Nombre del proveedor"></label></p>
					<p><label><strong><?php esc_html_e( 'Subir definición JSON, CSV o TXT (máximo 64 KiB)', 'taxocommerce-suppliers' ); ?></strong><br><input type="file" name="template_file" accept=".json,.csv,.txt,application/json,text/csv,text/plain"></label></p>
					<p><?php esc_html_e( 'O pegar la definición en el campo siguiente:', 'taxocommerce-suppliers' ); ?></p>
					<p><label><strong><?php esc_html_e( 'Formato del texto pegado', 'taxocommerce-suppliers' ); ?></strong><br>
						<select name="template_format"><option value="json">JSON</option><option value="csv">CSV</option><option value="txt">TXT</option></select>
					</label></p>
					<p><textarea name="template_text" rows="7" class="large-text code" placeholder='{"mapping":{"proveedor_id_externo":"Supplier ID","nombre":"Product name","precio_con_iva":"Price","moneda":"Currency"}}'></textarea></p>
					<p class="description"><?php esc_html_e( 'JSON: {"mapping":{"proveedor_id_externo":"ID","nombre":"Nombre"}}. CSV/TXT: dos columnas separadas por coma o punto y coma, una línea por correspondencia (campo interno, columna del proveedor). Si subes un archivo, tiene prioridad sobre el texto pegado.', 'taxocommerce-suppliers' ); ?></p>
					<?php submit_button( __( 'Guardar plantilla', 'taxocommerce-suppliers' ), 'primary', 'submit', false ); ?>
				</form>
			</details>
			<p><a class="button button-primary" href="<?php echo esc_url( $download ); ?>"><?php esc_html_e( 'Descargar cabecera CSV canónica', 'taxocommerce-suppliers' ); ?></a></p>
			<table class="widefat striped">
				<thead><tr><th><?php esc_html_e( 'Template', 'taxocommerce-suppliers' ); ?></th><th><?php esc_html_e( 'Supplier', 'taxocommerce-suppliers' ); ?></th><th><?php esc_html_e( 'Mode', 'taxocommerce-suppliers' ); ?></th><th><?php esc_html_e( 'Columns mapped', 'taxocommerce-suppliers' ); ?></th><th></th></tr></thead>
				<tbody>
				<?php foreach ( $templates as $id => $template ) : ?>
					<tr>
						<td><strong><?php echo esc_html( $template['name'] ); ?></strong><?php echo ! empty( $template['builtin'] ) ? '<br><small>' . esc_html__( 'Built in', 'taxocommerce-suppliers' ) . '</small>' : ''; ?></td>
						<td><?php echo esc_html( (string) ( $template['provider'] ?? '—' ) ); ?></td>
						<td><?php echo esc_html( (string) ( $template['mode'] ?? 'mapping' ) ); ?></td>
						<td><?php echo esc_html( count( (array) ( $template['mapping'] ?? array() ) ) ); ?></td>
						<td>
						<?php if ( empty( $template['builtin'] ) ) : ?>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;margin-right:8px;">
								<input type="hidden" name="action" value="tcsup_export_template">
								<input type="hidden" name="template_id" value="<?php echo esc_attr( $id ); ?>">
								<?php wp_nonce_field( 'tcsup_export_template' ); ?>
								<button class="button button-small"><?php esc_html_e( 'Descargar JSON', 'taxocommerce-suppliers' ); ?></button>
							</form>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;">
								<input type="hidden" name="action" value="tcsup_delete_template">
								<input type="hidden" name="template_id" value="<?php echo esc_attr( $id ); ?>">
								<?php wp_nonce_field( 'tcsup_delete_template' ); ?>
								<button class="button button-small"><?php esc_html_e( 'Delete', 'taxocommerce-suppliers' ); ?></button>
							</form>
						<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
				<?php if ( ! $templates ) : ?>
					<tr><td colspan="5"><?php esc_html_e( 'No templates have been created on this site.', 'taxocommerce-suppliers' ); ?></td></tr>
				<?php endif; ?>
				</tbody>
			</table>
		</section>
		<?php
	}

	private static function request_filter( $key ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filters.
		return isset( $_GET[ $key ] ) ? sanitize_text_field( wp_unslash( $_GET[ $key ] ) ) : '';
	}

	private static function catalogue_query( $limit = 50, $accepted_only = false ) {
		global $wpdb;

		$table         = TCSUP_Schema::catalog_table();
		$provider      = self::request_filter( 'supplier' );
		$selection     = self::request_filter( 'selection' );
		$country       = strtoupper( self::request_filter( 'country' ) );
		$search        = self::request_filter( 's' );
		$accepted_flag = $accepted_only ? 1 : 0;

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only pagination.
		$page   = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		$limit  = max( 1, min( 200, absint( $limit ) ) );
		$offset = ( $page - 1 ) * $limit;
		$like   = '' !== $search ? '%' . $wpdb->esc_like( $search ) . '%' : '';

		$total = absint(
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Read the current supplier inventory/schema for this admin operation; stale cache would misreport changes.
			$wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM %i
					 WHERE (
						(%d=1 AND estado_seleccion IN (%s,%s,%s))
						OR
						(%d=0 AND (%s='' OR estado_seleccion=%s))
					 )
					 AND (%s='' OR proveedor=%s)
					 AND (%s='' OR pais_origen=%s)
					 AND (%s='' OR nombre LIKE %s OR sku LIKE %s OR proveedor_id_externo LIKE %s)",
					$table,
					$accepted_flag,
					'aceptado',
					'publicado',
					'actualizar',
					$accepted_flag,
					$selection,
					$selection,
					$provider,
					$provider,
					$country,
					$country,
					$search,
					$like,
					$like,
					$like
				)
			)
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Read the current supplier inventory/schema for this admin operation; stale cache would misreport changes.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM %i
				 WHERE (
					(%d=1 AND estado_seleccion IN (%s,%s,%s))
					OR
					(%d=0 AND (%s='' OR estado_seleccion=%s))
				 )
				 AND (%s='' OR proveedor=%s)
				 AND (%s='' OR pais_origen=%s)
				 AND (%s='' OR nombre LIKE %s OR sku LIKE %s OR proveedor_id_externo LIKE %s)
				 ORDER BY actualizado DESC,id DESC
				 LIMIT %d OFFSET %d",
				$table,
				$accepted_flag,
				'aceptado',
				'publicado',
				'actualizar',
				$accepted_flag,
				$selection,
				$selection,
				$provider,
				$provider,
				$country,
				$country,
				$search,
				$like,
				$like,
				$like,
				$limit,
				$offset
			),
			ARRAY_A
		);

		return array(
			'rows'  => is_array( $rows ) ? $rows : array(),
			'total' => $total,
			'page'  => $page,
			'pages' => max( 1, (int) ceil( $total / $limit ) ),
			'limit' => $limit,
		);
	}

	private static function render_pagination( $tab, $result ) {
		$pages = absint( $result['pages'] ?? 1 );
		if ( $pages <= 1 ) {
			return;
		}
		$current = max( 1, absint( $result['page'] ?? 1 ) );
		$args = array(
			'page' => self::PAGE,
			'tab'  => $tab,
		);
		foreach ( array( 'supplier', 'selection', 'country', 's' ) as $key ) {
			$value = self::request_filter( $key );
			if ( '' !== $value ) {
				$args[ $key ] = $value;
			}
		}
		$base = add_query_arg( $args, admin_url( 'admin.php' ) );
		$base = add_query_arg( 'paged', 999999999, $base );
		$base = str_replace( '999999999', '%#%', $base );
		echo '<div class="tablenav"><div class="tablenav-pages">';
		echo wp_kses_post(
			paginate_links(
				array(
					'base'      => $base,
					'format'    => '',
					'current'   => $current,
					'total'     => $pages,
					'prev_text' => '‹',
					'next_text' => '›',
				)
			)
		);
		echo '</div></div>';
	}

	private static function providers() {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Current distinct suppliers must reflect new CSV imports immediately; cache would become stale.
		return $wpdb->get_col(
			$wpdb->prepare(
				'SELECT DISTINCT proveedor FROM %i WHERE proveedor<>%s ORDER BY proveedor ASC',
				TCSUP_Schema::catalog_table(),
				''
			)
		);
	}

	private static function filter_bar( $tab, $show_selection = true ) {
		?>
		<form method="get" class="tcsup-filter">
			<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE ); ?>">
			<input type="hidden" name="tab" value="<?php echo esc_attr( $tab ); ?>">
			<select name="supplier"><option value=""><?php esc_html_e( 'All suppliers', 'taxocommerce-suppliers' ); ?></option>
				<?php foreach ( self::providers() as $provider ) : ?><option <?php selected( self::request_filter( 'supplier' ), $provider ); ?>><?php echo esc_html( $provider ); ?></option><?php endforeach; ?>
			</select>
			<?php if ( $show_selection ) : ?>
			<select name="selection">
				<option value=""><?php esc_html_e( 'All decisions', 'taxocommerce-suppliers' ); ?></option>
				<?php foreach ( array( 'pendiente'=>__( 'Pending', 'taxocommerce-suppliers' ),'revisar'=>__( 'Review', 'taxocommerce-suppliers' ),'aceptado'=>__( 'Accepted', 'taxocommerce-suppliers' ),'descartado'=>__( 'Discarded', 'taxocommerce-suppliers' ),'publicado'=>__( 'Published · legacy', 'taxocommerce-suppliers' ),'actualizar'=>__( 'Update · legacy', 'taxocommerce-suppliers' ),'duplicado'=>__( 'Duplicate · legacy', 'taxocommerce-suppliers' ) ) as $value => $label ) : ?>
					<option value="<?php echo esc_attr( $value ); ?>" <?php selected( self::request_filter( 'selection' ), $value ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
			<?php endif; ?>
			<input type="text" name="country" value="<?php echo esc_attr( self::request_filter( 'country' ) ); ?>" placeholder="<?php echo esc_attr__( 'Country', 'taxocommerce-suppliers' ); ?>">
			<input type="search" name="s" value="<?php echo esc_attr( self::request_filter( 's' ) ); ?>" placeholder="<?php echo esc_attr__( 'Name, SKU or external ID', 'taxocommerce-suppliers' ); ?>">
			<button class="button"><?php esc_html_e( 'Filter', 'taxocommerce-suppliers' ); ?></button>
		</form>
		<?php
	}

	private static function selection_label( $value ) {
		$labels = array(
			'pendiente'  => __( 'Pending', 'taxocommerce-suppliers' ),
			'revisar'    => __( 'Review', 'taxocommerce-suppliers' ),
			'aceptado'   => __( 'Accepted', 'taxocommerce-suppliers' ),
			'descartado' => __( 'Discarded', 'taxocommerce-suppliers' ),
			'publicado'  => __( 'Published', 'taxocommerce-suppliers' ),
			'actualizar' => __( 'Update', 'taxocommerce-suppliers' ),
			'duplicado'   => __( 'Duplicate', 'taxocommerce-suppliers' ),
		);
		$value = sanitize_key( (string) $value );
		return $labels[ $value ] ?? ucfirst( str_replace( '_', ' ', $value ) );
	}

	private static function sync_label( $value ) {
		$labels = array(
			'nuevo'        => __( 'New', 'taxocommerce-suppliers' ),
			'modificado'   => __( 'Modified', 'taxocommerce-suppliers' ),
			'sin_cambios'  => __( 'Unchanged', 'taxocommerce-suppliers' ),
			'sincronizado' => __( 'Synchronized', 'taxocommerce-suppliers' ),
			'conflicto'    => __( 'Conflict', 'taxocommerce-suppliers' ),
			'ignorado'     => __( 'Ignored', 'taxocommerce-suppliers' ),
			'pendiente'    => __( 'Pending', 'taxocommerce-suppliers' ),
			'legacy'       => __( 'Legacy', 'taxocommerce-suppliers' ),
		);
		$value = sanitize_key( (string) $value );
		return $labels[ $value ] ?? ucfirst( str_replace( '_', ' ', $value ) );
	}

	private static function render_inventory() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only history selector.
		$history_id = isset( $_GET['history_id'] ) ? absint( $_GET['history_id'] ) : 0;
		$history_row = $history_id ? TCSUP_Products::row( $history_id ) : false;
		$result = self::catalogue_query( 50, false );
		$rows = $result['rows'];
		?>
		<section class="tcsup-card">
			<div class="tcsup-card-head"><div><h2><?php esc_html_e( 'Intermediate supplier inventory', 'taxocommerce-suppliers' ); ?></h2><p><?php esc_html_e( 'CSV rows stay here until they are accepted, sent to review, or discarded.', 'taxocommerce-suppliers' ); ?></p></div></div>
			<?php self::filter_bar( 'inventory', true ); ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="tcsup_bulk_action">
				<?php wp_nonce_field( 'tcsup_bulk_action' ); ?>
				<div class="tcsup-bulk">
					<select name="bulk_supplier_action">
						<option value=""><?php esc_html_e( 'Bulk action', 'taxocommerce-suppliers' ); ?></option>
						<option value="accept"><?php esc_html_e( 'Accept / link to WooCommerce', 'taxocommerce-suppliers' ); ?></option>
						<option value="review"><?php esc_html_e( 'Mark for review', 'taxocommerce-suppliers' ); ?></option>
						<option value="discard"><?php esc_html_e( 'Discard', 'taxocommerce-suppliers' ); ?></option>
						<option value="pending"><?php esc_html_e( 'Return to pending', 'taxocommerce-suppliers' ); ?></option>
					</select>
					<button class="button button-primary"><?php esc_html_e( 'Apply', 'taxocommerce-suppliers' ); ?></button>
				</div>
				<div class="tcsup-scroll"><table class="widefat striped">
					<thead><tr><th><input type="checkbox" id="tcsup-select-page" aria-label="<?php echo esc_attr__( 'Select all rows on this page', 'taxocommerce-suppliers' ); ?>"></th><th><?php esc_html_e( 'Supplier', 'taxocommerce-suppliers' ); ?></th><th><?php esc_html_e( 'External identity', 'taxocommerce-suppliers' ); ?></th><th><?php esc_html_e( 'Product', 'taxocommerce-suppliers' ); ?></th><th><?php esc_html_e( 'Supplier product', 'taxocommerce-suppliers' ); ?></th><th><?php esc_html_e( 'Market', 'taxocommerce-suppliers' ); ?></th><th><?php esc_html_e( 'Supplier price', 'taxocommerce-suppliers' ); ?></th><th><?php esc_html_e( 'Stock', 'taxocommerce-suppliers' ); ?></th><th><?php esc_html_e( 'Decision', 'taxocommerce-suppliers' ); ?></th><th><?php esc_html_e( 'Sync', 'taxocommerce-suppliers' ); ?></th><th><?php esc_html_e( 'Woo ID', 'taxocommerce-suppliers' ); ?></th><th><?php esc_html_e( 'Actions', 'taxocommerce-suppliers' ); ?></th></tr></thead>
					<tbody>
					<?php if ( ! $rows ) : ?><tr><td colspan="12"><?php esc_html_e( 'No supplier rows match the current filters.', 'taxocommerce-suppliers' ); ?></td></tr><?php endif; ?>
					<?php foreach ( $rows as $row ) : ?>
						<tr>
							<td><input type="checkbox" name="row_ids[]" value="<?php echo esc_attr( $row['id'] ); ?>"></td>
							<td><strong><?php echo esc_html( $row['proveedor'] ); ?></strong></td>
							<td><code><?php echo esc_html( $row['proveedor_id_externo'] ); ?></code><br><small><?php echo esc_html( $row['sku'] ); ?></small></td>
							<td><strong><?php echo esc_html( $row['nombre'] ); ?></strong><br><small><?php echo esc_html( $row['marca'] ); ?></small></td>
							<?php
							// Use the same supplier product URL priority as the existing Purchasing tab.
							$supplier_url = esc_url( trim( (string) ( $row['url_origen'] ?? '' ) ) );
							if ( '' === $supplier_url ) {
								$supplier_url = esc_url( trim( (string) ( $row['url_canonica'] ?? '' ) ) );
							}
							?>
							<td><?php if ( '' !== $supplier_url ) : ?><a class="button button-small" href="<?php echo esc_url( $supplier_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Purchase', 'taxocommerce-suppliers' ); ?></a><?php else : ?>—<?php endif; ?></td>
							<td><?php echo esc_html( $row['pais_origen'] ?: '—' ); ?></td>
							<td><?php echo esc_html( ( '' !== (string) $row['precio_con_iva'] ? $row['precio_con_iva'] : $row['precio_sin_iva'] ) . ' ' . $row['moneda'] ); ?></td>
							<td><?php echo esc_html( '' !== (string) $row['stock_cantidad'] ? $row['stock_cantidad'] : $row['stock_estado'] ); ?></td>
							<td><strong><?php echo esc_html( self::selection_label( $row['estado_seleccion'] ) ); ?></strong></td>
							<td><?php echo esc_html( self::sync_label( $row['estado_sincronizacion'] ) ); ?><?php echo $row['cambios_detectados'] ? '<br><small>' . esc_html( $row['cambios_detectados'] ) . '</small>' : ''; ?></td>
							<td><?php echo $row['object_id'] ? '<a href="' . esc_url( get_edit_post_link( absint( $row['object_id'] ) ) ) . '">#' . esc_html( $row['object_id'] ) . '</a>' : '—'; ?></td>
							<td class="tcsup-row-actions">
								<button class="button button-small button-primary" name="row_action" value="accept:<?php echo esc_attr( $row['id'] ); ?>"><?php esc_html_e( 'Accept', 'taxocommerce-suppliers' ); ?></button>
								<button class="button button-small" name="row_action" value="review:<?php echo esc_attr( $row['id'] ); ?>"><?php esc_html_e( 'Review', 'taxocommerce-suppliers' ); ?></button>
								<button class="button button-small" name="row_action" value="discard:<?php echo esc_attr( $row['id'] ); ?>"><?php esc_html_e( 'Discard', 'taxocommerce-suppliers' ); ?></button>
								<a class="button button-small" href="<?php echo esc_url( self::tab_url( 'inventory', array( 'history_id' => absint( $row['id'] ) ) ) ); ?>"><?php esc_html_e( 'Price history', 'taxocommerce-suppliers' ); ?></a>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table></div>
			</form>
			<?php if ( $history_row ) : ?>
				<section class="tcsup-card" id="tcsup-price-history">
					<h3><?php echo esc_html__( 'Price history', 'taxocommerce-suppliers' ) . ': ' . esc_html( $history_row['nombre'] ); ?></h3>
					<p><?php esc_html_e( 'Read-only price snapshots. The first entry reflects the first captured price, not a reconstructed earlier price.', 'taxocommerce-suppliers' ); ?></p>
					<div class="tcsup-scroll"><table class="widefat striped">
						<thead><tr>
							<th><?php esc_html_e( 'Date', 'taxocommerce-suppliers' ); ?></th>
							<th><?php esc_html_e( 'Supplier excl. tax', 'taxocommerce-suppliers' ); ?></th>
							<th><?php esc_html_e( 'Supplier incl. tax', 'taxocommerce-suppliers' ); ?></th>
							<th><?php esc_html_e( 'Woo regular', 'taxocommerce-suppliers' ); ?></th>
							<th><?php esc_html_e( 'Woo sale', 'taxocommerce-suppliers' ); ?></th>
							<th><?php esc_html_e( 'Effective price', 'taxocommerce-suppliers' ); ?></th>
							<th><?php esc_html_e( 'Currency', 'taxocommerce-suppliers' ); ?></th>
							<th><?php esc_html_e( 'Source', 'taxocommerce-suppliers' ); ?></th>
						</tr></thead>
						<tbody>
							<?php $history = TCSUP_Price_History::recent( $history_id ); ?>
							<?php if ( ! $history ) : ?><tr><td colspan="8"><?php esc_html_e( 'No price changes have been recorded yet.', 'taxocommerce-suppliers' ); ?></td></tr><?php endif; ?>
							<?php foreach ( $history as $entry ) : ?>
								<tr>
									<td><?php echo esc_html( get_date_from_gmt( $entry['fecha'], 'Y-m-d H:i:s' ) ); ?></td>
									<?php foreach ( array( 'precio_proveedor_sin_iva', 'precio_proveedor_con_iva', 'precio_normal', 'precio_oferta', 'precio_venta', 'moneda', 'origen' ) as $field ) : ?>
										<td><?php echo esc_html( null === $entry[ $field ] || '' === (string) $entry[ $field ] ? '—' : $entry[ $field ] ); ?></td>
									<?php endforeach; ?>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table></div>
				</section>
			<?php endif; ?>
			<script>
			(function(){
				var toggle=document.getElementById('tcsup-select-page');
				if(!toggle){return;}
				toggle.addEventListener('change',function(){
					document.querySelectorAll('input[name="row_ids[]"]').forEach(function(box){box.checked=toggle.checked;});
				});
			})();
			</script>
			<?php self::render_pagination( 'inventory', $result ); ?>
		</section>
		<?php
	}

	private static function render_prices() {
		$result = self::catalogue_query( 40, false );
		$rows = $result['rows'];
		$sale_margin = (float) get_option( 'taxocommerce_suppliers_sale_margin', 20 );
		$regular_margin = (float) get_option( 'taxocommerce_suppliers_regular_margin', 45 );
		?>
		<section class="tcsup-card">
			<h2><?php esc_html_e( 'Price control', 'taxocommerce-suppliers' ); ?></h2>
			<p><a class="button" href="<?php echo esc_url( self::tab_url( 'history' ) ); ?>"><?php esc_html_e( 'Price history', 'taxocommerce-suppliers' ); ?></a></p>
			<p><?php esc_html_e( 'Supplier cost remains separate from the WooCommerce selling price. You can correct imported supplier prices without re-importing a CSV.', 'taxocommerce-suppliers' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="tcsup-settings">
				<input type="hidden" name="action" value="tcsup_settings">
				<?php wp_nonce_field( 'tcsup_settings' ); ?>
				<label><?php esc_html_e( 'Default sale margin %', 'taxocommerce-suppliers' ); ?> <input type="number" step="0.01" name="sale_margin" value="<?php echo esc_attr( $sale_margin ); ?>"></label>
				<label><?php esc_html_e( 'Default regular margin %', 'taxocommerce-suppliers' ); ?> <input type="number" step="0.01" name="regular_margin" value="<?php echo esc_attr( $regular_margin ); ?>"></label>
				<button class="button"><?php esc_html_e( 'Save margins', 'taxocommerce-suppliers' ); ?></button>
			</form>
			<?php self::filter_bar( 'prices', true ); ?>
			<div class="tcsup-price-list">
			<?php foreach ( $rows as $row ) : ?>
				<?php
				$product = ! empty( $row['object_id'] ) && function_exists( 'wc_get_product' ) ? wc_get_product( absint( $row['object_id'] ) ) : false;
				?>
				<form class="tcsup-price-row" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="tcsup_price_update">
					<input type="hidden" name="row_id" value="<?php echo esc_attr( $row['id'] ); ?>">
					<?php wp_nonce_field( 'tcsup_price_update' ); ?>
					<div><strong><?php echo esc_html( $row['nombre'] ); ?></strong><br><small><?php echo esc_html( $row['proveedor'] . ' · ' . $row['sku'] ); ?></small></div>
					<label><?php esc_html_e( 'Excl. tax', 'taxocommerce-suppliers' ); ?> <input name="precio_sin_iva" value="<?php echo esc_attr( $row['precio_sin_iva'] ); ?>"></label>
					<label><?php esc_html_e( 'Incl. tax', 'taxocommerce-suppliers' ); ?> <input name="precio_con_iva" value="<?php echo esc_attr( $row['precio_con_iva'] ); ?>"></label>
					<label><?php esc_html_e( 'Tax %', 'taxocommerce-suppliers' ); ?> <input name="iva_porcentaje" value="<?php echo esc_attr( $row['iva_porcentaje'] ); ?>"></label>
					<label><?php esc_html_e( 'Currency', 'taxocommerce-suppliers' ); ?> <input name="moneda" value="<?php echo esc_attr( $row['moneda'] ); ?>"></label>
					<label><?php esc_html_e( 'Woo regular', 'taxocommerce-suppliers' ); ?> <input name="woo_regular" value="<?php echo esc_attr( $product ? $product->get_regular_price() : '' ); ?>" <?php disabled( ! $product ); ?>></label>
					<label><?php esc_html_e( 'Woo sale', 'taxocommerce-suppliers' ); ?> <input name="woo_sale" value="<?php echo esc_attr( $product ? $product->get_sale_price() : '' ); ?>" <?php disabled( ! $product ); ?>></label>
					<label class="tcsup-check"><input type="checkbox" name="apply_woo" value="1" <?php disabled( ! $product ); ?>> <?php esc_html_e( 'Apply Woo prices', 'taxocommerce-suppliers' ); ?></label>
					<button class="button button-primary"><?php esc_html_e( 'Save', 'taxocommerce-suppliers' ); ?></button>
				</form>
			<?php endforeach; ?>
			</div>
			<?php self::render_pagination( 'prices', $result ); ?>
		</section>
		<?php
	}

	private static function render_images() {
		$result = self::catalogue_query( 50, false );
		$rows = $result['rows'];
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only row selector.
		$selected_id = isset( $_GET['row_id'] ) ? absint( $_GET['row_id'] ) : 0;
		$selected = $selected_id ? TCSUP_Products::row( $selected_id ) : array();
		?>
		<section class="tcsup-card">
			<h2><?php esc_html_e( 'External Images', 'taxocommerce-suppliers' ); ?></h2>
			<p><?php esc_html_e( 'External image URLs are stored as references in', 'taxocommerce-suppliers' ); ?> <code>seo_supplier_images</code>. <?php esc_html_e( 'They are not downloaded into WordPress Media.', 'taxocommerce-suppliers' ); ?></p>
			<?php self::filter_bar( 'images', true ); ?>
			<?php if ( $selected ) : ?>
				<?php $urls = TCSUP_CSV::image_urls( $selected['imagenes'] ?? '' ); ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="tcsup-image-editor">
					<input type="hidden" name="action" value="tcsup_images_update">
					<input type="hidden" name="row_id" value="<?php echo esc_attr( $selected_id ); ?>">
					<?php wp_nonce_field( 'tcsup_images_update' ); ?>
					<h3><?php echo esc_html( $selected['nombre'] ); ?></h3>
					<p><strong><?php echo esc_html( $selected['proveedor'] ); ?></strong> · <code><?php echo esc_html( $selected['sku'] ); ?></code> · Woo ID <?php echo esc_html( $selected['object_id'] ?: '—' ); ?></p>
					<label><strong><?php esc_html_e( 'Image mode', 'taxocommerce-suppliers' ); ?></strong>
						<select name="image_mode">
							<option value="external" <?php selected( $selected['modo_imagenes'], 'external' ); ?>><?php esc_html_e( 'External references', 'taxocommerce-suppliers' ); ?></option>
							<option value="local" <?php selected( $selected['modo_imagenes'], 'local' ); ?>><?php esc_html_e( 'Local Media', 'taxocommerce-suppliers' ); ?></option>
							<option value="inherit" <?php selected( $selected['modo_imagenes'], 'inherit' ); ?>><?php esc_html_e( 'Inherit existing behavior', 'taxocommerce-suppliers' ); ?></option>
						</select>
					</label>
					<label><strong><?php esc_html_e( 'External image URLs — one per line', 'taxocommerce-suppliers' ); ?></strong>
						<textarea name="image_urls" rows="12"><?php echo esc_textarea( implode( "\n", $urls ) ); ?></textarea>
					</label>
					<?php submit_button( __( 'Save external images', 'taxocommerce-suppliers' ), 'primary', 'submit', false ); ?>
				</form>
			<?php endif; ?>
			<div class="tcsup-scroll"><table class="widefat striped">
				<thead><tr><th><?php esc_html_e( 'Supplier', 'taxocommerce-suppliers' ); ?></th><th><?php esc_html_e( 'Product', 'taxocommerce-suppliers' ); ?></th><th><?php esc_html_e( 'Woo ID', 'taxocommerce-suppliers' ); ?></th><th><?php esc_html_e( 'Mode', 'taxocommerce-suppliers' ); ?></th><th><?php esc_html_e( 'URLs', 'taxocommerce-suppliers' ); ?></th><th></th></tr></thead>
				<tbody>
				<?php foreach ( $rows as $row ) : ?>
					<tr>
						<td><?php echo esc_html( $row['proveedor'] ); ?><br><code><?php echo esc_html( $row['sku'] ); ?></code></td>
						<td><?php echo esc_html( $row['nombre'] ); ?></td>
						<td><?php echo esc_html( $row['object_id'] ?: '—' ); ?></td>
						<td><?php echo esc_html( $row['modo_imagenes'] ); ?></td>
						<td><?php echo esc_html( count( TCSUP_CSV::image_urls( $row['imagenes'] ?? '' ) ) ); ?></td>
						<td><a class="button button-small" href="<?php echo esc_url( self::tab_url( 'images', array( 'row_id' => absint( $row['id'] ) ) ) ); ?>"><?php esc_html_e( 'Edit', 'taxocommerce-suppliers' ); ?></a></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table></div>
			<?php self::render_pagination( 'images', $result ); ?>
		</section>
		<?php
	}

	public static function handle_settings() {
		self::guard();
		check_admin_referer( 'tcsup_settings' );
		$sale = isset( $_POST['sale_margin'] ) ? (float) sanitize_text_field( wp_unslash( $_POST['sale_margin'] ) ) : 20;
		$regular = isset( $_POST['regular_margin'] ) ? (float) sanitize_text_field( wp_unslash( $_POST['regular_margin'] ) ) : 45;
		$sale = max( -99, min( 1000, $sale ) );
		$regular = max( $sale, min( 1000, $regular ) );
		update_option( 'taxocommerce_suppliers_sale_margin', $sale, false );
		update_option( 'taxocommerce_suppliers_regular_margin', $regular, false );
		wp_safe_redirect( self::tab_url( 'prices', array( 'settings_saved' => 1 ) ) );
		exit;
	}
}
