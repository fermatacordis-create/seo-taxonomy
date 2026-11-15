<?php
/**
 * TaxoCommerce Core hierarchy manager.
 *
 * @package TaxoCommerceCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages SEO roles and the canonical hierarchy stored in seo_relations.
 */
final class TaxoCommerce_Core_Hierarchy {

	const ROLE_META                  = '_taxocommerce_core_seo_role';
	const PARENT_CLUSTER_META        = '_taxocommerce_core_parent_cluster';
	const PARENT_PRIMARY_META        = '_taxocommerce_core_parent_hub_primary';
	const PARENT_SECONDARY_TERM_META = '_taxocommerce_core_parent_hub_secondary';
	const LEGACY_IMPORT_OPTION       = 'taxocommerce_core_hierarchy_legacy_import_v1';

	/**
	 * Logger.
	 *
	 * @var TaxoCommerce_Core_Logger
	 */
	private $logger;

	/**
	 * Constructor.
	 *
	 * @param TaxoCommerce_Core_Logger $logger Logger.
	 */
	public function __construct( TaxoCommerce_Core_Logger $logger ) {
		$this->logger = $logger;
	}

	/**
	 * Registers admin actions.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'admin_init', array( $this, 'ensure_relations_schema' ), 10 );
		add_action( 'admin_init', array( $this, 'maybe_import_legacy' ), 30 );
		add_action( 'admin_init', array( $this, 'migrate_native_roles' ), 20 );
		add_action( 'admin_init', array( $this, 'reconcile_relations' ), 40 );
		add_action( 'admin_post_taxocommerce_core_replace_hierarchy_relations', array( $this, 'handle_replace_relations' ) );
		add_action( 'wp_ajax_taxocommerce_core_search_hierarchy_categories', array( $this, 'handle_search_relations' ) );
		add_action( 'admin_post_taxocommerce_core_save_page_role', array( $this, 'handle_save_page_role' ) );
		add_action( 'admin_post_taxocommerce_core_add_hierarchy_relation', array( $this, 'handle_add_relation' ) );
		add_action( 'admin_post_taxocommerce_core_delete_hierarchy_relation', array( $this, 'handle_delete_relation' ) );
	}

	/** Existing installations must receive the two canonical tables after upgrades. */
	public function ensure_relations_schema() {
		if ( ! current_user_can( apply_filters( 'taxocommerce_core_manage_capability', 'manage_options' ) ) ) {
			return;
		}
		global $wpdb;
		$nodes = $wpdb->prefix . 'seo_nodes';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Core hierarchy checks canonical node-table availability before operating.
		$nodes_exists = $nodes === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $nodes ) ) );
		if ( ! TaxoCommerce_Core_Hierarchy_Classic::available() || ! $nodes_exists ) {
			TaxoCommerce_Core_Hierarchy_Classic::install();
		}
		// Existing installations also need the country migration, even when both tables already exist.
		TaxoCommerce_Core_Hierarchy_Classic::ensure_country_schema();
	}

	/** Import active legacy page roles into WordPress post metadata once. */
	public function migrate_native_roles() {
		if ( current_user_can( 'manage_options' ) ) {
			TaxoCommerce_Core_Hierarchy_Classic::migrate_page_roles_to_native( $this );
		}
	}

	/**
	 * Reconcile native hierarchy relationships with canonical links.
	 * Existing seo_relations rows are never deleted or overwritten.
	 */
	public function reconcile_relations() {
		if ( current_user_can( 'manage_options' ) ) {
			TaxoCommerce_Core_Hierarchy_Classic::reconcile_role_nodes( $this );
			TaxoCommerce_Core_Hierarchy_Classic::reconcile_native( $this );
		}
	}

	/** Save one parent checkbox group. */
	public function handle_replace_relations() {
		$this->guard();
		$type = isset( $_POST['relation_type'] ) ? sanitize_key( wp_unslash( $_POST['relation_type'] ) ) : '';
		$source = isset( $_POST['source_id'] ) ? absint( wp_unslash( $_POST['source_id'] ) ) : 0;
		check_admin_referer( 'taxocommerce_core_replace_hierarchy_' . $type . '_' . $source, 'taxocommerce_core_nonce' );
		$ids = isset( $_POST['targets'] ) && is_array( $_POST['targets'] )
			? array_map( 'absint', wp_unslash( $_POST['targets'] ) ) : array();
		$result = TaxoCommerce_Core_Hierarchy_Classic::replace( $this, $type, $source, $ids, $this->country_request( true ) );
		$this->redirect_with_result( $result, 'relation', $source );
	}

	/** Search only free product categories via AJAX. */
	public function handle_search_relations() {
		TaxoCommerce_Core_Hierarchy_Classic::handle_search( $this );
	}

	/**
	 * Allowed page roles.
	 *
	 * @return array
	 */
	public function roles() {
		return array(
			'cluster'        => __( 'Cluster', 'taxocommerce-core' ),
			'hub_primary'    => __( 'Hub primario', 'taxocommerce-core' ),
			'hub_secondary'  => __( 'Hub secundario', 'taxocommerce-core' ),
			'landing'        => __( 'Landing', 'taxocommerce-core' ),
			'corporate_page' => __( 'Página corporativa', 'taxocommerce-core' ),
		);
	}

	/**
	 * Returns a page role.
	 *
	 * @param int $page_id Page ID.
	 * @return string
	 */
	public function get_page_role( $page_id ) {
		$role = TaxoCommerce_Core_Hierarchy_Classic::role_node( absint( $page_id ) );
		return isset( $this->roles()[ $role ] ) ? $role : '';
	}

	/**
	 * Assigns one role to a page.
	 *
	 * @param int    $page_id Page ID.
	 * @param string $role    Role, or empty to remove.
	 * @return true|WP_Error
	 */
	public function set_page_role( $page_id, $role ) {
		$page_id = absint( $page_id );
		$role    = sanitize_key( (string) $role );

		if ( ! $page_id || 'page' !== get_post_type( $page_id ) ) {
			return new WP_Error( 'taxocommerce_core_invalid_page', __( 'La página indicada no existe.', 'taxocommerce-core' ) );
		}

		if ( '' !== $role && ! isset( $this->roles()[ $role ] ) ) {
			return new WP_Error( 'taxocommerce_core_invalid_role', __( 'El rol SEO indicado no es válido.', 'taxocommerce-core' ) );
		}

		$current = $this->get_page_role( $page_id );
		if ( $current === $role ) {
			return true;
		}

		if ( $current && $this->page_has_hierarchy_links( $page_id ) ) {
			return new WP_Error(
				'taxocommerce_core_role_has_links',
				__( 'Esta página ya participa en la jerarquía. Desvincúlala antes de cambiar o quitar su rol.', 'taxocommerce-core' )
			);
		}

		// WordPress postmeta is authoritative. seo_nodes is a compatibility mirror.
		if ( '' === $role ) {
			delete_post_meta( $page_id, self::ROLE_META );
		} elseif ( ! update_post_meta( $page_id, self::ROLE_META, $role )
			&& (string) get_post_meta( $page_id, self::ROLE_META, true ) !== $role ) {
			return new WP_Error( 'taxocommerce_core_role_save_failed', __( 'No se pudo guardar el rol en WordPress.', 'taxocommerce-core' ) );
		}
		$saved = TaxoCommerce_Core_Hierarchy_Classic::save_role_node( $page_id, $role );
		if ( is_wp_error( $saved ) ) {
			$this->logger->warning( 'No se pudo sincronizar el rol SEO histórico.', array( 'page_id' => $page_id ) );
		}

		$this->logger->info(
			'Rol SEO de página actualizado.',
			array(
				'page_id' => $page_id,
				'role'    => $role,
			)
		);

		return true;
	}

	/**
	 * One-time import from the current TaxoCommerce SEO plugin.
	 *
	 * Uses the legacy plugin public helper APIs instead of reading its tables.
	 *
	 * @return void
	 */
	public function maybe_import_legacy() {
		if ( get_option( self::LEGACY_IMPORT_OPTION, false ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! function_exists( 'seo_page_editor_get_structure_tree' ) || ! function_exists( 'seo_page_editor_get_pages_by_roles' ) ) {
			return;
		}

		$report = array(
			'roles'      => 0,
			'relations'  => 0,
			'categories' => 0,
			'imported_at'=> current_time( 'mysql' ),
		);

		$rows = seo_page_editor_get_pages_by_roles( array_keys( $this->roles() ) );
		foreach ( (array) $rows as $row ) {
			$page_id = absint( isset( $row->ID ) ? $row->ID : 0 );
			$role    = sanitize_key( isset( $row->seo_role ) ? (string) $row->seo_role : '' );

			if ( ! $page_id || ! isset( $this->roles()[ $role ] ) || 'page' !== get_post_type( $page_id ) ) {
				continue;
			}

			if ( ! get_post_meta( $page_id, self::ROLE_META, true ) ) {
				update_post_meta( $page_id, self::ROLE_META, $role );
				$report['roles']++;
			}
		}

		$tree = seo_page_editor_get_structure_tree();
		foreach ( (array) $tree as $cluster ) {
			$cluster_id = absint( isset( $cluster['id'] ) ? $cluster['id'] : 0 );
			if ( ! $cluster_id ) {
				continue;
			}
			update_post_meta( $cluster_id, self::ROLE_META, 'cluster' );

			foreach ( (array) $cluster['primaries'] as $primary ) {
				$primary_id = absint( isset( $primary['id'] ) ? $primary['id'] : 0 );
				if ( ! $primary_id ) {
					continue;
				}

				update_post_meta( $primary_id, self::ROLE_META, 'hub_primary' );
				if ( $this->add_unique_post_meta_id( $primary_id, self::PARENT_CLUSTER_META, $cluster_id ) ) {
					$report['relations']++;
				}

				foreach ( (array) $primary['secondaries'] as $secondary ) {
					$secondary_id = absint( isset( $secondary['id'] ) ? $secondary['id'] : 0 );
					if ( ! $secondary_id ) {
						continue;
					}

					update_post_meta( $secondary_id, self::ROLE_META, 'hub_secondary' );
					if ( $this->add_unique_post_meta_id( $secondary_id, self::PARENT_PRIMARY_META, $primary_id ) ) {
						$report['relations']++;
					}

					foreach ( (array) $secondary['categories'] as $category ) {
						$category_id = absint( isset( $category['id'] ) ? $category['id'] : 0 );
						if ( $category_id && $this->add_unique_term_meta_id( $category_id, self::PARENT_SECONDARY_TERM_META, $secondary_id ) ) {
							$report['categories']++;
						}
					}
				}
			}
		}

		update_option( self::LEGACY_IMPORT_OPTION, $report, false );
		$this->logger->info( 'Jerarquía SEO importada desde el plugin actual.', $report );
	}

	/**
	 * Renders hierarchy administration.
	 *
	 * @return void
	 */
	public function render() {
		$this->guard();
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'hierarchy'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only UI tab.
		if ( ! in_array( $tab, array( 'hierarchy', 'roles' ), true ) ) {
			$tab = 'hierarchy';
		}
		$hierarchy_url = add_query_arg(
			array( 'page' => 'taxocommerce-core-hierarchy', 'tab' => 'hierarchy' ),
			admin_url( 'admin.php' )
		);
		$roles_url = add_query_arg(
			array( 'page' => 'taxocommerce-core-hierarchy', 'tab' => 'roles' ),
			admin_url( 'admin.php' )
		);
		?>
		<div class="wrap taxocommerce-hierarchy-admin tcc-family-screen">
            <header class="tcc-page-header">
                <span class="tcc-page-header__icon" aria-hidden="true"><span class="dashicons dashicons-networking"></span></span>
                <div class="tcc-page-header__content">
                    <p class="tcc-page-header__eyebrow"><?php esc_html_e( 'TaxoCommerce Core', 'taxocommerce-core' ); ?></p>
                    <h1><?php echo esc_html__( 'Jerarquía SEO', 'taxocommerce-core' ); ?></h1>
                    <p><?php echo esc_html__( 'Clasifica los contenidos y organiza las relaciones SEO de la tienda.', 'taxocommerce-core' ); ?></p>
                </div>
            </header>

			<nav class="nav-tab-wrapper" aria-label="<?php echo esc_attr__( 'Secciones de jerarquía SEO', 'taxocommerce-core' ); ?>">
				<a href="<?php echo esc_url( $hierarchy_url ); ?>" class="nav-tab <?php echo 'hierarchy' === $tab ? 'nav-tab-active' : ''; ?>" <?php echo 'hierarchy' === $tab ? 'aria-current="page"' : ''; ?>>
					<?php echo esc_html__( 'Jerarquía SEO', 'taxocommerce-core' ); ?>
				</a>
				<a href="<?php echo esc_url( $roles_url ); ?>" class="nav-tab <?php echo 'roles' === $tab ? 'nav-tab-active' : ''; ?>" <?php echo 'roles' === $tab ? 'aria-current="page"' : ''; ?>>
					<?php echo esc_html__( 'Asignación de roles', 'taxocommerce-core' ); ?>
				</a>
			</nav>
			<?php $this->render_notices(); ?>
            <form method="get" style="display:flex;align-items:center;gap:10px;margin:14px 0;flex-wrap:wrap;">
                <input type="hidden" name="page" value="taxocommerce-core-hierarchy">
                <input type="hidden" name="tab" value="<?php echo esc_attr( $tab ); ?>">
                <label for="tc-hierarchy-country"><strong>País</strong></label>
                <select id="tc-hierarchy-country" name="tc_country"><?php TaxoCommerce_Core_Country::options( $this->country_request() ); ?></select>
                <button class="button">Mostrar</button>
            </form>
			<section class="tc-hierarchy-panel">
				<?php if ( 'roles' === $tab ) : ?>
					<h2><?php echo esc_html__( 'Asignación de roles', 'taxocommerce-core' ); ?></h2>
					<p class="description"><?php echo esc_html__( 'Primero clasifica cada página como Cluster, Hub primario, Hub secundario, Landing o Corporativa. Después podrás vincularla desde la pestaña Jerarquía SEO.', 'taxocommerce-core' ); ?></p>
					<?php $this->render_page_roles(); ?>
				<?php else : ?>
					<h2><?php echo esc_html__( 'Organización de la jerarquía', 'taxocommerce-core' ); ?></h2>
					<p class="description"><?php echo esc_html__( 'Abre un cluster y sus hubs para asignar elementos mediante casillas. Los ya asignados aparecen arriba y los disponibles debajo. Cada bloque se guarda por separado.', 'taxocommerce-core' ); ?></p>
					<?php TaxoCommerce_Core_Hierarchy_Classic::render( $this, $this->country_request() ); ?>
					<details class="tc-hierarchy-preview">
						<summary><?php echo esc_html__( 'Ver árbol SEO completo (solo lectura)', 'taxocommerce-core' ); ?></summary>
						<?php TaxoCommerce_Core_Hierarchy_Classic::render_tree( $this, $this->country_request() ); ?>
					</details>
				<?php endif; ?>
			</section>
		</div>
		<?php
	}

	/**
	 * Saves page role action.
	 *
	 * @return void
	 */
	public function handle_save_page_role() {
		$this->guard();
		check_admin_referer( 'taxocommerce_core_save_page_role', 'taxocommerce_core_nonce' );

		$page_id = isset( $_POST['page_id'] ) ? absint( $_POST['page_id'] ) : 0;
		$role    = isset( $_POST['seo_role'] ) ? sanitize_key( wp_unslash( $_POST['seo_role'] ) ) : '';
		$country = TaxoCommerce_Core_Country::read_posted( 'tc_country' );
        if ( is_wp_error( $country ) ) { $this->redirect_with_result( $country, 'role' ); }
        if ( is_string( $country ) && $country !== TaxoCommerce_Core_Country::normalize( get_post_meta( $page_id, TaxoCommerce_Core_Country::META, true ) ) && $this->page_has_hierarchy_links( $page_id ) ) {
            $this->redirect_with_result( new WP_Error( 'tc_country_linked_page', 'Desvincula primero esta página de la jerarquía para cambiar su país.' ), 'role' );
        }
        $result = $this->set_page_role( $page_id, $role );
        if ( ! is_wp_error( $result ) && is_string( $country ) ) {
            $result = TaxoCommerce_Core_Country::set_post( $page_id, $country );
        }
		$this->redirect_with_result( $result, 'role' );
	}

	/**
	 * Adds hierarchy relation action.
	 *
	 * @return void
	 */
	public function handle_add_relation() {
		$this->guard();
		check_admin_referer( 'taxocommerce_core_add_hierarchy_relation', 'taxocommerce_core_nonce' );

		$type      = isset( $_POST['relation_type'] ) ? sanitize_key( wp_unslash( $_POST['relation_type'] ) ) : '';
		$source_id = isset( $_POST['source_id'] ) ? absint( $_POST['source_id'] ) : 0;
		$target_id = isset( $_POST['target_id'] ) ? absint( $_POST['target_id'] ) : 0;

		$result = $this->add_relation( $type, $source_id, $target_id );
		$this->redirect_with_result( $result, 'relation' );
	}

	/**
	 * Deletes one relation.
	 *
	 * @return void
	 */
	public function handle_delete_relation() {
		$this->guard();
		check_admin_referer( 'taxocommerce_core_delete_hierarchy_relation', 'taxocommerce_core_nonce' );

		$type      = isset( $_POST['relation_type'] ) ? sanitize_key( wp_unslash( $_POST['relation_type'] ) ) : '';
		$source_id = isset( $_POST['source_id'] ) ? absint( $_POST['source_id'] ) : 0;
		$target_id = isset( $_POST['target_id'] ) ? absint( $_POST['target_id'] ) : 0;

		$result = $this->delete_relation( $type, $source_id, $target_id );
		$this->redirect_with_result( $result, 'unlink', $source_id );
	}

	/**
	 * Adds one validated relation.
	 *
	 * @param string $type      Relation type.
	 * @param int    $source_id Source ID.
	 * @param int    $target_id Target ID.
	 * @return true|WP_Error
	 */
	private function add_relation( $type, $source_id, $target_id ) {
		return TaxoCommerce_Core_Hierarchy_Classic::add( $this, $type, $source_id, $target_id );
	}

	/**
	 * Deletes one relation.
	 *
	 * @param string $type      Relation type.
	 * @param int    $source_id Source ID.
	 * @param int    $target_id Target ID.
	 * @return true|WP_Error
	 */
	private function delete_relation( $type, $source_id, $target_id ) {
		return TaxoCommerce_Core_Hierarchy_Classic::remove( $this, $type, $source_id, $target_id );
	}

	/**
	 * Role table.
	 *
	 * @return void
	 */
	private function render_page_roles() {
		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter.
		$filter = isset( $_GET['role'] ) ? sanitize_key( wp_unslash( $_GET['role'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter.

		$args = array(
			'post_type'      => 'page',
			'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
			'posts_per_page' => 50,
			'orderby'        => 'title',
			'order'          => 'ASC',
			's'              => $search,
		);

		if ( $filter && isset( $this->roles()[ $filter ] ) ) {
			$role_pages = TaxoCommerce_Core_Hierarchy_Classic::role_pages( $filter );
			$args['post__in'] = $role_pages ? array_map( static function( $page ) { return absint( $page->ID ); }, $role_pages ) : array( 0 );
		}

		$country = $this->country_request();
        // Explicit page assignments are authoritative: absent metadata means Global.
        // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- The admin pages list is bounded to 50 rows.
        $args['meta_query'] = '' === $country
            ? array( 'relation' => 'OR',
                array( 'key' => TaxoCommerce_Core_Country::META, 'compare' => 'NOT EXISTS' ),
                array( 'key' => TaxoCommerce_Core_Country::META, 'value' => '' ),
            )
            : array( array( 'key' => TaxoCommerce_Core_Country::META, 'value' => $country ) );
        $query = new WP_Query( $args );
		?>
		<form class="tc-hierarchy-roles-filter" method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
			<input type="hidden" name="page" value="taxocommerce-core-hierarchy">
			<input type="hidden" name="tab" value="roles"><input type="hidden" name="tc_country" value="<?php echo esc_attr( $this->country_request() ); ?>">
			<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php echo esc_attr__( 'Buscar página', 'taxocommerce-core' ); ?>">
			<select name="role">
				<option value=""><?php echo esc_html__( 'Todos los roles', 'taxocommerce-core' ); ?></option>
				<?php foreach ( $this->roles() as $role => $label ) : ?>
					<option value="<?php echo esc_attr( $role ); ?>" <?php selected( $filter, $role ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
			<button class="button"><?php echo esc_html__( 'Filtrar', 'taxocommerce-core' ); ?></button>
		</form>

		<table class="widefat striped">
			<thead><tr><th><?php echo esc_html__( 'Página', 'taxocommerce-core' ); ?></th><th><?php echo esc_html__( 'Estado', 'taxocommerce-core' ); ?></th><th>País</th><th><?php echo esc_html__( 'Rol SEO', 'taxocommerce-core' ); ?></th><th><?php echo esc_html__( 'Acción', 'taxocommerce-core' ); ?></th></tr></thead>
			<tbody>
			<?php if ( empty( $query->posts ) ) : ?>
				<tr><td colspan="5"><?php echo esc_html__( 'No se han encontrado páginas.', 'taxocommerce-core' ); ?></td></tr>
			<?php else : ?>
				<?php foreach ( $query->posts as $page ) : ?>
					<?php $current = $this->get_page_role( $page->ID ); ?>
					<tr>
						<td><strong><?php echo esc_html( get_the_title( $page ) ); ?></strong><br><small>#<?php echo esc_html( (string) $page->ID ); ?></small></td>
						<td><?php echo esc_html( $page->post_status ); ?></td><td><?php echo esc_html( TaxoCommerce_Core_Country::label( get_post_meta( $page->ID, TaxoCommerce_Core_Country::META, true ) ) ); ?></td>
						<td>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:flex;gap:6px;align-items:center;">
								<input type="hidden" name="action" value="taxocommerce_core_save_page_role">
								<input type="hidden" name="page_id" value="<?php echo esc_attr( (string) $page->ID ); ?>">
								<?php wp_nonce_field( 'taxocommerce_core_save_page_role', 'taxocommerce_core_nonce' ); ?>
								<select name="seo_role">
									<option value=""><?php echo esc_html__( 'Sin rol', 'taxocommerce-core' ); ?></option>
									<?php foreach ( $this->roles() as $role => $label ) : ?>
										<option value="<?php echo esc_attr( $role ); ?>" <?php selected( $current, $role ); ?>><?php echo esc_html( $label ); ?></option>
									<?php endforeach; ?>
								</select>
								<label class="screen-reader-text" for="tc-role-country-<?php echo absint( $page->ID ); ?>">País</label>
                                <select name="tc_country" id="tc-role-country-<?php echo absint( $page->ID ); ?>"><?php TaxoCommerce_Core_Country::options( get_post_meta( $page->ID, TaxoCommerce_Core_Country::META, true ), true ); ?></select>
                                <button class="button button-small"><?php echo esc_html__( 'Guardar', 'taxocommerce-core' ); ?></button>
							</form>
						</td>
						<td><a href="<?php echo esc_url( get_edit_post_link( $page->ID, '' ) ); ?>"><?php echo esc_html__( 'Editar página', 'taxocommerce-core' ); ?></a></td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
			</tbody>
		</table>
		<?php
		wp_reset_postdata();
	}

	/**
	 * Relation forms.
	 *
	 * @return void
	 */
	private function render_relation_forms() {
		$clusters    = $this->pages_by_role( 'cluster' );
		$primaries   = $this->pages_by_role( 'hub_primary' );
		$secondaries = $this->pages_by_role( 'hub_secondary' );
		$categories  = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
				'orderby'    => 'name',
			)
		);
		$categories = is_wp_error( $categories ) ? array() : $categories;
		?>
		<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:14px;">
			<?php $this->render_relation_card( __( 'Cluster → Hub primario', 'taxocommerce-core' ), 'cluster_to_primary', $clusters, $primaries, false ); ?>
			<?php $this->render_relation_card( __( 'Hub primario → Hub secundario', 'taxocommerce-core' ), 'primary_to_secondary', $primaries, $secondaries, false ); ?>
			<?php $this->render_relation_card( __( 'Hub secundario → Categoría', 'taxocommerce-core' ), 'secondary_to_category', $secondaries, $categories, true ); ?>
		</div>
		<?php
	}

	/**
	 * One relation card.
	 *
	 * @param string $title       Title.
	 * @param string $type        Type.
	 * @param array  $sources     Sources.
	 * @param array  $targets     Targets.
	 * @param bool   $target_term Whether target is term.
	 * @return void
	 */
	private function render_relation_card( $title, $type, $sources, $targets, $target_term ) {
		?>
		<div class="card" style="max-width:none;">
			<h3><?php echo esc_html( $title ); ?></h3>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="taxocommerce_core_add_hierarchy_relation">
				<input type="hidden" name="relation_type" value="<?php echo esc_attr( $type ); ?>">
				<?php wp_nonce_field( 'taxocommerce_core_add_hierarchy_relation', 'taxocommerce_core_nonce' ); ?>
				<p><select name="source_id" style="width:100%;"><option value=""><?php echo esc_html__( 'Seleccionar origen', 'taxocommerce-core' ); ?></option><?php foreach ( $sources as $source ) : ?><option value="<?php echo esc_attr( (string) $source->ID ); ?>"><?php echo esc_html( get_the_title( $source ) ); ?></option><?php endforeach; ?></select></p>
				<p><select name="target_id" style="width:100%;"><option value=""><?php echo esc_html__( 'Seleccionar destino', 'taxocommerce-core' ); ?></option><?php foreach ( $targets as $target ) : ?><?php $target_id = $target_term ? absint( $target->term_id ) : absint( $target->ID ); $target_name = $target_term ? $target->name : get_the_title( $target ); ?><option value="<?php echo esc_attr( (string) $target_id ); ?>"><?php echo esc_html( $target_name ); ?></option><?php endforeach; ?></select></p>
				<p><button class="button button-primary"><?php echo esc_html__( 'Asociar', 'taxocommerce-core' ); ?></button></p>
			</form>
		</div>
		<?php
	}

	/**
	 * Tree rendering.
	 *
	 * @return void
	 */
	private function render_tree() {
		$clusters    = $this->pages_by_role( 'cluster' );
		$primaries   = $this->pages_by_role( 'hub_primary' );
		$secondaries = $this->pages_by_role( 'hub_secondary' );
		$categories  = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false, 'orderby' => 'name' ) );
		$categories  = is_wp_error( $categories ) ? array() : $categories;

		$primary_by_cluster = array();
		foreach ( $primaries as $primary ) {
			foreach ( $this->meta_ids( $primary->ID, self::PARENT_CLUSTER_META ) as $cluster_id ) {
				$primary_by_cluster[ $cluster_id ][] = $primary;
			}
		}

		$secondary_by_primary = array();
		foreach ( $secondaries as $secondary ) {
			foreach ( $this->meta_ids( $secondary->ID, self::PARENT_PRIMARY_META ) as $primary_id ) {
				$secondary_by_primary[ $primary_id ][] = $secondary;
			}
		}

		$category_by_secondary = array();
		foreach ( $categories as $category ) {
			foreach ( $this->term_meta_ids( $category->term_id, self::PARENT_SECONDARY_TERM_META ) as $secondary_id ) {
				$category_by_secondary[ $secondary_id ][] = $category;
			}
		}

		if ( empty( $clusters ) ) {
			echo '<p>' . esc_html__( 'Todavía no hay páginas con rol Cluster.', 'taxocommerce-core' ) . '</p>';
			return;
		}

		echo '<div style="max-width:1200px;">';
		foreach ( $clusters as $cluster ) {
			echo '<div class="card" style="max-width:none;margin-bottom:14px;">';
			echo '<h3>' . esc_html( get_the_title( $cluster ) ) . ' <small>#' . esc_html( (string) $cluster->ID ) . '</small></h3>';

			$cluster_primaries = $primary_by_cluster[ $cluster->ID ] ?? array();
			if ( empty( $cluster_primaries ) ) {
				echo '<p><em>' . esc_html__( 'Sin hubs primarios asociados.', 'taxocommerce-core' ) . '</em></p>';
			}

			foreach ( $cluster_primaries as $primary ) {
				echo '<div style="margin-left:20px;border-left:3px solid #ccd0d4;padding-left:12px;">';
				echo '<p><strong>↳ ' . esc_html( get_the_title( $primary ) ) . '</strong> ';
				$this->render_unlink_button( 'cluster_to_primary', $cluster->ID, $primary->ID );
				echo '</p>';

				$primary_secondaries = $secondary_by_primary[ $primary->ID ] ?? array();
				foreach ( $primary_secondaries as $secondary ) {
					echo '<div style="margin-left:20px;">';
					echo '<p>↳ <strong>' . esc_html( get_the_title( $secondary ) ) . '</strong> ';
					$this->render_unlink_button( 'primary_to_secondary', $primary->ID, $secondary->ID );
					echo '</p>';

					$secondary_categories = $category_by_secondary[ $secondary->ID ] ?? array();
					if ( empty( $secondary_categories ) ) {
						echo '<p style="margin-left:20px;"><em>' . esc_html__( 'Sin categorías asociadas.', 'taxocommerce-core' ) . '</em></p>';
					} else {
						echo '<ul style="margin-left:35px;list-style:disc;">';
						foreach ( $secondary_categories as $category ) {
							echo '<li>' . esc_html( $category->name ) . ' ';
							$this->render_unlink_button( 'secondary_to_category', $secondary->ID, $category->term_id );
							echo '</li>';
						}
						echo '</ul>';
					}
					echo '</div>';
				}
				echo '</div>';
			}
			echo '</div>';
		}
		echo '</div>';
	}

	/**
	 * Unlink form.
	 *
	 * @param string $type      Relation type.
	 * @param int    $source_id Source.
	 * @param int    $target_id Target.
	 * @return void
	 */
	private function render_unlink_button( $type, $source_id, $target_id ) {
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
			<input type="hidden" name="action" value="taxocommerce_core_delete_hierarchy_relation">
			<input type="hidden" name="relation_type" value="<?php echo esc_attr( $type ); ?>">
			<input type="hidden" name="source_id" value="<?php echo esc_attr( (string) absint( $source_id ) ); ?>">
			<input type="hidden" name="target_id" value="<?php echo esc_attr( (string) absint( $target_id ) ); ?>">
			<?php wp_nonce_field( 'taxocommerce_core_delete_hierarchy_relation', 'taxocommerce_core_nonce' ); ?>
			<button class="button-link-delete" type="submit"><?php echo esc_html__( 'Desvincular', 'taxocommerce-core' ); ?></button>
		</form>
		<?php
	}

	/**
	 * KPI cards.
	 *
	 * @return void
	 */
	private function render_kpis() {
		$cards = array();
		foreach ( $this->roles() as $role => $label ) {
			$cards[ $label ] = count( $this->pages_by_role( $role ) );
		}

		$linked_categories = array();
		foreach ( TaxoCommerce_Core_Hierarchy_Classic::rows( 'hub_secondary_to_category' ) as $row ) {
			$linked_categories[ absint( $row['target_id'] ) ] = true;
		}
		$linked_categories = count( $linked_categories );
		$cards[ __( 'Categorías vinculadas', 'taxocommerce-core' ) ] = $linked_categories;
		?>
		<div style="display:flex;flex-wrap:wrap;gap:12px;margin:16px 0;">
			<?php foreach ( $cards as $label => $value ) : ?>
				<div class="card" style="min-width:150px;margin:0;padding:12px 16px;"><strong style="display:block;font-size:22px;"><?php echo esc_html( (string) $value ); ?></strong><span><?php echo esc_html( $label ); ?></span></div>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Success/error notices from redirect.
	 *
	 * @return void
	 */
	private function render_notices() {
		$result = isset( $_GET['tc_result'] ) ? sanitize_key( wp_unslash( $_GET['tc_result'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Redirect notice.
		$msg    = isset( $_GET['tc_message'] ) ? sanitize_text_field( wp_unslash( $_GET['tc_message'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Redirect notice.

		if ( 'ok' === $result ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Jerarquía actualizada.', 'taxocommerce-core' ) . '</p></div>';
		} elseif ( 'error' === $result && $msg ) {
			echo '<div class="notice notice-error"><p>' . esc_html( $msg ) . '</p></div>';
		}
	}

	/**
	 * Redirects after a write action.
	 *
	 * @param true|WP_Error $result Result.
	 * @param string        $context Context.
	 * @return void
	 */
	private function country_request( $write = false ) {
		if ( $write ) {
			// All callers are mutation handlers after their action-specific nonce.
			// Delegate POST validation to the shared, already verified helper.
			$value = TaxoCommerce_Core_Country::read_posted( 'tc_country' );
			return is_string( $value ) ? TaxoCommerce_Core_Country::normalize( $value ) : '';
		}
		$value = isset( $_GET['tc_country'] ) && is_string( $_GET['tc_country'] ) ? sanitize_text_field( wp_unslash( $_GET['tc_country'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only navigation selector, never persisted or mutated.
		return TaxoCommerce_Core_Country::normalize( $value );
	}

	private function redirect_with_result( $result, $context, $source_id = 0 ) {
		$tab = 'role' === $context ? 'roles' : 'hierarchy';
		// The caller verified the mutation nonce and supplies the already
		// sanitized source ID; never read unverified POST during redirects.
		$focus = 'hierarchy' === $tab ? absint( $source_id ) : 0;
		$args = array(
			'page'       => 'taxocommerce-core-hierarchy',
            'tc_country' => $this->country_request( true ),
			'tab'        => $tab,
			'tc_context' => sanitize_key( $context ),
		);
		if ( $focus ) {
			$args['tc_focus'] = $focus;
		}

		if ( is_wp_error( $result ) ) {
			$args['tc_result']  = 'error';
			$args['tc_message'] = $result->get_error_message();
		} else {
			$args['tc_result'] = 'ok';
		}

		$url = add_query_arg( $args, admin_url( 'admin.php' ) );
		wp_safe_redirect( $url . ( $focus ? '#tc-source-' . $focus : '' ) );
		exit;
	}

	/**
	 * Pages by role.
	 *
	 * @param string $role Role.
	 * @return WP_Post[]
	 */
	private function pages_by_role( $role ) {
		$role = sanitize_key( $role );
		return isset( $this->roles()[ $role ] )
			? TaxoCommerce_Core_Hierarchy_Classic::role_pages( $role ) : array();
	}

	/**
	 * Whether a page participates in any hierarchy relation.
	 *
	 * @param int $page_id Page ID.
	 * @return bool
	 */
	private function page_has_hierarchy_links( $page_id ) {
		$page_id = absint( $page_id );
		foreach ( TaxoCommerce_Core_Hierarchy_Classic::rows() as $relation ) {
			if ( absint( $relation['source_id'] ) === $page_id || (
				absint( $relation['target_id'] ) === $page_id && 'hub_secondary_to_category' !== $relation['relation_type']
			) ) {
				return true;
			}
		}

		if ( $this->meta_ids( $page_id, self::PARENT_CLUSTER_META ) || $this->meta_ids( $page_id, self::PARENT_PRIMARY_META ) ) {
			return true;
		}

		foreach ( $this->pages_by_role( 'hub_primary' ) as $primary ) {
			if ( in_array( $page_id, $this->meta_ids( $primary->ID, self::PARENT_CLUSTER_META ), true ) ) {
				return true;
			}
		}

		foreach ( $this->pages_by_role( 'hub_secondary' ) as $secondary ) {
			if ( in_array( $page_id, $this->meta_ids( $secondary->ID, self::PARENT_PRIMARY_META ), true ) ) {
				return true;
			}
		}

		$categories = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false, 'fields' => 'ids' ) );
		if ( ! is_wp_error( $categories ) ) {
			foreach ( $categories as $category_id ) {
				if ( in_array( $page_id, $this->term_meta_ids( $category_id, self::PARENT_SECONDARY_TERM_META ), true ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Adds a numeric post-meta value only once.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key     Meta key.
	 * @param int    $value   ID.
	 * @return bool True when newly added.
	 */
	private function add_unique_post_meta_id( $post_id, $key, $value ) {
		$value = absint( $value );
		if ( ! $value || in_array( $value, $this->meta_ids( $post_id, $key ), true ) ) {
			return false;
		}
		add_post_meta( absint( $post_id ), $key, $value, false );
		return true;
	}

	/**
	 * Adds a numeric term-meta value only once.
	 *
	 * @param int    $term_id Term ID.
	 * @param string $key     Meta key.
	 * @param int    $value   ID.
	 * @return bool True when newly added.
	 */
	private function add_unique_term_meta_id( $term_id, $key, $value ) {
		$value = absint( $value );
		if ( ! $value || in_array( $value, $this->term_meta_ids( $term_id, $key ), true ) ) {
			return false;
		}
		add_term_meta( absint( $term_id ), $key, $value, false );
		return true;
	}

	/**
	 * Numeric post-meta values.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key     Key.
	 * @return int[]
	 */
	private function meta_ids( $post_id, $key ) {
		return array_values( array_unique( array_filter( array_map( 'absint', get_post_meta( absint( $post_id ), $key, false ) ) ) ) );
	}

	/**
	 * Numeric term-meta values.
	 *
	 * @param int    $term_id Term ID.
	 * @param string $key     Key.
	 * @return int[]
	 */
	private function term_meta_ids( $term_id, $key ) {
		return array_values( array_unique( array_filter( array_map( 'absint', get_term_meta( absint( $term_id ), $key, false ) ) ) ) );
	}

	/**
	 * Permission guard.
	 *
	 * @return void
	 */
	private function guard() {
		if ( ! current_user_can( apply_filters( 'taxocommerce_core_manage_capability', 'manage_options' ) ) ) {
			wp_die( esc_html__( 'No tienes permisos para gestionar TaxoCommerce Core.', 'taxocommerce-core' ) );
		}
	}
}
