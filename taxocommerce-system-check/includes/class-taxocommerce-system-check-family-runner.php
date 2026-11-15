<?php
/**
 * Manual, bounded family validation coordinator. Core is the first adapter.
 *
 * @package TaxoCommerceSystemCheck
 */
defined( 'ABSPATH' ) || exit;

final class TaxoCommerce_System_Check_Family_Runner {
    const ACTION = 'tcs_family_validation';
    const KEY = 'tcs_family_runner_';
    const TTL = HOUR_IN_SECONDS;

    public static function boot() {
        add_action( 'wp_ajax_' . self::ACTION, array( __CLASS__, 'ajax' ) );
    }

    private static function key() {
        return self::KEY . get_current_blog_id() . '_' . get_current_user_id();
    }

    private static function state() {
        $state = get_transient( self::key() );
        return is_array( $state ) && isset( $state['phase'], $state['status'] ) ? $state : false;
    }

    private static function available() {
        return class_exists( 'TaxoCommerce_Core_Auto_Test', false )
            && class_exists( 'TaxoCommerce_Core_Autotest_Code', false )
            && is_callable( array( 'TaxoCommerce_Core_Auto_Test', 'run_suite' ) )
            && is_callable( array( 'TaxoCommerce_Core_Autotest_Code', 'step' ) );
    }

    public static function speeds() {
        return array(
            1 => array( 'size' => 1, 'delay' => 6000 ),
            2 => array( 'size' => 1, 'delay' => 4000 ),
            3 => array( 'size' => 2, 'delay' => 2500 ),
            4 => array( 'size' => 3, 'delay' => 1500 ),
            5 => array( 'size' => 4, 'delay' => 900 ),
        );
    }

    private static function begin( $speed ) {
        if ( ! self::available() ) {
            return new WP_Error( 'tcs_core_unavailable', __( 'Los motores de autocheck de Core no están disponibles.', 'taxocommerce-system-check' ) );
        }
        $cases = TaxoCommerce_Core_Auto_Test::cases();
        // Never trigger a potentially destructive UAT from the family overview.
        unset( $cases['write_uat'] );
        $files = TaxoCommerce_Core_Autotest_Code::files();
        if ( empty( $cases ) || empty( $files ) ) {
            return new WP_Error( 'tcs_empty_inventory', __( 'No hay pruebas o archivos suficientes para ejecutar el piloto.', 'taxocommerce-system-check' ) );
        }
        return array(
            'schema' => 1, 'status' => 'running', 'phase' => 'functional',
            'speed' => $speed, 'batch_size' => self::speeds()[ $speed ]['size'],
            'updated_at' => gmdate( 'c' ), 'cases' => $cases, 'functional_cursor' => 0,
            'functional_rows' => array(), 'functional_totals' => array( 'pass' => 0, 'fail' => 0, 'skip' => 0 ),
            'code' => array(
                'schema' => 1, 'plugin' => 'taxocommerce-core', 'timestamp' => gmdate( 'c' ),
                'status' => 'running', 'speed' => $speed, 'cursor' => 0, 'files' => $files,
                'symbols' => array(), 'results' => array(),
                'native_static_review' => 'enabled', 'native_rules_revision' => 1,
                'official_pcp' => 'not_executed', 'runtime_security' => 'not_verified',
                'older_php_versions' => 'not_tested', 'inventory_truncated' => count( $files ) >= 250,
            ),
        );
    }

    private static function advance( $state ) {
        $started = microtime( true );
        $memory_before = memory_get_usage( true );
        $cpu_start = function_exists( 'getrusage' ) ? getrusage() : false;
        $size = max( 1, min( 4, (int) $state['batch_size'] ) );
        if ( 'functional' === $state['phase'] ) {
            $all = array_keys( $state['cases'] );
            $slice = array_slice( $all, $state['functional_cursor'], $size );
            foreach ( $slice as $id ) {
                $report = TaxoCommerce_Core_Auto_Test::run_suite( array( $id => $state['cases'][ $id ] ) );
                foreach ( $report['rows'] as $row ) {
                    $state['functional_rows'][] = $row;
                    $status = $row['status'] ?? 'fail';
                    if ( ! isset( $state['functional_totals'][ $status ] ) ) {
                        $status = 'fail';
                    }
                    ++$state['functional_totals'][ $status ];
                }
                ++$state['functional_cursor'];
                if ( microtime( true ) - $started >= 1.2 ) {
                    break;
                }
            }
            if ( $state['functional_cursor'] >= count( $all ) ) {
                $state['phase'] = 'code';
            }
        } elseif ( 'code' === $state['phase'] ) {
            // Reuse Core's own scanner and findings; never copy or change its rules.
            // Core owns the scanner; select its bounded file size from the adaptive cap.
            $code_speed = 1;
            foreach ( TaxoCommerce_Core_Autotest_Code::speeds() as $candidate => $config ) {
                if ( $candidate <= $state['speed'] && $config['size'] <= $size ) {
                    $code_speed = $candidate;
                }
            }
            $state['code']['speed'] = $code_speed;
            $state['code'] = TaxoCommerce_Core_Autotest_Code::step( $state['code'] );
            if ( 'completed' === $state['code']['status'] ) {
                $state['phase'] = 'done';
                $state['status'] = 'completed';
            }
        }
        $elapsed = ( microtime( true ) - $started ) * 1000;
        $memory = memory_get_peak_usage( true );
        $limit = wp_convert_hr_to_bytes( ini_get( 'memory_limit' ) );
        $pressure = $limit > 0 ? $memory / $limit : 0;
        $cpu_end = function_exists( 'getrusage' ) ? getrusage() : false;
        $cpu_ms = null;
        if ( is_array( $cpu_start ) && is_array( $cpu_end ) ) {
            $cpu_before = ( $cpu_start['ru_utime.tv_sec'] + $cpu_start['ru_stime.tv_sec'] ) * 1000
                + ( $cpu_start['ru_utime.tv_usec'] + $cpu_start['ru_stime.tv_usec'] ) / 1000;
            $cpu_after = ( $cpu_end['ru_utime.tv_sec'] + $cpu_end['ru_stime.tv_sec'] ) * 1000
                + ( $cpu_end['ru_utime.tv_usec'] + $cpu_end['ru_stime.tv_usec'] ) / 1000;
            $cpu_ms = max( 0, (int) round( $cpu_after - $cpu_before ) );
        }
        $maximum = self::speeds()[ $state['speed'] ]['size'];
        if ( $elapsed > 1500 || $pressure > 0.70 || ( null !== $cpu_ms && $cpu_ms > 1200 ) ) {
            $state['batch_size'] = max( 1, (int) $state['batch_size'] - 1 );
        } elseif ( $elapsed < 450 && $pressure < 0.50 ) {
            $state['batch_size'] = min( $maximum, (int) $state['batch_size'] + 1 );
        }
        $state['load'] = array(
            'elapsed_ms' => (int) round( $elapsed ),
            'php_cpu_ms' => $cpu_ms,
            'memory_bytes' => $memory,
            'memory_delta_bytes' => max( 0, $memory - $memory_before ),
            'memory_limit_bytes' => $limit > 0 ? $limit : null,
            'batch_size' => $state['batch_size'],
        );
        $state['updated_at'] = gmdate( 'c' );
        return $state;
    }

    public static function summary( $state ) {
        if ( ! $state ) {
            return array( 'status' => 'not_run', 'phase' => 'pending', 'functional' => array( 'status' => 'pending' ), 'code' => array( 'status' => 'pending' ) );
        }
        $done = (int) $state['functional_cursor'];
        $total = count( $state['cases'] );
        $code = $state['code'];
        $code_summary = TaxoCommerce_Core_Autotest_Code::summary( $code );
        return array(
            'status' => $state['status'], 'phase' => $state['phase'], 'updated_at' => $state['updated_at'],
            'functional' => array(
                'status' => $done >= $total ? ( $state['functional_totals']['fail'] ? 'fail' : 'completed' ) : 'running',
                'done' => $done, 'total' => $total, 'totals' => $state['functional_totals'],
            ),
            'code' => array(
                'status' => $code_summary['status'], 'done' => (int) $code['cursor'],
                'total' => count( $code['files'] ), 'findings' => $code_summary['findings'],
                'failed' => $code_summary['failed'], 'skipped' => $code_summary['skipped'],
            ),
            'load' => $state['load'] ?? array(),
            'speed' => $state['speed'], 'delay_ms' => self::speeds()[ $state['speed'] ]['delay'],
        );
    }

    public static function ajax() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permisos insuficientes.', 'taxocommerce-system-check' ) ), 403 );
        }
        check_ajax_referer( self::ACTION, 'nonce' );
        $op = isset( $_POST['operation'] ) && is_scalar( $_POST['operation'] )
            ? sanitize_key( wp_unslash( $_POST['operation'] ) ) : 'status';
        $state = self::state();
        if ( 'start' === $op ) {
            $requested = isset( $_POST['speed'] ) ? absint( wp_unslash( $_POST['speed'] ) ) : 3;
            $speed = isset( self::speeds()[ $requested ] ) ? $requested : 3;
            $state = self::begin( $speed );
            if ( is_wp_error( $state ) ) {
                wp_send_json_error( array( 'message' => $state->get_error_message() ), 400 );
            }
        } elseif ( 'stop' === $op && $state && 'running' === $state['status'] ) {
            $state['status'] = 'paused';
        } elseif ( 'resume' === $op && $state && 'paused' === $state['status'] ) {
            $state['status'] = 'running';
        } elseif ( 'step' === $op && $state && 'running' === $state['status'] ) {
            try {
                $state = self::advance( $state );
            } catch ( Throwable $error ) {
                $state['status'] = 'paused';
                $state['error'] = sanitize_text_field( $error->getMessage() );
            }
        }
        if ( is_array( $state ) ) {
            set_transient( self::key(), $state, self::TTL );
        }
        $result = self::summary( $state );
        if ( ! empty( $state['error'] ) ) {
            $result['error'] = $state['error'];
        }
        wp_send_json_success( $result );
    }

    public static function render() {
        $summary = self::summary( self::state() );
        echo '<div id="tcs-family-runner" class="postbox" style="padding:14px 18px;max-width:1100px">';
        echo '<h3>' . esc_html__( 'Validación de la familia: piloto Core', 'taxocommerce-system-check' ) . '</h3>';
        echo '<p>' . esc_html__( 'Primero se ejecutan las funcionalidades de Core y después su chequeo de código por lotes. Los demás plugins permanecen pendientes de integración.', 'taxocommerce-system-check' ) . '</p>';
        echo '<label for="tcs-family-speed">' . esc_html__( 'Carga de análisis', 'taxocommerce-system-check' ) . '</label> ';
        echo '<select id="tcs-family-speed">';
        foreach ( array( 1 => 'Suave', 2 => 'Baja', 3 => 'Normal', 4 => 'Alta', 5 => 'Máxima' ) as $speed => $name ) {
            echo '<option value="' . esc_attr( (string) $speed ) . '"' . selected( 3, $speed, false ) . '>' . esc_html( $name ) . '</option>';
        }
        echo '</select> ';
        echo '<button type="button" class="button button-primary" data-tcs-op="start">' . esc_html__( 'Ejecutar piloto Core', 'taxocommerce-system-check' ) . '</button> ';
        echo '<button type="button" class="button" data-tcs-op="stop">' . esc_html__( 'Detener', 'taxocommerce-system-check' ) . '</button> ';
        echo '<button type="button" class="button" data-tcs-op="resume">' . esc_html__( 'Reanudar', 'taxocommerce-system-check' ) . '</button>';
        echo '<p id="tcs-family-progress" role="status" aria-live="polite"></p>';
        echo '</div>';
        wp_add_inline_script(
            'taxocommerce-system-check-family-runner',
            'window.tcsFamilyRunner=' . wp_json_encode( array(
                'url' => admin_url( 'admin-ajax.php' ),
                'nonce' => wp_create_nonce( self::ACTION ),
                'state' => $summary,
            ) ) . ';',
            'before'
        );
    }
}
