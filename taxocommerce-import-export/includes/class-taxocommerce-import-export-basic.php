<?php
/**
 * Simple field-mapped CSV import/export for native WordPress and WooCommerce.
 *
 * Deliberately separate from advanced TaxoCommerce batch formats.
 *
 * @package TaxoCommerceImportExport
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TaxoCommerce_Import_Export_Basic {
	const TRANSIENT_PREFIX = 'tc_ie_basic_';
	const MAX_BYTES = 5242880;
	const STEP = 20;

	public function boot() {
		add_action( 'admin_post_tc_ie_basic_upload', array( $this, 'upload' ) );
		add_action( 'admin_post_tc_ie_basic_export', array( $this, 'export' ) );
		add_action( 'wp_ajax_tc_ie_basic_step', array( $this, 'step' ) );
	}

	private function guard() {
		if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'No tienes permisos para importar o exportar.', 'taxocommerce-import-export' ) );
		}
	}

	private static function types() {
		return array(
			'product' => __( 'Productos WooCommerce', 'taxocommerce-import-export' ),
			'product_cat' => __( 'Categorías de producto', 'taxocommerce-import-export' ),
			'category' => __( 'Categorías de entradas WordPress', 'taxocommerce-import-export' ),
			'page' => __( 'Páginas WordPress', 'taxocommerce-import-export' ),
			'post' => __( 'Entradas WordPress', 'taxocommerce-import-export' ),
		);
	}

	private static function fields( $type ) {
		if ( 'product' === $type ) {
			return array(
				'id' => 'ID',
				'sku' => 'SKU',
				'name' => __( 'Nombre', 'taxocommerce-import-export' ),
				'slug' => 'Slug',
				'status' => __( 'Estado', 'taxocommerce-import-export' ),
				'regular_price' => __( 'Precio normal', 'taxocommerce-import-export' ),
				'sale_price' => __( 'Precio rebajado', 'taxocommerce-import-export' ),
				'stock_quantity' => __( 'Cantidad en stock', 'taxocommerce-import-export' ),
				'stock_status' => __( 'Estado de existencias', 'taxocommerce-import-export' ),
				'manage_stock' => __( 'Gestionar existencias (sí/no)', 'taxocommerce-import-export' ),
				'description' => __( 'Descripción', 'taxocommerce-import-export' ),
				'short_description' => __( 'Descripción corta', 'taxocommerce-import-export' ),
				'categories' => __( 'Categorías (slugs separados por |)', 'taxocommerce-import-export' ),
				'tags' => __( 'Etiquetas (slugs separados por |)', 'taxocommerce-import-export' ),
				'featured_image' => __( 'Imagen destacada (URL de Media)', 'taxocommerce-import-export' ),
				'weight' => __( 'Peso', 'taxocommerce-import-export' ),
				'length' => __( 'Longitud', 'taxocommerce-import-export' ),
				'width' => __( 'Anchura', 'taxocommerce-import-export' ),
				'height' => __( 'Altura', 'taxocommerce-import-export' ),
				'catalog_visibility' => __( 'Visibilidad en el catálogo', 'taxocommerce-import-export' ),
				'featured' => __( 'Destacado (sí/no)', 'taxocommerce-import-export' ),
				'virtual' => __( 'Virtual (sí/no)', 'taxocommerce-import-export' ),
				'sold_individually' => __( 'Vender individualmente (sí/no)', 'taxocommerce-import-export' ),
			);
		}
		if ( in_array( $type, array( 'category', 'product_cat' ), true ) ) {
			return array(
				'id' => 'ID',
				'name' => __( 'Nombre', 'taxocommerce-import-export' ),
				'slug' => 'Slug',
				'description' => __( 'Descripción', 'taxocommerce-import-export' ),
				'parent_slug' => __( 'Slug categoría padre', 'taxocommerce-import-export' ),
			);
		}
		$fields = array(
			'id' => 'ID',
			'title' => __( 'Título', 'taxocommerce-import-export' ),
			'slug' => 'Slug',
			'status' => __( 'Estado', 'taxocommerce-import-export' ),
			'excerpt' => __( 'Extracto', 'taxocommerce-import-export' ),
			'content' => __( 'Contenido', 'taxocommerce-import-export' ),
			'date' => __( 'Fecha de publicación', 'taxocommerce-import-export' ),
			'author' => __( 'Autor (usuario WordPress)', 'taxocommerce-import-export' ),
			'featured_image' => __( 'Imagen destacada (URL de Media)', 'taxocommerce-import-export' ),
			'menu_order' => __( 'Orden de menú', 'taxocommerce-import-export' ),
			'comment_status' => __( 'Comentarios (open/closed)', 'taxocommerce-import-export' ),
		);
		if ( 'post' === $type ) {
			$fields['categories'] = __( 'Categorías (slugs separados por |)', 'taxocommerce-import-export' );
			$fields['tags'] = __( 'Etiquetas (slugs separados por |)', 'taxocommerce-import-export' );
		} else {
			$fields['parent_slug'] = __( 'Slug página padre', 'taxocommerce-import-export' );
		}
		return $fields;
	}

	private function redirect( $args ) {
		wp_safe_redirect( add_query_arg( array_merge( array( 'page' => 'taxocommerce-import-export', 'tc_ie_tab' => 'individual' ), $args ), admin_url( 'admin.php' ) ) );
		exit;
	}

	private function directory() {
		$uploads = wp_upload_dir( null, false );
		if ( ! empty( $uploads['error'] ) || empty( $uploads['basedir'] ) ) {
			return '';
		}
		$path = trailingslashit( $uploads['basedir'] ) . 'taxocommerce-import-export/basic';
		if ( ! wp_mkdir_p( $path ) ) {
			return '';
		}
		if ( ! file_exists( $path . '/index.php' ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Protect a private, nonpublic CSV work directory.
			file_put_contents( $path . '/index.php', '<?php // Silence is golden.' );
		}
		if ( ! file_exists( $path . '/.htaccess' ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Apache protection; filenames also random and never disclosed publicly.
			file_put_contents( $path . '/.htaccess', "Require all denied\nDeny from all\n" );
		}
		return $path;
	}

	private function transient_key( $token ) {
		return self::TRANSIENT_PREFIX . get_current_user_id() . '_' . sanitize_key( $token );
	}

	private function load_state( $token ) {
		$state = get_transient( $this->transient_key( $token ) );
		return is_array( $state ) ? $state : array();
	}

	private function store_state( $token, $state ) {
		set_transient( $this->transient_key( $token ), $state, HOUR_IN_SECONDS );
	}

	private function delimiter( $line ) {
		$counts = array(
			',' => count( str_getcsv( $line, ',' ) ),
			';' => count( str_getcsv( $line, ';' ) ),
			"\t" => count( str_getcsv( $line, "\t" ) ),
		);
		arsort( $counts, SORT_NUMERIC );
		return (string) key( $counts );
	}

	private function csv_row( $handle, $delimiter ) {
		return fgetcsv( $handle, 0, $delimiter, '"', '' );
	}

	public function upload() {
		$this->guard();
		check_admin_referer( 'tc_ie_basic_upload' );
		$file = isset( $_FILES['csv_file'] ) && is_array( $_FILES['csv_file'] ) ? $_FILES['csv_file'] : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Uploaded file validated below.
		$name = isset( $file['name'] ) && is_scalar( $file['name'] ) ? sanitize_file_name( wp_unslash( (string) $file['name'] ) ) : '';
		$size = absint( $file['size'] ?? 0 );
		if ( UPLOAD_ERR_OK !== (int) ( $file['error'] ?? UPLOAD_ERR_NO_FILE ) || 'csv' !== strtolower( pathinfo( $name, PATHINFO_EXTENSION ) ) || 0 === $size || $size > self::MAX_BYTES || ! is_uploaded_file( (string) ( $file['tmp_name'] ?? '' ) ) ) {
			$this->redirect( array( 'tc_ie_basic_error' => 'invalid_upload' ) );
		}
		$dir = $this->directory();
		if ( '' === $dir ) {
			$this->redirect( array( 'tc_ie_basic_error' => 'storage' ) );
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		$uploaded = wp_handle_upload( $file, array( 'test_form' => false ) );
		if ( ! is_array( $uploaded ) || ! empty( $uploaded['error'] ) || empty( $uploaded['file'] ) ) {
			$this->redirect( array( 'tc_ie_basic_error' => 'storage' ) );
		}
		$token = strtolower( wp_generate_password( 24, false, false ) );
		$path = $dir . '/' . $token . '.csv';
		// phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename -- Move a validated upload to a protected private directory.
		if ( ! rename( $uploaded['file'], $path ) ) {
			wp_delete_file( $uploaded['file'] );
			$this->redirect( array( 'tc_ie_basic_error' => 'storage' ) );
		}
		$handle = fopen( $path, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Stream bounded uploaded CSV.
		if ( ! $handle ) {
			wp_delete_file( $path );
			$this->redirect( array( 'tc_ie_basic_error' => 'csv' ) );
		}
		$first = fgets( $handle );
		rewind( $handle );
		$delimiter = $this->delimiter( (string) $first );
		$headers = $this->csv_row( $handle, $delimiter );
		if ( ! is_array( $headers ) || count( $headers ) > 100 || count( $headers ) < 1 ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Close the bounded private CSV input stream.
			fclose( $handle );
			wp_delete_file( $path );
			$this->redirect( array( 'tc_ie_basic_error' => 'csv' ) );
		}
		$headers[0] = preg_replace( '/^\xEF\xBB\xBF/', '', $headers[0] );
		$sample = array();
		for ( $i = 0; $i < 3 && false !== ( $row = $this->csv_row( $handle, $delimiter ) ); $i++ ) {
			if ( is_array( $row ) ) {
				$sample[] = array_slice( $row, 0, count( $headers ) );
			}
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Close the bounded private CSV input stream.
		fclose( $handle );
		$this->store_state( $token, array(
			'path' => $path, 'filename' => $name, 'headers' => $headers, 'sample' => $sample,
			'delimiter' => $delimiter, 'offset' => 0, 'processed' => 0, 'saved' => 0,
			'errors' => 0, 'notices' => array(), 'done' => false, 'started' => false,
		) );
		$this->redirect( array( 'tc_ie_basic_token' => $token ) );
	}

	public function render() {
		$this->guard();
		$token = isset( $_GET['tc_ie_basic_token'] ) ? sanitize_key( wp_unslash( $_GET['tc_ie_basic_token'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only view of current user's private transient.
		$state = $token ? $this->load_state( $token ) : array();
		$types = self::types();
		$nonce = wp_create_nonce( 'tc_ie_basic_step' );
		$error = isset( $_GET['tc_ie_basic_error'] ) ? sanitize_key( wp_unslash( $_GET['tc_ie_basic_error'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only error.
		?>
		<h2><?php esc_html_e( 'Importación y exportación básica de WordPress y WooCommerce', 'taxocommerce-import-export' ); ?></h2>
		<p><?php esc_html_e( 'Utiliza campos nativos sin necesitar columnas ni módulos de TaxoCommerce. Las importaciones avanzadas siguen disponibles en Importación por lotes.', 'taxocommerce-import-export' ); ?></p>
		<?php if ( $error ) : ?>
			<div class="notice notice-error"><p><?php esc_html_e( 'No se ha podido leer o guardar el CSV. Utiliza un archivo CSV válido de hasta 5 MB.', 'taxocommerce-import-export' ); ?></p></div>
		<?php endif; ?>
		<div class="card" style="max-width:none;padding:20px;">
			<h3><?php esc_html_e( 'Exportar CSV seleccionando campos', 'taxocommerce-import-export' ); ?></h3>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="tc_ie_basic_export">
				<?php wp_nonce_field( 'tc_ie_basic_export' ); ?>
				<p><label for="tc-ie-basic-export-type"><?php esc_html_e( 'Tipo de contenido', 'taxocommerce-import-export' ); ?></label>
					<select name="content_type" id="tc-ie-basic-export-type">
						<?php foreach ( $types as $type => $label ) : ?>
							<option value="<?php echo esc_attr( $type ); ?>"><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select></p>
				<?php foreach ( $types as $type => $label ) : ?>
				<fieldset class="tc-ie-basic-fields" data-type="<?php echo esc_attr( $type ); ?>" style="display:none;margin:12px 0;">
					<legend><strong><?php esc_html_e( 'Campos a exportar', 'taxocommerce-import-export' ); ?></strong></legend>
					<?php foreach ( self::fields( $type ) as $name => $field_label ) : ?>
						<label style="display:inline-block;margin:6px 15px 6px 0;"><input type="checkbox" name="fields[<?php echo esc_attr( $type ); ?>][]" value="<?php echo esc_attr( $name ); ?>" checked> <?php echo esc_html( $field_label ); ?></label>
					<?php endforeach; ?>
				</fieldset>
				<?php endforeach; ?>
				<?php submit_button( __( 'Descargar CSV básico', 'taxocommerce-import-export' ), 'secondary', 'submit', false ); ?>
			</form>
		</div>
		<div class="card" style="max-width:none;padding:20px;margin-top:18px;">
			<h3><?php esc_html_e( 'Importar CSV asignando columnas', 'taxocommerce-import-export' ); ?></h3>
			<?php if ( ! $state ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
					<input type="hidden" name="action" value="tc_ie_basic_upload">
					<?php wp_nonce_field( 'tc_ie_basic_upload' ); ?>
					<p><input type="file" name="csv_file" accept=".csv,text/csv" required></p>
					<?php submit_button( __( 'Leer columnas CSV', 'taxocommerce-import-export' ), 'primary', 'submit', false ); ?>
				</form>
			<?php else : ?>
				<p><strong><?php echo esc_html( $state['filename'] ); ?></strong></p>
				<form id="tc-ie-basic-map-form">
					<input type="hidden" name="token" value="<?php echo esc_attr( $token ); ?>">
					<p><label for="tc-ie-basic-map-type"><?php esc_html_e( 'Importar como', 'taxocommerce-import-export' ); ?></label>
						<select name="content_type" id="tc-ie-basic-map-type">
							<?php foreach ( $types as $type => $label ) : ?><option value="<?php echo esc_attr( $type ); ?>"><?php echo esc_html( $label ); ?></option><?php endforeach; ?>
						</select></p>
					<p><label for="tc-ie-basic-mode"><?php esc_html_e( 'Acción', 'taxocommerce-import-export' ); ?></label>
						<select name="mode" id="tc-ie-basic-mode">
							<option value="update"><?php esc_html_e( 'Actualizar solo registros existentes', 'taxocommerce-import-export' ); ?></option>
							<option value="create"><?php esc_html_e( 'Actualizar y crear los que falten', 'taxocommerce-import-export' ); ?></option>
						</select></p>
					<p class="description"><?php esc_html_e( 'La identidad se busca por SKU (productos), slug o ID. Los campos sin asignar y las celdas vacías se conservan. Las altas se crean como borrador salvo estado explícito. Categorías y etiquetas se relacionan con términos existentes mediante slugs separados por |. Las imágenes destacadas deben estar ya en la biblioteca Media; no se descargan archivos externos.', 'taxocommerce-import-export' ); ?></p>
					<table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Columna del CSV', 'taxocommerce-import-export' ); ?></th><th><?php esc_html_e( 'Campo de destino', 'taxocommerce-import-export' ); ?></th><th><?php esc_html_e( 'Ejemplo', 'taxocommerce-import-export' ); ?></th></tr></thead><tbody>
					<?php foreach ( $state['headers'] as $i => $header ) : ?>
						<tr><td><?php echo esc_html( $header ); ?></td><td>
							<select class="tc-ie-basic-map" name="mapping[<?php echo esc_attr( (string) $i ); ?>]" data-header="<?php echo esc_attr( sanitize_key( $header ) ); ?>">
								<option value=""><?php esc_html_e( 'No importar', 'taxocommerce-import-export' ); ?></option>
							</select>
						</td><td><?php echo esc_html( (string) ( $state['sample'][0][ $i ] ?? '' ) ); ?></td></tr>
					<?php endforeach; ?>
					</tbody></table>
					<p><?php submit_button( __( 'Iniciar importación', 'taxocommerce-import-export' ), 'primary', 'submit', false ); ?></p>
				</form>
				<p id="tc-ie-basic-progress" role="status" aria-live="polite"></p>
				<pre id="tc-ie-basic-errors" style="white-space:pre-wrap;max-height:160px;overflow:auto;"></pre>
			<?php endif; ?>
		</div>
		<script>
		(function(){
			var exportSelect=document.getElementById('tc-ie-basic-export-type');
			if(exportSelect){var showExport=function(){document.querySelectorAll('.tc-ie-basic-fields').forEach(function(x){x.style.display=x.dataset.type===exportSelect.value?'block':'none';});};exportSelect.addEventListener('change',showExport);showExport();}
			var form=document.getElementById('tc-ie-basic-map-form');
			if(!form)return;
			var typeSelect=document.getElementById('tc-ie-basic-map-type');
			var fields=<?php echo wp_json_encode( array_map( array( __CLASS__, 'fields' ), array_keys( $types ) ) ); ?>;
			var keys=<?php echo wp_json_encode( array_keys( $types ) ); ?>;
			var byType={};keys.forEach(function(k,i){byType[k]=fields[i];});
			var alias={product:{product_id:'id',product_name:'name',nombre:'name',estado:'status',published:'status',precio:'regular_price',precio_normal:'regular_price',precio_rebajado:'sale_price',regularprice:'regular_price',saleprice:'sale_price',shortdescription:'short_description',stock:'stock_quantity',instock:'stock_status',weightkg:'weight',lengthcm:'length',widthcm:'width',heightcm:'height',images:'featured_image',imagen:'featured_image',categorias:'categories',etiquetas:'tags'},page:{post_id:'id',post_title:'title',post_content:'content',titulo:'title',estado:'status',contenido:'content',fecha:'date',imagen:'featured_image'},post:{post_id:'id',post_title:'title',post_content:'content',titulo:'title',estado:'status',contenido:'content',fecha:'date',categorias:'categories',etiquetas:'tags',imagen:'featured_image'},category:{nombre:'name',categoria:'name'},product_cat:{nombre:'name',categoria:'name'}};
			function updateFields(){
				var type=typeSelect.value, available=byType[type]||{};
				document.querySelectorAll('.tc-ie-basic-map').forEach(function(el){
					var current=el.value, suggested=alias[type]&&alias[type][el.dataset.header]||el.dataset.header;
					el.replaceChildren();
					var blank=document.createElement('option');blank.value='';blank.textContent='<?php echo esc_js( __( 'No importar', 'taxocommerce-import-export' ) ); ?>';el.appendChild(blank);
					Object.keys(available).forEach(function(key){var op=document.createElement('option');op.value=key;op.textContent=available[key];el.appendChild(op);});
					el.value=Object.prototype.hasOwnProperty.call(available,current)?current:(Object.prototype.hasOwnProperty.call(available,suggested)?suggested:'');
				});
			}
			typeSelect.addEventListener('change',updateFields);updateFields();
			var progress=document.getElementById('tc-ie-basic-progress'), errors=document.getElementById('tc-ie-basic-errors');
			var endpoint='<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>', nonce='<?php echo esc_js( $nonce ); ?>';
			form.addEventListener('submit',function(event){
				event.preventDefault();
				var payload=new FormData(form), used={},duplicate=false;
				form.querySelectorAll('.tc-ie-basic-map').forEach(function(sel){if(sel.value){if(used[sel.value])duplicate=true;used[sel.value]=true;}});
				if(duplicate){progress.textContent='<?php echo esc_js( __( 'Dos columnas apuntan al mismo campo. Corrige el mapeo.', 'taxocommerce-import-export' ) ); ?>';return;}
				form.querySelectorAll('button,input,select').forEach(function(el){el.disabled=true;});
				payload.append('action','tc_ie_basic_step');payload.append('nonce',nonce);
				progress.textContent='<?php echo esc_js( __( 'Importando por bloques…', 'taxocommerce-import-export' ) ); ?>';
				function send(){
					fetch(endpoint,{method:'POST',body:payload,credentials:'same-origin'}).then(function(r){return r.json();}).then(function(o){
						if(!o.success)throw new Error(o.data&&o.data.message||'Error de importación');
						var s=o.data;progress.textContent=s.processed+' filas · '+s.saved+' guardadas · '+s.errors+' errores';
						errors.textContent=(s.notices||[]).join('\n');
						if(!s.done){payload=new FormData();payload.append('action','tc_ie_basic_step');payload.append('nonce',nonce);payload.append('token',form.querySelector('[name="token"]').value);send();}
						else{progress.textContent+=' · <?php echo esc_js( __( 'Importación finalizada', 'taxocommerce-import-export' ) ); ?>';}
					}).catch(function(e){progress.textContent=e.message;form.querySelectorAll('button,input,select').forEach(function(el){el.disabled=false;});});
				}send();
			});
		})();
		</script>
		<?php
	}

	private function safe_csv( $value ) {
		$value = (string) $value;
		return preg_match( '/^[=+@\t\r]/u', $value ) ? "'" . $value : $value;
	}

	/**
	 * Export a list of existing native term slugs, never TaxoCommerce taxonomy IDs.
	 *
	 * @param int    $object_id WordPress object ID.
	 * @param string $taxonomy Native taxonomy.
	 * @return string
	 */
	private function export_term_slugs( $object_id, $taxonomy ) {
		$terms = wp_get_object_terms( $object_id, $taxonomy, array( 'fields' => 'slugs' ) );
		return is_wp_error( $terms ) ? '' : implode( '|', (array) $terms );
	}

	/**
	 * Return one natively stored field value.
	 *
	 * @param string $type Type of record.
	 * @param int    $id Record ID.
	 * @param string $field Selected field.
	 * @return string
	 */
	private function export_value( $type, $id, $field ) {
		if ( 'product' === $type ) {
			$product = wc_get_product( $id );
			if ( ! $product ) {
				return '';
			}
			if ( 'categories' === $field || 'tags' === $field ) {
				return $this->export_term_slugs( $id, 'categories' === $field ? 'product_cat' : 'product_tag' );
			}
			if ( 'featured_image' === $field ) {
				return $product->get_image_id() ? (string) wp_get_attachment_url( $product->get_image_id() ) : '';
			}
			$methods = array(
				'id' => 'get_id', 'sku' => 'get_sku', 'name' => 'get_name',
				'slug' => 'get_slug', 'status' => 'get_status',
				'regular_price' => 'get_regular_price', 'sale_price' => 'get_sale_price',
				'stock_quantity' => 'get_stock_quantity', 'stock_status' => 'get_stock_status',
				'manage_stock' => 'get_manage_stock',
				'description' => 'get_description', 'short_description' => 'get_short_description',
				'weight' => 'get_weight', 'length' => 'get_length', 'width' => 'get_width',
				'height' => 'get_height', 'catalog_visibility' => 'get_catalog_visibility',
				'featured' => 'get_featured', 'virtual' => 'get_virtual',
				'sold_individually' => 'get_sold_individually',
			);
			$method = $methods[ $field ] ?? '';
			if ( ! $method ) {
				return '';
			}
			$value = $product->$method();
			return is_bool( $value ) ? ( $value ? 'yes' : 'no' ) : (string) $value;
		}
		if ( in_array( $type, array( 'category', 'product_cat' ), true ) ) {
			$term = get_term( $id, $type );
			if ( ! $term || is_wp_error( $term ) ) {
				return '';
			}
			if ( 'parent_slug' === $field ) {
				$parent = $term->parent ? get_term( $term->parent, $type ) : null;
				return $parent && ! is_wp_error( $parent ) ? $parent->slug : '';
			}
			return (string) ( $term->$field ?? '' );
		}
		$post = get_post( $id );
		if ( ! $post ) {
			return '';
		}
		if ( 'categories' === $field || 'tags' === $field ) {
			return $this->export_term_slugs( $id, 'categories' === $field ? 'category' : 'post_tag' );
		}
		if ( 'featured_image' === $field ) {
			$image_id = get_post_thumbnail_id( $id );
			return $image_id ? (string) wp_get_attachment_url( $image_id ) : '';
		}
		if ( 'author' === $field ) {
			$author = get_userdata( (int) $post->post_author );
			return $author ? $author->user_login : '';
		}
		if ( 'parent_slug' === $field ) {
			$parent = $post->post_parent ? get_post( $post->post_parent ) : null;
			return $parent ? $parent->post_name : '';
		}
		$key = array(
			'id' => 'ID', 'title' => 'post_title', 'slug' => 'post_name',
			'status' => 'post_status', 'excerpt' => 'post_excerpt',
			'content' => 'post_content', 'date' => 'post_date',
			'menu_order' => 'menu_order', 'comment_status' => 'comment_status',
		)[ $field ] ?? '';
		return $key ? (string) $post->$key : '';
	}

	public function export() {
		$this->guard();
		check_admin_referer( 'tc_ie_basic_export' );
		$type = sanitize_key( wp_unslash( $_POST['content_type'] ?? '' ) );
		$allowed = self::fields( $type );
		if ( ! isset( self::types()[ $type ] ) || ! $allowed ) {
			wp_die( esc_html__( 'Tipo no válido.', 'taxocommerce-import-export' ) );
		}
		$cap = array( 'product' => 'edit_products', 'product_cat' => 'manage_product_terms',
			'category' => 'manage_categories', 'page' => 'edit_pages', 'post' => 'edit_posts' )[ $type ];
		if ( ! current_user_can( $cap ) ) {
			wp_die( esc_html__( 'Sin permiso para exportar este tipo de contenido.', 'taxocommerce-import-export' ) );
		}
		$requested = isset( $_POST['fields'][ $type ] ) ? (array) wp_unslash( $_POST['fields'][ $type ] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Whitelist against fields.
		$selected = array_values( array_intersect( array_keys( $allowed ), array_map( 'sanitize_key', $requested ) ) );
		if ( ! $selected ) {
			wp_die( esc_html__( 'Selecciona al menos un campo.', 'taxocommerce-import-export' ) );
		}
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="wordpress-' . $type . '-' . gmdate( 'Ymd' ) . '.csv"' );
		header( 'X-Content-Type-Options: nosniff' );
		$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- CSV stream to authorised browser.
		fwrite( $out, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- UTF-8 BOM for spreadsheet clients.
		fputcsv( $out, $selected, ',', '"', '' );
		$page = 1;
		do {
			if ( in_array( $type, array( 'category', 'product_cat' ), true ) ) {
				$ids = get_terms( array( 'taxonomy' => $type, 'hide_empty' => false,
					'fields' => 'ids', 'number' => 200, 'offset' => ( $page - 1 ) * 200 ) );
			} else {
				$post_type = 'product' === $type ? 'product' : $type;
				$q = new WP_Query( array( 'post_type' => $post_type, 'post_status' => array( 'publish', 'draft', 'pending', 'private', 'future' ),
					'posts_per_page' => 200, 'paged' => $page, 'fields' => 'ids', 'orderby' => 'ID', 'order' => 'ASC',
					'no_found_rows' => true ) );
				$ids = $q->posts;
			}
			if ( is_wp_error( $ids ) ) break;
			foreach ( $ids as $id ) {
				if ( ! in_array( $type, array( 'category', 'product_cat' ), true ) && ! current_user_can( 'edit_post', $id ) ) continue;
				$row = array();
				foreach ( $selected as $field ) $row[] = $this->safe_csv( $this->export_value( $type, $id, $field ) );
				fputcsv( $out, $row, ',', '"', '' );
			}
			$page++;
		} while ( count( $ids ) === 200 && $page < 1001 );
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- End CSV stream.
		exit;
	}

	private function mapped_row( $headers, $row, $map ) {
		$data = array();
		foreach ( $map as $i => $field ) {
			if ( '' === $field || ! isset( $headers[ $i ] ) ) continue;
			$value = isset( $row[ $i ] ) ? trim( (string) $row[ $i ] ) : '';
			if ( '' !== $value ) $data[ $field ] = $value;
		}
		return $data;
	}

	/**
	 * Resolve a delimited list of existing native terms without creating categories.
	 *
	 * @param string $value CSV value, slugs/names separated by | or comma.
	 * @param string $taxonomy WordPress taxonomy.
	 * @return array|WP_Error Term IDs or failure.
	 */
	private function term_ids( $value, $taxonomy ) {
		$tax = get_taxonomy( $taxonomy );
		if ( ! $tax || ! current_user_can( $tax->cap->assign_terms ) ) {
			return new WP_Error( 'terms_forbidden', __( 'Sin permisos para asignar esta taxonomía.', 'taxocommerce-import-export' ) );
		}
		$ids = array();
		foreach ( preg_split( '/[|,]/u', (string) $value, -1, PREG_SPLIT_NO_EMPTY ) as $entry ) {
			$entry = trim( $entry );
			if ( '' === $entry ) {
				continue;
			}
			$term = get_term_by( 'slug', sanitize_title( $entry ), $taxonomy );
			if ( ! $term ) {
				$term = get_term_by( 'name', $entry, $taxonomy );
			}
			if ( ! $term ) {
				/* translators: %s: Name or slug of the term absent from WordPress. */
				return new WP_Error( 'term_missing', sprintf( __( 'La categoría o etiqueta «%s» no existe. Importa primero las categorías o crea la etiqueta.', 'taxocommerce-import-export' ), $entry ) );
			}
			$ids[] = (int) $term->term_id;
		}
		return array_values( array_unique( $ids ) );
	}

	/**
	 * Resolve an image already in WordPress Media (local ID or URL).
	 * External images are deliberately not fetched.
	 *
	 * @param string $value Attachment ID or URL.
	 * @return int|WP_Error
	 */
	private function media_image_id( $value ) {
		$value = trim( (string) $value );
		// WooCommerce CSV's Images column can contain multiple URLs.
		$value = trim( explode( ',', $value )[0] );
		$id = ctype_digit( $value ) ? absint( $value ) : attachment_url_to_postid( esc_url_raw( $value ) );
		if ( ! $id || ! wp_attachment_is_image( $id ) ) {
			return new WP_Error( 'image_missing', __( 'La imagen no está en la biblioteca Media. Importa el archivo en Media antes de relacionarlo.', 'taxocommerce-import-export' ) );
		}
		return $id;
	}

	/**
	 * Normalize native yes/no CSV fields and reject ambiguous values.
	 *
	 * @param string $value CSV value.
	 * @return bool|WP_Error
	 */
	private function csv_boolean( $value ) {
		$value = strtolower( remove_accents( trim( (string) $value ) ) );
		if ( in_array( $value, array( '1', 'yes', 'si', 'true', 'on' ), true ) ) {
			return true;
		}
		if ( in_array( $value, array( '0', 'no', 'false', 'off' ), true ) ) {
			return false;
		}
		return new WP_Error( 'invalid_boolean', __( 'El campo de sí/no debe contener yes/no, sí/no, true/false o 1/0.', 'taxocommerce-import-export' ) );
	}

	/**
	 * Resolve an existing WordPress user by login, email or local ID.
	 *
	 * @param string $value CSV author value.
	 * @return int|WP_Error
	 */
	private function author_id( $value ) {
		$value = trim( (string) $value );
		$user = ctype_digit( $value ) ? get_user_by( 'id', absint( $value ) ) : get_user_by( 'login', $value );
		if ( ! $user && is_email( $value ) ) {
			$user = get_user_by( 'email', $value );
		}
		return $user ? (int) $user->ID : new WP_Error( 'author_missing', __( 'El usuario autor no existe en WordPress.', 'taxocommerce-import-export' ) );
	}

	/**
	 * Resolve optional product/post taxonomies before saving the main record.
	 *
	 * @param string $type Content type.
	 * @param array  $data Mapped row.
	 * @return array|WP_Error Taxonomy => term IDs.
	 */
	private function mapped_terms( $type, $data ) {
		$taxonomies = 'product' === $type ? array( 'categories' => 'product_cat', 'tags' => 'product_tag' ) :
			( 'post' === $type ? array( 'categories' => 'category', 'tags' => 'post_tag' ) : array() );
		$resolved = array();
		foreach ( $taxonomies as $field => $taxonomy ) {
			if ( ! isset( $data[ $field ] ) ) {
				continue;
			}
			$ids = $this->term_ids( $data[ $field ], $taxonomy );
			if ( is_wp_error( $ids ) ) {
				return $ids;
			}
			$resolved[ $taxonomy ] = $ids;
		}
		return $resolved;
	}

	/**
	 * Save only explicitly mapped term relationships.
	 *
	 * @param int   $id Object ID.
	 * @param array $terms Taxonomy => term IDs.
	 * @return true|WP_Error
	 */
	private function save_terms( $id, $terms ) {
		foreach ( $terms as $taxonomy => $ids ) {
			$result = wp_set_object_terms( $id, $ids, $taxonomy, false );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}
		return true;
	}

	private function import_record( $type, $data, $mode ) {
		if ( ! $data ) return new WP_Error( 'empty', __( 'Fila sin campos con valores.', 'taxocommerce-import-export' ) );
		if ( isset( $data['status'] ) ) {
			$source_status = strtolower( trim( $data['status'] ) );
			$native_status = array( '1' => 'publish', '0' => 'draft', '-1' => 'private', 'yes' => 'publish', 'no' => 'draft' );
			$data['status'] = $native_status[ $source_status ] ?? $source_status;
		}
		if ( 'product' === $type && isset( $data['stock_status'] ) ) {
			$source_stock = strtolower( trim( $data['stock_status'] ) );
			$data['stock_status'] = array( '1' => 'instock', '0' => 'outofstock', 'yes' => 'instock', 'no' => 'outofstock' )[ $source_stock ] ?? $source_stock;
		}
		$cap = array( 'product' => 'edit_products', 'product_cat' => 'manage_product_terms',
			'category' => 'manage_categories', 'page' => 'edit_pages', 'post' => 'edit_posts' )[ $type ] ?? '';
		if ( ! $cap || ! current_user_can( $cap ) ) {
			return new WP_Error( 'forbidden', __( 'Sin permisos para modificar este tipo de contenido.', 'taxocommerce-import-export' ) );
		}
		if ( in_array( $type, array( 'category', 'product_cat' ), true ) ) {
			// Portable slug takes precedence over source-site numeric IDs.
			$term = ! empty( $data['slug'] ) ? get_term_by( 'slug', sanitize_title( $data['slug'] ), $type ) : null;
			if ( ! $term && empty( $data['slug'] ) && isset( $data['id'] ) ) $term = get_term( absint( $data['id'] ), $type );
			if ( is_wp_error( $term ) ) $term = null;
			if ( ! $term && 'update' === $mode ) return new WP_Error( 'not_found', __( 'Categoría no encontrada.', 'taxocommerce-import-export' ) );
			$args = array();
			if ( isset( $data['slug'] ) ) $args['slug'] = sanitize_title( $data['slug'] );
			if ( isset( $data['description'] ) ) $args['description'] = wp_kses_post( $data['description'] );
			if ( isset( $data['parent_slug'] ) ) {
				$parent = get_term_by( 'slug', sanitize_title( $data['parent_slug'] ), $type );
				if ( ! $parent ) return new WP_Error( 'parent_missing', __( 'La categoría padre no existe.', 'taxocommerce-import-export' ) );
				$args['parent'] = (int) $parent->term_id;
			}
			if ( $term ) {
				if ( isset( $data['name'] ) ) $args['name'] = sanitize_text_field( $data['name'] );
				return $args ? ! is_wp_error( wp_update_term( $term->term_id, $type, $args ) ) : true;
			}
			if ( empty( $data['name'] ) ) return new WP_Error( 'missing_name', __( 'Falta el nombre de la categoría.', 'taxocommerce-import-export' ) );
			return ! is_wp_error( wp_insert_term( sanitize_text_field( $data['name'] ), $type, $args ) );
		}
		if ( 'product' === $type ) {
			if ( ! function_exists( 'wc_get_product' ) ) return new WP_Error( 'woocommerce_missing', __( 'WooCommerce no está activo.', 'taxocommerce-import-export' ) );
			$terms = $this->mapped_terms( $type, $data );
			if ( is_wp_error( $terms ) ) return $terms;
			$image_id = null;
			if ( isset( $data['featured_image'] ) ) {
				$image_id = $this->media_image_id( $data['featured_image'] );
				if ( is_wp_error( $image_id ) ) return $image_id;
			}
			if ( isset( $data['status'] ) && ! in_array( $data['status'], array( 'publish', 'draft', 'pending', 'private', 'future' ), true ) ) {
				return new WP_Error( 'invalid_status', __( 'Estado de producto no válido.', 'taxocommerce-import-export' ) );
			}
			$id = 0;
			if ( ! empty( $data['sku'] ) ) $id = wc_get_product_id_by_sku( sanitize_text_field( $data['sku'] ) );
			if ( ! $id && ! empty( $data['slug'] ) ) {
				$found = get_page_by_path( sanitize_title( $data['slug'] ), OBJECT, 'product' );
				if ( $found ) $id = $found->ID;
			}
			if ( ! $id && empty( $data['sku'] ) && empty( $data['slug'] ) && isset( $data['id'] ) && 'product' === get_post_type( absint( $data['id'] ) ) ) $id = absint( $data['id'] );
			if ( ! $id && 'update' === $mode ) return new WP_Error( 'not_found', __( 'Producto no encontrado.', 'taxocommerce-import-export' ) );
			if ( ! $id && empty( $data['name'] ) ) return new WP_Error( 'missing_name', __( 'Falta el nombre del producto.', 'taxocommerce-import-export' ) );
			if ( $id && ! current_user_can( 'edit_post', $id ) ) return new WP_Error( 'forbidden', __( 'Sin permiso para editar el producto.', 'taxocommerce-import-export' ) );
			if ( isset( $data['status'] ) && in_array( $data['status'], array( 'publish', 'private', 'future' ), true ) && ! current_user_can( 'publish_products' ) ) return new WP_Error( 'forbidden', __( 'Sin permiso para publicar productos.', 'taxocommerce-import-export' ) );
			$p = $id ? wc_get_product( $id ) : new WC_Product_Simple();
			if ( ! $id && empty( $data['status'] ) ) $p->set_status( 'draft' );
			if ( ! $p ) return new WP_Error( 'invalid_product', __( 'Producto no válido.', 'taxocommerce-import-export' ) );
			$methods = array(
				'sku' => 'set_sku', 'name' => 'set_name', 'slug' => 'set_slug',
				'status' => 'set_status', 'description' => 'set_description',
				'short_description' => 'set_short_description',
				'regular_price' => 'set_regular_price', 'sale_price' => 'set_sale_price',
				'stock_status' => 'set_stock_status', 'manage_stock' => 'set_manage_stock',
				'weight' => 'set_weight', 'length' => 'set_length',
				'width' => 'set_width', 'height' => 'set_height',
				'catalog_visibility' => 'set_catalog_visibility',
				'featured' => 'set_featured', 'virtual' => 'set_virtual',
				'sold_individually' => 'set_sold_individually',
			);
			try {
				foreach ( $methods as $field => $method ) {
					if ( ! isset( $data[ $field ] ) ) continue;
					$value = $data[ $field ];
					if ( in_array( $field, array( 'description', 'short_description' ), true ) ) {
						$value = wp_kses_post( $value );
					} elseif ( in_array( $field, array( 'regular_price', 'sale_price', 'weight', 'length', 'width', 'height' ), true ) ) {
						$value = wc_format_decimal( $value );
						if ( ! is_numeric( $value ) || (float) $value < 0 ) {
							return new WP_Error( 'invalid_number', __( 'Precio, peso o dimensión no válidos.', 'taxocommerce-import-export' ) );
						}
					} elseif ( in_array( $field, array( 'manage_stock', 'featured', 'virtual', 'sold_individually' ), true ) ) {
						$value = $this->csv_boolean( $value );
						if ( is_wp_error( $value ) ) return $value;
					} elseif ( 'stock_status' === $field ) {
						if ( ! in_array( $value, array( 'instock', 'outofstock', 'onbackorder' ), true ) ) {
							return new WP_Error( 'invalid_stock_status', __( 'Estado de existencias no válido.', 'taxocommerce-import-export' ) );
						}
					} elseif ( 'catalog_visibility' === $field ) {
						if ( ! in_array( $value, array( 'visible', 'catalog', 'search', 'hidden' ), true ) ) {
							return new WP_Error( 'invalid_visibility', __( 'Visibilidad del catálogo no válida.', 'taxocommerce-import-export' ) );
						}
					} elseif ( 'slug' === $field ) {
						$value = sanitize_title( $value );
					} else {
						$value = sanitize_text_field( $value );
					}
					$p->$method( $value );
				}
				if ( isset( $data['stock_quantity'] ) ) {
					if ( ! ctype_digit( $data['stock_quantity'] ) ) return new WP_Error( 'invalid_stock', __( 'Stock no válido.', 'taxocommerce-import-export' ) );
					$p->set_manage_stock( true );
					$p->set_stock_quantity( (int) $data['stock_quantity'] );
				}
				if ( null !== $image_id ) {
					$p->set_image_id( $image_id );
				}
				$p->save();
			} catch ( Exception $e ) {
				return new WP_Error( 'product_save', $e->getMessage() );
			}
			return $this->save_terms( $p->get_id(), $terms );
		}
		// Posts and pages use only standard WordPress data and registered taxonomies.
		$terms = $this->mapped_terms( $type, $data );
		if ( is_wp_error( $terms ) ) return $terms;
		$image_id = null;
		if ( isset( $data['featured_image'] ) ) {
			$image_id = $this->media_image_id( $data['featured_image'] );
			if ( is_wp_error( $image_id ) ) return $image_id;
		}
		$id = 0;
		if ( ! empty( $data['slug'] ) ) {
			$post = get_page_by_path( sanitize_title( $data['slug'] ), OBJECT, $type );
			if ( $post ) $id = $post->ID;
		}
		if ( ! $id && empty( $data['slug'] ) && isset( $data['id'] ) && $type === get_post_type( absint( $data['id'] ) ) ) {
			$id = absint( $data['id'] );
		}
		if ( $id && ! current_user_can( 'edit_post', $id ) ) {
			return new WP_Error( 'forbidden', __( 'Sin permiso para editar esta página o entrada.', 'taxocommerce-import-export' ) );
		}
		if ( ! $id && 'update' === $mode ) {
			return new WP_Error( 'not_found', __( 'Página o entrada no encontrada.', 'taxocommerce-import-export' ) );
		}
		if ( ! $id && empty( $data['title'] ) ) {
			return new WP_Error( 'missing_title', __( 'Falta el título para crear el contenido.', 'taxocommerce-import-export' ) );
		}
		if ( isset( $data['status'] ) && ! in_array( $data['status'], array( 'publish', 'draft', 'pending', 'private', 'future' ), true ) ) {
			return new WP_Error( 'invalid_status', __( 'Estado de publicación no válido.', 'taxocommerce-import-export' ) );
		}
		if ( isset( $data['status'] ) && in_array( $data['status'], array( 'publish', 'private', 'future' ), true ) && ! current_user_can( 'page' === $type ? 'publish_pages' : 'publish_posts' ) ) {
			return new WP_Error( 'forbidden', __( 'Sin permiso para publicar este contenido.', 'taxocommerce-import-export' ) );
		}
		$fields = array(
			'title' => 'post_title', 'slug' => 'post_name',
			'status' => 'post_status', 'excerpt' => 'post_excerpt',
			'content' => 'post_content',
		);
		$args = array( 'post_type' => $type );
		if ( $id ) $args['ID'] = $id;
		foreach ( $fields as $key => $target ) {
			if ( ! isset( $data[ $key ] ) ) continue;
			$args[ $target ] = in_array( $key, array( 'content', 'excerpt' ), true ) ?
				wp_kses_post( $data[ $key ] ) : ( 'slug' === $key ? sanitize_title( $data[ $key ] ) : sanitize_text_field( $data[ $key ] ) );
		}
		if ( isset( $data['date'] ) ) {
			$date = str_replace( 'T', ' ', trim( $data['date'] ) );
			if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/D', $date ) ) {
				$date .= ' 00:00:00';
			}
			$parsed = DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', $date );
			if ( ! $parsed || $parsed->format( 'Y-m-d H:i:s' ) !== $date ) {
				return new WP_Error( 'invalid_date', __( 'Fecha no válida; utiliza AAAA-MM-DD HH:MM:SS.', 'taxocommerce-import-export' ) );
			}
			$args['post_date'] = $date;
		}
		if ( isset( $data['author'] ) ) {
			$author_id = $this->author_id( $data['author'] );
			if ( is_wp_error( $author_id ) ) return $author_id;
			$existing_author = $id ? (int) get_post_field( 'post_author', $id ) : get_current_user_id();
			if ( $author_id !== $existing_author && ! current_user_can( 'page' === $type ? 'edit_others_pages' : 'edit_others_posts' ) ) {
				return new WP_Error( 'forbidden', __( 'Sin permiso para cambiar el autor.', 'taxocommerce-import-export' ) );
			}
			$args['post_author'] = $author_id;
		}
		if ( isset( $data['menu_order'] ) ) {
			if ( ! ctype_digit( (string) $data['menu_order'] ) ) {
				return new WP_Error( 'invalid_order', __( 'El orden debe ser un número entero positivo.', 'taxocommerce-import-export' ) );
			}
			$args['menu_order'] = (int) $data['menu_order'];
		}
		if ( isset( $data['comment_status'] ) ) {
			if ( ! in_array( $data['comment_status'], array( 'open', 'closed' ), true ) ) {
				return new WP_Error( 'invalid_comments', __( 'Comentarios: utiliza open o closed.', 'taxocommerce-import-export' ) );
			}
			$args['comment_status'] = $data['comment_status'];
		}
		if ( 'page' === $type && isset( $data['parent_slug'] ) ) {
			$parent = get_page_by_path( sanitize_title( $data['parent_slug'] ), OBJECT, 'page' );
			if ( ! $parent || ( $id && $id === (int) $parent->ID ) ) {
				return new WP_Error( 'parent_missing', __( 'La página padre no existe o no es válida.', 'taxocommerce-import-export' ) );
			}
			$args['post_parent'] = (int) $parent->ID;
		}
		if ( ! $id && empty( $args['post_status'] ) ) $args['post_status'] = 'draft';
		$result = $id ? wp_update_post( $args, true ) : wp_insert_post( $args, true );
		if ( is_wp_error( $result ) || ! $result ) {
			return is_wp_error( $result ) ? $result : new WP_Error( 'post_save', __( 'WordPress no ha podido guardar el contenido.', 'taxocommerce-import-export' ) );
		}
		$result_id = (int) $result;
		if ( null !== $image_id ) {
			if ( (int) get_post_thumbnail_id( $result_id ) !== $image_id && ! set_post_thumbnail( $result_id, $image_id ) ) {
				return new WP_Error( 'image_save', __( 'No se ha podido asignar la imagen destacada.', 'taxocommerce-import-export' ) );
			}
		}
		return $this->save_terms( $result_id, $terms );
	}

	public function step() {
		$this->guard();
		check_ajax_referer( 'tc_ie_basic_step', 'nonce' );
		$token = isset( $_POST['token'] ) ? sanitize_key( wp_unslash( $_POST['token'] ) ) : '';
		$state = $this->load_state( $token );
		if ( ! $state || empty( $state['path'] ) ) wp_send_json_error( array( 'message' => __( 'El CSV ha caducado. Vuelve a subirlo.', 'taxocommerce-import-export' ) ) );
		if ( empty( $state['started'] ) ) {
			$type = sanitize_key( wp_unslash( $_POST['content_type'] ?? '' ) );
			$mode = sanitize_key( wp_unslash( $_POST['mode'] ?? 'update' ) );
			if ( ! isset( self::types()[ $type ] ) || ! in_array( $mode, array( 'update', 'create' ), true ) ) wp_send_json_error( array( 'message' => 'Invalid type or mode' ) );
			$raw_map = isset( $_POST['mapping'] ) && is_array( $_POST['mapping'] ) ? wp_unslash( $_POST['mapping'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Each value sanitized and whitelisted below.
			$allowed = self::fields( $type );
			$map = array();
			foreach ( $raw_map as $i => $name ) {
				$name = is_scalar( $name ) ? sanitize_key( (string) $name ) : '';
				if ( '' !== $name && isset( $allowed[ $name ] ) && is_numeric( $i ) && isset( $state['headers'][ (int) $i ] ) ) $map[ (int) $i ] = $name;
			}
			if ( ! $map || count( $map ) !== count( array_unique( array_values( $map ) ) ) ) wp_send_json_error( array( 'message' => __( 'Mapeo vacío o repetido.', 'taxocommerce-import-export' ) ) );
			$identity = 'product' === $type ? array( 'sku', 'slug', 'id' ) : array( 'slug', 'id' );
			if ( 'update' === $mode && ! array_intersect( $identity, array_values( $map ) ) ) {
				wp_send_json_error( array( 'message' => __( 'Para actualizar, relaciona una columna de identidad (SKU, slug o ID).', 'taxocommerce-import-export' ) ) );
			}
			$state['type'] = $type; $state['mode'] = $mode; $state['map'] = $map; $state['started'] = true;
			$state['offset'] = 0;
		}
		if ( ! empty( $state['done'] ) ) {
			wp_send_json_success( array( 'processed' => $state['processed'], 'saved' => $state['saved'], 'errors' => $state['errors'], 'notices' => $state['notices'], 'done' => true ) );
		}
		$handle = fopen( $state['path'], 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Read private, size-limited CSV in bounded increments.
		if ( ! $handle ) wp_send_json_error( array( 'message' => __( 'No se puede abrir el CSV.', 'taxocommerce-import-export' ) ) );
		if ( ! $state['offset'] ) {
			$this->csv_row( $handle, $state['delimiter'] );
		} else fseek( $handle, $state['offset'] );
		$count = 0;
		while ( $count < self::STEP && false !== ( $row = $this->csv_row( $handle, $state['delimiter'] ) ) ) {
			$count++; $state['processed']++;
			if ( ! is_array( $row ) || count( $row ) !== count( $state['headers'] ) ) {
				$result = new WP_Error( 'bad_columns', __( 'Número de columnas incorrecto.', 'taxocommerce-import-export' ) );
			} else {
				$result = $this->import_record( $state['type'], $this->mapped_row( $state['headers'], $row, $state['map'] ), $state['mode'] );
			}
			if ( true === $result ) $state['saved']++;
			else {
				$state['errors']++;
				$detail = is_wp_error( $result ) ? $result->get_error_message() : __( 'No se ha guardado la fila.', 'taxocommerce-import-export' );
				$state['notices'][] = sprintf( '%d: %s', $state['processed'] + 1, $detail );
				$state['notices'] = array_slice( $state['notices'], -12 );
			}
		}
		$state['offset'] = ftell( $handle );
		$state['done'] = feof( $handle );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Close the bounded private CSV input stream.
		fclose( $handle );
		if ( $state['done'] ) wp_delete_file( $state['path'] );
		// Return only public progress data, not internal storage paths.
		$this->store_state( $token, $state );
		wp_send_json_success( array(
			'processed' => $state['processed'], 'saved' => $state['saved'],
			'errors' => $state['errors'], 'notices' => $state['notices'], 'done' => $state['done'],
		) );
	}
}
