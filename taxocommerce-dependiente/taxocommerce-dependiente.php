<?php
/**
 * Plugin Name: TaxoCommerce Assistant
 * Plugin URI: https://asociacionfocazul.com/taxocommerce-dependiente/
 * Description: Intelligent WooCommerce assistant with auditable L1-L10 lessons, semantic interpretation, L9 memory, and continuous updates.
 * Version: 0.3.40
 * Release Date: 2026-11-10
 * Requires at least: 6.5
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * Author: David Perez
 * License: GPLv2 or later
 * Text Domain: taxocommerce-dependiente
 * Domain Path: /languages
 */
defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/includes/admin-branding.php';
require_once __DIR__ . '/includes/class-taxocommerce-autotest-code.php';
\TaxoCommerce_Dependiente_Autotest_Code::boot();

define( 'TAXOCOMMERCE_DEPENDIENTE_VERSION', '0.3.40' );
define( 'TAXOCOMMERCE_DEPENDIENTE_FILE', __FILE__ );
define( 'TAXOCOMMERCE_DEPENDIENTE_PATH', plugin_dir_path( __FILE__ ) );

require_once TAXOCOMMERCE_DEPENDIENTE_PATH . 'includes/class-tcd-load.php';
require_once TAXOCOMMERCE_DEPENDIENTE_PATH . 'includes/class-tcd-learning-log.php';
require_once TAXOCOMMERCE_DEPENDIENTE_PATH . 'includes/class-tcd-sources.php';
require_once TAXOCOMMERCE_DEPENDIENTE_PATH . 'includes/class-tcd-academy.php';
require_once TAXOCOMMERCE_DEPENDIENTE_PATH . 'includes/class-tcd-knowledge-transfer.php';
require_once TAXOCOMMERCE_DEPENDIENTE_PATH . 'includes/class-tcd-feedback.php';
require_once TAXOCOMMERCE_DEPENDIENTE_PATH . 'includes/class-tcd-intelligence.php';
require_once TAXOCOMMERCE_DEPENDIENTE_PATH . 'includes/class-tcd-maintenance.php';
require_once TAXOCOMMERCE_DEPENDIENTE_PATH . 'includes/class-tcd-backup.php';
require_once TAXOCOMMERCE_DEPENDIENTE_PATH . 'includes/class-tcd-search.php';
/* Original Interpreter 1.2.0 and Linguist original curriculum. */
require_once TAXOCOMMERCE_DEPENDIENTE_PATH . 'includes/interpreter/class-tcd-interpreter-lexicon.php';
require_once TAXOCOMMERCE_DEPENDIENTE_PATH . 'includes/interpreter/class-tcd-linguist.php';
require_once TAXOCOMMERCE_DEPENDIENTE_PATH . 'includes/interpreter/class-tcd-interpreter-legacy.php';
require_once TAXOCOMMERCE_DEPENDIENTE_PATH . 'includes/interpreter/class-tcd-linguist-l9-adapter.php';
require_once TAXOCOMMERCE_DEPENDIENTE_PATH . 'includes/interpreter/lessons/class-dependiente-linguista-lesson9.php';
require_once TAXOCOMMERCE_DEPENDIENTE_PATH . 'includes/class-tcd-auto-test.php';
require_once TAXOCOMMERCE_DEPENDIENTE_PATH . 'includes/class-tcd-admin.php';
require_once TAXOCOMMERCE_DEPENDIENTE_PATH . 'includes/class-tcd-manual.php';

add_action( 'plugins_loaded', static function () {
    if ( ! class_exists( 'WooCommerce' ) ) {
        return;
    }
    TCD_Learning_Log::boot();
    TCD_Academy::boot();
    TCD_Knowledge_Transfer::boot();
    TCD_Feedback::boot();
    TCD_Intelligence::boot();
    TCD_Maintenance::boot();
    TCD_Backup::boot();
    // Plugin updates do not trigger WordPress activation hooks. The lexicon
    // tables must also be available when upgrading an existing installation.
    if (
        (string) get_option(TCD_Original_Interprete_DB::SCHEMA_OPTION, '') !== TCD_Original_Interprete_DB::SCHEMA_VERSION
        || !TCD_Original_Interprete_DB::table_exists()
        || !TCD_Original_Interprete_DB::evidence_table_exists()
    ) {
        TCD_Original_Interprete_DB::install();
    }
    TCD_Original_Linguista::init();

    // One-time recovery from the previous invented 180-ID M11 regression.
    // Do not reset Academy progress, knowledge, snapshots or any linguistic data.
    if (!get_option('tcd_original_interpreter_m11_recovered_0316', false)) {
        $academy = TCD_Academy::state();
        if (
            11 === absint($academy['lesson'] ?? 0)
            && 'error' === (string) ($academy['status'] ?? '')
            && (false !== stripos((string) ($academy['last_error'] ?? ''), 'regresi')
                || false !== stripos((string) ($academy['last_error'] ?? ''), 'Intérprete'))
        ) {
            TCD_Academy::save(array(
                'status'=>'running',
                'last_error'=>'',
                'last_message'=>'Recuperando M11: formación del Intérprete original, sin reiniciar L1–L10.',
            ));
            wp_schedule_single_event(time()+5, TCD_Academy::CRON_HOOK);
        }
        update_option('tcd_original_interpreter_m11_recovered_0316', 1, false);
    }
    TCD_Search::boot();
    TCD_Admin::boot();
    TCD_Manual::boot();
    TCD_Auto_Test::boot();
}, 30 );

add_action( 'taxocommerce_core_register_modules', static function ( $core ) {
    if ( method_exists( $core, 'register_module' ) ) {
        $core->register_module( 'dependiente', array(
            'name' => 'TaxoCommerce Assistant',
            'version' => TAXOCOMMERCE_DEPENDIENTE_VERSION,
            'status' => 'active',
            'description' => 'Catalog Assistant with L1-L10, semantic interpretation, L9 memory, and continuous updates.',
            'admin_url' => admin_url( 'admin.php?page=taxocommerce-dependiente' ),
        ) );
    }
}, 10, 1 );

register_activation_hook( __FILE__, static function () {
    // A reactivation or an upgrade must not start a new learning run.
    $first_install = false === get_option( TCD_Academy::STATE, false );
    TCD_Academy::install();
    TCD_Knowledge_Transfer::install();
    TCD_Feedback::install();
    TCD_Intelligence::install();
    TCD_Original_Interprete_DB::install();
    if ( $first_install ) {
        // Queue bounded learning from this site's own products only.
        TCD_Academy::start_first_install_catalog();
    }
} );
register_deactivation_hook( __FILE__, static function () {
    wp_clear_scheduled_hook( TCD_Academy::CRON_HOOK );
    TCD_Maintenance::deactivate();
    wp_clear_scheduled_hook('tcd_original_linguista_worker');
} );

/* Native local gettext catalog fallback (#2062). */
add_action( 'init', static function () {
    $domain = 'taxocommerce-dependiente';
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
