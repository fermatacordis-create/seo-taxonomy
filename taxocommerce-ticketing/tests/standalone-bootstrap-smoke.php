<?php
/**
 * Regression smoke for missing Ticketing messages and check separation.
 *
 * Standalone; no WordPress, network, database or customer record is required.
 */
define( 'ABSPATH', __DIR__ . '/' );

class TCTK_Event_Log {
    public static function record( $event, $id = 0 ) { unset( $event, $id ); }
}
function current_time( $format ) {
    return 'mysql' === $format ? '2026-11-20 20:00:00' : '';
}
require_once dirname( __DIR__ ) . '/includes/class-tctk-ticketing.php';

class TCTK_Fake_WPDB {
    public $prefix = 'wp_';
    public $insert_id = 0;
    public $update_called = false;
    public function insert( $table, $values ) {
        if ( 'wp_tctk_messages' !== $table || 'Initial user message' !== $values['body'] ) {
            return false;
        }
        $this->insert_id = 938;
        return 1;
    }
    public function update( $table, $values, $where ) {
        $this->update_called = 'wp_tctk_tickets' === $table && 21 === $where['id'];
        // Simulate a driver clearing the last insert ID on UPDATE.
        $this->insert_id = 0;
        return $this->update_called ? 1 : false;
    }
}
global $wpdb;
$wpdb = new TCTK_Fake_WPDB();
$id = TCTK_Ticketing::add_message( 21, 'Test', 'Initial user message', false, false );
if ( 938 !== $id || ! $wpdb->update_called ) {
    fwrite( STDERR, "FAIL: ID was lost between insert and ticket timestamp update\n" );
    exit( 1 );
}
$source = file_get_contents( dirname( __DIR__ ) . '/includes/class-tctk-ticketing.php' );
if ( ! preg_match( '/if\s*\(\s*!\s*empty\(\s*\$_POST\[\x27website\x27\]\s*\)\s*\)\s*\{(.{0,240})\}/s', $source, $honeypot ) ||
    false === strpos( $honeypot[1], "self::redirect( \$return, 'error' );" ) ||
    false !== strpos( $honeypot[1], "'created'" ) ) {
    fwrite( STDERR, "FAIL: honeypot can falsely claim ticket creation\n" );
    exit( 1 );
}
$autocheck = file_get_contents( dirname( __DIR__ ) . '/includes/class-tctk-autocheck.php' );
$codecheck = file_get_contents( dirname( __DIR__ ) . '/includes/class-tctk-autotest-code.php' );
$log = file_get_contents( dirname( __DIR__ ) . '/includes/class-tctk-event-log.php' );
$main = file_get_contents( dirname( __DIR__ ) . '/taxocommerce-ticketing.php' );
$expected = array(
    'actual persistence probe' => false !== strpos( $autocheck, 'TCTK_Ticketing::add_message(' ),
    'probe cleanup' => false !== strpos( $autocheck, "'probe_cleanup'" ),
    'functional JSON' => false !== strpos( $autocheck, 'tctk_autocheck_export' ),
    'independent static code scan' => false !== strpos( $codecheck, 'public static function analyze(' ),
    'five code speeds' => false !== strpos( $codecheck, '5 => array(' ),
    'privacy-minimal events' => false !== strpos( $log, 'const LIMIT = 150;' ) &&
        false === strpos( $log, 'REMOTE_ADDR' ),
    'user manual' => false !== strpos( $main, 'class-tctk-manuals.php' ),
    'event boot' => false !== strpos( $main, 'TCTK_Event_Log::boot();' ),
);
foreach ( $expected as $label => $pass ) {
    if ( ! $pass ) {
        fwrite( STDERR, "FAIL: {$label}\n" );
        exit( 1 );
    }
}
echo "PASS: Ticketing message ID, honeypot, activity log and both check modes\n";
