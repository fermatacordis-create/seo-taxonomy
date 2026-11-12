<?php
/** Isolated smoke: expensive reference lookup must be reused across CPU batches. */
define( 'ABSPATH', __DIR__ );
class WP_Error {
    private $code;
    public function __construct( $code, $message ) { $this->code = $code; }
    public function get_error_code() { return $this->code; }
}
function __( $text, $domain = '' ) { return $text; }
function absint( $number ) { return abs( (int) $number ); }
function get_option( $name, $default = false ) { return $GLOBALS['tci_opts'][ $name ] ?? $default; }
function update_option( $name, $value, $autoload = false ) { $GLOBALS['tci_opts'][ $name ] = $value; return true; }
function add_filter( $name, $callback, $priority = 10, $args = 1 ) { return true; }
function remove_filter( $name, $callback, $priority = 10 ) { return true; }
function get_post( $id ) { return (object) array( 'ID' => $id ); }
function taxocommerce_images_refs_build_usage_report( $posts ) {
    ++$GLOBALS['tci_refs_calls'];
    $results = array();
    foreach ( $posts as $post ) { $results[ $post->ID ] = (int) ( 0 === $post->ID % 2 ); }
    if ( ! empty( $GLOBALS['tci_incomplete'] ) ) { array_pop( $results ); }
    return $results;
}
class WP_Query {
    public $posts = array();
    public function __construct( $args ) {
        $this->posts = range( 1, (int) $args['posts_per_page'] );
    }
}
function check_usage( $ok, $message ) {
    if ( ! $ok ) { fwrite( STDERR, 'FAIL: ' . $message . PHP_EOL ); exit( 1 ); }
}
require dirname( __DIR__ ) . '/includes/class-taxocommerce-images-maintenance.php';
$GLOBALS['tci_opts'] = array();
$GLOBALS['tci_refs_calls'] = 0;
$method = new ReflectionMethod( 'TaxoCommerce_Images_Maintenance', 'cleanup_usage' );
$method->setAccessible( true );
$run = array( 'run' => 'run-1', 'last' => 0, 'max' => 1000 );
$first = $method->invoke( null, $run, array( 1, 2, 3 ) );
check_usage( 1 === $GLOBALS['tci_refs_calls'], 'global references computed once for first 3 IDs' );
check_usage( 1 === $first[2] && 0 === $first[1], 'used/unused statuses remain distinct' );
$next = $method->invoke( null, $run, array( 4, 5, 6 ) );
check_usage( 1 === $GLOBALS['tci_refs_calls'], 'new adaptive batch reuses same 512-ID window' );
check_usage( 1 === $next[4], 'cached reference remains correct' );
$new_run = $run;
$new_run['run'] = 'run-2';
$method->invoke( null, $new_run, array( 1, 2 ) );
check_usage( 2 === $GLOBALS['tci_refs_calls'], 'new run invalidates earlier reference snapshot' );
$GLOBALS['tci_incomplete'] = true;
$new_run['run'] = 'run-3';
$error = $method->invoke( null, $new_run, array( 1, 2 ) );
check_usage( $error instanceof WP_Error, 'incomplete lookup cannot mark missing images unused' );
echo "Images cleanup lookup window: PASS (one full reference check per window, safe run invalidation)\n";
