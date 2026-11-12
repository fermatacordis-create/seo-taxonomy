<?php
/**
 * Local image metadata and cleanup, without remote HTTP checks.
 *
 * @package TaxoCommerceImages
 */
defined( 'ABSPATH' ) || exit;

final class TaxoCommerce_Images_Maintenance {
	const BATCH = 25;
	// One reference lookup covers several CPU-regulated hash batches.
	const CLEANUP_USAGE_WINDOW = 512;
	const CLEANUP_USAGE_OPTION = 'taxocommerce_images_cleanup_usage_window';
	const LOCK_PREFIX = 'taxocommerce_images_process_lock_';
	private static $tokens = array();

	public static function boot() {
		add_action( 'wp_ajax_taxocommerce_images_maintenance', array( __CLASS__, 'ajax' ) );
		add_action( 'admin_post_taxocommerce_images_label_save', array( __CLASS__, 'save_labels' ) );
		add_action( 'admin_post_taxocommerce_images_save_priority', array( __CLASS__, 'save_priority' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		// The same administrator must not convert and delete the same file concurrently.
		add_action( 'wp_ajax_tci_webp_batch', array( __CLASS__, 'protect_legacy_job' ), 0 );
		add_action( 'wp_ajax_tci_optimizer_batch', array( __CLASS__, 'protect_legacy_job' ), 0 );
	}
	public static function assets( $hook ) {
		if ( false === strpos( (string) $hook, 'taxocommerce-images' ) ) { return; }
		wp_enqueue_script( 'taxocommerce-images-maintenance', plugin_dir_url( TAXOCOMMERCE_IMAGES_FILE ) . 'assets/maintenance.js', array(), TAXOCOMMERCE_IMAGES_VERSION, true );
		wp_localize_script(
			'taxocommerce-images-maintenance',
			'taxocommerceImagesMaintenance',
			array(
				'url'   => admin_url( 'admin-ajax.php' ),
				'nonce'            => wp_create_nonce( 'taxocommerce_images_maintenance' ),
				'assignmentNonce' => wp_create_nonce( 'taxocommerce_images_assignment' ),
				'delay'            => 4000,
			)
		);
	}
	public static function protect_legacy_job() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( array( 'message' => 'Sin permisos.' ), 403 ); }
		$is_webp = doing_action( 'wp_ajax_tci_webp_batch' );
		check_ajax_referer( $is_webp ? 'tci_webp_run' : 'tci_optimizer_run', 'nonce' );
		if ( ! self::lock( 'mutation' ) ) { wp_send_json_error( array( 'message' => 'Otro proceso de modificación de imágenes está activo.' ), 409 ); }
		register_shutdown_function( array( __CLASS__, 'unlock' ), 'mutation' );
	}
	public static function lock( $process = 'mutation' ) {
		global $wpdb;
		$process = sanitize_key( (string) $process );
		$process = '' === $process ? 'mutation' : $process;
		$key = self::LOCK_PREFIX . $process;
		$token = wp_generate_uuid4();
		$value = array( 'token' => $token, 'time' => time() );
		if ( add_option( $key, $value, '', false ) ) {
			self::$tokens[ $process ] = $token;
			return true;
		}
		$old = get_option( $key );
		if ( is_array( $old ) && (int) ( $old['time'] ?? 0 ) < time() - 900 ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Atomic compare-and-delete by stored lock token/value prevents stealing a renewed lock; delete_option() is not equivalent.
			$deleted = $wpdb->delete( $wpdb->options, array( 'option_name' => $key, 'option_value' => maybe_serialize( $old ) ), array( '%s', '%s' ) );
			wp_cache_delete( $key, 'options' );
			if ( $deleted && add_option( $key, $value, '', false ) ) {
				self::$tokens[ $process ] = $token;
				return true;
			}
		}
		return false;
	}
	public static function unlock( $process = 'mutation' ) {
		global $wpdb;
		$process = sanitize_key( (string) $process );
		$process = '' === $process ? 'mutation' : $process;
		$key = self::LOCK_PREFIX . $process;
		$token = (string) ( self::$tokens[ $process ] ?? '' );
		$old = get_option( $key );
		if ( $token && is_array( $old ) && hash_equals( $token, (string) ( $old['token'] ?? '' ) ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Release only the exact lock value owned by this worker; direct conditional delete prevents concurrent-owner races.
			$wpdb->delete( $wpdb->options, array( 'option_name' => $key, 'option_value' => maybe_serialize( $old ) ), array( '%s', '%s' ) );
			wp_cache_delete( $key, 'options' );
		}
		unset( self::$tokens[ $process ] );
	}

	private static function option( $mode ) { return 'taxocommerce_images_' . $mode . '_report_v2'; }
	public static function state( $mode ) {
		$value = get_option( self::option( $mode ), array() );
		return is_array( $value ) ? $value : array();
	}
	private static function save_state( $mode, $state ) { update_option( self::option( $mode ), $state, false ); }
	public static function ids_after( $after, $max, $limit ) {
		$filter = static function ( $where, $query ) use ( $after, $max ) {
			global $wpdb;
			if ( $query->get( 'taxocommerce_images_scan' ) ) { $where .= $wpdb->prepare( " AND {$wpdb->posts}.ID > %d AND {$wpdb->posts}.ID <= %d", $after, $max ); }
			return $where;
		};
		add_filter( 'posts_where', $filter, 10, 2 );
		try {
			$query = new WP_Query( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'post_mime_type' => 'image', 'fields' => 'ids', 'posts_per_page' => $limit, 'orderby' => 'ID', 'order' => 'ASC', 'no_found_rows' => true, 'taxocommerce_images_scan' => true ) );
		} finally { remove_filter( 'posts_where', $filter, 10 ); }
		return array_map( 'absint', $query->posts );
	}
	public static function begin( $mode ) {
		if ( ! current_user_can( 'manage_options' ) || ! in_array( $mode, array( 'labels', 'cleanup' ), true ) ) { return new WP_Error( 'permission', 'Sin permisos.' ); }
		$q = new WP_Query( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'post_mime_type' => 'image', 'fields' => 'ids', 'posts_per_page' => 1, 'orderby' => 'ID', 'order' => 'DESC' ) );
		$counts = 'cleanup' === $mode
			? array( 'used' => 0, 'unused' => 0, 'duplicate' => 0, 'keeper' => 0, 'review' => 0 )
			: array();
		$state = array(
			'run'          => wp_generate_uuid4(),
			'last'         => 0,
			'max'          => $q->posts ? absint( $q->posts[0] ) : 0,
			'total'        => (int) $q->found_posts,
			'scanned'      => 0,
			'status'       => 'running',
			'counts'       => $counts,
			'removed'      => 0,
			'saved_bytes'  => 0,
			'started_at'   => current_time( 'mysql' ),
			'completed_at' => '',
			'message'      => 'cleanup' === $mode
				? __( 'Escaneo completo iniciado. Los lotes internos se regulan automáticamente hasta recorrer toda la biblioteca.', 'taxocommerce-images' )
				: __( 'Escaneo completo de etiquetas iniciado. Los lotes internos se regulan automáticamente hasta recorrer toda la biblioteca.', 'taxocommerce-images' ),
		);
		if ( 'cleanup' === $mode ) {
			delete_option( self::CLEANUP_USAGE_OPTION );
		}
		self::save_state( $mode, $state );
		return $state;
	}
	private static function row_key( $mode ) { return '_taxocommerce_images_' . $mode . '_result'; }
	public static function record( $mode, $id, $row ) {
		$state = self::state( $mode );
		$old = get_post_meta( $id, self::row_key( $mode ), true );
		if ( is_array( $old ) && ( $old['run'] ?? '' ) === $state['run'] ) {
			foreach ( (array) ( $old['flags'] ?? array( $old['status'] ) ) as $flag ) { $state['counts'][ $flag ] = max( 0, (int) ( $state['counts'][ $flag ] ?? 0 ) - 1 ); }
		}
		$row['run'] = $state['run'];
		foreach ( (array) ( $row['flags'] ?? array( $row['status'] ) ) as $flag ) { $state['counts'][ $flag ] = (int) ( $state['counts'][ $flag ] ?? 0 ) + 1; }
		update_post_meta( $id, self::row_key( $mode ), $row );
		update_post_meta( $id, '_taxocommerce_images_' . $mode . '_run', $state['run'] );
		update_post_meta( $id, '_taxocommerce_images_' . $mode . '_status', $row['status'] );
		self::save_state( $mode, $state );
	}
	public static function label_flags( $id ) {
		$meta = wp_get_attachment_metadata( $id );
		$meta = is_array( $meta ) ? $meta : array();
		$flags = array();
		$alt = trim( (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) );
		$decorative = (bool) get_post_meta( $id, '_taxocommerce_images_decorative', true );
		$title = trim( (string) get_post_field( 'post_title', $id ) );
		if ( '' === $alt && ! $decorative ) { $flags[] = 'missing_alt'; }
		if ( '' !== $alt && $decorative ) { $flags[] = 'decorative_alt'; }
		$file = get_attached_file( $id );
		$stem = is_string( $file ) ? pathinfo( $file, PATHINFO_FILENAME ) : '';
		if ( '' === $title ) { $flags[] = 'missing_title'; }
		elseif ( preg_match( '/^(?:img|dsc|image|foto)?[-_ ]*[0-9]+$/i', $title ) || ( '' !== $stem && sanitize_title( $title ) === sanitize_title( $stem ) ) ) { $flags[] = 'review_title'; }
		if ( empty( $meta['width'] ) || empty( $meta['height'] ) ) { $flags[] = 'missing_dimensions'; }
		if ( ! is_string( $file ) || ! is_file( $file ) ) { $flags[] = 'missing_file'; }
		return $flags;
	}
	/**
	 * Reuse the complete cross-content reference check for a bounded ID window.
	 * The previous implementation repeated all global post/meta/term scans on
	 * every 1-5-image hash batch. Cache is tied to this scan UUID, not a site
	 * transient, and never reused after a fresh start.
	 *
	 * @param array $state Current scan snapshot.
	 * @param array $ids   IDs for the small adaptive hash batch.
	 * @return array
	 */
	private static function cleanup_usage( $state, $ids ) {
		if ( ! $ids ) {
			return array();
		}
		$cached = get_option( self::CLEANUP_USAGE_OPTION, array() );
		$valid = is_array( $cached )
			&& ( $cached['run'] ?? '' ) === ( $state['run'] ?? '' )
			&& isset( $cached['usage'] )
			&& is_array( $cached['usage'] );
		if ( $valid ) {
			foreach ( $ids as $id ) {
				if ( ! array_key_exists( $id, $cached['usage'] ) ) {
					$valid = false;
					break;
				}
			}
		}
		if ( ! $valid ) {
			$window_ids = self::ids_after( $state['last'], $state['max'], self::CLEANUP_USAGE_WINDOW );
			$posts = array_filter( array_map( 'get_post', $window_ids ) );
			$usage = taxocommerce_images_refs_build_usage_report( $posts );
			// Do not cache incomplete reports as authoritative "unused" results.
			if ( ! is_array( $usage ) || count( $usage ) !== count( $window_ids ) ) {
				return new WP_Error( 'tci_cleanup_usage', __( 'No se pudo verificar el uso de todas las imágenes del lote.', 'taxocommerce-images' ) );
			}
			$cached = array( 'run' => $state['run'], 'usage' => $usage );
			update_option( self::CLEANUP_USAGE_OPTION, $cached, false );
		}
		return $cached['usage'];
	}

	public static function scan( $mode ) {
		if ( ! current_user_can( 'manage_options' ) ) { return new WP_Error( 'permission', 'Sin permisos.' ); }
		$state = self::state( $mode );
		if ( ! $state || 'running' !== $state['status'] ) { return $state; }
		$ids = self::ids_after( $state['last'], $state['max'], TaxoCommerce_Images_Load::batch_size( $mode ) );
		$usage = 'cleanup' === $mode ? self::cleanup_usage( $state, $ids ) : array();
		if ( is_wp_error( $usage ) ) {
			return $usage;
		}
		foreach ( $ids as $id ) {
			if ( 'labels' === $mode ) {
				$flags = self::label_flags( $id );
				self::record( $mode, $id, array( 'status' => $flags ? 'review' : 'ok', 'flags' => $flags ? $flags : array( 'ok' ) ) );
			} else {
				$row = array( 'status' => empty( $usage[ $id ] ) ? 'unused' : 'used', 'duplicate' => 0, 'message' => '', 'bytes' => taxocommerce_images_refs_get_attachment_size( $id ) );
				$file = get_attached_file( $id );
				if ( ! self::local_file( $file ) ) { $row['status'] = 'review'; $row['message'] = 'Archivo no local, ausente o ilegible.'; }
				else {
					$digest = hash_file( 'sha256', $file );
					if ( false === $digest ) { $row['status'] = 'review'; $row['message'] = 'No se pudo calcular la huella.'; }
					else {
						$matches = get_posts(
							array(
								'post_type'      => 'attachment',
								'post_status'    => 'inherit',
								'posts_per_page' => 2,
								'fields'         => 'ids',
								'orderby'        => 'ID',
								'order'          => 'ASC',
								// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Two exact metadata predicates restrict SHA-256 duplicate candidates to the active run; no custom lookup table.
								'meta_query'     => array(
									array( 'key' => '_taxocommerce_images_cleanup_run', 'value' => $state['run'] ),
									array( 'key' => '_taxocommerce_images_digest', 'value' => $digest ),
								),
							)
						);
						// Reject a crash-retried image without post__not_in (which generates a costly exclusion).
						$matches = array_values( array_filter( array_map( 'absint', $matches ), static function ( $candidate_id ) use ( $id ) {
							return $candidate_id !== absint( $id );
						} ) );
						update_post_meta( $id, '_taxocommerce_images_digest', $digest );
						if ( $matches ) {
							$row['duplicate'] = absint( $matches[0] ); $row['status'] = 'duplicate';
							$canonical = get_post_meta( $matches[0], self::row_key( 'cleanup' ), true );
							if ( is_array( $canonical ) && 'unused' === $canonical['status'] ) { $canonical['status'] = 'keeper'; self::record( 'cleanup', $matches[0], $canonical ); }
						}
					}
				}
				self::record( $mode, $id, $row );
			}
			$state = self::state( $mode ); $state['last'] = $id; ++$state['scanned']; self::save_state( $mode, $state );
		}
		$state = self::state( $mode );
		if ( ! $ids || $state['last'] >= $state['max'] ) {
			$state['status'] = 'complete';
			$state['completed_at'] = current_time( 'mysql' );
			if ( 'cleanup' === $mode ) {
				$state['message'] = sprintf(
					/* translators: 1: scanned images, 2: unused images, 3: exact duplicates. */
					__( 'Escaneo completo: %1$d imágenes analizadas, %2$d sin uso conocido y %3$d duplicadas exactas.', 'taxocommerce-images' ),
					absint( $state['scanned'] ?? 0 ),
					absint( $state['counts']['unused'] ?? 0 ),
					absint( $state['counts']['duplicate'] ?? 0 )
				);
			} else {
				$state['message'] = sprintf(
					/* translators: 1: scanned images, 2: missing ALT, 3: titles to review. */
					__( 'Escaneo completo de etiquetas: %1$d imágenes analizadas, %2$d sin ALT informativo y %3$d con título a revisar.', 'taxocommerce-images' ),
					absint( $state['scanned'] ?? 0 ),
					absint( $state['counts']['missing_alt'] ?? 0 ),
					absint( $state['counts']['missing_title'] ?? 0 ) + absint( $state['counts']['review_title'] ?? 0 )
				);
			}
		}
		self::save_state( $mode, $state );
		return $state;
	}
	public static function local_file( $file ) {
		$uploads = wp_get_upload_dir(); $root = realpath( $uploads['basedir'] );
		$real = is_string( $file ) ? realpath( $file ) : false;
		return $root && $real && is_file( $real ) && is_readable( $real ) && 0 === strpos( wp_normalize_path( $real ), trailingslashit( wp_normalize_path( $root ) ) );
	}
	public static function rows( $mode, $statuses = array(), $page = 1, $limit = 25 ) {
		$state = self::state( $mode );
		$args = array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => min( 100, max( 1, absint( $limit ) ) ),
			'paged'          => max( 1, absint( $page ) ),
			'orderby'        => 'ID',
			'order'          => 'ASC',
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Results must be isolated to this run and filtered by metadata; native paged WP_Query returns the correct live total.
			'meta_query'     => array( array( 'key' => '_taxocommerce_images_' . $mode . '_run', 'value' => $state['run'] ?? '-' ) ),
		);
		if ( $statuses ) { $args['meta_query'][] = array( 'key' => '_taxocommerce_images_' . $mode . '_status', 'value' => $statuses, 'compare' => 'IN' ); }
		return new WP_Query( $args );
	}
	public static function ajax() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( array( 'message' => 'Sin permisos.' ), 403 ); }
		check_ajax_referer( 'taxocommerce_images_maintenance', 'nonce' );
		$mode = isset( $_POST['mode'] ) && is_string( $_POST['mode'] ) ? sanitize_key( wp_unslash( $_POST['mode'] ) ) : '';
		$operation = isset( $_POST['operation'] ) && is_string( $_POST['operation'] ) ? sanitize_key( wp_unslash( $_POST['operation'] ) ) : '';
		if ( ! in_array( $mode, array( 'labels', 'cleanup' ), true ) || ! in_array( $operation, array( 'start', 'scan' ), true ) ) {
			wp_send_json_error( array( 'message' => 'Operación no válida.' ), 400 );
		}
		if ( ! self::lock( $mode ) ) { wp_send_json_error( array( 'message' => 'Este mismo proceso ya tiene un lote en curso.' ), 409 ); }
		$measurement = TaxoCommerce_Images_Load::begin();
		try {
			if ( 'start' === $operation ) { self::begin( $mode ); }
			$before = self::state( $mode );
			$before_scanned = absint( $before['scanned'] ?? 0 );
			$state = self::scan( $mode );
			if ( is_wp_error( $state ) ) {
				$result = $state;
			} else {
				$processed = max( 0, absint( $state['scanned'] ?? 0 ) - $before_scanned );
				$sample = TaxoCommerce_Images_Load::finish( $mode, $measurement, $processed );
				if ( 'start' === $operation && 'running' === ( $state['status'] ?? '' ) ) {
					TaxoCommerce_Images_Background::start( $mode, get_current_user_id() );
				}
				$result = array(
					'done'   => ( $state['status'] ?? '' ) === 'complete',
					'paused' => false,
					'state'  => $state,
					'load'   => $sample,
					'delay'  => TaxoCommerce_Images_Load::delay_ms( $mode ),
				);
			}
		} catch ( Throwable $error ) { $result = new WP_Error( 'exception', sanitize_text_field( $error->getMessage() ) ); }
		finally { self::unlock( $mode ); }
		if ( is_wp_error( $result ) ) { wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 ); }
		wp_send_json_success( $result );
	}
	/**
	 * Save Images-only workload priority.
	 *
	 * @return void
	 */
	public static function save_priority() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'No tienes permisos para cambiar la prioridad.', 'taxocommerce-images' ) );
		}
		check_admin_referer( 'taxocommerce_images_save_priority', 'taxocommerce_images_nonce' );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified above.
		$mode = isset( $_POST['mode'] ) && is_scalar( $_POST['mode'] ) ? sanitize_key( wp_unslash( $_POST['mode'] ) ) : 'cleanup';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified above.
		$priority = isset( $_POST['priority'] ) && is_scalar( $_POST['priority'] )
			? sanitize_text_field( wp_unslash( (string) $_POST['priority'] ) ) : '';
		$result = TaxoCommerce_Images_Load::set_priority( $priority, $mode );
		if ( is_wp_error( $result ) ) {
			wp_die( esc_html( $result->get_error_message() ) );
		}
		if ( 'audit' === $mode ) {
			$tab = 'audit';
		} elseif ( 0 === strpos( $mode, 'assignment_' ) ) {
			$tab = 'assignment';
		} else {
			$tab = 'labels' === $mode ? 'labels' : 'references';
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'       => 'taxocommerce-images',
					'tab'        => $tab,
					'tc_result'  => 'ok',
					'tc_message' => __( 'Prioridad de Images guardada.', 'taxocommerce-images' ),
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	public static function save_labels() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Sin permisos.', 'taxocommerce-images' ) ); }
		check_admin_referer( 'taxocommerce_images_label_save', 'taxocommerce_images_nonce' );
		$id = isset( $_POST['attachment_id'] ) && is_scalar( $_POST['attachment_id'] ) ? absint( $_POST['attachment_id'] ) : 0;
		if ( ! wp_attachment_is_image( $id ) || ! current_user_can( 'edit_post', $id ) ) { wp_die( esc_html__( 'Imagen no válida.', 'taxocommerce-images' ) ); }
		$title = isset( $_POST['image_title'] ) && is_string( $_POST['image_title'] ) ? sanitize_text_field( wp_unslash( $_POST['image_title'] ) ) : '';
		$alt = isset( $_POST['image_alt'] ) && is_string( $_POST['image_alt'] ) ? sanitize_text_field( wp_unslash( $_POST['image_alt'] ) ) : '';
		$decorative = ! empty( $_POST['decorative'] );
		$updated = wp_update_post( wp_slash( array( 'ID' => $id, 'post_title' => $title ) ), true );
		if ( is_wp_error( $updated ) ) { wp_die( esc_html( $updated->get_error_message() ) ); }
		update_post_meta( $id, '_wp_attachment_image_alt', wp_slash( $decorative ? '' : $alt ) );
		update_post_meta( $id, '_taxocommerce_images_decorative', $decorative ? 1 : 0 );
		if ( self::state( 'labels' ) ) { $flags = self::label_flags( $id ); self::record( 'labels', $id, array( 'status' => $flags ? 'review' : 'ok', 'flags' => $flags ? $flags : array( 'ok' ) ) ); }
		wp_safe_redirect( admin_url( 'admin.php?page=taxocommerce-images&tab=labels' ) ); exit;
	}
	public static function render( $mode ) {
		$state = self::state( $mode );
		$labels = array( 'missing_alt' => 'Sin ALT informativo', 'decorative_alt' => 'Decorativa con ALT', 'missing_title' => 'Sin título', 'review_title' => 'Revisar título descriptivo', 'missing_dimensions' => 'Sin dimensiones registradas', 'missing_file' => 'Archivo ausente', 'ok' => 'Sin incidencias', 'unused' => 'Sin referencias conocidas', 'duplicate' => 'Duplicadas exactas', 'keeper' => 'Conservadas hasta consolidar', 'used' => 'En uso', 'review' => 'Revisión necesaria' );
		?>
		<section data-tci-maintenance="<?php echo esc_attr( $mode ); ?>">
		<?php TaxoCommerce_Images_Background::render_panel( $mode ); ?>
		<h2><?php echo esc_html( 'labels' === $mode ? 'Etiquetas y atributos de imágenes' : 'Liberar espacio: sin uso y duplicadas' ); ?></h2>
		<p><?php echo esc_html( 'labels' === $mode ? 'Escanea la biblioteca completa en un único trabajo para revisar ALT, título, dimensiones y existencia del archivo. Internamente usa lotes adaptativos regulados 1–5 para proteger CPU y memoria, pero continúa automáticamente hasta revisar todas las imágenes. Leyenda y descripción son opcionales; no se rellena texto genérico automáticamente. Marcar decorativa expresa la decisión del administrador.' : 'Escanea la biblioteca completa en un único trabajo. Internamente usa lotes adaptativos regulados 1–5 para proteger CPU y memoria, pero continúa automáticamente hasta revisar todas las imágenes. El informe separa imágenes en uso, sin uso conocido, duplicadas exactas y casos que requieren revisión. Las duplicadas exactas se verifican comparando SHA-256 del archivo original; no se deducen por nombre, tamaño o dimensiones. No se elimina ninguna imagen durante este análisis.' ); ?></p>
		<?php
		$priority = TaxoCommerce_Images_Load::priority( $mode );
		$sample = TaxoCommerce_Images_Load::last_sample( $mode );
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="tci-load-control">
			<input type="hidden" name="action" value="taxocommerce_images_save_priority">
			<input type="hidden" name="mode" value="<?php echo esc_attr( $mode ); ?>">
			<?php wp_nonce_field( 'taxocommerce_images_save_priority', 'taxocommerce_images_nonce' ); ?>
			<label for="tci-priority-<?php echo esc_attr( $mode ); ?>"><strong><?php esc_html_e( 'Prioridad de Images', 'taxocommerce-images' ); ?></strong></label>
			<div class="tci-load-control__slider">
				<span><?php esc_html_e( '1 · Suave', 'taxocommerce-images' ); ?></span>
				<input id="tci-priority-<?php echo esc_attr( $mode ); ?>" name="priority" type="range" min="1" max="5" step="1" value="<?php echo esc_attr( (string) $priority ); ?>" data-tci-priority>
				<span><?php esc_html_e( '5 · Máxima', 'taxocommerce-images' ); ?></span>
				<output data-tci-priority-output><strong><?php echo esc_html( $priority . ' · ' . TaxoCommerce_Images_Load::label( $priority ) ); ?></strong></output>
			</div>
			<p class="description"><?php esc_html_e( '1 trabaja suavemente a largo plazo; 3 es el equilibrio recomendado; 5 prioriza terminar cuanto antes. Images ajusta automáticamente el tamaño del siguiente lote según el coste real de sus propias peticiones.', 'taxocommerce-images' ); ?></p>
			<button type="submit" class="button button-primary"><?php esc_html_e( 'Guardar prioridad', 'taxocommerce-images' ); ?></button>
			<?php if ( $sample ) : ?>
				<?php
				$cpu = null !== ( $sample['cpu_percent'] ?? null )
					? number_format_i18n( (float) $sample['cpu_percent'], 1 ) . ' % CPU petición'
					: __( 'CPU petición no disponible', 'taxocommerce-images' );
				?>
				<p class="description tci-load-control__sample">
					<?php
					echo esc_html(
						sprintf(
							/* translators: 1: processed items, 2: CPU, 3: seconds, 4: memory, 5: next batch. */
							__( 'Último lote: %1$s elementos · %2$s · %3$s s · pico %4$s · siguiente lote %5$s.', 'taxocommerce-images' ),
							number_format_i18n( absint( $sample['items'] ?? 0 ) ),
							$cpu,
							number_format_i18n( (float) ( $sample['elapsed'] ?? 0 ), 2 ),
							size_format( absint( $sample['memory_peak'] ?? 0 ) ),
							number_format_i18n( absint( $sample['next_batch'] ?? TaxoCommerce_Images_Load::batch_size( $mode ) ) )
						)
					);
					?>
				</p>
			<?php endif; ?>
		</form>
		<?php if ( 'cleanup' === $mode ) : ?>
			<?php
			$cleanup_counts = wp_parse_args(
				(array) ( $state['counts'] ?? array() ),
				array( 'used' => 0, 'unused' => 0, 'duplicate' => 0, 'keeper' => 0, 'review' => 0 )
			);
			?>
			<div class="tcc-stat-grid">
				<div class="card"><strong><?php echo esc_html( number_format_i18n( absint( $state['total'] ?? 0 ) ) ); ?></strong> <?php esc_html_e( 'Total biblioteca', 'taxocommerce-images' ); ?></div>
				<div class="card"><strong><?php echo esc_html( number_format_i18n( absint( $state['scanned'] ?? 0 ) ) ); ?></strong> <?php esc_html_e( 'Analizadas', 'taxocommerce-images' ); ?></div>
				<div class="card"><strong><?php echo esc_html( number_format_i18n( absint( $cleanup_counts['used'] ) ) ); ?></strong> <?php esc_html_e( 'En uso', 'taxocommerce-images' ); ?></div>
				<div class="card"><strong><?php echo esc_html( number_format_i18n( absint( $cleanup_counts['unused'] ) ) ); ?></strong> <?php esc_html_e( 'Sin uso conocido', 'taxocommerce-images' ); ?></div>
				<div class="card"><strong><?php echo esc_html( number_format_i18n( absint( $cleanup_counts['duplicate'] ) ) ); ?></strong> <?php esc_html_e( 'Duplicadas exactas (SHA-256)', 'taxocommerce-images' ); ?></div>
				<div class="card"><strong><?php echo esc_html( number_format_i18n( absint( $cleanup_counts['review'] ) ) ); ?></strong> <?php esc_html_e( 'Revisión necesaria', 'taxocommerce-images' ); ?></div>
			</div>
			<p><strong><?php esc_html_e( 'Informe de limpieza:', 'taxocommerce-images' ); ?></strong>
				<?php
				echo esc_html(
					sprintf(
						/* translators: 1: unused, 2: duplicates, 3: keepers, 4: review. */
						__( '%1$d imágenes sin uso conocido · %2$d copias duplicadas exactas · %3$d originales conservados de grupos duplicados · %4$d casos para revisar.', 'taxocommerce-images' ),
						absint( $cleanup_counts['unused'] ),
						absint( $cleanup_counts['duplicate'] ),
						absint( $cleanup_counts['keeper'] ),
						absint( $cleanup_counts['review'] )
					)
				);
				?>
			</p>
		<?php else : ?>
			<?php
			$label_counts = wp_parse_args(
				(array) ( $state['counts'] ?? array() ),
				array(
					'missing_alt'        => 0,
					'decorative_alt'     => 0,
					'missing_title'      => 0,
					'review_title'       => 0,
					'missing_dimensions' => 0,
					'missing_file'       => 0,
					'ok'                 => 0,
				)
			);
			?>
			<div class="tcc-stat-grid">
				<div class="card"><strong><?php echo esc_html( number_format_i18n( absint( $state['total'] ?? 0 ) ) ); ?></strong> <?php esc_html_e( 'Total biblioteca', 'taxocommerce-images' ); ?></div>
				<div class="card"><strong><?php echo esc_html( number_format_i18n( absint( $state['scanned'] ?? 0 ) ) ); ?></strong> <?php esc_html_e( 'Analizadas', 'taxocommerce-images' ); ?></div>
				<div class="card"><strong><?php echo esc_html( number_format_i18n( absint( $label_counts['missing_alt'] ) ) ); ?></strong> <?php esc_html_e( 'Sin ALT informativo', 'taxocommerce-images' ); ?></div>
				<div class="card"><strong><?php echo esc_html( number_format_i18n( absint( $label_counts['missing_title'] ) + absint( $label_counts['review_title'] ) ) ); ?></strong> <?php esc_html_e( 'Título a revisar', 'taxocommerce-images' ); ?></div>
				<div class="card"><strong><?php echo esc_html( number_format_i18n( absint( $label_counts['missing_dimensions'] ) ) ); ?></strong> <?php esc_html_e( 'Sin dimensiones', 'taxocommerce-images' ); ?></div>
				<div class="card"><strong><?php echo esc_html( number_format_i18n( absint( $label_counts['missing_file'] ) ) ); ?></strong> <?php esc_html_e( 'Archivo ausente', 'taxocommerce-images' ); ?></div>
				<div class="card"><strong><?php echo esc_html( number_format_i18n( absint( $label_counts['ok'] ) ) ); ?></strong> <?php esc_html_e( 'Sin incidencias', 'taxocommerce-images' ); ?></div>
			</div>
			<p><strong><?php esc_html_e( 'Informe de etiquetas:', 'taxocommerce-images' ); ?></strong>
				<?php
				echo esc_html(
					sprintf(
						/* translators: 1: missing ALT, 2: title review, 3: missing dimensions, 4: missing files. */
						__( '%1$d sin ALT · %2$d con título a revisar · %3$d sin dimensiones · %4$d con archivo ausente.', 'taxocommerce-images' ),
						absint( $label_counts['missing_alt'] ),
						absint( $label_counts['missing_title'] ) + absint( $label_counts['review_title'] ),
						absint( $label_counts['missing_dimensions'] ),
						absint( $label_counts['missing_file'] )
					)
				);
				?>
			</p>
		<?php endif; ?>
		<p><?php echo esc_html( 'Analizadas: ' . (int) ( $state['scanned'] ?? 0 ) . ' / ' . (int) ( $state['total'] ?? 0 ) . '. El proceso recorre la biblioteca completa; el tamaño del lote es únicamente un regulador interno y no limita el informe.' ); ?></p>
		<?php if ( ! empty( $state['message'] ) ) : ?><p><strong><?php echo esc_html( (string) $state['message'] ); ?></strong></p><?php endif; ?>
		<p><button class="button button-primary" data-tci-operation="start">Escanear toda la biblioteca</button> <button class="button" data-tci-operation="scan" <?php disabled( ! $state || 'running' !== $state['status'] ); ?>>Continuar análisis completo</button> <button class="button" data-tci-pause>Pausar</button>
		<?php if ( 'cleanup' === $mode ) : ?><a class="button" href="<?php echo esc_url( admin_url( 'upload.php?mode=list' ) ); ?>">Revisar en Media</a><?php endif; ?></p>
		<p data-tci-progress role="status" aria-live="polite"><?php echo esc_html( 'Eliminadas: ' . (int) ( $state['removed'] ?? 0 ) . '. Espacio liberado comprobado: ' . size_format( (int) ( $state['saved_bytes'] ?? 0 ) ) ); ?></p>
		<?php
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only pagination.
		$page = isset( $_GET['report_page'] ) && is_scalar( $_GET['report_page'] ) ? max( 1, absint( $_GET['report_page'] ) ) : 1;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only report filter.
		$report_filter = isset( $_GET['report_filter'] ) && is_scalar( $_GET['report_filter'] ) ? sanitize_key( wp_unslash( $_GET['report_filter'] ) ) : ( 'cleanup' === $mode ? 'issues' : 'all' );
		$filter_statuses = array();
		if ( 'cleanup' === $mode ) {
			if ( 'issues' === $report_filter ) { $filter_statuses = array( 'unused', 'duplicate', 'keeper', 'review' ); }
			elseif ( 'unused' === $report_filter ) { $filter_statuses = array( 'unused' ); }
			elseif ( 'duplicates' === $report_filter ) { $filter_statuses = array( 'duplicate', 'keeper' ); }
			elseif ( 'used' === $report_filter ) { $filter_statuses = array( 'used' ); }
			elseif ( 'review' === $report_filter ) { $filter_statuses = array( 'review' ); }
			else { $report_filter = 'all'; }
		}
		$q = self::rows( $mode, $filter_statuses, $page );
		?>
		<?php if ( 'cleanup' === $mode ) : ?>
			<p class="subsubsub" style="float:none;margin:12px 0;">
			<?php
			$filters = array(
				'issues'     => __( 'Sin uso + duplicadas + revisión', 'taxocommerce-images' ),
				'unused'     => __( 'Sin uso', 'taxocommerce-images' ),
				'duplicates' => __( 'Duplicadas', 'taxocommerce-images' ),
				'used'       => __( 'En uso', 'taxocommerce-images' ),
				'review'     => __( 'Revisión', 'taxocommerce-images' ),
				'all'        => __( 'Todas', 'taxocommerce-images' ),
			);
			$filter_links = array();
			foreach ( $filters as $key => $filter_label ) {
				$url = add_query_arg(
					array(
						'page'          => 'taxocommerce-images',
						'tab'           => 'references',
						'report_filter' => $key,
					),
					admin_url( 'admin.php' )
				);
				$filter_links[] = '<a class="' . ( $report_filter === $key ? 'current' : '' ) . '" href="' . esc_url( $url ) . '">' . esc_html( $filter_label ) . '</a>';
			}
			echo wp_kses_post( implode( ' | ', $filter_links ) );
			?>
			</p>
		<?php endif; ?>
		<table class="widefat striped"><thead><tr><th>Imagen</th><th>Resultado</th><th>Acciones</th></tr></thead><tbody>
		<?php foreach ( $q->posts as $post ) : $id = $post->ID; $row = get_post_meta( $id, self::row_key( $mode ), true ); ?>
		<tr><td><?php echo wp_kses_post( wp_get_attachment_image( $id, array( 60, 60 ) ) ); ?> <?php echo esc_html( get_the_title( $id ) . ' #' . $id ); ?></td><td>
		<?php foreach ( (array) ( $row['flags'] ?? array( $row['status'] ?? '' ) ) as $flag ) { echo esc_html( ( $labels[ $flag ] ?? $flag ) . ' ' ); } ?>
		<?php if ( ! empty( $row['duplicate'] ) ) { echo esc_html( ' → #' . $row['duplicate'] ); } ?>
		<?php echo esc_html( (string) ( $row['message'] ?? '' ) ); ?></td><td>
		<?php if ( 'labels' === $mode ) : ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="taxocommerce_images_label_save"><input type="hidden" name="attachment_id" value="<?php echo esc_attr( (string) $id ); ?>">
		<?php wp_nonce_field( 'taxocommerce_images_label_save', 'taxocommerce_images_nonce' ); ?>
		<label>Título <input name="image_title" value="<?php echo esc_attr( $post->post_title ); ?>"></label>
		<label>ALT <input name="image_alt" value="<?php echo esc_attr( get_post_meta( $id, '_wp_attachment_image_alt', true ) ); ?>"></label>
		<label><input type="checkbox" name="decorative" value="1" <?php checked( (bool) get_post_meta( $id, '_taxocommerce_images_decorative', true ) ); ?>>Decorativa: ALT vacío</label>
		<button class="button">Guardar</button></form>
		<?php endif; ?>
		<a href="<?php echo esc_url( get_edit_post_link( $id ) ); ?>">Editar en Media</a></td></tr>
		<?php endforeach; ?></tbody></table>
		<?php echo wp_kses_post( paginate_links( array( 'base' => add_query_arg( array( 'page' => 'taxocommerce-images', 'tab' => 'labels' === $mode ? 'labels' : 'references', 'report_filter' => $report_filter, 'report_page' => '%#%' ), admin_url( 'admin.php' ) ), 'current' => $page, 'total' => $q->max_num_pages ) ) ); ?>
		</section>
		<?php
	}
}
