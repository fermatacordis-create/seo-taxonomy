<?php
/**
 * Connect the historical Analysis GSC day-by-day importer to the current
 * standalone Insights credentials, without rewriting or exposing old tokens.
 *
 * No cron, background API calls or automatic imports. Network verification is
 * performed only when an administrator starts an explicit synchronization.
 */
defined( 'ABSPATH' ) || exit;

final class TaxoCommerce_Google_Bing_GSC_Sync_Bridge {
    const SELECTION_OPTION = 'tgb_gsc_sync_selection_v1';
    const LAST_SYNC_OPTION = 'tgb_gsc_sync_last_v1';

    /**
     * Keep each property paired with the credential that owns its permission.
     * Never assume an OAuth site can be read by the service account, or vice versa.
     */
    private static function candidates() {
        $candidates = array();

        if ( class_exists( 'TaxoCommerce_Google_Bing_OAuth', false ) ) {
            $settings = (array) TaxoCommerce_Google_Bing_OAuth::settings();
            $tokens   = (array) TaxoCommerce_Google_Bing_OAuth::tokens();
            $site     = trim( (string) ( $settings['property_id'] ?? '' ) );
            if ( '' !== $site && ( ! empty( $tokens['refresh_token'] ) || ! empty( $tokens['access_token'] ) ) ) {
                $candidates[] = array( 'auth' => 'oauth', 'site' => $site );
            }
        }

        if ( class_exists( 'TaxoCommerce_Google_Bing_Connections', false ) ) {
            $settings = (array) TaxoCommerce_Google_Bing_Connections::google_settings();
            $site     = trim( (string) ( $settings['search_console_site_url'] ?? '' ) );
            if ( '' !== $site && ! empty( $settings['service_account_json'] ) ) {
                $candidates[] = array( 'auth' => 'service_account', 'site' => $site );
            }
        }

        return $candidates;
    }

    private static function selected() {
        $candidates = self::candidates();
        $saved = get_option( self::SELECTION_OPTION, array() );
        if ( is_array( $saved ) ) {
            foreach ( $candidates as $candidate ) {
                if ( $candidate['auth'] === ( $saved['auth'] ?? '' )
                    && $candidate['site'] === ( $saved['site'] ?? '' ) ) {
                    return $candidate;
                }
            }
        }

        return $candidates ? $candidates[0] : array();
    }

    /**
     * Supply the existing SQL analysis engine with a MODERN property ID.
     * Other historical options remain readable but are never rewritten here.
     */
    public static function settings( $legacy ) {
        $legacy = is_array( $legacy ) ? $legacy : array();
        $settings = wp_parse_args(
            $legacy,
            array(
                'client_id'      => '',
                'client_secret'  => '',
                'property_id'    => '',
                'sync_frequency' => 'manual',
                'last_sync'      => '',
            )
        );
        $choice = self::selected();
        if ( $choice ) {
            $settings['property_id'] = $choice['site'];
        }
        $settings['sync_frequency'] = 'manual';
        $settings['last_sync'] = (string) get_option( self::LAST_SYNC_OPTION, $settings['last_sync'] );

        return $settings;
    }

    public static function status( $legacy ) {
        if ( self::selected() ) {
            return 'connected';
        }
        $legacy = is_array( $legacy ) ? $legacy : array();
        return ! empty( $legacy['property_id'] ) ? 'not_authorized' : 'missing_property';
    }

    private static function token_for( $source ) {
        if ( 'oauth' === $source['auth'] ) {
            return TaxoCommerce_Google_Bing_OAuth::access_token();
        }
        return TaxoCommerce_Google_Bing_Connections::access_token(
            'https://www.googleapis.com/auth/webmasters.readonly'
        );
    }

    /**
     * The old importer requests one day per AJAX call. It must use the
     * credential verified at the beginning of that run, not legacy wp_options.
     */
    public static function access_token() {
        $choice = self::selected();
        if ( ! $choice ) {
            return new WP_Error(
                'tgb_gsc_sync_not_configured',
                'No hay credenciales y propiedad de Search Console configuradas en Connections.'
            );
        }
        return self::token_for( $choice );
    }

    /**
     * One short real query catches revoked OAuth tokens, property mismatches,
     * permission errors and service-account access issues before any SQL write.
     */
    public static function verify_and_select() {
        $candidates = self::candidates();
        if ( ! $candidates ) {
            return new WP_Error(
                'tgb_gsc_sync_not_configured',
                'Falta una autorización OAuth o una cuenta de servicio con acceso a una propiedad Search Console.'
            );
        }

        $selected = self::selected();
        if ( $selected ) {
            usort( $candidates, static function ( $a, $b ) use ( $selected ) {
                return (int) ( $b === $selected ) - (int) ( $a === $selected );
            } );
        }

        $day = gmdate( 'Y-m-d', time() - ( 3 * DAY_IN_SECONDS ) );
        $errors = array();
        foreach ( $candidates as $source ) {
            $token = self::token_for( $source );
            if ( is_wp_error( $token ) ) {
                $errors[] = $source['auth'] . ': ' . $token->get_error_message();
                continue;
            }
            if ( ! is_string( $token ) || '' === $token ) {
                $errors[] = $source['auth'] . ': token no disponible';
                continue;
            }

            $url = 'https://www.googleapis.com/webmasters/v3/sites/'
                . rawurlencode( $source['site'] ) . '/searchAnalytics/query';
            $response = wp_remote_post(
                $url,
                array(
                    'timeout' => 30,
                    'headers' => array(
                        'Authorization' => 'Bearer ' . $token,
                        'Content-Type'  => 'application/json; charset=utf-8',
                        'Accept'        => 'application/json',
                    ),
                    'body' => wp_json_encode(
                        array(
                            'startDate'  => $day,
                            'endDate'    => $day,
                            'dimensions' => array(),
                            'type'       => 'web',
                            'dataState'  => 'final',
                            'rowLimit'   => 1,
                        )
                    ),
                )
            );
            if ( is_wp_error( $response ) ) {
                $errors[] = $source['auth'] . ': ' . $response->get_error_message();
                continue;
            }

            $status = (int) wp_remote_retrieve_response_code( $response );
            if ( $status >= 200 && $status < 300 ) {
                update_option( self::SELECTION_OPTION, $source, false );
                return $source;
            }
            $body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
            $message = is_array( $body ) && ! empty( $body['error']['message'] )
                ? sanitize_text_field( (string) $body['error']['message'] ) : '';
            $errors[] = $source['auth'] . ' HTTP ' . $status . ( $message ? ': ' . $message : '' );
        }

        return new WP_Error(
            'tgb_gsc_sync_access_denied',
            'No se puede consultar Search Console con las credenciales actuales. '
            . implode( '; ', $errors )
        );
    }

    public static function record_completed() {
        update_option( self::LAST_SYNC_OPTION, current_time( 'mysql' ), false );
    }
}
