<?php
/**
 * Offline budget and isolation contract; does not require a WordPress site.
 *
 * @package TaxoCommerceVocabulary
 */
define( 'ABSPATH', __DIR__ . '/' );
function absint( $value ) { return abs( (int) $value ); }
require dirname( __DIR__ ) . '/includes/class-tcv-classifier-batch.php';

function tcv_cpu_assert( $ok, $message ) {
    if ( ! $ok ) {
        fwrite( STDERR, 'FAIL Vocabulary CPU budget: ' . $message . PHP_EOL );
        exit( 1 );
    }
}

$last_rows = 0;
$last_seconds = 0;
$last_delay = 1000;
for ( $level = 1; $level <= 5; ++$level ) {
    $budget = TCV_Classifier_Batch::budget( $level );
    tcv_cpu_assert( $budget['rows'] > $last_rows, 'five levels increase batch size' );
    tcv_cpu_assert( $budget['seconds'] >= $last_seconds && $budget['seconds'] <= 12, 'strict time ceiling' );
    tcv_cpu_assert( $budget['delay'] < $last_delay && $budget['delay'] >= 15, 'delay decreases without eliminating breathing room' );
    $last_rows = $budget['rows'];
    $last_seconds = $budget['seconds'];
    $last_delay = $budget['delay'];
}
tcv_cpu_assert( TCV_Classifier_Batch::budget( 0 ) === TCV_Classifier_Batch::budget( 1 ), 'invalid low level is clamped' );
tcv_cpu_assert( TCV_Classifier_Batch::budget( 999 ) === TCV_Classifier_Batch::budget( 5 ), 'invalid high level is clamped' );
$source = file_get_contents( dirname( __DIR__ ) . '/includes/class-tcv-classifier-batch.php' );
$bootstrap = file_get_contents( dirname( __DIR__ ) . '/taxocommerce-vocabulary.php' );
$engine = file_get_contents( dirname( __DIR__ ) . '/includes/class-tcv-classifier.php' );
tcv_cpu_assert( false !== strpos( $source, 'wp_schedule_single_event(' ), 'uses native WordPress scheduling' );
tcv_cpu_assert( false !== strpos( $source, 'wp_clear_scheduled_hook(' ), 'cancel and pause clear pending events' );
tcv_cpu_assert( false !== strpos( $source, 'add_option( self::LOCK' ), 'overlapping Cron batches are locked' );
tcv_cpu_assert( false !== strpos( $source, "'safe' === ( " ), 'automatic assignment is opt-in' );
tcv_cpu_assert( false !== strpos( $bootstrap, 'TCV_Classifier_Batch::boot()' ), 'standalone plugin registers the new worker' );
tcv_cpu_assert( false !== strpos( $engine, "'classifier_auto'" ), 'automatic assignments are distinguishable from human-reviewed evidence' );
tcv_cpu_assert( false === strpos( $source, 'seo_classifier_job_run(' ), 'no monolithic scheduler is required' );
echo "Vocabulary CPU batch contract: PASS\n";
