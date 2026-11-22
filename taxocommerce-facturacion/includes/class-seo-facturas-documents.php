<?php
/**
 * Servicio de documentos: numeracion, persistencia, render y reintentos.
 */

defined('ABSPATH') || exit;

final class SEO_Facturas_Documents {

    const TYPE_INVOICE = 'invoice';
    const TYPE_PROFORMA = 'proforma';
    const TYPE_QUOTE = 'quote';
    const TYPE_RECTIFICATION = 'rectification';

    public static function table_name() {
        return SEO_Facturas_Install::table_name();
    }

    public static function issue_for_order($order_id, $type) {
        global $wpdb;

        $order_id = absint($order_id);
        $type = sanitize_key($type);

        if (!in_array($type, array(self::TYPE_INVOICE, self::TYPE_PROFORMA), true)) {
            return new WP_Error('seo_facturas_invalid_type', 'Tipo de documento no valido.');
        }

        if (!SEO_Facturas_Settings::get('enabled', 0)) {
            return new WP_Error('seo_facturas_disabled', 'El sistema documental esta desactivado.');
        }

        if (self::TYPE_INVOICE === $type && !SEO_Facturas_Settings::get('invoice_enabled', 1)) {
            return new WP_Error('seo_facturas_invoice_disabled', 'La emision de facturas esta desactivada.');
        }

        if (self::TYPE_PROFORMA === $type && !SEO_Facturas_Settings::get('proforma_enabled', 1)) {
            return new WP_Error('seo_facturas_proforma_disabled', 'La emision de proformas esta desactivada.');
        }

        if (!function_exists('wc_get_order')) {
            return new WP_Error('seo_facturas_woocommerce_missing', 'WooCommerce no esta disponible.');
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            return new WP_Error('seo_facturas_order_missing', 'No se encuentra el pedido WooCommerce.');
        }

        $existing = self::get_for_order($order_id, $type);
        if ($existing) {
            if ('issued' !== $existing->status || empty($existing->pdf_path) || !is_readable($existing->pdf_path)) {
                return self::retry_pdf($existing);
            }
            return $existing;
        }

        if (self::TYPE_INVOICE === $type && !$order->is_paid()) {
            $allowed = (bool) apply_filters('seo_facturas_allow_invoice_for_unpaid_order', false, $order);
            if (!$allowed) {
                return new WP_Error('seo_facturas_order_unpaid', 'La factura no se emite porque WooCommerce no considera pagado el pedido.');
            }
        }

        if (self::TYPE_PROFORMA === $type && $order->is_paid()) {
            $allowed = (bool) apply_filters('seo_facturas_allow_proforma_for_paid_order', false, $order);
            if (!$allowed) {
                return new WP_Error('seo_facturas_order_paid', 'No se genera proforma para un pedido que WooCommerce ya considera pagado.');
            }
        }

        // No registrar ni numerar documentos nuevos si el pedido contiene
        // lineas ordinarias sujetas a IVA pero WooCommerce calculo cuota cero.
        // Las facturas existentes se conservan sin alterar sus snapshots.
        if (class_exists('SEO_Facturas_Tax')) {
            $tax_validation = SEO_Facturas_Tax::validate_order_tax($order);
            if (is_wp_error($tax_validation)) {
                return $tax_validation;
            }
        }

        $numbering = self::reserve_number($type);
        if (is_wp_error($numbering)) {
            return $numbering;
        }

        $sequence = absint($numbering['sequence']);
        $series = (string) $numbering['series'];
        $issued_at = (string) $numbering['issued_at'];
        $number = (string) $numbering['number'];
        $snapshot = SEO_Facturas_Snapshot::from_order($order, $type, $number, $issued_at);
        if (is_wp_error($snapshot)) {
            return $snapshot;
        }

        $html = SEO_Facturas_PDF::render_html($snapshot);
        if (is_wp_error($html)) {
            return $html;
        }

        $now = current_time('mysql');
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Insercion en tabla documental propia; requiere escritura inmediata para mantener unicidad por pedido.
        $inserted = $wpdb->insert(
            self::table_name(),
            array(
                'order_id'          => $order_id,
                'document_type'     => $type,
                'series'            => $series,
                'sequence_number'   => $sequence,
                'document_number'   => $number,
                'status'            => 'rendering',
                'issued_at'         => $issued_at,
                'order_status'      => (string) $order->get_status(),
                'payment_method'    => (string) $order->get_payment_method(),
                'snapshot'          => wp_json_encode($snapshot, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'rendered_html'     => $html,
                'created_at'        => $now,
                'updated_at'        => $now,
            ),
            array('%d','%s','%s','%d','%s','%s','%s','%s','%s','%s','%s','%s','%s')
        );

        if (!$inserted) {
            $existing = self::get_for_order($order_id, $type);
            if ($existing) {
                return $existing;
            }
            return new WP_Error('seo_facturas_insert_failed', 'No se pudo registrar el documento.', $wpdb->last_error);
        }

        $document_id = absint($wpdb->insert_id);
        $document = self::get($document_id);
        if (!$document) {
            return new WP_Error('seo_facturas_document_missing', 'El documento se registro pero no puede recuperarse.');
        }

        return self::retry_pdf($document);
    }

    public static function retry_pdf($document) {
        global $wpdb;

        if (is_numeric($document)) {
            $document = self::get(absint($document));
        }
        if (!$document || empty($document->id)) {
            return new WP_Error('seo_facturas_document_invalid', 'Documento no valido.');
        }

        $html = (string) $document->rendered_html;
        if ('' === $html) {
            $snapshot = self::decode_snapshot($document);
            if (is_wp_error($snapshot)) {
                return $snapshot;
            }
            $html = SEO_Facturas_PDF::render_html($snapshot);
            if (is_wp_error($html)) {
                return $html;
            }
        }

        $pdf = SEO_Facturas_PDF::create_pdf(
            absint($document->id),
            (string) $document->document_number,
            (string) $document->issued_at,
            $html
        );

        if (is_wp_error($pdf)) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Estado PDF persistido en tabla documental propia; escritura inmediata, no cacheable.
            $wpdb->update(
                self::table_name(),
                array(
                    'status'     => 'error',
                    'last_error' => $pdf->get_error_message(),
                    'updated_at' => current_time('mysql'),
                ),
                array('id' => absint($document->id)),
                array('%s','%s','%s'),
                array('%d')
            );
            return $pdf;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Estado PDF persistido en tabla documental propia; escritura inmediata, no cacheable.
        $wpdb->update(
            self::table_name(),
            array(
                'status'        => 'issued',
                'rendered_html' => $html,
                'pdf_path'      => (string) $pdf['path'],
                'file_hash'     => (string) $pdf['hash'],
                'last_error'    => null,
                'updated_at'    => current_time('mysql'),
            ),
            array('id' => absint($document->id)),
            array('%s','%s','%s','%s','%s','%s'),
            array('%d')
        );

        $issued = self::get(absint($document->id));
        if ($issued && 'issued' === (string) $issued->status &&
            self::TYPE_INVOICE === (string) $issued->document_type) {
            /**
             * Fires after a sales invoice PDF and its immutable snapshot are issued.
             * Optional accounting plugins may import it via read-only lookups.
             *
             * @param int    $order_id WooCommerce order ID.
             * @param object $issued   Issued invoice document.
             */
            do_action('taxocommerce_billing_invoice_issued', absint($issued->order_id), $issued);
        }
        return $issued;
    }

    public static function get($document_id) {
        global $wpdb;
        $document_id = absint($document_id);
        if (!$document_id) {
            return null;
        }
        $table = self::table_name();
        $sql = $wpdb->prepare('SELECT * FROM %i WHERE id = %d LIMIT 1', $table, $document_id);
        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- SQL preparado con %i/%d/%s sobre tabla propia; lectura actual sin cache para evitar documentos fiscales obsoletos.
        return $wpdb->get_row($sql);
    }

    public static function get_for_order($order_id, $type) {
        global $wpdb;
        $order_id = absint($order_id);
        $type = sanitize_key($type);
        if (!$order_id || '' === $type) {
            return null;
        }
        $table = self::table_name();
        $sql = $wpdb->prepare('SELECT * FROM %i WHERE order_id = %d AND document_type = %s LIMIT 1', $table, $order_id, $type);
        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- SQL preparado con %i/%d/%s sobre tabla propia; lectura actual sin cache para evitar documentos fiscales obsoletos.
        return $wpdb->get_row($sql);
    }

    public static function list_recent($limit = 100) {
        global $wpdb;
        $limit = max(1, min(500, absint($limit)));
        $table = self::table_name();
        $sql = $wpdb->prepare('SELECT * FROM %i ORDER BY issued_at DESC, id DESC LIMIT %d', $table, $limit);
        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- SQL preparado con %i/%d/%s sobre tabla propia; lectura actual sin cache para evitar documentos fiscales obsoletos.
        return $wpdb->get_results($sql);
    }

    public static function mark_emailed($document_id) {
        global $wpdb;
        $document_id = absint($document_id);
        if (!$document_id) {
            return false;
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Persistencia de fecha de correo enviado, operacion de escritura no cacheable.
        return false !== $wpdb->update(
            self::table_name(),
            array(
                'email_sent_at' => current_time('mysql'),
                'updated_at'    => current_time('mysql'),
            ),
            array('id' => $document_id),
            array('%s','%s'),
            array('%d')
        );
    }

    public static function decode_snapshot($document) {
        $raw = is_object($document) ? (string) $document->snapshot : (string) $document;
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return new WP_Error('seo_facturas_snapshot_invalid', 'El snapshot fiscal del documento no es valido.');
        }
        return $data;
    }

    public static function order_admin_url($order_id) {
        $order_id = absint($order_id);
        if (!$order_id) {
            return admin_url('admin.php?page=wc-orders');
        }

        if (class_exists('Automattic\\WooCommerce\\Utilities\\OrderUtil')) {
            $class = 'Automattic\\WooCommerce\\Utilities\\OrderUtil';
            if (method_exists($class, 'custom_orders_table_usage_is_enabled') && $class::custom_orders_table_usage_is_enabled()) {
                return admin_url('admin.php?page=wc-orders&action=edit&id=' . $order_id);
            }
        }

        return admin_url('post.php?post=' . $order_id . '&action=edit');
    }

    public static function reserve_number($type) {
        $type = sanitize_key((string) $type);
        if (!in_array($type, array(self::TYPE_INVOICE, self::TYPE_PROFORMA, self::TYPE_QUOTE, self::TYPE_RECTIFICATION), true)) {
            return new WP_Error('seo_facturas_number_type', 'Tipo de documento no valido para numeracion.');
        }

        $series = SEO_Facturas_Settings::series_for_type($type);
        foreach (array(self::TYPE_INVOICE, self::TYPE_PROFORMA, self::TYPE_QUOTE, self::TYPE_RECTIFICATION) as $other_type) {
            if ($other_type === $type) {
                continue;
            }
            if ($series === SEO_Facturas_Settings::series_for_type($other_type)) {
                return new WP_Error(
                    'seo_facturas_series_conflict',
                    'Las series de Facturas, Proformas y Presupuestos deben ser diferentes entre si.'
                );
            }
        }

        $sequence = self::next_sequence($type);
        if (is_wp_error($sequence)) {
            return $sequence;
        }

        $issued_at = current_time('mysql');
        $number = self::format_number($type, $series, $sequence, $issued_at);

        return array(
            'type'      => $type,
            'series'    => $series,
            'sequence'  => absint($sequence),
            'issued_at' => $issued_at,
            'number'    => $number,
        );
    }

    private static function next_sequence($type) {
        global $wpdb;

        $series = SEO_Facturas_Settings::series_for_type($type);
        $year = wp_date('Y');
        $option_name = 'seo_facturas_seq_' . md5($type . '|' . $series . '|' . $year);

        if (add_option($option_name, '1', '', false)) {
            return 1;
        }

        $sql = $wpdb->prepare(
            "UPDATE {$wpdb->options} SET option_value = LAST_INSERT_ID(CAST(option_value AS UNSIGNED) + 1) WHERE option_name = %s",
            $option_name
        );
        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Incremento atomico de secuencia fiscal en tabla options; no debe cachearse ni perder la operacion SQL LAST_INSERT_ID().
        $updated = $wpdb->query($sql);
        if (false === $updated || 0 === $updated) {
            return new WP_Error('seo_facturas_sequence_failed', 'No se pudo incrementar la numeracion del documento.');
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- LAST_INSERT_ID() es especifico de esta conexion y se lee justo despues del UPDATE atomico, nunca desde cache.
        $next = absint($wpdb->get_var('SELECT LAST_INSERT_ID()'));
        if (!$next) {
            return new WP_Error('seo_facturas_sequence_invalid', 'La numeracion devolvio un valor no valido.');
        }
        return $next;
    }

    private static function format_number($type, $series, $sequence, $issued_at) {
        $padding = SEO_Facturas_Settings::padding_for_type($type);
        $timestamp = strtotime((string) $issued_at);
        $year = $timestamp ? wp_date('Y', $timestamp) : wp_date('Y');
        return $series . '-' . $year . '-' . str_pad((string) absint($sequence), $padding, '0', STR_PAD_LEFT);
    }
}
