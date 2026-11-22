<?php
/**
 * Smoke offline: etiquetas IGIC/IPSI + helpers UE sin WooCommerce runtime completo.
 */
define('ABSPATH', __DIR__ . '/');
if (!function_exists('sanitize_title')) {
    function sanitize_title($s) { return strtolower(preg_replace('/[^a-z0-9]+/', '-', (string) $s)); }
}
if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($s) { return trim(strip_tags((string) $s)); }
}
if (!defined('DAY_IN_SECONDS')) { define('DAY_IN_SECONDS', 86400); }
if (!defined('MINUTE_IN_SECONDS')) { define('MINUTE_IN_SECONDS', 60); }

// Minimal stubs for settings used by zone_definitions indirectly via class load.
class SEO_Facturas_Settings {
    public static function get($k, $d = null) {
        $map = array(
            'tax_eu_b2b_enabled' => 1,
            'tax_manager_enabled' => 1,
            'tax_eu_reverse_charge_note' => 'ISP note',
        );
        return array_key_exists($k, $map) ? $map[$k] : $d;
    }
    public static function taxation() {
        return array(
            'tax_peninsula_rate' => 21,
            'tax_baleares_rate' => 21,
            'tax_canarias_rate' => 7,
            'tax_ceuta_rate' => 10,
            'tax_melilla_rate' => 10,
            'tax_special_note' => 'special',
            'tax_shipping' => 1,
            'tax_special_rates_enabled' => 1,
            'tax_igic_general' => 7,
            'tax_igic_reducido' => 3,
            'tax_ipsi_ceuta_general' => 10,
            'tax_ipsi_melilla_r2' => 2,
        );
    }
}

require_once dirname(__DIR__) . '/includes/class-seo-facturas-tax.php';
require_once dirname(__DIR__) . '/includes/class-seo-facturas-vies.php';
require_once dirname(__DIR__) . '/includes/class-seo-facturas-eu-vat.php';
require_once dirname(__DIR__) . '/includes/class-seo-facturas-special-rates.php';

function assert_true($cond, $msg) {
    if (!$cond) {
        fwrite(STDERR, "FAIL: $msg\n");
        exit(1);
    }
    echo "OK: $msg\n";
}

$zones = SEO_Facturas_Tax::zone_definitions(SEO_Facturas_Settings::taxation());
assert_true($zones['canarias']['tax'] === 'IGIC', 'Canarias tax name IGIC');
assert_true(strpos($zones['canarias']['label'], 'IGIC') === 0, 'Canarias label IGIC');
assert_true($zones['ceuta']['tax'] === 'IPSI', 'Ceuta tax name IPSI');
assert_true($zones['melilla']['tax'] === 'IPSI', 'Melilla tax name IPSI');
assert_true($zones['intra_eu']['rate'] === 0.0, 'Intra EU rate 0');
assert_true(SEO_Facturas_Tax::classify_location('ES', 'GC', '35001') === 'canarias', 'classify Canarias');
assert_true(SEO_Facturas_Eu_Vat::is_eu_vat_country('FR'), 'FR is EU');
assert_true(!SEO_Facturas_Eu_Vat::is_eu_vat_country('ES'), 'ES not in EU list for ISP');
assert_true(!SEO_Facturas_Eu_Vat::is_eu_vat_country('US'), 'US not EU');
assert_true(SEO_Facturas_Eu_Vat::normalize_vat('FR', '123') === 'FR123', 'normalize vat');
$invalid = SEO_Facturas_Vies::validate('FR', '');
assert_true($invalid['status'] === 'invalid', 'empty vat invalid');
$igic = SEO_Facturas_Special_Rates::igic_types();
assert_true(isset($igic['general']) && abs($igic['general']['rate'] - 7.0) < 0.001, 'IGIC general 7');
assert_true(count($igic) === 6, 'IGIC has 6 types');
$ipsi = SEO_Facturas_Special_Rates::ipsi_types();
assert_true(count($ipsi) === 7, 'IPSI has 7 types');
assert_true(SEO_Facturas_Special_Rates::resolve_type_key('canarias', 'igic-reducido') === 'reducido', 'resolve IGIC reducido');
assert_true(SEO_Facturas_Special_Rates::resolve_type_key('ceuta', '') === 'general', 'default IPSI general');
assert_true(SEO_Facturas_Special_Rates::resolve_type_key('melilla', 'ipsi-4') === 'r4', 'resolve IPSI 4');
$settings = array_merge(SEO_Facturas_Settings::taxation(), array(
    'tax_igic_general' => 7, 'tax_igic_reducido' => 3,
    'tax_ipsi_ceuta_general' => 10, 'tax_ipsi_melilla_r2' => 2,
));
assert_true(abs(SEO_Facturas_Special_Rates::rate_for($settings, 'canarias', 'reducido') - 3) < 0.001, 'rate IGIC reducido');
assert_true(abs(SEO_Facturas_Special_Rates::rate_for($settings, 'melilla', 'r2') - 2) < 0.001, 'rate IPSI melilla 2');
echo "ALL PASS\n";
