<?php
/**
 * One HTTP redirect engine shared with the legacy seo_redirects table.
 *
 * When the monolith is also active, unregister its old send_headers callback
 * during this request. Deactivation of this plugin restores old behavior.
 */

namespace TaxoCommerce\Redirects;

defined( 'ABSPATH' ) || exit;

final class Engine {
    const MAX_CHAIN_HOPS = 20;

    public static function register() {
        add_action( 'init', array( __CLASS__, 'disable_legacy_runtime' ), 100 );
        add_action( 'template_redirect', array( __CLASS__, 'maybe_redirect' ), 0 );
        add_filter( 'opnv_redirects_owner', array( __CLASS__, 'claim_opnv_redirects' ) );
        add_action( 'opnv_redirect_slug_changed', array( __CLASS__, 'save_from_opnv' ), 10, 3 );
    }

    /**
     * OPNV still notices slug changes; while this plugin owns redirects it hands
     * them over here instead of storing them itself. Store::save() validates
     * duplicates and cycles; a rejected rule is simply not added.
     *
     * @param string $from Old path.
     * @param string $to   New path.
     * @param int    $code HTTP status.
     * @return void
     */
    public static function save_from_opnv( $from, $to, $code = 301 ) {
        if ( 'taxocommerce' !== self::claim_opnv_redirects( 'opnv' ) ) {
            return;
        }
        Store::save( (string) $from, (string) $to, (int) $code );
    }

    /**
     * Tell OPNV that this plugin serves the redirects, so OPNV's own redirect
     * manager stands down and one request is never answered by two engines.
     * Only claimed once the storage is ready: until then OPNV keeps redirecting.
     *
     * @param mixed $owner Current owner id ('opnv' by default).
     * @return mixed
     */
    public static function claim_opnv_redirects( $owner ) {
        if ( '' !== self::external_owner() ) {
            return $owner; // The admin gave redirects to another plugin.
        }
        if ( '1' !== (string) get_option( 'tc_redirects_schema_ready', '' ) || ! Store::table_exists() ) {
            return $owner;
        }

        return 'taxocommerce';
    }

    /**
     * Whether this site has enabled the redirect engine.
     *
     * An absent setting preserves the existing enabled behavior.
     *
     * @return bool
     */
    public static function enabled() {
        return 'no' !== get_option( 'tc_redirects_enabled', 'yes' );
    }

    /**
     * Resolve the redirect owner without depending on an external plugin.
     *
     * @return string Empty when Redirects owns the requests, otherwise owner ID.
     */
    public static function external_owner() {
        if ( ! self::enabled() ) {
            return 'disabled';
        }

        $owner = sanitize_key( (string) apply_filters( 'taxocommerce_redirects_owner', 'taxocommerce' ) );

        return ( '' === $owner || 'taxocommerce' === $owner ) ? '' : $owner;
    }

    public static function disable_legacy_runtime() {
        if ( ! self::enabled() ) {
            return; // Do not suppress another redirect engine after opt-out.
        }

        // Runtime-only coordination. NEVER edit the monolith's source files.
        // If new storage is unavailable, leave the working legacy redirect
        // handler active instead of silently disabling live redirects.
        if ( '1' !== (string) get_option( 'tc_redirects_schema_ready', '' ) || ! Store::table_exists() ) {
            return;
        }
        if ( '1' !== (string) get_option( 'tc_redirects_schema_ready', '' ) ) {
            return;
        }
        if ( false !== has_action( 'send_headers', 'interceptar_redireccion_antes_de_wordpress' ) ) {
            remove_action( 'send_headers', 'interceptar_redireccion_antes_de_wordpress', 1 );
        }
    }

    public static function maybe_redirect() {
        if ( '' !== self::external_owner() ) {
            return;
        }
        if ( is_admin() || wp_doing_ajax() || wp_doing_cron()
            || ( defined( 'REST_REQUEST' ) && REST_REQUEST )
            || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
            return;
        }
        $method = isset( $_SERVER['REQUEST_METHOD'] )
            ? strtoupper( sanitize_key( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) )
            : 'GET';
        if ( ! in_array( $method, array( 'GET', 'HEAD' ), true ) ) {
            return;
        }
        if ( '1' !== (string) get_option( 'tc_redirects_schema_ready', '' ) ) {
            return;
        }

        $uri = isset( $_SERVER['REQUEST_URI'] )
            ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) )
            : '/';
        $path = wp_parse_url( $uri, PHP_URL_PATH );
        if ( ! is_string( $path ) || '' === $path ) {
            return;
        }
        if ( preg_match( '/\.(jpg|jpeg|png|gif|ico|css|js|webp|svg|woff|woff2|map|pdf|xml|txt)$/i', $path ) ) {
            return;
        }
        // Preserve the explicit exception in the original runtime.
        if ( false !== strpos( $path, 'logistica-embalaje-preparacion-de-pedidos' ) ) {
            return;
        }

        $origin = Store::effective_path( $path );
        if ( null === $origin ) {
            return;
        }
        $rule = Store::find_origin( $origin );
        if ( ! $rule ) {
            return;
        }

        // Historical entries can contain chains/cycles even when new writes
        // validate them. Never issue a redirect into a detected cycle.
        $visited = array( $origin => true );
        $cursor = (string) $rule->target_url;
        $terminated = false;
        for ( $hop = 0; $hop < self::MAX_CHAIN_HOPS; $hop++ ) {
            $effective = Store::effective_path( $cursor );
            if ( null === $effective || isset( $visited[ $effective ] ) ) {
                return;
            }
            $visited[ $effective ] = true;
            $next = Store::find_origin( $effective );
            if ( ! $next ) {
                $terminated = true;
                break;
            }
            $cursor = (string) $next->target_url;
        }
        if ( ! $terminated ) {
            return;
        }

        // Redirect directly to the terminal URL, not to the first intermediate
        // rule. Existing database rows are left untouched on public requests.
        $target = Store::absolute_target( $cursor );
        if ( is_wp_error( $target ) ) {
            return;
        }

        $status = absint( $rule->status_code );
        if ( ! in_array( $status, array( 301, 302 ), true ) ) {
            return;
        }
        // WordPress enforces allowed hosts; the store only accepts this site's
        // host. The rule's status code is honored (unlike the old runtime).
        if ( wp_safe_redirect( $target, $status, 'TaxoCommerce Redirects' ) ) {
            Store::record_hit( $rule->id );
            exit;
        }
    }
}
