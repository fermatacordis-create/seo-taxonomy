<?php

defined( 'ABSPATH' ) || exit;

/**
 * On-demand operational reports for GA4 and Bing Webmaster.
 *
 * Nothing is downloaded from the Connections tab. These requests are executed
 * only when the administrator opens the Reports tab, with a short transient
 * cache to avoid repeated API calls.
 */
final class TaxoCommerce_Google_Bing_Reports {

    const CACHE_TTL = 900;

    // Public contract for consumers (Analista, etc.): read only, no API request.
    // Individual snapshots are private WordPress options with autoload disabled.
    const STORE_PREFIX = 'tgb_manual_report_v1_';

    public static function register() {
        add_action( 'admin_post_tgb_download_report', array( __CLASS__, 'download_report' ) );
        add_action( 'admin_post_tgb_export_report', array( __CLASS__, 'export_report' ) );
    }

    private static function source_tab( $source ) {
        if ( 'search' === $source ) {
            return 'google_search';
        }
        if ( 'analytics' === $source ) {
            return 'google_analytics';
        }
        return 'bing';
    }

    public static function saved_reports( $source ) {
        if ( ! in_array( $source, array( 'analytics', 'search', 'bing' ), true ) ) {
            return array();
        }
        $value = get_option( self::STORE_PREFIX . $source, array() );
        return is_array( $value ) ? $value : array();
    }

    public static function get_saved_snapshot( $source, $start, $end ) {
        $entries = self::saved_reports( $source );
        $key = md5( $start . '|' . $end );
        return isset( $entries[ $key ] ) && is_array( $entries[ $key ] ) ? $entries[ $key ] : null;
    }

    public static function render( $source = 'analytics' ) {
        if ( ! in_array( $source, array( 'analytics', 'search', 'bing' ), true ) ) {
            return;
        }

        // GET is strictly a local read. All external API calls are in download_report().
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Filters do not mutate anything.
        $input = wp_unslash( $_GET );
        $selection = self::selection( $input );
        echo '<div class="tgb-reports">';
        if ( is_wp_error( $selection ) ) {
            echo '<div class="notice notice-error inline"><p>' . esc_html( $selection->get_error_message() ) . '</p></div>';
            $selection = self::selection( array( 'period' => '30' ) );
        }
        $days = $selection['days'];
        $ranges = $selection['ranges'];

        $names = array(
            'analytics' => 'Google Analytics 4',
            'search'    => 'Google Search Console',
            'bing'      => 'Microsoft Bing Webmaster',
        );

        echo '<div class="tgb-report-toolbar"><div><h2>' . esc_html( $names[ $source ] ) . '</h2>';
        echo '<p>' . esc_html__( 'Charts are generated from the latest saved data. Opening the page or changing the period does not call external APIs.', 'taxocommerce-google-bing' ) . '</p>' . '</div>';
        echo '<div class="tgb-periods">';
        foreach ( array( 7 => '7 days', 30 => '30 days', 90 => '90 days' ) as $period => $label ) {
            $url = add_query_arg(
                array( 'page' => 'taxocommerce-google-bing', 'tgb_tab' => self::source_tab( $source ), 'period' => $period ),
                admin_url( 'admin.php' )
            );
            echo '<a class="button ' . ( (string) $period === $selection['period'] ? 'button-primary' : '' ) . '" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
        }
        echo '</div></div>';
        echo '<form class="tgb-custom-range" method="get" action="' . esc_url( admin_url( 'admin.php' ) ) . '">';
        echo '<input type="hidden" name="page" value="taxocommerce-google-bing">';
        echo '<input type="hidden" name="tgb_tab" value="' . esc_attr( self::source_tab( $source ) ) . '">';
        echo '<input type="hidden" name="period" value="custom">';
        echo '<label>Desde <input type="date" name="start" value="' . esc_attr( $ranges['current']['start'] ) . '" required></label>';
        echo '<label>Hasta <input type="date" name="end" value="' . esc_attr( $ranges['current']['end'] ) . '" required></label>';
        echo '<button type="submit" class="button">' . esc_html__( 'Aplicar fechas (sin actualizar)', 'taxocommerce-google-bing' ) . '</button>';
        echo '</form>';
        echo '<p class="description tgb-report-range">Period: ' . esc_html( $ranges['current']['label'] ) . ' · Comparison: ' . esc_html( $ranges['previous']['label'] ) . '. Full calendar days; the comparison period has the same duration.</p>';

        echo '<form method="post" class="tgb-download-form" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        echo '<input type="hidden" name="action" value="tgb_download_report">';
        echo '<input type="hidden" name="source" value="' . esc_attr( $source ) . '">';
        echo '<input type="hidden" name="period" value="' . esc_attr( $selection['period'] ) . '">';
        if ( 'custom' === $selection['period'] ) {
            echo '<input type="hidden" name="start" value="' . esc_attr( $ranges['current']['start'] ) . '">';
            echo '<input type="hidden" name="end" value="' . esc_attr( $ranges['current']['end'] ) . '">';
        }
        wp_nonce_field( 'tgb_download_report_' . $source );
        echo '<button type="submit" class="button button-primary">Actualizar datos de ' . esc_html( $names[ $source ] ) . '</button>';
        echo '</form>';

        $notice_key = 'tgb_download_notice_' . get_current_user_id();
        $notice = get_transient( $notice_key );
        if ( is_array( $notice ) && ( $notice['source'] ?? '' ) === $source ) {
            echo '<div class="notice ' . ( ! empty( $notice['error'] ) ? 'notice-error' : 'notice-success' ) . ' inline"><p>' . esc_html( (string) ( $notice['message'] ?? '' ) ) . '</p></div>';
            delete_transient( $notice_key );
        }

        $snapshots = self::saved_reports( $source );
        $snapshot = self::get_saved_snapshot( $source, $ranges['current']['start'], $ranges['current']['end'] );
        if ( null === $snapshot && ! empty( $snapshots ) ) {
            $snapshot = end( $snapshots );
            echo '<div class="notice notice-info inline">' . '<p>' . esc_html__( 'No saved report exists for the requested dates. The latest available snapshot is shown without downloading new data.', 'taxocommerce-google-bing' ) . '</p>' . '</div>';
        }

        if ( ! empty( $snapshots ) ) {
            echo '<div class="tgb-history">' . '<strong>' . esc_html__( 'Saved reports:', 'taxocommerce-google-bing' ) . '</strong>' . ' ';
            foreach ( array_reverse( $snapshots, true ) as $saved ) {
                $saved_current = (array) $saved['current'];
                $link = add_query_arg(
                    array(
                        'page' => 'taxocommerce-google-bing',
                        'tgb_tab' => self::source_tab( $source ),
                        'period' => 'custom',
                        'start' => $saved_current['start'],
                        'end' => $saved_current['end'],
                    ),
                    admin_url( 'admin.php' )
                );
                echo '<a class="button button-small" href="' . esc_url( $link ) . '">' . esc_html( $saved_current['start'] . ' / ' . $saved_current['end'] ) . '</a> ';
            }
            echo '</div>';
        }

        if ( null === $snapshot ) {
            echo '<div class="notice notice-info inline">' . '<p>' . esc_html__( 'No hay datos guardados todavía. Pulsa “Actualizar datos” para consultar la API.', 'taxocommerce-google-bing' ) . '</p>' . '</div>';
            self::styles();
            echo '</div>';
            return;
        }

        echo '<p class="description">Report for ' . esc_html( $snapshot['current']['label'] )
            . ', comparado con ' . esc_html( $snapshot['previous']['label'] )
            . '. Descargado el ' . esc_html( wp_date( 'd/m/Y H:i', (int) $snapshot['downloaded_at'] ) )
            . '. Local snapshot, no external connections.</p>';
        $args = array(
            'action'  => 'tgb_export_report',
            'source'  => $source,
            'period'  => 'custom',
            'start'   => $snapshot['current']['start'],
            'end'     => $snapshot['current']['end'],
        );
        $export = wp_nonce_url( add_query_arg( $args, admin_url( 'admin-post.php' ) ), 'tgb_export_report_' . $source );
        echo '<p><a class="button" href="' . esc_url( $export ) . '">Export JSON copy</a></p>';

        $data = $snapshot['data'];
        if ( 'analytics' === $source ) {
            self::render_ga4( $data );
        } elseif ( 'search' === $source ) {
            self::render_gsc( $data );
        } else {
            self::render_bing( $data );
        }
        echo '<p class="description tgb-report-note">' . esc_html__( 'GA4 records events and sessions, not HTTP requests. Search Console and Bing show search-engine traffic, not total site visits. Periods may be incomplete because each provider has its own processing delay.', 'taxocommerce-google-bing' ) . '</p>';
        self::styles();
        echo '</div>';
    }

    public static function download_report() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Acceso no autorizado.', 'taxocommerce-google-bing' ) );
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified below with source-specific nonce.
        $source = isset( $_POST['source'] ) ? sanitize_key( wp_unslash( $_POST['source'] ) ) : '';
        if ( ! in_array( $source, array( 'analytics', 'search', 'bing' ), true ) ) {
            wp_die( esc_html__( 'Invalid report.', 'taxocommerce-google-bing' ) );
        }
        check_admin_referer( 'tgb_download_report_' . $source );
        $selection = self::selection( wp_unslash( $_POST ) );
        if ( is_wp_error( $selection ) ) {
            self::finish_download( $source, array( 'period' => '30' ), $selection->get_error_message(), true );
        }

        $lock = 'tgb_report_running_' . get_current_blog_id() . '_' . $source;
        if ( get_transient( $lock ) ) {
            self::finish_download( $source, $selection, 'Another download for this source is already running.', true );
        }
        set_transient( $lock, 1, 2 * MINUTE_IN_SECONDS );
        $ranges = $selection['ranges'];
        $days = $selection['days'];
        if ( 'analytics' === $source ) {
            $data = self::ga4_data( $days, $ranges );
        } elseif ( 'search' === $source ) {
            $data = TaxoCommerce_Google_Bing_GSC::download( $days, $ranges );
        } else {
            $data = self::bing_data( $days, $ranges );
        }
        delete_transient( $lock );

        if ( is_wp_error( $data ) ) {
            self::finish_download( $source, $selection, $data->get_error_message(), true );
        }
        if ( ! is_array( $data ) ) {
            self::finish_download( $source, $selection, 'The API did not return a valid report.', true );
        }

        $entries = self::saved_reports( $source );
        $key = md5( $ranges['current']['start'] . '|' . $ranges['current']['end'] );
        unset( $entries[ $key ] );
        $entries = array_slice( $entries, -9, null, true );
        $entries[ $key ] = array(
            'source'        => $source,
            'schema'        => 1,
            'downloaded_at' => time(),
            'current'       => $ranges['current'],
            'previous'      => $ranges['previous'],
            'data'          => $data,
        );
        update_option( self::STORE_PREFIX . $source, $entries, false );
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Existing public hook name is retained to preserve connected callbacks.
        do_action( 'tgb_manual_report_saved', $source, $ranges, $entries[ $key ] );
        self::finish_download( $source, $selection, 'Datos actualizados y guardados correctamente.', false );
    }

    public static function export_report() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Acceso no autorizado.', 'taxocommerce-google-bing' ) );
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Verified below.
        $source = isset( $_GET['source'] ) ? sanitize_key( wp_unslash( $_GET['source'] ) ) : '';
        if ( ! in_array( $source, array( 'analytics', 'search', 'bing' ), true ) ) {
            wp_die( esc_html__( 'Invalid report.', 'taxocommerce-google-bing' ) );
        }
        check_admin_referer( 'tgb_export_report_' . $source );
        $selection = self::selection( wp_unslash( $_GET ) );
        if ( is_wp_error( $selection ) ) {
            wp_die( esc_html( $selection->get_error_message() ) );
        }
        $range = $selection['ranges']['current'];
        $snapshot = self::get_saved_snapshot( $source, $range['start'], $range['end'] );
        if ( null === $snapshot ) {
            wp_die( esc_html__( 'Report not available. Download it first.', 'taxocommerce-google-bing' ) );
        }
        nocache_headers();
        header( 'Content-Type: application/json; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="taxocommerce_' . $source . '_' . $range['start'] . '_' . $range['end'] . '.json"' );
        echo wp_json_encode( $snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Explicit JSON download.
        exit;
    }

    private static function finish_download( $source, $selection, $message, $error ) {
        set_transient(
            'tgb_download_notice_' . get_current_user_id(),
            array( 'source' => $source, 'error' => (bool) $error, 'message' => sanitize_text_field( $message ) ),
            2 * MINUTE_IN_SECONDS
        );
        $params = array(
            'page'    => 'taxocommerce-google-bing',
            'tgb_tab' => self::source_tab( $source ),
            'period'  => $selection['period'] ?? '30',
        );
        if ( 'custom' === $params['period'] ) {
            $params['start'] = $selection['ranges']['current']['start'];
            $params['end']   = $selection['ranges']['current']['end'];
        }
        wp_safe_redirect( add_query_arg( $params, admin_url( 'admin.php' ) ) );
        exit;
    }

    private static function selection( $input ) {
        $period = isset( $input['period'] ) && is_scalar( $input['period'] )
            ? sanitize_text_field( (string) $input['period'] ) : '30';

        if ( 'custom' !== $period ) {
            $days = in_array( (int) $period, array( 7, 30, 90 ), true ) ? (int) $period : 30;
            return array( 'period' => (string) $days, 'days' => $days, 'ranges' => self::ranges( $days ) );
        }

        $start = isset( $input['start'] ) && is_scalar( $input['start'] ) ? sanitize_text_field( (string) $input['start'] ) : '';
        $end = isset( $input['end'] ) && is_scalar( $input['end'] ) ? sanitize_text_field( (string) $input['end'] ) : '';
        $tz = wp_timezone();
        $from = DateTimeImmutable::createFromFormat( '!Y-m-d', $start, $tz );
        $until = DateTimeImmutable::createFromFormat( '!Y-m-d', $end, $tz );
        $max = ( new DateTimeImmutable( 'today', $tz ) )->modify( '-1 day' );

        if ( ! $from || ! $until || $from->format( 'Y-m-d' ) !== $start
            || $until->format( 'Y-m-d' ) !== $end || $until > $max || $from > $until ) {
            return new WP_Error( 'tgb_range_invalid', 'Select valid dates, excluding today and future dates.' );
        }
        $days = (int) $from->diff( $until )->days + 1;
        if ( $days > 90 ) {
            return new WP_Error( 'tgb_range_large', 'The maximum download range is 90 days.' );
        }
        $previous_end = $from->modify( '-1 day' );
        $previous_start = $previous_end->modify( '-' . ( $days - 1 ) . ' days' );
        return array(
            'period' => 'custom',
            'days' => $days,
            'ranges' => array(
                'current' => self::date_range( $from, $until ),
                'previous' => self::date_range( $previous_start, $previous_end ),
            ),
        );
    }

    private static function date_range( $from, $until ) {
        return array(
            'start' => $from->format( 'Y-m-d' ),
            'end' => $until->format( 'Y-m-d' ),
            'label' => $from->format( 'd/m/Y' ) . ' – ' . $until->format( 'd/m/Y' ),
        );
    }

    private static function render_gsc( $data ) {
        $current = (array) ( $data['summary']['current'] ?? array() );
        $previous = (array) ( $data['summary']['previous'] ?? array() );
        echo '<section class="tgb-report-section">';
        echo '<div class="tgb-section-head">' . '<div>' . '<h2>' . esc_html__( 'Google Search performance', 'taxocommerce-google-bing' ) . '</h2>';
        echo '<p>' . esc_html__( 'Web results: trends, queries, pages, countries, and devices.', 'taxocommerce-google-bing' ) . '</p>' . '</div>' . '<span class="tgb-source-badge">' . esc_html__( 'Search Console', 'taxocommerce-google-bing' ) . '</span>' . '</div>';
        echo '<div class="tgb-kpi-grid">';
        self::kpi( 'Clicks', $current['clicks'], $previous['clicks'] );
        self::kpi( 'Impressions', $current['impressions'], $previous['impressions'] );
        self::kpi( 'CTR', $current['ctr'], $previous['ctr'], 'percent' );
        self::kpi( 'Average position', $current['position'], $previous['position'], 'decimal', true );
        echo '</div><div class="tgb-report-grid">';
        self::line_card(
            'Click trend',
            'Clicks',
            $data['daily']['labels'],
            array(
                array( 'label' => 'Current', 'values' => $data['daily']['current_clicks'] ),
                array( 'label' => 'Previous', 'values' => $data['daily']['previous_clicks'], 'previous' => true ),
            )
        );
        self::line_card(
            'Impression trend',
            'Impressions',
            $data['daily']['labels'],
            array(
                array( 'label' => 'Current', 'values' => $data['daily']['current_impressions'] ),
                array( 'label' => 'Previous', 'values' => $data['daily']['previous_impressions'], 'previous' => true ),
            )
        );
        foreach ( array( 'countries' => 'Clicks by country', 'devices' => 'Clicks by device' ) as $type => $title ) {
            $slice = array_slice( (array) ( $data[ $type ] ?? array() ), 0, 10 );
            $labels = array_column( $slice, 'name' );
            $values = array_column( $slice, 'clicks' );
            self::bar_card( $title, 'Clicks', $labels, array( array( 'label' => 'Current', 'values' => $values ) ) );
        }
        echo '</div>';
        self::render_rank_table( 'Top queries', (array) ( $data['queries'] ?? array() ) );
        self::render_rank_table( 'Top pages', (array) ( $data['pages'] ?? array() ), true );
        echo '<p class="description">' . esc_html( (string) ( $data['note'] ?? '' ) ) . '</p>';
        echo '</section>';
    }

    private static function render_rank_table( $title, $rows, $is_url = false ) {
        echo '<div class="tgb-rank-card"><h3>' . esc_html( $title ) . '</h3>';
        if ( empty( $rows ) ) {
            echo '<p>' . esc_html__( 'No records for this period.', 'taxocommerce-google-bing' ) . '</p>' . '</div>';
            return;
        }
        echo '<div class="tgb-table-scroll"><table class="widefat striped"><thead><tr>';
        echo '<th>' . esc_html__( 'Query / page', 'taxocommerce-google-bing' ) . '</th>' . '<th>' . esc_html__( 'Clicks', 'taxocommerce-google-bing' ) . '</th>' . '<th>' . esc_html__( 'Impressions', 'taxocommerce-google-bing' ) . '</th>' . '<th>' . esc_html__( 'CTR', 'taxocommerce-google-bing' ) . '</th>' . '<th>' . esc_html__( 'Average position', 'taxocommerce-google-bing' ) . '</th>';
        echo '</tr></thead><tbody>';
        foreach ( array_slice( $rows, 0, 30 ) as $item ) {
            $label = (string) ( $item['name'] ?? '' );
            $clicks = (int) ( $item['clicks'] ?? 0 );
            $impressions = (int) ( $item['impressions'] ?? 0 );
            $ctr = isset( $item['ctr'] ) ? (float) $item['ctr'] : ( $impressions > 0 ? $clicks / $impressions : 0 );
            $position = (float) ( $item['position'] ?? 0 );
            echo '<tr><td class="tgb-rank-label">';
            if ( $is_url && filter_var( $label, FILTER_VALIDATE_URL )
                && in_array( strtolower( (string) wp_parse_url( $label, PHP_URL_SCHEME ) ), array( 'http', 'https' ), true ) ) {
                echo '<a target="_blank" rel="noopener noreferrer" href="' . esc_url( $label ) . '">' . esc_html( $label ) . '</a>';
            } else {
                echo esc_html( $label );
            }
            echo '</td><td>' . esc_html( number_format_i18n( $clicks ) ) . '</td>';
            echo '<td>' . esc_html( number_format_i18n( $impressions ) ) . '</td>';
            echo '<td>' . esc_html( number_format_i18n( $ctr * 100, 1 ) ) . '%</td>';
            echo '<td>' . esc_html( number_format_i18n( $position, 1 ) ) . '</td></tr>';
        }
        echo '</tbody></table></div></div>';
    }

    private static function render_ga4( $data ) {
        echo '<section class="tgb-report-section">';
        echo '<div class="tgb-section-head">' . '<div>' . '<h2>' . esc_html__( 'Google Analytics 4', 'taxocommerce-google-bing' ) . '</h2>' . '<p>' . esc_html__( 'Traffic, users, recurrence, engagement, and geographic origin.', 'taxocommerce-google-bing' ) . '</p>' . '</div>' . '<span class="tgb-source-badge">' . 'GA4' . '</span>' . '</div>';

        if ( is_wp_error( $data ) ) {
            echo '<div class="notice notice-warning inline"><p>' . esc_html( $data->get_error_message() ) . '</p></div>';
            echo '</section>';
            return;
        }

        $current  = (array) $data['summary']['current'];
        $previous = (array) $data['summary']['previous'];

        echo '<div class="tgb-kpi-grid">';
        self::kpi( 'Active users', $current['activeUsers'], $previous['activeUsers'], 'number' );
        self::kpi( 'New users', $current['newUsers'], $previous['newUsers'], 'number' );
        self::kpi( 'Returning users', $data['users']['current_returning'], $data['users']['previous_returning'], 'number' );
        self::kpi( 'Sessions', $current['sessions'], $previous['sessions'], 'number' );
        self::kpi( 'Page views', $current['screenPageViews'], $previous['screenPageViews'], 'number' );
        self::kpi( 'Engagement rate', $current['engagementRate'], $previous['engagementRate'], 'percent' );
        self::kpi( 'Tasa de rebote', $current['bounceRate'], $previous['bounceRate'], 'percent', true );
        self::kpi( 'Average session duration', $current['averageSessionDuration'], $previous['averageSessionDuration'], 'duration' );
        self::kpi( 'Engaged sessions', $current['engagedSessions'], $previous['engagedSessions'] );
        self::kpi( 'Eventos', $current['eventCount'], $previous['eventCount'] );
        self::kpi( 'Eventos clave', $current['keyEvents'], $previous['keyEvents'] );
        self::kpi( 'Recorded purchases', $current['ecommercePurchases'], $previous['ecommercePurchases'] );
        echo '</div>';

        echo '<div class="tgb-report-grid">';
        self::line_card(
            'Session trend',
            'Sessions',
            $data['daily']['labels'],
            array(
                array( 'label' => 'Current', 'values' => $data['daily']['current_sessions'] ),
                array( 'label' => 'Previous', 'values' => $data['daily']['previous_sessions'], 'previous' => true ),
            )
        );
        self::line_card(
            'View trend',
            'Vistas',
            $data['daily']['labels'],
            array(
                array( 'label' => 'Current', 'values' => $data['daily']['current_views'] ),
                array( 'label' => 'Previous', 'values' => $data['daily']['previous_views'], 'previous' => true ),
            )
        );
        self::bar_card(
            'Users by country',
            'Active users',
            $data['countries']['labels'],
            array(
                array( 'label' => 'Current', 'values' => $data['countries']['current'] ),
                array( 'label' => 'Previous', 'values' => $data['countries']['previous'], 'previous' => true ),
            )
        );
        self::bar_card(
            'Nuevos vs. recurrentes',
            'Active users',
            array( 'Nuevos', 'Recurrentes' ),
            array(
                array(
                    'label'  => 'Current',
                    'values' => array( $data['users']['current_new'], $data['users']['current_returning'] ),
                ),
                array(
                    'label'    => 'Previous',
                    'values'   => array( $data['users']['previous_new'], $data['users']['previous_returning'] ),
                    'previous' => true,
                ),
            )
        );
        self::bar_card(
            'Sessions by acquisition channel',
            'Sessions',
            $data['channels']['labels'],
            array(
                array( 'label' => 'Current', 'values' => $data['channels']['current'] ),
                array( 'label' => 'Previous', 'values' => $data['channels']['previous'], 'previous' => true ),
            )
        );
        self::bar_card(
            'Users by device',
            'Active users',
            $data['devices']['labels'],
            array(
                array( 'label' => 'Current', 'values' => $data['devices']['current'] ),
                array( 'label' => 'Previous', 'values' => $data['devices']['previous'], 'previous' => true ),
            )
        );
        echo '</div>';
        self::render_ga4_ecommerce( $data );
        self::render_ga4_pages( $data['pages'] );
        echo '<p class="description">' . esc_html__( 'Purchases and key events are shown only when GA4 receives the corresponding events.', 'taxocommerce-google-bing' ) . '</p>';
        echo '</section>';
    }

    /**
     * GA4 ecommerce event counts are independent: these are not unique users
     * or a linked checkout journey. Item counts are quantities, not carts.
     *
     * @param array $data Cached GA4 snapshot.
     */
    private static function render_ga4_ecommerce( $data ) {
        $current  = (array) ( $data['funnel']['current'] ?? array() );
        $previous = (array) ( $data['funnel']['previous'] ?? array() );
        $events   = array(
            'view_item'      => __( 'Producto visto', 'taxocommerce-google-bing' ),
            'add_to_cart'    => __( 'Añadir al carrito', 'taxocommerce-google-bing' ),
            'view_cart'      => __( 'Ver carrito', 'taxocommerce-google-bing' ),
            'begin_checkout' => __( 'Iniciar compra', 'taxocommerce-google-bing' ),
            'purchase'       => __( 'Compra registrada', 'taxocommerce-google-bing' ),
        );
        $labels = array_values( $events );
        $now    = array();
        $before = array();
        foreach ( array_keys( $events ) as $event ) {
            $now[]    = max( 0, (int) ( $current[ $event ] ?? 0 ) );
            $before[] = max( 0, (int) ( $previous[ $event ] ?? 0 ) );
        }

        echo '<div class="tgb-rank-card"><h3>' . esc_html__( 'Interacciones de compra (GA4)', 'taxocommerce-google-bing' ) . '</h3>';
        echo '<p class="description">' . esc_html__( 'Eventos agregados: no representan compradores únicos ni pasos consecutivos del mismo usuario.', 'taxocommerce-google-bing' ) . '</p>';
        // phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- These private chart/legend builders escape SVG attributes, chart labels and HTML labels before returning trusted markup.
        echo self::svg_bar_chart( $labels, array(
            array( 'label' => __( 'Periodo actual', 'taxocommerce-google-bing' ), 'values' => $now ),
            array( 'label' => __( 'Periodo anterior', 'taxocommerce-google-bing' ), 'values' => $before, 'previous' => true ),
        ), __( 'Eventos', 'taxocommerce-google-bing' ) );
        echo self::legend( array(
            array( 'label' => __( 'Periodo actual', 'taxocommerce-google-bing' ) ),
            array( 'label' => __( 'Periodo anterior', 'taxocommerce-google-bing' ), 'previous' => true ),
        ) );
        // phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
        echo '</div>';

        echo '<div class="tgb-rank-card"><h3>' . esc_html__( 'Productos añadidos al carrito (GA4)', 'taxocommerce-google-bing' ) . '</h3>';
        $items = (array) ( $data['cart_products'] ?? array() );
        if ( empty( $items['available'] ) ) {
            echo '<p>' . esc_html__( 'El informe guardado no incluye todavía el desglose por producto. Pulsa «Actualizar datos de Google Analytics 4» para solicitarlo.', 'taxocommerce-google-bing' ) . '</p>';
            if ( ! empty( $items['error'] ) ) {
                echo '<p class="description">' . esc_html( (string) $items['error'] ) . '</p>';
            }
            echo '</div>';
            return;
        }

        $rows = (array) ( $items['rows'] ?? array() );
        if ( empty( $rows ) ) {
            echo '<p>' . esc_html__( 'GA4 no devuelve artículos identificados para el periodo seleccionado.', 'taxocommerce-google-bing' ) . '</p></div>';
            return;
        }

        $top = array_slice( $rows, 0, 10 );
        $short_labels = array();
        foreach ( $top as $item ) {
            $short_labels[] = function_exists( 'mb_strimwidth' )
                ? mb_strimwidth( (string) $item['name'], 0, 28, '…' )
                : substr( (string) $item['name'], 0, 26 );
        }
        // phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- These private chart/legend builders escape SVG attributes, chart labels and HTML labels before returning trusted markup.
        echo self::svg_bar_chart( $short_labels, array(
            array( 'label' => __( 'Unidades añadidas', 'taxocommerce-google-bing' ), 'values' => array_column( $top, 'added' ) ),
            array( 'label' => __( 'Unidades compradas', 'taxocommerce-google-bing' ), 'values' => array_column( $top, 'purchased' ) ),
        ), __( 'Unidades', 'taxocommerce-google-bing' ) );
        echo self::legend( array(
            array( 'label' => __( 'Unidades añadidas', 'taxocommerce-google-bing' ) ),
            array( 'label' => __( 'Unidades compradas', 'taxocommerce-google-bing' ) ),
        ) );
        // phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
        echo '<div class="tgb-table-scroll"><table class="widefat striped"><thead><tr>';
        echo '<th>' . esc_html__( 'Producto', 'taxocommerce-google-bing' ) . '</th>';
        echo '<th>' . esc_html__( 'ID de GA4', 'taxocommerce-google-bing' ) . '</th>';
        echo '<th>' . esc_html__( 'Unidades vistas', 'taxocommerce-google-bing' ) . '</th>';
        echo '<th>' . esc_html__( 'Unidades añadidas', 'taxocommerce-google-bing' ) . '</th>';
        echo '<th>' . esc_html__( 'Unidades compradas', 'taxocommerce-google-bing' ) . '</th>';
        echo '</tr></thead><tbody>';
        foreach ( $rows as $item ) {
            echo '<tr><td>' . esc_html( (string) $item['name'] ) . '</td>';
            echo '<td>' . esc_html( (string) $item['id'] ) . '</td>';
            echo '<td>' . esc_html( number_format_i18n( (int) $item['viewed'] ) ) . '</td>';
            echo '<td>' . esc_html( number_format_i18n( (int) $item['added'] ) ) . '</td>';
            echo '<td>' . esc_html( number_format_i18n( (int) $item['purchased'] ) ) . '</td></tr>';
        }
        echo '</tbody></table></div>';
        echo '<p class="description">' . esc_html__( 'Las unidades añadidas no equivalen a carritos abandonados. GA4 no identifica por sí solo al comprador ni confirma qué artículos siguen en un carrito.', 'taxocommerce-google-bing' ) . '</p></div>';
    }

    private static function render_ga4_pages( $pages ) {
        echo '<div class="tgb-rank-card">' . '<h3>' . esc_html__( 'Most viewed pages (GA4)', 'taxocommerce-google-bing' ) . '</h3>';
        if ( empty( $pages ) ) {
            echo '<p>' . esc_html__( 'No pages recorded for this period.', 'taxocommerce-google-bing' ) . '</p>' . '</div>';
            return;
        }
        echo '<div class="tgb-table-scroll">' . '<table class="widefat striped">' . '<thead>' . '<tr>' . '<th>' . esc_html__( 'Path', 'taxocommerce-google-bing' ) . '</th>' . '<th>' . esc_html__( 'Current views', 'taxocommerce-google-bing' ) . '</th>' . '<th>' . esc_html__( 'Previous views', 'taxocommerce-google-bing' ) . '</th>' . '<th>' . esc_html__( 'Change', 'taxocommerce-google-bing' ) . '</th>' . '</tr>' . '</thead>' . '<tbody>';
        foreach ( array_slice( $pages, 0, 30 ) as $item ) {
            $current = (int) $item['views'];
            $previous = (int) $item['previous_views'];
            $delta = self::delta( $current, $previous );
            echo '<tr><td class="tgb-rank-label">' . esc_html( (string) $item['name'] ) . '</td>';
            echo '<td>' . esc_html( number_format_i18n( $current ) ) . '</td>';
            echo '<td>' . esc_html( number_format_i18n( $previous ) ) . '</td>';
            echo '<td>' . esc_html( self::format_delta( $delta, $current, $previous ) ) . '</td></tr>';
        }
        echo '</tbody></table></div></div>';
    }

    private static function render_bing( $data ) {
        echo '<section class="tgb-report-section">';
        echo '<div class="tgb-section-head">' . '<div>' . '<h2>' . esc_html__( 'Microsoft Bing Webmaster', 'taxocommerce-google-bing' ) . '</h2>' . '<p>' . esc_html__( 'Organic traffic, search terms, top pages, indexing, and crawl health.', 'taxocommerce-google-bing' ) . '</p>' . '</div>' . '<span class="tgb-source-badge">' . esc_html__( 'Bing', 'taxocommerce-google-bing' ) . '</span>' . '</div>';

        if ( is_wp_error( $data ) ) {
            echo '<div class="notice notice-warning inline"><p>' . esc_html( $data->get_error_message() ) . '</p></div>';
            echo '</section>';
            return;
        }

        $current  = (array) $data['traffic']['current'];
        $previous = (array) $data['traffic']['previous'];
        $crawl_current  = (array) $data['crawl']['current'];
        $crawl_previous = (array) $data['crawl']['previous'];

        echo '<div class="tgb-kpi-grid">';
        self::kpi( 'Impressions', $current['impressions'], $previous['impressions'], 'number' );
        self::kpi( 'Clicks', $current['clicks'], $previous['clicks'], 'number' );
        self::kpi( 'CTR', $current['ctr'], $previous['ctr'], 'percent' );
        self::kpi( 'Crawled pages', $crawl_current['crawled_pages'], $crawl_previous['crawled_pages'], 'number' );
        self::kpi( '4xx + 5xx errors', $crawl_current['http_errors'], $crawl_previous['http_errors'], 'number', true );
        self::kpi( 'Indexed URLs', $crawl_current['in_index'], $crawl_previous['in_index'], 'number' );
        echo '</div>';

        echo '<div class="tgb-report-grid">';
        self::line_card(
            'Impressions en Bing',
            'Impressions',
            $data['daily']['labels'],
            array(
                array( 'label' => 'Current', 'values' => $data['daily']['current_impressions'] ),
                array( 'label' => 'Previous', 'values' => $data['daily']['previous_impressions'], 'previous' => true ),
            )
        );
        self::line_card(
            'Clicks desde Bing',
            'Clicks',
            $data['daily']['labels'],
            array(
                array( 'label' => 'Current', 'values' => $data['daily']['current_clicks'] ),
                array( 'label' => 'Previous', 'values' => $data['daily']['previous_clicks'], 'previous' => true ),
            )
        );
        echo '</div>';
        self::render_rank_table( 'Top queries en Bing', (array) ( $data['queries'] ?? array() ) );
        self::render_rank_table( 'Top pages en Bing', (array) ( $data['pages'] ?? array() ), true );
        if ( ! empty( $data['warnings'] ) ) {
            foreach ( (array) $data['warnings'] as $warning ) {
                echo '<p class="notice notice-warning inline" style="padding:10px">' . esc_html( $warning ) . '</p>';
            }
        }

        echo '<div class="tgb-crawl-strip">';
        echo '<span><strong>2xx:</strong> ' . esc_html( number_format_i18n( $crawl_current['code2xx'] ) ) . '</span>';
        echo '<span><strong>4xx:</strong> ' . esc_html( number_format_i18n( $crawl_current['code4xx'] ) ) . '</span>';
        echo '<span><strong>5xx:</strong> ' . esc_html( number_format_i18n( $crawl_current['code5xx'] ) ) . '</span>';
        echo '<span><strong>Crawl errors:</strong> ' . esc_html( number_format_i18n( $crawl_current['crawl_errors'] ) ) . '</span>';
        echo '<span><strong>Bloqueadas por robots:</strong> ' . esc_html( number_format_i18n( $crawl_current['blocked_robots'] ) ) . '</span>';
        echo '<span><strong>Enlaces entrantes:</strong> ' . esc_html( number_format_i18n( $crawl_current['in_links'] ) ) . '</span>';
        echo '</div>';

        echo '</section>';
    }

    private static function ga4_data( $days, $ranges ) {
        $settings = TaxoCommerce_Google_Bing_Connections::google_settings();
        $property = preg_replace( '/\D+/', '', (string) ( $settings['analytics_property_id'] ?? '' ) );

        if ( '' === $property || empty( $settings['service_account_json'] ) ) {
            return new WP_Error( 'tgb_ga4_not_configured', 'Google Analytics is not configured. Configure the GA4 connection first.' );
        }

        $current_reports = self::ga4_batch( $property, $ranges['current'] );
        if ( is_wp_error( $current_reports ) ) {
            return $current_reports;
        }

        $previous_reports = self::ga4_batch( $property, $ranges['previous'] );
        if ( is_wp_error( $previous_reports ) ) {
            return $previous_reports;
        }

        if ( count( $current_reports ) < 9 || count( $previous_reports ) < 9 ) {
            return new WP_Error( 'tgb_ga4_incomplete', 'Google Analytics returned an incomplete report batch.' );
        }

        $current_summary  = self::ga4_summary( $current_reports[0], $current_reports[8] );
        $previous_summary = self::ga4_summary( $previous_reports[0], $previous_reports[8] );
        $current_users    = self::ga4_user_split( $current_reports[1] );
        $previous_users   = self::ga4_user_split( $previous_reports[1] );
        $current_country  = self::ga4_country_map( $current_reports[2] );
        $previous_country = self::ga4_country_map( $previous_reports[2] );

        $axis_current  = self::axis( $ranges['current']['start'], $days );
        $axis_previous = self::axis( $ranges['previous']['start'], $days, false );
        $daily_current  = self::ga4_daily( $current_reports[3], $axis_current['dates'], $days );
        $daily_previous = self::ga4_daily( $previous_reports[3], $axis_previous['dates'], $days );

        $country_totals = $current_country;
        foreach ( $previous_country as $country => $value ) {
            $country_totals[ $country ] = ( $country_totals[ $country ] ?? 0 ) + $value;
        }
        arsort( $country_totals );
        $country_labels = array_slice( array_keys( $country_totals ), 0, 10 );

        $countries_current = array();
        $countries_previous = array();
        foreach ( $country_labels as $country ) {
            $countries_current[]  = (int) ( $current_country[ $country ] ?? 0 );
            $countries_previous[] = (int) ( $previous_country[ $country ] ?? 0 );
        }

        $out = array(
            'summary' => array(
                'current'  => $current_summary,
                'previous' => $previous_summary,
            ),
            'users' => array(
                'current_new'        => (int) $current_users['new'],
                'current_returning'  => (int) $current_users['returning'],
                'previous_new'       => (int) $previous_users['new'],
                'previous_returning' => (int) $previous_users['returning'],
            ),
            'countries' => array(
                'labels'   => $country_labels,
                'current'  => $countries_current,
                'previous' => $countries_previous,
            ),
            'channels' => self::ga4_groups( $current_reports[4], $previous_reports[4] ),
            'devices' => self::ga4_groups( $current_reports[5], $previous_reports[5] ),
            'pages' => self::ga4_pages( $current_reports[6], $previous_reports[6] ),
            'funnel' => array(
                'current'  => self::ga4_event_map( $current_reports[7] ),
                'previous' => self::ga4_event_map( $previous_reports[7] ),
            ),
            'cart_products' => self::ga4_cart_products( $property, $ranges['current'] ),
            'daily' => array(
                'labels'              => $axis_current['labels'],
                'current_sessions'    => $daily_current['sessions'],
                'previous_sessions'   => $daily_previous['sessions'],
                'current_views'       => $daily_current['views'],
                'previous_views'      => $daily_previous['views'],
                'current_users'       => $daily_current['users'],
                'previous_users'      => $daily_previous['users'],
            ),
        );

        return $out;
    }

    /**
     * Independent, optional GA4 item report. Any API incompatibility leaves the
     * nine existing GA4 reports untouched, including their saved snapshots.
     */
    private static function ga4_cart_products( $property, $range ) {
        $fallback = array( 'available' => false, 'rows' => array() );
        $token = TaxoCommerce_Google_Bing_Connections::access_token(
            'https://www.googleapis.com/auth/analytics.readonly'
        );
        if ( is_wp_error( $token ) ) {
            return $fallback;
        }

        $request = array(
            'dateRanges' => array( array(
                'startDate' => (string) $range['start'],
                'endDate'   => (string) $range['end'],
            ) ),
            'dimensions' => array(
                array( 'name' => 'itemName' ),
                array( 'name' => 'itemId' ),
            ),
            'metrics' => array(
                array( 'name' => 'itemsViewed' ),
                array( 'name' => 'itemsAddedToCart' ),
                array( 'name' => 'itemsPurchased' ),
            ),
            'orderBys' => array( array(
                'metric' => array( 'metricName' => 'itemsAddedToCart' ),
                'desc'   => true,
            ) ),
            'limit' => 50,
        );

        $response = wp_remote_post(
            'https://analyticsdata.googleapis.com/v1beta/properties/' . rawurlencode( $property ) . ':runReport',
            array(
                'timeout' => 30,
                'headers' => array(
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type'  => 'application/json',
                    'Accept'        => 'application/json',
                ),
                'body' => wp_json_encode( $request ),
            )
        );
        if ( is_wp_error( $response ) ) {
            return $fallback;
        }
        $code = (int) wp_remote_retrieve_response_code( $response );
        $body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
        if ( $code < 200 || $code >= 300 || ! is_array( $body ) ) {
            return $fallback;
        }

        $rows = array();
        foreach ( (array) ( $body['rows'] ?? array() ) as $row ) {
            $name = sanitize_text_field( (string) self::row_dimension( $row, 0 ) );
            $id   = sanitize_text_field( (string) self::row_dimension( $row, 1 ) );
            $added = max( 0, (int) self::row_metric( $row, 1 ) );
            $viewed = max( 0, (int) self::row_metric( $row, 0 ) );
            $purchased = max( 0, (int) self::row_metric( $row, 2 ) );
            if ( 0 === $added && 0 === $purchased ) {
                continue;
            }
            $rows[] = array(
                'name' => '' !== $name ? $name : __( 'Producto sin nombre', 'taxocommerce-google-bing' ),
                'id' => $id,
                'viewed' => $viewed,
                'added' => $added,
                'purchased' => $purchased,
            );
        }

        return array( 'available' => true, 'rows' => $rows );
    }

    private static function ga4_batch( $property, $range ) {
        $token = TaxoCommerce_Google_Bing_Connections::access_token(
            'https://www.googleapis.com/auth/analytics.readonly'
        );
        if ( is_wp_error( $token ) ) {
            return $token;
        }

        $date_range = array(
            'startDate' => (string) $range['start'],
            'endDate'   => (string) $range['end'],
        );

        $requests = array(
            array(
                'dateRanges' => array( $date_range ),
                'metrics' => array(
                    array( 'name' => 'activeUsers' ),
                    array( 'name' => 'newUsers' ),
                    array( 'name' => 'sessions' ),
                    array( 'name' => 'screenPageViews' ),
                    array( 'name' => 'engagedSessions' ),
                    array( 'name' => 'engagementRate' ),
                    array( 'name' => 'eventCount' ),
                    array( 'name' => 'bounceRate' ),
                    array( 'name' => 'averageSessionDuration' ),
                    array( 'name' => 'keyEvents' ),
                ),
                'limit' => 1,
            ),
            array(
                'dateRanges' => array( $date_range ),
                'dimensions' => array( array( 'name' => 'newVsReturning' ) ),
                'metrics'    => array( array( 'name' => 'activeUsers' ) ),
                'limit'      => 10,
            ),
            array(
                'dateRanges' => array( $date_range ),
                'dimensions' => array( array( 'name' => 'country' ) ),
                'metrics'    => array( array( 'name' => 'activeUsers' ) ),
                'orderBys'   => array(
                    array(
                        'metric' => array( 'metricName' => 'activeUsers' ),
                        'desc'   => true,
                    ),
                ),
                'limit' => 25,
            ),
            array(
                'dateRanges' => array( $date_range ),
                'dimensions' => array( array( 'name' => 'date' ) ),
                'metrics' => array(
                    array( 'name' => 'sessions' ),
                    array( 'name' => 'screenPageViews' ),
                    array( 'name' => 'activeUsers' ),
                ),
                'orderBys' => array(
                    array(
                        'dimension' => array( 'dimensionName' => 'date' ),
                        'desc'      => false,
                    ),
                ),
                'limit' => 100,
            ),
            array(
                'dateRanges' => array( $date_range ),
                'dimensions' => array( array( 'name' => 'sessionDefaultChannelGroup' ) ),
                'metrics' => array( array( 'name' => 'sessions' ) ),
                'orderBys' => array( array( 'metric' => array( 'metricName' => 'sessions' ), 'desc' => true ) ),
                'limit' => 25,
            ),
            array(
                'dateRanges' => array( $date_range ),
                'dimensions' => array( array( 'name' => 'deviceCategory' ) ),
                'metrics' => array( array( 'name' => 'activeUsers' ) ),
                'limit' => 10,
            ),
            array(
                'dateRanges' => array( $date_range ),
                'dimensions' => array( array( 'name' => 'pagePath' ) ),
                'metrics' => array( array( 'name' => 'screenPageViews' ) ),
                'orderBys' => array( array( 'metric' => array( 'metricName' => 'screenPageViews' ), 'desc' => true ) ),
                'limit' => 100,
            ),
            array(
                'dateRanges' => array( $date_range ),
                'dimensions' => array( array( 'name' => 'eventName' ) ),
                'metrics'    => array( array( 'name' => 'eventCount' ) ),
                'dimensionFilter' => array(
                    'filter' => array(
                        'fieldName'    => 'eventName',
                        'inListFilter' => array(
                            'values' => array( 'view_item', 'add_to_cart', 'view_cart', 'begin_checkout', 'purchase' ),
                        ),
                    ),
                ),
                'limit' => 20,
            ),
            // GA4 accepts a maximum of 10 metrics per individual RunReport request.
            // Keep the original eight report positions intact and fetch purchases separately.
            array(
                'dateRanges' => array( $date_range ),
                'metrics'    => array( array( 'name' => 'ecommercePurchases' ) ),
                'limit'      => 1,
            ),
        );

        $reports = array();
        foreach ( array_chunk( $requests, 5 ) as $request_chunk ) {
            $response = wp_remote_post(
            'https://analyticsdata.googleapis.com/v1beta/properties/' . rawurlencode( $property ) . ':batchRunReports',
            array(
                'timeout' => 30,
                'headers' => array(
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type'  => 'application/json',
                    'Accept'        => 'application/json',
                ),
                'body' => wp_json_encode( array( 'requests' => $request_chunk ) ),
            )
        );

            if ( is_wp_error( $response ) ) {
                return $response;
            }

            $code = (int) wp_remote_retrieve_response_code( $response );
            $body = json_decode( (string) wp_remote_retrieve_body( $response ), true );

            if ( $code < 200 || $code >= 300 || ! is_array( $body ) ) {
                $message = 'Google Analytics returned HTTP ' . $code . '.';
                if ( is_array( $body ) && ! empty( $body['error']['message'] ) ) {
                    $message .= ' ' . sanitize_text_field( (string) $body['error']['message'] );
                }
                return new WP_Error( 'tgb_ga4_report_error', $message );
            }

            foreach ( (array) ( $body['reports'] ?? array() ) as $report ) {
                $reports[] = $report;
            }
        }

        return $reports;
    }

    private static function ga4_summary( $report, $purchases_report ) {
        $row = (array) ( $report['rows'][0] ?? array() );
        return array(
            'activeUsers'      => (int) self::row_metric( $row, 0 ),
            'newUsers'         => (int) self::row_metric( $row, 1 ),
            'sessions'         => (int) self::row_metric( $row, 2 ),
            'screenPageViews'  => (int) self::row_metric( $row, 3 ),
            'engagedSessions'  => (int) self::row_metric( $row, 4 ),
            'engagementRate'   => (float) self::row_metric( $row, 5 ),
            'eventCount'       => (int) self::row_metric( $row, 6 ),
            'bounceRate'       => (float) self::row_metric( $row, 7 ),
            'averageSessionDuration' => (float) self::row_metric( $row, 8 ),
            'keyEvents'        => (int) self::row_metric( $row, 9 ),
            'ecommercePurchases' => (int) self::row_metric( (array) ( $purchases_report['rows'][0] ?? array() ), 0 ),
        );
    }

    private static function ga4_event_map( $report ) {
        $events = array(
            'view_item'      => 0,
            'add_to_cart'    => 0,
            'view_cart'      => 0,
            'begin_checkout' => 0,
            'purchase'       => 0,
        );
        foreach ( (array) ( $report['rows'] ?? array() ) as $row ) {
            $name = sanitize_key( self::row_dimension( $row, 0 ) );
            if ( array_key_exists( $name, $events ) ) {
                $events[ $name ] += (int) self::row_metric( $row, 0 );
            }
        }
        return $events;
    }

    private static function ga4_user_split( $report ) {
        $out = array( 'new' => 0, 'returning' => 0 );
        foreach ( (array) ( $report['rows'] ?? array() ) as $row ) {
            $type = strtolower( trim( self::row_dimension( $row, 0 ) ) );
            $value = (int) self::row_metric( $row, 0 );
            if ( 'new' === $type ) {
                $out['new'] += $value;
            } elseif ( 'returning' === $type ) {
                $out['returning'] += $value;
            }
        }
        return $out;
    }

    private static function ga4_country_map( $report ) {
        $out = array();
        foreach ( (array) ( $report['rows'] ?? array() ) as $row ) {
            $country = trim( self::row_dimension( $row, 0 ) );
            if ( '' === $country || '(not set)' === strtolower( $country ) ) {
                $country = 'Sin identificar';
            }
            $out[ $country ] = (int) self::row_metric( $row, 0 );
        }
        return $out;
    }

    private static function ga4_groups( $current, $previous ) {
        $now = self::ga4_country_map( $current );
        $prior = self::ga4_country_map( $previous );
        $combined = $now;
        foreach ( $prior as $name => $value ) {
            $combined[ $name ] = ( $combined[ $name ] ?? 0 ) + $value;
        }
        arsort( $combined );
        $labels = array_slice( array_keys( $combined ), 0, 10 );
        $current_values = array();
        $previous_values = array();
        foreach ( $labels as $label ) {
            $current_values[] = (int) ( $now[ $label ] ?? 0 );
            $previous_values[] = (int) ( $prior[ $label ] ?? 0 );
        }
        return array( 'labels' => $labels, 'current' => $current_values, 'previous' => $previous_values );
    }

    private static function ga4_pages( $current, $previous ) {
        $now = self::ga4_country_map( $current );
        $prior = self::ga4_country_map( $previous );
        arsort( $now );
        $out = array();
        foreach ( array_slice( $now, 0, 100, true ) as $name => $views ) {
            $out[] = array( 'name' => $name, 'views' => $views, 'previous_views' => (int) ( $prior[ $name ] ?? 0 ) );
        }
        return $out;
    }

    private static function ga4_daily( $report, $date_map, $days ) {
        $out = array(
            'sessions' => array_fill( 0, $days, 0 ),
            'views'    => array_fill( 0, $days, 0 ),
            'users'    => array_fill( 0, $days, 0 ),
        );

        foreach ( (array) ( $report['rows'] ?? array() ) as $row ) {
            $date = self::row_dimension( $row, 0 );
            if ( ! isset( $date_map[ $date ] ) ) {
                continue;
            }
            $index = $date_map[ $date ];
            $out['sessions'][ $index ] = (int) self::row_metric( $row, 0 );
            $out['views'][ $index ]    = (int) self::row_metric( $row, 1 );
            $out['users'][ $index ]    = (int) self::row_metric( $row, 2 );
        }

        return $out;
    }

    private static function bing_data( $days, $ranges ) {
        $settings = TaxoCommerce_Google_Bing_Connections::bing_settings();
        $key      = TaxoCommerce_Google_Bing_Connections::bing_api_key_value();

        if ( empty( $settings['enabled'] ) || '' === $key || empty( $settings['site_url'] ) ) {
            return new WP_Error( 'tgb_bing_not_configured', 'Bing Webmaster is not configured or is disabled.' );
        }

        $traffic_rows = self::bing_request( 'GetRankAndTrafficStats', $settings, $key );
        if ( is_wp_error( $traffic_rows ) ) {
            return $traffic_rows;
        }

        $crawl_rows = self::bing_request( 'GetCrawlStats', $settings, $key );
        if ( is_wp_error( $crawl_rows ) ) {
            return $crawl_rows;
        }

        // Older Bing endpoints may be unavailable for a site. Preserve the
        // successful traffic/crawl report and flag each unavailable breakdown.
        $warnings = array();
        $queries_raw = self::bing_request( 'GetQueryStats', $settings, $key );
        if ( is_wp_error( $queries_raw ) ) {
            $warnings[] = 'Queries: ' . $queries_raw->get_error_message();
            $queries_raw = array();
        }
        $pages_raw = self::bing_request( 'GetPageStats', $settings, $key );
        if ( is_wp_error( $pages_raw ) ) {
            $warnings[] = 'Pages: ' . $pages_raw->get_error_message();
            $pages_raw = array();
        }

        $axis_current  = self::axis_iso( $ranges['current']['start'], $days );
        $axis_previous = self::axis_iso( $ranges['previous']['start'], $days, false );

        $daily_current_impressions  = array_fill( 0, $days, 0 );
        $daily_previous_impressions = array_fill( 0, $days, 0 );
        $daily_current_clicks       = array_fill( 0, $days, 0 );
        $daily_previous_clicks      = array_fill( 0, $days, 0 );

        $traffic = array(
            'current'  => array( 'clicks' => 0, 'impressions' => 0, 'ctr' => 0.0 ),
            'previous' => array( 'clicks' => 0, 'impressions' => 0, 'ctr' => 0.0 ),
        );

        foreach ( (array) $traffic_rows as $row ) {
            $date = self::bing_date( $row['Date'] ?? '' );
            if ( '' === $date ) {
                continue;
            }
            $clicks      = (int) ( $row['Clicks'] ?? 0 );
            $impressions = (int) ( $row['Impressions'] ?? 0 );

            if ( isset( $axis_current['dates'][ $date ] ) ) {
                $index = $axis_current['dates'][ $date ];
                $daily_current_clicks[ $index ] += $clicks;
                $daily_current_impressions[ $index ] += $impressions;
                $traffic['current']['clicks'] += $clicks;
                $traffic['current']['impressions'] += $impressions;
            } elseif ( isset( $axis_previous['dates'][ $date ] ) ) {
                $index = $axis_previous['dates'][ $date ];
                $daily_previous_clicks[ $index ] += $clicks;
                $daily_previous_impressions[ $index ] += $impressions;
                $traffic['previous']['clicks'] += $clicks;
                $traffic['previous']['impressions'] += $impressions;
            }
        }

        foreach ( array( 'current', 'previous' ) as $period ) {
            $traffic[ $period ]['ctr'] = $traffic[ $period ]['impressions'] > 0
                ? $traffic[ $period ]['clicks'] / $traffic[ $period ]['impressions']
                : 0.0;
        }

        $crawl_current  = self::bing_crawl_period( $crawl_rows, $ranges['current'] );
        $crawl_previous = self::bing_crawl_period( $crawl_rows, $ranges['previous'] );

        $out = array(
            'traffic' => $traffic,
            'daily' => array(
                'labels'               => $axis_current['labels'],
                'current_impressions'  => $daily_current_impressions,
                'previous_impressions' => $daily_previous_impressions,
                'current_clicks'       => $daily_current_clicks,
                'previous_clicks'      => $daily_previous_clicks,
            ),
            'crawl' => array(
                'current'  => $crawl_current,
                'previous' => $crawl_previous,
            ),
            'queries' => self::bing_rank_rows( $queries_raw, $ranges['current'], false ),
            'pages' => self::bing_rank_rows( $pages_raw, $ranges['current'], true ),
            'warnings' => $warnings,
        );

        return $out;
    }

    private static function bing_request( $method, $settings, $key ) {
        $allowed = array( 'GetRankAndTrafficStats', 'GetCrawlStats', 'GetQueryStats', 'GetPageStats' );
        if ( ! in_array( $method, $allowed, true ) ) {
            return new WP_Error( 'tgb_bing_method', 'Bing method not allowed for this report.' );
        }

        $url = add_query_arg(
            array(
                'siteUrl' => (string) $settings['site_url'],
                'apikey'  => $key,
            ),
            'https://ssl.bing.com/webmaster/api.svc/json/' . rawurlencode( $method )
        );

        $response = wp_remote_get(
            $url,
            array(
                'timeout'     => 25,
                'redirection' => 2,
                'headers'     => array( 'Accept' => 'application/json' ),
                'user-agent'  => 'TaxoCommerce-Google-Bing/' . TAXOCOMMERCE_GOOGLE_BING_VERSION,
            )
        );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = (int) wp_remote_retrieve_response_code( $response );
        $body = json_decode( (string) wp_remote_retrieve_body( $response ), true );

        if ( $code < 200 || $code >= 300 || ! is_array( $body ) ) {
            return new WP_Error( 'tgb_bing_report_error', 'Bing Webmaster returned HTTP ' . $code . '.' );
        }

        return array_values( (array) ( $body['d'] ?? array() ) );
    }

    private static function bing_rank_rows( $rows, $range, $pages ) {
        $items = array();
        foreach ( (array) $rows as $row ) {
            if ( ! is_array( $row ) ) {
                continue;
            }
            $date = self::bing_date( $row['Date'] ?? '' );
            if ( '' === $date || $date < $range['start'] || $date > $range['end'] ) {
                continue;
            }

            // GetPageStats historically returns the page URL in "Query".
            $name = $pages
                ? (string) ( $row['PageURL'] ?? $row['PageUrl'] ?? $row['Page'] ?? $row['Url'] ?? $row['Query'] ?? '' )
                : (string) ( $row['Query'] ?? '' );
            $name = sanitize_text_field( $name );
            if ( '' === $name ) {
                continue;
            }
            $clicks = max( 0, (int) ( $row['Clicks'] ?? 0 ) );
            $impressions = max( 0, (int) ( $row['Impressions'] ?? 0 ) );
            $position = (float) ( $row['AvgImpressionPosition'] ?? $row['AvgClickPosition'] ?? 0 );
            if ( ! isset( $items[ $name ] ) ) {
                $items[ $name ] = array(
                    'name' => $name,
                    'clicks' => 0,
                    'impressions' => 0,
                    'position_weighted' => 0,
                );
            }
            $items[ $name ]['clicks'] += $clicks;
            $items[ $name ]['impressions'] += $impressions;
            $items[ $name ]['position_weighted'] += $position * $impressions;
        }

        $out = array_values( $items );
        foreach ( $out as &$item ) {
            $impressions = $item['impressions'];
            $item['ctr'] = $impressions > 0 ? $item['clicks'] / $impressions : 0.0;
            $item['position'] = $impressions > 0 ? $item['position_weighted'] / $impressions : 0.0;
            unset( $item['position_weighted'] );
        }
        unset( $item );
        usort( $out, static function ( $left, $right ) {
            return $right['clicks'] <=> $left['clicks'];
        } );
        return array_slice( $out, 0, 100 );
    }

    private static function bing_crawl_period( $rows, $range ) {
        $out = array(
            'crawled_pages'  => 0,
            'code2xx'        => 0,
            'code4xx'        => 0,
            'code5xx'        => 0,
            'http_errors'    => 0,
            'crawl_errors'   => 0,
            'blocked_robots' => 0,
            'in_index'       => 0,
            'in_links'       => 0,
            'latest_date'    => '',
        );

        foreach ( (array) $rows as $row ) {
            $date = self::bing_date( $row['Date'] ?? '' );
            if ( '' === $date || $date < $range['start'] || $date > $range['end'] ) {
                continue;
            }

            $out['crawled_pages']  += (int) ( $row['CrawledPages'] ?? 0 );
            $out['code2xx']        += (int) ( $row['Code2xx'] ?? 0 );
            $out['code4xx']        += (int) ( $row['Code4xx'] ?? 0 );
            $out['code5xx']        += (int) ( $row['Code5xx'] ?? 0 );
            $out['crawl_errors']   += (int) ( $row['CrawlErrors'] ?? 0 );
            $out['blocked_robots'] += (int) ( $row['BlockedByRobotsTxt'] ?? 0 );

            if ( $date >= $out['latest_date'] ) {
                $out['latest_date'] = $date;
                $out['in_index'] = (int) ( $row['InIndex'] ?? 0 );
                $out['in_links'] = (int) ( $row['InLinks'] ?? 0 );
            }
        }

        $out['http_errors'] = $out['code4xx'] + $out['code5xx'];
        return $out;
    }

    private static function bing_date( $raw ) {
        $value = trim( (string) $raw );
        if ( '' === $value ) {
            return '';
        }

        if ( preg_match( '/\/Date\((-?[0-9]+)/', $value, $match ) ) {
            $milliseconds = (float) $match[1];
            return gmdate( 'Y-m-d', (int) floor( $milliseconds / 1000 ) );
        }

        $timestamp = strtotime( $value );
        return $timestamp ? gmdate( 'Y-m-d', $timestamp ) : '';
    }

    private static function ranges( $days ) {
        $tz = wp_timezone();
        $end = ( new DateTimeImmutable( 'today', $tz ) )->modify( '-1 day' );
        $current_start = $end->modify( '-' . ( $days - 1 ) . ' days' );
        $previous_end = $current_start->modify( '-1 day' );
        $previous_start = $previous_end->modify( '-' . ( $days - 1 ) . ' days' );

        return array(
            'current' => array(
                'start' => $current_start->format( 'Y-m-d' ),
                'end'   => $end->format( 'Y-m-d' ),
                'label' => $current_start->format( 'd/m/Y' ) . ' – ' . $end->format( 'd/m/Y' ),
            ),
            'previous' => array(
                'start' => $previous_start->format( 'Y-m-d' ),
                'end'   => $previous_end->format( 'Y-m-d' ),
                'label' => $previous_start->format( 'd/m/Y' ) . ' – ' . $previous_end->format( 'd/m/Y' ),
            ),
        );
    }

    private static function period_days() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only report selector.
        $days = isset( $_GET['period'] ) ? absint( wp_unslash( $_GET['period'] ) ) : 30;
        return in_array( $days, array( 7, 30, 90 ), true ) ? $days : 30;
    }

    private static function axis( $start, $days, $labels = true ) {
        $base = new DateTimeImmutable( $start );
        $dates = array();
        $out_labels = array();

        for ( $i = 0; $i < $days; $i++ ) {
            $date = $base->modify( '+' . $i . ' days' );
            $raw  = $date->format( 'Ymd' );
            $dates[ $raw ] = $i;
            if ( $labels ) {
                $out_labels[] = self::axis_label( $date, $i, $days );
            }
        }

        return array( 'dates' => $dates, 'labels' => $out_labels );
    }

    private static function axis_iso( $start, $days, $labels = true ) {
        $base = new DateTimeImmutable( $start );
        $dates = array();
        $out_labels = array();

        for ( $i = 0; $i < $days; $i++ ) {
            $date = $base->modify( '+' . $i . ' days' );
            $raw  = $date->format( 'Y-m-d' );
            $dates[ $raw ] = $i;
            if ( $labels ) {
                $out_labels[] = self::axis_label( $date, $i, $days );
            }
        }

        return array( 'dates' => $dates, 'labels' => $out_labels );
    }

    private static function axis_label( $date, $index, $days ) {
        if ( $days <= 7 ) {
            return $date->format( 'd/m' );
        }
        $step = $days <= 30 ? 3 : 9;
        return 0 === $index % $step ? $date->format( 'd/m' ) : '';
    }

    private static function row_dimension( $row, $index ) {
        return isset( $row['dimensionValues'][ $index ]['value'] )
            ? (string) $row['dimensionValues'][ $index ]['value']
            : '';
    }

    private static function row_metric( $row, $index ) {
        return isset( $row['metricValues'][ $index ]['value'] )
            ? (float) $row['metricValues'][ $index ]['value']
            : 0.0;
    }

    private static function kpi( $label, $current, $previous, $format = 'number', $inverse = false ) {
        $delta = self::delta( $current, $previous );
        $delta_class = '';

        if ( null !== $delta ) {
            $better = $inverse ? $delta < 0 : $delta > 0;
            $worse  = $inverse ? $delta > 0 : $delta < 0;
            if ( $better ) {
                $delta_class = ' tgb-delta-good';
            } elseif ( $worse ) {
                $delta_class = ' tgb-delta-bad';
            }
        }

        echo '<div class="tgb-kpi">';
        echo '<span class="tgb-kpi-label">' . esc_html( $label ) . '</span>';
        echo '<strong>' . esc_html( self::format_value( $current, $format ) ) . '</strong>';
        echo '<span class="tgb-kpi-previous">Previous: ' . esc_html( self::format_value( $previous, $format ) ) . '</span>';
        echo '<span class="tgb-delta' . esc_attr( $delta_class ) . '">' . esc_html( self::format_delta( $delta, $current, $previous ) ) . '</span>';
        echo '</div>';
    }

    private static function delta( $current, $previous ) {
        $current  = (float) $current;
        $previous = (float) $previous;

        if ( 0.0 === $previous ) {
            return 0.0 === $current ? 0.0 : null;
        }

        return ( ( $current - $previous ) / abs( $previous ) ) * 100;
    }

    private static function format_delta( $delta, $current, $previous ) {
        if ( null === $delta ) {
            return (float) $current > 0 && 0.0 === (float) $previous ? 'Nuevo' : '—';
        }
        $prefix = $delta > 0 ? '+' : '';
        return $prefix . number_format_i18n( $delta, 1 ) . '%';
    }

    private static function format_value( $value, $format ) {
        if ( 'percent' === $format ) {
            return number_format_i18n( (float) $value * 100, 1 ) . '%';
        }
        if ( 'decimal' === $format ) {
            return number_format_i18n( (float) $value, 1 );
        }
        if ( 'duration' === $format ) {
            $seconds = max( 0, (int) round( (float) $value ) );
            return sprintf( '%d:%02d', (int) floor( $seconds / 60 ), $seconds % 60 );
        }
        return number_format_i18n( (float) $value, 0 );
    }

    private static function line_card( $title, $unit, $labels, $series ) {
        echo '<section class="tgb-chart-card">';
        echo '<h3>' . esc_html( $title ) . '</h3>';
        echo self::svg_line_chart( $labels, $series, $unit ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Generated SVG is escaped internally.
        echo self::legend( $series ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Generated legend is escaped internally.
        echo '</section>';
    }

    private static function bar_card( $title, $unit, $labels, $series ) {
        echo '<section class="tgb-chart-card">';
        echo '<h3>' . esc_html( $title ) . '</h3>';
        echo self::svg_bar_chart( $labels, $series, $unit ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Generated SVG is escaped internally.
        echo self::legend( $series ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Generated legend is escaped internally.
        echo '</section>';
    }

    private static function svg_line_chart( $labels, $series, $unit ) {
        $width  = 720;
        $height = 280;
        $left   = 48;
        $right  = 18;
        $top    = 20;
        $bottom = 42;
        $plot_w = $width - $left - $right;
        $plot_h = $height - $top - $bottom;
        $max    = 0.0;

        foreach ( $series as $item ) {
            foreach ( (array) $item['values'] as $value ) {
                $max = max( $max, (float) $value );
            }
        }
        $max = max( 1.0, $max );
        $count = max( 1, count( $labels ) );
        $palette = array( '#2271b1', '#8c8f94', '#00a32a', '#646970' );

        $svg = '<svg class="tgb-chart" viewBox="0 0 ' . $width . ' ' . $height . '" role="img" aria-label="' . esc_attr( $unit ) . '">';
        for ( $g = 0; $g <= 4; $g++ ) {
            $y = $top + ( $plot_h * $g / 4 );
            $value = round( $max * ( 1 - $g / 4 ) );
            $svg .= '<line x1="' . $left . '" y1="' . $y . '" x2="' . ( $width - $right ) . '" y2="' . $y . '" class="tgb-gridline"/>';
            $svg .= '<text x="' . ( $left - 8 ) . '" y="' . ( $y + 4 ) . '" text-anchor="end" class="tgb-axis">' . esc_html( number_format_i18n( $value ) ) . '</text>';
        }

        foreach ( $series as $s_index => $item ) {
            $values = array_values( (array) $item['values'] );
            $points = array();
            foreach ( $values as $i => $value ) {
                $x = $left + ( $count <= 1 ? 0 : $plot_w * $i / ( $count - 1 ) );
                $y = $top + $plot_h - ( (float) $value / $max ) * $plot_h;
                $points[] = round( $x, 2 ) . ',' . round( $y, 2 );
            }

            $dash  = ! empty( $item['previous'] ) ? ' stroke-dasharray="7 5"' : '';
            $color = $palette[ $s_index % count( $palette ) ];
            $svg .= '<polyline points="' . esc_attr( implode( ' ', $points ) ) . '" fill="none" stroke="' . esc_attr( $color ) . '" stroke-width="2.4"' . $dash . '/>';
        }

        foreach ( $labels as $i => $label ) {
            if ( '' === $label ) {
                continue;
            }
            $x = $left + ( $count <= 1 ? 0 : $plot_w * $i / ( $count - 1 ) );
            $svg .= '<text x="' . round( $x, 2 ) . '" y="' . ( $height - 14 ) . '" text-anchor="middle" class="tgb-axis">' . esc_html( $label ) . '</text>';
        }

        return $svg . '</svg>';
    }

    private static function svg_bar_chart( $labels, $series, $unit ) {
        $width  = 720;
        $height = 300;
        $left   = 48;
        $right  = 18;
        $top    = 20;
        $bottom = 78;
        $plot_w = $width - $left - $right;
        $plot_h = $height - $top - $bottom;
        $max    = 0.0;

        foreach ( $series as $item ) {
            foreach ( (array) $item['values'] as $value ) {
                $max = max( $max, (float) $value );
            }
        }

        $max          = max( 1.0, $max );
        $groups       = max( 1, count( $labels ) );
        $series_count = max( 1, count( $series ) );
        $group_w      = $plot_w / $groups;
        $bar_w        = min( 24, max( 5, ( $group_w - 8 ) / $series_count ) );
        $palette      = array( '#2271b1', '#8c8f94', '#00a32a', '#646970' );

        $svg = '<svg class="tgb-chart" viewBox="0 0 ' . $width . ' ' . $height . '" role="img" aria-label="' . esc_attr( $unit ) . '">';
        for ( $g = 0; $g <= 4; $g++ ) {
            $y = $top + ( $plot_h * $g / 4 );
            $value = round( $max * ( 1 - $g / 4 ) );
            $svg .= '<line x1="' . $left . '" y1="' . $y . '" x2="' . ( $width - $right ) . '" y2="' . $y . '" class="tgb-gridline"/>';
            $svg .= '<text x="' . ( $left - 8 ) . '" y="' . ( $y + 4 ) . '" text-anchor="end" class="tgb-axis">' . esc_html( number_format_i18n( $value ) ) . '</text>';
        }

        foreach ( $labels as $i => $label ) {
            $center = $left + $group_w * $i + $group_w / 2;
            foreach ( $series as $s_index => $item ) {
                $value = (float) ( $item['values'][ $i ] ?? 0 );
                $h     = $value > 0 ? max( 1, ( $value / $max ) * $plot_h ) : 0;
                $x     = $center - ( $bar_w * $series_count / 2 ) + $bar_w * $s_index;
                $y     = $top + $plot_h - $h;
                $color = $palette[ $s_index % count( $palette ) ];
                $opacity = ! empty( $item['previous'] ) ? '0.55' : '1';
                $svg .= '<rect x="' . round( $x, 2 ) . '" y="' . round( $y, 2 ) . '" width="' . round( $bar_w - 1, 2 ) . '" height="' . round( $h, 2 ) . '" fill="' . esc_attr( $color ) . '" opacity="' . esc_attr( $opacity ) . '"/>';
            }
            $svg .= '<text transform="translate(' . round( $center - 2, 2 ) . ',' . ( $height - 12 ) . ') rotate(-38)" text-anchor="end" class="tgb-axis">' . esc_html( $label ) . '</text>';
        }

        return $svg . '</svg>';
    }

    private static function legend( $series ) {
        $palette = array( '#2271b1', '#8c8f94', '#00a32a', '#646970' );
        $html = '<div class="tgb-chart-legend">';

        foreach ( $series as $index => $item ) {
            $html .= '<span><i style="background:' . esc_attr( $palette[ $index % count( $palette ) ] ) . ';opacity:' . ( ! empty( $item['previous'] ) ? '0.55' : '1' ) . '"></i>' . esc_html( $item['label'] ) . '</span>';
        }

        return $html . '</div>';
    }

    private static function styles() {
        echo '<style>
        .tgb-report-toolbar{display:flex;align-items:flex-end;justify-content:space-between;gap:16px;margin:20px 0 8px}
        .tgb-report-toolbar h2{margin:0 0 5px}.tgb-report-toolbar p{margin:0;color:#646970}
        .tgb-periods{display:flex;gap:6px;flex-wrap:wrap}.tgb-report-range{margin:8px 0 18px}
        .tgb-report-section{margin:18px 0 26px}.tgb-section-head{display:flex;align-items:flex-start;justify-content:space-between;gap:14px;margin-bottom:12px}
        .tgb-section-head h2{margin:0 0 4px}.tgb-section-head p{margin:0;color:#646970}
        .tgb-source-badge{display:inline-block;background:#f0f0f1;border-radius:999px;padding:5px 10px;font-weight:600;font-size:12px}
        .tgb-kpi-grid{display:grid;grid-template-columns:repeat(6,minmax(130px,1fr));gap:10px;margin:12px 0 16px}
        .tgb-kpi{background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:14px;display:flex;flex-direction:column;gap:4px}
        .tgb-kpi-label{font-size:12px;color:#646970}.tgb-kpi strong{font-size:23px;line-height:1.1}.tgb-kpi-previous{font-size:11px;color:#8c8f94}
        .tgb-delta{font-size:12px;font-weight:600;color:#646970}.tgb-delta-good{color:#008a20}.tgb-delta-bad{color:#b32d2e}
        .tgb-report-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}
        .tgb-chart-card{background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:16px;min-width:0}.tgb-chart-card h3{margin:0 0 10px;font-size:15px}
        .tgb-chart{display:block;width:100%;height:auto;min-height:220px}.tgb-gridline{stroke:#dcdcde;stroke-width:1}.tgb-axis{fill:#646970;font-size:10px}
        .tgb-chart-legend{display:flex;gap:12px;flex-wrap:wrap;margin-top:8px;font-size:12px;color:#50575e}.tgb-chart-legend span{display:inline-flex;align-items:center;gap:5px}.tgb-chart-legend i{display:inline-block;width:16px;height:3px;border-radius:2px}
        .tgb-crawl-strip{display:flex;gap:16px;flex-wrap:wrap;background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:12px 14px;margin-top:14px;font-size:12px}
        .tgb-report-note{margin-top:16px}
        .tgb-custom-range{display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin:16px 0 10px}
        .tgb-custom-range label{display:inline-flex;align-items:center;gap:6px}
        .tgb-download-form{margin:14px 0}
        .tgb-history{margin:12px 0;display:flex;align-items:center;flex-wrap:wrap;gap:5px}
        .tgb-rank-card{background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:16px;margin:16px 0}
        .tgb-rank-card h3{margin:0 0 10px;font-size:15px}
        .tgb-table-scroll{max-width:100%;overflow-x:auto}
        .tgb-rank-label{min-width:200px;word-break:break-word;max-width:550px}
        @media(max-width:1250px){.tgb-kpi-grid{grid-template-columns:repeat(3,minmax(130px,1fr))}}
        @media(max-width:1000px){.tgb-report-grid{grid-template-columns:1fr}}
        @media(max-width:782px){.tgb-report-toolbar,.tgb-section-head{align-items:flex-start;flex-direction:column}.tgb-kpi-grid{grid-template-columns:repeat(2,minmax(120px,1fr))}}
        @media(max-width:480px){.tgb-kpi-grid{grid-template-columns:1fr}}
        </style>';
    }
}
