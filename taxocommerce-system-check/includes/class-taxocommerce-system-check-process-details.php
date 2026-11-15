<?php
/**
 * On-demand, read-only diagnostics for application jobs.
 *
 * Not a process killer or a PHP runtime log viewer. Action Scheduler is the
 * sole source for action execution logs. No credentials or arguments are shown.
 *
 * @package TaxoCommerceSystemCheck
 */
defined( 'ABSPATH' ) || exit;

final class TaxoCommerce_System_Check_Process_Details {
    const MAX_LOGS = 20;
    const NONCE = 'taxocommerce_system_check_process_details';

    /** Link to one bounded, administrator-only diagnostic. */
    public static function url( $type, $fields ) {
        return wp_nonce_url(
            add_query_arg(
                array_merge(
                    array( 'page' => 'taxocommerce-system-check', 'tcs_tab' => 'processes', 'tcs_detail' => $type ),
                    $fields
                ),
                admin_url( 'admin.php' )
            ),
            self::NONCE
        );
    }

    /** No hook name alone proves which plugin owns an action. */
    public static function callback_status( $hook ) {
        if ( ! function_exists( 'has_action' ) ) {
            return 'unknown';
        }
        return false === has_action( $hook ) ? 'missing' : 'registered';
    }

    /** Never disclose tokens, passwords or sensitive URL query parameters. */
    public static function safe_log_message( $message ) {
        $text = sanitize_text_field( wp_strip_all_tags( (string) $message ) );
        $text = preg_replace( '/\\b(Bearer)\\s+[A-Za-z0-9._~+\\/-]+/i', '$1 [redacted]', $text );
        $text = preg_replace( '/(?<![a-z0-9_])"?(password|passwd|secret|token|api[_-]?key|authorization|access[_-]?(?:key|token)|refresh[_-]?token)"?\\s*[:=]\\s*(?:"[^"]*"|\\x27[^\\x27]*\\x27|[^\\s&,;}\\]]+)/i', '$1=[redacted]', $text );
        $text = preg_replace( '/([?&](?:key|api_key|token|secret|password|auth|signature|code)=)[^&#\\s]+/i', '$1[redacted]', $text );
        return wp_html_excerpt( (string) $text, 400 );
    }

    /** Use Action Scheduler's own logger; never read arbitrary paths or debug.log. */
    public static function action_logs( $id ) {
        if ( $id < 1 || ! class_exists( 'ActionScheduler', false ) || ! is_callable( array( 'ActionScheduler', 'logger' ) ) ) {
            return array();
        }
        try {
            $logger = ActionScheduler::logger();
            if ( ! is_object( $logger ) || ! is_callable( array( $logger, 'get_logs' ) ) ) {
                return array();
            }
            $all = (array) $logger->get_logs( $id );
            $rows = array();
            foreach ( array_slice( $all, -self::MAX_LOGS ) as $entry ) {
                if ( ! is_object( $entry ) || ! is_callable( array( $entry, 'get_message' ) ) ) {
                    continue;
                }
                $date = is_callable( array( $entry, 'get_date' ) ) ? $entry->get_date() : null;
                $rows[] = array(
                    'at' => $date instanceof DateTimeInterface ? gmdate( 'Y-m-d H:i:s', $date->getTimestamp() ) : '',
                    'message' => self::safe_log_message( $entry->get_message() ),
                );
            }
            return $rows;
        } catch ( Throwable $exception ) {
            return array();
        }
    }

    /** Fetch a single action on demand, including historical failures. */
    public static function action( $id ) {
        if ( $id < 1 || ! class_exists( 'ActionScheduler', false ) ) {
            return false;
        }
        try {
            $store = ActionScheduler::store();
            $job = $store->fetch_action( $id );
            if ( ! is_object( $job ) || ! is_callable( array( $job, 'get_hook' ) ) ) {
                return false;
            }
            $hook = (string) $job->get_hook();
            return array(
                'type' => 'action',
                'id' => $id,
                'hook' => $hook,
                'group' => (string) $job->get_group(),
                'status' => (string) $store->get_status( $id ),
                'callback' => self::callback_status( $hook ),
                'logs' => self::action_logs( $id ),
            );
        } catch ( Throwable $exception ) {
            return false;
        }
    }

    public static function cron( $hook, $when, $signature ) {
        if ( '' === $hook || $when < 1 || ! preg_match( '/^[a-f0-9]{32}$/', $signature ) ) {
            return false;
        }
        foreach ( TaxoCommerce_System_Check_Processes::cron() as $row ) {
            if ( $row['hook'] !== $hook || (int) $row['due'] !== $when ) {
                continue;
            }
            if ( ! hash_equals( md5( wp_json_encode( $row['args'] ) ), $signature ) ) {
                continue;
            }
            return array(
                'type' => 'cron',
                'hook' => $hook,
                'group' => '',
                'status' => $row['status'],
                'due' => $row['due'],
                'callback' => self::callback_status( $hook ),
                'logs' => array(),
            );
        }
        return false;
    }

    /** Read-only worker state: no claim that a running PHP request was killed. */
    public static function worker( $id ) {
        foreach ( TaxoCommerce_System_Check_Processes::workers() as $worker ) {
            if ( (string) $worker['id'] === $id ) {
                return array(
                    'type' => 'worker',
                    'hook' => (string) $worker['label'],
                    'group' => '',
                    'status' => (string) $worker['status'],
                    'detail' => (string) ( $worker['detail'] ?? '' ),
                    'callback' => 'unknown',
                    'logs' => array(),
                );
            }
        }
        return false;
    }

    /** The detail selector never invokes a callback, changes a schedule or logs secrets. */
    public static function selected() {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only request selectors; nonce verified before any detail is displayed.
        $type = isset( $_GET['tcs_detail'] ) && is_scalar( $_GET['tcs_detail'] ) ? sanitize_key( wp_unslash( $_GET['tcs_detail'] ) ) : '';
        if ( ! in_array( $type, array( 'action', 'cron', 'worker' ), true ) ) {
            return false;
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            return false;
        }
        check_admin_referer( self::NONCE );
        $id = isset( $_GET['tcs_item'] ) && is_scalar( $_GET['tcs_item'] ) ? absint( wp_unslash( $_GET['tcs_item'] ) ) : 0;
        if ( 'action' === $type ) {
            return self::action( $id );
        }
        if ( 'worker' === $type ) {
            $worker_id = isset( $_GET['tcs_worker'] ) && is_scalar( $_GET['tcs_worker'] ) ? sanitize_text_field( wp_unslash( $_GET['tcs_worker'] ) ) : '';
            return self::worker( $worker_id );
        }
        $hook = isset( $_GET['tcs_hook'] ) && is_scalar( $_GET['tcs_hook'] ) ? sanitize_key( wp_unslash( $_GET['tcs_hook'] ) ) : '';
        $when = isset( $_GET['tcs_when'] ) && is_scalar( $_GET['tcs_when'] ) ? absint( wp_unslash( $_GET['tcs_when'] ) ) : 0;
        $signature = isset( $_GET['tcs_sig'] ) && is_scalar( $_GET['tcs_sig'] ) ? sanitize_text_field( wp_unslash( $_GET['tcs_sig'] ) ) : '';
        // phpcs:enable WordPress.Security.NonceVerification.Recommended
        return self::cron( $hook, $when, $signature );
    }

    public static function render_selected() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Navigation-only selector; selected() enforces the nonce and capability.
        if ( empty( $_GET['tcs_detail'] ) ) {
            return;
        }
        $item = self::selected();
        echo '<div class="postbox" style="padding:12px 16px;max-width:1000px"><h3>' . esc_html__( 'Diagnóstico de tarea', 'taxocommerce-system-check' ) . '</h3>';
        if ( ! $item ) {
            echo '<p>' . esc_html__( 'La tarea ya no existe o no está disponible.', 'taxocommerce-system-check' ) . '</p></div>';
            return;
        }
        echo '<p><strong>' . esc_html( $item['hook'] ) . '</strong> · ' . esc_html( $item['status'] ) . '</p>';
        if ( ! empty( $item['group'] ) ) {
            echo '<p>' . esc_html__( 'Grupo:', 'taxocommerce-system-check' ) . ' ' . esc_html( $item['group'] ) . '</p>';
        }
        if ( 'missing' === $item['callback'] ) {
            echo '<p class="notice notice-warning" style="padding:10px">' . esc_html__( 'Posible tarea huérfana: no hay un callback registrado en esta petición. Confirma si el plugin está activo antes de cancelar.', 'taxocommerce-system-check' ) . '</p>';
        } elseif ( 'registered' === $item['callback'] ) {
            echo '<p>' . esc_html__( 'Callback registrado. Esto no demuestra una ejecución correcta.', 'taxocommerce-system-check' ) . '</p>';
        } else {
            echo '<p>' . esc_html__( 'Ejecutor no verificable desde este estado.', 'taxocommerce-system-check' ) . '</p>';
        }
        if ( 'cron' === $item['type'] ) {
            echo '<p>' . esc_html__( 'Programado (UTC):', 'taxocommerce-system-check' ) . ' ' . esc_html( gmdate( 'Y-m-d H:i:s', $item['due'] ) ) . '</p>';
            echo '<p>' . esc_html__( 'WP-Cron no conserva un historial individual de errores. Consulta los registros del plugin responsable si existen.', 'taxocommerce-system-check' ) . '</p>';
        }
        if ( 'worker' === $item['type'] ) {
            echo '<p>' . esc_html( $item['detail'] ?? '' ) . '</p>';
            echo '<p>' . esc_html__( 'Este estado no prueba que una petición PHP siga ejecutándose.', 'taxocommerce-system-check' ) . '</p>';
        }
        if ( 'action' === $item['type'] ) {
            echo '<h4>' . esc_html__( 'Registro de Action Scheduler (últimas 20 entradas)', 'taxocommerce-system-check' ) . '</h4>';
            if ( $item['logs'] ) {
                echo '<ol>';
                foreach ( $item['logs'] as $entry ) {
                    echo '<li><strong>' . esc_html( $entry['at'] ) . ' UTC</strong> · ' . esc_html( $entry['message'] ) . '</li>';
                }
                echo '</ol>';
            } else {
                echo '<p>' . esc_html__( 'No hay entradas de registro disponibles para esta tarea.', 'taxocommerce-system-check' ) . '</p>';
            }
        }
        echo '<p>' . esc_html__( 'El prefijo histórico seo_ no demuestra una dependencia del antiguo monolítico. No se muestran argumentos ni credenciales.', 'taxocommerce-system-check' ) . '</p></div>';
    }
}
