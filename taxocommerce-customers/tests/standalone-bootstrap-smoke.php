<?php
/**
 * Standalone regression: no WordPress database, WooCommerce installation or FTP.
 *
 * @package TaxoCommerceCustomers
 */
error_reporting( E_ALL );
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' ); }
if ( ! defined( 'HOUR_IN_SECONDS' ) ) { define( 'HOUR_IN_SECONDS', 3600 ); }
if ( ! defined( 'DAY_IN_SECONDS' ) ) { define( 'DAY_IN_SECONDS', 86400 ); }
if ( ! defined( 'ARRAY_A' ) ) { define( 'ARRAY_A', 'ARRAY_A' ); }

function tccust_test_check( $ok, $message ) {
	if ( ! $ok ) { throw new RuntimeException( $message ); }
}
function plugin_dir_path( $file ) { return dirname( $file ) . '/'; }
function plugin_dir_url( $file ) { return 'https://example.test/wp-content/plugins/' . basename( dirname( $file ) ) . '/'; }
function plugin_basename( $file ) { return basename( dirname( $file ) ) . '/' . basename( $file ); }
function register_activation_hook( $file, $callback ) {}
function add_action( $name, $callback, $priority = 10, $args = 1 ) {}
function add_filter( $name, $callback ) {}
function __( $value, $domain = '' ) { return $value; }
function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function sanitize_textarea_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function sanitize_email( $value ) { return filter_var( trim( (string) $value ), FILTER_SANITIZE_EMAIL ); }
function is_email( $value ) { return false !== filter_var( $value, FILTER_VALIDATE_EMAIL ); }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) ); }
function absint( $value ) { return abs( (int) $value ); }
function current_time( $type ) { return '2026-11-13 16:00:00'; }
function wp_salt( $scheme ) { return 'regression-test-only-key'; }
function wc_get_orders( $args ) { return (object) array( 'orders' => array(), 'max_num_pages' => 0 ); }
function wc_get_order( $id ) { return null; }
function wc_get_is_paid_statuses() { return array( 'processing', 'completed' ); }

class TCCUST_FakeDB {
	public $prefix = 'wp_';
	public $insert_id = 0;
	public $rows = array();
	public $links = array();
	private $counter = 0;
	public function prepare( $sql, ...$args ) { return array( $sql, $args ); }
	public function get_row( $query, $output = null ) {
		$sql = $query[0]; $args = $query[1];
		foreach ( $this->rows as $row ) {
			if ( false !== strpos( $sql, 'WHERE id=%d' ) && (int) $row['id'] === (int) end( $args ) ) { return $row; }
			if ( false !== strpos( $sql, 'WHERE woo_user_id=%d' ) && (int) $row['woo_user_id'] === (int) end( $args ) && $row['woo_user_id'] ) { return $row; }
			if ( false !== strpos( $sql, 'WHERE email_key=%s' ) && $row['email_key'] === end( $args ) ) { return $row; }
			if ( false !== strpos( $sql, 'WHERE email_digest=%s' ) && ( $row['email_digest'] ?? null ) === end( $args ) && ! empty( $row['erased_at'] ) ) { return $row; }
		}
		return null;
	}
	public function insert( $table, $values, $formats = null ) {
		$id = ++$this->counter;
		$values['id'] = $id;
		$values += array( 'woo_user_id' => null, 'source' => 'manual', 'email_key' => null );
		$this->rows[ $id ] = $values;
		$this->insert_id = $id;
		return 1;
	}
	public function update( $table, $fields, $where, $formats = null, $where_format = null ) {
		$id = (int) $where['id'];
		if ( ! isset( $this->rows[ $id ] ) ) { return false; }
		$this->rows[ $id ] = array_merge( $this->rows[ $id ], $fields );
		return 1;
	}
	public function delete( $table, $where ) {
		if ( isset( $where['order_id'] ) ) { unset( $this->links[ (int) $where['order_id'] ] ); return 1; }
		unset( $this->rows[ (int) $where['id'] ] );
		return 1;
	}
	public function replace( $table, $values, $formats = null ) {
		$this->links[ (int) $values['order_id'] ] = $values;
		return 1;
	}
	public function get_var( $sql ) { return null; }
}

class TCCUST_FakeDate {
	public function date( $format ) { return '2026-11-13 12:00:00'; }
}
class WC_Order {
	private $attrs;
	public function __construct( $attrs ) { $this->attrs = $attrs; }
	public function get_id() { return $this->attrs['id']; }
	public function is_paid() { return $this->attrs['paid']; }
	public function get_customer_id() { return $this->attrs['user_id']; }
	public function get_billing_email() { return $this->attrs['email']; }
	public function get_billing_first_name() { return $this->attrs['name']; }
	public function get_billing_last_name() { return ''; }
	public function get_billing_company() { return ''; }
	public function get_billing_phone() { return ''; }
	public function get_billing_address_1() { return ''; }
	public function get_billing_postcode() { return ''; }
	public function get_billing_city() { return ''; }
	public function get_billing_state() { return ''; }
	public function get_billing_country() { return ''; }
	public function get_date_paid() { return new TCCUST_FakeDate(); }
	public function get_date_created() { return new TCCUST_FakeDate(); }
}
$wpdb = new TCCUST_FakeDB();
require_once dirname( __DIR__ ) . '/taxocommerce-customers.php';

tccust_test_check( class_exists( 'TCCUST_Admin' ), 'Admin class loads standalone' );
tccust_test_check( class_exists( 'TaxoCommerce_Customers_Autotest_Code' ), 'Code checker loads standalone' );
tccust_test_check( class_exists( 'TCCUST_Privacy' ), 'Privacy integration loads standalone' );
tccust_test_check( class_exists( 'TCCUST_CSV_Export' ), 'Server-side CSV exporter loads standalone' );
tccust_test_check( 17 === count( TCCUST_CSV::HEADERS ), 'CSV has exactly 17 columns' );
tccust_test_check( count( TCCUST_CSV::HEADERS ) === count( array_unique( TCCUST_CSV::HEADERS ) ), 'CSV headers unique' );
tccust_test_check( null === TCCUST_DB::email_key( 'not-an-email' ), 'Invalid email is never used for identity' );
tccust_test_check( 'buyer@example.com' === TCCUST_DB::email_key( ' Buyer@Example.COM ' ), 'Email normalized exactly' );
$columns = new ReflectionMethod( 'TCCUST_CSV', 'valid_columns' );
$columns->setAccessible( true );
tccust_test_check( true === $columns->invoke( null, TCCUST_CSV::HEADERS ), 'Export headers round trip' );
tccust_test_check( false === $columns->invoke( null, array_slice( TCCUST_CSV::HEADERS, 1 ) ), 'CSV missing ID rejected' );
tccust_test_check( false === $columns->invoke( null, array_merge( TCCUST_CSV::HEADERS, array( 'marketing_consent' ) ) ), 'Unknown CSV column rejected' );

$fields = TCCUST_Admin::valid_data( array(
	'nombre' => 'Contacto', 'telefono' => '600 000 000', 'observaciones' => 'WhatsApp: consultas sobre piezas.'
) );
tccust_test_check( is_array( $fields ), 'Manual phone-only contact valid' );
tccust_test_check( false === TCCUST_Admin::valid_data( array( 'nombre' => 'Sin contacto' ) ), 'Manual record must include contact route' );
tccust_test_check( false === TCCUST_Admin::valid_data( array( 'nombre' => 'Mal', 'email' => 'incorrecto' ) ), 'Invalid email rejected' );

$manual = TCCUST_DB::save( array_merge( $fields, array(
	'email' => 'buyer@example.com', 'email_key' => 'buyer@example.com',
	'source' => 'manual', 'woo_user_id' => null
) ) );
tccust_test_check( 1 === $manual, 'Manual CRM record created' );
$guest = new WC_Order( array( 'id' => 77, 'user_id' => 0, 'email' => 'buyer@example.com', 'name' => 'Comprador', 'paid' => true ) );
tccust_test_check( 'linked' === TCCUST_Sync::sync_order( $guest ), 'Paid guest links to existing manual email' );
tccust_test_check( 1 === count( $wpdb->rows ), 'Manual and guest are not duplicated' );
tccust_test_check( 1 === count( $wpdb->links ), 'One order reference stored' );
tccust_test_check( 'WhatsApp: consultas sobre piezas.' === $wpdb->rows[1]['observaciones'], 'Private notes survive purchase link' );
tccust_test_check( 'linked' === TCCUST_Sync::sync_order( $guest ) && 1 === count( $wpdb->links ), 'Repeated checkout hook does not double count' );
$registered = new WC_Order( array( 'id' => 78, 'user_id' => 9, 'email' => 'buyer@example.com', 'name' => 'Comprador', 'paid' => true ) );
tccust_test_check( 'linked' === TCCUST_Sync::sync_order( $registered ), 'Account with same email safely links' );
tccust_test_check( 1 === count( $wpdb->rows ) && 2 === count( $wpdb->links ), 'Two orders, one CRM profile' );
tccust_test_check( 9 === (int) $wpdb->rows[1]['woo_user_id'], 'Registered account attached' );
$other = new WC_Order( array( 'id' => 79, 'user_id' => 10, 'email' => 'buyer@example.com', 'name' => 'Otra persona', 'paid' => true ) );
tccust_test_check( 'conflict' === TCCUST_Sync::sync_order( $other ), 'Different registered IDs do not silently merge' );
tccust_test_check( 2 === count( $wpdb->links ), 'Conflict does not create order link' );
$unpaid = new WC_Order( array( 'id' => 77, 'user_id' => 0, 'email' => 'buyer@example.com', 'name' => 'Comprador', 'paid' => false ) );
tccust_test_check( 'unpaid' === TCCUST_Sync::sync_order( $unpaid ) && 1 === count( $wpdb->links ), 'Unpaid order removed from purchase links' );
$anonymous = new WC_Order( array( 'id' => 80, 'user_id' => 0, 'email' => '', 'name' => 'No identificable', 'paid' => true ) );
tccust_test_check( 'skipped' === TCCUST_Sync::sync_order( $anonymous ), 'Guest with no email skipped' );

// Erasure must not retain duplicate CRM billing data or be undone by a later
// WooCommerce hook (registered orders and guest checkout share the same email).
$erasure = TCCUST_Privacy::eraser( 'buyer@example.com' );
tccust_test_check( ! empty( $erasure['items_removed'] ), 'Personal data erasure reports success' );
tccust_test_check( '' === $wpdb->rows[1]['email'] && null === $wpdb->rows[1]['email_key'], 'Erased CRM has no plain email' );
foreach ( array( 'nombre', 'apellidos', 'empresa', 'telefono', 'whatsapp', 'direccion', 'codigo_postal', 'localidad', 'provincia', 'pais', 'canal_preferido', 'etiquetas', 'observaciones' ) as $field ) {
	tccust_test_check( '' === $wpdb->rows[1][ $field ], 'Erasure clears private field: ' . $field );
}
tccust_test_check( ! empty( $wpdb->rows[1]['erased_at'] ), 'Erasure marker retained' );
tccust_test_check( 9 === (int) $wpdb->rows[1]['woo_user_id'], 'Technical Woo user association retained' );
tccust_test_check( 1 === count( $wpdb->links ), 'Woo order reference retained after erasure' );
tccust_test_check( ! empty( $wpdb->rows[1]['email_digest'] ), 'Non-reversible digest retained for guest matching' );
$guest_after_erase = new WC_Order( array( 'id' => 81, 'user_id' => 0, 'email' => 'buyer@example.com', 'name' => 'Debe permanecer anónimo', 'paid' => true ) );
tccust_test_check( 'linked' === TCCUST_Sync::sync_order( $guest_after_erase ), 'Redacted guest identity still links order' );
tccust_test_check( '' === $wpdb->rows[1]['email'] && '' === $wpdb->rows[1]['nombre'], 'Automatic sync never restores erased CRM PII' );
tccust_test_check( 1 === count( $wpdb->rows ), 'Erasure prevents duplicate buyer creation' );
tccust_test_check( false !== strpos( file_get_contents( dirname( __DIR__ ) . '/assets/admin.js' ), 'Opening the directory must not trigger' ), 'No automatic historical backfill from page load' );
tccust_test_check( false === strpos( file_get_contents( dirname( __DIR__ ) . '/assets/admin.js' ), 'new Blob(' ), 'CSV export does not use a browser memory Blob' );


$base = dirname( __DIR__ );
$csv = file_get_contents( $base . '/includes/class-tccust-csv.php' );
tccust_test_check( false === strpos( $csv, 'wp_update_post(' ), 'No WordPress order writes' );
tccust_test_check( false === strpos( $csv, 'wc_update_order(' ), 'No WooCommerce order writes' );
tccust_test_check( false !== strpos( $csv, 'MAX_BATCH = 350' ), 'Bounded CSV batch size' );
tccust_test_check( false !== strpos( $csv, 'flock( $handle, LOCK_EX | LOCK_NB )' ), 'CSV import locked per temporary file' );
tccust_test_check( false !== strpos( $csv, 'TCCUST_DB::find( $id )' ), 'Existing CSV updates identify CRM record' );
tccust_test_check( false !== strpos( file_get_contents( $base . '/assets/admin.js' ), "tccust_csv_cancel" ), 'Cancellation wired in UI' );
tccust_test_check( false !== strpos( file_get_contents( $base . '/includes/class-tccust-sync.php' ), "'paginate' => true" ), 'HPOS API paginated backfill' );
tccust_test_check( is_file( $base . '/CHANGELOG.md' ) && is_file( $base . '/readme.txt' ), 'Standalone plugin packaging includes documentation' );

echo "TaxoCommerce Customers standalone, identity, purchase and CSV contracts passed\n";
