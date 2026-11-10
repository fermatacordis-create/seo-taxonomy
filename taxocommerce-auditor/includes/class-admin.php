<?php
/**
 * Native WordPress administration for the independent content Auditor.
 */

namespace TaxoCommerce\Auditor;

defined( 'ABSPATH' ) || exit;

final class Admin {
    private static $monitor_enqueued = false;

    public static function register() {
        add_action( 'admin_menu', array( __CLASS__, 'register_menu' ), 70 );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
        add_action( 'admin_post_taxocommerce_auditor_action', array( __CLASS__, 'handle_action' ) );
        add_action( 'admin_post_taxocommerce_auditor_export', array( __CLASS__, 'handle_export' ) );
        add_action( 'wp_ajax_taxocommerce_auditor_watch', array( __CLASS__, 'handle_watch' ) );
    }

    public static function enqueue( $hook ) {
        if ( false === strpos( (string) $hook, Plugin::MENU_SLUG ) ) {
            return;
        }
        wp_enqueue_style(
            'taxocommerce-auditor-admin',
            plugins_url( 'assets/admin.css', dirname( __DIR__ ) . '/taxocommerce-auditor.php' ),
            array(),
            Plugin::VERSION
        );
        self::enqueue_monitor();
    }

    /**
     * Enqueue the footer worker from the admin hook OR the actual Auditor
     * screen. WordPress custom menu hosts may use a different screen hook.
     */
    private static function enqueue_monitor() {
        if ( self::$monitor_enqueued ) {
            return;
        }
        self::$monitor_enqueued = true;
        wp_enqueue_script(
            'taxocommerce-auditor-watch',
            plugins_url( 'assets/admin-watch.js', dirname( __DIR__ ) . '/taxocommerce-auditor.php' ),
            array(),
            Plugin::VERSION,
            true
        );
        wp_localize_script(
            'taxocommerce-auditor-watch',
            'taxocommerceAuditorWatch',
            array(
                'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
                'nonce'    => wp_create_nonce( 'taxocommerce_auditor_watch' ),
                'latest'   => __( 'Último lote', 'taxocommerce-auditor' ),
                'none'       => __( 'Sin datos', 'taxocommerce-auditor' ),
                'continuous' => __( 'Ejecución automática con regulación adaptativa de carga.', 'taxocommerce-auditor' ),
                'nextBatch'  => __( 'Próximo lote', 'taxocommerce-auditor' ),
                'duration'   => __( 'Duración', 'taxocommerce-auditor' ),
                'cpu'        => __( 'CPU PHP', 'taxocommerce-auditor' ),
                'failure'    => __( 'No se pudo procesar el siguiente lote. Comprueba la conexión; el sistema lo reintentará.', 'taxocommerce-auditor' ),
            )
        );
    }

    private static function guard() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'No tienes permisos para consultar Auditor.', 'taxocommerce-auditor' ), '', array( 'response' => 403 ) );
        }
    }

    public static function register_menu() {
        if ( function_exists( 'taxocommerce_core' ) || defined( 'TAXOCOMMERCE_CORE_VERSION' ) ) {
            add_submenu_page(
                'taxocommerce-core',
                __( 'Auditor de contenidos', 'taxocommerce-auditor' ),
                __( 'Auditor', 'taxocommerce-auditor' ),
                'manage_options',
                Plugin::MENU_SLUG,
                array( __CLASS__, 'render' )
            );
        } else {
            add_menu_page(
                __( 'TaxoCommerce Auditor', 'taxocommerce-auditor' ),
                __( 'Auditor', 'taxocommerce-auditor' ),
                'manage_options',
                Plugin::MENU_SLUG,
                array( __CLASS__, 'render' ),
                'dashicons-visibility',
                62
            );
        }
    }

    private static function state_label( $status ) {
        $labels = array(
            'queued'       => __( 'En cola', 'taxocommerce-auditor' ),
            'running'      => __( 'En curso', 'taxocommerce-auditor' ),
            'completed'    => __( 'Completado', 'taxocommerce-auditor' ),
            'paused'       => __( 'Pausado', 'taxocommerce-auditor' ),
            'error'        => __( 'Error', 'taxocommerce-auditor' ),
            'unavailable'  => __( 'Fuente no disponible', 'taxocommerce-auditor' ),
        );
        return $labels[ $status ] ?? __( 'Sin ejecutar', 'taxocommerce-auditor' );
    }

    private static function scope_link( $scope ) {
        return add_query_arg( array( 'page' => Plugin::MENU_SLUG, 'auditor_scope' => $scope ), admin_url( 'admin.php' ) );
    }

    private static function action_form( $scope, $task, $label, $primary = false ) {
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        echo '<input type="hidden" name="action" value="taxocommerce_auditor_action">';
        echo '<input type="hidden" name="scope" value="' . esc_attr( $scope ) . '">';
        echo '<input type="hidden" name="task" value="' . esc_attr( $task ) . '">';
        wp_nonce_field( 'taxocommerce_auditor_action_' . $scope );
        echo '<button type="submit" class="' . esc_attr( $primary ? 'button button-primary' : 'button' ) . '">' . esc_html( $label ) . '</button>';
        echo '</form>';
    }

    private static function filter_form( $scope, $label ) {
        $state = Runner::state( $scope );
        $filters = Runner::sanitize_filters( $scope, (array) ( $state['filters'] ?? array() ) );
        echo '<div class="taxocommerce-auditor-filter-wrap">' . '<p>' . '<strong>' . esc_html__( 'Auditoría filtrada', 'taxocommerce-auditor' ) . '</strong>' . ' ' . esc_html__( '· revisión rápida de una muestra o búsqueda concreta.', 'taxocommerce-auditor' ) . '</p>';
        echo '<form class="taxocommerce-auditor-filter" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        echo '<input type="hidden" name="action" value="taxocommerce_auditor_action">';
        echo '<input type="hidden" name="scope" value="' . esc_attr( $scope ) . '">';
        echo '<input type="hidden" name="task" value="start">';
        wp_nonce_field( 'taxocommerce_auditor_action_' . $scope );
        echo '<label>' . esc_html__( 'Buscar', 'taxocommerce-auditor' ) . ' <input type="text" name="search" value="' . esc_attr( $filters['search'] ) . '" placeholder="' . esc_attr__( 'Título o ID', 'taxocommerce-auditor' ) . '"></label>';
        if ( 'products' === $scope ) {
            $categories = get_terms( array( 'taxonomy'=>'product_cat', 'hide_empty'=>false, 'number'=>250, 'orderby'=>'name', 'order'=>'ASC' ) );
            if ( is_wp_error( $categories ) ) {
                $categories = array();
            }
            echo '<label>' . esc_html__( 'Categoría', 'taxocommerce-auditor' ) . ' ' . '<select name="category_id">' . '<option value="0">' . esc_html__( 'Todas', 'taxocommerce-auditor' ) . '</option>';
            foreach ( $categories as $category ) {
                echo '<option value="' . esc_attr( $category->term_id ) . '" ' . selected( $filters['category_id'], $category->term_id, false ) . '>' . esc_html( $category->name ) . '</option>';
            }
            echo '</select></label>';
        }
        echo '<label>' . esc_html__( 'Máximo', 'taxocommerce-auditor' ) . ' ' . '<select name="rows">';
        foreach ( array(10,25,50,100,250) as $n ) {
            echo '<option value="' . esc_attr( $n ) . '" ' . selected( $filters['rows'], $n, false ) . '>' . esc_html( $n ) . '</option>';
        }
        echo '</select></label>';
        echo '<button type="submit" class="button">' . esc_html( $label ) . '</button>';
        echo '</form></div>';
    }

    private static function scope_tab_url( $scope ) {
        return add_query_arg(
            array(
                'page'          => Plugin::MENU_SLUG,
                'auditor_scope' => sanitize_key( (string) $scope ),
            ),
            admin_url( 'admin.php' )
        );
    }

    private static function render_scope_controls( $scope ) {
        $state  = Runner::state( $scope );
        $report = Runner::report( $scope );
        $status = (string) ( $state['status'] ?? '' );
        $count = isset( $state['processed'] ) ? absint( $state['processed'] ) : 0;
        $expected = isset( $state['total'] ) ? absint( $state['total'] ) : null;

        echo '<section class="card taxocommerce-auditor-scope-controls">';
        echo '<div class="taxocommerce-auditor-scope-status"><div>';
        echo '<h2> ' . esc_html__( 'Auditoría de', 'taxocommerce-auditor' ) . ' ' . esc_html( Runner::scopes()[ $scope ] ) . '</h2>';
        echo '<p><strong> ' . esc_html__( 'Estado:', 'taxocommerce-auditor' ) . '  <span data-tca-status-label>' . esc_html( self::state_label( $status ) ) . '</span>';
        if ( in_array( $status, array( 'queued', 'running', 'paused' ), true ) ) {
            echo ' · <span data-tca-counter>' . esc_html( (string) $count ) . ' / ' . esc_html( null === $expected ? __( 'sin recuento disponible', 'taxocommerce-auditor' ) : (string) $expected ) . '</span>';
        }
        echo '</p>';
        if ( in_array( $status, array( 'queued', 'running' ), true ) ) {
            echo '<div class="taxocommerce-auditor-live" data-tca-live data-scope="' . esc_attr( $scope ) . '">';
            echo '<p class="description" data-tca-progress>' . esc_html__( 'Modo automático: los lotes avanzan solos y se ajustan a la carga disponible. Mantén abierta la pestaña.', 'taxocommerce-auditor' ) . '</p>';
            echo '<p class="description" role="status" data-tca-warning hidden></p>';
            echo '</div>';
        }
        if ( ! empty( $state['reason'] ) ) {
            echo '<p>' . esc_html( (string) $state['reason'] ) . '</p>';
        }
        echo '</div>';

        if ( $report ) {
            $mode = 'full' === (string) ( $report['run_mode'] ?? '' ) ? __( 'completa', 'taxocommerce-auditor' ) : __( 'filtrada', 'taxocommerce-auditor' );
            echo '<div class="taxocommerce-auditor-last-run">';
            echo '<strong>' . esc_html__( 'Último informe', 'taxocommerce-auditor' ) . '</strong>';
            echo '<span>' . esc_html( (string) ( $report['completed_gmt'] ?? '' ) ) . ' UTC</span>';
            echo '<span>' . esc_html( ucfirst( $mode ) ) . ' · ' . esc_html( (string) absint( $report['processed'] ?? 0 ) ) . ' ' . esc_html__( 'revisados', 'taxocommerce-auditor' ) . ' · ' . esc_html( (string) absint( $report['finding_count'] ?? 0 ) ) . ' ' . esc_html__( 'hallazgos', 'taxocommerce-auditor' ) . '</span>';
            $export = wp_nonce_url(
                add_query_arg( array( 'action' => 'taxocommerce_auditor_export', 'scope' => $scope ), admin_url( 'admin-post.php' ) ),
                'taxocommerce_auditor_export_' . $scope
            );
            echo '<a class="button" href="' . esc_url( $export ) . '"> ' . esc_html__( 'JSON completo', 'taxocommerce-auditor' ) . '</a>';
            echo '</div>';
        }
        echo '</div>';

        // Each audit scope has its own persisted workload setting. The form
        // neither resets the active run nor changes other scopes.
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="taxocommerce-auditor-workload">';
        echo '<input type="hidden" name="action" value="taxocommerce_auditor_action">';
        echo '<input type="hidden" name="scope" value="' . esc_attr( $scope ) . '">';
        echo '<input type="hidden" name="task" value="speed">';
        wp_nonce_field( 'taxocommerce_auditor_action_' . $scope );
        echo '<label for="taxocommerce-auditor-speed"><strong>' . esc_html__( 'Velocidad de auditoría', 'taxocommerce-auditor' ) . '</strong></label> ';
        echo '<select id="taxocommerce-auditor-speed" name="speed">';
        for ( $speed = 1; $speed <= 5; $speed++ ) {
            echo '<option value="' . esc_attr( (string) $speed ) . '" ' . selected( Workload::speed( $scope ), $speed, false ) . '>'
                . esc_html( (string) $speed . ' · ' . Workload::label( $speed ) ) . '</option>';
        }
        echo '</select> <button type="submit" class="button">' . esc_html__( 'Guardar velocidad', 'taxocommerce-auditor' ) . '</button>';
        echo '<p class="description">' . esc_html__( 'El nivel establece la carga máxima. Auditor adapta los lotes según duración, CPU del proceso PHP y memoria disponible. No mide el porcentaje global de CPU del servidor. Cambiarlo no reinicia el progreso.', 'taxocommerce-auditor' ) . '</p>';
        echo '</form>';

        // Do not offer a second start while a scope is already active or
        // paused: it would reject a valid existing cursor with HTTP 409.
        if ( ! in_array( $status, array( 'queued', 'running', 'paused' ), true ) ) {
            echo '<div class="taxocommerce-auditor-run-modes">';
            echo '<div class="taxocommerce-auditor-full">' . '<p>' . '<strong>' . esc_html__( 'Auditoría completa', 'taxocommerce-auditor' ) . '</strong>' . ' ' . esc_html__( '· recorre todo el bloque publicado por lotes y genera un JSON sin truncar la cola.', 'taxocommerce-auditor' ) . '</p>';
            self::action_form( $scope, 'start_full', $report ? __( 'Repetir auditoría completa', 'taxocommerce-auditor' ) : __( 'Auditar todo', 'taxocommerce-auditor' ), true );
            echo '</div>';
            self::filter_form( $scope, $report ? __( 'Repetir filtro', 'taxocommerce-auditor' ) : __( 'Auditar filtro', 'taxocommerce-auditor' ) );
            echo '</div>';
        }

        if ( in_array( $status, array( 'queued', 'running' ), true ) ) {
            // Next batch is driven automatically by assets/admin-watch.js.
            // Keep the previous 'step' handler for API/backwards compatibility,
            // but remove the manual step button from all five audit screens.
            echo '<div class="taxocommerce-auditor-process-actions">';
            self::action_form( $scope, 'pause', __( 'Pausar', 'taxocommerce-auditor' ) );
            echo '</div>';
        } elseif ( 'paused' === $status ) {
            echo '<div class="taxocommerce-auditor-process-actions">';
            self::action_form( $scope, 'resume', __( 'Reanudar', 'taxocommerce-auditor' ) );
            echo '</div>';
        }
        echo '</section>';
    }

    public static function render() {
        self::guard();
        // Reliable fallback when the menu is hosted by another plugin and
        // admin_enqueue_scripts receives a non-standard page hook. The script
        // is footer-loaded so registration here remains in time for printing.
        self::enqueue_monitor();
        $scopes = Runner::scopes();

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only scope selection.
        $selected = isset( $_GET['auditor_scope'] ) ? sanitize_key( wp_unslash( $_GET['auditor_scope'] ) ) : 'products';
        if ( ! Runner::valid_scope( $selected ) && ! in_array( $selected, array( 'auto-test', 'manual' ), true ) ) {
            $selected = 'products';
        }

        echo '<div class="wrap"><h1> ' . esc_html__( 'TaxoCommerce Auditor de contenidos', 'taxocommerce-auditor' ) . ' <small>v' . esc_html( Plugin::VERSION ) . '</small></h1>';
        echo '<p>' . esc_html__( 'Especialista de solo lectura en calidad e integridad del contenido. Detecta errores, incoherencias y trabajo pendiente; no publica, borra, mueve ni redirige contenidos.', 'taxocommerce-auditor' ) . '</p>';
        echo '<p>' . '<strong>' . esc_html__( 'Fuentes:', 'taxocommerce-auditor' ) . '</strong>' . ' ' . esc_html__( 'WordPress/WooCommerce nativo, Vocabulary, TaxoCommerce FAQs y contexto local persistido de otros módulos cuando existe. No ejecuta APIs externas durante la auditoría.', 'taxocommerce-auditor' ) . '</p>';

        echo '<nav class="nav-tab-wrapper taxocommerce-auditor-scope-tabs" aria-label="' . esc_attr__( 'Tipos de contenido', 'taxocommerce-auditor' ) . '">';
        foreach ( $scopes as $scope => $label ) {
            echo '<a href="' . esc_url( self::scope_tab_url( $scope ) ) . '" class="nav-tab' . ( $selected === $scope ? ' nav-tab-active' : '' ) . '"' . ( $selected === $scope ? ' aria-current="page"' : '' ) . '>' . esc_html( $label ) . '</a>';
        }
        foreach ( array( 'auto-test' => __( 'Autocheck', 'taxocommerce-auditor' ), 'manual' => __( 'Manual', 'taxocommerce-auditor' ) ) as $key => $label ) {
            echo '<a href="' . esc_url( self::scope_tab_url( $key ) ) . '" class="nav-tab' . ( $selected === $key ? ' nav-tab-active' : '' ) . '"' . ( $selected === $key ? ' aria-current="page"' : '' ) . '>' . esc_html( $label ) . '</a>';
        }
        echo '</nav>';

        if ( 'auto-test' === $selected ) {
            Auto_Test::render();
            echo '</div>';
            return;
        }
        if ( 'manual' === $selected ) {
            Manual::render();
            echo '</div>';
            return;
        }

        self::render_scope_controls( $selected );

        $report = Runner::report( $selected );
        if ( $report ) {
            echo '<section class="taxocommerce-auditor-scope-kpis">';
            self::render_kpis( $selected, $report );
            echo '</section>';
            self::render_report( $selected );
        } else {
            echo '<section class="taxocommerce-auditor-empty-report">';
            echo '<h2> ' . esc_html__( 'KPIs de', 'taxocommerce-auditor' ) . ' ' . esc_html( $scopes[ $selected ] ) . '</h2>';
            echo '<p>' . esc_html__( 'Los KPIs aparecerán aquí en cuanto finalice la primera auditoría de este bloque. Para indicadores representativos del conjunto, utiliza', 'taxocommerce-auditor' ) . ' ' . '<strong>' . esc_html__( 'Auditar todo', 'taxocommerce-auditor' ) . '</strong>' . '.' . '</p>';
            echo '</section>';
        }

        echo '</div>';
    }

    private static function edit_url( $type, $id ) {
        if ( 'product_cat' === $type || 'category' === $type ) {
            $url = get_edit_term_link( $id, 'product_cat' );
            return is_wp_error( $url ) ? '' : (string) $url;
        }
        if ( 'faq' === $type ) {
            if ( class_exists( '\\TaxoCommerce_FAQs' ) && is_callable( array( '\\TaxoCommerce_FAQs', 'url' ) ) ) {
                return (string) \TaxoCommerce_FAQs::url( array( 'faq_id' => absint( $id ) ) );
            }
            return (string) add_query_arg(
                array( 'page' => 'taxocommerce-core-content', 'tc_type' => 'faqs', 'faq_id' => absint( $id ) ),
                admin_url( 'admin.php' )
            );
        }
        return (string) get_edit_post_link( $id, '' );
    }

    private static function report_url( $scope, $view ) {
        return add_query_arg(
            array(
                'page'          => Plugin::MENU_SLUG,
                'auditor_scope' => $scope,
                'auditor_view'  => $view,
            ),
            admin_url( 'admin.php' )
        );
    }

    private static function entity_url( $row ) {
        return self::edit_url(
            sanitize_key( (string) ( $row['entity_type'] ?? '' ) ),
            absint( $row['entity_id'] ?? 0 )
        );
    }

    private static function render_report( $scope ) {
        $report = Runner::report( $scope );
        echo '<section class="taxocommerce-auditor-report">';
        echo '<h2>' . esc_html__( 'Detalle del informe', 'taxocommerce-auditor' ) . '</h2>';
        if ( ! $report ) {
            echo '<p>' . esc_html__( 'No hay todavía un informe finalizado para este bloque. Ejecuta Auditar y consulta su estado.', 'taxocommerce-auditor' ) . '</p>' . '</section>';
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only report view selector.
        $view = isset( $_GET['auditor_view'] ) ? sanitize_key( wp_unslash( $_GET['auditor_view'] ) ) : 'summary';
        $views = array(
            'summary'  => __( 'Resumen', 'taxocommerce-auditor' ),
            'quality'  => __( 'Calidad interna', 'taxocommerce-auditor' ),
            'plan'     => __( 'Plan priorizado', 'taxocommerce-auditor' ),
            'findings' => __( 'Hallazgos', 'taxocommerce-auditor' ),
            'context'  => __( 'Contexto de decisión', 'taxocommerce-auditor' ),
            'history'  => __( 'Historial', 'taxocommerce-auditor' ),
        );
        if ( 'categories' === $scope ) {
            $views['balance'] = __( 'Reequilibrio', 'taxocommerce-auditor' );
        }
        if ( ! isset( $views[ $view ] ) ) {
            $view = 'summary';
        }

        echo '<nav class="nav-tab-wrapper taxocommerce-auditor-tabs" aria-label="' . esc_attr__( 'Vistas del informe', 'taxocommerce-auditor' ) . '">';
        foreach ( $views as $key => $name ) {
            echo '<a href="' . esc_url( self::report_url( $scope, $key ) ) . '" class="nav-tab' . ( $view === $key ? ' nav-tab-active' : '' ) . '"' . ( $view === $key ? ' aria-current="page"' : '' ) . '>' . esc_html( $name ) . '</a>';
        }
        echo '</nav>';

        if ( 'quality' === $view ) {
            self::render_quality( $report );
        } elseif ( 'plan' === $view ) {
            self::render_plan( $report );
        } elseif ( 'findings' === $view ) {
            self::render_findings( $report );
        } elseif ( 'balance' === $view ) {
            self::render_balance( $report );
        } elseif ( 'context' === $view ) {
            self::render_context( $report );
        } elseif ( 'history' === $view ) {
            self::render_history( $scope );
        } else {
            self::render_summary( $report );
        }
        echo '</section>';
    }

    private static function render_metric( $value, $label ) {
        echo '<div class="taxocommerce-auditor-metric"><strong>' . esc_html( (string) $value ) . '</strong><span>' . esc_html( $label ) . '</span></div>';
    }

    private static function render_kpi_card( $row ) {
        $row = is_array( $row ) ? $row : array();
        $tone = sanitize_key( (string) ( $row['tone'] ?? 'neutral' ) );
        if ( ! in_array( $tone, array( 'good', 'warn', 'bad', 'neutral' ), true ) ) {
            $tone = 'neutral';
        }
        $value = (string) ( $row['value'] ?? 'N/D' );
        $unit = (string) ( $row['unit'] ?? '' );
        echo '<div class="taxocommerce-auditor-kpi is-' . esc_attr( $tone ) . '">';
        echo '<div class="taxocommerce-auditor-kpi-value"><strong>' . esc_html( $value ) . '</strong>';
        if ( '' !== $unit ) {
            echo '<span>' . esc_html( $unit ) . '</span>';
        }
        echo '</div>';
        echo '<h4>' . esc_html( (string) ( $row['label'] ?? '' ) ) . '</h4>';
        if ( ! empty( $row['detail'] ) ) {
            echo '<p>' . esc_html( (string) $row['detail'] ) . '</p>';
        }
        echo '</div>';
    }

    private static function render_kpis( $scope, $report ) {
        $kpis = (array) ( $report['scope_kpis'] ?? Runner::scope_kpis( $scope, $report ) );
        $scope_labels = array(
            'products'   => __( 'Productos', 'taxocommerce-auditor' ),
            'categories' => __( 'Categorías', 'taxocommerce-auditor' ),
            'posts'      => __( 'Posts', 'taxocommerce-auditor' ),
            'pages'      => __( 'Páginas y landings', 'taxocommerce-auditor' ),
            'faqs'       => 'FAQs',
        );
        $coverage = 'complete_scope' === (string) ( $kpis['coverage'] ?? '' )
            ? 'auditoría completa'
            : 'auditoría filtrada';

        echo '<div class="taxocommerce-auditor-kpi-head">';
        echo '<div><h3> ' . esc_html__( 'KPIs de', 'taxocommerce-auditor' ) . ' ' . esc_html( $scope_labels[ $scope ] ?? $scope ) . '</h3>';
        echo '<p>' . esc_html__( 'Indicadores específicos de calidad, integridad y errores a corregir sobre la última', 'taxocommerce-auditor' ) . ' ' . esc_html( $coverage ) . '.</p></div>';
        echo '<span class="taxocommerce-auditor-kpi-mode">' . esc_html( $coverage ) . '</span>';
        echo '</div>';

        echo '<h4 class="taxocommerce-auditor-kpi-section">' . esc_html__( 'Estado general', 'taxocommerce-auditor' ) . '</h4>';
        echo '<div class="taxocommerce-auditor-kpi-grid">';
        foreach ( (array) ( $kpis['general'] ?? array() ) as $row ) {
            self::render_kpi_card( $row );
        }
        echo '</div>';

        echo '<h4 class="taxocommerce-auditor-kpi-section">' . esc_html__( 'Diagnóstico específico', 'taxocommerce-auditor' ) . '</h4>';
        echo '<div class="taxocommerce-auditor-kpi-grid">';
        foreach ( (array) ( $kpis['specific'] ?? array() ) as $row ) {
            self::render_kpi_card( $row );
        }
        if ( empty( $kpis['specific'] ) ) {
            echo '<p>' . esc_html__( 'No hay indicadores específicos disponibles para este bloque.', 'taxocommerce-auditor' ) . '</p>';
        }
        echo '</div>';

        echo '<div class="notice notice-info inline"><p>' . esc_html( (string) ( $kpis['note'] ?? '' ) ) . '</p></div>';
    }

    private static function render_summary( $report ) {
        $counts = (array) ( $report['severity_counts'] ?? array() );
        $quality = (array) ( $report['quality'] ?? array() );
        echo '<h3>' . esc_html__( 'Resumen del último análisis', 'taxocommerce-auditor' ) . '</h3>';
        echo '<p>' . esc_html__( 'Finalizado:', 'taxocommerce-auditor' ) . ' <strong>' . esc_html( (string) ( $report['completed_gmt'] ?? '' ) ) . ' UTC</strong> · ' . esc_html__( 'Duración:', 'taxocommerce-auditor' ) . ' ' . esc_html( (string) absint( $report['duration_seconds'] ?? 0 ) ) . ' ' . esc_html__( 'segundos', 'taxocommerce-auditor' ) . '.</p>';
        echo '<div class="taxocommerce-auditor-metrics">';
        self::render_metric( absint( $report['processed'] ?? 0 ), __( 'Entidades revisadas', 'taxocommerce-auditor' ) );
        self::render_metric( absint( $report['finding_count'] ?? 0 ), __( 'Hallazgos', 'taxocommerce-auditor' ) );
        self::render_metric( absint( $counts['critical'] ?? 0 ), __( 'Críticos', 'taxocommerce-auditor' ) );
        self::render_metric( absint( $counts['high'] ?? 0 ), __( 'Alta', 'taxocommerce-auditor' ) );
        self::render_metric( absint( $counts['medium'] ?? 0 ), __( 'Media', 'taxocommerce-auditor' ) );
        self::render_metric( null === ( $quality['average'] ?? null ) ? 'N/D' : (string) $quality['average'], __( 'Calidad media / 100', 'taxocommerce-auditor' ) );
        echo '</div>';

        if ( absint( $report['excluded'] ?? 0 ) ) {
            echo '<p>' . esc_html__( 'Páginas funcionales/corporativas excluidas del diagnóstico editorial:', 'taxocommerce-auditor' ) . ' <strong>' . esc_html( (string) absint( $report['excluded'] ) ) . '</strong>.</p>';
        }
        self::render_priority_counts( (array) ( $report['priority_counts'] ?? array() ) );

        $scope = sanitize_key( (string) ( $report['scope'] ?? '' ) );
        $kpis = (array) ( $report['scope_kpis'] ?? ( $scope ? Runner::scope_kpis( $scope, $report ) : array() ) );
        if ( ! empty( $kpis['specific'] ) ) {
            echo '<h3>' . esc_html__( 'KPIs específicos', 'taxocommerce-auditor' ) . '</h3>' . '<div class="taxocommerce-auditor-kpi-grid is-compact">';
            foreach ( array_slice( (array) $kpis['specific'], 0, 4 ) as $row ) {
                self::render_kpi_card( $row );
            }
            echo '</div>';
            echo '<p class="description">' . esc_html__( 'El panel completo de KPIs de este tipo se muestra encima del detalle del informe.', 'taxocommerce-auditor' ) . '</p>';
        }

        $rules = (array) ( $report['rule_counts'] ?? array() );
        arsort( $rules, SORT_NUMERIC );
        echo '<h3>' . esc_html__( 'Reglas con más incidencias', 'taxocommerce-auditor' ) . '</h3>';
        echo '<div class="taxocommerce-auditor-table">' . '<table class="widefat striped">' . '<thead>' . '<tr>' . '<th>' . esc_html__( 'Regla', 'taxocommerce-auditor' ) . '</th>' . '<th>' . esc_html__( 'Hallazgos', 'taxocommerce-auditor' ) . '</th>' . '</tr>' . '</thead>' . '<tbody>';
        foreach ( array_slice( $rules, 0, 25, true ) as $code => $total ) {
            echo '<tr><td><code>' . esc_html( (string) $code ) . '</code></td><td>' . esc_html( (string) absint( $total ) ) . '</td></tr>';
        }
        if ( ! $rules ) {
            echo '<tr>' . '<td colspan="2">' . esc_html__( 'Sin incidencias registradas.', 'taxocommerce-auditor' ) . '</td>' . '</tr>';
        }
        echo '</tbody></table></div>';

        if ( ! empty( $report['findings_sample_truncated'] ) ) {
            echo '<p>' . '<strong>' . esc_html__( 'Atención:', 'taxocommerce-auditor' ) . '</strong>' . ' ' . esc_html__( 'se conservan los recuentos completos por regla y prioridad, pero las tareas mostradas son una muestra acotada para evitar informes demasiado pesados.', 'taxocommerce-auditor' ) . '</p>';
        }
        echo '<p>' . esc_html__( 'El índice de calidad es una evaluación interna para ordenar revisiones. No equivale a una puntuación de Google ni de Bing.', 'taxocommerce-auditor' ) . '</p>';
    }

    private static function render_priority_counts( $counts ) {
        echo '<h3>' . esc_html__( 'Plan de trabajo · prioridades P1–P5', 'taxocommerce-auditor' ) . '</h3>';
        $labels = array(
            'P1' => __( 'Atender primero / verificar incidencias', 'taxocommerce-auditor' ),
            'P2' => __( 'Esperar enriquecimiento', 'taxocommerce-auditor' ),
            'P3' => __( 'Migrar a Solucionador', 'taxocommerce-auditor' ),
            'P4' => __( 'Revisar', 'taxocommerce-auditor' ),
            'P5' => __( 'Informativo', 'taxocommerce-auditor' ),
        );
        echo '<div class="taxocommerce-auditor-metrics">';
        foreach ( $labels as $priority => $label ) {
            self::render_metric( absint( $counts[ $priority ] ?? 0 ), $priority . ' · ' . $label );
        }
        echo '</div>';
    }

    private static function render_quality( $report ) {
        $quality = (array) ( $report['quality'] ?? array() );
        echo '<h3>' . esc_html__( 'Puntuación interna de calidad', 'taxocommerce-auditor' ) . '</h3>' . '<p>' . esc_html__( 'Se conservan las cinco dimensiones del Auditor anterior; es una señal de priorización propia, no el algoritmo de un buscador.', 'taxocommerce-auditor' ) . '</p>';
        echo '<p>' . esc_html__( 'Entidades puntuadas:', 'taxocommerce-auditor' ) . ' ' . esc_html( (string) absint( $quality['scored'] ?? 0 ) );
        echo ' · ' . esc_html__( 'Media:', 'taxocommerce-auditor' ) . ' <strong>' . esc_html( null === ( $quality['average'] ?? null ) ? __( 'No disponible', 'taxocommerce-auditor' ) : (string) $quality['average'] . '/100' ) . '</strong>.</p>';
        $dimensions = array(
            'utility'         => __( 'Relevancia y utilidad', 'taxocommerce-auditor' ),
            'trust'           => __( 'Confianza y rigor', 'taxocommerce-auditor' ),
            'differentiation' => __( 'Diferenciación / canibalización', 'taxocommerce-auditor' ),
            'architecture'    => __( 'Arquitectura semántica', 'taxocommerce-auditor' ),
            'technical'       => __( 'Preparación técnica', 'taxocommerce-auditor' ),
        );
        echo '<div class="taxocommerce-auditor-table">' . '<table class="widefat striped">' . '<thead>' . '<tr>' . '<th>' . esc_html__( 'Dimensión', 'taxocommerce-auditor' ) . '</th>' . '<th>' . esc_html__( 'Máximo', 'taxocommerce-auditor' ) . '</th>' . '</tr>' . '</thead>' . '<tbody>';
        foreach ( $dimensions as $key => $label ) {
            echo '<tr><td>' . esc_html( $label ) . '</td><td>' . esc_html( (string) absint( $quality['dimensions'][ $key ] ?? 0 ) ) . '</td></tr>';
        }
        echo '</tbody></table></div>';

        $bands = (array) ( $quality['bands'] ?? array() );
        echo '<div class="taxocommerce-auditor-metrics">';
        foreach ( array( 'fuerte' => __( 'Fuerte', 'taxocommerce-auditor' ), 'correcta' => __( 'Correcta', 'taxocommerce-auditor' ), 'mejorable' => __( 'Mejorable', 'taxocommerce-auditor' ), 'debil' => __( 'Débil', 'taxocommerce-auditor' ) ) as $key => $label ) {
            self::render_metric( absint( $bands[ $key ] ?? 0 ), $label );
        }
        echo '</div>';

        echo '<h3>' . esc_html__( 'Entidades con menor puntuación', 'taxocommerce-auditor' ) . '</h3>';
        echo '<div class="taxocommerce-auditor-table">' . '<table class="widefat striped">' . '<thead>' . '<tr>' . '<th>' . esc_html__( 'Entidad', 'taxocommerce-auditor' ) . '</th>' . '<th>' . esc_html__( 'Calidad', 'taxocommerce-auditor' ) . '</th>' . '<th>' . esc_html__( 'Dimensiones', 'taxocommerce-auditor' ) . '</th>' . '</tr>' . '</thead>' . '<tbody>';
        foreach ( (array) ( $quality['lowest_scored_sample'] ?? array() ) as $row ) {
            $url = self::entity_url( $row );
            echo '<tr><td>';
            if ( $url ) {
                echo '<a href="' . esc_url( $url ) . '">' . esc_html( (string) ( $row['title'] ?? '' ) ) . '</a>';
            } else {
                echo esc_html( (string) ( $row['title'] ?? '' ) );
            }
            echo ' <code>#' . esc_html( (string) absint( $row['entity_id'] ?? 0 ) ) . '</code></td>';
            echo '<td><strong>' . esc_html( (string) absint( $row['score'] ?? 0 ) ) . '/100</strong> · ' . esc_html( (string) ( $row['band'] ?? '' ) ) . '</td>';
            $parts = array();
            foreach ( (array) ( $row['dimensions'] ?? array() ) as $key => $value ) {
                $parts[] = ( $dimensions[ $key ] ?? $key ) . ': ' . absint( $value );
            }
            echo '<td>' . esc_html( implode( ' · ', $parts ) ) . '</td></tr>';
        }
        echo '</tbody></table></div>';
    }

    private static function render_plan( $report ) {
        $rows = (array) ( $report['findings_sample'] ?? array() );
        echo '<h3>' . esc_html__( 'Plan de revisión priorizado', 'taxocommerce-auditor' ) . '</h3>';
        echo '<p>' . esc_html__( 'Cada tarea incluye prioridad, estado, evidencia y recomendación. Una prioridad alta no autoriza a modificar automáticamente contenidos.', 'taxocommerce-auditor' ) . '</p>';
        if ( ! empty( $report['findings_sample_truncated'] ) ) {
            echo '<p>' . esc_html__( 'El listado es una muestra priorizada de', 'taxocommerce-auditor' ) . ' ' . esc_html( (string) count( $rows ) ) . ' ' . esc_html__( 'tareas sobre', 'taxocommerce-auditor' ) . ' ' . esc_html( (string) absint( $report['finding_count'] ?? 0 ) ) . ' ' . esc_html__( 'hallazgos. Consulta los contadores completos en Resumen.', 'taxocommerce-auditor' ) . '</p>';
        }
        if ( ! $rows ) {
            echo '<p>' . esc_html__( 'Sin hallazgos.', 'taxocommerce-auditor' ) . '</p>';
            return;
        }

        echo '<div class="taxocommerce-auditor-table"><table class="widefat striped"><thead><tr>';
        echo '<th>' . esc_html__( 'Prioridad', 'taxocommerce-auditor' ) . '</th>' . '<th>' . esc_html__( 'Entidad', 'taxocommerce-auditor' ) . '</th>' . '<th>' . esc_html__( 'Problema', 'taxocommerce-auditor' ) . '</th>' . '<th>' . esc_html__( 'Evidencia', 'taxocommerce-auditor' ) . '</th>' . '<th>' . esc_html__( 'Recomendación', 'taxocommerce-auditor' ) . '</th>' . '<th>' . esc_html__( 'Estado', 'taxocommerce-auditor' ) . '</th>' . '</tr>' . '</thead>' . '<tbody>';
        foreach ( array_slice( $rows, 0, 200 ) as $row ) {
            $url = self::entity_url( $row );
            $type = sanitize_key( (string) ( $row['entity_type'] ?? '' ) );
            $id = absint( $row['entity_id'] ?? 0 );
            echo '<tr><td><strong>' . esc_html( (string) ( $row['priority'] ?? 'P5' ) ) . '</strong>';
            echo '<br>' . esc_html( (string) ( $row['severity'] ?? '' ) );
            echo '<br><small>' . esc_html( (string) ( $row['priority_score'] ?? '' ) ) . '</small></td>';
            echo '<td>';
            if ( $url ) {
                echo '<a href="' . esc_url( $url ) . '">' . esc_html( (string) ( $row['title'] ?? '' ) ) . '</a>';
            } else {
                echo esc_html( (string) ( $row['title'] ?? '' ) );
            }
            echo '<br><code>' . esc_html( $type . ' #' . $id ) . '</code></td>';
            echo '<td><code>' . esc_html( (string) ( $row['code'] ?? '' ) ) . '</code>';
            echo '<br><small>' . esc_html( (string) ( $row['task_id'] ?? '' ) ) . '</small></td>';
            echo '<td>' . esc_html( (string) ( $row['evidence'] ?? '' ) );
            if ( ! empty( $row['context'] ) ) {
                echo '<details><summary>' . esc_html__( 'Datos de contexto', 'taxocommerce-auditor' ) . '</summary><pre>' . esc_html( wp_json_encode( $row['context'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) ) . '</pre></details>';
            }
            echo '</td><td>' . esc_html( (string) ( $row['recommendation'] ?? '' ) ) . '</td>';
            echo '<td>' . esc_html( (string) ( $row['status'] ?? 'REVIEW_REQUIRED' ) ) . '</td></tr>';
        }
        echo '</tbody></table></div>';
    }

    private static function render_findings( $report ) {
        echo '<h3>' . esc_html__( 'Hallazgos por regla', 'taxocommerce-auditor' ) . '</h3>';
        $rows = (array) ( $report['rule_counts'] ?? array() );
        arsort( $rows, SORT_NUMERIC );
        echo '<p>' . esc_html__( 'Esta vista resume la totalidad de incidencias contabilizadas, no solo las que caben en la muestra de tareas.', 'taxocommerce-auditor' ) . '</p>';
        echo '<div class="taxocommerce-auditor-table">' . '<table class="widefat striped">' . '<thead>' . '<tr>' . '<th>' . esc_html__( 'Código de hallazgo', 'taxocommerce-auditor' ) . '</th>' . '<th>' . esc_html__( 'Incidencias', 'taxocommerce-auditor' ) . '</th>' . '</tr>' . '</thead>' . '<tbody>';
        foreach ( $rows as $code => $count ) {
            echo '<tr><td><code>' . esc_html( (string) $code ) . '</code></td><td>' . esc_html( (string) absint( $count ) ) . '</td></tr>';
        }
        if ( ! $rows ) {
            echo '<tr>' . '<td colspan="2">' . esc_html__( 'Ninguna incidencia.', 'taxocommerce-auditor' ) . '</td>' . '</tr>';
        }
        echo '</tbody></table></div>';
    }

    private static function render_balance( $report ) {
        $balance = (array) ( $report['category_rebalance'] ?? array() );
        $summary = (array) ( $balance['summary'] ?? array() );
        echo '<h3>' . esc_html__( 'Reequilibrio de categorías', 'taxocommerce-auditor' ) . '</h3>';
        echo '<p>' . esc_html__( 'Se conserva la referencia histórica de 5–10 productos por categoría. El tamaño solo señala casos para revisar:', 'taxocommerce-auditor' ) . ' ' . '<strong>' . esc_html__( 'no justifica por sí solo dividir, fusionar ni crear una URL', 'taxocommerce-auditor' ) . '</strong>' . '.' . '</p>';
        echo '<div class="taxocommerce-auditor-metrics">';
        self::render_metric( absint( $summary['review_split'] ?? 0 ), __( 'Revisar por división', 'taxocommerce-auditor' ) );
        self::render_metric( absint( $summary['split_proposals'] ?? 0 ), __( 'Propuestas de división', 'taxocommerce-auditor' ) );
        self::render_metric( absint( $summary['review_merge'] ?? 0 ), __( 'Revisar concentración', 'taxocommerce-auditor' ) );
        self::render_metric( absint( $summary['merge_proposals'] ?? 0 ), __( 'Propuestas de concentración', 'taxocommerce-auditor' ) );
        echo '</div>';
        echo '<p>' . esc_html__( 'La generación de propuestas está activa. La aprobación/aplicación con movimiento de productos y redirecciones 301 sigue deshabilitada en esta migración: requiere una capa transaccional nativa validada.', 'taxocommerce-auditor' ) . '</p>';

        $rows = (array) ( $balance['proposals'] ?? array() );
        if ( ! $rows ) {
            echo '<p>' . esc_html__( 'No hay propuestas respaldadas por evidencia semántica suficiente. Las categorías fuera del tamaño de referencia siguen reflejadas como hallazgos para revisión manual.', 'taxocommerce-auditor' ) . '</p>';
            return;
        }
        if ( ! empty( $balance['sample_truncated'] ) ) {
            echo '<p>' . esc_html__( 'La tabla contiene una muestra de propuestas, no el total.', 'taxocommerce-auditor' ) . '</p>';
        }
        echo '<div class="taxocommerce-auditor-table">' . '<table class="widefat striped">' . '<thead>' . '<tr>' . '<th>' . esc_html__( 'Categoría origen', 'taxocommerce-auditor' ) . '</th>' . '<th>' . esc_html__( 'Tipo', 'taxocommerce-auditor' ) . '</th>' . '<th>' . esc_html__( 'Productos', 'taxocommerce-auditor' ) . '</th>' . '<th>' . esc_html__( 'Evidencia', 'taxocommerce-auditor' ) . '</th>' . '<th>' . esc_html__( 'Acción', 'taxocommerce-auditor' ) . '</th>' . '</tr>' . '</thead>' . '<tbody>';
        foreach ( array_slice( $rows, 0, 150 ) as $row ) {
            $id = absint( $row['category_id'] ?? 0 );
            $url = self::edit_url( 'product_cat', $id );
            $proposal = (array) ( $row['proposal'] ?? array() );
            $split = ! empty( $row['split_proposal'] );
            echo '<tr><td><strong>' . esc_html( (string) ( $row['category_name'] ?? '' ) ) . '</strong><br><code>#' . esc_html( (string) $id ) . '</code></td>';
            echo '<td>' . esc_html( $split ? __( 'División por TIPO', 'taxocommerce-auditor' ) : __( 'Concentración en hermana', 'taxocommerce-auditor' ) ) . '</td>';
            echo '<td>' . esc_html( (string) absint( $row['products'] ?? 0 ) ) . '</td><td>';
            if ( $split ) {
                foreach ( array_slice( (array) ( $proposal['cohorts'] ?? array() ), 0, 8 ) as $cohort ) {
                    echo '<p><strong>' . esc_html( (string) ( $cohort['label'] ?? '' ) ) . '</strong>: ' . esc_html( (string) absint( $cohort['products'] ?? 0 ) ) . ' ' . esc_html__( 'productos', 'taxocommerce-auditor' ) . '</p>';
                }
                echo '<small>' . esc_html__( 'Ambiguos:', 'taxocommerce-auditor' ) . ' ' . esc_html( (string) absint( $proposal['ambiguous_products'] ?? 0 ) ) . '</small>';
            } else {
                echo esc_html__( 'Destino sugerido:', 'taxocommerce-auditor' ) . ' <strong>' . esc_html( (string) ( $proposal['target_name'] ?? '' ) ) . '</strong> #' . esc_html( (string) absint( $proposal['target_id'] ?? 0 ) );
                echo '<p>' . esc_html__( 'Similitud de nombre:', 'taxocommerce-auditor' ) . ' ' . esc_html( (string) ( $proposal['title_similarity'] ?? 'N/D' ) ) . ' · ' . esc_html__( 'Vocabulary:', 'taxocommerce-auditor' ) . ' ' . esc_html( (string) ( $proposal['concept_overlap'] ?? 'N/D' ) ) . '</p>';
            }
            echo '</td><td>';
            if ( $url ) {
                echo '<a class="button" href="' . esc_url( $url ) . '">' . esc_html__( 'Abrir categoría', 'taxocommerce-auditor' ) . '</a>';
            }
            echo '<p>' . '<small>' . esc_html__( 'Revisión; no hay cambios automáticos.', 'taxocommerce-auditor' ) . '</small>' . '</p>' . '</td>' . '</tr>';
        }
        echo '</tbody></table></div>';
    }

    private static function render_context( $report ) {
        echo '<h3>' . esc_html__( 'Contexto de decisión', 'taxocommerce-auditor' ) . '</h3>' . '<p>' . esc_html__( 'Auditor evalúa consistencia interna y no consulta directamente APIs de buscadores, compras ni analítica.', 'taxocommerce-auditor' ) . '</p>';
        echo '<p>' . esc_html__( 'Fuentes nativas disponibles para esta ejecución:', 'taxocommerce-auditor' ) . ' <strong>' . esc_html( (string) ( $report['source_model'] ?? 'WordPress / WooCommerce' ) ) . '</strong>.</p>';
        $context = $report['decision_context'] ?? null;
        if ( ! is_array( $context ) || ! $context ) {
            echo '<p>' . '<strong>' . esc_html__( 'Analista, Ojeador, Dependiente y Comparador:', 'taxocommerce-auditor' ) . '</strong>' . ' ' . esc_html__( 'datos no disponibles para este informe. Ausente no significa cero; no se ha inventado ningún valor.', 'taxocommerce-auditor' ) . '</p>';
        } else {
            echo '<p>' . esc_html__( 'Contexto leído exclusivamente de proveedores persistidos mediante el filtro de integración del nuevo Auditor.', 'taxocommerce-auditor' ) . '</p>';
            echo '<pre class="taxocommerce-auditor-json">' . esc_html( wp_json_encode( $context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) ) . '</pre>';
        }
        if ( ! empty( $report['editorial_comparison'] ) ) {
            $map = (array) $report['editorial_comparison'];
            echo '<p>' . esc_html__( 'Mapa editorial indexado:', 'taxocommerce-auditor' ) . ' ' . esc_html( (string) absint( $map['indexed_posts_pages'] ?? 0 ) ) . ' ' . esc_html__( 'objetos', 'taxocommerce-auditor' ) . '.';
            echo ! empty( $map['partial'] ) ? ' ' . esc_html__( 'La comparación es parcial por límite de seguridad.', 'taxocommerce-auditor' ) . '</p>' : '</p>';
        }
        echo '<p>' . esc_html__( 'Los enlaces a fuentes y el contraste externo son tareas distintas de la auditoría de contenidos.', 'taxocommerce-auditor' ) . '</p>';
    }

    private static function render_history( $scope ) {
        $history = Runner::history( $scope );
        echo '<h3>' . esc_html__( 'Historial de auditorías completadas', 'taxocommerce-auditor' ) . '</h3>';
        if ( ! $history ) {
            echo '<p>' . esc_html__( 'No existen ejecuciones completadas todavía.', 'taxocommerce-auditor' ) . '</p>';
            return;
        }
        echo '<div class="taxocommerce-auditor-table">' . '<table class="widefat striped">' . '<thead>' . '<tr>' . '<th>' . esc_html__( 'Fecha UTC', 'taxocommerce-auditor' ) . '</th>' . '<th>' . esc_html__( 'Duración', 'taxocommerce-auditor' ) . '</th>' . '<th>' . esc_html__( 'Revisados', 'taxocommerce-auditor' ) . '</th>' . '<th>' . esc_html__( 'Hallazgos', 'taxocommerce-auditor' ) . '</th>' . '<th>' . 'P1–P5' . '</th>' . '</tr>' . '</thead>' . '<tbody>';
        foreach ( $history as $row ) {
            $priorities = array();
            foreach ( array( 'P1','P2','P3','P4','P5' ) as $priority ) {
                $priorities[] = $priority . ': ' . absint( $row['priority_counts'][ $priority ] ?? 0 );
            }
            echo '<tr><td>' . esc_html( (string) ( $row['completed_gmt'] ?? '' ) ) . '</td>';
            echo '<td>' . esc_html( (string) absint( $row['duration_seconds'] ?? 0 ) ) . ' s</td>';
            echo '<td>' . esc_html( (string) absint( $row['processed'] ?? 0 ) ) . '</td>';
            echo '<td>' . esc_html( (string) absint( $row['finding_count'] ?? 0 ) ) . '</td>';
            echo '<td>' . esc_html( implode( ' · ', $priorities ) ) . '</td></tr>';
        }
        echo '</tbody></table></div>';
    }

    /**
     * Authenticated browser worker: execute one bounded batch per request.
     * The next AJAX request follows the previous response. No WP-Cron
     * timestamp is consulted before running; WP-Cron remains an optional
     * background fallback for installations where it is available.
     */
    public static function handle_watch() {
        self::guard();
        check_ajax_referer( 'taxocommerce_auditor_watch', 'nonce' );
        $scope = isset( $_POST['scope'] ) && is_string( $_POST['scope'] )
            ? sanitize_key( wp_unslash( $_POST['scope'] ) ) : '';
        if ( ! Runner::valid_scope( $scope ) ) {
            wp_send_json_error( array( 'message' => __( 'Bloque Auditor no reconocido.', 'taxocommerce-auditor' ) ), 400 );
        }

        $before = Runner::state( $scope );
        if ( in_array( (string) ( $before['status'] ?? '' ), array( 'queued', 'running' ), true ) ) {
            // run_batch() owns the existing per-scope atomic lock, budget,
            // cursor and JSONL persistence; it also respects pause/error.
            Runner::run_batch( $scope );
        }
        $progress = Runner::progress( $scope );
        $progress['batch_processed'] = max( 0, absint( $progress['processed'] ) - absint( $before['processed'] ?? 0 ) );
        $progress['status_label'] = self::state_label( $progress['status'] );
        // Refresh the admin nonce during very long scans without reopening a tab.
        $progress['nonce'] = wp_create_nonce( 'taxocommerce_auditor_watch' );
        wp_send_json_success( $progress );
    }

    public static function handle_action() {
        self::guard();
        $scope = isset( $_POST['scope'] ) && is_string( $_POST['scope'] )
            ? sanitize_key( wp_unslash( $_POST['scope'] ) ) : '';
        $task = isset( $_POST['task'] ) && is_string( $_POST['task'] )
            ? sanitize_key( wp_unslash( $_POST['task'] ) ) : '';
        if ( ! Runner::valid_scope( $scope ) ) {
            wp_die( esc_html__( 'Bloque Auditor no reconocido.', 'taxocommerce-auditor' ), '', array( 'response' => 400 ) );
        }
        check_admin_referer( 'taxocommerce_auditor_action_' . $scope );
        if ( 'speed' === $task ) {
            $raw_speed = isset( $_POST['speed'] ) && is_scalar( $_POST['speed'] )
                ? sanitize_text_field( wp_unslash( (string) $_POST['speed'] ) ) : '';
            if ( ! Workload::set_speed( $scope, $raw_speed ) ) {
                wp_die( esc_html__( 'Velocidad no válida.', 'taxocommerce-auditor' ), '', array( 'response' => 400 ) );
            }
            Runner::reschedule( $scope );
        } elseif ( in_array( $task, array( 'start', 'start_full' ), true ) ) {
            $current = Runner::state( $scope );
            if ( in_array( (string) ( $current['status'] ?? '' ), array( 'queued', 'running', 'paused' ), true ) ) {
                // Stale tabs must not restart active work or discard progress.
                wp_safe_redirect( self::scope_link( $scope ) );
                exit;
            }
            $filters = Runner::sanitize_filters( $scope, array(
                'mode'        => 'start_full' === $task ? 'full' : 'filtered',
                'search'      => isset( $_POST['search'] ) && is_string( $_POST['search'] )
                    ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '',
                'category_id' => isset( $_POST['category_id'] ) && is_scalar( $_POST['category_id'] )
                    ? absint( wp_unslash( (string) $_POST['category_id'] ) ) : 0,
                'rows'        => isset( $_POST['rows'] ) && is_scalar( $_POST['rows'] )
                    ? absint( wp_unslash( (string) $_POST['rows'] ) ) : 25,
            ) );
            if ( ! Runner::start( $scope, $filters ) ) {
                wp_die( esc_html__( 'Ya hay una auditoría de este bloque procesándose. Espera a que acabe el lote antes de reiniciarla.', 'taxocommerce-auditor' ), '', array( 'response' => 409 ) );
            }
            // The admin screen starts a browser-driven batch chain after redirect.
            // WP-Cron is a secondary fallback only; no event must fire first.
        } elseif ( 'step' === $task ) {
            Runner::run_batch( $scope );
        } elseif ( 'pause' === $task ) {
            Runner::pause( $scope );
        } elseif ( 'resume' === $task ) {
            Runner::resume( $scope );
        } else {
            wp_die( esc_html__( 'Acción no reconocida.', 'taxocommerce-auditor' ), '', array( 'response' => 400 ) );
        }
        wp_safe_redirect( self::scope_link( $scope ) );
        exit;
    }

    public static function handle_export() {
        self::guard();
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Validated immediately after scope.
        $scope = isset( $_GET['scope'] ) ? sanitize_key( wp_unslash( $_GET['scope'] ) ) : '';
        if ( ! Runner::valid_scope( $scope ) ) {
            wp_die( esc_html__( 'Bloque Auditor no reconocido.', 'taxocommerce-auditor' ), '', array( 'response' => 400 ) );
        }
        check_admin_referer( 'taxocommerce_auditor_export_' . $scope );
        $report = Runner::report( $scope );
        if ( ! $report ) {
            wp_die( esc_html__( 'No hay informe completo que exportar.', 'taxocommerce-auditor' ), '', array( 'response' => 404 ) );
        }
        nocache_headers();
        header( 'X-Content-Type-Options: nosniff' );
        header( 'Content-Type: application/json; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="taxocommerce-auditor-' . $scope . '-' . gmdate( 'Ymd-His' ) . '.json"' );
        if ( ! Export_Store::stream_report( $scope, $report ) ) {
            // Backward compatibility for reports created before the complete
            // JSON spool existed.
            echo wp_json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE );
        }
        exit;
    }
}
