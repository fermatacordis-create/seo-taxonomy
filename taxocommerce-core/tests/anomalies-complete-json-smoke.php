<?php
/**
 * Regression of the complete JSON export's shared-report capture.
 * Does not require a database or a WordPress site.
 */
define( 'ABSPATH', __DIR__ );
function add_action( $hook, $callback ) {}
function wp_strip_all_tags( $text ) { return strip_tags( (string) $text ); }
function sanitize_title( $text ) {
    return trim( preg_replace( '/[^a-z0-9]+/', '-', strtolower( (string) $text ) ), '-' );
}
require dirname( __DIR__ ) . '/includes/class-taxocommerce-core-anomalies-full-json.php';

function assert_export( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, 'FAIL: ' . $message . PHP_EOL );
        exit( 1 );
    }
}

$GLOBALS['taxocommerce_core_anomalies_full_json_sections'] = array();
$html = '<h3>Categories without structural assignment</h3>'
    . '<p>These categories have no hub.</p>'
    . '<div>• Cintas transportadoras <code>(58240)</code><br>'
    . '• VEVOR DE - Auto <code>(58180)</code></div>'
    . '<form><input type="hidden" name="_wpnonce" value="do-not-export-this-token">'
    . '<button type="submit">Borrar categorías</button></form>';

taxocommerce_core_anomalies_full_json_capture_section( $html );
assert_export( count( $GLOBALS['taxocommerce_core_anomalies_full_json_sections'] ) === 1, 'Exactly one section captured' );
$row = $GLOBALS['taxocommerce_core_anomalies_full_json_sections'][0];
assert_export( $row['section'] === 'Categories without structural assignment', 'Original heading preserved' );
assert_export( strpos( implode( "\n", $row['evidence_lines'] ), 'Cintas transportadoras (58240)' ) !== false, 'First category evidence included' );
assert_export( strpos( implode( "\n", $row['evidence_lines'] ), 'VEVOR DE - Auto (58180)' ) !== false, 'Second category evidence included' );
assert_export( strpos( implode( "\n", $row['evidence_lines'] ), 'do-not-export-this-token' ) === false, 'Form nonce excluded' );
assert_export( strpos( implode( "\n", $row['evidence_lines'] ), 'Borrar categorías' ) === false, 'Action controls excluded' );
unset( $GLOBALS['taxocommerce_core_anomalies_full_json_sections'] );
taxocommerce_core_anomalies_full_json_capture_section( $html );
assert_export( ! isset( $GLOBALS['taxocommerce_core_anomalies_full_json_sections'] ), 'Normal page render does not collect evidence' );

echo "Core full anomalies JSON smoke passed.\n";
