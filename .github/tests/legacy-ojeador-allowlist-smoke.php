<?php
/**
 * One-off regression: legacy Ojeador's JSON SQL export allowlist (#2487).
 *
 * No WordPress installation or database connection is needed.
 */
define( 'ABSPATH', __DIR__ );
define( 'ARRAY_A', 'ARRAY_A' );

function wp_json_encode( $value, $flags = 0 ) {
    return json_encode( $value, $flags );
}

final class Ojeador_Export_Test_DB {
    public $queries = array();
    private $fixture = array();

    public function __construct() {
        for ( $i = 1; $i <= 1203; $i++ ) {
            $this->fixture[] = array(
                'id'        => $i,
                'term_id'   => $i,
                'object_id' => $i,
            );
        }
    }

    public function expected_json() {
        return json_encode( $this->fixture, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
    }

    public function prepare( $query, $limit, $offset ) {
        $values = array( (int) $limit, (int) $offset );
        $index = 0;
        return preg_replace_callback(
            '/%d/',
            static function () use ( &$index, $values ) {
                return (string) $values[ $index++ ];
            },
            $query
        );
    }

    public function get_results( $query, $output ) {
        if ( ARRAY_A !== $output || ! preg_match( '/ LIMIT ([0-9]+) OFFSET ([0-9]+)$/', $query, $match ) ) {
            throw new RuntimeException( 'Unparameterized query.' );
        }
        $this->queries[] = $query;
        return array_slice( $this->fixture, (int) $match[2], (int) $match[1] );
    }
}

require dirname( __DIR__, 2 ) . '/includes/ojeador/class-ojeador-admin.php';

$method = new ReflectionMethod( 'SEO_Ojeador_Admin', 'stream_table_json' );
$method->setAccessible( true );
$wpdb = new Ojeador_Export_Test_DB();

$run = static function ( $table, $order, $where ) use ( $method, $wpdb ) {
    $wpdb->queries = array();
    ob_start();
    $method->invoke( null, $table, $order, $where );
    return array( ob_get_clean(), $wpdb->queries );
};

$check = static function ( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, 'FAIL: ' . $message . PHP_EOL );
        exit( 1 );
    }
};

foreach ( array( 'id ASC', 'term_id ASC', 'object_id ASC' ) as $order ) {
    foreach ( array( '1=1', "object_type='product_cat'" ) as $where ) {
        list( $json, $queries ) = $run( 'wp_seo_ojeador', $order, $where );
        $check( $json === $wpdb->expected_json(), 'Unchanged export content: ' . $order . '/' . $where );
        $check( 3 === count( $queries ), '500+500+203 paging' );
    }
}
foreach ( array(
    array( 'wp_seo_ojeador', 'id DESC', '1=1' ),
    array( 'wp_seo_ojeador', 'id ASC', '1=1 OR 1=1' ),
    array( 'wp_seo_ojeador;DROP', 'id ASC', '1=1' ),
) as $invalid ) {
    list( $json, $queries ) = $run( $invalid[0], $invalid[1], $invalid[2] );
    $check( '[]' === $json && 0 === count( $queries ), 'Reject unapproved input without queries' );
}

echo "Legacy Ojeador stream_table_json allowlist: PASS\n";
