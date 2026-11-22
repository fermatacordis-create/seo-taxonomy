<?php
/**
 * Main plugin coordinator.
 *
 * @package TaxoCommerceImportExport
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Coordinates administration and Core integration.
 */
final class TaxoCommerce_Import_Export {

	/**
	 * Singleton instance.
	 *
	 * @var TaxoCommerce_Import_Export|null
	 */
	private static $instance = null;

	/**
	 * CSV service.
	 *
	 * @var TaxoCommerce_Import_Export_CSV|null
	 */
	private $csv = null;

	/**
	 * Media service.
	 *
	 * @var TaxoCommerce_Import_Export_Media|null
	 */
	private $media = null;

	/**
	 * Batch queue.
	 *
	 * @var TaxoCommerce_Import_Export_Batch|null
	 */
	private $batch = null;

	/**
	 * Incremental export jobs.
	 *
	 * @var TaxoCommerce_Import_Export_Exporter|null
	 */
	private $exporter = null;

	/** @var TaxoCommerce_Import_Export_Basic */
	private $basic = null;

	/**
	 * Returns singleton.
	 *
	 * @return TaxoCommerce_Import_Export
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Boots hooks.
	 *
	 * @return void
	 */
	public function boot() {
		$this->csv      = new TaxoCommerce_Import_Export_CSV();
		$this->media    = new TaxoCommerce_Import_Export_Media();
		$this->batch    = new TaxoCommerce_Import_Export_Batch( $this->csv );
		$this->exporter = new TaxoCommerce_Import_Export_Exporter( $this->csv );
		$this->basic    = new TaxoCommerce_Import_Export_Basic();
		$this->batch->boot();
		$this->exporter->boot();
		$this->basic->boot();

		add_action( 'admin_menu', array( $this, 'register_menu' ), 40 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_family_admin_styles' ) );
		add_filter( 'admin_body_class', array( $this, 'family_admin_body_class' ) );
		add_action( 'admin_post_taxocommerce_import_export_export', array( $this->csv, 'handle_export' ) );
		add_action( 'admin_post_taxocommerce_import_export_import', array( $this->csv, 'handle_import' ) );
		add_action( 'wp_ajax_taxocommerce_ie_individual_status', array( $this, 'ajax_individual_status' ) );
		add_action( 'admin_post_taxocommerce_import_export_media_export', array( $this->media, 'handle_export' ) );
		add_action( 'admin_post_taxocommerce_import_export_media_import', array( $this->media, 'handle_import' ) );
		add_filter( 'plugin_action_links_' . TAXOCOMMERCE_IMPORT_EXPORT_BASENAME, array( $this, 'plugin_action_links' ) );
	}

	/**
	 * Use the common family theme even when this extension runs standalone.
	 * If Core is active it owns the shared stylesheet and body class.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_family_admin_styles( $hook ) {
		if ( defined( 'TAXOCOMMERCE_CORE_VERSION' ) || false === strpos( (string) $hook, 'taxocommerce-import-export' ) ) {
			return;
		}
		wp_enqueue_style(
			'taxocommerce-family-admin',
			plugin_dir_url( TAXOCOMMERCE_IMPORT_EXPORT_FILE ) . 'assets/admin.css',
			array(),
			TAXOCOMMERCE_IMPORT_EXPORT_VERSION
		);
	}

	/**
	 * Isolate the shared appearance from unrelated WordPress plugin screens.
	 *
	 * @param string $classes Admin body class string.
	 * @return string
	 */
	public function family_admin_body_class( $classes ) {
		if ( defined( 'TAXOCOMMERCE_CORE_VERSION' ) ) {
			return $classes;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		return $screen && false !== strpos( (string) $screen->id, 'taxocommerce-import-export' )
			? $classes . ' tcc-family-admin'
			: $classes;
	}

	/**
	 * Registers plugin in TaxoCommerce Core when Core is active.
	 *
	 * Do not invoke translations here because Core registration runs before init.
	 *
	 * @param object $core Core registry.
	 * @return void
	 */
	public function register_in_core( $core ) {
		if ( ! is_object( $core ) || ! method_exists( $core, 'register_module' ) ) {
			return;
		}

		$core->register_module(
			'import_export',
			array(
				'name'        => 'TaxoCommerce Import-Export',
				'version'     => TAXOCOMMERCE_IMPORT_EXPORT_VERSION,
				'status'      => 'active',
				'description' => 'Importación y exportación de catálogo, contenidos y Media.',
				'admin_url'   => admin_url( 'admin.php?page=taxocommerce-import-export' ),
			)
		);
	}

	/**
	 * Registers admin menu.
	 *
	 * @return void
	 */
	public function register_menu() {
		$capability = $this->capability();

		if ( function_exists( 'taxocommerce_core' ) ) {
			add_submenu_page(
				'taxocommerce-core',
				__( 'Import / Export', 'taxocommerce-import-export' ),
				__( 'Import / Export', 'taxocommerce-import-export' ),
				$capability,
				'taxocommerce-import-export',
				array( $this, 'render_page' )
			);
			return;
		}

		add_menu_page(
			__( 'TaxoCommerce Import-Export', 'taxocommerce-import-export' ),
			__( 'TaxoCommerce Import-Export', 'taxocommerce-import-export' ),
			$capability,
			'taxocommerce-import-export',
			array( $this, 'render_page' ),
			'dashicons-database-import',
			58
		);
	}

	/**
	 * Plugin-list shortcut.
	 *
	 * @param array $links Links.
	 * @return array
	 */
	public function plugin_action_links( $links ) {
		array_unshift(
			$links,
			'<a href="' . esc_url( admin_url( 'admin.php?page=taxocommerce-import-export' ) ) . '">' . esc_html__( 'Abrir', 'taxocommerce-import-export' ) . '</a>'
		);
		return $links;
	}

	/**
	 * Renders administration page.
	 *
	 * @return void
	 */
	public function render_page() {
		$this->guard();

		$result = isset( $_GET['tc_ie_result'] ) ? sanitize_key( wp_unslash( $_GET['tc_ie_result'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Redirect status only.
		$count  = isset( $_GET['tc_ie_count'] ) ? absint( $_GET['tc_ie_count'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Redirect status only.
		$error  = isset( $_GET['tc_ie_error'] ) ? sanitize_text_field( wp_unslash( $_GET['tc_ie_error'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Redirect status only.
		$kind    = isset( $_GET['tc_ie_kind'] ) ? sanitize_key( wp_unslash( $_GET['tc_ie_kind'] ) ) : 'csv'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Redirect status only.
		$tab     = isset( $_GET['tc_ie_tab'] ) ? sanitize_key( wp_unslash( $_GET['tc_ie_tab'] ) ) : 'export'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Navigation only.
		$raw_message = isset( $_GET['tc_ie_message'] ) && is_string( $_GET['tc_ie_message'] ) ? sanitize_text_field( wp_unslash( $_GET['tc_ie_message'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only redirect notice.
		$message = sanitize_text_field( rawurldecode( $raw_message ) );
		if ( ! in_array( $tab, array( 'export', 'individual', 'batch', 'auto-test', 'manual' ), true ) ) {
			$tab = 'export';
		}
		?>
		<div class="wrap tcc-family-screen">

			<nav class="nav-tab-wrapper" aria-label="<?php esc_attr_e( 'Secciones de Import-Export', 'taxocommerce-import-export' ); ?>">
				<a class="nav-tab <?php echo esc_attr( 'export' === $tab ? 'nav-tab-active' : '' ); ?>" href="<?php echo esc_url( add_query_arg( array( 'page' => 'taxocommerce-import-export', 'tc_ie_tab' => 'export' ), admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'Exportación', 'taxocommerce-import-export' ); ?></a>
				<a class="nav-tab <?php echo esc_attr( 'individual' === $tab ? 'nav-tab-active' : '' ); ?>" href="<?php echo esc_url( add_query_arg( array( 'page' => 'taxocommerce-import-export', 'tc_ie_tab' => 'individual' ), admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'Importación individual', 'taxocommerce-import-export' ); ?></a>
				<a class="nav-tab <?php echo esc_attr( 'batch' === $tab ? 'nav-tab-active' : '' ); ?>" href="<?php echo esc_url( add_query_arg( array( 'page' => 'taxocommerce-import-export', 'tc_ie_tab' => 'batch' ), admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'Importación por lotes', 'taxocommerce-import-export' ); ?></a>
				<a class="nav-tab <?php echo esc_attr( 'auto-test' === $tab ? 'nav-tab-active' : '' ); ?>" href="<?php echo esc_url( add_query_arg( array( 'page' => 'taxocommerce-import-export', 'tc_ie_tab' => 'auto-test' ), admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'Autocheck', 'taxocommerce-import-export' ); ?></a>
				<a class="nav-tab <?php echo esc_attr( 'manual' === $tab ? 'nav-tab-active' : '' ); ?>" href="<?php echo esc_url( add_query_arg( array( 'page' => 'taxocommerce-import-export', 'tc_ie_tab' => 'manual' ), admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'Manual', 'taxocommerce-import-export' ); ?></a>
			</nav>

			<?php if ( 'manual' === $tab ) : ?>
				<?php TaxoCommerce_Import_Export_Manual::render(); ?>
				</div>
				<?php return; ?>
			<?php endif; ?>

			<?php if ( 'auto-test' === $tab ) : ?>
				<?php TaxoCommerce_Import_Export_Auto_Test::render(); ?>
				</div>
				<?php return; ?>
			<?php endif; ?>

			<?php if ( $message ) : ?>
				<div class="notice <?php echo esc_attr( 'error' === $result ? 'notice-error' : 'notice-success' ); ?> is-dismissible"><p><?php echo esc_html( $message ); ?></p></div>
			<?php endif; ?>

			<?php if ( 'export' === $tab && $this->exporter ) : ?>
				<?php $this->exporter->render(); ?>
				<div class="card" style="max-width:none;margin-top:20px;padding:20px;">
					<h2 style="margin-top:0"><?php esc_html_e( 'Media · paquete ZIP', 'taxocommerce-import-export' ); ?></h2>
					<p><?php esc_html_e( 'Media mantiene su exportación específica porque transporta archivos binarios además de metadatos. No forma parte del CSV por bloques.', 'taxocommerce-import-export' ); ?></p>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="taxocommerce_import_export_media_export">
						<?php wp_nonce_field( 'taxocommerce_import_export_media_export', 'taxocommerce_import_export_nonce' ); ?>
						<?php submit_button( __( 'Descargar ZIP de Media', 'taxocommerce-import-export' ), 'secondary', 'submit', false ); ?>
					</form>
				</div>
				</div>
				<?php return; ?>
			<?php endif; ?>

			<?php if ( 'batch' === $tab && $this->batch ) : ?>
				<?php $this->batch->render(); ?>
				</div>
				<?php return; ?>
			<?php endif; ?>

			<?php if ( 'ok' === $result && ! $message ) : ?>
				<div class="notice notice-success is-dismissible"><p>
					<?php
					if ( 'media' === $kind ) {
						/* translators: %d: number of Media images imported. */
						echo esc_html( sprintf( __( 'Importación de Media completada. Imágenes importadas: %d.', 'taxocommerce-import-export' ), $count ) );
					} else {
						/* translators: %d: number of CSV rows processed. */
						echo esc_html( sprintf( __( 'Importación completada. Filas procesadas: %d.', 'taxocommerce-import-export' ), $count ) );
					}
					?>
				</p></div>
			<?php elseif ( 'error' === $result && $error ) : ?>
				<div class="notice notice-error"><p><?php echo esc_html( $error ); ?></p></div>
			<?php endif; ?>

			<?php $this->basic->render(); ?>

			<h2><?php echo esc_html__( 'Media · imágenes', 'taxocommerce-import-export' ); ?></h2>
			<p><?php echo esc_html__( 'Las imágenes se transportan aquí, no desde TaxoCommerce Images. El paquete contiene originales, metadatos y relaciones; las miniaturas se regeneran al importar.', 'taxocommerce-import-export' ); ?></p>
			<div class="tcc-family-grid">
				<div class="card">
					<h3><?php echo esc_html__( 'Importar Media', 'taxocommerce-import-export' ); ?></h3>
					<p><?php echo esc_html__( 'Importa un ZIP generado por TaxoCommerce Import-Export. Los archivos se crean como attachments reales en Media.', 'taxocommerce-import-export' ); ?></p>
					<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="taxocommerce_import_export_media_import">
						<?php wp_nonce_field( 'taxocommerce_import_export_media_import', 'taxocommerce_import_export_nonce' ); ?>
						<p>
							<label for="tc-ie-media-file"><strong><?php echo esc_html__( 'Paquete ZIP de Media', 'taxocommerce-import-export' ); ?></strong></label><br>
							<input id="tc-ie-media-file" type="file" name="media_zip" accept=".zip,application/zip" required>
						</p>
						<?php submit_button( __( 'Importar ZIP de Media', 'taxocommerce-import-export' ), 'primary', 'submit', false ); ?>
					</form>
				</div>
			</div>

			<h2><?php echo esc_html__( 'Qué se transporta', 'taxocommerce-import-export' ); ?></h2>
			<table class="widefat striped" style="max-width:1200px;">
				<thead><tr><th><?php echo esc_html__( 'Tipo', 'taxocommerce-import-export' ); ?></th><th><?php echo esc_html__( 'Contenido', 'taxocommerce-import-export' ); ?></th></tr></thead>
				<tbody>
					<tr><td><?php echo esc_html__( 'Productos', 'taxocommerce-import-export' ); ?></td><td><?php echo esc_html__( 'ID, tipo, estado, nombre, slug, SKU, precios, stock, descripciones, categorías, etiquetas, atributos y datos de producto externo.', 'taxocommerce-import-export' ); ?></td></tr>
					<tr><td><?php echo esc_html__( 'Categorías', 'taxocommerce-import-export' ); ?></td><td><?php echo esc_html__( 'ID, nombre, slug, padre, descripción e imagen.', 'taxocommerce-import-export' ); ?></td></tr>
					<tr><td><?php echo esc_html__( 'Posts / Páginas', 'taxocommerce-import-export' ); ?></td><td><?php echo esc_html__( 'ID, estado, título, slug, extracto, contenido, fecha, autor y taxonomías.', 'taxocommerce-import-export' ); ?></td></tr>
					<tr><td><?php echo esc_html__( 'Media · imágenes', 'taxocommerce-import-export' ); ?></td><td><?php echo esc_html__( 'Archivo original, título, ALT, leyenda, descripción, post_parent, imagen destacada, galería WooCommerce e imagen de categoría.', 'taxocommerce-import-export' ); ?></td></tr>
				</tbody>
			</table>
		</div>
		<?php
	}

	/** Authenticated, read-only snapshot of the latest individual import. */
	public function ajax_individual_status() {
		$this->guard();
		check_ajax_referer( 'taxocommerce_ie_individual_status', 'nonce' );
		$batch = new TaxoCommerce_Import_Export_Batch( $this->csv );
		wp_send_json_success( $batch->individual_status() );
	}

	/**
	 * Required capability.
	 *
	 * @return string
	 */
	private function capability() {
		return (string) apply_filters( 'taxocommerce_import_export_capability', 'manage_woocommerce' );
	}

	/**
	 * Guard.
	 *
	 * @return void
	 */
	private function guard() {
		if ( ! current_user_can( $this->capability() ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'No tienes permisos para gestionar TaxoCommerce Import-Export.', 'taxocommerce-import-export' ) );
		}
	}
}
