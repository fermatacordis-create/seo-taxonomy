<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('seo_google_add_inline_script')) {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
    function seo_google_add_inline_script($script) {
        $script = (string) $script;
        if ('' === trim($script)) {
            return;
        }

        $handle = 'taxocommerce-google-admin';
        if (!wp_script_is($handle, 'registered')) {
            wp_register_script(
                $handle,
                false,
                array(),
                defined('SEO_SYSTEM_VERSION') ? SEO_SYSTEM_VERSION : null,
                true
            );
        }
        wp_enqueue_script($handle);
        wp_add_inline_script($handle, $script);
    }
}

/**
 * Google Intelligence: OAuth, almacenamiento y sincronización de Search Console.
 *
 * Esta primera versión:
 * - guarda Client ID y Client Secret;
 * - inicia y completa OAuth 2.0;
 * - solicita acceso de solo lectura a Search Console;
 * - guarda y renueva tokens;
 * - lista las propiedades accesibles;
 * - permite seleccionar una propiedad y frecuencia de sincronización;
 * - utiliza un callback dedicado mediante admin-post.php;
 * - documenta los errores OAuth más habituales.
 */

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Legacy SEO_GOOGLE_* constant is part of the existing public integration contract; renaming could break stored settings or third-party/monolith interoperability.
const SEO_GOOGLE_SCOPE_SEARCH_CONSOLE = 'https://www.googleapis.com/auth/webmasters.readonly';
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Legacy SEO_GOOGLE_* constant is part of the existing public integration contract; renaming could break stored settings or third-party/monolith interoperability.
const SEO_GOOGLE_OPTION_SETTINGS       = 'seo_google_intelligence_settings';
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Legacy SEO_GOOGLE_* constant is part of the existing public integration contract; renaming could break stored settings or third-party/monolith interoperability.
const SEO_GOOGLE_OPTION_TOKENS         = 'seo_google_intelligence_tokens';
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Legacy SEO_GOOGLE_* constant is part of the existing public integration contract; renaming could break stored settings or third-party/monolith interoperability.
const SEO_GOOGLE_OPTION_DB_VERSION     = 'seo_google_intelligence_db_version';
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Legacy SEO_GOOGLE_* constant is part of the existing public integration contract; renaming could break stored settings or third-party/monolith interoperability.
const SEO_GOOGLE_MODULE_VERSION        = '2.1.0';
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Legacy SEO_GOOGLE_* constant is part of the existing public integration contract; renaming could break stored settings or third-party/monolith interoperability.
const SEO_GOOGLE_DB_VERSION            = '1.5.0';
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Legacy SEO_GOOGLE_* constant is part of the existing public integration contract; renaming could break stored settings or third-party/monolith interoperability.
const SEO_GOOGLE_INITIAL_SYNC_DAYS      = 90;
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Legacy SEO_GOOGLE_* constant is part of the existing public integration contract; renaming could break stored settings or third-party/monolith interoperability.
const SEO_GOOGLE_FINAL_DATA_DELAY_DAYS = 3;
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Legacy SEO_GOOGLE_* constant is part of the existing public integration contract; renaming could break stored settings or third-party/monolith interoperability.
const SEO_GOOGLE_ROW_LIMIT              = 25000;
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Legacy SEO_GOOGLE_* constant is part of the existing public integration contract; renaming could break stored settings or third-party/monolith interoperability.
const SEO_GOOGLE_MAX_ROWS_PER_DAY       = 50000;

// Analisis separado de demanda vs. catalogo para mantener este archivo estable.
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Existing global name is retained for module compatibility.
$seo_google_demand_catalog_file = __DIR__ . '/seo-google-demand-catalog.php';
if (file_exists($seo_google_demand_catalog_file)) {
    require_once $seo_google_demand_catalog_file;
}

// Mercado externo mediante Google Trends (datos importados/exportados).
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Existing global name is retained for module compatibility.
$seo_google_trends_file = __DIR__ . '/seo-google-trends.php';
if (file_exists($seo_google_trends_file)) {
    require_once $seo_google_trends_file;
}

// Motor central: demanda externa -> cobertura interna -> decisiones.
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Existing global name is retained for module compatibility.
$seo_google_opportunity_engine_file = __DIR__ . '/seo-google-opportunity-engine.php';
if (file_exists($seo_google_opportunity_engine_file)) {
    require_once $seo_google_opportunity_engine_file;
}

// Informe final legacy que cruza mercado, Search Console y catalogo.
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Existing global name is retained for module compatibility.
$seo_google_growth_guidance_file = __DIR__ . '/seo-google-growth-guidance.php';
if (file_exists($seo_google_growth_guidance_file)) {
    require_once $seo_google_growth_guidance_file;
}

add_action('admin_post_seo_google_save_settings', 'seo_google_save_settings_handler');
add_action('admin_post_seo_google_connect', 'seo_google_connect_handler');
add_action('admin_post_seo_google_disconnect', 'seo_google_disconnect_handler');
add_action('admin_post_seo_google_test_connection', 'seo_google_test_connection_handler');
add_action('admin_post_seo_google_oauth_callback', 'seo_google_oauth_callback_handler');
add_action('admin_post_seo_google_repair_tables', 'seo_google_repair_tables_handler');
add_action('admin_post_seo_google_export_csv', 'seo_google_export_csv_handler');
add_action('admin_post_seo_google_export_decisions_json', 'seo_google_export_decisions_json_handler');
add_action('admin_init', 'seo_google_maybe_install_tables', 1);
add_action('wp_ajax_seo_google_sync_start', 'seo_google_sync_start_handler');
add_action('wp_ajax_seo_google_sync_day', 'seo_google_sync_day_handler');


/**
 * Intenta instalar el esquema en admin_init cuando el archivo se carga a tiempo.
 * La pantalla y los endpoints AJAX vuelven a comprobarlo, por lo que también
 * funciona cuando este archivo se incluye después de admin_init.
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
function seo_google_maybe_install_tables() {
    seo_google_install_tables(false);
}

/**
 * URL base de Google Intelligence.
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
function seo_google_admin_url($view = 'settings', $args = array()) {
    $view = sanitize_key($view);

    if ('settings' === $view) {
        $url = add_query_arg(
            array('page' => 'taxocommerce-google-bing', 'tgb_tab' => 'connections'),
            admin_url('admin.php')
        );
    } elseif ('sync' === $view) {
        $url = add_query_arg(
            array('page' => 'taxocommerce-google-bing', 'tgb_tab' => 'sync'),
            admin_url('admin.php')
        );
    } elseif ('sources' === $view) {
        $url = add_query_arg(
            array('page' => 'taxocommerce-google-bing', 'tgb_tab' => 'intelligence', 'google_view' => 'summary'),
            admin_url('admin.php')
        );
    } else {
        $url = add_query_arg(
            array(
                'page'        => 'taxocommerce-google-bing',
                'tgb_tab'     => 'intelligence',
                'google_view' => $view,
            ),
            admin_url('admin.php')
        );
    }

    return !empty($args) ? add_query_arg($args, $url) : $url;
}

/**
 * URI exacta que debe registrarse en Google Cloud.
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
function seo_google_redirect_uri() {
    return admin_url('admin-post.php?action=seo_google_oauth_callback');
}

/**
 * Configuración no sensible o de aplicación.
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
function seo_google_get_settings() {
    $defaults = array(
        'client_id'      => '',
        'client_secret'  => '',
        'property_id'    => '',
        'sync_frequency' => 'manual',
        'last_sync'      => '',
    );

    $settings = get_option(SEO_GOOGLE_OPTION_SETTINGS, array());

    return apply_filters('taxocommerce_google_bing_gsc_sync_settings', wp_parse_args(is_array($settings) ? $settings : array(), $defaults));
}

/**
 * Tokens OAuth almacenados por separado.
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
function seo_google_get_tokens() {
    $defaults = array(
        'access_token'  => '',
        'refresh_token' => '',
        'expires_at'    => 0,
        'scope'         => '',
        'token_type'    => 'Bearer',
    );

    $tokens = get_option(SEO_GOOGLE_OPTION_TOKENS, array());

    return wp_parse_args(is_array($tokens) ? $tokens : array(), $defaults);
}

/**
 * Guarda opciones evitando autoload.
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
function seo_google_update_option($option_name, $value) {
    if (false === get_option($option_name, false)) {
        add_option($option_name, $value, '', false);
        return;
    }

    update_option($option_name, $value, false);
}

/**
 * Determina el estado de conexión.
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
function seo_google_connection_status() {
    $modern_status = apply_filters('taxocommerce_google_bing_gsc_sync_status', null);
    if (null !== $modern_status) {
        return $modern_status;
    }

    $settings = seo_google_get_settings();
    $tokens   = seo_google_get_tokens();

    if (empty($settings['client_id']) || empty($settings['client_secret'])) {
        return 'missing_credentials';
    }

    if (empty($tokens['refresh_token']) && empty($tokens['access_token'])) {
        return 'not_authorized';
    }

    if (empty($settings['property_id'])) {
        return 'missing_property';
    }

    return 'connected';
}

/**
 * Guarda Client ID, secreto, propiedad y frecuencia.
 * Los campos sensibles vacíos conservan el valor ya almacenado.
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
function seo_google_save_settings_handler() {
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('No tienes permisos para configurar Google Intelligence.', 'taxocommerce-google-bing'));
    }

    check_admin_referer('seo_google_save_settings', 'seo_google_settings_nonce');

    $settings = seo_google_get_settings();

    $client_id = isset($_POST['client_id'])
        ? sanitize_text_field(wp_unslash($_POST['client_id']))
        : '';

    $client_secret = isset($_POST['client_secret'])
        ? sanitize_text_field(wp_unslash($_POST['client_secret']))
        : '';

    if ('' !== $client_id) {
        $settings['client_id'] = $client_id;
    }

    if ('' !== $client_secret) {
        $settings['client_secret'] = $client_secret;
    }

    $raw_property = isset($_POST['property_id'])
        ? sanitize_text_field(wp_unslash($_POST['property_id']))
        : '';

    // Las propiedades de dominio usan el prefijo sc-domain: y no son URLs.
    $settings['property_id'] = 0 === strpos($raw_property, 'sc-domain:')
        ? $raw_property
        : esc_url_raw($raw_property);

    $frequency = isset($_POST['sync_frequency'])
        ? sanitize_key(wp_unslash($_POST['sync_frequency']))
        : 'manual';

    $settings['sync_frequency'] = in_array($frequency, array('manual', 'daily', 'weekly'), true)
        ? $frequency
        : 'manual';

    seo_google_update_option(SEO_GOOGLE_OPTION_SETTINGS, $settings);

    wp_safe_redirect(seo_google_admin_url('settings', array('google_notice' => 'settings_saved')));
    exit;
}

/**
 * Inicia OAuth en Google.
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
function seo_google_connect_handler() {
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('No tienes permisos para conectar Google.', 'taxocommerce-google-bing'));
    }

    check_admin_referer('seo_google_connect', 'seo_google_connect_nonce');

    $settings = seo_google_get_settings();

    if (empty($settings['client_id']) || empty($settings['client_secret'])) {
        wp_safe_redirect(seo_google_admin_url('settings', array('google_error' => 'missing_credentials')));
        exit;
    }

    $state = wp_generate_password(48, false, false);
    set_transient(
        'seo_google_oauth_state_' . get_current_user_id(),
        $state,
        15 * MINUTE_IN_SECONDS
    );

    $authorization_url = add_query_arg(
        array(
            'client_id'                => $settings['client_id'],
            'redirect_uri'             => seo_google_redirect_uri(),
            'response_type'            => 'code',
            'scope'                    => SEO_GOOGLE_SCOPE_SEARCH_CONSOLE,
            'access_type'              => 'offline',
            'include_granted_scopes'   => 'true',
            'prompt'                   => 'consent',
            'state'                    => $state,
        ),
        'https://accounts.google.com/o/oauth2/v2/auth'
    );

    // Redirección externa intencionada al endpoint OAuth fijo de Google.
    // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- accounts.google.com is the fixed Google OAuth authorization host assembled above.
    wp_redirect(esc_url_raw($authorization_url));
    exit;
}

/**
 * Lee filtros y avisos GET de solo lectura del módulo Google.
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
function seo_google_query_arg($key, $default = '') {
    $key = sanitize_key((string) $key);
    if ('' === $key) {
        return $default;
    }
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Filtros/avisos GET de solo lectura.
    if (!isset($_GET[$key])) {
        return $default;
    }
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only GET filter; sanitize the value when it enters the application.
    return sanitize_text_field(wp_unslash($_GET[$key]));
}

/**
 * Recibe el callback OAuth y cambia el código por tokens.
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
function seo_google_oauth_callback_handler() {
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('No tienes permisos para completar esta conexión.', 'taxocommerce-google-bing'));
    }

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- OAuth callback uses the one-time state token below.
    if (isset($_GET['error'])) {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- OAuth callback parameter.
        $oauth_error = sanitize_key(wp_unslash($_GET['error']));

        wp_safe_redirect(
            seo_google_admin_url(
                'settings',
                array('google_error' => $oauth_error ?: 'access_denied')
            )
        );
        exit;
    }

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- OAuth state is compared with the one-time transient immediately below.
    $received_state = isset($_GET['state'])
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- OAuth callback state value is validated against the one-time transient before exchanging tokens.
        ? sanitize_text_field(wp_unslash($_GET['state']))
        : '';

    $state_key    = 'seo_google_oauth_state_' . get_current_user_id();
    $stored_state = get_transient($state_key);
    delete_transient($state_key);

    if (
        '' === $received_state
        || empty($stored_state)
        || !hash_equals((string) $stored_state, (string) $received_state)
    ) {
        wp_safe_redirect(seo_google_admin_url('settings', array('google_error' => 'invalid_state')));
        exit;
    }

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- OAuth authorization code is accepted only after state validation.
    $code = isset($_GET['code'])
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- OAuth authorization code is accepted only following the verified one-time state comparison.
        ? sanitize_text_field(wp_unslash($_GET['code']))
        : '';

    if ('' === $code) {
        wp_safe_redirect(seo_google_admin_url('settings', array('google_error' => 'missing_code')));
        exit;
    }

    $settings = seo_google_get_settings();

    if (empty($settings['client_id']) || empty($settings['client_secret'])) {
        wp_safe_redirect(seo_google_admin_url('settings', array('google_error' => 'missing_credentials')));
        exit;
    }

    $response = wp_remote_post(
        'https://oauth2.googleapis.com/token',
        array(
            'timeout' => 30,
            'body'    => array(
                'code'          => $code,
                'client_id'     => $settings['client_id'],
                'client_secret' => $settings['client_secret'],
                'redirect_uri'  => seo_google_redirect_uri(),
                'grant_type'    => 'authorization_code',
            ),
        )
    );

    if (is_wp_error($response)) {
        set_transient(
            'seo_google_oauth_error_' . get_current_user_id(),
            $response->get_error_message(),
            2 * MINUTE_IN_SECONDS
        );
        wp_safe_redirect(seo_google_admin_url('settings', array('google_error' => 'token_request_failed')));
        exit;
    }

    $status_code = (int) wp_remote_retrieve_response_code($response);
    $raw_body    = wp_remote_retrieve_body($response);
    $body        = json_decode($raw_body, true);

    if (200 !== $status_code || !is_array($body) || empty($body['access_token'])) {
        $google_message = '';

        if (is_array($body)) {
            $google_message = sanitize_text_field(
                $body['error_description']
                ?? $body['error']['message']
                ?? $body['error']
                ?? ''
            );
        }

        if ('' === $google_message) {
            $google_message = 'Google devolvió HTTP ' . $status_code . ' al intercambiar el código.';
        }

        set_transient(
            'seo_google_oauth_error_' . get_current_user_id(),
            $google_message,
            2 * MINUTE_IN_SECONDS
        );

        wp_safe_redirect(seo_google_admin_url('settings', array('google_error' => 'token_exchange_failed')));
        exit;
    }

    $old_tokens = seo_google_get_tokens();

    $tokens = array(
        'access_token'  => sanitize_text_field($body['access_token']),
        'refresh_token' => !empty($body['refresh_token'])
            ? sanitize_text_field($body['refresh_token'])
            : $old_tokens['refresh_token'],
        'expires_at'    => time() + max(60, absint($body['expires_in'] ?? 3600) - 60),
        'scope'         => sanitize_text_field($body['scope'] ?? SEO_GOOGLE_SCOPE_SEARCH_CONSOLE),
        'token_type'    => sanitize_text_field($body['token_type'] ?? 'Bearer'),
    );

    seo_google_update_option(SEO_GOOGLE_OPTION_TOKENS, $tokens);

    wp_safe_redirect(seo_google_admin_url('settings', array('google_notice' => 'connected')));
    exit;
}

/**
 * Devuelve un access token válido, renovándolo cuando sea necesario.
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
function seo_google_get_access_token() {
    $modern_token = apply_filters('taxocommerce_google_bing_gsc_sync_access_token', null);
    if (null !== $modern_token) {
        return $modern_token;
    }

    $settings = seo_google_get_settings();
    $tokens   = seo_google_get_tokens();

    if (!empty($tokens['access_token']) && (int) $tokens['expires_at'] > time()) {
        return $tokens['access_token'];
    }

    if (empty($tokens['refresh_token']) || empty($settings['client_id']) || empty($settings['client_secret'])) {
        return new WP_Error('seo_google_not_authorized', 'Google no está autorizado o faltan credenciales.');
    }

    $response = wp_remote_post(
        'https://oauth2.googleapis.com/token',
        array(
            'timeout' => 30,
            'body'    => array(
                'client_id'     => $settings['client_id'],
                'client_secret' => $settings['client_secret'],
                'refresh_token' => $tokens['refresh_token'],
                'grant_type'    => 'refresh_token',
            ),
        )
    );

    if (is_wp_error($response)) {
        return $response;
    }

    $status_code = (int) wp_remote_retrieve_response_code($response);
    $body        = json_decode(wp_remote_retrieve_body($response), true);

    if (200 !== $status_code || !is_array($body) || empty($body['access_token'])) {
        return new WP_Error('seo_google_refresh_failed', 'Google no ha podido renovar el token de acceso.');
    }

    $tokens['access_token'] = sanitize_text_field($body['access_token']);
    $tokens['expires_at']   = time() + max(60, absint($body['expires_in'] ?? 3600) - 60);
    $tokens['scope']        = sanitize_text_field($body['scope'] ?? $tokens['scope']);
    $tokens['token_type']   = sanitize_text_field($body['token_type'] ?? 'Bearer');

    seo_google_update_option(SEO_GOOGLE_OPTION_TOKENS, $tokens);

    return $tokens['access_token'];
}

/**
 * Petición autenticada a una API de Google.
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
function seo_google_api_get($url) {
    $access_token = seo_google_get_access_token();

    if (is_wp_error($access_token)) {
        return $access_token;
    }

    $response = wp_remote_get(
        $url,
        array(
            'timeout' => 30,
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
                'Accept'        => 'application/json',
            ),
        )
    );

    if (is_wp_error($response)) {
        return $response;
    }

    $status_code = (int) wp_remote_retrieve_response_code($response);
    $body        = json_decode(wp_remote_retrieve_body($response), true);

    if ($status_code < 200 || $status_code >= 300) {
        $message = is_array($body) && !empty($body['error']['message'])
            ? sanitize_text_field($body['error']['message'])
            : 'Google ha devuelto un error HTTP ' . $status_code . '.';

        return new WP_Error('seo_google_api_error', $message);
    }

    return is_array($body) ? $body : array();
}

/**
 * Lista propiedades de Search Console disponibles para la cuenta autorizada.
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
function seo_google_get_search_console_properties() {
    $response = seo_google_api_get('https://www.googleapis.com/webmasters/v3/sites');

    if (is_wp_error($response)) {
        return $response;
    }

    $properties = array();

    foreach ((array) ($response['siteEntry'] ?? array()) as $entry) {
        if (empty($entry['siteUrl'])) {
            continue;
        }

        $properties[] = array(
            'site_url'         => sanitize_text_field($entry['siteUrl']),
            'permission_level' => sanitize_key($entry['permissionLevel'] ?? ''),
        );
    }

    usort(
        $properties,
        static function ($a, $b) {
            return strcasecmp($a['site_url'], $b['site_url']);
        }
    );

    return $properties;
}

/**
 * Prueba la autorización consultando las propiedades disponibles.
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
function seo_google_test_connection_handler() {
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('No tienes permisos para probar esta conexión.', 'taxocommerce-google-bing'));
    }

    check_admin_referer('seo_google_test_connection', 'seo_google_test_nonce');

    $properties = seo_google_get_search_console_properties();

    if (is_wp_error($properties)) {
        set_transient(
            'seo_google_test_error_' . get_current_user_id(),
            $properties->get_error_message(),
            2 * MINUTE_IN_SECONDS
        );

        wp_safe_redirect(seo_google_admin_url('settings', array('google_error' => 'test_failed')));
        exit;
    }

    wp_safe_redirect(
        seo_google_admin_url(
            'settings',
            array(
                'google_notice' => 'test_ok',
                'properties'    => count($properties),
            )
        )
    );
    exit;
}

/**
 * Revoca el token cuando sea posible y elimina la conexión local.
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
function seo_google_disconnect_handler() {
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('No tienes permisos para desconectar Google.', 'taxocommerce-google-bing'));
    }

    check_admin_referer('seo_google_disconnect', 'seo_google_disconnect_nonce');

    $tokens = seo_google_get_tokens();
    $token  = !empty($tokens['refresh_token']) ? $tokens['refresh_token'] : $tokens['access_token'];

    if ('' !== $token) {
        wp_remote_post(
            'https://oauth2.googleapis.com/revoke',
            array(
                'timeout' => 15,
                'body'    => array('token' => $token),
            )
        );
    }

    delete_option(SEO_GOOGLE_OPTION_TOKENS);

    $settings                = seo_google_get_settings();
    $settings['property_id'] = '';
    seo_google_update_option(SEO_GOOGLE_OPTION_SETTINGS, $settings);

    wp_safe_redirect(seo_google_admin_url('settings', array('google_notice' => 'disconnected')));
    exit;
}


/**
 * Nombre de una tabla propia de Google Intelligence.
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
function seo_google_table($suffix) {
    global $wpdb;

    return $wpdb->prefix . 'seo_google_' . sanitize_key($suffix);
}

/**
 * Comprueba que una tabla existe físicamente en la base de datos.
 *
 * Se usa information_schema para evitar falsos positivos de SHOW TABLES LIKE
 * cuando el nombre contiene guiones bajos. Si el hosting restringe
 * information_schema, se utiliza SHOW TABLES como alternativa.
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
function seo_google_table_exists($table_name) {
    global $wpdb;

    $table_name = (string) $table_name;
    $previous   = $wpdb->suppress_errors(true);
    $pattern    = $wpdb->esc_like($table_name);
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Native live read/write of plugin-owned tables, retaining immediate reporting consistency; query values are prepared.
    $found      = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $pattern));
    $wpdb->suppress_errors($previous);

    return $table_name === $found;
}

/**
 * Estado físico de las tablas requeridas.
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
function seo_google_tables_status() {
    $runs_table = esc_sql( seo_google_table('sync_runs') );
    $data_table = esc_sql( seo_google_table('search_data') );
    $totals_table = esc_sql( seo_google_table('search_totals') );
    $pages_table = esc_sql( seo_google_table('search_pages') );

    return array(
        'runs_table'  => $runs_table,
        'data_table'  => $data_table,
        'totals_table' => $totals_table,
        'pages_table' => $pages_table,
        'runs_exists' => seo_google_table_exists($runs_table),
        'data_exists' => seo_google_table_exists($data_table),
        'totals_exists' => seo_google_table_exists($totals_table),
        'pages_exists' => seo_google_table_exists($pages_table),
    );
}

/**
 * Instala o actualiza las tablas del módulo.
 *
 * Primero usa dbDelta para mantener compatibilidad con WordPress. Si dbDelta
 * no llega a crear una tabla, ejecuta CREATE TABLE IF NOT EXISTS directamente
 * y conserva el error exacto de MySQL para el diagnóstico.
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
function seo_google_install_tables($force = false) {
    global $wpdb;

    $installed_version = (string) get_option(SEO_GOOGLE_OPTION_DB_VERSION, '');
    $status            = seo_google_tables_status();

    if (
        !$force
        && SEO_GOOGLE_DB_VERSION === $installed_version
        && $status['runs_exists']
        && $status['data_exists']
        && $status['totals_exists']
        && $status['pages_exists']
    ) {
        return true;
    }

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $runs_table     = $status['runs_table'];
    $data_table     = $status['data_table'];
    $totals_table   = $status['totals_table'];
    $pages_table    = $status['pages_table'];
    $charset_collate = $wpdb->get_charset_collate();

    $runs_sql = "CREATE TABLE {$runs_table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        property_id varchar(255) NOT NULL,
        property_hash char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        date_from date NOT NULL,
        date_to date NOT NULL,
        cursor_date date NULL,
        status varchar(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'pending',
        rows_received bigint(20) unsigned NOT NULL DEFAULT 0,
        rows_upserted bigint(20) unsigned NOT NULL DEFAULT 0,
        days_completed int(10) unsigned NOT NULL DEFAULT 0,
        days_total int(10) unsigned NOT NULL DEFAULT 0,
        error_message text NULL,
        created_by bigint(20) unsigned NOT NULL DEFAULT 0,
        started_at datetime NOT NULL,
        finished_at datetime NULL,
        updated_at datetime NOT NULL,
        PRIMARY KEY  (id),
        KEY property_hash (property_hash),
        KEY status (status),
        KEY started_at (started_at)
    ) {$charset_collate};";

    $data_sql = "CREATE TABLE {$data_table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        property_id varchar(255) NOT NULL,
        property_hash char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        data_date date NOT NULL,
        search_type varchar(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'web',
        query_text text NOT NULL,
        query_hash char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        page_url text NOT NULL,
        page_hash char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        clicks double NOT NULL DEFAULT 0,
        impressions double NOT NULL DEFAULT 0,
        ctr double NOT NULL DEFAULT 0,
        position double NOT NULL DEFAULT 0,
        sync_run_id bigint(20) unsigned NOT NULL DEFAULT 0,
        imported_at datetime NOT NULL,
        updated_at datetime NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY google_row (property_hash,data_date,search_type,query_hash,page_hash),
        KEY property_date (property_hash,data_date),
        KEY query_hash (query_hash),
        KEY page_hash (page_hash),
        KEY sync_run_id (sync_run_id)
    ) {$charset_collate};";

    // Los totales sin dimensión son comparables con el gráfico de rendimiento
    // de Search Console. La tabla consulta+página es una muestra diagnóstica.
    $totals_sql = "CREATE TABLE {$totals_table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        property_id varchar(255) NOT NULL,
        property_hash char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        data_date date NOT NULL,
        search_type varchar(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'web',
        clicks double NOT NULL DEFAULT 0,
        impressions double NOT NULL DEFAULT 0,
        ctr double NOT NULL DEFAULT 0,
        position double NOT NULL DEFAULT 0,
        pages_synced tinyint(1) unsigned NOT NULL DEFAULT 0,
        sync_run_id bigint(20) unsigned NOT NULL DEFAULT 0,
        updated_at datetime NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY property_day (property_hash,data_date,search_type),
        KEY property_date (property_hash,data_date)
    ) {$charset_collate};";

    $pages_sql = "CREATE TABLE {$pages_table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        property_id varchar(255) NOT NULL,
        property_hash char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        data_date date NOT NULL,
        search_type varchar(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'web',
        page_url text NOT NULL,
        page_hash char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        clicks double NOT NULL DEFAULT 0,
        impressions double NOT NULL DEFAULT 0,
        ctr double NOT NULL DEFAULT 0,
        position double NOT NULL DEFAULT 0,
        sync_run_id bigint(20) unsigned NOT NULL DEFAULT 0,
        updated_at datetime NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY property_page_day (property_hash,data_date,search_type,page_hash),
        KEY property_date (property_hash,data_date),
        KEY page_hash (page_hash)
    ) {$charset_collate};";

    $errors          = array();
    $previous_errors = $wpdb->suppress_errors(true);

    // Primera pasada: creación directa. Es la vía más predecible en hostings
    // donde dbDelta no informa con claridad de un índice incompatible.
    if (!seo_google_table_exists($runs_table)) {
        $wpdb->last_error = '';
        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- CREATE TABLE SQL is generated internally from plugin table names and WordPress charset/collation; it contains no external values. Native live read/write of plugin-owned tables, retaining immediate reporting consistency; query values are prepared.
        $result = $wpdb->query(rtrim($runs_sql, ";\r\n\t "));
        if (false === $result) {
            $errors[] = $runs_table . ': ' . ($wpdb->last_error ?: 'CREATE TABLE devolvió false.');
        }
    }

    if (!seo_google_table_exists($data_table)) {
        $wpdb->last_error = '';
        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- CREATE TABLE SQL is generated internally from plugin table names and WordPress charset/collation; it contains no external values. Native live read/write of plugin-owned tables, retaining immediate reporting consistency; query values are prepared.
        $result = $wpdb->query(rtrim($data_sql, ";\r\n\t "));
        if (false === $result) {
            $errors[] = $data_table . ': ' . ($wpdb->last_error ?: 'CREATE TABLE devolvió false.');
        }
    }

    if (!seo_google_table_exists($totals_table)) {
        $wpdb->last_error = '';
        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- CREATE TABLE SQL is generated internally from plugin table names and WordPress charset/collation; it contains no external values. Native live read/write of plugin-owned tables, retaining immediate reporting consistency; query values are prepared.
        $result = $wpdb->query(rtrim($totals_sql, ";\r\n\t "));
        if (false === $result) {
            $errors[] = $totals_table . ': ' . ($wpdb->last_error ?: 'CREATE TABLE devolvió false.');
        }
    }

    if (!seo_google_table_exists($pages_table)) {
        $wpdb->last_error = '';
        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- CREATE TABLE SQL is generated internally from plugin table names and WordPress charset/collation; it contains no external values. Native live read/write of plugin-owned tables, retaining immediate reporting consistency; query values are prepared.
        $result = $wpdb->query(rtrim($pages_sql, ";\r\n\t "));
        if (false === $result) $errors[] = $pages_table . ': ' . ($wpdb->last_error ?: 'CREATE TABLE devolvió false.');
    }

    // Segunda pasada: mecanismo oficial de WordPress para futuras mejoras
    // del esquema. Las columnas hash usan ASCII para que el índice compuesto
    // sea compatible también con límites InnoDB antiguos.
    dbDelta($runs_sql);
    dbDelta($data_sql);
    dbDelta($totals_sql);
    dbDelta($pages_sql);
    $wpdb->suppress_errors($previous_errors);

    $status = seo_google_tables_status();
    $missing = array();

    if (!$status['runs_exists']) {
        $missing[] = $runs_table;
    }

    if (!$status['data_exists']) {
        $missing[] = $data_table;
    }
    if (!$status['totals_exists']) {
        $missing[] = $totals_table;
    }
    if (!$status['pages_exists']) {
        $missing[] = $pages_table;
    }

    if ($missing) {
        delete_option(SEO_GOOGLE_OPTION_DB_VERSION);

        $message = 'No se pudieron crear las tablas de Google Intelligence: ' . implode(', ', $missing) . '.';
        if ($errors) {
            $message .= ' MySQL: ' . implode(' | ', $errors);
        } elseif ($wpdb->last_error) {
            $message .= ' MySQL: ' . $wpdb->last_error;
        }

        return new WP_Error('seo_google_tables_missing', $message, array('tables' => $status));
    }

    seo_google_update_option(SEO_GOOGLE_OPTION_DB_VERSION, SEO_GOOGLE_DB_VERSION);

    return true;
}

/**
 * Reparación manual de tablas desde la pantalla de sincronización.
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
function seo_google_repair_tables_handler() {
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('No tienes permisos para reparar las tablas.', 'taxocommerce-google-bing'));
    }

    check_admin_referer('seo_google_repair_tables', 'seo_google_repair_nonce');

    delete_option(SEO_GOOGLE_OPTION_DB_VERSION);
    $result = seo_google_install_tables(true);

    if (is_wp_error($result)) {
        set_transient(
            'seo_google_table_repair_error_' . get_current_user_id(),
            $result->get_error_message(),
            5 * MINUTE_IN_SECONDS
        );
        wp_safe_redirect(seo_google_admin_url('sync', array('google_error' => 'table_repair_failed')));
        exit;
    }

    wp_safe_redirect(seo_google_admin_url('sync', array('google_notice' => 'tables_repaired')));
    exit;
}

/**
 * Fecha final segura para datos finalizados de Search Console.
 * Search Console interpreta sus fechas en la zona del Pacífico.
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
function seo_google_finalized_end_date() {
    try {
        $date = new DateTimeImmutable('now', new DateTimeZone('America/Los_Angeles'));
        return $date->modify('-' . SEO_GOOGLE_FINAL_DATA_DELAY_DAYS . ' days')->format('Y-m-d');
    } catch (Exception $exception) {
        return gmdate('Y-m-d', time() - (SEO_GOOGLE_FINAL_DATA_DELAY_DAYS * DAY_IN_SECONDS));
    }
}

/**
 * Número de días incluidos entre dos fechas.
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
function seo_google_days_inclusive($date_from, $date_to) {
    try {
        $from = new DateTimeImmutable($date_from);
        $to   = new DateTimeImmutable($date_to);
        return max(1, (int) $from->diff($to)->days + 1);
    } catch (Exception $exception) {
        return 1;
    }
}

/**
 * Ejecuta una petición POST JSON autenticada a Google.
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
function seo_google_api_post_json($url, array $payload) {
    $access_token = seo_google_get_access_token();

    if (is_wp_error($access_token)) {
        return $access_token;
    }

    $response = wp_remote_post(
        $url,
        array(
            'timeout' => 90,
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
                'Accept'        => 'application/json',
                'Content-Type'  => 'application/json; charset=utf-8',
            ),
            'body'    => wp_json_encode($payload),
        )
    );

    if (is_wp_error($response)) {
        return $response;
    }

    $status_code = (int) wp_remote_retrieve_response_code($response);
    $body        = json_decode(wp_remote_retrieve_body($response), true);

    if ($status_code < 200 || $status_code >= 300) {
        $message = is_array($body) && !empty($body['error']['message'])
            ? sanitize_text_field($body['error']['message'])
            : 'Google ha devuelto un error HTTP ' . $status_code . '.';

        return new WP_Error('seo_google_api_error', $message);
    }

    return is_array($body) ? $body : array();
}

/**
 * Descarga un día de Search Console conservando consulta y página juntas.
 * Google expone hasta 50.000 filas diarias por tipo de búsqueda.
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
function seo_google_fetch_search_day($property_id, $date, $dimensions = array('query', 'page')) {
    $endpoint = 'https://www.googleapis.com/webmasters/v3/sites/'
        . rawurlencode($property_id)
        . '/searchAnalytics/query';

    $all_rows = array();

    for ($start_row = 0; $start_row < SEO_GOOGLE_MAX_ROWS_PER_DAY; $start_row += SEO_GOOGLE_ROW_LIMIT) {
        $response = seo_google_api_post_json(
            $endpoint,
            array(
                'startDate'       => $date,
                'endDate'         => $date,
                'dimensions'      => $dimensions,
                'type'            => 'web',
                'aggregationType' => $dimensions === array('page') ? 'byPage' : 'auto',
                'dataState'       => 'final',
                'rowLimit'        => SEO_GOOGLE_ROW_LIMIT,
                'startRow'        => $start_row,
            )
        );

        if (is_wp_error($response)) {
            return $response;
        }

        $rows      = isset($response['rows']) && is_array($response['rows']) ? $response['rows'] : array();
        $row_count = count($rows);

        if ($row_count > 0) {
            $all_rows = array_merge($all_rows, $rows);
        }

        if ($row_count < SEO_GOOGLE_ROW_LIMIT) {
            break;
        }
    }

    if ($dimensions === array('page') && count($all_rows) >= SEO_GOOGLE_MAX_ROWS_PER_DAY) {
        return new WP_Error('seo_google_pages_truncated', 'Google ha alcanzado el límite diario de 50.000 páginas; no se marcará este día como completo.');
    }

    return $all_rows;
}

/** Filas por URL sin desglosar consultas anónimas. */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
function seo_google_upsert_page_rows($run_id, $property_id, $date, array $rows) {
    global $wpdb;
    $table = esc_sql( seo_google_table('search_pages') );
    $now = current_time('mysql', true);
    $property_hash = hash('sha256', $property_id);
    $stored = 0;
    foreach (array_chunk($rows, 200) as $chunk) {
        $placeholders = array();
        $args = array();
        foreach ($chunk as $row) {
            $url = esc_url_raw((string) ($row['keys'][0] ?? ''));
            if (!$url) continue;
            $placeholders[] = '(%s,%s,%s,%s,%s,%s,%f,%f,%f,%f,%d,%s)';
            array_push($args, $property_id, $property_hash, $date, 'web', $url, hash('sha256', $url),
                (float) ($row['clicks'] ?? 0), (float) ($row['impressions'] ?? 0),
                (float) ($row['ctr'] ?? 0), (float) ($row['position'] ?? 0), absint($run_id), $now);
        }
        if (!$placeholders) continue;
        $sql = "INSERT INTO {$table} (property_id,property_hash,data_date,search_type,page_url,page_hash,clicks,impressions,ctr,position,sync_run_id,updated_at)
            VALUES " . implode(',', $placeholders) . " ON DUPLICATE KEY UPDATE
            property_id=VALUES(property_id),page_url=VALUES(page_url),clicks=VALUES(clicks),impressions=VALUES(impressions),ctr=VALUES(ctr),position=VALUES(position),sync_run_id=VALUES(sync_run_id),updated_at=VALUES(updated_at)";
        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Internal Search Console table plus generated placeholders; every row value is bound through prepare(). Native live read/write of plugin-owned tables, retaining immediate reporting consistency; query values are prepared.
        if (false === $wpdb->query($wpdb->prepare($sql, $args))) {
            return new WP_Error('seo_google_pages_database_error', $wpdb->last_error);
        }
        $stored += count($placeholders);
    }
    return $stored;
}

/** Total diario de propiedad, sin dimensiones ni filtros, búsqueda web final. */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
function seo_google_fetch_property_day($property_id, $date) {
    $endpoint = 'https://www.googleapis.com/webmasters/v3/sites/'
        . rawurlencode($property_id) . '/searchAnalytics/query';
    $response = seo_google_api_post_json($endpoint, array(
        'startDate' => $date,
        'endDate' => $date,
        'dimensions' => array('date'),
        'type' => 'web',
        'aggregationType' => 'byProperty',
        'dataState' => 'final',
    ));
    if (is_wp_error($response)) return $response;
    $rows = isset($response['rows']) && is_array($response['rows']) ? $response['rows'] : array();
    if (!$rows) return array('clicks'=>0, 'impressions'=>0, 'ctr'=>0, 'position'=>0);
    $row = $rows[0];
    if (!isset($row['keys'][0]) || $row['keys'][0] !== $date) {
        return new WP_Error('seo_google_property_day_mismatch', 'Google devolvió un día distinto para el total de la propiedad.');
    }
    return array(
        'clicks' => (float) ($row['clicks'] ?? 0),
        'impressions' => (float) ($row['impressions'] ?? 0),
        'ctr' => (float) ($row['ctr'] ?? 0),
        'position' => (float) ($row['position'] ?? 0),
    );
}

/** Persiste también días de cero impresiones para poder comprobar cobertura. */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
function seo_google_upsert_property_day($run_id, $property_id, $date, array $totals) {
    global $wpdb;
    $table = esc_sql( seo_google_table('search_totals') );
    $sql = "INSERT INTO {$table} (property_id,property_hash,data_date,search_type,clicks,impressions,ctr,position,pages_synced,sync_run_id,updated_at)
        VALUES (%s,%s,%s,%s,%f,%f,%f,%f,1,%d,%s)
        ON DUPLICATE KEY UPDATE property_id=VALUES(property_id),clicks=VALUES(clicks),impressions=VALUES(impressions),ctr=VALUES(ctr),position=VALUES(position),pages_synced=1,sync_run_id=VALUES(sync_run_id),updated_at=VALUES(updated_at)";
    // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Internal totals table; all property/date/metric values are bound through prepare(). Native live read/write of plugin-owned tables, retaining immediate reporting consistency; query values are prepared.
    $result = $wpdb->query($wpdb->prepare($sql, $property_id, hash('sha256', $property_id), $date, 'web',
        $totals['clicks'], $totals['impressions'], $totals['ctr'], $totals['position'], absint($run_id), current_time('mysql', true)));
    return false === $result ? new WP_Error('seo_google_totals_database_error', $wpdb->last_error) : true;
}

/**
 * Guarda filas en bloques y actualiza las ya existentes.
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
function seo_google_upsert_search_rows($run_id, $property_id, $date, array $rows) {
    global $wpdb;

    $table         = esc_sql( seo_google_table('search_data') );
    $property_hash = hash('sha256', $property_id);
    $now           = current_time('mysql', true);
    $valid_rows    = array();

    foreach ($rows as $row) {
        $keys = isset($row['keys']) && is_array($row['keys']) ? $row['keys'] : array();

        if (!isset($keys[0], $keys[1])) {
            continue;
        }

        $query = sanitize_text_field((string) $keys[0]);
        $page  = esc_url_raw((string) $keys[1]);

        if ('' === $query || '' === $page) {
            continue;
        }

        $valid_rows[] = array(
            'query'       => $query,
            'page'        => $page,
            'clicks'      => (float) ($row['clicks'] ?? 0),
            'impressions' => (float) ($row['impressions'] ?? 0),
            'ctr'         => (float) ($row['ctr'] ?? 0),
            'position'    => (float) ($row['position'] ?? 0),
        );
    }

    $stored = 0;

    foreach (array_chunk($valid_rows, 200) as $chunk) {
        $placeholders = array();
        $arguments    = array();

        foreach ($chunk as $row) {
            $placeholders[] = '(%s,%s,%s,%s,%s,%s,%s,%s,%f,%f,%f,%f,%d,%s,%s)';

            array_push(
                $arguments,
                $property_id,
                $property_hash,
                $date,
                'web',
                $row['query'],
                hash('sha256', $row['query']),
                $row['page'],
                hash('sha256', $row['page']),
                $row['clicks'],
                $row['impressions'],
                $row['ctr'],
                $row['position'],
                absint($run_id),
                $now,
                $now
            );
        }

        $sql = "INSERT INTO {$table}
            (property_id,property_hash,data_date,search_type,query_text,query_hash,page_url,page_hash,clicks,impressions,ctr,position,sync_run_id,imported_at,updated_at)
            VALUES " . implode(',', $placeholders) . "
            ON DUPLICATE KEY UPDATE
                property_id = VALUES(property_id),
                query_text = VALUES(query_text),
                page_url = VALUES(page_url),
                clicks = VALUES(clicks),
                impressions = VALUES(impressions),
                ctr = VALUES(ctr),
                position = VALUES(position),
                sync_run_id = VALUES(sync_run_id),
                updated_at = VALUES(updated_at)";

        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Internal Search Console table plus generated row placeholders; every value is bound through prepare(). Native live read/write of plugin-owned tables, retaining immediate reporting consistency; query values are prepared.
        $result = $wpdb->query($wpdb->prepare($sql, $arguments));

        if (false === $result) {
            return new WP_Error(
                'seo_google_database_error',
                $wpdb->last_error ? $wpdb->last_error : 'No se pudieron guardar los datos de Search Console.'
            );
        }

        $stored += count($chunk);
    }

    return $stored;
}

/**
 * Inicia una respuesta AJAX limpia. Cualquier aviso o HTML generado por PHP,
 * WordPress o MySQL queda dentro de un búfer y no contamina el JSON.
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
function seo_google_ajax_begin() {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Existing global name is retained for module compatibility.
    $GLOBALS['seo_google_ajax_active']   = true;
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Existing global name is retained for module compatibility.
    $GLOBALS['seo_google_ajax_ob_level'] = ob_get_level();

    if (function_exists('nocache_headers')) {
        nocache_headers();
    }

    ob_start();
    register_shutdown_function('seo_google_ajax_shutdown');
}

/**
 * Limpia únicamente los búferes abiertos por el módulo.
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
function seo_google_ajax_clean_buffers() {
    $base_level = isset($GLOBALS['seo_google_ajax_ob_level'])
        ? (int) $GLOBALS['seo_google_ajax_ob_level']
        : 0;

    while (ob_get_level() > $base_level) {
        @ob_end_clean();
    }
}

/**
 * Convierte un error fatal en JSON para evitar "Unexpected token <".
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
function seo_google_ajax_shutdown() {
    if (empty($GLOBALS['seo_google_ajax_active'])) {
        return;
    }

    $error = error_get_last();
    $fatal_types = array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR);

    if (!$error || !in_array((int) $error['type'], $fatal_types, true)) {
        return;
    }

    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Existing global name is retained for module compatibility.
    $GLOBALS['seo_google_ajax_active'] = false;
    seo_google_ajax_clean_buffers();

    if (function_exists('seo_system_private_log')) {
        seo_system_private_log(
            'critical',
            'Google Intelligence: error fatal AJAX',
            array(
                'message' => (string) $error['message'],
                'file'    => (string) $error['file'],
                'line'    => absint($error['line']),
            )
        );
    }

    if (!headers_sent()) {
        status_header(500);
        header('Content-Type: application/json; charset=' . get_option('blog_charset'));
    }

    echo wp_json_encode(
        array(
            'success' => false,
            'data'    => array(
                'message' => 'Error PHP durante la sincronización: '
                    . wp_strip_all_tags((string) $error['message'])
                    . ' (línea ' . absint($error['line']) . ').',
            ),
        )
    );
}

/**
 * Envía JSON correcto descartando cualquier salida previa.
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
function seo_google_ajax_success($data = null, $status_code = null) {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Existing global name is retained for module compatibility.
    $GLOBALS['seo_google_ajax_active'] = false;
    seo_google_ajax_clean_buffers();
    wp_send_json_success($data, $status_code);
}

/**
 * Envía un error JSON correcto descartando cualquier salida previa.
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
function seo_google_ajax_error($data = null, $status_code = null) {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Existing global name is retained for module compatibility.
    $GLOBALS['seo_google_ajax_active'] = false;
    seo_google_ajax_clean_buffers();
    wp_send_json_error($data, $status_code);
}

/**
 * Crea o recupera una ejecución de sincronización manual.
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
function seo_google_sync_start_handler() {
    seo_google_ajax_begin();

    if (false === check_ajax_referer('seo_google_sync', 'nonce', false)) {
        seo_google_ajax_error(array('message' => 'La sesión ha caducado. Recarga la página y vuelve a intentarlo.'), 403);
    }

    if (!current_user_can('manage_options')) {
        seo_google_ajax_error(array('message' => 'No tienes permisos para sincronizar Google Intelligence.'), 403);
    }

    // Verify a real GSC query using the current OAuth/service-account pair before SQL writes.
    $verified = apply_filters('taxocommerce_google_bing_gsc_sync_verify_start', true);
    if (is_wp_error($verified)) {
        seo_google_ajax_error(array('message' => $verified->get_error_message()), 400);
    }

    if ('connected' !== seo_google_connection_status()) {
        seo_google_ajax_error(array('message' => 'Completa la conexión y selecciona una propiedad.'), 400);
    }

    $install_result = seo_google_install_tables(true);

    if (is_wp_error($install_result)) {
        seo_google_ajax_error(array('message' => $install_result->get_error_message()), 500);
    }

    global $wpdb;

    $settings      = seo_google_get_settings();
    $property_id   = $settings['property_id'];
    $property_hash = hash('sha256', $property_id);
    $runs_table    = esc_sql( seo_google_table('sync_runs') );
    $data_table    = esc_sql( seo_google_table('search_data') );
    $now           = current_time('mysql', true);
    $stale_before  = gmdate('Y-m-d H:i:s', time() - (30 * MINUTE_IN_SECONDS));

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Native live read/write of plugin-owned tables, retaining immediate reporting consistency; query values are prepared.
    $active = $wpdb->get_row(
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Trusted internal table identifiers; generated value placeholders are bound using wpdb::prepare (WordPress 5.8 compatibility).
        $wpdb->prepare(
            "SELECT * FROM {$runs_table}
             WHERE property_hash = %s AND status = 'running'
             ORDER BY id DESC LIMIT 1",
            $property_hash
        ),
        ARRAY_A
    );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber

    if ($active && !empty($active['updated_at']) && $active['updated_at'] >= $stale_before) {
        seo_google_ajax_success(
            array(
                'run_id'         => absint($active['id']),
                'current_date'   => $active['cursor_date'],
                'date_from'      => $active['date_from'],
                'date_to'        => $active['date_to'],
                'days_completed' => absint($active['days_completed']),
                'days_total'     => absint($active['days_total']),
                'mode'           => 'resume',
            )
        );
    }

    if ($active) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Native live read/write of plugin-owned tables, retaining immediate reporting consistency; query values are prepared.
        $wpdb->update(
            $runs_table,
            array(
                'status'        => 'failed',
                'error_message' => 'La ejecución anterior quedó interrumpida y fue sustituida.',
                'finished_at'   => $now,
                'updated_at'    => $now,
            ),
            array('id' => absint($active['id'])),
            array('%s', '%s', '%s', '%s'),
            array('%d')
        );
    }

    $date_to = seo_google_finalized_end_date();

    try {
        $initial_from = (new DateTimeImmutable($date_to))
            ->modify('-' . (SEO_GOOGLE_INITIAL_SYNC_DAYS - 1) . ' days')
            ->format('Y-m-d');
    } catch (Exception $exception) {
        $initial_from = gmdate('Y-m-d', strtotime($date_to . ' -89 days'));
    }

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Native live read/write of plugin-owned tables, retaining immediate reporting consistency; query values are prepared.
    $last_date = $wpdb->get_var(
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Trusted internal table identifiers; generated value placeholders are bound using wpdb::prepare (WordPress 5.8 compatibility).
        $wpdb->prepare(
            "SELECT MAX(data_date) FROM {$data_table} WHERE property_hash = %s",
            $property_hash
        )
    );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber

    // Tras actualizar el esquema, las filas históricas no contienen totales
    // de propiedad. Recuperar la ventana entera una sola vez.
    $totals_table = esc_sql( seo_google_table('search_totals') );
    // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Trusted internal table identifiers; generated value placeholders are bound using wpdb::prepare (WordPress 5.8 compatibility).
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Native live read/write of plugin-owned tables, retaining immediate reporting consistency; query values are prepared.
    $totals_coverage = $wpdb->get_row($wpdb->prepare(
        "SELECT MIN(data_date) AS first_date, MAX(data_date) AS last_date, COUNT(*) AS days
         FROM {$totals_table} WHERE property_hash = %s AND data_date BETWEEN %s AND %s AND search_type = 'web' AND pages_synced = 1",
        $property_hash, $initial_from, $date_to
    ), ARRAY_A);
    // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
    $totals_complete_through_last = !empty($totals_coverage['last_date'])
        && $totals_coverage['first_date'] === $initial_from
        && (int) $totals_coverage['days'] === seo_google_days_inclusive($initial_from, $totals_coverage['last_date']);

    $mode      = 'initial';
    $date_from = $initial_from;

    if ($last_date && $totals_complete_through_last) {
        $mode = 'incremental';
        try {
            $overlap_from = (new DateTimeImmutable($totals_coverage['last_date']))->modify('-6 days')->format('Y-m-d');
            $date_from    = max($initial_from, $overlap_from);
        } catch (Exception $exception) {
            $date_from = $initial_from;
        }
    }

    if ($date_from > $date_to) {
        $date_from = $date_to;
    }

    $days_total = seo_google_days_inclusive($date_from, $date_to);

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Native live read/write of plugin-owned tables, retaining immediate reporting consistency; query values are prepared.
    $inserted = $wpdb->insert(
        $runs_table,
        array(
            'property_id'    => $property_id,
            'property_hash'  => $property_hash,
            'date_from'      => $date_from,
            'date_to'        => $date_to,
            'cursor_date'    => $date_from,
            'status'         => 'running',
            'rows_received'  => 0,
            'rows_upserted'  => 0,
            'days_completed' => 0,
            'days_total'     => $days_total,
            'error_message'  => null,
            'created_by'     => get_current_user_id(),
            'started_at'     => $now,
            'updated_at'     => $now,
        ),
        array('%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%s', '%d', '%s', '%s')
    );

    if (false === $inserted) {
        seo_google_ajax_error(
            array('message' => $wpdb->last_error ?: 'No se pudo crear la ejecución de sincronización.'),
            500
        );
    }

    seo_google_ajax_success(
        array(
            'run_id'         => absint($wpdb->insert_id),
            'current_date'   => $date_from,
            'date_from'      => $date_from,
            'date_to'        => $date_to,
            'days_completed' => 0,
            'days_total'     => $days_total,
            'mode'           => $mode,
        )
    );
}

/**
 * Procesa un único día de una ejecución para evitar tiempos de espera largos.
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
function seo_google_sync_day_handler() {
    seo_google_ajax_begin();

    if (false === check_ajax_referer('seo_google_sync', 'nonce', false)) {
        seo_google_ajax_error(array('message' => 'La sesión ha caducado. Recarga la página y vuelve a intentarlo.'), 403);
    }

    if (!current_user_can('manage_options')) {
        seo_google_ajax_error(array('message' => 'No tienes permisos para sincronizar Google Intelligence.'), 403);
    }

    if (function_exists('set_time_limit')) {
        // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- Long-running administrator/worker operation with explicit bounds or recovery controls.
        @set_time_limit(120);}

    $run_id = isset($_POST['run_id']) ? absint(wp_unslash($_POST['run_id'])) : 0;

    if (!$run_id) {
        seo_google_ajax_error(array('message' => 'Falta el identificador de la sincronización.'), 400);
    }

    $install_result = seo_google_install_tables(true);

    if (is_wp_error($install_result)) {
        seo_google_ajax_error(array('message' => $install_result->get_error_message()), 500);
    }

    global $wpdb;

    $runs_table = esc_sql( seo_google_table('sync_runs') );
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Native live read/write of plugin-owned tables, retaining immediate reporting consistency; query values are prepared.
    $run        = $wpdb->get_row(
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Trusted internal table identifiers; generated value placeholders are bound using wpdb::prepare (WordPress 5.8 compatibility).
        $wpdb->prepare("SELECT * FROM {$runs_table} WHERE id = %d", $run_id),
        ARRAY_A
    );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber

    if (!$run) {
        seo_google_ajax_error(array('message' => 'La sincronización no existe.'), 404);
    }

    if ('success' === $run['status']) {
        seo_google_ajax_success(
            array(
                'done'           => true,
                'days_completed' => absint($run['days_completed']),
                'days_total'     => absint($run['days_total']),
                'rows_received'  => absint($run['rows_received']),
                'rows_upserted'  => absint($run['rows_upserted']),
            )
        );
    }

    if ('running' !== $run['status']) {
        seo_google_ajax_error(array('message' => $run['error_message'] ?: 'La sincronización no está activa.'), 409);
    }

    $date = $run['cursor_date'];

    if (!$date) {
        seo_google_ajax_error(array('message' => 'La sincronización no tiene una fecha pendiente.'), 409);
    }

    $rows = seo_google_fetch_search_day($run['property_id'], $date);
    $pages = is_wp_error($rows) ? $rows : seo_google_fetch_search_day($run['property_id'], $date, array('page'));
    $totals = is_wp_error($pages) ? $pages : seo_google_fetch_property_day($run['property_id'], $date);

    if (is_wp_error($totals)) {
        $message = $totals->get_error_message();
        $now     = current_time('mysql', true);

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Native live read/write of plugin-owned tables, retaining immediate reporting consistency; query values are prepared.
        $wpdb->update(
            $runs_table,
            array(
                'status'        => 'failed',
                'error_message' => $message,
                'finished_at'   => $now,
                'updated_at'    => $now,
            ),
            array('id' => $run_id),
            array('%s', '%s', '%s', '%s'),
            array('%d')
        );

        seo_google_ajax_error(array('message' => $message), 500);
    }

    // Un día puede cambiar entre sincronizaciones. Sustituimos sus filas de
    // detalle y página en una transacción para no conservar URLs ya retiradas.
    $property_hash = hash('sha256', $run['property_id']);
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Native live read/write of plugin-owned tables, retaining immediate reporting consistency; query values are prepared.
    $transaction_started = false !== $wpdb->query('START TRANSACTION');
    if ($transaction_started) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Native live read/write of plugin-owned tables, retaining immediate reporting consistency; query values are prepared.
        $deleted_detail = $wpdb->delete(seo_google_table('search_data'),
            array('property_hash'=>$property_hash,'data_date'=>$date,'search_type'=>'web'), array('%s','%s','%s'));
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Native live read/write of plugin-owned tables, retaining immediate reporting consistency; query values are prepared.
        $deleted_pages = $wpdb->delete(seo_google_table('search_pages'),
            array('property_hash'=>$property_hash,'data_date'=>$date,'search_type'=>'web'), array('%s','%s','%s'));
        $stored = (false === $deleted_detail || false === $deleted_pages)
            ? new WP_Error('seo_google_day_delete_error', $wpdb->last_error)
            : seo_google_upsert_search_rows($run_id, $run['property_id'], $date, $rows);
    } else {
        $stored = new WP_Error('seo_google_transaction_error', $wpdb->last_error);
    }

    if (!is_wp_error($stored)) {
        $page_stored = seo_google_upsert_page_rows($run_id, $run['property_id'], $date, $pages);
        $stored = is_wp_error($page_stored) ? $page_stored : seo_google_upsert_property_day($run_id, $run['property_id'], $date, $totals);
        if (!is_wp_error($stored)) $stored = count($rows);
    }

    if ($transaction_started) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Native live read/write of plugin-owned tables, retaining immediate reporting consistency; query values are prepared.
        if (is_wp_error($stored)) $wpdb->query('ROLLBACK');
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Native live read/write of plugin-owned tables, retaining immediate reporting consistency; query values are prepared.
        elseif (false === $wpdb->query('COMMIT')) $stored = new WP_Error('seo_google_commit_error', $wpdb->last_error);
    }

    if (is_wp_error($stored)) {
        $message = $stored->get_error_message();
        $now     = current_time('mysql', true);

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Native live read/write of plugin-owned tables, retaining immediate reporting consistency; query values are prepared.
        $wpdb->update(
            $runs_table,
            array(
                'status'        => 'failed',
                'error_message' => $message,
                'finished_at'   => $now,
                'updated_at'    => $now,
            ),
            array('id' => $run_id),
            array('%s', '%s', '%s', '%s'),
            array('%d')
        );

        seo_google_ajax_error(array('message' => $message), 500);
    }

    try {
        $next_date = (new DateTimeImmutable($date))->modify('+1 day')->format('Y-m-d');
    } catch (Exception $exception) {
        $next_date = gmdate('Y-m-d', strtotime($date . ' +1 day'));
    }

    $done           = $next_date > $run['date_to'];
    $days_completed = min(absint($run['days_total']), absint($run['days_completed']) + 1);
    $rows_received  = absint($run['rows_received']) + count($rows);
    $rows_upserted  = absint($run['rows_upserted']) + absint($stored);
    $now            = current_time('mysql', true);

    $update = array(
        'cursor_date'    => $done ? null : $next_date,
        'status'         => $done ? 'success' : 'running',
        'rows_received'  => $rows_received,
        'rows_upserted'  => $rows_upserted,
        'days_completed' => $days_completed,
        'updated_at'     => $now,
    );
    $formats = array('%s', '%s', '%d', '%d', '%d', '%s');

    if ($done) {
        $update['finished_at'] = $now;
        $formats[]             = '%s';
    }

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Native live read/write of plugin-owned tables, retaining immediate reporting consistency; query values are prepared.
    $wpdb->update($runs_table, $update, array('id' => $run_id), $formats, array('%d'));

    if ($done) {
        if (has_action('taxocommerce_google_bing_gsc_sync_completed')) {
            // Insights owns last-sync metadata; never write modern or legacy OAuth secrets.
            do_action('taxocommerce_google_bing_gsc_sync_completed');
        } else {
            $settings              = seo_google_get_settings();
            $settings['last_sync'] = current_time('mysql');
            seo_google_update_option(SEO_GOOGLE_OPTION_SETTINGS, $settings);
        }
    }

    seo_google_ajax_success(
        array(
            'done'           => $done,
            'processed_date' => $date,
            'next_date'      => $done ? '' : $next_date,
            'day_rows'       => count($rows),
            'days_completed' => $days_completed,
            'days_total'     => absint($run['days_total']),
            'rows_received'  => $rows_received,
            'rows_upserted'  => $rows_upserted,
        )
    );
}

/**
 * Último día almacenado para una propiedad.
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
function seo_google_latest_data_date($property_id) {
    global $wpdb;

    $totals_table = esc_sql( seo_google_table('search_totals') );
    if (seo_google_table_exists($totals_table)) {
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Trusted internal table identifiers; generated value placeholders are bound using wpdb::prepare (WordPress 5.8 compatibility).
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Native live read/write of plugin-owned tables, retaining immediate reporting consistency; query values are prepared.
        $latest_total = $wpdb->get_var($wpdb->prepare(
            "SELECT MAX(data_date) FROM {$totals_table} WHERE property_hash = %s AND search_type = 'web'",
            hash('sha256', $property_id)
        ));
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
        if ($latest_total) return $latest_total;
    }

    $table = esc_sql( seo_google_table('search_data') );

    if (!seo_google_table_exists($table)) {
        return null;
    }

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Native live read/write of plugin-owned tables, retaining immediate reporting consistency; query values are prepared.
    return $wpdb->get_var(
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Trusted internal table identifiers; generated value placeholders are bound using wpdb::prepare (WordPress 5.8 compatibility).
        $wpdb->prepare(
            "SELECT MAX(data_date) FROM {$table} WHERE property_hash = %s",
            hash('sha256', $property_id)
        )
    );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
}

/**
 * Totales comparables con Search Console solo cuando están todos los días.
 * Consultas y páginas siguen siendo un diagnóstico parcial y no se suman
 * para presentar clics/impresiones de la propiedad.
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
function seo_google_period_metrics($property_id, $date_from, $date_to) {
    global $wpdb;
    $table = esc_sql( seo_google_table('search_data') );
    // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Trusted internal table identifiers; generated value placeholders are bound using wpdb::prepare (WordPress 5.8 compatibility).
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Native live read/write of plugin-owned tables, retaining immediate reporting consistency; query values are prepared.
    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT COALESCE(SUM(clicks),0) AS clicks, COALESCE(SUM(impressions),0) AS impressions,
            COUNT(DISTINCT query_hash) AS queries, COUNT(DISTINCT page_hash) AS pages,
            CASE WHEN SUM(impressions)>0 THEN SUM(clicks)/SUM(impressions) ELSE 0 END AS ctr,
            CASE WHEN SUM(impressions)>0 THEN SUM(position*impressions)/SUM(impressions) ELSE 0 END AS position
         FROM {$table} WHERE property_hash=%s AND data_date BETWEEN %s AND %s AND search_type='web'",
        hash('sha256', $property_id), $date_from, $date_to
    ), ARRAY_A);
    // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
    $row = is_array($row) ? $row : array();
    $row['source'] = 'query_page_partial';
    $row['days_available'] = 0;
    $row['days_expected'] = seo_google_days_inclusive($date_from, $date_to);

    $totals_table = esc_sql( seo_google_table('search_totals') );
    if (!seo_google_table_exists($totals_table)) return $row;
    // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Trusted internal table identifiers; generated value placeholders are bound using wpdb::prepare (WordPress 5.8 compatibility).
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Native live read/write of plugin-owned tables, retaining immediate reporting consistency; query values are prepared.
    $totals = $wpdb->get_row($wpdb->prepare(
        "SELECT COUNT(*) AS days_available, COALESCE(SUM(clicks),0) AS clicks,
            COALESCE(SUM(impressions),0) AS impressions,
            CASE WHEN SUM(impressions)>0 THEN SUM(clicks)/SUM(impressions) ELSE 0 END AS ctr,
            CASE WHEN SUM(impressions)>0 THEN SUM(position*impressions)/SUM(impressions) ELSE 0 END AS position
         FROM {$totals_table} WHERE property_hash=%s AND data_date BETWEEN %s AND %s AND search_type='web'",
        hash('sha256', $property_id), $date_from, $date_to
    ), ARRAY_A);
    // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
    $row['days_available'] = (int) ($totals['days_available'] ?? 0);
    if ($row['days_available'] !== $row['days_expected']) return $row;
    foreach (array('clicks','impressions','ctr','position') as $key) $row[$key] = $totals[$key];
    if (seo_google_pages_period_complete($property_id, $date_from, $date_to)) {
        $pages_table = esc_sql( seo_google_table('search_pages') );
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Trusted internal table identifiers; generated value placeholders are bound using wpdb::prepare (WordPress 5.8 compatibility).
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Native live read/write of plugin-owned tables, retaining immediate reporting consistency; query values are prepared.
        $row['pages'] = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT page_hash) FROM {$pages_table} WHERE property_hash=%s AND search_type='web' AND data_date BETWEEN %s AND %s",
            hash('sha256', $property_id), $date_from, $date_to
        ));
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
        $row['pages_source'] = 'gsc_page_web_final';
    } else {
        $row['pages_source'] = 'query_page_partial';
    }
    $row['source'] = 'gsc_property_web_final';
    return $row;
}

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
function seo_google_metrics_coverage_notice($metrics) {
    if (($metrics['source'] ?? '') === 'gsc_property_web_final') return '';
    return 'Datos parciales de consulta y página: ' . (int) ($metrics['days_available'] ?? 0)
        . '/' . (int) ($metrics['days_expected'] ?? 0)
        . ' días con totales de propiedad. Sincroniza Google Intelligence para comparar los totales con Search Console. Las listas de consultas y páginas son diagnósticas.';
}

/** Cobertura real de las filas por página, incluidos los días sin URLs. */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
function seo_google_pages_period_complete($property_id, $date_from, $date_to) {
    global $wpdb;
    $table = esc_sql( seo_google_table('search_totals') );
    if (!seo_google_table_exists($table)) return false;
    // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Trusted internal table identifiers; generated value placeholders are bound using wpdb::prepare (WordPress 5.8 compatibility).
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Native live read/write of plugin-owned tables, retaining immediate reporting consistency; query values are prepared.
    $count = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$table} WHERE property_hash=%s AND search_type='web' AND pages_synced=1 AND data_date BETWEEN %s AND %s",
        hash('sha256', $property_id), $date_from, $date_to
    ));
    // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
    return $count === seo_google_days_inclusive($date_from, $date_to);
}

/**
 * Métricas agregadas del período más reciente disponible.
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
function seo_google_get_summary_metrics($property_id, $days = 28) {
    $latest_date = seo_google_latest_data_date($property_id);

    if (!$latest_date) {
        return null;
    }

    try {
        $date_from = (new DateTimeImmutable($latest_date))
            ->modify('-' . (max(1, absint($days)) - 1) . ' days')
            ->format('Y-m-d');
    } catch (Exception $exception) {
        $date_from = gmdate('Y-m-d', strtotime($latest_date . ' -27 days'));
    }

    $row = seo_google_period_metrics($property_id, $date_from, $latest_date);

    if (!$row) {
        return null;
    }

    $row['date_from'] = $date_from;
    $row['date_to']   = $latest_date;

    return $row;
}

/**
 * Consultas principales de un período.
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
function seo_google_get_top_queries($property_id, $date_from, $date_to, $limit = 15) {
    global $wpdb;

    $table = esc_sql( seo_google_table('search_data') );

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Native live read/write of plugin-owned tables, retaining immediate reporting consistency; query values are prepared.
    return $wpdb->get_results(
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Trusted internal table identifiers; generated value placeholders are bound using wpdb::prepare (WordPress 5.8 compatibility).
        $wpdb->prepare(
            "SELECT
                MAX(query_text) AS label,
                SUM(clicks) AS clicks,
                SUM(impressions) AS impressions,
                CASE WHEN SUM(impressions) > 0 THEN SUM(clicks) / SUM(impressions) ELSE 0 END AS ctr,
                CASE WHEN SUM(impressions) > 0 THEN SUM(position * impressions) / SUM(impressions) ELSE 0 END AS position
             FROM {$table}
             WHERE property_hash = %s AND data_date BETWEEN %s AND %s
             GROUP BY query_hash
             ORDER BY impressions DESC, clicks DESC
             LIMIT %d",
            hash('sha256', $property_id),
            $date_from,
            $date_to,
            max(1, absint($limit))
        ),
        ARRAY_A
    );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
}

/**
 * Páginas principales de un período.
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
function seo_google_get_top_pages($property_id, $date_from, $date_to, $limit = 15) {
    global $wpdb;

    $table = esc_sql( seo_google_table(seo_google_pages_period_complete($property_id, $date_from, $date_to)
        ? 'search_pages' : 'search_data') );

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Native live read/write of plugin-owned tables, retaining immediate reporting consistency; query values are prepared.
    return $wpdb->get_results(
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Trusted internal table identifiers; generated value placeholders are bound using wpdb::prepare (WordPress 5.8 compatibility).
        $wpdb->prepare(
            "SELECT
                MAX(page_url) AS label,
                SUM(clicks) AS clicks,
                SUM(impressions) AS impressions,
                CASE WHEN SUM(impressions) > 0 THEN SUM(clicks) / SUM(impressions) ELSE 0 END AS ctr,
                CASE WHEN SUM(impressions) > 0 THEN SUM(position * impressions) / SUM(impressions) ELSE 0 END AS position
             FROM {$table}
             WHERE property_hash = %s AND data_date BETWEEN %s AND %s
             GROUP BY page_hash
             ORDER BY impressions DESC, clicks DESC
             LIMIT %d",
            hash('sha256', $property_id),
            $date_from,
            $date_to,
            max(1, absint($limit))
        ),
        ARRAY_A
    );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
}

/**
 * Historial reciente de sincronizaciones.
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
function seo_google_get_sync_history($property_id, $limit = 10) {
    global $wpdb;

    $table = esc_sql( seo_google_table('sync_runs') );

    if (!seo_google_table_exists($table)) {
        return array();
    }

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Native live read/write of plugin-owned tables, retaining immediate reporting consistency; query values are prepared.
    return $wpdb->get_results(
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Trusted internal table identifiers; generated value placeholders are bound using wpdb::prepare (WordPress 5.8 compatibility).
        $wpdb->prepare(
            "SELECT * FROM {$table}
             WHERE property_hash = %s
             ORDER BY id DESC LIMIT %d",
            hash('sha256', $property_id),
            max(1, absint($limit))
        ),
        ARRAY_A
    );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
}


/**
 * Pantalla principal de Google Intelligence.
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
function seo_google_intelligence_page() {
    $install_result = seo_google_install_tables();

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only Google Intelligence view selector.
    $view = isset($_GET['google_view'])
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only GET parameter, no state modification.
        ? sanitize_key(wp_unslash($_GET['google_view']))
        : 'actions';

    // Conserva compatibilidad con enlaces de versiones anteriores.
    if ('opportunities' === $view) {
        $view = 'signals';
    } elseif ('discrepancies' === $view) {
        $view = 'comparison';
    } elseif ('growth_guidance' === $view) {
        $view = 'actions';
    }

    $executive_views = array(
        'actions',
        'market',
        'landings_plan',
        'content_plan',
        'catalog_plan',
        'results',
    );
    $technical_views = array(
        'summary',
        'signals',
        'changes',
        'comparison',
        'demand_catalog',
        'trends_market',
        'coverage',
        'laboratory',
    );
    $allowed_views = array_merge($executive_views, $technical_views);

    if (!in_array($view, $allowed_views, true)) {
        $view = 'actions';
    }

    $base_url = seo_google_admin_url('actions');
    $tabs = array(
        'actions'       => 'Qué hacer',
        'market'        => 'Mercado',
        'landings_plan' => 'Landings',
        'content_plan'  => 'Contenido',
        'catalog_plan'  => 'Catálogo',
        'results'       => 'Resultados',
    );

    echo '<div class="seo-google-intelligence">';
    echo '<h2>' . esc_html__( 'Google Intelligence', 'taxocommerce-google-bing' ) . '</h2>';
    echo '<p style="margin-top:-6px;color:#646970;">Módulo de decisión <strong>V' . esc_html(SEO_GOOGLE_MODULE_VERSION) . '</strong></p>';
    echo '<p>' . esc_html__( 'Google descubre y mide la demanda; WordPress y el catálogo comprueban cobertura; el sistema recomienda qué mejorar.', 'taxocommerce-google-bing' ) . '</p>';

    echo '<nav class="nav-tab-wrapper" style="margin-bottom:20px;">';
    foreach ($tabs as $tab_key => $tab_label) {
        $tab_url = add_query_arg('google_view', $tab_key, $base_url);
        $class = 'nav-tab' . ($view === $tab_key ? ' nav-tab-active' : '');
        echo '<a class="' . esc_attr($class) . '" href="' . esc_url($tab_url) . '">' . esc_html($tab_label) . '</a>';
    }
    echo '</nav>';

    seo_google_render_notices();

    if (in_array($view, $technical_views, true)) {
        echo '<div class="notice notice-info inline"><p><strong>Vista técnica:</strong> esta pantalla se conserva como evidencia y diagnóstico. <a href="' . esc_url(seo_google_admin_url('market')) . '">Volver a Mercado</a>.</p></div>';
    }

    if (is_wp_error($install_result) && 'summary' === $view) {
        $table_status = seo_google_tables_status();
        echo '<div class="notice notice-error inline"><p><strong>Error de base de datos:</strong> ' . esc_html($install_result->get_error_message()) . '</p></div>';
        echo '<p><strong>Versión cargada:</strong> ' . esc_html(SEO_GOOGLE_MODULE_VERSION) . '</p>';
        echo '<p><code>' . esc_html($table_status['runs_table']) . '</code>: ' . ($table_status['runs_exists'] ? 'creada' : '<strong>no existe</strong>') . '<br>';
        echo '<code>' . esc_html($table_status['data_table']) . '</code>: ' . ($table_status['data_exists'] ? 'creada' : '<strong>no existe</strong>') . '</p>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin:16px 0;">';
        echo '<input type="hidden" name="action" value="seo_google_repair_tables">';
        wp_nonce_field('seo_google_repair_tables', 'seo_google_repair_nonce');
        submit_button('Crear o reparar tablas', 'primary', 'submit', false);
        echo '</form>';
        echo '<p>' . esc_html__( 'Si la reparación vuelve a fallar, copia el mensaje completo de MySQL mostrado en esta pantalla.', 'taxocommerce-google-bing' ) . '</p>';
        echo '</div>';
        return;
    }

    if (in_array($view, $executive_views, true)) {
        if (function_exists('seo_google_opportunity_render')) {
            $days = absint(seo_google_query_arg('opportunity_days', 60));
            $days = in_array($days, array(28, 60, 90), true) ? $days : 60;
            seo_google_opportunity_render($view, $days);
        } else {
            echo '<div class="notice notice-error inline"><p>Falta el archivo <code>seo-google-opportunity-engine.php</code>.</p></div>';
        }
    } elseif ('summary' === $view) {
        seo_google_render_summary();
    } elseif ('signals' === $view) {
        seo_google_render_signals();
    } elseif ('changes' === $view) {
        seo_google_render_changes();
    } elseif ('comparison' === $view) {
        seo_google_render_comparison();
    } elseif ('demand_catalog' === $view) {
        if (function_exists('seo_google_render_demand_catalog')) {
            seo_google_render_demand_catalog();
        } else {
            echo '<div class="notice notice-error inline"><p>Falta el archivo <code>seo-google-demand-catalog.php</code>.</p></div>';
        }
    } elseif ('trends_market' === $view) {
        if (function_exists('seo_google_render_trends_market')) {
            seo_google_render_trends_market();
        } else {
            echo '<div class="notice notice-error inline"><p>Falta el archivo <code>seo-google-trends.php</code>.</p></div>';
        }
    } elseif ('coverage' === $view) {
        seo_google_render_coverage();
    } elseif ('laboratory' === $view) {
        seo_google_render_laboratory();
    }

    echo '</div>';
}

/**
 * Avisos de acciones.
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
function seo_google_render_notices() {
    $notice = sanitize_key((string) seo_google_query_arg('google_notice', ''));
    $error  = sanitize_key((string) seo_google_query_arg('google_error', ''));

    $notice_messages = array(
        'settings_saved' => 'Configuración guardada.',
        'connected'      => 'Google Search Console se ha conectado correctamente.',
        'disconnected'   => 'La conexión con Google se ha eliminado.',
        'test_ok'        => 'Conexión correcta. Google ha respondido correctamente.',
        'tables_repaired' => 'Las tablas de Google Intelligence se han creado o reparado correctamente.',
    );

    $error_messages = array(
        'missing_credentials'   => 'Guarda primero el Client ID y el Client Secret.',
        'access_denied'         => 'Google ha denegado la autorización.',
        'invalid_state'         => 'La validación de seguridad OAuth ha fallado. Vuelve a iniciar la conexión.',
        'missing_code'          => 'Google no ha devuelto el código de autorización.',
        'token_request_failed'  => 'No se pudo contactar con el servicio de tokens de Google.',
        'token_exchange_failed' => 'Google no aceptó el código de autorización. Revisa las credenciales y la URI de redirección.',
        'test_failed'           => 'La prueba de conexión ha fallado.',
        'table_repair_failed'   => 'No se pudieron crear las tablas de Google Intelligence.',
    );

    if (isset($notice_messages[$notice])) {
        $message = $notice_messages[$notice];

        if ('test_ok' === $notice && '' !== (string) seo_google_query_arg('properties', '')) {
            $message .= ' Propiedades accesibles: ' . absint(seo_google_query_arg('properties', 0)) . '.';
        }

        echo '<div class="notice notice-success inline"><p>' . esc_html($message) . '</p></div>';
    }

    if ('table_repair_failed' === $error) {
        $repair_error = get_transient('seo_google_table_repair_error_' . get_current_user_id());
        delete_transient('seo_google_table_repair_error_' . get_current_user_id());
        if ($repair_error) {
            echo '<div class="notice notice-error inline"><p><strong>Error MySQL:</strong> ' . esc_html($repair_error) . '</p></div>';
            return;
        }
    }

    if ('test_failed' === $error) {
        $stored_error = get_transient('seo_google_test_error_' . get_current_user_id());
        delete_transient('seo_google_test_error_' . get_current_user_id());

        if ($stored_error) {
            $error_messages['test_failed'] .= ' ' . $stored_error;
        }
    }

    if (in_array($error, array('token_request_failed', 'token_exchange_failed'), true)) {
        $oauth_error = get_transient('seo_google_oauth_error_' . get_current_user_id());
        delete_transient('seo_google_oauth_error_' . get_current_user_id());

        if ($oauth_error && isset($error_messages[$error])) {
            $error_messages[$error] .= ' Detalle: ' . $oauth_error;
        }
    }

    if (isset($error_messages[$error])) {
        echo '<div class="notice notice-error inline"><p>' . esc_html($error_messages[$error]) . '</p></div>';
    }
}

/**
 * Pantalla de configuración completa.
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
function seo_google_render_settings() {
    $settings   = seo_google_get_settings();
    $tokens     = seo_google_get_tokens();
    $status     = seo_google_connection_status();
    $properties = array();

    if (!empty($tokens['access_token']) || !empty($tokens['refresh_token'])) {
        $properties = seo_google_get_search_console_properties();
    }

    $status_labels = array(
        'missing_credentials' => array('Faltan credenciales', '#b32d2e'),
        'not_authorized'      => array('Credenciales guardadas; falta autorizar Google', '#b57d00'),
        'missing_property'    => array('Google conectado; falta seleccionar propiedad', '#b57d00'),
        'connected'           => array('Conectado', '#2e7d32'),
    );

    $status_data = $status_labels[$status] ?? array('Estado desconocido', '#646970');

    echo '<div style="display:grid;grid-template-columns:minmax(0,2fr) minmax(300px,1fr);gap:20px;align-items:start;">';

    echo '<div>';
    echo '<div style="background:#fff;border:1px solid #dcdcde;padding:20px;border-radius:6px;">';
    echo '<h3 style="margin-top:0;">' . esc_html__( 'Conexión con Google Search Console', 'taxocommerce-google-bing' ) . '</h3>';
    echo '<p><strong>Estado:</strong> <span style="color:' . esc_attr($status_data[1]) . ';font-weight:700;">' . esc_html($status_data[0]) . '</span></p>';

    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
    echo '<input type="hidden" name="action" value="seo_google_save_settings">';
    wp_nonce_field('seo_google_save_settings', 'seo_google_settings_nonce');

    echo '<table class="form-table" role="presentation"><tbody>';

    echo '<tr>' . '<th scope="row">' . '<label for="seo-google-client-id">' . esc_html__( 'Client ID', 'taxocommerce-google-bing' ) . '</label>' . '</th>' . '<td>';
    echo '<input type="text" id="seo-google-client-id" name="client_id" value="' . esc_attr($settings['client_id']) . '" class="regular-text" autocomplete="off">';
    echo '<p class="description">' . esc_html__( 'Identificador del cliente OAuth 2.0 de tipo “Aplicación web”.', 'taxocommerce-google-bing' ) . '</p>';
    echo '</td></tr>';

    echo '<tr>' . '<th scope="row">' . '<label for="seo-google-client-secret">' . esc_html__( 'Client Secret', 'taxocommerce-google-bing' ) . '</label>' . '</th>' . '<td>';
    echo '<input type="password" id="seo-google-client-secret" name="client_secret" value="" class="regular-text" autocomplete="new-password" placeholder="' . (!empty($settings['client_secret']) ? 'Guardado; déjalo vacío para conservarlo' : '') . '">';
    echo '<p class="description">' . esc_html__( 'Se guarda en WordPress y nunca se muestra completo de nuevo.', 'taxocommerce-google-bing' ) . '</p>';
    echo '</td></tr>';

    echo '<tr>' . '<th scope="row">' . esc_html__( 'URI de redirección', 'taxocommerce-google-bing' ) . '</th>' . '<td>';
    echo '<input type="text" readonly value="' . esc_attr(seo_google_redirect_uri()) . '" class="large-text code" onclick="this.select();">';
    echo '<p class="description">' . '<strong>' . esc_html__( 'URI única del módulo.', 'taxocommerce-google-bing' ) . '</strong>' . ' ' . esc_html__( 'Cópiala exactamente en “URI de redireccionamiento autorizados” de Google Cloud. La misma dirección se usa al autorizar y al intercambiar el código por tokens.', 'taxocommerce-google-bing' ) . '</p>';
    echo '</td></tr>';

    echo '<tr>' . '<th scope="row">' . '<label for="seo-google-property">' . esc_html__( 'Propiedad de Search Console', 'taxocommerce-google-bing' ) . '</label>' . '</th>' . '<td>';

    if (is_wp_error($properties)) {
        echo '<input type="text" id="seo-google-property" name="property_id" value="' . esc_attr($settings['property_id']) . '" class="regular-text">';
        echo '<p class="description" style="color:#b32d2e;">No se pudo cargar la lista: ' . esc_html($properties->get_error_message()) . '</p>';
    } elseif (!empty($properties)) {
        echo '<select id="seo-google-property" name="property_id" style="min-width:420px;max-width:100%;">';
        echo '<option value="">' . esc_html__( 'Selecciona una propiedad', 'taxocommerce-google-bing' ) . '</option>';
        foreach ($properties as $property) {
            $label = $property['site_url'];
            if (!empty($property['permission_level'])) {
                $label .= ' — ' . $property['permission_level'];
            }
            echo '<option value="' . esc_attr($property['site_url']) . '" ' . selected($settings['property_id'], $property['site_url'], false) . '>';
            echo esc_html($label);
            echo '</option>';
        }
        echo '</select>';
    } else {
        echo '<input type="text" id="seo-google-property" name="property_id" value="' . esc_attr($settings['property_id']) . '" class="regular-text" placeholder="sc-domain:ejemplo.com">';
        echo '<p class="description">' . esc_html__( 'La lista aparecerá automáticamente después de autorizar Google.', 'taxocommerce-google-bing' ) . '</p>';
    }

    echo '</td></tr>';

    echo '<tr>' . '<th scope="row">' . '<label for="seo-google-frequency">' . esc_html__( 'Frecuencia', 'taxocommerce-google-bing' ) . '</label>' . '</th>' . '<td>';
    echo '<select id="seo-google-frequency" name="sync_frequency">';
    foreach (array('manual' => 'Manual', 'daily' => 'Diaria', 'weekly' => 'Semanal') as $value => $label) {
        echo '<option value="' . esc_attr($value) . '" ' . selected($settings['sync_frequency'], $value, false) . '>' . esc_html($label) . '</option>';
    }
    echo '</select>';
    echo '<p class="description">' . esc_html__( 'La sincronización manual ya está disponible. La ejecución automática diaria o semanal se incorporará en la siguiente fase.', 'taxocommerce-google-bing' ) . '</p>';
    echo '</td></tr>';

    echo '</tbody></table>';
    submit_button('Guardar configuración');
    echo '</form>';

    echo '<hr style="margin:24px 0;">';

    if (in_array($status, array('not_authorized', 'missing_property', 'connected'), true)) {
        echo '<div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">';

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="seo_google_connect">';
        wp_nonce_field('seo_google_connect', 'seo_google_connect_nonce');
        submit_button(
            in_array($status, array('missing_property', 'connected'), true) ? 'Volver a autorizar Google' : 'Conectar con Google',
            'primary',
            'submit',
            false
        );
        echo '</form>';

        if (!empty($tokens['access_token']) || !empty($tokens['refresh_token'])) {
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
            echo '<input type="hidden" name="action" value="seo_google_test_connection">';
            wp_nonce_field('seo_google_test_connection', 'seo_google_test_nonce');
            submit_button('Probar conexión', 'secondary', 'submit', false);
            echo '</form>';

            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" onsubmit="return confirm(\'Se eliminará la autorización de Google almacenada en TaxoCommerce SEO. ¿Continuar?\');">';
            echo '<input type="hidden" name="action" value="seo_google_disconnect">';
            wp_nonce_field('seo_google_disconnect', 'seo_google_disconnect_nonce');
            submit_button('Desconectar', 'delete', 'submit', false);
            echo '</form>';
        }

        echo '</div>';
    }

    echo '</div>';
    echo '</div>';

    echo '<aside style="background:#fff;border:1px solid #dcdcde;padding:20px;border-radius:6px;">';
    echo '<h3 style="margin-top:0;">' . esc_html__( 'Cómo obtener las credenciales', 'taxocommerce-google-bing' ) . '</h3>';
    echo '<ol style="padding-left:20px;line-height:1.65;">';
    echo '<li>' . esc_html__( 'Entra en Google Cloud Console y crea o selecciona un proyecto.', 'taxocommerce-google-bing' ) . '</li>';
    echo '<li>' . esc_html__( 'Activa la', 'taxocommerce-google-bing' ) . ' ' . '<strong>' . esc_html__( 'Google Search Console API', 'taxocommerce-google-bing' ) . '</strong>' . '.' . '</li>';
    echo '<li>' . esc_html__( 'Configura Google Auth Platform y la pantalla de consentimiento OAuth.', 'taxocommerce-google-bing' ) . '</li>';
    echo '<li>' . esc_html__( 'Crea credenciales OAuth 2.0 de tipo', 'taxocommerce-google-bing' ) . ' ' . '<strong>' . esc_html__( 'Aplicación web', 'taxocommerce-google-bing' ) . '</strong>' . '.' . '</li>';
    echo '<li>' . 'En ' . '<strong>' . esc_html__( 'URIs de redireccionamiento autorizados', 'taxocommerce-google-bing' ) . '</strong>' . esc_html__( ', añade exactamente la dirección mostrada en este formulario. No la pongas solo en “Orígenes autorizados de JavaScript”.', 'taxocommerce-google-bing' ) . '</li>';
    echo '<li>' . esc_html__( 'Copia aquí el Client ID y el Client Secret y guarda.', 'taxocommerce-google-bing' ) . '</li>';
    echo '<li>' . esc_html__( 'Si la aplicación está en modo Pruebas, añade tu cuenta en', 'taxocommerce-google-bing' ) . ' ' . '<strong>' . esc_html__( 'Usuarios de prueba', 'taxocommerce-google-bing' ) . '</strong>' . esc_html__( '; si está en producción, completa los requisitos que Google solicite.', 'taxocommerce-google-bing' ) . '</li>';
    echo '<li>' . esc_html__( 'Pulsa', 'taxocommerce-google-bing' ) . ' ' . '<strong>' . esc_html__( 'Conectar con Google', 'taxocommerce-google-bing' ) . '</strong>' . ' ' . esc_html__( 'y acepta el permiso de lectura.', 'taxocommerce-google-bing' ) . '</li>';
    echo '<li>' . esc_html__( 'Después de autorizar, selecciona la propiedad de Search Console y vuelve a guardar.', 'taxocommerce-google-bing' ) . '</li>';
    echo '</ol>';

    echo '<details style="margin-top:18px;border-top:1px solid #dcdcde;padding-top:14px;">';
    echo '<summary style="cursor:pointer;font-weight:700;">' . esc_html__( 'Problemas frecuentes y solución', 'taxocommerce-google-bing' ) . '</summary>';
    echo '<div style="line-height:1.65;margin-top:12px;">';
    echo '<p>' . '<strong>' . esc_html__( 'Error 400: redirect_uri_mismatch', 'taxocommerce-google-bing' ) . '</strong>' . '<br>' . esc_html__( 'La URI enviada por TaxoCommerce SEO no coincide exactamente con Google Cloud. Copia la URI mostrada arriba y regístrala, carácter por carácter, en “URIs de redireccionamiento autorizados”.', 'taxocommerce-google-bing' ) . '</p>';
    echo '<p>' . '<strong>' . esc_html__( 'Error 403: access_denied / solo testers', 'taxocommerce-google-bing' ) . '</strong>' . '<br>' . esc_html__( 'La aplicación está en modo Pruebas y la cuenta no figura como usuario de prueba. Añádela en Google Auth Platform → Público/Audiencia → Usuarios de prueba, o publica la aplicación si corresponde.', 'taxocommerce-google-bing' ) . '</p>';
    echo '<p><strong>Forbidden al volver desde Google</strong><br>La versión actual usa <code>wp-admin/admin-post.php</code> como callback dedicado. Si el hosting sigue bloqueándolo, revisa ModSecurity o el firewall y permite la acción <code>seo_google_oauth_callback</code>.</p>';
    echo '<p>' . '<strong>' . esc_html__( 'Credenciales guardadas; falta autorizar Google', 'taxocommerce-google-bing' ) . '</strong>' . '<br>' . esc_html__( 'Las claves existen, pero todavía no hay tokens. Pulsa “Conectar con Google”.', 'taxocommerce-google-bing' ) . '</p>';
    echo '<p><strong>Google conectado; falta seleccionar propiedad</strong><br>Elige una propiedad de la lista y guarda. Para una propiedad de dominio el formato habitual es <code>sc-domain:dominio.com</code>.</p>';
    echo '</div>';
    echo '</details>';

    echo '<div style="background:#f6f7f7;border-left:4px solid #2271b1;padding:12px;margin-top:16px;">';
    echo '<strong>' . esc_html__( 'Permiso solicitado', 'taxocommerce-google-bing' ) . '</strong>' . '<br>';
    echo '<code style="word-break:break-all;">' . esc_html(SEO_GOOGLE_SCOPE_SEARCH_CONSOLE) . '</code>';
    echo '<p style="margin-bottom:0;">' . esc_html__( 'Es un permiso de solo lectura. TaxoCommerce SEO no podrá añadir ni eliminar propiedades o sitemaps.', 'taxocommerce-google-bing' ) . '</p>';
    echo '</div>';

    echo '<p style="margin-top:16px;">' . '<a href="https://console.cloud.google.com/apis/credentials" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Abrir credenciales de Google Cloud', 'taxocommerce-google-bing' ) . '</a>' . '</p>';
    echo '<p>' . '<a href="https://console.cloud.google.com/apis/library/searchconsole.googleapis.com" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Activar Search Console API', 'taxocommerce-google-bing' ) . '</a>' . '</p>';
    echo '</aside>';

    echo '</div>';
}


/**
 * Pantalla de sincronización manual y su historial.
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
function seo_google_render_sync_status() {
    $settings       = seo_google_get_settings();
    $status         = seo_google_connection_status();
    $install_result = seo_google_install_tables(false);

    echo '<div style="background:#fff;border:1px solid #dcdcde;padding:20px;border-radius:6px;">';
    echo '<h3 style="margin-top:0;">' . esc_html__( 'Sincronización', 'taxocommerce-google-bing' ) . '</h3>';

    $table_status = seo_google_tables_status();
    echo '<p style="color:#646970;"><strong>Versión:</strong> ' . esc_html(SEO_GOOGLE_MODULE_VERSION)
        . ' · <strong>Tabla de ejecuciones:</strong> ' . ($table_status['runs_exists'] ? 'OK' : 'NO EXISTE')
        . ' · <strong>Consultas:</strong> ' . ($table_status['data_exists'] ? 'OK' : 'NO EXISTE')
        . ' · <strong>Totales:</strong> ' . ($table_status['totals_exists'] ? 'OK' : 'NO EXISTE')
        . ' · <strong>Páginas:</strong> ' . ($table_status['pages_exists'] ? 'OK' : 'NO EXISTE') . '</p>';

    if (is_wp_error($install_result)) {
        echo '<div class="notice notice-error inline"><p><strong>No se pudieron preparar las tablas:</strong> '
            . esc_html($install_result->get_error_message()) . '</p></div>';
        echo '<p>' . esc_html__( 'No se consultará el historial hasta que todas las tablas indiquen', 'taxocommerce-google-bing' ) . ' ' . '<strong>' . 'OK' . '</strong>' . '.' . '</p>';
        echo '</div>';
        return;
    }

    if ('connected' !== $status) {
        echo '<div class="notice notice-warning inline">' . '<p>' . esc_html__( 'Completa la conexión y selecciona una propiedad antes de sincronizar.', 'taxocommerce-google-bing' ) . '</p>' . '</div>';
        echo '<p><a class="button button-primary" href="' . esc_url(seo_google_admin_url('settings')) . '">Ir a configuración</a></p>';
        echo '</div>';
        return;
    }

    $history = seo_google_get_sync_history($settings['property_id'], 10);
    $latest  = seo_google_latest_data_date($settings['property_id']);

    echo '<p><strong>Propiedad:</strong> <code>' . esc_html($settings['property_id']) . '</code></p>';
    echo '<p><strong>Frecuencia configurada:</strong> ' . esc_html($settings['sync_frequency']) . '</p>';
    echo '<p><strong>Último día almacenado:</strong> ' . ($latest ? esc_html($latest) : 'Sin datos todavía') . '</p>';

    echo '<div style="background:#f6f7f7;border-left:4px solid #2271b1;padding:12px 14px;margin:16px 0;">';
    echo '<p style="margin:0 0 6px;">' . '<strong>' . esc_html__( 'Primera sincronización o migración:', 'taxocommerce-google-bing' ) . '</strong>' . ' ' . esc_html__( 'descarga 90 días de totales de propiedad, páginas y consultas finalizados.', 'taxocommerce-google-bing' ) . '</p>';
    echo '<p style="margin:0;">' . '<strong>' . esc_html__( 'Sincronizaciones posteriores:', 'taxocommerce-google-bing' ) . '</strong>' . ' ' . esc_html__( 'actualizan los últimos 7 días con solapamiento para recoger correcciones de Google.', 'taxocommerce-google-bing' ) . '</p>';
    echo '</div>';

    echo '<button type="button" id="seo-google-sync-button" class="button button-primary button-hero">' . esc_html__( 'Sincronizar ahora', 'taxocommerce-google-bing' ) . '</button>';
    echo '<p id="seo-google-sync-message" style="margin-top:14px;"></p>';
    echo '<div id="seo-google-sync-progress-wrap" style="display:none;max-width:720px;background:#dcdcde;border-radius:999px;overflow:hidden;height:18px;">';
    echo '<div id="seo-google-sync-progress" style="width:0%;height:18px;background:#2271b1;transition:width .2s;"></div>';
    echo '</div>';
    echo '<p class="description">' . esc_html__( 'Mantén esta pestaña abierta durante la primera importación. Si se interrumpe, vuelve a pulsar el botón para reanudar.', 'taxocommerce-google-bing' ) . '</p>';

    $config = array(
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce'   => wp_create_nonce('seo_google_sync'),
    );

    ob_start();
    echo 'window.seoGoogleSyncConfig=' . wp_json_encode($config) . ';';
    echo <<<'JS'
(function(){
    const button = document.getElementById('seo-google-sync-button');
    const message = document.getElementById('seo-google-sync-message');
    const progressWrap = document.getElementById('seo-google-sync-progress-wrap');
    const progress = document.getElementById('seo-google-sync-progress');
    const cfg = window.seoGoogleSyncConfig || {};

    if (!button || !cfg.ajaxUrl) return;

    async function request(action, data) {
        const body = new URLSearchParams(Object.assign({action: action, nonce: cfg.nonce}, data || {}));
        const response = await fetch(cfg.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
            body: body.toString()
        });

        const raw = await response.text();
        let json;

        try {
            json = JSON.parse(raw);
        } catch (parseError) {
            const readable = String(raw || '')
                .replace(/<script[\s\S]*?<\/script>/gi, ' ')
                .replace(/<style[\s\S]*?<\/style>/gi, ' ')
                .replace(/<[^>]*>/g, ' ')
                .replace(/&nbsp;/gi, ' ')
                .replace(/\s+/g, ' ')
                .trim();

            throw new Error(
                'El servidor no devolvió JSON. Respuesta HTTP ' + response.status
                + (readable ? ': ' + readable.slice(0, 500) : '. Respuesta vacía.')
            );
        }

        if (!response.ok || !json.success) {
            const errorMessage = json.data && json.data.message
                ? json.data.message
                : 'La sincronización ha fallado con HTTP ' + response.status + '.';
            throw new Error(errorMessage);
        }

        return json.data;
    }

    function updateProgress(done, total) {
        const percent = total > 0 ? Math.min(100, Math.round((done / total) * 100)) : 0;
        progressWrap.style.display = 'block';
        progress.style.width = percent + '%';
    }

    async function processRun(run) {
        let state = run;
        updateProgress(state.days_completed || 0, state.days_total || 1);

        while (true) {
            message.textContent = 'Procesando ' + (state.current_date || state.next_date || '') + '…';
            const result = await request('seo_google_sync_day', {run_id: state.run_id});
            updateProgress(result.days_completed, result.days_total);

            message.textContent = 'Días: ' + result.days_completed + '/' + result.days_total
                + ' · Filas guardadas: ' + Number(result.rows_upserted || 0).toLocaleString('es-ES');

            if (result.done) {
                progress.style.width = '100%';
                message.innerHTML = '<strong>Sincronización completada.</strong> Días: '
                    + result.days_completed + ' · Filas guardadas: '
                    + Number(result.rows_upserted || 0).toLocaleString('es-ES')
                    + '. Recargando el informe…';
                setTimeout(function(){ window.location.reload(); }, 1200);
                return;
            }

            state.current_date = result.next_date;
        }
    }

    button.addEventListener('click', async function(){
        button.disabled = true;
        button.textContent = 'Preparando sincronización…';
        message.textContent = '';

        try {
            const run = await request('seo_google_sync_start');
            button.textContent = run.mode === 'resume' ? 'Reanudando…' : 'Sincronizando…';
            await processRun(run);
        } catch (error) {
            message.textContent = 'Error: ' + String(error.message || error);
            button.disabled = false;
            button.textContent = 'Reintentar sincronización';
        }
    });
})();
JS;
    $seo_google_sync_script = (string) ob_get_clean();
    seo_google_add_inline_script($seo_google_sync_script);

    echo '</div>';

    echo '<div style="background:#fff;border:1px solid #dcdcde;padding:20px;border-radius:6px;margin-top:20px;">';
    echo '<h3 style="margin-top:0;">' . esc_html__( 'Historial reciente', 'taxocommerce-google-bing' ) . '</h3>';

    if (!$history) {
        echo '<p>' . esc_html__( 'No hay sincronizaciones registradas.', 'taxocommerce-google-bing' ) . '</p>';
    } else {
        echo '<div style="overflow:auto;"><table class="widefat striped"><thead><tr>';
        echo '<th>' . esc_html__( 'Inicio', 'taxocommerce-google-bing' ) . '</th>' . '<th>' . esc_html__( 'Período', 'taxocommerce-google-bing' ) . '</th>' . '<th>' . esc_html__( 'Estado', 'taxocommerce-google-bing' ) . '</th>' . '<th>' . esc_html__( 'Días', 'taxocommerce-google-bing' ) . '</th>' . '<th>' . esc_html__( 'Filas', 'taxocommerce-google-bing' ) . '</th>' . '<th>' . esc_html__( 'Detalle', 'taxocommerce-google-bing' ) . '</th>';
        echo '</tr></thead><tbody>';

        foreach ($history as $run) {
            $status_label = array(
                'running' => 'En curso',
                'success' => 'Correcta',
                'failed'  => 'Fallida',
                'pending' => 'Pendiente',
            );

            echo '<tr>';
            echo '<td>' . esc_html($run['started_at']) . '</td>';
            echo '<td><code>' . esc_html($run['date_from']) . '</code> → <code>' . esc_html($run['date_to']) . '</code></td>';
            echo '<td>' . esc_html($status_label[$run['status']] ?? $run['status']) . '</td>';
            echo '<td>' . esc_html(number_format_i18n(absint($run['days_completed']))) . '/' . esc_html(number_format_i18n(absint($run['days_total']))) . '</td>';
            echo '<td>' . esc_html(number_format_i18n(absint($run['rows_upserted']))) . '</td>';
            echo '<td>' . ($run['error_message'] ? esc_html($run['error_message']) : '—') . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table></div>';
    }

    echo '</div>';
}


/**
 * Serie diaria histórica para los gráficos del resumen.
 *
 * No crea tablas nuevas: reutiliza seo_google_search_data y conserva el mismo
 * criterio de posición media ponderada por impresiones usado en el resumen.
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
function seo_google_get_summary_trend_data($property_id, $days = 365) {
    global $wpdb;

    $table       = esc_sql( seo_google_table('search_data') );
    $latest_date = seo_google_latest_data_date($property_id);

    if (!$latest_date || !seo_google_table_exists($table)) {
        return array();
    }

    $days = max(7, min(730, absint($days)));

    try {
        $date_from = (new DateTimeImmutable($latest_date))
            ->modify('-' . ($days - 1) . ' days')
            ->format('Y-m-d');
    } catch (Exception $exception) {
        $date_from = gmdate('Y-m-d', strtotime($latest_date . ' -364 days'));
    }

    $totals_table = esc_sql( seo_google_table('search_totals') );
    if (seo_google_table_exists($totals_table)) {
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Trusted internal table identifiers; generated value placeholders are bound using wpdb::prepare (WordPress 5.8 compatibility).
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Native live read/write of plugin-owned tables, retaining immediate reporting consistency; query values are prepared.
        $totals_rows = $wpdb->get_results($wpdb->prepare(
            "SELECT data_date,clicks,impressions,ctr,position FROM {$totals_table}
             WHERE property_hash=%s AND data_date BETWEEN %s AND %s AND search_type='web' ORDER BY data_date ASC",
            hash('sha256', $property_id), $date_from, $latest_date
        ), ARRAY_A);
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
        // Never splice property totals with a different aggregation method.
        // Search Console has no meaningful average position on a day with
        // zero impressions. Preserve zero clicks/impressions, but expose
        // position as null so charts/exports do not invent a ranking of 0.
        if ($totals_rows) {
            foreach ($totals_rows as &$total_row) {
                $total_row['clicks'] = (float) ($total_row['clicks'] ?? 0);
                $total_row['impressions'] = (float) ($total_row['impressions'] ?? 0);
                $total_row['ctr'] = (float) ($total_row['ctr'] ?? 0);
                $total_row['position'] = $total_row['impressions'] > 0
                    ? (float) ($total_row['position'] ?? 0)
                    : null;
            }
            unset($total_row);
            return $totals_rows;
        }
    }

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Native live read/write of plugin-owned tables, retaining immediate reporting consistency; query values are prepared.
    $rows = $wpdb->get_results(
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Trusted internal table identifiers; generated value placeholders are bound using wpdb::prepare (WordPress 5.8 compatibility).
        $wpdb->prepare(
            "SELECT
                data_date,
                COALESCE(SUM(clicks),0) AS clicks,
                COALESCE(SUM(impressions),0) AS impressions,
                CASE WHEN SUM(impressions) > 0 THEN SUM(clicks) / SUM(impressions) ELSE 0 END AS ctr,
                CASE WHEN SUM(impressions) > 0 THEN SUM(position * impressions) / SUM(impressions) ELSE 0 END AS position
             FROM {$table}
             WHERE property_hash = %s
               AND data_date BETWEEN %s AND %s
             GROUP BY data_date
             ORDER BY data_date ASC",
            hash('sha256', $property_id),
            $date_from,
            $latest_date
        ),
        ARRAY_A
    );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber

    foreach ($rows as &$row) {
        $row['clicks']      = (float) $row['clicks'];
        $row['impressions'] = (float) $row['impressions'];
        $row['ctr']         = (float) $row['ctr'];
        $row['position']    = $row['impressions'] > 0 ? (float) $row['position'] : null;
    }
    unset($row);

    return $rows;
}

/**
 * Renderiza tres gráficos ligeros sin librerías externas.
 *
 * El navegador agrupa la misma serie diaria por día, semana o mes. Así no se
 * duplica información ni se necesita otra tabla de histórico.
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
function seo_google_render_summary_charts(array $daily_rows) {
    if (empty($daily_rows)) {
        return;
    }

    $chart_id = 'seo-google-trends-' . wp_rand(1000, 999999);
    $payload  = array();

    foreach ($daily_rows as $row) {
        $payload[] = array(
            'date'        => (string) $row['data_date'],
            'clicks'      => (float) $row['clicks'],
            'impressions' => (float) $row['impressions'],
            'position'    => isset($row['position']) ? (float) $row['position'] : null,
        );
    }

    echo '<section id="' . esc_attr($chart_id) . '" class="seo-google-trends" style="background:#fff;border:1px solid #dcdcde;padding:20px;border-radius:6px;margin-top:20px;">';
    echo '<div style="display:flex;gap:14px;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;">';
    echo '<div>';
    echo '<h3 style="margin:0 0 6px;">' . esc_html__( 'Evolución en Google', 'taxocommerce-google-bing' ) . '</h3>';
    echo '<p style="margin:0;color:#646970;">' . esc_html__( 'Mismos datos almacenados por Search Console, vistos como tendencia. En posición,', 'taxocommerce-google-bing' ) . ' ' . '<strong>' . esc_html__( 'menos es mejor', 'taxocommerce-google-bing' ) . '</strong>' . '.' . '</p>';
    echo '</div>';
    echo '<div class="seo-google-trend-switch" style="display:flex;gap:6px;flex-wrap:wrap;">';
    echo '<button type="button" class="button button-primary" data-granularity="day">' . esc_html__( 'Diario', 'taxocommerce-google-bing' ) . '</button>';
    echo '<button type="button" class="button" data-granularity="week">' . esc_html__( 'Semanal', 'taxocommerce-google-bing' ) . '</button>';
    echo '<button type="button" class="button" data-granularity="month">' . esc_html__( 'Mensual', 'taxocommerce-google-bing' ) . '</button>';
    echo '</div>';
    echo '</div>';

    echo '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:16px;margin-top:18px;">';

    $charts = array(
        'position'    => array('title' => 'Posición media', 'note' => 'La línea sube visualmente cuando mejora el ranking.'),
        'impressions' => array('title' => 'Impresiones', 'note' => 'Cuántas veces aparecemos en resultados de Google.'),
        'clicks'      => array('title' => 'Clics', 'note' => 'Visitas recibidas desde los resultados de Google.'),
    );

    foreach ($charts as $metric => $chart) {
        echo '<div style="border:1px solid #dcdcde;border-radius:6px;padding:14px;min-width:0;">';
        echo '<div style="display:flex;justify-content:space-between;gap:10px;align-items:baseline;">';
        echo '<h4 style="margin:0;">' . esc_html($chart['title']) . '</h4>';
        echo '<strong data-latest="' . esc_attr($metric) . '" style="font-size:18px;white-space:nowrap;"></strong>';
        echo '</div>';
        echo '<div data-chart="' . esc_attr($metric) . '" style="margin-top:8px;min-height:220px;"></div>';
        echo '<p class="description" style="margin:6px 0 0;">' . esc_html($chart['note']) . '</p>';
        echo '</div>';
    }

    echo '</div>';
    echo '<p class="description" style="margin:14px 0 0;">' . '<span data-period-count>' . '</span>' . ' ' . esc_html__( '· La posición sigue ponderada por impresiones, igual que en las tarjetas superiores.', 'taxocommerce-google-bing' ) . '</p>';
    echo '</section>';

    ob_start();
    echo '(function(){';
    echo 'const root=document.getElementById(' . wp_json_encode($chart_id) . ');';
    echo 'const raw=' . wp_json_encode($payload) . ';';
    echo <<<'JS'
if (!root || !Array.isArray(raw) || !raw.length) return;

const svgNS = 'http://www.w3.org/2000/svg';
const state = {mode: 'day'};

function mondayKey(dateString) {
    const d = new Date(dateString + 'T00:00:00Z');
    const offset = (d.getUTCDay() + 6) % 7;
    d.setUTCDate(d.getUTCDate() - offset);
    return d.toISOString().slice(0, 10);
}

function periodKey(dateString, mode) {
    if (mode === 'month') return dateString.slice(0, 7);
    if (mode === 'week') return mondayKey(dateString);
    return dateString;
}

function labelFor(key, mode) {
    if (mode === 'month') {
        const parts = key.split('-');
        return parts[1] + '/' + parts[0];
    }

    const parts = key.split('-');
    const label = parts[2] + '/' + parts[1];
    return mode === 'week' ? 'Sem ' + label : label;
}

function aggregate(mode) {
    const buckets = new Map();

    raw.forEach(function(row) {
        const key = periodKey(row.date, mode);
        if (!buckets.has(key)) {
            buckets.set(key, {
                key: key,
                clicks: 0,
                impressions: 0,
                positionWeighted: 0,
                positionImpressions: 0
            });
        }

        const bucket = buckets.get(key);
        const impressions = Number(row.impressions || 0);
        const rawPosition = row.position;
        const position = rawPosition === null || rawPosition === undefined ? null : Number(rawPosition);
        bucket.clicks += Number(row.clicks || 0);
        bucket.impressions += impressions;
        if (impressions > 0 && position !== null && Number.isFinite(position) && position > 0) {
            bucket.positionWeighted += position * impressions;
            bucket.positionImpressions += impressions;
        }
    });

    return Array.from(buckets.values()).map(function(bucket) {
        return {
            key: bucket.key,
            label: labelFor(bucket.key, mode),
            clicks: bucket.clicks,
            impressions: bucket.impressions,
            position: bucket.positionImpressions > 0 ? bucket.positionWeighted / bucket.positionImpressions : null
        };
    });
}

function formatNumber(value, metric) {
    if (metric === 'position' && (value === null || value === undefined || !Number.isFinite(Number(value)) || Number(value) <= 0)) {
        return '—';
    }
    const number = Number(value || 0);
    if (metric === 'position') {
        return number.toLocaleString('es-ES', {minimumFractionDigits: 1, maximumFractionDigits: 1});
    }
    return Math.round(number).toLocaleString('es-ES');
}

function niceMax(value) {
    if (value <= 0) return 1;
    const power = Math.pow(10, Math.floor(Math.log10(value)));
    const scaled = value / power;
    let nice = 10;
    if (scaled <= 1) nice = 1;
    else if (scaled <= 2) nice = 2;
    else if (scaled <= 5) nice = 5;
    return nice * power;
}

function appendText(svg, x, y, text, anchor, size, weight) {
    const node = document.createElementNS(svgNS, 'text');
    node.setAttribute('x', x);
    node.setAttribute('y', y);
    node.setAttribute('text-anchor', anchor || 'start');
    node.setAttribute('font-size', size || '11');
    node.setAttribute('fill', '#646970');
    if (weight) node.setAttribute('font-weight', weight);
    node.textContent = text;
    svg.appendChild(node);
}

function renderChart(container, rows, metric) {
    container.innerHTML = '';

    const width = 760;
    const height = 220;
    const margin = {top: 16, right: 18, bottom: 34, left: 54};
    const plotW = width - margin.left - margin.right;
    const plotH = height - margin.top - margin.bottom;
    const values = rows.map(function(row){
        if (metric === 'position') {
            const value = row[metric];
            if (value === null || value === undefined) return null;
            const number = Number(value);
            return Number.isFinite(number) && number > 0 ? number : null;
        }
        return Number(row[metric] || 0);
    });
    const validValues = metric === 'position'
        ? values.filter(function(value){ return value !== null; })
        : values;

    if (!validValues.length) {
        container.textContent = metric === 'position' ? 'Sin posición válida para este periodo.' : 'Sin datos.';
        return;
    }

    let min = Math.min.apply(null, validValues);
    let max = Math.max.apply(null, validValues);

    if (metric !== 'position') {
        min = 0;
        max = niceMax(max);
    } else {
        const span = Math.max(1, max - min);
        min = Math.max(0, min - span * 0.08);
        max = max + span * 0.08;
    }

    if (max === min) max = min + 1;

    const svg = document.createElementNS(svgNS, 'svg');
    svg.setAttribute('viewBox', '0 0 ' + width + ' ' + height);
    svg.setAttribute('width', '100%');
    svg.setAttribute('height', '220');
    svg.setAttribute('role', 'img');
    svg.setAttribute('aria-label', 'Gráfico de ' + metric);

    const x = function(index) {
        if (rows.length === 1) return margin.left + plotW / 2;
        return margin.left + (index / (rows.length - 1)) * plotW;
    };

    const y = function(value) {
        const ratio = (Number(value) - min) / (max - min);
        if (metric === 'position') {
            // Para ranking: menor posición = más arriba en el gráfico.
            return margin.top + ratio * plotH;
        }
        return margin.top + (1 - ratio) * plotH;
    };

    for (let i = 0; i <= 4; i++) {
        const gy = margin.top + (i / 4) * plotH;
        const line = document.createElementNS(svgNS, 'line');
        line.setAttribute('x1', margin.left);
        line.setAttribute('x2', width - margin.right);
        line.setAttribute('y1', gy);
        line.setAttribute('y2', gy);
        line.setAttribute('stroke', '#e2e4e7');
        line.setAttribute('stroke-width', '1');
        svg.appendChild(line);

        let value;
        if (metric === 'position') {
            value = min + (i / 4) * (max - min);
        } else {
            value = max - (i / 4) * (max - min);
        }
        appendText(svg, margin.left - 8, gy + 4, formatNumber(value, metric), 'end', '10');
    }

    const tickIndexes = [];
    const tickCount = Math.min(5, rows.length);
    for (let i = 0; i < tickCount; i++) {
        tickIndexes.push(Math.round((i / Math.max(1, tickCount - 1)) * (rows.length - 1)));
    }
    Array.from(new Set(tickIndexes)).forEach(function(index) {
        appendText(svg, x(index), height - 10, rows[index].label, 'middle', '10');
    });

    const points = values.map(function(value, index) {
        if (metric === 'position' && value === null) return null;
        return x(index).toFixed(2) + ',' + y(value).toFixed(2);
    }).filter(Boolean);

    const polyline = document.createElementNS(svgNS, 'polyline');
    polyline.setAttribute('points', points.join(' '));
    polyline.setAttribute('fill', 'none');
    polyline.setAttribute('stroke', '#2271b1');
    polyline.setAttribute('stroke-width', '3');
    polyline.setAttribute('stroke-linecap', 'round');
    polyline.setAttribute('stroke-linejoin', 'round');
    svg.appendChild(polyline);

    rows.forEach(function(row, index) {
        if (metric === 'position' && values[index] === null) return;
        const dot = document.createElementNS(svgNS, 'circle');
        dot.setAttribute('cx', x(index));
        dot.setAttribute('cy', y(values[index]));
        dot.setAttribute('r', rows.length <= 31 ? '3' : '1.8');
        dot.setAttribute('fill', '#2271b1');
        const title = document.createElementNS(svgNS, 'title');
        title.textContent = row.label + ': ' + formatNumber(row[metric], metric);
        dot.appendChild(title);
        svg.appendChild(dot);
    });

    container.appendChild(svg);
}

function render() {
    const rows = aggregate(state.mode);

    root.querySelectorAll('[data-chart]').forEach(function(container) {
        const metric = container.getAttribute('data-chart');
        renderChart(container, rows, metric);

        const latest = root.querySelector('[data-latest="' + metric + '"]');
        if (latest && rows.length) {
            let latestValue = rows[rows.length - 1][metric];
            if (metric === 'position') {
                for (let i = rows.length - 1; i >= 0; i--) {
                    const candidate = rows[i][metric];
                    if (candidate !== null && candidate !== undefined && Number.isFinite(Number(candidate)) && Number(candidate) > 0) {
                        latestValue = candidate;
                        break;
                    }
                }
            }
            latest.textContent = formatNumber(latestValue, metric);
        }
    });

    const count = root.querySelector('[data-period-count]');
    if (count) {
        const unit = state.mode === 'month' ? 'meses' : (state.mode === 'week' ? 'semanas' : 'días');
        count.textContent = rows.length.toLocaleString('es-ES') + ' ' + unit + ' representados';
    }

    root.querySelectorAll('[data-granularity]').forEach(function(button) {
        const active = button.getAttribute('data-granularity') === state.mode;
        button.classList.toggle('button-primary', active);
    });
}

root.querySelectorAll('[data-granularity]').forEach(function(button) {
    button.addEventListener('click', function() {
        state.mode = button.getAttribute('data-granularity') || 'day';
        render();
    });
});

render();
JS;
    echo '})();';
    $seo_google_chart_script = (string) ob_get_clean();
    seo_google_add_inline_script($seo_google_chart_script);
}

/**
 * Primer resumen real de Search Console.
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
function seo_google_render_summary() {
    $settings = seo_google_get_settings();
    $status   = seo_google_connection_status();

    if ('connected' !== $status) {
        echo '<div class="notice notice-info inline">' . '<p>' . '<strong>' . esc_html__( 'Google Search Console todavía no está conectado completamente.', 'taxocommerce-google-bing' ) . '</strong>' . '</p>' . '</div>';
        echo '<p><a class="button button-primary" href="' . esc_url(seo_google_admin_url('settings')) . '">Configurar conexión</a></p>';
        return;
    }

    $metrics = seo_google_get_summary_metrics($settings['property_id'], 28);

    if (!$metrics) {
        echo '<div class="notice notice-info inline">' . '<p>' . '<strong>' . esc_html__( 'La conexión está preparada, pero aún no hay datos almacenados.', 'taxocommerce-google-bing' ) . '</strong>' . '</p>' . '</div>';
        echo '<p><a class="button button-primary" href="' . esc_url(seo_google_admin_url('sync')) . '">Ejecutar primera sincronización</a></p>';
        return;
    }

    $cards = array(
        'Clics'       => number_format_i18n((float) $metrics['clicks'], 0),
        'Impresiones' => number_format_i18n((float) $metrics['impressions'], 0),
        'CTR'         => number_format_i18n(((float) $metrics['ctr']) * 100, 2) . '%',
        'Posición'    => number_format_i18n((float) $metrics['position'], 1),
        'Consultas'   => number_format_i18n(absint($metrics['queries'])),
        'Páginas'     => number_format_i18n(absint($metrics['pages'])),
    );

    echo '<div style="background:#fff;border:1px solid #dcdcde;padding:20px;border-radius:6px;">';
    echo '<h3 style="margin-top:0;">' . esc_html__( 'Últimos 28 días disponibles', 'taxocommerce-google-bing' ) . '</h3>';
    echo '<p><code>' . esc_html($metrics['date_from']) . '</code> → <code>' . esc_html($metrics['date_to']) . '</code></p>';
    $coverage_notice = seo_google_metrics_coverage_notice($metrics);
    if ($coverage_notice) echo '<div class="notice notice-warning inline"><p>' . esc_html($coverage_notice) . '</p></div>';
    echo '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:14px;">';

    foreach ($cards as $label => $value) {
        echo '<div style="border:1px solid #dcdcde;border-radius:6px;padding:14px;">';
        echo '<div style="color:#646970;font-size:12px;text-transform:uppercase;font-weight:700;">' . esc_html($label) . '</div>';
        echo '<div style="font-size:26px;font-weight:700;margin-top:6px;">' . esc_html($value) . '</div>';
        echo '</div>';
    }

    echo '</div>';
    echo '<p class="description" style="margin-top:14px;">' . esc_html__( 'Clics, impresiones, CTR y posición proceden del total de propiedad de Search Console cuando la cobertura está completa. Consultas y páginas son una muestra diagnóstica; no suman necesariamente el total.', 'taxocommerce-google-bing' ) . '</p>';
    echo '</div>';

    $trend_rows = seo_google_get_summary_trend_data($settings['property_id'], 365);
    seo_google_render_summary_charts($trend_rows);

    $queries = seo_google_get_top_queries($settings['property_id'], $metrics['date_from'], $metrics['date_to'], 15);
    $pages   = seo_google_get_top_pages($settings['property_id'], $metrics['date_from'], $metrics['date_to'], 15);

    echo '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(420px,1fr));gap:20px;margin-top:20px;">';
    seo_google_render_top_table('Consultas con más impresiones', $queries, false);
    seo_google_render_top_table('Páginas con más impresiones', $pages, true);
    echo '</div>';
}

/**
 * Tabla reutilizable de consultas o páginas.
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
function seo_google_render_top_table($title, array $rows, $is_url = false) {
    echo '<div style="background:#fff;border:1px solid #dcdcde;padding:18px;border-radius:6px;overflow:auto;">';
    echo '<h3 style="margin-top:0;">' . esc_html($title) . '</h3>';

    if (!$rows) {
        echo '<p>' . esc_html__( 'No hay datos para este período.', 'taxocommerce-google-bing' ) . '</p>' . '</div>';
        return;
    }

    echo '<table class="widefat striped">' . '<thead>' . '<tr>' . '<th>' . esc_html__( 'Elemento', 'taxocommerce-google-bing' ) . '</th>' . '<th>' . esc_html__( 'Imp.', 'taxocommerce-google-bing' ) . '</th>' . '<th>' . esc_html__( 'Clics', 'taxocommerce-google-bing' ) . '</th>' . '<th>' . esc_html__( 'CTR', 'taxocommerce-google-bing' ) . '</th>' . '<th>' . esc_html__( 'Pos.', 'taxocommerce-google-bing' ) . '</th>' . '</tr>' . '</thead>' . '<tbody>';

    foreach ($rows as $row) {
        $label = (string) $row['label'];
        echo '<tr><td style="max-width:360px;word-break:break-word;">';
        if ($is_url) {
            echo '<a href="' . esc_url($label) . '" target="_blank" rel="noopener noreferrer">' . esc_html($label) . '</a>';
        } else {
            echo esc_html($label);
        }
        echo '</td>';
        echo '<td>' . esc_html(number_format_i18n((float) $row['impressions'], 0)) . '</td>';
        echo '<td>' . esc_html(number_format_i18n((float) $row['clicks'], 0)) . '</td>';
        echo '<td>' . esc_html(number_format_i18n(((float) $row['ctr']) * 100, 2)) . '%</td>';
        echo '<td>' . esc_html(number_format_i18n((float) $row['position'], 1)) . '</td></tr>';
    }

    echo '</tbody></table></div>';
}

/**
 * Contexto de análisis basado en el último día realmente almacenado.
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
function seo_google_get_analysis_period($property_id, $days = 28) {
    $latest = seo_google_latest_data_date($property_id);
    if (!$latest) {
        return null;
    }

    $days = max(1, min(90, absint($days)));

    try {
        $date_to       = new DateTimeImmutable($latest);
        $date_from     = $date_to->modify('-' . ($days - 1) . ' days');
        $previous_to   = $date_from->modify('-1 day');
        $previous_from = $previous_to->modify('-' . ($days - 1) . ' days');
    } catch (Exception $exception) {
        return null;
    }

    return array(
        'days'          => $days,
        'current_from'  => $date_from->format('Y-m-d'),
        'current_to'    => $date_to->format('Y-m-d'),
        'previous_from' => $previous_from->format('Y-m-d'),
        'previous_to'   => $previous_to->format('Y-m-d'),
    );
}

/**
 * Comprueba conexión y datos antes de renderizar una vista analítica.
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
function seo_google_analysis_ready() {
    $settings = seo_google_get_settings();

    if ('connected' !== seo_google_connection_status()) {
        echo '<div class="notice notice-info inline">' . '<p>' . '<strong>' . esc_html__( 'Google Search Console todavía no está conectado completamente.', 'taxocommerce-google-bing' ) . '</strong>' . '</p>' . '</div>';
        echo '<p><a class="button button-primary" href="' . esc_url(seo_google_admin_url('settings')) . '">Configurar conexión</a></p>';
        return false;
    }

    if (!seo_google_latest_data_date($settings['property_id'])) {
        echo '<div class="notice notice-info inline">' . '<p>' . '<strong>' . esc_html__( 'Primero debes ejecutar la sincronización.', 'taxocommerce-google-bing' ) . '</strong>' . '</p>' . '</div>';
        echo '<p><a class="button button-primary" href="' . esc_url(seo_google_admin_url('sync')) . '">Sincronizar Search Console</a></p>';
        return false;
    }

    return true;
}

/**
 * Métricas de consultas con número de URLs y URL principal como evidencia.
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
function seo_google_get_signal_queries($property_id, $date_from, $date_to, $limit = 50, $min_impressions = 1, $search = '') {
    global $wpdb;

    $table = esc_sql( seo_google_table('search_data') );
    $where = "property_hash = %s AND data_date BETWEEN %s AND %s";
    $args  = array(hash('sha256', $property_id), $date_from, $date_to);

    if ('' !== $search) {
        $where .= ' AND query_text LIKE %s';
        $args[] = '%' . $wpdb->esc_like($search) . '%';
    }

    $args[] = max(0, (float) $min_impressions);
    $args[] = max(1, min(250, absint($limit)));

    $sql = "SELECT
                query_hash,
                MAX(query_text) AS label,
                SUM(clicks) AS clicks,
                SUM(impressions) AS impressions,
                COUNT(DISTINCT page_hash) AS pages,
                CASE WHEN SUM(impressions) > 0 THEN SUM(clicks) / SUM(impressions) ELSE 0 END AS ctr,
                CASE WHEN SUM(impressions) > 0 THEN SUM(position * impressions) / SUM(impressions) ELSE 0 END AS position
            FROM {$table}
            WHERE {$where}
            GROUP BY query_hash
            HAVING SUM(impressions) >= %f
            ORDER BY impressions DESC, clicks DESC
            LIMIT %d";

    // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Internal Search Console table and closed report fragments; all filter/range/limit values are bound through prepare(). Native live read/write of plugin-owned tables, retaining immediate reporting consistency; query values are prepared.
    $rows = $wpdb->get_results($wpdb->prepare($sql, $args), ARRAY_A);

    foreach ($rows as &$row) {
        $evidence = seo_google_get_query_page_evidence(
            $property_id,
            $row['query_hash'],
            $date_from,
            $date_to,
            4
        );
        $row['evidence'] = $evidence;
        $row['top_page'] = !empty($evidence[0]['page_url']) ? $evidence[0]['page_url'] : '';
    }
    unset($row);

    return $rows;
}

/**
 * Páginas que Google asocia a una consulta.
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
function seo_google_get_query_page_evidence($property_id, $query_hash, $date_from, $date_to, $limit = 4) {
    global $wpdb;

    $table = esc_sql( seo_google_table('search_data') );

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Native live read/write of plugin-owned tables, retaining immediate reporting consistency; query values are prepared.
    return $wpdb->get_results(
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Trusted internal table identifiers; generated value placeholders are bound using wpdb::prepare (WordPress 5.8 compatibility).
        $wpdb->prepare(
            "SELECT
                MAX(page_url) AS page_url,
                SUM(clicks) AS clicks,
                SUM(impressions) AS impressions,
                CASE WHEN SUM(impressions) > 0 THEN SUM(position * impressions) / SUM(impressions) ELSE 0 END AS position
             FROM {$table}
             WHERE property_hash = %s
               AND query_hash = %s
               AND data_date BETWEEN %s AND %s
             GROUP BY page_hash
             ORDER BY impressions DESC, clicks DESC
             LIMIT %d",
            hash('sha256', $property_id),
            $query_hash,
            $date_from,
            $date_to,
            max(1, min(10, absint($limit)))
        ),
        ARRAY_A
    );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
}

/**
 * Consultas principales asociadas a una página.
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
function seo_google_get_page_query_evidence($property_id, $page_hash, $date_from, $date_to, $limit = 5) {
    global $wpdb;

    $table = esc_sql( seo_google_table('search_data') );

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Native live read/write of plugin-owned tables, retaining immediate reporting consistency; query values are prepared.
    return $wpdb->get_results(
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Trusted internal table identifiers; generated value placeholders are bound using wpdb::prepare (WordPress 5.8 compatibility).
        $wpdb->prepare(
            "SELECT
                MAX(query_text) AS query_text,
                SUM(clicks) AS clicks,
                SUM(impressions) AS impressions,
                CASE WHEN SUM(impressions) > 0 THEN SUM(position * impressions) / SUM(impressions) ELSE 0 END AS position
             FROM {$table}
             WHERE property_hash = %s
               AND page_hash = %s
               AND data_date BETWEEN %s AND %s
             GROUP BY query_hash
             ORDER BY impressions DESC, clicks DESC
             LIMIT %d",
            hash('sha256', $property_id),
            $page_hash,
            $date_from,
            $date_to,
            max(1, min(10, absint($limit)))
        ),
        ARRAY_A
    );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
}

/**
 * Páginas principales con cantidad de consultas asociadas.
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
function seo_google_get_signal_pages($property_id, $date_from, $date_to, $limit = 30, $min_impressions = 1, $search = '') {
    global $wpdb;

    $page_source = seo_google_pages_period_complete($property_id, $date_from, $date_to);
    $table = esc_sql( seo_google_table($page_source ? 'search_pages' : 'search_data') );
    $where = "property_hash = %s AND data_date BETWEEN %s AND %s AND search_type = 'web'";
    $args  = array(hash('sha256', $property_id), $date_from, $date_to);

    if ('' !== $search) {
        $where .= ' AND page_url LIKE %s';
        $args[] = '%' . $wpdb->esc_like($search) . '%';
    }

    $args[] = max(0, (float) $min_impressions);
    $args[] = max(1, min(250, absint($limit)));

    $sql = "SELECT
                page_hash,
                MAX(page_url) AS label,
                SUM(clicks) AS clicks,
                SUM(impressions) AS impressions,
                " . ($page_source ? '0' : 'COUNT(DISTINCT query_hash)') . " AS queries,
                CASE WHEN SUM(impressions) > 0 THEN SUM(clicks) / SUM(impressions) ELSE 0 END AS ctr,
                CASE WHEN SUM(impressions) > 0 THEN SUM(position * impressions) / SUM(impressions) ELSE 0 END AS position
            FROM {$table}
            WHERE {$where}
            GROUP BY page_hash
            HAVING SUM(impressions) >= %f
            ORDER BY impressions DESC, clicks DESC
            LIMIT %d";

    // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Internal Search Console table and closed report fragments; all filter/range/limit values are bound through prepare(). Native live read/write of plugin-owned tables, retaining immediate reporting consistency; query values are prepared.
    $rows = $wpdb->get_results($wpdb->prepare($sql, $args), ARRAY_A);

    if ($page_source) {
        seo_google_attach_page_query_counts($rows, $property_id, $date_from, $date_to);
    }

    foreach ($rows as &$row) {
        $row['evidence'] = seo_google_get_page_query_evidence(
            $property_id,
            $row['page_hash'],
            $date_from,
            $date_to,
            5
        );
    }
    unset($row);

    return $rows;
}

/** Consulta+página solo aporta el número de consultas visibles, nunca los totales de la URL. */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
function seo_google_attach_page_query_counts(&$rows, $property_id, $date_from, $date_to) {
    global $wpdb;
    if (!$rows) return;
    $hashes = array_values(array_unique(array_filter(array_column($rows, 'page_hash'))));
    if (!$hashes) return;
    $table = esc_sql( seo_google_table('search_data') );
    $placeholders = implode(',', array_fill(0, count($hashes), '%s'));
    // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Trusted internal table identifiers; generated value placeholders are bound using wpdb::prepare (WordPress 5.8 compatibility).
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Native live read/write of plugin-owned tables, retaining immediate reporting consistency; query values are prepared.
    $counts = $wpdb->get_results($wpdb->prepare(
        "SELECT page_hash,COUNT(DISTINCT query_hash) AS queries FROM {$table}
         WHERE property_hash=%s AND search_type='web' AND data_date BETWEEN %s AND %s
           AND page_hash IN ({$placeholders}) GROUP BY page_hash",
        ...array_merge(array(hash('sha256', $property_id), $date_from, $date_to), $hashes)
    ), ARRAY_A);
    // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
    $by_hash = array();
    foreach ((array) $counts as $count) $by_hash[$count['page_hash']] = (int) $count['queries'];
    foreach ($rows as &$row) $row['queries'] = $by_hash[$row['page_hash']] ?? 0;
    unset($row);
}

/**
 * Pestaña Señales: datos agrupados y evidencias, sin dictar una conclusión.
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
function seo_google_render_signals() {
    if (!seo_google_analysis_ready()) {
        return;
    }

    $settings        = seo_google_get_settings();
    $days            = absint(seo_google_query_arg('signal_days', 28));
    $days            = in_array($days, array(7, 14, 28, 60, 90), true) ? $days : 28;
    $min_impressions = max(0, (float) sanitize_text_field((string) seo_google_query_arg('signal_min', '5')));
    $search          = sanitize_text_field((string) seo_google_query_arg('signal_search', ''));
    $period          = seo_google_get_analysis_period($settings['property_id'], $days);

    if (!$period) {
        echo '<div class="notice notice-error inline">' . '<p>' . esc_html__( 'No se pudo calcular el período de análisis.', 'taxocommerce-google-bing' ) . '</p>' . '</div>';
        return;
    }

    $queries = seo_google_get_signal_queries(
        $settings['property_id'],
        $period['current_from'],
        $period['current_to'],
        50,
        $min_impressions,
        $search
    );
    $pages = seo_google_get_signal_pages(
        $settings['property_id'],
        $period['current_from'],
        $period['current_to'],
        30,
        $min_impressions,
        ''
    );

    echo '<div style="background:#fff;border:1px solid #dcdcde;padding:18px;border-radius:6px;margin-bottom:20px;">';
    echo '<h3 style="margin-top:0;">' . esc_html__( 'Señales observadas por Google', 'taxocommerce-google-bing' ) . '</h3>';
    echo '<p>' . esc_html__( 'Esta vista agrupa consultas y páginas, pero no decide por ti si una señal es una oportunidad, un error histórico, una búsqueda comercial o una coincidencia accidental.', 'taxocommerce-google-bing' ) . '</p>';
    echo '<form method="get" style="display:flex;gap:12px;flex-wrap:wrap;align-items:end;">';
    echo '<input type="hidden" name="page" value="taxocommerce-google-bing"><input type="hidden" name="tab" value="google_intelligence"><input type="hidden" name="google_view" value="signals">';
    echo '<label>' . '<strong>' . esc_html__( 'Período', 'taxocommerce-google-bing' ) . '</strong>' . '<br>' . '<select name="signal_days">';
    foreach (array(7, 14, 28, 60, 90) as $option_days) {
        echo '<option value="' . absint($option_days) . '" ' . selected($days, $option_days, false) . '>' . absint($option_days) . ' días</option>';
    }
    echo '</select></label>';
    echo '<label><strong>Impresiones mínimas</strong><br><input type="number" min="0" step="1" name="signal_min" value="' . esc_attr($min_impressions) . '" style="width:130px;"></label>';
    echo '<label style="min-width:280px;"><strong>Buscar consulta</strong><br><input type="search" name="signal_search" value="' . esc_attr($search) . '" class="regular-text" placeholder="Ej.: carretillas"></label>';
    submit_button('Aplicar', 'secondary', 'submit', false);
    echo '</form>';
    echo '<p class="description"><code>' . esc_html($period['current_from']) . '</code> → <code>' . esc_html($period['current_to']) . '</code></p>';
    if (!seo_google_pages_period_complete($settings['property_id'], $period['current_from'], $period['current_to'])) {
        echo '<p class="notice notice-warning inline">' . esc_html__( 'Páginas parciales: faltan días sincronizados por URL. Las consultas visibles tampoco representan todas las búsquedas.', 'taxocommerce-google-bing' ) . '</p>';
    }
    echo '</div>';

    echo '<div style="background:#fff;border:1px solid #dcdcde;padding:18px;border-radius:6px;overflow:auto;">';
    echo '<h3 style="margin-top:0;">' . esc_html__( 'Consultas y páginas asociadas', 'taxocommerce-google-bing' ) . '</h3>';
    if (!$queries) {
        echo '<p>' . esc_html__( 'No hay consultas que cumplan los filtros.', 'taxocommerce-google-bing' ) . '</p>';
    } else {
        echo '<table class="widefat striped">' . '<thead>' . '<tr>' . '<th>' . esc_html__( 'Consulta', 'taxocommerce-google-bing' ) . '</th>' . '<th>' . esc_html__( 'Imp.', 'taxocommerce-google-bing' ) . '</th>' . '<th>' . esc_html__( 'Clics', 'taxocommerce-google-bing' ) . '</th>' . '<th>' . esc_html__( 'CTR', 'taxocommerce-google-bing' ) . '</th>' . '<th>' . esc_html__( 'Pos.', 'taxocommerce-google-bing' ) . '</th>' . '<th>' . esc_html__( 'URLs', 'taxocommerce-google-bing' ) . '</th>' . '<th>' . esc_html__( 'Evidencias', 'taxocommerce-google-bing' ) . '</th>' . '</tr>' . '</thead>' . '<tbody>';
        foreach ($queries as $row) {
            echo '<tr>';
            echo '<td><strong>' . esc_html($row['label']) . '</strong></td>';
            echo '<td>' . esc_html(number_format_i18n((float) $row['impressions'], 0)) . '</td>';
            echo '<td>' . esc_html(number_format_i18n((float) $row['clicks'], 0)) . '</td>';
            echo '<td>' . esc_html(number_format_i18n(((float) $row['ctr']) * 100, 2)) . '%</td>';
            echo '<td>' . esc_html(number_format_i18n((float) $row['position'], 1)) . '</td>';
            echo '<td>' . esc_html(number_format_i18n(absint($row['pages']))) . '</td>';
            echo '<td style="min-width:360px;">';
            if (!empty($row['evidence'])) {
                echo '<details>' . '<summary>' . esc_html__( 'Ver URLs relacionadas', 'taxocommerce-google-bing' ) . '</summary>' . '<ul style="margin:8px 0 0 18px;">';
                foreach ($row['evidence'] as $evidence) {
                    echo '<li style="margin-bottom:7px;"><a href="' . esc_url($evidence['page_url']) . '" target="_blank" rel="noopener noreferrer">' . esc_html($evidence['page_url']) . '</a><br><small>';
                    echo esc_html(number_format_i18n((float) $evidence['impressions'], 0)) . ' imp. · ';
                    echo esc_html(number_format_i18n((float) $evidence['clicks'], 0)) . ' clics · pos. ';
                    echo esc_html(number_format_i18n((float) $evidence['position'], 1)) . '</small></li>';
                }
                echo '</ul></details>';
            } else {
                echo '—';
            }
            echo '</td></tr>';
        }
        echo '</tbody></table>';
    }
    echo '</div>';

    echo '<div style="background:#fff;border:1px solid #dcdcde;padding:18px;border-radius:6px;overflow:auto;margin-top:20px;">';
    echo '<h3 style="margin-top:0;">' . esc_html__( 'Páginas y consultas asociadas', 'taxocommerce-google-bing' ) . '</h3>';
    if (!$pages) {
        echo '<p>' . esc_html__( 'No hay páginas que cumplan los filtros.', 'taxocommerce-google-bing' ) . '</p>';
    } else {
        echo '<table class="widefat striped">' . '<thead>' . '<tr>' . '<th>' . esc_html__( 'URL', 'taxocommerce-google-bing' ) . '</th>' . '<th>' . esc_html__( 'Imp.', 'taxocommerce-google-bing' ) . '</th>' . '<th>' . esc_html__( 'Clics', 'taxocommerce-google-bing' ) . '</th>' . '<th>' . esc_html__( 'Pos.', 'taxocommerce-google-bing' ) . '</th>' . '<th>' . esc_html__( 'Consultas', 'taxocommerce-google-bing' ) . '</th>' . '<th>' . esc_html__( 'Evidencias', 'taxocommerce-google-bing' ) . '</th>' . '</tr>' . '</thead>' . '<tbody>';
        foreach ($pages as $row) {
            echo '<tr><td style="max-width:420px;word-break:break-word;"><a href="' . esc_url($row['label']) . '" target="_blank" rel="noopener noreferrer">' . esc_html($row['label']) . '</a></td>';
            echo '<td>' . esc_html(number_format_i18n((float) $row['impressions'], 0)) . '</td>';
            echo '<td>' . esc_html(number_format_i18n((float) $row['clicks'], 0)) . '</td>';
            echo '<td>' . esc_html(number_format_i18n((float) $row['position'], 1)) . '</td>';
            echo '<td>' . esc_html(number_format_i18n(absint($row['queries']))) . '</td><td style="min-width:280px;">';
            if (!empty($row['evidence'])) {
                echo '<details>' . '<summary>' . esc_html__( 'Ver consultas', 'taxocommerce-google-bing' ) . '</summary>' . '<ul style="margin:8px 0 0 18px;">';
                foreach ($row['evidence'] as $evidence) {
                    echo '<li><strong>' . esc_html($evidence['query_text']) . '</strong> — ' . esc_html(number_format_i18n((float) $evidence['impressions'], 0)) . ' imp. · pos. ' . esc_html(number_format_i18n((float) $evidence['position'], 1)) . '</li>';
                }
                echo '</ul></details>';
            } else {
                echo '—';
            }
            echo '</td></tr>';
        }
        echo '</tbody></table>';
    }
    echo '</div>';
}

/**
 * Cambios de consultas o páginas entre dos períodos equivalentes.
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
function seo_google_get_dimension_changes($property_id, $dimension, array $period, $min_impressions = 3, $limit = 25) {
    global $wpdb;

    $dimension = ('page' === $dimension) ? 'page' : 'query';
    $complete_pages = 'page' === $dimension
        && seo_google_pages_period_complete($property_id, $period['previous_from'], $period['previous_to'])
        && seo_google_pages_period_complete($property_id, $period['current_from'], $period['current_to']);
    $table = esc_sql( seo_google_table($complete_pages ? 'search_pages' : 'search_data') );
    $hash_field = ('page' === $dimension) ? 'page_hash' : 'query_hash';
    $text_field = ('page' === $dimension) ? 'page_url' : 'query_text';

    $sql = "SELECT
                {$hash_field} AS item_hash,
                MAX({$text_field}) AS label,
                SUM(CASE WHEN data_date BETWEEN %s AND %s THEN impressions ELSE 0 END) AS current_impressions,
                SUM(CASE WHEN data_date BETWEEN %s AND %s THEN impressions ELSE 0 END) AS previous_impressions,
                SUM(CASE WHEN data_date BETWEEN %s AND %s THEN clicks ELSE 0 END) AS current_clicks,
                SUM(CASE WHEN data_date BETWEEN %s AND %s THEN clicks ELSE 0 END) AS previous_clicks,
                CASE WHEN SUM(CASE WHEN data_date BETWEEN %s AND %s THEN impressions ELSE 0 END) > 0
                    THEN SUM(CASE WHEN data_date BETWEEN %s AND %s THEN position * impressions ELSE 0 END)
                       / SUM(CASE WHEN data_date BETWEEN %s AND %s THEN impressions ELSE 0 END)
                    ELSE 0 END AS current_position,
                CASE WHEN SUM(CASE WHEN data_date BETWEEN %s AND %s THEN impressions ELSE 0 END) > 0
                    THEN SUM(CASE WHEN data_date BETWEEN %s AND %s THEN position * impressions ELSE 0 END)
                       / SUM(CASE WHEN data_date BETWEEN %s AND %s THEN impressions ELSE 0 END)
                    ELSE 0 END AS previous_position
            FROM {$table}
            WHERE property_hash = %s AND search_type = 'web'
              AND data_date BETWEEN %s AND %s
            GROUP BY {$hash_field}
            HAVING current_impressions >= %f OR previous_impressions >= %f";

    $args = array(
        $period['current_from'], $period['current_to'],
        $period['previous_from'], $period['previous_to'],
        $period['current_from'], $period['current_to'],
        $period['previous_from'], $period['previous_to'],
        $period['current_from'], $period['current_to'],
        $period['current_from'], $period['current_to'],
        $period['current_from'], $period['current_to'],
        $period['previous_from'], $period['previous_to'],
        $period['previous_from'], $period['previous_to'],
        $period['previous_from'], $period['previous_to'],
        hash('sha256', $property_id),
        $period['previous_from'], $period['current_to'],
        max(0, (float) $min_impressions),
        max(0, (float) $min_impressions),
    );

    // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Internal Search Console table and closed report fragments; all filter/range/limit values are bound through prepare(). Native live read/write of plugin-owned tables, retaining immediate reporting consistency; query values are prepared.
    $rows = $wpdb->get_results($wpdb->prepare($sql, $args), ARRAY_A);
    $groups = array('new' => array(), 'lost' => array(), 'growth' => array(), 'decline' => array());

    foreach ($rows as $row) {
        $current  = (float) $row['current_impressions'];
        $previous = (float) $row['previous_impressions'];
        $row['delta'] = $current - $previous;
        $row['delta_percent'] = $previous > 0 ? (($current - $previous) / $previous) * 100 : null;

        if ($current > 0 && $previous <= 0) {
            $groups['new'][] = $row;
        } elseif ($previous > 0 && $current <= 0) {
            $groups['lost'][] = $row;
        } elseif ($row['delta'] > 0) {
            $groups['growth'][] = $row;
        } elseif ($row['delta'] < 0) {
            $groups['decline'][] = $row;
        }
    }

    usort($groups['new'], function ($a, $b) { return $b['current_impressions'] <=> $a['current_impressions']; });
    usort($groups['lost'], function ($a, $b) { return $b['previous_impressions'] <=> $a['previous_impressions']; });
    usort($groups['growth'], function ($a, $b) { return $b['delta'] <=> $a['delta']; });
    usort($groups['decline'], function ($a, $b) { return $a['delta'] <=> $b['delta']; });

    foreach ($groups as $key => $items) {
        $groups[$key] = array_slice($items, 0, max(1, min(100, absint($limit))));
    }

    return $groups;
}

/**
 * Pestaña Cambios: comparación neutral entre períodos equivalentes.
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
function seo_google_render_changes() {
    if (!seo_google_analysis_ready()) {
        return;
    }

    $settings = seo_google_get_settings();
    $days = absint(seo_google_query_arg('change_days', 28));
    $days = in_array($days, array(7, 14, 28), true) ? $days : 28;
    $min  = max(0, (float) sanitize_text_field((string) seo_google_query_arg('change_min', '3')));
    $period = seo_google_get_analysis_period($settings['property_id'], $days);

    if (!$period) {
        echo '<div class="notice notice-error inline">' . '<p>' . esc_html__( 'No se pudo calcular el período de comparación.', 'taxocommerce-google-bing' ) . '</p>' . '</div>';
        return;
    }

    $query_changes = seo_google_get_dimension_changes($settings['property_id'], 'query', $period, $min, 25);
    $page_changes  = seo_google_get_dimension_changes($settings['property_id'], 'page', $period, $min, 20);

    echo '<div style="background:#fff;border:1px solid #dcdcde;padding:18px;border-radius:6px;margin-bottom:20px;">';
    echo '<h3 style="margin-top:0;">' . esc_html__( 'Cambios entre períodos equivalentes', 'taxocommerce-google-bing' ) . '</h3>';
    echo '<p>' . esc_html__( 'Esta vista señala aumentos, descensos, apariciones y desapariciones. Un cambio no implica por sí solo que exista un problema o una oportunidad.', 'taxocommerce-google-bing' ) . '</p>';
    echo '<form method="get" style="display:flex;gap:12px;flex-wrap:wrap;align-items:end;">';
    echo '<input type="hidden" name="page" value="taxocommerce-google-bing"><input type="hidden" name="tab" value="google_intelligence"><input type="hidden" name="google_view" value="changes">';
    echo '<label>' . '<strong>' . esc_html__( 'Duración de cada período', 'taxocommerce-google-bing' ) . '</strong>' . '<br>' . '<select name="change_days">';
    foreach (array(7, 14, 28) as $option_days) {
        echo '<option value="' . absint($option_days) . '" ' . selected($days, $option_days, false) . '>' . absint($option_days) . ' días</option>';
    }
    echo '</select></label>';
    echo '<label><strong>Impresiones mínimas</strong><br><input type="number" min="0" step="1" name="change_min" value="' . esc_attr($min) . '"></label>';
    submit_button('Comparar', 'secondary', 'submit', false);
    echo '</form>';
    echo '<p><strong>Actual:</strong> <code>' . esc_html($period['current_from']) . '</code> → <code>' . esc_html($period['current_to']) . '</code><br>';
    echo '<strong>Anterior:</strong> <code>' . esc_html($period['previous_from']) . '</code> → <code>' . esc_html($period['previous_to']) . '</code></p>';
    if (!seo_google_pages_period_complete($settings['property_id'], $period['previous_from'], $period['previous_to'])
        || !seo_google_pages_period_complete($settings['property_id'], $period['current_from'], $period['current_to'])) {
        echo '<p class="notice notice-warning inline">' . esc_html__( 'Los cambios por página proceden de consultas visibles y son parciales hasta completar la sincronización de ambos períodos.', 'taxocommerce-google-bing' ) . '</p>';
    }
    echo '</div>';

    seo_google_render_change_sections('Consultas', $query_changes, false);
    echo '<div style="height:20px;"></div>';
    seo_google_render_change_sections('Páginas', $page_changes, true);
}

/**
 * Renderiza bloques de cambios sin convertirlos en recomendaciones.
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
function seo_google_render_change_sections($title, array $groups, $is_url) {
    $labels = array(
        'new'     => 'Aparecen en el período actual',
        'growth'  => 'Aumentan impresiones',
        'decline' => 'Disminuyen impresiones',
        'lost'    => 'No aparecen en el período actual',
    );

    echo '<section><h3>' . esc_html($title) . '</h3>';
    echo '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(460px,1fr));gap:18px;">';
    foreach ($labels as $key => $label) {
        echo '<div style="background:#fff;border:1px solid #dcdcde;padding:16px;border-radius:6px;overflow:auto;">';
        echo '<h4 style="margin-top:0;">' . esc_html($label) . '</h4>';
        if (empty($groups[$key])) {
            echo '<p>' . esc_html__( 'No hay elementos con los filtros actuales.', 'taxocommerce-google-bing' ) . '</p>' . '</div>';
            continue;
        }
        echo '<table class="widefat striped">' . '<thead>' . '<tr>' . '<th>' . esc_html__( 'Elemento', 'taxocommerce-google-bing' ) . '</th>' . '<th>' . esc_html__( 'Antes', 'taxocommerce-google-bing' ) . '</th>' . '<th>' . esc_html__( 'Ahora', 'taxocommerce-google-bing' ) . '</th>' . '<th>' . 'Δ' . '</th>' . '<th>' . esc_html__( 'Pos. antes', 'taxocommerce-google-bing' ) . '</th>' . '<th>' . esc_html__( 'Pos. ahora', 'taxocommerce-google-bing' ) . '</th>' . '</tr>' . '</thead>' . '<tbody>';
        foreach ($groups[$key] as $row) {
            $label_text = (string) $row['label'];
            echo '<tr><td style="max-width:360px;word-break:break-word;">';
            if ($is_url) {
                echo '<a href="' . esc_url($label_text) . '" target="_blank" rel="noopener noreferrer">' . esc_html($label_text) . '</a>';
            } else {
                echo esc_html($label_text);
            }
            echo '</td><td>' . esc_html(number_format_i18n((float) $row['previous_impressions'], 0)) . '</td>';
            echo '<td>' . esc_html(number_format_i18n((float) $row['current_impressions'], 0)) . '</td>';
            $delta = (float) $row['delta'];
            echo '<td>' . ($delta > 0 ? '+' : '') . esc_html(number_format_i18n($delta, 0)) . '</td>';
            echo '<td>' . ((float) $row['previous_position'] > 0 ? esc_html(number_format_i18n((float) $row['previous_position'], 1)) : '—') . '</td>';
            echo '<td>' . ((float) $row['current_position'] > 0 ? esc_html(number_format_i18n((float) $row['current_position'], 1)) : '—') . '</td></tr>';
        }
        echo '</tbody></table></div>';
    }
    echo '</div></section>';
}

/**
 * Normaliza URLs para compararlas con entidades locales.
 *
 * Se ignoran parámetros y fragmentos porque WordPress identifica la entidad
 * por la ruta pública. La URL original sí se conserva al comprobar redirects.
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
function seo_google_normalize_url($url) {
    $url = trim((string) $url);
    if ('' === $url) {
        return '';
    }

    $parts = wp_parse_url($url);
    if (!$parts || empty($parts['host'])) {
        return untrailingslashit($url);
    }

    $scheme = !empty($parts['scheme']) ? strtolower($parts['scheme']) : 'https';
    $host   = strtolower($parts['host']);
    $port   = !empty($parts['port']) ? ':' . absint($parts['port']) : '';
    $path   = isset($parts['path']) ? '/' . ltrim($parts['path'], '/') : '/';

    return untrailingslashit($scheme . '://' . $host . $port . $path);
}

/**
 * Normaliza una URL completa para detectar bucles durante una redirección.
 *
 * A diferencia de seo_google_normalize_url(), conserva la consulta porque
 * algunos redirects dependen de sus parámetros.
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
function seo_google_redirect_loop_key($url) {
    $url = trim((string) $url);

    if ('' === $url) {
        return '';
    }

    $parts = wp_parse_url($url);

    if (!$parts || empty($parts['host'])) {
        return strtolower(preg_replace('/#.*$/', '', $url));
    }

    $scheme = !empty($parts['scheme']) ? strtolower($parts['scheme']) : 'https';
    $host   = strtolower($parts['host']);
    $port   = !empty($parts['port']) ? ':' . absint($parts['port']) : '';
    $path   = isset($parts['path']) ? '/' . ltrim($parts['path'], '/') : '/';
    $query  = isset($parts['query']) && '' !== $parts['query'] ? '?' . $parts['query'] : '';

    return $scheme . '://' . $host . $port . $path . $query;
}

/**
 * Convierte el destino de una cabecera Location en una URL absoluta.
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
function seo_google_redirect_absolute_url($base_url, $location) {
    $location = html_entity_decode(
        trim((string) $location),
        ENT_QUOTES | ENT_HTML5,
        'UTF-8'
    );

    if ('' === $location || '#' === substr($location, 0, 1)) {
        return '';
    }

    if (preg_match('#^https?://#i', $location)) {
        return esc_url_raw($location);
    }

    $base = wp_parse_url($base_url);

    if (!$base || empty($base['host'])) {
        return '';
    }

    $scheme = !empty($base['scheme']) ? strtolower($base['scheme']) : 'https';
    $host   = strtolower($base['host']);
    $port   = !empty($base['port']) ? ':' . absint($base['port']) : '';

    if (0 === strpos($location, '//')) {
        return esc_url_raw($scheme . ':' . $location);
    }

    $authority = $scheme . '://' . $host . $port;
    $base_path = isset($base['path']) && '' !== $base['path']
        ? $base['path']
        : '/';

    if ('/' === substr($location, 0, 1)) {
        $target = $location;
    } elseif ('?' === substr($location, 0, 1)) {
        $target = $base_path . $location;
    } else {
        $directory = preg_replace('#/[^/]*$#', '/', $base_path);
        $target    = $directory . $location;
    }

    $query = '';
    $query_position = strpos($target, '?');

    if (false !== $query_position) {
        $query  = substr($target, $query_position);
        $target = substr($target, 0, $query_position);
    }

    $segments = array();

    foreach (explode('/', $target) as $segment) {
        if ('' === $segment || '.' === $segment) {
            continue;
        }

        if ('..' === $segment) {
            array_pop($segments);
            continue;
        }

        $segments[] = $segment;
    }

    $path = '/' . implode('/', $segments);

    return esc_url_raw($authority . $path . $query);
}

/**
 * Extrae una redirección HTTP normal o una cabecera Refresh inmediata.
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
function seo_google_redirect_location_from_response($response) {
    if (is_wp_error($response)) {
        return '';
    }

    $location = wp_remote_retrieve_header($response, 'location');

    if (is_array($location)) {
        $location = end($location);
    }

    $location = trim((string) $location);

    if ('' !== $location) {
        return $location;
    }

    $refresh = wp_remote_retrieve_header($response, 'refresh');

    if (is_array($refresh)) {
        $refresh = end($refresh);
    }

    if (
        is_string($refresh)
        && preg_match('/^\s*(\d+)\s*;\s*url\s*=\s*[\'"]?(.+?)[\'"]?\s*$/i', $refresh, $matches)
        && 1 >= absint($matches[1])
    ) {
        return trim($matches[2]);
    }

    return '';
}

/**
 * Consulta una URL como lo haría un navegador, sin seguir redirects
 * automáticamente. GET solo se usa si el servidor rechaza HEAD.
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
function seo_google_redirect_http_response($url) {
    $args = array(
        'timeout'     => 4,
        'redirection' => 0,
        'headers'     => array(
            'Accept'     => 'text/html,application/xhtml+xml;q=0.9,*/*;q=0.1',
            'User-Agent' => 'SEO-Google-Intelligence/' . SEO_GOOGLE_MODULE_VERSION . ' ' . home_url('/'),
        ),
    );

    $response = wp_safe_remote_head($url, $args);

    if (!is_wp_error($response)) {
        $status = absint(wp_remote_retrieve_response_code($response));

        if (!in_array($status, array(403, 405, 501), true)) {
            return $response;
        }
    }

    $args['limit_response_size'] = 2048;

    return wp_safe_remote_get($url, $args);
}

/**
 * Sigue una cadena de redirects sin abandonar la protección SSRF de
 * wp_safe_remote_* y almacena el resultado para no castigar al servidor.
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
function seo_google_follow_redirects($url, $max_hops = 5) {
    $source_url = esc_url_raw(preg_replace('/#.*$/', '', trim((string) $url)));
    $max_hops   = max(1, min(10, absint($max_hops)));

    $empty = array(
        'redirected'   => false,
        'source_url'   => $source_url,
        'final_url'    => $source_url,
        'final_status' => 0,
        'chain'        => array(),
        'error'        => '',
    );

    if ('' === $source_url || !preg_match('#^https?://#i', $source_url)) {
        $empty['error'] = 'La URL no es HTTP/HTTPS.';
        return $empty;
    }

    $cache_key = 'seo_google_redirect_' . md5(seo_google_redirect_loop_key($source_url));
    $cached    = get_transient($cache_key);

    if (is_array($cached) && isset($cached['final_url'], $cached['chain'])) {
        return wp_parse_args($cached, $empty);
    }

    $result  = $empty;
    $current = $source_url;
    $visited = array();

    for ($hop = 0; $hop <= $max_hops; $hop++) {
        $loop_key = seo_google_redirect_loop_key($current);

        if ('' === $loop_key || isset($visited[$loop_key])) {
            $result['error'] = 'Se detectó un bucle de redirección.';
            break;
        }

        $visited[$loop_key] = true;
        $response = seo_google_redirect_http_response($current);

        if (is_wp_error($response)) {
            $result['error'] = $response->get_error_message();
            break;
        }

        $status   = absint(wp_remote_retrieve_response_code($response));
        $location = seo_google_redirect_location_from_response($response);

        $result['final_url']    = $current;
        $result['final_status'] = $status;

        $is_http_redirect = 300 <= $status && 400 > $status;
        $refresh_header   = wp_remote_retrieve_header($response, 'refresh');

        if ('' === $location || (!$is_http_redirect && empty($refresh_header))) {
            break;
        }

        if ($hop >= $max_hops) {
            $result['error'] = sprintf(
                'La cadena supera el límite de %d redirecciones.',
                $max_hops
            );
            break;
        }

        $next = seo_google_redirect_absolute_url($current, $location);

        if ('' === $next) {
            $result['error'] = 'El servidor devolvió un destino de redirección no válido.';
            break;
        }

        $result['chain'][] = array(
            'url'      => $current,
            'status'   => $status,
            'location' => $next,
        );
        $result['redirected'] = true;
        $current              = $next;
    }

    $ttl = '' === $result['error']
        ? 12 * HOUR_IN_SECONDS
        : 15 * MINUTE_IN_SECONDS;

    set_transient($cache_key, $result, $ttl);

    return $result;
}

/**
 * Resume los códigos HTTP de una cadena de redirecciones.
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
function seo_google_redirect_status_summary($redirect) {
    $codes = array();

    foreach ((array) ($redirect['chain'] ?? array()) as $hop) {
        $code = absint($hop['status'] ?? 0);

        if (0 < $code) {
            $codes[] = $code;
        }
    }

    $final_status = absint($redirect['final_status'] ?? 0);

    if (0 < $final_status) {
        $codes[] = $final_status;
    }

    return implode(' → ', $codes);
}

/**
 * Construye un mapa de categorías de producto por URL.
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
function seo_google_get_product_category_url_map() {
    static $map = null;

    if (null !== $map) {
        return $map;
    }

    $map = array();
    if (!taxonomy_exists('product_cat')) {
        return $map;
    }

    $terms = get_terms(array('taxonomy' => 'product_cat', 'hide_empty' => false));
    if (is_wp_error($terms)) {
        return $map;
    }

    foreach ($terms as $term) {
        $link = get_term_link($term);
        if (!is_wp_error($link)) {
            $map[seo_google_normalize_url($link)] = $term;
        }
    }

    return $map;
}

/**
 * Resuelve una URL directamente contra WordPress/WooCommerce, sin red.
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
function seo_google_resolve_local_entity_direct($url) {
    $normalized = seo_google_normalize_url($url);
    $home       = seo_google_normalize_url(home_url('/'));

    if ($normalized === $home) {
        return array(
            'recognized' => true,
            'type'       => 'Inicio',
            'title'      => get_bloginfo('name'),
            'status'     => 'publicada',
            'id'         => 0,
        );
    }

    $post_id = url_to_postid($url);

    if ($post_id) {
        $post_type = get_post_type($post_id);
        $object    = get_post_type_object($post_type);

        return array(
            'recognized' => true,
            'type'       => $object && !empty($object->labels->singular_name)
                ? $object->labels->singular_name
                : $post_type,
            'title'      => get_the_title($post_id),
            'status'     => get_post_status($post_id),
            'id'         => $post_id,
        );
    }

    $term_map = seo_google_get_product_category_url_map();

    if (isset($term_map[$normalized])) {
        $term = $term_map[$normalized];

        return array(
            'recognized' => true,
            'type'       => 'Categoría de producto',
            'title'      => $term->name,
            'status'     => 'activa',
            'id'         => $term->term_id,
        );
    }

    return array(
        'recognized' => false,
        'type'       => 'No reconocida',
        'title'      => '',
        'status'     => 'No coincide directamente con una entidad local.',
        'id'         => 0,
    );
}

/**
 * Intenta resolver una URL de Google contra WordPress/WooCommerce.
 *
 * Si la URL no existe directamente, comprueba sus redirects HTTP y vuelve a
 * resolver el destino final. Así una URL histórica de Search Console puede
 * relacionarse con el producto, página o categoría vigente.
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
function seo_google_resolve_local_entity($url) {
    $entity = seo_google_resolve_local_entity_direct($url);

    $entity['redirected']     = false;
    $entity['source_url']     = (string) $url;
    $entity['resolved_url']   = (string) $url;
    $entity['redirect_chain'] = array();
    $entity['http_summary']   = '';

    if (!empty($entity['recognized'])) {
        return $entity;
    }

    $redirect = seo_google_follow_redirects($url, 5);

    $entity['resolved_url']   = (string) ($redirect['final_url'] ?? $url);
    $entity['redirect_chain'] = (array) ($redirect['chain'] ?? array());
    $entity['http_summary']   = seo_google_redirect_status_summary($redirect);

    if (empty($redirect['redirected'])) {
        $status = absint($redirect['final_status'] ?? 0);

        if (!empty($redirect['error'])) {
            $entity['status'] = 'No reconocida. No se pudo comprobar el redirect: ' . $redirect['error'];
        } elseif (0 < $status) {
            $entity['status'] = sprintf(
                'No reconocida. La URL respondió HTTP %d sin indicar un destino.',
                $status
            );
        }

        return $entity;
    }

    $entity['redirected'] = true;
    $destination          = seo_google_resolve_local_entity_direct($entity['resolved_url']);

    $destination['redirected']     = true;
    $destination['source_url']     = (string) $url;
    $destination['resolved_url']   = $entity['resolved_url'];
    $destination['redirect_chain'] = $entity['redirect_chain'];
    $destination['http_summary']   = $entity['http_summary'];

    if (!empty($destination['recognized'])) {
        $destination['status'] = sprintf(
            'Relacionada mediante redirect%s · %s',
            '' !== $destination['http_summary']
                ? ' HTTP ' . $destination['http_summary']
                : '',
            $destination['status']
        );

        return $destination;
    }

    $destination['status'] = sprintf(
        'Redirect detectado%s, pero el destino «%s» tampoco coincide con una entidad local.%s',
        '' !== $destination['http_summary']
            ? ' (' . $destination['http_summary'] . ')'
            : '',
        $destination['resolved_url'],
        !empty($redirect['error'])
            ? ' ' . $redirect['error']
            : ''
    );

    return $destination;
}

/**
 * Google vs catálogo: correspondencia básica y verificable con WordPress.
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
function seo_google_render_comparison() {
    if (!seo_google_analysis_ready()) {
        return;
    }

    $settings = seo_google_get_settings();
    $period   = seo_google_get_analysis_period($settings['property_id'], 28);
    $pages    = seo_google_get_signal_pages($settings['property_id'], $period['current_from'], $period['current_to'], 100, 1, '');

    $recognized   = 0;
    $redirected   = 0;
    $unrecognized = 0;

    foreach ($pages as &$page) {
        $page['entity'] = seo_google_resolve_local_entity($page['label']);

        if (!empty($page['entity']['recognized'])) {
            $recognized++;

            if (!empty($page['entity']['redirected'])) {
                $redirected++;
            }
        } else {
            $unrecognized++;
        }
    }
    unset($page);

    echo '<div style="background:#fff;border:1px solid #dcdcde;padding:18px;border-radius:6px;margin-bottom:20px;">';
    echo '<h3 style="margin-top:0;">' . esc_html__( 'Google vs. catálogo', 'taxocommerce-google-bing' ) . '</h3>';
    echo '<p>' . esc_html__( 'Relaciona las URLs vistas por Google con entidades locales de WordPress/WooCommerce. Cuando una URL no existe directamente, se comprueba su cadena HTTP y se intenta relacionar el destino final.', 'taxocommerce-google-bing' ) . '</p>';
    echo '<div style="display:flex;gap:14px;flex-wrap:wrap;">';
    echo '<div style="border:1px solid #dcdcde;padding:12px 18px;border-radius:6px;"><small>Reconocidas</small><br><strong style="font-size:24px;">' . esc_html(number_format_i18n($recognized)) . '</strong></div>';
    echo '<div style="border:1px solid #dcdcde;padding:12px 18px;border-radius:6px;"><small>Vía redirect</small><br><strong style="font-size:24px;">' . esc_html(number_format_i18n($redirected)) . '</strong></div>';
    echo '<div style="border:1px solid #dcdcde;padding:12px 18px;border-radius:6px;"><small>No reconocidas</small><br><strong style="font-size:24px;">' . esc_html(number_format_i18n($unrecognized)) . '</strong></div>';
    echo '</div><p class="description">Se analizan las 100 páginas principales del período <code>' . esc_html($period['current_from']) . '</code> → <code>' . esc_html($period['current_to']) . '</code>. Los redirects comprobados se guardan durante 12 horas para evitar peticiones repetidas.</p>';
    echo '</div>';

    echo '<div style="background:#fff;border:1px solid #dcdcde;padding:18px;border-radius:6px;overflow:auto;">';
    echo '<table class="widefat striped">' . '<thead>' . '<tr>' . '<th>' . esc_html__( 'URL de Google / destino', 'taxocommerce-google-bing' ) . '</th>' . '<th>' . esc_html__( 'Imp.', 'taxocommerce-google-bing' ) . '</th>' . '<th>' . esc_html__( 'Clics', 'taxocommerce-google-bing' ) . '</th>' . '<th>' . esc_html__( 'Pos.', 'taxocommerce-google-bing' ) . '</th>' . '<th>' . esc_html__( 'Entidad local', 'taxocommerce-google-bing' ) . '</th>' . '<th>' . esc_html__( 'Estado', 'taxocommerce-google-bing' ) . '</th>' . '<th>' . esc_html__( 'Consultas', 'taxocommerce-google-bing' ) . '</th>' . '</tr>' . '</thead>' . '<tbody>';

    foreach ($pages as $page) {
        $entity = $page['entity'];

        echo '<tr><td style="max-width:430px;word-break:break-word;"><a href="' . esc_url($page['label']) . '" target="_blank" rel="noopener noreferrer">' . esc_html($page['label']) . '</a>';

        if (
            !empty($entity['redirected'])
            && !empty($entity['resolved_url'])
            && seo_google_redirect_loop_key($entity['resolved_url']) !== seo_google_redirect_loop_key($page['label'])
        ) {
            echo '<br><small><strong>Destino:</strong> <a href="' . esc_url($entity['resolved_url']) . '" target="_blank" rel="noopener noreferrer">' . esc_html($entity['resolved_url']) . '</a>';

            if (!empty($entity['http_summary'])) {
                echo ' <code>' . esc_html($entity['http_summary']) . '</code>';
            }

            echo '</small>';
        }

        echo '</td>';
        echo '<td>' . esc_html(number_format_i18n((float) $page['impressions'], 0)) . '</td>';
        echo '<td>' . esc_html(number_format_i18n((float) $page['clicks'], 0)) . '</td>';
        echo '<td>' . esc_html(number_format_i18n((float) $page['position'], 1)) . '</td>';
        echo '<td><strong>' . esc_html($entity['type']) . '</strong>';

        if (!empty($entity['redirected']) && !empty($entity['recognized'])) {
            echo '<br>' . '<small>' . esc_html__( 'Relacionada por redirección', 'taxocommerce-google-bing' ) . '</small>';
        }

        if ($entity['title']) {
            echo '<br>' . esc_html($entity['title']);
        }

        echo '</td><td>' . esc_html($entity['status']) . '</td>';
        echo '<td>' . esc_html(number_format_i18n(absint($page['queries']))) . '</td></tr>';
    }

    echo '</tbody></table></div>';
}

/**
 * Métricas de páginas para calcular cobertura por rutas.
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
function seo_google_get_all_page_metrics($property_id, $date_from, $date_to, $limit = 5000) {
    global $wpdb;

    $page_source = seo_google_pages_period_complete($property_id, $date_from, $date_to);
    $table = esc_sql( seo_google_table($page_source ? 'search_pages' : 'search_data') );

    if ($page_source) {
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Internal GSC table; property, dates and limit are bound through $wpdb->prepare().
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Trusted internal table identifiers; generated value placeholders are bound using wpdb::prepare (WordPress 5.8 compatibility).
        $prepared_sql = $wpdb->prepare(
            "SELECT page_hash,MAX(page_url) AS page_url,SUM(clicks) AS clicks,SUM(impressions) AS impressions,0 AS queries,
                    CASE WHEN SUM(impressions)>0 THEN SUM(position*impressions)/SUM(impressions) ELSE 0 END AS position
             FROM {$table}
             WHERE property_hash=%s AND search_type='web' AND data_date BETWEEN %s AND %s
             GROUP BY page_hash ORDER BY impressions DESC LIMIT %d",
            hash('sha256',$property_id),$date_from,$date_to,max(1,min(10000,absint($limit)))
        );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
    } else {
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Internal GSC table; property, dates and limit are bound through $wpdb->prepare().
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Trusted internal table identifiers; generated value placeholders are bound using wpdb::prepare (WordPress 5.8 compatibility).
        $prepared_sql = $wpdb->prepare(
            "SELECT page_hash,MAX(page_url) AS page_url,SUM(clicks) AS clicks,SUM(impressions) AS impressions,COUNT(DISTINCT query_hash) AS queries,
                    CASE WHEN SUM(impressions)>0 THEN SUM(position*impressions)/SUM(impressions) ELSE 0 END AS position
             FROM {$table}
             WHERE property_hash=%s AND search_type='web' AND data_date BETWEEN %s AND %s
             GROUP BY page_hash ORDER BY impressions DESC LIMIT %d",
            hash('sha256',$property_id),$date_from,$date_to,max(1,min(10000,absint($limit)))
        );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
    }
    // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- $prepared_sql is the direct result of $wpdb->prepare(). Native live read/write of plugin-owned tables, retaining immediate reporting consistency; query values are prepared.
    $rows = $wpdb->get_results($prepared_sql, ARRAY_A);
    if ($page_source) seo_google_attach_page_query_counts($rows, $property_id, $date_from, $date_to);
    return $rows;
}

/**
 * Convierte una URL en un área navegable basada en su primera carpeta.
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
function seo_google_url_area($url) {
    $path = (string) wp_parse_url($url, PHP_URL_PATH);
    $path = trim($path, '/');
    if ('' === $path) {
        return '/ (inicio)';
    }
    $segments = explode('/', $path);
    return '/' . sanitize_title($segments[0]) . '/';
}

/**
 * Cobertura observada en Google por áreas y rangos de posición.
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
function seo_google_render_coverage() {
    if (!seo_google_analysis_ready()) {
        return;
    }

    $settings = seo_google_get_settings();
    $period   = seo_google_get_analysis_period($settings['property_id'], 28);
    $pages    = seo_google_get_all_page_metrics($settings['property_id'], $period['current_from'], $period['current_to'], 5000);
    $areas    = array();
    $ranges   = array(
        '1–3' => 0,
        '4–10' => 0,
        '11–20' => 0,
        '21–50' => 0,
        '51–100' => 0,
        'Más de 100' => 0,
    );

    foreach ($pages as $page) {
        $area = seo_google_url_area($page['page_url']);
        if (!isset($areas[$area])) {
            $areas[$area] = array('pages' => 0, 'impressions' => 0, 'clicks' => 0, 'weighted_position' => 0, 'queries' => 0);
        }
        $areas[$area]['pages']++;
        $areas[$area]['impressions'] += (float) $page['impressions'];
        $areas[$area]['clicks'] += (float) $page['clicks'];
        $areas[$area]['weighted_position'] += (float) $page['position'] * (float) $page['impressions'];
        $areas[$area]['queries'] += absint($page['queries']);

        $position = (float) $page['position'];
        if ($position <= 3) {
            $ranges['1–3']++;
        } elseif ($position <= 10) {
            $ranges['4–10']++;
        } elseif ($position <= 20) {
            $ranges['11–20']++;
        } elseif ($position <= 50) {
            $ranges['21–50']++;
        } elseif ($position <= 100) {
            $ranges['51–100']++;
        } else {
            $ranges['Más de 100']++;
        }
    }

    uasort($areas, function ($a, $b) { return $b['impressions'] <=> $a['impressions']; });

    echo '<div style="background:#fff;border:1px solid #dcdcde;padding:18px;border-radius:6px;margin-bottom:20px;">';
    echo '<h3 style="margin-top:0;">' . esc_html__( 'Cobertura observada', 'taxocommerce-google-bing' ) . '</h3>';
    echo '<p>' . esc_html__( 'Distribución de las páginas que Google ha mostrado. Esta cobertura describe visibilidad; todavía no mide si el catálogo es suficiente o correcto.', 'taxocommerce-google-bing' ) . '</p>';
    echo '<p><code>' . esc_html($period['current_from']) . '</code> → <code>' . esc_html($period['current_to']) . '</code></p>';
    if (!seo_google_pages_period_complete($settings['property_id'], $period['current_from'], $period['current_to'])) {
        echo '<p class="notice notice-warning inline">' . esc_html__( 'Cobertura parcial basada en consultas visibles hasta completar la sincronización diaria de páginas.', 'taxocommerce-google-bing' ) . '</p>';
    }
    echo '</div>';

    echo '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(420px,1fr));gap:20px;">';
    echo '<div style="background:#fff;border:1px solid #dcdcde;padding:18px;border-radius:6px;overflow:auto;">' . '<h3 style="margin-top:0;">' . esc_html__( 'Áreas URL', 'taxocommerce-google-bing' ) . '</h3>';
    echo '<table class="widefat striped">' . '<thead>' . '<tr>' . '<th>' . esc_html__( 'Área', 'taxocommerce-google-bing' ) . '</th>' . '<th>' . esc_html__( 'Páginas', 'taxocommerce-google-bing' ) . '</th>' . '<th>' . esc_html__( 'Imp.', 'taxocommerce-google-bing' ) . '</th>' . '<th>' . esc_html__( 'Clics', 'taxocommerce-google-bing' ) . '</th>' . '<th>' . esc_html__( 'Pos.', 'taxocommerce-google-bing' ) . '</th>' . '</tr>' . '</thead>' . '<tbody>';
    foreach (array_slice($areas, 0, 50, true) as $area => $values) {
        $position = $values['impressions'] > 0 ? $values['weighted_position'] / $values['impressions'] : 0;
        echo '<tr><td><code>' . esc_html($area) . '</code></td><td>' . esc_html(number_format_i18n($values['pages'])) . '</td><td>' . esc_html(number_format_i18n($values['impressions'], 0)) . '</td><td>' . esc_html(number_format_i18n($values['clicks'], 0)) . '</td><td>' . esc_html(number_format_i18n($position, 1)) . '</td></tr>';
    }
    echo '</tbody></table></div>';

    echo '<div style="background:#fff;border:1px solid #dcdcde;padding:18px;border-radius:6px;">' . '<h3 style="margin-top:0;">' . esc_html__( 'Páginas por rango de posición', 'taxocommerce-google-bing' ) . '</h3>';
    echo '<table class="widefat striped">' . '<thead>' . '<tr>' . '<th>' . esc_html__( 'Rango', 'taxocommerce-google-bing' ) . '</th>' . '<th>' . esc_html__( 'Páginas', 'taxocommerce-google-bing' ) . '</th>' . '</tr>' . '</thead>' . '<tbody>';
    foreach ($ranges as $range => $count) {
        echo '<tr><td>' . esc_html($range) . '</td><td>' . esc_html(number_format_i18n($count)) . '</td></tr>';
    }
    echo '</tbody>' . '</table>' . '<p class="description">' . esc_html__( 'Cada página se sitúa según su posición media ponderada del período.', 'taxocommerce-google-bing' ) . '</p>' . '</div>';
    echo '</div>';
}

/**
 * Lee y valida los filtros del Laboratorio.
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
function seo_google_get_lab_filters($property_id, $source = null) {
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Read-only filter map; each consumed field is unslashed and sanitized by type below.
    $source = is_array($source) ? $source : $_GET;
    $period = seo_google_get_analysis_period($property_id, 28);

    $group = isset($source['lab_group']) ? sanitize_key(wp_unslash($source['lab_group'])) : 'query_page';
    if (!in_array($group, array('query', 'page', 'query_page', 'date'), true)) {
        $group = 'query_page';
    }

    $date_from = isset($source['lab_from']) ? sanitize_text_field(wp_unslash($source['lab_from'])) : $period['current_from'];
    $date_to   = isset($source['lab_to']) ? sanitize_text_field(wp_unslash($source['lab_to'])) : $period['current_to'];

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from)) {
        $date_from = $period['current_from'];
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to)) {
        $date_to = $period['current_to'];
    }
    if ($date_from > $date_to) {
        $tmp = $date_from;
        $date_from = $date_to;
        $date_to = $tmp;
    }

    $order = isset($source['lab_order']) ? sanitize_key(wp_unslash($source['lab_order'])) : 'impressions';
    if (!in_array($order, array('impressions', 'clicks', 'ctr', 'position'), true)) {
        $order = 'impressions';
    }

    return array(
        'group'           => $group,
        'date_from'       => $date_from,
        'date_to'         => $date_to,
        'query'           => isset($source['lab_query']) ? sanitize_text_field(wp_unslash($source['lab_query'])) : '',
        'url'             => isset($source['lab_url']) ? sanitize_text_field(wp_unslash($source['lab_url'])) : '',
        'min_impressions' => isset($source['lab_min']) ? max(0, (float) $source['lab_min']) : 0,
        'limit'           => isset($source['lab_limit']) ? max(10, min(1000, absint($source['lab_limit']))) : 100,
        'order'           => $order,
    );
}

/**
 * Consulta configurable del Laboratorio.
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
function seo_google_get_lab_rows($property_id, array $filters) {
    global $wpdb;

    $table = esc_sql( seo_google_table('search_data') );
    $where = 'property_hash = %s AND data_date BETWEEN %s AND %s';
    $args  = array(hash('sha256', $property_id), $filters['date_from'], $filters['date_to']);

    if ('' !== $filters['query']) {
        $where .= ' AND query_text LIKE %s';
        $args[] = '%' . $wpdb->esc_like($filters['query']) . '%';
    }
    if ('' !== $filters['url']) {
        $where .= ' AND page_url LIKE %s';
        $args[] = '%' . $wpdb->esc_like($filters['url']) . '%';
    }

    $select = '';
    $group_by = '';
    if ('query' === $filters['group']) {
        $select = "MAX(query_text) AS query_text, '' AS page_url, '' AS data_date";
        $group_by = 'query_hash';
    } elseif ('page' === $filters['group']) {
        $select = "'' AS query_text, MAX(page_url) AS page_url, '' AS data_date";
        $group_by = 'page_hash';
    } elseif ('date' === $filters['group']) {
        $select = "'' AS query_text, '' AS page_url, data_date";
        $group_by = 'data_date';
    } else {
        $select = "MAX(query_text) AS query_text, MAX(page_url) AS page_url, '' AS data_date";
        $group_by = 'query_hash, page_hash';
    }

    $order_sql = array(
        'impressions' => 'impressions DESC, clicks DESC',
        'clicks'      => 'clicks DESC, impressions DESC',
        'ctr'         => 'ctr DESC, impressions DESC',
        'position'    => 'position ASC, impressions DESC',
    );

    $args[] = max(0, (float) $filters['min_impressions']);
    $args[] = max(10, min(10000, absint($filters['limit'])));

    $sql = "SELECT
                {$select},
                SUM(clicks) AS clicks,
                SUM(impressions) AS impressions,
                CASE WHEN SUM(impressions) > 0 THEN SUM(clicks) / SUM(impressions) ELSE 0 END AS ctr,
                CASE WHEN SUM(impressions) > 0 THEN SUM(position * impressions) / SUM(impressions) ELSE 0 END AS position
            FROM {$table}
            WHERE {$where}
            GROUP BY {$group_by}
            HAVING SUM(impressions) >= %f
            ORDER BY {$order_sql[$filters['order']]}
            LIMIT %d";

    // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- SELECT/GROUP/ORDER fragments come from closed allowlists; all user filter values and LIMIT are bound through prepare(). Native live read/write of plugin-owned tables, retaining immediate reporting consistency; query values are prepared.
    return $wpdb->get_results($wpdb->prepare($sql, $args), ARRAY_A);
}

/**
 * Exportación CSV de los resultados del Laboratorio.
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
function seo_google_export_csv_handler() {
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('No tienes permisos para exportar estos datos.', 'taxocommerce-google-bing'));
    }

    check_admin_referer('seo_google_export_csv', 'seo_google_export_nonce');
    $settings = seo_google_get_settings();
    if ('connected' !== seo_google_connection_status()) {
        wp_die(esc_html__('Google Search Console no está conectado.', 'taxocommerce-google-bing'));
    }

    $filters = seo_google_get_lab_filters($settings['property_id'], $_GET);
    $filters['limit'] = min(10000, max(10, absint(seo_google_query_arg('lab_export_limit', 5000))));
    $rows = seo_google_get_lab_rows($settings['property_id'], $filters);

    nocache_headers();
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="google-intelligence-' . gmdate('Ymd-His') . '.csv"');
    echo "\xEF\xBB\xBF";
    $output = fopen('php://output', 'w');
    fputcsv($output, array('fecha', 'consulta', 'url', 'impresiones', 'clics', 'ctr', 'posicion'), ';', '"', '');
    foreach ($rows as $row) {
        fputcsv($output, array(
            $row['data_date'],
            $row['query_text'],
            $row['page_url'],
            $row['impressions'],
            $row['clicks'],
            $row['ctr'],
            $row['position'],
        ), ';', '"', '');
    }
    // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Cierre explícito del stream CSV de Google Intelligence.
    fclose($output);
    exit;
}

/**
 * Exporta el JSON ejecutivo de resultados Google y decisiones.
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
function seo_google_export_decisions_json_handler() {
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('No tienes permisos para exportar estos datos.', 'taxocommerce-google-bing'));
    }

    check_admin_referer('seo_google_export_decisions_json');

    $days = absint(seo_google_query_arg('days', 60));
    $days = in_array($days, array(28, 60, 90), true) ? $days : 60;

    if (!function_exists('seo_google_opportunity_export_payload')) {
        wp_die(esc_html__('El motor de decisiones de Google Intelligence no está disponible.', 'taxocommerce-google-bing'));
    }

    $payload = seo_google_opportunity_export_payload($days);
    $date = wp_date('Ymd_His');

    nocache_headers();
    header('X-Content-Type-Options: nosniff');
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="google-intelligence-decisions-' . $date . '.json"');

    echo wp_json_encode(
        $payload,
        JSON_PRETTY_PRINT
        | JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
        | JSON_INVALID_UTF8_SUBSTITUTE
        | JSON_PARTIAL_OUTPUT_ON_ERROR
    );
    exit;
}

/**
 * Laboratorio: consulta abierta, filtrable y exportable.
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
function seo_google_render_laboratory() {
    if (!seo_google_analysis_ready()) {
        return;
    }

    $settings = seo_google_get_settings();
    $filters  = seo_google_get_lab_filters($settings['property_id']);
    $rows     = seo_google_get_lab_rows($settings['property_id'], $filters);

    echo '<div style="background:#fff;border:1px solid #dcdcde;padding:18px;border-radius:6px;margin-bottom:20px;">';
    echo '<h3 style="margin-top:0;">' . esc_html__( 'Laboratorio de datos', 'taxocommerce-google-bing' ) . '</h3>';
    echo '<p>' . esc_html__( 'Consulta los datos con tus propios filtros. El sistema agrupa y resume, pero conserva visibles la consulta, la URL y las métricas utilizadas.', 'taxocommerce-google-bing' ) . '</p>';
    echo '<form method="get" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;align-items:end;">';
    echo '<input type="hidden" name="page" value="taxocommerce-google-bing"><input type="hidden" name="tab" value="google_intelligence"><input type="hidden" name="google_view" value="laboratory">';
    echo '<label>' . '<strong>' . esc_html__( 'Agrupar por', 'taxocommerce-google-bing' ) . '</strong>' . '<br>' . '<select name="lab_group" style="width:100%;">';
    foreach (array('query_page' => 'Consulta + URL', 'query' => 'Consulta', 'page' => 'URL', 'date' => 'Fecha') as $value => $label) {
        echo '<option value="' . esc_attr($value) . '" ' . selected($filters['group'], $value, false) . '>' . esc_html($label) . '</option>';
    }
    echo '</select></label>';
    echo '<label><strong>Desde</strong><br><input type="date" name="lab_from" value="' . esc_attr($filters['date_from']) . '" style="width:100%;"></label>';
    echo '<label><strong>Hasta</strong><br><input type="date" name="lab_to" value="' . esc_attr($filters['date_to']) . '" style="width:100%;"></label>';
    echo '<label><strong>Consulta contiene</strong><br><input type="search" name="lab_query" value="' . esc_attr($filters['query']) . '" style="width:100%;"></label>';
    echo '<label><strong>URL contiene</strong><br><input type="search" name="lab_url" value="' . esc_attr($filters['url']) . '" style="width:100%;"></label>';
    echo '<label><strong>Impresiones mín.</strong><br><input type="number" min="0" step="1" name="lab_min" value="' . esc_attr($filters['min_impressions']) . '" style="width:100%;"></label>';
    echo '<label>' . '<strong>' . esc_html__( 'Orden', 'taxocommerce-google-bing' ) . '</strong>' . '<br>' . '<select name="lab_order" style="width:100%;">';
    foreach (array('impressions' => 'Más impresiones', 'clicks' => 'Más clics', 'ctr' => 'Mayor CTR', 'position' => 'Mejor posición') as $value => $label) {
        echo '<option value="' . esc_attr($value) . '" ' . selected($filters['order'], $value, false) . '>' . esc_html($label) . '</option>';
    }
    echo '</select></label>';
    echo '<label>' . '<strong>' . esc_html__( 'Límite', 'taxocommerce-google-bing' ) . '</strong>' . '<br>' . '<select name="lab_limit" style="width:100%;">';
    foreach (array(50, 100, 250, 500, 1000) as $limit) {
        echo '<option value="' . absint($limit) . '" ' . selected($filters['limit'], $limit, false) . '>' . absint($limit) . '</option>';
    }
    echo '</select></label>';
    echo '<div>'; submit_button('Consultar', 'primary', 'submit', false); echo '</div>';
    echo '</form>';

    $export_args = array(
        'action'             => 'seo_google_export_csv',
        'lab_group'          => $filters['group'],
        'lab_from'           => $filters['date_from'],
        'lab_to'             => $filters['date_to'],
        'lab_query'          => $filters['query'],
        'lab_url'            => $filters['url'],
        'lab_min'            => $filters['min_impressions'],
        'lab_order'          => $filters['order'],
        'lab_export_limit'   => 5000,
        'seo_google_export_nonce' => wp_create_nonce('seo_google_export_csv'),
    );
    echo '<p style="margin-bottom:0;"><a class="button" href="' . esc_url(add_query_arg($export_args, admin_url('admin-post.php'))) . '">Exportar CSV con estos filtros</a></p>';
    echo '</div>';

    echo '<div style="background:#fff;border:1px solid #dcdcde;padding:18px;border-radius:6px;overflow:auto;">';
    echo '<p><strong>Resultados:</strong> ' . esc_html(number_format_i18n(count($rows))) . '</p>';
    if (!$rows) {
        echo '<p>' . esc_html__( 'No hay filas que cumplan los filtros.', 'taxocommerce-google-bing' ) . '</p>' . '</div>';
        return;
    }
    echo '<table class="widefat striped">' . '<thead>' . '<tr>' . '<th>' . esc_html__( 'Fecha', 'taxocommerce-google-bing' ) . '</th>' . '<th>' . esc_html__( 'Consulta', 'taxocommerce-google-bing' ) . '</th>' . '<th>' . esc_html__( 'URL', 'taxocommerce-google-bing' ) . '</th>' . '<th>' . esc_html__( 'Imp.', 'taxocommerce-google-bing' ) . '</th>' . '<th>' . esc_html__( 'Clics', 'taxocommerce-google-bing' ) . '</th>' . '<th>' . esc_html__( 'CTR', 'taxocommerce-google-bing' ) . '</th>' . '<th>' . esc_html__( 'Pos.', 'taxocommerce-google-bing' ) . '</th>' . '</tr>' . '</thead>' . '<tbody>';
    foreach ($rows as $row) {
        echo '<tr><td>' . ($row['data_date'] ? esc_html($row['data_date']) : '—') . '</td>';
        echo '<td style="max-width:300px;word-break:break-word;">' . ($row['query_text'] ? esc_html($row['query_text']) : '—') . '</td>';
        echo '<td style="max-width:430px;word-break:break-word;">';
        if ($row['page_url']) {
            echo '<a href="' . esc_url($row['page_url']) . '" target="_blank" rel="noopener noreferrer">' . esc_html($row['page_url']) . '</a>';
        } else {
            echo '—';
        }
        echo '</td><td>' . esc_html(number_format_i18n((float) $row['impressions'], 0)) . '</td><td>' . esc_html(number_format_i18n((float) $row['clicks'], 0)) . '</td>';
        echo '<td>' . esc_html(number_format_i18n(((float) $row['ctr']) * 100, 2)) . '%</td><td>' . esc_html(number_format_i18n((float) $row['position'], 1)) . '</td></tr>';
    }
    echo '</tbody></table></div>';
}
