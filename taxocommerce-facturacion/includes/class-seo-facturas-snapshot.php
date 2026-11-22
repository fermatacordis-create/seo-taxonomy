<?php
/**
 * Crea una copia inmutable de los datos fiscales de un pedido WooCommerce.
 */

defined('ABSPATH') || exit;

final class SEO_Facturas_Snapshot {

    public static function from_order($order, $document_type, $document_number, $issued_at) {
        if (!$order || !is_a($order, 'WC_Order')) {
            return new WP_Error('seo_facturas_invalid_order', 'Pedido WooCommerce no valido.');
        }

        $currency = (string) $order->get_currency();
        $billing = self::address_snapshot($order->get_address('billing'));
        $shipping = self::address_snapshot($order->get_address('shipping'));
        $billing['tax_id'] = self::customer_tax_id($order);

        $destination = !empty($shipping['country']) ? $shipping : $billing;
        $fiscal = class_exists('SEO_Facturas_Tax')
            ? SEO_Facturas_Tax::context_for_location(
                (string) ($destination['country'] ?? ''),
                (string) ($destination['state'] ?? ''),
                (string) ($destination['postcode'] ?? '')
            )
            : array();

        if (
            class_exists('SEO_Facturas_Eu_Vat')
            && SEO_Facturas_Eu_Vat::order_is_reverse_charge($order)
        ) {
            $fiscal['regime'] = 'intra_eu_reverse_charge';
            $fiscal['zone'] = 'intra_eu';
            $fiscal['zone_label'] = 'UE B2B (ISP)';
            $fiscal['tax_name'] = 'IVA';
            $fiscal['rate'] = 0.0;
            $fiscal['label'] = 'IVA 0% (ISP)';
            $fiscal['note'] = SEO_Facturas_Eu_Vat::legal_note();
            $fiscal['enabled'] = true;
            if (empty($billing['tax_id'])) {
                $billing['tax_id'] = (string) $order->get_meta(SEO_Facturas_Eu_Vat::META_VAT);
            }
        }

        $include_commercial = SEO_Facturas_Documents::TYPE_PROFORMA === sanitize_key((string) $document_type);

        $items = array();
        foreach ($order->get_items('line_item') as $item_id => $item) {
            $product = $item->get_product();
            $qty = (float) $item->get_quantity();
            $total = (float) $item->get_total();
            $items[] = array(
                'item_id'       => absint($item_id),
                'product_id'    => absint($item->get_product_id()),
                'object_id'     => absint($item->get_product_id()),
                'variation_id'  => absint($item->get_variation_id()),
                'sku'           => $product ? (string) $product->get_sku() : '',
                'name'          => (string) $item->get_name(),
                'quantity'      => $qty,
                'subtotal'      => (float) $item->get_subtotal(),
                'subtotal_tax'  => (float) $item->get_subtotal_tax(),
                'total'         => $total,
                'total_tax'     => (float) $item->get_total_tax(),
                'unit_net'      => $qty > 0 ? ($total / $qty) : $total,
                'tax_class'     => (string) $item->get_tax_class(),
                'commercial'    => ($include_commercial && $product)
                    ? self::product_commercial_snapshot($product)
                    : array(),
            );
        }

        $shipping_lines = array();
        foreach ($order->get_items('shipping') as $item_id => $item) {
            $shipping_lines[] = array(
                'item_id'    => absint($item_id),
                'name'       => (string) $item->get_name(),
                'method_id'  => (string) $item->get_method_id(),
                'total'      => (float) $item->get_total(),
                'total_tax'  => (float) $item->get_total_tax(),
            );
        }

        $fee_lines = array();
        foreach ($order->get_items('fee') as $item_id => $item) {
            $fee_lines[] = array(
                'item_id'   => absint($item_id),
                'name'      => (string) $item->get_name(),
                'total'     => (float) $item->get_total(),
                'total_tax' => (float) $item->get_total_tax(),
            );
        }

        $coupon_lines = array();
        foreach ($order->get_items('coupon') as $item_id => $item) {
            $coupon_lines[] = array(
                'item_id'  => absint($item_id),
                'code'     => (string) $item->get_code(),
                'discount' => (float) $item->get_discount(),
            );
        }

        $tax_lines = array();
        foreach ($order->get_items('tax') as $item_id => $item) {
            $tax_lines[] = array(
                'item_id'           => absint($item_id),
                'label'             => (string) $item->get_label(),
                'rate_id'           => absint($item->get_rate_id()),
                'rate_percent'      => self::tax_rate_percent($item->get_rate_id()),
                'tax_total'         => (float) $item->get_tax_total(),
                'shipping_tax_total'=> (float) $item->get_shipping_tax_total(),
            );
        }

        $paid_at = $order->get_date_paid();
        $created_at = $order->get_date_created();

        $profile = SEO_Facturas_Settings::document_profile($document_type);

        $snapshot = array(
            'schema_version' => 2,
            'document'       => array_merge(
                array(
                    'type'      => (string) $document_type,
                    'number'    => (string) $document_number,
                    'issued_at' => (string) $issued_at,
                ),
                $profile
            ),
            'seller'         => SEO_Facturas_Settings::company_snapshot(),
            'order'          => array(
                'id'                   => absint($order->get_id()),
                'number'               => (string) $order->get_order_number(),
                'status'               => (string) $order->get_status(),
                'currency'             => $currency,
                'payment_method'       => (string) $order->get_payment_method(),
                'payment_method_title' => (string) $order->get_payment_method_title(),
                'created_at'           => $created_at ? $created_at->date('Y-m-d H:i:s') : '',
                'paid_at'              => $paid_at ? $paid_at->date('Y-m-d H:i:s') : '',
                'customer_note'        => (string) $order->get_customer_note(),
            ),
            'billing'         => $billing,
            'shipping'        => $shipping,
            'fiscal'          => $fiscal,
            'items'           => $items,
            'shipping_lines'  => $shipping_lines,
            'fee_lines'       => $fee_lines,
            'coupon_lines'    => $coupon_lines,
            'tax_lines'       => $tax_lines,
            'totals'          => array(
                'subtotal_items' => (float) $order->get_subtotal(),
                'discount_total' => (float) $order->get_discount_total(),
                'shipping_total' => (float) $order->get_shipping_total(),
                'fee_total'      => self::fee_total($fee_lines),
                'total_tax'      => (float) $order->get_total_tax(),
                'total'          => (float) $order->get_total(),
                'base_total'     => max(0, (float) $order->get_total() - (float) $order->get_total_tax()),
            ),
        );

        return apply_filters('seo_facturas_order_snapshot', $snapshot, $order, $document_type);
    }

    /**
     * Convierte contenido editorial de producto en texto continuo apto para PDF.
     * Sustituye cierres de bloque por espacios para evitar uniones como
     * "empezar.Entre" y normaliza espacios sin introducir truncamientos visuales.
     */
    private static function commercial_clean_text($html) {
        $charset = (string) get_bloginfo('charset');
        if ('' === $charset) {
            $charset = 'UTF-8';
        }

        $text = html_entity_decode((string) $html, ENT_QUOTES | ENT_HTML5, $charset);
        $text = preg_replace('#<(?:br|hr)\s*/?>#i', ' ', $text);
        $text = preg_replace('#</(?:p|div|li|ul|ol|h[1-6]|tr|td|th|section|article)>#i', ' ', $text);
        $text = wp_strip_all_tags((string) $text);
        $text = preg_replace('/([.!?])(?=[\p{Lu}\d])/u', '$1 ', (string) $text);
        $text = preg_replace('/\s+/u', ' ', (string) $text);

        return trim((string) $text);
    }

    /**
     * Resume usando frases completas cuando el contenido dispone de puntuacion.
     * Si el origen carece de frases, aplica un limite de palabras sin usar
     * elipsis para que el PDF no parezca texto cortado.
     */
    private static function commercial_sentence_excerpt($html, $max_sentences = 2, $fallback_words = 55) {
        $text = self::commercial_clean_text($html);
        if ('' === $text) {
            return '';
        }

        $max_sentences = max(1, absint($max_sentences));
        $fallback_words = max(10, absint($fallback_words));
        $sentences = preg_split('/(?<=[.!?])\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY);

        if (is_array($sentences) && (count($sentences) > 1 || preg_match('/[.!?]$/u', $text))) {
            return trim(implode(' ', array_slice($sentences, 0, $max_sentences)));
        }

        $words = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($words) || count($words) <= $fallback_words) {
            return $text;
        }

        return rtrim(implode(' ', array_slice($words, 0, $fallback_words)), " \t\n\r\0\x0B,;:-") . '.';
    }

    /**
     * Copia comercial del producto para presupuestos/proformas.
     *
     * Se congela dentro del snapshot para que el documento conserve la ficha
     * mostrada al cliente aunque el producto cambie posteriormente.
     */
    public static function product_commercial_snapshot($product) {
        if (!$product || !is_a($product, 'WC_Product')) {
            return array();
        }

        $source = $product;
        if (is_a($product, 'WC_Product_Variation') && $product->get_parent_id()) {
            $parent = wc_get_product($product->get_parent_id());
            if ($parent) {
                $source = $parent;
            }
        }

        $product_id = absint($source->get_id());
        $short_description = (string) $source->get_short_description();
        $full_description = (string) $source->get_description();

        $summary_source = '' !== trim(wp_strip_all_tags($short_description))
            ? $short_description
            : $full_description;

        $summary = self::commercial_sentence_excerpt($summary_source, 2, 55);
        $description = self::commercial_sentence_excerpt($full_description, 3, 90);

        $categories = wp_get_post_terms($product_id, 'product_cat', array('fields' => 'names'));
        if (is_wp_error($categories)) {
            $categories = array();
        }

        $tags = wp_get_post_terms($product_id, 'product_tag', array('fields' => 'names'));
        if (is_wp_error($tags)) {
            $tags = array();
        }

        $attributes = array();
        if (function_exists('dht_shared_product_compare_data')) {
            $compare = dht_shared_product_compare_data($source, 3);
            if (is_array($compare) && !empty($compare['attributes']) && is_array($compare['attributes'])) {
                $attributes = $compare['attributes'];
            }
        }

        if (!$attributes) {
            $append = static function (&$target, $label, $values, $only_if_missing = false) {
                $label = trim(wp_strip_all_tags((string) $label));
                if ('' === $label || ($only_if_missing && isset($target[$label]))) {
                    return;
                }

                $values = array_values(array_unique(array_filter(array_map(static function ($value) {
                    return trim(wp_strip_all_tags((string) $value));
                }, (array) $values))));

                if (!$values) {
                    return;
                }

                $target[$label] = implode(', ', $values);
            };

            if (function_exists('seo_attributes_get_product_rows')) {
                $groups = array();
                foreach ((array) seo_attributes_get_product_rows($product_id) as $row) {
                    if (isset($row->attribute_visible) && !(int) $row->attribute_visible) {
                        continue;
                    }

                    $label = trim((string) ($row->attribute_name ?? ''));
                    $value = trim((string) ($row->attribute_value ?? ''));
                    if ('' === $label || '' === $value) {
                        continue;
                    }
                    $groups[$label] = $groups[$label] ?? array();
                    $groups[$label][] = $value;
                }
                foreach ($groups as $label => $values) {
                    $append($attributes, $label, $values);
                }
            }

            $weight = trim((string) $source->get_weight('edit'));
            if ('' !== $weight && is_numeric($weight) && (float) $weight > 0) {
                $append(
                    $attributes,
                    'Peso',
                    array(function_exists('wc_format_weight') ? wc_format_weight($weight) : $weight . ' ' . get_option('woocommerce_weight_unit', 'kg')),
                    true
                );
            }

            $dimensions = $source->get_dimensions(false);
            if (is_array($dimensions)) {
                $parts = array();
                foreach (array('length' => 'L', 'width' => 'An', 'height' => 'Al') as $key => $prefix) {
                    $value = trim((string) ($dimensions[$key] ?? ''));
                    if ('' !== $value && is_numeric($value) && (float) $value > 0) {
                        $parts[] = $prefix . ' ' . $value;
                    }
                }
                if ($parts) {
                    $append(
                        $attributes,
                        'Dimensiones',
                        array(implode(' × ', $parts) . ' ' . get_option('woocommerce_dimension_unit', 'cm')),
                        true
                    );
                }
            }

            foreach ((array) $source->get_attributes() as $attribute) {
                if (!is_a($attribute, 'WC_Product_Attribute')) {
                    continue;
                }

                $name = (string) $attribute->get_name();
                $label = function_exists('wc_attribute_label')
                    ? (string) wc_attribute_label($name, $source)
                    : $name;
                $values = $attribute->is_taxonomy() && function_exists('wc_get_product_terms')
                    ? wc_get_product_terms($product_id, $name, array('fields' => 'names'))
                    : (array) $attribute->get_options();

                if (is_wp_error($values)) {
                    $values = array();
                }

                $append($attributes, $label, $values, true);
            }

            global $wpdb;
            $semantic_labels = array(
                'tipo'       => 'Tipo',
                'rol'        => 'Rol',
                'aplicacion' => 'Aplicación',
                'plataforma' => 'Plataforma',
                'subtipo'    => 'Subtipo',
            );
            $vocabulary_table = esc_sql( $wpdb->prefix . 'seo_vocabulary' );
            $object_table = esc_sql( $wpdb->prefix . 'seo_object_vocabulary' );
            $semantic_available = function_exists('seo_catalog_table_exists')
                && seo_catalog_table_exists($vocabulary_table)
                && seo_catalog_table_exists($object_table);

            if ($semantic_available) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Lectura de taxonomia semantica propia para snapshot fiscal inmutable; requiere datos actuales, no cache persistente.
                $rows = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT v.semantic_group, v.label
                         FROM %i ov
                         INNER JOIN %i v
                            ON v.id = ov.vocabulary_id
                           AND v.active = 1
                         WHERE ov.object_type = 'product'
                           AND ov.object_id = %d
                           AND ov.status = 1
                           AND v.semantic_group IN ('tipo','rol','aplicacion','plataforma','subtipo')
                         ORDER BY FIELD(v.semantic_group,'tipo','rol','aplicacion','plataforma','subtipo'),
                                  v.label ASC",
                        $object_table,
                        $vocabulary_table,
                        $product_id
                    ),
                    ARRAY_A
                );

                $groups = array();
                foreach ((array) $rows as $row) {
                    $group = sanitize_key((string) ($row['semantic_group'] ?? ''));
                    $label = trim((string) ($row['label'] ?? ''));
                    if ('' === $group || '' === $label || !isset($semantic_labels[$group])) {
                        continue;
                    }
                    $groups[$group] = $groups[$group] ?? array();
                    $groups[$group][] = $label;
                }
                foreach ($semantic_labels as $group => $display_label) {
                    if (!empty($groups[$group])) {
                        $append($attributes, $display_label, $groups[$group]);
                    }
                }
            }
        }

        $image_data_uri = self::product_image_data_uri($source);

        $effective_price = $product->get_price('view');
        $price_text = '';
        if ('' !== (string) $effective_price && is_numeric($effective_price)) {
            $display_price = function_exists('wc_get_price_to_display')
                ? wc_get_price_to_display($product, array('price' => (float) $effective_price))
                : (float) $effective_price;
            $price_html = function_exists('wc_price') ? wc_price($display_price) : (string) $display_price;
            $price_text = html_entity_decode(
                wp_strip_all_tags((string) $price_html, true),
                ENT_QUOTES | ENT_HTML5,
                (string) get_bloginfo('charset') ?: 'UTF-8'
            );
            $price_text = str_replace("\xC2\xA0", ' ', $price_text);
            $price_text = trim((string) preg_replace('/[\\s\\x{00A0}]+/u', ' ', $price_text));
        }

        return array(
            'product_id'     => $product_id,
            'name'           => wp_strip_all_tags((string) $product->get_name()),
            'url'            => esc_url_raw((string) get_permalink($product_id)),
            'image_data_uri' => $image_data_uri,
            'summary'        => $summary,
            'description'    => $description,
            'categories'     => array_slice(array_values(array_unique(array_filter(array_map('sanitize_text_field', (array) $categories)))), 0, 8),
            'tags'           => array_slice(array_values(array_unique(array_filter(array_map('sanitize_text_field', (array) $tags)))), 0, 12),
            'attributes'     => array_slice($attributes, 0, 20, true),
            'price'          => $price_text,
        );
    }

    private static function product_image_data_uri($product) {
        if (!$product || !is_a($product, 'WC_Product')) {
            return '';
        }

        $attachment_id = absint($product->get_image_id());
        if (!$attachment_id && is_a($product, 'WC_Product_Variation') && $product->get_parent_id()) {
            $parent = wc_get_product($product->get_parent_id());
            $attachment_id = $parent ? absint($parent->get_image_id()) : 0;
        }

        if (!$attachment_id) {
            return self::supplier_image_data_uri(absint($product->get_id()));
        }

        $path = '';
        $image = wp_get_attachment_image_src($attachment_id, 'medium');
        if (is_array($image) && !empty($image[0])) {
            $uploads = wp_upload_dir(null, false);
            $baseurl = rtrim((string) ($uploads['baseurl'] ?? ''), '/');
            $basedir = rtrim((string) ($uploads['basedir'] ?? ''), DIRECTORY_SEPARATOR);
            $url = (string) $image[0];

            if ($baseurl && $basedir && 0 === strpos($url, $baseurl . '/')) {
                $relative = ltrim(substr($url, strlen($baseurl)), '/');
                $candidate = $basedir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
                if (is_readable($candidate)) {
                    $path = $candidate;
                }
            }
        }

        if ('' === $path) {
            $path = (string) get_attached_file($attachment_id);
        }
        if (!$path || !is_readable($path)) {
            return self::supplier_image_data_uri(absint($product->get_id()));
        }

        $mime = function_exists('wp_check_filetype') ? (string) (wp_check_filetype($path)['type'] ?? '') : '';
        if (!$mime) {
            $mime = (string) get_post_mime_type($attachment_id);
        }
        if (!$mime || 0 !== strpos($mime, 'image/')) {
            return '';
        }

        $bytes = file_get_contents($path);
        if (!is_string($bytes) || '' === $bytes) {
            return '';
        }

        return 'data:' . $mime . ';base64,' . base64_encode($bytes);
    }

    private static function supplier_image_data_uri($product_id) {
        $product_id = absint($product_id);
        if (!$product_id) {
            return '';
        }

        $urls = array();
        $add = static function ($url) use (&$urls) {
            $url = esc_url_raw((string) $url);
            if ($url && preg_match('#^https?://#i', $url) && !in_array($url, $urls, true)) {
                $urls[] = $url;
            }
        };

        global $wpdb;
        $table = $wpdb->prefix . 'seo_supplier_images';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Comprobar presencia de tabla legado antes de consultar imagenes; sin alternativa WP.
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))) === $table;
        if ($exists) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Consulta parametrizada con %i/%d, sobre tabla legado de imagenes; usar URLs vigentes al crear el snapshot.
            $rows = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT image_url
                     FROM %i
                     WHERE product_id = %d
                       AND status = 'active'
                       AND image_url IS NOT NULL
                       AND TRIM(image_url) <> ''
                     ORDER BY is_primary DESC, position ASC, id ASC
                     LIMIT 3",
                    $table,
                    $product_id
                )
            );
            foreach ((array) $rows as $url) {
                $add($url);
            }
        }

        if (function_exists('seo_supplier_v2_external_primary_url')) {
            $add(seo_supplier_v2_external_primary_url($product_id));
        }

        foreach ($urls as $url) {
            $response = wp_safe_remote_get($url, array(
                'timeout'             => 8,
                'redirection'         => 3,
                'limit_response_size' => 3145728,
                'headers'             => array(
                    'User-Agent' => 'Mozilla/5.0 (compatible; DistribuidorDeHerramientas/1.0)',
                    'Accept'     => 'image/avif,image/webp,image/apng,image/svg+xml,image/*,*/*;q=0.8',
                ),
            ));
            if (is_wp_error($response)) {
                continue;
            }

            $code = (int) wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            $mime = strtolower(trim((string) wp_remote_retrieve_header($response, 'content-type')));
            if ($code < 200 || $code >= 300 || !is_string($body) || '' === $body) {
                continue;
            }

            if (false !== strpos($mime, ';')) {
                $mime = trim(strtok($mime, ';'));
            }
            if (!preg_match('#^image/(jpeg|png|gif|webp|avif)$#i', $mime)) {
                continue;
            }

            return 'data:' . $mime . ';base64,' . base64_encode($body);
        }

        return '';
    }

    private static function address_snapshot($address) {
        $address = is_array($address) ? $address : array();
        $country = (string) ($address['country'] ?? '');
        $state = (string) ($address['state'] ?? '');

        return array(
            'first_name'   => (string) ($address['first_name'] ?? ''),
            'last_name'    => (string) ($address['last_name'] ?? ''),
            'company'      => (string) ($address['company'] ?? ''),
            'address_1'    => (string) ($address['address_1'] ?? ''),
            'address_2'    => (string) ($address['address_2'] ?? ''),
            'postcode'     => (string) ($address['postcode'] ?? ''),
            'city'         => (string) ($address['city'] ?? ''),
            'state'        => $state,
            'state_name'   => self::state_name($country, $state),
            'country'      => $country,
            'country_name' => self::country_name($country),
            'email'        => (string) ($address['email'] ?? ''),
            'phone'        => (string) ($address['phone'] ?? ''),
        );
    }

    private static function customer_tax_id($order) {
        $value = '';
        $keys = SEO_Facturas_Settings::customer_tax_meta_keys();

        foreach ($keys as $key) {
            $candidate = trim((string) $order->get_meta($key, true));
            if ('' !== $candidate) {
                $value = $candidate;
                break;
            }
        }

        return (string) apply_filters('seo_facturas_customer_tax_id', $value, $order, $keys);
    }

    private static function tax_rate_percent($rate_id) {
        $rate_id = absint($rate_id);
        if (!$rate_id || !class_exists('WC_Tax')) {
            return null;
        }

        if (method_exists('WC_Tax', 'get_rate_percent_value')) {
            return (float) WC_Tax::get_rate_percent_value($rate_id);
        }

        if (method_exists('WC_Tax', 'get_rate_percent')) {
            $percent = (string) WC_Tax::get_rate_percent($rate_id);
            $percent = str_replace('%', '', $percent);
            return is_numeric($percent) ? (float) $percent : null;
        }

        return null;
    }

    private static function country_name($country) {
        if (function_exists('WC') && WC() && isset(WC()->countries)) {
            $countries = WC()->countries->get_countries();
            if (isset($countries[$country])) {
                return (string) $countries[$country];
            }
        }
        return $country;
    }

    private static function state_name($country, $state) {
        if (function_exists('WC') && WC() && isset(WC()->countries)) {
            $states = WC()->countries->get_states($country);
            if (is_array($states) && isset($states[$state])) {
                return (string) $states[$state];
            }
        }
        return $state;
    }

    private static function fee_total($fee_lines) {
        $total = 0.0;
        foreach ($fee_lines as $fee) {
            $total += (float) ($fee['total'] ?? 0);
        }
        return $total;
    }
}
