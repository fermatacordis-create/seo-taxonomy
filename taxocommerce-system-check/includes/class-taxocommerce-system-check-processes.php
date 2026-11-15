<?php
/**
 * Application task monitor and cooperative stop controls.
 *
 * Never attempts to terminate an operating-system process or an active PHP
 * request. Only pending application callbacks and supported logical workers
 * are stoppable.
 *
 * @package TaxoCommerceSystemCheck
 */

defined( 'ABSPATH' ) || exit;

final class TaxoCommerce_System_Check_Processes {
	const ACTION = 'taxocommerce_system_check_process_control';
	const MAX_ACTIONS = 100;

	public static function boot() {
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'handle' ) );
	}

	/**
	 * Read bounded Action Scheduler records, including tasks from unrelated
	 * plugins. No table-name assumptions or external plugin requirements.
	 *
	 * @return array
	 */
	public static function actions() {
		if ( ! function_exists( 'as_get_scheduled_actions' ) ) {
			return array();
		}
		$found = array();
		foreach ( array( 'in-progress', 'pending' ) as $status ) {
			$ids = as_get_scheduled_actions(
				array( 'status' => $status, 'per_page' => self::MAX_ACTIONS, 'orderby' => 'date', 'order' => 'ASC' ),
				'ids'
			);
			foreach ( (array) $ids as $id ) {
				$id = absint( $id );
				if ( ! $id || ! class_exists( 'ActionScheduler' ) ) {
					continue;
				}
				try {
					$action = ActionScheduler::store()->fetch_action( $id );
					if ( ! is_object( $action ) || ! method_exists( $action, 'get_hook' ) ) {
						continue;
					}
					$hook  = (string) $action->get_hook();
					$group = (string) $action->get_group();
					$found[] = array(
						'type'       => 'action',
						'id'         => $id,
						'hook'       => $hook,
						'group'      => $group,
						'status'     => $status,
						'can_cancel' => 'pending' === $status && self::is_safe_hook( $hook, $group ),
					);
				} catch ( Throwable $exception ) {
					continue;
				}
			}
		}
		return $found;
	}

	/**
	 * Treat only application-namespaced tasks as directly cancellable.
	 * WooCommerce payment/order infrastructure and WP core must remain intact.
	 */
	private static function is_safe_hook( $hook, $group ) {
		// These logical jobs require their own cooperative stop/cleanup;
		// removing their queue action alone would leave a stale running state.
		if ( in_array( $hook, array( 'taxocommerce_ie_export_background_worker', 'taxocommerce_system_check_process_url_batch' ), true ) ) {
			return false;
		}
		if ( preg_match( '/^(woocommerce_|wc_|action_scheduler_|wp_|delete_|recovery_mode_)/', $hook ) ) {
			return false;
		}
		$allowed = (bool) preg_match( '/^(taxocommerce_|tcd_|seo_)/', $hook );
		$allowed = $allowed || (bool) preg_match( '/^taxocommerce[-_]/', $group );
		/**
		 * Allow independently developed plugins to opt into task cancellation.
		 * Callbacks must only authorize their own non-critical jobs.
		 */
		return (bool) apply_filters( 'taxocommerce_system_check_can_cancel_action', $allowed, $hook, $group );
	}

	/**
	 * Enumerate bounded WP-Cron entries without modifying their schedules.
	 */
	public static function cron() {
		if ( ! function_exists( '_get_cron_array' ) ) {
			return array();
		}
		$crons = _get_cron_array();
		if ( ! is_array( $crons ) ) {
			return array();
		}
		$rows = array();
		foreach ( $crons as $timestamp => $hooks ) {
			foreach ( (array) $hooks as $hook => $entries ) {
				foreach ( (array) $entries as $entry ) {
					if ( count( $rows ) >= self::MAX_ACTIONS ) {
						return $rows;
					}
					$args = is_array( $entry['args'] ?? null ) ? $entry['args'] : array();
					$rows[] = array(
						'type'       => 'cron',
						'hook'       => (string) $hook,
						'due'        => absint( $timestamp ),
						'schedule'   => sanitize_key( (string) ( $entry['schedule'] ?? '' ) ),
						'args'       => $args,
						'status'     => (int) $timestamp <= time() ? 'due' : 'scheduled',
						'can_cancel' => self::is_safe_hook( (string) $hook, '' ),
					);
				}
			}
		}
		return $rows;
	}

	/**
	 * Native cooperative integration with the two pre-existing worker states.
	 * Both adapters are optional and never load the dependent plugin.
	 */
	public static function workers() {
		$rows = array();
		if ( class_exists( 'TCD_Academy', false ) && is_callable( array( 'TCD_Academy', 'state' ) ) ) {
			$state = TCD_Academy::state();
			$rows[] = array(
				'id' => 'dependiente', 'label' => __( 'Aprendizaje de Dependiente', 'taxocommerce-system-check' ),
				'status' => sanitize_key( (string) ( $state['status'] ?? 'idle' ) ),
				'detail' => sprintf( 'L%d / %s', absint( $state['lesson'] ?? 0 ), sanitize_key( (string) ( $state['phase'] ?? '' ) ) ),
				'can_stop' => 'running' === ( $state['status'] ?? '' ),
			);
		}
		if ( class_exists( 'TaxoCommerce_Import_Export_Batch', false ) ) {
			$state = get_option( 'taxocommerce_ie_batch_state', array() );
			if ( is_array( $state ) ) {
				$rows[] = array(
					'id' => 'import', 'label' => __( 'Cola de importación', 'taxocommerce-system-check' ),
					'status' => sanitize_key( (string) ( $state['status'] ?? 'idle' ) ),
					'detail' => is_scalar( $state['current_file'] ?? '' ) ? sanitize_text_field( (string) $state['current_file'] ) : '',
					'can_stop' => ! empty( $state['enabled'] ) || in_array( (string) ( $state['status'] ?? '' ), array( 'processing', 'waiting_next', 'stopping' ), true ),
				);
			}
		}
		if ( class_exists( 'TaxoCommerce_System_Check_URL_Audit', false )
			&& is_callable( array( 'TaxoCommerce_System_Check_URL_Audit', 'process_rows' ) ) ) {
			$rows = array_merge( $rows, TaxoCommerce_System_Check_URL_Audit::process_rows() );
		}
		// Export jobs are per owner. Discover their identifiers via the
		// application's already scheduled action, not by scanning user data.
		if ( class_exists( 'TaxoCommerce_Import_Export_Exporter', false )
			&& class_exists( 'ActionScheduler' ) ) {
			$seen = array();
			foreach ( self::actions() as $entry ) {
				if ( 'taxocommerce_ie_export_background_worker' !== $entry['hook'] ) {
					continue;
				}
				try {
					$job_action = ActionScheduler::store()->fetch_action( $entry['id'] );
					if ( ! is_object( $job_action ) || ! method_exists( $job_action, 'get_args' ) ) {
						continue;
					}
					$args = $job_action->get_args();
					$user_id = absint( $args[0] ?? 0 );
					$job_id = sanitize_text_field( (string) ( $args[1] ?? '' ) );
					if ( ! $user_id || ! preg_match( '/^[a-f0-9-]{36}$/i', $job_id ) || isset( $seen[ $job_id ] ) ) {
						continue;
					}
					$state = get_transient( 'taxocommerce_ie_export_' . $user_id );
					if ( ! is_array( $state ) || ! hash_equals( $job_id, (string) ( $state['id'] ?? '' ) ) || ! empty( $state['complete'] ) ) {
						continue;
					}
					$seen[ $job_id ] = true;
					$rows[] = array(
						'id' => 'export:' . $user_id . ':' . $job_id,
						'label' => __( 'Exportación CSV', 'taxocommerce-system-check' ),
						'status' => sanitize_key( (string) ( $state['status'] ?? 'queued' ) ),
						'detail' => sanitize_text_field( (string) ( $state['type_label'] ?? '' ) ),
						'can_stop' => ! in_array( (string) ( $state['status'] ?? '' ), array( 'failed', 'cancelling', 'cancelled', 'completed' ), true ),
					);
				} catch ( Throwable $exception ) {
					continue;
				}
			}
		}
		return (array) apply_filters( 'taxocommerce_system_check_process_workers', $rows );
	}

	private static function stop_worker( $id ) {
		if ( 'dependiente' === $id && class_exists( 'TCD_Academy', false ) ) {
			$state = TCD_Academy::state();
			if ( 'running' !== ( $state['status'] ?? '' ) || empty( $state['run_id'] ) ) {
				return false;
			}
			update_option( 'tcd_learning_stop', (string) $state['run_id'], false );
			TCD_Academy::save( array( 'status' => 'stopped', 'last_message' => __( 'Detenido desde System Check. No se iniciarán nuevos lotes.', 'taxocommerce-system-check' ) ) );
			wp_clear_scheduled_hook( TCD_Academy::CRON_HOOK );
			if ( function_exists( 'as_unschedule_all_actions' ) ) {
				as_unschedule_all_actions( TCD_Academy::CRON_HOOK );
			}
			return true;
		}
		if ( 'import' === $id && class_exists( 'TaxoCommerce_Import_Export_Batch', false ) ) {
			$state = get_option( 'taxocommerce_ie_batch_state', array() );
			if ( ! is_array( $state ) || ( empty( $state['enabled'] ) && ! in_array( (string) ( $state['status'] ?? '' ), array( 'processing', 'waiting_next', 'stopping' ), true ) ) ) {
				return false;
			}
			$state['enabled'] = false;
			$state['stop_requested'] = true;
			$state['status'] = 'stopping';
			$state['message'] = __( 'Detención solicitada desde System Check; se conserva el cursor.', 'taxocommerce-system-check' );
			update_option( 'taxocommerce_ie_batch_state', $state, false );
			wp_clear_scheduled_hook( 'taxocommerce_ie_batch_worker' );
			if ( function_exists( 'as_unschedule_all_actions' ) ) {
				as_unschedule_all_actions( 'taxocommerce_ie_batch_worker', array(), 'taxocommerce-import-export-batch' );
			}
			return true;
		}
		if ( 0 === strpos( $id, 'http:' ) && class_exists( 'TaxoCommerce_System_Check_URL_Audit', false ) ) {
			return TaxoCommerce_System_Check_URL_Audit::stop_process( absint( substr( $id, 5 ) ) );
		}
		if ( 0 === strpos( $id, 'export:' ) && class_exists( 'TaxoCommerce_Import_Export_Exporter', false ) ) {
			$parts = explode( ':', $id, 3 );
			$user_id = absint( $parts[1] ?? 0 );
			$job_id = sanitize_text_field( (string) ( $parts[2] ?? '' ) );
			if ( $user_id < 1 || ! preg_match( '/^[a-f0-9-]{36}$/i', $job_id ) ) {
				return false;
			}
			$key = 'taxocommerce_ie_export_' . $user_id;
			$state = get_transient( $key );
			if ( ! is_array( $state ) || ! hash_equals( (string) ( $state['id'] ?? '' ), $job_id ) || ! empty( $state['complete'] ) ) {
				return false;
			}
			update_option( 'taxocommerce_ie_export_cancel_' . $user_id, $job_id, false );
			$state['status'] = 'cancelling';
			set_transient( $key, $state, TaxoCommerce_Import_Export_Exporter::TTL );
			$args = array( $user_id, $job_id );
			$hook = TaxoCommerce_Import_Export_Exporter::WORKER_HOOK;
			$group = TaxoCommerce_Import_Export_Exporter::WORKER_GROUP;
			// Leave an existing scheduled worker intact: it must acquire the
			// export lock to safely delete its partial CSV file.
			if ( function_exists( 'as_schedule_single_action' ) ) {
				as_schedule_single_action( time() + 5, $hook, $args, $group, false );
			} elseif ( ! wp_next_scheduled( $hook, $args ) ) {
				wp_schedule_single_event( time() + 5, $hook, $args );
			}
			return true;
		}
		// Optional third-party adapters may implement a safe, cooperative stop.
		return (bool) apply_filters( 'taxocommerce_system_check_stop_worker', false, $id );
	}

	private static function cancel_action( $id ) {
		if ( ! $id || ! class_exists( 'ActionScheduler' ) ) {
			return false;
		}
		try {
			$store = ActionScheduler::store();
			$action = $store->fetch_action( $id );
			if ( ! is_object( $action ) || ! method_exists( $action, 'get_hook' )
				|| 'pending' !== $store->get_status( $id )
				|| ! self::is_safe_hook( (string) $action->get_hook(), (string) $action->get_group() ) ) {
				return false;
			}
			$store->cancel_action( $id );
			return true;
		} catch ( Throwable $exception ) {
			return false;
		}
	}

	private static function cancel_cron( $hook, $timestamp, $signature ) {
		foreach ( self::cron() as $row ) {
			if ( $row['hook'] === $hook && $row['due'] === $timestamp && $row['can_cancel']
				&& hash_equals( md5( wp_json_encode( $row['args'] ) ), $signature ) ) {
				return false !== wp_unschedule_event( $timestamp, $hook, $row['args'] );
			}
		}
		return false;
	}

	public static function handle() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Acceso no autorizado.', 'taxocommerce-system-check' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( self::ACTION );
		$operation = isset( $_POST['operation'] ) ? sanitize_key( wp_unslash( $_POST['operation'] ) ) : '';
		$success = 0;
		if ( 'stop_worker' === $operation ) {
			$id = isset( $_POST['worker'] ) && is_scalar( $_POST['worker'] ) ? sanitize_text_field( wp_unslash( $_POST['worker'] ) ) : '';
			$success = self::stop_worker( $id ) ? 1 : 0;
		} elseif ( 'cancel_action' === $operation ) {
			$id = isset( $_POST['id'] ) ? absint( wp_unslash( $_POST['id'] ) ) : 0;
			$success = self::cancel_action( $id ) ? 1 : 0;
		} elseif ( 'cancel_cron' === $operation ) {
			$hook = isset( $_POST['hook'] ) ? sanitize_key( wp_unslash( $_POST['hook'] ) ) : '';
			$when = isset( $_POST['when'] ) ? absint( wp_unslash( $_POST['when'] ) ) : 0;
			$sig  = isset( $_POST['signature'] ) ? sanitize_text_field( wp_unslash( $_POST['signature'] ) ) : '';
			$success = self::cancel_cron( $hook, $when, $sig ) ? 1 : 0;
		} elseif ( 'stop_all' === $operation ) {
			foreach ( self::workers() as $worker ) {
				if ( ! empty( $worker['can_stop'] ) && self::stop_worker( (string) $worker['id'] ) ) {
					++$success;
				}
			}
			foreach ( self::actions() as $action ) {
				if ( $action['can_cancel'] && self::cancel_action( (int) $action['id'] ) ) {
					++$success;
				}
			}
			foreach ( self::cron() as $event ) {
				if ( $event['can_cancel'] && self::cancel_cron( $event['hook'], $event['due'], md5( wp_json_encode( $event['args'] ) ) ) ) {
					++$success;
				}
			}
		}
		wp_safe_redirect( add_query_arg( 'stopped', absint( $success ), admin_url( 'admin.php?page=taxocommerce-system-check&tcs_tab=processes' ) ) );
		exit;
	}

	private static function button( $operation, $label, $fields = array(), $confirm = false ) {
		if ( $confirm ) {
			echo '<details><summary>' . esc_html__( 'Mostrar confirmación de parada global', 'taxocommerce-system-check' ) . '</summary>';
		}
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '">';
		echo '<input type="hidden" name="operation" value="' . esc_attr( $operation ) . '">';
		foreach ( $fields as $key => $value ) {
			echo '<input type="hidden" name="' . esc_attr( $key ) . '" value="' . esc_attr( (string) $value ) . '">';
		}
		wp_nonce_field( self::ACTION );
		echo '<button type="submit" class="button"';
		echo '>' . esc_html( $label ) . '</button></form>';
		if ( $confirm ) {
			echo '</details>';
		}
	}

	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		// FTPS deployments are not atomic: load the optional detail module lazily
		// so an older bootstrap cannot leave the Processes tab in a fatal state.
		$details_available = class_exists( 'TaxoCommerce_System_Check_Process_Details', false );
		if ( ! $details_available ) {
			$details_file = __DIR__ . '/class-taxocommerce-system-check-process-details.php';
			if ( is_readable( $details_file ) ) {
				require_once $details_file;
				$details_available = class_exists( 'TaxoCommerce_System_Check_Process_Details', false );
			}
		}
		$workers = self::workers();
		$actions = self::actions();
		$crons   = self::cron();
		echo '<div class="wrap"><h2>' . esc_html__( 'Procesos de WordPress y WooCommerce', 'taxocommerce-system-check' ) . '</h2>';
		echo '<p>' . esc_html__( 'Tareas de aplicación, no procesos Linux. Detener no interrumpe una petición PHP ya en marcha. Se preservan los trabajos críticos de WordPress y WooCommerce.', 'taxocommerce-system-check' ) . '</p>';
		if ( class_exists( 'TaxoCommerce_System_Check_Queue_Health' ) ) {
			echo '<h3>' . esc_html__( 'Queue health diagnostics (read-only)', 'taxocommerce-system-check' ) . '</h3>';
			echo '<p>' . esc_html__( 'WP-Cron and Action Scheduler signals are measured when this page opens. These measurements do not start, cancel or resume jobs, and cannot prove a PHP runner is stopped.', 'taxocommerce-system-check' ) . '</p>';
			echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Metric', 'taxocommerce-system-check' ) . '</th><th>' . esc_html__( 'Value', 'taxocommerce-system-check' ) . '</th><th>' . esc_html__( 'Status', 'taxocommerce-system-check' ) . '</th><th>' . esc_html__( 'Details', 'taxocommerce-system-check' ) . '</th></tr></thead><tbody>';
			TaxoCommerce_System_Check_Queue_Health::render_cron_rows();
			TaxoCommerce_System_Check_Queue_Health::render_scheduler_rows();
			echo '</tbody></table>';
		}
		if ( $details_available ) {
			$queue_state = class_exists( 'TaxoCommerce_System_Check_Queue_Health' ) ? TaxoCommerce_System_Check_Queue_Health::scheduler() : array();
			if ( ! empty( $queue_state['last_failed_id'] ) ) {
				echo '<p><a class="button button-secondary" href="' . esc_url( TaxoCommerce_System_Check_Process_Details::url( 'action', array( 'tcs_item' => (int) $queue_state['last_failed_id'] ) ) ) . '">' . esc_html__( 'Ver log del último fallo', 'taxocommerce-system-check' ) . '</a></p>';
			}
			TaxoCommerce_System_Check_Process_Details::render_selected();
		} else {
			echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'Process diagnostics are unavailable until the plugin update completes.', 'taxocommerce-system-check' ) . '</p></div>';
		}
		self::button( 'stop_all', __( 'Detener los trabajos cancelables mostrados', 'taxocommerce-system-check' ), array(), true );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only status feedback.
		$stopped = isset( $_GET['stopped'] ) ? absint( wp_unslash( $_GET['stopped'] ) ) : null;
		if ( null !== $stopped ) {
			/* translators: %d: Number of jobs whose cancellation was requested or completed. */
			echo '<p role="status">' . esc_html( sprintf( __( 'Operaciones de detención solicitadas o completadas: %d.', 'taxocommerce-system-check' ), $stopped ) ) . '</p>';
		}
		echo '<h3>' . esc_html__( 'Procesos de larga duración', 'taxocommerce-system-check' ) . '</h3>';
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Trabajo', 'taxocommerce-system-check' ) . '</th><th>' . esc_html__( 'Estado', 'taxocommerce-system-check' ) . '</th><th>' . esc_html__( 'Detalle', 'taxocommerce-system-check' ) . '</th><th>' . esc_html__( 'Acción', 'taxocommerce-system-check' ) . '</th><th>' . esc_html__( 'Diagnóstico', 'taxocommerce-system-check' ) . '</th></tr></thead><tbody>';
		foreach ( $workers as $worker ) {
			echo '<tr><td>' . esc_html( $worker['label'] ) . '</td><td>' . esc_html( $worker['status'] ) . '</td><td>' . esc_html( $worker['detail'] ?? '' ) . '</td><td>';
			if ( ! empty( $worker['can_stop'] ) ) {
				self::button( 'stop_worker', __( 'Detener', 'taxocommerce-system-check' ), array( 'worker' => $worker['id'] ) );
			}
			if ( $details_available ) {
				echo '</td><td><a class="button" href="' . esc_url( TaxoCommerce_System_Check_Process_Details::url( 'worker', array( 'tcs_worker' => $worker['id'] ) ) ) . '">' . esc_html__( 'Ver detalles', 'taxocommerce-system-check' ) . '</a></td></tr>';
			} else {
				echo '</td><td>' . esc_html__( 'Unavailable', 'taxocommerce-system-check' ) . '</td></tr>';
			}
		}
		if ( ! $workers ) {
			echo '<tr><td colspan="5">' . esc_html__( 'No hay procesos registrados.', 'taxocommerce-system-check' ) . '</td></tr>';
		}
		echo '</tbody></table><h3>Action Scheduler</h3>';
		echo '<table class="widefat striped"><thead><tr><th>ID</th><th>Hook</th><th>Grupo</th><th>Estado</th><th>Acción</th><th>' . esc_html__( 'Diagnóstico', 'taxocommerce-system-check' ) . '</th></tr></thead><tbody>';
		foreach ( $actions as $action ) {
			echo '<tr><td>' . esc_html( (string) $action['id'] ) . '</td><td><code>' . esc_html( $action['hook'] ) . '</code></td><td>' . esc_html( $action['group'] ) . '</td><td>' . esc_html( $action['status'] ) . '</td><td>';
			if ( $action['can_cancel'] ) {
				self::button( 'cancel_action', __( 'Cancelar pendiente', 'taxocommerce-system-check' ), array( 'id' => $action['id'] ) );
			}
			if ( $details_available ) {
				echo '</td><td><a class="button" href="' . esc_url( TaxoCommerce_System_Check_Process_Details::url( 'action', array( 'tcs_item' => (int) $action['id'] ) ) ) . '">' . esc_html__( 'Ver log / diagnóstico', 'taxocommerce-system-check' ) . '</a></td></tr>';
			} else {
				echo '</td><td>' . esc_html__( 'Unavailable', 'taxocommerce-system-check' ) . '</td></tr>';
			}
		}
		if ( ! $actions ) {
			echo '<tr><td colspan="6">' . esc_html__( 'Sin acciones pendientes o en curso.', 'taxocommerce-system-check' ) . '</td></tr>';
		}
		echo '</tbody></table><h3>WP-Cron</h3>';
		echo '<table class="widefat striped"><thead><tr><th>Hook</th><th>Programado (UTC)</th><th>Estado</th><th>Acción</th><th>' . esc_html__( 'Diagnóstico', 'taxocommerce-system-check' ) . '</th></tr></thead><tbody>';
		foreach ( $crons as $event ) {
			echo '<tr><td><code>' . esc_html( $event['hook'] ) . '</code></td><td>' . esc_html( gmdate( 'Y-m-d H:i:s', $event['due'] ) ) . '</td><td>' . esc_html( $event['status'] ) . '</td><td>';
			if ( $event['can_cancel'] ) {
				self::button( 'cancel_cron', __( 'Cancelar programación', 'taxocommerce-system-check' ), array( 'hook' => $event['hook'], 'when' => $event['due'], 'signature' => md5( wp_json_encode( $event['args'] ) ) ) );
			}
			if ( $details_available ) {
				echo '</td><td><a class="button" href="' . esc_url( TaxoCommerce_System_Check_Process_Details::url( 'cron', array( 'tcs_hook' => $event['hook'], 'tcs_when' => $event['due'], 'tcs_sig' => md5( wp_json_encode( $event['args'] ) ) ) ) ) . '">' . esc_html__( 'Ver detalles', 'taxocommerce-system-check' ) . '</a></td></tr>';
			} else {
				echo '</td><td>' . esc_html__( 'Unavailable', 'taxocommerce-system-check' ) . '</td></tr>';
			}
		}
		if ( ! $crons ) {
			echo '<tr><td colspan="5">' . esc_html__( 'No hay eventos de WP-Cron.', 'taxocommerce-system-check' ) . '</td></tr>';
		}
		echo '</tbody></table></div>';
	}
}
