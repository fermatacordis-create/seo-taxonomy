<?php
/**
 * Focused, responsive WordPress administration dashboard for TaxoCommerce Core.
 *
 * Presentation only: no data migration, background jobs or stored state.
 *
 * @package TaxoCommerceCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the Core home screen without mixing UI with plugin service logic.
 */
final class TaxoCommerce_Core_Dashboard {

	/**
	 * Render the Core dashboard with live module/service counts.
	 *
	 * @param array $modules     Registered modules.
		 * @param bool  $woocommerce Whether WooCommerce is loaded.
	 * @return void
	 */
	public static function render( $modules, $woocommerce, $extensions_renderer = null ) {
		$modules     = is_array( $modules ) ? $modules : array();
		$woocommerce = (bool) $woocommerce;

		$metrics = array(
			array(
				'icon'  => 'dashicons-admin-generic',
				'label' => __( 'Versión del Core', 'taxocommerce-core' ),
				'value' => TAXOCOMMERCE_CORE_VERSION,
				'tone'  => 'neutral',
			),
			array(
				'icon'  => 'dashicons-cart',
				'label' => __( 'WooCommerce', 'taxocommerce-core' ),
				'value' => $woocommerce ? __( 'Detectado', 'taxocommerce-core' ) : __( 'No detectado', 'taxocommerce-core' ),
				'tone'  => $woocommerce ? 'success' : 'warning',
			),
			array(
				'icon'  => 'dashicons-admin-plugins',
				'label' => __( 'Extensiones registradas', 'taxocommerce-core' ),
				'value' => number_format_i18n( count( $modules ) ),
				'tone'  => 'neutral',
			),
		);

		$navigation = array(
			array(
				'title'       => __( 'User Guide', 'taxocommerce-core' ),
				'icon'        => 'dashicons-book',
				'description' => __( 'Aprende para qué sirve Core y cómo utilizar los contenidos, la jerarquía SEO, los menús y las extensiones.', 'taxocommerce-core' ),
				'state'       => __( 'Manual de usuario', 'taxocommerce-core' ),
				'tone'        => 'info',
				'slug'        => TaxoCommerce_Core_User_Guide::PAGE_SLUG,
			),
			array(
				'title'       => __( 'Contenidos', 'taxocommerce-core' ),
				'icon'        => 'dashicons-text-page',
				'description' => __( 'Edita productos, categorías de WooCommerce, entradas y páginas desde un entorno unificado.', 'taxocommerce-core' ),
				'state'       => __( 'Piloto funcional', 'taxocommerce-core' ),
				'tone'        => 'success',
				'slug'        => TaxoCommerce_Core_Content_Manager::PAGE_SLUG,
			),
			array(
				'title'       => __( 'Jerarquía SEO', 'taxocommerce-core' ),
				'icon'        => 'dashicons-networking',
				'description' => __( 'Gestiona la arquitectura Cluster → Hub primario → Hub secundario → Categoría → Producto.', 'taxocommerce-core' ),
				'state'       => __( 'Modelo nativo del Core', 'taxocommerce-core' ),
				'tone'        => 'info',
				'slug'        => 'taxocommerce-core-hierarchy',
			),
			array(
				'title'       => __( 'Gestor de menús', 'taxocommerce-core' ),
				'icon'        => 'dashicons-menu-alt3',
				'description' => __( 'Organiza la navegación del sitio a partir de la jerarquía SEO y de los menús de WordPress.', 'taxocommerce-core' ),
				'state'       => __( 'Migrado al Core', 'taxocommerce-core' ),
				'tone'        => 'info',
				'slug'        => 'taxocommerce-core-menu',
			),
		);


        $tc_version_label = __( 'Version', 'taxocommerce-core' );
        $tc_updated_label = __( 'Last updated', 'taxocommerce-core' );
        $tc_translations = array(
            'es' => array( 'Versión', 'Última actualización' ),
            'en' => array( 'Version', 'Last updated' ),
            'fr' => array( 'Version', 'Dernière mise à jour' ),
            'de' => array( 'Version', 'Zuletzt aktualisiert' ),
            'nl' => array( 'Versie', 'Laatst bijgewerkt' ),
        );
        $tc_locale = strtolower( substr( determine_locale(), 0, 2 ) );
        if ( isset( $tc_translations[ $tc_locale ] ) ) {
            if ( 'Version' === $tc_version_label ) {
                $tc_version_label = $tc_translations[ $tc_locale ][0];
            }
            if ( 'Last updated' === $tc_updated_label ) {
                $tc_updated_label = $tc_translations[ $tc_locale ][1];
            }
        }
        $tc_official_info = require __DIR__ . '/official-excerpt.php';
        $tc_plugin_headers = get_file_data( dirname( __DIR__ ) . '/taxocommerce-core.php', array( 'description' => 'Description' ), 'plugin' );
        $tc_plugin_description = trim( wp_strip_all_tags( (string) ( $tc_plugin_headers['description'] ?? '' ) ) );
        // The version history is bundled with this plugin, not fetched from GitHub.
        $tc_history_file = dirname( __DIR__ ) . '/CHANGELOG.md';
        $tc_history = is_readable( $tc_history_file ) ? file_get_contents( $tc_history_file ) : '';
        $tc_history_names = array(
            'es' => 'Historial de versiones y mejoras',
            'en' => 'Version history and improvements',
            'fr' => 'Historique des versions et améliorations',
            'de' => 'Versionsverlauf und Verbesserungen',
            'nl' => 'Versiegeschiedenis en verbeteringen',
        );
        $tc_history_label = __( 'Version history and improvements', 'taxocommerce-core' );
        if ( 'Version history and improvements' === $tc_history_label && isset( $tc_history_names[ $tc_locale ] ) ) {
            $tc_history_label = $tc_history_names[ $tc_locale ];
        }
    // CHANGELOG.md as headings and lists. Every piece of text is escaped first.
        $tc_md = static function ( $md ) {
            $html = '';
            $list = false;
            $inline = static function ( $text ) {
                $text = esc_html( $text );
                $text = preg_replace( '/`([^`]+)`/', '<code>$1</code>', $text );
                return preg_replace( '/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $text );
            };
            foreach ( preg_split( '/\r\n|\r|\n/', (string) $md ) as $line ) {
                $line = rtrim( $line );
                if ( preg_match( '/^\s*[-*]\s+(.*)$/', $line, $m ) ) {
                    $html .= ( $list ? '' : '<ul>' ) . '<li>' . $inline( $m[1] ) . '</li>';
                    $list = true;
                    continue;
                }
                if ( $list ) {
                    $html .= '</ul>';
                    $list = false;
                }
                if ( '' === trim( $line ) || preg_match( '/^#\s/', $line ) ) {
                    continue;
                }
                if ( preg_match( '/^#{2,3}\s+(.*)$/', $line, $m ) ) {
                    $html .= '<h3>' . $inline( $m[1] ) . '</h3>';
                } else {
                    $html .= '<p>' . $inline( $line ) . '</p>';
                }
            }
            return $list ? $html . '</ul>' : $html;
        };

		?>
		<div class="wrap tcc-dashboard" id="taxocommerce-core-dashboard">
			<header class="tcc-hero" aria-labelledby="tcc-dashboard-title">
				<div class="tcc-hero__identity">
					<div class="tcc-hero__emblem" aria-hidden="true"><span class="dashicons dashicons-products"></span></div>
					<div class="tcc-hero__copy">
						<p class="tcc-eyebrow"><?php esc_html_e( 'TaxoCommerce Core', 'taxocommerce-core' ); ?></p>
						<h1 id="tcc-dashboard-title"><?php esc_html_e( 'TaxoCommerce Core', 'taxocommerce-core' ); ?></h1>
						<p><?php echo esc_html( $tc_plugin_description ); ?></p>
                        <?php $tc_official_info->render( 'taxocommerce-core', $tc_locale ); ?>
					</div>
				</div>
				<div class="tcc-hero__metadata">
                    <span><?php echo esc_html( $tc_version_label . ' ' . TAXOCOMMERCE_CORE_VERSION ); ?></span>
                    <span><?php echo esc_html( $tc_updated_label . ': ' . TAXOCOMMERCE_CORE_RELEASE_DATE ); ?></span>
                </div>
				<div class="tcc-hero__art" aria-hidden="true"><span></span><span></span><span></span><span></span></div>
			</header>
			<?php if ( is_string( $tc_history ) && '' !== trim( $tc_history ) ) : ?>
			<details class="tc-family-history-panel" open><summary class="tc-family-history-panel__header"><span class="tc-family-history-panel__title"><span class="dashicons dashicons-backup" aria-hidden="true"></span><?php echo esc_html( $tc_history_label ); ?></span><span class="tc-family-history-panel__chevron" aria-hidden="true"></span></summary><div class="tc-family-history-panel__body"><div class="tc-family-history-panel__content"><?php echo $tc_md( $tc_history ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from esc_html() text in $tc_md. ?></div></div></details>
			<?php endif; ?>
			<hr class="wp-header-end">

			<nav class="nav-tab-wrapper" aria-label="<?php esc_attr_e( 'Navegación de TaxoCommerce Core', 'taxocommerce-core' ); ?>">
				<a class="nav-tab nav-tab-active" aria-current="page" href="<?php echo esc_url( admin_url( 'admin.php?page=taxocommerce-core' ) ); ?>"><?php esc_html_e( 'Overview', 'taxocommerce-core' ); ?></a>
				<a class="nav-tab" href="<?php echo esc_url( admin_url( 'admin.php?page=' . TaxoCommerce_Core_User_Guide::PAGE_SLUG ) ); ?>"><?php esc_html_e( 'User Guide', 'taxocommerce-core' ); ?></a>
				<a class="nav-tab" href="<?php echo esc_url( admin_url( 'admin.php?page=taxocommerce-core&tab=autocheck' ) ); ?>"><?php esc_html_e( 'Autocheck', 'taxocommerce-core' ); ?></a>
			</nav>

			<section class="tcc-metrics" aria-label="<?php esc_attr_e( 'Estado del sistema', 'taxocommerce-core' ); ?>">
				<?php foreach ( $metrics as $metric ) : ?>
					<div class="tcc-metric">
						<span class="tcc-metric__icon dashicons <?php echo esc_attr( $metric['icon'] ); ?>" aria-hidden="true"></span>
						<div class="tcc-metric__content">
							<span class="tcc-metric__label"><?php echo esc_html( $metric['label'] ); ?></span>
							<?php if ( 'neutral' === $metric['tone'] ) : ?>
								<strong class="tcc-metric__value"><?php echo esc_html( $metric['value'] ); ?></strong>
							<?php else : ?>
								<span class="tcc-status tcc-status--<?php echo esc_attr( $metric['tone'] ); ?>"><?php echo esc_html( $metric['value'] ); ?></span>
							<?php endif; ?>
						</div>
					</div>
				<?php endforeach; ?>
			</section>

			<section class="tcc-section" aria-labelledby="tcc-modules-title">
				<div class="tcc-section__header">
					<div>
						<h2 id="tcc-modules-title"><?php esc_html_e( 'Módulos del Core', 'taxocommerce-core' ); ?></h2>
						<p><?php esc_html_e( 'Accede a las funciones disponibles desde un panel organizado y coherente.', 'taxocommerce-core' ); ?></p>
					</div>
				</div>
				<div class="tcc-module-grid">
					<?php foreach ( $navigation as $module ) : ?>
						<article class="tcc-module">
							<span class="tcc-module__icon dashicons <?php echo esc_attr( $module['icon'] ); ?>" aria-hidden="true"></span>
							<h3><?php echo esc_html( $module['title'] ); ?></h3>
							<p class="tcc-module__description"><?php echo esc_html( $module['description'] ); ?></p>
							<div class="tcc-module__footer">
								<span class="tcc-status tcc-status--<?php echo esc_attr( $module['tone'] ); ?>"><?php echo esc_html( $module['state'] ); ?></span>
								<a class="tcc-button" href="<?php echo esc_url( admin_url( 'admin.php?page=' . $module['slug'] ) ); ?>" aria-label="<?php
                                    /* translators: %s: title of the module displayed on the dashboard. */
                                    echo esc_attr( sprintf( __( 'Abrir %s', 'taxocommerce-core' ), $module['title'] ) );
                                ?>"><?php esc_html_e( 'Abrir', 'taxocommerce-core' ); ?> <span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span></a>
							</div>
						</article>
					<?php endforeach; ?>
				</div>
			</section>

			<?php if ( is_callable( $extensions_renderer ) ) : ?>
				<?php call_user_func( $extensions_renderer ); ?>
			<?php endif; ?>
		</div>
		<?php
	}
}
