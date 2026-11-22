<?php
/**
 * Documentos PDF efimeros generados desde el carrito WooCommerce.
 *
 * El presupuesto es un documento comercial. La "proforma borrador" del
 * carrito es solo una previsualizacion no persistida: la proforma definitiva
 * se vincula a un pedido WooCommerce existente y la factura solo se emite
 * cuando WooCommerce confirma el pago.
 */

defined('ABSPATH') || exit;

final class SEO_Facturas_Quotes {

    private static $initialized = false;
    private static $rendered = false;
    private static $dht_rendered = false;
    private static $request_kind = 'quote';

    public static function init() {
        if (self::$initialized) {
            return;
        }
        self::$initialized = true;

        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue_assets'));
        add_action('woocommerce_proceed_to_checkout', array(__CLASS__, 'render_cart_form'), 30);
        add_action('seo_facturas_cart_documents', array(__CLASS__, 'render_dht_cart_form'), 10);
        add_filter('render_block_woocommerce/cart', array(__CLASS__, 'append_to_cart_block'), 20, 2);
        add_shortcode('seo_facturas_presupuesto', array(__CLASS__, 'shortcode'));
        add_action('template_redirect', array(__CLASS__, 'handle_download'), 20);
    }

    public static function enqueue_assets() {
        if (!self::is_available() || !function_exists('is_cart') || !is_cart()) {
            return;
        }
        self::enqueue_front_assets();
    }

    public static function render_cart_form() {
        if (self::$rendered || !self::is_available()) {
            return;
        }
        self::$rendered = true;
        self::enqueue_front_assets();
        echo self::form_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    /**
     * Render exclusivo para la plantilla DHT del carrito.
     *
     * No reutiliza la bandera $rendered porque WooCommerce puede procesar
     * previamente su Cart Block aunque la plantilla DHT sea la salida final.
     * En ese caso, marcar $rendered no debe impedir que el resumen DHT muestre
     * sus documentos descargables.
     */
    public static function render_dht_cart_form() {
        if (self::$dht_rendered || !self::is_available()) {
            return;
        }

        self::$dht_rendered = true;
        self::enqueue_front_assets();
        echo self::form_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    public static function append_to_cart_block($block_content, $block) {
        if (self::$rendered || is_admin() || !self::is_available()) {
            return $block_content;
        }

        self::$rendered = true;
        self::enqueue_front_assets();
        return $block_content . self::form_html();
    }

    public static function shortcode() {
        if (!self::is_available()) {
            return '';
        }
        self::enqueue_front_assets();
        return self::form_html();
    }

    private static function enqueue_front_assets() {
        wp_enqueue_style(
            'seo-facturas-front',
            SEO_FACTURAS_URL . 'assets/frontend.css',
            array(),
            SEO_FACTURAS_VERSION
        );
        wp_enqueue_script(
            'seo-facturas-front',
            SEO_FACTURAS_URL . 'assets/frontend.js',
            array(),
            SEO_FACTURAS_VERSION,
            true
        );
    }

    private static function form_html() {
        if (!is_user_logged_in() && !SEO_Facturas_Settings::get('quote_guest_allowed', 1)) {
            return '<div class="woocommerce-info seo-facturas-quote-info">Los documentos descargables del carrito estan disponibles para clientes identificados.</div>';
        }

        $quote_available = self::is_quote_available();
        $draft_available = self::is_proforma_draft_available();
        if (!$quote_available && !$draft_available) {
            return '';
        }

        $error = self::pull_session_notice('seo_facturas_quote_error');
        $session_kind = sanitize_key(self::pull_session_notice('seo_facturas_quote_kind'));
        $initial_kind = in_array($session_kind, array('quote', 'proforma_draft'), true) ? $session_kind : ($quote_available ? 'quote' : 'proforma_draft');

        $s = SEO_Facturas_Settings::quote();
        $button_text = trim((string) $s['quote_button_text']);
        if ('' === $button_text) {
            $button_text = 'Descargar presupuesto';
        }

        ob_start();
        ?>
        <section class="seo-facturas-quote-box" data-seo-quote-box>
            <?php if ($error) : ?>
                <div class="woocommerce-error" role="alert"><?php echo esc_html($error); ?></div>
            <?php endif; ?>

            <div class="seo-facturas-cart-document-actions">
                <?php if ($quote_available) : ?>
                    <button
                        type="button"
                        class="button alt seo-facturas-doc-button seo-facturas-doc-button--quote"
                        data-seo-doc-open="quote"
                        data-title="Preparar presupuesto"
                        data-help="Generaremos un presupuesto PDF con los importes calculados actualmente por WooCommerce. No crea ningun pedido."
                        data-submit="Descargar presupuesto PDF"
                        aria-expanded="false"
                    >
                        <strong><?php echo esc_html(strtoupper($button_text)); ?> PDF</strong>
                        <span>Documento comercial sin validez fiscal</span>
                    </button>
                <?php endif; ?>

                <?php if ($draft_available) : ?>
                    <button
                        type="button"
                        class="button seo-facturas-doc-button seo-facturas-doc-button--proforma"
                        data-seo-doc-open="proforma_draft"
                        data-title="Preparar proforma borrador"
                        data-help="Generaremos una proforma de borrador a partir del carrito. No crea pedido, no usa la serie PRO definitiva y no acredita ningun pago."
                        data-submit="Descargar proforma borrador PDF"
                        aria-expanded="false"
                    >
                        <strong>DESCARGAR PROFORMA BORRADOR PDF</strong>
                        <span>La proforma definitiva se vincula al pedido</span>
                    </button>
                <?php endif; ?>
            </div>

            <p class="seo-facturas-invoice-cart-note">
                <strong>FACTURA:</strong> se emite unicamente cuando WooCommerce confirma el pago. Antes del pago no se genera una factura fiscal de borrador.
            </p>

            <div class="seo-facturas-quote-panel" data-seo-quote-panel <?php echo $error ? '' : 'hidden'; ?>>
                <h3 data-seo-doc-title><?php echo esc_html('proforma_draft' === $initial_kind ? 'Preparar proforma borrador' : 'Preparar presupuesto'); ?></h3>
                <p class="seo-facturas-quote-help" data-seo-doc-help>
                    <?php echo esc_html('proforma_draft' === $initial_kind ? 'Generaremos una proforma de borrador a partir del carrito. No crea pedido, no usa la serie PRO definitiva y no acredita ningun pago.' : 'Generaremos un presupuesto PDF con los importes calculados actualmente por WooCommerce. No crea ningun pedido.'); ?>
                </p>

                <form method="post" action="<?php echo esc_url(self::cart_url()); ?>" class="seo-facturas-quote-form">
                    <?php wp_nonce_field('seo_facturas_quote_download', 'seo_facturas_quote_nonce'); ?>
                    <input type="hidden" name="seo_facturas_quote_download" value="1">
                    <input type="hidden" name="seo_facturas_cart_document_kind" value="<?php echo esc_attr($initial_kind); ?>" data-seo-doc-kind>
                    <input type="hidden" name="seo_facturas_compare_ids" value="" data-seo-compare-ids>

                    <?php if (!empty($s['quote_ask_company'])) : ?>
                        <p class="form-row form-row-wide">
                            <label for="seo-quote-company">Empresa / nombre</label>
                            <input type="text" id="seo-quote-company" name="seo_quote_company" maxlength="160" autocomplete="organization">
                        </p>
                    <?php endif; ?>

                    <?php if (!empty($s['quote_ask_tax_id'])) : ?>
                        <p class="form-row form-row-first">
                            <label for="seo-quote-tax-id">NIF / CIF</label>
                            <input type="text" id="seo-quote-tax-id" name="seo_quote_tax_id" maxlength="60">
                        </p>
                    <?php endif; ?>

                    <?php if (!empty($s['quote_ask_contact'])) : ?>
                        <p class="form-row form-row-last">
                            <label for="seo-quote-contact">Persona de contacto</label>
                            <input type="text" id="seo-quote-contact" name="seo_quote_contact" maxlength="160" autocomplete="name">
                        </p>
                    <?php endif; ?>

                    <p class="form-row form-row-wide">
                        <label for="seo-quote-address-1">Dirección</label>
                        <input type="text" id="seo-quote-address-1" name="seo_quote_address_1" maxlength="190" autocomplete="address-line1">
                    </p>
                    <p class="form-row form-row-wide">
                        <label for="seo-quote-address-2">Dirección (segunda línea, opcional)</label>
                        <input type="text" id="seo-quote-address-2" name="seo_quote_address_2" maxlength="190" autocomplete="address-line2">
                    </p>
                    <p class="form-row form-row-first">
                        <label for="seo-quote-postcode">Código postal</label>
                        <input type="text" id="seo-quote-postcode" name="seo_quote_postcode" maxlength="20" autocomplete="postal-code">
                    </p>
                    <p class="form-row form-row-last">
                        <label for="seo-quote-city">Localidad</label>
                        <input type="text" id="seo-quote-city" name="seo_quote_city" maxlength="120" autocomplete="address-level2">
                    </p>
                    <p class="form-row form-row-first">
                        <label for="seo-quote-state">Provincia</label>
                        <input type="text" id="seo-quote-state" name="seo_quote_state" maxlength="100" autocomplete="address-level1">
                    </p>
                    <p class="form-row form-row-last">
                        <label for="seo-quote-country">País</label>
                        <?php
                        $country_choices = (function_exists('WC') && WC() && isset(WC()->countries))
                            ? WC()->countries->get_allowed_countries()
                            : array('ES' => 'España');
                        ?>
                        <select id="seo-quote-country" name="seo_quote_country" autocomplete="country">
                            <option value="">Seleccionar país</option>
                            <?php foreach ($country_choices as $country_code => $country_label) : ?>
                                <option value="<?php echo esc_attr($country_code); ?>"><?php echo esc_html($country_label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </p>

                    <?php if (!empty($s['quote_ask_email'])) : ?>
                        <p class="form-row form-row-wide">
                            <label for="seo-quote-email">Email<?php echo !empty($s['quote_require_email']) ? ' *' : ' (opcional)'; ?></label>
                            <input type="email" id="seo-quote-email" name="seo_quote_email" maxlength="190" autocomplete="email" <?php echo !empty($s['quote_require_email']) ? 'required' : ''; ?>>
                        </p>
                    <?php endif; ?>

                    <div class="clear"></div>
                    <p class="seo-facturas-quote-validity">Validez configurada: <strong><?php echo esc_html(absint($s['quote_validity_days'])); ?> dias</strong>. Ningun documento del carrito reserva stock.</p>
                    <button type="submit" class="button alt seo-facturas-quote-submit" data-seo-doc-submit>
                        <?php echo esc_html('proforma_draft' === $initial_kind ? 'Descargar proforma borrador PDF' : 'Descargar presupuesto PDF'); ?>
                    </button>
                </form>
            </div>
        </section>
        <?php
        return (string) ob_get_clean();
    }

    public static function handle_download() {
        $request_method = isset($_SERVER['REQUEST_METHOD']) ? sanitize_key(wp_unslash($_SERVER['REQUEST_METHOD'])) : 'get';
        if ('post' !== $request_method) {
            return;
        }
        $download_requested = isset($_POST['seo_facturas_quote_download'])
            ? sanitize_text_field(wp_unslash($_POST['seo_facturas_quote_download']))
            : '';
        if ('1' !== $download_requested) {
            return;
        }

        $nonce = isset($_POST['seo_facturas_quote_nonce'])
            ? sanitize_text_field(wp_unslash($_POST['seo_facturas_quote_nonce']))
            : '';
        if (!$nonce || !wp_verify_nonce($nonce, 'seo_facturas_quote_download')) {
            self::fail('La solicitud ha caducado. Recarga el carrito y vuelve a intentarlo.');
        }

        // Read and sanitize form fields only after the nonce has been verified.
        // Helpers below receive these validated values instead of reading $_POST.
        $quote_fields = array(
            'company'     => sanitize_text_field(wp_unslash($_POST['seo_quote_company'] ?? '')),
            'tax_id'      => sanitize_text_field(wp_unslash($_POST['seo_quote_tax_id'] ?? '')),
            'contact'     => sanitize_text_field(wp_unslash($_POST['seo_quote_contact'] ?? '')),
            'email'       => sanitize_email(wp_unslash($_POST['seo_quote_email'] ?? '')),
            'address_1'   => sanitize_text_field(wp_unslash($_POST['seo_quote_address_1'] ?? '')),
            'address_2'   => sanitize_text_field(wp_unslash($_POST['seo_quote_address_2'] ?? '')),
            'postcode'    => sanitize_text_field(wp_unslash($_POST['seo_quote_postcode'] ?? '')),
            'city'        => sanitize_text_field(wp_unslash($_POST['seo_quote_city'] ?? '')),
            'state'       => sanitize_text_field(wp_unslash($_POST['seo_quote_state'] ?? '')),
            'country'     => strtoupper(sanitize_text_field(wp_unslash($_POST['seo_quote_country'] ?? ''))),
            'compare_ids' => sanitize_text_field(wp_unslash($_POST['seo_facturas_compare_ids'] ?? '')),
        );

        $kind = isset($_POST['seo_facturas_cart_document_kind'])
            ? sanitize_key(wp_unslash($_POST['seo_facturas_cart_document_kind']))
            : 'quote';
        if (!in_array($kind, array('quote', 'proforma_draft'), true)) {
            $kind = 'quote';
        }
        self::$request_kind = $kind;

        if ('quote' === $kind && !self::is_quote_available()) {
            self::fail('La descarga de presupuestos no esta disponible.');
        }
        if ('proforma_draft' === $kind && !self::is_proforma_draft_available()) {
            self::fail('La descarga de proformas de borrador no esta disponible.');
        }

        if (!is_user_logged_in() && !SEO_Facturas_Settings::get('quote_guest_allowed', 1)) {
            self::fail('Debes iniciar sesion para descargar este documento.');
        }

        if (function_exists('wc_load_cart') && (!function_exists('WC') || !WC() || !WC()->cart)) {
            wc_load_cart();
        }

        if (!function_exists('WC') || !WC() || !WC()->cart || WC()->cart->is_empty()) {
            self::fail('El carrito esta vacio.');
        }

        $rate_error = self::check_rate_limit();
        if (is_wp_error($rate_error)) {
            self::fail($rate_error->get_error_message());
        }

        $buyer = self::buyer_from_request($quote_fields);
        if (is_wp_error($buyer)) {
            self::fail($buyer->get_error_message());
        }

        $engine = SEO_Facturas_PDF::engine_status();
        if (empty($engine['available'])) {
            self::fail('No se puede generar el PDF porque Dompdf no esta disponible.');
        }

        // Calcular IVA segun el destino indicado para este documento, sin
        // conservar dicha direccion de prueba en la sesion del comprador.
        $customer = WC()->customer;
        $previous_address = array();
        $previous_shipping_calculated = null;
        $document_country = strtoupper((string) ($buyer['country'] ?? ''));
        $destination_complete = '' !== $document_country
            && '' !== trim((string) ($buyer['postcode'] ?? ''))
            && '' !== trim((string) ($buyer['city'] ?? ''));

        if ($customer) {
            if (is_callable(array($customer, 'has_calculated_shipping'))) {
                $previous_shipping_calculated = (bool) $customer->has_calculated_shipping();
            }
            foreach (array('billing', 'shipping') as $address_kind) {
                foreach (array('country', 'state', 'postcode', 'city') as $field) {
                    $getter = 'get_' . $address_kind . '_' . $field;
                    $setter = 'set_' . $address_kind . '_' . $field;
                    if (!is_callable(array($customer, $getter)) || !is_callable(array($customer, $setter))) {
                        continue;
                    }
                    $previous_address[$address_kind][$field] = $customer->{$getter}();
                    $new_value = 'country' === $field ? $document_country : (string) ($buyer[$field] ?? '');
                    if ('state' === $field) {
                        $new_value = self::woocommerce_state_code($document_country, $new_value);
                    }
                    $customer->{$setter}($new_value);
                }
            }
            if (is_callable(array($customer, 'set_calculated_shipping'))) {
                $customer->set_calculated_shipping($destination_complete);
            }
        }

        try {
            // WooCommerce requiere calcular primero las tarifas por destino y
            // despues los totales/IVA. El formulario no modifica el cliente.
            if (is_callable(array(WC()->cart, 'calculate_shipping'))) {
                WC()->cart->calculate_shipping();
            }
            WC()->cart->calculate_totals();

            $shipping_error = null;
            if (
                $destination_complete
                && WC()->cart->needs_shipping()
                && (
                    !is_callable(array(WC()->cart, 'get_shipping_methods'))
                    || !WC()->cart->get_shipping_methods()
                )
            ) {
                $shipping_error = new WP_Error(
                    'seo_facturas_quote_no_shipping_rate',
                    'No hay una tarifa de transporte disponible para el destino indicado. Comprueba la dirección o las reglas de Logística.'
                );
            }
            $tax_validation = class_exists('SEO_Facturas_Tax')
                ? SEO_Facturas_Tax::validate_cart_tax(WC()->cart, $buyer)
                : true;
        } finally {
            if ($customer) {
                foreach ($previous_address as $address_kind => $fields) {
                    foreach ($fields as $field => $old_value) {
                        $setter = 'set_' . $address_kind . '_' . $field;
                        $customer->{$setter}($old_value);
                    }
                }
                if (null !== $previous_shipping_calculated && is_callable(array($customer, 'set_calculated_shipping'))) {
                    $customer->set_calculated_shipping($previous_shipping_calculated);
                }
            }
        }
        if (is_wp_error($shipping_error)) {
            self::fail($shipping_error->get_error_message());
        }
        if (is_wp_error($tax_validation)) {
            self::fail($tax_validation->get_error_message());
        }

        if ('quote' === $kind) {
            $numbering = SEO_Facturas_Documents::reserve_number(SEO_Facturas_Documents::TYPE_QUOTE);
        } else {
            $numbering = self::draft_numbering();
        }
        if (is_wp_error($numbering)) {
            self::fail($numbering->get_error_message());
        }

        $snapshot = self::snapshot_from_cart($buyer, $numbering, $kind, $quote_fields['compare_ids']);
        if (is_wp_error($snapshot)) {
            self::fail($snapshot->get_error_message());
        }

        $html = SEO_Facturas_PDF::render_html($snapshot);
        if (is_wp_error($html)) {
            self::fail($html->get_error_message());
        }

        $binary = SEO_Facturas_PDF::render_binary($html, (string) $numbering['number'], 0);
        if (is_wp_error($binary)) {
            self::fail($binary->get_error_message());
        }

        if ('quote' === $kind && SEO_Facturas_Settings::get('quote_send_email_copy', 0) && !empty($buyer['email'])) {
            self::email_copy((string) $buyer['email'], (string) $numbering['number'], $binary);
        }

        self::mark_rate_limit();

        $filename = sanitize_file_name((string) $numbering['number'] . '.pdf');
        nocache_headers();
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($binary));
        header('X-Robots-Tag: noindex, nofollow, noarchive', true);
        echo $binary; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        exit;
    }

    private static function buyer_from_request(array $validated_fields) {
        $s = SEO_Facturas_Settings::quote();
        $country = (string) $validated_fields['country'];
        $allowed_countries = (function_exists('WC') && WC() && isset(WC()->countries))
            ? WC()->countries->get_allowed_countries()
            : array('ES' => 'España');
        if ('' !== $country && !isset($allowed_countries[$country])) {
            return new WP_Error('seo_facturas_quote_country_invalid', 'Selecciona un país válido.');
        }

        $buyer = array(
            'company'   => $validated_fields['company'],
            'tax_id'    => $validated_fields['tax_id'],
            'contact'   => $validated_fields['contact'],
            'email'     => $validated_fields['email'],
            'address_1' => $validated_fields['address_1'],
            'address_2' => $validated_fields['address_2'],
            'postcode'  => $validated_fields['postcode'],
            'city'      => $validated_fields['city'],
            'state'     => $validated_fields['state'],
            'country'   => $country,
        );

        if (!empty($s['quote_require_email']) && empty($buyer['email'])) {
            return new WP_Error('seo_facturas_quote_email_required', 'Introduce un email valido para generar el presupuesto.');
        }

        return $buyer;
    }

    private static function snapshot_from_cart(array $buyer, array $numbering, $kind = 'quote', $comparison_raw = '') {
        $cart = WC()->cart;
        $currency = get_woocommerce_currency();
        $profile = SEO_Facturas_Settings::document_profile(SEO_Facturas_Documents::TYPE_QUOTE);
        $kind = ('proforma_draft' === sanitize_key((string) $kind)) ? 'proforma_draft' : 'quote';

        if ('proforma_draft' === $kind) {
            $proforma = SEO_Facturas_Settings::proforma();
            $base_title = trim((string) ($proforma['proforma_title'] ?? 'FACTURA PROFORMA'));
            $profile['title'] = ($base_title ?: 'FACTURA PROFORMA') . ' - BORRADOR';
            $profile['show_sku'] = !empty($proforma['proforma_show_sku']);
            $profile['warning_text'] = 'BORRADOR DE PROFORMA - SIN VALIDEZ FISCAL - NO ES FACTURA';
            $profile['recipient_label'] = 'Proforma para:';
            $profile['detail_heading'] = 'Detalle de la proforma borrador';
            $profile['reference_label'] = 'Referencia de borrador:';
            $profile['total_label'] = 'TOTAL PROFORMA (BORRADOR)';
            $profile['terms_text'] = 'Borrador de factura proforma generado a partir del carrito. No acredita pago, no constituye factura fiscal, no reserva stock y puede variar hasta que se cree el pedido. La proforma definitiva se vinculara al pedido si este queda pendiente de pago.';
            $profile['footer_text'] = (string) ($proforma['proforma_footer_text'] ?? '');
        }

        $validity_days = max(1, absint($profile['validity_days'] ?? 15));
        if (function_exists('current_datetime')) {
            $valid_until = current_datetime()->modify('+' . $validity_days . ' days')->format('Y-m-d H:i:s');
        } else {
            $valid_until = wp_date('Y-m-d H:i:s', time() + ($validity_days * DAY_IN_SECONDS));
        }

        $items = array();
        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            $product = isset($cart_item['data']) && is_a($cart_item['data'], 'WC_Product') ? $cart_item['data'] : null;
            $qty = max(0.0, (float) ($cart_item['quantity'] ?? 0));
            $line_subtotal = (float) ($cart_item['line_subtotal'] ?? 0);
            $line_total = (float) ($cart_item['line_total'] ?? 0);
            $commercial = ($product && class_exists('SEO_Facturas_Snapshot'))
                ? SEO_Facturas_Snapshot::product_commercial_snapshot($product)
                : array();

            $items[] = array(
                'cart_item_key' => sanitize_text_field((string) $cart_item_key),
                'product_id'    => absint($cart_item['product_id'] ?? 0),
                'object_id'     => absint($cart_item['product_id'] ?? 0),
                'variation_id'  => absint($cart_item['variation_id'] ?? 0),
                'sku'           => $product ? (string) $product->get_sku() : '',
                'name'          => $product ? (string) $product->get_name() : '',
                'quantity'      => $qty,
                'subtotal'      => $line_subtotal,
                'subtotal_tax'  => (float) ($cart_item['line_subtotal_tax'] ?? 0),
                'total'         => $line_total,
                'total_tax'     => (float) ($cart_item['line_tax'] ?? 0),
                'unit_net'      => $qty > 0 ? ($line_total / $qty) : $line_total,
                'image_data_uri'=> !empty($profile['show_images'])
                    ? (string) ($commercial['image_data_uri'] ?? self::product_image_data_uri($product))
                    : '',
                'commercial'    => $commercial,
            );
        }

        if (!$items) {
            return new WP_Error('seo_facturas_quote_items_empty', 'No hay productos que incluir en el presupuesto.');
        }

        $tax_lines = array();
        foreach ((array) $cart->get_tax_totals() as $tax) {
            $tax_lines[] = array(
                'label'       => isset($tax->label) ? (string) $tax->label : 'Impuesto',
                'tax_total'   => isset($tax->amount) ? (float) $tax->amount : 0.0,
                'rate_percent'=> null,
            );
        }

        $coupons = array();
        foreach ((array) $cart->get_coupons() as $code => $coupon) {
            $coupons[] = array(
                'code'     => sanitize_text_field((string) $code),
                'discount' => (float) $cart->get_coupon_discount_amount($code, false),
            );
        }

        $customer = WC()->customer;
        // Los datos del destinatario proceden del formulario, no de una
        // dirección anterior conservada en la sesión de WooCommerce.
        $billing = array(
            'company'      => (string) $buyer['company'],
            'contact'      => (string) $buyer['contact'],
            'email'        => (string) $buyer['email'],
            'tax_id'       => (string) $buyer['tax_id'],
            'address_1'    => (string) $buyer['address_1'],
            'address_2'    => (string) $buyer['address_2'],
            'postcode'     => (string) $buyer['postcode'],
            'city'         => (string) $buyer['city'],
            'state'        => (string) $buyer['state'],
            'state_name'   => self::state_name((string) $buyer['country'], (string) $buyer['state']),
            'country'      => (string) $buyer['country'],
            'country_name' => '' !== $buyer['country'] ? self::country_name((string) $buyer['country']) : '',
        );

        // La sesión se utiliza solo para el contexto fiscal/porte ya calculado.
        // No se imprime como una supuesta dirección facilitada en la proforma.
        $cart_shipping = self::customer_address($customer, 'shipping');
        $destination = !empty($billing['country']) ? $billing : $cart_shipping;
        $shipping = array();
        $fiscal = class_exists('SEO_Facturas_Tax')
            ? SEO_Facturas_Tax::context_for_location(
                (string) ($destination['country'] ?? ''),
                (string) ($destination['state'] ?? ''),
                (string) ($destination['postcode'] ?? '')
            )
            : array();

        if ($tax_lines && !empty($fiscal['enabled']) && null !== ($fiscal['rate'] ?? null)) {
            foreach ($tax_lines as &$tax_line) {
                if (null === ($tax_line['rate_percent'] ?? null)) {
                    $tax_line['rate_percent'] = (float) $fiscal['rate'];
                }
            }
            unset($tax_line);
        }

        $snapshot = array(
            'schema_version' => 2,
            'document'       => array_merge(
                array(
                    'type'        => SEO_Facturas_Documents::TYPE_QUOTE,
                    'number'      => (string) $numbering['number'],
                    'issued_at'   => (string) $numbering['issued_at'],
                    'valid_until' => $valid_until,
                    'variant'     => $kind,
                ),
                $profile
            ),
            'seller'         => SEO_Facturas_Settings::company_snapshot(),
            'cart'           => array(
                'currency' => $currency,
            ),
            'billing'        => $billing,
            'shipping'       => $shipping,
            'fiscal'         => $fiscal,
            'items'          => $items,
            'coupon_lines'   => $coupons,
            'tax_lines'      => $tax_lines,
            'comparison'     => self::comparison_from_request($comparison_raw),
            'totals'         => array(
                'subtotal_items' => (float) $cart->get_subtotal(),
                'discount_total' => (float) $cart->get_discount_total(),
                'shipping_total' => (float) $cart->get_shipping_total(),
                'shipping_tax'   => (float) $cart->get_shipping_tax(),
                'shipping_pending' => (
                    $cart->needs_shipping()
                    && (
                        empty($billing['country'])
                        || empty($billing['postcode'])
                        || empty($billing['city'])
                        || !is_callable(array($cart, 'has_calculated_shipping'))
                        || !$cart->has_calculated_shipping()
                        || !is_callable(array($cart, 'get_shipping_methods'))
                        || !$cart->get_shipping_methods()
                    )
                ),
                'fee_total'      => (float) $cart->get_fee_total(),
                'total_tax'      => (float) $cart->get_total_tax(),
                'total'          => (float) $cart->get_total('edit'),
                'base_total'     => max(0, (float) $cart->get_total('edit') - (float) $cart->get_total_tax()),
            ),
        );

        return apply_filters('seo_facturas_quote_snapshot', $snapshot, $cart, $buyer, $kind);
    }

    /**
     * Comparativa solicitada durante esta sesion.
     *
     * Los IDs llegan desde sessionStorage mediante el formulario del documento.
     * No se genera comparativa si el usuario no la abrio previamente.
     */
    private static function comparison_from_request($raw) {
        if ('' === $raw) {
            return array();
        }

        $decoded = json_decode($raw, true);
        $ids = array_values(array_unique(array_filter(array_map('absint', is_array($decoded) ? $decoded : array()))));
        $ids = array_slice($ids, 0, 6);
        if (count($ids) < 2 || !function_exists('wc_get_product')) {
            return array();
        }

        $products = array();
        foreach ($ids as $id) {
            $product = wc_get_product($id);
            if (!$product || !$product->is_visible()) {
                continue;
            }

            $commercial = class_exists('SEO_Facturas_Snapshot')
                ? SEO_Facturas_Snapshot::product_commercial_snapshot($product)
                : array();

            if (!$commercial) {
                continue;
            }

            $products[] = array(
                'id'         => absint($id),
                'name'       => (string) ($commercial['name'] ?? $product->get_name()),
                'price'      => (string) ($commercial['price'] ?? ''),
                'attributes' => is_array($commercial['attributes'] ?? null) ? $commercial['attributes'] : array(),
            );
        }

        if (count($products) < 2) {
            return array();
        }

        $labels = array();
        foreach ($products as $product) {
            foreach (array_keys((array) $product['attributes']) as $label) {
                $label = trim(wp_strip_all_tags((string) $label));
                if ($label !== '' && !in_array($label, $labels, true)) {
                    $labels[] = $label;
                }
            }
        }
        $labels = array_slice($labels, 0, 18);

        $rows = array();
        $price_values = array();
        foreach ($products as $product) {
            $price_values[(string) $product['id']] = (string) $product['price'];
        }
        $rows[] = array('label' => 'Precio', 'values' => $price_values);

        foreach ($labels as $label) {
            $values = array();
            foreach ($products as $product) {
                $values[(string) $product['id']] = (string) (($product['attributes'][$label] ?? '') ?: '—');
            }
            $rows[] = array('label' => $label, 'values' => $values);
        }

        return array(
            'requested' => true,
            'products'  => $products,
            'rows'      => $rows,
        );
    }

    /**
     * El formulario recibe la provincia como texto libre; WooCommerce exige
     * el codigo ISO interno para sus zonas (p. ej., Huesca => HU).
     */
    private static function woocommerce_state_code($country, $state) {
        $country = strtoupper(trim((string) $country));
        $state = trim((string) $state);
        if ('' === $country || '' === $state || !function_exists('WC') || !WC() || !isset(WC()->countries)) {
            return $state;
        }
        $states = WC()->countries->get_states($country);
        if (!is_array($states) || array_key_exists($state, $states)) {
            return $state;
        }
        $needle = sanitize_title(remove_accents($state));
        foreach ($states as $code => $name) {
            if ($needle === sanitize_title(remove_accents((string) $name))) {
                return (string) $code;
            }
        }
        return $state;
    }

    private static function customer_address($customer, $kind) {
        $kind = ('shipping' === $kind) ? 'shipping' : 'billing';
        if (!$customer) {
            return array();
        }

        $getter = static function ($field) use ($customer, $kind) {
            $method = 'get_' . $kind . '_' . $field;
            return is_callable(array($customer, $method)) ? (string) $customer->{$method}() : '';
        };

        $country = $getter('country');
        $state = $getter('state');

        return array(
            'first_name'   => $getter('first_name'),
            'last_name'    => $getter('last_name'),
            'company'      => $getter('company'),
            'contact'      => '',
            'address_1'    => $getter('address_1'),
            'address_2'    => $getter('address_2'),
            'postcode'     => $getter('postcode'),
            'city'         => $getter('city'),
            'state'        => $state,
            'state_name'   => self::state_name($country, $state),
            'country'      => $country,
            'country_name' => self::country_name($country),
            'email'        => ('billing' === $kind) ? $getter('email') : '',
            'phone'        => ('billing' === $kind) ? $getter('phone') : '',
            'tax_id'       => '',
        );
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

    private static function state_name($country, $state) {
        if (function_exists('WC') && WC() && isset(WC()->countries)) {
            $states = WC()->countries->get_states($country);
            if (is_array($states) && isset($states[$state])) {
                return (string) $states[$state];
            }
        }
        return (string) $state;
    }

    private static function product_image_data_uri($product) {
        if (!$product || !is_a($product, 'WC_Product')) {
            return '';
        }

        $attachment_id = absint($product->get_image_id());
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
        if (!is_string($bytes) || '' === $bytes) {
            return '';
        }

        return 'data:' . $mime . ';base64,' . base64_encode($bytes);
    }

    private static function email_copy($to, $number, $binary) {
        $to = sanitize_email($to);
        if (!$to || !is_string($binary) || '' === $binary) {
            return false;
        }

        $tmp_base = wp_tempnam(sanitize_file_name($number));
        if (!$tmp_base) {
            return false;
        }
        $tmp = $tmp_base . '.pdf';
        wp_delete_file($tmp_base);

        $written = file_put_contents($tmp, $binary, LOCK_EX);
        if (false === $written || $written <= 0) {
            wp_delete_file($tmp);
            return false;
        }

        $subject = 'Presupuesto ' . $number;
        $message = '<p>Adjuntamos el presupuesto <strong>' . esc_html($number) . '</strong> generado desde el carrito.</p>';
        $sent = wp_mail(
            $to,
            apply_filters('seo_facturas_quote_email_subject', $subject, $number),
            apply_filters('seo_facturas_quote_email_message', $message, $number),
            array('Content-Type: text/html; charset=UTF-8'),
            array($tmp)
        );
        wp_delete_file($tmp);
        return (bool) $sent;
    }

    private static function check_rate_limit() {
        if (!function_exists('WC') || !WC() || !WC()->session) {
            return true;
        }

        $last = absint(WC()->session->get('seo_facturas_quote_last_ts', 0));
        if ($last && (time() - $last) < 3) {
            return new WP_Error('seo_facturas_quote_too_fast', 'Espera unos segundos antes de generar otro presupuesto.');
        }

        $bucket = wp_date('YmdH');
        $stored_bucket = (string) WC()->session->get('seo_facturas_quote_hour_bucket', '');
        $count = absint(WC()->session->get('seo_facturas_quote_hour_count', 0));
        if ($stored_bucket !== $bucket) {
            return true;
        }

        $limit = max(1, absint(SEO_Facturas_Settings::get('quote_hourly_limit', 20)));
        if ($count >= $limit) {
            return new WP_Error('seo_facturas_quote_limit', 'Se ha alcanzado temporalmente el limite de presupuestos de esta sesion.');
        }

        return true;
    }

    private static function mark_rate_limit() {
        if (!function_exists('WC') || !WC() || !WC()->session) {
            return;
        }

        $bucket = wp_date('YmdH');
        $stored_bucket = (string) WC()->session->get('seo_facturas_quote_hour_bucket', '');
        $count = absint(WC()->session->get('seo_facturas_quote_hour_count', 0));
        if ($stored_bucket !== $bucket) {
            $count = 0;
        }

        WC()->session->set('seo_facturas_quote_hour_bucket', $bucket);
        WC()->session->set('seo_facturas_quote_hour_count', $count + 1);
        WC()->session->set('seo_facturas_quote_last_ts', time());
    }

    private static function is_available() {
        return self::is_quote_available() || self::is_proforma_draft_available();
    }

    private static function is_quote_available() {
        return (bool) (
            SEO_Facturas_Settings::get('enabled', 0)
            && SEO_Facturas_Settings::get('quote_enabled', 0)
            && class_exists('WooCommerce')
        );
    }

    private static function is_proforma_draft_available() {
        return (bool) (
            SEO_Facturas_Settings::get('enabled', 0)
            && SEO_Facturas_Settings::get('proforma_enabled', 1)
            && class_exists('WooCommerce')
        );
    }

    private static function draft_numbering() {
        $issued_at = current_time('mysql');
        $timestamp = strtotime((string) $issued_at);
        $stamp = $timestamp ? wp_date('Ymd-His', $timestamp) : wp_date('Ymd-His');
        $token = strtoupper(wp_generate_password(5, false, false));

        return array(
            'type'      => SEO_Facturas_Documents::TYPE_QUOTE,
            'series'    => 'PRO-BORRADOR',
            'sequence'  => 0,
            'issued_at' => $issued_at,
            'number'    => 'PRO-BORRADOR-' . $stamp . '-' . $token,
        );
    }

    private static function cart_url() {
        if (function_exists('wc_get_cart_url')) {
            return wc_get_cart_url();
        }
        return home_url('/cart/');
    }

    private static function fail($message) {
        self::set_session_notice('seo_facturas_quote_error', sanitize_text_field((string) $message));
        self::set_session_notice('seo_facturas_quote_kind', self::$request_kind);
        if (!headers_sent()) {
            wp_safe_redirect(self::cart_url());
            exit;
        }
        wp_die(esc_html((string) $message));
    }

    private static function set_session_notice($key, $message) {
        if (function_exists('WC') && WC() && WC()->session) {
            WC()->session->set($key, $message);
        }
    }

    private static function pull_session_notice($key) {
        if (!function_exists('WC') || !WC() || !WC()->session) {
            return '';
        }
        $message = (string) WC()->session->get($key, '');
        if ('' !== $message) {
            WC()->session->__unset($key);
        }
        return $message;
    }
}
