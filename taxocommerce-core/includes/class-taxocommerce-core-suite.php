<?php
/**
 * TaxoCommerce suite: remove a module's files and download it again.
 *
 * "Quitar" deletes the module folder from wp-content/plugins to free space,
 * WITHOUT running the module's uninstall routine: its tables and options stay,
 * because several modules share the historical seo_* tables. "Descargar"
 * installs the module again from the suite channel (a signed-by-hash manifest)
 * and reactivates it, so the data kept on removal is used again.
 *
 * @package TaxoCommerce_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Suite channel and module removal.
 */
final class TaxoCommerce_Core_Suite {

	/** Option with the modules removed from this site: id => array( version, removed_at ). */
	const OPTION_REMOVED = 'taxocommerce_core_removed_modules';

	/** Default suite channel: latest release of the fork that builds the suite. */
	const DEFAULT_MANIFEST_URL = 'https://github.com/fermatacordis-create/seo-taxonomy/releases/latest/download/taxocommerce-suite.json';

	/** First OPNV version whose automatic translation handles wrapped PO entries. */
	const OPNV_MIN_I18N = '1.0.260';

	/** Nonce action shared with the Core extension forms. */
	const NONCE_ACTION = 'taxocommerce_core_extension_action';

	/**
	 * Registers the protected admin-post handlers.
	 *
	 * @return void
	 */
	public static function register_hooks() {
		add_action( 'admin_post_taxocommerce_core_remove_extension', array( __CLASS__, 'handle_remove' ) );
		add_action( 'admin_post_taxocommerce_core_download_extension', array( __CLASS__, 'handle_download' ) );
		// OPNV automatic translation: translate the family into the admin's language.
		add_filter( 'opnv_plugin_i18n_auto_targets', array( __CLASS__, 'opnv_i18n_targets' ) );
	}

	/**
	 * Plugin folder of a catalog entry, or '' when it is not a family module.
	 *
	 * @param array $extension Catalog entry.
	 * @return string
	 */
	public static function module_dir( $extension ) {
		$plugin_file = (string) ( $extension['plugin_file'] ?? '' );
		$parts       = explode( '/', $plugin_file );
		if ( 2 !== count( $parts ) || $parts[1] !== $parts[0] . '.php' ) {
			return '';
		}
		$dir = $parts[0];
		if ( 'taxocommerce-core' === $dir || ! preg_match( '/^taxocommerce-[a-z0-9-]+$/', $dir ) ) {
			return '';
		}
		return $dir;
	}

	/**
	 * Modules removed from this site.
	 *
	 * @return array
	 */
	public static function removed() {
		$removed = get_option( self::OPTION_REMOVED, array() );
		return is_array( $removed ) ? $removed : array();
	}

	/**
	 * Suite channel manifest URL.
	 *
	 * @return string
	 */
	public static function manifest_url() {
		$url = defined( 'TAXOCOMMERCE_SUITE_MANIFEST_URL' ) ? (string) TAXOCOMMERCE_SUITE_MANIFEST_URL : self::DEFAULT_MANIFEST_URL;
		/**
		 * Filters the suite channel manifest URL.
		 *
		 * @param string $url Manifest URL.
		 */
		return (string) apply_filters( 'taxocommerce_core_suite_manifest_url', $url );
	}

	/**
	 * Whether a URL may be used by the suite channel.
	 *
	 * @param string $url URL.
	 * @return bool
	 */
	private static function url_allowed( $url ) {
		$parts = wp_parse_url( (string) $url );
		if ( empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
			return false;
		}
		/**
		 * Filters whether the suite channel accepts plain http (test labs only).
		 *
		 * @param bool $allow Allow http.
		 */
		$http_ok = (bool) apply_filters( 'taxocommerce_core_suite_allow_http', false );
		if ( 'https' !== $parts['scheme'] && ! ( $http_ok && 'http' === $parts['scheme'] ) ) {
			return false;
		}
		/**
		 * Filters the hosts the suite channel may download from.
		 *
		 * @param string[] $hosts Allowed hosts.
		 */
		$hosts = (array) apply_filters( 'taxocommerce_core_suite_allowed_hosts', array( 'github.com' ) );
		return in_array( strtolower( $parts['host'] ), array_map( 'strtolower', $hosts ), true );
	}

	/**
	 * Card buttons for an installed module (active or inactive).
	 *
	 * @param array $item Card item built by the Core.
	 * @return void
	 */
	public static function render_remove_button( $item ) {
		if ( empty( $item['installed'] ) || empty( $item['suite_dir'] ) || ! current_user_can( 'delete_plugins' ) ) {
			return;
		}
		$confirm = sprintf(
			/* translators: %s: module name. */
			__( '¿Quitar %s? Se borran sus archivos para ahorrar espacio, pero se conservan sus datos. Para volver a usarlo habrá que descargarlo de nuevo.', 'taxocommerce-core' ),
			(string) $item['name']
		);
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return window.confirm(<?php echo esc_attr( wp_json_encode( $confirm ) ); ?>);">
			<input type="hidden" name="action" value="taxocommerce_core_remove_extension">
			<input type="hidden" name="extension_id" value="<?php echo esc_attr( $item['id'] ); ?>">
			<?php wp_nonce_field( self::NONCE_ACTION, 'taxocommerce_core_nonce' ); ?>
			<button class="button button-small button-link-delete" type="submit"><?php esc_html_e( 'Quitar', 'taxocommerce-core' ); ?></button>
		</form>
		<?php
	}

	/**
	 * Card block for a module that is not installed.
	 *
	 * @param array $item Card item built by the Core.
	 * @return void
	 */
	public static function render_download_button( $item ) {
		if ( ! empty( $item['installed'] ) || empty( $item['suite_dir'] ) ) {
			return;
		}
		$removed = self::removed();
		$id      = (string) $item['id'];
		if ( isset( $removed[ $id ] ) ) {
			echo '<p class="tcc-extension-card__removed">' . esc_html(
				sprintf(
					/* translators: %s: version that was removed. */
					__( 'Quitado de esta web (versión %s). Sus datos se conservan.', 'taxocommerce-core' ),
					(string) ( $removed[ $id ]['version'] ?? '—' )
				)
			) . '</p>';
		}
		if ( ! current_user_can( 'install_plugins' ) ) {
			return;
		}
		$confirm = sprintf(
			/* translators: %s: module name. */
			__( 'Se descargará %s desde el canal de la suite TaxoCommerce y se activará. ¿Continuar?', 'taxocommerce-core' ),
			(string) $item['name']
		);
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return window.confirm(<?php echo esc_attr( wp_json_encode( $confirm ) ); ?>);">
			<input type="hidden" name="action" value="taxocommerce_core_download_extension">
			<input type="hidden" name="extension_id" value="<?php echo esc_attr( $id ); ?>">
			<?php wp_nonce_field( self::NONCE_ACTION, 'taxocommerce_core_nonce' ); ?>
			<button class="button button-primary button-small" type="submit"><?php esc_html_e( 'Descargar y activar', 'taxocommerce-core' ); ?></button>
		</form>
		<?php
	}

	/**
	 * Offers every active TaxoCommerce plugin with a .pot to OPNV's automatic
	 * admin translation. Source is "auto" because the catalogs mix Spanish and
	 * English msgids. OPNV writes <domain>-<locale>.po/.mo in the module's
	 * languages folder, which is exactly what TaxoCommerce loads.
	 *
	 * @param array $targets OPNV targets.
	 * @return array
	 */
	public static function opnv_i18n_targets( $targets ) {
		$targets = is_array( $targets ) ? $targets : array();
		// OPNV before 1.0.260 mangled wrapped msgstr lines: do not hand it our catalogs.
		if ( ! defined( 'OPNV_VERSION' ) || version_compare( (string) OPNV_VERSION, self::OPNV_MIN_I18N, '<' ) ) {
			return $targets;
		}
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$seen = array();
		foreach ( $targets as $target ) {
			$seen[ (string) ( $target['slug'] ?? '' ) ] = true;
		}
		foreach ( get_plugins() as $basename => $meta ) {
			$dir = strtok( (string) $basename, '/' );
			if ( isset( $seen[ $basename ] ) || 0 !== strpos( (string) $dir, 'taxocommerce-' ) || ! is_plugin_active( $basename ) ) {
				continue;
			}
			if ( ! glob( trailingslashit( WP_PLUGIN_DIR ) . $dir . '/languages/*.pot' ) ) {
				continue;
			}
			$targets[] = array(
				'slug'        => $basename,
				'label'       => (string) ( $meta['Name'] ?? $dir ),
				'source_lang' => 'auto',
				'type'        => 'plugin',
			);
		}
		return $targets;
	}

	/**
	 * Common request checks; returns the catalog entry.
	 *
	 * @param string $capability Required capability.
	 * @return array
	 */
	private static function request_extension( $capability ) {
		if ( ! current_user_can( $capability ) ) {
			wp_die( esc_html__( 'No tienes permisos para esta acción.', 'taxocommerce-core' ) );
		}
		check_admin_referer( self::NONCE_ACTION, 'taxocommerce_core_nonce' );

		$id      = isset( $_POST['extension_id'] ) ? sanitize_key( wp_unslash( $_POST['extension_id'] ) ) : '';
		$catalog = TaxoCommerce_Core::instance()->extension_catalog();
		if ( '' === $id || ! isset( $catalog[ $id ] ) || '' === self::module_dir( (array) $catalog[ $id ] ) ) {
			wp_die( esc_html__( 'Unknown TaxoCommerce extension.', 'taxocommerce-core' ) );
		}
		$extension       = (array) $catalog[ $id ];
		$extension['id'] = $id;
		return $extension;
	}

	/**
	 * Removes a module's files, keeping its data.
	 *
	 * @return void
	 */
	public static function handle_remove() {
		$extension   = self::request_extension( 'delete_plugins' );
		$dir         = self::module_dir( $extension );
		$plugin_file = $dir . '/' . $dir . '.php';

		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';

		$plugins = get_plugins();
		if ( ! isset( $plugins[ $plugin_file ] ) ) {
			self::result( 'error', __( 'El módulo no está instalado.', 'taxocommerce-core' ) );
		}
		$version = (string) ( $plugins[ $plugin_file ]['Version'] ?? '' );

		if ( is_plugin_active( $plugin_file ) ) {
			deactivate_plugins( $plugin_file, false, false );
		}

		$path = trailingslashit( WP_PLUGIN_DIR ) . $dir;
		$real = realpath( $path );
		$base = realpath( WP_PLUGIN_DIR );
		if ( false === $real || false === $base || dirname( $real ) !== $base ) {
			self::result( 'error', __( 'La carpeta del módulo no está donde se esperaba; no se ha borrado nada.', 'taxocommerce-core' ) );
		}

		// Delete only the files. The module's uninstall.php is NOT run on purpose:
		// its tables and options are kept (some are shared with other modules).
		if ( ! WP_Filesystem() ) {
			self::result( 'error', __( 'WordPress no tiene acceso de escritura a los plugins; el módulo está desactivado pero no se ha borrado.', 'taxocommerce-core' ) );
		}
		global $wp_filesystem;
		if ( ! $wp_filesystem->delete( $real, true ) ) {
			self::result( 'error', __( 'No se pudieron borrar los archivos del módulo; ha quedado desactivado.', 'taxocommerce-core' ) );
		}
		wp_clean_plugins_cache( true );

		$removed                       = self::removed();
		$removed[ $extension['id'] ] = array(
			'version'    => $version,
			'removed_at' => gmdate( 'c' ),
		);
		update_option( self::OPTION_REMOVED, $removed, false );

		self::result(
			'ok',
			sprintf(
				/* translators: %s: module name. */
				__( '%s quitado. Sus datos se conservan; puedes volver a descargarlo desde esta misma tarjeta.', 'taxocommerce-core' ),
				(string) ( $extension['name'] ?? $dir )
			)
		);
	}

	/**
	 * Reads the suite manifest.
	 *
	 * @return array|WP_Error
	 */
	public static function fetch_manifest() {
		$url = self::manifest_url();
		if ( ! self::url_allowed( $url ) ) {
			return new WP_Error( 'tc_suite_url', __( 'El canal de descarga de la suite no está configurado o no es de confianza.', 'taxocommerce-core' ) );
		}
		$response = wp_safe_remote_get( $url, array( 'timeout' => 15, 'redirection' => 5 ) );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return new WP_Error( 'tc_suite_http', __( 'El canal de la suite no ha respondido correctamente.', 'taxocommerce-core' ) );
		}
		$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $data ) || empty( $data['modules'] ) || ! is_array( $data['modules'] ) ) {
			return new WP_Error( 'tc_suite_manifest', __( 'El índice de la suite no es válido.', 'taxocommerce-core' ) );
		}
		return $data;
	}

	/**
	 * Downloads, verifies, installs and activates a removed module.
	 *
	 * @return void
	 */
	public static function handle_download() {
		$extension   = self::request_extension( 'install_plugins' );
		$dir         = self::module_dir( $extension );
		$plugin_file = $dir . '/' . $dir . '.php';

		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

		if ( isset( get_plugins()[ $plugin_file ] ) ) {
			self::result( 'error', __( 'El módulo ya está instalado.', 'taxocommerce-core' ) );
		}

		$manifest = self::fetch_manifest();
		if ( is_wp_error( $manifest ) ) {
			self::result( 'error', $manifest->get_error_message() );
		}
		$entry = isset( $manifest['modules'][ $dir ] ) && is_array( $manifest['modules'][ $dir ] ) ? $manifest['modules'][ $dir ] : array();
		$url   = (string) ( $entry['url'] ?? '' );
		$sha   = strtolower( (string) ( $entry['sha256'] ?? '' ) );
		if ( '' === $url || ! self::url_allowed( $url ) || ! preg_match( '/^[a-f0-9]{64}$/', $sha ) ) {
			self::result( 'error', __( 'Este módulo no está disponible en el canal de la suite.', 'taxocommerce-core' ) );
		}

		$requires = (string) ( $entry['requires_core'] ?? '' );
		if ( '' !== $requires && version_compare( TAXOCOMMERCE_CORE_VERSION, $requires, '<' ) ) {
			self::result(
				'error',
				sprintf(
					/* translators: 1: required Core version, 2: installed Core version. */
					__( 'Para volver a añadir este módulo, actualiza primero TaxoCommerce Core a la versión %1$s o superior (tienes la %2$s).', 'taxocommerce-core' ),
					$requires,
					TAXOCOMMERCE_CORE_VERSION
				)
			);
		}

		$tmp = download_url( $url, 300 );
		if ( is_wp_error( $tmp ) ) {
			self::result( 'error', $tmp->get_error_message() );
		}
		if ( ! hash_equals( $sha, (string) hash_file( 'sha256', $tmp ) ) ) {
			wp_delete_file( $tmp );
			self::result( 'error', __( 'El archivo descargado no coincide con su huella; no se ha instalado.', 'taxocommerce-core' ) );
		}
		if ( ! self::zip_contains( $tmp, $plugin_file ) ) {
			wp_delete_file( $tmp );
			self::result( 'error', __( 'El paquete descargado no corresponde a este módulo.', 'taxocommerce-core' ) );
		}

		$upgrader = new Plugin_Upgrader( new Automatic_Upgrader_Skin() );
		$result   = $upgrader->install( $tmp );
		wp_delete_file( $tmp );
		if ( is_wp_error( $result ) || ! $result ) {
			self::result( 'error', is_wp_error( $result ) ? $result->get_error_message() : __( 'WordPress no pudo instalar el módulo.', 'taxocommerce-core' ) );
		}
		wp_clean_plugins_cache( true );
		if ( ! isset( get_plugins()[ $plugin_file ] ) ) {
			self::result( 'error', __( 'El paquete se procesó, pero el módulo no quedó en la carpeta esperada.', 'taxocommerce-core' ) );
		}

		$removed = self::removed();
		unset( $removed[ $extension['id'] ] );
		update_option( self::OPTION_REMOVED, $removed, false );

		$activated = activate_plugin( $plugin_file, '', false, false );
		if ( is_wp_error( $activated ) ) {
			self::result( 'error', __( 'Módulo descargado, pero no se pudo activar: ', 'taxocommerce-core' ) . $activated->get_error_message() );
		}

		/**
		 * A module was installed: ask OPNV (when present) to translate it into
		 * the administrator's language.
		 */
		do_action( 'opnv_plugin_i18n_auto_request', $plugin_file );

		self::result(
			'ok',
			sprintf(
				/* translators: 1: module name, 2: version. */
				__( '%1$s %2$s descargado y activado.', 'taxocommerce-core' ),
				(string) ( $extension['name'] ?? $dir ),
				(string) ( $entry['version'] ?? '' )
			)
		);
	}

	/**
	 * Whether a ZIP holds the expected plugin main file.
	 *
	 * @param string $zip_path    ZIP path.
	 * @param string $plugin_file Expected basename.
	 * @return bool
	 */
	private static function zip_contains( $zip_path, $plugin_file ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return false;
		}
		$zip = new ZipArchive();
		if ( true !== $zip->open( $zip_path ) ) {
			return false;
		}
		$found = false;
		$top   = strtok( $plugin_file, '/' ) . '/';
		for ( $i = 0; $i < $zip->numFiles; $i++ ) {
			$name = str_replace( '\\', '/', (string) $zip->getNameIndex( $i ) );
			if ( 0 !== strpos( $name, $top ) || false !== strpos( $name, '..' ) ) {
				$zip->close();
				return false;
			}
			if ( $name === $plugin_file ) {
				$found = true;
			}
		}
		$zip->close();
		return $found;
	}

	/**
	 * Redirects back to the extension cards with a message.
	 *
	 * @param string $result  ok|error.
	 * @param string $message Message.
	 * @return void
	 */
	private static function result( $result, $message ) {
		$url = add_query_arg(
			array(
				'page'       => 'taxocommerce-core',
				'tc_result'  => 'ok' === $result ? 'ok' : 'error',
				'tc_message' => rawurlencode( sanitize_text_field( $message ) ),
			),
			admin_url( 'admin.php' )
		);
		wp_safe_redirect( $url . '#tcc-extensions' );
		exit;
	}
}
