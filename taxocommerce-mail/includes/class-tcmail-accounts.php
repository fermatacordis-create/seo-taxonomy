<?php
/**
 * Encrypted multi-account settings. No personal message is stored in WordPress.
 *
 * @package TaxoCommerceMail
 */
defined( 'ABSPATH' ) || exit;
final class TCMAIL_Accounts {
    const OPTION = 'tcmail_accounts';
    const LIMIT = 20;

    public static function ready() {
        return function_exists( 'openssl_encrypt' ) && function_exists( 'openssl_decrypt' ) &&
            function_exists( 'random_bytes' ) && in_array( 'aes-256-gcm', openssl_get_cipher_methods(), true );
    }

    private static function key() {
        return hash( 'sha256', wp_salt( 'auth' ) . ':' . wp_salt( 'secure_auth' ) . ':tcmail-v1', true );
    }

    public static function encrypt( $password ) {
        if ( ! self::ready() || ! is_string( $password ) || '' === $password || strlen( $password ) > 1024 || preg_match( '/[\x00\r\n]/', $password ) ) {
            return false;
        }
        $nonce = random_bytes( 12 );
        $tag = '';
        $encrypted = openssl_encrypt( $password, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $nonce, $tag );
        return false === $encrypted ? false : base64_encode( $nonce . $tag . $encrypted );
    }

    public static function decrypt( $encrypted ) {
        if ( ! self::ready() || ! is_string( $encrypted ) ) {
            return false;
        }
        $raw = base64_decode( $encrypted, true );
        if ( false === $raw || strlen( $raw ) < 29 ) {
            return false;
        }
        return openssl_decrypt( substr( $raw, 28 ), 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, substr( $raw, 0, 12 ), substr( $raw, 12, 16 ) );
    }

    private static function all() {
        $saved = get_option( self::OPTION, array() );
        return is_array( $saved ) ? $saved : array();
    }

    /** Only the administrator may retrieve secrets; never return them to a view. */
    public static function credentials( $id ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            return false;
        }
        foreach ( self::all() as $account ) {
            if ( isset( $account['id'] ) && hash_equals( $account['id'], $id ) ) {
                $secret = self::decrypt( isset( $account['secret'] ) ? $account['secret'] : '' );
                if ( false === $secret ) {
                    return false;
                }
                $account['password'] = $secret;
                unset( $account['secret'] );
                return $account;
            }
        }
        return false;
    }

    public static function public_list() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return array();
        }
        $accounts = array();
        foreach ( self::all() as $item ) {
            unset( $item['secret'] );
            $accounts[] = $item;
        }
        return $accounts;
    }

    private static function host( $value ) {
        $value = strtolower( trim( (string) $value ) );
        if ( strlen( $value ) > 253 || false === strpos( $value, '.' ) ||
            ! filter_var( $value, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME ) ) {
            return '';
        }
        return $value;
    }

    /**
     * Only TLS connections are supported; no insecure ports or server defaults.
     */
    /**
     * Expand the three-field IMAP quick setup without changing existing accounts.
     * A complete email address is used as the mailbox username.
     *
     * @param array $input Sanitized account settings.
     * @return array
     */
    public static function basic_defaults( $input ) {
        $username = isset( $input['username'] ) ? $input['username'] : '';
        if ( empty( $input['email'] ) ) {
            $input['email'] = sanitize_email( $username );
        }
        if ( empty( $input['name'] ) && is_email( $input['email'] ) ) {
            $input['name'] = strstr( $input['email'], '@', true );
        }
        if ( empty( $input['smtp_host'] ) && isset( $input['imap_host'] ) ) {
            $input['smtp_host'] = $input['imap_host'];
        }
        if ( empty( $input['smtp_port'] ) ) {
            $input['smtp_port'] = 465;
        }
        return $input;
    }

    public static function validate( $input ) {
        $email = isset( $input['email'] ) ? sanitize_email( $input['email'] ) : '';
        $name = isset( $input['name'] ) ? sanitize_text_field( $input['name'] ) : '';
        $imap = self::host( isset( $input['imap_host'] ) ? $input['imap_host'] : '' );
        $smtp = self::host( isset( $input['smtp_host'] ) ? $input['smtp_host'] : '' );
        $port = isset( $input['smtp_port'] ) ? absint( $input['smtp_port'] ) : 465;
        $username = isset( $input['username'] ) ? sanitize_text_field( $input['username'] ) : '';
        if ( ! is_email( $email ) || ! $name || strlen( $name ) > 100 || ! $imap || ! $smtp ||
            ! in_array( $port, array( 465, 587 ), true ) || ! $username || strlen( $username ) > 190 ||
            preg_match( '/[\x00-\x1F\x7F]/', $username ) ) {
            return false;
        }
        return array(
            'name' => $name, 'email' => $email,
            'imap_host' => $imap, 'smtp_host' => $smtp,
            'smtp_port' => $port, 'username' => $username,
        );
    }

    public static function save( $input, $password, $id = '' ) {
        if ( ! current_user_can( 'manage_options' ) || ! self::ready() ) {
            return false;
        }
        $data = self::validate( $input );
        if ( ! $data ) {
            return false;
        }
        $list = self::all();
        $position = null;
        foreach ( $list as $i => $item ) {
            if ( $id && isset( $item['id'] ) && hash_equals( $item['id'], $id ) ) {
                $position = $i;
                break;
            }
        }
        if ( $id && null === $position ) {
            return false;
        }
        if ( null === $position && count( $list ) >= self::LIMIT ) {
            return false;
        }
        $secret = $password !== '' ? self::encrypt( $password ) : ( null !== $position ? $list[ $position ]['secret'] : false );
        if ( ! $secret ) {
            return false;
        }
        $data['secret'] = $secret;
        $data['id'] = null !== $position ? $list[ $position ]['id'] : wp_generate_uuid4();
        if ( null === $position ) {
            $list[] = $data;
        } else {
            $list[ $position ] = $data;
        }
        update_option( self::OPTION, array_values( $list ), false );
        return true;
    }

    public static function remove( $id ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            return false;
        }
        $list = self::all();
        $filtered = array_values( array_filter( $list, static function ( $item ) use ( $id ) {
            return ! isset( $item['id'] ) || ! hash_equals( $item['id'], $id );
        } ) );
        if ( count( $filtered ) === count( $list ) ) {
            return false;
        }
        update_option( self::OPTION, $filtered, false );
        return true;
    }
}
