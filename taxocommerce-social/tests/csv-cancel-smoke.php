<?php
/**
 * Regression checks for cancellation CSV import: independent of WordPress.
 *
 * Run: php taxocommerce-social/tests/csv-cancel-smoke.php
 */
declare(strict_types=1);

define( 'ABSPATH', __DIR__ . '/' );
define( 'MINUTE_IN_SECONDS', 60 );

class WP_Error {
    private $message;
    public function __construct( $code, $message ) { $this->message = $message; }
    public function get_error_message() { return $this->message; }
}
function __( $text, $domain = '' ) { return $text; }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function add_action( $hook, $callback ) {}
function absint( $value ) { return abs( (int) $value ); }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) ); }
function sanitize_text_field( $value ) { return trim( (string) $value ); }
function get_the_title( $id ) { return 'Contenido ' . $id; }
function tcsocial_network_csv_stream_open( $path, $mode ) { return fopen( $path, $mode ); }
function tcsocial_network_csv_stream_close( $handle ) { return fclose( $handle ); }
function tcsocial_network_scheduler_detect_delimiter( $line ) {
    $counts = array( ';' => substr_count( $line, ';' ), ',' => substr_count( $line, ',' ), "\t" => substr_count( $line, "\t" ) );
    arsort( $counts );
    $delimiter = key( $counts );
    return $counts[ $delimiter ] ? $delimiter : ';';
}
function tcsocial_network_scheduler_normalize_header( $value ) {
    $value = preg_replace( '/^\xEF\xBB\xBF/', '', (string) $value );
    $value = strtolower( trim( $value ) );
    $value = preg_replace( '/[^a-z0-9_]+/', '_', $value );
    return trim( $value, '_' );
}
function tcsocial_network_scheduler_parse_import_datetime( $value ) {
    $date = DateTimeImmutable::createFromFormat( '!Y-m-d H:i', (string) $value, new DateTimeZone( 'UTC' ) );
    return $date ? $date->getTimestamp() : new WP_Error( 'invalid_date', 'Fecha inválida' );
}
function tcsocial_network_get_provider( $name ) {
    return in_array( $name, array( 'facebook', 'pinterest' ), true ) ? array( 'label' => $name ) : null;
}
$GLOBALS['tcsocial_test_schedule'] = array(
    '101|facebook' => strtotime( '2026-11-16 22:00:00 UTC' ),
    '102|pinterest' => strtotime( '2026-10-25 22:00:00 UTC' ),
    '103|facebook' => strtotime( '2026-11-20 22:00:00 UTC' ),
);
function tcsocial_network_get_scheduled_timestamp( $id, $provider ) {
    return $GLOBALS['tcsocial_test_schedule'][ $id . '|' . $provider ] ?? 0;
}

require __DIR__ . '/../includes/csv-cancel.php';

function check( $condition, $description ) {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: $description\n" );
        exit( 1 );
    }
}
$cancel_file = tempnam( sys_get_temp_dir(), 'tcsocial-cancel-' );
$schedule_file = tempnam( sys_get_temp_dir(), 'tcsocial-schedule-' );
if ( ! $cancel_file || ! $schedule_file ) {
    fwrite( STDERR, "Unable to create temporary files.\n" );
    exit( 1 );
}
try {
    file_put_contents( $cancel_file, "\xEF\xBB\xBFcontenido_id;redes;fecha_hora;accion;titulo\n"
        . "101;facebook;2026-11-16 22:00;cancelar;Non-Spanish example\n"
        . "102;pinterest;2026-10-25 22:00;cancelar;Past due entry\n"
        . "103;facebook;2026-11-19 22:00;cancelar;Changed since export\n" );
    file_put_contents( $schedule_file, "contenido_id;redes;fecha_hora\n"
        . "101;facebook;2026-11-16 22:00\n" );

    check( tcsocial_csv_cancel_has_action_column( $cancel_file ), 'Existing CSV import must identify cancellation column with UTF-8 BOM' );
    check( ! tcsocial_csv_cancel_has_action_column( $schedule_file ), 'Ordinary schedule CSV must not be redirected to cancellation' );
    $entries = tcsocial_csv_cancel_parse( $cancel_file );
    check( ! is_wp_error( $entries ) && 3 === count( $entries ), 'All cancellation rows must parse' );
    check( empty( $entries[0]['errors'] ), 'Future matched entry must be cancelable' );
    check( empty( $entries[1]['errors'] ), 'Past matched entry must be cancelable' );
    check( ! empty( $entries[2]['errors'] ), 'Date mismatch must block cancellation of reprogrammed entry' );
    check( (int) $entries[0]['expected'] === $GLOBALS['tcsocial_test_schedule']['101|facebook'], 'Exact timestamp must be preserved' );
    check( (int) $entries[1]['expected'] === $GLOBALS['tcsocial_test_schedule']['102|pinterest'], 'Past timestamp must be preserved' );

    echo "OK: cancel CSV detection, ordinary CSV compatibility, future/expired entries and date safeguards.\n";
} finally {
    unlink( $cancel_file );
    unlink( $schedule_file );
}
