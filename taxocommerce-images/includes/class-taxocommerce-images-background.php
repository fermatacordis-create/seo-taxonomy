<?php
/**
 * Persistent, browser-independent processing for TaxoCommerce Images.
 *
 * Cleanup runs via signed, bounded internal requests without WP-Cron.
 * Other modes retain native WordPress scheduling.
 *
 * @package TaxoCommerceImages
 */
defined( 'ABSPATH' ) || exit;

final class TaxoCommerce_Images_Background {
	const OPTION = 'taxocommerce_images_background_jobs';
	const HOOK   = 'taxocommerce_images_background_tick';

	public static function boot() {
		add_action( self::HOOK, array( __CLASS__, 'tick' ) );
		add_action( 'wp_ajax_taxocommerce_images_background_control', array( __CLASS__, 'ajax' ) );
		add_action( 'wp_ajax_taxocommerce_images_cleanup_worker', array( __CLASS__, 'cleanup_worker' ) );
		add_action( 'wp_ajax_nopriv_taxocommerce_images_cleanup_worker', array( __CLASS__, 'cleanup_worker' ) );
		add_action( 'init', array( __CLASS__, 'watchdog' ), 99 );
	}

	public static function modes() {
		$modes = array( 'audit', 'labels', 'cleanup', 'optimize', 'webp' );
		foreach ( array_keys( TaxoCommerce_Images_Assignment::scopes() ) as $scope ) {
			$modes[] = 'assignment_' . $scope;
		}
		return $modes;
	}

	/**
	 * Keep each process in its own option to avoid lost updates when two jobs
	 * are controlled concurrently from different browser sessions.
	 */
	private static function all() {
		$jobs = array();
		foreach ( self::modes() as $mode ) {
			$job = self::state( $mode );
			if ( $job ) {
				$jobs[ $mode ] = $job;
			}
		}
		return $jobs;
	}

	public static function state( $mode ) {
		$job = get_option( 'taxocommerce_images_bg_' . sanitize_key( (string) $mode ), array() );
		return is_array( $job ) ? $job : array();
	}

	private static function save( $mode, $job ) {
		update_option( 'taxocommerce_images_bg_' . sanitize_key( (string) $mode ), $job, false );
	}

	/**
	 * Register a job after its existing scan state has been initialized.
	 * Only optimize/webp use their own ID snapshot and results.
	 */
	public static function start( $mode, $actor_id ) {
		$mode = sanitize_key( (string) $mode );
		if ( ! in_array( $mode, self::modes(), true ) ) {
			return new WP_Error( 'tci_background_mode', __( 'Proceso de imágenes no válido.', 'taxocommerce-images' ) );
		}
		$actor_id = absint( $actor_id );
		if ( ! $actor_id || ! user_can( $actor_id, 'manage_options' )
			|| ( in_array( $mode, array( 'optimize', 'webp' ), true ) && ! user_can( $actor_id, 'upload_files' ) ) ) {
			return new WP_Error( 'tci_background_access', __( 'Administrador sin permisos para ejecutar el proceso.', 'taxocommerce-images' ) );
		}
		$old = self::state( $mode );
		$scan = 'cleanup' === $mode ? TaxoCommerce_Images_Maintenance::state( 'cleanup' ) : array();
		$scan_run = (string) ( $scan['run'] ?? '' );
		if ( 'running' === ( $old['status'] ?? '' )
			&& ( 'cleanup' !== $mode || '' === (string) ( $old['scan_run'] ?? '' )
				|| $scan_run === (string) $old['scan_run'] ) ) {
			if ( 'cleanup' === $mode ) {
				$old['scan_run'] = $scan_run;
				$old['processed'] = absint( $scan['scanned'] ?? 0 );
				$old['total'] = absint( $scan['total'] ?? 0 );
				self::save( $mode, $old );
				self::dispatch_cleanup( $old );
			}
			return $old;
		}
		global $wpdb;
		$total = 0;
		$max = 0;
		if ( in_array( $mode, array( 'optimize', 'webp' ), true ) ) {
			$mimes = 'webp' === $mode
				? array( 'image/jpeg', 'image/png' )
				: array( 'image/jpeg', 'image/png', 'image/webp', 'image/avif' );
			$marks = implode( ',', array_fill( 0, count( $mimes ), '%s' ) );
			$sql = "SELECT COUNT(*) AS total, COALESCE(MAX(ID),0) AS max_id FROM %i WHERE post_type = 'attachment' AND post_status = 'inherit' AND post_mime_type IN ($marks)";
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Compute a live MIME-filtered count and maximum ID for an independent job snapshot; not a reusable cacheable query. Prepared statements and native cache invalidation verified; $sql is constructed with allowlisted MIME placeholders; all values and table identifiers are prepared.
			$rows = $wpdb->get_row( $wpdb->prepare( $sql, array_merge( array( $wpdb->posts ), $mimes ) ), ARRAY_A );
			$total = absint( $rows['total'] ?? 0 );
			$max = absint( $rows['max_id'] ?? 0 );
		}
		// A paused scan resumes from its saved cursor; completed/stopped
		// mutation jobs require an explicit fresh start.
		$resume = in_array( (string) ( $old['status'] ?? '' ), array( 'paused', 'error' ), true )
			&& ( 'cleanup' !== $mode || '' === (string) ( $old['scan_run'] ?? '' )
				|| $scan_run === (string) $old['scan_run'] );
		$job = $resume ? $old : array(
			'mode' => $mode,
			'run' => wp_generate_uuid4(),
			'actor' => $actor_id,
			'status' => 'running',
			'cursor' => 0,
			'total' => $total,
			'max_id' => $max,
			'processed' => 0,
			'changed' => 0,
			'errors' => array(),
			'started_at' => current_time( 'mysql' ),
			'last_tick' => 0,
		);
		$job['actor'] = $actor_id;
		$job['status'] = 'running';
		$job['next_run'] = time() + ( 'cleanup' === $mode ? 2 : 0 );
		if ( 'cleanup' === $mode ) {
			$job['scan_run'] = $scan_run;
			$job['processed'] = absint( $scan['scanned'] ?? 0 );
			$job['total'] = absint( $scan['total'] ?? 0 );
		}
		self::save( $mode, $job );
		if ( 'cleanup' === $mode ) {
			self::dispatch_cleanup( $job );
		} else {
			self::schedule( 0 );
		}
		return $job;
	}

	/**
	 * Run only the current read-only cleanup scan. This signed URL cannot
	 * start a scan, delete files, or mutate arbitrary WordPress content.
	 */
	private static function dispatch_cleanup( $job ) {
		if ( 'running' !== ( $job['status'] ?? '' ) || empty( $job['run'] ) ) {
			return;
		}
		$live = self::state( 'cleanup' );
		if ( ( $live['run'] ?? '' ) !== ( $job['run'] ?? '' ) ) {
			return;
		}
		$live['last_dispatch'] = time();
		self::save( 'cleanup', $live );
		$run = (string) $job['run'];
		wp_remote_post(
			admin_url( 'admin-ajax.php' ),
			array(
				'timeout' => 1,
				'blocking' => false,
				'redirection' => 0,
				'body' => array(
					'action' => 'taxocommerce_images_cleanup_worker',
					'run' => $run,
					'signature' => hash_hmac( 'sha256', $run, wp_salt( 'auth' ) ),
				),
			)
		);
	}

	/** Authenticated-by-HMAC server continuation independent of WP-Cron. */
	public static function cleanup_worker() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- This is not a browser submission: both fields are immediately checked against a server-secret HMAC, the saved job UUID and the current scan UUID before any work can execute.
		$run = isset( $_POST['run'] ) && is_scalar( $_POST['run'] )
			? sanitize_text_field( wp_unslash( $_POST['run'] ) ) : '';
		$signature = isset( $_POST['signature'] ) && is_scalar( $_POST['signature'] )
			? sanitize_text_field( wp_unslash( $_POST['signature'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		$job = self::state( 'cleanup' );
		$scan = TaxoCommerce_Images_Maintenance::state( 'cleanup' );
		$expected = hash_hmac( 'sha256', $run, wp_salt( 'auth' ) );
		if ( '' === $run || ! hash_equals( $expected, $signature )
			|| 'running' !== ( $job['status'] ?? '' ) || $run !== (string) ( $job['run'] ?? '' )
			|| (string) ( $job['scan_run'] ?? '' ) !== (string) ( $scan['run'] ?? '' ) ) {
			status_header( 403 );
			wp_die();
		}
		$next_run = absint( $job['next_run'] ?? 0 );
		if ( $next_run > time() ) {
			sleep( min( 15, $next_run - time() ) );
		}
		self::tick( 'cleanup' );
		wp_die();
	}

	private static function schedule( $delay ) {
		$at = time() + max( 0, (int) $delay );
		$existing = wp_next_scheduled( self::HOOK );
		if ( $existing && $existing <= $at ) {
			return;
		}
		if ( $existing ) {
			wp_unschedule_event( $existing, self::HOOK );
		}
		wp_schedule_single_event( $at, self::HOOK );
		if ( $at <= time() && function_exists( 'spawn_cron' ) && ! ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) ) {
			spawn_cron( time() );
		}
	}

	/** Re-arm a missing cron event if another WordPress request is available. */
	public static function watchdog() {
		$soonest = null;
		foreach ( self::all() as $job ) {
			if ( 'running' !== ( $job['status'] ?? '' ) ) {
				continue;
			}
			$next = absint( $job['next_run'] ?? 0 );
			if ( 'cleanup' === (string) ( $job['mode'] ?? '' ) ) {
				// Upgrade old persisted jobs without discarding their Media cursor.
				if ( empty( $job['scan_run'] ) ) {
					$scan = TaxoCommerce_Images_Maintenance::state( 'cleanup' );
					if ( 'running' === ( $scan['status'] ?? '' ) && ! empty( $scan['run'] ) ) {
						$job['scan_run'] = (string) $scan['run'];
						$job['processed'] = absint( $scan['scanned'] ?? 0 );
						$job['total'] = absint( $scan['total'] ?? 0 );
						self::save( 'cleanup', $job );
					}
				}
				if ( ! wp_doing_ajax() && time() >= $next
					&& time() - absint( $job['last_dispatch'] ?? 0 ) >= 20 ) {
					self::dispatch_cleanup( $job );
				}
				continue;
			}
			$soonest = null === $soonest ? $next : min( $soonest, $next );
		}
		if ( null !== $soonest && ! wp_next_scheduled( self::HOOK ) ) {
			self::schedule( max( 0, $soonest - time() ) );
		}
	}

	/**
	 * One bounded chunk per cron tick. The actor is checked again before
	 * executing and the former current user is always restored.
	 */
	public static function tick( $only_mode = '' ) {
		$only_mode = 'cleanup' === $only_mode ? 'cleanup' : '';
		if ( ! TaxoCommerce_Images_Maintenance::lock( 'background_worker' ) ) {
			if ( '' === $only_mode ) {
				self::schedule( 15 );
			}
			return;
		}
		$previous_user = get_current_user_id();
		try {
			$jobs = self::all();
			$selected = '';
			$soonest = null;
			foreach ( $jobs as $mode => $job ) {
				if ( ( '' === $only_mode && 'cleanup' === $mode )
					|| ( '' !== $only_mode && $only_mode !== $mode ) ) {
					continue;
				}
				if ( 'running' !== ( $job['status'] ?? '' ) ) {
					continue;
				}
				$next = absint( $job['next_run'] ?? 0 );
				if ( $next <= time() && ( '' === $selected || $next < absint( $jobs[ $selected ]['next_run'] ?? 0 ) ) ) {
					$selected = $mode;
				}
				$soonest = null === $soonest ? $next : min( $soonest, $next );
			}
			if ( '' === $selected ) {
				if ( '' === $only_mode && null !== $soonest ) {
					self::schedule( max( 1, $soonest - time() ) );
				}
				return;
			}
			$job = $jobs[ $selected ];
			$actor = absint( $job['actor'] ?? 0 );
			if ( ! user_can( $actor, 'manage_options' )
				|| ( in_array( $selected, array( 'optimize', 'webp' ), true ) && ! user_can( $actor, 'upload_files' ) ) ) {
				$job['status'] = 'error';
				$job['message'] = __( 'El administrador ya no dispone de permisos.', 'taxocommerce-images' );
				self::save( $selected, $job );
				return;
			}
			wp_set_current_user( $actor );
			try {
				$result = self::chunk( $selected, $job );
				if ( is_wp_error( $result ) ) {
					if ( in_array( $result->get_error_code(), array( 'tci_audit_background_busy', 'tci_background_assignment_busy', 'tci_mutation_busy', 'tci_busy' ), true ) ) {
						$job['message'] = __( 'Otro lote está activo. Se reintentará.', 'taxocommerce-images' );
					} else {
						throw new RuntimeException( $result->get_error_message() );
					}
				} else {
					$job = $result;
					$job['failures'] = 0;
				}
			} catch ( Throwable $error ) {
				$job['failures'] = absint( $job['failures'] ?? 0 ) + 1;
				$job['message'] = sanitize_text_field( $error->getMessage() );
				if ( $job['failures'] >= 3 ) {
					$job['status'] = 'error';
				}
			}
			// A pause/stop (or new run) can be requested while the chunk runs.
			// Never overwrite a more recent administrator decision.
			$live = self::state( $selected );
			if ( ! empty( $live['run'] ) && $live['run'] !== ( $job['run'] ?? '' ) ) {
				return;
			}
			if ( in_array( (string) ( $live['status'] ?? '' ), array( 'paused', 'stopped' ), true ) ) {
				$job['status'] = $live['status'];
			}
			$job['last_tick'] = time();
			$delay = max( 1, (int) ceil( TaxoCommerce_Images_Load::delay_ms( $selected ) / 1000 ) );
			$job['next_run'] = time() + $delay;
			self::save( $selected, $job );
			$next = null;
			foreach ( self::all() as $item ) {
				if ( 'cleanup' === (string) ( $item['mode'] ?? '' )
					|| 'running' !== ( $item['status'] ?? '' ) ) {
					continue;
				}
				$when = absint( $item['next_run'] ?? time() );
				$next = null === $next ? $when : min( $next, $when );
			}
			if ( '' === $only_mode && null !== $next ) {
				self::schedule( max( 1, $next - time() ) );
			}
		} finally {
			wp_set_current_user( $previous_user );
			TaxoCommerce_Images_Maintenance::unlock( 'background_worker' );
			if ( 'cleanup' === $only_mode ) {
				$latest = self::state( 'cleanup' );
				if ( 'running' === ( $latest['status'] ?? '' ) ) {
					self::dispatch_cleanup( $latest );
				}
			} else {
				self::watchdog();
			}
		}
	}

	private static function chunk( $mode, $job ) {
		if ( 'audit' === $mode ) {
			$audit = new TaxoCommerce_Images_Audit();
			$state = $audit->run_background_batch();
			if ( is_wp_error( $state ) ) {
				return $state;
			}
			$job['processed'] = absint( $state['scanned'] ?? 0 );
			$job['total'] = absint( $state['total'] ?? 0 );
			if ( 'completed' === ( $state['status'] ?? '' ) ) {
				$job['status'] = 'complete';
			}
			return $job;
		}
		if ( in_array( $mode, array( 'labels', 'cleanup' ), true ) ) {
			if ( ! TaxoCommerce_Images_Maintenance::lock( $mode ) ) {
				return new WP_Error( 'tci_busy', 'El proceso ya está ejecutándose.' );
			}
			try {
				$measurement = TaxoCommerce_Images_Load::begin();
				$before = TaxoCommerce_Images_Maintenance::state( $mode );
				$state = TaxoCommerce_Images_Maintenance::scan( $mode );
				if ( is_wp_error( $state ) ) {
					return $state;
				}
				TaxoCommerce_Images_Load::finish( $mode, $measurement, max( 0, absint( $state['scanned'] ?? 0 ) - absint( $before['scanned'] ?? 0 ) ) );
				$job['processed'] = absint( $state['scanned'] ?? 0 );
				$job['total'] = absint( $state['total'] ?? 0 );
				if ( 'complete' === ( $state['status'] ?? '' ) ) {
					$job['status'] = 'complete';
				}
			} finally {
				TaxoCommerce_Images_Maintenance::unlock( $mode );
			}
			return $job;
		}
		if ( 0 === strpos( $mode, 'assignment_' ) ) {
			$scope = substr( $mode, strlen( 'assignment_' ) );
			$state = TaxoCommerce_Images_Assignment::run_background_batch( $scope );
			if ( is_wp_error( $state ) ) {
				return $state;
			}
			$job['processed'] = absint( $state['seen'] ?? 0 );
			$job['total'] = absint( $state['total'] ?? 0 );
			$job['changed'] = absint( $state['assigned'] ?? 0 );
			if ( 'complete' === ( $state['status'] ?? '' ) ) {
				$job['status'] = 'complete';
			}
			return $job;
		}
		if ( ! in_array( $mode, array( 'optimize', 'webp' ), true ) ) {
			return new WP_Error( 'tci_mode', 'Modo no compatible.' );
		}
		if ( ! TaxoCommerce_Images_Maintenance::lock( 'mutation' ) ) {
			return new WP_Error( 'tci_mutation_busy', 'Otro trabajo está modificando las imágenes.' );
		}
		try {
			global $wpdb;
			$mimes = 'webp' === $mode
				? array( 'image/jpeg', 'image/png' )
				: array( 'image/jpeg', 'image/png', 'image/webp', 'image/avif' );
			$marks = implode( ',', array_fill( 0, count( $mimes ), '%s' ) );
			$sql = "SELECT ID FROM %i WHERE post_type = 'attachment' AND post_status = 'inherit' AND post_mime_type IN ($marks) AND ID > %d AND ID <= %d ORDER BY ID ASC LIMIT 1";
			$priority = TaxoCommerce_Images_Load::priority( $mode );
			$budget = array( 1 => 3, 2 => 5, 3 => 7, 4 => 10, 5 => 14 )[ $priority ];
			$php_limit = (int) ini_get( 'max_execution_time' );
			if ( $php_limit > 0 ) {
				$budget = min( $budget, max( 1, $php_limit - 8 ) );
			}
			$deadline = microtime( true ) + $budget;
			$maximum = min( 40, TaxoCommerce_Images_Load::batch_size( $mode ) );
			$processed = 0;
			$measure = TaxoCommerce_Images_Load::begin();
			while ( $processed < $maximum ) {
				if ( $processed > 0 && microtime( true ) >= $deadline ) {
					break;
				}
				$memory = TaxoCommerce_Images_Load::memory_percent();
				if ( $processed > 0 && null !== $memory && $memory >= 78 ) {
					break;
				}
				$live = self::state( $mode );
				if ( in_array( (string) ( $live['status'] ?? '' ), array( 'paused', 'stopped' ), true ) ) {
					$job['status'] = $live['status'];
					break;
				}
				$params = array_merge(
					array( $wpdb->posts ), $mimes,
					array( absint( $job['cursor'] ?? 0 ), absint( $job['max_id'] ?? 0 ) )
				);
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Use a freshly prepared cursor query to claim the next pending attachment; cached IDs risk duplicate or skipped work. Prepared statements and native cache invalidation verified; SQL identifiers and values are prepared; MIME types are a closed allowlist.
				$id = absint( $wpdb->get_var( $wpdb->prepare( $sql, $params ) ) );
				if ( ! $id ) {
					$job['status'] = 'complete';
					break;
				}
				$item = 'webp' === $mode
					? taxocommerce_images_webp_process_attachment( $id )
					: taxocommerce_images_optimizer_process_attachment( $id );
				$job['cursor'] = $id;
				++$job['processed'];
				++$processed;
				$job['changed'] = absint( $job['changed'] ?? 0 )
					+ absint( $item[ 'webp' === $mode ? 'converted' : 'optimized' ] ?? 0 );
				if ( ! empty( $item['errors'] ) ) {
					$job['errors'] = array_slice(
						array_merge( (array) ( $job['errors'] ?? array() ), (array) $item['errors'] ), -30
					);
				}
				// Persist after each image: a failed PHP request must not lose
				// successful file transformations or restart from the first item.
				self::save( $mode, $job );
			}
			TaxoCommerce_Images_Load::finish( $mode, $measure, $processed );
			return $job;
		} finally {
			TaxoCommerce_Images_Maintenance::unlock( 'mutation' );
		}
	}

	/** Stop an obsolete job (for example, when its audit snapshot is reset). */
	public static function stop( $mode ) {
		$job = self::state( $mode );
		if ( $job ) {
			$job['status'] = 'stopped';
			self::save( $mode, $job );
		}
	}

	public static function ajax() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Sin permisos.' ), 403 );
		}
		check_ajax_referer( 'taxocommerce_images_background', 'nonce' );
		$mode = isset( $_POST['mode'] ) && is_scalar( $_POST['mode'] ) ? sanitize_key( wp_unslash( $_POST['mode'] ) ) : '';
		$operation = isset( $_POST['operation'] ) && is_scalar( $_POST['operation'] ) ? sanitize_key( wp_unslash( $_POST['operation'] ) ) : 'status';
		if ( ! in_array( $mode, self::modes(), true ) || ! in_array( $operation, array( 'status', 'start', 'pause', 'resume', 'stop', 'drive' ), true ) ) {
			wp_send_json_error( array( 'message' => 'Operación no válida.' ), 400 );
		}
		$job = self::state( $mode );
		if ( 'drive' === $operation ) {
			if ( 'cleanup' !== $mode ) {
				wp_send_json_error( array( 'message' => 'Operación no disponible para este proceso.' ), 400 );
			}
			if ( 'running' === ( $job['status'] ?? '' ) ) {
				self::tick( 'cleanup' );
			}
			$job = self::state( $mode );
		} elseif ( 'start' === $operation ) {
			if ( ! in_array( $mode, array( 'optimize', 'webp' ), true ) ) {
				wp_send_json_error( array( 'message' => 'Inicia este análisis desde su botón habitual.' ), 400 );
			}
			$job = self::start( $mode, get_current_user_id() );
		} elseif ( 'resume' === $operation && ! $job && ! in_array( $mode, array( 'optimize', 'webp' ), true ) ) {
			// Adopt a previously started scan rather than restarting its cursor.
			$existing = array();
			if ( 'audit' === $mode ) {
				$existing = ( new TaxoCommerce_Images_Audit() )->state();
			} elseif ( in_array( $mode, array( 'labels', 'cleanup' ), true ) ) {
				$existing = TaxoCommerce_Images_Maintenance::state( $mode );
			} elseif ( 0 === strpos( $mode, 'assignment_' ) ) {
				$existing = TaxoCommerce_Images_Assignment::assignment_state( substr( $mode, strlen( 'assignment_' ) ) );
			}
			if ( 'running' !== ( $existing['status'] ?? '' ) ) {
				wp_send_json_error( array( 'message' => 'No hay un proceso interrumpido que reanudar.' ), 400 );
			}
			$job = self::start( $mode, get_current_user_id() );
		} elseif ( in_array( $operation, array( 'pause', 'resume', 'stop' ), true ) && $job ) {
			if ( 'resume' === $operation && in_array( $job['status'], array( 'paused', 'error' ), true ) ) {
				$job = self::start( $mode, get_current_user_id() );
			} elseif ( 'pause' === $operation && 'running' === $job['status'] ) {
				$job['status'] = 'paused';
				self::save( $mode, $job );
			} elseif ( 'stop' === $operation ) {
				$job['status'] = 'stopped';
				self::save( $mode, $job );
			}
		}
		if ( is_wp_error( $job ) ) {
			wp_send_json_error( array( 'message' => $job->get_error_message() ), 400 );
		}
		$job['cron_disabled'] = 'cleanup' !== $mode && defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON;
		$last_activity = max( absint( $job['last_tick'] ?? 0 ), absint( $job['last_dispatch'] ?? 0 ) );
		$job['stalled'] = 'running' === ( $job['status'] ?? '' )
			&& ( 0 === $last_activity || time() - $last_activity > 120 );
		wp_send_json_success( array( 'job' => $job ) );
	}

	public static function render_panel( $mode ) {
		$job = self::state( $mode );
		$status = isset( $job['status'] ) ? sanitize_key( $job['status'] ) : 'not-started';
		echo '<div class="tci-background-panel" data-tci-bg-mode="' . esc_attr( $mode ) . '">';
		echo '<p><strong>' . esc_html__( 'Ejecución en segundo plano:', 'taxocommerce-images' ) . '</strong> <span data-tci-bg-status>' . esc_html( $status ) . '</span></p>';
		echo '<p class="description" data-tci-bg-progress>' . esc_html(
			'cleanup' === $mode
				? __( 'Escaneo por peticiones internas sin WP-Cron. Si el alojamiento bloquea conexiones internas, mantén esta pestaña abierta para continuar automáticamente.', 'taxocommerce-images' )
				: __( 'Este trabajo continuará en el servidor sin mantener esta pestaña abierta, siempre que WordPress Cron esté operativo.', 'taxocommerce-images' )
		) . '</p>';
		echo '<button type="button" class="button" data-tci-bg-control="pause">' . esc_html__( 'Pausar servidor', 'taxocommerce-images' ) . '</button> ';
		echo '<button type="button" class="button" data-tci-bg-control="resume">' . esc_html__( 'Reanudar', 'taxocommerce-images' ) . '</button> ';
		echo '<button type="button" class="button" data-tci-bg-control="stop">' . esc_html__( 'Detener', 'taxocommerce-images' ) . '</button>';
		echo '</div>';
	}
}
