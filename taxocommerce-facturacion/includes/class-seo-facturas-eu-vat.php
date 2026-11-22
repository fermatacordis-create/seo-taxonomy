<?php
/**
 * Checkout B2B UE: NIF-IVA, validación VIES e inversión del sujeto pasivo.
 *
 * Canarias, Ceuta y Melilla quedan fuera: no son territorio IVA UE.
 */

defined('ABSPATH') || exit;

final class SEO_Facturas_Eu_Vat {
    const SESSION_KEY = 'seo_facturas_eu_vat';
    const META_VAT = '_billing_vat';
    const META_VAT_VALIDATED = '_seo_facturas_vat_validated';
    const META_VAT_CHECKED_AT = '_seo_facturas_vat_validated_at';
    const META_VAT_COUNTRY = '_seo_facturas_vat_country';
    const META_VAT_STATUS = '_seo_facturas_vat_status';
    const META_IS_B2B = '_seo_facturas_is_eu_b2b';
    const META_REGIME = '_seo_facturas_tax_regime';

    private static $initialized = false;

    public static function init() {
        if (self::$initialized) {
            return;
        }
        self::$initialized = true;

        if (!self::enabled()) {
            return;
        }

        add_filter('woocommerce_billing_fields', array(__CLASS__, 'billing_fields'), 20);
        add_action('woocommerce_checkout_process', array(__CLASS__, 'validate_checkout'));
        add_action('woocommerce_checkout_create_order', array(__CLASS__, 'persist_order_meta'), 20, 2);
        add_action('woocommerce_checkout_update_order_meta', array(__CLASS__, 'persist_order_meta_legacy'), 20, 1);
        add_action('woocommerce_cart_calculate_fees', array(__CLASS__, 'refresh_session_from_customer'), 5);
    }

    public static function enabled() {
        return (bool) SEO_Facturas_Settings::get('tax_eu_b2b_enabled', 0)
            && (bool) SEO_Facturas_Settings::get('tax_manager_enabled', 0);
    }

    /**
     * Estados miembros UE del IVA (sin ES; sin GB).
     *
     * @return string[]
     */
    public static function eu_vat_countries() {
        return array(
            'AT', 'BE', 'BG', 'CY', 'CZ', 'DE', 'DK', 'EE', 'EL', 'FI', 'FR',
            'HR', 'HU', 'IE', 'IT', 'LT', 'LU', 'LV', 'MT', 'NL', 'PL', 'PT',
            'RO', 'SE', 'SI', 'SK',
        );
    }

    public static function is_eu_vat_country($country) {
        $country = strtoupper((string) $country);
        if ('GR' === $country) {
            $country = 'EL';
        }
        return in_array($country, self::eu_vat_countries(), true);
    }

    public static function billing_fields($fields) {
        $fields = is_array($fields) ? $fields : array();
        $fields['billing_eu_b2b'] = array(
            'type' => 'checkbox',
            'label' => __('Soy empresa / profesional de la UE', 'taxocommerce-facturacion'),
            'required' => false,
            'class' => array('form-row-wide'),
            'priority' => 25,
        );
        $fields['billing_vat'] = array(
            'type' => 'text',
            'label' => __('NIF-IVA (VIES)', 'taxocommerce-facturacion'),
            'required' => false,
            'class' => array('form-row-wide'),
            'priority' => 26,
            'placeholder' => 'ESB12345678 / FR12345678901',
        );
        return $fields;
    }

    public static function validate_checkout() {
        $is_b2b = !empty($_POST['billing_eu_b2b']); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WC checkout nonce.
        $country = strtoupper(sanitize_text_field(wp_unslash($_POST['billing_country'] ?? ''))); // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $vat = sanitize_text_field(wp_unslash($_POST['billing_vat'] ?? '')); // phpcs:ignore WordPress.Security.NonceVerification.Missing

        if (!$is_b2b) {
            self::store_session(array(
                'is_b2b' => 0,
                'vat_number' => '',
                'vat_country' => '',
                'status' => '',
                'valid' => false,
            ));
            return;
        }

        if (!self::is_eu_vat_country($country) || 'ES' === $country) {
            wc_add_notice(__('El régimen B2B intracomunitario aplica a empresas de otro Estado UE (no España).', 'taxocommerce-facturacion'), 'error');
            return;
        }

        if ('' === $vat) {
            wc_add_notice(__('Indica el NIF-IVA para ventas B2B en la UE.', 'taxocommerce-facturacion'), 'error');
            return;
        }

        $vies_country = ('GR' === $country) ? 'EL' : $country;
        $result = SEO_Facturas_Vies::validate($vies_country, $vat);
        self::store_session(array(
            'is_b2b' => 1,
            'vat_number' => self::normalize_vat($vies_country, $vat),
            'vat_country' => $vies_country,
            'status' => $result['status'],
            'valid' => !empty($result['valid']),
            'checked_at' => $result['checked_at'],
            'message' => $result['message'],
        ));

        if ('valid' !== $result['status']) {
            $block_unavailable = (bool) SEO_Facturas_Settings::get('tax_eu_block_if_vies_down', 1);
            if ('unavailable' === $result['status'] && $block_unavailable) {
                wc_add_notice(__('No se pudo validar el NIF-IVA en VIES. Inténtalo de nuevo más tarde.', 'taxocommerce-facturacion'), 'error');
                return;
            }
            if ('invalid' === $result['status']) {
                wc_add_notice(__('El NIF-IVA no es válido en VIES. No se puede aplicar la inversión del sujeto pasivo.', 'taxocommerce-facturacion'), 'error');
            }
        }
    }

    public static function persist_order_meta($order, $data = array()) {
        if (!$order || !is_a($order, 'WC_Order')) {
            return;
        }
        $session = self::session_data();
        $is_b2b = !empty($session['is_b2b']);
        $vat = (string) ($session['vat_number'] ?? '');
        $country = (string) ($session['vat_country'] ?? '');
        $status = (string) ($session['status'] ?? '');
        $valid = !empty($session['valid']);

        $order->update_meta_data(self::META_IS_B2B, $is_b2b ? '1' : '0');
        if ($vat) {
            $order->update_meta_data(self::META_VAT, $vat);
            $order->update_meta_data(self::META_VAT_COUNTRY, $country);
            $order->update_meta_data(self::META_VAT_STATUS, $status);
            $order->update_meta_data(self::META_VAT_VALIDATED, $valid ? 'valid' : $status);
            $order->update_meta_data(self::META_VAT_CHECKED_AT, (string) ($session['checked_at'] ?? gmdate('c')));
        }
        if ($valid && self::is_eu_vat_country($country)) {
            $order->update_meta_data(self::META_REGIME, 'intra_eu_reverse_charge');
        }
    }

    public static function persist_order_meta_legacy($order_id) {
        $order = wc_get_order($order_id);
        if ($order) {
            self::persist_order_meta($order);
            $order->save();
        }
    }

    public static function refresh_session_from_customer() {
        if (!function_exists('WC') || !WC()->customer) {
            return;
        }
        // Recalcula contexto fiscal cuando el cliente cambia de país en checkout.
        $country = strtoupper((string) WC()->customer->get_billing_country());
        $session = self::session_data();
        if (!empty($session['is_b2b']) && $country && $country !== (string) ($session['vat_country'] ?? '')) {
            // Fuerza revalidación en el siguiente process; no asume validez cruzada.
            $session['valid'] = false;
            $session['status'] = 'review';
            self::store_session($session);
        }
    }

    /**
     * ¿Aplica ISP (IVA 0 %) para el destino indicado?
     */
    public static function is_reverse_charge_for_location($country, $state = '', $postcode = '') {
        if (!self::enabled()) {
            return false;
        }
        $country = strtoupper((string) $country);
        if ('ES' === $country) {
            // Territorios especiales ES: nunca ISP UE.
            if (class_exists('SEO_Facturas_Tax')) {
                $zone = SEO_Facturas_Tax::classify_location($country, $state, $postcode);
                if (in_array($zone, array('canarias', 'ceuta', 'melilla'), true)) {
                    return false;
                }
            }
            return false;
        }
        if (!self::is_eu_vat_country($country)) {
            return false;
        }
        $session = self::session_data();
        return !empty($session['is_b2b']) && !empty($session['valid']);
    }

    public static function order_is_reverse_charge($order) {
        if (!$order || !is_a($order, 'WC_Order')) {
            return false;
        }
        return 'intra_eu_reverse_charge' === (string) $order->get_meta(self::META_REGIME)
            || 'valid' === (string) $order->get_meta(self::META_VAT_VALIDATED);
    }

    public static function legal_note() {
        $custom = trim((string) SEO_Facturas_Settings::get('tax_eu_reverse_charge_note', ''));
        if ('' !== $custom) {
            return $custom;
        }
        return 'Operación intracomunitaria. Inversión del sujeto pasivo (art. 25 Ley 37/1992 del IVA y normativa UE aplicable). IVA 0 %.';
    }

    public static function normalize_vat($country, $vat) {
        $country = strtoupper(preg_replace('/[^A-Z]/', '', (string) $country));
        $vat = strtoupper(preg_replace('/[^A-Z0-9]/', '', (string) $vat));
        if (2 === strlen($country) && 0 === strpos($vat, $country)) {
            return $vat;
        }
        return $country . $vat;
    }

    private static function store_session(array $data) {
        if (function_exists('WC') && WC()->session) {
            WC()->session->set(self::SESSION_KEY, $data);
        }
    }

    private static function session_data() {
        if (function_exists('WC') && WC()->session) {
            $data = WC()->session->get(self::SESSION_KEY);
            return is_array($data) ? $data : array();
        }
        return array();
    }
}
