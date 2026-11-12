<?php
/**
 * Authenticated, resumable, bounded export of the complete Insights JSON.
 *
 * No WP-Cron: the administrator's browser triggers one section per AJAX call.
 * A private temporary file is streamed only after all steps finish.
 */
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'TaxoCommerce_Insights_Chunked_Export', false ) ) {
final class TaxoCommerce_Insights_Chunked_Export {
    const ACTION = 'taxocommerce_insights_chunked_export';
    const NONCE = 'taxocommerce_insights_chunked_export';
    const OPTION_PREFIX = 'tgb_chunked_json_';
    const TTL = 3600;

    public static function steps() {
        return array(
            array( 'meta', '' ),
            array( 'donde_estamos', '' ),
            array( 'directrices', 'literatura' ),
            array( 'directrices', 'estructura' ),
            array( 'directrices', 'tendencias' ),
            array( 'directrices', 'search_console_acceleration' ),
            array( 'comparacion', 'tracked_keywords' ),
            array( 'comparacion', 'competition' ),
            array( 'comparacion', 'google_trends' ),
            array( 'comparacion', 'bing' ),
            array( 'comparacion', 'bing_google_queries' ),
            array( 'comparacion', 'catalog_guidance' ),
            array( 'comparacion', 'suppliers' ),
            array( 'estrategia', 'plan' ),
            array( 'estrategia', 'validation' ),
            array( 'campanas', '' ),
            array( 'arquitectura', '' ),
            array( 'hacia_donde_vamos', '' ),
            array( 'sources', '' ),
            array( 'operational_health', '' ),
            array( 'privacy', '' ),
        );
    }

    private static function key() {
        return self::OPTION_PREFIX . get_current_user_id();
    }

    private static function authorize() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Permisos insuficientes.' ), 403 );
        }
    }

    private static function load() {
        $state = get_option( self::key(), array() );
        if ( ! is_array( $state ) || empty( $state['file'] ) || empty( $state['id'] ) ) {
            return array();
        }
        if ( (int) ($state['created'] ?? 0) < time() - self::TTL ) {
            self::remove( $state );
            return array();
        }
        return $state;
    }

    private static function remove( $state ) {
        $file = isset( $state['file'] ) ? (string) $state['file'] : '';
        // Paths are created on the server; never take a path from user input.
        if ( $file && is_file( $file ) && 0 === strpos( basename( $file ), 'tgb-insights-' ) ) {
            wp_delete_file( $file );
            if ( is_file( $file . '.lock' ) ) wp_delete_file( $file . '.lock' );
        }
        delete_option( self::key() );
    }

    private static function encode( $value ) {
        return wp_json_encode(
            $value,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR
        );
    }

    private static function append( $path, $data ) {
        return strlen( $data ) === file_put_contents( $path, $data, FILE_APPEND | LOCK_EX );
    }

    private static function response( $state, $elapsed = 0, $cpu = null ) {
        $total = count( self::steps() );
        $memory = self::memory_pressure();
        $delay = 500;
        if ( $elapsed > 8000 || $memory >= 80 || ( null !== $cpu && $cpu > 6500 ) ) {
            $delay = 3500;
        } elseif ( $elapsed > 3500 || $memory >= 65 || ( null !== $cpu && $cpu > 2500 ) ) {
            $delay = 1800;
        }
        $speed = max( 1, min( 5, (int) ($state['speed'] ?? 3) ) );
        $minimum_delays = array( 1 => 6000, 2 => 3500, 3 => 2000, 4 => 1000, 5 => 300 );
        $delay = max( $delay, $minimum_delays[$speed] );
        return array(
            'id' => $state['id'],
            'done' => (int) $state['next'],
            'total' => $total,
            'complete' => ! empty( $state['complete'] ),
            'delay_ms' => $delay,
            'memory_percent' => $memory,
            'elapsed_ms' => $elapsed,
            'download_url' => ! empty( $state['complete'] ) ? self::download_url( $state ) : '',
        );
    }

    private static function memory_pressure() {
        if ( ! function_exists( 'wp_convert_hr_to_bytes' ) ) return 0;
        $limit = wp_convert_hr_to_bytes( (string) ini_get( 'memory_limit' ) );
        return $limit > 0 ? min( 100, (int) round( 100 * memory_get_peak_usage( true ) / $limit ) ) : 0;
    }

    private static function cpu_ms() {
        if ( ! function_exists( 'getrusage' ) ) return null;
        $r = getrusage();
        if ( ! is_array( $r ) ) return null;
        return (int) ( 1000 * ( ( $r['ru_utime.tv_sec'] ?? 0 ) + ( $r['ru_stime.tv_sec'] ?? 0 ) )
            + ( ( $r['ru_utime.tv_usec'] ?? 0 ) + ( $r['ru_stime.tv_usec'] ?? 0 ) ) / 1000 );
    }

    private static function download_url( $state ) {
        return wp_nonce_url(
            add_query_arg( array( 'action' => self::ACTION . '_download', 'job' => $state['id'] ), admin_url( 'admin-post.php' ) ),
            self::NONCE, 'nonce'
        );
    }

    public static function ajax() {
        self::authorize();
        // Keep verification in the same entry point that reads POST fields.
        check_ajax_referer( self::NONCE, 'nonce' );
        $op = isset( $_POST['op'] ) ? sanitize_key( wp_unslash( $_POST['op'] ) ) : '';
        if ( 'start' === $op ) {
            $old = self::load();
            if ( $old && empty( $old['complete'] ) ) {
                wp_send_json_success( self::response( $old ) );
            }
            if ( $old ) self::remove( $old );
            $days = isset( $_POST['days'] ) ? seo_analista_days( absint( wp_unslash( $_POST['days'] ) ) ) : 28;
            $speed = isset( $_POST['speed'] ) ? max( 1, min( 5, absint( wp_unslash( $_POST['speed'] ) ) ) ) : 3;
            $file = tempnam( get_temp_dir(), 'tgb-insights-' );
            if ( ! $file || false === file_put_contents( $file, '{', LOCK_EX ) ) {
                wp_send_json_error( array( 'message' => 'No se pudo crear el archivo temporal.' ), 500 );
            }
            $state = array(
                'id' => wp_generate_password( 32, false, false ),
                'created' => time(),
                'days' => $days,
                'speed' => $speed,
                'file' => $file,
                'next' => 0,
                'complete' => false,
            );
            update_option( self::key(), $state, false );
            wp_send_json_success( self::response( $state ) );
        }

        $state = self::load();
        $job = isset( $_POST['job'] ) ? sanitize_text_field( wp_unslash( $_POST['job'] ) ) : '';
        if ( ! $state || ! hash_equals( $state['id'], $job ) ) {
            wp_send_json_error( array( 'message' => 'Exportación caducada; inicia una nueva.' ), 409 );
        }
        if ( 'cancel' === $op ) {
            self::remove( $state );
            wp_send_json_success( array( 'cancelled' => true ) );
        }
        if ( 'status' === $op ) {
            wp_send_json_success( self::response( $state ) );
        }
        if ( 'step' !== $op ) {
            wp_send_json_error( array( 'message' => 'Operación desconocida.' ), 400 );
        }
        if ( ! empty( $state['complete'] ) ) {
            wp_send_json_success( self::response( $state ) );
        }

        // Prevent overlapping requests from appending the same step twice.
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- A private temporary stream needs an exclusive flock/read handle; WP_Filesystem has no locking or streaming API.
        $lock = fopen( $state['file'] . '.lock', 'c' );
        if ( ! $lock || ! flock( $lock, LOCK_EX | LOCK_NB ) ) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Close the explicit temporary flock/stream handle before responding.
            if ( $lock ) fclose( $lock );
            wp_send_json_error( array( 'message' => 'Hay un bloque en curso. Reintentar.' ), 409 );
        }
        $started = microtime( true );
        $before_cpu = self::cpu_ms();
        $steps = self::steps();
        $index = (int) $state['next'];
        $current = $steps[$index];
        $section = $current[0];
        $part = $current[1];
        $previous = $index > 0 ? $steps[$index-1][0] : '';
        $next_section = $index + 1 < count($steps) ? $steps[$index+1][0] : '';
        $value = seo_analista_build_json_export( $state['days'], $section, $part );
        $items = array();
        if ( 'meta' === $section || ( 'estrategia' === $section && 'plan' === $part ) ) {
            $items = (array) $value;
        } elseif ( '' !== $part ) {
            $items = array( $part => $value );
        } else {
            $items = array( $section => $value );
        }

        $chunk = '';
        if ( 'meta' !== $section ) {
            if ( $previous !== $section ) {
                $chunk .= (0 === $index ? '' : ',');
                if ( '' !== $part ) $chunk .= self::encode( $section ) . ':{';
            } else {
                $chunk .= ',';
            }
        }
        $first = true;
        foreach ( $items as $key => $item ) {
            if ( ! $first ) $chunk .= ',';
            $chunk .= self::encode( (string) $key ) . ':' . self::encode( $item );
            $first = false;
        }
        if ( 'directrices' === $section ) {
            $counts = isset( $state['directrices_counts'] ) ? (array) $state['directrices_counts'] : array();
            $counts[$part] = is_array( $value ) ? count( $value ) : 0;
            $state['directrices_counts'] = $counts;
            if ( $next_section !== $section ) {
                $summary = array(
                    'literatura' => (int) ($counts['literatura'] ?? 0),
                    'estructura' => (int) ($counts['estructura'] ?? 0),
                    'tendencias' => (int) ($counts['tendencias'] ?? 0),
                    'aceleraciones_search_console' => (int) ($counts['search_console_acceleration'] ?? 0),
                );
                $chunk .= ',"summary":' . self::encode( $summary );
            }
        }
        if ( 'meta' !== $section && '' !== $part && $next_section !== $section ) {
            $chunk .= '}';
        }
        if ( $index + 1 === count( $steps ) ) $chunk .= '}';
        if ( '' === $chunk ) {
            wp_send_json_error( array( 'message' => 'Bloque vacío.' ), 500 );
        }
        if ( ! self::append( $state['file'], $chunk ) ) {
            flock( $lock, LOCK_UN );
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Close the explicit temporary flock/stream handle before responding.
            fclose( $lock );
            wp_send_json_error( array( 'message' => 'No se pudo guardar el bloque.' ), 500 );
        }
        $state['next']++;
        if ( $state['next'] === count( $steps ) ) {
            // Final validation is separate from the heavy source calculations.
            $contents = file_get_contents( $state['file'] );
            if ( false === $contents ) {
                flock( $lock, LOCK_UN );
                // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Close the explicit temporary flock/stream handle before responding.
                fclose( $lock );
                wp_send_json_error( array( 'message' => 'Error al leer el JSON final.' ), 500 );
            }
            json_decode( $contents, true );
            unset( $contents );
            if ( JSON_ERROR_NONE !== json_last_error() ) {
                flock( $lock, LOCK_UN );
                // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Close the explicit temporary flock/stream handle before responding.
                fclose( $lock );
                wp_send_json_error( array( 'message' => 'Los bloques no forman un JSON válido.' ), 500 );
            }
            $state['complete'] = true;
        }
        update_option( self::key(), $state, false );
        $elapsed = (int) round( 1000 * ( microtime( true ) - $started ) );
        $after_cpu = self::cpu_ms();
        $cpu = null === $before_cpu || null === $after_cpu ? null : max( 0, $after_cpu - $before_cpu );
        flock( $lock, LOCK_UN );
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Close the explicit temporary flock/stream handle before responding.
        fclose( $lock );
        wp_send_json_success( self::response( $state, $elapsed, $cpu ) );
    }

    public static function download() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Permisos insuficientes.', '', array( 'response' => 403 ) );
        check_admin_referer( self::NONCE, 'nonce' );
        $job = isset( $_GET['job'] ) ? sanitize_text_field( wp_unslash( $_GET['job'] ) ) : '';
        $state = self::load();
        if ( ! $state || ! hash_equals( $state['id'], $job ) || empty( $state['complete'] )
            || ! is_readable( $state['file'] ) ) {
            wp_die( 'El archivo aún no está completo o ha caducado.', '', array( 'response' => 409 ) );
        }
        nocache_headers();
        header( 'X-Content-Type-Options: nosniff' );
        header( 'Content-Type: application/json; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="seo-analista-unificado-' . gmdate( 'Ymd-His' ) . '.json"' );
        header( 'Content-Length: ' . filesize( $state['file'] ) );
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- A private temporary stream needs an exclusive flock/read handle; WP_Filesystem has no locking or streaming API.
        $handle = fopen( $state['file'], 'rb' );
        if ( $handle ) {
            while ( ! feof( $handle ) ) {
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.WP.AlternativeFunctions.file_system_operations_fread -- Stream raw private JSON via authenticated download; no filesystem alternative supports bounded output.
                echo fread( $handle, 65536 );
                flush();
            }
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Close the explicit temporary flock/stream handle before responding.
            fclose( $handle );
        }
        exit;
    }
}
add_action( 'wp_ajax_' . TaxoCommerce_Insights_Chunked_Export::ACTION, array( 'TaxoCommerce_Insights_Chunked_Export', 'ajax' ) );
add_action( 'admin_post_' . TaxoCommerce_Insights_Chunked_Export::ACTION . '_download', array( 'TaxoCommerce_Insights_Chunked_Export', 'download' ) );
}
