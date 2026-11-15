<?php
/**
 * Storage, validation and diagnostics for standalone Redirects.
 *
 * The existing seo_redirects schema is intentionally reused so legacy rules
 * survive activation/deactivation and no migration of production URLs occurs.
 * This is the only custom table owned/shared by this module.
 */

namespace TaxoCommerce\Redirects;

defined( 'ABSPATH' ) || exit;

final class Store {
    const MAX_ROWS = 50000;
    const MAX_IMPORT_BYTES = 26214400;

    public static function table() {
        global $wpdb;
        return $wpdb->prefix . 'seo_redirects';
    }

    public static function table_exists( $refresh = false ) {
        static $cached = null;
        if ( null !== $cached && ! $refresh ) {
            return $cached;
        }
        global $wpdb;
        $name = self::table();
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema existence is checked in memory and refreshed on installation.
        $result = $wpdb->get_var(
            $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $name ) )
        );
        $cached = (string) $result === $name;
        return $cached;
    }

    /**
     * Match the legacy schema exactly; NEVER overwrite or truncate its rows.
     */
    public static function install() {
        if ( self::table_exists() ) {
            update_option( 'tc_redirects_schema_ready', '1', false );
            return true;
        }
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $table = self::table();
        $charset = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            origin_url VARCHAR(255) NOT NULL,
            target_url VARCHAR(255) NOT NULL,
            status_code SMALLINT UNSIGNED NOT NULL DEFAULT 301,
            hits BIGINT UNSIGNED NOT NULL DEFAULT 0,
            last_hit DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY origin_url (origin_url),
            KEY status_code (status_code)
        ) {$charset};";
        dbDelta( $sql );
        if ( self::table_exists( true ) ) {
            update_option( 'tc_redirects_schema_ready', '1', false );
            return true;
        }
        delete_option( 'tc_redirects_schema_ready' );
        return new \WP_Error( 'tc_redirects_install', 'No se ha podido crear la tabla de redirecciones. Revisa los permisos CREATE de la base de datos.' );
    }

    public static function same_host( $left, $right ) {
        $left = preg_replace( '/^www\./i', '', strtolower( (string) $left ) );
        $right = preg_replace( '/^www\./i', '', strtolower( (string) $right ) );
        return '' !== $left && $left === $right;
    }

    private static function path( $value ) {
        $value = trim( (string) $value );
        if ( '' === $value || false !== strpos( $value, '\\' ) || preg_match( '/[\x00-\x1f]/', $value ) ) {
            return new \WP_Error( 'tc_redirects_path', 'La ruta contiene caracteres no validos.' );
        }
        if ( 0 === strpos( $value, '//' ) ) {
            return new \WP_Error( 'tc_redirects_protocol_relative', 'No se admiten URLs sin protocolo.' );
        }
        $value = '/' . ltrim( $value, '/' );
        $value = preg_replace( '#/+#', '/', $value );
        return '/' === $value ? $value : rtrim( $value, '/' );
    }

    public static function normalize_origin( $value ) {
        if ( ! is_scalar( $value ) ) {
            return new \WP_Error( 'tc_redirects_origin_type', 'La ruta de origen no es valida.' );
        }
        $value = trim( (string) $value );
        if ( preg_match( '#^[a-z][a-z0-9+.-]*://#i', $value ) && ! preg_match( '#^https?://#i', $value ) ) {
            return new \WP_Error( 'tc_redirects_origin_protocol', 'Solo se admiten URLs HTTP o HTTPS.' );
        }
        if ( '' === $value || false !== strpos( $value, '?' ) ) {
            return new \WP_Error( 'tc_redirects_origin', 'El origen debe ser una ruta y no puede contener parametros.' );
        }
        if ( false !== strpos( $value, '#' ) ) {
            $value = explode( '#', $value, 2 )[0];
        }
        if ( preg_match( '#^https?://#i', $value ) ) {
            $host = wp_parse_url( $value, PHP_URL_HOST );
            if ( ! self::same_host( $host, wp_parse_url( home_url( '/' ), PHP_URL_HOST ) ) ) {
                return new \WP_Error( 'tc_redirects_origin_domain', 'El origen debe pertenecer a este sitio.' );
            }
            $value = (string) wp_parse_url( $value, PHP_URL_PATH );
        }
        $path = self::path( $value );
        if ( is_wp_error( $path ) ) {
            return $path;
        }
        if ( '/' === $path || preg_match( '#^/(?:wp-admin|wp-json)(?:/|$)|^/wp-login\.php(?:/|$)#i', $path ) ) {
            return new \WP_Error( 'tc_redirects_protected', 'No se permite redirigir la raiz ni rutas de administracion.' );
        }
        if ( strlen( $path ) > 255 ) {
            return new \WP_Error( 'tc_redirects_origin_length', 'La URL de origen supera el limite de 255 bytes.' );
        }
        return $path;
    }

    public static function normalize_target( $value ) {
        if ( ! is_scalar( $value ) ) {
            return new \WP_Error( 'tc_redirects_target_type', 'La URL destino no es valida.' );
        }
        $value = trim( (string) $value );
        if ( preg_match( '#^[a-z][a-z0-9+.-]*://#i', $value ) && ! preg_match( '#^https?://#i', $value ) ) {
            return new \WP_Error( 'tc_redirects_target_protocol', 'Solo se admiten URLs HTTP o HTTPS.' );
        }
        if ( '' === $value ) {
            return new \WP_Error( 'tc_redirects_target', 'Selecciona una URL de destino.' );
        }
        if ( 0 === strpos( $value, '//' ) ) {
            return new \WP_Error( 'tc_redirects_external', 'Las URLs sin protocolo no estan permitidas.' );
        }
        if ( preg_match( '#^https?://#i', $value ) ) {
            $host = wp_parse_url( $value, PHP_URL_HOST );
            if ( ! self::same_host( $host, wp_parse_url( home_url( '/' ), PHP_URL_HOST ) ) ) {
                return new \WP_Error( 'tc_redirects_external', 'Por seguridad, esta version permite destinos del propio dominio.' );
            }
            $path = (string) wp_parse_url( $value, PHP_URL_PATH );
            $query = (string) wp_parse_url( $value, PHP_URL_QUERY );
            $fragment = (string) wp_parse_url( $value, PHP_URL_FRAGMENT );
            $value = ( '' !== $path ? $path : '/' )
                . ( '' !== $query ? '?' . $query : '' )
                . ( '' !== $fragment ? '#' . $fragment : '' );
        }
        $path = explode( '#', $value, 2 );
        $fragment = isset( $path[1] ) ? '#' . $path[1] : '';
        $query_parts = explode( '?', $path[0], 2 );
        $query = isset( $query_parts[1] ) ? '?' . $query_parts[1] : '';
        $normalized = self::path( $query_parts[0] );
        if ( is_wp_error( $normalized ) ) {
            return $normalized;
        }
        $value = $normalized . $query . $fragment;
        if ( strlen( $value ) > 255 ) {
            return new \WP_Error( 'tc_redirects_target_length', 'La URL de destino supera el limite de 255 bytes.' );
        }
        return $value;
    }

    public static function effective_path( $value ) {
        if ( ! is_scalar( $value ) ) {
            return null;
        }
        $value = trim( (string) $value );
        if ( '' === $value ) {
            return null;
        }
        if ( preg_match( '#^https?://#i', $value ) ) {
            if ( ! self::same_host( wp_parse_url( $value, PHP_URL_HOST ), wp_parse_url( home_url( '/' ), PHP_URL_HOST ) ) ) {
                return null;
            }
            $value = (string) wp_parse_url( $value, PHP_URL_PATH );
        }
        $value = explode( '#', $value, 2 )[0];
        $value = explode( '?', $value, 2 )[0];
        $path = self::path( $value );
        return is_wp_error( $path ) ? null : $path;
    }

    public static function absolute_target( $target ) {
        $target = self::normalize_target( $target );
        if ( is_wp_error( $target ) ) {
            return $target;
        }
        $path = ltrim( $target, '/' );
        $absolute = home_url( '/' . $path );
        $safe = wp_validate_redirect( $absolute, '' );
        return '' !== $safe ? $safe : new \WP_Error( 'tc_redirects_unsafe_target', 'Destino rechazado por WordPress.' );
    }

    public static function rows() {
        global $wpdb;
        if ( ! self::table_exists() ) {
            return array();
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Read-only bounded list must immediately reflect edits to redirect rules.
        return (array) $wpdb->get_results(
            $wpdb->prepare(
                'SELECT id, origin_url, target_url, status_code, hits, last_hit, created_at, updated_at FROM %i ORDER BY id DESC LIMIT %d',
                self::table(),
                self::MAX_ROWS
            )
        );
    }

    public static function get( $id ) {
        global $wpdb;
        if ( ! self::table_exists() ) {
            return null;
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- A changed or deleted redirect cannot be served from stale cache.
        return $wpdb->get_row(
            $wpdb->prepare(
                'SELECT id, origin_url, target_url, status_code, hits, last_hit FROM %i WHERE id = %d LIMIT 1',
                self::table(),
                absint( $id )
            )
        );
    }

    public static function find_origin( $origin ) {
        global $wpdb;
        $path = self::effective_path( $origin );
        if ( null === $path || ! self::table_exists() ) {
            return null;
        }
        $alternate = '/' === $path ? $path : $path . '/';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Live public redirect matching requires current rules, without stale cache.
        return $wpdb->get_row(
            $wpdb->prepare(
                'SELECT id, origin_url, target_url, status_code, hits, last_hit FROM %i WHERE origin_url IN (%s,%s) ORDER BY id ASC LIMIT 1',
                self::table(),
                $path,
                $alternate
            )
        );
    }

    /**
     * Obtain the final target of an existing chain without modifying any rule.
     * Ambiguous historical duplicate origins are deliberately not consolidated.
     *
     * @param int $id Redirect rule ID.
     * @return string|\WP_Error Terminal destination or an error.
     */
    public static function chain_destination( $id ) {
        $rule = self::get( absint( $id ) );
        if ( ! $rule ) {
            return new \WP_Error( 'tc_redirects_missing', __( 'La redirección no existe.', 'taxocommerce-redirects' ) );
        }

        $by_origin = array();
        foreach ( self::rows() as $row ) {
            $source = self::effective_path( $row->origin_url );
            if ( null === $source ) {
                continue;
            }
            if ( isset( $by_origin[ $source ] ) ) {
                $by_origin[ $source ] = false;
            } elseif ( ! array_key_exists( $source, $by_origin ) ) {
                $by_origin[ $source ] = $row;
            }
        }

        $origin = self::effective_path( $rule->origin_url );
        if ( null === $origin || ! isset( $by_origin[ $origin ] ) || false === $by_origin[ $origin ] ) {
            return new \WP_Error( 'tc_redirects_ambiguous', __( 'El origen tiene reglas duplicadas o es inválido.', 'taxocommerce-redirects' ) );
        }

        $visited = array( $origin => true );
        $cursor = (string) $rule->target_url;
        for ( $hop = 0; $hop < 20; $hop++ ) {
            $effective = self::effective_path( $cursor );
            if ( null === $effective || isset( $visited[ $effective ] ) ) {
                return new \WP_Error( 'tc_redirects_chain_cycle', __( 'La cadena contiene un destino inválido o un ciclo.', 'taxocommerce-redirects' ) );
            }
            $visited[ $effective ] = true;
            if ( ! array_key_exists( $effective, $by_origin ) ) {
                return 0 === $hop
                    ? new \WP_Error( 'tc_redirects_not_chain', __( 'La regla no forma parte de una cadena.', 'taxocommerce-redirects' ) )
                    : $cursor;
            }
            if ( false === $by_origin[ $effective ] ) {
                return new \WP_Error( 'tc_redirects_ambiguous', __( 'La cadena contiene un origen duplicado.', 'taxocommerce-redirects' ) );
            }
            $cursor = (string) $by_origin[ $effective ]->target_url;
        }
        return new \WP_Error( 'tc_redirects_chain_too_long', __( 'La cadena supera el límite de seguridad.', 'taxocommerce-redirects' ) );
    }

    private static function cycle( $rows, $origin, $target, $exclude_id = 0 ) {
        $map = array();
        foreach ( $rows as $row ) {
            if ( absint( $row->id ) === absint( $exclude_id ) ) {
                continue;
            }
            $source = self::effective_path( $row->origin_url );
            $dest = self::effective_path( $row->target_url );
            if ( null !== $source && null !== $dest && ! isset( $map[ $source ] ) ) {
                $map[ $source ] = $dest;
            }
        }
        $map[ $origin ] = $target;
        $seen = array();
        $cursor = $origin;
        for ( $i = 0; $i <= count( $map ); $i++ ) {
            if ( isset( $seen[ $cursor ] ) ) {
                return true;
            }
            $seen[ $cursor ] = true;
            if ( ! isset( $map[ $cursor ] ) ) {
                return false;
            }
            $cursor = $map[ $cursor ];
        }
        return true;
    }

    public static function validate_rule( $origin_raw, $target_raw, $exclude_id = 0 ) {
        $origin = self::normalize_origin( $origin_raw );
        $target = self::normalize_target( $target_raw );
        if ( is_wp_error( $origin ) ) {
            return $origin;
        }
        if ( is_wp_error( $target ) ) {
            return $target;
        }
        $dest = self::effective_path( $target );
        if ( null === $dest || $origin === $dest ) {
            return new \WP_Error( 'tc_redirects_self', 'El destino no puede ser igual al origen.' );
        }
        $rows = self::rows();
        foreach ( $rows as $row ) {
            if ( absint( $row->id ) !== absint( $exclude_id )
                && self::effective_path( $row->origin_url ) === $origin ) {
                return new \WP_Error( 'tc_redirects_duplicate', 'Ya existe una regla con este origen. Edita la existente.' );
            }
        }
        if ( self::cycle( $rows, $origin, $dest, $exclude_id ) ) {
            return new \WP_Error( 'tc_redirects_cycle', 'La regla crearia un ciclo de redireccion.' );
        }
        return array( 'origin_url' => $origin, 'target_url' => $target );
    }

    public static function save( $origin, $target, $code = 301, $id = 0 ) {
        global $wpdb;
        if ( ! self::table_exists() ) {
            return new \WP_Error( 'tc_redirects_table_missing', 'No existe la tabla de redirecciones. Reactiva el plugin para instalarla.' );
        }
        $id = absint( $id );
        if ( $id && ! self::get( $id ) ) {
            return new \WP_Error( 'tc_redirects_missing', 'No existe la regla que intentas editar.' );
        }
        $validated = self::validate_rule( $origin, $target, $id );
        if ( is_wp_error( $validated ) ) {
            return $validated;
        }
        $code = in_array( absint( $code ), array( 301, 302 ), true ) ? absint( $code ) : 301;
        $fields = array(
            'origin_url' => $validated['origin_url'],
            'target_url' => $validated['target_url'],
            'status_code' => $code,
            'updated_at' => current_time( 'mysql', true ),
        );
        if ( $id ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Native immediate update to this plugin-owned table must remain consistent.
            $ok = $wpdb->update( self::table(), $fields, array( 'id' => $id ), array( '%s', '%s', '%d', '%s' ), array( '%d' ) );
            return false === $ok ? new \WP_Error( 'tc_redirects_db_update', 'Error al modificar la regla.' ) : $id;
        }
        $fields['hits'] = 0;
        $fields['last_hit'] = null;
        $fields['created_at'] = current_time( 'mysql', true );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Insert a validated redirect into this plugin-owned table.
        $ok = $wpdb->insert(
            self::table(),
            $fields,
            array( '%s', '%s', '%d', '%s', '%d', '%s', '%s' )
        );
        return false === $ok ? new \WP_Error( 'tc_redirects_db_insert', 'Error al insertar la regla.' ) : absint( $wpdb->insert_id );
    }

    public static function remove( $id ) {
        global $wpdb;
        if ( ! self::table_exists() || ! self::get( $id ) ) {
            return false;
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Deleting a rule must be immediately effective.
        return 1 === (int) $wpdb->delete( self::table(), array( 'id' => absint( $id ) ), array( '%d' ) );
    }

    public static function record_hit( $id ) {
        global $wpdb;
        if ( ! self::table_exists() ) {
            return;
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Atomic hit increments must not lose concurrent accesses.
        $wpdb->query(
            $wpdb->prepare(
                'UPDATE %i SET hits = hits + 1, last_hit = %s WHERE id = %d',
                self::table(),
                current_time( 'mysql' ),
                absint( $id )
            )
        );
    }

    /**
     * Basic structural diagnostics. A "correct" rule is NOT an external HTTP
     * availability verification for its destination.
     */
    public static function diagnostics( $rows ) {
        $by_source = array();
        $map = array();
        $total_hits = 0;
        $with_hits = 0;
        $last24 = 0;
        $last7 = 0;
        foreach ( $rows as $row ) {
            $source = self::effective_path( $row->origin_url );
            $target = self::effective_path( $row->target_url );
            $total_hits += absint( $row->hits );
            if ( absint( $row->hits ) > 0 ) {
                $with_hits++;
            }
            if ( $row->last_hit ) {
                $seconds = time() - strtotime( (string) $row->last_hit );
                if ( $seconds >= 0 && $seconds < DAY_IN_SECONDS ) {
                    $last24++;
                }
                if ( $seconds >= 0 && $seconds < 7 * DAY_IN_SECONDS ) {
                    $last7++;
                }
            }
            if ( null !== $source ) {
                $by_source[ $source ] = ( $by_source[ $source ] ?? 0 ) + 1;
                if ( null !== $target && ! isset( $map[ $source ] ) ) {
                    $map[ $source ] = $target;
                }
            }
        }
        $details = array();
        $counts = array(
            'total' => count( $rows ),
            'with_hits' => $with_hits,
            'without_hits' => count( $rows ) - $with_hits,
            'hits_total' => $total_hits,
            'used_last_24h_rules' => $last24,
            'used_last_7d_rules' => $last7,
            'structural' => 0,
            'duplicates' => 0,
            'chains' => 0,
            'cycles' => 0,
            'external_targets' => 0,
        );
        foreach ( $rows as $row ) {
            $from = self::effective_path( $row->origin_url );
            $to = self::effective_path( $row->target_url );
            $issues = array();
            $chain = 0;
            $cycle = false;
            if ( null === $from || preg_match( '#^https?://#i', (string) $row->origin_url ) ) {
                $issues[] = 'Origen incompatible con el motor de rutas.';
            }
            if ( null !== $from && ( $by_source[ $from ] ?? 0 ) > 1 ) {
                $issues[] = 'Origen efectivo duplicado.';
                $counts['duplicates']++;
            }
            if ( null !== $from && $from === $to ) {
                $issues[] = 'Autoredireccion.';
            }
            if ( null === $to ) {
                $issues[] = 'Destino externo o no reconocido por el motor independiente.';
                $counts['external_targets']++;
            }
            if ( ! in_array( absint( $row->status_code ), array( 301, 302 ), true ) ) {
                $issues[] = 'Codigo HTTP no admitido: solo 301/302.';
            }
            if ( null !== $from && isset( $map[ $from ] ) ) {
                $seen = array();
                $current = $from;
                while ( isset( $map[ $current ] ) && $chain < 25 ) {
                    if ( isset( $seen[ $current ] ) ) {
                        $cycle = true;
                        break;
                    }
                    $seen[ $current ] = true;
                    $chain++;
                    $current = $map[ $current ];
                }
                if ( $cycle ) {
                    $issues[] = 'Ciclo de redirecciones.';
                    $counts['cycles']++;
                } elseif ( $chain > 1 ) {
                    $issues[] = 'Cadena de ' . $chain . ' saltos.';
                    $counts['chains']++;
                }
            }
            if ( $issues ) {
                $counts['structural']++;
            }
            $details[ absint( $row->id ) ] = array(
                'issues' => $issues,
                'severity' => $cycle || ( null !== $from && $from === $to ) ? 'error' : ( $issues ? 'warning' : 'ok' ),
                'chain_hops' => $chain,
                'cycle' => $cycle,
            );
        }
        return array( 'stats' => $counts, 'details' => $details );
    }
}
