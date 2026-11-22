<?php
/**
 * Standalone read-only CSV validation regressions.
 *
 * No WordPress installation, imports, products or live downloads are touched.
 *
 * @package TaxoCommerceImportExport
 */

if ( 'cli' !== PHP_SAPI ) {
    exit;
}

if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', __DIR__ );
}

if ( ! class_exists( 'WP_Error' ) ) {
    class WP_Error {
        private $message;
        public function __construct( $code, $message ) {
            $this->message = $message;
        }
        public function get_error_message() {
            return $this->message;
        }
    }
}
function __( $text, $domain = '' ) { return $text; }
function absint( $number ) { return abs( (int) $number ); }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_-]/', '', strtolower( (string) $value ) ); }
function trailingslashit( $value ) { return rtrim( $value, '/' ) . '/'; }
function wp_upload_dir( $time = null, $create_dir = true ) {
    return array( 'basedir' => $GLOBALS['tcie_csv_fixture_basedir'] );
}

function tc_ie_export_file_check( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, 'FAIL: ' . $message . PHP_EOL );
        exit( 1 );
    }
}

class TaxoCommerce_Import_Export_CSV {
    public function export_header_for_job( $type ) {
        return array( 'record_type', 'id', 'title' );
    }
}

require dirname( __DIR__ ) . '/includes/class-taxocommerce-import-export-exporter.php';

$base = sys_get_temp_dir() . '/tc-ie-export-' . bin2hex( random_bytes( 6 ) );
$dir = $base . '/taxocommerce-import-export/exports';
tc_ie_export_file_check( mkdir( $dir, 0700, true ), 'Create isolated CSV test directory' );
$GLOBALS['tcie_csv_fixture_basedir'] = $base;
$path = $dir . '/export.csv';
file_put_contents( $path, "\xEF\xBB\xBFrecord_type,id,title\npage,123,Sample\n" );

$state = array(
    'path' => $path,
    'type' => 'pages',
    'complete' => true,
    'total' => 1,
    'processed' => 1,
    'examined' => 1,
    'file_size' => filesize( $path ),
);

tc_ie_export_file_check( true === TaxoCommerce_Import_Export_Exporter::validate_export_file( $state ), 'Completed CSV with BOM, header and row accepted' );

file_put_contents( $path, "\xEF\xBB\xBFrecord_type,id,title\n" );
clearstatcache( true, $path );
$state['file_size'] = filesize( $path );
tc_ie_export_file_check( is_wp_error( TaxoCommerce_Import_Export_Exporter::validate_export_file( $state ) ), 'Completed export without promised rows rejected' );

$state['complete'] = false;
$state['processed'] = 0;
tc_ie_export_file_check( true === TaxoCommerce_Import_Export_Exporter::validate_export_file( $state ), 'Header-only partial export is valid while running' );

file_put_contents( $path, "wrong,header\n" );
clearstatcache( true, $path );
$state['file_size'] = filesize( $path );
tc_ie_export_file_check( is_wp_error( TaxoCommerce_Import_Export_Exporter::validate_export_file( $state ) ), 'Incorrect CSV header rejected' );

file_put_contents( $path, "\xEF\xBB\xBFrecord_type,id,title\npage,123,Sample\n" );
clearstatcache( true, $path );
$state['file_size'] = filesize( $path ) + 10;
tc_ie_export_file_check( is_wp_error( TaxoCommerce_Import_Export_Exporter::validate_export_file( $state ) ), 'Truncated CSV rejected' );

$outside = $base . '/outside.csv';
file_put_contents( $outside, "\xEF\xBB\xBFrecord_type,id,title\npage,123,Sample\n" );
$state['path'] = $outside;
$state['file_size'] = filesize( $outside );
tc_ie_export_file_check( is_wp_error( TaxoCommerce_Import_Export_Exporter::validate_export_file( $state ) ), 'CSV outside plugin private exports directory rejected' );

unlink( $outside );
unlink( $path );
rmdir( $dir );
rmdir( dirname( $dir ) );
rmdir( $base );
echo "Export file smoke: OK (valid, empty, partial, malformed, truncated, out-of-scope)." . PHP_EOL;
