<?php
/**
 * Regression for inclusive IVA: standalone and read-only.
 * Run: php taxocommerce-facturacion/tests/inclusive-iva-smoke.php
 */
define('ABSPATH', __DIR__);
$GLOBALS['billing_test_options'] = array(
    'woocommerce_calc_taxes' => 'yes',
    'woocommerce_prices_include_tax' => 'yes',
);
function get_option($name, $default = false) {
    return array_key_exists($name, $GLOBALS['billing_test_options']) ? $GLOBALS['billing_test_options'][$name] : $default;
}
function sanitize_title($value) { return strtolower(trim((string) $value)); }
function is_wp_error($value) { return $value instanceof WP_Error; }
class WP_Error {
    public $code;
    public $message;
    public function __construct($code, $message) { $this->code = $code; $this->message = $message; }
    public function get_error_code() { return $this->code; }
}
class SEO_Facturas_Settings {
    public static function taxation() {
        return array('tax_prices_include_tax' => 1, 'tax_restrict_to_spain' => 0, 'tax_peninsula_rate' => 21,
            'tax_baleares_rate' => 21, 'tax_canarias_rate' => 0, 'tax_ceuta_rate' => 0, 'tax_melilla_rate' => 0);
    }
    public static function get($key, $default = null) {
        $values = self::taxation();
        return isset($values[$key]) ? $values[$key] : $default;
    }
}
class BillingTaxProduct {
    private $status;
    private $class;
    public function __construct($status = 'taxable', $class = '') { $this->status = $status; $this->class = $class; }
    public function get_tax_status() { return $this->status; }
    public function get_tax_class() { return $this->class; }
}
class BillingTaxCart {
    private $lines;
    public function __construct($lines) { $this->lines = $lines; }
    public function get_cart() { return $this->lines; }
}
class BillingTaxOrderItem {
    private $product;
    private $total;
    private $tax;
    public function __construct($product, $total, $tax) { $this->product = $product; $this->total = $total; $this->tax = $tax; }
    public function get_product() { return $this->product; }
    public function get_total() { return $this->total; }
    public function get_total_tax() { return $this->tax; }
}
class BillingTaxOrder {
    private $items;
    public function __construct($items) { $this->items = $items; }
    public function get_items($type) { return $this->items; }
    public function get_shipping_country() { return 'ES'; }
    public function get_shipping_state() { return 'M'; }
    public function get_shipping_postcode() { return '28001'; }
}
require dirname(__DIR__) . '/includes/class-seo-facturas-tax.php';
function assert_tax($condition, $label) {
    if (!$condition) { fwrite(STDERR, "FAIL: " . $label . PHP_EOL); exit(1); }
    echo "PASS: " . $label . PHP_EOL;
}
$taxable = new BillingTaxProduct();
$missing = new BillingTaxCart(array(array('data' => $taxable, 'line_total' => 513.72, 'line_tax' => 0)));
$correct = new BillingTaxCart(array(array('data' => $taxable, 'line_total' => 513.72, 'line_tax' => 107.88)));
assert_tax(is_wp_error(SEO_Facturas_Tax::validate_cart_tax($missing, array('country' => 'ES', 'state' => 'M', 'postcode' => '28001'))),
    'Presupuesto/proforma: taxable standard line with 0 IVA rejected');
assert_tax(true === SEO_Facturas_Tax::validate_cart_tax($correct, array('country' => 'ES', 'state' => 'M', 'postcode' => '28001')),
    'Presupuesto/proforma: tax correctly calculated accepted');
assert_tax(true === SEO_Facturas_Tax::validate_cart_tax($missing, array('country' => 'ES', 'state' => 'GC', 'postcode' => '35001')),
    'Canarias: no automatic Spanish mainland IVA');
$exempt = new BillingTaxProduct('none', '');
assert_tax(true === SEO_Facturas_Tax::validate_cart_tax(new BillingTaxCart(array(
    array('data' => $exempt, 'line_total' => 10, 'line_tax' => 0)
)), array('country' => 'ES')), 'Tax-exempt products are not incorrectly blocked');
$order = new BillingTaxOrder(array(new BillingTaxOrderItem($taxable, 621.60, 0)));
assert_tax(is_wp_error(SEO_Facturas_Tax::validate_order_tax($order)),
    'Factura/proforma: unpaid tax on standard taxable order line is caught');
$order_ok = new BillingTaxOrder(array(new BillingTaxOrderItem($taxable, 513.72, 107.88)));
assert_tax(true === SEO_Facturas_Tax::validate_order_tax($order_ok),
    'Factura/proforma: order correctly calculated accepted');
assert_tax(abs(round(621.60 / 1.21, 2) - 513.72) < 0.0001 &&
    abs(round(621.60 - 621.60 / 1.21, 2) - 107.88) < 0.0001,
    'PVP 621.60 remains fixed with 513.72 base and 107.88 IVA');
echo "Inclusive IVA smoke passed." . PHP_EOL;
