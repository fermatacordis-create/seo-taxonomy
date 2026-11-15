<?php
/**
 * TaxoCommerce SEO - proveedor Pinterest para Social Network.
 *
 * Publica Pins organicos con imagen destacada y enlace mediante Pinterest API v5.
 * La autorizacion usa OAuth 2.0 Authorization Code. Los secretos y tokens se
 * guardan cifrados por la capa comun de Social Network.
 */

defined('ABSPATH') || exit;

if (!defined('TCSOCIAL_PINTEREST_API_BASE')) {
    define('TCSOCIAL_PINTEREST_API_BASE', 'https://api.pinterest.com/v5');
}

if (!defined('TCSOCIAL_PINTEREST_SCOPES')) {
    define('TCSOCIAL_PINTEREST_SCOPES', 'boards:read boards:write pins:read pins:write');
}

/**
 * @param array $providers
 * @return array
 */
function tcsocial_pinterest_register_provider($providers)
{
    $providers = is_array($providers) ? $providers : array();
    $providers['pinterest'] = array(
        'label'                        => 'Pinterest',
        'icon'                         => 'dashicons-pinterest',
        'sanitize_connection_callback' => 'tcsocial_pinterest_sanitize_connection',
        'test_callback'                => 'tcsocial_pinterest_test_connection',
        'publish_callback'             => 'tcsocial_pinterest_publish',
        'render_connection_callback'   => 'tcsocial_pinterest_render_connection',
    );
    return $providers;
}
add_filter('tcsocial_network_providers', 'tcsocial_pinterest_register_provider');

/**
 * @param string $value
 * @param int    $limit
 * @return string
 */
function tcsocial_pinterest_limit_text($value, $limit)
{
    $value = trim(wp_strip_all_tags((string) $value));
    $limit = max(1, absint($limit));

    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $limit);
    }

    return substr($value, 0, $limit);
}

/**
 * @param array $posted
 * @param array $current
 * @return array|WP_Error
 */
function tcsocial_pinterest_sanitize_connection($posted, $current)
{
    $posted = is_array($posted) ? $posted : array();
    $current = is_array($current) ? $current : array();

    $app_id = isset($posted['app_id']) ? sanitize_text_field((string) $posted['app_id']) : '';
    $board_id = isset($posted['board_id']) ? preg_replace('/[^0-9]/', '', (string) $posted['board_id']) : '';

    $app_secret_enc = isset($current['app_secret_enc']) ? (string) $current['app_secret_enc'] : '';
    $new_app_secret = isset($posted['app_secret']) ? trim((string) $posted['app_secret']) : '';
    if ($new_app_secret !== '') {
        $app_secret_enc = tcsocial_network_encrypt_secret($new_app_secret);
        if (is_wp_error($app_secret_enc)) {
            return $app_secret_enc;
        }
    }

    $access_token_enc = isset($current['access_token_enc']) ? (string) $current['access_token_enc'] : '';
    $token_expires_at = isset($current['token_expires_at']) ? absint($current['token_expires_at']) : 0;
    $new_access_token = isset($posted['access_token']) ? trim((string) $posted['access_token']) : '';
    if ($new_access_token !== '') {
        $access_token_enc = tcsocial_network_encrypt_secret($new_access_token);
        if (is_wp_error($access_token_enc)) {
            return $access_token_enc;
        }
        // Un token pegado manualmente no incluye un TTL fiable.
        $token_expires_at = 0;
    }

    $boards = isset($current['boards']) && is_array($current['boards']) ? $current['boards'] : array();
    $board_name = isset($current['board_name']) ? sanitize_text_field((string) $current['board_name']) : '';
    $account_username = isset($current['account_username']) ? sanitize_user((string) $current['account_username'], true) : '';
    $page_link = isset($current['page_link']) ? esc_url_raw((string) $current['page_link']) : '';

    foreach ($boards as $board) {
        if (!is_array($board) || empty($board['id']) || (string) $board['id'] !== (string) $board_id) {
            continue;
        }

        $board_name = isset($board['name']) ? sanitize_text_field((string) $board['name']) : $board_name;
        $account_username = isset($board['owner_username']) ? sanitize_user((string) $board['owner_username'], true) : $account_username;
        if ($account_username !== '') {
            $page_link = 'https://www.pinterest.com/' . rawurlencode($account_username) . '/';
        }
        break;
    }

    return array(
        'enabled'                  => ($board_id !== '' && $access_token_enc !== '') ? 1 : 0,
        'app_id'                   => $app_id,
        'app_secret_enc'           => $app_secret_enc,
        'access_token_enc'         => $access_token_enc,
        'refresh_token_enc'        => isset($current['refresh_token_enc']) ? (string) $current['refresh_token_enc'] : '',
        'token_expires_at'         => $token_expires_at,
        'refresh_token_expires_at' => isset($current['refresh_token_expires_at']) ? absint($current['refresh_token_expires_at']) : 0,
        'scope'                    => isset($current['scope']) ? sanitize_text_field((string) $current['scope']) : TCSOCIAL_PINTEREST_SCOPES,
        'board_id'                 => $board_id,
        'board_name'               => $board_name,
        'boards'                   => $boards,
        'account_username'         => $account_username,
        'page_name'                => $board_name,
        'page_link'                => $page_link,
        'publish_mode'             => 'image',
        // Estos campos pertenecen a Pinterest Merchant y se editan desde
        // Importar/Exportar > Conexiones. Se conservan al guardar OAuth/social.
        'domain_verify_enabled'    => !empty($current['domain_verify_enabled']) ? 1 : 0,
        'domain_verify_code'       => isset($current['domain_verify_code']) ? sanitize_text_field((string) $current['domain_verify_code']) : '',
        'catalog_feed_url'         => isset($current['catalog_feed_url']) ? esc_url_raw((string) $current['catalog_feed_url']) : '',
        'merchant_last_test_at'    => isset($current['merchant_last_test_at']) ? sanitize_text_field((string) $current['merchant_last_test_at']) : '',
        'merchant_last_test_ok'    => !empty($current['merchant_last_test_ok']) ? 1 : 0,
        'merchant_last_test_error' => isset($current['merchant_last_test_error']) ? sanitize_text_field((string) $current['merchant_last_test_error']) : '',
        'last_test_at'             => isset($current['last_test_at']) ? sanitize_text_field((string) $current['last_test_at']) : '',
        'last_test_ok'             => !empty($current['last_test_ok']) ? 1 : 0,
        'last_test_error'          => isset($current['last_test_error']) ? sanitize_text_field((string) $current['last_test_error']) : '',
    );
}

/**
 * @param string $token
 * @param array  $extra
 * @return array
 */
function tcsocial_pinterest_headers($token, $extra = array())
{
    return array_merge(
        array(
            'Authorization' => 'Bearer ' . $token,
            'Accept'        => 'application/json',
            'Content-Type'  => 'application/json',
        ),
        is_array($extra) ? $extra : array()
    );
}

/**
 * @param mixed $response
 * @return array|WP_Error
 */
function tcsocial_pinterest_decode_response($response)
{
    if (is_wp_error($response)) {
        return $response;
    }

    $code = (int) wp_remote_retrieve_response_code($response);
    $body = (string) wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    $data = is_array($data) ? $data : array();

    if ($code < 200 || $code >= 300) {
        $message = '';
        if (!empty($data['message'])) {
            $message = (string) $data['message'];
        } elseif (!empty($data['error_description'])) {
            $message = (string) $data['error_description'];
        } elseif (!empty($data['error'])) {
            $message = is_string($data['error']) ? $data['error'] : wp_json_encode($data['error']);
        } elseif (!empty($data['code'])) {
            $message = 'Pinterest API error ' . sanitize_text_field((string) $data['code']) . '.';
        }

        if ($message === '') {
            $message = 'Pinterest devolvio HTTP ' . $code . '.';
        }

        return new WP_Error('pinterest_api_error', sanitize_text_field($message));
    }

    $data['_http_code'] = $code;
    return $data;
}

/**
 * @return string
 */
function tcsocial_pinterest_redirect_uri()
{
    return rest_url('seo-system/v1/pinterest/callback');
}

/**
 * Guarda en la configuracion un juego de tokens devuelto por Pinterest.
 * Pinterest rota el refresh token, por lo que siempre se conserva el ultimo.
 *
 * @param array $config
 * @param array $token_data
 * @return array|WP_Error
 */
function tcsocial_pinterest_apply_token_data($config, $token_data)
{
    $config = is_array($config) ? $config : array();
    $token_data = is_array($token_data) ? $token_data : array();

    if (empty($token_data['access_token'])) {
        return new WP_Error('pinterest_missing_access_token', 'Pinterest no devolvio Access Token.');
    }

    $access_enc = tcsocial_network_encrypt_secret((string) $token_data['access_token']);
    if (is_wp_error($access_enc)) {
        return $access_enc;
    }

    $config['access_token_enc'] = $access_enc;
    $config['token_expires_at'] = !empty($token_data['expires_in'])
        ? time() + absint($token_data['expires_in'])
        : 0;

    if (!empty($token_data['refresh_token'])) {
        $refresh_enc = tcsocial_network_encrypt_secret((string) $token_data['refresh_token']);
        if (is_wp_error($refresh_enc)) {
            return $refresh_enc;
        }
        $config['refresh_token_enc'] = $refresh_enc;
    }

    if (!empty($token_data['refresh_token_expires_at'])) {
        $config['refresh_token_expires_at'] = absint($token_data['refresh_token_expires_at']);
    } elseif (!empty($token_data['refresh_token_expires_in'])) {
        $config['refresh_token_expires_at'] = time() + absint($token_data['refresh_token_expires_in']);
    }

    if (!empty($token_data['scope'])) {
        $config['scope'] = sanitize_text_field((string) $token_data['scope']);
    }

    return $config;
}

/**
 * @param array $config
 * @return string|WP_Error
 */
function tcsocial_pinterest_get_access_token($config)
{
    $config = is_array($config) ? $config : array();
    $token = tcsocial_network_decrypt_secret(isset($config['access_token_enc']) ? $config['access_token_enc'] : '');

    if ($token === '') {
        return new WP_Error('pinterest_missing_token', 'Pinterest no tiene un Access Token guardado.');
    }

    $expires_at = isset($config['token_expires_at']) ? absint($config['token_expires_at']) : 0;
    if ($expires_at === 0 || $expires_at > time() + DAY_IN_SECONDS) {
        return $token;
    }

    $refresh_token = tcsocial_network_decrypt_secret(isset($config['refresh_token_enc']) ? $config['refresh_token_enc'] : '');
    $app_secret = tcsocial_network_decrypt_secret(isset($config['app_secret_enc']) ? $config['app_secret_enc'] : '');
    $app_id = isset($config['app_id']) ? trim((string) $config['app_id']) : '';

    if ($refresh_token === '' || $app_id === '' || $app_secret === '') {
        return new WP_Error('pinterest_token_expired', 'El Access Token de Pinterest ha caducado. Vuelve a autorizar Pinterest desde Conexiones.');
    }

    $response = wp_remote_post(
        TCSOCIAL_PINTEREST_API_BASE . '/oauth/token',
        array(
            'timeout' => 20,
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($app_id . ':' . $app_secret),
                'Accept'        => 'application/json',
                'Content-Type'  => 'application/x-www-form-urlencoded',
            ),
            'body' => array(
                'grant_type'    => 'refresh_token',
                'refresh_token' => $refresh_token,
            ),
        )
    );

    $token_data = tcsocial_pinterest_decode_response($response);
    if (is_wp_error($token_data)) {
        return $token_data;
    }

    $updated = tcsocial_pinterest_apply_token_data($config, $token_data);
    if (is_wp_error($updated)) {
        return $updated;
    }

    $settings = tcsocial_network_get_settings();
    $settings['providers']['pinterest'] = $updated;
    tcsocial_network_save_settings($settings);

    return tcsocial_network_decrypt_secret($updated['access_token_enc']);
}

/**
 * Lista tableros publicos accesibles por la cuenta autorizada.
 *
 * @param string $token
 * @return array|WP_Error
 */
function tcsocial_pinterest_discover_boards($token)
{
    $boards = array();
    $bookmark = '';
    $page = 0;

    do {
        $page++;
        $url = add_query_arg(
            array_filter(
                array(
                    'page_size' => 100,
                    'bookmark'  => $bookmark,
                ),
                static function ($value) {
                    return $value !== '' && $value !== null;
                }
            ),
            TCSOCIAL_PINTEREST_API_BASE . '/boards'
        );

        $response = wp_remote_get(
            $url,
            array(
                'timeout' => 20,
                'headers' => tcsocial_pinterest_headers($token),
            )
        );
        $data = tcsocial_pinterest_decode_response($response);
        if (is_wp_error($data)) {
            return $data;
        }

        foreach ((array) ($data['items'] ?? array()) as $item) {
            if (!is_array($item) || empty($item['id'])) {
                continue;
            }

            $privacy = isset($item['privacy']) ? strtoupper((string) $item['privacy']) : '';
            if ($privacy !== '' && $privacy !== 'PUBLIC') {
                continue;
            }

            $owner_username = '';
            if (!empty($item['owner']) && is_array($item['owner']) && !empty($item['owner']['username'])) {
                $owner_username = sanitize_user((string) $item['owner']['username'], true);
            }

            $boards[] = array(
                'id'             => preg_replace('/[^0-9]/', '', (string) $item['id']),
                'name'           => sanitize_text_field(isset($item['name']) ? (string) $item['name'] : ''),
                'privacy'        => sanitize_text_field($privacy),
                'owner_username' => $owner_username,
            );
        }

        $bookmark = !empty($data['bookmark']) ? sanitize_text_field((string) $data['bookmark']) : '';
    } while ($bookmark !== '' && $page < 5);

    return $boards;
}

/**
 * @param string $token
 * @param string $board_id
 * @return array|WP_Error
 */
function tcsocial_pinterest_fetch_board($token, $board_id)
{
    $board_id = preg_replace('/[^0-9]/', '', (string) $board_id);
    if ($board_id === '') {
        return new WP_Error('pinterest_board_missing', 'Selecciona un tablero de Pinterest.');
    }

    $response = wp_remote_get(
        TCSOCIAL_PINTEREST_API_BASE . '/boards/' . rawurlencode($board_id),
        array(
            'timeout' => 20,
            'headers' => tcsocial_pinterest_headers($token),
        )
    );

    return tcsocial_pinterest_decode_response($response);
}

/**
 * @param array $config
 * @return array|WP_Error
 */
function tcsocial_pinterest_test_connection($config)
{
    $config = is_array($config) ? $config : array();
    $board_id = isset($config['board_id']) ? preg_replace('/[^0-9]/', '', (string) $config['board_id']) : '';

    if ($board_id === '') {
        return new WP_Error('pinterest_board_missing', 'Selecciona un tablero de Pinterest antes de probar la conexion.');
    }

    $token = tcsocial_pinterest_get_access_token($config);
    if (is_wp_error($token)) {
        return $token;
    }

    // Recuperar la configuracion actualizada por si el token se refresco.
    $settings = tcsocial_network_get_settings();
    $config = isset($settings['providers']['pinterest']) && is_array($settings['providers']['pinterest'])
        ? $settings['providers']['pinterest']
        : $config;

    $board = tcsocial_pinterest_fetch_board($token, $board_id);
    if (is_wp_error($board)) {
        return $board;
    }

    $username = '';
    if (!empty($board['owner']) && is_array($board['owner']) && !empty($board['owner']['username'])) {
        $username = sanitize_user((string) $board['owner']['username'], true);
    }

    $page_link = $username !== '' ? 'https://www.pinterest.com/' . rawurlencode($username) . '/' : 'https://www.pinterest.com/';

    return array(
        'page_name' => !empty($board['name']) ? sanitize_text_field((string) $board['name']) : 'Pinterest',
        'page_link' => $page_link,
    );
}

/**
 * @param array $payload
 * @return array|WP_Error
 */
function tcsocial_pinterest_publish($payload)
{
    $payload = is_array($payload) ? $payload : array();
    $config = isset($payload['provider']) && is_array($payload['provider']) ? $payload['provider'] : array();
    $post = isset($payload['post']) && $payload['post'] instanceof WP_Post ? $payload['post'] : null;
    $board_id = isset($config['board_id']) ? preg_replace('/[^0-9]/', '', (string) $config['board_id']) : '';
    $image_url = isset($payload['image_url']) ? esc_url_raw((string) $payload['image_url']) : '';
    $target_url = isset($payload['target_url']) ? esc_url_raw((string) $payload['target_url']) : '';

    if (!$post) {
        return new WP_Error('pinterest_post_missing', 'No se encontro el contenido que se quiere publicar.');
    }
    if ($board_id === '') {
        return new WP_Error('pinterest_board_missing', 'Pinterest no tiene un tablero de destino seleccionado.');
    }
    if ($image_url === '') {
        return new WP_Error('pinterest_image_missing', 'Pinterest requiere una imagen. Asigna una imagen destacada al contenido antes de publicarlo.');
    }
    if ($target_url === '') {
        return new WP_Error('pinterest_link_missing', 'No se pudo generar la URL de destino del Pin.');
    }

    $known_scopes = isset($config['scope']) ? preg_split('/[\s,]+/', strtolower((string) $config['scope']), -1, PREG_SPLIT_NO_EMPTY) : array();
    if (!empty($known_scopes) && !in_array('boards:write', $known_scopes, true)) {
        return new WP_Error('pinterest_missing_board_scope', 'La autorización actual de Pinterest no incluye boards:write. Vuelve a autorizar la cuenta con los permisos nuevos.');
    }

    $token = tcsocial_pinterest_get_access_token($config);
    if (is_wp_error($token)) {
        return $token;
    }

    $message = isset($payload['message']) ? (string) $payload['message'] : '';
    $title = tcsocial_pinterest_limit_text(get_the_title($post), 100);
    $description = tcsocial_pinterest_limit_text($message, 500);

    $body = array(
        'board_id'    => $board_id,
        'link'        => $target_url,
        'title'       => $title,
        'description' => $description,
        'media_source' => array(
            'source_type' => 'image_url',
            'url'         => $image_url,
            'is_standard' => true,
        ),
    );

    $response = wp_remote_post(
        TCSOCIAL_PINTEREST_API_BASE . '/pins',
        array(
            'timeout' => 30,
            'headers' => tcsocial_pinterest_headers($token),
            'body'    => wp_json_encode($body),
        )
    );
    $data = tcsocial_pinterest_decode_response($response);
    if (is_wp_error($data)) {
        return $data;
    }

    $pin_id = !empty($data['id']) ? preg_replace('/[^0-9]/', '', (string) $data['id']) : '';
    if ($pin_id === '') {
        return new WP_Error('pinterest_missing_pin_id', 'Pinterest acepto la peticion pero no devolvio el ID del Pin.');
    }

    return array(
        'remote_id'  => $pin_id,
        'remote_url' => 'https://www.pinterest.com/pin/' . rawurlencode($pin_id) . '/',
    );
}

/**
 * Inicia OAuth desde wp-admin.
 */
function tcsocial_pinterest_oauth_start()
{
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('No tienes permisos para conectar Pinterest.', 'taxocommerce-social'));
    }
    check_admin_referer('tcsocial_pinterest_oauth_start');

    $settings = tcsocial_network_get_settings();
    $config = isset($settings['providers']['pinterest']) && is_array($settings['providers']['pinterest'])
        ? $settings['providers']['pinterest']
        : array();
    $app_id = isset($config['app_id']) ? trim((string) $config['app_id']) : '';
    $app_secret = tcsocial_network_decrypt_secret(isset($config['app_secret_enc']) ? $config['app_secret_enc'] : '');

    if ($app_id === '' || $app_secret === '') {
        wp_safe_redirect(tcsocial_network_admin_url('connections', array('social_msg' => 'pinterest_app_missing')));
        exit;
    }

    $state = wp_generate_password(48, false, false);
    $state_key = 'seo_pi_state_' . substr(hash('sha256', $state), 0, 32);
    set_transient(
        $state_key,
        array(
            'user_id' => get_current_user_id(),
            'created' => time(),
        ),
        10 * MINUTE_IN_SECONDS
    );

    $url = add_query_arg(
        array(
            'client_id'     => $app_id,
            'redirect_uri'  => tcsocial_pinterest_redirect_uri(),
            'response_type' => 'code',
            'scope'         => TCSOCIAL_PINTEREST_SCOPES,
            'state'         => $state,
        ),
        'https://www.pinterest.com/oauth/'
    );

    wp_redirect(esc_url_raw($url)); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
    exit;
}
add_action('admin_post_tcsocial_pinterest_oauth_start', 'tcsocial_pinterest_oauth_start');

/**
 * Callback OAuth expuesto mediante REST para disponer de una Redirect URL estable.
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response|void
 */
function tcsocial_pinterest_oauth_callback(WP_REST_Request $request)
{
    $state = sanitize_text_field((string) $request->get_param('state'));
    $code = sanitize_text_field((string) $request->get_param('code'));
    $oauth_error = sanitize_text_field((string) $request->get_param('error'));
    $oauth_error_description = sanitize_text_field((string) $request->get_param('error_description'));
    $state_key = $state !== '' ? 'seo_pi_state_' . substr(hash('sha256', $state), 0, 32) : '';
    $state_data = $state_key !== '' ? get_transient($state_key) : false;

    if ($state_key !== '') {
        delete_transient($state_key);
    }

    if (!is_array($state_data) || empty($state_data['user_id'])) {
        return new WP_REST_Response(array('error' => 'Estado OAuth de Pinterest invalido o caducado.'), 400);
    }

    $user_id = absint($state_data['user_id']);
    $user = get_user_by('id', $user_id);
    if (!$user || !user_can($user, 'manage_options')) {
        return new WP_REST_Response(array('error' => 'El usuario que inicio OAuth ya no tiene permisos.'), 403);
    }

    if ($oauth_error !== '' || $code === '') {
        $detail = $oauth_error_description !== '' ? $oauth_error_description : $oauth_error;
        if ($detail === '') {
            $detail = 'Pinterest no devolvio un codigo de autorizacion.';
        }
        set_transient('tcsocial_pinterest_oauth_error_' . $user_id, $detail, 90);
        wp_safe_redirect(tcsocial_network_admin_url('connections', array('social_msg' => 'pinterest_oauth_failed')));
        exit;
    }

    $settings = tcsocial_network_get_settings();
    $config = isset($settings['providers']['pinterest']) && is_array($settings['providers']['pinterest'])
        ? $settings['providers']['pinterest']
        : array();
    $app_id = isset($config['app_id']) ? trim((string) $config['app_id']) : '';
    $app_secret = tcsocial_network_decrypt_secret(isset($config['app_secret_enc']) ? $config['app_secret_enc'] : '');

    if ($app_id === '' || $app_secret === '') {
        set_transient('tcsocial_pinterest_oauth_error_' . $user_id, 'Faltan el App ID o el App Secret guardados.', 90);
        wp_safe_redirect(tcsocial_network_admin_url('connections', array('social_msg' => 'pinterest_oauth_failed')));
        exit;
    }

    $response = wp_remote_post(
        TCSOCIAL_PINTEREST_API_BASE . '/oauth/token',
        array(
            'timeout' => 20,
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($app_id . ':' . $app_secret),
                'Accept'        => 'application/json',
                'Content-Type'  => 'application/x-www-form-urlencoded',
            ),
            'body' => array(
                'grant_type'   => 'authorization_code',
                'code'         => $code,
                'redirect_uri' => tcsocial_pinterest_redirect_uri(),
            ),
        )
    );

    $token_data = tcsocial_pinterest_decode_response($response);
    if (is_wp_error($token_data)) {
        set_transient('tcsocial_pinterest_oauth_error_' . $user_id, $token_data->get_error_message(), 90);
        wp_safe_redirect(tcsocial_network_admin_url('connections', array('social_msg' => 'pinterest_oauth_failed')));
        exit;
    }

    $updated = tcsocial_pinterest_apply_token_data($config, $token_data);
    if (is_wp_error($updated)) {
        set_transient('tcsocial_pinterest_oauth_error_' . $user_id, $updated->get_error_message(), 90);
        wp_safe_redirect(tcsocial_network_admin_url('connections', array('social_msg' => 'pinterest_oauth_failed')));
        exit;
    }

    $boards = tcsocial_pinterest_discover_boards((string) $token_data['access_token']);
    if (is_wp_error($boards)) {
        set_transient('tcsocial_pinterest_oauth_error_' . $user_id, $boards->get_error_message(), 90);
        wp_safe_redirect(tcsocial_network_admin_url('connections', array('social_msg' => 'pinterest_oauth_failed')));
        exit;
    }

    $updated['boards'] = $boards;
    $selected_id = isset($updated['board_id']) ? preg_replace('/[^0-9]/', '', (string) $updated['board_id']) : '';
    $selected = null;

    foreach ($boards as $board) {
        if ($selected_id !== '' && (string) $board['id'] === $selected_id) {
            $selected = $board;
            break;
        }
    }

    if (!$selected && count($boards) === 1) {
        $selected = reset($boards);
    }

    if ($selected) {
        $updated['board_id'] = (string) $selected['id'];
        $updated['board_name'] = (string) $selected['name'];
        $updated['account_username'] = (string) $selected['owner_username'];
        $updated['page_name'] = (string) $selected['name'];
        $updated['page_link'] = $selected['owner_username'] !== ''
            ? 'https://www.pinterest.com/' . rawurlencode($selected['owner_username']) . '/'
            : 'https://www.pinterest.com/';
    }

    $updated['enabled'] = (!empty($updated['board_id']) && !empty($updated['access_token_enc'])) ? 1 : 0;
    $updated['last_test_at'] = '';
    $updated['last_test_ok'] = 0;
    $updated['last_test_error'] = '';

    $settings['providers']['pinterest'] = $updated;
    tcsocial_network_save_settings($settings);

    wp_safe_redirect(tcsocial_network_admin_url('connections', array('social_msg' => 'pinterest_connected')));
    exit;
}

/**
 * Valida el estado OAuth antes de ejecutar el callback público.
 *
 * @param WP_REST_Request $request
 * @return true|WP_Error
 */
function tcsocial_pinterest_oauth_permission(WP_REST_Request $request)
{
    $state = sanitize_text_field((string) $request->get_param('state'));
    if ('' === $state) {
        return new WP_Error('taxocommerce_oauth_state_missing', 'Estado OAuth ausente.', array('status' => 403));
    }

    $state_key = 'seo_pi_state_' . substr(hash('sha256', $state), 0, 32);
    $state_data = get_transient($state_key);
    if (!is_array($state_data) || empty($state_data['user_id'])) {
        return new WP_Error('taxocommerce_oauth_state_invalid', 'Estado OAuth inválido o caducado.', array('status' => 403));
    }

    $user = get_user_by('id', absint($state_data['user_id']));
    if (!$user || !user_can($user, 'manage_options')) {
        return new WP_Error('taxocommerce_oauth_user_forbidden', 'El usuario que inició OAuth ya no tiene permisos.', array('status' => 403));
    }

    return true;
}

/**
 * Registra el callback OAuth de Pinterest.
 */
function tcsocial_pinterest_register_rest_route()
{
    register_rest_route(
        'seo-system/v1',
        '/pinterest/callback',
        array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => 'tcsocial_pinterest_oauth_callback',
            'permission_callback' => 'tcsocial_pinterest_oauth_permission',
        )
    );
}
add_action('rest_api_init', 'tcsocial_pinterest_register_rest_route');

/**
 * Extrae el codigo de verificacion de dominio de Pinterest.
 * Acepta tanto el valor suelto como la etiqueta <meta> completa que entrega
 * Pinterest, pero nunca guarda HTML arbitrario.
 *
 * @param string $value
 * @return string
 */
function tcsocial_pinterest_extract_domain_verify($value)
{
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }

    if (stripos($value, '<meta') !== false) {
        if (preg_match('/<meta\b[^>]*\bname\s*=\s*(["\'])p:domain_verify\1[^>]*\bcontent\s*=\s*(["\'])([^"\']+)\2[^>]*>/i', $value, $match)) {
            $value = $match[3];
        } elseif (preg_match('/<meta\b[^>]*\bcontent\s*=\s*(["\'])([^"\']+)\1[^>]*\bname\s*=\s*(["\'])p:domain_verify\3[^>]*>/i', $value, $match)) {
            $value = $match[2];
        } else {
            return '';
        }
    }

    $value = sanitize_text_field($value);
    if (!preg_match('/^[A-Za-z0-9._:-]{6,255}$/', $value)) {
        return '';
    }

    return $value;
}

/**
 * Devuelve la configuracion Pinterest Merchant almacenada dentro del proveedor
 * Pinterest comun. No duplica tokens ni credenciales.
 *
 * @return array
 */
function tcsocial_pinterest_merchant_settings()
{
    $settings = tcsocial_network_get_settings();
    $config = isset($settings['providers']['pinterest']) && is_array($settings['providers']['pinterest'])
        ? $settings['providers']['pinterest']
        : array();

    return array(
        'domain_verify_enabled'    => !empty($config['domain_verify_enabled']) ? 1 : 0,
        'domain_verify_code'       => isset($config['domain_verify_code']) ? tcsocial_pinterest_extract_domain_verify($config['domain_verify_code']) : '',
        'catalog_feed_url'         => isset($config['catalog_feed_url']) ? esc_url_raw((string) $config['catalog_feed_url']) : '',
        'merchant_last_test_at'    => isset($config['merchant_last_test_at']) ? sanitize_text_field((string) $config['merchant_last_test_at']) : '',
        'merchant_last_test_ok'    => !empty($config['merchant_last_test_ok']) ? 1 : 0,
        'merchant_last_test_error' => isset($config['merchant_last_test_error']) ? sanitize_text_field((string) $config['merchant_last_test_error']) : '',
    );
}

/**
 * Guarda solo la parte Merchant sin tocar OAuth, tokens, tableros o publicacion.
 */
function tcsocial_pinterest_save_merchant_connection()
{
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('No tienes permisos para configurar Pinterest.', 'taxocommerce-social'));
    }

    check_admin_referer('tcsocial_pinterest_merchant_save', 'tcsocial_pinterest_merchant_nonce');

    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Full Pinterest meta markup is accepted intentionally and reduced to a strict verification token by tcsocial_pinterest_extract_domain_verify().
    $raw_code = isset($_POST['domain_verify_code']) ? wp_unslash($_POST['domain_verify_code']) : '';
    $code = tcsocial_pinterest_extract_domain_verify($raw_code);
    $enabled = empty($_POST['domain_verify_enabled']) ? 0 : 1;
    $feed_url = isset($_POST['catalog_feed_url']) ? esc_url_raw(wp_unslash($_POST['catalog_feed_url'])) : '';

    if ($enabled && $code === '') {
        wp_safe_redirect(add_query_arg(
            array(
                'page'                    => 'seo-import-export',
                'seo_ie_tab'              => 'conexiones-proveedores',
                'pinterest_merchant_error' => 'verification_code',
            ),
            admin_url('admin.php')
        ));
        exit;
    }

    $settings = tcsocial_network_get_settings();
    $config = isset($settings['providers']['pinterest']) && is_array($settings['providers']['pinterest'])
        ? $settings['providers']['pinterest']
        : array();

    $config['domain_verify_enabled'] = $enabled;
    $config['domain_verify_code'] = $code;
    $config['catalog_feed_url'] = $feed_url;
    // Al cambiar la configuracion, la prueba anterior deja de ser concluyente.
    $config['merchant_last_test_at'] = '';
    $config['merchant_last_test_ok'] = 0;
    $config['merchant_last_test_error'] = '';

    $settings['providers']['pinterest'] = $config;
    tcsocial_network_save_settings($settings);

    wp_safe_redirect(add_query_arg(
        array(
            'page'                     => 'seo-import-export',
            'seo_ie_tab'               => 'conexiones-proveedores',
            'pinterest_merchant_saved' => 1,
        ),
        admin_url('admin.php')
    ));
    exit;
}
add_action('admin_post_tcsocial_pinterest_merchant_save', 'tcsocial_pinterest_save_merchant_connection');

/**
 * Imprime exclusivamente la meta de verificacion de dominio. No carga JS,
 * cookies, pixel, Pinterest Tag ni seguimiento de conversiones.
 */
function tcsocial_pinterest_domain_verify_head()
{
    if (is_admin()) {
        return;
    }

    $merchant = tcsocial_pinterest_merchant_settings();
    if (empty($merchant['domain_verify_enabled']) || $merchant['domain_verify_code'] === '') {
        return;
    }

    echo "\n<!-- TaxoCommerce SEO: Pinterest domain verification -->\n";
    echo '<meta name="p:domain_verify" content="' . esc_attr($merchant['domain_verify_code']) . '">' . "\n";
}
add_action('wp_head', 'tcsocial_pinterest_domain_verify_head', 5);

/**
 * Comprueba desde el servidor que la home publica contiene exactamente la meta
 * guardada. La URL de prueba lleva query string para reducir falsos negativos
 * provocados por caches intermedias.
 *
 * @return array
 */
function tcsocial_pinterest_test_merchant_connection()
{
    $merchant = tcsocial_pinterest_merchant_settings();
    $code = (string) ($merchant['domain_verify_code'] ?? '');

    if (empty($merchant['domain_verify_enabled']) || $code === '') {
        return array(
            'ok'       => false,
            'messages' => array('Activa la verificacion e introduce el codigo de Pinterest antes de probar.'),
        );
    }

    $url = add_query_arg('seo_pinterest_verify', (string) time(), home_url('/'));
    $response = wp_remote_get(
        $url,
        array(
            'timeout'     => 20,
            'redirection' => 5,
            'headers'     => array(
                'Cache-Control' => 'no-cache',
                'User-Agent'    => 'TaxoCommerce-SEO-Pinterest-Verify/1.0',
            ),
        )
    );

    if (is_wp_error($response)) {
        return array(
            'ok'       => false,
            'messages' => array('No se pudo consultar la web publica: ' . $response->get_error_message()),
        );
    }

    $status = (int) wp_remote_retrieve_response_code($response);
    $html = (string) wp_remote_retrieve_body($response);
    if ($status < 200 || $status >= 400 || $html === '') {
        return array(
            'ok'       => false,
            'messages' => array('La comprobacion publica devolvio HTTP ' . $status . '.'),
        );
    }

    $head_html = $html;
    $head_end = stripos($html, '</head>');
    if (false !== $head_end) {
        $head_html = substr($html, 0, $head_end + 7);
    }

    $found = false;
    if (preg_match_all('/<meta\b[^>]*>/i', $head_html, $matches)) {
        foreach ((array) $matches[0] as $meta) {
            if (stripos($meta, 'p:domain_verify') === false) {
                continue;
            }
            $meta_code = tcsocial_pinterest_extract_domain_verify($meta);
            if ($meta_code !== '' && hash_equals($code, $meta_code)) {
                $found = true;
                break;
            }
        }
    }

    return array(
        'ok'       => $found,
        'messages' => array(
            $found
                ? 'Pinterest: etiqueta de verificacion encontrada correctamente en el <head> publico.'
                : 'Pinterest: no se encontro en el HTML publico la etiqueta de verificacion guardada. Purga cache si acabas de cambiarla.',
        ),
    );
}

/**
 * Guarda el resultado de la ultima comprobacion Merchant.
 *
 * @param array $test
 */
function tcsocial_pinterest_store_merchant_test($test)
{
    $test = is_array($test) ? $test : array();
    $settings = tcsocial_network_get_settings();
    $config = isset($settings['providers']['pinterest']) && is_array($settings['providers']['pinterest'])
        ? $settings['providers']['pinterest']
        : array();

    $messages = array_map('sanitize_text_field', (array) ($test['messages'] ?? array()));
    $config['merchant_last_test_at'] = current_time('mysql');
    $config['merchant_last_test_ok'] = !empty($test['ok']) ? 1 : 0;
    $config['merchant_last_test_error'] = !empty($test['ok']) ? '' : implode(' ', $messages);
    $settings['providers']['pinterest'] = $config;
    tcsocial_network_save_settings($settings);
}

/**
 * @param array $config
 */
function tcsocial_pinterest_render_connection($config)
{
    $config = is_array($config) ? $config : array();
    $has_secret = !empty($config['app_secret_enc']);
    $has_token = !empty($config['access_token_enc']);
    $boards = isset($config['boards']) && is_array($config['boards']) ? $config['boards'] : array();
    $selected_id = isset($config['board_id']) ? (string) $config['board_id'] : '';

    echo '<p class="seo-social-help"><strong>Redirect URL para Pinterest Developer:</strong><br><code>' . esc_html(tcsocial_pinterest_redirect_uri()) . '</code></p>';
    echo '<p class="seo-social-help">OAuth 2.0 · scopes: <code>' . esc_html(TCSOCIAL_PINTEREST_SCOPES) . '</code>. Publica Pins organicos en un tablero de la cuenta autorizada.</p>';

    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
    echo '<input type="hidden" name="action" value="tcsocial_network_save_connection"><input type="hidden" name="provider" value="pinterest">';
    wp_nonce_field('tcsocial_network_save_connection');

    echo '<div class="seo-social-grid">';
    echo '<div class="seo-social-field"><label>Pinterest App ID</label><input type="text" name="connection[app_id]" value="' . esc_attr(isset($config['app_id']) ? $config['app_id'] : '') . '" autocomplete="off"></div>';
    echo '<div class="seo-social-field"><label>Pinterest App Secret</label><input type="password" name="connection[app_secret]" value="" autocomplete="new-password" placeholder="' . esc_attr($has_secret ? 'Secret guardado; deja vacio para conservarlo' : 'App Secret de Pinterest') . '"></div>';
    echo '</div>';

    echo '<div class="seo-social-field" style="margin-top:12px">' . '<label>' . esc_html__( 'Tablero destino', 'taxocommerce-social' ) . '</label>';
    if (!empty($boards)) {
        echo '<select name="connection[board_id]">' . '<option value="">' . esc_html__( 'Selecciona un tablero', 'taxocommerce-social' ) . '</option>';
        foreach ($boards as $board) {
            if (!is_array($board) || empty($board['id'])) {
                continue;
            }
            $label = !empty($board['name']) ? $board['name'] : 'Tablero #' . $board['id'];
            echo '<option value="' . esc_attr((string) $board['id']) . '" ' . selected($selected_id, (string) $board['id'], false) . '>' . esc_html($label . ' (#' . $board['id'] . ')') . '</option>';
        }
        echo '</select>';
    } else {
        echo '<input type="text" inputmode="numeric" name="connection[board_id]" value="' . esc_attr($selected_id) . '" placeholder="ID numerico del tablero">';
    }
    echo '<p class="seo-social-help">' . esc_html__( 'Tras OAuth, el plugin carga los tableros publicos disponibles y permite elegir el destino.', 'taxocommerce-social' ) . '</p>' . '</div>';

    echo '<div class="seo-social-field" style="margin-top:12px">' . '<label>' . esc_html__( 'Modo de publicacion', 'taxocommerce-social' ) . '</label>' . '<input type="text" value="Pin con imagen destacada + titulo + descripcion + enlace UTM" readonly>' . '</div>';

    echo '<details style="margin-top:12px"><summary style="cursor:pointer">Token manual (opcional)</summary><div class="seo-social-field" style="margin-top:10px"><label>Access Token</label><input type="password" name="connection[access_token]" value="" autocomplete="new-password" placeholder="' . esc_attr($has_token ? 'Token guardado; deja vacio para conservarlo' : 'Solo para pruebas o token generado fuera del plugin') . '"><p class="seo-social-help">El token se cifra antes de guardarlo. OAuth es la opcion recomendada.</p></div></details>';

    if (!empty($config['token_expires_at'])) {
        echo '<p class="seo-social-help"><strong>Access Token:</strong> caduca ' . esc_html(wp_date(get_option('date_format') . ' ' . get_option('time_format'), absint($config['token_expires_at']))) . '. El plugin intenta renovarlo automaticamente con el refresh token.</p>';
    }

    if (!empty($config['last_test_at'])) {
        $ok = !empty($config['last_test_ok']);
        echo '<p><span class="seo-social-state ' . ($ok ? 'is-ok' : 'is-failed') . '">' . esc_html($ok ? 'Ultima prueba correcta' : 'Ultima prueba fallida') . '</span> <small>' . esc_html($config['last_test_at']) . '</small></p>';
        if (!$ok && !empty($config['last_test_error'])) {
            echo '<p class="seo-social-help" style="color:#b32d2e">' . esc_html($config['last_test_error']) . '</p>';
        }
    }

    echo '<div class="seo-social-actions">' . '<button type="submit" class="button">' . esc_html__( 'Guardar configuracion', 'taxocommerce-social' ) . '</button>';
    if ($has_token && $selected_id !== '') {
        echo '<button type="submit" class="button button-primary" name="test_after_save" value="1">' . esc_html__( 'Guardar y probar conexion', 'taxocommerce-social' ) . '</button>';
    }
    echo '</div></form>';

    if (!empty($config['app_id']) && $has_secret) {
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin-top:10px">';
        echo '<input type="hidden" name="action" value="tcsocial_pinterest_oauth_start">';
        wp_nonce_field('tcsocial_pinterest_oauth_start');
        echo '<button type="submit" class="button button-primary">' . esc_html__( 'Conectar / renovar autorizacion con Pinterest', 'taxocommerce-social' ) . '</button>';
        echo '</form>';
    } else {
        echo '<p class="seo-social-help">' . esc_html__( 'Guarda primero el App ID y el App Secret. Despues aparecera el boton para autorizar Pinterest.', 'taxocommerce-social' ) . '</p>';
    }

    if ($has_token || !empty($config['enabled'])) {
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin-top:10px">';
        echo '<input type="hidden" name="action" value="tcsocial_network_disconnect"><input type="hidden" name="provider" value="pinterest">';
        wp_nonce_field('tcsocial_network_disconnect');
        echo '<button type="submit" class="button button-link-delete" onclick="return confirm(\'Se eliminaran los tokens guardados de Pinterest. ¿Continuar?\');">Desconectar Pinterest</button>';
        echo '</form>';
    }

    echo '<p class="seo-social-help" style="margin-top:12px">' . esc_html__( 'Para publicar, cada entrada o landing debe tener una imagen destacada accesible publicamente por Pinterest.', 'taxocommerce-social' ) . '</p>';
}
