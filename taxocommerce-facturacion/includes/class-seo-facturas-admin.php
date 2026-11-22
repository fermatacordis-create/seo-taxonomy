<?php
/**
 * Administracion del modulo documental.
 */

defined('ABSPATH') || exit;

final class SEO_Facturas_Admin {

    private static $initialized = false;

    public static function init() {
        if (self::$initialized || !is_admin()) {
            return;
        }
        self::$initialized = true;

        add_action('admin_menu', array(__CLASS__, 'register_page'), 30);
        add_filter('seo_tools_items', array(__CLASS__, 'add_tools_card'));
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_assets'));
        add_action('add_meta_boxes', array(__CLASS__, 'register_order_meta_box'));
        add_action('admin_notices', array(__CLASS__, 'render_notice'));

        add_action('admin_post_seo_facturas_generate', array(__CLASS__, 'handle_generate'));
        add_action('admin_post_seo_facturas_download', array(__CLASS__, 'handle_download'));
        add_action('admin_post_seo_facturas_email', array(__CLASS__, 'handle_email'));
    }

    public static function register_page() {
        add_submenu_page(
            null,
            __( 'Facturas y presupuestos', 'taxocommerce-facturacion' ),
            __( 'Facturas y presupuestos', 'taxocommerce-facturacion' ),
            'manage_options',
            'seo-facturas',
            array(__CLASS__, 'render_page')
        );
    }

    public static function add_tools_card($tools) {
        $tools = is_array($tools) ? $tools : array();
        foreach ($tools as $tool) {
            if (!empty($tool['page']) && 'taxocommerce-facturacion' === $tool['page']) {
                return $tools;
            }
        }

        $tools[] = array(
            'title' => 'Facturas y presupuestos',
            'icon'  => 'dashicons-media-spreadsheet',
            'page'  => 'taxocommerce-facturacion',
            'desc'  => 'Facturas, proformas y presupuestos PDF conectados a WooCommerce.',
        );

        return $tools;
    }

    public static function enqueue_assets($hook_suffix) {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Solo se lee el identificador de pantalla para cargar CSS; no se modifica estado.
        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
        if ('seo-facturas' !== $page) {
            return;
        }

        wp_enqueue_media();
        wp_enqueue_style(
            'seo-facturas-admin',
            SEO_FACTURAS_URL . 'assets/admin.css',
            array(),
            SEO_FACTURAS_VERSION
        );
    }

    public static function register_order_meta_box() {
        add_meta_box(
            'seo-facturas-order-box',
            'Facturacion',
            array(__CLASS__, 'render_order_meta_box'),
            'shop_order',
            'side',
            'default'
        );

        add_meta_box(
            'seo-facturas-order-box',
            'Facturacion',
            array(__CLASS__, 'render_order_meta_box'),
            'woocommerce_page_wc-orders',
            'side',
            'default'
        );
    }

    public static function render_page() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('No tienes permisos para acceder a esta pagina.', 'taxocommerce-facturacion'));
        }

        $tabs = array(
            'company'   => 'Datos empresa',
            'tax'       => 'Fiscalidad',
            'invoices'  => 'Facturas',
            'proformas' => 'Proformas',
            'quotes'    => 'Presupuestos',
            'documents' => 'Documentos',
            'manual'    => 'Manual',
            'autotest'  => __( 'Autocheck', 'taxocommerce-facturacion' ), 
        );

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Navegacion de pestañas de solo lectura, no requiere nonce.
        $tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'company';
        if (!isset($tabs[$tab])) {
            $tab = 'company';
        }
        ?>
        <div class="wrap seo-facturas-wrap">
            <h1>Facturas y presupuestos</h1>
            <p>WooCommerce conserva productos, carrito, pedidos, clientes, impuestos y pagos. Este modulo utiliza esos datos para generar documentos.</p>

            <?php self::render_system_status(); ?>

            <nav class="nav-tab-wrapper" aria-label="Configuracion de documentos">
                <?php foreach ($tabs as $key => $label) : ?>
                    <a class="nav-tab <?php echo $tab === $key ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url(admin_url('admin.php?page=seo-facturas&tab=' . $key)); ?>"><?php echo esc_html($label); ?></a>
                <?php endforeach; ?>
            </nav>

            <?php
            switch ($tab) {
                case 'manual':
                    TaxoCommerce_Billing_Manual::render();
                    break;
                case 'autotest':
                    TaxoCommerce_Billing_Autotest::render();
                    break;
                case 'tax':
                    self::render_tax_tab();
                    break;
                case 'invoices':
                    self::render_invoice_tab();
                    break;
                case 'proformas':
                    self::render_proforma_tab();
                    break;
                case 'quotes':
                    self::render_quote_tab();
                    break;
                case 'documents':
                    self::render_documents_tab();
                    break;
                case 'company':
                default:
                    self::render_company_tab();
                    break;
            }
            ?>
        </div>
        <?php
    }


    public static function render_notice() {
        if (!current_user_can('manage_options') && !current_user_can('manage_woocommerce')) {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Mensaje informativo escapado, sin operaciones de escritura.
        if (!empty($_GET['sf_error'])) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Texto mostrado con esc_html; sin efectos secundarios.
            $message = sanitize_text_field(wp_unslash($_GET['sf_error']));
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($message) . '</p></div>';
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Solo selecciona mensaje de un catalogo cerrado.
        if (!empty($_GET['sf_notice'])) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Identificador de mensaje de solo lectura.
            $notice = sanitize_key(wp_unslash($_GET['sf_notice']));
            $messages = array(
                'document_generated' => 'Documento generado correctamente.',
                'document_emailed'   => 'Documento enviado por email correctamente.',
                'rectification_issued' => __( 'Rectifying invoice issued. The original remains unchanged.', 'taxocommerce-facturacion' ),
                'rectification_emailed' => __( 'Rectifying invoice sent to customer.', 'taxocommerce-facturacion' ),
            );
            if (isset($messages[$notice])) {
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($messages[$notice]) . '</p></div>';
            }
        }
    }

    public static function render_order_meta_box($post_or_order) {
        if (!current_user_can('manage_woocommerce') && !current_user_can('manage_options')) {
            echo '<p>' . esc_html__( 'Sin permisos.', 'taxocommerce-facturacion' ) . '</p>';
            return;
        }

        $order = self::resolve_order_from_screen($post_or_order);
        if (!$order) {
            echo '<p>' . esc_html__( 'No se ha podido cargar el pedido.', 'taxocommerce-facturacion' ) . '</p>';
            return;
        }

        $invoice = SEO_Facturas_Documents::get_for_order($order->get_id(), SEO_Facturas_Documents::TYPE_INVOICE);
        $proforma = SEO_Facturas_Documents::get_for_order($order->get_id(), SEO_Facturas_Documents::TYPE_PROFORMA);

        echo '<p><strong>Pago WooCommerce:</strong> ' . esc_html($order->is_paid() ? 'Pagado' : 'Pendiente') . '</p>';

        if (SEO_Facturas_Settings::get('proforma_enabled', 1)) {
            self::render_order_document_row($order, $proforma, SEO_Facturas_Documents::TYPE_PROFORMA);
        }
        if (SEO_Facturas_Settings::get('invoice_enabled', 1)) {
            self::render_order_document_row($order, $invoice, SEO_Facturas_Documents::TYPE_INVOICE);
        }

        if (!SEO_Facturas_Settings::get('enabled', 0)) {
            echo '<p class="description">' . esc_html__( 'El sistema documental esta desactivado en Datos empresa.', 'taxocommerce-facturacion' ) . '</p>';
        }
    }

    public static function handle_generate() {
        self::require_order_capability();
        check_admin_referer('seo_facturas_generate');

        $order_id = absint($_GET['order_id'] ?? 0);
        $type = sanitize_key(wp_unslash($_GET['type'] ?? ''));

        $document = SEO_Facturas_Documents::issue_for_order($order_id, $type);
        if (is_wp_error($document)) {
            self::redirect_back(array('sf_error' => $document->get_error_message()), $order_id);
        }

        self::redirect_back(array('sf_notice' => 'document_generated'), $order_id);
    }

    public static function handle_download() {
        self::require_order_capability();

        $document_id = absint($_GET['document_id'] ?? 0);
        check_admin_referer('seo_facturas_download_' . $document_id);

        $document = SEO_Facturas_Documents::get($document_id);
        if (!$document) {
            wp_die('Documento no encontrado.');
        }

        if (empty($document->pdf_path) || !is_readable($document->pdf_path) || !self::allowed_pdf_path($document->pdf_path)) {
            wp_die('El PDF no esta disponible o su ruta no es valida.');
        }

        $filename = sanitize_file_name($document->document_number . '.pdf');
        nocache_headers();
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($document->pdf_path));
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- PDF validado dentro de rutas permitidas; streaming evita cargar el documento completo en memoria.
        readfile($document->pdf_path);
        exit;
    }

    public static function handle_email() {
        self::require_order_capability();

        $document_id = absint($_GET['document_id'] ?? 0);
        check_admin_referer('seo_facturas_email_' . $document_id);

        $document = SEO_Facturas_Documents::get($document_id);
        if (!$document) {
            self::redirect_back(array('sf_error' => 'Documento no encontrado.'));
        }

        if ('issued' !== $document->status || empty($document->pdf_path) || !is_readable($document->pdf_path)) {
            $order_id = absint($document->order_id);
            $document = SEO_Facturas_Documents::retry_pdf($document);
            if (is_wp_error($document)) {
                self::redirect_back(array('sf_error' => $document->get_error_message()), $order_id);
            }
        }

        $order = function_exists('wc_get_order') ? wc_get_order(absint($document->order_id)) : null;
        if (!$order) {
            self::redirect_back(array('sf_error' => 'No se encuentra el pedido WooCommerce.'), absint($document->order_id));
        }

        $to = sanitize_email($order->get_billing_email());
        if (!$to) {
            self::redirect_back(array('sf_error' => 'El pedido no tiene un email de facturacion valido.'), $order->get_id());
        }

        $label = SEO_Facturas_Documents::TYPE_INVOICE === $document->document_type ? 'Factura' : 'Factura proforma';
        $subject = $label . ' ' . $document->document_number . ' - pedido ' . $order->get_order_number();
        $message = '<p>Adjuntamos ' . esc_html(strtolower($label)) . ' <strong>' . esc_html($document->document_number) . '</strong> asociada a su pedido <strong>' . esc_html($order->get_order_number()) . '</strong>.</p><p>Gracias.</p>';

        $sent = wp_mail(
            $to,
            apply_filters('seo_facturas_manual_email_subject', $subject, $document, $order),
            apply_filters('seo_facturas_manual_email_message', $message, $document, $order),
            array('Content-Type: text/html; charset=UTF-8'),
            array($document->pdf_path)
        );

        if (!$sent) {
            self::redirect_back(array('sf_error' => 'WordPress no ha podido enviar el email.'), $order->get_id());
        }

        SEO_Facturas_Documents::mark_emailed($document->id);
        self::redirect_back(array('sf_notice' => 'document_emailed'), $order->get_id());
    }

    private static function render_company_tab() {
        $s = SEO_Facturas_Settings::company();
        $option = SEO_Facturas_Settings::COMPANY_OPTION;
        ?>
        <form method="post" action="options.php" class="seo-facturas-settings-form">
            <?php settings_fields('seo_facturas_company_group'); ?>
            <h2>Configuracion comun</h2>
            <p>Estos datos se utilizan en facturas, proformas y presupuestos. Se configuran una sola vez.</p>
            <table class="form-table" role="presentation">
                <?php self::checkbox_row($option, 'enabled', 'Sistema documental', $s['enabled'], 'Activar facturas, proformas y presupuestos configurados en sus respectivas pestañas.'); ?>
                <?php self::text_row($option, 'company_name', 'Razon social', $s['company_name']); ?>
                <?php self::text_row($option, 'company_trade_name', 'Nombre comercial', $s['company_trade_name']); ?>
                <?php self::text_row($option, 'company_tax_id', 'NIF / CIF', $s['company_tax_id']); ?>
                <?php self::text_row($option, 'company_address', 'Direccion', $s['company_address']); ?>
                <?php self::text_row($option, 'company_postcode', 'Codigo postal', $s['company_postcode']); ?>
                <?php self::text_row($option, 'company_city', 'Localidad', $s['company_city']); ?>
                <?php self::text_row($option, 'company_region', 'Provincia / region', $s['company_region']); ?>
                <?php self::country_row($option, $s['company_country']); ?>
                <?php self::text_row($option, 'company_phone', 'Telefono', $s['company_phone']); ?>
                <?php self::text_row($option, 'company_email', 'Email', $s['company_email'], 'email'); ?>
                <?php self::text_row($option, 'company_website', 'Web', $s['company_website'], 'url'); ?>
                <?php self::logo_row($option, absint($s['logo_id'])); ?>
                <?php self::textarea_row($option, 'footer_text', 'Pie comun', $s['footer_text'], 'Aparece al final de los documentos, salvo que la plantilla decida ocultarlo.'); ?>
                <?php self::text_row($option, 'customer_tax_meta_keys', 'Metas posibles para NIF/CIF cliente', $s['customer_tax_meta_keys'], 'text', 'Claves separadas por comas. WooCommerce no define un NIF/CIF estandar.'); ?>
            </table>
            <?php submit_button('Guardar datos de empresa'); ?>
        </form>
        <?php self::logo_script(); ?>
        <?php
    }

    private static function render_tax_tab() {
        $s = SEO_Facturas_Settings::taxation();
        $option = SEO_Facturas_Settings::TAX_OPTION;
        $woo_tax_enabled = 'yes' === get_option('woocommerce_calc_taxes', 'no');
        $woo_prices_include_tax = 'yes' === get_option('woocommerce_prices_include_tax', 'no');
        $woo_tax_display_shop = (string) get_option('woocommerce_tax_display_shop', 'excl');
        $woo_tax_display_cart = (string) get_option('woocommerce_tax_display_cart', 'excl');
        ?>
        <form method="post" action="options.php" class="seo-facturas-settings-form">
            <?php settings_fields('seo_facturas_tax_group'); ?>
            <h2>Fiscalidad de ventas en España</h2>
            <p>Estas reglas se aplican al cálculo fiscal de WooCommerce. Presupuestos, proformas y facturas copian después los importes calculados en el carrito o pedido, por lo que no existe un segundo cálculo distinto en el PDF.</p>

            <div class="notice notice-info inline"><p><strong>Importante:</strong> Península y Baleares usan <strong>IVA</strong>. Canarias se etiqueta como <strong>IGIC</strong> y Ceuta/Melilla como <strong>IPSI</strong> (configura el tipo aplicable; no es un catálogo oficial completo de tipos). El B2B UE (VIES) es opcional y queda fuera de Canarias/Ceuta/Melilla.</p></div>

            <table class="form-table" role="presentation">
                <?php self::checkbox_row($option, 'tax_manager_enabled', 'Gestión fiscal desde Facturación', $s['tax_manager_enabled'], 'Activar estas reglas y utilizarlas en los cálculos de WooCommerce.'); ?>
                <?php self::checkbox_row($option, 'tax_prices_include_tax', 'Los precios del catálogo ya incluyen impuestos', $s['tax_prices_include_tax'], 'Actívalo si el PVP guardado en WooCommerce es el precio final con impuesto. Evita que WooCommerce vuelva a sumarlo sobre ese mismo PVP.'); ?>
                <?php self::checkbox_row($option, 'tax_restrict_to_spain', 'Vender solo en España', $s['tax_restrict_to_spain'], 'Limitar los países de facturación y envío de WooCommerce a España mientras este gestor esté activo. Desactívalo si usas B2B UE.'); ?>
                <?php self::checkbox_row($option, 'tax_shipping', 'Impuesto sobre transporte', $s['tax_shipping'], 'Aplicar al transporte la misma tasa fiscal de la zona cuando WooCommerce calcule los portes.'); ?>
            </table>

            <h3>Tipos por localización</h3>
            <table class="widefat striped" style="max-width:760px">
                <thead><tr><th>Zona</th><th>Impuesto</th><th>Tipo %</th><th>Detección</th></tr></thead>
                <tbody>
                    <tr><td><strong>Península</strong></td><td>IVA</td><td><?php self::decimal_input($option, 'tax_peninsula_rate', $s['tax_peninsula_rate']); ?> %</td><td>España, excepto las zonas especiales indicadas abajo.</td></tr>
                    <tr><td><strong>Islas Baleares</strong></td><td>IVA</td><td><?php self::decimal_input($option, 'tax_baleares_rate', $s['tax_baleares_rate']); ?> %</td><td>Provincia PM o código postal 07.</td></tr>
                    <tr><td><strong>Canarias</strong></td><td>IGIC</td><td><?php self::decimal_input($option, 'tax_canarias_rate', $s['tax_canarias_rate']); ?> %</td><td>Las Palmas / Santa Cruz de Tenerife o códigos 35 / 38. Fuera del IVA UE.</td></tr>
                    <tr><td><strong>Ceuta</strong></td><td>IPSI</td><td><?php self::decimal_input($option, 'tax_ceuta_rate', $s['tax_ceuta_rate']); ?> %</td><td>Provincia CE o código postal 51. Fuera del IVA UE.</td></tr>
                    <tr><td><strong>Melilla</strong></td><td>IPSI</td><td><?php self::decimal_input($option, 'tax_melilla_rate', $s['tax_melilla_rate']); ?> %</td><td>Provincia ML o código postal 52. Fuera del IVA UE.</td></tr>
                </tbody>
            </table>

            <h3>B2B intracomunitario (VIES)</h3>
            <table class="form-table" role="presentation">
                <?php self::checkbox_row($option, 'tax_eu_b2b_enabled', 'Habilitar B2B UE con VIES', $s['tax_eu_b2b_enabled'] ?? 0, 'Permite inversión del sujeto pasivo (IVA 0 %) para empresas de otro Estado UE con NIF-IVA válido. Requiere desactivar «Vender solo en España» para esos destinos.'); ?>
                <?php self::checkbox_row($option, 'tax_eu_block_if_vies_down', 'Bloquear pedido si VIES no responde', $s['tax_eu_block_if_vies_down'] ?? 1, 'Recomendado: no emitir ISP si la validación no puede completarse.'); ?>
                <tr>
                    <th scope="row"><label for="sf-tax_eu_seller_vat">NIF-IVA del emisor</label></th>
                    <td><input type="text" class="regular-text" id="sf-tax_eu_seller_vat" name="<?php echo esc_attr($option); ?>[tax_eu_seller_vat]" value="<?php echo esc_attr($s['tax_eu_seller_vat'] ?? ''); ?>" placeholder="ESB00000000"></td>
                </tr>
                <?php self::textarea_row($option, 'tax_eu_reverse_charge_note', 'Mención legal ISP', $s['tax_eu_reverse_charge_note'] ?? '', 'Se añade al PDF cuando aplica inversión del sujeto pasivo.'); ?>
            </table>

            <h3>Catálogo IGIC (Canarias)</h3>
            <p class="description">Asigna la clase fiscal WooCommerce del producto (<code>igic-general</code>, <code>igic-reducido</code>, …). Sin clase específica se aplica el tipo general. Porcentajes orientativos y editables.</p>
            <table class="form-table" role="presentation">
                <?php self::checkbox_row($option, 'tax_special_rates_enabled', 'Usar catálogo completo IGIC/IPSI', $s['tax_special_rates_enabled'] ?? 1, 'Crea clases fiscales WooCommerce y tasas por tipo. Desactívalo solo si prefieres un único % por zona.'); ?>
            </table>
            <table class="widefat striped" style="max-width:760px">
                <thead><tr><th>Tipo</th><th>Clase WC</th><th>%</th></tr></thead>
                <tbody>
                    <tr><td>Tipo cero</td><td><code>igic-cero</code></td><td><?php self::decimal_input($option, 'tax_igic_cero', $s['tax_igic_cero'] ?? 0); ?> %</td></tr>
                    <tr><td>Reducido</td><td><code>igic-reducido</code></td><td><?php self::decimal_input($option, 'tax_igic_reducido', $s['tax_igic_reducido'] ?? 3); ?> %</td></tr>
                    <tr><td>General</td><td><code>igic-general</code> / estándar</td><td><?php self::decimal_input($option, 'tax_igic_general', $s['tax_igic_general'] ?? 7); ?> %</td></tr>
                    <tr><td>Incrementado</td><td><code>igic-incrementado</code></td><td><?php self::decimal_input($option, 'tax_igic_incrementado', $s['tax_igic_incrementado'] ?? 9.5); ?> %</td></tr>
                    <tr><td>Especial 15%</td><td><code>igic-especial-15</code></td><td><?php self::decimal_input($option, 'tax_igic_especial_15', $s['tax_igic_especial_15'] ?? 15); ?> %</td></tr>
                    <tr><td>Especial 20%</td><td><code>igic-especial-20</code></td><td><?php self::decimal_input($option, 'tax_igic_especial_20', $s['tax_igic_especial_20'] ?? 20); ?> %</td></tr>
                </tbody>
            </table>

            <h3>Catálogo IPSI (Ceuta)</h3>
            <table class="widefat striped" style="max-width:760px">
                <thead><tr><th>Tipo</th><th>Clase WC</th><th>%</th></tr></thead>
                <tbody>
                    <tr><td>Cero</td><td><code>ipsi-cero</code></td><td><?php self::decimal_input($option, 'tax_ipsi_ceuta_cero', $s['tax_ipsi_ceuta_cero'] ?? 0); ?> %</td></tr>
                    <tr><td>0,5%</td><td><code>ipsi-05</code></td><td><?php self::decimal_input($option, 'tax_ipsi_ceuta_r05', $s['tax_ipsi_ceuta_r05'] ?? 0.5); ?> %</td></tr>
                    <tr><td>1%</td><td><code>ipsi-1</code></td><td><?php self::decimal_input($option, 'tax_ipsi_ceuta_r1', $s['tax_ipsi_ceuta_r1'] ?? 1); ?> %</td></tr>
                    <tr><td>2%</td><td><code>ipsi-2</code></td><td><?php self::decimal_input($option, 'tax_ipsi_ceuta_r2', $s['tax_ipsi_ceuta_r2'] ?? 2); ?> %</td></tr>
                    <tr><td>4%</td><td><code>ipsi-4</code></td><td><?php self::decimal_input($option, 'tax_ipsi_ceuta_r4', $s['tax_ipsi_ceuta_r4'] ?? 4); ?> %</td></tr>
                    <tr><td>8%</td><td><code>ipsi-8</code></td><td><?php self::decimal_input($option, 'tax_ipsi_ceuta_r8', $s['tax_ipsi_ceuta_r8'] ?? 8); ?> %</td></tr>
                    <tr><td>General 10%</td><td><code>ipsi-general</code> / estándar</td><td><?php self::decimal_input($option, 'tax_ipsi_ceuta_general', $s['tax_ipsi_ceuta_general'] ?? 10); ?> %</td></tr>
                </tbody>
            </table>

            <h3>Catálogo IPSI (Melilla)</h3>
            <table class="widefat striped" style="max-width:760px">
                <thead><tr><th>Tipo</th><th>Clase WC</th><th>%</th></tr></thead>
                <tbody>
                    <tr><td>Cero</td><td><code>ipsi-cero</code></td><td><?php self::decimal_input($option, 'tax_ipsi_melilla_cero', $s['tax_ipsi_melilla_cero'] ?? 0); ?> %</td></tr>
                    <tr><td>0,5%</td><td><code>ipsi-05</code></td><td><?php self::decimal_input($option, 'tax_ipsi_melilla_r05', $s['tax_ipsi_melilla_r05'] ?? 0.5); ?> %</td></tr>
                    <tr><td>1%</td><td><code>ipsi-1</code></td><td><?php self::decimal_input($option, 'tax_ipsi_melilla_r1', $s['tax_ipsi_melilla_r1'] ?? 1); ?> %</td></tr>
                    <tr><td>2%</td><td><code>ipsi-2</code></td><td><?php self::decimal_input($option, 'tax_ipsi_melilla_r2', $s['tax_ipsi_melilla_r2'] ?? 2); ?> %</td></tr>
                    <tr><td>4%</td><td><code>ipsi-4</code></td><td><?php self::decimal_input($option, 'tax_ipsi_melilla_r4', $s['tax_ipsi_melilla_r4'] ?? 4); ?> %</td></tr>
                    <tr><td>8%</td><td><code>ipsi-8</code></td><td><?php self::decimal_input($option, 'tax_ipsi_melilla_r8', $s['tax_ipsi_melilla_r8'] ?? 8); ?> %</td></tr>
                    <tr><td>General 10%</td><td><code>ipsi-general</code> / estándar</td><td><?php self::decimal_input($option, 'tax_ipsi_melilla_general', $s['tax_ipsi_melilla_general'] ?? 10); ?> %</td></tr>
                </tbody>
            </table>

            <h3>Nota para destinos especiales (IGIC / IPSI)</h3>
            <table class="form-table" role="presentation">
                <?php self::textarea_row($option, 'tax_special_note', 'Canarias, Ceuta y Melilla', $s['tax_special_note'], 'Se añade a los documentos de estos destinos. No se mezclan con el flujo VIES/ISP.'); ?>
            </table>

            <p class="description">Estado actual de impuestos WooCommerce: <strong><?php echo esc_html($woo_tax_enabled ? 'activados' : 'desactivados'); ?></strong>. Precios introducidos con IVA: <strong><?php echo esc_html($woo_prices_include_tax ? 'sí' : 'no'); ?></strong>. Mostrar precios en tienda: <strong><?php echo esc_html('incl' === $woo_tax_display_shop ? 'con IVA' : 'sin IVA'); ?></strong>. Mostrar precios en carrito/checkout: <strong><?php echo esc_html('incl' === $woo_tax_display_cart ? 'con IVA' : 'sin IVA'); ?></strong>.</p>
            <p class="description"><strong>Regla del proyecto:</strong> si el catálogo guarda PVP final con IVA, mantén activada «Los precios del catálogo ya incluyen IVA». Esta regla se aplica aunque «Gestión fiscal desde Facturación» esté desactivada. Si se desactiva, WooCommerce tratará el precio guardado como base imponible y añadirá IVA encima.</p>
            <p class="description">No se modifican facturas o proformas ya emitidas. Sus snapshots permanecen inmutables.</p>
            <?php submit_button('Guardar fiscalidad'); ?>
        </form>
        <?php
    }

    private static function render_invoice_tab() {
        $s = SEO_Facturas_Settings::invoice();
        $option = SEO_Facturas_Settings::INVOICE_OPTION;
        ?>
        <form method="post" action="options.php" class="seo-facturas-settings-form">
            <?php settings_fields('seo_facturas_invoice_group'); ?>
            <h2>Facturas</h2>
            <p>La factura se emite a partir de un pedido que WooCommerce considera pagado.</p>
            <table class="form-table" role="presentation">
                <?php self::checkbox_row($option, 'invoice_enabled', 'Facturas activas', $s['invoice_enabled'], 'Permitir emision de facturas.'); ?>
                <?php self::text_row($option, 'invoice_title', 'Titulo del PDF', $s['invoice_title']); ?>
                <?php self::text_row($option, 'invoice_series', 'Serie', $s['invoice_series']); ?>
                <?php self::number_row($option, 'invoice_padding', 'Digitos de secuencia', $s['invoice_padding'], 3, 10); ?>
                <?php self::checkbox_row($option, 'auto_invoice', 'Generacion automatica', $s['auto_invoice'], 'Emitir cuando WooCommerce confirme el pago o el pedido entre en un estado pagado.'); ?>
                <?php self::checkbox_row($option, 'invoice_attach_to_woo_emails', 'Adjuntar a emails WooCommerce', $s['invoice_attach_to_woo_emails'], 'Adjuntar el PDF a los emails configurados abajo.'); ?>
                <?php self::text_row($option, 'invoice_email_ids', 'IDs de email WooCommerce', $s['invoice_email_ids'], 'text', 'Separados por comas. Ej.: customer_processing_order,customer_completed_order'); ?>
                <?php self::checkbox_row($option, 'invoice_show_order_reference', 'Referencia del pedido', $s['invoice_show_order_reference'], 'Mostrar pedido WooCommerce y fecha del pedido.'); ?>
                <?php self::checkbox_row($option, 'invoice_show_payment_method', 'Metodo de pago', $s['invoice_show_payment_method'], 'Mostrar el metodo de pago en el PDF.'); ?>
                <?php self::checkbox_row($option, 'invoice_show_sku', 'Referencia interna del producto', $s['invoice_show_sku'], 'Mostrar el ID interno de WordPress del producto. El SKU del proveedor no se muestra al cliente.'); ?>
                <?php self::textarea_row($option, 'invoice_footer_text', 'Pie exclusivo de factura', $s['invoice_footer_text'], 'Se muestra ademas del pie comun.'); ?>
            </table>
            <?php submit_button('Guardar facturas'); ?>
        </form>
        <?php
    }

    private static function render_proforma_tab() {
        $s = SEO_Facturas_Settings::proforma();
        $option = SEO_Facturas_Settings::PROFORMA_OPTION;
        ?>
        <form method="post" action="options.php" class="seo-facturas-settings-form">
            <?php settings_fields('seo_facturas_proforma_group'); ?>
            <h2>Facturas proforma</h2>
            <p>La proforma corresponde a un pedido existente todavia no pagado. No sustituye a la factura fiscal.</p>
            <table class="form-table" role="presentation">
                <?php self::checkbox_row($option, 'proforma_enabled', 'Proformas activas', $s['proforma_enabled'], 'Permitir generacion de facturas proforma.'); ?>
                <?php self::text_row($option, 'proforma_title', 'Titulo del PDF', $s['proforma_title']); ?>
                <?php self::text_row($option, 'proforma_series', 'Serie', $s['proforma_series']); ?>
                <?php self::number_row($option, 'proforma_padding', 'Digitos de secuencia', $s['proforma_padding'], 3, 10); ?>
                <?php self::checkbox_row($option, 'auto_proforma', 'Generacion automatica', $s['auto_proforma'], 'Emitir al entrar en cualquiera de los estados configurados.'); ?>
                <?php self::text_row($option, 'proforma_order_statuses', 'Estados WooCommerce', $s['proforma_order_statuses'], 'text', 'Slugs separados por comas. Por defecto: on-hold'); ?>
                <?php self::checkbox_row($option, 'proforma_attach_to_woo_emails', 'Adjuntar a emails WooCommerce', $s['proforma_attach_to_woo_emails'], 'Adjuntar el PDF a los emails configurados abajo.'); ?>
                <?php self::text_row($option, 'proforma_email_ids', 'IDs de email WooCommerce', $s['proforma_email_ids'], 'text', 'Por defecto: customer_on_hold_order'); ?>
                <?php self::checkbox_row($option, 'proforma_show_order_reference', 'Referencia del pedido', $s['proforma_show_order_reference'], 'Mostrar pedido WooCommerce y fecha del pedido.'); ?>
                <?php self::checkbox_row($option, 'proforma_show_payment_method', 'Metodo de pago', $s['proforma_show_payment_method'], 'Mostrar el metodo de pago seleccionado.'); ?>
                <?php self::checkbox_row($option, 'proforma_show_sku', 'Referencia interna del producto', $s['proforma_show_sku'], 'Mostrar el ID interno de WordPress del producto. El SKU del proveedor no se muestra al cliente.'); ?>
                <?php self::checkbox_row($option, 'proforma_show_payment_info', 'Instrucciones de pago', $s['proforma_show_payment_info'], 'Incluir datos para transferencia/Bizum en la proforma.'); ?>
                <?php self::text_row($option, 'proforma_beneficiary', 'Beneficiario', $s['proforma_beneficiary']); ?>
                <?php self::text_row($option, 'proforma_iban', 'IBAN', $s['proforma_iban']); ?>
                <?php self::text_row($option, 'proforma_bizum', 'Bizum', $s['proforma_bizum']); ?>
                <?php self::textarea_row($option, 'proforma_payment_instructions', 'Texto de pago', $s['proforma_payment_instructions'], 'Ej.: indique como concepto el numero de pedido.'); ?>
                <?php self::textarea_row($option, 'proforma_footer_text', 'Pie exclusivo de proforma', $s['proforma_footer_text'], 'Se muestra ademas del pie comun.'); ?>
            </table>
            <?php submit_button('Guardar proformas'); ?>
        </form>
        <?php
    }

    private static function render_quote_tab() {
        $s = SEO_Facturas_Settings::quote();
        $option = SEO_Facturas_Settings::QUOTE_OPTION;
        $engine = SEO_Facturas_PDF::engine_status();
        ?>
        <form method="post" action="options.php" class="seo-facturas-settings-form">
            <?php settings_fields('seo_facturas_quote_group'); ?>
            <h2>Presupuestos desde el carrito</h2>
            <p>El cliente descarga un PDF a partir del carrito actual. No se crea pedido, cliente ni historial de presupuestos.</p>
            <table class="form-table" role="presentation">
                <?php self::checkbox_row($option, 'quote_enabled', 'Presupuestos activos', $s['quote_enabled'], 'Mostrar la opcion de presupuesto en el carrito.'); ?>
                <?php self::text_row($option, 'quote_title', 'Titulo del PDF', $s['quote_title']); ?>
                <?php self::text_row($option, 'quote_button_text', 'Texto del boton', $s['quote_button_text']); ?>
                <?php self::text_row($option, 'quote_series', 'Serie', $s['quote_series']); ?>
                <?php self::number_row($option, 'quote_padding', 'Digitos de secuencia', $s['quote_padding'], 3, 10); ?>
                <?php self::number_row($option, 'quote_validity_days', 'Validez (dias)', $s['quote_validity_days'], 1, 365); ?>
                <?php self::checkbox_row($option, 'quote_guest_allowed', 'Visitantes', $s['quote_guest_allowed'], 'Permitir presupuestos sin iniciar sesion.'); ?>
            </table>

            <h3>Datos que puede facilitar el comprador</h3>
            <table class="form-table" role="presentation">
                <?php self::checkbox_row($option, 'quote_ask_company', 'Empresa / nombre', $s['quote_ask_company'], 'Mostrar el campo.'); ?>
                <?php self::checkbox_row($option, 'quote_ask_tax_id', 'NIF / CIF', $s['quote_ask_tax_id'], 'Mostrar el campo.'); ?>
                <?php self::checkbox_row($option, 'quote_ask_contact', 'Persona de contacto', $s['quote_ask_contact'], 'Mostrar el campo.'); ?>
                <?php self::checkbox_row($option, 'quote_ask_email', 'Email', $s['quote_ask_email'], 'Mostrar el campo email.'); ?>
                <?php self::checkbox_row($option, 'quote_require_email', 'Email obligatorio', $s['quote_require_email'], 'No generar el presupuesto sin un email valido.'); ?>
            </table>

            <h3>Contenido del PDF</h3>
            <table class="form-table" role="presentation">
                <?php self::checkbox_row($option, 'quote_show_sku', 'Referencia interna del producto', $s['quote_show_sku'], 'Mostrar el ID interno de WordPress del producto. El SKU del proveedor no se muestra al cliente.'); ?>
                <?php self::checkbox_row($option, 'quote_show_tax', 'Impuestos', $s['quote_show_tax'], 'Desglosar base e impuestos.'); ?>
                <?php self::checkbox_row($option, 'quote_show_shipping', 'Transporte', $s['quote_show_shipping'], 'Mostrar transporte y destino utilizado por WooCommerce.'); ?>
                <?php self::checkbox_row($option, 'quote_show_discounts', 'Descuentos', $s['quote_show_discounts'], 'Mostrar descuentos aplicados al carrito.'); ?>
                <?php self::checkbox_row($option, 'quote_show_images', 'Imagen de producto', $s['quote_show_images'], 'Incluir miniaturas locales en el PDF.'); ?>
                <?php self::checkbox_row($option, 'quote_send_email_copy', 'Copia por email', $s['quote_send_email_copy'], 'Si el comprador facilita email, enviar tambien una copia. La descarga sigue siendo directa.'); ?>
                <?php self::number_row($option, 'quote_hourly_limit', 'Limite por sesion / hora', $s['quote_hourly_limit'], 1, 200); ?>
                <?php self::textarea_row($option, 'quote_terms_text', 'Condiciones del presupuesto', $s['quote_terms_text'], 'Texto de validez comercial, stock y ausencia de valor fiscal.'); ?>
                <?php self::textarea_row($option, 'quote_footer_text', 'Pie exclusivo de presupuesto', $s['quote_footer_text'], 'Se muestra ademas del pie comun.'); ?>
            </table>

            <p class="description">Motor PDF: <strong><?php echo esc_html($engine['label']); ?></strong>. Los presupuestos necesitan el motor PDF operativo porque se generan y descargan en el momento.</p>
            <p class="description">Compatibilidad: se inserta en el carrito clasico y tambien se anade al bloque WooCommerce Cart. Se incluye el shortcode <code>[seo_facturas_presupuesto]</code> como ubicacion manual de respaldo.</p>
            <?php submit_button('Guardar presupuestos'); ?>
        </form>
        <?php
    }

    private static function render_documents_tab() {
        $documents = SEO_Facturas_Documents::list_recent(150);
        ?>
        <h2>Documentos emitidos</h2>
        <p>Este registro contiene facturas y proformas vinculadas a pedidos WooCommerce. Los presupuestos de carrito son efimeros y no se almacenan.</p>
        <table class="widefat striped seo-facturas-table">
            <thead>
                <tr>
                    <th>Documento</th>
                    <th>Pedido Woo</th>
                    <th>Tipo</th>
                    <th>Fecha</th>
                    <th>Estado</th>
                    <th>Email manual</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$documents) : ?>
                <tr><td colspan="7">Todavia no hay documentos.</td></tr>
            <?php else : ?>
                <?php foreach ($documents as $document) : ?>
                    <tr>
                        <td><strong><?php echo esc_html($document->document_number); ?></strong></td>
                        <td><a href="<?php echo esc_url(SEO_Facturas_Documents::order_admin_url($document->order_id)); ?>">#<?php echo esc_html($document->order_id); ?></a></td>
                        <td><?php echo esc_html(self::type_label($document->document_type)); ?></td>
                        <td><?php echo esc_html($document->issued_at); ?></td>
                        <td><?php echo esc_html($document->status); ?><?php if (!empty($document->last_error)) : ?><br><small><?php echo esc_html($document->last_error); ?></small><?php endif; ?></td>
                        <td><?php echo esc_html($document->email_sent_at ?: '-'); ?></td>
                        <td><?php echo self::document_actions_html($document); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
        <?php SEO_Facturas_Rectifications::render_register(); ?>
        <?php
    }

    private static function render_system_status() {
        $engine = SEO_Facturas_PDF::engine_status();
        $master = (bool) SEO_Facturas_Settings::get('enabled', 0);
        $woo = class_exists('WooCommerce');
        ?>
        <div class="seo-facturas-status-grid">
            <div class="seo-facturas-status-card"><strong>Sistema documental</strong><br><?php echo esc_html($master ? 'Activo' : 'Desactivado'); ?></div>
            <div class="seo-facturas-status-card"><strong>WooCommerce</strong><br><?php echo esc_html($woo ? 'Disponible' : 'No disponible'); ?></div>
            <div class="seo-facturas-status-card"><strong>Motor PDF</strong><br><?php echo esc_html($engine['label']); ?></div>
        </div>
        <?php
    }

    private static function render_order_document_row($order, $document, $type) {
        $label = self::type_label($type);
        echo '<div class="seo-facturas-order-document"><strong>' . esc_html($label) . '</strong><br>';
        if ($document) {
            echo esc_html($document->document_number) . ' - ' . esc_html($document->status) . '<br>';
            echo self::document_actions_html($document); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        } else {
            $url = wp_nonce_url(
                admin_url('admin-post.php?action=seo_facturas_generate&order_id=' . absint($order->get_id()) . '&type=' . rawurlencode($type)),
                'seo_facturas_generate'
            );
            echo '<a class="button button-small" href="' . esc_url($url) . '">Generar</a>';
        }
        echo '</div>';
    }

    private static function document_actions_html($document) {
        if (!$document || empty($document->id)) {
            return '';
        }

        $parts = array();
        if (!empty($document->pdf_path) && is_readable($document->pdf_path)) {
            $download = wp_nonce_url(
                admin_url('admin-post.php?action=seo_facturas_download&document_id=' . absint($document->id)),
                'seo_facturas_download_' . absint($document->id)
            );
            $parts[] = '<a class="button button-small" href="' . esc_url($download) . '">PDF</a>';
        } else {
            $generate = wp_nonce_url(
                admin_url('admin-post.php?action=seo_facturas_generate&order_id=' . absint($document->order_id) . '&type=' . rawurlencode($document->document_type)),
                'seo_facturas_generate'
            );
            $parts[] = '<a class="button button-small" href="' . esc_url($generate) . '">Reintentar PDF</a>';
        }

        $email = wp_nonce_url(
            admin_url('admin-post.php?action=seo_facturas_email&document_id=' . absint($document->id)),
            'seo_facturas_email_' . absint($document->id)
        );
        $parts[] = '<a class="button button-small" href="' . esc_url($email) . '">Reenviar</a>';
        $parts[] = SEO_Facturas_Rectifications::render_action($document);

        return implode(' ', $parts);
    }

    private static function resolve_order_from_screen($post_or_order) {
        if (is_a($post_or_order, 'WC_Order')) {
            return $post_or_order;
        }
        if (is_object($post_or_order) && isset($post_or_order->ID) && function_exists('wc_get_order')) {
            return wc_get_order(absint($post_or_order->ID));
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Identificador de pedido de la pantalla actual, solo lectura.
        if (isset($_GET['id']) && function_exists('wc_get_order')) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Recupera el pedido, no lo modifica.
            return wc_get_order(absint($_GET['id']));
        }
        return null;
    }

    private static function type_label($type) {
        return SEO_Facturas_Documents::TYPE_INVOICE === $type ? 'Factura' : 'Proforma';
    }

    private static function require_order_capability() {
        if (!current_user_can('manage_woocommerce') && !current_user_can('manage_options')) {
            wp_die('No tienes permisos suficientes.');
        }
    }

    private static function redirect_back($args, $order_id = 0) {
        $args = is_array($args) ? $args : array();
        if ($order_id) {
            $url = SEO_Facturas_Documents::order_admin_url($order_id);
        } else {
            $url = admin_url('admin.php?page=taxocommerce-facturacion&tab=documents');
        }
        wp_safe_redirect(add_query_arg($args, $url));
        exit;
    }

    private static function allowed_pdf_path($path) {
        $uploads = wp_upload_dir(null, false);
        if (!empty($uploads['error']) || empty($uploads['basedir'])) {
            return false;
        }
        $base = realpath(trailingslashit($uploads['basedir']) . 'seo-facturas');
        $real = realpath((string) $path);
        return $base && $real && 0 === strpos($real, $base . DIRECTORY_SEPARATOR);
    }

    private static function checkbox_row($option, $key, $label, $value, $description = '') {
        ?>
        <tr>
            <th scope="row"><?php echo esc_html($label); ?></th>
            <td>
                <label><input type="checkbox" name="<?php echo esc_attr($option); ?>[<?php echo esc_attr($key); ?>]" value="1" <?php checked(1, $value); ?>> <?php echo esc_html($description); ?></label>
            </td>
        </tr>
        <?php
    }

    private static function text_row($option, $key, $label, $value, $type = 'text', $description = '') {
        ?>
        <tr>
            <th scope="row"><label for="sf-<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></label></th>
            <td>
                <input class="regular-text" type="<?php echo esc_attr($type); ?>" id="sf-<?php echo esc_attr($key); ?>" name="<?php echo esc_attr($option); ?>[<?php echo esc_attr($key); ?>]" value="<?php echo esc_attr($value); ?>">
                <?php if ($description) : ?><p class="description"><?php echo esc_html($description); ?></p><?php endif; ?>
            </td>
        </tr>
        <?php
    }

    private static function number_row($option, $key, $label, $value, $min, $max) {
        ?>
        <tr>
            <th scope="row"><label for="sf-<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></label></th>
            <td><input type="number" id="sf-<?php echo esc_attr($key); ?>" min="<?php echo esc_attr($min); ?>" max="<?php echo esc_attr($max); ?>" name="<?php echo esc_attr($option); ?>[<?php echo esc_attr($key); ?>]" value="<?php echo esc_attr($value); ?>"></td>
        </tr>
        <?php
    }

    private static function decimal_input($option, $key, $value) {
        ?>
        <input type="number" step="0.01" min="0" max="100" style="width:110px" id="sf-<?php echo esc_attr($key); ?>" name="<?php echo esc_attr($option); ?>[<?php echo esc_attr($key); ?>]" value="<?php echo esc_attr($value); ?>">
        <?php
    }

    private static function textarea_row($option, $key, $label, $value, $description = '') {
        ?>
        <tr>
            <th scope="row"><label for="sf-<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></label></th>
            <td>
                <textarea class="large-text" rows="4" id="sf-<?php echo esc_attr($key); ?>" name="<?php echo esc_attr($option); ?>[<?php echo esc_attr($key); ?>]"><?php echo esc_textarea($value); ?></textarea>
                <?php if ($description) : ?><p class="description"><?php echo esc_html($description); ?></p><?php endif; ?>
            </td>
        </tr>
        <?php
    }

    private static function country_row($option, $selected) {
        $countries = array('ES' => 'España');
        if (function_exists('WC') && WC() && isset(WC()->countries)) {
            $wc_countries = WC()->countries->get_countries();
            if (is_array($wc_countries) && $wc_countries) {
                $countries = $wc_countries;
            }
        }
        ?>
        <tr>
            <th scope="row"><label for="sf-company-country">Pais</label></th>
            <td><select id="sf-company-country" name="<?php echo esc_attr($option); ?>[company_country]">
                <?php foreach ($countries as $code => $name) : ?><option value="<?php echo esc_attr($code); ?>" <?php selected($selected, $code); ?>><?php echo esc_html($name); ?></option><?php endforeach; ?>
            </select></td>
        </tr>
        <?php
    }

    private static function logo_row($option, $logo_id) {
        $url = $logo_id ? wp_get_attachment_image_url($logo_id, 'medium') : '';
        ?>
        <tr>
            <th scope="row">Logo</th>
            <td>
                <input type="hidden" id="seo-facturas-logo-id" name="<?php echo esc_attr($option); ?>[logo_id]" value="<?php echo esc_attr($logo_id); ?>">
                <div id="seo-facturas-logo-preview"><?php if ($url) : ?><img src="<?php echo esc_url($url); ?>" alt="" style="max-width:180px;max-height:80px;"><?php endif; ?></div>
                <p><button type="button" class="button" id="seo-facturas-select-logo">Seleccionar logo</button></p>
            </td>
        </tr>
        <?php
    }

    private static function logo_script() {
        ?>
        <script>
        document.addEventListener('DOMContentLoaded', function () {
            var button = document.getElementById('seo-facturas-select-logo');
            var input = document.getElementById('seo-facturas-logo-id');
            var preview = document.getElementById('seo-facturas-logo-preview');
            if (!button || !input || typeof wp === 'undefined' || !wp.media) return;
            button.addEventListener('click', function (event) {
                event.preventDefault();
                var frame = wp.media({title: 'Seleccionar logo', multiple: false, library: {type: 'image'}});
                frame.on('select', function () {
                    var item = frame.state().get('selection').first().toJSON();
                    input.value = item.id || '';
                    if (preview && item.url) preview.innerHTML = '<img src="' + item.url + '" alt="" style="max-width:180px;max-height:80px;">';
                });
                frame.open();
            });
        });
        </script>
        <?php
    }
}
