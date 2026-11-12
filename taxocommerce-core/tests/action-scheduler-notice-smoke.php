<?php
/**
 * Verify the exact scoped Action Scheduler overdue notice suppression.
 *
 * @package TaxoCommerceCore
 */
$source = file_get_contents( dirname( __DIR__ ) . '/taxocommerce-core.php' );
if ( false === $source ) {
    fwrite( STDERR, "Core entrypoint is unreadable.\n" );
    exit( 1 );
}
$needle = "add_filter( 'action_scheduler_check_pastdue_actions', '__return_false', PHP_INT_MAX );";
if ( 1 !== substr_count( $source, $needle ) ) {
    fwrite( STDERR, "Expected exactly one early, explicit overdue-warning filter.\n" );
    exit( 1 );
}
if ( false !== strpos( $source, "remove_all_actions( 'admin_notices'" ) ||
    false !== strpos( $source, "remove_all_filters( 'admin_notices'" ) ||
    false !== strpos( $source, "remove_action( 'action_scheduler_run_queue'" ) ) {
    fwrite( STDERR, "Broad notice or queue suppression is forbidden.\n" );
    exit( 1 );
}
echo "PASS: only Action Scheduler overdue-actions notice suppressed.\n";
