<?php
/**
 * Read-only FAQ diagnostics, immediate execution and a downloadable JSON report.
 *
 * Checks never call FAQ save/delete/import/export, install, uninstall or
 * migration methods. Only the administrator's own reports are stored in
 * usermeta, separately per site, after an explicit nonce-protected request.
 * Legacy comparison metadata is retained but no longer displayed or updated.
 *
 * @package TaxoCommerceFAQs
 */
defined( 'ABSPATH' ) || exit;

final class TaxoCommerce_FAQs_Auto_Test {
	const ACTION = 'taxocommerce_faqs_auto_test_run';
	const DOWNLOAD_ACTION = 'taxocommerce_faqs_auto_test_download';
	const NONCE = 'taxocommerce_faqs_auto_test';
	const BASELINE = 'taxocommerce_faqs_at_before_';
	const LATEST = 'taxocommerce_faqs_at_latest_';

	public static function register() {
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'handle_run' ) );
		add_action( 'admin_post_' . self::DOWNLOAD_ACTION, array( __CLASS__, 'handle_download' ) );
	}

	private static function allowed() {
		return current_user_can( 'manage_options' );
	}

	private static function meta_key( $which ) {
		return ( 'before' === $which ? self::BASELINE : self::LATEST ) . get_current_blog_id();
	}

	private static function site_hash() {
		return hash( 'sha256', (string) home_url( '/' ) );
	}

	private static function url() {
		return TaxoCommerce_FAQs::url( array( 'tc_faq_tab' => 'auto-test' ) );
	}

	public static function groups() {
		return array(
			'startup' => 'Arranque y administración',
			'data' => 'Base de datos y catálogo',
			'editor' => 'Edición, destinos y etiquetas',
			'csv' => 'Importación / exportación CSV',
			'security' => 'Seguridad y mantenimiento',
			'uat' => 'Operaciones reales (pruebas manuales)',
		);
	}

	public static function cases() {
		return array(
			'classes' => array( 'startup', 'Componentes PHP cargados' ),
			'version' => array( 'startup', 'Versión declarada y constante' ),
			'menu' => array( 'startup', 'Menú de administración registrado' ),
			'core' => array( 'startup', 'Registro opcional en TaxoCommerce Core' ),
			'routes' => array( 'startup', 'Acciones de gestión registradas' ),
			'db' => array( 'data', 'Conexión MySQL de lectura' ),
			'table' => array( 'data', 'Tabla canónica de FAQs disponible' ),
			'columns' => array( 'data', 'Columnas obligatorias y categoría de producto' ),
			'categories' => array( 'data', 'Taxonomía WooCommerce de categorías' ),
			'editor' => array( 'editor', 'Controladores de creación y edición disponibles' ),
			'delete' => array( 'editor', 'Controlador de eliminación registrado' ),
			'vocabulary' => array( 'editor', 'Vocabulary opcional sin dependencia obligatoria' ),
			'csv_methods' => array( 'csv', 'Controladores CSV disponibles' ),
			'csv_parser' => array( 'csv', 'Analizador CSV de comillas y líneas' ),
			'csv_routes' => array( 'csv', 'Rutas de importación y exportación registradas' ),
			'access' => array( 'security', 'Permisos administrativos de Autocheck' ),
			'diagnostic_routes' => array( 'security', 'Acciones protegidas para informe y descarga' ),
			'storage' => array( 'security', 'Informes separados por usuario y sitio' ),
			'uninstall' => array( 'security', 'Control de desinstalación presente' ),
			'deactivation' => array( 'security', 'Desactivación sin borrado de registros' ),
			'real_editor' => array( 'uat', 'Crear y editar una FAQ real en staging' ),
			'real_import' => array( 'uat', 'Importar y verificar un CSV real en staging' ),
			'real_export' => array( 'uat', 'Descargar y abrir un CSV real en staging' ),
			'real_frontend' => array( 'uat', 'Comprobar presentación pública y caché' ),
			'real_uninstall' => array( 'uat', 'Verificar eliminación solo en sitio desechable' ),
		);
	}

	private static function result( $status, $message ) {
		return array( 'status' => $status, 'message' => $message );
	}
	private static function pass( $message ) { return self::result( 'pass', $message ); }
	private static function fail( $message ) { return self::result( 'fail', $message ); }
	private static function skip( $message ) { return self::result( 'skip', $message ); }

	/**
	 * Executes individually bounded, read-only checks. No catalogue records
	 * or stored plugin settings are written by this function.
	 *
	 * @param string $selected Group or all.
	 * @param array|null $custom_cases Offline test fixture.
	 * @return array
	 */
	public static function run_suite( $selected = 'all', $custom_cases = null ) {
		$groups = self::groups();
		$selected = 'all' === $selected || isset( $groups[ $selected ] ) ? $selected : 'all';
		$cases = null === $custom_cases ? self::cases() : $custom_cases;
		$start = microtime( true );
		$totals = array( 'pass' => 0, 'fail' => 0, 'skip' => 0, 'not_run' => 0 );
		$rows = array();
		foreach ( $cases as $id => $labels ) {
			$begin = microtime( true );
			if ( 'all' !== $selected && $selected !== $labels[0] ) {
				$outcome = self::result( 'not_run', 'Grupo no ejecutado.' );
			} else {
				try {
					$callback = array( __CLASS__, 'check_' . $id );
					$outcome = is_callable( $callback ) ? call_user_func( $callback ) : self::fail( 'Comprobación no implementada.' );
					if ( ! is_array( $outcome ) || ! isset( $outcome['status'], $outcome['message'] )
						|| ! in_array( $outcome['status'], array( 'pass', 'fail', 'skip' ), true ) ) {
						$outcome = self::fail( 'Resultado de prueba inválido.' );
					}
				} catch ( Throwable $error ) {
					// Do not expose stack traces, SQL, user data or host paths.
					$outcome = self::fail( 'Excepción de diagnóstico (' . get_class( $error ) . '). Consultar log privado de PHP.' );
				}
			}
			$outcome['id'] = $id;
			$outcome['group_id'] = $labels[0];
			$outcome['group'] = isset( $groups[ $labels[0] ] ) ? $groups[ $labels[0] ] : $labels[0];
			$outcome['label'] = $labels[1];
			$outcome['ms'] = (int) round( ( microtime( true ) - $begin ) * 1000 );
			$rows[] = $outcome;
			++$totals[ $outcome['status'] ];
		}
		global $wp_version;
		return array(
			'schema' => 1,
			'phase' => 'check',
			'group' => $selected,
			'site_hash' => self::site_hash(),
			'version' => TAXOCOMMERCE_FAQS_VERSION,
			'wordpress' => isset( $wp_version ) ? (string) $wp_version : '',
			'php' => PHP_VERSION,
			'date' => current_time( 'mysql' ), 'executed_at' => gmdate( 'c' ),
			'timestamp' => time(),
			'duration_ms' => (int) round( ( microtime( true ) - $start ) * 1000 ),
			'totals' => $totals,
			'rows' => $rows,
		);
	}

	/**
	 * Compares full-suite snapshots from the same site and in correct order.
	 */
	public static function compare( $before, $after ) {
		if ( ! is_array( $before ) || ! is_array( $after )
			|| empty( $before['site_hash'] ) || empty( $after['site_hash'] )
			|| ! hash_equals( (string) $before['site_hash'], (string) $after['site_hash'] )
			|| ( $before['phase'] ?? '' ) !== 'before' || ( $after['phase'] ?? '' ) !== 'after'
			|| ( $before['group'] ?? '' ) !== 'all' || ( $after['group'] ?? '' ) !== 'all'
			|| (int) ( $after['timestamp'] ?? 0 ) < (int) ( $before['timestamp'] ?? 0 ) ) {
			return null;
		}
		$old = array();
		$new = array();
		foreach ( (array) ( $before['rows'] ?? array() ) as $row ) {
			if ( isset( $row['id'], $row['status'] ) ) { $old[ $row['id'] ] = $row['status']; }
		}
		foreach ( (array) ( $after['rows'] ?? array() ) as $row ) {
			if ( isset( $row['id'], $row['status'] ) ) { $new[ $row['id'] ] = $row['status']; }
		}
		$changes = array( 'new_failures' => array(), 'resolved' => array(), 'unchanged_failures' => array(), 'coverage_lost' => array(), 'new_checks' => array() );
		foreach ( $new as $id => $status ) {
			if ( ! array_key_exists( $id, $old ) ) {
				$changes['new_checks'][] = $id;
				if ( 'fail' === $status ) { $changes['new_failures'][] = $id; }
				continue;
			}
			if ( 'fail' === $status ) {
				$changes[ 'fail' === $old[ $id ] ? 'unchanged_failures' : 'new_failures' ][] = $id;
			} elseif ( 'fail' === $old[ $id ] && 'pass' === $status ) {
				$changes['resolved'][] = $id;
			} elseif ( in_array( $old[ $id ], array( 'pass', 'fail' ), true )
				&& in_array( $status, array( 'skip', 'not_run' ), true ) ) {
				$changes['coverage_lost'][] = $id;
			}
		}
		foreach ( $old as $id => $status ) {
			if ( ! array_key_exists( $id, $new ) ) { $changes['coverage_lost'][] = $id; }
		}
		return $changes;
	}

	private static function get_saved( $which ) {
		$report = get_user_meta( get_current_user_id(), self::meta_key( $which ), true );
		return is_array( $report ) && ! empty( $report['rows'] ) && ! empty( $report['site_hash'] )
			&& hash_equals( self::site_hash(), (string) $report['site_hash'] ) ? $report : array();
	}

	public static function handle_run() {
		if ( ! self::allowed() ) { wp_die( esc_html__( 'Sin permisos para ejecutar Autocheck.', 'taxocommerce-faqs' ) ); }
		check_admin_referer( self::NONCE, 'taxocommerce_faqs_at_nonce' );
		// Legacy phase/group fields are ignored: every click runs the complete suite.
		// Keep the latest report and existing metadata keys; never overwrite an old baseline.
		update_user_meta( get_current_user_id(), self::meta_key( 'latest' ), self::run_suite( 'all' ) );
		wp_safe_redirect( self::url() );
		exit;
	}

	public static function handle_download() {
		if ( ! self::allowed() ) { wp_die( esc_html__( 'Sin permisos para descargar Autocheck.', 'taxocommerce-faqs' ) ); }
		check_admin_referer( self::NONCE, 'taxocommerce_faqs_at_nonce' );
		// Download only the latest report; old before/after snapshots remain untouched.
		$report = self::get_saved( 'latest' );
		if ( ! $report ) { wp_die( esc_html__( 'No hay informe válido para este sitio.', 'taxocommerce-faqs' ) ); }
		$json = wp_json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE );
		if ( false === $json ) { wp_die( esc_html__( 'Error creando JSON.', 'taxocommerce-faqs' ) ); }
		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Content-Disposition: attachment; filename="taxocommerce-faqs-autocheck-' . gmdate( 'Ymd-His' ) . '.json"' );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Intentional JSON download (not HTML).
		echo $json;
		exit;
	}

	/** Bounded read-only checks. */
	private static function check_classes() {
		return class_exists( 'TaxoCommerce_FAQs' ) && class_exists( 'TaxoCommerce_FAQs_Lifecycle' )
			&& class_exists( __CLASS__ ) ? self::pass( 'Editor, ciclo de vida y Autocheck cargados.' )
			: self::fail( 'Falta un componente PHP requerido.' );
	}

	private static function check_version() {
		return defined( 'TAXOCOMMERCE_FAQS_VERSION' ) && preg_match( '/^\d+\.\d+\.\d+$/', TAXOCOMMERCE_FAQS_VERSION )
			? self::pass( 'Versión disponible: ' . TAXOCOMMERCE_FAQS_VERSION )
			: self::fail( 'Versión del plugin no declarada correctamente.' );
	}

	private static function check_menu() {
		return has_action( 'admin_menu', array( 'TaxoCommerce_FAQs', 'menu' ) )
			? self::pass( 'Menú y navegación de FAQs registrados.' )
			: self::fail( 'Falta la navegación administrativa.' );
	}

	private static function check_core() {
		if ( ! function_exists( 'taxocommerce_core' ) ) {
			return self::skip( 'Core no está activo; se utiliza el menú propio.' );
		}
		return has_action( 'taxocommerce_core_register_modules', array( 'TaxoCommerce_FAQs', 'register_core' ) )
			? self::pass( 'Integración opcional con Core registrada.' )
			: self::fail( 'Core activo sin registro de FAQs.' );
	}

	private static function check_routes() {
		foreach ( array( 'save', 'delete', 'export', 'import' ) as $method ) {
			if ( ! has_action( 'admin_post_taxocommerce_faqs_' . $method, array( 'TaxoCommerce_FAQs', $method ) ) ) {
				return self::fail( 'No se ha registrado la acción ' . $method . '.' );
			}
		}
		return self::pass( 'Cuatro acciones administrativas registradas, sin ejecutarlas.' );
	}

	private static function check_db() {
		global $wpdb;
		if ( ! is_object( $wpdb ) ) { return self::fail( 'No hay conexión wpdb.' ); }
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Live SQL read confirms MySQL, must not be served from cache.
		$answer = $wpdb->get_var( 'SELECT 1' );
		return '1' === (string) $answer ? self::pass( 'MySQL disponible en modo solo lectura.' ) : self::fail( 'MySQL no responde a SELECT 1.' );
	}

	private static function table_exists( $suffix ) {
		global $wpdb;
		$table = $wpdb->prefix . $suffix;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Fresh optional table existence check, never creates a table.
		return $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
	}

	private static function check_table() {
		return self::table_exists( 'seo_faq' )
			? self::pass( 'Tabla canónica de preguntas disponible, sin modificarla.' )
			: self::fail( 'Falta la tabla seo_faq. No se crean tablas desde Autocheck.' );
	}

	private static function check_columns() {
		if ( ! self::table_exists( 'seo_faq' ) ) { return self::skip( 'Sin tabla FAQ: no se puede comprobar su esquema.' ); }
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema inspection must reflect current schema.
		$columns = (array) $wpdb->get_col( $wpdb->prepare( 'SHOW COLUMNS FROM %i', $wpdb->prefix . 'seo_faq' ) );
		$needed = array( 'id', 'object_type', 'object_id', 'product_cat_id', 'question', 'answer', 'sort_order', 'active', 'load_count', 'open_count', 'created_at', 'updated_at' );
		$missing = array_diff( $needed, $columns );
		return ! $missing ? self::pass( 'Tabla FAQ con campos canónicos y product_cat_id.' )
			: self::fail( 'Faltan columnas: ' . implode( ', ', $missing ) . '. No se ejecuta migración.' );
	}

	private static function check_categories() {
		return function_exists( 'taxonomy_exists' ) && taxonomy_exists( 'product_cat' )
			? self::pass( 'Taxonomía product_cat disponible.' )
			: self::skip( 'La taxonomía product_cat aún no está registrada; validar con WooCommerce activo.' );
	}

	private static function check_editor() {
		return is_callable( array( 'TaxoCommerce_FAQs', 'save' ) )
			&& is_callable( array( 'TaxoCommerce_FAQs', 'render' ) )
			? self::pass( 'Formulario y guardado disponibles; no se crea ninguna FAQ.' )
			: self::fail( 'Falta el editor o su controlador.' );
	}

	private static function check_delete() {
		return is_callable( array( 'TaxoCommerce_FAQs', 'delete' ) )
			? self::pass( 'Eliminación manual disponible, sin ejecutar borrado.' )
			: self::fail( 'Falta el controlador de eliminación.' );
	}

	private static function check_vocabulary() {
		$has_vocab = self::table_exists( 'seo_vocabulary' );
		$has_rel = self::table_exists( 'seo_object_vocabulary' );
		if ( ! $has_vocab && ! $has_rel ) {
			return self::skip( 'Vocabulary es opcional: las FAQs funcionan sin etiquetas semánticas.' );
		}
		return $has_vocab && $has_rel
			? self::pass( 'Ambas tablas de Vocabulary disponibles; no se modifican relaciones.' )
			: self::skip( 'Integración parcial con Vocabulary. No se fuerza ninguna escritura.' );
	}

	private static function check_csv_methods() {
		return is_callable( array( 'TaxoCommerce_FAQs', 'export' ) )
			&& is_callable( array( 'TaxoCommerce_FAQs', 'import' ) )
			? self::pass( 'Importador y exportador CSV disponibles; sin acceder a archivos.' )
			: self::fail( 'Falta el importador o exportador.' );
	}

	private static function check_csv_parser() {
		$method = new ReflectionMethod( 'TaxoCommerce_FAQs', 'parse_csv_rows' );
		$method->setAccessible( true );
		$rows = $method->invoke( null, "id,question,answer\n1,\"¿Duda, con coma?\",\"Primera\nsegunda\"\n" );
		$invalid = $method->invoke( null, "id,question\n1,\"sin cerrar" );
		return is_array( $rows ) && count( $rows ) === 2
			&& isset( $rows[1][1], $rows[1][2] )
			&& '¿Duda, con coma?' === $rows[1][1] && "Primera\nsegunda" === $rows[1][2]
			&& is_wp_error( $invalid )
			? self::pass( 'CSV con comas, comillas, saltos de línea y rechazo de comillas abiertas.' )
			: self::fail( 'El analizador CSV ha cambiado de comportamiento.' );
	}

	private static function check_csv_routes() {
		return has_action( 'admin_post_taxocommerce_faqs_import' )
			&& has_action( 'admin_post_taxocommerce_faqs_export' )
			? self::pass( 'Ambas rutas CSV registradas (sin importar ni exportar).' )
			: self::fail( 'Falta alguna ruta CSV.' );
	}

	private static function check_access() {
		return self::allowed() ? self::pass( 'Acceso limitado a manage_options.' )
			: self::fail( 'No hay permisos de administrador.' );
	}

	private static function check_diagnostic_routes() {
		return has_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'handle_run' ) )
			&& has_action( 'admin_post_' . self::DOWNLOAD_ACTION, array( __CLASS__, 'handle_download' ) )
			? self::pass( 'Informe y descarga protegidos por permisos y nonce.' )
			: self::fail( 'Falta alguna ruta de Autocheck.' );
	}

	private static function check_storage() {
		return function_exists( 'get_user_meta' ) && function_exists( 'update_user_meta' )
			&& '' !== self::meta_key( 'latest' )
			? self::pass( 'Último resultado aislado por usuario y sitio, sin texto de preguntas.' )
			: self::fail( 'No hay almacenamiento de informes por usuario.' );
	}

	private static function check_uninstall() {
		return is_readable( TAXOCOMMERCE_FAQS_PATH . 'uninstall.php' )
			&& is_callable( array( 'TaxoCommerce_FAQs_Lifecycle', 'uninstall' ) )
			? self::pass( 'Control de desinstalación presente. No se invoca ninguna limpieza.' )
			: self::fail( 'Falta el contrato de desinstalación.' );
	}

	private static function check_deactivation() {
		return ! has_action( 'deactivate_taxocommerce-faqs/taxocommerce-faqs.php' )
			? self::pass( 'La desactivación no tiene un borrado registrado.' )
			: self::fail( 'Hay una acción de desactivación: revisar antes de producción.' );
	}

	private static function check_real_editor() { return self::skip( 'Validar creación, edición y categoría de una FAQ real en staging.' ); }
	private static function check_real_import() { return self::skip( 'No se importa un CSV real. Validar 500 filas y rechazo de entradas incorrectas en staging.' ); }
	private static function check_real_export() { return self::skip( 'No se descarga ningún fichero. Exportar y abrir CSV en staging.' ); }
	private static function check_real_frontend() { return self::skip( 'La presentación pública depende también de otros módulos; verificar en staging.' ); }
	private static function check_real_uninstall() { return self::skip( 'NUNCA desinstalar en producción para comprobarlo. Usar un sitio desechable.' ); }


	/**
	 * Single-click read-only diagnosis; no before/after workflow.
	 */
	private static function button() {
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="tc-faq-at-form">
			<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>">
			<?php wp_nonce_field( self::NONCE, 'taxocommerce_faqs_at_nonce' ); ?>
			<button type="submit" class="button button-secondary"><?php esc_html_e( 'Ejecutar Autocheck', 'taxocommerce-faqs' ); ?></button>
		</form>
		<?php
	}

	private static function download_button( $ready ) {
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="tc-faq-at-form">
			<input type="hidden" name="action" value="<?php echo esc_attr( self::DOWNLOAD_ACTION ); ?>">
			<?php wp_nonce_field( self::NONCE, 'taxocommerce_faqs_at_nonce' ); ?>
			<button type="submit" class="button button-secondary" <?php disabled( ! $ready ); ?>><?php esc_html_e( 'Descargar resultados (JSON)', 'taxocommerce-faqs' ); ?></button>
		</form>
		<?php
	}

	/**
	 * Match the Import-Export Autocheck workflow without changing 25 checks,
	 * the independent Code Check or the previously saved private metadata.
	 */
	public static function render() {
		if ( \TaxoCommerce_FAQs_Autotest_Code::render_subtabs() ) { return; }
		if ( ! self::allowed() ) {
			wp_die( esc_html__( 'Sin permisos para consultar Autocheck.', 'taxocommerce-faqs' ) );
		}
		$last = self::get_saved( 'latest' );
		$cases = self::cases();
		$groups = self::groups();
		$index = array();
		foreach ( (array) ( $last['rows'] ?? array() ) as $row ) {
			if ( isset( $row['id'] ) ) {
				$index[ $row['id'] ] = $row;
			}
		}
		$totals = $last['totals'] ?? array( 'pass' => 0, 'fail' => 0, 'skip' => 0, 'not_run' => count( $cases ) );
		?>
		<section class="tc-faq-at" aria-labelledby="tc-faq-at-title">
			<style>
			.tc-faq-at{max-width:1180px;margin:20px 0}
			.tc-faq-at__actions,.tc-faq-at__totals{display:flex;flex-wrap:wrap;gap:12px;margin:16px 0}
			.tc-faq-at__panel,.tc-faq-at__group{background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:16px 20px}
			.tc-faq-at__totals>div{background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:14px 20px;min-width:150px}
			.tc-faq-at__totals strong{display:block;font-size:27px;line-height:1.3}
			.tc-faq-at__totals span{font-size:12px;color:#50575e}
			.tc-faq-at__group{margin:12px 0}
			.tc-faq-at__group summary{cursor:pointer;font-weight:600;font-size:15px}
			.tc-faq-at-form{margin:0}
			.tc-faq-at__row{display:flex;align-items:flex-start;gap:12px;border-top:1px solid #eee;padding:10px 0}
			.tc-faq-at__row p{margin:5px 0}
			.tc-faq-at__row small{color:#646970}
			.tc-faq-at__dot{flex:0 0 11px;width:11px;height:11px;border-radius:50%;margin-top:4px;background:#999}
			.tc-faq-at__dot--pass{background:#16823a}
			.tc-faq-at__dot--fail{background:#b42318}
			.tc-faq-at__dot--skip{background:#cf9116}
			.tc-faq-at__dot--not_run{background:#9299a3}
			.tc-faq-at__fail strong{color:#b42318}
			@media(max-width:782px){.tc-faq-at__group{padding:14px}}
			</style>
			<h2 id="tc-faq-at-title"><?php esc_html_e( 'Autocheck de FAQs', 'taxocommerce-faqs' ); ?></h2>
			<p><?php esc_html_e( 'Diagnóstico manual y no destructivo: comprueba funciones y configuración sin crear, editar, eliminar ni importar preguntas.', 'taxocommerce-faqs' ); ?></p>
			<div class="tc-faq-at__actions">
				<?php self::button(); ?>
				<?php self::download_button( (bool) $last ); ?>
			</div>
			<?php if ( $last ) : ?>
				<p class="description"><strong><?php esc_html_e( 'Fecha de ejecución:', 'taxocommerce-faqs' ); ?></strong>
				<?php echo esc_html( (string) ( $last['date'] ?? '' ) ); ?>
				· <?php echo esc_html( 'v' . (string) ( $last['version'] ?? '' ) ); ?>
				· <?php echo esc_html( absint( $last['duration_ms'] ?? 0 ) . ' ms' ); ?></p>
			<?php else : ?>
				<p class="description"><?php esc_html_e( 'Todavía no se ha ejecutado el Autocheck en este sitio.', 'taxocommerce-faqs' ); ?></p>
			<?php endif; ?>
			<div class="tc-faq-at__totals" aria-label="<?php esc_attr_e( 'Resultados', 'taxocommerce-faqs' ); ?>">
				<div><strong><?php echo esc_html( absint( $totals['pass'] ?? 0 ) ); ?></strong><span><?php esc_html_e( 'Correctos', 'taxocommerce-faqs' ); ?></span></div>
				<div class="tc-faq-at__fail"><strong><?php echo esc_html( absint( $totals['fail'] ?? 0 ) ); ?></strong><span><?php esc_html_e( 'Errores', 'taxocommerce-faqs' ); ?></span></div>
				<div><strong><?php echo esc_html( absint( $totals['skip'] ?? 0 ) ); ?></strong><span><?php esc_html_e( 'Omitidos / externos', 'taxocommerce-faqs' ); ?></span></div>
				<div><strong><?php echo esc_html( absint( $totals['not_run'] ?? 0 ) ); ?></strong><span><?php esc_html_e( 'No ejecutados', 'taxocommerce-faqs' ); ?></span></div>
			</div>
			<?php foreach ( $groups as $group => $name ) : ?>
				<?php
				$items = array_filter( $cases, static function ( $entry ) use ( $group ) { return $entry[0] === $group; } );
				$failed = 0;
				foreach ( $items as $id => $entry ) {
					if ( ( $index[ $id ]['status'] ?? '' ) === 'fail' ) { ++$failed; }
				}
				?>
				<details class="tc-faq-at__group" <?php echo $failed || ! $last ? 'open' : ''; ?>>
					<summary><?php echo esc_html( $name . ' · ' . count( $items ) . ' pruebas' . ( $failed ? ' · ' . $failed . ' errores' : '' ) ); ?></summary>
					<?php foreach ( $items as $id => $entry ) : ?>
						<?php $row = $index[ $id ] ?? array( 'status' => 'not_run', 'message' => 'Todavía no ejecutada.', 'ms' => 0 ); ?>
						<div class="tc-faq-at__row">
							<span class="tc-faq-at__dot tc-faq-at__dot--<?php echo esc_attr( $row['status'] ); ?>" aria-hidden="true"></span>
							<div><strong><?php echo esc_html( $entry[1] ); ?> <small><?php echo esc_html( $id ); ?></small></strong>
								<p><?php echo esc_html( $row['message'] ); ?></p>
								<small><?php echo esc_html( absint( $row['ms'] ) . ' ms' ); ?></small>
							</div>
						</div>
					<?php endforeach; ?>
				</details>
			<?php endforeach; ?>
			<p class="description"><?php esc_html_e( 'Los controles comprueban contratos y lecturas, no operaciones reales. Los casos omitidos requieren validación manual en STAGING; el Code Check no sustituye a WordPress Plugin Check.', 'taxocommerce-faqs' ); ?></p>
		</section>
		<?php
	}
}
