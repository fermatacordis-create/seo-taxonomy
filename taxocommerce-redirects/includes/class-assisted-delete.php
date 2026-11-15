<?php
/**
 * Shared, explicit confirmation flow for four native editors and four Core
 * editors. All irreversible actions happen here after nonce/capability checks.
 *
 * WordPress post/page/product => trash first. product_cat => permanent removal
 * only when empty, childless and not protected by legacy/Core links.
 */

namespace TaxoCommerce\Redirects;

defined( 'ABSPATH' ) || exit;

final class Assisted_Delete {
    const ALLOWED = array(
        'post' => 'Entrada',
        'page' => 'Página',
        'product' => 'Producto',
        'product_cat' => 'Categoría de productos',
    );

    public static function register() {
        add_action( 'admin_menu', array( __CLASS__, 'confirmation_page' ), 45 );
        add_action( 'admin_init', array( __CLASS__, 'intercept_native' ), 3 );
        add_action( 'admin_post_tc_redirects_assisted_delete', array( __CLASS__, 'handle_delete' ) );
        add_action( 'untrashed_post', array( __CLASS__, 'restore_original_route' ), 10, 1 );
        add_action( 'admin_post_taxocommerce_core_save_content', array( __CLASS__, 'intercept_core_trash' ), 0 );
        add_filter( 'post_row_actions', array( __CLASS__, 'post_row_actions' ), 20, 2 );
        add_filter( 'page_row_actions', array( __CLASS__, 'post_row_actions' ), 20, 2 );
        add_filter( 'product_cat_row_actions', array( __CLASS__, 'term_row_actions' ), 20, 2 );
        add_action( 'post_submitbox_misc_actions', array( __CLASS__, 'post_editor_button' ) );
        add_action( 'product_cat_edit_form_fields', array( __CLASS__, 'term_editor_button' ) );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_core' ), 30 );
        add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'enqueue_block_editor' ) );
    }

    public static function confirmation_page() {
        add_submenu_page(
            null,
            __( 'Eliminar contenido · TaxoCommerce Redirects', 'taxocommerce-redirects' ),
            __( 'Eliminar contenido', 'taxocommerce-redirects' ),
            'read',
            Plugin::CONFIRM_SLUG,
            array( __CLASS__, 'render_confirmation' )
        );
    }

    public static function confirmation_url( $type, $id ) {
        return add_query_arg(
            array(
                'page' => Plugin::CONFIRM_SLUG,
                'tc_type' => sanitize_key( (string) $type ),
                'tc_id' => absint( $id ),
            ),
            admin_url( 'admin.php' )
        );
    }

    public static function allowed_object( $type, $id ) {
        $id = absint( $id );
        if ( ! $id || ! isset( self::ALLOWED[ $type ] ) ) {
            return false;
        }
        if ( 'product_cat' === $type ) {
            $term = get_term( $id, 'product_cat' );
            $tax = get_taxonomy( 'product_cat' );
            return $tax && $term instanceof \WP_Term && current_user_can( $tax->cap->delete_terms );
        }
        return get_post_type( $id ) === $type && current_user_can( 'delete_post', $id );
    }

    private static function old_category_guard() {
        return false !== has_action( 'pre_delete_term', 'seo_product_category_pre_delete_term' );
    }

    private static function object_data( $type, $id ) {
        if ( ! self::allowed_object( $type, $id ) ) {
            return new \WP_Error( 'tc_redirects_forbidden', 'El elemento no existe o no tienes permiso para borrarlo.' );
        }
        if ( 'product_cat' === $type ) {
            $term = get_term( $id, 'product_cat' );
            $url = get_term_link( $term );
            return is_wp_error( $url ) ? $url : array(
                'title' => (string) $term->name,
                'origin' => (string) $url,
                'status' => 'publish',
            );
        }
        $post = get_post( $id );
        $url = get_permalink( $id );
        return ! $url ? new \WP_Error( 'tc_redirects_permalink', 'No se encuentra la URL original.' ) : array(
            'title' => (string) $post->post_title,
            'origin' => (string) $url,
            'status' => (string) $post->post_status,
        );
    }

    public static function intercept_native() {
        $request_method = isset( $_SERVER['REQUEST_METHOD'] )
            ? strtoupper( sanitize_key( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) )
            : '';
        if ( 'GET' !== $request_method ) {
            return;
        }
        global $pagenow;
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- No mutation. Confirmation POST requires own nonce.
        $action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';
        if ( 'post.php' === $pagenow && in_array( $action, array( 'trash', 'delete', 'delete-post' ), true ) ) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- No mutation.
            $id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
            $type = get_post_type( $id );
            if ( is_string( $type ) && self::allowed_object( $type, $id ) ) {
                wp_safe_redirect( self::confirmation_url( $type, $id ) );
                exit;
            }
        }
        if ( 'edit-tags.php' === $pagenow && 'delete' === $action ) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- No mutation.
            $tax = isset( $_GET['taxonomy'] ) ? sanitize_key( wp_unslash( $_GET['taxonomy'] ) ) : '';
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- No mutation.
            $id = isset( $_GET['tag_ID'] ) ? absint( $_GET['tag_ID'] ) : 0;
            if ( 'product_cat' === $tax && self::allowed_object( 'product_cat', $id ) ) {
                wp_safe_redirect( self::confirmation_url( 'product_cat', $id ) );
                exit;
            }
        }
    }

    public static function intercept_core_trash() {
        // Core's POST save_content() normally accepts status=trash directly.
        // Intercept ONLY that path; ordinary Core saves are never touched.
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified with wp_verify_nonce below.
        $status = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : '';
        if ( 'trash' !== $status ) {
            return;
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified below.
        $nonce = isset( $_POST['taxocommerce_core_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['taxocommerce_core_nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'taxocommerce_core_save_content' ) ) {
            return;
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified above.
        $type = isset( $_POST['content_type'] ) ? sanitize_key( wp_unslash( $_POST['content_type'] ) ) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified above.
        $id = isset( $_POST['item_id'] ) ? absint( $_POST['item_id'] ) : 0;
        $map = array( 'products' => 'product', 'posts' => 'post', 'pages' => 'page' );
        if ( isset( $map[ $type ] ) && self::allowed_object( $map[ $type ], $id ) ) {
            wp_safe_redirect( self::confirmation_url( $map[ $type ], $id ), 303 );
            exit;
        }
    }

    public static function post_row_actions( $actions, $post ) {
        if ( ! $post instanceof \WP_Post || ! self::allowed_object( $post->post_type, $post->ID ) ) {
            return $actions;
        }
        $link = '<a href="' . esc_url( self::confirmation_url( $post->post_type, $post->ID ) ) . '">Eliminar con Redirects</a>';
        if ( isset( $actions['trash'] ) ) {
            $actions['trash'] = $link;
        } elseif ( isset( $actions['delete'] ) ) {
            $actions['delete'] = $link;
        }
        if ( ! isset( $actions['trash'] ) && ! isset( $actions['delete'] ) ) {
            $actions['tc_redirects_delete'] = $link;
        }
        return $actions;
    }

    public static function term_row_actions( $actions, $term ) {
        if ( $term instanceof \WP_Term && self::allowed_object( 'product_cat', $term->term_id ) ) {
            $link = '<a href="' . esc_url( self::confirmation_url( 'product_cat', $term->term_id ) ) . '">Eliminar con Redirects</a>';
            if ( isset( $actions['delete'] ) ) {
                $actions['delete'] = $link;
            } else {
                $actions['tc_redirects_delete'] = $link;
            }
        }
        return $actions;
    }

    public static function post_editor_button() {
        global $post;
        if ( $post instanceof \WP_Post && self::allowed_object( $post->post_type, $post->ID ) ) {
            echo '<div class="misc-pub-section"><a class="submitdelete" href="'
                . esc_url( self::confirmation_url( $post->post_type, $post->ID ) )
                . '">Eliminar con TaxoCommerce Redirects</a></div>';
        }
    }

    public static function term_editor_button( $term ) {
        if ( $term instanceof \WP_Term && self::allowed_object( 'product_cat', $term->term_id ) ) {
            echo '<tr class="form-field tc-redirects-delete-field"><th scope="row">Eliminación asistida</th><td>'
                . '<a class="button button-link-delete" href="'
                . esc_url( self::confirmation_url( 'product_cat', $term->term_id ) )
                . '">Eliminar con Redirects</a><p class="description">Elegir redirección 301 o eliminación sin redirección.</p></td></tr>';
        }
    }

    public static function enqueue_core() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only identifies the Core screen.
        $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
        if ( 'taxocommerce-core-content' !== $page || ! class_exists( 'TaxoCommerce_Core_Content_Manager' ) ) {
            return;
        }
        wp_enqueue_script(
            'taxocommerce-redirects-core-ui',
            plugins_url( 'assets/core.js', dirname( __DIR__ ) . '/taxocommerce-redirects.php' ),
            array(), Plugin::VERSION, true
        );
        wp_localize_script(
            'taxocommerce-redirects-core-ui',
            'tcRedirectsCore',
            array(
                'confirmBase' => admin_url( 'admin.php?page=' . Plugin::CONFIRM_SLUG ),
                'typeMap' => array( 'posts' => 'post', 'pages' => 'page', 'products' => 'product', 'categories' => 'product_cat' ),
            )
        );
    }

    public static function enqueue_block_editor() {
        global $post;
        if ( ! $post instanceof \WP_Post || ! self::allowed_object( $post->post_type, $post->ID ) ) {
            return;
        }
        wp_enqueue_script(
            'taxocommerce-redirects-block-button',
            plugins_url( 'assets/block-editor.js', dirname( __DIR__ ) . '/taxocommerce-redirects.php' ),
            array( 'wp-plugins', 'wp-element', 'wp-edit-post', 'wp-components', 'wp-api-fetch' ),
            Plugin::VERSION,
            true
        );
        $post_type_obj = get_post_type_object( $post->post_type );
        $rest_base = $post_type_obj && ! empty( $post_type_obj->rest_base )
            ? sanitize_key( $post_type_obj->rest_base )
            : ( 'page' === $post->post_type ? 'pages' : ( 'product' === $post->post_type ? 'products' : 'posts' ) );
        $namespace = $post_type_obj && ! empty( $post_type_obj->rest_namespace )
            ? trim( (string) $post_type_obj->rest_namespace, '/' ) : 'wp/v2';
        wp_localize_script(
            'taxocommerce-redirects-block-button',
            'tcRedirectsBlockEditor',
            array(
                'confirmUrl' => self::confirmation_url( $post->post_type, $post->ID ),
                'restIds' => array(
                    '/' . $namespace . '/' . $rest_base . '/' . absint( $post->ID ),
                    '/wc/v3/products/' . absint( $post->ID ),
                ),
            )
        );
    }

    private static function category_preflight( $id ) {
        if ( self::old_category_guard() ) {
            return new \WP_Error(
                'tc_redirects_legacy_guard',
                'El plugin antiguo sigue controlando los borrados de categorias y exige redireccion al Hub secundario. Para evitar conflictos, realiza este borrado con el gestor antiguo mientras siga activo.'
            );
        }
        if ( absint( get_option( 'default_product_cat' ) ) === $id ) {
            return new \WP_Error( 'tc_redirects_default_cat', 'No puedes borrar la categoria predeterminada de WooCommerce.' );
        }
        $children = get_terms(
            array( 'taxonomy' => 'product_cat', 'parent' => $id, 'hide_empty' => false, 'number' => 1, 'fields' => 'ids' )
        );
        if ( is_wp_error( $children ) ) {
            return $children;
        }
        if ( $children ) {
            return new \WP_Error( 'tc_redirects_children', 'La categoria contiene subcategorias. Reubicalas antes de eliminar.' );
        }
        // Bounded native check: never materialize tens of thousands of
        // product IDs merely to reject deletion of a populated category.
        // Include Trash: otherwise a restored product could lose its category.
        $objects = get_posts(
            array(
                'post_type'           => 'product',
                'post_status'         => array_keys( get_post_stati() ),
                'posts_per_page'      => 1,
                'fields'              => 'ids',
                'no_found_rows'       => true,
                'suppress_filters'    => false,
                // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Indexed taxonomy preflight is mandatory before category deletion, limited to one product ID.
                'tax_query'           => array(
                    array(
                        'taxonomy'         => 'product_cat',
                        'field'            => 'term_id',
                        'terms'            => array( $id ),
                        'include_children' => false,
                    ),
                ),
            )
        );
        if ( $objects ) {
            return new \WP_Error( 'tc_redirects_products', 'La categoria contiene productos, incluso en papelera. Reasignalos antes de eliminarla.' );
        }
        $core_parents = get_term_meta( $id, '_taxocommerce_core_parent_hub_secondary', false );
        if ( $core_parents && array_filter( array_map( 'absint', (array) $core_parents ) ) ) {
            return new \WP_Error( 'tc_redirects_core_links', 'La categoria tiene un Hub secundario asociado. Desvincula la relacion en Core antes de eliminarla.' );
        }
        return true;
    }

    /**
     * Suggest the most specific WooCommerce category that remains populated after
     * this product is removed. Never register a redirect without explicit approval.
     *
     * @param int $product_id Published product ID.
     * @return string Safe internal target or empty string.
     */
    private static function suggested_product_category( $product_id ) {
        if ( ! taxonomy_exists( 'product_cat' ) ) {
            return '';
        }
        $terms = get_the_terms( $product_id, 'product_cat' );
        if ( ! is_array( $terms ) ) {
            return '';
        }
        $default_category = absint( get_option( 'default_product_cat', 0 ) );
        $choices = array();
        foreach ( $terms as $term ) {
            if ( ! $term instanceof \WP_Term || $term->term_id === $default_category || (int) $term->count <= 1 ) {
                continue;
            }
            $url = get_term_link( $term );
            if ( is_wp_error( $url ) ) {
                continue;
            }
            $safe = Store::normalize_target( $url );
            if ( is_wp_error( $safe ) || '/' === $safe ) {
                continue;
            }
            $choices[] = array(
                'path' => $safe,
                'depth' => count( get_ancestors( $term->term_id, 'product_cat', 'taxonomy' ) ),
                'count' => (int) $term->count,
                'id' => (int) $term->term_id,
            );
        }
        usort( $choices, static function ( $a, $b ) {
            if ( $a['depth'] !== $b['depth'] ) {
                return $b['depth'] <=> $a['depth'];
            }
            if ( $a['count'] !== $b['count'] ) {
                return $b['count'] <=> $a['count'];
            }
            return $a['id'] <=> $b['id'];
        } );
        return $choices ? (string) $choices[0]['path'] : '';
    }

    public static function render_confirmation() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only confirmation screen.
        $type = isset( $_GET['tc_type'] ) ? sanitize_key( wp_unslash( $_GET['tc_type'] ) ) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only confirmation screen.
        $id = isset( $_GET['tc_id'] ) ? absint( $_GET['tc_id'] ) : 0;
        $data = self::object_data( $type, $id );
        if ( is_wp_error( $data ) ) {
            wp_die( esc_html( $data->get_error_message() ), '', array( 'response' => 403 ) );
        }
        $can_redirect = 'publish' === $data['status'];
        $warning = 'product_cat' === $type ? self::category_preflight( $id ) : true;
        $permanent = 'trash' === $data['status'];
        $suggested_category = 'product' === $type && $can_redirect ? self::suggested_product_category( $id ) : '';
        ?>
        <div class="wrap tc-rd tc-rd-confirm">
            <h1>Eliminar <?php echo esc_html( self::ALLOWED[ $type ] ); ?></h1>
            <p><strong><?php echo esc_html( $data['title'] ); ?></strong> · ID <?php echo esc_html( (string) $id ); ?></p>
            <p>URL original: <code><?php echo esc_html( $data['origin'] ); ?></code></p>
            <div class="tc-rd-panel">
                <h2>¿Quieres añadir una redirección antes de eliminar?</h2>
                <?php if ( 'product_cat' !== $type ) : ?>
                    <?php if ( $permanent ) : ?>
                        <p><strong>Atención: el contenido ya está en la papelera y esta operación lo eliminaría definitivamente.</strong></p>
                    <?php else : ?>
                        <p>Se moverá el contenido a la papelera. Este paso no lo elimina definitivamente.</p>
                    <?php endif; ?>
                <?php else : ?>
                    <p>Las categorías de productos no tienen papelera. Este borrado es definitivo.</p>
                <?php endif; ?>
                <?php if ( is_wp_error( $warning ) ) : ?>
                    <div class="notice notice-warning inline"><p><?php echo esc_html( $warning->get_error_message() ); ?></p></div>
                <?php endif; ?>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="tc-rd-delete-form">
                    <?php wp_nonce_field( 'tc_redirects_delete_' . $type . '_' . $id ); ?>
                    <input type="hidden" name="action" value="tc_redirects_assisted_delete">
                    <input type="hidden" name="tc_type" value="<?php echo esc_attr( $type ); ?>">
                    <input type="hidden" name="tc_id" value="<?php echo esc_attr( (string) $id ); ?>">
                    <input type="hidden" name="origin_snapshot" value="<?php echo esc_attr( $data['origin'] ); ?>">
                    <?php if ( $can_redirect ) : ?>
                        <label class="tc-rd-choice"><input type="radio" name="mode" value="redirect" checked> Eliminar y registrar redirección 301</label>
                        <div class="tc-rd-destination">
                            <label for="tc-rd-delete-target">Destino del redirect</label>
                            <input id="tc-rd-delete-target" type="text" class="large-text" name="target_url" placeholder="/categoria-destino/" maxlength="255" value="<?php echo esc_attr( $suggested_category ); ?>">
                            <?php if ( '' !== $suggested_category ) : ?>
                                <p class="description"><?php esc_html_e( 'Categoría WooCommerce sugerida: contiene otros productos y es la más específica disponible. Revisa que sea adecuada antes de confirmar; puedes sustituirla por un producto equivalente.', 'taxocommerce-redirects' ); ?></p>
                            <?php else : ?>
                                <p class="description"><?php esc_html_e( 'No se ha identificado una categoría específica con otros productos. Selecciona un destino realmente equivalente o elimina sin redirección.', 'taxocommerce-redirects' ); ?></p>
                            <?php endif; ?>
                            <p class="description">Destino interno del mismo sitio. No se genera ningún redirect hasta que confirmes.</p>
                        </div>
                    <?php endif; ?>
                    <label class="tc-rd-choice"><input type="radio" name="mode" value="delete" <?php checked( ! $can_redirect ); ?>> Eliminar sin redirección</label>
                    <p class="description">Al borrar sin redirección, la antigua URL podrá responder 404. Si ya hay un redirect para ese origen, debes gestionarlo primero.</p>
                    <?php if ( $permanent ) : ?>
                        <p><label><input type="checkbox" name="confirm_permanent" value="1" required> Confirmo que deseo eliminar definitivamente este elemento de la papelera.</label></p>
                    <?php endif; ?>
                    <div class="tc-rd-button-row">
                        <button type="submit" class="button button-primary" <?php disabled( is_wp_error( $warning ) ); ?>><?php echo $permanent ? 'Eliminar definitivamente' : 'Confirmar eliminación'; ?></button>
                        <a class="button" href="<?php echo esc_url( wp_get_referer() ?: admin_url( 'index.php' ) ); ?>">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
        <?php
    }

    /**
     * Restore the original public URL safely: a 301 created by this form must
     * not keep intercepting a post or product restored from WordPress Trash.
     * Never remove rules which have since been edited by an administrator.
     */
    public static function restore_original_route( $post_id ) {
        $post_id = absint( $post_id );
        $marker = get_post_meta( $post_id, '_tc_redirects_assisted_rule', true );
        if ( ! is_array( $marker ) ) {
            return;
        }
        $id = absint( $marker['id'] ?? 0 );
        $origin = (string) ( $marker['origin'] ?? '' );
        $target = (string) ( $marker['target'] ?? '' );
        if ( $id && '' !== $origin && Store::table_exists() ) {
            $rule = Store::get( $id );
            $post = get_post( $post_id );
            if ( $rule && $post instanceof \WP_Post
                && (string) $rule->target_url === $target
                && Store::effective_path( $rule->origin_url ) === Store::effective_path( $origin ) ) {
                Store::remove( $id );
            }
        }
        delete_post_meta( $post_id, '_tc_redirects_assisted_rule' );
    }

    public static function handle_delete() {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified below.
        $type = isset( $_POST['tc_type'] ) ? sanitize_key( wp_unslash( $_POST['tc_type'] ) ) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified below.
        $id = isset( $_POST['tc_id'] ) ? absint( $_POST['tc_id'] ) : 0;
        if ( ! self::allowed_object( $type, $id ) ) {
            wp_die( 'No existe el elemento o faltan permisos de eliminación.', '', array( 'response' => 403 ) );
        }
        check_admin_referer( 'tc_redirects_delete_' . $type . '_' . $id );

        $data = self::object_data( $type, $id );
        if ( is_wp_error( $data ) ) {
            wp_die( esc_html( $data->get_error_message() ), '', array( 'response' => 409 ) );
        }
        // A changed slug between GET and POST must not silently redirect the
        // wrong URL; confirmation is bound to the originally displayed URL.
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified above.
        $snapshot = isset( $_POST['origin_snapshot'] ) ? esc_url_raw( wp_unslash( $_POST['origin_snapshot'] ) ) : '';
        if ( $snapshot !== $data['origin'] ) {
            wp_die( 'La URL original ha cambiado. Abre de nuevo el formulario de confirmacion.', '', array( 'response' => 409 ) );
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified above.
        $mode = isset( $_POST['mode'] ) ? sanitize_key( wp_unslash( $_POST['mode'] ) ) : '';
        if ( ! in_array( $mode, array( 'redirect', 'delete' ), true ) ) {
            wp_die( 'Selecciona una de las dos opciones de borrado.', '', array( 'response' => 400 ) );
        }
        if ( 'product_cat' === $type ) {
            $preflight = self::category_preflight( $id );
            if ( is_wp_error( $preflight ) ) {
                wp_die( esc_html( $preflight->get_error_message() ), '', array( 'response' => 409 ) );
            }
        }

        // A trashed object can already have a redirect created on the first
        // trash action. Its permanent deletion must NOT remove/block that rule.
        // For ordinary deletion, only touch the redirect table if this is a
        // currently public URL. No-redirect deletion never requires permalink
        // rewrite rules or a valid public-facing origin.
        $origin = null;
        if ( 'publish' === $data['status'] || 'redirect' === $mode ) {
            $origin = Store::normalize_origin( $data['origin'] );
            if ( is_wp_error( $origin ) ) {
                wp_die( esc_html( $origin->get_error_message() ), '', array( 'response' => 409 ) );
            }
            $existing = Store::find_origin( $origin );
            if ( $existing ) {
                wp_die( 'La URL ya tiene una redireccion. Modifica o elimina esa regla antes de continuar con el borrado.', '', array( 'response' => 409 ) );
            }
        }
        if ( 'redirect' === $mode && 'publish' !== $data['status'] ) {
            wp_die( 'Solo se pueden crear redirects al eliminar contenido publicado.', '', array( 'response' => 409 ) );
        }
        if ( 'trash' === $data['status'] && 'product_cat' !== $type && empty( $_POST['confirm_permanent'] ) ) {
            wp_die( 'Confirma expresamente el borrado permanente antes de continuar.', '', array( 'response' => 409 ) );
        }

        $created = 0;
        if ( 'redirect' === $mode ) {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified above.
            $target = isset( $_POST['target_url'] ) ? sanitize_text_field( wp_unslash( $_POST['target_url'] ) ) : '';
            $created = Store::save( $origin, $target, 301 );
            if ( is_wp_error( $created ) ) {
                wp_die( esc_html( $created->get_error_message() ), '', array( 'response' => 409 ) );
            }
        }

        if ( 'product_cat' === $type ) {
            $removed = wp_delete_term( $id, 'product_cat' );
            $success = ! is_wp_error( $removed ) && (bool) $removed;
        } elseif ( 'trash' === $data['status'] ) {
            // An explicit permanent deletion is handled by WordPress itself.
            // WooCommerce product deletion is performed through its CRUD API.
            if ( 'product' === $type && function_exists( 'wc_get_product' ) ) {
                $product = wc_get_product( $id );
                $removed = $product ? $product->delete( true ) : false;
            } else {
                $removed = wp_delete_post( $id, true );
            }
            $success = (bool) $removed;
        } else {
            $removed = wp_trash_post( $id );
            $success = (bool) $removed;
        }

        if ( ! $success ) {
            if ( $created ) {
                Store::remove( $created ); // Roll back only the rule we created.
            }
            $reason = is_wp_error( $removed ) ? $removed->get_error_message() : 'WordPress no ha podido eliminar el contenido.';
            wp_die( esc_html( $reason ), '', array( 'response' => 409 ) );
        }

        // A future "Restore from Trash" must remove this rule only if it
        // still matches the exact redirect created by this operation.
        if ( $created && 'product_cat' !== $type && 'trash' !== $data['status'] ) {
            update_post_meta(
                $id,
                '_tc_redirects_assisted_rule',
                array( 'id' => absint( $created ), 'origin' => $origin, 'target' => Store::get( $created )->target_url )
            );
        }
        update_option(
            'tc_redirects_last_assisted_action',
            array( 'type' => $type, 'id' => $id, 'mode' => $mode, 'time_gmt' => current_time( 'mysql', true ) ),
            false
        );
        wp_safe_redirect( add_query_arg( array( 'page' => Plugin::SLUG, 'tc_deleted' => '1' ), admin_url( 'admin.php' ) ), 303 );
        exit;
    }
}
