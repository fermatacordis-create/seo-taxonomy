<?php
/**
 * Bounded background runner for the five content audits.
 *
 * All catalogue reads use WP_Query, term APIs and WooCommerce CRUD. No seo_*
 * tables, legacy callbacks, or bulk catalogue mutations are used.
 */

namespace TaxoCommerce\Auditor;

defined( 'ABSPATH' ) || exit;

final class Runner {
    const HOOK       = 'taxocommerce_auditor_process_batch';
    const BATCH_SIZE = 500;
    const MAX_STORED_FINDINGS = 400;
    const MAX_QUALITY_ROWS    = 100;
    const MAX_INDEXED_TITLES  = 25000;
    const MAX_REBALANCE_PROPOSALS = 150;

    public static function scopes() {
        return array(
            'products'   => __( 'Productos · contenido e identidad', 'taxocommerce-auditor' ),
            'categories' => __( 'Categorías y arquitectura', 'taxocommerce-auditor' ),
            'posts'      => __( 'Posts', 'taxocommerce-auditor' ),
            'pages'      => __( 'Páginas y landings', 'taxocommerce-auditor' ),
            'faqs'       => __( 'FAQs', 'taxocommerce-auditor' ),
        );
    }

    public static function valid_scope( $scope ) {
        return is_string( $scope ) && isset( self::scopes()[ $scope ] );
    }

    public static function faq_post_type() {
        $types = apply_filters( 'taxocommerce_auditor_faq_post_types', array( 'tc_faq', 'taxocommerce_faq', 'faq' ) );
        foreach ( (array) $types as $type ) {
            $type = sanitize_key( (string) $type );
            if ( post_type_exists( $type ) ) {
                return $type;
            }
        }
        return '';
    }

    private static function option( $scope, $kind ) {
        return 'taxocommerce_auditor_' . $kind . '_' . $scope;
    }

    public static function state( $scope ) {
        return self::valid_scope( $scope ) ? (array) get_option( self::option( $scope, 'state' ), array() ) : array();
    }

    public static function report( $scope ) {
        return self::valid_scope( $scope ) ? (array) get_option( self::option( $scope, 'report' ), array() ) : array();
    }

    public static function history( $scope ) {
        return self::valid_scope( $scope )
            ? (array) get_option( self::option( $scope, 'history' ), array() )
            : array();
    }

    private static function persisted_decision_context( $scope ) {
        // Optional integration; other services must pass an already-persisted
        // snapshot. Never initiate remote queries from a content audit.
        $context = apply_filters( 'taxocommerce_auditor_decision_context', null, $scope );
        return is_array( $context ) && $context ? array_slice( $context, 0, 10, true ) : null;
    }

    public static function register() {
        add_action( self::HOOK, array( __CLASS__, 'run_batch' ), 10, 1 );
        add_action( 'admin_init', array( __CLASS__, 'ensure_scheduled' ) );
    }

    public static function deactivate() {
        foreach ( array_keys( self::scopes() ) as $scope ) {
            wp_clear_scheduled_hook( self::HOOK, array( $scope ) );
        }
    }

    private static function enqueue( $scope ) {
        if ( ! wp_next_scheduled( self::HOOK, array( $scope ) ) ) {
            wp_schedule_single_event( time() + Workload::delay_seconds( $scope ), self::HOOK, array( $scope ) );
        }
    }

    /**
     * Apply a speed change to a queued/running scope without resetting its cursor.
     */
    public static function reschedule( $scope ) {
        if ( ! self::valid_scope( $scope ) ) {
            return;
        }
        $state = self::state( $scope );
        if ( in_array( (string) ( $state['status'] ?? '' ), array( 'queued', 'running' ), true ) ) {
            wp_clear_scheduled_hook( self::HOOK, array( $scope ) );
            self::enqueue( $scope );
        }
    }

    /**
     * Browser-assisted worker eligibility. WP-Cron remains the default worker;
     * the admin monitor may recover a due batch if hosting loopback is broken.
     *
     * @param string $scope Audit scope.
     * @return bool
     */
    public static function browser_batch_due( $scope ) {
        if ( ! self::valid_scope( $scope ) ) {
            return false;
        }
        $state = self::state( $scope );
        if ( ! in_array( (string) ( $state['status'] ?? '' ), array( 'queued', 'running' ), true ) ) {
            return false;
        }
        $last = absint( $state['last_batch_timestamp'] ?? 0 );
        $start = absint( $state['started_timestamp'] ?? 0 );
        $anchor = $last > 0 ? $last : $start;
        return $anchor > 0 && time() >= $anchor + Workload::delay_seconds( $scope );
    }

    /**
     * Read-only health snapshot for the admin progress monitor.
     *
     * @param string $scope Audit scope.
     * @return array
     */
    public static function progress( $scope ) {
        if ( ! self::valid_scope( $scope ) ) {
            return array();
        }
        $state = self::state( $scope );
        $next = wp_next_scheduled( self::HOOK, array( $scope ) );
        $next = false === $next ? 0 : absint( $next );
        $running = in_array( (string) ( $state['status'] ?? '' ), array( 'queued', 'running' ), true );
        return array(
            'status'              => sanitize_key( (string) ( $state['status'] ?? '' ) ),
            'processed'           => absint( $state['processed'] ?? 0 ),
            'total'               => isset( $state['total'] ) ? absint( $state['total'] ) : null,
            'last_batch_timestamp'=> absint( $state['last_batch_timestamp'] ?? 0 ),
            'next_event_timestamp'=> $next,
            'speed_delay_seconds' => Workload::delay_seconds( $scope ),
            'client_delay_ms'     => Workload::client_delay_ms( $scope ),
            'batch_budget_ms'     => Workload::budget_seconds( $scope ) * 1000,
            'batch_duration_ms'   => absint( $state['last_batch_duration_ms'] ?? 0 ),
            'batch_cpu_ms'        => isset( $state['last_batch_cpu_ms'] ) ? absint( $state['last_batch_cpu_ms'] ) : null,
            'last_batch_objects'  => absint( $state['last_batch_objects'] ?? 0 ),
            'effective_batch_size'=> Workload::effective_batch_size( $scope, $state ),
            'memory_pressure_percent' => absint( $state['memory_pressure_percent'] ?? 0 ),
            'cron_disabled'       => defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON,
            'cron_overdue'        => $running && $next > 0 && $next < time() - 60,
            'reason'              => sanitize_text_field( (string) ( $state['reason'] ?? '' ) ),
        );
    }

    /**
     * Repair a lost WP-Cron pulse without resetting a partially completed run.
     */
    public static function ensure_scheduled() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        foreach ( array_keys( self::scopes() ) as $scope ) {
            $state = self::state( $scope );
            if ( in_array( (string) ( $state['status'] ?? '' ), array( 'queued', 'running' ), true ) ) {
                self::enqueue( $scope );
            }
        }
    }

    public static function sanitize_filters( $scope, $filters ) {
        $mode = sanitize_key( (string) ( $filters['mode'] ?? 'filtered' ) );
        $mode = 'full' === $mode ? 'full' : 'filtered';
        $rows = absint( $filters['rows'] ?? 25 );
        if ( 'full' === $mode ) {
            $rows = 0;
        } elseif ( ! in_array( $rows, array( 10,25,50,100,250 ), true ) ) {
            $rows = 25;
        }
        return array(
            'mode'        => $mode,
            'search'      => 'full' === $mode ? '' : sanitize_text_field( (string) ( $filters['search'] ?? '' ) ),
            'category_id' => 'full' === $mode ? 0 : ( 'products' === $scope ? absint( $filters['category_id'] ?? 0 ) : 0 ),
            'rows'        => $rows,
        );
    }

    private static function count_objects( $scope ) {
        if ( 'categories' === $scope ) {
            $count = wp_count_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false ) );
            return is_wp_error( $count ) ? null : absint( $count );
        }

        if ( 'faqs' === $scope && FAQ_Source::available() ) {
            return FAQ_Source::count_active();
        }
        $post_type = 'faqs' === $scope ? self::faq_post_type() : ( 'products' === $scope ? 'product' : ( 'pages' === $scope ? 'page' : 'post' ) );
        $counts = wp_count_posts( $post_type );
        return $counts && isset( $counts->publish ) ? absint( $counts->publish ) : null;
    }

    private static function unavailable_reason( $scope ) {
        if ( 'products' === $scope && ( ! function_exists( 'wc_get_product' ) || ! post_type_exists( 'product' ) ) ) {
            return 'WooCommerce no está disponible. No se interpreta la ausencia como cero productos.';
        }
        if ( 'categories' === $scope && ! taxonomy_exists( 'product_cat' ) ) {
            return 'No existe la taxonomía product_cat.';
        }
        if ( 'faqs' === $scope && ! FAQ_Source::available() && ! self::faq_post_type() ) {
            return 'No está disponible la tabla canónica de TaxoCommerce FAQs ni un CPT FAQ alternativo.';
        }
        return '';
    }

    public static function start( $scope, $filters = array() ) {
        if ( ! self::valid_scope( $scope ) ) {
            return false;
        }
        // Never reset a queued/running audit or discard its cursor/JSONL.
        $existing = self::state( $scope );
        if ( in_array( (string) ( $existing['status'] ?? '' ), array( 'queued', 'running' ), true )
            || get_transient( 'taxocommerce_auditor_lock_' . $scope ) ) {
            return false;
        }
        wp_clear_scheduled_hook( self::HOOK, array( $scope ) );

        $filters = self::sanitize_filters( $scope, $filters );
        $unavailable = self::unavailable_reason( $scope );
        $full = 'full' === (string) $filters['mode'];
        $run_file = $unavailable ? '' : Export_Store::begin( $scope );
        if ( ! $unavailable && '' === $run_file ) {
            $unavailable = 'No se pudo preparar el archivo completo de auditoría en uploads/taxocommerce-auditor/.';
        }
        $expected = $unavailable ? null : ( $full ? self::count_objects( $scope ) : $filters['rows'] );
        $state = array(
            'status'           => $unavailable ? 'unavailable' : 'queued',
            'reason'           => $unavailable,
            'started_gmt'      => current_time( 'mysql', true ),
            'started_timestamp' => time(),
            'last_batch_timestamp' => 0,
            'completed_gmt'    => null,
            'processed'        => 0,
            'excluded'         => 0,
            'total'            => $expected,
            'filters'          => $filters,
            'run_mode'         => $full ? 'full' : 'filtered',
            'run_file'         => $run_file,
            'cursor'           => 0,
            'finding_count'    => 0,
            'finding_entities' => array(),
            'severity_counts'  => array( 'critical' => 0, 'high' => 0, 'medium' => 0, 'low' => 0, 'info' => 0 ),
            'rule_counts'      => array(),
            'action_class_counts' => array(),
            'status_counts'    => array(),
            'ready_now_count'  => 0,
            'findings'         => array(),
            'quality_total'    => 0,
            'quality_scored'   => 0,
            'quality_bands'    => array( 'fuerte' => 0, 'correcta' => 0, 'mejorable' => 0, 'debil' => 0 ),
            'quality_worst'    => array(),
            'seen_titles'      => array(),
            'seen_skus'        => array(),
            'seen_templates'   => array(),
            'seen_faq_questions' => array(),
            'priority_counts'  => array( 'P1' => 0, 'P2' => 0, 'P3' => 0, 'P4' => 0, 'P5' => 0 ),
            'rebalance_summary' => array(
                'review_split' => 0,
                'split_proposals' => 0,
                'review_merge' => 0,
                'merge_proposals' => 0,
                'approved' => 0,
            ),
            'rebalance_proposals' => array(),
            'editorial_map'   => ! $unavailable && in_array( $scope, array( 'posts', 'pages' ), true )
                ? Editorial_Specialist::build_map()
                : array(),
            'notes'            => array(
                'Auditoria de solo lectura: no altera contenido, terminos ni relaciones.',
                'La puntuacion de calidad es interna, no una metrica ni una prediccion de Google.',
                $full
                    ? 'Auditoria completa: recorre todo el universo publicado del bloque en lotes acotados.'
                    : 'Auditoria filtrada: limitada al numero maximo de entidades elegido.',
                'La interfaz conserva una muestra acotada; el JSON completo se genera desde uploads sin truncar la cola.',
            ),
        );
        if ( ! empty( $state['editorial_map']['partial'] ) ) {
            $state['notes'][] = 'El mapa editorial se ha limitado a ' . Editorial_Specialist::MAP_LIMIT . ' objetos; la cobertura comparativa es parcial.';
        }
        if ( 'categories' === $scope ) {
            $state['notes'][] = 'Referencia histórica para revisar tamaños: 5-10 productos. Las propuestas requieren evidencia semántica y no autorizan cambios automáticos.';
        }
        if ( 'faqs' === $scope && ! $unavailable ) {
            $state['notes'][] = FAQ_Source::available()
                ? 'Las FAQs se leen desde el almacén canónico de TaxoCommerce FAQs y se valida owner, categoría, contenido y duplicados.'
                : 'FAQ CPT alternativo: la validación del owner depende del filtro taxocommerce_auditor_faq_owner.';
        }
        update_option( self::option( $scope, 'state' ), $state, false );
        if ( ! $unavailable ) {
            self::enqueue( $scope );
        }
        return true;
    }

    public static function pause( $scope ) {
        if ( ! self::valid_scope( $scope ) ) {
            return;
        }
        $state = self::state( $scope );
        if ( in_array( (string) ( $state['status'] ?? '' ), array( 'queued', 'running' ), true ) ) {
            $state['status'] = 'paused';
            update_option( self::option( $scope, 'state' ), $state, false );
        }
        wp_clear_scheduled_hook( self::HOOK, array( $scope ) );
    }

    public static function resume( $scope ) {
        if ( ! self::valid_scope( $scope ) ) {
            return;
        }
        $state = self::state( $scope );
        if ( 'paused' === (string) ( $state['status'] ?? '' ) ) {
            $state['status'] = 'queued';
            update_option( self::option( $scope, 'state' ), $state, false );
            self::enqueue( $scope );
        }
    }

    /**
     * Seek by post ID to avoid large OFFSET queries for 10k+ products.
     * The filter applies exclusively to this one WP_Query instance.
     */
    private static function batch( $scope, $cursor, $filters = array(), $remaining = null, $requested_size = null ) {
        $filters = self::sanitize_filters( $scope, $filters );
        $speed_batch = null === $requested_size
            ? Workload::batch_size( $scope )
            : min( Workload::batch_size( $scope ), max( 1, absint( $requested_size ) ) );
        $batch_size = min( $speed_batch, null === $remaining ? $speed_batch : max( 1, absint( $remaining ) ) );
        if ( 'faqs' === $scope && FAQ_Source::available() ) {
            return FAQ_Source::batch(
                absint( $cursor ),
                $batch_size,
                (string) ( $filters['search'] ?? '' )
            );
        }
        if ( 'categories' === $scope ) {
            $terms = get_terms(
                array(
                    'taxonomy'   => 'product_cat',
                    'hide_empty' => false,
                    'number'     => $batch_size,
                    'offset'     => absint( $cursor ),
                    'search'     => $filters['search'],
                    'orderby'    => 'term_id',
                    'order'      => 'ASC',
                )
            );
            if ( is_wp_error( $terms ) ) {
                throw new \RuntimeException( esc_html( $terms->get_error_message() ) );
            }
            return (array) $terms;
        }

        $post_type = 'faqs' === $scope ? self::faq_post_type() : ( 'products' === $scope ? 'product' : ( 'pages' === $scope ? 'page' : 'post' ) );
        $seek = absint( $cursor );
        $filter = static function ( $where, $query ) use ( $seek ) {
            if ( 1 !== absint( $query->get( 'taxocommerce_auditor_seek' ) ) ) {
                return $where;
            }
            global $wpdb;
            return $where . $wpdb->prepare( " AND {$wpdb->posts}.ID > %d", $seek );
        };
        add_filter( 'posts_where', $filter, 10, 2 );
        try {
            $query_args = array(
                    'post_type'              => $post_type,
                    'post_status'            => 'publish',
                    'fields'                 => 'ids',
                    'posts_per_page'         => $batch_size,
                    'orderby'                => 'ID',
                    'order'                  => 'ASC',
                    'no_found_rows'          => true,
                    'suppress_filters'       => false,
                    'taxocommerce_auditor_seek' => 1,
                    'update_post_meta_cache' => true,
                    'update_post_term_cache' => true,
                    's'                       => $filters['search'],
                );
            if ( 'products' === $scope && $filters['category_id'] ) {
                // Native product-category filter, bounded to at most 40 IDs per cursor batch.
                // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Indexed term relationships are needed to retain the selected category; the ID seek and no_found_rows avoid unbounded/offset scans.
                $query_args['tax_query'] = array(
                    array(
                        'taxonomy' => 'product_cat',
                        'field'    => 'term_id',
                        'terms'    => array( $filters['category_id'] ),
                    ),
                );
            }
            if ( ctype_digit( $filters['search'] ) ) {
                $query_args['p'] = absint( $filters['search'] );
                unset( $query_args['s'] );
            }
            $query = new \WP_Query( $query_args );
        } finally {
            remove_filter( 'posts_where', $filter, 10 );
        }
        $objects = array();
        foreach ( (array) $query->posts as $id ) {
            $post = get_post( absint( $id ) );
            if ( $post instanceof \WP_Post ) {
                $objects[] = $post;
            }
        }
        return $objects;
    }

    /**
     * Four exact-match indexes must survive background batches. Compress them
     * in the native WP option instead of repeatedly writing multi-MB arrays
     * while auditing a large store. PHP zlib is optional; fall back safely.
     */
    private static function load_indexes( &$state ) {
        $keys = array( 'seen_titles', 'seen_skus', 'seen_templates', 'seen_faq_questions' );
        if ( isset( $state['indexes_compressed'] ) ) {
            if ( ! function_exists( 'gzuncompress' ) ) {
                throw new \RuntimeException( 'Falta zlib para recuperar los índices de este proceso.' );
            }
            $raw = base64_decode( (string) $state['indexes_compressed'], true );
            $decoded = is_string( $raw ) ? @gzuncompress( $raw ) : false;
            $maps = is_string( $decoded ) ? json_decode( $decoded, true ) : null;
            if ( ! is_array( $maps ) ) {
                throw new \RuntimeException( 'No se pueden recuperar los índices del Auditor; iniciar una nueva auditoría.' );
            }
            foreach ( $keys as $key ) {
                $state[ $key ] = is_array( $maps[ $key ] ?? null ) ? $maps[ $key ] : array();
            }
            unset( $state['indexes_compressed'] );
        } else {
            foreach ( $keys as $key ) {
                $state[ $key ] = is_array( $state[ $key ] ?? null ) ? $state[ $key ] : array();
            }
        }
    }

    private static function save_indexes( &$state ) {
        if ( ! function_exists( 'gzcompress' ) ) {
            return;
        }
        $keys = array( 'seen_titles', 'seen_skus', 'seen_templates', 'seen_faq_questions' );
        $maps = array();
        foreach ( $keys as $key ) {
            $maps[ $key ] = (array) ( $state[ $key ] ?? array() );
        }
        $json = wp_json_encode( $maps );
        $compressed = is_string( $json ) ? gzcompress( $json, 4 ) : false;
        if ( ! is_string( $compressed ) ) {
            return;
        }
        $state['indexes_compressed'] = base64_encode( $compressed );
        foreach ( $keys as $key ) {
            unset( $state[ $key ] );
        }
    }

    private static function compact_evidence( $value, $depth = 0 ) {
        if ( is_scalar( $value ) || null === $value ) {
            return is_string( $value ) ? mb_substr( $value, 0, 1200, 'UTF-8' ) : $value;
        }
        if ( ! is_array( $value ) || $depth >= 4 ) {
            return null;
        }
        $out = array();
        foreach ( array_slice( $value, 0, 30, true ) as $key => $child ) {
            $out[ $key ] = self::compact_evidence( $child, $depth + 1 );
        }
        return $out;
    }

    private static function add_finding( &$state, $row ) {
        $row = Priority::decorate( (array) $row );
        $row = Decision_Context::decorate( $row );
        $severity = $row['severity'];
        $code = $row['code'];
        $priority = $row['priority'];
        $action_class = (string) ( $row['action_class'] ?? 'INFORMATIVO' );
        $status = (string) ( $row['status'] ?? 'NO_ACTION' );
        $row['evidence'] = self::compact_evidence( $row['evidence'] ?? '' );

        $state['finding_count']++;
        $state['severity_counts'][ $severity ]++;
        $state['rule_counts'][ $code ] = absint( $state['rule_counts'][ $code ] ?? 0 ) + 1;
        $state['priority_counts'][ $priority ] = absint( $state['priority_counts'][ $priority ] ?? 0 ) + 1;
        $state['action_class_counts'][ $action_class ] = absint( $state['action_class_counts'][ $action_class ] ?? 0 ) + 1;
        $state['status_counts'][ $status ] = absint( $state['status_counts'][ $status ] ?? 0 ) + 1;
        if ( ! empty( $row['ready_now'] ) ) {
            $state['ready_now_count']++;
        }
        $entity_key = sanitize_key( (string) ( $row['entity_type'] ?? '' ) ) . '|' . (string) ( $row['entity_id'] ?? '' );
        if ( '|' !== $entity_key ) {
            $state['finding_entities'][ md5( $entity_key ) ] = true;
        }

        if ( empty( $state['run_file'] ) || ! Export_Store::append( $state['run_file'], $row ) ) {
            throw new \RuntimeException( 'No se pudo escribir la cola completa del Auditor en uploads.' );
        }
        $state['findings'][] = $row;
    }

    private static function append_quality( &$state, $scope, $object, $quality ) {
        if ( ! is_array( $quality ) || ! isset( $quality['score'] ) ) {
            return;
        }
        $score = absint( $quality['score'] );
        $state['quality_total'] += $score;
        $state['quality_scored']++;
        $band = Rules::band( $score );
        $state['quality_bands'][ $band ] = (int) ( $state['quality_bands'][ $band ] ?? 0 ) + 1;
        $is_term = $object instanceof \WP_Term;
        $state['quality_worst'][] = array(
            'entity_type' => $is_term ? 'product_cat' : ( 'faqs' === $scope ? 'faq' : ( 'products' === $scope ? 'product' : ( 'pages' === $scope ? 'page' : 'post' ) ) ),
            'entity_id'   => $is_term ? absint( $object->term_id ) : absint( $object->ID ),
            'title'       => $is_term ? (string) $object->name : (string) $object->post_title,
            'score'       => $score,
            'band'        => $band,
            'dimensions'  => $quality['dimensions'],
        );
    }

    private static function trim_samples( &$state ) {
        usort( $state['findings'], static function ( $a, $b ) {
            return (int) ( $b['priority_score'] ?? 0 ) <=> (int) ( $a['priority_score'] ?? 0 );
        } );
        $state['findings'] = array_slice( $state['findings'], 0, self::MAX_STORED_FINDINGS );
        usort( $state['quality_worst'], static function ( $a, $b ) {
            return (int) ( $a['score'] ?? 0 ) <=> (int) ( $b['score'] ?? 0 );
        } );
        $state['quality_worst'] = array_slice( $state['quality_worst'], 0, self::MAX_QUALITY_ROWS );
    }

    private static function inventory( $scope, $state ) {
        $processed = absint( $state['processed'] ?? 0 );
        if ( 'products' === $scope ) {
            $categories = wp_count_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false ) );
            return array(
                'products'   => $processed,
                'categories' => is_wp_error( $categories ) ? null : absint( $categories ),
            );
        }
        if ( 'categories' === $scope ) {
            $with_products = wp_count_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => true ) );
            return array(
                'categories'              => $processed,
                'categories_with_products'=> is_wp_error( $with_products ) ? null : absint( $with_products ),
            );
        }
        if ( 'posts' === $scope ) {
            return array( 'posts' => $processed );
        }
        if ( 'pages' === $scope ) {
            return array( 'pages' => $processed );
        }
        if ( 'faqs' === $scope ) {
            return array( 'faqs' => $processed );
        }
        return array( 'entities' => $processed );
    }

    private static function rule_total( $rules, $codes ) {
        $total = 0;
        foreach ( (array) $codes as $code ) {
            $total += absint( $rules[ $code ] ?? 0 );
        }
        return $total;
    }

    private static function kpi_row( $key, $label, $value, $unit = '', $detail = '', $tone = 'neutral' ) {
        return array(
            'key'    => sanitize_key( (string) $key ),
            'label'  => (string) $label,
            'value'  => is_numeric( $value ) ? 0 + $value : (string) $value,
            'unit'   => (string) $unit,
            'detail' => (string) $detail,
            'tone'   => sanitize_key( (string) $tone ) ?: 'neutral',
        );
    }

    public static function scope_kpis( $scope, $report ) {
        $scope = sanitize_key( (string) $scope );
        $report = is_array( $report ) ? $report : array();
        $rules = (array) ( $report['rule_counts'] ?? array() );
        $severity = (array) ( $report['severity_counts'] ?? array() );
        $priority = (array) ( $report['priority_counts'] ?? array() );
        $statuses = (array) ( $report['status_counts'] ?? array() );
        $quality = (array) ( $report['quality'] ?? array() );
        $bands = (array) ( $quality['bands'] ?? array() );
        $processed = absint( $report['processed'] ?? 0 );
        $excluded = absint( $report['excluded'] ?? 0 );
        $audited = max( 0, $processed - $excluded );
        $review = absint( $report['entities_to_review'] ?? 0 );
        $clean = max( 0, $audited - $review );
        $clean_pct = $audited ? round( ( $clean / $audited ) * 100, 1 ) : null;

        $general = array(
            self::kpi_row( 'entities_reviewed', __( 'Entidades auditadas', 'taxocommerce-auditor' ), $audited, '', $excluded ? 'Entidades realmente evaluadas; se excluyen ' . $excluded . ' páginas funcionales/corporativas.' : 'Universo realmente evaluado en esta ejecución.' ),
            self::kpi_row( 'clean_entities', __( 'Sin incidencias detectadas', 'taxocommerce-auditor' ), $clean, '', __( 'Entidades auditadas sin hallazgos en las reglas activas.', 'taxocommerce-auditor' ), $clean === $audited && $audited ? 'good' : 'neutral' ),
            self::kpi_row( 'clean_rate', __( 'Cobertura limpia', 'taxocommerce-auditor' ), null === $clean_pct ? 'N/D' : $clean_pct, null === $clean_pct ? '' : '%', __( 'Porcentaje de entidades sin hallazgos en esta ejecución.', 'taxocommerce-auditor' ), null !== $clean_pct && $clean_pct >= 90 ? 'good' : ( null !== $clean_pct && $clean_pct < 70 ? 'bad' : 'warn' ) ),
            self::kpi_row( 'findings', __( 'Hallazgos', 'taxocommerce-auditor' ), absint( $report['finding_count'] ?? 0 ), '', __( 'Total de incidencias detectadas.', 'taxocommerce-auditor' ) ),
            self::kpi_row( 'critical_high', __( 'Críticos + alta', 'taxocommerce-auditor' ), absint( $severity['critical'] ?? 0 ) + absint( $severity['high'] ?? 0 ), '', __( 'Incidencias con mayor severidad.', 'taxocommerce-auditor' ), ( absint( $severity['critical'] ?? 0 ) + absint( $severity['high'] ?? 0 ) ) ? 'bad' : 'good' ),
            self::kpi_row( 'p1', __( 'Prioridad P1', 'taxocommerce-auditor' ), absint( $priority['P1'] ?? 0 ), '', __( 'Tareas que requieren revisión prioritaria.', 'taxocommerce-auditor' ), absint( $priority['P1'] ?? 0 ) ? 'bad' : 'good' ),
            self::kpi_row( 'source_verification', __( 'Verificación de fuente', 'taxocommerce-auditor' ), absint( $statuses['NEEDS_SOURCE_VERIFICATION'] ?? 0 ), '', __( 'Casos que no deben corregirse sin contrastar fabricante/proveedor.', 'taxocommerce-auditor' ), absint( $statuses['NEEDS_SOURCE_VERIFICATION'] ?? 0 ) ? 'warn' : 'good' ),
            self::kpi_row( 'quality_average', __( 'Calidad media', 'taxocommerce-auditor' ), null === ( $quality['average'] ?? null ) ? 'N/D' : (float) $quality['average'], null === ( $quality['average'] ?? null ) ? '' : '/100', __( 'Índice interno de calidad; no es una métrica de Google.', 'taxocommerce-auditor' ), null !== ( $quality['average'] ?? null ) && (float) $quality['average'] >= 80 ? 'good' : ( null !== ( $quality['average'] ?? null ) && (float) $quality['average'] < 60 ? 'bad' : 'warn' ) ),
            self::kpi_row( 'weak_quality', __( 'Calidad débil', 'taxocommerce-auditor' ), absint( $bands['debil'] ?? 0 ), '', __( 'Entidades clasificadas en la banda interna más baja.', 'taxocommerce-auditor' ), absint( $bands['debil'] ?? 0 ) ? 'warn' : 'good' ),
        );

        $specific = array();
        if ( 'products' === $scope ) {
            $specific = array(
                self::kpi_row( 'missing_description', __( 'Sin descripción', 'taxocommerce-auditor' ), absint( $rules['product_missing_description'] ?? 0 ), '', __( 'Producto sin descripción larga útil.', 'taxocommerce-auditor' ), absint( $rules['product_missing_description'] ?? 0 ) ? 'bad' : 'good' ),
                self::kpi_row( 'missing_excerpt', __( 'Sin resumen', 'taxocommerce-auditor' ), absint( $rules['product_missing_excerpt'] ?? 0 ), '', __( 'Producto sin descripción corta/resumen.', 'taxocommerce-auditor' ), absint( $rules['product_missing_excerpt'] ?? 0 ) ? 'warn' : 'good' ),
                self::kpi_row( 'without_category', __( 'Sin categoría', 'taxocommerce-auditor' ), absint( $rules['product_without_category'] ?? 0 ), '', __( 'Productos publicados sin product_cat.', 'taxocommerce-auditor' ), absint( $rules['product_without_category'] ?? 0 ) ? 'bad' : 'good' ),
                self::kpi_row( 'without_vocabulary', __( 'Sin vocabulario', 'taxocommerce-auditor' ), absint( $rules['product_without_native_vocabulary'] ?? 0 ), '', __( 'Productos sin clasificación semántica nativa.', 'taxocommerce-auditor' ), absint( $rules['product_without_native_vocabulary'] ?? 0 ) ? 'warn' : 'good' ),
                self::kpi_row( 'identity_drift', __( 'Deriva de identidad', 'taxocommerce-auditor' ), self::rule_total( $rules, array( 'product_title_excerpt_drift', 'product_description_identity_drift', 'product_description_alignment_weak', 'product_tag_identity_drift' ) ), '', __( 'Título, resumen, descripción o tags con señales de incoherencia.', 'taxocommerce-auditor' ), 'warn' ),
                self::kpi_row( 'attribute_conflicts', __( 'Conflictos de atributos', 'taxocommerce-auditor' ), self::rule_total( $rules, array( 'product_attribute_title_conflict', 'attribute_value_malformed', 'attribute_without_name', 'attribute_without_value' ) ), '', __( 'Atributos que requieren validación o normalización.', 'taxocommerce-auditor' ), 'warn' ),
                self::kpi_row( 'unstable_sku', 'SKU no estable', absint( $rules['product_no_stable_sku'] ?? 0 ), '', 'Productos sin identificador SKU estable.', absint( $rules['product_no_stable_sku'] ?? 0 ) ? 'warn' : 'good' ),
            );
        } elseif ( 'categories' === $scope ) {
            $rebalance = (array) ( $report['category_rebalance']['summary'] ?? array() );
            $specific = array(
                self::kpi_row( 'empty_categories', __( 'Categorías vacías', 'taxocommerce-auditor' ), absint( $rules['category_empty'] ?? 0 ), '', __( 'Categorías sin productos.', 'taxocommerce-auditor' ), absint( $rules['category_empty'] ?? 0 ) ? 'bad' : 'good' ),
                self::kpi_row( 'single_product', __( 'Con un solo producto', 'taxocommerce-auditor' ), absint( $rules['category_single_product'] ?? 0 ), '', __( 'Categorías con profundidad comercial mínima.', 'taxocommerce-auditor' ), 'warn' ),
                self::kpi_row( 'missing_description', __( 'Sin descripción', 'taxocommerce-auditor' ), absint( $rules['category_missing_description'] ?? 0 ), '', __( 'Categorías sin contenido descriptivo.', 'taxocommerce-auditor' ), absint( $rules['category_missing_description'] ?? 0 ) ? 'warn' : 'good' ),
                self::kpi_row( 'without_vocabulary', __( 'Sin vocabulario', 'taxocommerce-auditor' ), absint( $rules['category_without_native_vocabulary'] ?? 0 ), '', __( 'Categorías sin clasificación semántica nativa.', 'taxocommerce-auditor' ), absint( $rules['category_without_native_vocabulary'] ?? 0 ) ? 'warn' : 'good' ),
                self::kpi_row( 'without_secondary_hub', __( 'Sin Hub secundario', 'taxocommerce-auditor' ), absint( $rules['category_no_native_secondary_hub'] ?? 0 ), '', __( 'Categorías sin encaje arquitectónico nativo.', 'taxocommerce-auditor' ), absint( $rules['category_no_native_secondary_hub'] ?? 0 ) ? 'warn' : 'good' ),
                self::kpi_row( 'underfilled', __( 'Infra-dimensionadas', 'taxocommerce-auditor' ), absint( $rules['category_underfilled'] ?? 0 ), '', __( 'Categorías candidatas a revisión por poca profundidad.', 'taxocommerce-auditor' ), 'warn' ),
                self::kpi_row( 'oversized', __( 'Sobre-dimensionadas', 'taxocommerce-auditor' ), absint( $rules['category_oversized'] ?? 0 ), '', __( 'Categorías candidatas a división.', 'taxocommerce-auditor' ), 'warn' ),
                self::kpi_row( 'split_proposals', __( 'Propuestas de división', 'taxocommerce-auditor' ), absint( $rebalance['split_proposals'] ?? 0 ), '', __( 'Propuestas basadas en evidencia semántica; no ejecutan cambios.', 'taxocommerce-auditor' ), 'neutral' ),
                self::kpi_row( 'merge_proposals', __( 'Propuestas de unión', 'taxocommerce-auditor' ), absint( $rebalance['merge_proposals'] ?? 0 ), '', __( 'Propuestas de consolidación para revisión humana.', 'taxocommerce-auditor' ), 'neutral' ),
            );
        } elseif ( 'posts' === $scope ) {
            $specific = array(
                self::kpi_row( 'thin_content', __( 'Contenido débil', 'taxocommerce-auditor' ), absint( $rules['editorial_thin_content'] ?? 0 ), '', __( 'Posts con cuerpo insuficiente para su función.', 'taxocommerce-auditor' ), absint( $rules['editorial_thin_content'] ?? 0 ) ? 'warn' : 'good' ),
                self::kpi_row( 'without_vocabulary', __( 'Sin vocabulario', 'taxocommerce-auditor' ), absint( $rules['editorial_without_native_vocabulary'] ?? 0 ), '', __( 'Posts sin clasificación semántica nativa.', 'taxocommerce-auditor' ), absint( $rules['editorial_without_native_vocabulary'] ?? 0 ) ? 'warn' : 'good' ),
                self::kpi_row( 'title_drift', __( 'Título/contenido incoherente', 'taxocommerce-auditor' ), absint( $rules['editorial_title_content_drift'] ?? 0 ), '', __( 'Señales de desalineación entre título y cuerpo.', 'taxocommerce-auditor' ), 'warn' ),
                self::kpi_row( 'cannibalization', __( 'Canibalización', 'taxocommerce-auditor' ), self::rule_total( $rules, array( 'editorial_post_canonical_cannibalization', 'editorial_post_category_cannibalization', 'editorial_post_landing_cannibalization' ) ), '', __( 'Solapamientos de intención con categorías, landings u otras URL canónicas.', 'taxocommerce-auditor' ), 'bad' ),
                self::kpi_row( 'structural_overlap', __( 'Solapamiento estructural', 'taxocommerce-auditor' ), absint( $rules['editorial_post_structural_overlap'] ?? 0 ), '', __( 'Posts que compiten con piezas estructurales del sitio.', 'taxocommerce-auditor' ), 'warn' ),
                self::kpi_row( 'duplicate_titles', __( 'Títulos duplicados', 'taxocommerce-auditor' ), absint( $rules['duplicate_editorial_title'] ?? 0 ), '', __( 'Identidades editoriales repetidas dentro del bloque.', 'taxocommerce-auditor' ), 'warn' ),
            );
        } elseif ( 'pages' === $scope ) {
            $specific = array(
                self::kpi_row( 'thin_content', __( 'Contenido débil', 'taxocommerce-auditor' ), absint( $rules['editorial_thin_content'] ?? 0 ), '', __( 'Páginas/landings con cuerpo insuficiente.', 'taxocommerce-auditor' ), absint( $rules['editorial_thin_content'] ?? 0 ) ? 'warn' : 'good' ),
                self::kpi_row( 'without_vocabulary', __( 'Sin vocabulario', 'taxocommerce-auditor' ), absint( $rules['editorial_without_native_vocabulary'] ?? 0 ), '', __( 'Páginas sin clasificación semántica nativa.', 'taxocommerce-auditor' ), absint( $rules['editorial_without_native_vocabulary'] ?? 0 ) ? 'warn' : 'good' ),
                self::kpi_row( 'landing_duplicates', __( 'Landings duplicadas', 'taxocommerce-auditor' ), absint( $rules['editorial_landing_duplicate'] ?? 0 ), '', __( 'Landings con identidad editorial repetida.', 'taxocommerce-auditor' ), absint( $rules['editorial_landing_duplicate'] ?? 0 ) ? 'bad' : 'good' ),
                self::kpi_row( 'cannibalization', __( 'Canibalización', 'taxocommerce-auditor' ), self::rule_total( $rules, array( 'editorial_canonical_post_cannibalization', 'editorial_landing_canonical_cannibalization', 'editorial_landing_post_cannibalization', 'editorial_landing_category_overlap' ) ), '', __( 'Solapamientos entre páginas estructurales, landings, posts y categorías.', 'taxocommerce-auditor' ), 'bad' ),
                self::kpi_row( 'structural_overlap', __( 'Solapamiento estructural', 'taxocommerce-auditor' ), absint( $rules['editorial_landing_structural_overlap'] ?? 0 ), '', __( 'Landings que invaden funciones de otras piezas estructurales.', 'taxocommerce-auditor' ), 'warn' ),
                self::kpi_row( 'unclassified_role', __( 'Rol sin clasificar', 'taxocommerce-auditor' ), absint( $rules['editorial_page_role_unclassified'] ?? 0 ), '', __( 'Páginas cuyo rol SEO no está resuelto.', 'taxocommerce-auditor' ), 'warn' ),
                self::kpi_row( 'missing_parent', __( 'Sin padre nativo', 'taxocommerce-auditor' ), absint( $rules['page_missing_native_parent'] ?? 0 ), '', __( 'Páginas estructurales sin relación padre esperada.', 'taxocommerce-auditor' ), 'warn' ),
            );
        } elseif ( 'faqs' === $scope ) {
            $specific = array(
                self::kpi_row( 'thin_answer', __( 'Respuesta insuficiente', 'taxocommerce-auditor' ), absint( $rules['faq_thin_answer'] ?? 0 ), '', 'FAQs con respuesta vacía o demasiado escueta.', absint( $rules['faq_thin_answer'] ?? 0 ) ? 'warn' : 'good' ),
                self::kpi_row( 'empty_question', __( 'Pregunta vacía', 'taxocommerce-auditor' ), absint( $rules['faq_empty_question'] ?? 0 ), '', 'FAQs sin pregunta utilizable.', absint( $rules['faq_empty_question'] ?? 0 ) ? 'bad' : 'good' ),
                self::kpi_row( 'orphan_owner', __( 'Owner huérfano', 'taxocommerce-auditor' ), absint( $rules['faq_orphan_owner'] ?? 0 ), '', 'FAQs cuyo destino canónico ya no existe.', absint( $rules['faq_orphan_owner'] ?? 0 ) ? 'bad' : 'good' ),
                self::kpi_row( 'invalid_owner', __( 'Owner inválido', 'taxocommerce-auditor' ), absint( $rules['faq_invalid_owner_type'] ?? 0 ), '', 'FAQs fuera del modelo canónico página/hub, categoría o producto.', absint( $rules['faq_invalid_owner_type'] ?? 0 ) ? 'bad' : 'good' ),
                self::kpi_row( 'missing_category', __( 'Sin categoría', 'taxocommerce-auditor' ), absint( $rules['faq_missing_product_category'] ?? 0 ), '', 'FAQs sin product_cat_id válido.', absint( $rules['faq_missing_product_category'] ?? 0 ) ? 'warn' : 'good' ),
                self::kpi_row( 'semantic_drift', __( 'Deriva semántica', 'taxocommerce-auditor' ), absint( $rules['faq_owner_semantic_drift'] ?? 0 ), '', __( 'Pregunta posiblemente alejada del owner confirmado.', 'taxocommerce-auditor' ), 'warn' ),
                self::kpi_row( 'duplicate_same_owner', __( 'Duplicadas en mismo owner', 'taxocommerce-auditor' ), absint( $rules['duplicate_faq_same_owner'] ?? 0 ), '', __( 'Preguntas equivalentes repetidas para el mismo destino.', 'taxocommerce-auditor' ), 'warn' ),
            );
        }

        return array(
            'scope'    => $scope,
            'mode'     => (string) ( $report['run_mode'] ?? 'filtered' ),
            'coverage' => 'full' === (string) ( $report['run_mode'] ?? '' ) ? 'complete_scope' : 'filtered_sample',
            'general'  => $general,
            'specific' => $specific,
            'note'     => __( 'Los KPI describen calidad interna y errores del contenido auditado. No son métricas de posicionamiento de Google/Bing.', 'taxocommerce-auditor' ),
        );
    }

    private static function complete( $scope, &$state ) {
        $state['status'] = 'completed';
        $state['completed_gmt'] = current_time( 'mysql', true );
        self::trim_samples( $state );
        $previous_report = self::report( $scope );

        $report = array(
            'schema'      => 'taxocommerce_auditor_native_v2',
            'version'     => Plugin::VERSION,
            'scope'       => $scope,
            'run_mode'    => (string) ( $state['run_mode'] ?? 'filtered' ),
            'started_gmt' => $state['started_gmt'],
            'completed_gmt' => $state['completed_gmt'],
            'completed_local' => current_time( 'mysql' ),
            'duration_seconds' => max( 0, time() - absint( $state['started_timestamp'] ?? time() ) ),
            'source_model' => 'WordPress/WooCommerce nativo + Vocabulary + TaxoCommerce FAQs + contexto persistido local opcional',
            'processed'   => $state['processed'],
            'excluded'    => $state['excluded'],
            'expected'    => $state['total'],
            'filters'     => (array) ( $state['filters'] ?? array() ),
            'inventory'   => self::inventory( $scope, $state ),
            'finding_count' => $state['finding_count'],
            'entities_to_review' => count( (array) ( $state['finding_entities'] ?? array() ) ),
            'severity_counts' => $state['severity_counts'],
            'rule_counts'     => $state['rule_counts'],
            'priority_counts' => $state['priority_counts'],
            'action_class_counts' => (array) ( $state['action_class_counts'] ?? array() ),
            'status_counts'    => (array) ( $state['status_counts'] ?? array() ),
            'ready_now_count'  => absint( $state['ready_now_count'] ?? 0 ),
            'decision_context' => Decision_Context::public_snapshot(),
            'full_findings_file' => (string) ( $state['run_file'] ?? '' ),
            'category_rebalance' => 'categories' === $scope
                ? array(
                    'summary' => $state['rebalance_summary'],
                    'proposals' => $state['rebalance_proposals'],
                    'sample_truncated' => (
                        (int) $state['rebalance_summary']['split_proposals']
                        + (int) $state['rebalance_summary']['merge_proposals']
                    ) > count( $state['rebalance_proposals'] ),
                    'apply_actions_enabled' => false,
                    'note' => __( 'Solo propuestas de revisión. No se mueven ni eliminan categorías y no se crean redirecciones.', 'taxocommerce-auditor' ),
                )
                : null,
            'editorial_comparison' => in_array( $scope, array( 'posts', 'pages' ), true )
                ? array(
                    'indexed_posts_pages' => absint( $state['editorial_map']['indexed_posts_pages'] ?? 0 ),
                    'partial' => ! empty( $state['editorial_map']['partial'] ),
                )
                : null,
            'quality' => array(
                'not_google_score' => true,
                'dimensions' => Rules::DIMENSIONS,
                'scored' => $state['quality_scored'],
                'average' => $state['quality_scored'] ? round( $state['quality_total'] / $state['quality_scored'], 1 ) : null,
                'bands'  => $state['quality_bands'],
                'lowest_scored_sample' => $state['quality_worst'],
            ),
            'findings_sample' => $state['findings'],
            'findings_sample_truncated' => $state['finding_count'] > count( $state['findings'] ),
            'notes' => $state['notes'],
        );
        $report['scope_kpis'] = self::scope_kpis( $scope, $report );
        update_option( self::option( $scope, 'report' ), $report, false );
        $previous_file = (string) ( $previous_report['full_findings_file'] ?? '' );
        if ( $previous_file && $previous_file !== (string) $report['full_findings_file'] ) {
            Export_Store::delete( $previous_file );
        }

        // Store a bounded list of completed runs, as in the legacy Auditor.
        $history = (array) get_option( self::option( $scope, 'history' ), array() );
        array_unshift(
            $history,
            array(
                'completed_gmt' => $report['completed_gmt'],
                'duration_seconds' => $report['duration_seconds'],
                'processed' => $report['processed'],
                'finding_count' => $report['finding_count'],
                'severity_counts' => $report['severity_counts'],
                'priority_counts' => $report['priority_counts'],
            )
        );
        update_option( self::option( $scope, 'history' ), array_slice( $history, 0, 12 ), false );

        unset(
            $state['seen_titles'],
            $state['seen_skus'],
            $state['seen_templates'],
            $state['seen_faq_questions'],
            $state['editorial_map'],
            $state['finding_entities'],
            $state['findings'],
            $state['quality_worst'],
            $state['rebalance_proposals']
        );
        update_option( self::option( $scope, 'state' ), $state, false );
        wp_clear_scheduled_hook( self::HOOK, array( $scope ) );
    }

    public static function run_batch( $scope ) {
        if ( ! self::valid_scope( $scope ) ) {
            return;
        }
        $state = self::state( $scope );
        if ( ! in_array( (string) ( $state['status'] ?? '' ), array( 'queued', 'running' ), true ) ) {
            return;
        }
        $lock = 'taxocommerce_auditor_lock_' . $scope;
        $worker_option = 'taxocommerce_auditor_worker_' . $scope;
        if ( get_transient( $lock ) ) {
            // Another worker is busy; leave its scheduled next pulse intact.
            self::enqueue( $scope );
            return;
        }
        // add_option() is an atomic database insert: prevent a cron + AJAX
        // race from processing the same cursor twice.
        if ( ! add_option( $worker_option, time(), '', false ) ) {
            $occupied_since = absint( get_option( $worker_option, 0 ) );
            if ( $occupied_since > 0 && $occupied_since < time() - 180 ) {
                delete_option( $worker_option );
            }
            if ( ! add_option( $worker_option, time(), '', false ) ) {
                self::enqueue( $scope );
                return;
            }
        }
        set_transient( $lock, 1, 120 );
        try {
            // A browser-assisted batch consumes its due cron event explicitly.
            // Cron callbacks have already consumed the event by this point.
            wp_clear_scheduled_hook( self::HOOK, array( $scope ) );
            self::load_indexes( $state );
            $state['status'] = 'running';
            $full = 'full' === (string) ( $state['filters']['mode'] ?? 'filtered' );
            $limit = $full ? 0 : absint( $state['filters']['rows'] ?? 25 );
            $remaining = $full ? null : max( 0, $limit - absint( $state['processed'] ) );
            if ( ! $full && 0 === $remaining ) {
                self::complete( $scope, $state );
                return;
            }
            $maximum_batch = Workload::effective_batch_size( $scope, $state );
            $requested_batch = min( $maximum_batch, null === $remaining ? $maximum_batch : max( 1, $remaining ) );
            // Include query and object hydration, not just per-item inspection,
            // when measuring load and enforcing the existing time budget.
            $batch_cpu_started = Workload::process_cpu_ms();
            $batch_started = microtime( true );
            $objects = self::batch( $scope, absint( $state['cursor'] ), (array) ( $state['filters'] ?? array() ), $remaining, $requested_batch );
            $processed_batch = 0;
            $fetched_batch = count( $objects );
            foreach ( $objects as $object ) {
                $analysis = Rules::inspect( $scope, $object );
                $state['processed']++;
                $processed_batch++;
                $id = $object instanceof \WP_Term ? absint( $object->term_id ) : absint( $object->ID );

                if ( 'categories' !== $scope ) {
                    $state['cursor'] = max( absint( $state['cursor'] ), $id );
                }

                if ( ! empty( $analysis['excluded'] ) ) {
                    $state['excluded']++;
                    if ( microtime( true ) - $batch_started >= Workload::budget_seconds( $scope ) ) {
                        break;
                    }
                    continue;
                }

                $type = $object instanceof \WP_Term ? 'product_cat'
                    : ( 'products' === $scope ? 'product'
                    : ( 'faqs' === $scope ? 'faq' : ( 'pages' === $scope ? 'page' : 'post' ) ) );
                $title = $object instanceof \WP_Term ? (string) $object->name : (string) $object->post_title;
                $key = (string) ( $analysis['title_key'] ?? '' );
                $identity_duplicate = false;
                $editorial_collision = false;

                if ( 'categories' === $scope && $object instanceof \WP_Term ) {
                    $review = Category_Review::inspect( $object );
                    $analysis['findings'] = array_merge(
                        (array) ( $analysis['findings'] ?? array() ),
                        (array) ( $review['findings'] ?? array() )
                    );
                    $inventory = (array) ( $review['inventory'] ?? array() );
                    if ( ! empty( $inventory['review_split'] ) ) {
                        $state['rebalance_summary']['review_split']++;
                    }
                    if ( ! empty( $inventory['review_merge'] ) ) {
                        $state['rebalance_summary']['review_merge']++;
                    }
                    if ( ! empty( $inventory['split_proposal'] ) ) {
                        $state['rebalance_summary']['split_proposals']++;
                    }
                    if ( ! empty( $inventory['merge_proposal'] ) ) {
                        $state['rebalance_summary']['merge_proposals']++;
                    }
                    if ( ( ! empty( $inventory['split_proposal'] ) || ! empty( $inventory['merge_proposal'] ) )
                        && count( $state['rebalance_proposals'] ) < self::MAX_REBALANCE_PROPOSALS ) {
                        $state['rebalance_proposals'][] = $inventory;
                    }
                }

                if ( in_array( $scope, array( 'posts', 'pages' ), true ) && $object instanceof \WP_Post ) {
                    $additional = Editorial_Specialist::inspect(
                        $object,
                        $scope,
                        (array) ( $state['editorial_map'] ?? array() )
                    );
                    $analysis['findings'] = array_merge(
                        (array) ( $analysis['findings'] ?? array() ),
                        (array) $additional
                    );
                    foreach ( $additional as $finding ) {
                        $code = (string) ( $finding['code'] ?? '' );
                        if ( false !== strpos( $code, 'cannibalization' )
                            || false !== strpos( $code, '_duplicate' )
                            || false !== strpos( $code, '_structural_overlap' ) ) {
                            $editorial_collision = true;
                        }
                    }
                }

                // Legacy FAQ duplicate rule is scoped to the same confirmed
                // owner; identical general questions across owners are valid.
                if ( 'faqs' === $scope ) {
                    $owner_key = (string) ( $analysis['owner_key'] ?? '' );
                    if ( '' !== $owner_key && '' !== $key ) {
                        $faq_key = md5( $owner_key . '|' . $key );
                        if ( isset( $state['seen_faq_questions'][ $faq_key ] ) ) {
                            self::add_finding(
                                $state,
                                array(
                                    'code'           => 'duplicate_faq_same_owner',
                                    'severity'       => 'medium',
                                    'entity_type'    => $type,
                                    'entity_id'      => $id,
                                    'title'          => $title,
                                    'evidence'       => 'Pregunta repetida para ' . $owner_key . '; primera FAQ #' . absint( $state['seen_faq_questions'][ $faq_key ] ),
                                    'recommendation' => 'Revisar si las respuestas son equivalentes y consolidar solo después de verificarlas.',
                                )
                            );
                        } elseif ( count( $state['seen_faq_questions'] ) < self::MAX_INDEXED_TITLES ) {
                            $state['seen_faq_questions'][ $faq_key ] = $id;
                        }
                    }
                } elseif ( '' !== $key ) {
                    $identity_hash = md5( $key );
                    if ( isset( $state['seen_titles'][ $identity_hash ] )
                        && absint( $state['seen_titles'][ $identity_hash ] ) !== $id ) {
                        $identity_duplicate = true;
                        $code = in_array( $scope, array( 'posts', 'pages' ), true )
                            ? 'duplicate_editorial_title' : 'duplicate_title_candidate';
                        self::add_finding(
                            $state,
                            array(
                                'code'           => $code,
                                'severity'       => 'medium',
                                'entity_type'    => $type,
                                'entity_id'      => $id,
                                'title'          => $title,
                                'evidence'       => 'Misma identidad de título que #' . absint( $state['seen_titles'][ $identity_hash ] ) . ' en este bloque.',
                                'recommendation' => 'Revisar variantes, localizaciones e intención antes de consolidar URLs.',
                            )
                        );
                    } elseif ( count( $state['seen_titles'] ) < self::MAX_INDEXED_TITLES ) {
                        $state['seen_titles'][ $identity_hash ] = $id;
                    }
                }

                // Legacy product-identity check. Provider/country scope may
                // be supplied explicitly by a native module; no country is
                // guessed from the language or title.
                $sku_key = (string) ( $analysis['sku_key'] ?? '' );
                if ( 'products' === $scope && '' !== $sku_key ) {
                    $identity_scope = apply_filters( 'taxocommerce_auditor_product_identity_scope', '', $id );
                    $identity_scope = sanitize_key( (string) $identity_scope );
                    $sku_hash = md5( $identity_scope . '|' . $sku_key );
                    if ( isset( $state['seen_skus'][ $sku_hash ] ) ) {
                        self::add_finding(
                            $state,
                            array(
                                'code'           => 'source_duplicate_sku',
                                'severity'       => 'medium',
                                'entity_type'    => 'product',
                                'entity_id'      => $id,
                                'title'          => $title,
                                'evidence'       => 'SKU repetido con producto #' . absint( $state['seen_skus'][ $sku_hash ] ) . '. Ámbito: ' . ( $identity_scope ?: 'no especificado' ) . '.',
                                'recommendation' => 'Contrastar proveedor y país: dos mercados pueden publicar legítimamente el mismo SKU.',
                            )
                        );
                        $identity_duplicate = true;
                    } elseif ( count( $state['seen_skus'] ) < self::MAX_INDEXED_TITLES ) {
                        $state['seen_skus'][ $sku_hash ] = $id;
                    }
                }

                $template = (string) ( $analysis['template_key'] ?? '' );
                if ( '' !== $template && 'faqs' !== $scope ) {
                    $template_hash = md5( $template );
                    if ( isset( $state['seen_templates'][ $template_hash ] )
                        && absint( $state['seen_templates'][ $template_hash ] ) !== $id ) {
                        self::add_finding(
                            $state,
                            array(
                                'code'           => 'duplicate_content_template_candidate',
                                'severity'       => 'low',
                                'entity_type'    => $type,
                                'entity_id'      => $id,
                                'title'          => $title,
                                'evidence'       => 'Patrón de cuerpo repetido; primer objeto #' . absint( $state['seen_templates'][ $template_hash ] ) . '.',
                                'recommendation' => 'Revisar contenido original y diferencias legítimas. No duplicar ni eliminar por una coincidencia de plantilla.',
                            )
                        );
                        $identity_duplicate = true;
                    } elseif ( count( $state['seen_templates'] ) < self::MAX_INDEXED_TITLES ) {
                        $state['seen_templates'][ $template_hash ] = $id;
                    }
                }

                foreach ( (array) ( $analysis['findings'] ?? array() ) as $finding ) {
                    self::add_finding( $state, $finding );
                }
                $quality = $analysis['quality'] ?? null;
                if ( is_array( $quality ) && ( $identity_duplicate || $editorial_collision ) ) {
                    $quality['dimensions']['differentiation'] = $identity_duplicate ? 5 : 10;
                    $quality['score'] = array_sum( $quality['dimensions'] );
                    $quality['band'] = Rules::band( $quality['score'] );
                }
                self::append_quality( $state, $scope, $object, $quality );
                if ( microtime( true ) - $batch_started >= Workload::budget_seconds( $scope ) ) {
                    break;
                }
            }
            if ( 'categories' === $scope ) {
                $state['cursor'] = absint( $state['cursor'] ) + $processed_batch;
            }
            // A user may pause/restart while this worker is running. Never
            // overwrite that explicit newer state with an old batch.
            $current_state = self::state( $scope );
            if ( ! in_array( (string) ( $current_state['status'] ?? '' ), array( 'queued', 'running' ), true )
                || (string) ( $current_state['started_gmt'] ?? '' ) !== (string) $state['started_gmt'] ) {
                return;
            }

            $state['last_batch_timestamp'] = time();
            $state['last_batch_duration_ms'] = max( 1, (int) round( ( microtime( true ) - $batch_started ) * 1000 ) );
            $batch_cpu_finished = Workload::process_cpu_ms();
            $state['last_batch_cpu_ms'] = null !== $batch_cpu_started && null !== $batch_cpu_finished
                ? max( 0, $batch_cpu_finished - $batch_cpu_started ) : null;
            $state['last_batch_objects'] = absint( $processed_batch );
            $state['memory_pressure_percent'] = Workload::memory_pressure_percent();
            $state['adaptive_batch_size'] = Workload::adjusted_batch_size(
                $scope,
                $requested_batch,
                $state['last_batch_duration_ms'],
                $state['memory_pressure_percent'],
                $processed_batch,
                $state['last_batch_cpu_ms']
            );
            self::trim_samples( $state );
            $full = 'full' === (string) ( $state['filters']['mode'] ?? 'filtered' );
            $limit_reached = ! $full && absint( $state['processed'] ) >= absint( $state['filters']['rows'] ?? 25 );
            // The threshold is captured at query time, even if another admin
            // changes speed during the worker request.
            $effective_batch = $requested_batch;
            // A time-budgeted loop may have fetched more rows than it processed.
            // Do not mark such a run complete or skip category offsets.
            if ( $limit_reached || ( $processed_batch === $fetched_batch && $fetched_batch < $effective_batch ) ) {
                self::complete( $scope, $state );
            } else {
                self::save_indexes( $state );
                update_option( self::option( $scope, 'state' ), $state, false );
                self::enqueue( $scope );
            }
        } catch ( \Throwable $e ) {
            $state['status'] = 'error';
            $state['reason'] = sanitize_text_field( $e->getMessage() );
            update_option( self::option( $scope, 'state' ), $state, false );
            wp_clear_scheduled_hook( self::HOOK, array( $scope ) );
        } finally {
            delete_transient( $lock );
            delete_option( $worker_option );
        }
    }
}
