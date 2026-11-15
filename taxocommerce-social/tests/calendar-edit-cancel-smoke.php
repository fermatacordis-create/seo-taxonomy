<?php
/**
 * Safe calendar edit/cancel tests, without database or social network access.
 *
 * Run: php taxocommerce-social/tests/calendar-edit-cancel-smoke.php
 *
 * @package TaxoCommerce_Social
 */

declare(strict_types=1);
define('ABSPATH', __DIR__ . '/');
define('MINUTE_IN_SECONDS', 60);

class WP_Error {
    private $message;
    public function __construct($code, $message) { $this->message = (string) $message; }
    public function get_error_message() { return $this->message; }
}
function __( $text, $domain = '' ) { return $text; }
function esc_html__($text, $domain = '') { return htmlspecialchars((string) $text, ENT_QUOTES); }
function is_wp_error($value) { return $value instanceof WP_Error; }
function add_action($name, $callback) {}
function absint($value) { return abs((int) $value); }
function sanitize_key($value) { return preg_replace('/[^a-z0-9_-]/', '', strtolower((string) $value)); }
function sanitize_text_field($value) { return trim((string) $value); }
function wp_timezone() { return new DateTimeZone('UTC'); }
function wp_date($format, $time, $timezone = null) { return gmdate($format, (int) $time); }
function get_edit_post_link($id) { return 'https://example.invalid/edit/' . absint($id); }
function current_user_can($capability) { return 'manage_options' === $capability; }
function admin_url($path) { return 'https://example.invalid/wp-admin/' . $path; }
function wp_nonce_field($action) { echo '<input name="_wpnonce" value="' . htmlspecialchars($action, ENT_QUOTES) . '">'; }
function esc_attr($text) { return htmlspecialchars((string) $text, ENT_QUOTES); }
function esc_html($text) { return htmlspecialchars((string) $text, ENT_QUOTES); }
function esc_url($text) { return htmlspecialchars((string) $text, ENT_QUOTES); }
function selected($selected, $current = true, $echo = true) { $attr = ($selected == $current) ? ' selected="selected"' : ''; if ($echo) { echo $attr; } return $attr; }
function get_current_user_id() { return 1; }

$GLOBALS['test_social'] = array(
    '101|facebook'  => strtotime('2030-01-01 22:00:00 UTC'),
    '101|pinterest' => strtotime('2030-01-02 22:00:00 UTC'),
    '102|facebook'  => strtotime('2025-01-02 22:00:00 UTC'),
);
$GLOBALS['test_campaigns'] = array(
    '55|202|pinterest' => strtotime('2030-01-03 20:00:00 UTC'),
    '56|203|pinterest' => strtotime('2030-01-04 20:00:00 UTC'),
    '57|203|pinterest' => strtotime('2030-01-04 20:00:00 UTC'),
);
$GLOBALS['test_cleared'] = array();
$GLOBALS['test_social_states'] = array();

function tcsocial_network_get_provider($provider) {
    return in_array($provider, array('facebook', 'pinterest'), true) ? array('label' => $provider) : null;
}
function tcsocial_network_get_providers() {
    return array('facebook' => array('label' => 'Facebook'), 'pinterest' => array('label' => 'Pinterest'));
}
function tcsocial_network_get_scheduled_timestamp($id, $provider) {
    return $GLOBALS['test_social'][absint($id) . '|' . sanitize_key($provider)] ?? 0;
}
function tcsocial_network_clear_scheduled_publication($id, $provider) {
    unset($GLOBALS['test_social'][absint($id) . '|' . sanitize_key($provider)]);
    unset($GLOBALS['test_social_states'][absint($id) . '|' . sanitize_key($provider)]);
}
function wp_clear_scheduled_hook($hook, $args) {
    $GLOBALS['test_cleared'][] = $hook . '|' . implode('|', (array) $args);
    return 1;
}
function tcsocial_network_set_scheduled_publication($id, $provider, $when) {
    if ($when <= time() + 30) {
        return new WP_Error('invalid_date', 'Past schedule');
    }
    $key = absint($id) . '|' . sanitize_key($provider);
    $GLOBALS['test_social'][$key] = (int) $when;
    $GLOBALS['test_social_states'][$key] = 'programada';
    return true;
}
function tcsocial_network_get_schedule_state($id, $provider) {
    $key = absint($id) . '|' . sanitize_key($provider);
    if (empty($GLOBALS['test_social'][$key])) { return ''; }
    return $GLOBALS['test_social_states'][$key] ?? 'programada';
}
function tcsocial_network_pause_scheduled_publication($id, $provider, $when) {
    $key = absint($id) . '|' . sanitize_key($provider);
    if (empty($GLOBALS['test_social'][$key])) { return new WP_Error('missing', 'Schedule not found'); }
    $GLOBALS['test_social'][$key] = (int) $when;
    $GLOBALS['test_social_states'][$key] = 'pausada';
    wp_clear_scheduled_hook('tcsocial_network_publish_manual_scheduled', array($id, $provider));
    return true;
}
function tccampaigns_social_get_scheduled_timestamp($campaign_id, $product_id, $provider) {
    return $GLOBALS['test_campaigns'][absint($campaign_id) . '|' . absint($product_id) . '|' . sanitize_key($provider)] ?? 0;
}
function tccampaigns_social_clear_schedule($campaign_id, $product_id, $provider) {
    unset($GLOBALS['test_campaigns'][absint($campaign_id) . '|' . absint($product_id) . '|' . sanitize_key($provider)]);
}
function tccampaigns_social_set_schedule($campaign_id, $product_id, $provider, $when) {
    $GLOBALS['test_campaigns'][absint($campaign_id) . '|' . absint($product_id) . '|' . sanitize_key($provider)] = (int) $when;
    return true;
}
function tccampaigns_social_scheduled_rows() {
    $rows = array();
    foreach ($GLOBALS['test_campaigns'] as $key => $timestamp) {
        list($campaign_id, $product_id, $provider) = explode('|', $key, 3);
        $rows[] = array(
            'campaign_id' => (int) $campaign_id,
            'product_id'  => (int) $product_id,
            'provider'    => $provider,
            'timestamp'   => $timestamp,
        );
    }
    return $rows;
}
function tcsocial_network_scheduler_exportable_agenda_rows() {
    return array(
        array('101', 'Spanish post', 'post', 'facebook', '2030-01-01 22:00', 'UTC', 'programada', ''),
        array('101', 'Spanish post', 'post', 'pinterest', '2030-01-02 22:00', 'UTC', 'programada', ''),
        array('102', 'Old foreign page', 'page', 'facebook', '2025-01-02 22:00', 'UTC', 'programada', ''),
        array('202', 'Campaign 55 / product', 'Campaña / Producto', 'pinterest', '2030-01-03 20:00', 'UTC', 'programada', ''),
        array('203', 'Ambiguous campaigns', 'Campaña / Producto', 'pinterest', '2030-01-04 20:00', 'UTC', 'programada', ''),
    );
}

require_once dirname(__DIR__) . '/includes/calendar.php';

function check($condition, $label) {
    if (!$condition) {
        fwrite(STDERR, 'FAIL: ' . $label . "\n");
        exit(1);
    }
}

$events = tcsocial_calendar_events();
check(5 === count($events), 'Every agenda event is still listed.');
$byid = array();
foreach ($events as $e) {
    $byid[$e['content_id'] . '|' . $e['provider']] = $e;
}
check($byid['101|facebook']['can_manage'] && 'social' === $byid['101|facebook']['source'], 'Regular social entry has controls.');
check($byid['202|pinterest']['can_manage'] && 'campaign' === $byid['202|pinterest']['source'] && 55 === $byid['202|pinterest']['campaign_id'], 'Campaign entry resolves exact owner.');
check(!$byid['203|pinterest']['can_manage'], 'Ambiguous campaign ownership never exposes destructive controls.');

ob_start();
tcsocial_calendar_render_event_actions($byid['101|facebook']);
$controls = ob_get_clean();
check(false !== strpos($controls, 'Editar') && false !== strpos($controls, 'Borrar programación') && false !== strpos($controls, 'Confirmar borrado'), 'Visible edit and confirmed deletion controls.');
check(false !== strpos($controls, 'name="calendar_status"') && false !== strpos($controls, 'pausada'), 'Status selector is in edit form.');
ob_start();
tcsocial_calendar_render_event_actions($byid['101|facebook'], 'compact');
$compact_controls = ob_get_clean();
check(false !== strpos($compact_controls, 'is-compact') && false !== strpos($compact_controls, 'Borrar programación'), 'Compact row actions are visible.');
check(false !== strpos($controls, 'calendar_expected') && false !== strpos($controls, '_wpnonce'), 'Forms contain optimistic timestamp and nonce.');
ob_start();
tcsocial_calendar_render_event_actions($byid['203|pinterest']);
check('' === ob_get_clean(), 'No destructive controls on ambiguous campaign.');

$old_facebook = $GLOBALS['test_social']['101|facebook'];
$old_pinterest = $GLOBALS['test_social']['101|pinterest'];
$old_campaign = $GLOBALS['test_campaigns']['55|202|pinterest'];

$stale = tcsocial_calendar_change_schedule('cancel', 'social', 101, 'facebook', 0, $old_facebook + 60);
check(is_wp_error($stale) && $old_facebook === $GLOBALS['test_social']['101|facebook'], 'Stale timestamp cannot cancel another schedule.');

$cancel = tcsocial_calendar_change_schedule('cancel', 'social', 101, 'facebook', 0, $old_facebook);
check(true === $cancel && !isset($GLOBALS['test_social']['101|facebook']), 'Correct Facebook schedule cancelled.');
check($old_pinterest === $GLOBALS['test_social']['101|pinterest'], 'Pinterest schedule remains untouched.');
check(in_array('seo_social_network_publish_manual_scheduled|101|facebook', $GLOBALS['test_cleared'], true), 'Legacy Cron also cancelled.');

$edit = tcsocial_calendar_change_schedule('edit', 'social', 101, 'pinterest', 0, $old_pinterest, strtotime('2030-03-01 22:00:00 UTC'));
check(true === $edit && strtotime('2030-03-01 22:00:00 UTC') === $GLOBALS['test_social']['101|pinterest'], 'Edited one network without touching other networks.');

$paused = tcsocial_calendar_change_schedule('edit', 'social', 101, 'pinterest', 0, $GLOBALS['test_social']['101|pinterest'], $GLOBALS['test_social']['101|pinterest'], 'pausada');
check(true === $paused && 'pausada' === tcsocial_network_get_schedule_state(101, 'pinterest'), 'Social schedule is paused without deleting its timestamp.');
check(is_wp_error(tcsocial_calendar_change_schedule('edit', 'social', 101, 'pinterest', 0, $GLOBALS['test_social']['101|pinterest'], $GLOBALS['test_social']['101|pinterest'], 'unknown')), 'Invalid status must be rejected.');
$resumed = tcsocial_calendar_change_schedule('edit', 'social', 101, 'pinterest', 0, $GLOBALS['test_social']['101|pinterest'], strtotime('2030-04-01 22:00:00 UTC'), 'programada');
check(true === $resumed && 'programada' === tcsocial_network_get_schedule_state(101, 'pinterest'), 'Resuming schedules a future date and clears paused state.');
check(is_wp_error(tcsocial_calendar_change_schedule('edit', 'campaign', 202, 'pinterest', 55, $old_campaign, strtotime('2030-02-03 20:00:00 UTC'), 'pausada')), 'No misleading pause on optional campaigns.');

$old_expired = $GLOBALS['test_social']['102|facebook'];
$cancel_old = tcsocial_calendar_change_schedule('cancel', 'social', 102, 'facebook', 0, $old_expired);
check(true === $cancel_old && !isset($GLOBALS['test_social']['102|facebook']), 'Old, overdue schedule can be cancelled.');

$campaign_edit = tcsocial_calendar_change_schedule('edit', 'campaign', 202, 'pinterest', 55, $old_campaign, strtotime('2030-02-03 20:00:00 UTC'));
check(true === $campaign_edit && strtotime('2030-02-03 20:00:00 UTC') === $GLOBALS['test_campaigns']['55|202|pinterest'], 'Campaign edit uses campaign owner.');

$wrong_campaign = tcsocial_calendar_change_schedule('cancel', 'campaign', 202, 'pinterest', 56, $old_campaign);
check(is_wp_error($wrong_campaign), 'Wrong campaign ID cannot cancel another campaign.');

$campaign_cancel = tcsocial_calendar_change_schedule('cancel', 'campaign', 202, 'pinterest', 55, $GLOBALS['test_campaigns']['55|202|pinterest']);
check(true === $campaign_cancel && !isset($GLOBALS['test_campaigns']['55|202|pinterest']), 'Only owned campaign schedule cancelled.');
check(isset($GLOBALS['test_campaigns']['56|203|pinterest']) && isset($GLOBALS['test_campaigns']['57|203|pinterest']), 'Other campaigns remain untouched.');

$past_edit = tcsocial_calendar_change_schedule('edit', 'social', 101, 'pinterest', 0, $GLOBALS['test_social']['101|pinterest'], strtotime('2020-01-01 UTC'));
check(is_wp_error($past_edit), 'Cannot reprogram into the past.');
$core_source = file_get_contents(dirname(__DIR__) . '/includes/core.php');
check(is_string($core_source) && false !== strpos($core_source, "function tcsocial_network_get_schedule_state("), 'Social schedule state reader exists.');
check(false !== strpos($core_source, "function tcsocial_network_pause_scheduled_publication("), 'Real social pause mutator exists.');
check(false !== strpos($core_source, "if ('pausada' === tcsocial_network_get_schedule_state("), 'WP-Cron reconciliation and callback guard both check persisted pause.');
check(substr_count($core_source, "if ('pausada' === tcsocial_network_get_schedule_state(") >= 2, 'Both reconciliation and publication callback check pause.');
check(false !== strpos($core_source, 'delete_post_meta($content_id, tcsocial_network_schedule_state_meta_key($provider));'), 'Deleting/rescheduling clears paused flag.');
check(false !== strpos($core_source, 'tcsocial_network_get_schedule_state((int) $row->post_id, $provider)'), 'Agenda export reflects real pause state.');

echo "OK: compact buttons, editable date/status, pause/resume, deletion, exact owner, stale guards, old dates, campaign isolation.\n";
