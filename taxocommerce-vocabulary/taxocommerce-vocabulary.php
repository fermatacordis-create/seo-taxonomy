<?php
/**
 * Plugin Name: TaxoCommerce Vocabulary
 * Plugin URI: https://asociacionfocazul.com/taxocommerce-vocabulary/
 * Description: Vocabulario semántico, atributos técnicos, clasificación asistida e importación/exportación para TaxoCommerce.
 * Version: 0.3.21
 * Release Date: 2026-11-10
 * Requires at least: 6.7
 * Requires PHP: 7.4
 * Author: David Perez
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: taxocommerce-vocabulary
 * Domain Path: /languages
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/includes/admin-branding.php';
require_once __DIR__ . '/includes/class-taxocommerce-autotest-code.php';
\TaxoCommerce_Vocabulary_Autotest_Code::boot();

define( 'TAXOCOMMERCE_VOCABULARY_VERSION', '0.3.21' );
define( 'TAXOCOMMERCE_VOCABULARY_PATH', plugin_dir_path( __FILE__ ) );
define( 'TAXOCOMMERCE_VOCABULARY_URL', plugin_dir_url( __FILE__ ) );

require_once TAXOCOMMERCE_VOCABULARY_PATH . 'includes/class-tcv-schema.php';
require_once TAXOCOMMERCE_VOCABULARY_PATH . 'includes/class-tcv-extractor.php';
require_once TAXOCOMMERCE_VOCABULARY_PATH . 'includes/class-tcv-classifier.php';
require_once TAXOCOMMERCE_VOCABULARY_PATH . 'includes/class-tcv-io.php';
require_once TAXOCOMMERCE_VOCABULARY_PATH . 'includes/class-tcv-portable.php';
require_once TAXOCOMMERCE_VOCABULARY_PATH . 'includes/class-tcv-admin.php';
require_once TAXOCOMMERCE_VOCABULARY_PATH . 'includes/class-tcv-manual.php';
require_once TAXOCOMMERCE_VOCABULARY_PATH . 'includes/class-tcv-auto-test.php';

register_activation_hook( __FILE__, array( 'TCV_Schema', 'install' ) );

add_action(
    'plugins_loaded',
    static function () {
        TCV_Schema::maybe_upgrade();
        TCV_IO::boot();
        TCV_Portable::boot();
        if ( is_admin() ) {
            TCV_Admin::boot();
        }
    }
);


function taxocommerce_vocabulary_register_with_core( $core ) {
    if ( ! is_object( $core ) || ! method_exists( $core, 'register_module' ) ) {
        return;
    }

    // Core announces modules during plugins_loaded, before WordPress init.
    // Defer translated metadata until init, after the local language catalog.
    if ( ! did_action( 'init' ) ) {
        add_action( 'init', static function () use ( $core ) {
            taxocommerce_vocabulary_register_with_core( $core );
        }, 11 );
        return;
    }

    $core->register_module(
        'vocabulary',
        array(
            'name'        => 'TaxoCommerce Vocabulary',
            'version'     => TAXOCOMMERCE_VOCABULARY_VERSION,
            'status'      => 'active',
            'description' => __( 'Inventario transversal de etiquetas y atributos con Clasificador automático y ejecución local.', 'taxocommerce-vocabulary' ),
            'admin_url'   => admin_url( 'admin.php?page=taxocommerce-vocabulary' ),
        )
    );
}
add_action( 'taxocommerce_core_register_modules', 'taxocommerce_vocabulary_register_with_core' );

if ( function_exists( 'taxocommerce_core' ) && did_action( 'taxocommerce_core_loaded' ) ) {
    taxocommerce_vocabulary_register_with_core( taxocommerce_core() );
}

/* Native local gettext catalog fallback (#2062). */
add_action( 'init', static function () {
    $domain = 'taxocommerce-vocabulary';
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
