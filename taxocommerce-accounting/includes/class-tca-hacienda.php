<?php
/**
 * Read-only preparatory tax workspace for the Spanish Tax Agency (AEAT).
 *
 * These downloads are NOT official AEAT return files, and cannot be filed.
 * They intentionally do not guess missing fiscal classifications, NIFs,
 * accounting balances or official record layouts.
 *
 * @package TaxoCommerceAccounting
 */

defined( 'ABSPATH' ) || exit;

/**
 * Tax workbooks built only from the store's own accounting ledger.
 */
final class TCA_Hacienda {
    const MAX_EXPORT = 20000;
    const BATCH = 250;

    /**
     * Known reporting views; not a statement of any merchant's tax obligations.
     *
     * @return array
     */
    public static function sections() {
        $books = 'https://sede.agenciatributaria.gob.es/Sede/iva/libros-registro.html';
        return array(
            'issued'   => array(
                'name' => __( 'Issued invoices book', 'taxocommerce-accounting' ),
                'detail' => __( 'Sales and refunds registered in the local ledger. Verify invoice series, dates, tax details and corrections.', 'taxocommerce-accounting' ),
                'scope' => 'issued', 'url' => $books,
            ),
            'received' => array(
                'name' => __( 'Received invoices book', 'taxocommerce-accounting' ),
                'detail' => __( 'Supplier invoices and expenses. Confirm deductible VAT and retain the original purchase documents.', 'taxocommerce-accounting' ),
                'scope' => 'received', 'url' => $books,
            ),
            'assets'   => array(
                'name' => __( 'Investment goods book', 'taxocommerce-accounting' ),
                'detail' => __( 'Review capital goods separately; adjustments and asset-specific information are not calculated.', 'taxocommerce-accounting' ),
                'scope' => 'assets', 'url' => $books,
            ),
            'intra'    => array(
                'name' => __( 'Intra-EU operations book', 'taxocommerce-accounting' ),
                'detail' => __( 'Review transactions classified as intra-EU. Check VAT ID, transaction type and country before filing.', 'taxocommerce-accounting' ),
                'scope' => 'intra', 'url' => $books,
            ),
            '303'      => array(
                'name' => __( 'Model 303 — VAT', 'taxocommerce-accounting' ),
                'detail' => __( 'Indicative quarterly domestic VAT balance only. Special regimes, non-domestic transactions, adjustments and the official return boxes are missing.', 'taxocommerce-accounting' ),
                'scope' => 'summary', 'url' => 'https://sede.agenciatributaria.gob.es/Sede/ayuda/consultas-informaticas/presentacion-declaraciones-ayuda-tecnica/modelo-303.html',
            ),
            '390'      => array(
                'name' => __( 'Model 390 — annual VAT', 'taxocommerce-accounting' ),
                'detail' => __( 'Indicative annual domestic VAT summary. Exemptions, carryovers, official totals and filing eligibility require separate verification.', 'taxocommerce-accounting' ),
                'scope' => 'summary', 'url' => 'https://sede.agenciatributaria.gob.es/Sede/ayuda/consultas-informaticas/presentacion-declaraciones-ayuda-tecnica/modelo-390.html',
            ),
            '347'      => array(
                'name' => __( 'Model 347 — third-party transactions', 'taxocommerce-accounting' ),
                'detail' => __( 'Raw sales and purchase movements for reconciliation. The statutory thresholds, exclusions, counterparties and declaration records are NOT calculated.', 'taxocommerce-accounting' ),
                'scope' => 'all', 'url' => 'https://sede.agenciatributaria.gob.es/Sede/ayuda/consultas-informaticas/declaraciones-informativas-ayuda-tecnica/modelos-291-347/modelo-347-presentacion-mediante-formulario.html',
            ),
            '349'      => array(
                'name' => __( 'Model 349 — intra-EU transactions', 'taxocommerce-accounting' ),
                'detail' => __( 'Only transactions tagged as intra-EU are listed. Fiscal operation keys, partner VAT IDs, required periodicity and official files are not produced.', 'taxocommerce-accounting' ),
                'scope' => 'intra', 'url' => 'https://sede.agenciatributaria.gob.es/Sede/iva/iva-operaciones-comercio-exterior/identificacion-realizar-operaciones-otros-empresarios-ue/modelo-349.html',
            ),
            '200'      => array(
                'name' => __( 'Model 200 — corporate income tax', 'taxocommerce-accounting' ),
                'detail' => __( 'Only raw transactions are available. A balance sheet, profit and loss account, tax adjustments and applicability checks are not implemented.', 'taxocommerce-accounting' ),
                'scope' => 'all', 'url' => 'https://sede.agenciatributaria.gob.es/Sede/impuesto-sobre-sociedades/gestion-impuesto-sobre-sociedades/modelos-declaracion.html',
            ),
        );
    }

    /**
     * Keep fiscal-year and quarter filters constrained to stable dates.
     *
     * @param int $year Calendar year.
     * @param int $quarter Quarter for the 303 only; 0 means the whole year.
     * @return array
     */
    public static function dates( $year, $quarter = 0 ) {
        $year = (int) $year;
        $quarter = (int) $quarter;
        if ( $quarter >= 1 && $quarter <= 4 ) {
            $start_month = ( ( $quarter - 1 ) * 3 ) + 1;
            $end_month = $start_month + 2;
            $last_day = (int) gmdate( 't', gmmktime( 0, 0, 0, $end_month, 1, $year ) );
            return array(
                sprintf( '%04d-%02d-01', $year, $start_month ),
                sprintf( '%04d-%02d-%02d', $year, $end_month, $last_day ),
            );
        }
        return array( sprintf( '%04d-01-01', $year ), sprintf( '%04d-12-31', $year ) );
    }

    /**
     * Full-dataset database aggregation: does NOT inherit the dashboard's
     * 400-row display limit. Figures remain indicative, not AEAT boxes.
     *
     * @param int $year Calendar year.
     * @param int $quarter 0 or selected quarter.
     * @return array
     */
    public static function vat_summary( $year, $quarter = 0 ) {
        global $wpdb;
        list( $start, $end ) = self::dates( $year, $quarter );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Full financial-year aggregate of the plugin's own fiscal table.
        $groups = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT SUBSTRING(fiscal_date, 1, 7) AS period, kind, status, treatment, COUNT(*) AS entry_count,
                    SUM(amount_base) AS amount_base, SUM(amount_total) AS amount_total, SUM(amount_tax) AS amount_tax,
                    SUM(amount_tax * deductible_pct / 100) AS deductible_tax
                FROM %i WHERE fiscal_date BETWEEN %s AND %s
                GROUP BY SUBSTRING(fiscal_date, 1, 7), kind, status, treatment
                ORDER BY period ASC',
                tca_table(),
                $start,
                $end
            )
        );
        $months = array();
        foreach ( (array) $groups as $group ) {
            $month = (string) $group->period;
            if ( ! isset( $months[ $month ] ) ) {
                $months[ $month ] = array(
                    'month' => $month, 'sales' => 0.0, 'purchases' => 0.0,
                    'sales_base' => 0.0, 'purchases_base' => 0.0,
                    'output_vat' => 0.0, 'deductible_input_vat' => 0.0,
                    'pending_review' => 0, 'entries' => 0,
                );
            }
            $count = (int) $group->entry_count;
            $months[ $month ]['entries'] += $count;
            if ( 'validated' !== $group->status || 'domestic' !== $group->treatment ) {
                $months[ $month ]['pending_review'] += $count;
            } elseif ( in_array( $group->kind, array( 'sale', 'refund' ), true ) ) {
                $months[ $month ]['sales'] += (float) $group->amount_total;
                $months[ $month ]['sales_base'] += (float) $group->amount_base;
                $months[ $month ]['output_vat'] += (float) $group->amount_tax;
            } elseif ( in_array( $group->kind, array( 'purchase', 'expense', 'asset' ), true ) ) {
                $months[ $month ]['purchases'] += (float) $group->amount_total;
                $months[ $month ]['purchases_base'] += (float) $group->amount_base;
                $months[ $month ]['deductible_input_vat'] += (float) $group->deductible_tax;
            } else {
                $months[ $month ]['pending_review'] += $count;
            }
        }
        ksort( $months );
        foreach ( $months as &$month ) {
            $month['difference'] = round( $month['output_vat'] - $month['deductible_input_vat'], 2 );
            foreach ( array( 'sales', 'sales_base', 'purchases', 'purchases_base', 'output_vat', 'deductible_input_vat' ) as $field ) {
                $month[ $field ] = round( $month[ $field ], 2 );
            }
        }
        unset( $month );
        return array_values( $months );
    }

    /**
     * Native prepared SQL filters, no dynamic SQL constructed from user input.
     *
     * @param int $year Reporting year.
     * @param string $scope One of fixed internal scopes.
     * @param int $limit Page size.
     * @param int $offset Page offset.
     * @param bool $count Return record count instead of page.
     * @return array|int
     */
    public static function ledger( $year, $scope, $limit = 20, $offset = 0, $count = false ) {
        global $wpdb;
        list( $start, $end ) = self::dates( $year );
        $scope = in_array( $scope, array( 'all', 'issued', 'received', 'assets', 'intra' ), true ) ? $scope : 'all';
        // The two queries use fixed, literal SQL and bind every data value.
        if ( $count ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Read-only count of the plugin's own ledger, no WordPress API equivalent.
            return (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM %i WHERE fiscal_date BETWEEN %s AND %s AND
                    ( %s = 'all'
                      OR ( %s = 'issued' AND kind IN ('sale', 'refund') )
                      OR ( %s = 'received' AND kind IN ('purchase', 'expense') )
                      OR ( %s = 'assets' AND kind = 'asset' )
                      OR ( %s = 'intra' AND ( kind = 'intra' OR treatment = 'intra' ) )
                    )",
                    tca_table(), $start, $end, $scope, $scope, $scope, $scope, $scope
                )
            );
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Limited, paginated read-only ledger query.
        return (array) $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, source, source_id, kind, fiscal_date, document_no, party,
                    tax_id, concept, amount_base, amount_tax, amount_total,
                    deductible_pct, currency, treatment, status, notes
                FROM %i WHERE fiscal_date BETWEEN %s AND %s AND
                ( %s = 'all'
                  OR ( %s = 'issued' AND kind IN ('sale', 'refund') )
                  OR ( %s = 'received' AND kind IN ('purchase', 'expense') )
                  OR ( %s = 'assets' AND kind = 'asset' )
                  OR ( %s = 'intra' AND ( kind = 'intra' OR treatment = 'intra' ) )
                ) ORDER BY fiscal_date ASC, id ASC LIMIT %d OFFSET %d",
                tca_table(), $start, $end, $scope, $scope, $scope, $scope, $scope,
                absint( $limit ), absint( $offset )
            )
        );
    }

    /**
     * Read-only completeness counters for the selected fiscal period.
     * These counters report ledger coverage, not whether a return is legally ready.
     *
     * @param int $year Calendar year.
     * @param int $quarter 0 for full year, or 1-4.
     * @return array
     */
    public static function readiness( $year, $quarter = 0 ) {
        global $wpdb;
        list( $start, $end ) = self::dates( $year, $quarter );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Read-only aggregate of the plugin's own ledger, used for live tax-preparation warnings.
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT kind, status, treatment, COUNT(*) AS entry_count,
                    SUM(CASE WHEN document_no = %s THEN 1 ELSE 0 END) AS missing_document,
                    SUM(CASE WHEN tax_id = %s THEN 1 ELSE 0 END) AS missing_tax_id
                FROM %i WHERE fiscal_date BETWEEN %s AND %s
                GROUP BY kind, status, treatment',
                '', '', tca_table(), $start, $end
            )
        );
        $result = array(
            'total' => 0,
            'pending' => 0,
            'other_treatments' => 0,
            'missing_document' => 0,
            'missing_tax_id' => 0,
        );
        foreach ( (array) $rows as $row ) {
            $count = (int) $row->entry_count;
            $result['total'] += $count;
            if ( 'validated' !== $row->status ) {
                $result['pending'] += $count;
            }
            if ( 'domestic' !== $row->treatment ) {
                $result['other_treatments'] += $count;
            }
            $result['missing_document'] += (int) $row->missing_document;
            // Not every transaction legally needs a customer tax ID, so this is
            // informational rather than a blanket blocking error.
            $result['missing_tax_id'] += (int) $row->missing_tax_id;
        }
        return $result;
    }

    /**
     * Explain fiscal preparation without claiming eligibility or filing readiness.
     *
     * @param int $year Calendar year.
     * @param int $quarter Selected quarter.
     * @return void
     */
    private static function render_readiness( $year, $quarter ) {
        $data = self::readiness( $year, $quarter );
        echo '<h3>' . esc_html__( 'Preparation checklist', 'taxocommerce-accounting' ) . '</h3>';
        $settings = tca_settings();
        $sync_state = (array) get_option( 'tca_sales_sync_state', array() );
        echo '<p><strong>' . esc_html__( 'WooCommerce background sales import:', 'taxocommerce-accounting' ) . '</strong> ';
        if ( empty( $settings['auto_sync_sales'] ) ) {
            echo esc_html__( 'Not enabled. Enable it under Accounting Settings to keep new paid orders available for review.', 'taxocommerce-accounting' );
        } elseif ( empty( $sync_state['last_checked'] ) ) {
            echo esc_html__( 'Enabled but not executed yet. WP-Cron requires site traffic or an external scheduler.', 'taxocommerce-accounting' );
        } else {
            echo esc_html__( 'Last check:', 'taxocommerce-accounting' ) . ' ' . esc_html( (string) $sync_state['last_checked'] );
            echo '. ' . esc_html__( 'Last full cycle:', 'taxocommerce-accounting' ) . ' ' . esc_html( ! empty( $sync_state['last_full_cycle'] ) ? (string) $sync_state['last_full_cycle'] : __( 'Not completed yet', 'taxocommerce-accounting' ) );
            if ( ! empty( $sync_state['last_error'] ) ) {
                echo '. ' . esc_html__( 'Error:', 'taxocommerce-accounting' ) . ' ' . esc_html( (string) $sync_state['last_error'] );
            }
        }
        echo ' <a href="' . esc_url( tca_admin_url( 'settings', array( 'year' => $year ) ) ) . '">' . esc_html__( 'Settings', 'taxocommerce-accounting' ) . '</a></p>';

        echo '<p>' . esc_html__( 'Accounting entries in selected period:', 'taxocommerce-accounting' ) . ' <strong>' . esc_html( (string) $data['total'] ) . '</strong>. ';
        echo esc_html__( 'Pending validation:', 'taxocommerce-accounting' ) . ' <strong>' . esc_html( (string) $data['pending'] ) . '</strong>. ';
        echo esc_html__( 'Operations outside standard domestic VAT:', 'taxocommerce-accounting' ) . ' <strong>' . esc_html( (string) $data['other_treatments'] ) . '</strong>. ';
        echo esc_html__( 'Entries without a document number:', 'taxocommerce-accounting' ) . ' <strong>' . esc_html( (string) $data['missing_document'] ) . '</strong>.</p>';
        if ( 0 === $data['total'] ) {
            echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'There are no recorded operations in this period. This does not establish that the tax return is zero: first check the WooCommerce import and supplier invoices.', 'taxocommerce-accounting' ) . '</p></div>';
        } elseif ( $data['pending'] > 0 || $data['other_treatments'] > 0 || $data['missing_document'] > 0 ) {
            echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'Review the outstanding records before using these figures. Non-domestic transactions and missing references need individual tax treatment.', 'taxocommerce-accounting' ) . '</p></div>';
        } else {
            echo '<p>' . esc_html__( 'No pending status or missing document numbers were detected. This does NOT mean a tax return is complete, accurate or ready for filing.', 'taxocommerce-accounting' ) . '</p>';
        }
        echo '<p>' . esc_html__( 'Entries without tax identification (check only where legally required):', 'taxocommerce-accounting' ) . ' ' . esc_html( (string) $data['missing_tax_id'] ) . '.</p>';
        echo '<p><a class="button" href="' . esc_url( tca_admin_url( 'sales', array( 'year' => $year ) ) ) . '">' . esc_html__( 'Review sales', 'taxocommerce-accounting' ) . '</a> ';
        echo '<a class="button" href="' . esc_url( tca_admin_url( 'received', array( 'year' => $year ) ) ) . '">' . esc_html__( 'Review purchases and expenses', 'taxocommerce-accounting' ) . '</a></p>';
        echo '<p>' . esc_html__( 'Verify that the fiscal start date includes the entire applicable reporting period. Imported WooCommerce orders are drafts until reviewed, and the ledger may omit transactions not entered manually.', 'taxocommerce-accounting' ) . '</p>';
    }

    /**
     * User-facing model navigation and per-subtab downloads.
     *
     * @param int $year Reporting year.
     * @return void
     */
    public static function render( $year ) {
        $sections = self::sections();
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only subtab selection.
        $selected = isset( $_GET['hacienda'] ) && is_scalar( $_GET['hacienda'] ) ? sanitize_key( wp_unslash( $_GET['hacienda'] ) ) : '303';
        if ( ! isset( $sections[ $selected ] ) ) {
            $selected = '303';
        }
        $default_quarter = ( (int) current_time( 'Y' ) === (int) $year ) ? (int) ceil( (int) current_time( 'n' ) / 3 ) : 4;
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only quarter used exclusively as a GET navigation filter.
        $quarter = isset( $_GET['quarter'] ) && is_scalar( $_GET['quarter'] ) ? absint( wp_unslash( $_GET['quarter'] ) ) : $default_quarter;
        if ( $quarter < 1 || $quarter > 4 ) {
            $quarter = 1;
        }
        $section = $sections[ $selected ];
        list( $period_start, $period_end ) = self::dates( $year, '303' === $selected ? $quarter : 0 );

        echo '<h2>' . esc_html__( 'Hacienda — tax reports and records', 'taxocommerce-accounting' ) . '</h2>';
        echo '<p>' . esc_html__( 'These are internal working reports, NOT official AEAT tax forms. They are not accepted for submission. The filing obligations depend on the business and tax regime.', 'taxocommerce-accounting' ) . '</p>';
        echo '<p><strong>' . esc_html__( 'Reporting period:', 'taxocommerce-accounting' ) . '</strong> ' . esc_html( $period_start . ' — ' . $period_end ) . '. ';
        echo esc_html__( 'Figures recalculate from the current register whenever you open this screen; you do not have to wait for the tax deadline.', 'taxocommerce-accounting' ) . '</p>';

        echo '<nav class="nav-tab-wrapper" aria-label="' . esc_attr__( 'Tax documents', 'taxocommerce-accounting' ) . '">';
        foreach ( $sections as $id => $item ) {
            $url = tca_admin_url( 'hacienda', array( 'year' => $year, 'hacienda' => $id ) );
            echo '<a class="nav-tab ' . ( $id === $selected ? 'nav-tab-active' : '' ) . '" href="' . esc_url( $url ) . '">' . esc_html( $item['name'] ) . '</a>';
        }
        echo '</nav>';
        $selected_period_quarter = '303' === $selected ? $quarter : 0;
        self::render_readiness( $year, $selected_period_quarter );
        echo '<h3>' . esc_html( $section['name'] ) . '</h3>';
        echo '<p>' . esc_html( $section['detail'] ) . '</p>';

        if ( '303' === $selected ) {
            echo '<p><strong>' . esc_html__( 'VAT quarter', 'taxocommerce-accounting' ) . ':</strong> ';
            for ( $q = 1; $q <= 4; $q++ ) {
                $url = tca_admin_url( 'hacienda', array( 'year' => $year, 'hacienda' => '303', 'quarter' => $q ) );
                echo '<a class="button button-small ' . ( $q === $quarter ? 'button-primary' : '' ) . '" href="' . esc_url( $url ) . '">' . esc_html( $q . 'T' ) . '</a> ';
            }
            echo '</p>';
        }

        if ( 'summary' === $section['scope'] ) {
            $values = self::vat_summary( $year, '303' === $selected ? $quarter : 0 );
            self::render_vat( $values );
        } else {
            $total = self::ledger( $year, $section['scope'], 0, 0, true );
            echo '<p><strong>' . esc_html__( 'Matching accounting entries', 'taxocommerce-accounting' ) . ':</strong> ' . esc_html( (string) $total ) . '</p>';
            if ( $total > self::MAX_EXPORT ) {
                echo '<p class="notice notice-warning">' . esc_html__( 'More than 20,000 entries match. Download is blocked rather than silently omitting data.', 'taxocommerce-accounting' ) . '</p>';
            }
            self::render_ledger( self::ledger( $year, $section['scope'], 20 ), $total );
        }

        echo '<p><strong>' . esc_html__( 'Download working data (not for filing)', 'taxocommerce-accounting' ) . ':</strong> ';
        if ( 'summary' === $section['scope'] || $total <= self::MAX_EXPORT ) {
            foreach ( array( 'csv' => 'CSV', 'json' => 'JSON' ) as $format => $label ) {
                $url = wp_nonce_url(
                    add_query_arg(
                        array(
                            'action' => 'tca_hacienda_download', 'year' => $year,
                            'document' => $selected, 'quarter' => $quarter, 'format' => $format,
                        ),
                        admin_url( 'admin-post.php' )
                    ),
                    'tca_hacienda_download'
                );
                echo '<a class="button button-secondary" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a> ';
            }
        }
        echo '</p>';
        if ( in_array( $selected, array( 'issued', 'received', 'assets', 'intra', '303' ), true ) ) {
            echo '<p><strong>' . esc_html__( 'Official AEAT blank workbook templates (not filled by TaxoCommerce):', 'taxocommerce-accounting' ) . '</strong> ';
            echo '<a class="button" target="_blank" rel="noopener noreferrer" href="https://sede.agenciatributaria.gob.es/static_files/Sede/Tema/IVA/Fact_registro/Libros/PLANTILLA_LIBROS_Pers_Juridicas.xlsx">' . esc_html__( 'Download AEAT XLSX template — legal entities', 'taxocommerce-accounting' ) . '</a> ';
            echo '<a class="button" target="_blank" rel="noopener noreferrer" href="https://sede.agenciatributaria.gob.es/static_files/Sede/Tema/IVA/Fact_registro/Libros/PLANTILLA_LIBROS_UNIFICADOS.xlsx">' . esc_html__( 'Download AEAT XLSX template — general', 'taxocommerce-accounting' ) . '</a></p>';
        }
        echo '<p><a class="button" target="_blank" rel="noopener noreferrer" href="' . esc_url( $section['url'] ) . '">' . esc_html__( 'Open official AEAT information and filing', 'taxocommerce-accounting' ) . '</a></p>';
        echo '<p>' . esc_html__( 'The tax models shown are references, not a list of mandatory returns for this merchant. Applicability depends on tax registration and legal form; for example, Model 130 may apply to certain self-employed taxpayers under direct estimation, while Model 200 concerns corporate income tax. Check tax registration with the AEAT or your adviser before preparing a return.', 'taxocommerce-accounting' ) . ' ';
        echo '<a target="_blank" rel="noopener noreferrer" href="https://sede.agenciatributaria.gob.es/Sede/procedimientoini/G601.shtml">' . esc_html__( 'Official AEAT Model 130 information', 'taxocommerce-accounting' ) . '</a></p>';
        echo '<p>' . esc_html__( 'To file a return, use the official AEAT procedure and verify all fields and fiscal obligations. No data is transmitted automatically.', 'taxocommerce-accounting' ) . '</p>';
    }

    /**
     * Print a bounded preview without confusing working figures with returns.
     *
     * @param array $rows Grouped VAT data.
     * @return void
     */
    private static function render_vat( $rows ) {
        echo '<table class="widefat striped"><thead><tr>';
        foreach ( array(
            __( 'Month', 'taxocommerce-accounting' ),
            __( 'Sales', 'taxocommerce-accounting' ),
            __( 'Purchases / expenses', 'taxocommerce-accounting' ),
            __( 'Output VAT', 'taxocommerce-accounting' ),
            __( 'Deductible input VAT', 'taxocommerce-accounting' ),
            __( 'Difference', 'taxocommerce-accounting' ),
            __( 'Pending review', 'taxocommerce-accounting' ),
        ) as $header ) {
            echo '<th>' . esc_html( $header ) . '</th>';
        }
        echo '</tr></thead><tbody>';
        foreach ( $rows as $row ) {
            echo '<tr><td>' . esc_html( $row['month'] ) . '</td>';
            foreach ( array( 'sales', 'purchases', 'output_vat', 'deductible_input_vat', 'difference' ) as $field ) {
                echo '<td>' . esc_html( tca_money( $row[ $field ] ) ) . '</td>';
            }
            echo '<td>' . esc_html( (string) $row['pending_review'] ) . '</td></tr>';
        }
        if ( ! $rows ) {
            echo '<tr><td colspan="7">' . esc_html__( 'No accounting entries for this period.', 'taxocommerce-accounting' ) . '</td></tr>';
        }
        echo '</tbody></table>';
        $totals = array(
            'sales_base' => 0.0, 'sales' => 0.0,
            'purchases_base' => 0.0, 'purchases' => 0.0,
            'output_vat' => 0.0, 'deductible_input_vat' => 0.0,
            'pending_review' => 0,
        );
        foreach ( $rows as $row ) {
            foreach ( $totals as $key => $value ) {
                $totals[ $key ] += isset( $row[ $key ] ) ? (float) $row[ $key ] : 0.0;
            }
        }
        echo '<h3>' . esc_html__( 'Accumulated figures for the selected period', 'taxocommerce-accounting' ) . '</h3>';
        echo '<table class="widefat striped"><tbody>';
        foreach ( array(
            'sales_base' => __( 'Domestic sales taxable base (validated)', 'taxocommerce-accounting' ),
            'sales' => __( 'Domestic sales with VAT', 'taxocommerce-accounting' ),
            'purchases_base' => __( 'Domestic purchases / expenses taxable base (validated)', 'taxocommerce-accounting' ),
            'purchases' => __( 'Domestic purchases / expenses with VAT', 'taxocommerce-accounting' ),
            'output_vat' => __( 'Accrued output VAT', 'taxocommerce-accounting' ),
            'deductible_input_vat' => __( 'Deductible input VAT', 'taxocommerce-accounting' ),
        ) as $key => $label ) {
            echo '<tr><th scope="row">' . esc_html( $label ) . '</th><td>' . esc_html( tca_money( round( $totals[ $key ], 2 ) ) ) . '</td></tr>';
        }
        echo '<tr><th scope="row">' . esc_html__( 'Indicative domestic VAT balance (not final tax due)', 'taxocommerce-accounting' ) . '</th><td><strong>' .
            esc_html( tca_money( round( $totals['output_vat'] - $totals['deductible_input_vat'], 2 ) ) ) . '</strong></td></tr>';
        echo '<tr><th scope="row">' . esc_html__( 'Operations pending review or special tax treatment', 'taxocommerce-accounting' ) . '</th><td>' . esc_html( (string) (int) $totals['pending_review'] ) . '</td></tr>';
        echo '</tbody></table>';
        echo '<p>' . esc_html__( 'These are live accounting totals, not official AEAT boxes. The current register does not retain an invoice-by-invoice split of VAT rates, prior period offsets, all tax adjustments or filing options. Do not copy the indicative balance directly as the amount to pay.', 'taxocommerce-accounting' ) . '</p>';
        echo '<p>' . esc_html__( 'Only validated domestic operations are included in the indicative VAT figures. Drafts and other regimes require review. This is not a complete tax return.', 'taxocommerce-accounting' ) . '</p>';
    }

    /**
     * Show at most 20 transactions; downloads are separately bounded.
     *
     * @param array $rows Preview entries.
     * @param int   $count Full count for the selected scope.
     * @return void
     */
    private static function render_ledger( $rows, $count ) {
        echo '<table class="widefat striped"><thead><tr>';
        foreach ( array(
            __( 'Date', 'taxocommerce-accounting' ),
            __( 'Document', 'taxocommerce-accounting' ),
            __( 'Party', 'taxocommerce-accounting' ),
            __( 'Tax identification', 'taxocommerce-accounting' ),
            __( 'Base', 'taxocommerce-accounting' ),
            __( 'VAT', 'taxocommerce-accounting' ),
            __( 'Total', 'taxocommerce-accounting' ),
            __( 'Status', 'taxocommerce-accounting' ),
        ) as $header ) {
            echo '<th>' . esc_html( $header ) . '</th>';
        }
        echo '</tr></thead><tbody>';
        foreach ( $rows as $row ) {
            echo '<tr>';
            foreach ( array( 'fiscal_date', 'document_no', 'party', 'tax_id' ) as $field ) {
                echo '<td>' . esc_html( (string) $row->$field ) . '</td>';
            }
            foreach ( array( 'amount_base', 'amount_tax', 'amount_total' ) as $field ) {
                echo '<td>' . esc_html( tca_money( $row->$field ) ) . '</td>';
            }
            echo '<td>' . esc_html( $row->status . ' / ' . $row->treatment ) . '</td></tr>';
        }
        if ( ! $rows ) {
            echo '<tr><td colspan="8">' . esc_html__( 'No accounting entries for this period.', 'taxocommerce-accounting' ) . '</td></tr>';
        }
        echo '</tbody></table>';
        if ( $count > 20 ) {
            echo '<p>' . esc_html__( 'Preview shows 20 entries. Download to inspect the complete matching register.', 'taxocommerce-accounting' ) . '</p>';
        }
    }

    /**
     * Read-only authenticated CSV or JSON export of preparatory working data.
     * Never produce an official .303, .347, .349 or .200 filename.
     *
     * @return void
     */
    public static function download() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( esc_html__( 'Insufficient permissions.', 'taxocommerce-accounting' ) );
        }
        check_admin_referer( 'tca_hacienda_download' );
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce checked above.
        $year = isset( $_GET['year'] ) && is_scalar( $_GET['year'] ) ? absint( wp_unslash( $_GET['year'] ) ) : 0;
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce checked above.
        $document = isset( $_GET['document'] ) && is_scalar( $_GET['document'] ) ? sanitize_key( wp_unslash( $_GET['document'] ) ) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce checked above.
        $quarter = isset( $_GET['quarter'] ) && is_scalar( $_GET['quarter'] ) ? absint( wp_unslash( $_GET['quarter'] ) ) : 0;
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce checked above.
        $format = isset( $_GET['format'] ) && is_scalar( $_GET['format'] ) ? sanitize_key( wp_unslash( $_GET['format'] ) ) : '';
        $sections = self::sections();
        if ( $year < 2000 || $year > 2100 || ! isset( $sections[ $document ] )
            || ! in_array( $format, array( 'csv', 'json' ), true )
            || ( '303' === $document && ( $quarter < 1 || $quarter > 4 ) ) ) {
            wp_die( esc_html__( 'Invalid tax report selection.', 'taxocommerce-accounting' ) );
        }
        $scope = $sections[ $document ]['scope'];
        if ( 'summary' !== $scope && self::ledger( $year, $scope, 0, 0, true ) > self::MAX_EXPORT ) {
            wp_die( esc_html__( 'More than 20,000 entries match. Download is blocked rather than silently omitting data.', 'taxocommerce-accounting' ) );
        }
        $warning = __( 'PREPARATORY WORKING DATA — NOT AN OFFICIAL AEAT RETURN OR VALID FILING FILE.', 'taxocommerce-accounting' );
        $filename = sanitize_file_name( 'taxocommerce-hacienda-' . $document . '-' . $year . '-borrador.' . $format );
        nocache_headers();
        header( 'Content-Type: ' . ( 'json' === $format ? 'application/json' : 'text/csv' ) . '; charset=utf-8' );
        header( 'X-Content-Type-Options: nosniff' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        if ( 'summary' === $scope ) {
            $rows = self::vat_summary( $year, '303' === $document ? $quarter : 0 );
            $data = array(
                'official_aeat_format' => false, 'warning' => $warning,
                'document' => $document, 'year' => $year, 'quarter' => '303' === $document ? $quarter : null,
                'generated_at_utc' => gmdate( 'c' ), 'rows' => $rows,
            );
            if ( 'json' === $format ) {
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON response (not HTML).
                echo wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE );
            } else {
                // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- CSV output stream is not a local filesystem write.
                $out = fopen( 'php://output', 'w' );
                fputcsv( $out, array( 'aviso', 'mes', 'ventas', 'compras_gastos', 'iva_repercutido', 'iva_deducible', 'diferencia', 'pendientes' ) );
                foreach ( $rows as $row ) {
                    fputcsv( $out, array(
                        tca_csv_guard( $warning ), $row['month'], $row['sales'], $row['purchases'],
                        $row['output_vat'], $row['deductible_input_vat'], $row['difference'], $row['pending_review'],
                    ) );
                }
            }
            exit;
        }
        $fields = array(
            'id', 'source', 'source_id', 'kind', 'fiscal_date', 'document_no',
            'party', 'tax_id', 'concept', 'amount_base', 'amount_tax',
            'amount_total', 'deductible_pct', 'currency', 'treatment', 'status', 'notes',
        );
        if ( 'csv' === $format ) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Streaming CSV response; no file stored on the server.
            $out = fopen( 'php://output', 'w' );
            fputcsv( $out, array_merge( array( 'aviso' ), $fields ) );
        } else {
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON metadata is encoded as JSON, not HTML.
            echo '{"official_aeat_format":false,"warning":' . wp_json_encode( $warning ) . ',"document":' . wp_json_encode( $document ) . ',"year":' . (int) $year . ',"rows":[';
        }
        $first = true;
        for ( $offset = 0; $offset < self::MAX_EXPORT; $offset += self::BATCH ) {
            $rows = self::ledger( $year, $scope, self::BATCH, $offset );
            if ( ! $rows ) {
                break;
            }
            foreach ( $rows as $row ) {
                $entry = array();
                foreach ( $fields as $field ) {
                    $entry[ $field ] = $row->$field;
                }
                if ( 'csv' === $format ) {
                    $values = array( tca_csv_guard( $warning ) );
                    foreach ( $fields as $field ) {
                        $values[] = in_array( $field, array( 'id', 'source_id', 'amount_base', 'amount_tax', 'amount_total', 'deductible_pct' ), true )
                            ? (string) $entry[ $field ] : tca_csv_guard( $entry[ $field ] );
                    }
                    fputcsv( $out, $values );
                } else {
                    if ( ! $first ) {
                        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON array delimiter, not HTML.
                        echo ',';
                    }
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON response; wp_json_encode escapes data.
                    echo wp_json_encode( $entry, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE );
                    $first = false;
                }
            }
            if ( count( $rows ) < self::BATCH ) {
                break;
            }
        }
        if ( 'json' === $format ) {
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Closing static JSON delimiters.
            echo ']}';
        }
        exit;
    }
}

add_action( 'admin_post_tca_hacienda_download', array( 'TCA_Hacienda', 'download' ) );
