<?php

defined( 'ABSPATH' ) || exit;

/**
 * Common Google reporting service owned by TaxoCommerce Google / Bing.
 *
 * This class centralizes Search Console and GA4 Data API reads so consumer
 * modules never need to know credentials.
 */
final class TaxoCommerce_Google_Bing_Google_Service {

    public static function reporting_dates( $days = 28 ) {
        $days = max( 1, absint( $days ) );
        $end = ( new DateTimeImmutable( 'today', wp_timezone() ) )->modify( '-1 day' );
        $start = $end->modify( '-' . ( $days - 1 ) . ' days' );

        return array(
            'startDate' => $start->format( 'Y-m-d' ),
            'endDate'   => $end->format( 'Y-m-d' ),
        );
    }

    public static function search_console_query( $request = array() ) {
        $settings = TaxoCommerce_Google_Bing_Connections::google_settings();
        $site = trim( (string) ( $settings['search_console_site_url'] ?? '' ) );
        if ( '' === $site ) {
            return new WP_Error(
                'tgb_google_no_search_console_site',
                'No hay propiedad de Search Console configurada.'
            );
        }

        $defaults = array(
            'startDate'  => gmdate( 'Y-m-d', strtotime( '-28 days' ) ),
            'endDate'    => gmdate( 'Y-m-d', strtotime( '-1 day' ) ),
            'dimensions' => array( 'query', 'page' ),
            'rowLimit'   => 1000,
        );
        $payload = wp_parse_args( is_array( $request ) ? $request : array(), $defaults );
        $url = 'https://www.googleapis.com/webmasters/v3/sites/'
            . rawurlencode( $site )
            . '/searchAnalytics/query';

        return self::request( 'POST', $url, $payload );
    }

    public static function analytics_run_report( $request = array() ) {
        $settings = TaxoCommerce_Google_Bing_Connections::google_settings();
        $property = preg_replace( '/\\D+/', '', (string) ( $settings['analytics_property_id'] ?? '' ) );
        if ( '' === $property || empty( $settings['service_account_json'] ) ) {
            return new WP_Error( 'tgb_ga4_not_configured', 'Falta la propiedad o cuenta de servicio de Google Analytics 4.' );
        }

        return self::request(
            'POST',
            'https://analyticsdata.googleapis.com/v1beta/properties/' . rawurlencode( $property ) . ':runReport',
            is_array( $request ) ? $request : array(),
            'https://www.googleapis.com/auth/analytics.readonly'
        );
    }

    public static function test() {
        $out = array(
            'ok'             => true,
            'search_console' => '',
            'analytics'      => '',
        );

        $dates = self::reporting_dates( 7 );
        $gsc = self::search_console_query(
            array(
                'startDate'  => $dates['startDate'],
                'endDate'    => $dates['endDate'],
                'dimensions' => array( 'page' ),
                'rowLimit'   => 1,
            )
        );
        if ( is_wp_error( $gsc ) ) {
            $out['ok'] = false;
            $out['search_console'] = $gsc->get_error_message();
        } else {
            $out['search_console'] = 'OK';
        }

        $ga4 = self::analytics_run_report(
            array(
                'dateRanges' => array(
                    array(
                        'startDate' => $dates['startDate'],
                        'endDate'   => $dates['endDate'],
                    ),
                ),
                'metrics' => array(
                    array( 'name' => 'sessions' ),
                ),
                'limit' => 1,
            )
        );
        if ( is_wp_error( $ga4 ) ) {
            $out['ok'] = false;
            $out['analytics'] = $ga4->get_error_message();
        } else {
            $out['analytics'] = 'OK';
        }

        return $out;
    }

    private static function request( $method, $url, $body = null, $scope = 'https://www.googleapis.com/auth/webmasters.readonly' ) {
        $token = TaxoCommerce_Google_Bing_Connections::access_token( $scope );
        if ( is_wp_error( $token ) ) {
            return $token;
        }

        $args = array(
            'method'  => strtoupper( (string) $method ),
            'timeout' => 30,
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'Accept'        => 'application/json',
            ),
        );

        if ( null !== $body ) {
            $args['headers']['Content-Type'] = 'application/json; charset=utf-8';
            $args['body'] = wp_json_encode( $body );
        }

        $response = wp_remote_request( esc_url_raw( $url ), $args );
        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $status = (int) wp_remote_retrieve_response_code( $response );
        $decoded = json_decode( (string) wp_remote_retrieve_body( $response ), true );

        if ( $status < 200 || $status >= 300 ) {
            $message = 'Google API respondió HTTP ' . $status . '.';
            if ( is_array( $decoded ) && ! empty( $decoded['error']['message'] ) ) {
                $message .= ' ' . sanitize_text_field( (string) $decoded['error']['message'] );
            }
            return new WP_Error(
                'tgb_google_api_error',
                $message,
                array( 'status' => $status )
            );
        }

        return is_array( $decoded ) ? $decoded : array();
    }
}

/*
 * Compatibility layer.
 *
 * Existing TaxoCommerce consumers can continue using the historical function
 * names while their implementation is now owned by this plugin.
 */
if ( ! function_exists( 'seo_google_search_settings' ) ) {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
    function seo_google_search_settings() {
        return TaxoCommerce_Google_Bing_Connections::google_settings();
    }
}

if ( ! function_exists( 'seo_google_search_measurement_id' ) ) {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
    function seo_google_search_measurement_id() {
        $settings = TaxoCommerce_Google_Bing_Connections::google_settings();
        $id = strtoupper( trim( (string) ( $settings['measurement_id'] ?? '' ) ) );
        return preg_match( '/^G-[A-Z0-9]+$/', $id ) ? $id : '';
    }
}

if ( ! function_exists( 'seo_google_reporting_dates' ) ) {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
    function seo_google_reporting_dates( $days = 28 ) {
        return TaxoCommerce_Google_Bing_Google_Service::reporting_dates( $days );
    }
}

if ( ! function_exists( 'seo_google_search_console_query' ) ) {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
    function seo_google_search_console_query( $request = array() ) {
        return TaxoCommerce_Google_Bing_Google_Service::search_console_query( $request );
    }
}

if ( ! function_exists( 'seo_google_analytics_run_report' ) ) {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
    function seo_google_analytics_run_report( $request = array() ) {
        return TaxoCommerce_Google_Bing_Google_Service::analytics_run_report( $request );
    }
}
