<?php
/**
 * Standalone WordPress admin: rules, KPIs, diagnostics and JSON transfer.
 */

namespace TaxoCommerce\Redirects;

defined( 'ABSPATH' ) || exit;

final class Admin {
    public static function register() {
        add_action( 'admin_menu', array( __CLASS__, 'menu' ), 40 );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
        add_action( 'admin_post_tc_redirects_manage', array( __CLASS__, 'handle_manage' ) );
        add_action( 'admin_post_tc_redirects_export', array( __CLASS__, 'handle_export' ) );
        add_action( 'admin_post_tc_redirects_toggle', array( __CLASS__, 'handle_toggle' ) );
    }

    public static function menu() {
        if ( function_exists( 'taxocommerce_core' ) ) {
            add_submenu_page( 'taxocommerce-core', __( 'TaxoCommerce Redirects', 'taxocommerce-redirects' ), __( 'Redirects', 'taxocommerce-redirects' ), 'manage_options', Plugin::SLUG, array( __CLASS__, 'render' ) );
            return;
        }
        add_menu_page( __( 'TaxoCommerce Redirects', 'taxocommerce-redirects' ), __( 'Redirects', 'taxocommerce-redirects' ), 'manage_options', Plugin::SLUG, array( __CLASS__, 'render' ), 'dashicons-randomize', 62 );
    }

    public static function assets( $hook ) {
        if ( false === strpos( (string) $hook, Plugin::SLUG ) ) {
            return;
        }
        wp_enqueue_style(
            'taxocommerce-redirects-admin',
            plugins_url( 'assets/admin.css', dirname( __DIR__ ) . '/taxocommerce-redirects.php' ),
            array(), Plugin::VERSION
        );
    }

    private static function can_manage() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'No tienes permisos para administrar redirecciones.', '', array( 'response' => 403 ) );
        }
    }

    private static function url( $args = array() ) {
        return add_query_arg( array_merge( array( 'page' => Plugin::SLUG ), $args ), admin_url( 'admin.php' ) );
    }

    private static function success( $text, $is_error = false ) {
        set_transient(
            'tc_redirects_notice_' . get_current_user_id(),
            array( 'text' => sanitize_text_field( $text ), 'error' => (bool) $is_error ),
            MINUTE_IN_SECONDS
        );
        wp_safe_redirect( self::url() );
        exit;
    }

    private static function notice() {
        $key = 'tc_redirects_notice_' . get_current_user_id();
        $notice = get_transient( $key );
        delete_transient( $key );
        if ( ! is_array( $notice ) || empty( $notice['text'] ) ) {
            return;
        }
        echo '<div class="notice ' . ( ! empty( $notice['error'] ) ? 'notice-error' : 'notice-success' ) . ' is-dismissible"><p>'
            . esc_html( (string) $notice['text'] ) . '</p></div>';
    }

    /**
     * Save the redirect-engine ownership preference without touching rules.
     *
     * @return void
     */
    public static function handle_toggle() {
        self::can_manage();
        check_admin_referer( 'tc_redirects_toggle' );

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified above.
        $enabled = isset( $_POST['tc_redirects_enabled'] )
            && is_string( $_POST['tc_redirects_enabled'] )
            && '1' === sanitize_text_field( wp_unslash( $_POST['tc_redirects_enabled'] ) );

        update_option( 'tc_redirects_enabled', $enabled ? 'yes' : 'no', false );
        self::success( $enabled
            ? __( 'TaxoCommerce Redirects vuelve a gestionar las redirecciones.', 'taxocommerce-redirects' )
            : __( 'Gestión de redirecciones desactivada; las reglas guardadas se conservan.', 'taxocommerce-redirects' )
        );
    }

    public static function render() {
        self::can_manage();
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only tab navigation.
        $tab = isset( $_GET['tc_rd_tab'] ) && is_string( $_GET['tc_rd_tab'] ) ? sanitize_key( wp_unslash( $_GET['tc_rd_tab'] ) ) : 'redirects';
        // phpcs:enable WordPress.Security.NonceVerification.Recommended
        if ( 'auto-test' === $tab || 'user-guide' === $tab ) {
            echo '<div class="wrap tc-rd">' . '<h1>' . esc_html__( 'TaxoCommerce Redirects', 'taxocommerce-redirects' ) . '</h1>';
            self::tabs( $tab );
            if ( 'auto-test' === $tab ) {
                Auto_Test::render();
            } else {
                User_Guide::render();
            }
            echo '</div>';
            return;
        }
        $rows = Store::rows();
        $analysis = Store::diagnostics( $rows );
        $stats = $analysis['stats'];
        $details = $analysis['details'];
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter.
        $filter = isset( $_GET['tc_filter'] ) ? sanitize_key( wp_unslash( $_GET['tc_filter'] ) ) : 'all';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only search.
        $needle = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only edit selector.
        $edit_id = isset( $_GET['edit'] ) ? absint( $_GET['edit'] ) : 0;
        $editing = $edit_id ? Store::get( $edit_id ) : null;
        $subset = array();
        foreach ( $rows as $row ) {
            $detail = (array) ( $details[ absint( $row->id ) ] ?? array() );
            if ( 'issues' === $filter && empty( $detail['issues'] ) ) {
                continue;
            }
            if ( 'used' === $filter && 0 === absint( $row->hits ) ) {
                continue;
            }
            if ( 'unused' === $filter && absint( $row->hits ) > 0 ) {
                continue;
            }
            if ( '' !== $needle && false === stripos( (string) $row->origin_url . ' ' . (string) $row->target_url . ' ' . (string) $row->id, $needle ) ) {
                continue;
            }
            $subset[] = $row;
        }
        $page = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $per_page = 50;
        $max_page = max( 1, (int) ceil( count( $subset ) / $per_page ) );
        $page = min( $page, $max_page );
        $subset = array_slice( $subset, ( $page - 1 ) * $per_page, $per_page );
        ?>
        <div class="wrap tc-rd">
            <h1>Gestor de Redirecciones SEO</h1>
            <p>TaxoCommerce Redirects · gestor independiente de WordPress y WooCommerce.
                <strong>Reglas compartidas con TaxoCommerce SEO antiguo</strong>, sin migrar ni duplicar tablas.</p>
            <?php self::tabs( 'redirects' ); ?>
            <?php self::notice(); ?>
            <section class="tc-rd-panel" style="margin:16px 0;padding:16px;">
                <h2><?php esc_html_e( 'Control de redirecciones', 'taxocommerce-redirects' ); ?></h2>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <input type="hidden" name="action" value="tc_redirects_toggle">
                    <?php wp_nonce_field( 'tc_redirects_toggle' ); ?>
                    <label for="tc-redirects-enabled">
                        <input type="checkbox" name="tc_redirects_enabled" id="tc-redirects-enabled" value="1" <?php checked( Engine::enabled() ); ?>>
                        <strong><?php esc_html_e( 'TaxoCommerce gestiona las redirecciones', 'taxocommerce-redirects' ); ?></strong>
                    </label>
                    <p class="description"><?php esc_html_e( 'Desmarcar detiene las redirecciones de TaxoCommerce y cede el control a otro gestor si está instalado. Las reglas guardadas, la importación y los informes se conservan. No activa ni configura automáticamente otro plugin.', 'taxocommerce-redirects' ); ?></p>
                    <?php if ( Engine::enabled() && '' !== Engine::external_owner() ) : ?>
                        <p class="description"><?php esc_html_e( 'Otro plugin reclama actualmente la gestión de redirecciones.', 'taxocommerce-redirects' ); ?></p>
                    <?php elseif ( ! Engine::enabled() ) : ?>
                        <p class="description"><?php esc_html_e( 'Motor de redirecciones desactivado; ningún otro gestor se habilita automáticamente.', 'taxocommerce-redirects' ); ?></p>
                    <?php endif; ?>
                    <?php submit_button( __( 'Guardar elección', 'taxocommerce-redirects' ), 'secondary', 'submit', false ); ?>
                </form>
            </section>
            <?php if ( ! Store::table_exists() ) : ?>
                <div class="notice notice-error"><p>No existe la tabla de redirecciones. Desactiva y activa el plugin para crearla sin alterar las reglas antiguas.</p></div>
            <?php endif; ?>
            <div class="tc-rd-kpis">
                <?php
                self::kpi( $stats['total'], 'Redirects' );
                self::kpi( $stats['with_hits'], 'Reglas utilizadas' );
                self::kpi( $stats['without_hits'], 'Sin uso' );
                self::kpi( $stats['hits_total'], 'Hits acumulados' );
                self::kpi( $stats['used_last_24h_rules'], 'Reglas usadas en 24 h' );
                self::kpi( $stats['structural'], 'Con incidencias' );
                self::kpi( $stats['chains'], 'Cadenas' );
                self::kpi( $stats['cycles'], 'Ciclos' );
                ?>
            </div>
            <p class="description">Los hits son acumulados. «Reglas usadas en 24 h» cuenta reglas con actividad reciente, no número de solicitudes.</p>

            <div class="tc-rd-columns">
                <section class="tc-rd-panel">
                    <h2><?php echo $editing ? 'Editar redirección #' . esc_html( (string) $editing->id ) : 'Añadir nueva redirección'; ?></h2>
                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                        <?php wp_nonce_field( 'tc_redirects_manage' ); ?>
                        <input type="hidden" name="action" value="tc_redirects_manage">
                        <input type="hidden" name="task" value="save">
                        <input type="hidden" name="redirect_id" value="<?php echo esc_attr( (string) ( $editing ? $editing->id : 0 ) ); ?>">
                        <p><label for="tc-rd-origin">URL origen</label>
                            <input id="tc-rd-origin" class="large-text" type="text" name="origin_url" maxlength="255" required
                                value="<?php echo esc_attr( $editing ? $editing->origin_url : '' ); ?>" placeholder="/categoria-antigua"></p>
                        <p><label for="tc-rd-target">URL destino</label>
                            <input id="tc-rd-target" class="large-text" type="text" name="target_url" maxlength="255" required
                                value="<?php echo esc_attr( $editing ? $editing->target_url : '' ); ?>" placeholder="/categoria-nueva"></p>
                        <p><label for="tc-rd-code">Tipo</label>
                            <select id="tc-rd-code" name="status_code">
                                <option value="301" <?php selected( $editing ? $editing->status_code : 301, 301 ); ?>>301 permanente</option>
                                <option value="302" <?php selected( $editing ? $editing->status_code : 301, 302 ); ?>>302 temporal</option>
                            </select></p>
                        <p><button type="submit" class="button button-primary"><?php echo $editing ? 'Guardar cambios' : 'Crear redirección'; ?></button>
                            <?php if ( $editing ) : ?><a class="button" href="<?php echo esc_url( self::url() ); ?>">Cancelar edición</a><?php endif; ?></p>
                    </form>
                    <p class="description">Rutas del propio sitio. Se bloquean ciclos, orígenes repetidos y destinos iguales al origen.</p>
                </section>
                <section class="tc-rd-panel">
                    <h2>Importación y exportación JSON</h2>
                    <p>Las copias de seguridad contienen todas las reglas y sus contadores. El informe añade diagnósticos estructurales.</p>
                    <div class="tc-rd-button-row">
                        <?php foreach ( array( 'backup' => 'Exportar copia JSON', 'analysis' => 'Exportar análisis JSON' ) as $mode => $label ) : ?>
                            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                                <?php wp_nonce_field( 'tc_redirects_export' ); ?>
                                <input type="hidden" name="action" value="tc_redirects_export">
                                <input type="hidden" name="mode" value="<?php echo esc_attr( $mode ); ?>">
                                <button class="button" type="submit"><?php echo esc_html( $label ); ?></button>
                            </form>
                        <?php endforeach; ?>
                    </div>
                    <form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                        <?php wp_nonce_field( 'tc_redirects_manage' ); ?>
                        <input type="hidden" name="action" value="tc_redirects_manage">
                        <input type="hidden" name="task" value="import">
                        <p><input type="file" name="redirects_json" accept=".json,application/json" required></p>
                        <p><label><input type="checkbox" name="preserve_activity" value="1"> Conservar hits y último uso</label></p>
                        <p><button type="submit" class="button">Importar solo reglas nuevas</button></p>
                        <p class="description">No sobrescribe reglas existentes. Rechaza orígenes duplicados, ciclos y URLs inválidas. Máximo 25 MB/50.000 reglas.</p>
                    </form>
                </section>
            </div>

            <section class="tc-rd-panel">
                <h2>Redirecciones</h2>
                <nav class="tc-rd-filters" aria-label="Filtrar redirecciones">
                    <?php foreach ( array( 'all' => 'Todas', 'issues' => 'Revisar', 'used' => 'Con uso', 'unused' => 'Sin uso' ) as $key => $label ) : ?>
                        <a class="button <?php echo $filter === $key ? 'button-primary' : ''; ?>"
                            href="<?php echo esc_url( self::url( array( 'tc_filter' => $key ) ) ); ?>"><?php echo esc_html( $label ); ?></a>
                    <?php endforeach; ?>
                </nav>
                <form method="get" class="tc-rd-search">
                    <input type="hidden" name="page" value="<?php echo esc_attr( Plugin::SLUG ); ?>">
                    <input type="hidden" name="tc_filter" value="<?php echo esc_attr( $filter ); ?>">
                    <input type="search" name="s" value="<?php echo esc_attr( $needle ); ?>" placeholder="Buscar ID, origen o destino">
                    <button type="submit" class="button">Buscar</button>
                </form>
                <div class="tc-rd-table"><table class="widefat striped">
                    <thead><tr><th>ID</th><th>Origen</th><th>Destino</th><th>Diagnóstico</th><th>Tipo</th><th>Hits</th><th>Último uso</th><th>Acciones</th></tr></thead>
                    <tbody>
                    <?php if ( ! $subset ) : ?>
                        <tr><td colspan="8">No se han encontrado reglas para este filtro.</td></tr>
                    <?php endif; ?>
                    <?php foreach ( $subset as $row ) :
                        $detail = (array) ( $details[ absint( $row->id ) ] ?? array() );
                        $target = Store::absolute_target( $row->target_url );
                        $origin = home_url( (string) $row->origin_url );
                        ?>
                        <tr>
                            <td>#<?php echo esc_html( (string) $row->id ); ?></td>
                            <td><code><?php echo esc_html( $row->origin_url ); ?></code></td>
                            <td><code><?php echo esc_html( $row->target_url ); ?></code></td>
                            <td><span class="tc-rd-badge tc-rd-<?php echo esc_attr( $detail['severity'] ?? 'ok' ); ?>"><?php echo esc_html( $detail['severity'] ?? 'ok' ); ?></span>
                                <?php foreach ( array_slice( (array) ( $detail['issues'] ?? array() ), 0, 3 ) as $issue ) : ?>
                                    <div class="tc-rd-issue"><?php echo esc_html( $issue ); ?></div>
                                <?php endforeach; ?></td>
                            <td><?php echo esc_html( (string) $row->status_code ); ?></td>
                            <td><?php echo esc_html( number_format_i18n( absint( $row->hits ) ) ); ?></td>
                            <td><?php echo esc_html( $row->last_hit ?: '—' ); ?></td>
                            <td><div class="tc-rd-actions">
                                <a href="<?php echo esc_url( self::url( array( 'edit' => absint( $row->id ) ) ) ); ?>">Editar</a>
                                <a href="<?php echo esc_url( $origin ); ?>" target="_blank" rel="noopener">Probar</a>
                                <?php if ( ! is_wp_error( $target ) ) : ?>
                                    <a href="<?php echo esc_url( $target ); ?>" target="_blank" rel="noopener">Destino</a>
                                <?php endif; ?>
                                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('¿Eliminar esta regla de redirección?');">
                                    <?php wp_nonce_field( 'tc_redirects_manage' ); ?>
                                    <input type="hidden" name="action" value="tc_redirects_manage">
                                    <input type="hidden" name="task" value="delete">
                                    <input type="hidden" name="redirect_id" value="<?php echo esc_attr( (string) $row->id ); ?>">
                                    <button type="submit" class="button-link-delete">Borrar</button>
                                </form></div></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table></div>
                <?php
                echo wp_kses_post(
                    paginate_links(
                        array(
                            'base' => esc_url_raw( self::url( array( 'tc_filter' => $filter, 's' => $needle, 'paged' => '%#%' ) ) ),
                            'format' => '',
                            'current' => $page,
                            'total' => $max_page,
                            'type' => 'plain',
                        )
                    )
                );
                ?>
            </section>
            <p class="description">«Correcto» significa sin anomalías estructurales conocidas: no confirma por HTTP que el destino responda. Las pruebas abren la URL en otra pestaña.</p>
        </div>
        <?php
    }

    private static function tabs( $active ) {
        echo '<nav class="nav-tab-wrapper" aria-label="Secciones de Redirects">';
        foreach ( array( 'redirects' => 'Redirects', 'auto-test' => __( 'Autocheck', 'taxocommerce-redirects' ), 'user-guide' => 'User Guide' ) as $tab => $label ) {
            $url = 'redirects' === $tab ? self::url() : self::url( array( 'tc_rd_tab' => $tab ) );
            echo '<a class="nav-tab ' . ( $active === $tab ? 'nav-tab-active' : '' ) . '" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
        }
        echo '</nav>';
    }

    private static function kpi( $number, $title ) {
        echo '<div class="tc-rd-kpi"><strong>' . esc_html( number_format_i18n( absint( $number ) ) )
            . '</strong><span>' . esc_html( $title ) . '</span></div>';
    }

    public static function handle_manage() {
        self::can_manage();
        check_admin_referer( 'tc_redirects_manage' );
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified above.
        $task = isset( $_POST['task'] ) ? sanitize_key( wp_unslash( $_POST['task'] ) ) : '';
        if ( 'save' === $task ) {
            $id = isset( $_POST['redirect_id'] ) ? absint( $_POST['redirect_id'] ) : 0;
            $origin = isset( $_POST['origin_url'] ) ? sanitize_text_field( wp_unslash( $_POST['origin_url'] ) ) : '';
            $target = isset( $_POST['target_url'] ) ? sanitize_text_field( wp_unslash( $_POST['target_url'] ) ) : '';
            $status = isset( $_POST['status_code'] ) ? absint( $_POST['status_code'] ) : 301;
            $result = Store::save( $origin, $target, $status, $id );
            self::success( is_wp_error( $result ) ? $result->get_error_message() : 'Redirección guardada.', is_wp_error( $result ) );
        }
        if ( 'delete' === $task ) {
            $id = isset( $_POST['redirect_id'] ) ? absint( $_POST['redirect_id'] ) : 0;
            $ok = Store::remove( $id );
            self::success( $ok ? 'Regla eliminada.' : 'No se pudo eliminar la regla.', ! $ok );
        }
        if ( 'import' === $task ) {
            $file = array(
                'name'     => isset( $_FILES['redirects_json']['name'] )
                    ? sanitize_file_name( wp_unslash( $_FILES['redirects_json']['name'] ) )
                    : '',
                'type'     => isset( $_FILES['redirects_json']['type'] )
                    ? sanitize_mime_type( wp_unslash( $_FILES['redirects_json']['type'] ) )
                    : '',
                'tmp_name' => isset( $_FILES['redirects_json']['tmp_name'] )
                    ? sanitize_text_field( wp_unslash( $_FILES['redirects_json']['tmp_name'] ) )
                    : '',
                'error'    => isset( $_FILES['redirects_json']['error'] )
                    ? absint( $_FILES['redirects_json']['error'] )
                    : UPLOAD_ERR_NO_FILE,
                'size'     => isset( $_FILES['redirects_json']['size'] )
                    ? absint( $_FILES['redirects_json']['size'] )
                    : 0,
            );
            $result = self::import( $file, ! empty( $_POST['preserve_activity'] ) );
            self::success( is_wp_error( $result ) ? $result->get_error_message() : sprintf(
                'Importación finalizada: %d creadas, %d repetidas, %d inválidas, %d ciclos, %d errores SQL.',
                $result['inserted'], $result['existing'], $result['invalid'], $result['cycles'], $result['errors']
            ), is_wp_error( $result ) || ( is_array( $result ) && $result['errors'] > 0 ) );
        }
        self::success( 'Acción de administración no reconocida.', true );
    }

    public static function handle_export() {
        self::can_manage();
        check_admin_referer( 'tc_redirects_export' );
        $mode = isset( $_POST['mode'] ) ? sanitize_key( wp_unslash( $_POST['mode'] ) ) : 'backup';
        $mode = 'analysis' === $mode ? 'analysis' : 'backup';
        $rows = Store::rows();
        $rules = array();
        foreach ( $rows as $row ) {
            $rules[] = array(
                'origin_url' => (string) $row->origin_url,
                'target_url' => (string) $row->target_url,
                'status_code' => absint( $row->status_code ),
                'hits' => absint( $row->hits ),
                'last_hit' => (string) $row->last_hit,
            );
        }
        $payload = array(
            'schema' => 'taxocommerce-redirects-v1',
            'export_type' => $mode,
            'exported_gmt' => current_time( 'mysql', true ),
            'redirects' => $rules,
        );
        if ( 'analysis' === $mode ) {
            $payload['analysis'] = Store::diagnostics( $rows );
        }
        nocache_headers();
        header( 'Content-Type: application/json; charset=utf-8' );
        header( 'X-Content-Type-Options: nosniff' );
        header( 'Content-Disposition: attachment; filename="taxocommerce_redirects_' . $mode . '_' . gmdate( 'Ymd_His' ) . '.json"' );
        echo wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE );
        exit;
    }

    private static function map_cycle( $map, $origin, $destination ) {
        $seen = array( $origin => true );
        $cursor = $destination;
        for ( $i = 0; $i <= count( $map ); $i++ ) {
            if ( isset( $seen[ $cursor ] ) ) {
                return true;
            }
            if ( ! isset( $map[ $cursor ] ) ) {
                return false;
            }
            $seen[ $cursor ] = true;
            $cursor = $map[ $cursor ];
        }
        return true;
    }

    private static function import( $file, $preserve_activity ) {
        global $wpdb;
        $size = isset( $file['size'] ) ? absint( $file['size'] ) : 0;
        $error = isset( $file['error'] ) ? absint( $file['error'] ) : UPLOAD_ERR_NO_FILE;
        $tmp = isset( $file['tmp_name'] ) ? (string) $file['tmp_name'] : '';
        if ( UPLOAD_ERR_OK !== $error || $size < 1 || $size > Store::MAX_IMPORT_BYTES || ! is_uploaded_file( $tmp ) ) {
            return new \WP_Error( 'tc_redirects_upload', 'JSON no válido, vacío o mayor de 25 MB.' );
        }
        $raw = file_get_contents( $tmp );
        $json = is_string( $raw ) ? json_decode( $raw, true ) : null;
        if ( ! is_array( $json ) || ! isset( $json['redirects'] ) || ! is_array( $json['redirects'] ) ) {
            return new \WP_Error( 'tc_redirects_json', 'El JSON debe contener un array redirects.' );
        }
        if ( count( $json['redirects'] ) > Store::MAX_ROWS ) {
            return new \WP_Error( 'tc_redirects_size', 'El JSON supera las 50.000 reglas.' );
        }
        if ( ! Store::table_exists() ) {
            return new \WP_Error( 'tc_redirects_missing_table', 'No existe la tabla de redirecciones.' );
        }
        $known = array();
        $map = array();
        foreach ( Store::rows() as $row ) {
            $origin = Store::effective_path( $row->origin_url );
            $dest = Store::effective_path( $row->target_url );
            if ( null === $origin ) {
                continue;
            }
            $known[ $origin ] = true;
            if ( null !== $dest && ! isset( $map[ $origin ] ) ) {
                $map[ $origin ] = $dest;
            }
        }
        $counts = array( 'inserted' => 0, 'existing' => 0, 'invalid' => 0, 'cycles' => 0, 'errors' => 0 );
        foreach ( $json['redirects'] as $item ) {
            if ( ! is_array( $item ) ) {
                $counts['invalid']++;
                continue;
            }
            $from = Store::normalize_origin( $item['origin_url'] ?? '' );
            $to = Store::normalize_target( $item['target_url'] ?? '' );
            if ( is_wp_error( $from ) || is_wp_error( $to ) ) {
                $counts['invalid']++;
                continue;
            }
            $target = Store::effective_path( $to );
            if ( null === $target || $from === $target ) {
                $counts['invalid']++;
                continue;
            }
            if ( isset( $known[ $from ] ) ) {
                $counts['existing']++;
                continue;
            }
            if ( self::map_cycle( $map, $from, $target ) ) {
                $counts['cycles']++;
                continue;
            }
            $code = absint( $item['status_code'] ?? 301 );
            $code = in_array( $code, array( 301, 302 ), true ) ? $code : 301;
            $hit = $preserve_activity ? absint( $item['hits'] ?? 0 ) : 0;
            $last = null;
            if ( $preserve_activity && isset( $item['last_hit'] ) && preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', (string) $item['last_hit'] ) ) {
                $last = (string) $item['last_hit'];
            }
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Import adds only validated redirect rows after duplicate and cycle checks.
            $ok = $wpdb->insert(
                Store::table(),
                array(
                    'origin_url' => $from,
                    'target_url' => $to,
                    'status_code' => $code,
                    'hits' => $hit,
                    'last_hit' => $last,
                    'created_at' => current_time( 'mysql', true ),
                    'updated_at' => current_time( 'mysql', true ),
                ),
                array( '%s', '%s', '%d', '%d', '%s', '%s', '%s' )
            );
            if ( false === $ok ) {
                $counts['errors']++;
                continue;
            }
            $counts['inserted']++;
            $known[ $from ] = true;
            $map[ $from ] = $target;
        }
        return $counts;
    }
}
