<?php
/**
 * Bounded encrypted IMAPS transport without requiring PHP's retired IMAP extension.
 *
 * Supports secure IMAP read and explicit administrator-approved MOVE, CREATE, RENAME
 * and APPEND. No background synchronization or permanent deletion.
 *
 * @package TaxoCommerceMailPro
 */
defined( 'ABSPATH' ) || exit;
final class TCMAILPRO_IMAP {
    const MAX_RESPONSE = 3145728;
    const MAX_MESSAGE = 2097152;
    const INBOX_LIMIT = 12;
    private $stream;
    private $counter = 0;
    private $selected = 'INBOX';
    private $readonly = true;

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


    /**
     * Server-side folders; retain raw IMAP mailbox names (modified UTF-7).
     * Only selectable, bounded folders are presented; never render them as HTML.
     */
    public function folders() {
        $reply = $this->command( 'LIST "" "*"', 131072 );
        $out = array();
        foreach ( preg_split( '/\r?\n/', $reply['response'] ) as $line ) {
            if ( ! preg_match( '/^\* LIST \(([^)]*)\) ("[^"]*"|NIL) (.+)$/i', trim( $line ), $match ) ) {
                continue;
            }
            if ( false !== stripos( $match[1], '\\Noselect' ) ) {
                continue;
            }
            $raw = trim( $match[3] );
            $name = 0 === strpos( $raw, '"' ) && '"' === substr( $raw, -1 )
                ? stripcslashes( substr( $raw, 1, -1 ) ) : $raw;
            if ( '' === $name || strlen( $name ) > 200 || preg_match( '/[\x00-\x1F\x7F]/', $name ) ) {
                continue;
            }
            $out[ $name ] = array(
                'name' => $name,
                'sent' => false !== stripos( $match[1], '\\Sent' ) || (bool) preg_match( '~(?:^|[./])sent(?: items| messages)?$~i', $name ),
                'trash' => false !== stripos( $match[1], '\\Trash' ) || (bool) preg_match( '~(?:^|[./])(?:trash|deleted items|bin)$~i', $name ),
                'system' => (bool) preg_match( '~(?:^|[./])(?:inbox|sent(?: items| messages)?|trash|deleted items|bin|junk|spam|drafts)$~i', $name ) || (bool) preg_match( '/\\\\(?:Sent|Trash|Drafts|Junk|All)/i', $match[1] ),
            );
            if ( count( $out ) >= 80 ) {
                break;
            }
        }
        if ( ! isset( $out['INBOX'] ) ) {
            $out = array( 'INBOX' => array( 'name' => 'INBOX', 'sent' => false, 'trash' => false, 'system' => true ) ) + $out;
        }
        return $out;
    }

    public static function special_folder( $folders, $kind ) {
        foreach ( $folders as $name => $entry ) {
            if ( ! empty( $entry[ $kind ] ) ) {
                return $name;
            }
        }
        return '';
    }

    public function select_folder( $name, $write = false ) {
        self::quote( $name );
        if ( $this->selected === $name && $this->readonly === ! $write ) {
            return;
        }
        $this->command( ( $write ? 'SELECT ' : 'EXAMINE ' ) . self::quote( $name ), 65536 );
        $this->selected = $name;
        $this->readonly = ! $write;
    }

    public function messages( $folder, $page = 1, $term = '' ) {
        $this->select_folder( $folder );
        $page = max( 1, min( 10000, (int) $page ) );
        $term = trim( (string) $term );
        if ( strlen( $term ) > 80 || preg_match( '/[\x00-\x1F\x7F]/', $term ) ) {
            throw new RuntimeException( 'invalid_search' );
        }
        $query = 'UID SEARCH ' . ( '' === $term ? 'ALL' : 'TEXT ' . self::quote( $term ) );
        $result = $this->command( $query, self::MAX_RESPONSE );
        if ( ! preg_match( '/^\* SEARCH(?: ([0-9 ]+))?/m', $result['response'], $match ) ) {
            return array( 'items' => array(), 'total' => 0, 'page' => $page );
        }
        $uids = ! empty( $match[1] ) ? preg_split( '/\s+/', trim( $match[1] ) ) : array();
        $uids = array_reverse( array_filter( $uids, static function ( $uid ) {
            return ctype_digit( (string) $uid ) && (int) $uid > 0;
        } ) );
        $total = count( $uids );
        $page_uids = array_slice( $uids, ( $page - 1 ) * 20, 20 );
        $items = array();
        foreach ( $page_uids as $uid ) {
            $row = $this->command( 'UID FETCH ' . $uid . ' (BODY.PEEK[HEADER])', 98304 );
            if ( empty( $row['literals'][0] ) ) {
                continue;
            }
            $headers = self::parse_headers( $row['literals'][0] );
            $items[] = array(
                'uid' => (int) $uid,
                'from' => self::decode_header( isset( $headers['from'] ) ? $headers['from'] : '' ),
                'to' => self::decode_header( isset( $headers['to'] ) ? $headers['to'] : '' ),
                'subject' => self::decode_header( isset( $headers['subject'] ) ? $headers['subject'] : '' ),
                'date' => isset( $headers['date'] ) ? substr( $headers['date'], 0, 120 ) : '',
            );
        }
        return array( 'items' => $items, 'total' => $total, 'page' => $page );
    }

    public static function safe_uids( $input ) {
        if ( ! is_array( $input ) || count( $input ) < 1 || count( $input ) > 50 ) {
            return array();
        }
        $out = array();
        foreach ( $input as $value ) {
            if ( ! is_scalar( $value ) || ! preg_match( '/^[1-9][0-9]{0,9}$/D', (string) $value ) || (float) $value > 2147483647 ) {
                return array();
            }
            $out[] = (int) $value;
        }
        return array_values( array_unique( $out ) );
    }

    /** Requires RFC 6851 MOVE: no unsafe COPY + EXPUNGE fallback. */
    public function move( $from, $to, $uids ) {
        $uids = self::safe_uids( $uids );
        if ( ! $uids || $from === $to ) {
            throw new RuntimeException( 'invalid_move' );
        }
        $capabilities = $this->command( 'CAPABILITY', 16384 );
        if ( ! preg_match( '/(?:^|\s)MOVE(?:\s|\r|\n|$)/i', $capabilities['response'] ) ) {
            throw new RuntimeException( 'imap_move_unsupported' );
        }
        $this->select_folder( $from, true );
        $this->command( 'UID MOVE ' . implode( ',', $uids ) . ' ' . self::quote( $to ), 65536 );
        return count( $uids );
    }

    public static function valid_folder_name( $name ) {
        return is_string( $name ) && strlen( $name ) >= 1 && strlen( $name ) <= 60 &&
            (bool) preg_match( '/^[A-Za-z0-9][A-Za-z0-9 _.-]*$/D', $name ) &&
            ! preg_match( '/^(?:INBOX|Sent(?: Items| Messages)?|Trash|Deleted Items|Bin|Drafts|Junk|Spam)$/i', $name );
    }

    public function create_folder( $name ) {
        if ( ! self::valid_folder_name( $name ) ) {
            throw new RuntimeException( 'invalid_folder' );
        }
        $this->command( 'CREATE ' . self::quote( $name ), 65536 );
    }

    public function rename_folder( $old, $new, $folders ) {
        if ( ! isset( $folders[ $old ] ) || ! empty( $folders[ $old ]['system'] ) ||
            ! self::valid_folder_name( $new ) || isset( $folders[ $new ] ) ) {
            throw new RuntimeException( 'invalid_folder' );
        }
        $this->command( 'RENAME ' . self::quote( $old ) . ' ' . self::quote( $new ), 65536 );
    }

    /**
     * Save an SMTP-accepted MIME message in the server-side Sent folder.
     * No copy is written to WordPress options, tables, disk or logs.
     */
    public function append_sent( $folder, $mime ) {
        if ( ! is_string( $mime ) || '' === $mime || strlen( $mime ) > 131072 ) {
            throw new RuntimeException( 'invalid_sent_message' );
        }
        self::quote( $folder );
        $tag = 'T' . str_pad( (string) ++$this->counter, 5, '0', STR_PAD_LEFT );
        $command = $tag . ' APPEND ' . self::quote( $folder ) . ' (\\Seen) {' . strlen( $mime ) . "}\r\n";
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- TLS IMAP connection, not filesystem.
        if ( false === fwrite( $this->stream, $command ) ) {
            throw new RuntimeException( 'imap_write_failed' );
        }
        $reply = fgets( $this->stream, 4096 );
        if ( false === $reply || 0 !== strpos( $reply, '+' ) ) {
            throw new RuntimeException( 'imap_append_not_accepted' );
        }
        $payload = $mime . "\r\n";
        while ( '' !== $payload ) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- TLS IMAP connection.
            $sent = fwrite( $this->stream, $payload );
            if ( false === $sent || 0 === $sent ) {
                throw new RuntimeException( 'imap_write_failed' );
            }
            $payload = substr( $payload, $sent );
        }
        for ( $i = 0; $i < 20; $i++ ) {
            $response = fgets( $this->stream, 4096 );
            if ( false === $response ) {
                throw new RuntimeException( 'imap_read_failed' );
            }
            if ( 0 === strpos( $response, $tag . ' ' ) ) {
                if ( ! preg_match( '/^' . preg_quote( $tag, '/' ) . ' OK(?:\s|\r|\n)/i', $response ) ) {
                    throw new RuntimeException( 'imap_append_failed' );
                }
                return true;
            }
        }
        throw new RuntimeException( 'imap_append_failed' );
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
