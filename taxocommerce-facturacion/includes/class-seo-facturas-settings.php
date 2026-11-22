<?php
/**
 * Configuracion del modulo de facturacion documental.
 *
 * Separa los datos comunes del vendedor de la configuracion propia de
 * facturas, proformas y presupuestos de carrito.
 */

defined('ABSPATH') || exit;

final class SEO_Facturas_Settings {

    const LEGACY_OPTION   = 'seo_facturas_settings';
    const COMPANY_OPTION  = 'seo_facturas_company_settings';
    const TAX_OPTION      = 'seo_facturas_tax_settings';
    const INVOICE_OPTION  = 'seo_facturas_invoice_settings';
    const PROFORMA_OPTION = 'seo_facturas_proforma_settings';
    const QUOTE_OPTION    = 'seo_facturas_quote_settings';
    const MIGRATION_FLAG  = 'seo_facturas_settings_migrated_v2';

    public static function init() {
        self::maybe_migrate_legacy();

        if (is_admin()) {
            add_action('admin_init', array(__CLASS__, 'register'));
        }
    }

    public static function company_defaults() {
        return array(
            'enabled'                => 0,
            'company_name'           => get_bloginfo('name'),
            'company_trade_name'     => '',
            'company_tax_id'         => '',
            'company_address'        => '',
            'company_postcode'       => '',
            'company_city'           => '',
            'company_region'         => '',
            'company_country'        => 'ES',
            'company_phone'          => '',
            'company_email'          => get_option('admin_email'),
            'company_website'        => home_url('/'),
            'logo_id'                => 0,
            'footer_text'            => '',
            'customer_tax_meta_keys' => '_billing_nif,_billing_cif,_billing_vat,billing_nif,billing_cif,billing_vat',
        );
    }

    public static function tax_defaults() {
        return array(
            'tax_manager_enabled' => 0,
            'tax_prices_include_tax' => 1,
            'tax_restrict_to_spain' => 1,
            'tax_shipping' => 1,
            'tax_peninsula_rate' => 21.0,
            'tax_baleares_rate' => 21.0,
            'tax_canarias_rate' => 7.0,
            'tax_ceuta_rate' => 10.0,
            'tax_melilla_rate' => 10.0,
            'tax_special_note' => 'Canarias (IGIC), Ceuta y Melilla (IPSI) tienen regímenes fiscales propios, fuera del IVA peninsular y del IVA UE. Confirme tipos aplicables; despacho, transporte u otros gastos en destino pueden no estar incluidos.',
            'tax_eu_b2b_enabled' => 0,
            'tax_eu_block_if_vies_down' => 1,
            'tax_eu_seller_vat' => '',
            'tax_special_rates_enabled' => 1,
            // IGIC Canarias (catálogo completo, editable).
            'tax_igic_cero' => 0.0,
            'tax_igic_reducido' => 3.0,
            'tax_igic_general' => 7.0,
            'tax_igic_incrementado' => 9.5,
            'tax_igic_especial_15' => 15.0,
            'tax_igic_especial_20' => 20.0,
            // IPSI Ceuta.
            'tax_ipsi_ceuta_cero' => 0.0,
            'tax_ipsi_ceuta_r05' => 0.5,
            'tax_ipsi_ceuta_r1' => 1.0,
            'tax_ipsi_ceuta_r2' => 2.0,
            'tax_ipsi_ceuta_r4' => 4.0,
            'tax_ipsi_ceuta_r8' => 8.0,
            'tax_ipsi_ceuta_general' => 10.0,
            // IPSI Melilla.
            'tax_ipsi_melilla_cero' => 0.0,
            'tax_ipsi_melilla_r05' => 0.5,
            'tax_ipsi_melilla_r1' => 1.0,
            'tax_ipsi_melilla_r2' => 2.0,
            'tax_ipsi_melilla_r4' => 4.0,
            'tax_ipsi_melilla_r8' => 8.0,
            'tax_ipsi_melilla_general' => 10.0,
            'tax_eu_reverse_charge_note' => 'Operación intracomunitaria. Inversión del sujeto pasivo (art. 25 Ley 37/1992 del IVA y normativa UE aplicable). IVA 0 %.',
        );
    }

    public static function invoice_defaults() {
        return array(
            'invoice_enabled'              => 1,
            'invoice_series'               => 'FAC',
            'invoice_padding'              => 6,
            'invoice_title'                => 'FACTURA',
            'auto_invoice'                 => 1,
            'invoice_attach_to_woo_emails' => 1,
            'invoice_email_ids'            => 'customer_processing_order,customer_completed_order',
            'invoice_show_order_reference' => 1,
            'invoice_show_payment_method'  => 1,
            'invoice_show_sku'             => 1,
            'invoice_footer_text'          => '',
        );
    }

    public static function proforma_defaults() {
        return array(
            'proforma_enabled'              => 1,
            'proforma_series'               => 'PRO',
            'proforma_padding'              => 6,
            'proforma_title'                => 'FACTURA PROFORMA',
            'auto_proforma'                 => 1,
            'proforma_order_statuses'       => 'on-hold',
            'proforma_attach_to_woo_emails' => 1,
            'proforma_email_ids'            => 'customer_on_hold_order',
            'proforma_show_order_reference' => 1,
            'proforma_show_payment_method'  => 1,
            'proforma_show_sku'             => 1,
            'proforma_show_payment_info'    => 0,
            'proforma_beneficiary'          => '',
            'proforma_iban'                 => '',
            'proforma_bizum'                => '',
            'proforma_payment_instructions' => '',
            'proforma_footer_text'          => '',
        );
    }

    public static function quote_defaults() {
        return array(
            'quote_enabled'            => 0,
            'quote_series'             => 'PRE',
            'quote_padding'            => 6,
            'quote_title'              => 'PRESUPUESTO',
            'quote_button_text'        => 'Descargar presupuesto',
            'quote_validity_days'      => 15,
            'quote_guest_allowed'      => 1,
            'quote_ask_company'        => 1,
            'quote_ask_tax_id'         => 1,
            'quote_ask_contact'        => 1,
            'quote_ask_email'          => 1,
            'quote_require_email'      => 0,
            'quote_show_sku'           => 1,
            'quote_show_tax'           => 1,
            'quote_show_shipping'      => 1,
            'quote_show_discounts'     => 1,
            'quote_show_images'        => 0,
            'quote_send_email_copy'    => 0,
            'quote_hourly_limit'       => 20,
            'quote_terms_text'         => 'Este documento constituye un presupuesto comercial y no tiene validez fiscal. Los precios quedan sujetos al plazo de validez indicado y a disponibilidad de stock.',
            'quote_footer_text'        => '',
        );
    }

    /**
     * Compatibilidad con codigo V1: devuelve una vista plana de toda la configuracion.
     */
    public static function defaults() {
        return array_merge(
            self::company_defaults(),
            self::tax_defaults(),
            self::invoice_defaults(),
            self::proforma_defaults(),
            self::quote_defaults()
        );
    }

    public static function company() {
        return self::option_array(self::COMPANY_OPTION, self::company_defaults());
    }

    public static function taxation() {
        return self::option_array(self::TAX_OPTION, self::tax_defaults());
    }

    public static function invoice() {
        return self::option_array(self::INVOICE_OPTION, self::invoice_defaults());
    }

    public static function proforma() {
        return self::option_array(self::PROFORMA_OPTION, self::proforma_defaults());
    }

    public static function quote() {
        return self::option_array(self::QUOTE_OPTION, self::quote_defaults());
    }

    public static function all() {
        return array_merge(self::company(), self::taxation(), self::invoice(), self::proforma(), self::quote());
    }

    public static function get($key, $default = null) {
        $settings = self::all();
        return array_key_exists($key, $settings) ? $settings[$key] : $default;
    }

    public static function register() {
        register_setting(
            'seo_facturas_company_group',
            self::COMPANY_OPTION,
            array(
                'type'              => 'array',
                'sanitize_callback' => array(__CLASS__, 'sanitize_company'),
                'default'           => self::company_defaults(),
            )
        );

        register_setting(
            'seo_facturas_tax_group',
            self::TAX_OPTION,
            array(
                'type'              => 'array',
                'sanitize_callback' => array(__CLASS__, 'sanitize_tax'),
                'default'           => self::tax_defaults(),
            )
        );

        register_setting(
            'seo_facturas_invoice_group',
            self::INVOICE_OPTION,
            array(
                'type'              => 'array',
                'sanitize_callback' => array(__CLASS__, 'sanitize_invoice'),
                'default'           => self::invoice_defaults(),
            )
        );

        register_setting(
            'seo_facturas_proforma_group',
            self::PROFORMA_OPTION,
            array(
                'type'              => 'array',
                'sanitize_callback' => array(__CLASS__, 'sanitize_proforma'),
                'default'           => self::proforma_defaults(),
            )
        );

        register_setting(
            'seo_facturas_quote_group',
            self::QUOTE_OPTION,
            array(
                'type'              => 'array',
                'sanitize_callback' => array(__CLASS__, 'sanitize_quote'),
                'default'           => self::quote_defaults(),
            )
        );
    }

    public static function sanitize_company($input) {
        $input = is_array($input) ? $input : array();
        $d = self::company_defaults();

        return array(
            'enabled'                => empty($input['enabled']) ? 0 : 1,
            'company_name'           => sanitize_text_field($input['company_name'] ?? $d['company_name']),
            'company_trade_name'     => sanitize_text_field($input['company_trade_name'] ?? ''),
            'company_tax_id'         => sanitize_text_field($input['company_tax_id'] ?? ''),
            'company_address'        => sanitize_text_field($input['company_address'] ?? ''),
            'company_postcode'       => sanitize_text_field($input['company_postcode'] ?? ''),
            'company_city'           => sanitize_text_field($input['company_city'] ?? ''),
            'company_region'         => sanitize_text_field($input['company_region'] ?? ''),
            'company_country'        => self::sanitize_country($input['company_country'] ?? 'ES'),
            'company_phone'          => sanitize_text_field($input['company_phone'] ?? ''),
            'company_email'          => sanitize_email($input['company_email'] ?? ''),
            'company_website'        => esc_url_raw($input['company_website'] ?? ''),
            'logo_id'                => absint($input['logo_id'] ?? 0),
            'footer_text'            => sanitize_textarea_field($input['footer_text'] ?? ''),
            'customer_tax_meta_keys' => self::sanitize_csv_meta_keys($input['customer_tax_meta_keys'] ?? $d['customer_tax_meta_keys']),
        );
    }

    public static function sanitize_tax($input) {
        $input = is_array($input) ? $input : array();
        $d = self::tax_defaults();

        $clean = array(
            'tax_manager_enabled'   => empty($input['tax_manager_enabled']) ? 0 : 1,
            'tax_prices_include_tax'=> empty($input['tax_prices_include_tax']) ? 0 : 1,
            'tax_restrict_to_spain' => empty($input['tax_restrict_to_spain']) ? 0 : 1,
            'tax_shipping'         => empty($input['tax_shipping']) ? 0 : 1,
            'tax_peninsula_rate'   => self::sanitize_tax_rate($input['tax_peninsula_rate'] ?? $d['tax_peninsula_rate']),
            'tax_baleares_rate'    => self::sanitize_tax_rate($input['tax_baleares_rate'] ?? $d['tax_baleares_rate']),
            'tax_canarias_rate'    => self::sanitize_tax_rate($input['tax_canarias_rate'] ?? $d['tax_canarias_rate']),
            'tax_ceuta_rate'       => self::sanitize_tax_rate($input['tax_ceuta_rate'] ?? $d['tax_ceuta_rate']),
            'tax_melilla_rate'     => self::sanitize_tax_rate($input['tax_melilla_rate'] ?? $d['tax_melilla_rate']),
            'tax_special_note'     => sanitize_textarea_field($input['tax_special_note'] ?? $d['tax_special_note']),
            'tax_eu_b2b_enabled'   => empty($input['tax_eu_b2b_enabled']) ? 0 : 1,
            'tax_eu_block_if_vies_down' => empty($input['tax_eu_block_if_vies_down']) ? 0 : 1,
            'tax_eu_seller_vat'    => strtoupper(sanitize_text_field($input['tax_eu_seller_vat'] ?? $d['tax_eu_seller_vat'])),
            'tax_eu_reverse_charge_note' => sanitize_textarea_field($input['tax_eu_reverse_charge_note'] ?? $d['tax_eu_reverse_charge_note']),
            'tax_special_rates_enabled' => empty($input['tax_special_rates_enabled']) ? 0 : 1,
            'tax_igic_cero' => self::sanitize_tax_rate($input['tax_igic_cero'] ?? $d['tax_igic_cero']),
            'tax_igic_reducido' => self::sanitize_tax_rate($input['tax_igic_reducido'] ?? $d['tax_igic_reducido']),
            'tax_igic_general' => self::sanitize_tax_rate($input['tax_igic_general'] ?? $d['tax_igic_general']),
            'tax_igic_incrementado' => self::sanitize_tax_rate($input['tax_igic_incrementado'] ?? $d['tax_igic_incrementado']),
            'tax_igic_especial_15' => self::sanitize_tax_rate($input['tax_igic_especial_15'] ?? $d['tax_igic_especial_15']),
            'tax_igic_especial_20' => self::sanitize_tax_rate($input['tax_igic_especial_20'] ?? $d['tax_igic_especial_20']),
            'tax_ipsi_ceuta_cero' => self::sanitize_tax_rate($input['tax_ipsi_ceuta_cero'] ?? $d['tax_ipsi_ceuta_cero']),
            'tax_ipsi_ceuta_r05' => self::sanitize_tax_rate($input['tax_ipsi_ceuta_r05'] ?? $d['tax_ipsi_ceuta_r05']),
            'tax_ipsi_ceuta_r1' => self::sanitize_tax_rate($input['tax_ipsi_ceuta_r1'] ?? $d['tax_ipsi_ceuta_r1']),
            'tax_ipsi_ceuta_r2' => self::sanitize_tax_rate($input['tax_ipsi_ceuta_r2'] ?? $d['tax_ipsi_ceuta_r2']),
            'tax_ipsi_ceuta_r4' => self::sanitize_tax_rate($input['tax_ipsi_ceuta_r4'] ?? $d['tax_ipsi_ceuta_r4']),
            'tax_ipsi_ceuta_r8' => self::sanitize_tax_rate($input['tax_ipsi_ceuta_r8'] ?? $d['tax_ipsi_ceuta_r8']),
            'tax_ipsi_ceuta_general' => self::sanitize_tax_rate($input['tax_ipsi_ceuta_general'] ?? $d['tax_ipsi_ceuta_general']),
            'tax_ipsi_melilla_cero' => self::sanitize_tax_rate($input['tax_ipsi_melilla_cero'] ?? $d['tax_ipsi_melilla_cero']),
            'tax_ipsi_melilla_r05' => self::sanitize_tax_rate($input['tax_ipsi_melilla_r05'] ?? $d['tax_ipsi_melilla_r05']),
            'tax_ipsi_melilla_r1' => self::sanitize_tax_rate($input['tax_ipsi_melilla_r1'] ?? $d['tax_ipsi_melilla_r1']),
            'tax_ipsi_melilla_r2' => self::sanitize_tax_rate($input['tax_ipsi_melilla_r2'] ?? $d['tax_ipsi_melilla_r2']),
            'tax_ipsi_melilla_r4' => self::sanitize_tax_rate($input['tax_ipsi_melilla_r4'] ?? $d['tax_ipsi_melilla_r4']),
            'tax_ipsi_melilla_r8' => self::sanitize_tax_rate($input['tax_ipsi_melilla_r8'] ?? $d['tax_ipsi_melilla_r8']),
            'tax_ipsi_melilla_general' => self::sanitize_tax_rate($input['tax_ipsi_melilla_general'] ?? $d['tax_ipsi_melilla_general']),
        );

        if (!empty($clean['tax_special_rates_enabled'])) {
            $clean['tax_canarias_rate'] = $clean['tax_igic_general'];
            $clean['tax_ceuta_rate'] = $clean['tax_ipsi_ceuta_general'];
            $clean['tax_melilla_rate'] = $clean['tax_ipsi_melilla_general'];
        }
        if (!empty($clean['tax_manager_enabled']) && class_exists('SEO_Facturas_Tax')) {
            $sync = SEO_Facturas_Tax::sync_managed_rates($clean);
            if (is_wp_error($sync)) {
                add_settings_error(self::TAX_OPTION, 'seo_facturas_tax_sync', $sync->get_error_message(), 'error');
            }
        }

        return $clean;
    }

    public static function sanitize_invoice($input) {
        $input = is_array($input) ? $input : array();
        $d = self::invoice_defaults();

        return array(
            'invoice_enabled'              => empty($input['invoice_enabled']) ? 0 : 1,
            'invoice_series'               => self::sanitize_series($input['invoice_series'] ?? $d['invoice_series'], 'FAC'),
            'invoice_padding'              => self::sanitize_padding($input['invoice_padding'] ?? $d['invoice_padding']),
            'invoice_title'                => sanitize_text_field($input['invoice_title'] ?? $d['invoice_title']),
            'auto_invoice'                 => empty($input['auto_invoice']) ? 0 : 1,
            'invoice_attach_to_woo_emails' => empty($input['invoice_attach_to_woo_emails']) ? 0 : 1,
            'invoice_email_ids'            => self::sanitize_csv_keys($input['invoice_email_ids'] ?? $d['invoice_email_ids']),
            'invoice_show_order_reference' => empty($input['invoice_show_order_reference']) ? 0 : 1,
            'invoice_show_payment_method'  => empty($input['invoice_show_payment_method']) ? 0 : 1,
            'invoice_show_sku'             => empty($input['invoice_show_sku']) ? 0 : 1,
            'invoice_footer_text'          => sanitize_textarea_field($input['invoice_footer_text'] ?? ''),
        );
    }

    public static function sanitize_proforma($input) {
        $input = is_array($input) ? $input : array();
        $d = self::proforma_defaults();

        return array(
            'proforma_enabled'              => empty($input['proforma_enabled']) ? 0 : 1,
            'proforma_series'               => self::sanitize_series($input['proforma_series'] ?? $d['proforma_series'], 'PRO'),
            'proforma_padding'              => self::sanitize_padding($input['proforma_padding'] ?? $d['proforma_padding']),
            'proforma_title'                => sanitize_text_field($input['proforma_title'] ?? $d['proforma_title']),
            'auto_proforma'                 => empty($input['auto_proforma']) ? 0 : 1,
            'proforma_order_statuses'       => self::sanitize_csv_keys($input['proforma_order_statuses'] ?? $d['proforma_order_statuses']),
            'proforma_attach_to_woo_emails' => empty($input['proforma_attach_to_woo_emails']) ? 0 : 1,
            'proforma_email_ids'            => self::sanitize_csv_keys($input['proforma_email_ids'] ?? $d['proforma_email_ids']),
            'proforma_show_order_reference' => empty($input['proforma_show_order_reference']) ? 0 : 1,
            'proforma_show_payment_method'  => empty($input['proforma_show_payment_method']) ? 0 : 1,
            'proforma_show_sku'             => empty($input['proforma_show_sku']) ? 0 : 1,
            'proforma_show_payment_info'    => empty($input['proforma_show_payment_info']) ? 0 : 1,
            'proforma_beneficiary'          => sanitize_text_field($input['proforma_beneficiary'] ?? ''),
            'proforma_iban'                 => sanitize_text_field($input['proforma_iban'] ?? ''),
            'proforma_bizum'                => sanitize_text_field($input['proforma_bizum'] ?? ''),
            'proforma_payment_instructions' => sanitize_textarea_field($input['proforma_payment_instructions'] ?? ''),
            'proforma_footer_text'          => sanitize_textarea_field($input['proforma_footer_text'] ?? ''),
        );
    }

    public static function sanitize_quote($input) {
        $input = is_array($input) ? $input : array();
        $d = self::quote_defaults();
        $require_email = empty($input['quote_require_email']) ? 0 : 1;

        return array(
            'quote_enabled'         => empty($input['quote_enabled']) ? 0 : 1,
            'quote_series'          => self::sanitize_series($input['quote_series'] ?? $d['quote_series'], 'PRE'),
            'quote_padding'         => self::sanitize_padding($input['quote_padding'] ?? $d['quote_padding']),
            'quote_title'           => sanitize_text_field($input['quote_title'] ?? $d['quote_title']),
            'quote_button_text'     => sanitize_text_field($input['quote_button_text'] ?? $d['quote_button_text']),
            'quote_validity_days'   => max(1, min(365, absint($input['quote_validity_days'] ?? $d['quote_validity_days']))),
            'quote_guest_allowed'   => empty($input['quote_guest_allowed']) ? 0 : 1,
            'quote_ask_company'     => empty($input['quote_ask_company']) ? 0 : 1,
            'quote_ask_tax_id'      => empty($input['quote_ask_tax_id']) ? 0 : 1,
            'quote_ask_contact'     => empty($input['quote_ask_contact']) ? 0 : 1,
            'quote_ask_email'       => ($require_email || !empty($input['quote_ask_email'])) ? 1 : 0,
            'quote_require_email'   => $require_email,
            'quote_show_sku'        => empty($input['quote_show_sku']) ? 0 : 1,
            'quote_show_tax'        => empty($input['quote_show_tax']) ? 0 : 1,
            'quote_show_shipping'   => empty($input['quote_show_shipping']) ? 0 : 1,
            'quote_show_discounts'  => empty($input['quote_show_discounts']) ? 0 : 1,
            'quote_show_images'     => empty($input['quote_show_images']) ? 0 : 1,
            'quote_send_email_copy' => empty($input['quote_send_email_copy']) ? 0 : 1,
            'quote_hourly_limit'    => max(1, min(200, absint($input['quote_hourly_limit'] ?? $d['quote_hourly_limit']))),
            'quote_terms_text'      => sanitize_textarea_field($input['quote_terms_text'] ?? $d['quote_terms_text']),
            'quote_footer_text'     => sanitize_textarea_field($input['quote_footer_text'] ?? ''),
        );
    }

    public static function company_snapshot() {
        $s = self::company();

        return array(
            'name'          => (string) $s['company_name'],
            'trade_name'    => (string) $s['company_trade_name'],
            'tax_id'        => (string) $s['company_tax_id'],
            'address'       => (string) $s['company_address'],
            'postcode'      => (string) $s['company_postcode'],
            'city'          => (string) $s['company_city'],
            'region'        => (string) $s['company_region'],
            'country'       => (string) $s['company_country'],
            'country_name'  => self::country_name((string) $s['company_country']),
            'phone'         => (string) $s['company_phone'],
            'email'         => (string) $s['company_email'],
            'website'       => (string) $s['company_website'],
            'logo_data_uri' => self::logo_data_uri(absint($s['logo_id'])),
            'footer_text'   => (string) $s['footer_text'],
        );
    }

    public static function document_profile($type) {
        $type = sanitize_key((string) $type);

        if ('invoice' === $type) {
            $s = self::invoice();
            return array(
                'title'                => (string) $s['invoice_title'],
                'show_order_reference' => !empty($s['invoice_show_order_reference']),
                'show_payment_method'  => !empty($s['invoice_show_payment_method']),
                'show_sku'             => !empty($s['invoice_show_sku']),
                'footer_text'          => (string) $s['invoice_footer_text'],
            );
        }

        if ('proforma' === $type) {
            $s = self::proforma();
            return array(
                'title'                => (string) $s['proforma_title'],
                'show_order_reference' => !empty($s['proforma_show_order_reference']),
                'show_payment_method'  => !empty($s['proforma_show_payment_method']),
                'show_sku'             => !empty($s['proforma_show_sku']),
                'show_payment_info'    => !empty($s['proforma_show_payment_info']),
                'beneficiary'          => (string) $s['proforma_beneficiary'],
                'iban'                 => (string) $s['proforma_iban'],
                'bizum'                => (string) $s['proforma_bizum'],
                'payment_instructions' => (string) $s['proforma_payment_instructions'],
                'footer_text'          => (string) $s['proforma_footer_text'],
            );
        }

        if ('quote' === $type) {
            $s = self::quote();
            return array(
                'title'            => (string) $s['quote_title'],
                'show_sku'         => !empty($s['quote_show_sku']),
                'show_tax'         => !empty($s['quote_show_tax']),
                'show_shipping'    => !empty($s['quote_show_shipping']),
                'show_discounts'   => !empty($s['quote_show_discounts']),
                'show_images'      => !empty($s['quote_show_images']),
                'validity_days'    => absint($s['quote_validity_days']),
                'terms_text'       => (string) $s['quote_terms_text'],
                'footer_text'      => (string) $s['quote_footer_text'],
            );
        }

        return array();
    }

    public static function email_ids($type) {
        $key = ('invoice' === $type) ? 'invoice_email_ids' : 'proforma_email_ids';
        $raw = (string) self::get($key, '');
        $ids = array_filter(array_map('sanitize_key', array_map('trim', explode(',', $raw))));
        return array_values(array_unique($ids));
    }

    public static function proforma_order_statuses() {
        $raw = (string) self::get('proforma_order_statuses', 'on-hold');
        $items = array_filter(array_map('sanitize_key', array_map('trim', explode(',', $raw))));
        return array_values(array_unique($items));
    }

    public static function customer_tax_meta_keys() {
        $raw = (string) self::get('customer_tax_meta_keys', '');
        $keys = array_filter(array_map('trim', explode(',', $raw)));
        return array_values(array_unique($keys));
    }

    public static function padding_for_type($type) {
        $type = sanitize_key((string) $type);
        if ('rectification' === $type) {
            return 6;
        }
        if ('invoice' === $type) {
            return self::sanitize_padding(self::get('invoice_padding', 6));
        }
        if ('proforma' === $type) {
            return self::sanitize_padding(self::get('proforma_padding', 6));
        }
        return self::sanitize_padding(self::get('quote_padding', 6));
    }

    public static function series_for_type($type) {
        $type = sanitize_key((string) $type);
        if ('rectification' === $type) {
            return 'RECT';
        }
        if ('invoice' === $type) {
            return self::sanitize_series(self::get('invoice_series', 'FAC'), 'FAC');
        }
        if ('proforma' === $type) {
            return self::sanitize_series(self::get('proforma_series', 'PRO'), 'PRO');
        }
        return self::sanitize_series(self::get('quote_series', 'PRE'), 'PRE');
    }

    private static function option_array($option, array $defaults) {
        $saved = get_option($option, array());
        if (!is_array($saved)) {
            $saved = array();
        }
        return wp_parse_args($saved, $defaults);
    }

    private static function maybe_migrate_legacy() {
        if (get_option(self::MIGRATION_FLAG)) {
            return;
        }

        $legacy = get_option(self::LEGACY_OPTION, array());
        if (!is_array($legacy) || empty($legacy)) {
            update_option(self::MIGRATION_FLAG, '1', false);
            return;
        }

        if (false === get_option(self::COMPANY_OPTION, false)) {
            $company = self::company_defaults();
            foreach (array(
                'enabled', 'company_name', 'company_tax_id', 'company_address', 'company_postcode',
                'company_city', 'company_region', 'company_country', 'company_phone', 'company_email',
                'company_website', 'logo_id', 'footer_text', 'customer_tax_meta_keys'
            ) as $key) {
                if (array_key_exists($key, $legacy)) {
                    $company[$key] = $legacy[$key];
                }
            }
            update_option(self::COMPANY_OPTION, self::sanitize_company($company), false);
        }

        if (false === get_option(self::INVOICE_OPTION, false)) {
            $invoice = self::invoice_defaults();
            foreach (array('invoice_series', 'auto_invoice', 'invoice_email_ids') as $key) {
                if (array_key_exists($key, $legacy)) {
                    $invoice[$key] = $legacy[$key];
                }
            }
            if (isset($legacy['number_padding'])) {
                $invoice['invoice_padding'] = $legacy['number_padding'];
            }
            if (isset($legacy['attach_to_woo_emails'])) {
                $invoice['invoice_attach_to_woo_emails'] = $legacy['attach_to_woo_emails'];
            }
            update_option(self::INVOICE_OPTION, self::sanitize_invoice($invoice), false);
        }

        if (false === get_option(self::PROFORMA_OPTION, false)) {
            $proforma = self::proforma_defaults();
            foreach (array('proforma_series', 'auto_proforma', 'proforma_email_ids') as $key) {
                if (array_key_exists($key, $legacy)) {
                    $proforma[$key] = $legacy[$key];
                }
            }
            if (isset($legacy['number_padding'])) {
                $proforma['proforma_padding'] = $legacy['number_padding'];
            }
            if (isset($legacy['attach_to_woo_emails'])) {
                $proforma['proforma_attach_to_woo_emails'] = $legacy['attach_to_woo_emails'];
            }
            update_option(self::PROFORMA_OPTION, self::sanitize_proforma($proforma), false);
        }

        update_option(self::MIGRATION_FLAG, '1', false);
    }

    private static function sanitize_tax_rate($value) {
        if (is_string($value)) {
            $value = str_replace(',', '.', trim($value));
        }
        $value = is_numeric($value) ? (float) $value : 0.0;
        return round(max(0, min(100, $value)), 4);
    }

    private static function sanitize_padding($value) {
        return max(3, min(10, absint($value)));
    }

    private static function sanitize_series($value, $fallback) {
        $value = strtoupper((string) $value);
        $value = preg_replace('/[^A-Z0-9_-]/', '', $value);
        $value = substr($value, 0, 20);
        return '' !== $value ? $value : $fallback;
    }

    private static function sanitize_country($value) {
        $value = strtoupper(sanitize_text_field((string) $value));
        return preg_match('/^[A-Z]{2}$/', $value) ? $value : 'ES';
    }

    private static function sanitize_csv_keys($value) {
        $items = array_filter(array_map('sanitize_key', array_map('trim', explode(',', (string) $value))));
        return implode(',', array_values(array_unique($items)));
    }

    private static function sanitize_csv_meta_keys($value) {
        $items = array_filter(array_map('trim', explode(',', (string) $value)));
        $clean = array();
        foreach ($items as $item) {
            $item = preg_replace('/[^A-Za-z0-9_-]/', '', $item);
            if ('' !== $item) {
                $clean[] = $item;
            }
        }
        return implode(',', array_values(array_unique($clean)));
    }

    private static function country_name($country) {
        if (function_exists('WC') && WC() && isset(WC()->countries)) {
            $countries = WC()->countries->get_countries();
            if (isset($countries[$country])) {
                return (string) $countries[$country];
            }
        }
        return (string) $country;
    }

    private static function logo_data_uri($attachment_id) {
        if (!$attachment_id) {
            return '';
        }

        $path = get_attached_file($attachment_id);
        if (!$path || !is_readable($path)) {
            return '';
        }

        $mime = get_post_mime_type($attachment_id);
        if (!$mime || 0 !== strpos($mime, 'image/')) {
            return '';
        }

        $bytes = file_get_contents($path);
        if (false === $bytes || '' === $bytes) {
            return '';
        }

        return 'data:' . $mime . ';base64,' . base64_encode($bytes);
    }
}
