<?php
/**
 * Regression for early WordPress Core module registration.
 *
 * Before init, customers must not force a premature textdomain load.
 * No WordPress, WooCommerce, database, or external services are required.
 */
define( 'ABSPATH', __DIR__ . '/' );
define( 'TCCUST_VERSION', '0.1.2' );
$GLOBALS['tccust_init_complete'] = false;
$GLOBALS['tccust_early_translations'] = 0;
$GLOBALS['tccust_all_translations'] = 0;

function admin_url( $path = '' ) { return 'https://example.test/wp-admin/' . $path; }
function did_action( $hook ) {
    return 'init' === $hook && $GLOBALS['tccust_init_complete'] ? 1 : 0;
}
function __( $text, $domain = '' ) {
    if ( 'taxocommerce-customers' === $domain ) {
        ++$GLOBALS['tccust_all_translations'];
        if ( ! $GLOBALS['tccust_init_complete'] ) {
            ++$GLOBALS['tccust_early_translations'];
        }
        return 'localized:' . $text;
    }
    return $text;
}

require_once dirname( __DIR__ ) . '/includes/class-tccust-admin.php';

class TCCUST_Test_Core {
    public $description = '';
    public function register_module( $name, $attributes ) {
        $this->description = $attributes['description'];
    }
}

$receiver = new TCCUST_Test_Core();
TCCUST_Admin::register_module( $receiver );
if (
    $GLOBALS['tccust_early_translations'] ||
    0 !== $GLOBALS['tccust_all_translations'] ||
    'Private customer and purchaser management.' !== $receiver->description
) {
    fwrite( STDERR, "FAIL: Customers translates too early during Core plugins_loaded registration\n" );
    exit( 1 );
}

$GLOBALS['tccust_init_complete'] = true;
$receiver_after_init = new TCCUST_Test_Core();
TCCUST_Admin::register_module( $receiver_after_init );
if (
    0 !== $GLOBALS['tccust_early_translations'] ||
    1 !== $GLOBALS['tccust_all_translations'] ||
    'localized:Private customer and purchaser management.' !== $receiver_after_init->description
) {
    fwrite( STDERR, "FAIL: Customers description not translated after init\n" );
    exit( 1 );
}

$plugin = file_get_contents( dirname( __DIR__ ) . '/taxocommerce-customers.php' );
if ( false === $plugin || false === strpos( $plugin, "'description' => TCCUST_Admin::description()," ) ) {
    fwrite( STDERR, "FAIL: Core family description does not use guarded translation method\n" );
    exit( 1 );
}
echo "Customers early registration i18n: PASS\n";
