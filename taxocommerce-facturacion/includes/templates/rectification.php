<?php
/**
 * Rectifying invoice by substitution, with immutable original-reference data.
 *
 * Corrected lines come from the verified substitution snapshot and do not
 * change the original invoice, WooCommerce order or previous receipts.
 *
 * @var array $snapshot Verified immutable replacement snapshot.
 */
defined( 'ABSPATH' ) || exit;
$taxocommerce_billing_rect_doc = (array) ( $snapshot['document'] ?? array() );
$taxocommerce_billing_rect_order = (array) ( $snapshot['order'] ?? array() );
$taxocommerce_billing_rect_seller = (array) ( $snapshot['seller'] ?? array() );
$taxocommerce_billing_rect_buyer = (array) ( $snapshot['billing'] ?? array() );
$taxocommerce_billing_rect_rect = (array) ( $snapshot['rectification'] ?? array() );
$taxocommerce_billing_rect_totals = (array) ( $snapshot['totals'] ?? array() );
$taxocommerce_billing_rect_items = (array) ( $snapshot['rectification_items'] ?? array() );
$taxocommerce_billing_rect_currency = (string) ( $taxocommerce_billing_rect_order['currency'] ?? 'EUR' );
$taxocommerce_billing_rect_money = static function ( $value ) use ( $taxocommerce_billing_rect_currency ) {
    if ( function_exists( 'wc_price' ) ) {
        return html_entity_decode( wp_strip_all_tags( wc_price( (float) $value, array( 'currency' => $taxocommerce_billing_rect_currency ) ) ), ENT_QUOTES, 'UTF-8' );
    }
    return number_format( (float) $value, 2, ',', '.' ) . ' ' . $taxocommerce_billing_rect_currency;
};
$taxocommerce_billing_rect_buyer_name = trim( (string) ( $taxocommerce_billing_rect_buyer['company'] ?? '' ) );
if ( '' === $taxocommerce_billing_rect_buyer_name ) {
    $taxocommerce_billing_rect_buyer_name = trim( (string) ( $taxocommerce_billing_rect_buyer['first_name'] ?? '' ) . ' ' . (string) ( $taxocommerce_billing_rect_buyer['last_name'] ?? '' ) );
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<style>
    @page { margin: 34px 36px 42px; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color:#222; line-height:1.5; }
    h1 { color:#244f61; font-size:19px; margin:0 0 8px; }
    h2 { color:#244f61; font-size:12px; margin:15px 0 5px; }
    .small { color:#555; font-size:9px; }
    .parties { width:100%; border-collapse:collapse; }
    .parties td { width:50%; vertical-align:top; border:1px solid #aaa; padding:9px; }
    .block { border:1px solid #aaa; padding:8px 11px; margin:12px 0; }
    table.amounts { width:100%; border-collapse:collapse; margin-top:12px; }
    .amounts th, .amounts td { border:1px solid #aaa; padding:7px; }
    .amounts th { text-align:left; background:#f0f4f5; }
    .amounts td.num { text-align:right; white-space:nowrap; }
    .note { border-left:3px solid #244f61; background:#f5f7f8; padding:10px; margin:12px 0; }
    .footer { margin-top:18px; border-top:1px solid #ccc; color:#555; font-size:9px; padding-top:6px; }
</style>
</head>
<body>
<h1><?php echo esc_html__( 'RECTIFYING INVOICE — BY SUBSTITUTION', 'taxocommerce-facturacion' ); ?></h1>
<p><strong><?php echo esc_html__( 'Rectifying invoice number', 'taxocommerce-facturacion' ); ?>:</strong> <?php echo esc_html( $taxocommerce_billing_rect_doc['number'] ?? '' ); ?><br>
<strong><?php echo esc_html__( 'Issue date', 'taxocommerce-facturacion' ); ?>:</strong> <?php echo esc_html( $taxocommerce_billing_rect_doc['issued_at'] ?? '' ); ?><br>
<strong><?php echo esc_html__( 'Corrected original invoice', 'taxocommerce-facturacion' ); ?>:</strong> <?php echo esc_html( $taxocommerce_billing_rect_rect['original_number'] ?? '' ); ?><br>
<strong><?php echo esc_html__( 'Original invoice issue date', 'taxocommerce-facturacion' ); ?>:</strong> <?php echo esc_html( $taxocommerce_billing_rect_rect['original_issued_at'] ?? '' ); ?><br>
<strong><?php echo esc_html__( 'WooCommerce order reference', 'taxocommerce-facturacion' ); ?>:</strong> #<?php echo esc_html( $taxocommerce_billing_rect_order['number'] ?? $taxocommerce_billing_rect_order['id'] ?? '' ); ?></p>

<table class="parties"><tr>
<td><strong><?php echo esc_html__( 'Seller', 'taxocommerce-facturacion' ); ?></strong><br>
<?php echo esc_html( $taxocommerce_billing_rect_seller['name'] ?? '' ); ?><br>
<?php echo esc_html( $taxocommerce_billing_rect_seller['tax_id'] ?? '' ); ?><br>
<?php echo esc_html( $taxocommerce_billing_rect_seller['address'] ?? '' ); ?><br>
<?php echo esc_html( trim( (string) ( $taxocommerce_billing_rect_seller['postcode'] ?? '' ) . ' ' . (string) ( $taxocommerce_billing_rect_seller['city'] ?? '' ) ) ); ?><br>
<?php echo esc_html( $taxocommerce_billing_rect_seller['country_name'] ?? $taxocommerce_billing_rect_seller['country'] ?? '' ); ?></td>
<td><strong><?php echo esc_html__( 'Customer', 'taxocommerce-facturacion' ); ?></strong><br>
<?php echo esc_html( $taxocommerce_billing_rect_buyer_name ); ?><br>
<?php echo esc_html( $taxocommerce_billing_rect_buyer['tax_id'] ?? '' ); ?><br>
<?php echo esc_html( $taxocommerce_billing_rect_buyer['address_1'] ?? '' ); ?><br>
<?php echo esc_html( trim( (string) ( $taxocommerce_billing_rect_buyer['postcode'] ?? '' ) . ' ' . (string) ( $taxocommerce_billing_rect_buyer['city'] ?? '' ) ) ); ?><br>
<?php echo esc_html( $taxocommerce_billing_rect_buyer['country_name'] ?? $taxocommerce_billing_rect_buyer['country'] ?? '' ); ?></td>
</tr></table>

<h2><?php echo esc_html__( 'Corrected transaction', 'taxocommerce-facturacion' ); ?></h2>
<p class="small"><?php
echo esc_html( 'amounts' === (string) ( $taxocommerce_billing_rect_rect['mode'] ?? 'vat' )
    ? __( 'Product net amounts and VAT have been corrected; previous customer receipts are unchanged.', 'taxocommerce-facturacion' )
    : __( 'The original products and gross price are unchanged. Only the VAT breakdown has been corrected.', 'taxocommerce-facturacion' ) );
?></p>
<table class="amounts">
<thead><tr>
<th><?php echo esc_html__( 'Product', 'taxocommerce-facturacion' ); ?></th>
<th><?php echo esc_html__( 'Quantity', 'taxocommerce-facturacion' ); ?></th>
<th><?php echo esc_html__( 'Net unit price', 'taxocommerce-facturacion' ); ?></th>
<th><?php echo esc_html__( 'Net amount', 'taxocommerce-facturacion' ); ?></th>
<th><?php echo esc_html__( 'VAT', 'taxocommerce-facturacion' ); ?></th>
<th><?php echo esc_html__( 'Gross amount', 'taxocommerce-facturacion' ); ?></th>
</tr></thead><tbody>
<?php foreach ( $taxocommerce_billing_rect_items as $taxocommerce_billing_rect_item ) : ?>
<tr>
<td><?php echo esc_html( $taxocommerce_billing_rect_item['name'] ?? '' ); ?></td>
<td class="num"><?php echo esc_html( $taxocommerce_billing_rect_item['quantity'] ?? 0 ); ?></td>
<td class="num"><?php echo esc_html( number_format( (float) ( $taxocommerce_billing_rect_item['unit_net'] ?? 0 ), 4, ',', '.' ) . ' ' . $taxocommerce_billing_rect_currency ); ?></td>
<td class="num"><?php echo esc_html( $taxocommerce_billing_rect_money( $taxocommerce_billing_rect_item['base'] ?? 0 ) ); ?></td>
<td class="num"><?php echo esc_html( $taxocommerce_billing_rect_money( $taxocommerce_billing_rect_item['tax'] ?? 0 ) ); ?></td>
<td class="num"><?php echo esc_html( $taxocommerce_billing_rect_money( $taxocommerce_billing_rect_item['gross'] ?? 0 ) ); ?></td>
</tr>
<?php endforeach; ?>
</tbody></table>
<div class="note"><strong><?php echo esc_html__( 'Reason for correction', 'taxocommerce-facturacion' ); ?>:</strong>
<?php echo nl2br( esc_html( $taxocommerce_billing_rect_rect['reason'] ?? '' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- nl2br on escaped text. ?>
</div>
<table class="amounts">
<thead><tr>
<th><?php echo esc_html__( 'Amounts', 'taxocommerce-facturacion' ); ?></th>
<th><?php echo esc_html__( 'Original invoice', 'taxocommerce-facturacion' ); ?></th>
<th><?php echo esc_html__( 'Corrected invoice', 'taxocommerce-facturacion' ); ?></th>
<th><?php echo esc_html__( 'Difference', 'taxocommerce-facturacion' ); ?></th>
</tr></thead>
<tbody>
<?php foreach ( array(
    'base_total' => array( __( 'Taxable base', 'taxocommerce-facturacion' ), 'old_base' ),
    'total_tax' => array( __( 'VAT', 'taxocommerce-facturacion' ), 'old_tax' ),
    'total' => array( __( 'Total', 'taxocommerce-facturacion' ), 'old_total' ),
) as $taxocommerce_billing_rect_key => $taxocommerce_billing_rect_entry ) : ?>
    <?php $taxocommerce_billing_rect_old = (float) ( $taxocommerce_billing_rect_rect[ $taxocommerce_billing_rect_entry[1] ] ?? 0 ); $taxocommerce_billing_rect_new = (float) ( $taxocommerce_billing_rect_totals[ $taxocommerce_billing_rect_key ] ?? 0 ); ?>
    <tr><td><?php echo esc_html( $taxocommerce_billing_rect_entry[0] ); ?></td>
    <td class="num"><?php echo esc_html( $taxocommerce_billing_rect_money( $taxocommerce_billing_rect_old ) ); ?></td>
    <td class="num"><?php echo esc_html( $taxocommerce_billing_rect_money( $taxocommerce_billing_rect_new ) ); ?></td>
    <td class="num"><?php echo esc_html( $taxocommerce_billing_rect_money( $taxocommerce_billing_rect_new - $taxocommerce_billing_rect_old ) ); ?></td></tr>
<?php endforeach; ?>
</tbody></table>
<p><strong><?php echo esc_html__( 'Corrected VAT rate', 'taxocommerce-facturacion' ); ?>:</strong> <?php echo esc_html( number_format_i18n( (float) ( $taxocommerce_billing_rect_rect['rate'] ?? 0 ), 2 ) ); ?> %</p>
<?php $taxocommerce_billing_rect_delta = round( (float) ( $taxocommerce_billing_rect_totals['total'] ?? 0 ) - (float) ( $taxocommerce_billing_rect_rect['old_total'] ?? 0 ), 2 ); ?>
<p><strong><?php echo esc_html__( 'Increase or decrease in the invoiced total', 'taxocommerce-facturacion' ); ?>:</strong>
<?php echo esc_html( $taxocommerce_billing_rect_money( $taxocommerce_billing_rect_delta ) ); ?></p>
<p class="small"><?php echo esc_html__( 'This difference is not a recorded bank payment or refund. Verify the actual outstanding amount in Treasury.', 'taxocommerce-facturacion' ); ?></p>
<p class="footer"><?php echo esc_html__( 'This rectifying invoice must be kept together with the original invoice. It does not modify the WooCommerce order or the original receipt.', 'taxocommerce-facturacion' ); ?></p>
</body></html>
