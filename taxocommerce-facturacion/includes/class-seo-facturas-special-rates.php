<?php
/**
 * Catálogos de tipos IGIC (Canarias) e IPSI (Ceuta / Melilla).
 *
 * Los porcentajes por defecto son orientativos y editables. No sustituyen
 * el criterio del asesor ni la normativa vigente de cada territorio.
 */

defined('ABSPATH') || exit;

final class SEO_Facturas_Special_Rates {

    /**
     * IGIC — tipos habituales en Canarias.
     *
     * @return array<string,array{label:string,rate:float,wc_class:string}>
     */
    public static function igic_types() {
        return array(
            'cero'         => array('label' => 'IGIC tipo cero', 'rate' => 0.0, 'wc_class' => 'igic-cero'),
            'reducido'     => array('label' => 'IGIC reducido', 'rate' => 3.0, 'wc_class' => 'igic-reducido'),
            'general'      => array('label' => 'IGIC general', 'rate' => 7.0, 'wc_class' => 'igic-general'),
            'incrementado' => array('label' => 'IGIC incrementado', 'rate' => 9.5, 'wc_class' => 'igic-incrementado'),
            'especial_15'  => array('label' => 'IGIC especial 15%', 'rate' => 15.0, 'wc_class' => 'igic-especial-15'),
            'especial_20'  => array('label' => 'IGIC especial 20%', 'rate' => 20.0, 'wc_class' => 'igic-especial-20'),
        );
    }

    /**
     * IPSI — tipos habituales (Ceuta y Melilla; tasas por defecto editables).
     *
     * @return array<string,array{label:string,rate_ceuta:float,rate_melilla:float,wc_class:string}>
     */
    public static function ipsi_types() {
        return array(
            'cero'       => array('label' => 'IPSI cero', 'rate_ceuta' => 0.0, 'rate_melilla' => 0.0, 'wc_class' => 'ipsi-cero'),
            'r05'        => array('label' => 'IPSI 0,5%', 'rate_ceuta' => 0.5, 'rate_melilla' => 0.5, 'wc_class' => 'ipsi-05'),
            'r1'         => array('label' => 'IPSI 1%', 'rate_ceuta' => 1.0, 'rate_melilla' => 1.0, 'wc_class' => 'ipsi-1'),
            'r2'         => array('label' => 'IPSI 2%', 'rate_ceuta' => 2.0, 'rate_melilla' => 2.0, 'wc_class' => 'ipsi-2'),
            'r4'         => array('label' => 'IPSI 4%', 'rate_ceuta' => 4.0, 'rate_melilla' => 4.0, 'wc_class' => 'ipsi-4'),
            'r8'         => array('label' => 'IPSI 8%', 'rate_ceuta' => 8.0, 'rate_melilla' => 8.0, 'wc_class' => 'ipsi-8'),
            'general'    => array('label' => 'IPSI general 10%', 'rate_ceuta' => 10.0, 'rate_melilla' => 10.0, 'wc_class' => 'ipsi-general'),
        );
    }

    public static function ensure_wc_tax_classes() {
        if (!function_exists('WC') || !WC()->countries) {
            return;
        }
        $existing = array_filter(array_map('trim', explode("\n", (string) get_option('woocommerce_tax_classes', ''))));
        $needed = array();
        foreach (self::igic_types() as $type) {
            $needed[] = $type['wc_class'];
        }
        foreach (self::ipsi_types() as $type) {
            $needed[] = $type['wc_class'];
        }
        $changed = false;
        foreach ($needed as $class) {
            if (!in_array($class, $existing, true)) {
                $existing[] = $class;
                $changed = true;
            }
        }
        if ($changed) {
            update_option('woocommerce_tax_classes', implode("\n", $existing));
        }
    }

    /**
     * Resuelve la clave de tipo IGIC/IPSI a partir de la tax_class de WooCommerce.
     */
    public static function resolve_type_key($zone, $tax_class) {
        $tax_class = sanitize_title((string) $tax_class);
        if (in_array($zone, array('canarias'), true)) {
            foreach (self::igic_types() as $key => $type) {
                if ($tax_class === sanitize_title($type['wc_class'])) {
                    return $key;
                }
            }
            // Clase vacía / standard / gestionada → general.
            return 'general';
        }
        if (in_array($zone, array('ceuta', 'melilla'), true)) {
            foreach (self::ipsi_types() as $key => $type) {
                if ($tax_class === sanitize_title($type['wc_class'])) {
                    return $key;
                }
            }
            return 'general';
        }
        return '';
    }

    public static function rate_for($settings, $zone, $type_key) {
        $settings = is_array($settings) ? $settings : array();
        if ('canarias' === $zone) {
            $types = self::igic_types();
            if (!isset($types[$type_key])) {
                $type_key = 'general';
            }
            $option_key = 'tax_igic_' . $type_key;
            if (isset($settings[$option_key])) {
                return (float) $settings[$option_key];
            }
            return (float) $types[$type_key]['rate'];
        }
        if (in_array($zone, array('ceuta', 'melilla'), true)) {
            $types = self::ipsi_types();
            if (!isset($types[$type_key])) {
                $type_key = 'general';
            }
            $option_key = 'tax_ipsi_' . $zone . '_' . $type_key;
            if (isset($settings[$option_key])) {
                return (float) $settings[$option_key];
            }
            return (float) ('ceuta' === $zone ? $types[$type_key]['rate_ceuta'] : $types[$type_key]['rate_melilla']);
        }
        return 0.0;
    }

    public static function label_for($zone, $type_key, $rate) {
        $rate = (float) $rate;
        $formatted = rtrim(rtrim(number_format($rate, 3, '.', ''), '0'), '.');
        if ('canarias' === $zone) {
            $types = self::igic_types();
            $name = isset($types[$type_key]) ? $types[$type_key]['label'] : 'IGIC';
            return $name . ' (' . $formatted . '%)';
        }
        $types = self::ipsi_types();
        $name = isset($types[$type_key]) ? $types[$type_key]['label'] : 'IPSI';
        $place = ('ceuta' === $zone) ? 'Ceuta' : 'Melilla';
        return $name . ' ' . $place . ' (' . $formatted . '%)';
    }

    public static function storage_key($zone, $type_key) {
        return $zone . '__' . $type_key;
    }

    public static function default_settings_fragment() {
        $out = array('tax_special_rates_enabled' => 1);
        foreach (self::igic_types() as $key => $type) {
            $out['tax_igic_' . $key] = (float) $type['rate'];
        }
        foreach (array('ceuta', 'melilla') as $zone) {
            foreach (self::ipsi_types() as $key => $type) {
                $out['tax_ipsi_' . $zone . '_' . $key] = (float) (
                    'ceuta' === $zone ? $type['rate_ceuta'] : $type['rate_melilla']
                );
            }
        }
        return $out;
    }
}
