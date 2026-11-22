<?php
/**
 * Resumable standalone catalogue classification with bounded CPU usage.
 *
 * No legacy bootstrap, external service or mandatory TaxoCommerce dependency.
 *
 * @package TaxoCommerceVocabulary
 */
defined( 'ABSPATH' ) || exit;

final class TCV_Classifier_Batch {
    const OPTION = 'tcv_classifier_catalogue_job';
    const LOCK   = 'tcv_classifier_catalogue_lock';
    const HOOK   = 'tcv_classifier_catalogue_step';

    public static function boot() {
        add_action( 'init', array( __CLASS__, 'watchdog' ), 20 );
        add_action( self::HOOK, array( __CLASS__, 'step' ) );
        add_action( 'admin_post_tcv_classifier_job', array( __CLASS__, 'handle' ) );
        add_action( 'admin_post_tcv_classifier_approve', array( __CLASS__, 'approve' ) );
        add_action( 'admin_post_tcv_classifier_job_export', array( __CLASS__, 'export' ) );
    }

    /**
     * A deliberately conservative five-step budget. The actual CPU usage
     * depends on hosting and WP-Cron; this limits work, not server CPU percent.
     *
     * @return array{rows:int,seconds:int,delay:int}
     */
    public static function budget( $level ) {
        $levels = array(
            1 => array( 'rows' => 2,  'seconds' => 2,  'delay' => 120 ),
            2 => array( 'rows' => 5,  'seconds' => 3,  'delay' => 90 ),
            3 => array( 'rows' => 10, 'seconds' => 5,  'delay' => 60 ),
            4 => array( 'rows' => 20, 'seconds' => 8,  'delay' => 30 ),
            5 => array( 'rows' => 35, 'seconds' => 12, 'delay' => 15 ),
        );
        $level = min( 5, max( 1, absint( $level ) ) );
        return $levels[ $level ];
    }

    public static function status() {
        $job = get_option( self::OPTION, array() );
        return is_array( $job ) ? $job : array();
    }

    private static function schedule( $seconds ) {
        if ( wp_next_scheduled( self::HOOK ) ) {
            return true;
        }
        return wp_schedule_single_event( time() + max( 1, absint( $seconds ) ), self::HOOK, array(), true );
    }

    private static function record_schedule_error( $job, $error ) {
        $job['status']  = 'error';
        $job['message'] = is_wp_error( $error ) ? $error->get_error_message() : __( 'WordPress no pudo crear el siguiente evento WP-Cron.', 'taxocommerce-vocabulary' );
        update_option( self::OPTION, $job, false );
    }

    /**
     * Recover an interrupted or missing WP-Cron event on the next site request.
     * The lock prevents requeueing while an existing batch is executing.
     */
    public static function watchdog() {
        $job = self::status();
        if ( 'running' !== ( $job['status'] ?? '' ) || wp_next_scheduled( self::HOOK ) ) {
            return;
        }
        $lock = get_option( self::LOCK, array() );
        if ( is_array( $lock ) && isset( $lock['at'] ) && time() - absint( $lock['at'] ) < 300 ) {
            return;
        }
        $scheduled = self::schedule( 5 );
        if ( is_wp_error( $scheduled ) || ! $scheduled ) {
            self::record_schedule_error( $job, $scheduled );
        }
    }

    /**
     * Operate only through a capability- and nonce-checked administrator POST.
     */
    public static function handle() {
        if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Permisos insuficientes.', 'taxocommerce-vocabulary' ) );
        }
        check_admin_referer( 'tcv_classifier_job' );
        $operation = isset( $_POST['operation'] ) ? sanitize_key( wp_unslash( $_POST['operation'] ) ) : '';
        $level = isset( $_POST['cpu_level'] ) ? min( 5, max( 1, absint( wp_unslash( $_POST['cpu_level'] ) ) ) ) : 2;
        $job = self::status();
        $notice = __( 'Operación no disponible para el estado actual.', 'taxocommerce-vocabulary' );

        if ( 'start' === $operation && ! in_array( $job['status'] ?? '', array( 'running', 'paused' ), true ) ) {
            global $wpdb;
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Bounded catalogue snapshot uses the standard indexed WordPress posts table.
            $snapshot = $wpdb->get_row( $wpdb->prepare(
                "SELECT COALESCE(MAX(ID),0) AS max_id, COUNT(*) AS total FROM %i WHERE post_type=%s AND post_status=%s",
                $wpdb->posts, 'product', 'publish'
            ), ARRAY_A );
            if ( ! is_array( $snapshot ) || ! empty( $wpdb->last_error ) ) {
                wp_die( esc_html__( 'No se ha podido leer el catálogo; no se ha iniciado el recorrido.', 'taxocommerce-vocabulary' ) );
            }
            $mode = isset( $_POST['apply_safe'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['apply_safe'] ) ) ? 'safe' : 'review';
            $job = array(
                'id' => wp_generate_uuid4(), 'status' => 'running', 'level' => $level, 'mode' => $mode,
                'cursor' => 0, 'max_id' => absint( $snapshot['max_id'] ?? 0 ),
                'total' => absint( $snapshot['total'] ?? 0 ), 'processed' => 0,
                'proposed' => 0, 'applied' => 0, 'failures' => 0,
                'started_at' => current_time( 'mysql', true ), 'updated_at' => current_time( 'mysql', true ),
                'message' => '',
            );
            wp_clear_scheduled_hook( self::HOOK );
            update_option( self::OPTION, $job, false );
            $scheduled = self::schedule( 5 );
            if ( is_wp_error( $scheduled ) || ! $scheduled ) {
                self::record_schedule_error( $job, $scheduled );
                $notice = __( 'No se ha podido programar WP-Cron.', 'taxocommerce-vocabulary' );
            } else {
                $notice = __( 'Clasificación del catálogo programada.', 'taxocommerce-vocabulary' );
            }
        } elseif ( 'pause' === $operation && 'running' === ( $job['status'] ?? '' ) ) {
            $job['status'] = 'paused';
            $job['updated_at'] = current_time( 'mysql', true );
            update_option( self::OPTION, $job, false );
            wp_clear_scheduled_hook( self::HOOK );
            $notice = __( 'Clasificador pausado.', 'taxocommerce-vocabulary' );
        } elseif ( 'resume' === $operation && in_array( $job['status'] ?? '', array( 'paused', 'error' ), true ) ) {
            $job['status'] = 'running';
            $job['message'] = '';
            $job['updated_at'] = current_time( 'mysql', true );
            update_option( self::OPTION, $job, false );
            $scheduled = self::schedule( 5 );
            if ( is_wp_error( $scheduled ) || ! $scheduled ) {
                self::record_schedule_error( $job, $scheduled );
            } else {
                $notice = __( 'Clasificador reanudado.', 'taxocommerce-vocabulary' );
            }
        } elseif ( 'cancel' === $operation && in_array( $job['status'] ?? '', array( 'running', 'paused', 'error' ), true ) ) {
            $job['status'] = 'cancelled';
            $job['updated_at'] = current_time( 'mysql', true );
            update_option( self::OPTION, $job, false );
            wp_clear_scheduled_hook( self::HOOK );
            $notice = __( 'Clasificación cancelada; no se han eliminado datos.', 'taxocommerce-vocabulary' );
        } elseif ( 'speed' === $operation && ! empty( $job ) ) {
            $job['level'] = $level;
            $job['updated_at'] = current_time( 'mysql', true );
            update_option( self::OPTION, $job, false );
            if ( 'running' === ( $job['status'] ?? '' ) ) {
                wp_clear_scheduled_hook( self::HOOK );
                $scheduled = self::schedule( 5 );
                if ( is_wp_error( $scheduled ) || ! $scheduled ) {
                    self::record_schedule_error( $job, $scheduled );
                }
            }
            $notice = __( 'Velocidad actualizada.', 'taxocommerce-vocabulary' );
        }

        wp_safe_redirect( add_query_arg(
            array( 'page' => 'taxocommerce-vocabulary', 'tab' => 'classifier', 'tcv_status' => 'success', 'tcv_message' => rawurlencode( $notice ) ),
            admin_url( 'admin.php' )
        ) );
        exit;
    }

    /**
     * A lock prevents two overlapping cron requests from applying twice.
     */
    public static function step() {
        $job = self::status();
        if ( 'running' !== ( $job['status'] ?? '' ) || empty( $job['id'] ) ) {
            return;
        }
        $token = wp_generate_uuid4();
        if ( ! add_option( self::LOCK, array( 'id' => $token, 'at' => time() ), '', false ) ) {
            $old = get_option( self::LOCK, array() );
            if ( is_array( $old ) && ! empty( $old['at'] ) && ( time() - absint( $old['at'] ) ) > 300 ) {
                delete_option( self::LOCK );
                if ( ! add_option( self::LOCK, array( 'id' => $token, 'at' => time() ), '', false ) ) {
                    return;
                }
            } else {
                return;
            }
        }

        try {
            $budget = self::budget( $job['level'] ?? 2 );
            $start = microtime( true );
            global $wpdb;
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Keyset pagination on the standard indexed posts ID avoids OFFSET and unbounded scans.
            $ids = $wpdb->get_col( $wpdb->prepare(
                'SELECT ID FROM %i WHERE post_type=%s AND post_status=%s AND ID>%d AND ID<=%d ORDER BY ID ASC LIMIT %d',
                $wpdb->posts, 'product', 'publish', absint( $job['cursor'] ), absint( $job['max_id'] ), $budget['rows']
            ) );
            if ( ! empty( $wpdb->last_error ) ) {
                $job['status'] = 'error';
                $job['message'] = __( 'Error de consulta al recorrer productos.', 'taxocommerce-vocabulary' );
                $job['failures']++;
            } elseif ( ! $ids ) {
                $job['status'] = 'completed';
            } else {
                foreach ( $ids as $id ) {
                    $live = self::status();
                    if ( ( $live['id'] ?? '' ) !== $job['id'] || 'running' !== ( $live['status'] ?? '' ) ) {
                        $job['status'] = $live['status'] ?? 'paused';
                        break;
                    }
                    if ( microtime( true ) - $start >= $budget['seconds'] ) {
                        break;
                    }
                    $product_id = absint( $id );
                    try {
                        $result = TCV_Classifier::analyze_product( $product_id );
                    } catch ( Throwable $exception ) {
                        $result = new WP_Error( 'tcv_classifier_product_error', __( 'No se pudo analizar el producto.', 'taxocommerce-vocabulary' ) );
                    }
                    if ( is_wp_error( $result ) ) {
                        ++$job['failures'];
                        $job['message'] = sprintf(
                            /* translators: %d: product ID. */
                            __( 'No se pudo analizar el producto #%d.', 'taxocommerce-vocabulary' ),
                            $product_id
                        );
                    } else {
                        $job['proposed'] += count( $result['labels'] ) + count( $result['attributes'] );
                        if ( 'safe' === ( $job['mode'] ?? 'review' ) ) {
                            $job['applied'] += TCV_Classifier::apply_safe_labels( $product_id, $result['labels'] );
                        }
                    }
                    $job['cursor'] = $product_id;
                    ++$job['processed'];
                }
            }

            $live = self::status();
            if ( ( $live['id'] ?? '' ) === $job['id'] ) {
                if ( 'running' !== ( $live['status'] ?? '' ) ) {
                    $job['status'] = $live['status'];
                }
                $job['level'] = $live['level'] ?? $job['level'];
                $job['updated_at'] = current_time( 'mysql', true );
                update_option( self::OPTION, $job, false );
                if ( 'running' === $job['status'] ) {
                    $scheduled = self::schedule( self::budget( $job['level'] )['delay'] );
                    if ( is_wp_error( $scheduled ) || ! $scheduled ) {
                        self::record_schedule_error( $job, $scheduled );
                    }
                }
            }
        } finally {
            $lock = get_option( self::LOCK, array() );
            if ( is_array( $lock ) && ( $lock['id'] ?? '' ) === $token ) {
                delete_option( self::LOCK );
            }
        }
    }

    /**
     * Manual approval of an existing canonical concept or existing attribute.
     * Never inserts a new vocabulary/attribute definition or overwrites a value.
     */
    public static function approve() {
        if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Permisos insuficientes.', 'taxocommerce-vocabulary' ) );
        }
        $proposal_id = isset( $_POST['proposal_id'] ) ? absint( wp_unslash( $_POST['proposal_id'] ) ) : 0;
        check_admin_referer( 'tcv_classifier_approve_' . $proposal_id );
        $result = TCV_Classifier::apply_proposal( $proposal_id );
        $success = ! is_wp_error( $result );
        $message = $success ? __( 'Propuesta aplicada.', 'taxocommerce-vocabulary' ) : $result->get_error_message();
        wp_safe_redirect( add_query_arg(
            array( 'page' => 'taxocommerce-vocabulary', 'tab' => 'classifier', 'tcv_status' => $success ? 'success' : 'error', 'tcv_message' => rawurlencode( $message ) ),
            admin_url( 'admin.php' )
        ) );
        exit;
    }

    public static function export() {
        if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Permisos insuficientes.', 'taxocommerce-vocabulary' ) );
        }
        check_admin_referer( 'tcv_classifier_job_export' );
        nocache_headers();
        header( 'X-Content-Type-Options: nosniff' );
        header( 'Content-Type: application/json; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="taxocommerce-clasificador-progreso.json"' );
        echo wp_json_encode( self::status(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
        exit;
    }

    public static function render() {
        $job = self::status();
        $level = absint( $job['level'] ?? 2 );
        $budget = self::budget( $level );
        $status = sanitize_key( $job['status'] ?? 'not_started' );
        $progress = absint( $job['total'] ?? 0 ) ? min( 100, round( 100 * absint( $job['processed'] ?? 0 ) / absint( $job['total'] ) ) ) : 0;
        echo '<div class="tcv-panel"><h2>' . esc_html__( 'Clasificación integral · control de CPU', 'taxocommerce-vocabulary' ) . '</h2>';
        echo '<p>' . esc_html__( 'Recorre productos publicados por bloques. No consulta servicios externos. La carga se limita por productos, tiempo máximo y pausa entre ejecuciones; no controla el porcentaje real de CPU del servidor.', 'taxocommerce-vocabulary' ) . '</p>';
        echo '<p><strong>' . esc_html__( 'Estado:', 'taxocommerce-vocabulary' ) . '</strong> ' . esc_html( $status ) .
            ' · ' . esc_html__( 'Productos analizados:', 'taxocommerce-vocabulary' ) . ' <strong>' . esc_html( number_format_i18n( absint( $job['processed'] ?? 0 ) ) ) . ' / ' . esc_html( number_format_i18n( absint( $job['total'] ?? 0 ) ) ) . '</strong>' .
            ' · ' . esc_html__( 'Propuestas:', 'taxocommerce-vocabulary' ) . ' ' . esc_html( number_format_i18n( absint( $job['proposed'] ?? 0 ) ) ) .
            ' · ' . esc_html__( 'Etiquetas aplicadas:', 'taxocommerce-vocabulary' ) . ' ' . esc_html( number_format_i18n( absint( $job['applied'] ?? 0 ) ) ) .
            ' · ' . esc_html__( 'Errores:', 'taxocommerce-vocabulary' ) . ' ' . esc_html( number_format_i18n( absint( $job['failures'] ?? 0 ) ) ) . '</p>';
        echo '<progress max="100" value="' . esc_attr( $progress ) . '" style="width:100%"></progress>';
        echo '<p class="description">' . esc_html__( 'Última actividad (UTC):', 'taxocommerce-vocabulary' ) . ' ' . esc_html( (string) ( $job['updated_at'] ?? '—' ) ) .
            ' · ' . esc_html__( 'Último ID:', 'taxocommerce-vocabulary' ) . ' ' . esc_html( (string) absint( $job['cursor'] ?? 0 ) ) .
            ' · ' . esc_html__( 'Siguiente evento WP-Cron:', 'taxocommerce-vocabulary' ) . ' ' . esc_html( wp_next_scheduled( self::HOOK ) ? wp_date( 'Y-m-d H:i:s', wp_next_scheduled( self::HOOK ) ) : '—' ) . '</p>';
        if ( ! empty( $job['message'] ) ) {
            echo '<p class="notice notice-warning">' . esc_html( $job['message'] ) . '</p>';
        }
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        wp_nonce_field( 'tcv_classifier_job' );
        echo '<input type="hidden" name="action" value="tcv_classifier_job">';
        echo '<label for="tcv-cpu">' . esc_html__( 'Velocidad (1 mínima, 5 máxima)', 'taxocommerce-vocabulary' ) . ' </label>';
        echo '<select id="tcv-cpu" name="cpu_level">';
        for ( $i = 1; $i <= 5; $i++ ) {
            $b = self::budget( $i );
            $label = sprintf(
                /* translators: 1: CPU speed level, 2: maximum products per batch. */
                __( 'Nivel %1$d · máximo %2$d productos por bloque', 'taxocommerce-vocabulary' ),
                $i, $b['rows']
            );
            echo '<option value="' . esc_attr( $i ) . '" ' . selected( $level, $i, false ) . '>' . esc_html( $label ) . '</option>';
        }
        echo '</select> ';
        echo '<span class="description">' . esc_html( sprintf(
            /* translators: 1: maximum seconds, 2: delay in seconds. */
            __( 'Límite: %1$d s/bloque; pausa mínima: %2$d s.', 'taxocommerce-vocabulary' ),
            $budget['seconds'], $budget['delay']
        ) ) . '</span><p>';
        if ( ! in_array( $status, array( 'running', 'paused' ), true ) ) {
            echo '<label><input type="checkbox" name="apply_safe" value="1"> ' . esc_html__( 'Aplicar automáticamente solo etiquetas canónicas seguras; nunca crear términos ni sobrescribir valores', 'taxocommerce-vocabulary' ) . '</label></p>';
            echo '<button type="submit" class="button button-primary" name="operation" value="start">' . esc_html__( 'Iniciar recorrido completo', 'taxocommerce-vocabulary' ) . '</button> ';
        }
        if ( 'running' === $status ) {
            echo '<button type="submit" class="button" name="operation" value="pause">' . esc_html__( 'Pausar', 'taxocommerce-vocabulary' ) . '</button> ';
        } elseif ( in_array( $status, array( 'paused', 'error' ), true ) ) {
            echo '<button type="submit" class="button button-primary" name="operation" value="resume">' . esc_html__( 'Reanudar', 'taxocommerce-vocabulary' ) . '</button> ';
        }
        if ( in_array( $status, array( 'running', 'paused', 'error' ), true ) ) {
            echo '<button type="submit" class="button" name="operation" value="cancel">' . esc_html__( 'Cancelar', 'taxocommerce-vocabulary' ) . '</button> ';
        }
        echo '<button type="submit" class="button" name="operation" value="speed">' . esc_html__( 'Guardar velocidad', 'taxocommerce-vocabulary' ) . '</button></form>';
        $export = wp_nonce_url( admin_url( 'admin-post.php?action=tcv_classifier_job_export' ), 'tcv_classifier_job_export' );
        echo '<p><a class="button" href="' . esc_url( $export ) . '">' . esc_html__( 'Descargar progreso JSON', 'taxocommerce-vocabulary' ) . '</a></p>';
        echo '<p class="description">' . esc_html__( 'WP-Cron necesita tráfico o un ejecutor de cron configurado. Las propuestas nuevas y los atributos requieren aprobación; no se modifican precios, inventario ni fichas comerciales.', 'taxocommerce-vocabulary' ) . '</p></div>';
    }
}
