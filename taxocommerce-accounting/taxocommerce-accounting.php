<?php
/**
 * Plugin Name: TaxoCommerce Accounting
 * Description: Independent WooCommerce fiscal register, purchase expenses, monthly VAT summaries and safe data exports.
 * Version: 0.1.5
 * Requires at least: 6.5
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * Author: TaxoCommerce
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: taxocommerce-accounting
 * Domain Path: /languages
 */
defined( 'ABSPATH' ) || exit;

define( 'TCA_VERSION', '0.1.5' );
define( 'TCA_DB_VERSION', '2' );
define( 'TCA_OPTION', 'tca_settings_v1' );
// Prefer official WordPress language packs; load bundled MO only as a fallback.
add_action( 'init', static function () {
    $domain = 'taxocommerce-accounting';
    if ( is_textdomain_loaded( $domain ) ) return;
    $locale = sanitize_file_name( determine_locale() );
    $official = WP_LANG_DIR . '/plugins/' . $domain . '-' . $locale;
    if ( is_readable( $official . '.mo' ) || is_readable( $official . '.l10n.php' ) ) return;
    $fallbacks = array( 'es' => 'es_ES', 'en' => 'en_US', 'fr' => 'fr_FR', 'de' => 'de_DE', 'nl' => 'nl_NL' );
    $language = substr( strtolower( $locale ), 0, 2 );
    $filename = $domain . '-' . ( isset( $fallbacks[ $language ] ) ? $fallbacks[ $language ] : $locale ) . '.mo';
    $catalog = plugin_dir_path( __FILE__ ) . 'languages/' . $filename;
    if ( is_readable( $catalog ) ) load_textdomain( $domain, $catalog );
}, 5 );

require_once plugin_dir_path( __FILE__ ) . 'includes/class-tca-provider-invoices.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-tca-hacienda.php';

/** Own data; no dependency on any TaxoCommerce module. */
function tca_table() {
    global $wpdb;
    return $wpdb->prefix . 'taxocommerce_accounting_entries';
}
function tca_settings() {
    $today = current_time( 'Y-m-d' );
    $defaults = array(
        'start_date' => substr( $today, 0, 7 ) . '-01',
        'auto_sync_sales' => 0,
        'country'    => function_exists( 'wc_get_base_location' ) ? (string) ( wc_get_base_location()['country'] ?? 'ES' ) : 'ES',
        'currency'   => function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'EUR',
    );
    return wp_parse_args( (array) get_option( TCA_OPTION, array() ), $defaults );
}
function tca_install() {
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $table = esc_sql( tca_table() );
    $sql = "CREATE TABLE {$table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        source varchar(24) NOT NULL DEFAULT 'manual',
        source_id bigint(20) unsigned NOT NULL DEFAULT 0,
        kind varchar(24) NOT NULL DEFAULT 'expense',
        fiscal_date date NOT NULL,
        document_no varchar(100) NOT NULL DEFAULT '',
        party varchar(191) NOT NULL DEFAULT '',
        tax_id varchar(48) NOT NULL DEFAULT '',
        concept text NULL,
        amount_base decimal(18,2) NOT NULL DEFAULT 0.00,
        amount_tax decimal(18,2) NOT NULL DEFAULT 0.00,
        amount_total decimal(18,2) NOT NULL DEFAULT 0.00,
        deductible_pct decimal(5,2) NOT NULL DEFAULT 0.00,
        currency varchar(8) NOT NULL DEFAULT 'EUR',
        treatment varchar(24) NOT NULL DEFAULT 'review',
        status varchar(24) NOT NULL DEFAULT 'draft',
        notes text NULL,
        created_at datetime NOT NULL,
        updated_at datetime NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY source_identity (source,source_id),
        KEY fiscal_period (fiscal_date,kind),
        KEY state (status)
    ) {$wpdb->get_charset_collate()};";
    dbDelta( $sql );
    TCA_Provider_Invoices::install_schema();
    update_option( 'tca_db_version', TCA_DB_VERSION, false );
    if ( false === get_option( TCA_OPTION, false ) ) {
        add_option( TCA_OPTION, tca_settings(), '', false );
    }
}
register_activation_hook( __FILE__, 'tca_install' );
add_action( 'admin_init', static function () {
    if ( current_user_can( 'manage_woocommerce' ) && get_option( 'tca_db_version' ) !== TCA_DB_VERSION ) {
        tca_install();
    }
} );
function tca_capable() {
    return current_user_can( 'manage_woocommerce' );
}
function tca_admin_url( $tab = 'summary', $args = array() ) {
    return add_query_arg( array_merge( array( 'page' => 'taxocommerce-accounting', 'tab' => $tab ), $args ), admin_url( 'admin.php' ) );
}
function tca_menu() {
    $parent = function_exists( 'taxocommerce_core' ) || defined( 'TAXOCOMMERCE_CORE_VERSION' ) ? 'taxocommerce-core' : '';
    if ( $parent ) {
        add_submenu_page( $parent, __( 'Accounting', 'taxocommerce-accounting' ), __( 'Accounting', 'taxocommerce-accounting' ), 'manage_woocommerce', 'taxocommerce-accounting', 'tca_admin' );
    } else {
        add_menu_page( __( 'Accounting', 'taxocommerce-accounting' ), __( 'Accounting', 'taxocommerce-accounting' ), 'manage_woocommerce', 'taxocommerce-accounting', 'tca_admin', 'dashicons-calculator', 56 );
    }
}
add_action( 'admin_menu', 'tca_menu', 70 );
/**
 * Enqueue visual navigation styles only on the Accounting administration page.
 *
 * @param string $hook_suffix Current WordPress admin hook.
 * @return void
 */
function tca_enqueue_admin_styles( $hook_suffix ) {
    $suffix = '_page_taxocommerce-accounting';
    if ( $suffix !== substr( (string) $hook_suffix, -strlen( $suffix ) ) ) {
        return;
    }
    wp_enqueue_style(
        'taxocommerce-accounting-admin',
        plugin_dir_url( __FILE__ ) . 'assets/admin.css',
        array(),
        TCA_VERSION
    );
}
add_action( 'admin_enqueue_scripts', 'tca_enqueue_admin_styles' );

add_action( 'taxocommerce_core_register_modules', static function ( $core ) {
    if ( is_object( $core ) && method_exists( $core, 'register_module' ) ) {
        $core->register_module( 'accounting', array(
            'name' => 'TaxoCommerce Accounting', 'version' => TCA_VERSION,
            'status' => 'active', 'description' => 'Fiscal records and bookkeeping exports.',
            'admin_url' => tca_admin_url(),
        ) );
    }
} );
add_filter( 'taxocommerce_core_family_extensions', static function ( $extensions ) {
    $extensions['accounting'] = array(
        'id' => 'accounting', 'name' => 'TaxoCommerce Accounting',
        'plugin_file' => 'taxocommerce-accounting/taxocommerce-accounting.php',
        'org_slug' => 'taxocommerce-accounting', 'search_term' => 'TaxoCommerce Accounting',
        'wordpress_org' => false, 'admin_url' => tca_admin_url(),
        'description' => 'Fiscal records and bookkeeping exports.',
    );
    return $extensions;
} );

/** Database writes are always scoped to the plugin's own table. */
function tca_insert( $data ) {
    global $wpdb;
    $data = wp_parse_args( $data, array(
        'source' => 'manual', 'source_id' => 0, 'kind' => 'expense',
        'fiscal_date' => current_time( 'Y-m-d' ), 'document_no' => '',
        'party' => '', 'tax_id' => '', 'concept' => '',
        'amount_base' => 0, 'amount_tax' => 0, 'amount_total' => 0,
        'deductible_pct' => 0, 'currency' => 'EUR', 'treatment' => 'review',
        'status' => 'draft', 'notes' => '',
        'created_at' => current_time( 'mysql' ), 'updated_at' => current_time( 'mysql' ),
    ) );
    // Manual entries must have distinct source identities; unlike Woo orders they
    // have no external document ID. Never share the UNIQUE (source,source_id) key.
    if ( 'manual' === $data['source'] && ! absint( $data['source_id'] ) ) {
        $data['source_id'] = absint( hexdec( substr( str_replace( '-', '', wp_generate_uuid4() ), 0, 12 ) ) );
    }
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Write to the plugin's own ledger table.
    $saved = $wpdb->insert( tca_table(), $data );
    return $saved ? (int) $wpdb->insert_id : 0;
}
function tca_find_source( $source, $source_id ) {
    global $wpdb;
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Read latest ledger state; subsequent writes depend on it.
    return $wpdb->get_row( $wpdb->prepare(
        'SELECT * FROM %i WHERE source = %s AND source_id = %d LIMIT 1',
        tca_table(), $source, absint( $source_id )
    ) );
}
function tca_valid_date( $date ) {
    $date = (string) $date;
    if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) return false;
    $parts = explode( '-', $date );
    return checkdate( (int) $parts[1], (int) $parts[2], (int) $parts[0] );
}
function tca_decimal( $value ) {
    if ( ! is_scalar( $value ) ) return null;
    $value = trim( (string) $value );
    if ( ! preg_match( '/^-?\d{1,13}(?:\.\d{1,2})?$/', $value ) ) return null;
    return round( (float) $value, 2 );
}

/**
 * Synchronise a bounded page of paid orders. All new records are DRAFT.
 * Never change orders, invoices, previously validated entries or reimbursements.
 */
function tca_sync_sales( $page = 1 ) {
    if ( ! function_exists( 'wc_get_orders' ) ) return new WP_Error( 'tca_wc_missing', __( 'WooCommerce is unavailable.', 'taxocommerce-accounting' ) );
    $settings = tca_settings();
    $statuses = function_exists( 'wc_get_is_paid_statuses' ) ? wc_get_is_paid_statuses() : array( 'processing', 'completed' );
    $query = array(
        'type' => 'shop_order', 'limit' => 25, 'page' => max( 1, absint( $page ) ),
        'paginate' => true, 'orderby' => 'date', 'order' => 'ASC',
        'status' => $statuses, 'date_created' => '>=' . strtotime( $settings['start_date'] . ' 00:00:00' ),
    );
    $result = wc_get_orders( $query );
    if ( ! is_object( $result ) || ! isset( $result->orders ) ) return new WP_Error( 'tca_wc_query', __( 'Cannot read orders.', 'taxocommerce-accounting' ) );
    $created = 0;
    $skipped = 0;
    foreach ( $result->orders as $order ) {
        if ( ! $order instanceof WC_Order ) continue;
        $date = $order->get_date_created();
        if ( ! $date || $date->date( 'Y-m-d' ) < $settings['start_date'] ) continue;
        $already_imported = tca_find_source( 'woocommerce', $order->get_id() );
        $currency = (string) $order->get_currency();
        $tax = round( (float) $order->get_total_tax(), 2 );
        $total = round( (float) $order->get_total(), 2 );
        $country = (string) $order->get_billing_country();
        $treatment = $currency === $settings['currency'] && $country === $settings['country'] ? 'domestic' : 'review';
        if ( $already_imported ) {
            ++$skipped;
        } else {
        $inserted = tca_insert( array(
            'source' => 'woocommerce', 'source_id' => $order->get_id(),
            'kind' => 'sale', 'fiscal_date' => $date->date( 'Y-m-d' ),
            'party' => trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ),
            'tax_id' => '', 'concept' => sprintf( 'WooCommerce #%d', $order->get_id() ),
            'amount_base' => round( $total - $tax, 2 ),
            'amount_tax' => $tax, 'amount_total' => $total,
            'deductible_pct' => 0, 'currency' => $currency,
            'treatment' => $treatment, 'status' => 'draft',
            'notes' => __( 'Unverified order: reconcile the issued invoice, taxes and possible refunds before approval.', 'taxocommerce-accounting' ),
        ) );
        if ( $inserted ) ++$created;
        }
        // A refund may be issued after the original sale was imported.
        // Never skip refund discovery simply because the order already exists.
        foreach ( $order->get_refunds() as $refund ) {
            if ( ! $refund instanceof WC_Order_Refund || tca_find_source( 'wc_refund', $refund->get_id() ) ) continue;
            $refund_date = $refund->get_date_created();
            $amount = round( abs( (float) $refund->get_amount() ), 2 );
            // WooCommerce refunds may lack a tax split: always require manual review.
            $ref_tax = round( abs( (float) $refund->get_total_tax() ), 2 );
            if ( $amount > 0 ) {
                tca_insert( array(
                    'source' => 'wc_refund', 'source_id' => $refund->get_id(),
                    'kind' => 'refund', 'fiscal_date' => $refund_date ? $refund_date->date( 'Y-m-d' ) : $date->date( 'Y-m-d' ),
                    'party' => trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ),
                    'concept' => sprintf( 'Refund #%d / order #%d', $refund->get_id(), $order->get_id() ),
                    'amount_base' => -round( $amount - $ref_tax, 2 ),
                    'amount_tax' => -$ref_tax, 'amount_total' => -$amount,
                    'currency' => $currency, 'treatment' => 'review', 'status' => 'draft',
                    'notes' => __( 'Refund requires credit-note and VAT verification.', 'taxocommerce-accounting' ),
                ) );
            }
        }
    }
    return array( 'created' => $created, 'skipped' => $skipped, 'page' => absint( $page ), 'pages' => absint( $result->max_num_pages ) );
}
/**
 * Optional low-load background import into this plugin's own draft ledger.
 * It never changes orders, invoices, or validated accounting entries.
 */
function tca_schedule_sales_sync() {
    $enabled = ! empty( tca_settings()['auto_sync_sales'] );
    $next = wp_next_scheduled( 'tca_sales_auto_sync' );
    if ( $enabled && ! $next ) {
        wp_schedule_event( time() + 300, 'hourly', 'tca_sales_auto_sync' );
    } elseif ( ! $enabled && $next ) {
        wp_clear_scheduled_hook( 'tca_sales_auto_sync' );
    }
}
add_action( 'init', 'tca_schedule_sales_sync' );

function tca_background_sales_sync() {
    if ( empty( tca_settings()['auto_sync_sales'] ) ) {
        return;
    }
    $state = (array) get_option( 'tca_sales_sync_state', array() );
    $page = max( 1, absint( isset( $state['next_page'] ) ? $state['next_page'] : 1 ) );
    $created = 0;
    $scanned = 0;
    $total_pages = 0;
    for ( $batch = 0; $batch < 3; $batch++ ) {
        $result = tca_sync_sales( $page );
        if ( is_wp_error( $result ) ) {
            $state['last_error'] = $result->get_error_message();
            $state['last_checked'] = current_time( 'mysql' );
            $state['next_page'] = $page;
            update_option( 'tca_sales_sync_state', $state, false );
            return;
        }
        $created += (int) $result['created'];
        $scanned += (int) $result['created'] + (int) $result['skipped'];
        $total_pages = (int) $result['pages'];
        if ( $page >= $total_pages ) {
            $page = 1;
            $state['last_full_cycle'] = current_time( 'mysql' );
            break;
        }
        ++$page;
    }
    $state['last_checked'] = current_time( 'mysql' );
    $state['last_error'] = '';
    $state['next_page'] = $page;
    $state['last_created'] = $created;
    $state['last_scanned'] = $scanned;
    $state['total_pages'] = $total_pages;
    update_option( 'tca_sales_sync_state', $state, false );
}
add_action( 'tca_sales_auto_sync', 'tca_background_sales_sync' );
register_deactivation_hook( __FILE__, static function () {
    wp_clear_scheduled_hook( 'tca_sales_auto_sync' );
} );

function tca_input( $key ) {
    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Caller verifies the nonce before invoking this accessor.
    return isset( $_POST[ $key ] ) && is_scalar( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : '';
}
function tca_redirect( $tab, $message, $args = array() ) {
    wp_safe_redirect( tca_admin_url( $tab, array_merge( $args, array( 'notice' => $message ) ) ) );
    exit;
}
function tca_handle() {
    if ( ! tca_capable() ) wp_die( esc_html__( 'Insufficient permissions.', 'taxocommerce-accounting' ) );
    check_admin_referer( 'tca_action' );
    $operation = sanitize_key( tca_input( 'operation' ) );
    if ( 'settings' === $operation ) {
        $date = tca_input( 'start_date' );
        if ( ! tca_valid_date( $date ) ) tca_redirect( 'settings', 'invalid' );
        $old = tca_settings();
        $old['start_date'] = $date;
        $old['auto_sync_sales'] = '1' === tca_input( 'auto_sync_sales' ) ? 1 : 0;
        update_option( TCA_OPTION, $old, false );
        tca_redirect( 'settings', 'saved' );
    }
    if ( 'sync' === $operation ) {
        $page = max( 1, absint( tca_input( 'sync_page' ) ) );
        $res = tca_sync_sales( $page );
        if ( is_wp_error( $res ) ) tca_redirect( 'sales', 'error' );
        tca_redirect( 'sales', 'synced', array( 'sync_page' => $page, 'sync_pages' => $res['pages'] ) );
    }
    if ( 'save' === $operation ) {
        $kind = sanitize_key( tca_input( 'kind' ) );
        if ( ! in_array( $kind, array( 'purchase', 'expense', 'asset', 'intra' ), true ) ) tca_redirect( 'received', 'invalid' );
        $date = tca_input( 'fiscal_date' );
        $base = tca_decimal( tca_input( 'amount_base' ) );
        $tax = tca_decimal( tca_input( 'amount_tax' ) );
        $pct = tca_decimal( tca_input( 'deductible_pct' ) );
        if ( ! tca_valid_date( $date ) || null === $base || null === $tax || null === $pct || $base < 0 || $tax < 0 || $pct < 0 || $pct > 100 ) tca_redirect( 'received', 'invalid' );
        $document = tca_input( 'document_no' );
        $party = tca_input( 'party' );
        if ( '' === $document || '' === $party ) tca_redirect( 'received', 'invalid' );
        $entry = array(
            'kind' => $kind, 'fiscal_date' => $date, 'document_no' => $document,
            'party' => $party, 'tax_id' => tca_input( 'tax_id' ),
            'concept' => sanitize_textarea_field( tca_input( 'concept' ) ),
            'amount_base' => $base, 'amount_tax' => $tax,
            'amount_total' => round( $base + $tax, 2 ),
            'deductible_pct' => $pct,
            'currency' => tca_settings()['currency'], 'treatment' => sanitize_key( tca_input( 'treatment' ) ),
            'notes' => sanitize_textarea_field( tca_input( 'notes' ) ),
        );
        if ( ! in_array( $entry['treatment'], array( 'domestic', 'review', 'intra', 'exempt', 'reverse' ), true ) ) $entry['treatment'] = 'review';
        if ( ! tca_insert( $entry ) ) tca_redirect( 'received', 'error' );
        tca_redirect( 'received', 'saved' );
    }
    if ( 'validate' === $operation ) {
        global $wpdb;
        $id = absint( tca_input( 'entry_id' ) );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Ledger approval must inspect the latest stored record.
        $entry = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d LIMIT 1', tca_table(), $id ) );
        if ( ! $entry || 'draft' !== $entry->status ) tca_redirect( 'summary', 'invalid' );
        // Imported orders and refunds require the original/credit-note reference.
        $doc = tca_input( 'document_no' );
        if ( in_array( $entry->source, array( 'woocommerce', 'wc_refund' ), true ) && '' === $doc ) tca_redirect( 'sales', 'invalid' );
        $treatment = sanitize_key( tca_input( 'treatment' ) );
        if ( ! in_array( $treatment, array( 'domestic', 'intra', 'reverse', 'exempt' ), true ) ) tca_redirect( 'summary', 'review' );
        $data = array( 'status' => 'validated', 'treatment' => $treatment, 'updated_at' => current_time( 'mysql' ) );
        if ( '' !== $doc ) $data['document_no'] = $doc;
        if ( in_array( $entry->kind, array( 'purchase', 'expense', 'asset', 'intra' ), true ) ) {
            $deductible = tca_decimal( tca_input( 'deductible_pct' ) );
            if ( null === $deductible || $deductible < 0 || $deductible > 100 ) tca_redirect( 'received', 'invalid' );
            $data['deductible_pct'] = $deductible;
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Immutable post-approval fiscal record, own ledger.
        $wpdb->update( tca_table(), $data, array( 'id' => $id, 'status' => 'draft' ) );
        tca_redirect( 'summary', 'validated' );
    }
    tca_redirect( 'summary', 'invalid' );
}
add_action( 'admin_post_tca_action', 'tca_handle' );

function tca_rows( $year, $limit = 400 ) {
    global $wpdb;
    $start = sprintf( '%04d-01-01', $year );
    $end = sprintf( '%04d-12-31', $year );
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Fiscal report reads bounded current rows.
    return (array) $wpdb->get_results( $wpdb->prepare(
        'SELECT * FROM %i WHERE fiscal_date BETWEEN %s AND %s ORDER BY fiscal_date DESC, id DESC LIMIT %d',
        tca_table(), $start, $end, absint( $limit )
    ) );
}
function tca_summary( $rows ) {
    $totals = array();
    foreach ( $rows as $entry ) {
        $month = substr( $entry->fiscal_date, 0, 7 );
        if ( ! isset( $totals[ $month ] ) ) $totals[ $month ] = array( 'sales' => 0, 'purchase' => 0, 'tax_out' => 0, 'tax_in' => 0, 'pending' => 0 );
        if ( 'validated' !== $entry->status || 'domestic' !== $entry->treatment ) {
            ++$totals[ $month ]['pending'];
            continue;
        }
        if ( in_array( $entry->kind, array( 'sale', 'refund' ), true ) ) {
            $totals[ $month ]['sales'] += (float) $entry->amount_total;
            $totals[ $month ]['tax_out'] += (float) $entry->amount_tax;
        } elseif ( in_array( $entry->kind, array( 'purchase', 'expense', 'asset' ), true ) ) {
            $totals[ $month ]['purchase'] += (float) $entry->amount_total;
            $totals[ $month ]['tax_in'] += (float) $entry->amount_tax * (float) $entry->deductible_pct / 100;
        } else {
            ++$totals[ $month ]['pending'];
        }
    }
    ksort( $totals );
    return $totals;
}
function tca_csv_guard( $value ) {
    $value = (string) $value;
    if ( preg_match( '/^[=\+\-@\t\r]/', ltrim( $value ) ) ) return "'" . $value;
    return $value;
}
function tca_export() {
    if ( ! tca_capable() ) wp_die( esc_html__( 'Insufficient permissions.', 'taxocommerce-accounting' ) );
    check_admin_referer( 'tca_export' );
    $year = isset( $_GET['year'] ) ? absint( $_GET['year'] ) : (int) current_time( 'Y' );
    if ( $year < 2000 || $year > 2100 ) wp_die( esc_html__( 'Invalid year.', 'taxocommerce-accounting' ) );
    $type = isset( $_GET['format'] ) ? sanitize_key( wp_unslash( $_GET['format'] ) ) : 'csv';
    $rows = tca_rows( $year, 20000 );
    if ( 'json' === $type ) {
        $data = array( 'plugin' => 'taxocommerce-accounting', 'version' => TCA_VERSION, 'year' => $year, 'entries' => $rows, 'notice' => 'Internal fiscal ledger export; not an official tax submission.' );
        nocache_headers();
        header( 'Content-Type: application/json; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="accounting-' . $year . '.json"' );
        echo wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON download, not HTML.
        exit;
    }
    nocache_headers();
    header( 'Content-Type: text/csv; charset=utf-8' );
    header( 'Content-Disposition: attachment; filename="accounting-' . $year . '.csv"' );
    $out = fopen( 'php://output', 'w' );
    if ( false === $out ) exit;
    $fields = array( 'id', 'source', 'source_id', 'kind', 'fiscal_date', 'document_no', 'party', 'tax_id', 'concept', 'amount_base', 'amount_tax', 'amount_total', 'deductible_pct', 'currency', 'treatment', 'status', 'notes' );
    fputcsv( $out, $fields );
    foreach ( $rows as $row ) {
        $values = array();
        foreach ( $fields as $field ) {
            // Preserve signed numbers for correct refunds and bookkeeping math.
            if ( in_array( $field, array( 'id', 'source_id', 'amount_base', 'amount_tax', 'amount_total', 'deductible_pct' ), true ) ) {
                $values[] = (string) $row->$field;
            } else {
                $values[] = tca_csv_guard( $row->$field );
            }
        }
        fputcsv( $out, $values );
    }
    // php://output is closed by PHP at the end of this request.
    exit;
}
add_action( 'admin_post_tca_export', 'tca_export' );

function tca_money( $value ) {
    return number_format_i18n( (float) $value, 2 ) . ' €';
}
function tca_form_open( $operation ) {
    echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
    echo '<input type="hidden" name="action" value="tca_action"><input type="hidden" name="operation" value="' . esc_attr( $operation ) . '">';
    wp_nonce_field( 'tca_action' );
}
/**
 * All dynamic UI labels use static gettext literals, so WordPress can
 * discover their translations and Plugin Check can validate their usage.
 */
function tca_label( $label ) {
    $labels = array(
        'Date' => __( 'Date', 'taxocommerce-accounting' ),
        'Document' => __( 'Document', 'taxocommerce-accounting' ),
        'Party' => __( 'Party', 'taxocommerce-accounting' ),
        'Concept' => __( 'Concept', 'taxocommerce-accounting' ),
        'Base' => __( 'Base', 'taxocommerce-accounting' ),
        'VAT' => __( 'VAT', 'taxocommerce-accounting' ),
        'Total' => __( 'Total', 'taxocommerce-accounting' ),
        'Status' => __( 'Status', 'taxocommerce-accounting' ),
        'Action' => __( 'Action', 'taxocommerce-accounting' ),
        'Invoice number' => __( 'Invoice number', 'taxocommerce-accounting' ),
        'Supplier' => __( 'Supplier', 'taxocommerce-accounting' ),
        'Tax identification' => __( 'Tax identification', 'taxocommerce-accounting' ),
        'Taxable base' => __( 'Taxable base', 'taxocommerce-accounting' ),
        'Input VAT amount' => __( 'Input VAT amount', 'taxocommerce-accounting' ),
        'Deductible VAT percentage (review first)' => __( 'Deductible VAT percentage (review first)', 'taxocommerce-accounting' ),
        'Notes / document reference' => __( 'Notes / document reference', 'taxocommerce-accounting' ),
        'Requires review' => __( 'Requires review', 'taxocommerce-accounting' ),
        'Domestic VAT (verify)' => __( 'Domestic VAT (verify)', 'taxocommerce-accounting' ),
        'Intra-EU' => __( 'Intra-EU', 'taxocommerce-accounting' ),
        'Reverse charge' => __( 'Reverse charge', 'taxocommerce-accounting' ),
        'Exempt' => __( 'Exempt', 'taxocommerce-accounting' ),
        'Summary' => __( 'Summary', 'taxocommerce-accounting' ),
        'Sales' => __( 'Sales', 'taxocommerce-accounting' ),
        'Received invoices' => __( 'Received invoices', 'taxocommerce-accounting' ),
        'Expenses' => __( 'Expenses', 'taxocommerce-accounting' ),
        'Assets' => __( 'Assets', 'taxocommerce-accounting' ),
        'Tax returns' => __( 'Tax returns', 'taxocommerce-accounting' ),
        'Hacienda' => __( 'Hacienda', 'taxocommerce-accounting' ),
        'Exports' => __( 'Exports', 'taxocommerce-accounting' ),
        'Settings' => __( 'Settings', 'taxocommerce-accounting' ),
        'Autocheck' => __( 'Autocheck', 'taxocommerce-accounting' ),
        'Manual' => __( 'Manual', 'taxocommerce-accounting' ),
        'Month' => __( 'Month', 'taxocommerce-accounting' ),
        'Purchases / expenses' => __( 'Purchases / expenses', 'taxocommerce-accounting' ),
        'Output VAT' => __( 'Output VAT', 'taxocommerce-accounting' ),
        'Deductible input VAT' => __( 'Deductible input VAT', 'taxocommerce-accounting' ),
        'Difference' => __( 'Difference', 'taxocommerce-accounting' ),
        'Pending review' => __( 'Pending review', 'taxocommerce-accounting' ),
    );
    return isset( $labels[ $label ] ) ? $labels[ $label ] : (string) $label;
}
function tca_table_rows( $rows, $kind = '' ) {
    $documents = TCA_Provider_Invoices::documents_for_rows( $rows );
    echo '<div style="overflow-x:auto"><table class="widefat striped"><thead><tr>';
    foreach ( array( 'Date', 'Document', 'Party', 'Concept', 'Base', 'VAT', 'Total', 'Status', 'Action' ) as $label ) {
        echo '<th>' . esc_html( tca_label( $label ) ) . '</th>';
    }
    echo '</tr></thead><tbody>';
    $count = 0;
    foreach ( $rows as $row ) {
        if ( $kind && $kind !== $row->kind ) continue;
        ++$count;
        echo '<tr><td>' . esc_html( $row->fiscal_date ) . '</td><td>' . esc_html( $row->document_no ) . '</td><td>' . esc_html( $row->party ) . '</td><td>' . esc_html( $row->concept ) . '</td>';
        echo '<td>' . esc_html( tca_money( $row->amount_base ) ) . '</td><td>' . esc_html( tca_money( $row->amount_tax ) ) . '</td><td>' . esc_html( tca_money( $row->amount_total ) ) . '</td>';
        echo '<td>' . esc_html( $row->status . ' / ' . $row->treatment ) . '</td><td>';
        if ( 'draft' === $row->status ) {
            tca_form_open( 'validate' );
            echo '<input type="hidden" name="entry_id" value="' . esc_attr( $row->id ) . '">';
            if ( in_array( $row->source, array( 'woocommerce', 'wc_refund' ), true ) ) {
                echo '<input name="document_no" aria-label="' . esc_attr__( 'Issued invoice number', 'taxocommerce-accounting' ) . '" placeholder="' . esc_attr__( 'Invoice number', 'taxocommerce-accounting' ) . '" required>';
            }
            echo '<label>' . esc_html__( 'VAT treatment', 'taxocommerce-accounting' ) . ' <select name="treatment" required>';
            foreach ( array( 'review' => 'Requires review', 'domestic' => 'Domestic VAT (verify)', 'intra' => 'Intra-EU', 'reverse' => 'Reverse charge', 'exempt' => 'Exempt' ) as $key => $label ) {
                echo '<option value="' . esc_attr( $key ) . '" ' . selected( $row->treatment, $key, false ) . '>' . esc_html( tca_label( $label ) ) . '</option>';
            }
            echo '</select></label>';
            if ( in_array( $row->kind, array( 'purchase', 'expense', 'asset', 'intra' ), true ) ) {
                echo '<label>' . esc_html__( 'Deductible VAT percentage (review first)', 'taxocommerce-accounting' ) . ' <input type="number" min="0" max="100" step="0.01" name="deductible_pct" value="' . esc_attr( $row->deductible_pct ) . '" required></label>';
            }
            submit_button( __( 'Validate', 'taxocommerce-accounting' ), 'secondary small', 'submit', false );
            echo '</form>';
        } else {
            echo esc_html( '—' );
        }
        TCA_Provider_Invoices::render_row_controls( $row, $documents );
        echo '</td></tr>';
    }
    if ( ! $count ) echo '<tr><td colspan="9">' . esc_html__( 'No entries in this section.', 'taxocommerce-accounting' ) . '</td></tr>';
    echo '</tbody></table></div>';
}
function tca_new_invoice_form( $kind ) {
    tca_form_open( 'save' );
    echo '<h3>' . esc_html__( 'Add received invoice / expense', 'taxocommerce-accounting' ) . '</h3><table class="form-table">';
    $fields = array(
        'fiscal_date' => array( 'Date', 'date', current_time( 'Y-m-d' ) ),
        'document_no' => array( 'Invoice number', 'text', '' ),
        'party' => array( 'Supplier', 'text', '' ),
        'tax_id' => array( 'Tax identification', 'text', '' ),
        'concept' => array( 'Concept', 'text', '' ),
        'amount_base' => array( 'Taxable base', 'number', '' ),
        'amount_tax' => array( 'Input VAT amount', 'number', '' ),
        'deductible_pct' => array( 'Deductible VAT percentage (review first)', 'number', '0' ),
        'notes' => array( 'Notes / document reference', 'text', '' ),
    );
    echo '<input type="hidden" name="kind" value="' . esc_attr( $kind ) . '">';
    foreach ( $fields as $id => $spec ) {
        echo '<tr><th><label for="tca-' . esc_attr( $id ) . '">' . esc_html( tca_label( $spec[0] ) ) . '</label></th><td>';
        echo '<input class="regular-text" id="tca-' . esc_attr( $id ) . '" name="' . esc_attr( $id ) . '" type="' . esc_attr( $spec[1] ) . '" value="' . esc_attr( $spec[2] ) . '"';
        if ( 'number' === $spec[1] ) echo ' step="0.01" min="0"';
        if ( in_array( $id, array( 'fiscal_date', 'document_no', 'party', 'amount_base', 'amount_tax' ), true ) ) echo ' required';
        echo '></td></tr>';
    }
    echo '<tr><th><label for="tca-treatment">' . esc_html__( 'VAT treatment', 'taxocommerce-accounting' ) . '</label></th><td><select id="tca-treatment" name="treatment">';
    foreach ( array( 'review' => 'Requires review', 'domestic' => 'Domestic VAT (verify)', 'intra' => 'Intra-EU', 'reverse' => 'Reverse charge', 'exempt' => 'Exempt' ) as $key => $label ) {
        echo '<option value="' . esc_attr( $key ) . '">' . esc_html( tca_label( $label ) ) . '</option>';
    }
    echo '</select></td></tr></table>';
    submit_button( __( 'Save draft', 'taxocommerce-accounting' ) );
    echo '</form>';
}
function tca_autocheck( $rows ) {
    global $wpdb;
    $settings = tca_settings();
    $tests = array(
        'woocommerce' => function_exists( 'wc_get_orders' ) ? 'pass' : 'fail',
        'schema' => get_option( 'tca_db_version' ) === TCA_DB_VERSION ? 'pass' : 'fail',
        'start_date' => tca_valid_date( $settings['start_date'] ) ? 'pass' : 'fail',
        'ledger_read' => empty( $wpdb->last_error ) ? 'pass' : 'fail',
        'entries_pending' => count( array_filter( $rows, static function ( $row ) { return 'draft' === $row->status; } ) ) ? 'fail' : 'pass',
        'treatment_review' => count( array_filter( $rows, static function ( $row ) { return 'review' === $row->treatment; } ) ) ? 'fail' : 'pass',
        'unverified_invoices' => count( array_filter( $rows, static function ( $row ) { return 'sale' === $row->kind && '' === $row->document_no; } ) ) ? 'fail' : 'pass',
    );
    $rows = array();
    $totals = array( 'pass' => 0, 'fail' => 0, 'skip' => 0 );
    foreach ( $tests as $name => $status ) {
        $rows[] = array( 'id' => $name, 'status' => $status, 'message' => $status === 'pass' ? 'OK' : 'Requires review' );
        ++$totals[ $status ];
    }
    return array( 'version' => TCA_VERSION, 'executed_at' => gmdate( 'c' ),
        'date' => current_time( 'mysql' ), 'rows' => $rows, 'totals' => $totals,
        'pass' => $totals['pass'], 'fail' => $totals['fail'] );
}
function tca_autocheck_download() {
    if ( ! tca_capable() ) wp_die( esc_html__( 'Insufficient permissions.', 'taxocommerce-accounting' ) );
    check_admin_referer( 'tca_autocheck_download' );
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verified immediately above.
    $year = isset( $_GET['year'] ) ? absint( $_GET['year'] ) : (int) current_time( 'Y' );
    if ( $year < 2000 || $year > 2100 ) wp_die( esc_html__( 'Invalid year.', 'taxocommerce-accounting' ) );
    $report = tca_autocheck( tca_rows( $year ) );
    nocache_headers();
    header( 'Content-Type: application/json; charset=utf-8' );
    header( 'X-Content-Type-Options: nosniff' );
    header( 'Content-Disposition: attachment; filename="accounting-autocheck-' . $year . '.json"' );
    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON response (not HTML).
    echo wp_json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE );
    exit;
}
add_action( 'admin_post_tca_autocheck_download', 'tca_autocheck_download' );

function tca_admin() {
    if ( ! tca_capable() ) wp_die( esc_html__( 'Insufficient permissions.', 'taxocommerce-accounting' ) );
    $tabs = array(
        'summary' => 'Summary', 'sales' => 'Sales', 'received' => 'Received invoices',
        'expenses' => 'Expenses', 'assets' => 'Assets', 'intra' => 'Intra-EU',
        'hacienda' => 'Hacienda', 'exports' => 'Exports', 'settings' => 'Settings',
        'autocheck' => 'Autocheck', 'manual' => 'Manual',
    );
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only tab/year filters.
    $tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'summary';
    // The former Tax returns URL remains functional after the UI consolidation.
    if ( 'models' === $tab ) $tab = 'hacienda';
    if ( ! isset( $tabs[ $tab ] ) ) $tab = 'summary';
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only year selector.
    $year = isset( $_GET['year'] ) ? absint( $_GET['year'] ) : (int) current_time( 'Y' );
    if ( $year < 2000 || $year > 2100 ) $year = (int) current_time( 'Y' );
    $rows = tca_rows( $year );
    $summary = tca_summary( $rows );
    echo '<div class="wrap tca-accounting-admin"><h1>' . esc_html__( 'TaxoCommerce Accounting', 'taxocommerce-accounting' ) . ' <small>v' . esc_html( TCA_VERSION ) . '</small></h1>';
    echo '<p>' . esc_html__( 'Fiscal register, VAT review, supplier invoices and export. No tax return is filed automatically.', 'taxocommerce-accounting' ) . '</p>';
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only notice key.
    $notice = isset( $_GET['notice'] ) ? sanitize_key( wp_unslash( $_GET['notice'] ) ) : '';
    if ( $notice ) {
        $invoice_notices = array(
            'invoice_uploaded'        => __( 'Supplier invoice PDF securely attached to the purchase.', 'taxocommerce-accounting' ),
            'invoice_invalid_upload' => __( 'Select a valid PDF smaller than 10 MB.', 'taxocommerce-accounting' ),
            'invoice_storage'        => __( 'Could not store the encrypted supplier invoice. No accounting data was changed.', 'taxocommerce-accounting' ),
            'tca_invoice_storage'    => __( 'Could not store the encrypted supplier invoice. No accounting data was changed.', 'taxocommerce-accounting' ),
            'tca_invoice_crypto'     => __( 'Secure invoice encryption is unavailable.', 'taxocommerce-accounting' ),
            'invoice_not_found'      => __( 'The purchase record was not found.', 'taxocommerce-accounting' ),
        );
        echo '<div class="notice notice-info"><p>' . esc_html( isset( $invoice_notices[ $notice ] ) ? $invoice_notices[ $notice ] : $notice ) . '</p></div>';
    }
    echo '<nav class="nav-tab-wrapper" aria-label="' . esc_attr__( 'Accounting sections', 'taxocommerce-accounting' ) . '">';
    foreach ( $tabs as $key => $label ) echo '<a class="nav-tab ' . ( $tab === $key ? 'nav-tab-active' : '' ) . '" href="' . esc_url( tca_admin_url( $key, array( 'year' => $year ) ) ) . '">' . esc_html( tca_label( $label ) ) . '</a>';
    echo '</nav><div style="margin-top:18px">';
    echo '<p><strong>' . esc_html__( 'Financial year:', 'taxocommerce-accounting' ) . '</strong> ' . esc_html( $year ) . ' &nbsp; ';
    echo '<a href="' . esc_url( tca_admin_url( $tab, array( 'year' => $year - 1 ) ) ) . '">←</a> &nbsp; <a href="' . esc_url( tca_admin_url( $tab, array( 'year' => $year + 1 ) ) ) . '">→</a></p>';

    if ( 'summary' === $tab ) {
        $months = range( 1, 12 );
        echo '<h2>' . esc_html__( 'Monthly fiscal summary (validated domestic entries only)', 'taxocommerce-accounting' ) . '</h2><table class="widefat striped"><thead><tr>';
        foreach ( array( 'Month', 'Sales', 'Purchases / expenses', 'Output VAT', 'Deductible input VAT', 'Difference', 'Pending review' ) as $heading ) echo '<th>' . esc_html( tca_label( $heading ) ) . '</th>';
        echo '</tr></thead><tbody>';
        foreach ( $months as $m ) {
            $key = sprintf( '%04d-%02d', $year, $m );
            $v = $summary[ $key ] ?? array( 'sales'=>0,'purchase'=>0,'tax_out'=>0,'tax_in'=>0,'pending'=>0 );
            echo '<tr><td>' . esc_html( $key ) . '</td><td>' . esc_html( tca_money( $v['sales'] ) ) . '</td><td>' . esc_html( tca_money( $v['purchase'] ) ) . '</td><td>' . esc_html( tca_money( $v['tax_out'] ) ) . '</td><td>' . esc_html( tca_money( $v['tax_in'] ) ) . '</td><td>' . esc_html( tca_money( $v['tax_out'] - $v['tax_in'] ) ) . '</td><td>' . esc_html( (string) $v['pending'] ) . '</td></tr>';
        }
        echo '</tbody></table>';
        if ( count( $rows ) >= 400 ) echo '<p class="notice notice-warning">' . esc_html__( 'Year contains over 400 entries; use the exports for full reporting. This dashboard is limited.', 'taxocommerce-accounting' ) . '</p>';
    } elseif ( 'hacienda' === $tab ) {
        TCA_Hacienda::render( $year );
    } elseif ( 'sales' === $tab ) {
        echo '<h2>' . esc_html__( 'WooCommerce sales / refunds', 'taxocommerce-accounting' ) . '</h2>';
        echo '<p>' . esc_html__( 'Imports only paid-status orders dated after the configured start. All imported records remain drafts until invoice and VAT verification; repeated imports do not duplicate them.', 'taxocommerce-accounting' ) . '</p>';
        tca_form_open( 'sync' );
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only pagination key.
        $page = isset( $_GET['sync_page'] ) ? max( 1, absint( $_GET['sync_page'] ) ) : 1;
        echo '<input type="hidden" name="sync_page" value="' . esc_attr( (string) $page ) . '">';
        /* translators: %d: the WooCommerce sales-import batch page number. */
        submit_button( sprintf( __( 'Import page %d (up to 25 orders)', 'taxocommerce-accounting' ), $page ), 'primary', 'submit', false );
        echo '</form>';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only next-page link.
        $max = isset( $_GET['sync_pages'] ) ? absint( $_GET['sync_pages'] ) : 0;
        if ( $max > $page ) echo '<p><a href="' . esc_url( tca_admin_url( 'sales', array( 'sync_page' => $page + 1, 'sync_pages' => $max ) ) ) . '">' . esc_html__( 'Next import page', 'taxocommerce-accounting' ) . '</a></p>';
        tca_table_rows( array_filter( $rows, static function ( $row ) { return in_array( $row->kind, array( 'sale', 'refund' ), true ); } ) );
    } elseif ( in_array( $tab, array( 'received', 'expenses', 'assets', 'intra' ), true ) ) {
        $kind = array( 'received' => 'purchase', 'expenses' => 'expense', 'assets' => 'asset', 'intra' => 'intra' )[ $tab ];
        tca_new_invoice_form( $kind );
        tca_table_rows( $rows, $kind );
    } elseif ( 'exports' === $tab ) {
        echo '<h2>' . esc_html__( 'Internal exports', 'taxocommerce-accounting' ) . '</h2>';
        echo '<p>' . esc_html__( 'CSV and JSON are internal fiscal registers, not official AEAT files. Each export has a 20,000-row upper bound.', 'taxocommerce-accounting' ) . '</p>';
        foreach ( array( 'csv' => 'CSV', 'json' => 'JSON' ) as $format => $label ) {
            $url = wp_nonce_url( add_query_arg( array( 'action' => 'tca_export', 'year' => $year, 'format' => $format ), admin_url( 'admin-post.php' ) ), 'tca_export' );
            echo '<p><a class="button button-secondary" href="' . esc_url( $url ) . '">' . esc_html( $label ) . ' ' . esc_html( $year ) . '</a></p>';
        }
    } elseif ( 'settings' === $tab ) {
        $settings = tca_settings();
        echo '<h2>' . esc_html__( 'Fiscal ledger settings', 'taxocommerce-accounting' ) . '</h2>';
        tca_form_open( 'settings' );
        echo '<p><label>' . esc_html__( 'First real commercial date:', 'taxocommerce-accounting' ) . ' <input type="date" name="start_date" value="' . esc_attr( $settings['start_date'] ) . '" required></label></p>';
        echo '<p>' . esc_html__( 'Base country:', 'taxocommerce-accounting' ) . ' ' . esc_html( $settings['country'] ) . ' &nbsp; ' . esc_html__( 'Currency:', 'taxocommerce-accounting' ) . ' ' . esc_html( $settings['currency'] ) . '</p>';
        echo '<p>' . esc_html__( 'Changing this date does not erase previously imported records.', 'taxocommerce-accounting' ) . '</p>';
        echo '<p><label><input type="checkbox" name="auto_sync_sales" value="1" ' . checked( 1, (int) $settings['auto_sync_sales'], false ) . '> ' . esc_html__( 'Import new paid WooCommerce orders automatically in small background batches', 'taxocommerce-accounting' ) . '</label></p>';
        echo '<p class="description">' . esc_html__( 'Uses WordPress WP-Cron while the website receives visits. Imported sales and refunds remain drafts until invoice numbers, dates and tax treatment are verified. Does not import supplier bills or mark taxes as ready.', 'taxocommerce-accounting' ) . '</p>';
        submit_button( __( 'Save settings', 'taxocommerce-accounting' ) );
        echo '</form>';
    } elseif ( 'autocheck' === $tab ) {
        $report = tca_autocheck( $rows );
        echo '<h2>' . esc_html__( 'Read-only functional Autocheck', 'taxocommerce-accounting' ) . '</h2>';
        echo '<p>' . esc_html__( 'Fecha de ejecución:', 'taxocommerce-accounting' ) . ' ' . esc_html( $report['date'] ) . '</p>';
        echo '<p>' . esc_html( $report['pass'] ) . ' PASS / ' . esc_html( $report['fail'] ) . ' FAIL</p><table class="widefat striped">';
        foreach ( $report['rows'] as $check ) echo '<tr><td>' . esc_html( $check['id'] ) . '</td><td>' . esc_html( strtoupper( $check['status'] ) ) . '</td></tr>';
        echo '</table><p>' . esc_html__( 'Failure of draft/review checks means fiscal data needs validation, not that the plugin runtime has failed.', 'taxocommerce-accounting' ) . '</p>';
        $autocheck_url = wp_nonce_url( add_query_arg( array( 'action' => 'tca_autocheck_download', 'year' => $year ), admin_url( 'admin-post.php' ) ), 'tca_autocheck_download' );
        echo '<p><a class="button button-secondary" href="' . esc_url( $autocheck_url ) . '">' . esc_html__( 'Download Autocheck JSON', 'taxocommerce-accounting' ) . '</a></p>';
    } else {
        echo '<h2>' . esc_html__( 'User guide', 'taxocommerce-accounting' ) . '</h2>';
        echo '<p>' . esc_html__( '1. Set the start date; 2. import paid WooCommerce orders page by page; 3. enter actual supplier invoices and expenses; 4. verify tax classification and the issued invoice before validating an entry; 5. review months and export the ledger for your accountant.', 'taxocommerce-accounting' ) . '</p>';
        echo '<h2>' . esc_html__( 'Developer guide', 'taxocommerce-accounting' ) . '</h2>';
        echo '<p>' . esc_html__( 'All records live in a dedicated WordPress-prefix table, with unique source/source_id keys. Imports use WooCommerce WC_Order APIs and never change orders. No TaxoCommerce sibling is required. Validated entries are immutable; corrections need a new adjustment record in a later version.', 'taxocommerce-accounting' ) . '</p>';
        echo '<p>' . esc_html__( 'Supplier PDF originals can be attached to saved purchase and expense rows. Downloads require WordPress authentication. The files are encrypted at rest using the current site salts; preserve those salts in backups. CSV/JSON exports do not include PDFs.', 'taxocommerce-accounting' ) . '</p>';
    }
    echo '</div></div>';
}
