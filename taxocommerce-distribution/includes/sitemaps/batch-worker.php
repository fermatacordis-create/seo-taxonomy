<?php
/**
 * Incremental, independently throttled XML sitemap publisher.
 *
 * Every HTTP/cron window processes at most 250 catalog candidates or a small
 * number of already validated files. Public sitemap.xml is replaced last.
 */
defined( 'ABSPATH' ) || exit;

const TCDIST_SITEMAP_JOB_OPTION = 'tcdist_sitemap_background_state_v1';
const TCDIST_SITEMAP_SPEED_OPTION = 'tcdist_sitemap_speed_v1';
const TCDIST_SITEMAP_WORKER_HOOK = 'tcdist_sitemap_background_worker';
const TCDIST_SITEMAP_ITEMS_PER_WINDOW = 250;

function tcdist_sitemap_speed() {
    return max( 1, min( 5, absint( get_option( TCDIST_SITEMAP_SPEED_OPTION, 2 ) ) ) );
}

function tcdist_sitemap_speed_labels() {
    return array(
        1 => __( 'Suave', 'taxocommerce-distribution' ),
        2 => __( 'Baja', 'taxocommerce-distribution' ),
        3 => __( 'Normal', 'taxocommerce-distribution' ),
        4 => __( 'Alta', 'taxocommerce-distribution' ),
        5 => __( 'Máxima', 'taxocommerce-distribution' ),
    );
}

function tcdist_sitemap_speed_delay() {
    $delays = array( 1 => 15, 2 => 8, 3 => 4, 4 => 2, 5 => 1 );
    return $delays[ tcdist_sitemap_speed() ];
}

function tcdist_sitemap_job_state() {
    $state = get_option( TCDIST_SITEMAP_JOB_OPTION, array() );
    return is_array( $state ) ? $state : array();
}

/**
 * Read the timestamp of the last *published* valid index, never job start.
 * The manifest is updated at the successful finish; fall back to the live
 * main index for installations upgraded from older Distribution versions.
 */
function tcdist_sitemap_publication_time() {
    $storage = tcdist_sitemap_sitemap_storage();
    if ( is_wp_error( $storage ) ) {
        return 0;
    }
    $index = $storage['dir'] . 'sitemap.xml';
    if ( ! is_file( $index ) ) {
        return 0;
    }
    $manifest = tcdist_sitemap_get_sitemap_manifest();
    $time = ! empty( $manifest['generated_at_utc'] )
        ? strtotime( (string) $manifest['generated_at_utc'] )
        : false;
    return $time ? (int) $time : (int) filemtime( $index );
}

function tcdist_sitemap_save_job_state( $state ) {
    update_option( TCDIST_SITEMAP_JOB_OPTION, $state, false );
}

function tcdist_sitemap_schedule_window( $delay = 1 ) {
    if ( wp_next_scheduled( TCDIST_SITEMAP_WORKER_HOOK ) ) {
        return true;
    }
    $scheduled = wp_schedule_single_event( time() + max( 1, absint( $delay ) ), TCDIST_SITEMAP_WORKER_HOOK );
    return ! is_wp_error( $scheduled ) && false !== $scheduled;
}

/**
 * Stable, unique child file names for one generation. Existing public child
 * files stay untouched until the new validated main index is committed.
 */
function tcdist_sitemap_scoped_filename( $state, $phase, $number ) {
    $folder = (string) ( $state['folder'] ?? '' );
    if ( ! preg_match( '/^\\.build-([0-9a-f-]{36})$/', $folder, $matches ) ) {
        throw new RuntimeException( 'Invalid sitemap generation identifier.' );
    }
    $token = substr( str_replace( '-', '', $matches[1] ), 0, 12 );
    return 'sitemap-' . sanitize_key( $phase ) . '-' . $token . '-' . absint( $number ) . '.xml';
}

/**
 * Only folders generated internally may be used for cleanup/publication.
 */
function tcdist_sitemap_job_build_directory( $state ) {
    $folder = isset( $state['folder'] ) ? (string) $state['folder'] : '';
    if ( ! preg_match( '/^\\.build-[0-9a-f-]{36}$/', $folder ) ) {
        return new WP_Error( 'tcdist_sitemap_invalid_build_folder', 'Invalid sitemap build folder.' );
    }
    $storage = tcdist_sitemap_sitemap_storage();
    if ( is_wp_error( $storage ) ) {
        return $storage;
    }
    return $storage['dir'] . $folder . '/';
}

/**
 * Starts a new background job. No catalog items are processed here.
 */
function tcdist_sitemap_start_background_build() {
    if ( function_exists( 'tcdist_sitemap_external_owner' ) && '' !== tcdist_sitemap_external_owner() ) {
        return array( 'type' => 'error', 'message' => __( 'Distribution no gestiona el sitemap XML en esta instalación.', 'taxocommerce-distribution' ) );
    }

    $previous = tcdist_sitemap_job_state();
    if ( 'running' === ( $previous['status'] ?? '' ) ) {
        $previous['rerun_required'] = 1;
        tcdist_sitemap_save_job_state( $previous );
        tcdist_sitemap_schedule_window( 1 );
        return array( 'type' => 'success', 'message' => __( 'Ya hay una generación en curso. Se ha solicitado una actualización posterior.', 'taxocommerce-distribution' ) );
    }

    $storage = tcdist_sitemap_sitemap_storage();
    if ( is_wp_error( $storage ) ) {
        return array( 'type' => 'error', 'message' => $storage->get_error_message() );
    }
    if ( ! is_dir( $storage['dir'] ) && ! wp_mkdir_p( $storage['dir'] ) ) {
        return array( 'type' => 'error', 'message' => __( 'No se puede crear el directorio de sitemaps.', 'taxocommerce-distribution' ) );
    }

    $state = array(
        'status'         => 'running',
        'phase'          => 'products',
        'folder'         => '.build-' . wp_generate_uuid4(),
        'cursor'         => 0,
        'offset'         => 0,
        'file_number'    => 0,
        'publish_cursor' => 0,
        'files'          => array(),
        'processed'      => 0,
        'urls'           => 0,
        'started_at'     => time(),
        'updated_at'     => time(),
        'not_before'     => time(),
        'rerun_required' => 0,
        'last_sample'    => array(),
        'message'        => '',
    );

    $build_dir = tcdist_sitemap_job_build_directory( $state );
    if ( is_wp_error( $build_dir ) || ! wp_mkdir_p( $build_dir ) ) {
        return array( 'type' => 'error', 'message' => __( 'No se pudo preparar el directorio temporal.', 'taxocommerce-distribution' ) );
    }

    tcdist_sitemap_save_job_state( $state );
    if ( ! tcdist_sitemap_schedule_window( 1 ) ) {
        $state['status'] = 'error';
        $state['message'] = __( 'WordPress no ha podido programar el generador de sitemaps. Comprueba WP-Cron.', 'taxocommerce-distribution' );
        tcdist_sitemap_save_job_state( $state );
        tcdist_sitemap_remove_directory( $build_dir );
        return array( 'type' => 'error', 'message' => $state['message'] );
    }
    return array( 'type' => 'success', 'message' => __( 'Generación iniciada en segundo plano: lotes de 250 URL. El índice actual permanece publicado.', 'taxocommerce-distribution' ) );
}

/**
 * Obtain at most 250 items, without loading the entire product catalog.
 * Other groups reuse the established role/category rules, in bounded slices.
 */
function tcdist_sitemap_next_window_items( $state ) {
    $phase = $state['phase'];
    $limit = TCDIST_SITEMAP_ITEMS_PER_WINDOW;
    if ( 'products' === $phase ) {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Fresh, bounded seek query against WP posts; caching would skip newly published products.
        $ids = (array) $wpdb->get_col(
            $wpdb->prepare(
                "SELECT ID FROM %i WHERE post_type = %s AND post_status = 'publish' AND post_password = '' AND ID > %d ORDER BY ID ASC LIMIT %d",
                $wpdb->posts,
                'product',
                absint( $state['cursor'] ?? 0 ),
                $limit
            )
        );
        return array( 'raw' => $ids, 'entries' => tcdist_sitemap_posts_to_sitemap_urls( $ids, true ), 'last_id' => $ids ? absint( end( $ids ) ) : 0 );
    }

    switch ( $phase ) {
        case 'product-categories':
            $all = tcdist_sitemap_get_product_category_sitemap_urls();
            $slice = array_slice( $all, absint( $state['offset'] ?? 0 ), $limit );
            return array( 'raw' => $slice, 'entries' => $slice );
        case 'posts':
            $ids = tcdist_sitemap_get_published_post_ids( 'post' );
            break;
        case 'clusters':
            $ids = tcdist_sitemap_get_node_page_ids_by_roles( array( 'cluster' ) );
            break;
        case 'hubs':
            $ids = tcdist_sitemap_get_node_page_ids_by_roles( array( 'hub_primary', 'hub_secondary' ) );
            break;
        case 'pages':
            $ids = tcdist_sitemap_filter_generic_page_ids_for_sitemap( tcdist_sitemap_get_published_post_ids( 'page' ) );
            break;
        default:
            throw new RuntimeException( 'Unknown sitemap phase.' );
    }

    sort( $ids, SORT_NUMERIC );
    $slice = array_slice( $ids, absint( $state['offset'] ?? 0 ), $limit );
    return array( 'raw' => $slice, 'entries' => tcdist_sitemap_posts_to_sitemap_urls( $slice, false ) );
}

function tcdist_sitemap_next_phase( $phase ) {
    $phases = array( 'products', 'product-categories', 'posts', 'clusters', 'hubs', 'pages', 'publish', 'index', 'finish' );
    $index = array_search( $phase, $phases, true );
    return false === $index || ! isset( $phases[ $index + 1 ] ) ? 'finish' : $phases[ $index + 1 ];
}

function tcdist_sitemap_write_window( &$state, $build_dir ) {
    $phase = $state['phase'];
    $window = tcdist_sitemap_next_window_items( $state );
    $items = (array) $window['raw'];

    if ( ! $items ) {
        $state['phase'] = tcdist_sitemap_next_phase( $phase );
        $state['cursor'] = 0;
        $state['offset'] = 0;
        $state['file_number'] = 0;
        return;
    }

    $state['processed'] += count( $items );
    if ( 'products' === $phase ) {
        $state['cursor'] = absint( $window['last_id'] );
    } else {
        $state['offset'] += count( $items );
    }

    $entries = tcdist_sitemap_prepare_sitemap_urls( $window['entries'] );
    if ( ! $entries ) {
        return;
    }

    $state['file_number']++;
    $filename = tcdist_sitemap_scoped_filename( $state, $phase, $state['file_number'] );
    $written = tcdist_sitemap_write_validated_xml(
        $build_dir . $filename,
        tcdist_sitemap_build_urlset_xml( $entries ),
        'urlset'
    );
    if ( is_wp_error( $written ) ) {
        throw new RuntimeException( esc_html( $written->get_error_message() ) );
    }

    $state['urls'] += absint( $written['count'] );
    $state['files'][] = array(
        'filename'  => $filename,
        'url_count' => absint( $written['count'] ),
        'bytes'     => absint( $written['bytes'] ),
        'lastmod'   => gmdate( 'c' ),
        'group'     => $phase,
    );
}

/**
 * Publish a few validated children per window. The main index is published
 * after every child, avoiding an index pointing to missing new files.
 */
function tcdist_sitemap_publish_window( &$state, $build_dir, $public_dir ) {
    if ( ! $state['files'] ) {
        throw new RuntimeException( 'No public URLs were found for sitemap generation.' );
    }

    $limit = 5;
    $done = 0;
    while ( $done < $limit && isset( $state['files'][ $state['publish_cursor'] ] ) ) {
        $filename = $state['files'][ $state['publish_cursor'] ]['filename'];
        // Upgrade an unfinished job created before generation-scoped names:
        // rename inside the private build directory before any public write.
        if ( ! preg_match( '/^sitemap-[a-z0-9-]+-[a-f0-9]{12}-[0-9]+\\.xml$/', $filename ) ) {
            $replacement = tcdist_sitemap_scoped_filename(
                $state,
                (string) $state['files'][ $state['publish_cursor'] ]['group'],
                $state['publish_cursor'] + 1
            );
            if ( ! is_file( $build_dir . $filename ) ||
                 ! tcdist_sitemap_atomic_rename( $build_dir . $filename, $build_dir . $replacement ) ) {
                throw new RuntimeException( 'Cannot upgrade the pending sitemap child filename.' );
            }
            $filename = $replacement;
            $state['files'][ $state['publish_cursor'] ]['filename'] = $filename;
        }
        if ( ! tcdist_sitemap_publish_sitemap_file( $build_dir . $filename, $public_dir . $filename ) ) {
            throw new RuntimeException( esc_html( 'Cannot publish validated sitemap: ' . $filename ) );
        }
        $state['publish_cursor']++;
        $done++;
    }

    if ( count( $state['files'] ) <= $state['publish_cursor'] ) {
        $state['phase'] = 'index';
    }
}

function tcdist_sitemap_publish_index( &$state, $build_dir, $public_dir ) {
    $xml = tcdist_sitemap_build_sitemap_index_xml( $state['files'] );
    foreach ( array( 'sitemap-index.xml', 'sitemap_index.xml', 'sitemap.xml' ) as $filename ) {
        $written = tcdist_sitemap_write_validated_xml( $build_dir . $filename, $xml, 'sitemapindex' );
        if ( is_wp_error( $written ) ) {
            throw new RuntimeException( esc_html( $written->get_error_message() ) );
        }
        if ( ! tcdist_sitemap_publish_sitemap_file( $build_dir . $filename, $public_dir . $filename ) ) {
            throw new RuntimeException( esc_html( 'Cannot publish sitemap index: ' . $filename ) );
        }
    }
    $state['phase'] = 'finish';
}

function tcdist_sitemap_finish_background_build( &$state, $build_dir, $public_dir ) {
    $validation = tcdist_sitemap_validate_xml_file( $public_dir . 'sitemap.xml', 'sitemapindex' );
    if ( empty( $validation['valid'] ) ) {
        throw new RuntimeException( 'Published sitemap index failed XML validation.' );
    }

    $files = (array) $state['files'];
    $keep = array_fill_keys( array_merge( array_column( $files, 'filename' ), array( 'sitemap.xml', 'sitemap-index.xml', 'sitemap_index.xml' ) ), true );

    $manifest = array(
        'schema_version'   => TCDIST_SITEMAP_SCHEMA_VERSION,
        'generated_at_utc' => gmdate( 'c' ),
        'main_url'         => tcdist_sitemap_sitemap_public_url( 'sitemap.xml' ),
        'total_urls'       => absint( $state['urls'] ),
        'child_files'      => count( $files ),
        'files'            => $files,
    );
    if ( ! tcdist_sitemap_write_sitemap_manifest( $public_dir, $manifest ) ) {
        throw new RuntimeException( 'Cannot save sitemap manifest.' );
    }

    foreach ( (array) glob( $public_dir . 'sitemap*.xml' ) as $file ) {
        if ( ! isset( $keep[ basename( $file ) ] ) ) {
            wp_delete_file( $file );
        }
    }

    $state['status'] = 'completed';
    $state['phase'] = 'completed';
    $state['finished_at'] = time();
    $state['message'] = sprintf(
        /* translators: %1$d: URLs published; %2$d: XML files. */
        __( 'Completado: %1$d URL repartidas entre %2$d archivos XML.', 'taxocommerce-distribution' ),
        absint( $state['urls'] ),
        count( $files )
    );
    tcdist_sitemap_remove_directory( $build_dir );
}

function tcdist_sitemap_php_cpu_seconds() {
    if ( ! function_exists( 'getrusage' ) ) {
        return null;
    }
    $r = getrusage();
    if ( ! is_array( $r ) ) {
        return null;
    }
    return (float) ( $r['ru_utime.tv_sec'] ?? 0 ) + (float) ( $r['ru_utime.tv_usec'] ?? 0 ) / 1000000
        + (float) ( $r['ru_stime.tv_sec'] ?? 0 ) + (float) ( $r['ru_stime.tv_usec'] ?? 0 ) / 1000000;
}

function tcdist_sitemap_memory_percent() {
    $limit = function_exists( 'wp_convert_hr_to_bytes' ) ? wp_convert_hr_to_bytes( ini_get( 'memory_limit' ) ) : 0;
    return $limit > 0 && function_exists( 'memory_get_peak_usage' )
        ? round( 100 * memory_get_peak_usage( true ) / $limit, 1 )
        : null;
}

/**
 * One bounded unit of work per WP-Cron or authenticated admin AJAX request.
 * The lock is shared with the old synchronous publisher.
 */
function tcdist_sitemap_background_worker_tick() {
    $state = tcdist_sitemap_job_state();
    if ( 'running' === ( $state['status'] ?? '' ) && function_exists( 'tcdist_sitemap_external_owner' ) && '' !== tcdist_sitemap_external_owner() ) {
        // Never publish a window after another sitemap owner takes control.
        tcdist_sitemap_stop_background_build();
        return tcdist_sitemap_job_state();
    }
    if ( 'running' !== ( $state['status'] ?? '' ) ) {
        return $state;
    }
    if ( absint( $state['not_before'] ?? 0 ) > time() ) {
        tcdist_sitemap_schedule_window( absint( $state['not_before'] ) - time() );
        return $state;
    }

    $storage = tcdist_sitemap_sitemap_storage();
    if ( is_wp_error( $storage ) ) {
        $state['status'] = 'error';
        $state['message'] = $storage->get_error_message();
        $state['updated_at'] = time();
        tcdist_sitemap_save_job_state( $state );
        return $state;
    }
    $lock = tcdist_sitemap_lock_stream_open( $storage['dir'] . '.generation.lock', 'c' );
    if ( ! $lock || ! @flock( $lock, LOCK_EX | LOCK_NB ) ) {
        if ( is_resource( $lock ) ) {
            tcdist_sitemap_lock_stream_close( $lock );
        }
        if ( ! tcdist_sitemap_schedule_window( 15 ) ) {
            $state['status'] = 'error';
            $state['message'] = __( 'No se puede reprogramar la generación de sitemaps.', 'taxocommerce-distribution' );
            $state['updated_at'] = time();
            tcdist_sitemap_save_job_state( $state );
        }
        return $state;
    }

    $begin = microtime( true );
    $cpu_begin = tcdist_sitemap_php_cpu_seconds();
    try {
        $state = tcdist_sitemap_job_state();
        if ( 'running' !== ( $state['status'] ?? '' ) ) {
            return $state;
        }
        $dir = tcdist_sitemap_job_build_directory( $state );
        if ( is_wp_error( $dir ) || ! is_dir( $dir ) ) {
            throw new RuntimeException( 'Sitemap staging directory is missing.' );
        }

        if ( in_array( $state['phase'], array( 'products', 'product-categories', 'posts', 'clusters', 'hubs', 'pages' ), true ) ) {
            tcdist_sitemap_write_window( $state, $dir );
        } elseif ( 'publish' === $state['phase'] ) {
            tcdist_sitemap_publish_window( $state, $dir, $storage['dir'] );
        } elseif ( 'index' === $state['phase'] ) {
            tcdist_sitemap_publish_index( $state, $dir, $storage['dir'] );
        } elseif ( 'finish' === $state['phase'] ) {
            tcdist_sitemap_finish_background_build( $state, $dir, $storage['dir'] );
        } else {
            throw new RuntimeException( 'Unknown sitemap worker state.' );
        }

        $elapsed = max( 0, microtime( true ) - $begin );
        $cpu_end = tcdist_sitemap_php_cpu_seconds();
        $cpu = null !== $cpu_begin && null !== $cpu_end && $elapsed > 0
            ? min( 100, round( 100 * max( 0, $cpu_end - $cpu_begin ) / $elapsed, 1 ) )
            : null;
        $memory = tcdist_sitemap_memory_percent();
        $delay = tcdist_sitemap_speed_delay();

        // CPU and memory are from this PHP worker, not the hosting account.
        if ( $elapsed > 3 || ( null !== $memory && $memory >= 75 ) || ( null !== $cpu && $cpu >= 95 && $elapsed >= 2 ) ) {
            $delay = max( $delay, (int) ceil( $elapsed * 2 ), tcdist_sitemap_speed_delay() * 2 );
        }
        $state['last_sample'] = array(
            'seconds' => round( $elapsed, 2 ),
            'cpu_percent' => $cpu,
            'memory_percent' => $memory,
            'delay_seconds' => $delay,
            'at' => time(),
        );
        $state['updated_at'] = time();
        $state['not_before'] = time() + $delay;
        tcdist_sitemap_save_job_state( $state );
        if ( 'running' === $state['status'] ) {
            if ( ! tcdist_sitemap_schedule_window( $delay ) ) {
                $state['status'] = 'error';
                $state['message'] = __( 'WP-Cron no ha podido programar el siguiente bloque; los XML publicados no se han sustituido.', 'taxocommerce-distribution' );
                $state['updated_at'] = time();
                tcdist_sitemap_save_job_state( $state );
            }
        } elseif ( ! empty( $state['rerun_required'] ) ) {
            // A content change requested a fresh pass during the previous run.
            wp_schedule_single_event( time() + 5, 'tcdist_sitemap_regenerate_sitemaps_event' );
        }
    } catch ( Throwable $e ) {
        $state['status'] = 'error';
        $state['message'] = sanitize_text_field( $e->getMessage() );
        $state['updated_at'] = time();
        tcdist_sitemap_save_job_state( $state );
        $dir = tcdist_sitemap_job_build_directory( $state );
        if ( ! is_wp_error( $dir ) ) {
            tcdist_sitemap_remove_directory( $dir );
        }
    } finally {
        @flock( $lock, LOCK_UN );
        tcdist_sitemap_lock_stream_close( $lock );
    }
    return $state;
}
add_action( TCDIST_SITEMAP_WORKER_HOOK, 'tcdist_sitemap_background_worker_tick' );

/** Recover the worker when a previous PHP process was interrupted or cron stalled. */
function tcdist_sitemap_recover_background_worker() {
    if ( function_exists( 'tcdist_sitemap_external_owner' ) && '' !== tcdist_sitemap_external_owner() ) {
        return;
    }

    $state = tcdist_sitemap_job_state();
    if ( 'running' === ( $state['status'] ?? '' ) && ! wp_next_scheduled( TCDIST_SITEMAP_WORKER_HOOK ) ) {
        $delay = max( 1, absint( $state['not_before'] ?? 0 ) - time() );
        if ( ! tcdist_sitemap_schedule_window( $delay ) ) {
            $state['status'] = 'error';
            $state['message'] = __( 'WP-Cron no puede recuperar la generación interrumpida.', 'taxocommerce-distribution' );
            $state['updated_at'] = time();
            tcdist_sitemap_save_job_state( $state );
        }
    }
}
add_action( 'init', 'tcdist_sitemap_recover_background_worker', 40 );

function tcdist_sitemap_stop_background_build() {
    $state = tcdist_sitemap_job_state();
    if ( 'running' !== ( $state['status'] ?? '' ) ) {
        return true;
    }
    $storage = tcdist_sitemap_sitemap_storage();
    if ( is_wp_error( $storage ) ) {
        return $storage;
    }
    $lock = tcdist_sitemap_lock_stream_open( $storage['dir'] . '.generation.lock', 'c' );
    if ( ! $lock || ! @flock( $lock, LOCK_EX | LOCK_NB ) ) {
        if ( is_resource( $lock ) ) {
            tcdist_sitemap_lock_stream_close( $lock );
        }
        return new WP_Error( 'sitemap_busy', __( 'Hay un bloque en ejecución; inténtalo de nuevo.', 'taxocommerce-distribution' ) );
    }
    try {
        $state['status'] = 'stopped';
        $state['message'] = __( 'Generación detenida. Los sitemaps publicados siguen disponibles.', 'taxocommerce-distribution' );
        tcdist_sitemap_save_job_state( $state );
        wp_clear_scheduled_hook( TCDIST_SITEMAP_WORKER_HOOK );
        $dir = tcdist_sitemap_job_build_directory( $state );
        if ( ! is_wp_error( $dir ) ) {
            tcdist_sitemap_remove_directory( $dir );
        }
    } finally {
        @flock( $lock, LOCK_UN );
        tcdist_sitemap_lock_stream_close( $lock );
    }
    return true;
}
