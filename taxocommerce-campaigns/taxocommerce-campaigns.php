<?php
/**
 * Plugin Name: TaxoCommerce Campaigns
 * Plugin URI: https://asociacionfocazul.com/taxocommerce-campaigns/
 * Description: Offer campaigns, campaign pricing, public banners, social offer publishing, and a standalone commercial calendar.
 * Version: 0.1.15
 * Release Date: 2026-11-13
 * Requires at least: 6.7
 * Requires PHP: 7.4
 * Author: David Perez
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: taxocommerce-campaigns
 * Domain Path: /languages
 */

defined('ABSPATH') || exit;

require_once __DIR__ . '/includes/admin-branding.php';
require_once __DIR__ . '/includes/class-taxocommerce-autotest-code.php';
\TaxoCommerce_Campaigns_Autotest_Code::boot();

define('TAXOCOMMERCE_CAMPAIGNS_VERSION','0.1.15');
define('TAXOCOMMERCE_CAMPAIGNS_PATH',plugin_dir_path(__FILE__));
define('TAXOCOMMERCE_CAMPAIGNS_URL',plugin_dir_url(__FILE__));

require_once TAXOCOMMERCE_CAMPAIGNS_PATH.'includes/campaigns.php';
require_once TAXOCOMMERCE_CAMPAIGNS_PATH.'includes/campaign-images.php';
require_once TAXOCOMMERCE_CAMPAIGNS_PATH.'includes/social-publication.php';
require_once TAXOCOMMERCE_CAMPAIGNS_PATH.'includes/calendar.php';
require_once TAXOCOMMERCE_CAMPAIGNS_PATH.'includes/shortcodes.php';
require_once TAXOCOMMERCE_CAMPAIGNS_PATH.'includes/admin.php';
require_once TAXOCOMMERCE_CAMPAIGNS_PATH.'includes/autotest.php';
require_once TAXOCOMMERCE_CAMPAIGNS_PATH.'includes/manual.php';

register_activation_hook(__FILE__, static function(){
    tccampaigns_maybe_install_tables();
    tccampaigns_register_public_template();
    delete_option('tccampaigns_schedule_owner_v1');
});

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Existing TaxoCommerce Core registration callback; preserve public hook API.
function taxocommerce_campaigns_register_core($core){
    if(!is_object($core)||!method_exists($core,'register_module')) return;
    $core->register_module('campaigns',array(
        'name'=>'TaxoCommerce Campaigns',
        'version'=>TAXOCOMMERCE_CAMPAIGNS_VERSION,
        'status'=>'active',
        'description'=>'Offer campaigns, prices, banners, social offers, and commercial calendar.',
        'admin_url'=>admin_url('admin.php?page=taxocommerce-campaigns'),
    ));
}
add_action('taxocommerce_core_register_modules','taxocommerce_campaigns_register_core');

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Existing family extension callback; preserve public hook API.
function taxocommerce_campaigns_family_catalog($extensions){
    $extensions=is_array($extensions)?$extensions:array();
    $extensions['campaigns']=array(
        'id'=>'campaigns',
        'name'=>'TaxoCommerce Campaigns',
        'plugin_file'=>'taxocommerce-campaigns/taxocommerce-campaigns.php',
        'org_slug'=>'taxocommerce-campaigns',
        'search_term'=>'TaxoCommerce Campaigns',
        'wordpress_org'=>false,
        'admin_url'=>admin_url('admin.php?page=taxocommerce-campaigns'),
        'description'=>'Offer campaigns, campaign pricing, banners, social offer publication, and standalone commercial calendar.',
    );
    return $extensions;
}
add_filter('taxocommerce_core_family_extensions','taxocommerce_campaigns_family_catalog');

function tccampaigns_claim_schedule_when_legacy_is_off(){
    if(function_exists('seo_marketing_campaigns_get')) return;
    if(get_option('tccampaigns_schedule_owner_v1')) return;

    global $wpdb;
    $tables=tccampaigns_tables();
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Fresh campaign/social publication inventory query; stale cached data would affect scheduling.
    $ids=(array)$wpdb->get_col($wpdb->prepare("SELECT id FROM %i WHERE is_enabled=1", $tables['campaigns']));

    foreach($ids as $id){
        $id=absint($id);
        if(!$id) continue;

        if(function_exists('as_unschedule_all_actions')){
            as_unschedule_all_actions('seo_marketing_campaign_apply',array($id),'seo-marketing-campaigns');
            as_unschedule_all_actions('seo_marketing_campaign_restore',array($id),'seo-marketing-campaigns');
        }
        wp_clear_scheduled_hook('seo_marketing_campaign_apply',array($id));
        wp_clear_scheduled_hook('seo_marketing_campaign_restore',array($id));
        tccampaigns_reschedule($id);
    }

    // Existing campaign-social agenda is stored in postmeta and therefore
    // survives the plugin split. Rebind only when the old publisher is gone.
    if(!function_exists('seo_social_campaign_run_scheduled')){
        $like=$wpdb->esc_like('_seo_social_campaign_schedule_').'%';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Fresh campaign/social publication inventory query; stale cached data would affect scheduling.
        $rows=(array)$wpdb->get_results(
            $wpdb->prepare(
                "SELECT post_id,meta_key,meta_value FROM {$wpdb->postmeta}
                 WHERE meta_key LIKE %s AND CAST(meta_value AS UNSIGNED) > %d",
                $like,
                time()+30
            )
        );
        foreach($rows as $row){
            if(!preg_match('/^_seo_social_campaign_schedule_(\\d+)_([a-z0-9_-]+)$/',(string)$row->meta_key,$m)) continue;
            $args=array(absint($m[1]),absint($row->post_id),sanitize_key($m[2]));
            $ts=absint($row->meta_value);
            wp_clear_scheduled_hook('seo_social_campaign_publish_scheduled',$args);
            if($ts && !wp_next_scheduled('tccampaigns_social_publish_event',$args)){
                wp_schedule_single_event($ts,'tccampaigns_social_publish_event',$args);
            }
        }
    }

    update_option('tccampaigns_schedule_owner_v1',1,false);
}
add_action('init','tccampaigns_claim_schedule_when_legacy_is_off',30);

/**
 * Compatibility for TaxoCommerce Templates while the old monolith is disabled.
 * These functions are declared only after all active plugins have loaded.
 */
add_action('plugins_loaded', static function(){
    if(!function_exists('seo_marketing_campaigns_get_public_active')){
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Required legacy compatibility alias; renaming would break external integrations.
        function seo_marketing_campaigns_get_public_active(){ return tccampaigns_get_public_active(); }
    }
    if(!function_exists('seo_marketing_campaigns_public_template_is_enabled')){
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Required legacy compatibility alias; renaming would break external integrations.
        function seo_marketing_campaigns_public_template_is_enabled(){ return tccampaigns_public_template_is_enabled(); }
    }
    if(!function_exists('seo_marketing_campaigns_get_social_catalog')){
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Required legacy compatibility alias; renaming would break external integrations.
        function seo_marketing_campaigns_get_social_catalog(){ return tccampaigns_get_social_catalog(); }
    }
    if(!function_exists('seo_marketing_campaigns_get_social_campaign')){
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Required legacy compatibility alias; renaming would break external integrations.
        function seo_marketing_campaigns_get_social_campaign($id){ return tccampaigns_get_social_campaign($id); }
    }
    if(!function_exists('seo_marketing_campaigns_timestamp')){
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Required legacy compatibility alias; renaming would break external integrations.
        function seo_marketing_campaigns_timestamp($value){ return tccampaigns_timestamp($value); }
    }
    if(!function_exists('seo_marketing_campaigns_get')){
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Required legacy compatibility alias; renaming would break external integrations.
        function seo_marketing_campaigns_get($id){ return tccampaigns_get($id); }
    }
},99);

/* Native local gettext catalog fallback (#2062). */
add_action( 'init', static function () {
    $domain = 'taxocommerce-campaigns';
    if ( is_textdomain_loaded( $domain ) ) {
        return;
    }
    $locale = sanitize_file_name( determine_locale() );
    // Prefer official WordPress language packs when installed.
    $official_pack = WP_LANG_DIR . '/plugins/' . $domain . '-' . $locale;
    if ( is_readable( $official_pack . '.mo' ) || is_readable( $official_pack . '.l10n.php' ) ) {
        return;
    }
    $catalog = __DIR__ . '/languages/' . $domain . '-' . $locale . '.mo';
    if ( ! is_readable( $catalog ) ) {
        // Use a bundled base-language catalog for WordPress locale variants.
        $language = strtolower( substr( $locale, 0, 2 ) );
        $fallback_locales = array(
            'es' => 'es_ES',
            'en' => 'en_US',
            'fr' => 'fr_FR',
            'de' => 'de_DE',
            'nl' => 'nl_NL',
        );
        if ( isset( $fallback_locales[ $language ] ) ) {
            $catalog = __DIR__ . '/languages/' . $domain . '-' . $fallback_locales[ $language ] . '.mo';
        }
    }
    if ( is_readable( $catalog ) ) {
        load_textdomain( $domain, $catalog );
    }
}, 1 );
