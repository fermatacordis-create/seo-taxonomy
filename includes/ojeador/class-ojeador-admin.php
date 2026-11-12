<?php
/**
 * Ojeador admin: category-first Google Shopping market view.
 *
 * @package SEOSystem
 * @subpackage Ojeador
 * @since 0.6.0
 */

defined('ABSPATH') || exit;

final class SEO_Ojeador_Admin {
    const PAGE = 'seo-ojeador';
    private static $tools_filter_used = false;

    public static function init() {
        add_action('admin_menu', array(__CLASS__, 'register_page'), 90);
        // Acceso visible centralizado en Mercado; se conserva la ruta completa de Ojeador.
        // No inyectar una tarjeta alternativa en Herramientas.
        add_action('admin_post_seo_ojeador_settings_save', array(__CLASS__, 'save_settings'));
        add_action('admin_post_seo_ojeador_test_provider_chain', array(__CLASS__, 'test_provider_chain'));
        add_action('admin_post_seo_ojeador_start', array(__CLASS__, 'start'));
        add_action('admin_post_seo_ojeador_stop', array(__CLASS__, 'stop'));
        add_action('admin_post_seo_ojeador_export_json', array(__CLASS__, 'export_json'));
        add_action('admin_post_seo_ojeador_export_analysis_json', array(__CLASS__, 'export_analysis_json'));
        add_action('admin_post_seo_ojeador_export_stars_json', array(__CLASS__, 'export_stars_json'));
        add_action('admin_post_seo_ojeador_export_log_json', array(__CLASS__, 'export_log_json'));
    }

    public static function register_page() {
        add_submenu_page(null, 'Ojeador', 'Ojeador', 'manage_options', self::PAGE, array(__CLASS__, 'render'));
    }

    public static function register_tool_card($tools) {
        self::$tools_filter_used = true;
        $tools = is_array($tools) ? $tools : array();
        foreach ($tools as $tool) {
            if (($tool['page'] ?? '') === self::PAGE) {
                return $tools;
            }
        }
        $tools[] = array(
            'title' => 'Ojeador',
            'icon' => 'dashicons-chart-line',
            'page' => self::PAGE,
            'desc' => 'Compara el mercado conocido y descubre nuevas categorías, subfamilias y tecnologías.',
        );
        return $tools;
    }

    public static function tools_fallback_card() {
        if (self::$tools_filter_used || !current_user_can('manage_options')) {
            return;
        }
        if (sanitize_key((string) self::query_arg('page', '')) !== 'seo-tools') {
            return;
        }
        $url = admin_url('admin.php?page=' . self::PAGE);
        ?>
        <script id="seo-ojeador-tools-card-fallback">
        (function(){
            var grid=document.querySelector('.seo-tools-grid'); if(!grid) return;
            var href=<?php echo wp_json_encode($url); ?>;
            var links=grid.querySelectorAll('a[href]');
            for(var i=0;i<links.length;i++){if(links[i].href===href||links[i].getAttribute('href')===href)return;}
            var a=document.createElement('a');a.className='seo-tool-card-link';a.href=href;
            a.innerHTML='<div class="seo-tool-card"><span class="dashicons dashicons-chart-line"></span><h2>Ojeador</h2><p>Mercado por categorías en Google Shopping.</p><span class="button button-primary">Abrir</span></div>';
            grid.appendChild(a);
        })();
        </script>
        <?php
    }

    private static function guard($action) {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('No autorizado.', 'taxocommerce-seo'));
        }
        check_admin_referer($action);
    }

    /**
     * Lee filtros/avisos GET de solo lectura. No cambian estado y no requieren nonce.
     */
    private static function query_arg($key, $default = '') {
        $key = sanitize_key((string) $key);
        if ('' === $key) {
            return $default;
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Filtros y avisos GET de solo lectura.
        if (!isset($_GET[$key])) {
            return $default;
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Filtros y avisos GET de solo lectura.
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Raw query value is returned deliberately so each caller can apply the sanitizer appropriate to its expected type.
        return wp_unslash($_GET[$key]);
    }

    private static function redirect($args = array()) {
        wp_safe_redirect(add_query_arg(array_merge(array('page'=>self::PAGE), $args), admin_url('admin.php')));
        exit;
    }

    private static function redirect_provider_connections($args = array(), $anchor = 'seo-google-shared-providers') {
        $url = add_query_arg(array_merge(array('page'=>'seo-provider-connections'), $args), admin_url('admin.php'));
        if ($anchor !== '') {
            $url .= '#' . sanitize_html_class($anchor);
        }
        wp_safe_redirect($url);
        exit;
    }

    public static function save_settings() {
        self::guard('seo_ojeador_settings_save');
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce and capability verified by self::guard() above.
        $raw = isset($_POST['ojeador']) && is_array($_POST['ojeador']) ? map_deep(wp_unslash($_POST['ojeador']), 'sanitize_text_field') : array();
        $scope = isset($_POST['ojeador_scope']) ? sanitize_key((string) wp_unslash($_POST['ojeador_scope'])) : 'operation';
        $return_to_provider_connections = isset($_POST['ojeador_return'])
            && 'provider_connections' === sanitize_key((string) wp_unslash($_POST['ojeador_return']));

        // El checkbox sólo pertenece al formulario operativo de Ojeador. El
        // formulario central de proveedores no debe desactivar auto_enabled.
        if ($scope === 'operation') {
            $raw['auto_enabled'] = empty($raw['auto_enabled']) ? 0 : 1;
        }

        SEO_Ojeador_Shopping::save_settings($raw);
        // phpcs:enable WordPress.Security.NonceVerification.Missing

        if ($return_to_provider_connections) {
            self::redirect_provider_connections(array('ojeador_google_notice'=>'settings_saved'));
        }

        self::redirect(array('ojeador_tab'=>'operation','ojeador_notice'=>'settings_saved'));
    }

    public static function test_provider_chain() {
        self::guard('seo_ojeador_test_provider_chain');
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce and capability verified by self::guard() above.
        $return_to_provider_connections = isset($_POST['ojeador_return'])
            && 'provider_connections' === sanitize_key((string) wp_unslash($_POST['ojeador_return']));
        $result = SEO_Ojeador_Shopping::test_connection_chain();
        // phpcs:enable WordPress.Security.NonceVerification.Missing

        if ($return_to_provider_connections) {
            if (is_wp_error($result)) {
                self::redirect_provider_connections(array(
                    'ojeador_google_error'=>rawurlencode($result->get_error_message()),
                ));
            }
            self::redirect_provider_connections(array(
                'ojeador_google_notice'=>'connection_ok',
                'ojeador_google_provider'=>sanitize_key((string) ($result['provider'] ?? '')),
            ));
        }

        if (is_wp_error($result)) {
            self::redirect(array(
                'ojeador_tab'=>'operation',
                'ojeador_error'=>rawurlencode($result->get_error_message()),
            ));
        }

        self::redirect(array(
            'ojeador_tab'=>'operation',
            'ojeador_notice'=>'connection_ok',
            'ojeador_provider'=>sanitize_key((string) ($result['provider'] ?? '')),
        ));
    }

    public static function render_shared_google_connections() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $settings = SEO_Ojeador_Shopping::settings();
        $usage = SEO_Ojeador_Shopping::usage_month();
        $scraper_usage = SEO_Ojeador_Shopping::scraperapi_usage_month();
        $notice = isset($_GET['ojeador_google_notice'])
            ? sanitize_key((string) wp_unslash($_GET['ojeador_google_notice']))
            : '';
        $error_encoded = isset($_GET['ojeador_google_error'])
            ? sanitize_text_field(wp_unslash($_GET['ojeador_google_error']))
            : '';
        $error = '' !== $error_encoded ? sanitize_text_field(rawurldecode($error_encoded)) : '';
        $tested_provider = isset($_GET['ojeador_google_provider'])
            ? sanitize_key((string) wp_unslash($_GET['ojeador_google_provider']))
            : '';

        ?>
        <section id="seo-google-shared-providers" class="card" style="max-width:none;padding:20px;margin-top:20px;scroll-margin-top:40px;">
            <?php
wp_enqueue_style(
    'taxocommerce-ojeador-admin',
    SEO_SYSTEM_URL . 'includes/assets/ojeador-admin.css',
    array(),
    SEO_SYSTEM_VERSION
);
if (did_action('admin_print_styles')) {
    wp_print_styles('taxocommerce-ojeador-admin');
}
?>

            <div style="border-bottom:1px solid #dcdcde;padding-bottom:14px;margin-bottom:14px;">
                <p style="margin:0 0 5px;"><strong style="font-size:12px;text-transform:uppercase;color:#646970;">Servicios compartidos de búsqueda Google</strong></p>
                <h2 style="margin:0 0 7px;">SerpApi + ScraperAPI · conexión compartida para Ojeador e Ingeniero</h2>
                <p style="margin:0;max-width:980px;">
                    Estas conexiones permanecen disponibles para investigación externa de Ingeniero. Ojeador utiliza prioritariamente Merchant; sus consultas tradicionales de Shopping solo están disponibles cuando un administrador selecciona ese modo. El radar Novedades de Ojeador está retirado.
                </p>
            </div>

            <?php if ($notice === 'settings_saved') : ?>
                <div class="notice notice-success inline"><p>Conexiones Google guardadas.</p></div>
            <?php endif; ?>

            <?php if ($notice === 'connection_ok') : ?>
                <div class="notice notice-success inline"><p><strong>Cadena de conexión operativa.</strong>
                    <?php if ($tested_provider === 'scraperapi') : ?>
                        SerpApi no estaba disponible y ScraperAPI respondió correctamente como fallback.
                    <?php else : ?>
                        SerpApi respondió correctamente; ScraperAPI queda preparada como fallback.
                    <?php endif; ?>
                </p></div>
            <?php endif; ?>

            <?php if ($error !== '') : ?>
                <div class="notice notice-error inline"><p><?php echo esc_html($error); ?></p></div>
            <?php endif; ?>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('seo_ojeador_settings_save'); ?>
                <input type="hidden" name="action" value="seo_ojeador_settings_save">
                <input type="hidden" name="ojeador_scope" value="connections">
                <input type="hidden" name="ojeador_return" value="provider_connections">

                <div class="seo-google-provider-stack">
                    <div class="seo-google-provider">
                        <div class="seo-google-provider-head">
                            <strong>Conexión 1 · SerpApi</strong>
                            <span class="seo-google-provider-status <?php echo !empty($settings['api_key']) ? 'is-ok' : ''; ?>">
                                <?php echo !empty($settings['api_key']) ? 'Configurada' : 'Sin configurar'; ?>
                            </span>
                        </div>
                        <label>SerpApi API key
                            <input type="password" name="ojeador[api_key]" value="" placeholder="<?php echo esc_attr(!empty($settings['api_key']) ? 'Configurada · dejar vacío para conservar' : 'API key'); ?>" autocomplete="new-password">
                            <small>Proveedor primario para Ojeador e Ingeniero.</small>
                        </label>
                    </div>

                    <div class="seo-google-provider">
                        <div class="seo-google-provider-head">
                            <strong>Conexión 2 · ScraperAPI</strong>
                            <span class="seo-google-provider-status <?php echo !empty($settings['scraperapi_key']) ? 'is-ok' : ''; ?>">
                                <?php echo !empty($settings['scraperapi_key']) ? 'Configurada' : 'Sin configurar'; ?>
                            </span>
                        </div>
                        <label>ScraperAPI API key
                            <input type="password" name="ojeador[scraperapi_key]" value="" placeholder="<?php echo esc_attr(!empty($settings['scraperapi_key']) ? 'Configurada · dejar vacío para conservar' : 'API key'); ?>" autocomplete="new-password">
                            <small>Proveedor de respaldo. Se usa cuando SerpApi no está disponible, alcanza cuota o falla.</small>
                        </label>
                    </div>
                </div>

                <div class="seo-google-chain"><strong>Orden automático:</strong> SerpApi → ScraperAPI. Una respuesta válida de SerpApi, aunque tenga 0 resultados, no activa el segundo proveedor.</div>

                <p>
                    <label><strong>Límite mensual local SerpApi</strong><br>
                        <input type="number" min="1" max="1000000" name="ojeador[monthly_query_limit]" value="<?php echo absint($settings['monthly_query_limit']); ?>">
                        <small>Cuando se alcanza este límite, Ojeador e Ingeniero pueden continuar con ScraperAPI si está configurado.</small>
                    </label>
                </p>

                <div class="seo-google-usage">
                    <span><strong>SerpApi:</strong> <?php echo esc_html(number_format_i18n(absint($usage['used'] ?? 0))); ?> / <?php echo esc_html(number_format_i18n(absint($usage['limit'] ?? 0))); ?></span>
                    <span><strong>ScraperAPI:</strong> <?php echo esc_html(number_format_i18n(absint($scraper_usage['requests'] ?? 0))); ?> peticiones locales este mes</span>
                </div>

                <p><button class="button button-primary" type="submit">Guardar conexiones Google</button></p>
            </form>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:12px;padding-top:12px;border-top:1px solid #dcdcde;">
                <?php wp_nonce_field('seo_ojeador_test_provider_chain'); ?>
                <input type="hidden" name="action" value="seo_ojeador_test_provider_chain">
                <input type="hidden" name="ojeador_return" value="provider_connections">
                <button class="button" type="submit">Probar cadena SerpApi → ScraperAPI</button>
                <small>SerpApi se valida sin gastar una búsqueda. Sólo si debe probar ScraperAPI se realiza una petición estructurada de Google Search.</small>
            </form>
        </section>
        <?php
    }

    public static function start() {
        self::guard('seo_ojeador_start');
        $result = SEO_Ojeador_Worker::start_run('admin');
        if (is_wp_error($result)) {
            self::redirect(array('ojeador_error'=>rawurlencode($result->get_error_message())));
        }
        self::redirect(array('ojeador_notice'=>'scan_started'));
    }

    public static function stop() {
        self::guard('seo_ojeador_stop');
        SEO_Ojeador_Worker::stop_run();
        self::redirect(array('ojeador_notice'=>'scan_stopped'));
    }

    private static function status_label($status) {
        switch (sanitize_key((string) $status)) {
            case 'ok': return array('Con datos', '#008a20');
            case 'no_results': return array('Sin resultados', '#996800');
            case 'error': return array('Error', '#b32d2e');
            default: return array('Pendiente', '#646970');
        }
    }

    private static function query_log_label($status) {
        switch (sanitize_key((string) $status)) {
            case 'ok': return array('OK', '#008a20');
            case 'no_results': return array('Sin resultados', '#996800');
            case 'reused': return array('Reutilizada', '#2271b1');
            case 'response_ok':
            case 'parsed': return array('Respuesta recibida', '#2271b1');
            case 'requesting': return array('Consultando', '#996800');
            case 'prepared': return array('Preparada', '#646970');
            case 'budget_blocked': return array('Límite', '#996800');
            case 'query_error': return array('Consulta inválida', '#b32d2e');
            case 'network_error':
            case 'http_error':
            case 'api_error':
            case 'parse_error':
            case 'db_error': return array('Error', '#b32d2e');
            default: return array($status !== '' ? $status : '—', '#646970');
        }
    }

    private static function json_headers($filename) {
        while (ob_get_level()) {
            ob_end_clean();
        }
        nocache_headers();
        header('Content-Type: application/json; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . sanitize_file_name($filename) . '"');
        header('X-Content-Type-Options: nosniff');
    }

    private static function stream_table_json($table, $order_by = 'id ASC', $where = '1=1') {
        global $wpdb;
        $offset = 0;
        $chunk = 500;
        $first = true;
        echo '[';
        do {
            // Only the exporter's fixed query shapes are accepted; do not allow caller-provided SQL.
            $sort_map = array('id ASC' => 'id ASC', 'term_id ASC' => 'term_id ASC', 'object_id ASC' => 'object_id ASC');
            $where_map = array('1=1' => '1=1', "object_type='product_cat'" => "object_type='product_cat'");
            // Table names are $wpdb->prefix + a fixed name: only identifier characters are accepted.
            // (No %i placeholder here: it needs WordPress 6.2 and this plugin declares 5.8.)
            if (!isset($sort_map[$order_by], $where_map[$where]) || !preg_match('/^[A-Za-z0-9_]+$/', (string) $table)) {
                break;
            }
            $safe_order = $sort_map[$order_by];
            $safe_where = $where_map[$where];
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Interactive administrative export or status query must reflect current state; no cache invalidation contract exists.
            $rows = $wpdb->get_results(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table checked above; WHERE and ORDER BY come from the two fixed allowlists; pagination is parameterized.
                $wpdb->prepare("SELECT * FROM `{$table}` WHERE {$safe_where} ORDER BY {$safe_order} LIMIT %d OFFSET %d", $chunk, $offset),
                ARRAY_A
            );
            foreach ((array) $rows as $row) {
                if (!$first) {
                    echo ',';
                }
                echo wp_json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $first = false;
            }
            $count = count((array) $rows);
            $offset += $count;
        } while ($count === $chunk);
        echo ']';
    }

    public static function export_json() {
        self::guard('seo_ojeador_export_json');
        global $wpdb;

        $settings = SEO_Ojeador_Shopping::settings();
        $safe_settings = $settings;
        $safe_settings['api_key_configured'] = !empty($settings['api_key']);
        $safe_settings['scraperapi_key_configured'] = !empty($settings['scraperapi_key']);
        unset($safe_settings['api_key'], $safe_settings['scraperapi_key']);
        $usage = SEO_Ojeador_Shopping::usage_month();
        $summary = SEO_Ojeador_DB::category_summary();
        $analysis_export = SEO_Ojeador_Analysis::dashboard(200);
        $inventory = SEO_Ojeador_DB::list_market_categories(array('limit'=>2000, 'search'=>''));
        $latest_run = SEO_Ojeador_DB::latest_run();
        $schema_table = $wpdb->prefix . 'seo_google_schema_map';
        $schema_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $schema_table)) === $schema_table;

        self::json_headers('ojeador-inventario-completo-' . gmdate('Ymd-His') . '.json');
        echo '{';
        echo '"meta":' . wp_json_encode(array(
            'schema' => 'seo_ojeador_export',
            'schema_version' => '1.0.0',
            'generated_at_utc' => gmdate('c'),
            'site_url' => home_url('/'),
            'ojeador_version' => defined('SEO_OJEADOR_VERSION') ? SEO_OJEADOR_VERSION : '',
            'db_version' => get_option(SEO_Ojeador_DB::OPTION_DB_VERSION, ''),
        ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        echo ',"settings":' . wp_json_encode($safe_settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        echo ',"usage":' . wp_json_encode($usage, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        echo ',"summary":' . wp_json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        echo ',"analysis":' . wp_json_encode($analysis_export, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        echo ',"latest_run":' . wp_json_encode($latest_run, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        echo ',"category_inventory":' . wp_json_encode($inventory, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        echo ',"google_schema_product_cat":';
        if ($schema_exists) {
            self::stream_table_json($schema_table, 'object_id ASC', "object_type='product_cat'");
        } else {
            echo '[]';
        }
        echo ',"market_categories":';
        self::stream_table_json(SEO_Ojeador_DB::table('categories'), 'term_id ASC');
        echo ',"market_category_results":';
        self::stream_table_json(SEO_Ojeador_DB::table('category_results'), 'id ASC');
        echo ',"query_log":';
        self::stream_table_json(SEO_Ojeador_DB::table('query_log'), 'id ASC');
        echo ',"runs":';
        self::stream_table_json(SEO_Ojeador_DB::table('runs'), 'id ASC');
        echo ',"legacy_market_products":';
        self::stream_table_json(SEO_Ojeador_DB::table('products'), 'object_id ASC');
        echo ',"legacy_market_offers":';
        self::stream_table_json(SEO_Ojeador_DB::table('offers'), 'id ASC');
        echo '}';
        exit;
    }

    private static function stream_analysis_products_json() {
        global $wpdb;
        $table = SEO_Ojeador_DB::table('category_results');
        $categories = SEO_Ojeador_DB::table('categories');
        $offset = 0;
        $chunk = 500;
        $first = true;
        echo '[';
        do {
            $rows = $wpdb->get_results(
                "SELECT r.id,r.term_id,COALESCE(NULLIF(c.category_name,''),t.name) AS category_name,
                        c.query_text,r.google_product_id,r.gtin,r.mpn,r.brand,r.model,r.title,
                        r.merchant,r.price,r.old_price,r.currency,
                        CASE WHEN r.old_price>r.price AND r.old_price>0 THEN ROUND(((r.old_price-r.price)/r.old_price)*100,2) ELSE NULL END AS discount_pct,
                        r.delivery,r.rating,r.reviews,r.result_position,r.product_url,r.image_url,r.observed_at
                 FROM {$table} r
                 LEFT JOIN {$categories} c ON c.term_id=r.term_id
                 LEFT JOIN {$wpdb->terms} t ON t.term_id=r.term_id
                 WHERE r.active=1 AND c.status='ok'
                 ORDER BY r.term_id ASC,r.result_position ASC,r.id ASC
                 LIMIT {$chunk} OFFSET {$offset}",
                ARRAY_A
            );
            foreach ((array) $rows as $row) {
                if (!$first) echo ',';
                echo wp_json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $first = false;
            }
            $count = count((array) $rows);
            $offset += $count;
        } while ($count === $chunk);
        echo ']';
    }

    public static function export_analysis_json() {
        self::guard('seo_ojeador_export_analysis_json');
        $analysis = SEO_Ojeador_Analysis::dashboard(500);

        self::json_headers('ojeador-analisis-kpis-' . gmdate('Ymd-His') . '.json');
        echo '{';
        echo '"meta":' . wp_json_encode(array(
            'schema' => 'seo_ojeador_analysis_export',
            'schema_version' => '2.2.0',
            'generated_at_utc' => gmdate('c'),
            'site_url' => home_url('/'),
            'ojeador_version' => defined('SEO_OJEADOR_VERSION') ? SEO_OJEADOR_VERSION : '',
            'scope' => 'analysis_kpis_recommendations_comparison_and_price_position',
        ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        echo ',"kpis":' . wp_json_encode((array) ($analysis['kpis'] ?? array()), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        echo ',"thresholds":' . wp_json_encode((array) ($analysis['thresholds'] ?? array()), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        echo ',"operational_recommendations":' . wp_json_encode((array) ($analysis['operational_recommendations'] ?? array()), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        echo ',"recommendations":' . wp_json_encode((array) ($analysis['recommendations'] ?? array()), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        echo ',"categories":' . wp_json_encode((array) ($analysis['categories'] ?? array()), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        echo ',"merchants":' . wp_json_encode((array) ($analysis['merchants'] ?? array()), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        echo ',"products_comparison":';
        self::stream_analysis_products_json();
        $stars = SEO_Ojeador_Stars::export_data(false);
        echo ',"star_kpis":' . wp_json_encode((array) ($stars['kpis'] ?? array()), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        echo ',"star_products":' . wp_json_encode(array_slice((array) ($stars['products'] ?? array()), 0, 500), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        echo ',"notes":' . wp_json_encode((array) ($analysis['notes'] ?? array()), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        echo '}';
        exit;
    }

    public static function export_stars_json() {
        self::guard('seo_ojeador_export_stars_json');
        $stars = SEO_Ojeador_Stars::export_data(true);
        self::json_headers('ojeador-productos-estrella-' . gmdate('Ymd-His') . '.json');
        echo wp_json_encode(array(
            'meta' => array(
                'schema' => 'seo_ojeador_star_products',
                'schema_version' => '1.1.0',
                'generated_at_utc' => gmdate('c'),
                'site_url' => home_url('/'),
                'ojeador_version' => defined('SEO_OJEADOR_VERSION') ? SEO_OJEADOR_VERSION : '',
                'markup_rule_pct' => 20,
            ),
            'kpis' => (array) ($stars['kpis'] ?? array()),
            'thresholds' => (array) ($stars['thresholds'] ?? array()),
            'products' => (array) ($stars['products'] ?? array()),
            'notes' => (array) ($stars['notes'] ?? array()),
        ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function export_log_json() {
        self::guard('seo_ojeador_export_log_json');
        self::json_headers('ojeador-log-consultas-' . gmdate('Ymd-His') . '.json');
        echo '{';
        echo '"meta":' . wp_json_encode(array(
            'schema' => 'seo_ojeador_query_log',
            'schema_version' => '1.0.0',
            'generated_at_utc' => gmdate('c'),
            'ojeador_version' => defined('SEO_OJEADOR_VERSION') ? SEO_OJEADOR_VERSION : '',
        ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        echo ',"usage":' . wp_json_encode(SEO_Ojeador_Shopping::usage_month(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        echo ',"latest_run":' . wp_json_encode(SEO_Ojeador_DB::latest_run(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        echo ',"query_log":';
        self::stream_table_json(SEO_Ojeador_DB::table('query_log'), 'id ASC');
        echo '}';
        exit;
    }

    private static function analysis_tone_color($tone) {
        switch (sanitize_key((string) $tone)) {
            case 'danger': return '#b32d2e';
            case 'warning': return '#996800';
            case 'opportunity': return '#008a20';
            case 'info': return '#2271b1';
            case 'ok': return '#008a20';
            default: return '#646970';
        }
    }

    private static function render_analysis($analysis) {
        $analysis = is_array($analysis) ? $analysis : array();
        $sum = (array) ($analysis['kpis'] ?? array());
        $operational = (array) ($analysis['operational_recommendations'] ?? array());
        $recommendations = (array) ($analysis['recommendations'] ?? array());
        $categories = (array) ($analysis['categories'] ?? array());
        $merchants = (array) ($analysis['merchants'] ?? array());
        ?>
        <section class="seo-ojeador-analysis">
            <div style="margin:20px 0 8px">
                <h2 style="margin:0">Qué está diciendo el mercado</h2>
                <p class="seo-ojeador-muted" style="margin:4px 0 0">KPIs orientados a decisión. Los resultados de Google Shopping son una muestra por consulta, no una estimación del tamaño absoluto del mercado.</p>
            </div>

            <div class="seo-ojeador-cards seo-ojeador-analysis-cards">
                <div class="seo-ojeador-card"><strong><?php echo esc_html(number_format_i18n((float) ($sum['valid_market_coverage_pct'] ?? 0), 1)); ?>%</strong><span>Cobertura de mercado válida<br><small><?php echo esc_html(number_format_i18n(absint($sum['categories_with_market'] ?? 0))); ?> / <?php echo esc_html(number_format_i18n(absint($sum['categories_eligible'] ?? 0))); ?> categorías aptas</small></span></div>
                <div class="seo-ojeador-card"><strong><?php echo esc_html(number_format_i18n(absint($sum['categories_pending_first_scan'] ?? 0))); ?></strong><span>Pendientes de primera instantánea</span></div>
                <div class="seo-ojeador-card"><strong><?php echo esc_html(number_format_i18n(absint($sum['categories_error'] ?? 0))); ?></strong><span>Consultas con error</span></div>
                <div class="seo-ojeador-card"><strong><?php echo esc_html(number_format_i18n(absint($sum['provider_remaining'] ?? 0))); ?> / <?php echo esc_html(number_format_i18n(absint($sum['provider_limit'] ?? 0))); ?></strong><span>Consultas disponibles</span></div>
                <div class="seo-ojeador-card"><strong><?php echo esc_html(number_format_i18n(absint($sum['active_results'] ?? 0))); ?></strong><span>Productos de mercado observados</span></div>
                <div class="seo-ojeador-card"><strong><?php echo esc_html(number_format_i18n(absint($sum['unique_merchants'] ?? 0))); ?></strong><span>Comercios distintos</span></div>
                <div class="seo-ojeador-card"><strong><?php echo esc_html(number_format_i18n((float) ($sum['median_merchants_per_category'] ?? 0), 1)); ?></strong><span>Mediana de comercios / categoría</span></div>
                <div class="seo-ojeador-card seo-ojeador-kpi-action"><strong><?php echo esc_html(number_format_i18n(absint($sum['catalog_gap_candidates'] ?? 0))); ?></strong><span>Huecos de catálogo a revisar</span></div>
                <div class="seo-ojeador-card seo-ojeador-kpi-action"><strong><?php echo esc_html(number_format_i18n(absint($sum['high_visibility_categories'] ?? 0))); ?></strong><span>Categorías con visibilidad interna alta</span></div>
                <div class="seo-ojeador-card"><strong><?php echo esc_html(number_format_i18n(absint($sum['high_promo_categories'] ?? 0))); ?></strong><span>Presión promocional alta</span></div>
                <div class="seo-ojeador-card"><strong><?php echo esc_html(number_format_i18n(absint($sum['concentrated_categories'] ?? 0))); ?></strong><span>Oferta concentrada</span></div>
                <div class="seo-ojeador-card"><strong><?php echo esc_html(number_format_i18n((float) ($sum['avg_price_data_coverage_pct'] ?? 0), 1)); ?>%</strong><span>Cobertura media de precio</span></div>
            </div>

            <?php if ($operational) : ?>
                <div style="margin:22px 0 8px"><h3 style="margin:0">Bloqueos operativos</h3><p class="seo-ojeador-muted" style="margin:4px 0 0">Antes de interpretar categorías pendientes, resuelve estos puntos.</p></div>
                <div class="seo-ojeador-action-grid">
                    <?php foreach ($operational as $rec) : $tone = self::analysis_tone_color($rec['tone'] ?? ''); ?>
                        <div class="seo-ojeador-box" style="border-left:4px solid <?php echo esc_attr($tone); ?>">
                            <strong><?php echo esc_html((string) ($rec['signal'] ?? '')); ?></strong>
                            <p style="margin:7px 0"><strong>Acción:</strong> <?php echo esc_html((string) ($rec['action'] ?? '')); ?></p>
                            <small class="seo-ojeador-muted"><?php echo esc_html((string) ($rec['reason'] ?? '')); ?></small>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div style="display:flex;justify-content:space-between;gap:12px;align-items:end;flex-wrap:wrap;margin:24px 0 8px">
                <div><h3 style="margin:0">Recomendaciones comerciales</h3><p class="seo-ojeador-muted" style="margin:4px 0 0">Sólo aparecen categorías que activan una regla concreta. Una categoría puede generar varias recomendaciones.</p></div>
                <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=' . self::PAGE . '&ojeador_tab=products')); ?>">Abrir comparación de productos</a>
            </div>
            <div class="seo-ojeador-table">
                <table class="widefat striped">
                    <thead><tr><th>Prioridad</th><th>Categoría</th><th>Índice oportunidad</th><th>Señal</th><th>Qué hacer</th><th>Por qué</th><th>Datos clave</th></tr></thead>
                    <tbody>
                    <?php foreach (array_slice($recommendations, 0, 50) as $rec) : $tone = self::analysis_tone_color($rec['tone'] ?? ''); ?>
                        <tr>
                            <td><strong style="color:<?php echo esc_attr($tone); ?>"><?php echo esc_html(number_format_i18n(absint($rec['priority'] ?? 0))); ?></strong></td>
                            <td><strong><?php echo esc_html((string) ($rec['category_name'] ?? '')); ?></strong><br><small class="seo-ojeador-muted"><?php echo esc_html((string) ($rec['query_text'] ?? '')); ?></small></td>
                            <td><strong><?php echo esc_html(number_format_i18n((float) ($rec['opportunity_index'] ?? 0), 1)); ?></strong><br><small>gap <?php echo esc_html(number_format_i18n((float) ($rec['catalog_gap_index'] ?? 0), 0)); ?> · comp. <?php echo esc_html(number_format_i18n((float) ($rec['competition_index'] ?? 0), 0)); ?> · vis. <?php echo esc_html(number_format_i18n((float) ($rec['visibility_index'] ?? 0), 0)); ?></small></td>
                            <td><span class="seo-ojeador-pill" style="color:<?php echo esc_attr($tone); ?>"><?php echo esc_html((string) ($rec['signal'] ?? '')); ?></span></td>
                            <td><strong><?php echo esc_html((string) ($rec['action'] ?? '')); ?></strong></td>
                            <td><?php echo esc_html((string) ($rec['reason'] ?? '')); ?></td>
                            <td><small><?php echo esc_html(number_format_i18n(absint($rec['woo_product_count'] ?? 0))); ?> prod. propios · <?php echo esc_html(number_format_i18n(absint($rec['merchant_count'] ?? 0))); ?> comercios · <?php echo esc_html(number_format_i18n((float) ($rec['analista_impressions'] ?? 0), 0)); ?> imp.</small></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$recommendations) : ?><tr><td colspan="7">No hay recomendaciones comerciales prioritarias con los datos actuales.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>

            <details class="seo-ojeador-box seo-ojeador-analysis-detail" style="margin-top:18px">
                <summary>Índices por categoría</summary>
                <p class="seo-ojeador-muted">El índice de oportunidad es una prioridad interna, no una previsión de ventas. La profundidad de catálogo se compara con nuestras propias categorías; competencia usa comercios observados; visibilidad usa impresiones de Analista; reseñas usa volumen de reviews.</p>
                <div class="seo-ojeador-table" style="margin-top:10px">
                    <table class="widefat striped">
                        <thead><tr><th>Categoría</th><th>Productos propios</th><th>Comercios</th><th>Analista 28 d</th><th>Oportunidad</th><th>Gap catálogo</th><th>Competencia</th><th>Visibilidad</th><th>Reviews</th><th>Promo</th><th>Concentración</th><th>Acción principal</th></tr></thead>
                        <tbody>
                        <?php foreach ($categories as $row) : if (empty($row['ojeador_eligible']) || (string) ($row['market_status'] ?? '') !== 'ok') continue; $tone = self::analysis_tone_color($row['analysis_tone'] ?? ''); ?>
                            <tr>
                                <td><strong><?php echo esc_html((string) (($row['category_name'] ?? '') ?: ($row['woo_category'] ?? ''))); ?></strong></td>
                                <td><?php echo esc_html(number_format_i18n(absint($row['woo_product_count'] ?? 0))); ?></td>
                                <td><?php echo esc_html(number_format_i18n(absint($row['merchant_count'] ?? 0))); ?></td>
                                <td><?php echo esc_html(number_format_i18n((float) ($row['analista_impressions'] ?? 0), 0)); ?> imp.</td>
                                <td><strong><?php echo esc_html(number_format_i18n((float) ($row['opportunity_index'] ?? 0), 1)); ?></strong></td>
                                <td><?php echo esc_html(number_format_i18n((float) ($row['catalog_gap_index'] ?? 0), 0)); ?></td>
                                <td><?php echo esc_html(number_format_i18n((float) ($row['competition_index'] ?? 0), 0)); ?></td>
                                <td><?php echo esc_html(number_format_i18n((float) ($row['visibility_index'] ?? 0), 0)); ?></td>
                                <td><?php echo esc_html(number_format_i18n((float) ($row['review_index'] ?? 0), 0)); ?></td>
                                <td><?php echo esc_html(number_format_i18n((float) ($row['promo_index'] ?? 0), 0)); ?></td>
                                <td><?php echo esc_html(number_format_i18n((float) ($row['concentration_index'] ?? 0), 0)); ?></td>
                                <td><span class="seo-ojeador-pill" style="color:<?php echo esc_attr($tone); ?>"><?php echo esc_html((string) ($row['analysis_signal'] ?? '')); ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </details>

            <details class="seo-ojeador-box seo-ojeador-analysis-detail" style="margin-top:12px">
                <summary>Comercios más visibles en la muestra</summary>
                <div class="seo-ojeador-table" style="margin-top:10px">
                    <table class="widefat striped">
                        <thead><tr><th>Comercio</th><th>Apariciones</th><th>Categorías</th><th>Con precio</th><th>Con descuento</th></tr></thead>
                        <tbody>
                        <?php foreach ($merchants as $merchant) : ?>
                            <tr><td><strong><?php echo esc_html((string) ($merchant['merchant'] ?? '')); ?></strong></td><td><?php echo esc_html(number_format_i18n(absint($merchant['appearances'] ?? 0))); ?></td><td><?php echo esc_html(number_format_i18n(absint($merchant['categories'] ?? 0))); ?></td><td><?php echo esc_html(number_format_i18n(absint($merchant['rows_with_price'] ?? 0))); ?></td><td><?php echo esc_html(number_format_i18n(absint($merchant['discounted_rows'] ?? 0))); ?></td></tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </details>
        </section>
        <?php
    }

    private static function render_stars($stars) {
        $stars = is_array($stars) ? $stars : array();
        $rows = (array) ($stars['rows'] ?? array());
        $kpis = (array) ($stars['kpis'] ?? array());
        $filters = (array) ($stars['filters'] ?? array());
        $providers = (array) ($stars['provider_options'] ?? array());
        $categories = (array) ($stars['category_options'] ?? array());
        $classes = (array) ($stars['class_options'] ?? array());
        $page = absint($stars['page'] ?? 1);
        $pages = absint($stars['pages'] ?? 1);
        $total = absint($stars['total'] ?? 0);

        $base_args = array(
            'page' => self::PAGE,
            'ojeador_tab' => 'stars',
            'sq' => (string) ($filters['q'] ?? ''),
            'sprovider' => (string) ($filters['provider'] ?? ''),
            'scat' => absint($filters['term_id'] ?? 0),
            'sclass' => (string) ($filters['class'] ?? ''),
            'sminimp' => (string) ($filters['min_impressions'] ?? ''),
            'ssort' => (string) ($filters['sort'] ?? 'price_status'),
            'sper' => absint($filters['per_page'] ?? 100),
        );
        ?>
        <section class="seo-ojeador-stars">
            <div style="display:flex;justify-content:space-between;gap:12px;align-items:end;flex-wrap:wrap;margin:20px 0 8px">
                <div>
                    <h2 style="margin:0">Posición de precio</h2>
                    <p class="seo-ojeador-muted" style="margin:4px 0 0">Una sola lectura comercial: verde = podemos usarlo, amarillo = estamos dentro de mercado, rojo = no conviene promocionarlo con el coste actual. El cálculo usa coste de proveedor +20%.</p>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap">
                    <a class="button" href="<?php echo esc_url(add_query_arg(array('page'=>self::PAGE,'ojeador_tab'=>'stars','stars_refresh'=>1), admin_url('admin.php'))); ?>">Recalcular</a>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><?php wp_nonce_field('seo_ojeador_export_stars_json'); ?><input type="hidden" name="action" value="seo_ojeador_export_stars_json"><button class="button" type="submit">Exportar JSON precios</button></form>
                </div>
            </div>

            <div class="seo-ojeador-cards seo-ojeador-price-cards">
                <div class="seo-ojeador-card seo-ojeador-price-good"><strong><?php echo esc_html(number_format_i18n(absint($kpis['price_good'] ?? 0))); ?></strong><span>Precio muy bueno<br><small>Usable / promocionable</small></span></div>
                <div class="seo-ojeador-card seo-ojeador-price-market"><strong><?php echo esc_html(number_format_i18n(absint($kpis['price_market'] ?? 0))); ?></strong><span>Precio de mercado<br><small>Se puede modular</small></span></div>
                <div class="seo-ojeador-card seo-ojeador-price-bad"><strong><?php echo esc_html(number_format_i18n(absint($kpis['price_bad'] ?? 0))); ?></strong><span>Precio muy malo<br><small>No promocionar así</small></span></div>
            </div>
            <?php if (!empty($kpis['without_comparison'])) : ?><p class="seo-ojeador-muted"><?php echo esc_html(number_format_i18n(absint($kpis['without_comparison']))); ?> productos con impresiones quedan en gris porque todavía no existe una comparación externa suficientemente fiable.</p><?php endif; ?>

            <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" class="seo-ojeador-box">
                <input type="hidden" name="page" value="<?php echo esc_attr(self::PAGE); ?>">
                <input type="hidden" name="ojeador_tab" value="stars">
                <div class="seo-ojeador-filter-grid">
                    <label><strong>Producto</strong><input type="search" name="sq" value="<?php echo esc_attr((string) ($filters['q'] ?? '')); ?>" placeholder="Nombre o SKU"></label>
                    <label><strong>Proveedor</strong><select name="sprovider"><option value="">Todos</option><?php foreach ($providers as $opt) : ?><option value="<?php echo esc_attr((string) ($opt['provider'] ?? '')); ?>" <?php selected((string) ($filters['provider'] ?? ''), (string) ($opt['provider'] ?? '')); ?>><?php echo esc_html((string) ($opt['provider'] ?? '')); ?> (<?php echo esc_html(number_format_i18n(absint($opt['count'] ?? 0))); ?>)</option><?php endforeach; ?></select></label>
                    <label><strong>Categoría</strong><select name="scat"><option value="0">Todas</option><?php foreach ($categories as $opt) : ?><option value="<?php echo absint($opt['term_id'] ?? 0); ?>" <?php selected(absint($filters['term_id'] ?? 0), absint($opt['term_id'] ?? 0)); ?>><?php echo esc_html((string) ($opt['category_name'] ?? '')); ?> (<?php echo esc_html(number_format_i18n(absint($opt['count'] ?? 0))); ?>)</option><?php endforeach; ?></select></label>
                    <label><strong>Estado de precio</strong><select name="sclass"><option value="">Todos</option><?php foreach ($classes as $key=>$label) : ?><option value="<?php echo esc_attr($key); ?>" <?php selected((string) ($filters['class'] ?? ''), $key); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select></label>
                    <label><strong>Impresiones mín.</strong><input type="number" step="1" min="0" name="sminimp" value="<?php echo esc_attr((string) ($filters['min_impressions'] ?? '')); ?>"></label>
                    <label><strong>Orden</strong><select name="ssort"><?php $sorts=array('price_status'=>'Estado de precio','market_gap'=>'Mejor a peor precio','impressions'=>'Más visualizaciones','provider'=>'Proveedor'); foreach ($sorts as $key=>$label) : ?><option value="<?php echo esc_attr($key); ?>" <?php selected((string) ($filters['sort'] ?? 'price_status'), $key); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select></label>
                    <label><strong>Filas</strong><select name="sper"><?php foreach (array(50,100,200) as $n) : ?><option value="<?php echo esc_attr((string) $n); ?>" <?php selected(absint($filters['per_page'] ?? 100), $n); ?>><?php echo esc_html((string) $n); ?></option><?php endforeach; ?></select></label>
                </div>
                <p style="margin-bottom:0"><button class="button button-primary">Aplicar filtros</button> <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=' . self::PAGE . '&ojeador_tab=stars')); ?>">Limpiar</a> <span class="seo-ojeador-muted" style="margin-left:8px"><?php echo esc_html(number_format_i18n($total)); ?> productos</span></p>
            </form>

            <div class="seo-ojeador-table" style="margin-top:14px">
                <table class="widefat striped">
                    <thead><tr><th>Precio</th><th>Nuestro producto</th><th>Categoría</th><th>Coste / objetivo</th><th>Rango de mercado</th><th>Vs. mediana</th><th>Visualizaciones</th><th>Qué hacer</th></tr></thead>
                    <tbody>
                    <?php foreach ($rows as $row) :
                        $class = sanitize_key((string) ($row['star_class'] ?? ''));
                        $row_class = $class === 'precio_bueno' ? 'seo-ojeador-row-good' : ($class === 'precio_malo' ? 'seo-ojeador-row-bad' : ($class === 'precio_mercado' ? 'seo-ojeador-row-market' : 'seo-ojeador-row-neutral'));
                        $cost = isset($row['supplier_cost']) && is_numeric($row['supplier_cost']) ? (float) $row['supplier_cost'] : null;
                        $target = isset($row['target_price_20']) && is_numeric($row['target_price_20']) ? (float) $row['target_price_20'] : null;
                        $min = isset($row['market_price_min']) && is_numeric($row['market_price_min']) ? (float) $row['market_price_min'] : null;
                        $median = isset($row['market_price_median']) && is_numeric($row['market_price_median']) ? (float) $row['market_price_median'] : null;
                        $max = isset($row['market_price_max']) && is_numeric($row['market_price_max']) ? (float) $row['market_price_max'] : null;
                        $vs = isset($row['price_vs_market_median_pct']) && is_numeric($row['price_vs_market_median_pct']) ? (float) $row['price_vs_market_median_pct'] : null;
                    ?>
                        <tr class="<?php echo esc_attr($row_class); ?>">
                            <td><strong><?php echo esc_html((string) ($row['star_label'] ?? '')); ?></strong></td>
                            <td><a href="<?php echo esc_url(admin_url('post.php?post=' . absint($row['object_id'] ?? 0) . '&action=edit')); ?>"><strong><?php echo esc_html((string) ($row['product_name'] ?? '')); ?></strong></a><br><small class="seo-ojeador-muted"><?php echo esc_html((string) ($row['provider'] ?? '—')); ?><?php if (!empty($row['supplier_sku'])) : ?> · SKU <?php echo esc_html((string) $row['supplier_sku']); ?><?php endif; ?></small></td>
                            <td><?php echo esc_html((string) ($row['category_name'] ?? '')); ?></td>
                            <td><?php if ($cost !== null) : ?>Coste: <strong><?php echo esc_html(number_format_i18n($cost,2)); ?> €</strong><?php endif; ?><br><?php if ($target !== null) : ?>+20%: <strong><?php echo esc_html(number_format_i18n($target,2)); ?> €</strong><?php endif; ?></td>
                            <td><?php if ($min !== null && $max !== null) : ?><strong><?php echo esc_html(number_format_i18n($min,2)); ?>–<?php echo esc_html(number_format_i18n($max,2)); ?> €</strong><?php if ($median !== null) : ?><br><small>Mediana <?php echo esc_html(number_format_i18n($median,2)); ?> € · <?php echo esc_html(number_format_i18n(absint($row['competitor_comparable_count'] ?? 0))); ?> comparables</small><?php endif; ?><?php else : ?>—<br><small>Sin comparación fiable</small><?php endif; ?></td>
                            <td><?php if ($vs !== null) : ?><strong><?php echo $vs > 0 ? '+' : ''; ?><?php echo esc_html(number_format_i18n($vs,1)); ?>%</strong><?php else : ?>—<?php endif; ?></td>
                            <td><strong><?php echo esc_html(number_format_i18n((float) ($row['product_impressions'] ?? 0),0)); ?></strong> imp.<br><small><?php echo esc_html(number_format_i18n((float) ($row['product_clicks'] ?? 0),0)); ?> clics</small></td>
                            <td><strong><?php echo esc_html((string) ($row['recommendation'] ?? '')); ?></strong><br><small class="seo-ojeador-muted"><?php echo esc_html((string) ($row['reason'] ?? '')); ?></small></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$rows) : ?><tr><td colspan="8">No hay productos que cumplan estos filtros.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($pages > 1) : ?>
                <div class="tablenav"><div class="tablenav-pages"><span class="displaying-num"><?php echo esc_html(number_format_i18n($total)); ?> productos</span>
                    <?php if ($page > 1) : $prev=$base_args; $prev['spage']=$page-1; ?><a class="button" href="<?php echo esc_url(add_query_arg($prev, admin_url('admin.php'))); ?>">‹ Anterior</a><?php endif; ?>
                    <span class="paging-input"><?php echo esc_html(number_format_i18n($page)); ?> de <span class="total-pages"><?php echo esc_html(number_format_i18n($pages)); ?></span></span>
                    <?php if ($page < $pages) : $next=$base_args; $next['spage']=$page+1; ?><a class="button" href="<?php echo esc_url(add_query_arg($next, admin_url('admin.php'))); ?>">Siguiente ›</a><?php endif; ?>
                </div></div>
            <?php endif; ?>
        </section>
        <?php
    }

    private static function render_products($comparison, $category_options) {
        $comparison = is_array($comparison) ? $comparison : array();
        $rows = (array) ($comparison['rows'] ?? array());
        $filters = (array) ($comparison['filters'] ?? array());
        $page = absint($comparison['page'] ?? 1);
        $pages = absint($comparison['pages'] ?? 1);
        $total = absint($comparison['total'] ?? 0);
        $base_args = array(
            'page' => self::PAGE,
            'ojeador_tab' => 'products',
            'pcat' => absint($filters['term_id'] ?? 0),
            'pq' => (string) ($filters['q'] ?? ''),
            'pmerchant' => (string) ($filters['merchant'] ?? ''),
            'pmin' => (string) ($filters['min_price'] ?? ''),
            'pmax' => (string) ($filters['max_price'] ?? ''),
            'prating' => (string) ($filters['min_rating'] ?? ''),
            'previews' => (string) ($filters['min_reviews'] ?? ''),
            'pdiscount' => !empty($filters['discounted']) ? 1 : 0,
            'psort' => (string) ($filters['sort'] ?? 'position'),
            'pper' => absint($filters['per_page'] ?? 100),
        );
        ?>
        <section class="seo-ojeador-products">
            <div style="margin:20px 0 8px"><h2 style="margin:0">Comparador de productos encontrados</h2><p class="seo-ojeador-muted" style="margin:4px 0 0">Explora los productos devueltos por Google Shopping. Úsalo para comparar productos equivalentes antes de tomar decisiones de surtido, precio o promoción.</p></div>

            <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" class="seo-ojeador-box seo-ojeador-filterbox">
                <input type="hidden" name="page" value="<?php echo esc_attr(self::PAGE); ?>">
                <input type="hidden" name="ojeador_tab" value="products">
                <div class="seo-ojeador-filter-grid">
                    <label><strong>Categoría</strong><select name="pcat"><option value="0">Todas</option><?php foreach ((array) $category_options as $opt) : ?><option value="<?php echo absint($opt['term_id'] ?? 0); ?>" <?php selected(absint($filters['term_id'] ?? 0), absint($opt['term_id'] ?? 0)); ?>><?php echo esc_html((string) ($opt['category_name'] ?? '')); ?> (<?php echo esc_html(number_format_i18n(absint($opt['products_found'] ?? 0))); ?>)</option><?php endforeach; ?></select></label>
                    <label><strong>Producto / marca / modelo</strong><input type="search" name="pq" value="<?php echo esc_attr((string) ($filters['q'] ?? '')); ?>" placeholder="Buscar producto"></label>
                    <label><strong>Comercio</strong><input type="search" name="pmerchant" value="<?php echo esc_attr((string) ($filters['merchant'] ?? '')); ?>" placeholder="Leroy Merlin, Amazon..."></label>
                    <label><strong>Precio mín.</strong><input type="number" step="0.01" min="0" name="pmin" value="<?php echo esc_attr((string) ($filters['min_price'] ?? '')); ?>"></label>
                    <label><strong>Precio máx.</strong><input type="number" step="0.01" min="0" name="pmax" value="<?php echo esc_attr((string) ($filters['max_price'] ?? '')); ?>"></label>
                    <label><strong>Rating mínimo</strong><input type="number" step="0.1" min="0" max="5" name="prating" value="<?php echo esc_attr((string) ($filters['min_rating'] ?? '')); ?>"></label>
                    <label><strong>Reviews mínimas</strong><input type="number" step="1" min="0" name="previews" value="<?php echo esc_attr((string) ($filters['min_reviews'] ?? '')); ?>"></label>
                    <label><strong>Orden</strong><select name="psort">
                        <?php $sorts = array('position'=>'Posición Google','price_asc'=>'Precio ↑','price_desc'=>'Precio ↓','rating_desc'=>'Rating ↓','reviews_desc'=>'Reviews ↓','discount_desc'=>'Descuento ↓','merchant'=>'Comercio','title'=>'Producto','recent'=>'Más reciente'); foreach ($sorts as $key=>$label) : ?><option value="<?php echo esc_attr($key); ?>" <?php selected((string) ($filters['sort'] ?? 'position'), $key); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?>
                    </select></label>
                    <label><strong>Filas</strong><select name="pper"><?php foreach (array(50,100,200) as $n) : ?><option value="<?php echo esc_attr((string) $n); ?>" <?php selected(absint($filters['per_page'] ?? 100), $n); ?>><?php echo esc_html((string) $n); ?></option><?php endforeach; ?></select></label>
                    <label class="seo-ojeador-check"><input type="checkbox" name="pdiscount" value="1" <?php checked(!empty($filters['discounted'])); ?>> Sólo con descuento</label>
                </div>
                <p style="margin-bottom:0"><button class="button button-primary">Aplicar filtros</button> <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=' . self::PAGE . '&ojeador_tab=products')); ?>">Limpiar</a> <span class="seo-ojeador-muted" style="margin-left:8px"><?php echo esc_html(number_format_i18n($total)); ?> resultados</span></p>
            </form>

            <div class="seo-ojeador-table" style="margin-top:14px">
                <table class="widefat striped">
                    <thead><tr><th>Categoría</th><th>Producto</th><th>Comercio</th><th>Precio</th><th>Descuento</th><th>Rating / reviews</th><th>Posición</th><th>Observado</th></tr></thead>
                    <tbody>
                    <?php foreach ($rows as $row) :
                        $price = ($row['price'] !== null && $row['price'] !== '') ? (float) $row['price'] : null;
                        $old = ($row['old_price'] !== null && $row['old_price'] !== '') ? (float) $row['old_price'] : null;
                        $discount = ($row['discount_pct'] !== null && $row['discount_pct'] !== '') ? (float) $row['discount_pct'] : null;
                        $rating = ($row['rating'] !== null && $row['rating'] !== '') ? (float) $row['rating'] : null;
                    ?>
                        <tr>
                            <td><strong><?php echo esc_html((string) ($row['category_name'] ?? '')); ?></strong></td>
                            <td><?php if (!empty($row['product_url'])) : ?><a href="<?php echo esc_url((string) $row['product_url']); ?>" target="_blank" rel="noopener noreferrer"><strong><?php echo esc_html((string) ($row['title'] ?? '')); ?></strong></a><?php else : ?><strong><?php echo esc_html((string) ($row['title'] ?? '')); ?></strong><?php endif; ?><?php if (!empty($row['brand']) || !empty($row['model'])) : ?><br><small class="seo-ojeador-muted"><?php echo esc_html(trim((string) ($row['brand'] ?? '') . ' ' . (string) ($row['model'] ?? ''))); ?></small><?php endif; ?></td>
                            <td><?php echo esc_html((string) ($row['merchant'] ?? '—')); ?></td>
                            <td><?php echo $price !== null ? esc_html(number_format_i18n($price, 2)) . ' ' . esc_html((string) ($row['currency'] ?? 'EUR')) : '—'; ?><?php if ($old !== null && $old > 0) : ?><br><small class="seo-ojeador-muted"><del><?php echo esc_html(number_format_i18n($old, 2)); ?></del></small><?php endif; ?></td>
                            <td><?php echo $discount !== null ? '<strong>' . esc_html(number_format_i18n($discount, 1)) . '%</strong>' : '—'; ?></td>
                            <td><?php echo $rating !== null ? esc_html(number_format_i18n($rating, 1)) . ' / 5' : '—'; ?><br><small><?php echo esc_html(number_format_i18n(absint($row['reviews'] ?? 0))); ?> reviews</small></td>
                            <td><?php echo esc_html(number_format_i18n(absint($row['result_position'] ?? 0))); ?></td>
                            <td><?php echo esc_html((string) ($row['observed_at'] ?? '—')); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$rows) : ?><tr><td colspan="8">No hay productos que cumplan los filtros.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($pages > 1) : ?>
                <div class="tablenav"><div class="tablenav-pages"><span class="displaying-num"><?php echo esc_html(number_format_i18n($total)); ?> elementos</span>
                    <?php if ($page > 1) : $prev = $base_args; $prev['ppage']=$page-1; ?><a class="button" href="<?php echo esc_url(add_query_arg($prev, admin_url('admin.php'))); ?>">‹ Anterior</a><?php endif; ?>
                    <span class="paging-input"><?php echo esc_html(number_format_i18n($page)); ?> de <span class="total-pages"><?php echo esc_html(number_format_i18n($pages)); ?></span></span>
                    <?php if ($page < $pages) : $next = $base_args; $next['ppage']=$page+1; ?><a class="button" href="<?php echo esc_url(add_query_arg($next, admin_url('admin.php'))); ?>">Siguiente ›</a><?php endif; ?>
                </div></div>
            <?php endif; ?>
        </section>
        <?php
    }

    public static function render() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $tab = sanitize_key((string) self::query_arg('ojeador_tab', 'analysis'));
        if (!in_array($tab, array('analysis','stars','products','operation','merchant'), true)) {
            $tab = 'analysis';
        }

        $settings = SEO_Ojeador_Shopping::settings();
        $usage = SEO_Ojeador_Shopping::usage_month();
        $scraper_usage = SEO_Ojeador_Shopping::scraperapi_usage_month();
        $ready = SEO_Ojeador_Shopping::readiness();
        $run = SEO_Ojeador_DB::active_run();
        if (!$run) $run = SEO_Ojeador_DB::latest_run();
        $notice = sanitize_key((string) self::query_arg('ojeador_notice', ''));
        $error = '' !== (string) self::query_arg('ojeador_error', '') ? sanitize_text_field(rawurldecode((string) self::query_arg('ojeador_error', ''))) : '';

        $analysis = array();
        $stars = array();
        $comparison = array();
        $category_options = array();
        $summary = array();
        $rows = array();
        $logs = array();
        $latest_log = array();
        $search = '';

        if ($tab === 'analysis') {
            $analysis = SEO_Ojeador_Analysis::dashboard(100);
        } elseif ($tab === 'merchant') {
            // Only local snapshots are rendered; no API call on page load.
        } elseif ($tab === 'stars') {
            $stars = SEO_Ojeador_Stars::dashboard(array(
                'q' => sanitize_text_field(self::query_arg('sq', '')),
                'provider' => sanitize_text_field(self::query_arg('sprovider', '')),
                'term_id' => absint(self::query_arg('scat', 0)),
                'class' => sanitize_key((string) self::query_arg('sclass', '')),
                'min_impressions' => '' !== (string) self::query_arg('sminimp', '') ? sanitize_text_field(self::query_arg('sminimp', '')) : '',
                'sort' => sanitize_key((string) self::query_arg('ssort', 'price_status')),
                'page' => absint(self::query_arg('spage', 1)),
                'per_page' => absint(self::query_arg('sper', 100)),
                'refresh' => !empty(self::query_arg('stars_refresh', '')) ? 1 : 0,
            ));
        } elseif ($tab === 'products') {
            $comparison = SEO_Ojeador_Analysis::comparison_products(array(
                'term_id' => absint(self::query_arg('pcat', 0)),
                'q' => sanitize_text_field(self::query_arg('pq', '')),
                'merchant' => sanitize_text_field(self::query_arg('pmerchant', '')),
                'min_price' => '' !== (string) self::query_arg('pmin', '') ? sanitize_text_field(self::query_arg('pmin', '')) : '',
                'max_price' => '' !== (string) self::query_arg('pmax', '') ? sanitize_text_field(self::query_arg('pmax', '')) : '',
                'min_rating' => '' !== (string) self::query_arg('prating', '') ? sanitize_text_field(self::query_arg('prating', '')) : '',
                'min_reviews' => '' !== (string) self::query_arg('previews', '') ? sanitize_text_field(self::query_arg('previews', '')) : '',
                'discounted' => !empty(self::query_arg('pdiscount', '')) ? 1 : 0,
                'sort' => sanitize_key((string) self::query_arg('psort', 'position')),
                'page' => absint(self::query_arg('ppage', 1)),
                'per_page' => absint(self::query_arg('pper', 100)),
            ));
            $category_options = SEO_Ojeador_Analysis::comparison_category_options();
        } else {
            $summary = SEO_Ojeador_DB::category_summary();
            $search = sanitize_text_field(self::query_arg('s', ''));
            $rows = SEO_Ojeador_DB::list_market_categories(array('limit'=>1000, 'search'=>$search));
            $logs = SEO_Ojeador_DB::list_query_logs(100);
            $latest_log = SEO_Ojeador_DB::latest_query_log();
        }
        ?>
        <div class="wrap seo-ojeador-v090">
            

            <div class="seo-ojeador-head">
                <div>
                    <h1 style="margin-bottom:0">Ojeador <small style="font-size:14px;color:#646970">v<?php echo esc_html(SEO_OJEADOR_VERSION); ?></small></h1>
                    <p class="seo-ojeador-sub">Análisis de oportunidades comerciales, comparación de precios y referencias de Google Merchant. El histórico Shopping se conserva. Las recomendaciones sirven para decidir qué revisar; no ejecutan cambios automáticos.</p>
                </div>
                <div class="seo-ojeador-actions">
                    <?php if (SEO_Ojeador_Worker::is_pending()) : ?>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><?php wp_nonce_field('seo_ojeador_stop'); ?><input type="hidden" name="action" value="seo_ojeador_stop"><button class="button">Detener</button></form>
                    <?php else : ?>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><?php wp_nonce_field('seo_ojeador_start'); ?><input type="hidden" name="action" value="seo_ojeador_start"><button class="button button-primary" <?php disabled(is_wp_error($ready)); ?>>Continuar / actualizar mercado</button></form>
                    <?php endif; ?>
                    <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=seo-processes')); ?>">Procesos</a>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><?php wp_nonce_field('seo_ojeador_export_analysis_json'); ?><input type="hidden" name="action" value="seo_ojeador_export_analysis_json"><button class="button" type="submit">Exportar JSON análisis</button></form>
                </div>
            </div>

            <?php if ($notice === 'settings_saved') : ?><div class="notice notice-success inline"><p>Configuración guardada.</p></div><?php endif; ?>
            <?php if ($notice === 'connection_ok') : ?>
                <?php $tested_provider = sanitize_key((string) self::query_arg('ojeador_provider', '')); ?>
                <div class="notice notice-success inline"><p><strong>Cadena de conexión operativa.</strong>
                    <?php if ($tested_provider === 'scraperapi') : ?>
                        SerpApi no estaba disponible y ScraperAPI respondió correctamente como fallback.
                    <?php else : ?>
                        SerpApi respondió correctamente; ScraperAPI queda preparada como fallback.
                    <?php endif; ?>
                </p></div>
            <?php endif; ?>
            <?php if ($notice === 'scan_started') : ?><div class="notice notice-success inline"><p>Ojeador está consultando categorías aptas.</p></div><?php endif; ?>
            <?php if ($notice === 'scan_stopped') : ?><div class="notice notice-info inline"><p>Proceso detenido.</p></div><?php endif; ?>
            <?php if ($error !== '') : ?><div class="notice notice-error inline"><p><?php echo esc_html($error); ?></p></div><?php endif; ?>
            <?php if (is_wp_error($ready)) : ?><div class="notice notice-warning inline"><p><strong>Google Shopping:</strong> <?php echo esc_html($ready->get_error_message()); ?></p></div><?php endif; ?>

            <nav class="nav-tab-wrapper">
                <a class="nav-tab <?php echo $tab === 'analysis' ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url(admin_url('admin.php?page=' . self::PAGE . '&ojeador_tab=analysis')); ?>">Análisis y recomendaciones</a>
                <a class="nav-tab <?php echo $tab === 'stars' ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url(admin_url('admin.php?page=' . self::PAGE . '&ojeador_tab=stars')); ?>">Posición de precio</a>
                <a class="nav-tab <?php echo $tab === 'products' ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url(admin_url('admin.php?page=' . self::PAGE . '&ojeador_tab=products')); ?>">Comparador de productos</a>
                <a class="nav-tab <?php echo $tab === 'operation' ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url(admin_url('admin.php?page=' . self::PAGE . '&ojeador_tab=operation')); ?>">Operación y consultas</a>
                <a class="nav-tab <?php echo $tab === 'merchant' ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url(admin_url('admin.php?page=' . self::PAGE . '&ojeador_tab=merchant')); ?>">Merchant · Comparación gratuita</a>
            </nav>

            <?php if ($tab === 'merchant') : ?>
                <?php SEO_Ojeador_Merchant::render(); ?>
            <?php elseif ($tab === 'analysis') : ?>
                <?php self::render_analysis($analysis); ?>
            <?php elseif ($tab === 'stars') : ?>
                <?php self::render_stars($stars); ?>
            <?php elseif ($tab === 'products') : ?>
                <?php self::render_products($comparison, $category_options); ?>
            <?php else : ?>
                <div class="seo-ojeador-cards">
                    <div class="seo-ojeador-card"><strong><?php echo esc_html(number_format_i18n($summary['target'])); ?></strong><span>Categorías aptas Ojeador</span></div>
                    <div class="seo-ojeador-card"><strong><?php echo esc_html(number_format_i18n($summary['excluded'] ?? 0)); ?></strong><span>Excluidas por vocabulario</span></div>
                    <div class="seo-ojeador-card"><strong><?php echo esc_html(number_format_i18n($summary['consulted'])); ?></strong><span>Consultadas</span></div>
                    <div class="seo-ojeador-card"><strong><?php echo esc_html(number_format_i18n($summary['pending'])); ?></strong><span>Pendientes</span></div>
                    <div class="seo-ojeador-card"><strong><?php echo esc_html(number_format_i18n($usage['used'])); ?> / <?php echo esc_html(number_format_i18n($usage['limit'])); ?></strong><span>Uso SerpApi</span></div>
                    <div class="seo-ojeador-card"><strong><?php echo esc_html(number_format_i18n(absint($scraper_usage['requests'] ?? 0))); ?></strong><span>Peticiones ScraperAPI</span></div>
                    <div class="seo-ojeador-card"><strong><?php echo esc_html(number_format_i18n($summary['with_results'])); ?></strong><span>Con resultados</span></div>
                    <div class="seo-ojeador-card"><strong><?php echo esc_html(number_format_i18n($summary['errors'] ?? 0)); ?></strong><span>Errores</span></div>
                    <div class="seo-ojeador-card"><strong><?php echo esc_html(number_format_i18n($summary['coverage'], 1)); ?>%</strong><span>Cobertura consultada</span></div>
                </div>

                <?php if ($run) : ?>
                    <div class="seo-ojeador-run"><strong>Último proceso:</strong> <?php echo esc_html((string) ($run['status'] ?? '')); ?> · <?php echo esc_html(number_format_i18n(absint($run['processed_categories'] ?? 0))); ?> categorías · <?php echo esc_html(number_format_i18n(absint($run['api_queries'] ?? 0))); ?> peticiones HTTP · <?php echo esc_html(number_format_i18n(absint($run['results_seen'] ?? 0))); ?> resultados · <?php echo esc_html(number_format_i18n(absint($run['errors_count'] ?? 0))); ?> errores.</div>
                <?php endif; ?>

                <div style="display:flex;justify-content:space-between;gap:12px;align-items:end;flex-wrap:wrap;margin:22px 0 8px;">
                    <div><h2 style="margin:0">Mercado por categorías</h2><p class="seo-ojeador-muted" style="margin:4px 0 0">Estado operativo de cada shopping_query.</p></div>
                    <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>"><input type="hidden" name="page" value="<?php echo esc_attr(self::PAGE); ?>"><input type="hidden" name="ojeador_tab" value="operation"><input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Categoría o consulta"><button class="button">Buscar</button></form>
                </div>
                <div class="seo-ojeador-table"><table class="widefat striped"><thead><tr><th>Categoría</th><th>Consulta Google</th><th>Prioridad</th><th>Productos catálogo</th><th>Resultados</th><th>Estado</th><th>Última</th><th>Próxima</th></tr></thead><tbody>
                    <?php foreach ($rows as $row) :
                        $eligible = !empty($row['ojeador_eligible']); $first_scan = !empty($row['needs_trusted_scan']); $due = !empty($row['ojeador_due']);
                        if (!$eligible) { $label='No apta'; $color='#b32d2e'; } elseif ($first_scan) { $label='Pendiente'; $color='#996800'; } else { list($label,$color)=self::status_label($row['status'] ?? ''); }
                        $category_name=(string)($row['category_name'] ?: $row['woo_category']);
                        $query_text=$eligible ? (string)($first_scan ? ($row['trusted_query'] ?? '') : (($row['query_text'] ?? '') ?: ($row['trusted_query'] ?? ''))) : '';
                        $priority_label=!$eligible?'Excluida':($first_scan?'Primera consulta':($due?'Actualizar':'Al día'));
                    ?>
                        <tr><td><strong><?php echo esc_html($category_name); ?></strong></td><td><?php echo $query_text!==''?esc_html($query_text):'—'; ?></td><td><?php echo esc_html($priority_label); ?></td><td><?php echo esc_html(number_format_i18n(absint($row['woo_product_count'] ?? 0))); ?></td><td><?php echo esc_html(number_format_i18n(absint($row['result_count'] ?? 0))); ?></td><td><span class="seo-ojeador-pill" style="color:<?php echo esc_attr($color); ?>"><?php echo esc_html($label); ?></span><?php if (!empty($row['last_error'])) : ?><br><small><?php echo esc_html(wp_trim_words((string)$row['last_error'],14)); ?></small><?php endif; ?></td><td><?php echo !empty($row['last_scan_at'])?esc_html((string)$row['last_scan_at']):'—'; ?></td><td><?php echo !empty($row['next_scan_at'])?esc_html((string)$row['next_scan_at']):'—'; ?></td></tr>
                    <?php endforeach; ?>
                </tbody></table></div>

                <div style="display:flex;justify-content:space-between;gap:12px;align-items:end;flex-wrap:wrap;margin:22px 0 8px;"><div><h2 style="margin:0">Log de consultas Google Shopping</h2><p class="seo-ojeador-muted" style="margin:4px 0 0">Trazabilidad por petición.</p></div><?php if ($latest_log) : ?><small class="seo-ojeador-muted">Último evento: <?php echo esc_html((string) ($latest_log['created_at'] ?? '')); ?></small><?php endif; ?></div>
                <div class="seo-ojeador-table"><table class="widefat striped"><thead><tr><th>Fecha UTC</th><th>Run</th><th>Categoría</th><th>Consulta</th><th>Resultado</th><th>HTTP</th><th>Recibidos</th><th>Guardados</th><th>Tiempo</th><th>Error</th></tr></thead><tbody>
                    <?php foreach ($logs as $log) : list($log_label,$log_color)=self::query_log_label($log['event_status'] ?? ''); ?>
                        <tr><td><?php echo esc_html((string)($log['created_at'] ?? '')); ?></td><td><?php echo absint($log['run_id'] ?? 0) ?: '—'; ?></td><td><?php echo !empty($log['category_name'])?esc_html((string)$log['category_name']):'—'; ?></td><td><?php echo !empty($log['query_text'])?esc_html((string)$log['query_text']):'—'; ?></td><td><span class="seo-ojeador-pill" style="color:<?php echo esc_attr($log_color); ?>"><?php echo esc_html($log_label); ?></span></td><td><?php echo !empty($log['request_attempted'])?absint($log['http_code'] ?? 0):'—'; ?></td><td><?php echo esc_html(number_format_i18n(absint($log['raw_result_count'] ?? 0))); ?></td><td><?php echo esc_html(number_format_i18n(absint($log['saved_result_count'] ?? 0))); ?></td><td><?php echo !empty($log['duration_ms'])?esc_html(number_format_i18n(absint($log['duration_ms']))).' ms':'—'; ?></td><td><?php echo !empty($log['error_message'])?esc_html(wp_trim_words((string)$log['error_message'],18)):'—'; ?></td></tr>
                    <?php endforeach; ?>
                </tbody></table></div>

                <details class="seo-ojeador-box seo-ojeador-config"><summary>Ritmo de Ojeador</summary>
                    <p class="seo-ojeador-muted" style="margin:12px 0 0;">
                        Las credenciales compartidas de <strong>SerpApi y ScraperAPI</strong> se gestionan en
                        <a href="<?php echo esc_url(admin_url('admin.php?page=seo-provider-connections#seo-google-shared-providers')); ?>">Conexiones con proveedores</a>.
                    </p>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <?php wp_nonce_field('seo_ojeador_settings_save'); ?>
                        <input type="hidden" name="action" value="seo_ojeador_settings_save">
                        <input type="hidden" name="ojeador_scope" value="operation">

                        <div class="seo-ojeador-grid">
                            <label><strong>Actualizar cada</strong><input type="number" min="6" max="2160" name="ojeador[interval_hours]" value="<?php echo absint($settings['interval_hours']); ?>"><small>Horas por categoría.</small></label>
                            <label><strong>Categorías por paso</strong><input type="number" min="1" max="20" name="ojeador[batch_size]" value="<?php echo absint($settings['batch_size']); ?>"></label>
                            <label><strong>Reutilizar consulta durante</strong><input type="number" min="1" max="168" name="ojeador[query_reuse_hours]" value="<?php echo absint($settings['query_reuse_hours']); ?>"><small>Horas.</small></label>
                        </div>
                        <p><label><input type="checkbox" name="ojeador[auto_enabled]" value="1" <?php checked(!empty($settings['auto_enabled'])); ?>> Mantener el mercado actualizado automáticamente</label></p>
                        <p><button class="button button-primary" type="submit">Guardar ritmo de Ojeador</button></p>
                    </form>
                </details>

                <div class="seo-ojeador-actions" style="margin-top:18px">
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><?php wp_nonce_field('seo_ojeador_export_json'); ?><input type="hidden" name="action" value="seo_ojeador_export_json"><button class="button" type="submit">Exportar JSON completo (auditoría)</button></form>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><?php wp_nonce_field('seo_ojeador_export_log_json'); ?><input type="hidden" name="action" value="seo_ojeador_export_log_json"><button class="button" type="submit">Exportar log JSON</button></form>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

}
