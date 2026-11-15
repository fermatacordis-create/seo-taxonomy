<?php
/**
 * Local, rate-limited HTTP auditor backed by the existing health-scan inventory.
 *
 * Reuses seo_health_runs and seo_health_items for compatibility with the
 * original Page/Post/Image/Product audits. No GitHub credentials are needed.
 *
 * @package TaxoCommerceSystemCheck
 */

defined( 'ABSPATH' ) || exit;

final class TaxoCommerce_System_Check_URL_Audit {
    const HOOK = 'taxocommerce_system_check_process_url_batch';
    const ACTION = 'taxocommerce_system_check_audit_action';
    const LOCK = 'taxocommerce_system_check_url_worker_lock';
    const GROUP = 'taxocommerce-system-check';
    const RESUME_HOOK = 'taxocommerce_system_check_url_watchdog';

    public static function register_hooks() {
        add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'handle_action' ) );
        add_action( 'admin_post_taxocommerce_system_check_http_json', array( __CLASS__, 'download_json' ) );
        add_action( self::HOOK, array( __CLASS__, 'process_batch' ), 10, 1 );
        add_action( self::RESUME_HOOK, array( __CLASS__, 'watchdog' ) );
        add_action( 'init', array( __CLASS__, 'maybe_schedule_watchdog' ) );
        add_filter( 'cron_schedules', array( __CLASS__, 'cron_intervals' ) );
    }

    /**
     * The periodic watchdog resumes interrupted local scans without requiring
     * the store owner to click a button after a PHP timeout.
     */
    public static function maybe_schedule_watchdog() {
        if ( ! wp_next_scheduled( self::RESUME_HOOK ) ) {
            wp_schedule_event( time() + 300, 'tcs_five_minutes', self::RESUME_HOOK );
        }
    }

    public static function cron_intervals( $schedules ) {
        $schedules['tcs_five_minutes'] = array(
            'interval' => 300,
            'display' => 'Cada cinco minutos (System Check)',
        );
        return $schedules;
    }

    public static function watchdog() {
        if ( ! self::supported() ) {
            return;
        }
        global $wpdb;
        $table = self::table( 'runs' );
        $stale = gmdate( 'Y-m-d H:i:s', time() - 90 );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- This diagnostic queries current site/database state; cached values would hide live server changes.
        $ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT id FROM %i WHERE mode='local' AND status IN ('queued','running')
                 AND updated_at<=%s ORDER BY id ASC LIMIT 1",
                 $table,
                $stale
            )
        );
        foreach ( (array) $ids as $id ) {
            self::schedule( absint( $id ), 1 );
        }
    }

    public static function deactivate() {
        wp_clear_scheduled_hook( self::RESUME_HOOK );
        if ( ! self::supported() ) {
            return;
        }
        global $wpdb;
        $runs = self::table( 'runs' );
        $items = self::table( 'items' );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- This diagnostic queries current site/database state; cached values would hide live server changes.
        $run_ids = $wpdb->get_col(
            $wpdb->prepare("SELECT id FROM %i WHERE mode='local' AND status IN ('queued','running')", $runs)
        );
        foreach ( (array) $run_ids as $id ) {
            $args = array( absint( $id ) );
            wp_clear_scheduled_hook( self::HOOK, $args );
            if ( function_exists( 'as_unschedule_all_actions' ) ) {
                as_unschedule_all_actions( self::HOOK, $args, self::GROUP );
            }
        }
        $now = current_time( 'mysql', true );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct write coordinates the scheduler/diagnostic state; caching is not applicable.
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE %i SET status='cancelled',error_message='Plugin desactivado.',
                 completed_at=%s,updated_at=%s WHERE mode='local' AND status IN ('queued','running')",
                 $runs,
                $now,
                $now
            )
        );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct write coordinates the scheduler/diagnostic state; caching is not applicable.
        $wpdb->query(
            $wpdb->prepare("UPDATE %i SET queued_scan_id=0,queued_at=NULL
             WHERE queued_scan_id IN (SELECT id FROM %i WHERE mode='local' AND status='cancelled')", $items, $runs)
        );
        delete_option( self::LOCK );
    }

    private static function supported() {
        foreach ( array(
            'seo_health_scan_maybe_upgrade',
            'seo_health_scan_sync_inventory',
            'seo_health_scan_tables',
            'seo_health_scan_summary',
            'seo_health_scan_store_results',
            'seo_health_scan_refresh_run',
        ) as $method ) {
            if ( ! function_exists( $method ) ) {
                return false;
            }
        }
        return true;
    }

    private static function scopes() {
        return array(
            'page'    => 'Páginas',
            'post'    => 'Posts',
            'image'   => 'Imágenes',
            'product' => 'Productos',
        );
    }

    private static function scope( $value ) {
        $scope = sanitize_key( (string) $value );
        return isset( self::scopes()[ $scope ] ) ? $scope : 'page';
    }

    private static function table( $part ) {
        $tables = seo_health_scan_tables();
        // Internally generated, WP-prefixed identifier; never sourced from users.
        return esc_sql( (string) $tables[ $part ] );
    }

    private static function url( $scope, $args = array() ) {
        return add_query_arg(
            array_merge( array( 'page' => 'taxocommerce-system-check', 'tcs_tab' => 'http', 'tcs_scope' => self::scope( $scope ) ), $args ),
            admin_url( 'admin.php' )
        );
    }

    private static function schedule( $run_id, $delay = 10 ) {
        $run_id = absint( $run_id );
        if ( ! $run_id ) {
            return false;
        }
        $args = array( $run_id );
        // Avoid duplicate pending tasks for the same run.
        if ( function_exists( 'as_next_scheduled_action' ) && as_next_scheduled_action( self::HOOK, $args, self::GROUP ) ) {
            return true;
        }
        if ( wp_next_scheduled( self::HOOK, $args ) ) {
            return true;
        }
        $when = time() + max( 1, (int) $delay );
        // Reuse WooCommerce/Action Scheduler's queue, WP-Cron otherwise.
        if ( function_exists( 'as_schedule_single_action' ) ) {
            $id = as_schedule_single_action( $when, self::HOOK, $args, self::GROUP, true );
            return ! is_wp_error( $id ) && (bool) $id;
        }
        $scheduled = wp_schedule_single_event( $when, self::HOOK, $args, true );
        return ! is_wp_error( $scheduled ) && (bool) $scheduled;
    }

    private static function running_anywhere() {
        global $wpdb;
        $table = self::table( 'runs' );
        // Older GitHub scans also count: do not run two resource-heavy scans together.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- This diagnostic queries current site/database state; cached values would hide live server changes.
        return (int) $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM %i WHERE status IN ('queued','running','paused')", $table)
        ) > 0;
    }

    public static function start( $scope ) {
        global $wpdb;
        if ( ! self::supported() ) {
            return new WP_Error( 'tcs_engine_missing', 'El motor del inventario no está disponible.' );
        }
        $scope = self::scope( $scope );
        seo_health_scan_maybe_upgrade();
        if ( function_exists( 'seo_health_scan_expire_stale' ) ) {
            seo_health_scan_expire_stale();
        }
        if ( self::running_anywhere() ) {
            return new WP_Error( 'tcs_scan_busy', 'Ya existe un chequeo en curso. Para limitar la carga, termina o cancela primero el anterior.' );
        }
        $sync = self::sync_inventory( $scope );
        if ( is_wp_error( $sync ) ) {
            return $sync;
        }

        $runs = self::table( 'runs' );
        $items = self::table( 'items' );
        $now = current_time( 'mysql', true );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct write coordinates the scheduler/diagnostic state; caching is not applicable.
        $inserted = $wpdb->insert(
            $runs,
            array(
                'scan_uuid'          => wp_generate_uuid4(),
                'scope'              => $scope,
                'mode'               => 'local',
                'status'             => 'queued',
                'total_items'        => 0,
                'callback_token_hash'=> '',
                'created_at'         => $now,
                'updated_at'         => $now,
            ),
            array( '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s' )
        );
        if ( false === $inserted ) {
            return new WP_Error( 'tcs_insert_failed', 'No se pudo registrar el chequeo: ' . $wpdb->last_error );
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Diagnostic explicitly reads current database state, so caching would invalidate the result.
        $run_id = (int) $wpdb->insert_id;

        // Reserve all active URLs once. Small, bounded jobs will process them
        // progressively; there is no giant GitHub payload or PHP request.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct write coordinates the scheduler/diagnostic state; caching is not applicable.
        $reserved = $wpdb->query(
            $wpdb->prepare(
                "UPDATE %i SET queued_scan_id=%d,queued_at=%s
                 WHERE scope=%s AND active=1 AND queued_scan_id=0",
                 $items,
                $run_id,
                $now,
                $scope
            )
        );
        if ( false === $reserved || (int) $reserved < 1 ) {
            self::fail( $run_id, $wpdb->last_error ?: 'No hay URLs disponibles para escanear.' );
            return new WP_Error( 'tcs_empty', 'No se pudieron reservar las URLs.' );
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct write coordinates the scheduler/diagnostic state; caching is not applicable.
        $wpdb->update(
            $runs,
            array( 'total_items' => (int) $reserved ),
            array( 'id' => $run_id ),
            array( '%d' ),
            array( '%d' )
        );

        if ( ! self::schedule( $run_id, 1 ) ) {
            self::fail( $run_id, 'No se ha podido programar el trabajador local.' );
            return new WP_Error( 'tcs_schedule_error', 'No se ha podido programar el trabajador local.' );
        }
        return array( 'run_id' => $run_id, 'count' => (int) $reserved );
    }

    /**
     * Preserve the historical external image inventory but also include core
     * WordPress Media. A generic WordPress site has no SEO image-usage tables.
     */
    private static function sync_inventory( $scope ) {
        if ( 'image' !== $scope ) {
            return seo_health_scan_sync_inventory( $scope );
        }
        if ( ! function_exists( 'seo_health_scan_collect_images' ) || ! function_exists( 'seo_health_scan_upsert_rows' ) ) {
            return new WP_Error( 'tcs_image_engine_missing', 'El inventario de imágenes no está disponible.' );
        }

        global $wpdb;
        $by_url = array();
        foreach ( (array) seo_health_scan_collect_images() as $row ) {
            $url = esc_url_raw( (string) ( $row['url'] ?? '' ), array( 'http', 'https' ) );
            if ( '' !== $url ) {
                $by_url[ md5( $url ) ] = $row;
            }
        }
        // Native Media Library works without WooCommerce or TaxoCommerce SEO.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- This diagnostic queries current site/database state; cached values would hide live server changes.
        $attachment_ids = $wpdb->get_col(
            "SELECT ID FROM {$wpdb->posts}
             WHERE post_type='attachment' AND post_mime_type LIKE 'image/%'
               AND post_status IN ('inherit','publish') ORDER BY ID ASC"
        );
        foreach ( (array) $attachment_ids as $attachment_id ) {
            $id = absint( $attachment_id );
            $url = esc_url_raw( (string) wp_get_attachment_url( $id ), array( 'http', 'https' ) );
            if ( '' === $url ) {
                continue;
            }
            $by_url[ md5( $url ) ] = array(
                'object_id' => $id,
                'url'       => $url,
                'label'     => get_the_title( $id ),
                'source'    => 'wordpress_media',
            );
        }
        if ( empty( $by_url ) ) {
            return new WP_Error( 'tcs_images_empty', 'No hay imágenes públicas para escanear.' );
        }

        $token = wp_generate_uuid4();
        $written = seo_health_scan_upsert_rows( 'image', array_values( $by_url ), $token );
        if ( is_wp_error( $written ) ) {
            return $written;
        }
        $items = self::table( 'items' );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct write coordinates the scheduler/diagnostic state; caching is not applicable.
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE %i SET active=0,queued_scan_id=0,queued_at=NULL
                 WHERE scope=%s AND active=1 AND sync_token<>%s",
                 $items,
                'image',
                $token
            )
        );
        return array( 'count' => count( $by_url ), 'written' => $written );
    }

    private static function fail( $run_id, $reason ) {
        global $wpdb;
        $runs = self::table( 'runs' );
        $items = self::table( 'items' );
        $now = current_time( 'mysql', true );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct write coordinates the scheduler/diagnostic state; caching is not applicable.
        $wpdb->update(
            $runs,
            array(
                'status' => 'failed', 'error_message' => substr( sanitize_text_field( $reason ), 0, 4000 ),
                'callback_token_hash' => '', 'completed_at' => $now, 'updated_at' => $now,
            ),
            array( 'id' => absint( $run_id ) ),
            array( '%s', '%s', '%s', '%s', '%s' ),
            array( '%d' )
        );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct write coordinates the scheduler/diagnostic state; caching is not applicable.
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE %i SET queued_scan_id=0,queued_at=NULL WHERE queued_scan_id=%d",
                 $items,
                absint( $run_id )
            )
        );
    }

    private static function lock() {
        $existing = get_option( self::LOCK, array() );
        if ( is_array( $existing ) && ! empty( $existing['time'] ) && time() - absint( $existing['time'] ) > 120 ) {
            delete_option( self::LOCK );
        }
        $token = wp_generate_uuid4();
        return add_option( self::LOCK, array( 'time' => time(), 'token' => $token ), '', false ) ? $token : false;
    }

    private static function unlock( $token ) {
        $lock = get_option( self::LOCK, array() );
        if ( is_array( $lock ) && isset( $lock['token'] ) && hash_equals( (string) $lock['token'], (string) $token ) ) {
            delete_option( self::LOCK );
        }
    }

    public static function process_batch( $run_id ) {
        global $wpdb;
        $run_id = absint( $run_id );
        if ( ! $run_id || ! self::supported() ) {
            return;
        }

        $token = self::lock();
        if ( false === $token ) {
            // Preserve progress when two scheduled tasks happen to overlap.
            self::schedule( $run_id, 30 );
            return;
        }

        try {
            $runs  = self::table( 'runs' );
            $items = self::table( 'items' );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- This diagnostic queries current site/database state; cached values would hide live server changes.
            $run = $wpdb->get_row(
                $wpdb->prepare( "SELECT * FROM %i WHERE id=%d LIMIT 1",
                 $runs, $run_id ),
                ARRAY_A
            );
            if ( ! is_array( $run ) || ! in_array( $run['status'], array( 'queued', 'running' ), true ) || 'local' !== $run['mode'] ) {
                return;
            }

            $started = microtime( true );
            if ( 'queued' === $run['status'] ) {
                $now = current_time( 'mysql', true );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct write coordinates the scheduler/diagnostic state; caching is not applicable.
                $wpdb->update(
                    $runs,
                    array( 'status' => 'running', 'started_at' => $now, 'updated_at' => $now ),
                    array( 'id' => $run_id ),
                    array( '%s', '%s', '%s' ),
                    array( '%d' )
                );
            }
            $size = max( 1, min( 8, (int) apply_filters( 'taxocommerce_system_check_url_batch_size', 5 ) ) );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- This diagnostic queries current site/database state; cached values would hide live server changes.
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT id,url FROM %i WHERE queued_scan_id=%d ORDER BY id ASC LIMIT %d",
                 $items,
                    $run_id,
                    $size
                ),
                ARRAY_A
            );

            if ( empty( $rows ) ) {
                self::complete( $run_id );
                return;
            }
            $results = array();
            foreach ( $rows as $row ) {
                if ( microtime( true ) - $started > 12 && ! empty( $results ) ) {
                    break;
                }
                $results[] = self::probe( (string) $row['url'], (string) $run['scope'], (int) $row['id'] );
            }
            // The administrator may cancel while a remote request is in flight.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- This diagnostic queries current site/database state; cached values would hide live server changes.
            $current_state = (string) $wpdb->get_var(
                $wpdb->prepare( "SELECT status FROM %i WHERE id=%d LIMIT 1",
                 $runs, $run_id )
            );
            if ( ! in_array( $current_state, array( 'queued', 'running' ), true ) ) {
                return;
            }
            $stored = seo_health_scan_store_results( $run, $results );
            if ( (int) ( $stored['stored'] ?? 0 ) !== count( $results ) ) {
                self::fail( $run_id, 'No se han podido guardar todos los resultados del lote.' );
                return;
            }
            seo_health_scan_refresh_run( $run_id );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- This diagnostic queries current site/database state; cached values would hide live server changes.
            $remaining = (int) $wpdb->get_var(
                $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE queued_scan_id=%d",
                 $items, $run_id )
            );
            if ( $remaining <= 0 ) {
                self::complete( $run_id );
            } elseif ( ! self::schedule( $run_id, 10 ) ) {
                self::fail( $run_id, 'No se ha podido programar el siguiente lote del trabajador.' );
            }
        } finally {
            self::unlock( $token );
        }
    }

    private static function complete( $run_id ) {
        global $wpdb;
        $runs = self::table( 'runs' );
        $items = self::table( 'items' );
        seo_health_scan_refresh_run( $run_id );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- This diagnostic queries current site/database state; cached values would hide live server changes.
        $run = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM %i WHERE id=%d LIMIT 1",
                 $runs, absint( $run_id ) ),
            ARRAY_A
        );
        if ( ! is_array( $run ) || ! in_array( $run['status'], array( 'queued', 'running' ), true ) ) {
            return;
        }
        $processed = absint( $run['processed_items'] );
        $expected = absint( $run['total_items'] );
        if ( $processed < $expected ) {
            self::fail( $run_id, sprintf( 'Resultados incompletos: %d/%d.', $processed, $expected ) );
            return;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- This diagnostic queries current site/database state; cached values would hide live server changes.
        $average = $wpdb->get_var(
            $wpdb->prepare( "SELECT AVG(response_ms) FROM %i WHERE last_scan_id=%d",
                 $items, $run_id )
        );
        $offset = max( 0, (int) ceil( $processed * 0.95 ) - 1 );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- This diagnostic queries current site/database state; cached values would hide live server changes.
        $p95 = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT response_ms FROM %i WHERE last_scan_id=%d ORDER BY response_ms ASC LIMIT 1 OFFSET %d",
                 $items,
                $run_id,
                $offset
            )
        );
        $start_time = strtotime( (string) ( $run['started_at'] ?: $run['created_at'] ) . ' UTC' );
        $now = current_time( 'mysql', true );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct write coordinates the scheduler/diagnostic state; caching is not applicable.
        $wpdb->update(
            $runs,
            array(
                'status'              => 'completed',
                'duration_ms'         => max( 0, ( time() - (int) $start_time ) * 1000 ),
                'avg_response_ms'     => absint( round( (float) $average ) ),
                'p95_response_ms'     => absint( $p95 ),
                'pressure_events'     => absint( $run['status_429'] ) + absint( $run['status_5xx'] ),
                'callback_token_hash' => '',
                'error_message'       => '',
                'completed_at'        => $now,
                'updated_at'          => $now,
            ),
            array( 'id' => $run_id ),
            array( '%s', '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s' ),
            array( '%d' )
        );
    }

    private static function allowed_url( $url, $scope ) {
        $parsed = wp_parse_url( $url );
        if ( ! is_array( $parsed ) || empty( $parsed['host'] ) || ! in_array( strtolower( $parsed['scheme'] ?? '' ), array( 'http', 'https' ), true ) ) {
            return false;
        }
        if ( isset( $parsed['user'] ) || isset( $parsed['pass'] ) ) {
            return false;
        }
        if ( 'image' !== $scope ) {
            $target = preg_replace( '/^www\./', '', strtolower( (string) $parsed['host'] ) );
            $home = preg_replace( '/^www\./', '', strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) );
            $site = preg_replace( '/^www\./', '', strtolower( (string) wp_parse_url( site_url(), PHP_URL_HOST ) ) );
            if ( $target !== $home && $target !== $site ) {
                return false;
            }
        }
        // Prevent SSRF, including local/private IP ranges, on every redirect.
        return (bool) wp_http_validate_url( $url );
    }

    /**
     * Resolve relative Location headers without depending on a private or
     * version-sensitive WP_Http method. Final URLs are validated separately.
     */
    private static function resolve_redirect( $location, $current ) {
        $location = trim( (string) $location );
        if ( preg_match( '#^https?://#i', $location ) ) {
            return $location;
        }
        $parts = wp_parse_url( $current );
        if ( ! is_array( $parts ) || empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
            return '';
        }
        $scheme = strtolower( $parts['scheme'] );
        $authority = $parts['host'];
        if ( isset( $parts['port'] ) ) {
            $authority .= ':' . (int) $parts['port'];
        }
        $base = $scheme . '://' . $authority;
        if ( 0 === strpos( $location, '//' ) ) {
            return $scheme . ':' . $location;
        }
        if ( 0 === strpos( $location, '/' ) ) {
            return $base . $location;
        }
        $path = (string) ( $parts['path'] ?? '/' );
        if ( '?' === substr( $location, 0, 1 ) || '#' === substr( $location, 0, 1 ) ) {
            return $base . $path . $location;
        }
        $slash = strrpos( $path, '/' );
        $dir = false === $slash ? '/' : substr( $path, 0, $slash + 1 );
        return $base . $dir . $location;
    }

    private static function probe( $url, $scope, $item_id ) {
        $origin = esc_url_raw( $url, array( 'http', 'https' ) );
        $result = array(
            'item_id' => $item_id, 'url' => $origin, 'http_status' => 0,
            'final_status' => 0, 'final_url' => $origin, 'response_ms' => 0,
            'ttfb_ms' => 0, 'content_type' => '', 'content_length' => 0,
            'warnings' => array(), 'error_type' => '', 'error_message' => '',
            'checked_at' => current_time( 'mysql', true ),
        );
        $start = microtime( true );
        if ( ! self::allowed_url( $origin, $scope ) ) {
            $result['error_type'] = 'invalid_or_blocked_url';
            $result['error_message'] = 'URL no permitida o no verificable desde este hosting (DNS/SSRF).';
            return $result;
        }

        $current = $origin;
        $seen = array();
        $hops = 0;
        do {
            if ( ! self::allowed_url( $current, $scope ) ) {
                $result['error_type'] = 'blocked_redirect';
                $result['error_message'] = 'La redirección conduce a una URL no autorizada o no verificable.';
                break;
            }
            if ( isset( $seen[ $current ] ) ) {
                $result['error_type'] = 'redirect_loop';
                $result['error_message'] = 'Bucle de redirección.';
                break;
            }
            $seen[ $current ] = true;
            $response = wp_safe_remote_get(
                $current,
                array(
                    'timeout' => max( 2, min( 6, (float) apply_filters( 'taxocommerce_system_check_url_timeout', 3 ) ) ),
                    'redirection' => 0, 'sslverify' => true,
                    'reject_unsafe_urls' => true,
                    'limit_response_size' => 2048,
                    'headers' => array( 'Accept' => '*/*', 'User-Agent' => 'TaxoCommerce-System-Check/' . TAXOCOMMERCE_SYSTEM_CHECK_VERSION ),
                )
            );
            if ( is_wp_error( $response ) ) {
                $result['error_type'] = 'http_request_failed';
                $result['error_message'] = substr( sanitize_text_field( $response->get_error_message() ), 0, 400 );
                break;
            }
            $status = (int) wp_remote_retrieve_response_code( $response );
            if ( ! $result['http_status'] ) {
                $result['http_status'] = $status;
            }
            $result['final_status'] = $status;
            $result['final_url'] = $current;
            $result['content_type'] = sanitize_text_field( (string) wp_remote_retrieve_header( $response, 'content-type' ) );
            $result['content_length'] = absint( wp_remote_retrieve_header( $response, 'content-length' ) );

            if ( $status < 300 || $status > 399 ) {
                break;
            }
            $location = (string) wp_remote_retrieve_header( $response, 'location' );
            if ( '' === $location || $hops >= 3 ) {
                $result['warnings'][] = 'Redirección HTTP ' . $status . ' sin destino verificable o con límite de saltos alcanzado.';
                break;
            }
            $next = self::resolve_redirect( $location, $current );
            $next = esc_url_raw( (string) $next, array( 'http', 'https' ) );
            if ( '' === $next ) {
                $result['error_type'] = 'invalid_redirect';
                $result['error_message'] = 'Redirección a una URL inválida.';
                break;
            }
            $result['warnings'][] = 'Redirección HTTP ' . $status . '.';
            $current = $next;
            $hops++;
        } while ( true );

        $result['response_ms'] = max( 0, (int) round( ( microtime( true ) - $start ) * 1000 ) );
        if ( $result['final_status'] >= 400 ) {
            $result['error_type'] = 'http_' . $result['final_status'];
            $result['error_message'] = 'La URL responde HTTP ' . $result['final_status'] . '.';
        } elseif ( 0 === (int) $result['final_status'] && '' === $result['error_type'] ) {
            $result['error_type'] = 'no_http_status';
            $result['error_message'] = 'No se ha recibido una respuesta HTTP válida.';
        }
        return $result;
    }

    /**
     * Read-only inventory for the System Check Processes tab.
     *
     * @return array
     */
    public static function process_rows() {
        if ( ! function_exists( 'seo_health_scan_active_run' ) ) {
            return array();
        }
        $rows = array();
        foreach ( self::scopes() as $scope => $label ) {
            $run = seo_health_scan_active_run( $scope );
            if ( ! is_array( $run ) || 'local' !== (string) ( $run['mode'] ?? '' ) ) {
                continue;
            }
            $status = sanitize_key( (string) ( $run['status'] ?? '' ) );
            if ( ! in_array( $status, array( 'queued', 'running', 'paused' ), true ) ) {
                continue;
            }
            $batch_size = max( 1, min( 8, (int) apply_filters( 'taxocommerce_system_check_url_batch_size', 5 ) ) );
            $completed = absint( $run['processed_items'] ?? 0 );
            $total = absint( $run['total_items'] ?? 0 );
            $next = $total > 0 ? min( $total, $completed + 1 ) : 0;
            $detail = sprintf(
                /* translators: 1: Maximum URLs per batch; 2: completed URLs; 3: total URLs; 4: next pending URL position. */
                __( 'Batch limit: %1$d URLs · progress: %2$d/%3$d · resume: next pending URL (approx. position %4$d).', 'taxocommerce-system-check' ),
                $batch_size, $completed, $total, $next
            );
            $rows[] = array(
                'id' => 'http:' . absint( $run['id'] ),
                /* translators: %s: Content type for a running HTTP audit (pages, posts, images or products). */
                'label' => sprintf( __( 'Auditoría HTTP: %s', 'taxocommerce-system-check' ), $label ),
                'status' => $status,
                'detail' => $detail,
                'can_stop' => true,
            );
        }
        return $rows;
    }

    /**
     * Cancel one local audit cooperatively; do not interrupt active PHP.
     *
     * @param int $run_id Existing audit ID.
     * @return bool
     */
    public static function stop_process( $run_id ) {
        $run_id = absint( $run_id );
        if ( ! $run_id ) {
            return false;
        }
        $found = false;
        foreach ( self::process_rows() as $row ) {
            if ( 'http:' . $run_id === $row['id'] ) {
                $found = true;
                break;
            }
        }
        if ( ! $found ) {
            return false;
        }
        global $wpdb;
        $now = current_time( 'mysql', true );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Coordinate live, plugin-owned audit state.
        $updated = $wpdb->update(
            self::table( 'runs' ),
            array( 'status' => 'cancelled', 'completed_at' => $now, 'updated_at' => $now, 'error_message' => __( 'Cancelado desde System Check.', 'taxocommerce-system-check' ), 'callback_token_hash' => '' ),
            array( 'id' => $run_id, 'mode' => 'local' ),
            array( '%s', '%s', '%s', '%s', '%s' ),
            array( '%d', '%s' )
        );
        if ( false === $updated || 0 === $updated ) {
            return false;
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Release queued audit items after state cancellation.
        $wpdb->query(
            $wpdb->prepare( 'UPDATE %i SET queued_scan_id=0,queued_at=NULL WHERE queued_scan_id=%d', self::table( 'items' ), $run_id )
        );
        wp_clear_scheduled_hook( self::HOOK, array( $run_id ) );
        if ( function_exists( 'as_unschedule_all_actions' ) ) {
            as_unschedule_all_actions( self::HOOK, array( $run_id ), self::GROUP );
        }
        return true;
    }

    public static function handle_action() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'No autorizado.', 'taxocommerce-system-check' ) );
        }
        check_admin_referer( self::ACTION );
        // The action nonce is verified above. Scope is restricted to four known values.
        $scope = self::scope( isset( $_POST['tcs_scope'] ) && is_string( $_POST['tcs_scope'] )
            ? sanitize_key( wp_unslash( $_POST['tcs_scope'] ) ) : 'page' );
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- The nonce is checked immediately above.
        $task = isset( $_POST['tcs_task'] ) && is_string( $_POST['tcs_task'] )
            ? sanitize_key( wp_unslash( $_POST['tcs_task'] ) ) : '';

        if ( ! self::supported() ) {
            self::redirect_notice( $scope, 'El inventario de chequeos no está disponible.', true );
        }
        seo_health_scan_maybe_upgrade();

        if ( function_exists( 'taxocommerce_core' ) && in_array( $task, array( 'start', 'resume', 'cancel' ), true ) ) {
            self::redirect_notice( $scope, 'Gestiona los trabajos desde TaxoCommerce Core → Procesos automáticos.', true );
        }
        if ( 'start' === $task ) {
            $result = self::start( $scope );
            self::redirect_notice( $scope, is_wp_error( $result )
                ? $result->get_error_message()
                : sprintf( 'Chequeo local iniciado: %d URLs, en lotes pequeños.', $result['count'] ),
                is_wp_error( $result )
            );
        }
        if ( 'sync' === $task ) {
            if ( self::running_anywhere() ) {
                self::redirect_notice( $scope, 'No se puede modificar el inventario con un chequeo en marcha.', true );
            }
            $result = self::sync_inventory( $scope );
            self::redirect_notice( $scope, is_wp_error( $result ) ? $result->get_error_message() : sprintf( 'Inventario actualizado: %d URL.', $result['count'] ), is_wp_error( $result ) );
        }
        if ( 'cancel' === $task || 'resume' === $task ) {
            $active = function_exists( 'seo_health_scan_active_run' ) ? seo_health_scan_active_run( $scope ) : null;
            if ( ! $active || 'local' !== (string) $active['mode'] ) {
                self::redirect_notice( $scope, 'No existe un chequeo local activo en esa pestaña.', true );
            }
            if ( 'cancel' === $task ) {
                global $wpdb;
                $run_id = absint( $active['id'] );
                $now = current_time( 'mysql', true );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct write coordinates the scheduler/diagnostic state; caching is not applicable.
                $wpdb->update(
                    self::table( 'runs' ),
                    array( 'status' => 'cancelled', 'completed_at' => $now, 'updated_at' => $now, 'error_message' => 'Cancelado por el administrador.', 'callback_token_hash' => '' ),
                    array( 'id' => $run_id ),
                    array( '%s', '%s', '%s', '%s', '%s' ),
                    array( '%d' )
                );
                $items = self::table( 'items' );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct write coordinates the scheduler/diagnostic state; caching is not applicable.
                $wpdb->query(
                    $wpdb->prepare( "UPDATE %i SET queued_scan_id=0,queued_at=NULL WHERE queued_scan_id=%d",
                 $items, $run_id )
                );
                self::redirect_notice( $scope, 'Chequeo cancelado; los elementos pendientes han quedado liberados.' );
            }
            $ok = self::schedule( absint( $active['id'] ), 1 );
            self::redirect_notice( $scope, $ok ? 'Se ha solicitado reanudar el trabajador local.' : 'No se ha podido reprogramar el trabajador.', ! $ok );
        }
        self::redirect_notice( $scope, 'Acción no reconocida.', true );
    }

    private static function redirect_notice( $scope, $message, $error = false ) {
        set_transient(
            'tcs_url_notice_' . get_current_user_id(),
            array( 'message' => sanitize_text_field( $message ), 'error' => (bool) $error ),
            MINUTE_IN_SECONDS
        );
        wp_safe_redirect( self::url( $scope ) );
        exit;
    }

    /**
     * Download all stored HTTP results for the selected resource type.
     * Read-only, paged SQL reads: no new scans or background processes.
     */
    public static function download_json() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'No autorizado.', 'taxocommerce-system-check' ) );
        }
        check_admin_referer( 'taxocommerce_system_check_http_json' );
        if ( ! self::supported() ) {
            wp_die( esc_html__( 'El inventario HTTP no está disponible.', 'taxocommerce-system-check' ) );
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verified immediately above; read-only JSON export scope.
        $scope = self::scope( isset( $_GET['tcs_scope'] ) && is_string( $_GET['tcs_scope'] )
            ? sanitize_key( wp_unslash( $_GET['tcs_scope'] ) ) : 'page' );
        seo_health_scan_maybe_upgrade();
        $summary = seo_health_scan_summary( $scope );
        $latest = function_exists( 'seo_health_scan_latest_run' ) ? seo_health_scan_latest_run( $scope ) : null;
        if ( is_array( $latest ) ) {
            unset( $latest['callback_token_hash'] );
        }

        global $wpdb;
        $items = self::table( 'items' );
        nocache_headers();
        header( 'Content-Type: application/json; charset=UTF-8' );
        header( 'X-Content-Type-Options: nosniff' );
        header( 'Content-Disposition: attachment; filename="system-check-http-' . $scope . '-' . gmdate( 'Ymd-His' ) . '.json"' );

        echo '{"type":"http","scope":' . wp_json_encode( $scope );
        echo ',"exported_at":' . wp_json_encode( gmdate( 'c' ) );
        echo ',"summary":' . wp_json_encode( $summary );
        echo ',"latest_run":' . wp_json_encode( $latest );
        echo ',"items":[';

        $last_id = 0;
        $first = true;
        $read_error = '';
        do {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared -- Internal WP-prefixed table; all user values bound below.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- This diagnostic queries current site/database state; cached values would hide live server changes.
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT id,object_id,label,url,source,status_bucket,http_status,final_status,
                            final_url,response_ms,ttfb_ms,content_type,content_length,error_type,
                            error_message,warnings,last_checked_at,last_ok_at,last_error_at
                     FROM %i WHERE scope=%s AND active=1 AND id>%d
                     ORDER BY id ASC LIMIT %d",
                 $items,
                    $scope, $last_id, 250
                ),
                ARRAY_A
            );
            if ( ! is_array( $rows ) || $wpdb->last_error ) {
                $read_error = 'No se pudieron consultar todos los resultados HTTP.';
                break;
            }
            foreach ( $rows as $row ) {
                $last_id = absint( $row['id'] );
                $warnings = json_decode( (string) $row['warnings'], true );
                $row['warnings'] = is_array( $warnings ) ? $warnings : array();
                echo $first ? '' : ',';
                echo wp_json_encode( $row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
                $first = false;
            }
        } while ( count( $rows ) === 250 );

        echo '],"export_error":' . wp_json_encode( $read_error ) . '}';
        exit;
    }

    public static function render() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'No autorizado.', 'taxocommerce-system-check' ) );
        }
        echo '<div class="wrap tcs-url-audit">' . '<h2>' . esc_html__( 'Chequeos HTTP de recursos', 'taxocommerce-system-check' ) . '</h2>';
        echo '<p>' . esc_html__( 'Comprueba las URL públicas desde este servidor mediante peticiones HTTP reales. No necesita GitHub. No equivale a un monitor externo de disponibilidad.', 'taxocommerce-system-check' ) . '</p>';
        if ( ! self::supported() ) {
            echo '<div class="notice notice-error">' . '<p>' . esc_html__( 'No se ha cargado el motor del inventario. Revisa la instalación de System Check.', 'taxocommerce-system-check' ) . '</p>' . '</div>' . '</div>';
            return;
        }
        seo_health_scan_maybe_upgrade();
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only allowlisted tab selection; no state changes.
        $scope = self::scope( isset( $_GET['tcs_scope'] ) && is_string( $_GET['tcs_scope'] )
            ? sanitize_key( wp_unslash( $_GET['tcs_scope'] ) ) : 'page' );
        // phpcs:enable WordPress.Security.NonceVerification.Recommended
        echo '<nav class="nav-tab-wrapper">';
        foreach ( self::scopes() as $code => $label ) {
            echo '<a class="nav-tab' . ( $scope === $code ? ' nav-tab-active' : '' ) . '" href="' . esc_url( self::url( $code ) ) . '">' . esc_html( $label ) . '</a>';
        }
        echo '</nav>';

        $notice = get_transient( 'tcs_url_notice_' . get_current_user_id() );
        if ( is_array( $notice ) && ! empty( $notice['message'] ) ) {
            delete_transient( 'tcs_url_notice_' . get_current_user_id() );
            echo '<div class="notice notice-' . ( ! empty( $notice['error'] ) ? 'error' : 'success' ) . ' is-dismissible"><p>' . esc_html( $notice['message'] ) . '</p></div>';
        }

        $summary = seo_health_scan_summary( $scope );
        $active = function_exists( 'seo_health_scan_active_run' ) ? seo_health_scan_active_run( $scope ) : null;
        $latest = function_exists( 'seo_health_scan_latest_run' ) ? seo_health_scan_latest_run( $scope ) : null;
        $busy = self::running_anywhere();
        $checked = (int) $summary['checked'];
        $total = (int) $summary['total'];
        $coverage = $total > 0 ? round( 100 * $checked / $total, 1 ) : 0;

        echo '<div class="tcs-stats">';
        foreach ( array(
            'URL activas' => $total,
            'Comprobadas' => $checked,
            'HTTP correcto' => (int) $summary['ok_count'],
            'Avisos' => (int) $summary['warning_count'],
            'Errores' => (int) $summary['error_count'],
            'Pendientes' => (int) $summary['pending'],
        ) as $label => $count ) {
            echo '<div class="tcs-stat"><strong>' . esc_html( number_format_i18n( $count ) ) . '</strong><span>' . esc_html( $label ) . '</span></div>';
        }
        echo '</div>';
        global $wpdb;
        $table = self::table( 'items' );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- This diagnostic queries current site/database state; cached values would hide live server changes.
        $codes = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT SUM(last_checked_at IS NOT NULL AND final_status BETWEEN 200 AND 299) AS http_2xx,
                        SUM(last_checked_at IS NOT NULL AND http_status BETWEEN 300 AND 399) AS http_3xx,
                        SUM(last_checked_at IS NOT NULL AND final_status BETWEEN 400 AND 499) AS http_4xx,
                        SUM(last_checked_at IS NOT NULL AND final_status=404) AS http_404,
                        SUM(last_checked_at IS NOT NULL AND final_status BETWEEN 500 AND 599) AS http_5xx
                 FROM %i WHERE scope=%s AND active=1",
                 $table,
                $scope
            ),
            ARRAY_A
        );
        echo '<div class="tcs-stats">';
        foreach ( array(
            'HTTP 2xx' => (int) ( $codes['http_2xx'] ?? 0 ),
            'HTTP 3xx inicial' => (int) ( $codes['http_3xx'] ?? 0 ),
            'HTTP 4xx' => (int) ( $codes['http_4xx'] ?? 0 ),
            'HTTP 404' => (int) ( $codes['http_404'] ?? 0 ),
            'HTTP 5xx' => (int) ( $codes['http_5xx'] ?? 0 ),
        ) as $label => $count ) {
            echo '<div class="tcs-stat"><strong>' . esc_html( number_format_i18n( $count ) ) . '</strong><span>' . esc_html( $label ) . '</span></div>';
        }
        echo '</div>';
        echo '<p><strong>Cobertura histórica:</strong> ' . esc_html( number_format_i18n( $coverage, 1 ) ) . '% (' . esc_html( number_format_i18n( $checked ) ) . '/' . esc_html( number_format_i18n( $total ) ) . ')</p>';

        echo '<div class="tcs-actions">';
        self::action_button( $scope, 'start', 'Iniciar chequeo de todas las URL', $busy, true );
        if ( $active && 'local' === (string) $active['mode'] ) {
            self::action_button( $scope, 'resume', 'Reanudar trabajador', false );
            self::action_button( $scope, 'cancel', 'Cancelar chequeo', false );
        }
        self::action_button( $scope, 'sync', 'Actualizar inventario', $busy );
        echo '</div>';
        if ( $active ) {
            echo '<p class="description"><strong>En curso:</strong> ' . esc_html( number_format_i18n( (int) $active['processed_items'] ) )
                . '/' . esc_html( number_format_i18n( (int) $active['total_items'] ) ) . ' · modo '
                . esc_html( 'local' === $active['mode'] ? 'worker local' : 'worker externo anterior' ) . '.</p>';
        }
        if ( $latest ) {
            echo '<div class="tcs-last"><strong>Última ejecución:</strong> ' . esc_html( (string) $latest['status'] )
                . ' · ' . esc_html( number_format_i18n( (int) $latest['processed_items'] ) )
                . '/' . esc_html( number_format_i18n( (int) $latest['total_items'] ) )
                . ' · HTTP 429: ' . esc_html( number_format_i18n( (int) $latest['status_429'] ) )
                . ' · HTTP 5xx: ' . esc_html( number_format_i18n( (int) $latest['status_5xx'] ) )
                . ' · media ' . esc_html( number_format_i18n( (int) $latest['avg_response_ms'] ) ) . ' ms'
                . ' · p95 ' . esc_html( number_format_i18n( (int) $latest['p95_response_ms'] ) ) . ' ms.</div>';
            if ( ! empty( $latest['error_message'] ) ) {
                echo '<p class="notice notice-error inline">' . esc_html( $latest['error_message'] ) . '</p>';
            }
        }
        self::render_rows( $scope );
        if ( $active && 'local' === (string) $active['mode'] ) {
            echo '<script>window.setTimeout(function(){if(!document.hidden){window.location.reload();}},15000);</script>';
        }
        echo '</div>';
    }

    private static function action_button( $scope, $task, $label, $disabled, $primary = false ) {
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '">';
        echo '<input type="hidden" name="tcs_scope" value="' . esc_attr( $scope ) . '">';
        echo '<input type="hidden" name="tcs_task" value="' . esc_attr( $task ) . '">';
        wp_nonce_field( self::ACTION );
        echo '<button class="button' . ( $primary ? ' button-primary' : '' ) . '"' . ( $disabled ? ' disabled' : '' ) . '>' . esc_html( $label ) . '</button></form>';
    }

    private static function render_rows( $scope ) {
        global $wpdb;
        $items = self::table( 'items' );
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- The following allowlisted GET values only filter read-only result lists; no data is mutated.
        $filter = isset( $_GET['tcs_filter'] ) && is_string( $_GET['tcs_filter'] )
            ? sanitize_key( wp_unslash( $_GET['tcs_filter'] ) ) : 'error';
        if ( ! in_array( $filter, array( 'error', 'warning', 'pending', 'ok', 'all' ), true ) ) {
            $filter = 'error';
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only search.
        $search = isset( $_GET['tcs_search'] ) && is_string( $_GET['tcs_search'] )
            ? sanitize_text_field( wp_unslash( $_GET['tcs_search'] ) ) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only pagination.
        $page = isset( $_GET['tcs_paged'] ) && is_scalar( $_GET['tcs_paged'] )
            ? max( 1, absint( wp_unslash( $_GET['tcs_paged'] ) ) ) : 1;
        // phpcs:enable WordPress.Security.NonceVerification.Recommended
        $where = array( 'scope=%s', 'active=1' );
        $params = array( $scope );
        if ( 'pending' === $filter ) {
            $where[] = 'last_checked_at IS NULL';
        } elseif ( 'all' !== $filter ) {
            $where[] = 'last_checked_at IS NOT NULL AND status_bucket=%s';
            $params[] = $filter;
        }
        if ( '' !== $search ) {
            $where[] = '(url LIKE %s OR label LIKE %s OR error_message LIKE %s)';
            $needle = '%' . $wpdb->esc_like( $search ) . '%';
            array_push( $params, $needle, $needle, $needle );
        }
        $condition = implode( ' AND ', $where );
        // The WHERE clause contains only fixed filter templates; all submitted values are bound.
        $count_sql = 'SELECT COUNT(*) FROM %i WHERE ' . $condition;
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Constant WHERE templates and bound values; identifier is parameterized.
// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- WHERE filters are assembled only from fixed clauses, all user values use bound placeholders and the table uses %i.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- This diagnostic queries current site/database state; cached values would hide live server changes.
        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- $count_sql is assembled only from fixed WHERE fragments; %i and submitted values bind via prepare().
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Live URL-audit count
        // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter -- SQL WHERE components are fixed allowlisted templates; all values bound via prepare(). must reflect the current scan.
        $count = (int) $wpdb->get_var(
            $wpdb->prepare( $count_sql, array_merge( array( $items ), $params ) )
        );
        // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared
        $per_page = 40;
        $page_sql = 'SELECT id,label,url,status_bucket,http_status,final_status,response_ms,error_type,error_message,last_checked_at,warnings'
            . ' FROM %i WHERE ' . $condition
            . " ORDER BY CASE status_bucket WHEN 'error' THEN 0 WHEN 'warning' THEN 1 WHEN 'pending' THEN 2 ELSE 3 END,"
            . ' last_checked_at ASC,id ASC LIMIT %d OFFSET %d';
// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- WHERE fragments come only from fixed filters, all values and the %i identifier are bound with prepare().
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- This diagnostic queries current site/database state; cached values would hide live server changes.
        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- $page_sql uses only allowlisted WHERE clauses with bound values, identifier, limit and offset.
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Live URL-audit pagination
        // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter -- SQL WHERE components are fixed allowlisted templates; all values bound via prepare(). must reflect the current scan.
        $rows = $wpdb->get_results(
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- WHERE fragments are fixed, values and pagination use prepare().
            $wpdb->prepare(
                $page_sql,
                array_merge( array( $items ), $params, array( $per_page, ( $page - 1 ) * $per_page ) )
            ),
            ARRAY_A
        );
        // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared

        echo '<h2>' . esc_html__( 'Detalle por URL', 'taxocommerce-system-check' ) . '</h2>' . '<div class="tcs-filter">';
        foreach ( array( 'error' => 'Errores', 'warning' => 'Avisos', 'pending' => 'Pendientes', 'ok' => 'Correctas', 'all' => 'Todas' ) as $code => $label ) {
            echo '<a class="button' . ( $filter === $code ? ' button-primary' : '' ) . '" href="' . esc_url( self::url( $scope, array( 'tcs_filter' => $code ) ) ) . '">' . esc_html( $label ) . '</a>';
        }
        echo '</div><form method="get" class="tcs-filter">';
        echo '<input type="hidden" name="page" value="taxocommerce-system-check"><input type="hidden" name="tcs_tab" value="http"><input type="hidden" name="tcs_scope" value="' . esc_attr( $scope ) . '">';
        echo '<input type="hidden" name="tcs_filter" value="' . esc_attr( $filter ) . '">';
        echo '<input type="search" name="tcs_search" value="' . esc_attr( $search ) . '" placeholder="Buscar URL o error">';
        echo '<button class="button">' . esc_html__( 'Buscar', 'taxocommerce-system-check' ) . '</button>' . '</form>';
        echo '<table class="widefat striped">' . '<thead>' . '<tr>' . '<th>' . esc_html__( 'Recurso', 'taxocommerce-system-check' ) . '</th>' . '<th>' . esc_html__( 'Estado', 'taxocommerce-system-check' ) . '</th>' . '<th>' . esc_html__( 'HTTP', 'taxocommerce-system-check' ) . '</th>' . '<th>' . esc_html__( 'Tiempo', 'taxocommerce-system-check' ) . '</th>' . '<th>' . esc_html__( 'Último chequeo', 'taxocommerce-system-check' ) . '</th>' . '<th>' . esc_html__( 'Detalle', 'taxocommerce-system-check' ) . '</th>' . '</tr>' . '</thead>' . '<tbody>';
        if ( ! $rows ) {
            echo '<tr>' . '<td colspan="6">' . esc_html__( 'No hay URLs para este filtro.', 'taxocommerce-system-check' ) . '</td>' . '</tr>';
        }
        foreach ( (array) $rows as $row ) {
            $state = empty( $row['last_checked_at'] ) ? 'pending' : (string) $row['status_bucket'];
            $warnings = json_decode( (string) $row['warnings'], true );
            $detail = (string) $row['error_message'];
            if ( is_array( $warnings ) && ! empty( $warnings ) ) {
                $detail .= ( $detail ? ' · ' : '' ) . implode( ' · ', array_slice( $warnings, 0, 2 ) );
            }
            $http_code = absint( $row['final_status'] ?: $row['http_status'] );
            echo '<tr><td><strong>' . esc_html( $row['label'] ?: wp_basename( (string) wp_parse_url( $row['url'], PHP_URL_PATH ) ) ) . '</strong><br>';
            echo '<a href="' . esc_url( $row['url'] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $row['url'] ) . '</a></td>';
            echo '<td>' . esc_html( ucfirst( $state ) ) . '</td><td>' . ( $http_code ? esc_html( (string) $http_code ) : '—' ) . '</td>';
            echo '<td>' . ( $row['last_checked_at'] ? esc_html( number_format_i18n( (int) $row['response_ms'] ) ) . ' ms' : '—' ) . '</td>';
            echo '<td>' . esc_html( $row['last_checked_at'] ?: '—' ) . '</td><td>' . esc_html( $detail ?: '—' ) . '</td></tr>';
        }
        echo '</tbody></table>';
        $pages = (int) ceil( $count / $per_page );
        if ( $pages > 1 ) {
            echo '<div class="tablenav"><div class="tablenav-pages">' . wp_kses_post(
                paginate_links(
                    array(
                        'base' => self::url( $scope, array( 'tcs_filter' => $filter, 'tcs_search' => $search, 'tcs_paged' => '%#%' ) ),
                        'format' => '',
                        'current' => $page,
                        'total' => $pages,
                        'prev_text' => '‹',
                        'next_text' => '›',
                    )
                )
            ) . '</div></div>';
        }
    }
}
