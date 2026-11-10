<?php
/**
 * Main TaxoCommerce Core coordinator.
 *
 * @package TaxoCommerceCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Coordinates Core services and administration.
 */
final class TaxoCommerce_Core {

	/**
	 * Singleton.
	 *
	 * @var TaxoCommerce_Core|null
	 */
	private static $instance = null;

	/**
	 * Whether Core booted.
	 *
	 * @var bool
	 */
	private $booted = false;

	/**
	 * Logger.
	 *
	 * @var TaxoCommerce_Core_Logger|null
	 */
	private $logger = null;

	/**
	 * Hierarchy manager.
	 *
	 * @var TaxoCommerce_Core_Hierarchy|null
	 */
	private $hierarchy = null;

	/**
	 * Menu manager.
	 *
	 * @var TaxoCommerce_Core_Menu|null
	 */
	private $menu = null;

	/**
	 * Content manager.
	 *
	 * @var TaxoCommerce_Core_Content_Manager|null
	 */
	private $content = null;

	/**
	 * Runtime module registry.
	 *
	 * @var array
	 */
	private $modules = array();

	/**
	 * Returns singleton.
	 *
	 * @return TaxoCommerce_Core
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Boots the Core.
	 *
	 * @return void
	 */
	public function boot() {
		if ( $this->booted ) {
			return;
		}

		$this->logger    = new TaxoCommerce_Core_Logger();
		$this->hierarchy = new TaxoCommerce_Core_Hierarchy( $this->logger );
		$this->menu      = new TaxoCommerce_Core_Menu( $this->logger );
		$this->content   = new TaxoCommerce_Core_Content_Manager( $this->logger );

		$this->register_module(
			'core',
			array(
				'name'        => 'TaxoCommerce Core',
				'version'     => TAXOCOMMERCE_CORE_VERSION,
				'status'      => 'active',
				'description' => 'Shared content, hierarchy, and extension services.',
			)
		);

		/**
		 * Lets extensions register themselves.
		 *
		 * @param TaxoCommerce_Core $core Core instance.
		 */
		do_action( 'taxocommerce_core_register_modules', $this );


		add_action( 'admin_menu', array( $this, 'register_menu' ), 5 );
		add_action( 'admin_menu', array( $this, 'normalize_family_submenu_labels' ), 9999 );
		add_action( 'admin_enqueue_scripts', array( $this, 'hide_sidebar_submenu' ), 999 );
		add_action( 'admin_init', array( $this, 'redirect_legacy_family_page' ) );
		add_action( 'admin_init', array( $this, 'redirect_old_extensions_page' ) );
		add_action( 'admin_init', array( $this, 'redirect_old_auto_test_page' ) );
		add_action( 'admin_init', array( $this, 'redirect_legacy_anomalies_page' ) );
		add_action( 'admin_init', array( $this, 'redirect_legacy_reports_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_family_styles' ) );
		add_filter( 'admin_body_class', array( $this, 'family_body_class' ) );
		add_action( 'admin_post_taxocommerce_core_install_extension', array( $this, 'install_extension' ) );
		add_action( 'admin_post_taxocommerce_core_upload_extension', array( $this, 'upload_extension' ) );
		add_action( 'admin_post_taxocommerce_core_activate_extension', array( $this, 'activate_extension' ) );
		add_action( 'admin_post_taxocommerce_core_deactivate_extension', array( $this, 'deactivate_extension' ) );
		TaxoCommerce_Core_Suite::register_hooks();
		add_filter( 'plugin_action_links_' . TAXOCOMMERCE_CORE_BASENAME, array( $this, 'plugin_action_links' ) );

		TaxoCommerce_Core_Country::register_hooks();
		$this->hierarchy->register_hooks();
		$this->menu->register_hooks();
		$this->content->register_hooks();
		TaxoCommerce_Core_Auto_Test::register_hooks();
		$this->booted = true;

		/**
		 * Fires after Core is ready.
		 *
		 * @param TaxoCommerce_Core $core Core instance.
		 */
		do_action( 'taxocommerce_core_loaded', $this );
	}

	/**
	 * Adds a plugin-list shortcut.
	 *
	 * @param array $links Existing links.
	 * @return array
	 */
	public function plugin_action_links( $links ) {
		$url = admin_url( 'admin.php?page=taxocommerce-core' );
		array_unshift(
			$links,
			'<a href="' . esc_url( $url ) . '">' . esc_html__( 'Open Core', 'taxocommerce-core' ) . '</a>'
		);
		return $links;
	}

	/**
	 * Registers main menu.
	 *
	 * @return void
	 */
	public function register_menu() {
		$capability = $this->capability();

		add_menu_page(
			__( 'TaxoCommerce Core', 'taxocommerce-core' ),
			__( 'TaxoCommerce Core', 'taxocommerce-core' ),
			$capability,
			'taxocommerce-core',
			array( $this, 'render_dashboard' ),
			'dashicons-networking',
			58
		);

		// Core remains the only visible Core entry in the WordPress sidebar.
		// Its internal screens are registered for direct links, but hidden from
		// the sidebar after plugins have registered their own entries.
		add_submenu_page(
			'taxocommerce-core',
			__( 'TaxoCommerce Core', 'taxocommerce-core' ),
			__( 'TaxoCommerce Core', 'taxocommerce-core' ),
			$capability,
			'taxocommerce-core',
			array( $this, 'render_dashboard' )
		);

		// Register module screens normally so WordPress keeps their routes and capabilities.
		add_submenu_page(
			'taxocommerce-core',
			__( 'Content', 'taxocommerce-core' ),
			__( 'Content', 'taxocommerce-core' ),
			$capability,
			TaxoCommerce_Core_Content_Manager::PAGE_SLUG,
			array( $this->content, 'render' )
		);

		add_submenu_page(
			'taxocommerce-core',
			__( 'SEO Hierarchy', 'taxocommerce-core' ),
			__( 'SEO Hierarchy', 'taxocommerce-core' ),
			$capability,
			'taxocommerce-core-hierarchy',
			array( $this->hierarchy, 'render' )
		);

		add_submenu_page(
			'taxocommerce-core',
			__( 'Menu Manager', 'taxocommerce-core' ),
			__( 'Menu Manager', 'taxocommerce-core' ),
			$capability,
			'taxocommerce-core-menu',
			array( $this->menu, 'render' )
		);

		add_submenu_page(
			'taxocommerce-core',
			__( 'User Guide', 'taxocommerce-core' ),
			__( 'User Guide', 'taxocommerce-core' ),
			$capability,
			TaxoCommerce_Core_User_Guide::PAGE_SLUG,
			array( 'TaxoCommerce_Core_User_Guide', 'render' )
		);
	}


	/**
	 * Normalizes visible TaxoCommerce family submenu labels.
	 *
	 * Core is the visual authority for family navigation. Extensions keep
	 * ownership of their routes and capabilities, but Core applies canonical
	 * English labels after every active extension has registered its submenu.
	 *
	 * @return void
	 */
	public function normalize_family_submenu_labels() {
		global $menu, $submenu;

		// Extensions own their submenu pages, not the name of the Core parent.
		// The parent could be incorrectly labelled after late plugin registration.
		// Preserve WordPress' route, icon, capability and all child callbacks.
		if ( is_array( $menu ) ) {
			foreach ( $menu as &$top_level ) {
				if ( is_array( $top_level ) && isset( $top_level[2] ) && 'taxocommerce-core' === $top_level[2] ) {
					$top_level[0] = __( 'TaxoCommerce Core', 'taxocommerce-core' );
				}
			}
			unset( $top_level );
		}

		if ( empty( $submenu['taxocommerce-core'] ) || ! is_array( $submenu['taxocommerce-core'] ) ) {
			return;
		}

		$labels = array(
			'taxocommerce-core'          => __( 'TaxoCommerce Core', 'taxocommerce-core' ),
			'taxocommerce-core-content'  => __( 'Content', 'taxocommerce-core' ),
			'taxocommerce-core-hierarchy'=> __( 'SEO Hierarchy', 'taxocommerce-core' ),
			'taxocommerce-core-menu'     => __( 'Menu Manager', 'taxocommerce-core' ),
			'taxocommerce-core-user-guide'=> __( 'User Guide', 'taxocommerce-core' ),
			'taxocommerce-vocabulary'    => __( 'Vocabulary', 'taxocommerce-core' ),
			'taxocommerce-dependiente'   => __( 'Sales Assistant', 'taxocommerce-core' ),
			'taxocommerce-import-export' => __( 'Import / Export', 'taxocommerce-core' ),
			'taxocommerce-suppliers'     => __( 'Suppliers', 'taxocommerce-core' ),
			'taxocommerce-redirects'     => __( 'Redirects', 'taxocommerce-core' ),
			'taxocommerce-distribution'  => __( 'Distribution', 'taxocommerce-core' ),
			'taxocommerce-images'        => __( 'Images', 'taxocommerce-core' ),
			'taxocommerce-faqs'          => __( 'FAQs', 'taxocommerce-core' ),
			'taxocommerce-database'      => __( 'Database', 'taxocommerce-core' ),
			'taxocommerce-system-check'  => __( 'System Check', 'taxocommerce-core' ),
			'taxocommerce-templates'     => __( 'Templates', 'taxocommerce-core' ),
			'taxocommerce-google-bing'   => __( 'Insights', 'taxocommerce-core' ),
			'taxocommerce-scrappers'      => __( 'Scrappers', 'taxocommerce-core' ),
			'taxocommerce-auditor'       => __( 'Auditor', 'taxocommerce-core' ),
			'taxocommerce-social'        => __( 'Social', 'taxocommerce-core' ),
			'seo-facturas'               => __( 'Billing', 'taxocommerce-core' ),
			'taxocommerce-logistica'     => __( 'Logistics', 'taxocommerce-core' ),
		);

		foreach ( $submenu['taxocommerce-core'] as &$item ) {
			if ( ! is_array( $item ) || ! isset( $item[2] ) ) {
				continue;
			}

			$slug = (string) $item[2];
			if ( isset( $labels[ $slug ] ) ) {
				$item[0] = $labels[ $slug ];
			}
		}
		unset( $item );
	}

 
	/**
	 * Compatibility shim: never remove native WordPress submenu registrations.
	 *
	 * WordPress uses $submenu to resolve admin.php?page=... route parents.
	 * Unsetting those entries can make valid Core pages return HTTP 403.
	 * Visibility is controlled only by hide_sidebar_submenu() CSS instead.
	 */
	public function hide_core_internal_submenus() {
		// Intentionally leave the WordPress submenu registry unchanged.
	}

	/**
	 * Hides the Core submenu only in the browser.
	 *
	 * The submenu remains registered in WordPress so direct links, capability
	 * checks and page hooks continue to work normally.
	 *
	 * @return void
	 */
	public function hide_sidebar_submenu() {
		// Never remove entries from $submenu: WordPress needs them to resolve
		// route parents for Content, SEO Hierarchy, Menu Manager and Manual.
		global $submenu;
		$items = isset( $submenu['taxocommerce-core'] ) ? (array) $submenu['taxocommerce-core'] : array();
		$core_pages = array( 'taxocommerce-core', 'taxocommerce-core-content', 'taxocommerce-core-hierarchy', 'taxocommerce-core-menu', 'taxocommerce-core-user-guide' );
		$has_extensions = false;
		foreach ( $items as $item ) {
			if ( is_array( $item ) && isset( $item[2] ) && ! in_array( (string) $item[2], $core_pages, true ) ) {
				$has_extensions = true;
				break;
			}
		}

		// Always hide internal pages visually, while keeping WordPress' registered
		// submenu entries, callbacks and capability checks completely intact.
		// :has() hides entire rows; the a[href] rule is a browser fallback.
		$selectors = array();
		foreach ( array( 'taxocommerce-core-content', 'taxocommerce-core-hierarchy', 'taxocommerce-core-menu', 'taxocommerce-core-user-guide' ) as $slug ) {
			$selector = '#toplevel_page_taxocommerce-core .wp-submenu a[href*="page=' . $slug . '"]';
			$selectors[] = '#toplevel_page_taxocommerce-core .wp-submenu li:has(> a[href*="page=' . $slug . '"])';
			$selectors[] = $selector;
		}
		$style = implode( ',', $selectors ) . '{display:none!important;}';
		if ( ! $has_extensions ) {
			$style .= '#toplevel_page_taxocommerce-core > ul.wp-submenu{display:none!important;}';
		}
		// Use WordPress' stylesheet API; never print an unmanaged style tag.
		if ( wp_style_is( 'taxocommerce-core-admin', 'enqueued' ) ) {
			wp_add_inline_style( 'taxocommerce-core-admin', $style );
		}
	}

	/**
	 * Returns required capability.
	 *
	 * @return string
	 */
	private function capability() {
		return (string) apply_filters( 'taxocommerce_core_manage_capability', 'manage_options' );
	}

	/**
	 * Verifies current user.
	 *
	 * @return void
	 */
	private function guard() {
		if ( ! current_user_can( $this->capability() ) ) {
			wp_die( esc_html__( 'No tienes permisos para gestionar TaxoCommerce Core.', 'taxocommerce-core' ) );
		}
	}

	/**
	 * Registers one module.
	 *
	 * @param string $id   Module ID.
	 * @param array  $args Module metadata.
	 * @return bool
	 */
	public function register_module( $id, $args = array() ) {
		$id = sanitize_key( $id );
		if ( '' === $id || isset( $this->modules[ $id ] ) ) {
			return false;
		}

		$args = wp_parse_args(
			$args,
			array(
				'name'        => $id,
				'version'     => '',
				'status'      => 'active',
				'description' => '',
				'admin_url'   => '',
				'actions'     => array(),
			)
		);

		$status = sanitize_key( (string) $args['status'] );
		if ( ! in_array( $status, array( 'active', 'inactive', 'unavailable' ), true ) ) {
			$status = 'active';
		}

		$this->modules[ $id ] = array(
			'id'          => $id,
			'name'        => sanitize_text_field( (string) $args['name'] ),
			'version'     => sanitize_text_field( (string) $args['version'] ),
			'status'      => $status,
			'description' => sanitize_text_field( (string) $args['description'] ),
			'admin_url'   => esc_url_raw( (string) $args['admin_url'] ),
		);
		return true;
	}


	/** Return only modules registered by active plugins at runtime. */
	public function get_registered_modules() {
		return $this->modules;
	}

	/**
	 * Returns the built-in TaxoCommerce family catalog.
	 *
	 * Inactive extensions cannot execute hooks, so Core keeps the minimal
	 * identity needed to discover, activate, deactivate, or search for them.
	 *
	 * @return array
	 */
	public function extension_catalog() {
		return $this->family_extensions();
	}

	/**
	 * Family catalog (private source).
	 *
	 * @return array
	 */
	private function family_extensions() {
		$extensions = array(
			'import_export' => array(
				'id'          => 'import_export',
				'name'        => 'TaxoCommerce Import-Export',
				'plugin_file' => 'taxocommerce-import-export/taxocommerce-import-export.php',
				'org_slug'    => 'taxocommerce-import-export',
				'search_term'   => 'TaxoCommerce Import-Export',
				'wordpress_org' => true,
				'description' => __( 'Catalog, content, and Media import/export.', 'taxocommerce-core' ),
			),
			'suppliers' => array(
				'id'            => 'suppliers',
				'name'          => 'TaxoCommerce Suppliers',
				'plugin_file'   => 'taxocommerce-suppliers/taxocommerce-suppliers.php',
				'org_slug'      => 'taxocommerce-suppliers',
				'search_term'   => 'TaxoCommerce Suppliers',
				'wordpress_org' => false,
				'admin_url'     => admin_url( 'admin.php?page=taxocommerce-suppliers' ),
				'description'   => __( 'Supplier catalogue, purchasing, prices, and external images.', 'taxocommerce-core' ),
			),
			'database' => array(
				'id'            => 'database',
				'name'          => 'TaxoCommerce Database',
				'plugin_file'   => 'taxocommerce-database/taxocommerce-database.php',
				'org_slug'      => 'taxocommerce-database',
				'search_term'   => 'TaxoCommerce Database',
				'wordpress_org' => false,
				'admin_url'     => admin_url( 'admin.php?page=taxocommerce-database' ),
				'description'   => __( 'MySQL diagnostics, tables, backups, and controlled maintenance in an independent plugin.', 'taxocommerce-core' ),
			),
			'images' => array(
				'id'            => 'images',
				'name'          => 'TaxoCommerce Images',
				'plugin_file'   => 'taxocommerce-images/taxocommerce-images.php',
				'org_slug'      => 'taxocommerce-images',
				'search_term'   => 'TaxoCommerce Images',
				'wordpress_org' => true,
				'description'   => __( 'Image management, auditing, thumbnails, metadata, and optimization.', 'taxocommerce-core' ),
			),
			'system_check' => array(
				'id'            => 'system_check',
				'name'          => 'TaxoCommerce System Check',
				'plugin_file'   => 'taxocommerce-system-check/taxocommerce-system-check.php',
				'org_slug'      => 'taxocommerce-system-check',
				'search_term'   => 'TaxoCommerce System Check',
				'wordpress_org' => true,
				'description'   => __( 'Server, WordPress, and plugin validation diagnostics with reports and optional email notifications.', 'taxocommerce-core' ),
			),
			'templates' => array(
				'id'            => 'templates',
				'name'          => 'TaxoCommerce Templates',
				'plugin_file'   => 'taxocommerce-templates/taxocommerce-templates.php',
				'org_slug'      => 'taxocommerce-templates',
				'search_term'   => 'TaxoCommerce Templates',
				'wordpress_org' => true,
				'description'   => __( 'Editorial roles, automatic assignment, and template management.', 'taxocommerce-core' ),
			),
			'dependiente' => array(
				'id'            => 'dependiente',
				'name'          => 'TaxoCommerce Assistant',
				'plugin_file'   => 'taxocommerce-dependiente/taxocommerce-dependiente.php',
				'org_slug'      => 'taxocommerce-dependiente',
				'search_term'   => 'TaxoCommerce Assistant',
				'wordpress_org' => true,
				'description'   => __( 'Independent ten-lesson academy and catalog assistant, without automatic recycling.', 'taxocommerce-core' ),
			),
			'faqs' => array(
				'id'            => 'faqs',
				'name'          => 'TaxoCommerce FAQs',
				'plugin_file'   => 'taxocommerce-faqs/taxocommerce-faqs.php',
				'org_slug'      => 'taxocommerce-faqs',
				'search_term'   => 'TaxoCommerce FAQs',
				'wordpress_org' => true,
				'description'   => __( 'FAQs, manual editing, CSV, indicators, and Vocabulary integration.', 'taxocommerce-core' ),
			),
			'vocabulary' => array(
				'id'            => 'vocabulary',
				'name'          => 'TaxoCommerce Vocabulary',
				'plugin_file'   => 'taxocommerce-vocabulary/taxocommerce-vocabulary.php',
				'org_slug'      => 'taxocommerce-vocabulary',
				'search_term'   => 'TaxoCommerce Vocabulary',
				'wordpress_org' => true,
				'admin_url'     => admin_url( 'admin.php?page=taxocommerce-vocabulary' ),
				'description'   => __( 'Cross-catalog tag and attribute inventory with automatic classification and local execution.', 'taxocommerce-core' ),
			),
			'distribution' => array(
				'id'            => 'distribution',
				'name'          => 'TaxoCommerce Distribution',
				'plugin_file'   => 'taxocommerce-distribution/taxocommerce-distribution.php',
				'org_slug'      => 'taxocommerce-distribution',
				'search_term'   => 'TaxoCommerce Distribution',
				'wordpress_org' => false,
				'admin_url'     => admin_url( 'admin.php?page=taxocommerce-distribution' ),
				'description'   => __( 'Commercial feeds and XML sitemaps with bounded block-based publishing.', 'taxocommerce-core' ),
			),
			'social' => array(
				'id'            => 'social',
				'name'          => 'TaxoCommerce Social',
				'plugin_file'   => 'taxocommerce-social/taxocommerce-social.php',
				'org_slug'      => 'taxocommerce-social',
				'search_term'   => 'TaxoCommerce Social',
				'wordpress_org' => false,
				'admin_url'     => admin_url( 'admin.php?page=taxocommerce-social' ),
				'description'   => __( 'Social network connections, publishing templates, queue, history, and standalone publication calendar.', 'taxocommerce-core' ),
			),
			'campaigns' => array(
				'id'            => 'campaigns',
				'name'          => 'TaxoCommerce Campaigns',
				'plugin_file'   => 'taxocommerce-campaigns/taxocommerce-campaigns.php',
				'org_slug'      => 'taxocommerce-campaigns',
				'search_term'   => 'TaxoCommerce Campaigns',
				'wordpress_org' => false,
				'admin_url'     => admin_url( 'admin.php?page=taxocommerce-campaigns' ),
				'description'   => __( 'Offer campaigns, promotional prices, banners, social offer publication, and a standalone commercial calendar.', 'taxocommerce-core' ),
			),
			'google_bing' => array(
				'id'            => 'google_bing',
				'name'          => 'TaxoCommerce Insights',
				'plugin_file'   => 'taxocommerce-google-bing/taxocommerce-google-bing.php',
				'org_slug'      => 'taxocommerce-google-bing',
				'search_term'   => 'TaxoCommerce Google Bing',
				'wordpress_org' => true,
				'description'   => __( 'Search, Analytics, Bing, Trends, market, opportunity, and executive reports.', 'taxocommerce-core' ),
			),
			'scrappers' => array(
				'id'            => 'scrappers',
				'name'          => 'TaxoCommerce Scrappers',
				'plugin_file'   => 'taxocommerce-scrappers/taxocommerce-scrappers.php',
				'org_slug'      => 'taxocommerce-scrappers',
				'search_term'   => 'TaxoCommerce Scrappers',
				'wordpress_org' => false,
				'admin_url'     => admin_url( 'admin.php?page=taxocommerce-scrappers' ),
				'description'   => __( 'SerpApi/ScraperAPI connections with Ojeador market intelligence and Ingeniero technical research.', 'taxocommerce-core' ),
			),
			'logistica' => array(
				'id'            => 'logistica',
				'name'          => 'TaxoCommerce Logistics',
				'plugin_file'   => 'taxocommerce-logistica/taxocommerce-logistica.php',
				'org_slug'      => 'taxocommerce-logistica',
				'search_term'   => 'TaxoCommerce Logistics',
				'wordpress_org' => false,
				'admin_url'     => admin_url( 'admin.php?page=taxocommerce-logistica' ),
				'description'   => __( 'WooCommerce order weights, dimensions, and shipping-cost rules.', 'taxocommerce-core' ),
			),
			'facturacion' => array(
				'id'            => 'facturacion',
				'name'          => 'TaxoCommerce Billing',
				'plugin_file'   => 'taxocommerce-facturacion/taxocommerce-facturacion.php',
				'org_slug'      => 'taxocommerce-facturacion',
				'search_term'   => 'TaxoCommerce Billing',
				'wordpress_org' => false,
				'admin_url'     => admin_url( 'admin.php?page=seo-facturas' ),
				'description'   => __( 'Invoices, proformas, and PDF quotations connected to WooCommerce.', 'taxocommerce-core' ),
			),
			'redirects' => array(
				'id'            => 'redirects',
				'name'          => 'TaxoCommerce Redirects',
				'plugin_file'   => 'taxocommerce-redirects/taxocommerce-redirects.php',
				'org_slug'      => 'taxocommerce-redirects',
				'search_term'   => 'TaxoCommerce Redirects',
				'wordpress_org' => true,
				'admin_url'     => admin_url( 'admin.php?page=taxocommerce-redirects' ),
				'description'   => __( '301/302 redirects and assisted content deletion.', 'taxocommerce-core' ),
			),
			'tasks' => array(
				'id'            => 'tasks',
				'name'          => 'TaxoCommerce Tasks',
				'plugin_file'   => 'taxocommerce-tasks/taxocommerce-tasks.php',
				'org_slug'      => 'taxocommerce-tasks',
				'search_term'   => 'TaxoCommerce Tasks',
				'wordpress_org' => false,
				'admin_url'     => admin_url( 'admin.php?page=taxocommerce-tasks' ),
				'description'   => __( 'Private task agenda, calendar, categories and indicators.', 'taxocommerce-core' ),
			),
			'auditor' => array(
				'id'            => 'auditor',
				'name'          => 'TaxoCommerce Auditor',
				'plugin_file'   => 'taxocommerce-auditor/taxocommerce-auditor.php',
				'org_slug'      => 'taxocommerce-auditor',
				'search_term'   => 'TaxoCommerce Auditor',
				'wordpress_org' => true,
				'description'   => __( 'Independent editorial and content quality auditing.', 'taxocommerce-core' ),
			),
		);

		/**
		 * Filters the TaxoCommerce family catalog.
		 *
		 * Active extensions may add discovery metadata for related plugins.
		 *
		 * @param array $extensions Family extensions.
		 */
		return (array) apply_filters( 'taxocommerce_core_family_extensions', $extensions );
	}

	/**
	 * Returns the local TaxoCommerce package directory.
	 *
	 * Packages are intentionally stored outside the plugin directory so Core
	 * updates do not remove them.
	 *
	 * @return string
	 */
	private function local_package_directory() {
		$uploads = wp_upload_dir();
		if ( ! empty( $uploads['error'] ) || empty( $uploads['basedir'] ) ) {
			return '';
		}

		return trailingslashit( (string) $uploads['basedir'] ) . 'taxocommerce-core/plugins';
	}

	/**
	 * Returns the expected local ZIP for an extension when it exists.
	 *
	 * The default file name is the family slug plus ".zip", for example
	 * taxocommerce-auditor.zip. Extensions may override it with zip_filename.
	 *
	 * @param array $extension Extension metadata.
	 * @return string
	 */
	private function local_package_path( $extension ) {
		$directory = $this->local_package_directory();
		if ( '' === $directory ) {
			return '';
		}

		$slug = sanitize_key( (string) ( $extension['org_slug'] ?? '' ) );
		$file = sanitize_file_name( (string) ( $extension['zip_filename'] ?? ( $slug ? $slug . '.zip' : '' ) ) );
		if ( '' === $file ) {
			return '';
		}

		$path = trailingslashit( $directory ) . $file;
		return is_file( $path ) && is_readable( $path ) ? $path : '';
	}

	/**
	 * Installs one known TaxoCommerce extension from the local uploads package.
	 *
	 * @return void
	 */
	public function install_extension() {
		$this->guard();

		if ( ! current_user_can( 'install_plugins' ) ) {
			wp_die( esc_html__( 'No tienes permisos para instalar plugins.', 'taxocommerce-core' ) );
		}

		check_admin_referer( 'taxocommerce_core_extension_action', 'taxocommerce_core_nonce' );

		$extension_id = isset( $_POST['extension_id'] ) ? sanitize_key( wp_unslash( $_POST['extension_id'] ) ) : '';
		$catalog      = $this->family_extensions();

		if ( ! isset( $catalog[ $extension_id ]['plugin_file'] ) ) {
			wp_die( esc_html__( 'Unknown TaxoCommerce extension.', 'taxocommerce-core' ) );
		}

		$extension   = (array) $catalog[ $extension_id ];
		$plugin_file = sanitize_text_field( (string) $extension['plugin_file'] );
		if ( $this->installed_plugin_data( $plugin_file ) ) {
			$this->redirect_extension_result( 'error', __( 'The extension is already installed.', 'taxocommerce-core' ) );
		}

		$package = $this->local_package_path( $extension );
		if ( '' === $package ) {
			$this->redirect_extension_result(
				'error',
				__( 'The local ZIP for this extension was not found in uploads/taxocommerce-core/plugins.', 'taxocommerce-core' )
			);
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

		$skin     = new Automatic_Upgrader_Skin();
		$upgrader = new Plugin_Upgrader( $skin );
		$result   = $upgrader->install( $package );

		if ( is_wp_error( $result ) ) {
			$this->redirect_extension_result( 'error', $result->get_error_message() );
		}
		if ( false === $result ) {
			$this->redirect_extension_result( 'error', __( 'WordPress could not install the local ZIP.', 'taxocommerce-core' ) );
		}

		wp_clean_plugins_cache( true );
		if ( ! $this->installed_plugin_data( $plugin_file ) ) {
			$this->redirect_extension_result(
				'error',
				__( 'The ZIP was processed, but it does not contain the expected structure for this extension.', 'taxocommerce-core' )
			);
		}

		$this->redirect_extension_result( 'ok', __( 'Extension installed from the local ZIP. You can activate it now.', 'taxocommerce-core' ) );
	}


	/**
	 * Installs one known TaxoCommerce extension from a ZIP uploaded in Core.
	 *
	 * The ZIP must contain the exact plugin basename declared in the family
	 * catalog, so a package cannot accidentally be installed from another row.
	 *
	 * @return void
	 */
	public function upload_extension() {
		$this->guard();

		if ( ! current_user_can( 'install_plugins' ) ) {
			wp_die( esc_html__( 'No tienes permisos para instalar plugins.', 'taxocommerce-core' ) );
		}

		check_admin_referer( 'taxocommerce_core_extension_action', 'taxocommerce_core_nonce' );

		$extension_id = isset( $_POST['extension_id'] ) ? sanitize_key( wp_unslash( $_POST['extension_id'] ) ) : '';
		$catalog      = $this->family_extensions();

		if ( ! isset( $catalog[ $extension_id ]['plugin_file'] ) ) {
			wp_die( esc_html__( 'Unknown TaxoCommerce extension.', 'taxocommerce-core' ) );
		}

		$extension   = (array) $catalog[ $extension_id ];
		$plugin_file = sanitize_text_field( (string) $extension['plugin_file'] );

		if ( $this->installed_plugin_data( $plugin_file ) ) {
			$this->redirect_extension_result( 'error', __( 'The extension is already installed.', 'taxocommerce-core' ) );
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- File upload is validated below before use.
		$file = isset( $_FILES['plugin_zip'] ) && is_array( $_FILES['plugin_zip'] ) ? $_FILES['plugin_zip'] : array();
		$error = isset( $file['error'] ) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;

		if ( UPLOAD_ERR_OK !== $error ) {
			$this->redirect_extension_result( 'error', __( 'A valid ZIP was not received.', 'taxocommerce-core' ) );
		}

		$name     = isset( $file['name'] ) ? sanitize_file_name( (string) $file['name'] ) : '';
		$tmp_name = isset( $file['tmp_name'] ) ? (string) $file['tmp_name'] : '';
		$size     = isset( $file['size'] ) ? (int) $file['size'] : 0;

		if ( '' === $name || ! preg_match( '/\.zip$/i', $name ) || '' === $tmp_name || $size < 1 || ! is_uploaded_file( $tmp_name ) ) {
			$this->redirect_extension_result( 'error', __( 'The file must be a correctly uploaded ZIP.', 'taxocommerce-core' ) );
		}

		$max_size = (int) wp_max_upload_size();
		if ( $max_size > 0 && $size > $max_size ) {
			$this->redirect_extension_result( 'error', __( 'The ZIP exceeds the maximum upload size allowed by WordPress.', 'taxocommerce-core' ) );
		}

		if ( ! class_exists( 'ZipArchive' ) ) {
			$this->redirect_extension_result( 'error', __( 'ZipArchive is not available on the server to validate the package.', 'taxocommerce-core' ) );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

// Let WordPress validate and store the ZIP using the standard upload API.
		// test_form=false is required because this is a protected admin-post action.
		$uploaded = wp_handle_upload(
			$file,
			array(
				'test_form' => false,
				'mimes'     => array( 'zip' => 'application/zip' ),
			)
		);
		if ( ! is_array( $uploaded ) || ! empty( $uploaded['error'] ) || empty( $uploaded['file'] ) ) {
			$this->redirect_extension_result( 'error', __( 'WordPress could not prepare the ZIP temporarily.', 'taxocommerce-core' ) );
		}
		$temp_file = (string) $uploaded['file'];

		$zip   = new ZipArchive();
		$valid = false;

		if ( true === $zip->open( $temp_file ) ) {
			for ( $index = 0; $index < $zip->numFiles; $index++ ) {
				$entry = str_replace( '\\', '/', ltrim( (string) $zip->getNameIndex( $index ), '/' ) );
				if ( $plugin_file === $entry ) {
					$valid = true;
					break;
				}
			}
			$zip->close();
		}

		if ( ! $valid ) {
			wp_delete_file( $temp_file );
			$this->redirect_extension_result(
				'error',
				sprintf(
					/* translators: %s: expected plugin basename. */
					__( 'The ZIP does not match this extension. It must contain %s.', 'taxocommerce-core' ),
					$plugin_file
				)
			);
		}

		$skin     = new Automatic_Upgrader_Skin();
		$upgrader = new Plugin_Upgrader( $skin );
		$result   = $upgrader->install( $temp_file );
		wp_delete_file( $temp_file );

		if ( is_wp_error( $result ) ) {
			$this->redirect_extension_result( 'error', $result->get_error_message() );
		}
		if ( false === $result ) {
			$this->redirect_extension_result( 'error', __( 'WordPress could not install the uploaded ZIP.', 'taxocommerce-core' ) );
		}

		wp_clean_plugins_cache( true );
		if ( ! $this->installed_plugin_data( $plugin_file ) ) {
			$this->redirect_extension_result(
				'error',
				__( 'The ZIP was processed, but the extension was not installed at the expected path.', 'taxocommerce-core' )
			);
		}

		$this->redirect_extension_result( 'ok', __( 'Extension installed from ZIP. You can activate it now.', 'taxocommerce-core' ) );
	}

	/**
	 * Returns installed plugin data for a family extension.
	 *
	 * @param string $plugin_file Plugin basename.
	 * @return array
	 */
	private function installed_plugin_data( $plugin_file ) {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugins = get_plugins();
		return isset( $plugins[ $plugin_file ] ) && is_array( $plugins[ $plugin_file ] )
			? $plugins[ $plugin_file ]
			: array();
	}

	/**
	 * Whether a family extension is active.
	 *
	 * @param string $plugin_file Plugin basename.
	 * @return bool
	 */
	private function extension_is_active( $plugin_file ) {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		return is_plugin_active( $plugin_file );
	}

	/**
	 * Activates a known TaxoCommerce family extension.
	 *
	 * @return void
	 */
	public function activate_extension() {
		$this->guard();

		if ( ! current_user_can( 'activate_plugins' ) ) {
			wp_die( esc_html__( 'No tienes permisos para activar plugins.', 'taxocommerce-core' ) );
		}

		check_admin_referer( 'taxocommerce_core_extension_action', 'taxocommerce_core_nonce' );

		$extension_id = isset( $_POST['extension_id'] ) ? sanitize_key( wp_unslash( $_POST['extension_id'] ) ) : '';
		$catalog      = $this->family_extensions();

		if ( ! isset( $catalog[ $extension_id ]['plugin_file'] ) ) {
			wp_die( esc_html__( 'Unknown TaxoCommerce extension.', 'taxocommerce-core' ) );
		}

		$plugin_file = sanitize_text_field( (string) $catalog[ $extension_id ]['plugin_file'] );
		if ( ! $this->installed_plugin_data( $plugin_file ) ) {
			$this->redirect_extension_result( 'error', __( 'The extension is not installed yet.', 'taxocommerce-core' ) );
		}

		if ( ! function_exists( 'activate_plugin' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$result = activate_plugin( $plugin_file, '', false, false );
		if ( is_wp_error( $result ) ) {
			$this->redirect_extension_result( 'error', $result->get_error_message() );
		}

		$this->redirect_extension_result( 'ok', __( 'Extension activated.', 'taxocommerce-core' ) );
	}

	/**
	 * Deactivates a known TaxoCommerce family extension.
	 *
	 * @return void
	 */
	public function deactivate_extension() {
		$this->guard();

		if ( ! current_user_can( 'activate_plugins' ) ) {
			wp_die( esc_html__( 'No tienes permisos para desactivar plugins.', 'taxocommerce-core' ) );
		}

		check_admin_referer( 'taxocommerce_core_extension_action', 'taxocommerce_core_nonce' );

		$extension_id = isset( $_POST['extension_id'] ) ? sanitize_key( wp_unslash( $_POST['extension_id'] ) ) : '';
		$catalog      = $this->family_extensions();

		if ( ! isset( $catalog[ $extension_id ]['plugin_file'] ) ) {
			wp_die( esc_html__( 'Unknown TaxoCommerce extension.', 'taxocommerce-core' ) );
		}

		$plugin_file = sanitize_text_field( (string) $catalog[ $extension_id ]['plugin_file'] );
		if ( ! function_exists( 'deactivate_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		deactivate_plugins( $plugin_file, false, false );
		$this->redirect_extension_result( 'ok', __( 'Extension deactivated.', 'taxocommerce-core' ) );
	}

	/**
	 * Redirects to the extension registry after an action.
	 *
	 * @param string $result  Result key.
	 * @param string $message Message.
	 * @return void
	 */
	private function redirect_extension_result( $result, $message ) {
		$url = add_query_arg(
			array(
				'page'       => 'taxocommerce-core',
				'tc_result'  => sanitize_key( $result ),
				'tc_message' => sanitize_text_field( $message ),
			),
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $url . '#tcc-extensions' );
		exit;
	}

	/**
	 * Returns logger.
	 *
	 * @return TaxoCommerce_Core_Logger|null
	 */
	public function logger() {
		return $this->logger;
	}

	/**
	 * Returns content manager.
	 *
	 * @return TaxoCommerce_Core_Content_Manager|null
	 */
	public function content() {
		return $this->content;
	}

	/**
	 * Returns hierarchy manager.
	 *
	 * @return TaxoCommerce_Core_Hierarchy|null
	 */
	public function hierarchy() {
		return $this->hierarchy;
	}

	/**
	 * Returns menu manager.
	 *
	 * @return TaxoCommerce_Core_Menu|null
	 */
	public function menu() {
		return $this->menu;
	}

	/**
	 * Detects legacy plugin.
	 *
	 * @return bool
	 */
	public function legacy_detected() {
		return defined( 'SEO_SYSTEM_VERSION' );
	}

	/**
	 * Detects WooCommerce.
	 *
	 * @return bool
	 */
	public function woocommerce_detected() {
		return class_exists( 'WooCommerce' );
	}

	/**
	 * Identify TaxoCommerce screens without styling unrelated WordPress pages.
	 *
	 * @param string $hook WordPress screen hook.
	 * @return bool
	 */
	private function is_family_admin_screen( $hook = '' ) {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$screen_id = $screen ? (string) $screen->id : '';
		$hook = (string) $hook;
		// Explicit allowlist: never style TaxoCommerce SEO or another plugin
		// merely because its slug happens to contain "taxocommerce".
		$slugs = array(
			'taxocommerce-core',
			'taxocommerce-images',
			'taxocommerce-import-export',
			'taxocommerce-system-check',
			'taxocommerce-vocabulary',
			'tcs-system-status',
			'tcs-plugin-validation',
			'tcs-url-audit',
		);
		foreach ( array( $screen_id, $hook ) as $candidate ) {
			foreach ( $slugs as $slug ) {
				if ( $candidate === $slug
					|| $candidate === 'toplevel_page_' . $slug
					|| false !== strpos( $candidate, '_page_' . $slug ) ) {
					return true;
				}
			}
		}
		return false;
	}
	/**
	 * One shared stylesheet for every screen owned by the plugin family.
	 *
	 * @param string $hook WordPress page hook.
	 * @return void
	 */
	public function enqueue_family_styles( $hook ) {
		if ( ! $this->is_family_admin_screen( $hook ) ) {
			return;
		}
		wp_enqueue_style(
			'taxocommerce-core-admin',
			plugins_url( 'assets/admin.css', TAXOCOMMERCE_CORE_FILE ),
			array(),
			// File time in the version: browsers drop the cached copy whenever the CSS changes.
			TAXOCOMMERCE_CORE_VERSION . '.' . (int) filemtime( plugin_dir_path( TAXOCOMMERCE_CORE_FILE ) . 'assets/admin.css' )
		);
	}

	/**
	 * Scope shared styling to the family instead of WordPress itself.
	 *
	 * @param string $classes Admin body classes.
	 * @return string
	 */
	public function family_body_class( $classes ) {
		return $this->is_family_admin_screen() ? $classes . ' tcc-family-admin' : $classes;
	}

	/**
	 * Core dashboard.
	 *
	 * @return void
	 */
	public function render_dashboard() {
		$this->guard();
		// Auto Test is a Core dashboard tab, never a separate WordPress menu item.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only tab navigation.
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'overview';
		if ( in_array( $tab, array( 'auto-test', 'autocheck' ), true ) ) {
			TaxoCommerce_Core_Auto_Test::render();
			return;
		}
		TaxoCommerce_Core_Dashboard::render(
			$this->modules,
			$this->woocommerce_detected(),
			array( $this, 'render_extension_catalog' )
		);
	}

	/**
	 * Reports page at the same menu depth as Content.
	 * Reuses the cached, independent Core architecture report.
	 *
	 * @return void
	 */
	public function render_reports() {
		$this->guard();
		echo '<div class="wrap tcc-family-screen">';
		echo '<header class="tcc-page-header">';
		echo '<div class="tcc-page-header__content"><h1>' . esc_html__( 'Informes', 'taxocommerce-core' ) . '</h1>';
		echo '<p>' . esc_html__( 'Distribución de contenidos y arquitectura SEO.', 'taxocommerce-core' ) . '</p></div>';
		echo '</header>';
		TaxoCommerce_Core_Architecture_Report::render();
		echo '</div>';
	}

	/**
	 * Redirect old dedicated Auto Test URLs into the Core internal tab.
	 * The former submenu is intentionally no longer registered.
	 *
	 * @return void
	 */
	public function redirect_old_auto_test_page() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only navigation.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( TaxoCommerce_Core_Auto_Test::PAGE_SLUG !== $page ) {
			return;
		}
		$this->guard();
		wp_safe_redirect( add_query_arg( array( 'page' => 'taxocommerce-core', 'tab' => 'autocheck' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Redirect old Ampliar familia bookmarks to the single Extensions page.
	 *
	 * @return void
	 */
	public function redirect_legacy_family_page() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin navigation.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( 'taxocommerce-core-family' !== $page ) {
			return;
		}
		if ( ! current_user_can( $this->capability() ) ) {
			wp_die( esc_html__( 'No tienes permisos para gestionar extensiones.', 'taxocommerce-core' ) );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=taxocommerce-core#tcc-extensions' ) );
		exit;
	}

	/**
	 * Keep historical Extensions bookmarks functional without a menu entry.
	 * admin_init runs before WordPress resolves the removed submenu page.
	 */
	public function redirect_old_extensions_page() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only navigation.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( 'taxocommerce-core-modules' !== $page ) {
			return;
		}
		$this->guard();
		wp_safe_redirect( admin_url( 'admin.php?page=taxocommerce-core#tcc-extensions' ) );
		exit;
	}

	/**
	 * Redirect links from the former dedicated Anomalies screen to Content.
	 *
	 * @return void
	 */
	public function redirect_legacy_anomalies_page() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only navigation.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( 'taxocommerce-core-anomalies' !== $page ) {
			return;
		}

		$this->guard();
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => TaxoCommerce_Core_Content_Manager::PAGE_SLUG,
					'tc_type' => 'anomalies',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/** Redirect old Reports URLs to the unified Content summary. */
	public function redirect_legacy_reports_page() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin navigation.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( TaxoCommerce_Core_Architecture_Report::PAGE_SLUG !== $page ) {
			return;
		}
		$this->guard();
		wp_safe_redirect( add_query_arg( array( 'page' => TaxoCommerce_Core_Content_Manager::PAGE_SLUG, 'tc_type' => 'summary' ), admin_url( 'admin.php' ) ) . '#tc-architecture-report' );
		exit;
	}

	/**
	 * Modules page.
	 *
	 * @return void
	 */
	/**
	 * Compatibility callback. The old dedicated page is now a dashboard anchor.
	 */
	public function render_modules() {
		$this->render_dashboard();
	}

	/**
	 * Display the canonical extension manager as cards on the Core homepage.
	 * Uses WordPress's installed plugin list and the same protected POST
	 * handlers as the former standalone Extensions table.
	 */
	public function render_extension_catalog() {
		$this->guard();

		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$installed_plugins = get_plugins();
		$catalog = $this->family_extensions();
		$items = array();
		$active_count = 0;
		$inactive_count = 0;
		$available_count = 0;
		foreach ( $catalog as $extension_id => $extension ) {
			$extension = (array) $extension;
			$plugin_file = sanitize_text_field( (string) ( $extension['plugin_file'] ?? '' ) );
			$plugin_data = isset( $installed_plugins[ $plugin_file ] ) ? (array) $installed_plugins[ $plugin_file ] : array();
			$installed = ! empty( $plugin_data );
			$active = $installed && $this->extension_is_active( $plugin_file );
			$local_package = $this->local_package_path( $extension );
			$runtime = isset( $this->modules[ $extension_id ] ) ? (array) $this->modules[ $extension_id ] : array();
			$version = (string) ( $plugin_data['Version'] ?? $runtime['version'] ?? '' );
			$admin_url = ! empty( $runtime['admin_url'] ) ? (string) $runtime['admin_url'] : (string) ( $extension['admin_url'] ?? '' );
			if ( $active ) {
				$active_count++;
			} elseif ( $installed ) {
				$inactive_count++;
			} else {
				$available_count++;
			}
			$items[] = array(
				'id' => sanitize_key( (string) $extension_id ),
				'name' => (string) ( $extension['name'] ?? $extension_id ),
				'description' => (string) ( $extension['description'] ?? '' ),
				'version' => $version,
				'installed' => $installed,
				'active' => $active,
				'zip' => (bool) $local_package,
				'suite_dir' => TaxoCommerce_Core_Suite::module_dir( $extension ),
				'admin_url' => $admin_url,
				'search_url' => add_query_arg(
					array( 's' => (string) ( $extension['search_term'] ?? $extension['name'] ?? $extension_id ), 'tab' => 'search', 'type' => 'term' ),
					admin_url( 'plugin-install.php' )
				),
			);
		}
		$result = isset( $_GET['tc_result'] ) ? sanitize_key( wp_unslash( $_GET['tc_result'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only notice.
		$message = isset( $_GET['tc_message'] ) ? sanitize_text_field( wp_unslash( $_GET['tc_message'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only notice.
		?>
		<section class="tcc-section tcc-extensions" id="tcc-extensions" aria-labelledby="tcc-extensions-title">
			<div class="tcc-section__header tcc-extensions__header">
				<div>
					<h2 id="tcc-extensions-title"><?php esc_html_e( 'Extensions', 'taxocommerce-core' ); ?></h2>
					<p><?php esc_html_e( 'Gestiona cada plugin independiente desde su tarjeta.', 'taxocommerce-core' ); ?></p>
				</div>
				<div class="tcc-extensions__summary">
					<span><?php echo esc_html( sprintf( /* translators: %d: número de extensiones activas. */ __( '%d activas', 'taxocommerce-core' ), $active_count ) ); ?></span>
					<span><?php echo esc_html( sprintf( /* translators: %d: número de extensiones inactivas. */ __( '%d inactivas', 'taxocommerce-core' ), $inactive_count ) ); ?></span>
					<span><?php echo esc_html( sprintf( /* translators: %d: número de extensiones no instaladas. */ __( '%d no instaladas', 'taxocommerce-core' ), $available_count ) ); ?></span>
				</div>
			</div>

			<?php if ( $message && in_array( $result, array( 'ok', 'error' ), true ) ) : ?>
				<div class="notice <?php echo 'ok' === $result ? 'notice-success' : 'notice-error'; ?> inline"><p><?php echo esc_html( $message ); ?></p></div>
			<?php endif; ?>

			<div class="tcc-extensions-grid">
				<?php foreach ( $items as $item ) : ?>
					<?php
					$status = $item['active'] ? __( 'Activa', 'taxocommerce-core' ) : ( $item['installed'] ? __( 'Instalada · inactiva', 'taxocommerce-core' ) : __( 'No instalada', 'taxocommerce-core' ) );
					$tone = $item['active'] ? 'success' : ( $item['installed'] ? 'warning' : 'muted' );
					?>
					<article class="tcc-extension-card" aria-label="<?php echo esc_attr( $item['name'] ); ?>">
						<div class="tcc-extension-card__top">
							<span class="dashicons dashicons-admin-plugins" aria-hidden="true"></span>
							<span class="tcc-status tcc-status--<?php echo esc_attr( $tone ); ?>"><?php echo esc_html( $status ); ?></span>
						</div>
						<h3><?php echo esc_html( $item['name'] ); ?></h3>
						<p class="tcc-extension-card__description"><?php echo esc_html( $item['description'] ); ?></p>
						<div class="tcc-extension-card__meta">
							<span><?php esc_html_e( 'Versión', 'taxocommerce-core' ); ?>: <strong><?php echo esc_html( $item['version'] ?: '—' ); ?></strong></span>
							<?php if ( $item['zip'] && ! $item['installed'] ) : ?>
								<span class="tcc-extension-card__zip"><?php esc_html_e( 'ZIP local disponible', 'taxocommerce-core' ); ?></span>
							<?php endif; ?>
						</div>
						<div class="tcc-extension-card__actions">
							<?php if ( $item['active'] ) : ?>
								<?php if ( $item['admin_url'] ) : ?>
									<a class="button button-primary button-small" href="<?php echo esc_url( $item['admin_url'] ); ?>"><?php esc_html_e( 'Abrir', 'taxocommerce-core' ); ?></a>
								<?php endif; ?>
								<?php if ( current_user_can( 'activate_plugins' ) ) : ?>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
										<input type="hidden" name="action" value="taxocommerce_core_deactivate_extension">
										<input type="hidden" name="extension_id" value="<?php echo esc_attr( $item['id'] ); ?>">
										<?php wp_nonce_field( 'taxocommerce_core_extension_action', 'taxocommerce_core_nonce' ); ?>
										<button class="button button-small" type="submit"><?php esc_html_e( 'Desactivar', 'taxocommerce-core' ); ?></button>
									</form>
								<?php endif; ?>
								<?php TaxoCommerce_Core_Suite::render_remove_button( $item ); ?>
							<?php elseif ( $item['installed'] ) : ?>
								<?php if ( current_user_can( 'activate_plugins' ) ) : ?>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
										<input type="hidden" name="action" value="taxocommerce_core_activate_extension">
										<input type="hidden" name="extension_id" value="<?php echo esc_attr( $item['id'] ); ?>">
										<?php wp_nonce_field( 'taxocommerce_core_extension_action', 'taxocommerce_core_nonce' ); ?>
										<button class="button button-primary button-small" type="submit"><?php esc_html_e( 'Activar', 'taxocommerce-core' ); ?></button>
									</form>
								<?php endif; ?>
								<?php TaxoCommerce_Core_Suite::render_remove_button( $item ); ?>
							<?php else : ?>
								<?php TaxoCommerce_Core_Suite::render_download_button( $item ); ?>
								<?php if ( empty( $item['suite_dir'] ) ) : ?>
									<a class="button button-small" href="<?php echo esc_url( $item['search_url'] ); ?>"><?php esc_html_e( 'Buscar en WordPress.org', 'taxocommerce-core' ); ?></a>
								<?php endif; ?>
								<?php if ( $item['zip'] && current_user_can( 'install_plugins' ) ) : ?>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
										<input type="hidden" name="action" value="taxocommerce_core_install_extension">
										<input type="hidden" name="extension_id" value="<?php echo esc_attr( $item['id'] ); ?>">
										<?php wp_nonce_field( 'taxocommerce_core_extension_action', 'taxocommerce_core_nonce' ); ?>
										<button class="button button-primary button-small" type="submit"><?php esc_html_e( 'Instalar ZIP local', 'taxocommerce-core' ); ?></button>
									</form>
								<?php endif; ?>
								<?php if ( current_user_can( 'install_plugins' ) ) : ?>
									<details class="tcc-extension-card__upload">
										<summary><?php esc_html_e( 'Instalar otro ZIP', 'taxocommerce-core' ); ?></summary>
										<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
											<input type="hidden" name="action" value="taxocommerce_core_upload_extension">
											<input type="hidden" name="extension_id" value="<?php echo esc_attr( $item['id'] ); ?>">
											<?php wp_nonce_field( 'taxocommerce_core_extension_action', 'taxocommerce_core_nonce' ); ?>
											<label><?php esc_html_e( 'Archivo ZIP', 'taxocommerce-core' ); ?>
												<input type="file" name="plugin_zip" accept=".zip,application/zip" required>
											</label>
											<button class="button button-primary button-small" type="submit"><?php esc_html_e( 'Instalar ZIP', 'taxocommerce-core' ); ?></button>
										</form>
									</details>
								<?php endif; ?>
							<?php endif; ?>
						</div>
					</article>
				<?php endforeach; ?>
			</div>
		</section>
		<?php
	}

}
