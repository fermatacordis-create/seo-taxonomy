<?php
/** @var array $snapshot */
defined('ABSPATH') || exit;

$taxocommerce_billing_document = $snapshot['document'] ?? array();
$taxocommerce_billing_seller = $snapshot['seller'] ?? array();
$taxocommerce_billing_order = $snapshot['order'] ?? array();
$taxocommerce_billing_billing = $snapshot['billing'] ?? array();
$taxocommerce_billing_shipping = $snapshot['shipping'] ?? array();
$taxocommerce_billing_items = $snapshot['items'] ?? array();
$taxocommerce_billing_tax_lines = $snapshot['tax_lines'] ?? array();
$taxocommerce_billing_fiscal = $snapshot['fiscal'] ?? array();
$taxocommerce_billing_totals = $snapshot['totals'] ?? array();
$taxocommerce_billing_currency = (string) ($taxocommerce_billing_order['currency'] ?? 'EUR');
$taxocommerce_billing_type = sanitize_key((string) ($taxocommerce_billing_document['type'] ?? ''));
$taxocommerce_billing_is_invoice = SEO_Facturas_Documents::TYPE_INVOICE === $taxocommerce_billing_type;
$taxocommerce_billing_title = trim((string) ($taxocommerce_billing_document['title'] ?? ($taxocommerce_billing_is_invoice ? 'FACTURA' : 'FACTURA PROFORMA')));
$taxocommerce_billing_show_order_reference = !array_key_exists('show_order_reference', $taxocommerce_billing_document) || !empty($taxocommerce_billing_document['show_order_reference']);
$taxocommerce_billing_show_payment_method = !array_key_exists('show_payment_method', $taxocommerce_billing_document) || !empty($taxocommerce_billing_document['show_payment_method']);
$taxocommerce_billing_show_sku = !array_key_exists('show_sku', $taxocommerce_billing_document) || !empty($taxocommerce_billing_document['show_sku']);

$taxocommerce_billing_money = static function ($taxocommerce_billing_amount) use ($taxocommerce_billing_currency) {
    $taxocommerce_billing_amount = (float) $taxocommerce_billing_amount;
    if (function_exists('wc_price')) {
        $taxocommerce_billing_formatted = wc_price($taxocommerce_billing_amount, array('currency' => $taxocommerce_billing_currency));
        return html_entity_decode(wp_strip_all_tags($taxocommerce_billing_formatted), ENT_QUOTES, 'UTF-8');
    }
    return number_format($taxocommerce_billing_amount, 2, ',', '.') . ' ' . $taxocommerce_billing_currency;
};

$taxocommerce_billing_person_name = static function ($taxocommerce_billing_address) {
    $taxocommerce_billing_company = trim((string) ($taxocommerce_billing_address['company'] ?? ''));
    if ('' !== $taxocommerce_billing_company) {
        return $taxocommerce_billing_company;
    }
    return trim((string) ($taxocommerce_billing_address['first_name'] ?? '') . ' ' . (string) ($taxocommerce_billing_address['last_name'] ?? ''));
};

$taxocommerce_billing_address_lines = static function ($taxocommerce_billing_address) {
    $taxocommerce_billing_lines = array();
    foreach (array(
        trim((string) ($taxocommerce_billing_address['address_1'] ?? '')),
        trim((string) ($taxocommerce_billing_address['address_2'] ?? '')),
        trim((string) ($taxocommerce_billing_address['postcode'] ?? '') . ' ' . (string) ($taxocommerce_billing_address['city'] ?? '')),
        trim((string) ($taxocommerce_billing_address['state_name'] ?? $taxocommerce_billing_address['state'] ?? '')),
        trim((string) ($taxocommerce_billing_address['country_name'] ?? $taxocommerce_billing_address['country'] ?? '')),
    ) as $taxocommerce_billing_line) {
        if ('' !== $taxocommerce_billing_line) {
            $taxocommerce_billing_lines[] = $taxocommerce_billing_line;
        }
    }
    return $taxocommerce_billing_lines;
};

$taxocommerce_billing_issued_ts = strtotime((string) ($taxocommerce_billing_document['issued_at'] ?? ''));
$taxocommerce_billing_issued_date = $taxocommerce_billing_issued_ts ? wp_date(get_option('date_format', 'd/m/Y'), $taxocommerce_billing_issued_ts) : (string) ($taxocommerce_billing_document['issued_at'] ?? '');
$taxocommerce_billing_created_ts = strtotime((string) ($taxocommerce_billing_order['created_at'] ?? ''));
$taxocommerce_billing_order_date = $taxocommerce_billing_created_ts ? wp_date(get_option('date_format', 'd/m/Y'), $taxocommerce_billing_created_ts) : (string) ($taxocommerce_billing_order['created_at'] ?? '');
$taxocommerce_billing_document_footer = trim((string) ($taxocommerce_billing_document['footer_text'] ?? ''));
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<style>
    @page { margin: 26px 30px 34px; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #222; line-height: 1.35; }
    .header { width: 100%; margin-bottom: 16px; }
    .header td { vertical-align: middle; }
    .logo { max-width: 105px; max-height: 52px; }
    .brand { text-align: center; font-size: 15px; font-weight: 700; }
    .brand small { display: block; font-size: 9px; font-weight: 400; margin-top: 3px; }
    .contact { text-align: right; font-size: 10px; }
    h1 { font-size: 20px; margin: 3px 0 12px; color: #244f61; }
    h2 { font-size: 14px; margin: 18px 0 7px; color: #244f61; }
    table { border-collapse: collapse; width: 100%; }
    .parties td { width: 50%; border: 1px solid #555; vertical-align: top; padding: 7px; }
    .parties .head { background: #f2f2f2; font-weight: 700; padding-bottom: 4px; }
    .details td { padding: 3px 0; vertical-align: top; }
    .details td:first-child { width: 170px; font-weight: 700; }
    .products th, .products td { border: 1px solid #555; padding: 5px; vertical-align: top; }
    .products th { background: #f2f2f2; font-size: 9px; text-align: center; }
    .products .num { text-align: right; white-space: nowrap; }
    .products .qty { text-align: center; width: 55px; }
    .products .ref { width: 120px; }
    .totals { width: 56%; margin-left: auto; margin-top: 14px; }
    .totals td { border: 1px solid #666; padding: 5px 7px; }
    .totals td:last-child { text-align: right; white-space: nowrap; }
    .totals .grand td { font-weight: 700; font-size: 11px; }
    .warning { border: 2px solid #a33; color: #8c1d1d; padding: 8px; text-align: center; font-weight: 700; margin-bottom: 12px; }
    .payment-box { margin-top: 18px; border: 1px solid #9aa7ad; background: #f7f9fa; padding: 9px 11px; }
    .payment-box strong { color: #244f61; }
    .fiscal-note { margin-top: 14px; border: 1px solid #d6b45e; background: #fffaf0; padding: 8px 10px; }
    .commercial-sheet { margin-top:14px; border:1px solid #c9d1d6; page-break-inside:avoid; }
    .commercial-sheet td { vertical-align:top; padding:9px; }
    .commercial-sheet .media { width:120px; text-align:center; background:#fafafa; }
    .commercial-sheet .media img { max-width:105px; max-height:105px; }
    .commercial-sheet h3 { margin:0 0 5px; color:#244f61; font-size:13px; }
    .commercial-sheet p { margin:4px 0; }
    .commercial-meta { margin:6px 0 0; font-size:9px; color:#555; }
    .commercial-attrs { margin-top:7px; width:100%; }
    .commercial-attrs td { border-top:1px solid #e2e5e7; padding:3px 5px; font-size:8.8px; }
    .commercial-attrs td:first-child { width:34%; font-weight:700; color:#445; }
    .muted { color: #666; }
    .footer { margin-top: 26px; border-top: 1px solid #bbb; padding-top: 7px; text-align: center; font-size: 8.5px; color: #555; }
</style>
</head>
<body>

<table class="header">
    <tr>
        <td style="width:25%;">
            <?php if (!empty($taxocommerce_billing_seller['logo_data_uri'])) : ?>
                <img class="logo" src="<?php echo esc_attr($taxocommerce_billing_seller['logo_data_uri']); ?>" alt="">
            <?php endif; ?>
        </td>
        <td class="brand" style="width:50%;">
            <?php echo esc_html($taxocommerce_billing_seller['trade_name'] ?: ($taxocommerce_billing_seller['website'] ?? '')); ?>
            <small><?php echo esc_html($taxocommerce_billing_seller['email'] ?? ''); ?></small>
        </td>
        <td class="contact" style="width:25%;"><?php echo esc_html($taxocommerce_billing_seller['phone'] ?? ''); ?></td>
    </tr>
</table>

<h1><?php echo esc_html($taxocommerce_billing_title); ?></h1>

<?php if (!$taxocommerce_billing_is_invoice) : ?>
    <div class="warning">DOCUMENTO PROFORMA - SIN VALIDEZ FISCAL</div>
<?php endif; ?>

<table class="parties">
    <tr>
        <td>
            <div class="head">Vendido por:</div>
            <strong><?php echo esc_html($taxocommerce_billing_seller['name'] ?? ''); ?></strong><br>
            <?php if (!empty($taxocommerce_billing_seller['tax_id'])) : ?><?php echo esc_html($taxocommerce_billing_seller['tax_id']); ?><br><?php endif; ?>
            <?php if (!empty($taxocommerce_billing_seller['address'])) : ?><?php echo esc_html($taxocommerce_billing_seller['address']); ?><br><?php endif; ?>
            <?php if (trim((string) ($taxocommerce_billing_seller['postcode'] ?? '') . ' ' . (string) ($taxocommerce_billing_seller['city'] ?? ''))) : ?><?php echo esc_html(trim((string) ($taxocommerce_billing_seller['postcode'] ?? '') . ' ' . (string) ($taxocommerce_billing_seller['city'] ?? ''))); ?><br><?php endif; ?>
            <?php if (!empty($taxocommerce_billing_seller['region'])) : ?><?php echo esc_html($taxocommerce_billing_seller['region']); ?><br><?php endif; ?>
            <?php if (!empty($taxocommerce_billing_seller['country_name'] ?? $taxocommerce_billing_seller['country'])) : ?><?php echo esc_html($taxocommerce_billing_seller['country_name'] ?? $taxocommerce_billing_seller['country']); ?><br><?php endif; ?>
            <?php if (!empty($taxocommerce_billing_seller['phone'])) : ?>Tel.: <?php echo esc_html($taxocommerce_billing_seller['phone']); ?><br><?php endif; ?>
            <?php if (!empty($taxocommerce_billing_seller['email'])) : ?><?php echo esc_html($taxocommerce_billing_seller['email']); ?><?php endif; ?>
        </td>
        <td>
            <div class="head">Vendido a:</div>
            <strong><?php echo esc_html($taxocommerce_billing_person_name($taxocommerce_billing_billing)); ?></strong><br>
            <?php if (!empty($taxocommerce_billing_billing['tax_id'])) : ?><?php echo esc_html($taxocommerce_billing_billing['tax_id']); ?><br><?php endif; ?>
            <?php foreach ($taxocommerce_billing_address_lines($taxocommerce_billing_billing) as $taxocommerce_billing_line) : ?><?php echo esc_html($taxocommerce_billing_line); ?><br><?php endforeach; ?>
            <?php if (!empty($taxocommerce_billing_billing['email'])) : ?><?php echo esc_html($taxocommerce_billing_billing['email']); ?><br><?php endif; ?>
            <?php if (!empty($taxocommerce_billing_billing['phone'])) : ?><?php echo esc_html($taxocommerce_billing_billing['phone']); ?><?php endif; ?>
        </td>
    </tr>
</table>

<h2>Detalle del documento</h2>
<table class="details">
    <tr><td>Fecha de expedicion:</td><td><?php echo esc_html($taxocommerce_billing_issued_date); ?></td></tr>
    <tr><td>Numero:</td><td><strong><?php echo esc_html($taxocommerce_billing_document['number'] ?? ''); ?></strong></td></tr>
    <?php if ($taxocommerce_billing_show_order_reference) : ?>
        <tr><td>Pedido WooCommerce:</td><td>#<?php echo esc_html($taxocommerce_billing_order['number'] ?? $taxocommerce_billing_order['id'] ?? ''); ?></td></tr>
        <tr><td>Fecha del pedido:</td><td><?php echo esc_html($taxocommerce_billing_order_date); ?></td></tr>
    <?php endif; ?>
    <?php if ($taxocommerce_billing_show_payment_method) : ?>
        <tr><td>Forma de pago:</td><td><?php echo esc_html($taxocommerce_billing_order['payment_method_title'] ?? $taxocommerce_billing_order['payment_method'] ?? ''); ?></td></tr>
    <?php endif; ?>
    <tr><td>Base imponible total:</td><td><?php echo esc_html($taxocommerce_billing_money($taxocommerce_billing_totals['base_total'] ?? 0)); ?></td></tr>
    <tr><td>Impuestos:</td><td><?php echo esc_html($taxocommerce_billing_money($taxocommerce_billing_totals['total_tax'] ?? 0)); ?></td></tr>
    <tr><td>Total:</td><td><strong><?php echo esc_html($taxocommerce_billing_money($taxocommerce_billing_totals['total'] ?? 0)); ?></strong></td></tr>
</table>

<?php if (!empty(array_filter($taxocommerce_billing_shipping))) : ?>
<h2>Direccion de envio</h2>
<div>
    <strong><?php echo esc_html($taxocommerce_billing_person_name($taxocommerce_billing_shipping)); ?></strong><br>
    <?php foreach ($taxocommerce_billing_address_lines($taxocommerce_billing_shipping) as $taxocommerce_billing_line) : ?><?php echo esc_html($taxocommerce_billing_line); ?><br><?php endforeach; ?>
</div>
<?php endif; ?>

<h2>Detalle de los productos</h2>
<table class="products">
    <thead>
        <tr>
            <?php if ($taxocommerce_billing_show_sku) : ?><th class="ref">REFERENCIA</th><?php endif; ?>
            <th>CONCEPTO</th>
            <th class="qty">CANTIDAD</th>
            <th>PRECIO UNIDAD<br><span class="muted">sin impuestos</span></th>
            <th>SUBTOTAL<br><span class="muted">sin impuestos</span></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($taxocommerce_billing_items as $taxocommerce_billing_item) : ?>
        <tr>
            <?php if ($taxocommerce_billing_show_sku) : ?><td class="ref"><?php echo esc_html((string) ($taxocommerce_billing_item['object_id'] ?? $taxocommerce_billing_item['product_id'] ?? '')); ?></td><?php endif; ?>
            <td><?php echo esc_html($taxocommerce_billing_item['name'] ?? ''); ?></td>
            <td class="qty"><?php echo esc_html($taxocommerce_billing_item['quantity'] ?? 0); ?></td>
            <td class="num"><?php echo esc_html($taxocommerce_billing_money($taxocommerce_billing_item['unit_net'] ?? 0)); ?></td>
            <td class="num"><?php echo esc_html($taxocommerce_billing_money($taxocommerce_billing_item['total'] ?? 0)); ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<table class="totals">
    <tr><td>Subtotal productos</td><td><?php echo esc_html($taxocommerce_billing_money($taxocommerce_billing_totals['subtotal_items'] ?? 0)); ?></td></tr>
    <?php if (!empty($taxocommerce_billing_totals['discount_total'])) : ?><tr><td>Descuentos</td><td>-<?php echo esc_html($taxocommerce_billing_money($taxocommerce_billing_totals['discount_total'])); ?></td></tr><?php endif; ?>
    <tr><td>Transporte</td><td><?php echo esc_html($taxocommerce_billing_money($taxocommerce_billing_totals['shipping_total'] ?? 0)); ?></td></tr>
    <?php if (!empty($taxocommerce_billing_totals['fee_total'])) : ?><tr><td>Otros cargos</td><td><?php echo esc_html($taxocommerce_billing_money($taxocommerce_billing_totals['fee_total'])); ?></td></tr><?php endif; ?>
    <tr><td>BASE IMPONIBLE</td><td><?php echo esc_html($taxocommerce_billing_money($taxocommerce_billing_totals['base_total'] ?? 0)); ?></td></tr>
    <?php if ($taxocommerce_billing_tax_lines) : ?>
        <?php foreach ($taxocommerce_billing_tax_lines as $taxocommerce_billing_tax) : ?>
            <?php $taxocommerce_billing_tax_amount = (float) ($taxocommerce_billing_tax['tax_total'] ?? 0) + (float) ($taxocommerce_billing_tax['shipping_tax_total'] ?? 0); ?>
            <?php
            $taxocommerce_billing_tax_label = (string) ($taxocommerce_billing_tax['label'] ?? 'IVA');
            $taxocommerce_billing_rate_percent = $taxocommerce_billing_tax['rate_percent'] ?? null;
            if (null !== $taxocommerce_billing_rate_percent && false === strpos($taxocommerce_billing_tax_label, '%')) {
                $taxocommerce_billing_tax_label .= ' ' . rtrim(rtrim(number_format((float) $taxocommerce_billing_rate_percent, 3, '.', ''), '0'), '.') . '%';
            }
            ?>
            <tr>
                <td><?php echo esc_html($taxocommerce_billing_tax_label); ?></td>
                <td><?php echo esc_html($taxocommerce_billing_money($taxocommerce_billing_tax_amount)); ?></td>
            </tr>
        <?php endforeach; ?>
    <?php else : ?>
        <?php $taxocommerce_billing_fallback_tax_label = !empty($taxocommerce_billing_fiscal['enabled']) && !empty($taxocommerce_billing_fiscal['label']) ? (string) $taxocommerce_billing_fiscal['label'] : 'IMPUESTOS'; ?>
        <tr><td><?php echo esc_html($taxocommerce_billing_fallback_tax_label); ?></td><td><?php echo esc_html($taxocommerce_billing_money($taxocommerce_billing_totals['total_tax'] ?? 0)); ?></td></tr>
    <?php endif; ?>
    <tr class="grand"><td><?php echo esc_html($taxocommerce_billing_is_invoice ? 'TOTAL FACTURA' : 'TOTAL PROFORMA'); ?></td><td><?php echo esc_html($taxocommerce_billing_money($taxocommerce_billing_totals['total'] ?? 0)); ?></td></tr>
</table>

<?php if (!$taxocommerce_billing_is_invoice) : ?>
    <?php
    $taxocommerce_billing_commercial_items = array_values(array_filter($taxocommerce_billing_items, static function ($taxocommerce_billing_item) {
        return !empty($taxocommerce_billing_item['commercial']) && is_array($taxocommerce_billing_item['commercial']);
    }));
    ?>
    <?php if ($taxocommerce_billing_commercial_items) : ?>
        <h2>Información de los productos</h2>
        <p class="muted">Ficha comercial conservada con la proforma para facilitar la revisión de la selección.</p>
        <?php foreach ($taxocommerce_billing_commercial_items as $taxocommerce_billing_item) : ?>
            <?php $taxocommerce_billing_commercial = $taxocommerce_billing_item['commercial']; ?>
            <table class="commercial-sheet">
                <tr>
                    <td class="media">
                        <?php if (!empty($taxocommerce_billing_commercial['image_data_uri'])) : ?>
                            <img src="<?php echo esc_attr($taxocommerce_billing_commercial['image_data_uri']); ?>" alt="">
                        <?php else : ?>
                            <span class="muted">Sin imagen disponible</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <h3><?php echo esc_html($taxocommerce_billing_item['name'] ?? ($taxocommerce_billing_commercial['name'] ?? 'Producto')); ?></h3>
                        <?php if (!empty($taxocommerce_billing_item['object_id']) || !empty($taxocommerce_billing_item['product_id'])) : ?><p><strong>Referencia:</strong> <?php echo esc_html((string) ($taxocommerce_billing_item['object_id'] ?? $taxocommerce_billing_item['product_id'])); ?></p><?php endif; ?>
                        <?php if (!empty($taxocommerce_billing_commercial['summary'])) : ?><p><strong>Resumen:</strong> <?php echo esc_html($taxocommerce_billing_commercial['summary']); ?></p><?php endif; ?>
                        <?php if (!empty($taxocommerce_billing_commercial['description']) && $taxocommerce_billing_commercial['description'] !== ($taxocommerce_billing_commercial['summary'] ?? '')) : ?>
                            <p><?php echo esc_html($taxocommerce_billing_commercial['description']); ?></p>
                        <?php endif; ?>
                        <?php if (!empty($taxocommerce_billing_commercial['categories'])) : ?>
                            <p class="commercial-meta"><strong>Categorías:</strong> <?php echo esc_html(implode(' · ', (array) $taxocommerce_billing_commercial['categories'])); ?></p>
                        <?php endif; ?>
                        <?php if (!empty($taxocommerce_billing_commercial['tags'])) : ?>
                            <p class="commercial-meta"><strong>Etiquetas:</strong> <?php echo esc_html(implode(' · ', (array) $taxocommerce_billing_commercial['tags'])); ?></p>
                        <?php endif; ?>
                        <?php if (!empty($taxocommerce_billing_commercial['attributes']) && is_array($taxocommerce_billing_commercial['attributes'])) : ?>
                            <table class="commercial-attrs">
                                <?php foreach ($taxocommerce_billing_commercial['attributes'] as $taxocommerce_billing_label => $taxocommerce_billing_value) : ?>
                                    <tr>
                                        <td><?php echo esc_html($taxocommerce_billing_label); ?></td>
                                        <td><?php echo esc_html($taxocommerce_billing_value); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </table>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
        <?php endforeach; ?>
    <?php endif; ?>
<?php endif; ?>

<?php if (!empty($taxocommerce_billing_fiscal['enabled']) && !empty($taxocommerce_billing_fiscal['note'])) : ?>
    <?php
    $taxocommerce_billing_fiscal_heading = (!empty($taxocommerce_billing_fiscal['regime']) && 'intra_eu_reverse_charge' === $taxocommerce_billing_fiscal['regime'])
        ? 'Inversión del sujeto pasivo (UE)'
        : 'Condiciones fiscales del destino';
    ?>
    <div class="fiscal-note"><strong><?php echo esc_html($taxocommerce_billing_fiscal_heading); ?>:</strong><br><?php echo nl2br(esc_html($taxocommerce_billing_fiscal['note'])); ?></div>
<?php endif; ?>

<?php if (!$taxocommerce_billing_is_invoice && !empty($taxocommerce_billing_document['show_payment_info'])) : ?>
    <div class="payment-box">
        <strong>Informacion de pago</strong><br>
        <?php if (!empty($taxocommerce_billing_document['beneficiary'])) : ?>Beneficiario: <?php echo esc_html($taxocommerce_billing_document['beneficiary']); ?><br><?php endif; ?>
        <?php if (!empty($taxocommerce_billing_document['iban'])) : ?>IBAN: <?php echo esc_html($taxocommerce_billing_document['iban']); ?><br><?php endif; ?>
        <?php if (!empty($taxocommerce_billing_document['bizum'])) : ?>Bizum: <?php echo esc_html($taxocommerce_billing_document['bizum']); ?><br><?php endif; ?>
        <?php if (!empty($taxocommerce_billing_document['payment_instructions'])) : ?><br><?php echo nl2br(esc_html($taxocommerce_billing_document['payment_instructions'])); ?><?php endif; ?>
    </div>
<?php endif; ?>

<div class="footer">
    <?php if ('' !== $taxocommerce_billing_document_footer) : ?><?php echo nl2br(esc_html($taxocommerce_billing_document_footer)); ?><br><?php endif; ?>
    <?php if (!empty($taxocommerce_billing_seller['footer_text'])) : ?><?php echo nl2br(esc_html($taxocommerce_billing_seller['footer_text'])); ?><br><?php endif; ?>
    <?php echo esc_html($taxocommerce_billing_seller['website'] ?? ''); ?>
    <?php if (!empty($taxocommerce_billing_seller['phone'])) : ?> &nbsp;|&nbsp; <?php echo esc_html($taxocommerce_billing_seller['phone']); ?><?php endif; ?>
</div>

</body>
</html>
