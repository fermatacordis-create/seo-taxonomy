<?php
/**
 * Plugin Name: TaxoCommerce Mail Pro
 * Plugin URI: https://github.com/distribuidordeherramientases-wq/seo-taxonomy/tree/staging/taxocommerce-mail-pro
 * Description: Standalone Pro client for hosting mailboxes, folders, sent messages, search and secure bulk IMAP operations.
 * Version: 0.1.0
 * Release Date: 2026-11-23
 * Requires at least: 6.7
 * Requires PHP: 7.4
 * Author: David Perez
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: taxocommerce-mail-pro
 * Domain Path: /languages
 */
defined( 'ABSPATH' ) || exit;
define( 'TCMAILPRO_VERSION', '0.1.0' );
define( 'TCMAILPRO_PATH', plugin_dir_path( __FILE__ ) );
define( 'TCMAILPRO_URL', plugin_dir_url( __FILE__ ) );
require_once TCMAILPRO_PATH . 'includes/class-tcmailpro-accounts.php';
require_once TCMAILPRO_PATH . 'includes/class-tcmailpro-imap.php';
require_once TCMAILPRO_PATH . 'includes/class-tcmailpro-smtp.php';
require_once TCMAILPRO_PATH . 'includes/class-tcmailpro-admin.php';
require_once TCMAILPRO_PATH . 'includes/class-tcmailpro-mailbox.php';
require_once TCMAILPRO_PATH . 'includes/class-tcmailpro-autocheck.php';
require_once TCMAILPRO_PATH . 'includes/admin-branding.php';
register_activation_hook( __FILE__, array( 'TCMAILPRO_Admin', 'activate' ) );
// Prefer WordPress.org language packs; use bundled locale catalogs for offline shops.
add_action( 'init', static function () {
    if ( is_textdomain_loaded( 'taxocommerce-mail-pro' ) ) {
        return;
    }
    $locale = sanitize_file_name( determine_locale() );
    $official = WP_LANG_DIR . '/plugins/taxocommerce-mail-pro-' . $locale;
    if ( is_file( $official . '.mo' ) || is_file( $official . '.l10n.php' ) ) {
        return;
    }
    $supported_locales = array( 'es_ES', 'en_US', 'fr_FR', 'de_DE', 'nl_NL' );
    if ( in_array( $locale, $supported_locales, true ) ) {
        $catalog = TCMAILPRO_PATH . 'languages/taxocommerce-mail-pro-' . $locale . '.mo';
        if ( is_readable( $catalog ) ) {
            load_textdomain( 'taxocommerce-mail-pro', $catalog );
        }
    }
}, 1 );
add_action( 'plugins_loaded', array( 'TCMAILPRO_Admin', 'boot' ) );
add_action( 'plugins_loaded', array( 'TCMAILPRO_Mailbox', 'boot' ) );
add_action( 'plugins_loaded', array( 'TCMAILPRO_Autocheck', 'boot' ) );
add_filter( 'taxocommerce_core_family_extensions', static function ( $extensions ) {
    $extensions = is_array( $extensions ) ? $extensions : array();
    $extensions['mail_pro'] = array(
        'id' => 'mail_pro',
        'name' => 'TaxoCommerce Mail Pro',
        'plugin_file' => 'taxocommerce-mail-pro/taxocommerce-mail-pro.php',
        'org_slug' => 'taxocommerce-mail-pro',
        'wordpress_org' => false,
        'admin_url' => admin_url( 'admin.php?page=taxocommerce-mail-pro' ),
        'description' => __( 'Manage several hosting email accounts inside WordPress through secure IMAP and SMTP.', 'taxocommerce-mail-pro' ),
    );
    return $extensions;
} );
add_action( 'taxocommerce_core_register_modules', static function ( $core ) {
    if ( is_object( $core ) && method_exists( $core, 'register_module' ) ) {
        $core->register_module( 'mail_pro', array(
            'name' => 'TaxoCommerce Mail Pro',
            'version' => TCMAILPRO_VERSION,
            'status' => 'active',
            'description' => __( 'Private hosting email client.', 'taxocommerce-mail-pro' ),
            'admin_url' => admin_url( 'admin.php?page=taxocommerce-mail-pro' ),
        ) );
    }
} );
