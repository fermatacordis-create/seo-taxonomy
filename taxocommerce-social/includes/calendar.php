<?php
/**
 * TaxoCommerce SEO - Calendario visual de publicaciones sociales.
 *
 * Lee únicamente la agenda propia del módulo Social y la presenta por mes. No mezcla campañas, ofertas ni reglas especiales de Noticias.
 */

defined('ABSPATH') || exit;

/**
 * @param string $month YYYY-MM
 * @param string $provider
 * @return string
 */
function tcsocial_calendar_admin_url($month = '', $provider = '')
{
    $args = array();
    if ($month !== '') {
        $args['social_month'] = $month;
    }
    if ($provider !== '') {
        $args['social_calendar_provider'] = sanitize_key($provider);
    }

    return function_exists('tcsocial_network_admin_url')
        ? tcsocial_network_admin_url('calendar', $args)
        : add_query_arg(
            array_merge(
                array(
                    'page'          => 'taxocommerce-social',
                    'tab'           => 'social',
                    'social_subtab' => 'calendar',
                ),
                $args
            ),
            admin_url('admin.php')
        );
}

/**
 * @param string $value
 * @return DateTimeImmutable
 */
function tcsocial_calendar_month($value)
{
    $timezone = wp_timezone();
    $value = trim((string) $value);

    if (preg_match('/^\d{4}-\d{2}$/', $value)) {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value . '-01', $timezone);
        if ($date instanceof DateTimeImmutable && $date->format('Y-m') === $value) {
            return $date;
        }
    }

    return (new DateTimeImmutable('now', $timezone))->modify('first day of this month')->setTime(0, 0, 0);
}

/**
 * Convierte la agenda canónica del Programador en eventos de calendario.
 *
 * @return array<int,array<string,mixed>>
 */
function tcsocial_calendar_events()
{
    if (!function_exists('tcsocial_network_scheduler_exportable_agenda_rows')) {
        return array();
    }

    $providers = function_exists('tcsocial_network_get_providers')
        ? tcsocial_network_get_providers()
        : array();
    $timezone = wp_timezone();
    $events = array();
    $campaign_rows = array();

    // Optional Campaigns integration: identify exact schedule ownership.
    // Avoid editing/cancelling a campaign if more than one record matches.
    if (function_exists('tccampaigns_social_scheduled_rows')) {
        foreach ((array) tccampaigns_social_scheduled_rows() as $campaign) {
            $campaign_time = absint($campaign['timestamp'] ?? 0);
            $campaign_id = absint($campaign['campaign_id'] ?? 0);
            $product_id = absint($campaign['product_id'] ?? 0);
            $campaign_provider = sanitize_key($campaign['provider'] ?? '');
            if (!$campaign_id || !$product_id || !$campaign_time || !$campaign_provider) {
                continue;
            }
            $key = $product_id . '|' . $campaign_provider . '|' . wp_date('Y-m-d H:i', $campaign_time, $timezone);
            $campaign_rows[$key][] = array('campaign_id' => $campaign_id, 'timestamp' => $campaign_time);
        }
    }

    foreach ((array) tcsocial_network_scheduler_exportable_agenda_rows() as $row) {
        $row = array_values((array) $row);
        if (count($row) < 7) {
            continue;
        }

        $content_id = absint($row[0]);
        $title = sanitize_text_field((string) $row[1]);
        $raw_type = (string) $row[2];
        $provider = sanitize_key((string) $row[3]);
        $raw_date = trim((string) $row[4]);
        $status = sanitize_key((string) $row[6]);

        $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $raw_date, $timezone);
        $errors = DateTimeImmutable::getLastErrors();
        if (!$date instanceof DateTimeImmutable || (is_array($errors) && (!empty($errors['warning_count']) || !empty($errors['error_count'])))) {
            continue;
        }

        $type_key = 'other';
        $type_label = __( 'Otro', 'taxocommerce-social' );
        $source = 'social';
        $campaign_id = 0;
        $stored_timestamp = 0;
        if ('post' === $raw_type) {
            $type_key = 'post';
            $type_label = __( 'Entrada', 'taxocommerce-social' );
        } elseif ('page' === $raw_type) {
            $type_key = 'page';
            $type_label = __( 'Landing', 'taxocommerce-social' );
        } elseif ('Campaña / Producto' === $raw_type) {
            $type_key = 'campaign';
            $type_label = __( 'Campaña', 'taxocommerce-social' );
            $source = 'campaign';
            $key = $content_id . '|' . $provider . '|' . $raw_date;
            if (isset($campaign_rows[$key]) && 1 === count($campaign_rows[$key])) {
                $campaign_id = $campaign_rows[$key][0]['campaign_id'];
                $stored_timestamp = $campaign_rows[$key][0]['timestamp'];
            }
        }

        if ('social' === $source && function_exists('tcsocial_network_get_scheduled_timestamp')) {
            $stored_timestamp = tcsocial_network_get_scheduled_timestamp($content_id, $provider);
            if (wp_date('Y-m-d H:i', $stored_timestamp, $timezone) !== $raw_date) {
                $stored_timestamp = 0;
            }
        }

        $provider_label = ucfirst($provider);
        if (isset($providers[$provider]['label'])) {
            $provider_label = (string) $providers[$provider]['label'];
        }

        $events[] = array(
            'content_id'     => $content_id,
            'title'          => $title !== '' ? $title : sprintf( /* translators: %d: WordPress content ID. */ __( 'Contenido #%d', 'taxocommerce-social' ), $content_id ),
            'type_key'       => $type_key,
            'type_label'     => $type_label,
            'provider'       => $provider,
            'provider_label' => $provider_label,
            'timestamp'      => $stored_timestamp ?: $date->getTimestamp(),
            'source'         => $source,
            'campaign_id'    => $campaign_id,
            'can_manage'     => $stored_timestamp > 0 && ('social' === $source || $campaign_id > 0),
            'date_key'       => $date->format('Y-m-d'),
            'time'           => $date->format('H:i'),
            'status'         => $status,
            'edit_url'       => $content_id ? get_edit_post_link($content_id) : '',
        );
    }

    usort(
        $events,
        static function ($a, $b) {
            $diff = ((int) $a['timestamp']) <=> ((int) $b['timestamp']);
            if (0 !== $diff) {
                return $diff;
            }
            return strcmp((string) $a['provider'], (string) $b['provider']);
        }
    );

    return $events;
}

/**
 * @param array<int,array<string,mixed>> $events
 * @return bool
 */
function tcsocial_calendar_has_same_network_collision($events)
{
    $seen = array();
    foreach ((array) $events as $event) {
        $provider = sanitize_key((string) ($event['provider'] ?? ''));
        $time = sanitize_text_field((string) ($event['time'] ?? ''));
        if ($provider === '' || $time === '') {
            continue;
        }
        $key = $provider . '|' . $time;
        if (isset($seen[$key])) {
            return true;
        }
        $seen[$key] = true;
    }
    return false;
}

/**
 * @param array<int,array<string,mixed>> $events
 * @return array<string,array<int,array<string,mixed>>>
 */
function tcsocial_calendar_group_by_day($events)
{
    $days = array();
    foreach ((array) $events as $event) {
        $key = (string) ($event['date_key'] ?? '');
        if ($key === '') {
            continue;
        }
        if (!isset($days[$key])) {
            $days[$key] = array();
        }
        $days[$key][] = $event;
    }
    return $days;
}

/**
 * Read the owning module's live schedule without modifying it.
 *
 * @param string $source      social or campaign.
 * @param int    $content_id  Post/product ID.
 * @param string $provider    Network identifier.
 * @param int    $campaign_id Campaign ID (only for campaign schedules).
 * @return int
 */
function tcsocial_calendar_current_schedule($source, $content_id, $provider, $campaign_id = 0)
{
    $content_id = absint($content_id);
    $campaign_id = absint($campaign_id);
    $provider = sanitize_key($provider);
    if (!$content_id || !$provider || !function_exists('tcsocial_network_get_provider') || !tcsocial_network_get_provider($provider)) {
        return 0;
    }
    if ('social' === $source && 0 === $campaign_id && function_exists('tcsocial_network_get_scheduled_timestamp')) {
        return (int) tcsocial_network_get_scheduled_timestamp($content_id, $provider);
    }
    if ('campaign' === $source && $campaign_id && function_exists('tccampaigns_social_get_scheduled_timestamp')) {
        return (int) tccampaigns_social_get_scheduled_timestamp($campaign_id, $content_id, $provider);
    }
    return 0;
}

/**
 * Apply a single schedule change only when its original timestamp is unchanged.
 *
 * @param string $operation cancel or edit.
 * @param string $source social or campaign.
 * @param int    $content_id Content ID.
 * @param string $provider Network key.
 * @param int    $campaign_id Campaign owner ID.
 * @param int    $expected_timestamp Original exact timestamp.
 * @param int    $new_timestamp New timestamp for editing only.
 * @param string $new_status Desired schedule state: programada or pausada.
 * @return true|WP_Error
 */
function tcsocial_calendar_change_schedule($operation, $source, $content_id, $provider, $campaign_id, $expected_timestamp, $new_timestamp = 0, $new_status = 'programada')
{
    $content_id = absint($content_id);
    $campaign_id = absint($campaign_id);
    $expected_timestamp = absint($expected_timestamp);
    $new_timestamp = absint($new_timestamp);
    $provider = sanitize_key($provider);

    if (!in_array($operation, array('edit', 'cancel'), true)
        || !in_array($source, array('social', 'campaign'), true)
        || !$content_id || !$expected_timestamp
        || ('campaign' === $source && !$campaign_id)
        || ('social' === $source && $campaign_id)
    ) {
        return new WP_Error('invalid_schedule', __('La programación seleccionada no es válida.', 'taxocommerce-social'));
    }

    $current = tcsocial_calendar_current_schedule($source, $content_id, $provider, $campaign_id);
    if (!$current || $current !== $expected_timestamp) {
        return new WP_Error('stale_schedule', __('La programación ya no existe o se ha modificado. Actualiza el calendario.', 'taxocommerce-social'));
    }

    if ('cancel' === $operation) {
        if ('campaign' === $source) {
            if (!function_exists('tccampaigns_social_clear_schedule')) {
                return new WP_Error('campaign_missing', __('El módulo de campañas no está disponible.', 'taxocommerce-social'));
            }
            tccampaigns_social_clear_schedule($campaign_id, $content_id, $provider);
        } else {
            tcsocial_network_clear_scheduled_publication($content_id, $provider);
            // Clean up legacy WP-Cron too; do not publish any content.
            wp_clear_scheduled_hook('seo_social_network_publish_manual_scheduled', array($content_id, $provider));
        }
        if (tcsocial_calendar_current_schedule($source, $content_id, $provider, $campaign_id)) {
            return new WP_Error('cancel_failed', __('No se ha podido confirmar la cancelación.', 'taxocommerce-social'));
        }
        return true;
    }

    $new_status = sanitize_key($new_status);
    if (!in_array($new_status, array('programada', 'pausada'), true)) {
        return new WP_Error('invalid_state', __('Estado de programación no válido.', 'taxocommerce-social'));
    }
    if ('campaign' === $source && 'pausada' === $new_status) {
        return new WP_Error('unsupported_campaign_state', __('La pausa de campañas se gestiona desde el módulo de campañas.', 'taxocommerce-social'));
    }
    // Keep an already expired date when pausing; resuming always requires
    // a future date. Any intentional date change must also be in the future.
    if (($new_status === 'programada' || $new_timestamp !== $expected_timestamp)
        && $new_timestamp <= time() + 30) {
        return new WP_Error('invalid_date', __('Selecciona una fecha y hora futuras.', 'taxocommerce-social'));
    }

    if ('social' === $source && 'pausada' === $new_status) {
        return tcsocial_network_pause_scheduled_publication($content_id, $provider, $new_timestamp);
    }

    if ('campaign' === $source) {
        if (!function_exists('tccampaigns_social_set_schedule')) {
            return new WP_Error('campaign_missing', __('El módulo de campañas no está disponible.', 'taxocommerce-social'));
        }
        $result = tccampaigns_social_set_schedule($campaign_id, $content_id, $provider, $new_timestamp);
    } else {
        $result = tcsocial_network_set_scheduled_publication($content_id, $provider, $new_timestamp);
    }
    if (is_wp_error($result)) {
        return $result;
    }
    if (!tcsocial_calendar_current_schedule($source, $content_id, $provider, $campaign_id)
        || ('social' === $source && 'programada' !== tcsocial_network_get_schedule_state($content_id, $provider))) {
        return new WP_Error('save_failed', __('No se ha podido verificar la reprogramación.', 'taxocommerce-social'));
    }
    return true;
}

/**
 * Handle a single calendar event edit or cancellation from a nonce-protected POST.
 *
 * @return void
 */
function tcsocial_calendar_handle_manage()
{
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('No tienes permisos para modificar programaciones.', 'taxocommerce-social'));
    }

    $operation = isset($_POST['calendar_operation']) ? sanitize_key(wp_unslash($_POST['calendar_operation'])) : '';
    $source = isset($_POST['calendar_source']) ? sanitize_key(wp_unslash($_POST['calendar_source'])) : '';
    $content_id = isset($_POST['calendar_content_id']) ? absint(wp_unslash($_POST['calendar_content_id'])) : 0;
    $provider = isset($_POST['calendar_provider']) ? sanitize_key(wp_unslash($_POST['calendar_provider'])) : '';
    $campaign_id = isset($_POST['calendar_campaign_id']) ? absint(wp_unslash($_POST['calendar_campaign_id'])) : 0;
    $expected = isset($_POST['calendar_expected']) ? absint(wp_unslash($_POST['calendar_expected'])) : 0;
    $month = isset($_POST['calendar_month']) ? sanitize_text_field(wp_unslash($_POST['calendar_month'])) : '';
    $filter = isset($_POST['calendar_filter']) ? sanitize_key(wp_unslash($_POST['calendar_filter'])) : '';
    $date_value = isset($_POST['calendar_datetime']) ? sanitize_text_field(wp_unslash($_POST['calendar_datetime'])) : '';
    $status_value = isset($_POST['calendar_status']) ? sanitize_key(wp_unslash($_POST['calendar_status'])) : '';

    check_admin_referer('tcsocial_calendar_manage_' . $source . '_' . $content_id . '_' . $provider . '_' . $campaign_id . '_' . $expected);

    $new_timestamp = 0;
    if ('edit' === $operation) {
        $parsed = tcsocial_network_scheduler_parse_import_datetime($date_value);
        if (is_wp_error($parsed)) {
            $result = $parsed;
        } else {
            $new_timestamp = (int) $parsed;
        }
    }

    if (!isset($result)) {
        $result = tcsocial_calendar_change_schedule($operation, $source, $content_id, $provider, $campaign_id, $expected, $new_timestamp, $status_value);
    }

    $message = is_wp_error($result) ? 'error' : ('cancel' === $operation ? 'cancelled' : 'updated');
    if (is_wp_error($result)) {
        set_transient('tcsocial_calendar_error_' . get_current_user_id(), $result->get_error_message(), MINUTE_IN_SECONDS);
    }
    $redirect = tcsocial_calendar_admin_url(preg_match('/^\d{4}-\d{2}$/', $month) ? $month : '', $filter);
    wp_safe_redirect(add_query_arg('calendar_result', $message, $redirect));
    exit;
}
add_action('admin_post_tcsocial_calendar_manage', 'tcsocial_calendar_handle_manage');

/**
 * Render Edit and Cancel controls for one calendar occurrence.
 *
 * @param array $event The resolved agenda event.
 * @return void
 */
function tcsocial_calendar_render_event_actions($event, $view = 'calendar')
{
    if (empty($event['can_manage']) || !current_user_can('manage_options')) {
        return;
    }
    $source = sanitize_key($event['source']);
    $content_id = absint($event['content_id']);
    $provider = sanitize_key($event['provider']);
    $campaign_id = absint($event['campaign_id']);
    $expected = absint($event['timestamp']);
    $nonce_action = 'tcsocial_calendar_manage_' . $source . '_' . $content_id . '_' . $provider . '_' . $campaign_id . '_' . $expected;
    $month = wp_date('Y-m', $expected, wp_timezone());
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Navigation/filter value only.
    $filter = isset($_GET['social_calendar_provider']) ? sanitize_key(wp_unslash($_GET['social_calendar_provider'])) : '';

    $is_compact = 'compact' === $view;
    echo '<div class="seo-social-calendar-actions' . ($is_compact ? ' is-compact' : '') . '">';
    echo '<details><summary class="button button-small">' . esc_html__('Editar', 'taxocommerce-social') . '</summary>';
    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
    echo '<input type="hidden" name="action" value="tcsocial_calendar_manage">';
    echo '<input type="hidden" name="calendar_operation" value="edit">';
    echo '<input type="hidden" name="calendar_source" value="' . esc_attr($source) . '">';
    echo '<input type="hidden" name="calendar_content_id" value="' . esc_attr((string) $content_id) . '">';
    echo '<input type="hidden" name="calendar_provider" value="' . esc_attr($provider) . '">';
    echo '<input type="hidden" name="calendar_campaign_id" value="' . esc_attr((string) $campaign_id) . '">';
    echo '<input type="hidden" name="calendar_expected" value="' . esc_attr((string) $expected) . '">';
    echo '<input type="hidden" name="calendar_month" value="' . esc_attr($month) . '">';
    echo '<input type="hidden" name="calendar_filter" value="' . esc_attr($filter) . '">';
    wp_nonce_field($nonce_action);
    echo '<label>' . esc_html__('Nueva fecha y hora', 'taxocommerce-social') . '<input type="datetime-local" name="calendar_datetime" value="' . esc_attr(wp_date('Y-m-d\TH:i', $expected, wp_timezone())) . '" required></label>';
    if ('social' === $source) {
        $is_paused = 'pausada' === ($event['status'] ?? '');
        echo '<label>' . esc_html__('Estado', 'taxocommerce-social') . '<select name="calendar_status">';
        echo '<option value="programada"' . selected(!$is_paused, true, false) . '>' . esc_html__('Programada', 'taxocommerce-social') . '</option>';
        echo '<option value="pausada"' . selected($is_paused, true, false) . '>' . esc_html__('Pausada', 'taxocommerce-social') . '</option>';
        echo '</select></label>';
        echo '<p>' . esc_html__('Pausada conserva la fecha, pero impide la publicación automática. Para reanudar, elige una fecha futura.', 'taxocommerce-social') . '</p>';
    } else {
        echo '<input type="hidden" name="calendar_status" value="programada">';
        echo '<p>' . esc_html__('Estado: programada. Las campañas se gestionan desde su módulo.', 'taxocommerce-social') . '</p>';
    }

    if ('campaign' === $source) {
        echo '<p>' . esc_html__('Las campañas mantienen su hora fija y sus fechas permitidas.', 'taxocommerce-social') . '</p>';
    }
    echo '<button type="submit" class="button button-primary button-small">' . esc_html__('Guardar cambios', 'taxocommerce-social') . '</button></form></details>';

    echo '<details><summary class="button button-small">' . esc_html__('Borrar programación', 'taxocommerce-social') . '</summary>';
    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
    echo '<input type="hidden" name="action" value="tcsocial_calendar_manage">';
    echo '<input type="hidden" name="calendar_operation" value="cancel">';
    echo '<input type="hidden" name="calendar_source" value="' . esc_attr($source) . '">';
    echo '<input type="hidden" name="calendar_content_id" value="' . esc_attr((string) $content_id) . '">';
    echo '<input type="hidden" name="calendar_provider" value="' . esc_attr($provider) . '">';
    echo '<input type="hidden" name="calendar_campaign_id" value="' . esc_attr((string) $campaign_id) . '">';
    echo '<input type="hidden" name="calendar_expected" value="' . esc_attr((string) $expected) . '">';
    echo '<input type="hidden" name="calendar_month" value="' . esc_attr($month) . '">';
    echo '<input type="hidden" name="calendar_filter" value="' . esc_attr($filter) . '">';
    wp_nonce_field($nonce_action);
    echo '<p>' . esc_html__('Se cancelará solo esta programación, sin borrar el contenido ni otras redes.', 'taxocommerce-social') . '</p>';
    echo '<button type="submit" class="button button-secondary button-small">' . esc_html__('Confirmar borrado', 'taxocommerce-social') . '</button></form></details>';
    echo '</div>';
}

/**
 * Render de la subpestaña Calendario.
 */
function tcsocial_calendar_render_admin()
{
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only calendar month selector.
    $month = tcsocial_calendar_month(isset($_GET['social_month']) ? sanitize_text_field(wp_unslash($_GET['social_month'])) : '');
    $month_key = $month->format('Y-m');
    $month_start = $month->setTime(0, 0, 0);
    $month_end = $month->modify('last day of this month')->setTime(23, 59, 59);
    $today = new DateTimeImmutable('today', wp_timezone());
    $today_key = $today->format('Y-m-d');

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Filtro visual de calendario, solo lectura y sin operaciones de escritura.
    $provider_filter = isset($_GET['social_calendar_provider']) ? sanitize_key(wp_unslash($_GET['social_calendar_provider'])) : '';

    $providers = function_exists('tcsocial_network_get_providers')
        ? tcsocial_network_get_providers()
        : array();

    $events = array_values(
        array_filter(
            tcsocial_calendar_events(),
            static function ($event) use ($month_start, $month_end, $provider_filter) {
                $timestamp = (int) ($event['timestamp'] ?? 0);
                if ($timestamp < $month_start->getTimestamp() || $timestamp > $month_end->getTimestamp()) {
                    return false;
                }
                if ($provider_filter !== '' && (string) ($event['provider'] ?? '') !== $provider_filter) {
                    return false;
                }
                return true;
            }
        )
    );

    $by_day = tcsocial_calendar_group_by_day($events);
    $event_count = count($events);
    $occupied_days = count($by_day);
    $collision_days = 0;
    foreach ($by_day as $day_events) {
        if (tcsocial_calendar_has_same_network_collision($day_events)) {
            $collision_days++;
        }
    }

    $future_start = $today > $month_start ? $today : $month_start;
    $empty_future_days = 0;
    if ($future_start <= $month_end) {
        for ($cursor = $future_start; $cursor <= $month_end; $cursor = $cursor->modify('+1 day')) {
            if (empty($by_day[$cursor->format('Y-m-d')])) {
                $empty_future_days++;
            }
        }
    }

    $previous = $month->modify('-1 month')->format('Y-m');
    $next = $month->modify('+1 month')->format('Y-m');
    $current = (new DateTimeImmutable('now', wp_timezone()))->format('Y-m');

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only redirect feedback.
    $feedback = isset($_GET['calendar_result']) ? sanitize_key(wp_unslash($_GET['calendar_result'])) : '';
    if (in_array($feedback, array('cancelled', 'updated', 'error'), true)) {
        $texts = array(
            'cancelled' => __('Programación cancelada. El contenido no se ha eliminado.', 'taxocommerce-social'),
            'updated' => __('Programación actualizada.', 'taxocommerce-social'),
        );
        $notice_type = 'error' === $feedback ? 'error' : 'success';
        $message = isset($texts[$feedback]) ? $texts[$feedback] : get_transient('tcsocial_calendar_error_' . get_current_user_id());
        if ('error' === $feedback) {
            delete_transient('tcsocial_calendar_error_' . get_current_user_id());
        }
        if ($message) {
            echo '<div class="notice notice-' . esc_attr($notice_type) . ' inline"><p>' . esc_html($message) . '</p></div>';
        }
    }

    echo '<section class="seo-social-card seo-social-calendar-head">';
    echo '<div class="seo-social-intro">' . '<div>' . '<h2>' . esc_html__( 'Calendario de publicaciones', 'taxocommerce-social' ) . '</h2>' . '<p>' . esc_html__( 'Vista mensual exclusiva de publicaciones en redes sociales. No incorpora campañas, ofertas ni reglas especiales de Noticias.', 'taxocommerce-social' ) . '</p>' . '</div>' . '<span class="seo-social-state is-scheduled">' . esc_html__( 'Agenda visual', 'taxocommerce-social' ) . '</span>' . '</div>';

    echo '<div class="seo-social-calendar-toolbar">';
    echo '<div class="seo-social-calendar-nav">';
    echo '<a class="button" href="' . esc_url(tcsocial_calendar_admin_url($previous, $provider_filter)) . '">&larr; Mes anterior</a>';
    echo '<a class="button" href="' . esc_url(tcsocial_calendar_admin_url($current, $provider_filter)) . '">Hoy</a>';
    echo '<a class="button" href="' . esc_url(tcsocial_calendar_admin_url($next, $provider_filter)) . '">Mes siguiente &rarr;</a>';
    echo '</div>';

    echo '<form method="get" class="seo-social-calendar-filter">';
    echo '<input type="hidden" name="page" value="taxocommerce-social">';
    echo '<input type="hidden" name="tab" value="calendar">';
    echo '<input type="hidden" name="social_subtab" value="calendar">';
    echo '<input type="hidden" name="social_month" value="' . esc_attr($month_key) . '">';
    echo '<label>' . '<span>' . esc_html__( 'Red', 'taxocommerce-social' ) . '</span>' . '<select name="social_calendar_provider">' . '<option value="">' . esc_html__( 'Todas las redes', 'taxocommerce-social' ) . '</option>';
    foreach ($providers as $provider_key => $provider) {
        $label = isset($provider['label']) ? (string) $provider['label'] : ucfirst($provider_key);
        echo '<option value="' . esc_attr($provider_key) . '" ' . selected($provider_filter, $provider_key, false) . '>' . esc_html($label) . '</option>';
    }
    echo '</select>' . '</label>' . '<button class="button" type="submit">' . esc_html__( 'Aplicar', 'taxocommerce-social' ) . '</button>' . '</form>';
    echo '</div>';

    echo '<div class="seo-social-calendar-title-row"><h3>' . esc_html(ucfirst(wp_date('F Y', $month->getTimestamp(), wp_timezone()))) . '</h3>';
    echo '<div class="seo-social-calendar-legend">';
    echo '<span class="seo-social-calendar-legend-item is-post">' . esc_html__( 'Entrada', 'taxocommerce-social' ) . '</span>';
    echo '<span class="seo-social-calendar-legend-item is-page">' . esc_html__( 'Landing', 'taxocommerce-social' ) . '</span>';
        echo '<span class="seo-social-calendar-legend-item is-collision">' . esc_html__( 'Colisión', 'taxocommerce-social' ) . '</span>';
    echo '</div></div>';

    echo '<div class="seo-social-calendar-metrics">';
    echo '<div><strong>' . esc_html((string) $event_count) . '</strong><span>publicaciones</span></div>';
    echo '<div><strong>' . esc_html((string) $occupied_days) . '</strong><span>días ocupados</span></div>';
    echo '<div><strong>' . esc_html((string) $empty_future_days) . '</strong><span>días libres futuros</span></div>';
    echo '<div class="' . ($collision_days ? 'has-warning' : '') . '"><strong>' . esc_html((string) $collision_days) . '</strong><span>días con colisión</span></div>';
    echo '</div>';
    echo '</section>';

    $weekday_labels = array('Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom');
    $grid_start = $month_start->modify('-' . ((int) $month_start->format('N') - 1) . ' days');
    $grid_end = $month_end->modify('+' . (7 - (int) $month_end->format('N')) . ' days');

    echo '<section class="seo-social-card seo-social-calendar-card">';
    echo '<div class="seo-social-calendar-scroll"><div class="seo-social-calendar-grid">';
    foreach ($weekday_labels as $weekday) {
        echo '<div class="seo-social-calendar-weekday">' . esc_html($weekday) . '</div>';
    }

    for ($day = $grid_start; $day <= $grid_end; $day = $day->modify('+1 day')) {
        $date_key = $day->format('Y-m-d');
        $in_month = $day->format('Y-m') === $month_key;
        $is_today = $date_key === $today_key;
        $is_past = $day < $today;
        $day_events = $in_month && isset($by_day[$date_key]) ? $by_day[$date_key] : array();
        $collision = tcsocial_calendar_has_same_network_collision($day_events);
        $is_empty_future = $in_month && !$is_past && !$day_events;

        $classes = array('seo-social-calendar-day');
        if (!$in_month) {
            $classes[] = 'is-outside';
        }
        if ($is_today) {
            $classes[] = 'is-today';
        }
        if ($is_past) {
            $classes[] = 'is-past';
        }
        if ($collision) {
            $classes[] = 'has-collision';
        }
        if ($is_empty_future) {
            $classes[] = 'is-empty-future';
        }

        echo '<div class="' . esc_attr(implode(' ', $classes)) . '">';
        echo '<div class="seo-social-calendar-day-head"><span class="seo-social-calendar-day-number">' . esc_html($day->format('j')) . '</span>';
        if ($in_month && count($day_events) > 1) {
            echo '<span class="seo-social-calendar-day-count">' . esc_html((string) count($day_events)) . '</span>';
        }
        echo '</div>';

        if ($in_month && $day_events) {
            foreach ($day_events as $event) {
                $type_key = sanitize_html_class((string) $event['type_key']);
                $title = (string) $event['title'];
                $link = (string) $event['edit_url'];

                echo '<div class="seo-social-calendar-event is-' . esc_attr($type_key) . '" title="' . esc_attr($event['type_label'] . ' · ' . $event['provider_label'] . ' · ' . $event['time'] . ' · ' . $title) . '">';
                echo '<div class="seo-social-calendar-event-meta"><span>' . esc_html((string) $event['time']) . '</span><span>' . esc_html((string) $event['provider_label']) . '</span></div>';
                if ($link !== '') {
                    echo '<a href="' . esc_url($link) . '">' . esc_html($title) . '</a>';
                } else {
                    echo '<span class="seo-social-calendar-event-title">' . esc_html($title) . '</span>';
                }
                echo '<small>' . esc_html((string) $event['type_label']) . '</small>';
                tcsocial_calendar_render_event_actions($event);
                echo '</div>';
            }
        } elseif ($is_empty_future) {
            echo '<span class="seo-social-calendar-empty-label">' . esc_html__( 'Sin publicaciones', 'taxocommerce-social' ) . '</span>';
        }

        echo '</div>';
    }

    echo '</div></div>';
    echo '</section>';

    echo '<section class="seo-social-card">';
    echo '<div class="seo-social-intro">' . '<div>' . '<h2>' . esc_html__( 'Vista compacta', 'taxocommerce-social' ) . '</h2>' . '<p>' . esc_html__( 'Listado cronológico del mes. Útil en móvil y para revisar rápidamente horas, redes y duplicados reales.', 'taxocommerce-social' ) . '</p>' . '</div>' . '</div>';

    if (!$events) {
        echo '<p class="seo-social-code-note">No hay publicaciones programadas para este mes' . ($provider_filter !== '' ? ' en la red seleccionada' : '') . '.</p>';
        echo '</section>';
        return;
    }

    echo '<div class="seo-social-calendar-list">';
    foreach ($by_day as $date_key => $day_events) {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $date_key, wp_timezone());
        if (!$date instanceof DateTimeImmutable) {
            continue;
        }
        $collision = tcsocial_calendar_has_same_network_collision($day_events);
        echo '<div class="seo-social-calendar-list-day' . ($collision ? ' has-collision' : '') . '">';
        echo '<div class="seo-social-calendar-list-date"><strong>' . esc_html(wp_date('D j M', $date->getTimestamp(), wp_timezone())) . '</strong><span>' . esc_html((string) count($day_events)) . ' publicación' . (count($day_events) === 1 ? '' : 'es') . '</span></div>';
        echo '<div class="seo-social-calendar-list-events">';
        foreach ($day_events as $event) {
            echo '<div class="seo-social-calendar-list-event is-' . esc_attr(sanitize_html_class((string) $event['type_key'])) . '">';
            echo '<strong>' . esc_html((string) $event['time']) . '</strong><span class="seo-social-calendar-list-provider">' . esc_html((string) $event['provider_label']) . '</span>';
            if (!empty($event['edit_url'])) {
                echo '<a href="' . esc_url((string) $event['edit_url']) . '">' . esc_html((string) $event['title']) . '</a>';
            } else {
                echo '<span>' . esc_html((string) $event['title']) . '</span>';
            }
            echo '<small>' . esc_html((string) $event['type_label']) . ' · ' . esc_html(('pausada' === $event['status']) ? __('Pausada', 'taxocommerce-social') : __('Programada', 'taxocommerce-social')) . '</small>';
            tcsocial_calendar_render_event_actions($event, 'compact');
            echo '</div>';
        }
        echo '</div></div>';
    }
    echo '</div>';
    echo '</section>';
}
