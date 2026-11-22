<?php
/**
 * Bounded read-only IMAPS transport without requiring PHP's retired IMAP extension.
 *
 * Supports the basic RFC 3501 LOGIN, EXAMINE, UID SEARCH and UID FETCH commands
 * with mandatory peer TLS verification. No mailbox mutation or background sync.
 *
 * @package TaxoCommerceMail
 */
defined( 'ABSPATH' ) || exit;
final class TCMAIL_IMAP {
    const MAX_RESPONSE = 3145728;
    const MAX_MESSAGE = 2097152;
    const INBOX_LIMIT = 12;
    private $stream;
    private $counter = 0;

    public static function available() {
        return function_exists( 'stream_socket_client' ) && extension_loaded( 'openssl' );
    }

    private static function quote( $value ) {
        if ( ! is_string( $value ) || strlen( $value ) > 300 || preg_match( '/[\x00-\x1F\x7F]/', $value ) ) {
            throw new RuntimeException( 'invalid_credentials' );
        }
        return '"' . addcslashes( $value, "\\\"" ) . '"';
    }

    public function connect( $account ) {
        if ( ! self::available() || ! isset( $account['imap_host'], $account['username'], $account['password'] ) ) {
            throw new RuntimeException( 'imap_unavailable' );
        }
        $host = (string) $account['imap_host'];
        if ( '' === $host || strlen( $host ) > 253 || ! filter_var( $host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME ) ) {
            throw new RuntimeException( 'invalid_host' );
        }
        $context = stream_context_create( array(
            'ssl' => array(
                'verify_peer' => true,
                'verify_peer_name' => true,
                'allow_self_signed' => false,
                'peer_name' => $host,
                'SNI_enabled' => true,
            ),
        ) );
        $errno = 0;
        $error = '';
        // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Network TLS errors are intentionally converted to a generic admin notice.
        $stream = @stream_socket_client( 'ssl://' . $host . ':993', $errno, $error, 10, STREAM_CLIENT_CONNECT, $context );
        if ( false === $stream ) {
            throw new RuntimeException( 'tls_connection_failed' );
        }
        $this->stream = $stream;
        stream_set_timeout( $stream, 12 );
        $greeting = fgets( $stream, 16384 );
        if ( false === $greeting || 0 !== strpos( $greeting, '* OK' ) ) {
            $this->disconnect();
            throw new RuntimeException( 'imap_greeting_failed' );
        }
        try {
            $this->command( 'LOGIN ' . self::quote( $account['username'] ) . ' ' . self::quote( $account['password'] ), 65536 );
            $this->command( 'EXAMINE "INBOX"', 65536 );
        } catch ( RuntimeException $failure ) {
            $this->disconnect();
            throw $failure;
        }
    }

    private function command( $command, $limit = self::MAX_RESPONSE ) {
        if ( ! is_resource( $this->stream ) ) {
            throw new RuntimeException( 'imap_disconnected' );
        }
        $tag = 'T' . str_pad( (string) ++$this->counter, 5, '0', STR_PAD_LEFT );
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- IMAP TLS socket, not a WordPress filesystem path.
        $sent = fwrite( $this->stream, $tag . ' ' . $command . "\r\n" );
        if ( false === $sent ) {
            throw new RuntimeException( 'imap_write_failed' );
        }
        $output = '';
        $literals = array();
        $bytes = 0;
        for ( $iteration = 0; $iteration < 100000; ++$iteration ) {
            $line = fgets( $this->stream, min( 262144, $limit + 1 ) );
            if ( false === $line ) {
                throw new RuntimeException( 'imap_read_failed' );
            }
            $bytes += strlen( $line );
            if ( $bytes > $limit ) {
                throw new RuntimeException( 'imap_response_too_large' );
            }
            $output .= $line;
            if ( preg_match( '/\{([0-9]+)\}\r?\n$/', $line, $match ) ) {
                $length = (int) $match[1];
                if ( $length < 0 || $length > $limit - $bytes ) {
                    throw new RuntimeException( 'imap_message_too_large' );
                }
                $literal = '';
                while ( strlen( $literal ) < $length ) {
                    // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread -- IMAP TLS socket stream, not a filesystem file.
                    $chunk = fread( $this->stream, min( 16384, $length - strlen( $literal ) ) );
                    if ( false === $chunk || '' === $chunk ) {
                        throw new RuntimeException( 'imap_literal_read_failed' );
                    }
                    $literal .= $chunk;
                }
                $bytes += $length;
                $literals[] = $literal;
            }
            if ( 0 === strpos( $line, $tag . ' ' ) ) {
                if ( ! preg_match( '/^' . preg_quote( $tag, '/' ) . ' OK(?:\s|\r|\n)/i', $line ) ) {
                    throw new RuntimeException( 'imap_command_rejected' );
                }
                return array( 'response' => $output, 'literals' => $literals );
            }
            $status = stream_get_meta_data( $this->stream );
            if ( ! empty( $status['timed_out'] ) ) {
                throw new RuntimeException( 'imap_timeout' );
            }
        }
        throw new RuntimeException( 'imap_response_too_large' );
    }

    public function inbox() {
        $search = $this->command( 'UID SEARCH ALL', self::MAX_RESPONSE );
        if ( ! preg_match( '/^\* SEARCH(?: ([0-9 ]+))?/m', $search['response'], $matches ) ) {
            return array();
        }
        $uids = isset( $matches[1] ) && '' !== trim( $matches[1] ) ? preg_split( '/\s+/', trim( $matches[1] ) ) : array();
        $uids = array_slice( array_reverse( $uids ), 0, self::INBOX_LIMIT );
        $messages = array();
        foreach ( $uids as $uid ) {
            if ( ! preg_match( '/^[0-9]+$/', $uid ) || 0 === (int) $uid ) {
                continue;
            }
            try {
                $result = $this->command( 'UID FETCH ' . $uid . ' (BODY.PEEK[HEADER])', 98304 );
                if ( empty( $result['literals'][0] ) ) {
                    continue;
                }
                $headers = self::parse_headers( $result['literals'][0] );
                $messages[] = array(
                    'uid' => (int) $uid,
                    'from' => self::decode_header( isset( $headers['from'] ) ? $headers['from'] : '' ),
                    'subject' => self::decode_header( isset( $headers['subject'] ) ? $headers['subject'] : '' ),
                    'date' => isset( $headers['date'] ) ? substr( $headers['date'], 0, 120 ) : '',
                );
            } catch ( RuntimeException $failure ) {
                // A malformed single message should not break the entire mailbox.
                continue;
            }
        }
        return $messages;
    }

    public function message( $uid ) {
        $uid = (int) $uid;
        if ( $uid < 1 ) {
            throw new RuntimeException( 'imap_invalid_uid' );
        }
        $result = $this->command( 'UID FETCH ' . $uid . ' (BODY.PEEK[])', self::MAX_MESSAGE + 16384 );
        if ( empty( $result['literals'][0] ) || strlen( $result['literals'][0] ) > self::MAX_MESSAGE ) {
            throw new RuntimeException( 'message_unavailable' );
        }
        return self::parse_message( $result['literals'][0] );
    }

    public function disconnect() {
        if ( is_resource( $this->stream ) ) {
            // Closing the socket is safe even if LOGOUT could not complete.
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Close authenticated IMAPS network socket.
            fclose( $this->stream );
        }
        $this->stream = null;
    }

    public static function parse_headers( $block ) {
        $block = preg_replace( "/\r?\n[ \t]+/", ' ', (string) $block );
        $fields = array();
        foreach ( preg_split( '/\r?\n/', $block ) as $line ) {
            $split = strpos( $line, ':' );
            if ( false === $split ) {
                continue;
            }
            $key = strtolower( trim( substr( $line, 0, $split ) ) );
            if ( preg_match( '/^[a-z0-9-]+$/', $key ) && ! isset( $fields[ $key ] ) ) {
                $fields[ $key ] = trim( substr( $line, $split + 1 ) );
            }
        }
        return $fields;
    }

    public static function decode_header( $value ) {
        $value = (string) $value;
        if ( function_exists( 'iconv_mime_decode' ) ) {
            $decoded = iconv_mime_decode( $value, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8' );
            if ( false !== $decoded ) {
                $value = $decoded;
            }
        }
        return substr( preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value ), 0, 500 );
    }

    private static function split_message( $raw ) {
        $pos = strpos( $raw, "\r\n\r\n" );
        if ( false === $pos ) {
            $pos = strpos( $raw, "\n\n" );
            $sep = 2;
        } else {
            $sep = 4;
        }
        if ( false === $pos ) {
            return array( self::parse_headers( $raw ), '' );
        }
        return array( self::parse_headers( substr( $raw, 0, $pos ) ), substr( $raw, $pos + $sep ) );
    }

    private static function body( $headers, $data, $depth = 0 ) {
        if ( $depth > 4 ) {
            return '';
        }
        $type = isset( $headers['content-type'] ) ? $headers['content-type'] : 'text/plain';
        if ( 0 === stripos( $type, 'multipart/' ) && preg_match( '/boundary\s*=\s*(?:"([^"]+)"|([^;\s]+))/i', $type, $match ) ) {
            $boundary = isset( $match[1] ) && '' !== $match[1] ? $match[1] : $match[2];
            if ( strlen( $boundary ) > 100 ) {
                return '';
            }
            $alternatives = array();
            foreach ( explode( '--' . $boundary, $data ) as $part ) {
                if ( 0 === strpos( trim( $part ), '--' ) ) {
                    continue;
                }
                list( $h, $b ) = self::split_message( ltrim( $part, "\r\n" ) );
                if ( isset( $h['content-disposition'] ) && 0 === stripos( $h['content-disposition'], 'attachment' ) ) {
                    continue;
                }
                $rendered = self::body( $h, $b, $depth + 1 );
                if ( '' !== $rendered ) {
                    $alternatives[] = array( 'plain' => 0 === stripos( isset( $h['content-type'] ) ? $h['content-type'] : '', 'text/plain' ), 'text' => $rendered );
                }
            }
            foreach ( $alternatives as $alt ) {
                if ( $alt['plain'] ) {
                    return $alt['text'];
                }
            }
            return $alternatives ? $alternatives[0]['text'] : '';
        }
        if ( 0 !== stripos( $type, 'text/plain' ) && 0 !== stripos( $type, 'text/html' ) ) {
            return '';
        }
        $encoding = isset( $headers['content-transfer-encoding'] ) ? strtolower( trim( $headers['content-transfer-encoding'] ) ) : '';
        if ( 'base64' === $encoding ) {
            $decoded = base64_decode( $data, true );
            $data = false !== $decoded ? $decoded : '';
        } elseif ( 'quoted-printable' === $encoding ) {
            $data = quoted_printable_decode( $data );
        }
        if ( preg_match( '/charset\s*=\s*"?([a-zA-Z0-9_-]+)/i', $type, $charset ) &&
            'utf-8' !== strtolower( $charset[1] ) && function_exists( 'iconv' ) ) {
            $converted = iconv( $charset[1], 'UTF-8//IGNORE', $data );
            if ( false !== $converted ) {
                $data = $converted;
            }
        }
        if ( 0 === stripos( $type, 'text/html' ) ) {
            $data = html_entity_decode( wp_strip_all_tags( $data ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
        }
        return trim( substr( str_replace( "\0", '', $data ), 0, 18000 ) );
    }

    public static function parse_message( $raw ) {
        list( $headers, $content ) = self::split_message( (string) $raw );
        return array(
            'from' => self::decode_header( isset( $headers['from'] ) ? $headers['from'] : '' ),
            'reply_to' => self::decode_header( isset( $headers['reply-to'] ) ? $headers['reply-to'] : ( isset( $headers['from'] ) ? $headers['from'] : '' ) ),
            'subject' => self::decode_header( isset( $headers['subject'] ) ? $headers['subject'] : '' ),
            'date' => isset( $headers['date'] ) ? substr( $headers['date'], 0, 120 ) : '',
            'body' => self::body( $headers, $content ),
        );
    }
}
