<?php
/**
 * Offline regression of native Core Code Check, including manual WordPress.org
 * findings from November 2026. Included by autocheck-json-smoke.php (defines
 * check() and loads the installed Core inspector).
 *
 * @package TaxoCommerceCore
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

$tc_code_root = dirname( __DIR__, 3 ) . '/taxocommerce-core';
$tc_rules = static function ( $relative, $group ) use ( $tc_code_root ) {
	$report = TaxoCommerce_Core_Autotest_Code::analyze(
		$relative
	);
	return array_column( $report[ $group ]['evidence'], 'rule' );
};

$tc_core_php = $tc_rules( 'includes/class-taxocommerce-core.php', 'wordpress' );
check( ! in_array( 'review_plugin_installation', $tc_core_php, true ), 'Core no longer directly installs extensions' );
check( ! in_array( 'review_plugin_lifecycle_activate_plugin', $tc_core_php, true ), 'Activation is delegated to WordPress' );
check( ! in_array( 'review_plugin_lifecycle_deactivate_plugins', $tc_core_php, true ), 'Deactivation is delegated to WordPress' );

$tc_core_security = $tc_rules( 'includes/class-taxocommerce-core.php', 'security' );
check( ! in_array( 'review_file_upload', $tc_core_security, true ), 'WordPress handles the uploaded ZIP' );
check( ! in_array( 'review_upload_tmp_name', $tc_core_security, true ), 'No temporary ZIP path is processed by Core' );
check( ! in_array( 'review_dynamic_inline_style', $tc_core_security, true ), 'Static Core CSS replaces dynamic styles' );

$tc_hierarchy = TaxoCommerce_Core_Autotest_Code::analyze( 'includes/class-taxocommerce-core-hierarchy.php' );
$tc_country_source = file_get_contents( $tc_code_root . '/includes/class-taxocommerce-core-hierarchy.php' );
check( false !== strpos( $tc_country_source, "TaxoCommerce_Core_Country::read_posted( 'tc_country' )" ), 'Verified POST country lookup uses shared helper' );
check( false === strpos( $tc_country_source, '$source = $write ? $_POST : $_GET;' ), 'Country helper no longer reads POST without its own visible nonce' );

$tc_hooks = $tc_rules( 'includes/class-taxocommerce-core-anomalies.php', 'wordpress' );
check( ! in_array( 'review_nonprefixed_hook', $tc_hooks, true ), 'Core anomalies use unique prefixed hooks' );
$tc_hooks_json = $tc_rules( 'includes/class-taxocommerce-core-anomalies-json.php', 'wordpress' );
check( ! in_array( 'review_nonprefixed_hook', $tc_hooks_json, true ), 'Core JSON anomalies use the same prefixed hooks' );

$tc_buffer = $tc_rules( 'includes/class-taxocommerce-core-anomalies-full-json.php', 'wordpress' );
check( ! in_array( 'review_unclosed_output_buffer', $tc_buffer, true ), 'Anomalies buffer is closed in finally' );

$tc_root = realpath( $tc_code_root );
$tc_analyze = static function ( $source, $group ) use ( $tc_root ) {
	$report = TaxoCommerce_Core_Autotest_Code::analyze_source( $source, 'includes/fixture.php', $tc_root );
	return array_column( $report[ $group ]['evidence'], 'rule' );
};

$tc_unclosed = $tc_analyze( "<?php defined('ABSPATH') || exit; function taxocommerce_core_unclosed() { ob_start(); echo 'hello'; }", 'wordpress' );
check( in_array( 'review_unclosed_output_buffer', $tc_unclosed, true ), 'Unclosed output buffer is detected inside its function' );

$tc_balanced = $tc_analyze( "<?php defined('ABSPATH') || exit; function taxocommerce_core_balanced() { ob_start(); try { echo 'hello'; } finally { ob_end_clean(); } }", 'wordpress' );
check( ! in_array( 'review_unclosed_output_buffer', $tc_balanced, true ), 'try/finally closes its output buffer' );

$tc_write = $tc_analyze( <<<'PHP'
<?php
defined('ABSPATH') || exit;
function taxocommerce_core_one() {
    $data = $_POST['data'];
    return $data;
}
function taxocommerce_core_other() {
    check_admin_referer('action');
    current_user_can('manage_options');
    return sanitize_text_field('x');
}
PHP
, 'security' );
check( in_array( 'review_request_nonce', $tc_write, true ), 'Nonce in another function cannot authorize this POST' );
check( in_array( 'review_request_capability', $tc_write, true ), 'Capability in another function cannot authorize this POST' );
check( in_array( 'review_request_sanitization', $tc_write, true ), 'Sanitization in another function cannot sanitize this POST' );

$tc_protected = $tc_analyze( <<<'PHP'
<?php
defined('ABSPATH') || exit;
function taxocommerce_core_verified() {
    if (!current_user_can('manage_options')) { return; }
    check_admin_referer('action');
    $data = sanitize_text_field(wp_unslash($_POST['data']));
    return $data;
}
PHP
, 'security' );
check( ! in_array( 'review_request_nonce', $tc_protected, true ), 'Same-handler nonce does not raise a false positive' );
check( ! in_array( 'review_request_capability', $tc_protected, true ), 'Same-handler permission does not raise a false positive' );

$tc_sql = $tc_analyze( "<?php defined('ABSPATH') || exit; function taxocommerce_core_query(\$wpdb, \$sql) { return \$wpdb->get_results(\$sql); }", 'security' );
check( in_array( 'review_dynamic_sql_query', $tc_sql, true ), 'Raw dynamic SQL call gets reviewed' );

$tc_sessions = $tc_analyze( "<?php defined('ABSPATH') || exit; function taxocommerce_core_session() { session_start(); \$_SESSION['a'] = 1; }", 'php' );
check( in_array( 'review_php_session', $tc_sessions, true ), 'PHP session use is detected' );

$tc_names = $tc_analyze( "<?php defined('ABSPATH') || exit; class GenericClass {} function generic_callback() {} define('GENERIC_CONST', 1); apply_filters('seo_reports_is_non_editorial_post', false);", 'wordpress' );
check( in_array( 'review_generic_class_name', $tc_names, true ), 'Non-prefixed global class is reported' );
check( in_array( 'review_generic_function_name', $tc_names, true ), 'Non-prefixed global function is reported' );
check( in_array( 'review_generic_constant_name', $tc_names, true ), 'Non-prefixed global constant is reported' );
check( in_array( 'review_nonprefixed_hook', $tc_names, true ), 'Non-prefixed custom filter is reported' );

$tc_strings = $tc_analyze( "<?php defined('ABSPATH') || exit; // activate_plugin() and ob_start() only in comments.\n\$msg = 'session_start()'; apply_filters('taxocommerce_core_safe', true);", 'wordpress' );
check( ! in_array( 'review_plugin_lifecycle_activate_plugin', $tc_strings, true ), 'Comments are not parsed as plugin activation calls' );
check( ! in_array( 'review_unclosed_output_buffer', $tc_strings, true ), 'Comments are not parsed as output buffering' );
check( ! in_array( 'review_nonprefixed_hook', $tc_strings, true ), 'Own-prefixed hooks are allowed' );

$tc_output = $tc_analyze( "<?php defined('ABSPATH') || exit; function taxocommerce_core_print(\$value) { echo \$value; }", 'security' );
check( in_array( 'review_unescaped_output', $tc_output, true ), 'Direct HTML variable output needs contextual escaping' );

$tc_arch = TaxoCommerce_Core_Autotest_Code::analyze(
	'includes/class-taxocommerce-core-architecture-report.php'
);
$tc_declarations = array_column( $tc_arch['declarations'], 'name' );
check( ! in_array( 'render', $tc_declarations, true ), 'A class method after interpolated SQL is not a generic global render function' );
check( ! in_array( 'summarize', $tc_declarations, true ), 'Interpolated strings do not close class scope in source analysis' );

// New rules still report a real introduced violation and its source location.
$tc_details = TaxoCommerce_Core_Autotest_Code::analyze_source(
    "<?php defined( 'ABSPATH' ) || exit; function taxocommerce_core_bad_zip() { activate_plugin( 'other/other.php' ); }",
    'includes/fixture.php',
    $tc_root
);
$tc_findings = array_filter( $tc_details['wordpress']['evidence'], static function ( $entry ) {
    return 'review_plugin_lifecycle_activate_plugin' === $entry['rule'];
} );
$tc_findings = array_values( $tc_findings );
check( ! empty( $tc_findings ) && isset( $tc_findings[0]['line'] ), 'Native review still reports future unapproved lifecycle calls' );
