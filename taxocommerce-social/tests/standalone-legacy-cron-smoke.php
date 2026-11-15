<?php
/**
 * Regression: Social owns future legacy events without a monolith dependency.
 *
 * Run: php taxocommerce-social/tests/standalone-legacy-cron-smoke.php
 *
 * @package TaxoCommerce_Social
 */
define('ABSPATH', __DIR__ . '/');

$GLOBALS['tcsm_actions'] = array();
$GLOBALS['tcsm_filters'] = array();
$GLOBALS['tcsm_events'] = array();
$GLOBALS['tcsm_meta'] = array();
$GLOBALS['tcsm_schedules'] = 0;
$GLOBALS['tcsm_fail_schedule'] = false;
$GLOBALS['tcsm_legacy_active'] = false;
$GLOBALS['tcsm_get_post_calls'] = 0;

class WP_Error {
    public function __construct($code = '', $message = '') {}
}
function is_wp_error($value) { return $value instanceof WP_Error; }
function add_action($hook, $callback, $priority = 10, $accepted_args = 1) {
    $GLOBALS['tcsm_actions'][$hook][] = $callback;
}
function has_action($hook, $callback = false) {
    if ('seo_social_network_publish_manual_scheduled' === $hook) {
        return $GLOBALS['tcsm_legacy_active'];
    }
    return !empty($GLOBALS['tcsm_actions'][$hook]);
}
function add_filter($hook, $callback, $priority = 10, $accepted_args = 1) {
    $GLOBALS['tcsm_filters'][$hook][] = $callback;
}
function apply_filters($hook, $value) {
    foreach ($GLOBALS['tcsm_filters'][$hook] ?? array() as $callback) {
        $value = call_user_func($callback, $value);
    }
    return $value;
}
function absint($v) { return abs((int) $v); }
function sanitize_key($v) { return preg_replace('/[^a-z0-9_-]/', '', strtolower((string) $v)); }
function get_option($name, $default = false) { return $default; }
function get_post_meta($id, $key, $single = false) {
    return $GLOBALS['tcsm_meta'][absint($id)][$key] ?? '';
}
function get_post($id) {
    ++$GLOBALS['tcsm_get_post_calls'];
    return null; // Never send a real post to a provider in a test.
}
function esc_sql($value) { return $value; }

function tcsm_event_key($hook, $args) {
    return $hook . ':' . implode(':', (array) $args);
}
function wp_next_scheduled($hook, $args = array()) {
    return $GLOBALS['tcsm_events'][tcsm_event_key($hook, $args)] ?? false;
}
function wp_schedule_single_event($timestamp, $hook, $args = array(), $wp_error = false) {
    ++$GLOBALS['tcsm_schedules'];
    if ($GLOBALS['tcsm_fail_schedule']) {
        return $wp_error ? new WP_Error('cron_failed', 'Mock rejected scheduling') : false;
    }
    $GLOBALS['tcsm_events'][tcsm_event_key($hook, $args)] = (int) $timestamp;
    return true;
}
function wp_clear_scheduled_hook($hook, $args = array()) {
    $key = tcsm_event_key($hook, $args);
    $existed = isset($GLOBALS['tcsm_events'][$key]);
    unset($GLOBALS['tcsm_events'][$key]);
    return $existed ? 1 : 0;
}

class TCSM_Test_DB {
    public $postmeta = 'wp_postmeta';
    public $posts = 'wp_posts';
    public $prefix = 'wp_';
    public $rows = array();
    public $last_publication = null;
    public function esc_like($value) { return $value; }
    public function prepare($query) { return $query; }
    public function get_results($sql) {
        if (false === strpos($sql, 'CAST(pm.meta_value AS UNSIGNED) > %d')) {
            throw new RuntimeException('The fixture expects a future-only SQL query.');
        }
        // Simulate WordPress' future-only SQL predicate, not just its row store.
        return array_values(array_filter($this->rows, static function($row) {
            return absint($row->meta_value) > time() + 30;
        }));
    }
    public function get_row($sql) { return $this->last_publication; }
}

function tcsm_assert($condition, $message) {
    if (!$condition) {
        fwrite(STDERR, 'FAIL: ' . $message . "\n");
        exit(1);
    }
}
function tcsm_reset() {
    global $wpdb;
    $wpdb->rows = array();
    $wpdb->last_publication = null;
    $GLOBALS['tcsm_events'] = array();
    $GLOBALS['tcsm_meta'] = array();
    $GLOBALS['tcsm_schedules'] = 0;
    $GLOBALS['tcsm_fail_schedule'] = false;
    $GLOBALS['tcsm_legacy_active'] = false;
    $GLOBALS['tcsm_get_post_calls'] = 0;
}
function tcsm_schedule_row($id, $provider, $timestamp) {
    global $wpdb;
    $wpdb->rows[] = (object) array(
        'post_id' => $id,
        'meta_key' => '_seo_social_schedule_' . $provider,
        'meta_value' => (string) $timestamp,
    );
    $GLOBALS['tcsm_meta'][$id]['_seo_social_schedule_' . $provider] = (string) $timestamp;
}

$wpdb = new TCSM_Test_DB();
require_once dirname(__DIR__) . '/includes/core.php';
add_filter('tcsocial_network_providers', static function($providers) {
    $providers['facebook'] = array('label' => 'Facebook', 'publish_callback' => 'unreachable');
    $providers['linkedin'] = array('label' => 'LinkedIn', 'publish_callback' => 'unreachable');
    return $providers;
});
tcsm_assert(has_action('tcsocial_network_publish_manual_scheduled'), 'Own cron handler registered without monolith.');
tcsm_assert(has_action('init'), 'Own reconciliation registered without monolith.');

$future = time() + 7200;
$old_hook = 'seo_social_network_publish_manual_scheduled';
$own_hook = 'tcsocial_network_publish_manual_scheduled';

tcsm_reset();
tcsm_schedule_row(101, 'facebook', $future);
$GLOBALS['tcsm_events'][tcsm_event_key($old_hook, array(101, 'facebook'))] = $future;
tcsocial_network_reconcile_future_events();
tcsm_assert($future === wp_next_scheduled($own_hook, array(101, 'facebook')), 'Orphan future legacy job adopted by Social.');
tcsm_assert(false === wp_next_scheduled($old_hook, array(101, 'facebook')), 'Old job removed after successful takeover.');
tcsm_assert('' !== get_post_meta(101, '_seo_social_schedule_facebook', true), 'Shared schedule meta preserved.');
tcsocial_network_reconcile_future_events();
tcsm_assert(1 === $GLOBALS['tcsm_schedules'], 'Repeated reconciliation never duplicates a job.');

tcsm_reset();
tcsm_schedule_row(102, 'linkedin', $future);
$GLOBALS['tcsm_events'][tcsm_event_key($old_hook, array(102, 'linkedin'))] = $future;
$GLOBALS['tcsm_fail_schedule'] = true;
tcsocial_network_reconcile_future_events();
tcsm_assert(false === wp_next_scheduled($own_hook, array(102, 'linkedin')), 'Failed takeover does not claim success.');
tcsm_assert($future === wp_next_scheduled($old_hook, array(102, 'linkedin')), 'Failed takeover preserves the old event.');

tcsm_reset();
tcsm_schedule_row(103, 'facebook', $future);
$GLOBALS['tcsm_events'][tcsm_event_key($old_hook, array(103, 'facebook'))] = $future;
$GLOBALS['tcsm_legacy_active'] = true;
tcsocial_network_reconcile_future_events();
tcsm_assert(false === wp_next_scheduled($own_hook, array(103, 'facebook')), 'Coexisting active legacy hook never duplicated.');
tcsm_assert($future === wp_next_scheduled($old_hook, array(103, 'facebook')), 'Coexisting legacy job preserved.');

tcsm_reset();
tcsm_schedule_row(104, 'facebook', $future);
$GLOBALS['tcsm_events'][tcsm_event_key($old_hook, array(104, 'facebook'))] = $future;
$GLOBALS['tcsm_meta'][104]['_tcsocial_schedule_state_facebook'] = 'pausada';
tcsocial_network_reconcile_future_events();
tcsm_assert(false === wp_next_scheduled($own_hook, array(104, 'facebook')), 'Paused job never adopted.');

tcsm_reset();
tcsm_schedule_row(105, 'facebook', $future);
$wpdb->last_publication = (object) array('status' => 'pending');
tcsocial_network_reconcile_future_events();
tcsm_assert(0 === $GLOBALS['tcsm_schedules'], 'Pending remote request never re-sent.');
$wpdb->last_publication = (object) array('status' => 'published');
tcsocial_network_reconcile_future_events();
tcsm_assert(0 === $GLOBALS['tcsm_schedules'], 'Already published remote request never re-sent.');

tcsm_reset();
tcsm_schedule_row(106, 'facebook', time() - 7200);
tcsocial_network_reconcile_future_events();
tcsm_assert(0 === $GLOBALS['tcsm_schedules'], 'Expired tasks never automatically replayed.');

tcsm_reset();
tcsocial_network_publish_manual_scheduled(107, 'facebook');
tcsm_assert(0 === $GLOBALS['tcsm_get_post_calls'], 'Cancelled job cannot publish without schedule meta.');
tcsm_schedule_row(108, 'facebook', time() + 7200);
tcsocial_network_publish_manual_scheduled(108, 'facebook');
tcsm_assert(0 === $GLOBALS['tcsm_get_post_calls'], 'Stale cron cannot publish an event rescheduled into the future.');
$GLOBALS['tcsm_meta'][108]['_tcsocial_schedule_state_facebook'] = 'pausada';
tcsocial_network_publish_manual_scheduled(108, 'facebook');
tcsm_assert(0 === $GLOBALS['tcsm_get_post_calls'], 'Paused cron cannot publish.');
unset($GLOBALS['tcsm_meta'][108]['_tcsocial_schedule_state_facebook']);
$GLOBALS['tcsm_meta'][108]['_seo_social_schedule_facebook'] = (string) (time() - 60);
tcsocial_network_publish_manual_scheduled(108, 'facebook');
tcsm_assert(1 === $GLOBALS['tcsm_get_post_calls'], 'Valid due cron reaches publication function (test post safely rejected).');

echo "OK: independent Social owner, orphan cron takeover, no duplicates, paused/pending/published/expired guards, no obsolete callbacks.\n";
