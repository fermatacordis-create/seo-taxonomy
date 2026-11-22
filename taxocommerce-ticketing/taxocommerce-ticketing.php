<?php
/**
 * Plugin Name: TaxoCommerce Ticketing
 * Plugin URI: https://asociacionfocazul.com/taxocommerce-ticketing/
 * Description: Independent customer and internal helpdesk: configurable public forms, private attachments, replies, notifications and ticket management.
 * Version: 0.1.2
 * Release Date: 2026-11-20
 * Requires at least: 6.7
 * Requires PHP: 7.4
 * Author: David Perez
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: taxocommerce-ticketing
 * Domain Path: /languages
 */
defined( 'ABSPATH' ) || exit;

define( 'TCTK_VERSION', '0.1.2' );
define( 'TCTK_PATH', plugin_dir_path( __FILE__ ) );
define( 'TCTK_URL', plugin_dir_url( __FILE__ ) );

require_once TCTK_PATH . 'includes/class-tctk-ticketing.php';
require_once TCTK_PATH . 'includes/class-tctk-autocheck.php';
require_once TCTK_PATH . 'includes/class-tctk-autotest-code.php';
require_once TCTK_PATH . 'includes/class-tctk-event-log.php';
require_once TCTK_PATH . 'includes/class-tctk-manuals.php';
require_once TCTK_PATH . 'includes/admin-branding.php';
TCTK_Autocheck::boot();
TCTK_Autotest_Code::boot();
TCTK_Event_Log::boot();

register_activation_hook( __FILE__, array( 'TCTK_Ticketing', 'activate' ) );
add_action( 'plugins_loaded', array( 'TCTK_Ticketing', 'boot' ) );

add_filter( 'taxocommerce_core_family_extensions', static function ( $extensions ) {
    $extensions = is_array( $extensions ) ? $extensions : array();
    $extensions['ticketing'] = array(
        'id'            => 'ticketing',
        'name'          => 'TaxoCommerce Ticketing',
        'plugin_file'   => 'taxocommerce-ticketing/taxocommerce-ticketing.php',
        'org_slug'      => 'taxocommerce-ticketing',
        'wordpress_org' => false,
        'admin_url'     => admin_url( 'admin.php?page=taxocommerce-ticketing' ),
        'description'   => 'Independent customer and internal ticket management.',
    );
    return $extensions;
} );

add_action( 'taxocommerce_core_register_modules', static function ( $core ) {
    if ( is_object( $core ) && method_exists( $core, 'register_module' ) ) {
        $core->register_module( 'ticketing', array(
            'name'        => 'TaxoCommerce Ticketing',
            'version'     => TCTK_VERSION,
            'status'      => 'active',
            'description' => 'Customer and internal helpdesk.',
            'admin_url'   => admin_url( 'admin.php?page=taxocommerce-ticketing' ),
        ) );
    }
} );
