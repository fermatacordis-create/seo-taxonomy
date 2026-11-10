<?php
/**
 * Independent workload controller for the five Auditor scopes.
 *
 * The selected speed sets hard caps for work per request. Observed duration
 * and PHP memory pressure adapt the next batch; the browser adapts pacing.
 * Neither metric claims to measure server-wide CPU utilisation.
 *
 * @package TaxoCommerceAuditor
 */
namespace TaxoCommerce\Auditor;

defined( 'ABSPATH' ) || exit;

final class Workload {
    const OPTION = 'taxocommerce_auditor_workload';
    const DEFAULT_SPEED = 3;

    public static function speed( $scope ) {
        if ( ! Runner::valid_scope( $scope ) ) {
            return self::DEFAULT_SPEED;
        }
        $saved = get_option( self::OPTION, array() );
        $value = is_array( $saved ) && isset( $saved[ $scope ] ) ? $saved[ $scope ] : self::DEFAULT_SPEED;
        return max( 1, min( 5, absint( $value ) ) );
    }

    public static function set_speed( $scope, $value ) {
        if ( ! Runner::valid_scope( $scope ) || ! is_scalar( $value )
            || ! preg_match( '/^[1-5]$/', (string) $value ) ) {
            return false;
        }
        $stored = get_option( self::OPTION, array() );
        $stored = is_array( $stored ) ? $stored : array();
        $stored[ $scope ] = (int) $value;
        update_option( self::OPTION, $stored, false );
        return true;
    }

    public static function batch_size( $scope ) {
        $sizes = array( 1 => 40, 2 => 90, 3 => 180, 4 => 300, 5 => 500 );
        return $sizes[ self::speed( $scope ) ];
    }

    public static function delay_seconds( $scope ) {
        $delays = array( 1 => 90, 2 => 60, 3 => 35, 4 => 20, 5 => 12 );
        return $delays[ self::speed( $scope ) ];
    }

    public static function budget_seconds( $scope ) {
        $budgets = array( 1 => 2, 2 => 3, 3 => 5, 4 => 8, 5 => 12 );
        return $budgets[ self::speed( $scope ) ];
    }

    /**
     * Delay between successful browser-driven batches, in milliseconds.
     *
     * This is independent of WP-Cron and deliberately never zero. Batches
     * remain bounded by batch_size() and budget_seconds() on the server.
     *
     * @param string $scope Audit scope.
     * @return int
     */
    public static function client_delay_ms( $scope ) {
        $delays = array( 1 => 6000, 2 => 3500, 3 => 2000, 4 => 1000, 5 => 300 );
        return $delays[ self::speed( $scope ) ];
    }

    /**
     * The selected speed is a hard upper bound, not a fixed batch size.
     *
     * @param string $scope Audit scope.
     * @param array  $state Current persisted cursor state.
     * @return int
     */
    public static function effective_batch_size( $scope, $state = array() ) {
        $maximum = self::batch_size( $scope );
        // Start new and legacy in-progress audits conservatively, then grow
        // only after real measurements show headroom.
        $initial = max( 1, (int) ceil( $maximum / 8 ) );
        $saved = isset( $state['adaptive_batch_size'] ) ? absint( $state['adaptive_batch_size'] ) : $initial;
        return max( 1, min( $maximum, $saved ) );
    }

    /**
     * Adjust the *next* batch only, using observed PHP execution time and
     * request memory pressure. Never exceed the administrator's speed limit.
     * These measures are not the server-wide CPU utilisation percentage.
     *
     * @param string $scope Audit scope.
     * @param int    $previous Previous batch cap.
     * @param int    $elapsed_ms Total server batch time, including the query.
     * @param int    $memory_percent Peak PHP request memory / PHP memory limit.
     * @param int    $processed Number of entities actually processed.
     * @param int|null $cpu_ms PHP worker CPU time in milliseconds, when available.
     * @return int
     */
    public static function adjusted_batch_size( $scope, $previous, $elapsed_ms, $memory_percent, $processed, $cpu_ms = null ) {
        $maximum  = self::batch_size( $scope );
        $previous = max( 1, min( $maximum, absint( $previous ) ) );
        $elapsed  = absint( $elapsed_ms );
        $memory   = max( 0, min( 100, absint( $memory_percent ) ) );
        $cpu      = null !== $cpu_ms ? absint( $cpu_ms ) : null;

        if ( 0 === absint( $processed ) ) {
            return $previous;
        }
        $budget_ms = self::budget_seconds( $scope ) * 1000;
        if ( $memory >= 80 || $elapsed >= (int) ( $budget_ms * 0.85 )
            || ( null !== $cpu && $cpu >= (int) ( $budget_ms * 0.75 ) ) ) {
            return max( 1, (int) floor( $previous * 0.65 ) );
        }
        if ( $memory < 70 && $elapsed <= (int) ( $budget_ms * 0.30 )
            && ( null === $cpu || $cpu <= (int) ( $budget_ms * 0.30 ) ) ) {
            return min( $maximum, $previous + max( 1, (int) ceil( $previous * 0.25 ) ) );
        }
        return $previous;
    }

    /**
     * Approximate peak PHP request memory pressure; 0 means unavailable or
     * unlimited, not an absence of memory usage.
     *
     * @return int
     */
    public static function memory_pressure_percent() {
        if ( ! function_exists( 'wp_convert_hr_to_bytes' ) ) {
            return 0;
        }
        $limit = wp_convert_hr_to_bytes( (string) ini_get( 'memory_limit' ) );
        if ( ! is_numeric( $limit ) || $limit <= 0 ) {
            return 0;
        }
        return min( 100, max( 0, (int) round( 100 * memory_get_peak_usage( true ) / $limit ) ) );
    }

    /**
     * Current PHP worker CPU time (user + system) in milliseconds, if
     * supported. This is NOT a percentage of the shared server's CPU.
     *
     * @return int|null
     */
    public static function process_cpu_ms() {
        if ( ! function_exists( 'getrusage' ) ) {
            return null;
        }
        $resource = getrusage();
        if ( ! is_array( $resource ) ) {
            return null;
        }
        $keys = array( 'ru_utime.tv_sec', 'ru_stime.tv_sec', 'ru_utime.tv_usec', 'ru_stime.tv_usec' );
        foreach ( $keys as $key ) {
            if ( ! isset( $resource[ $key ] ) ) {
                return null;
            }
        }
        return max( 0, (int) round(
            ( (float) $resource['ru_utime.tv_sec'] + (float) $resource['ru_stime.tv_sec'] ) * 1000
            + ( (float) $resource['ru_utime.tv_usec'] + (float) $resource['ru_stime.tv_usec'] ) / 1000
        ) );
    }

    public static function label( $speed ) {
        $labels = array(
            1 => __( 'Suave', 'taxocommerce-auditor' ),
            2 => __( 'Baja', 'taxocommerce-auditor' ),
            3 => __( 'Normal', 'taxocommerce-auditor' ),
            4 => __( 'Alta', 'taxocommerce-auditor' ),
            5 => __( 'Máxima', 'taxocommerce-auditor' ),
        );
        return $labels[ max( 1, min( 5, absint( $speed ) ) ) ];
    }
}
