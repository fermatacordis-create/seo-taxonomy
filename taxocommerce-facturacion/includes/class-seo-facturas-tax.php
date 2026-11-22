<?php
/**
 * Gestion fiscal centralizada para Espana.
 *
 * Las reglas se configuran desde Facturas y presupuestos > Fiscalidad, pero
 * los importes se siguen calculando dentro de WooCommerce. Los documentos
 * copian despues los totales del carrito/pedido, evitando recalculos distintos.
 */

defined('ABSPATH') || exit;

final class SEO_Facturas_Tax {

    const IDS_OPTION  = 'seo_facturas_tax_rate_ids';
    const HASH_OPTION = 'seo_facturas_tax_rate_hash';
    const TAX_CLASS   = 'seo-facturas-managed';
    const INCLUSIVE_TAX_REPAIR_OPTION = 'seo_facturas_inclusive_tax_repair_20261116';

    private static $initialized = false;
    private static $rate_exists_cache = array();

    public static function init() {
        if (self::$initialized) {
            return;
        }
        self::$initialized = true;

        /*
         * El PVP almacenado ya contiene IVA. En instalaciones anteriores el
         * gestor de tasas podia quedar apagado y generar documentos con IVA 0.
         * Reparacion acotada y una sola vez: nunca recalcula pedidos emitidos.
         */
        self::maybe_repair_inclusive_tax_setup();
        self::sync_price_tax_mode();

        add_filter('woocommerce_find_rates', array(__CLASS__, 'filter_rates'), 99, 2);
        add_filter('woocommerce_countries_allowed_countries', array(__CLASS__, 'allowed_countries'), 99, 1);
        add_filter('woocommerce_countries_shipping_countries', array(__CLASS__, 'allowed_countries'), 99, 1);

        if (self::enabled()) {
            // La existencia de tasas no basta si WooCommerce no calcula IVA.
            if ('yes' !== (string) get_option('woocommerce_calc_taxes', 'no')) {
                update_option('woocommerce_calc_taxes', 'yes');
            }
            self::ensure_managed_rates();
        }
    }

    public static function enabled() {
        return (bool) SEO_Facturas_Settings::get('tax_manager_enabled', 0);
    }

    /**
     * Corrige una instalacion con PVP IVA incluido y tasas no operativas.
     * No sustituye una configuracion estandar 21 % ya funcional.
     *
     * Preserva paises de venta existentes: no activa la restriccion a ES.
     * No modifica precios, pedidos, facturas ni snapshots historicos.
     */
    private static function maybe_repair_inclusive_tax_setup() {
        if (get_option(self::INCLUSIVE_TAX_REPAIR_OPTION, false)) {
            return;
        }
        if (!class_exists('WC_Tax')) {
            return;
        }

        $settings = SEO_Facturas_Settings::taxation();
        if (!empty($settings['tax_prices_include_tax']) && empty($settings['tax_manager_enabled'])) {
            $correct_rate = false;
            $configured_rate = (float) ($settings['tax_peninsula_rate'] ?? 21);
            if ('yes' === (string) get_option('woocommerce_calc_taxes', 'no')) {
                $rates = WC_Tax::find_rates(array(
                    'country' => 'ES', 'state' => 'M', 'postcode' => '28001',
                    'city' => 'Madrid', 'tax_class' => '',
                ));
                foreach ((array) $rates as $rate) {
                    if (
                        abs((float) ($rate['rate'] ?? 0) - $configured_rate) < 0.0001
                        && (empty($settings['tax_shipping']) || 'yes' === (string) ($rate['shipping'] ?? 'no'))
                    ) {
                        $correct_rate = true;
                        break;
                    }
                }
            }
            if (!$correct_rate) {
                $settings['tax_manager_enabled'] = 1;
                // No limitar las ventas internacionales por corregir el IVA español.
                $settings['tax_restrict_to_spain'] = 0;
                update_option(SEO_Facturas_Settings::TAX_OPTION, $settings, false);
            }
        }
        update_option(self::INCLUSIVE_TAX_REPAIR_OPTION, '1', false);
    }

    /**
     * Sincroniza como WooCommerce interpreta y muestra los precios del catalogo.
     *
     * Esta regla es independiente de tax_manager_enabled. El gestor fiscal
     * puede estar apagado y WooCommerce aun debe saber que el PVP almacenado
     * ya incluye IVA; de lo contrario vuelve a sumarlo en carrito/checkout.
     */
    public static function sync_price_tax_mode($settings = null) {
        $settings = is_array($settings) ? $settings : SEO_Facturas_Settings::taxation();
        $prices_include_tax = empty($settings['tax_prices_include_tax']) ? 'no' : 'yes';
        $display_mode = 'yes' === $prices_include_tax ? 'incl' : 'excl';
        $changed = false;

        if ((string) get_option('woocommerce_prices_include_tax', 'no') !== $prices_include_tax) {
            update_option('woocommerce_prices_include_tax', $prices_include_tax);
            $changed = true;
        }

        if ((string) get_option('woocommerce_tax_display_shop', 'excl') !== $display_mode) {
            update_option('woocommerce_tax_display_shop', $display_mode);
            $changed = true;
        }

        if ((string) get_option('woocommerce_tax_display_cart', 'excl') !== $display_mode) {
            update_option('woocommerce_tax_display_cart', $display_mode);
            $changed = true;
        }

        if ($changed) {
            self::flush_tax_cache();
        }

        return $changed;
    }

    /**
     * Guarda/actualiza las tasas tecnicas que WooCommerce necesita para poder
     * asociar un rate_id real a los impuestos del pedido. Usamos una clase
     * fiscal interna para que estas filas no interfieran cuando el gestor esta
     * desactivado.
     */
    public static function sync_managed_rates($settings = null) {
        if (!class_exists('WooCommerce') || !class_exists('WC_Tax')) {
            return new WP_Error('seo_facturas_tax_woocommerce_missing', 'WooCommerce no esta disponible para sincronizar las reglas fiscales.');
        }

        $settings = is_array($settings) ? $settings : SEO_Facturas_Settings::taxation();

        /*
         * El modo de precios debe sincronizarse incluso si el gestor de tasas
         * esta apagado. Son dos responsabilidades distintas.
         */
        self::sync_price_tax_mode($settings);

        if (empty($settings['tax_manager_enabled'])) {
            return true;
        }

        update_option('woocommerce_calc_taxes', 'yes');

        if (class_exists('SEO_Facturas_Special_Rates')) {
            SEO_Facturas_Special_Rates::ensure_wc_tax_classes();
        }

        $zones = self::zone_definitions($settings);
        $ids = get_option(self::IDS_OPTION, array());
        $ids = is_array($ids) ? $ids : array();

        foreach ($zones as $zone => $definition) {
            $current_id = absint($ids[$zone] ?? 0);
            $data = array(
                'tax_rate_country'  => ('intra_eu' === $zone) ? '' : 'ES',
                'tax_rate_state'    => '',
                'tax_rate'          => self::format_rate($definition['rate']),
                'tax_rate_name'     => (string) $definition['label'],
                'tax_rate_priority' => 1,
                'tax_rate_compound' => 0,
                'tax_rate_shipping' => empty($settings['tax_shipping']) ? 0 : 1,
                'tax_rate_order'    => 0,
                'tax_rate_class'    => self::TAX_CLASS,
            );

            if ($current_id && self::rate_exists($current_id)) {
                self::update_rate($current_id, $data);
                $ids[$zone] = $current_id;
            } else {
                $new_id = self::insert_rate($data);
                if (!$new_id) {
                    return new WP_Error('seo_facturas_tax_rate_create_failed', 'No se ha podido crear la tasa fiscal interna de ' . $definition['name'] . '.');
                }
                $ids[$zone] = $new_id;
            }
        }

        // Catálogo IGIC / IPSI: una tasa WooCommerce por tipo + clase fiscal.
        if (!empty($settings['tax_special_rates_enabled']) && class_exists('SEO_Facturas_Special_Rates')) {
            foreach (SEO_Facturas_Special_Rates::igic_types() as $type_key => $type) {
                $storage = SEO_Facturas_Special_Rates::storage_key('canarias', $type_key);
                $rate = SEO_Facturas_Special_Rates::rate_for($settings, 'canarias', $type_key);
                $data = array(
                    'tax_rate_country'  => 'ES',
                    'tax_rate_state'    => '',
                    'tax_rate'          => self::format_rate($rate),
                    'tax_rate_name'     => SEO_Facturas_Special_Rates::label_for('canarias', $type_key, $rate),
                    'tax_rate_priority' => 1,
                    'tax_rate_compound' => 0,
                    'tax_rate_shipping' => empty($settings['tax_shipping']) ? 0 : 1,
                    'tax_rate_order'    => 0,
                    'tax_rate_class'    => sanitize_title($type['wc_class']),
                );
                $current_id = absint($ids[$storage] ?? 0);
                if ($current_id && self::rate_exists($current_id)) {
                    self::update_rate($current_id, $data);
                    $ids[$storage] = $current_id;
                } else {
                    $new_id = self::insert_rate($data);
                    if (!$new_id) {
                        return new WP_Error('seo_facturas_tax_rate_create_failed', 'No se ha podido crear la tasa IGIC ' . $type_key . '.');
                    }
                    $ids[$storage] = $new_id;
                }
            }
            foreach (array('ceuta', 'melilla') as $zone) {
                foreach (SEO_Facturas_Special_Rates::ipsi_types() as $type_key => $type) {
                    $storage = SEO_Facturas_Special_Rates::storage_key($zone, $type_key);
                    $rate = SEO_Facturas_Special_Rates::rate_for($settings, $zone, $type_key);
                    $data = array(
                        'tax_rate_country'  => 'ES',
                        'tax_rate_state'    => '',
                        'tax_rate'          => self::format_rate($rate),
                        'tax_rate_name'     => SEO_Facturas_Special_Rates::label_for($zone, $type_key, $rate),
                        'tax_rate_priority' => 1,
                        'tax_rate_compound' => 0,
                        'tax_rate_shipping' => empty($settings['tax_shipping']) ? 0 : 1,
                        'tax_rate_order'    => 0,
                        'tax_rate_class'    => sanitize_title($type['wc_class']),
                    );
                    $current_id = absint($ids[$storage] ?? 0);
                    if ($current_id && self::rate_exists($current_id)) {
                        self::update_rate($current_id, $data);
                        $ids[$storage] = $current_id;
                    } else {
                        $new_id = self::insert_rate($data);
                        if (!$new_id) {
                            return new WP_Error('seo_facturas_tax_rate_create_failed', 'No se ha podido crear la tasa IPSI ' . $zone . '/' . $type_key . '.');
                        }
                        $ids[$storage] = $new_id;
                    }
                }
            }
        }

        update_option(self::IDS_OPTION, $ids, false);
        update_option(self::HASH_OPTION, self::settings_hash($settings), false);
        self::$rate_exists_cache = array();
        self::flush_tax_cache();

        return true;
    }

    private static function ensure_managed_rates() {
        $settings = SEO_Facturas_Settings::taxation();
        $ids = get_option(self::IDS_OPTION, array());
        $ids = is_array($ids) ? $ids : array();
        $expected_hash = self::settings_hash($settings);
        $saved_hash = (string) get_option(self::HASH_OPTION, '');

        $complete = true;
        foreach (array_keys(self::zone_definitions($settings)) as $zone) {
            $id = absint($ids[$zone] ?? 0);
            if (!$id || !self::rate_exists($id)) {
                $complete = false;
                break;
            }
        }
        if ($complete && !empty($settings['tax_special_rates_enabled']) && class_exists('SEO_Facturas_Special_Rates')) {
            foreach (array_keys(SEO_Facturas_Special_Rates::igic_types()) as $type_key) {
                $storage = SEO_Facturas_Special_Rates::storage_key('canarias', $type_key);
                $id = absint($ids[$storage] ?? 0);
                if (!$id || !self::rate_exists($id)) {
                    $complete = false;
                    break;
                }
            }
            if ($complete) {
                foreach (array('ceuta', 'melilla') as $zone) {
                    foreach (array_keys(SEO_Facturas_Special_Rates::ipsi_types()) as $type_key) {
                        $storage = SEO_Facturas_Special_Rates::storage_key($zone, $type_key);
                        $id = absint($ids[$storage] ?? 0);
                        if (!$id || !self::rate_exists($id)) {
                            $complete = false;
                            break 2;
                        }
                    }
                }
            }
        }

        if ($complete && hash_equals($expected_hash, $saved_hash)) {
            return;
        }

        self::sync_managed_rates($settings);
    }

    public static function filter_rates($rates, $args) {
        if (!self::enabled()) {
            return $rates;
        }

        $args = is_array($args) ? $args : array();
        $tax_class = sanitize_title((string) ($args['tax_class'] ?? ''));
        $country = strtoupper((string) ($args['country'] ?? ''));
        $state = strtoupper((string) ($args['state'] ?? ''));
        $postcode = strtoupper((string) ($args['postcode'] ?? ''));

        if ('' === $country && SEO_Facturas_Settings::get('tax_restrict_to_spain', 1)) {
            $country = 'ES';
        }

        // B2B UE con VIES OK: inversión del sujeto pasivo (nunca Canarias/Ceuta/Melilla).
        if (
            class_exists('SEO_Facturas_Eu_Vat')
            && SEO_Facturas_Eu_Vat::is_reverse_charge_for_location($country, $state, $postcode)
        ) {
            return self::managed_rate_response('intra_eu', SEO_Facturas_Settings::taxation());
        }

        if ('ES' !== $country) {
            return empty(SEO_Facturas_Settings::get('tax_restrict_to_spain', 1)) ? $rates : array();
        }

        $zone = self::classify_location($country, $state, $postcode);
        $settings = SEO_Facturas_Settings::taxation();
        $definitions = self::zone_definitions($settings);
        if (!isset($definitions[$zone])) {
            $zone = 'peninsula';
        }

        // IGIC / IPSI por clase fiscal de producto (catálogo completo).
        if (
            !empty($settings['tax_special_rates_enabled'])
            && class_exists('SEO_Facturas_Special_Rates')
            && in_array($zone, array('canarias', 'ceuta', 'melilla'), true)
        ) {
            $type_key = SEO_Facturas_Special_Rates::resolve_type_key($zone, $tax_class);
            return self::special_rate_response($zone, $type_key, $settings);
        }

        // Península / Baleares: respetar clases reducidas/exentas ajenas.
        if ('' !== $tax_class && 'standard' !== $tax_class && self::TAX_CLASS !== $tax_class) {
            return $rates;
        }

        return self::managed_rate_response($zone, $settings);
    }

    private static function special_rate_response($zone, $type_key, $settings) {
        $storage = SEO_Facturas_Special_Rates::storage_key($zone, $type_key);
        $ids = get_option(self::IDS_OPTION, array());
        $rate_id = absint(is_array($ids) ? ($ids[$storage] ?? 0) : 0);
        $rate = SEO_Facturas_Special_Rates::rate_for($settings, $zone, $type_key);
        $label = SEO_Facturas_Special_Rates::label_for($zone, $type_key, $rate);
        if (!$rate_id || !self::rate_exists($rate_id)) {
            // Fallback: zona base (general) si aún no se sincronizó el catálogo.
            return self::managed_rate_response($zone, $settings);
        }
        return array(
            $rate_id => array(
                'rate'     => self::format_rate($rate),
                'label'    => $label,
                'shipping' => empty($settings['tax_shipping']) ? 'no' : 'yes',
                'compound' => 'no',
            ),
        );
    }

    private static function managed_rate_response($zone, $settings) {
        $definitions = self::zone_definitions($settings);
        if (!isset($definitions[$zone])) {
            return array();
        }
        $ids = get_option(self::IDS_OPTION, array());
        $rate_id = absint(is_array($ids) ? ($ids[$zone] ?? 0) : 0);
        if (!$rate_id || !self::rate_exists($rate_id)) {
            return array();
        }
        $definition = $definitions[$zone];
        return array(
            $rate_id => array(
                'rate'     => self::format_rate($definition['rate']),
                'label'    => (string) $definition['label'],
                'shipping' => empty($settings['tax_shipping']) ? 'no' : 'yes',
                'compound' => 'no',
            ),
        );
    }

    public static function allowed_countries($countries) {
        if (!self::enabled() || !SEO_Facturas_Settings::get('tax_restrict_to_spain', 1)) {
            return $countries;
        }

        $label = 'España';
        if (is_array($countries) && isset($countries['ES'])) {
            $label = $countries['ES'];
        } elseif (function_exists('WC') && WC() && isset(WC()->countries)) {
            $all = WC()->countries->get_countries();
            if (is_array($all) && isset($all['ES'])) {
                $label = $all['ES'];
            }
        }

        return array('ES' => $label);
    }

    public static function classify_location($country, $state = '', $postcode = '') {
        $country = strtoupper(trim((string) $country));
        $state = strtoupper(trim((string) $state));
        $postcode = preg_replace('/[^0-9]/', '', (string) $postcode);
        $prefix = strlen($postcode) >= 2 ? substr($postcode, 0, 2) : '';

        if ('ES' !== $country) {
            return 'other';
        }
        if ('PM' === $state || '07' === $prefix) {
            return 'baleares';
        }
        if (in_array($state, array('GC', 'TF'), true) || in_array($prefix, array('35', '38'), true)) {
            return 'canarias';
        }
        if ('CE' === $state || '51' === $prefix) {
            return 'ceuta';
        }
        if ('ML' === $state || '52' === $prefix) {
            return 'melilla';
        }
        return 'peninsula';
    }

    public static function context_for_location($country, $state = '', $postcode = '') {
        $settings = SEO_Facturas_Settings::taxation();
        $zone = self::classify_location($country, $state, $postcode);
        $definitions = self::zone_definitions($settings);
        $definition = $definitions[$zone] ?? null;

        if (
            !$definition
            && class_exists('SEO_Facturas_Eu_Vat')
            && SEO_Facturas_Eu_Vat::is_reverse_charge_for_location($country, $state, $postcode)
        ) {
            $definition = $definitions['intra_eu'];
            $zone = 'intra_eu';
        }

        if (!$definition) {
            return array(
                'enabled'    => self::enabled(),
                'zone'       => 'other',
                'zone_label' => 'Fuera de España',
                'tax_name'   => 'IVA',
                'rate'       => null,
                'label'      => 'Impuestos',
                'regime'     => 'other',
                'note'       => '',
            );
        }

        $note = '';
        if (in_array($zone, array('canarias', 'ceuta', 'melilla'), true)) {
            $note = (string) ($settings['tax_special_note'] ?? '');
        }
        $regime = 'domestic';
        if (
            class_exists('SEO_Facturas_Eu_Vat')
            && SEO_Facturas_Eu_Vat::is_reverse_charge_for_location($country, $state, $postcode)
        ) {
            $zone = 'intra_eu';
            $definition = $definitions['intra_eu'];
            $note = SEO_Facturas_Eu_Vat::legal_note();
            $regime = 'intra_eu_reverse_charge';
        }

        return array(
            'enabled'    => self::enabled(),
            'zone'       => $zone,
            'zone_label' => (string) $definition['name'],
            'tax_name'   => (string) ($definition['tax'] ?? 'IVA'),
            'rate'       => (float) $definition['rate'],
            'label'      => (string) $definition['label'],
            'regime'     => $regime,
            'note'       => $note,
        );
    }

    public static function current_context() {
        $country = 'ES';
        $state = '';
        $postcode = '';

        if (function_exists('WC') && WC() && WC()->customer) {
            $customer = WC()->customer;
            $country = (string) $customer->get_shipping_country();
            $state = (string) $customer->get_shipping_state();
            $postcode = (string) $customer->get_shipping_postcode();

            if ('' === $country) {
                $country = (string) $customer->get_billing_country();
                $state = (string) $customer->get_billing_state();
                $postcode = (string) $customer->get_billing_postcode();
            }
        }

        if ('' === $country) {
            $country = 'ES';
        }

        return self::context_for_location($country, $state, $postcode);
    }

    public static function zone_definitions($settings = null) {
        $settings = is_array($settings) ? $settings : SEO_Facturas_Settings::taxation();

        return array(
            'peninsula' => array(
                'name'  => 'Península',
                'tax'   => 'IVA',
                'rate'  => (float) ($settings['tax_peninsula_rate'] ?? 21),
                'label' => self::rate_label('IVA', (float) ($settings['tax_peninsula_rate'] ?? 21)),
            ),
            'baleares' => array(
                'name'  => 'Islas Baleares',
                'tax'   => 'IVA',
                'rate'  => (float) ($settings['tax_baleares_rate'] ?? 21),
                'label' => self::rate_label('IVA', (float) ($settings['tax_baleares_rate'] ?? 21)),
            ),
            'canarias' => array(
                'name'  => 'Canarias',
                'tax'   => 'IGIC',
                'rate'  => (float) ($settings['tax_igic_general'] ?? $settings['tax_canarias_rate'] ?? 7),
                'label' => self::rate_label('IGIC', (float) ($settings['tax_igic_general'] ?? $settings['tax_canarias_rate'] ?? 7)),
            ),
            'ceuta' => array(
                'name'  => 'Ceuta',
                'tax'   => 'IPSI',
                'rate'  => (float) ($settings['tax_ipsi_ceuta_general'] ?? $settings['tax_ceuta_rate'] ?? 10),
                'label' => self::rate_label('IPSI', (float) ($settings['tax_ipsi_ceuta_general'] ?? $settings['tax_ceuta_rate'] ?? 10)),
            ),
            'melilla' => array(
                'name'  => 'Melilla',
                'tax'   => 'IPSI',
                'rate'  => (float) ($settings['tax_ipsi_melilla_general'] ?? $settings['tax_melilla_rate'] ?? 10),
                'label' => self::rate_label('IPSI', (float) ($settings['tax_ipsi_melilla_general'] ?? $settings['tax_melilla_rate'] ?? 10)),
            ),
            'intra_eu' => array(
                'name'  => 'UE B2B (ISP)',
                'tax'   => 'IVA',
                'rate'  => 0.0,
                'label' => 'IVA 0% (ISP)',
            ),
        );
    }

    private static function rate_label($tax_name, $rate) {
        $rate = (float) $rate;
        $formatted = rtrim(rtrim(number_format($rate, 3, '.', ''), '0'), '.');
        return trim((string) $tax_name) . ' ' . $formatted . '%';
    }

    private static function format_rate($rate) {
        return number_format(max(0, min(100, (float) $rate)), 4, '.', '');
    }

    private static function settings_hash($settings) {
        $keys = array(
            'tax_manager_enabled', 'tax_prices_include_tax', 'tax_restrict_to_spain', 'tax_shipping',
            'tax_peninsula_rate', 'tax_baleares_rate', 'tax_canarias_rate',
            'tax_ceuta_rate', 'tax_melilla_rate',
            'tax_eu_b2b_enabled', 'tax_special_rates_enabled',
            'tax_igic_cero', 'tax_igic_reducido', 'tax_igic_general', 'tax_igic_incrementado',
            'tax_igic_especial_15', 'tax_igic_especial_20',
            'tax_ipsi_ceuta_cero', 'tax_ipsi_ceuta_r05', 'tax_ipsi_ceuta_r1', 'tax_ipsi_ceuta_r2',
            'tax_ipsi_ceuta_r4', 'tax_ipsi_ceuta_r8', 'tax_ipsi_ceuta_general',
            'tax_ipsi_melilla_cero', 'tax_ipsi_melilla_r05', 'tax_ipsi_melilla_r1', 'tax_ipsi_melilla_r2',
            'tax_ipsi_melilla_r4', 'tax_ipsi_melilla_r8', 'tax_ipsi_melilla_general',
        );
        $data = array();
        foreach ($keys as $key) {
            $data[$key] = $settings[$key] ?? null;
        }
        return md5(wp_json_encode($data));
    }

    private static function rate_exists($rate_id) {
        global $wpdb;
        $rate_id = absint($rate_id);
        if (!$rate_id || empty($wpdb->prefix)) {
            return false;
        }
        if (array_key_exists($rate_id, self::$rate_exists_cache)) {
            return self::$rate_exists_cache[$rate_id];
        }
        $table = $wpdb->prefix . 'woocommerce_tax_rates';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- WC no ofrece consulta equivalente por ID; el resultado se memoiza en rate_exists_cache durante la solicitud.
        $found = $wpdb->get_var($wpdb->prepare('SELECT tax_rate_id FROM %i WHERE tax_rate_id = %d LIMIT 1', $table, $rate_id));
        self::$rate_exists_cache[$rate_id] = (bool) $found;
        return self::$rate_exists_cache[$rate_id];
    }

    private static function insert_rate(array $data) {
        if (method_exists('WC_Tax', '_insert_tax_rate')) {
            return absint(WC_Tax::_insert_tax_rate($data));
        }

        global $wpdb;
        $table = $wpdb->prefix . 'woocommerce_tax_rates';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Fallback para WooCommerce sin _insert_tax_rate; tabla gestionada por WC, escritura inmediata.
        $inserted = $wpdb->insert($table, $data, array('%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%s'));
        return $inserted ? absint($wpdb->insert_id) : 0;
    }

    private static function update_rate($rate_id, array $data) {
        if (method_exists('WC_Tax', '_update_tax_rate')) {
            WC_Tax::_update_tax_rate(absint($rate_id), $data);
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'woocommerce_tax_rates';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Fallback para WooCommerce sin _update_tax_rate; escritura inmediata e invalidacion fiscal posterior.
        $wpdb->update(
            $table,
            $data,
            array('tax_rate_id' => absint($rate_id)),
            array('%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%s'),
            array('%d')
        );
    }

    /**
     * Impide emitir PDFs con base imponible igual al PVP cuando falta el IVA
     * de un producto ordinario, sujeto al tipo positivo de la zona.
     * Nunca inventa impuestos en el PDF; obliga a corregir WooCommerce.
     */
    public static function validate_cart_tax($cart, array $buyer = array()) {
        if (!$cart || !method_exists($cart, 'get_cart')) {
            return true;
        }
        $country = strtoupper((string) ($buyer['country'] ?? ''));
        $state = (string) ($buyer['state'] ?? '');
        $postcode = (string) ($buyer['postcode'] ?? '');
        if ('' === $country && function_exists('WC') && WC() && WC()->customer) {
            $customer = WC()->customer;
            $country = strtoupper((string) $customer->get_shipping_country());
            $state = (string) $customer->get_shipping_state();
            $postcode = (string) $customer->get_shipping_postcode();
        }
        if (!self::requires_standard_iva($country, $state, $postcode)) {
            return true;
        }
        if (
            class_exists('SEO_Facturas_Eu_Vat')
            && SEO_Facturas_Eu_Vat::is_reverse_charge_for_location($country, $state, $postcode)
        ) {
            return true;
        }
        if (function_exists('WC') && WC() && WC()->customer
            && is_callable(array(WC()->customer, 'get_is_vat_exempt'))
            && WC()->customer->get_is_vat_exempt()) {
            return true;
        }
        foreach ((array) $cart->get_cart() as $line) {
            $product = $line['data'] ?? null;
            if (!$product || !is_callable(array($product, 'get_tax_class'))) {
                continue;
            }
            $class = (string) $product->get_tax_class();
            if ('' !== $class && 'standard' !== $class) {
                continue;
            }
            if (!is_callable(array($product, 'get_tax_status')) || 'taxable' !== (string) $product->get_tax_status()) {
                continue;
            }
            if ((float) ($line['line_total'] ?? 0) > 0 && (float) ($line['line_tax'] ?? 0) < 0.001) {
                return new WP_Error('seo_facturas_cart_tax_missing',
                    'No se puede emitir el documento: WooCommerce no ha desglosado el IVA de un producto sujeto al tipo general. Comprueba la fiscalidad y el estado fiscal del producto.');
            }
        }
        return true;
    }

    public static function validate_order_tax($order) {
        if (!$order || !is_callable(array($order, 'get_items'))) {
            return true;
        }
        $country = strtoupper((string) $order->get_shipping_country());
        $state = (string) $order->get_shipping_state();
        $postcode = (string) $order->get_shipping_postcode();
        if ('' === $country) {
            $country = strtoupper((string) $order->get_billing_country());
            $state = (string) $order->get_billing_state();
            $postcode = (string) $order->get_billing_postcode();
        }
        if (!self::requires_standard_iva($country, $state, $postcode)) {
            return true;
        }
        if (class_exists('SEO_Facturas_Eu_Vat') && SEO_Facturas_Eu_Vat::order_is_reverse_charge($order)) {
            return true;
        }
        foreach ((array) $order->get_items('line_item') as $item) {
            $product = is_callable(array($item, 'get_product')) ? $item->get_product() : null;
            if (!$product || !is_callable(array($product, 'get_tax_class'))) {
                continue;
            }
            $class = (string) $product->get_tax_class();
            if ('' !== $class && 'standard' !== $class) {
                continue;
            }
            if (!is_callable(array($product, 'get_tax_status')) || 'taxable' !== (string) $product->get_tax_status()) {
                continue;
            }
            if ((float) $item->get_total() > 0 && (float) $item->get_total_tax() < 0.001) {
                return new WP_Error('seo_facturas_order_tax_missing',
                    'No se emite la factura/proforma: el pedido WooCommerce tiene un producto sin el IVA esperado. Revisa y recalcula los impuestos del pedido antes de emitir documentos.');
            }
        }
        return true;
    }

    private static function requires_standard_iva($country, $state, $postcode) {
        if (!SEO_Facturas_Settings::get('tax_prices_include_tax', 1)
            || 'yes' !== (string) get_option('woocommerce_calc_taxes', 'no')) {
            return false;
        }
        $country = strtoupper((string) $country);
        if ('' === $country && SEO_Facturas_Settings::get('tax_restrict_to_spain', 0)) {
            $country = 'ES';
        }
        if ('ES' !== $country) {
            return false;
        }
        $zone = self::classify_location($country, $state, $postcode);
        $zones = self::zone_definitions();
        return (float) ($zones[$zone]['rate'] ?? 0) > 0.0;
    }

    private static function flush_tax_cache() {
        if (class_exists('WC_Cache_Helper') && method_exists('WC_Cache_Helper', 'invalidate_cache_group')) {
            WC_Cache_Helper::invalidate_cache_group('taxes');
        }
        if (function_exists('wc_delete_product_transients')) {
            wc_delete_product_transients();
        }
    }
}
