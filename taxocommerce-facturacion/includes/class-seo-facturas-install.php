<?php
/**
 * Instalacion y migraciones del modulo de facturas.
 */

defined('ABSPATH') || exit;

final class SEO_Facturas_Install {

    public static function table_name() {
        global $wpdb;
        return $wpdb->prefix . 'seo_factura_documents';
    }

    public static function maybe_upgrade() {
        $installed = (string) get_option('seo_facturas_db_version', '');
        if ($installed === SEO_FACTURAS_DB_VERSION) {
            return;
        }

        self::install_schema();
        update_option('seo_facturas_db_version', SEO_FACTURAS_DB_VERSION, false);
    }

    private static function install_schema() {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $table = self::table_name();
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            order_id bigint(20) unsigned NOT NULL,
            document_type varchar(20) NOT NULL,
            series varchar(32) NOT NULL,
            sequence_number bigint(20) unsigned NOT NULL,
            document_number varchar(64) NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'rendering',
            issued_at datetime NOT NULL,
            order_status varchar(32) NOT NULL DEFAULT '',
            payment_method varchar(100) NOT NULL DEFAULT '',
            snapshot longtext NOT NULL,
            rendered_html longtext NULL,
            pdf_path text NULL,
            file_hash char(64) NULL,
            email_sent_at datetime NULL,
            last_error text NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY order_document (order_id,document_type),
            UNIQUE KEY document_number (document_number),
            KEY order_id (order_id),
            KEY issued_at (issued_at),
            KEY status (status)
        ) {$charset};";

        dbDelta($sql);
        SEO_Facturas_Rectifications::install_schema();
    }
}
