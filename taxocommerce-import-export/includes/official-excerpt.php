<?php
/**
 * Optional official-page excerpt for one standalone TaxoCommerce plugin.
 *
 * This copy is generated for each plugin. No connection to another
 * TaxoCommerce plugin or a particular commercial website is required.
 *
 * @package TaxoCommerce
 */
defined( 'ABSPATH' ) || exit;

return new class() {
    private function labels( $locale ) {
        $labels = array(
            'en' => array( 'Plugin information', 'Official plugin page', 'No official page configured.', 'Excerpt temporarily unavailable.' ),
            'es' => array( 'Información del plugin', 'Ver ficha oficial', 'No hay ficha oficial configurada.', 'Extracto temporalmente no disponible.' ),
            'fr' => array( 'Informations sur le plugin', 'Voir la fiche officielle', 'Aucune page officielle configurée.', 'Extrait temporairement indisponible.' ),
            'de' => array( 'Plugininformationen', 'Offizielle Pluginseite', 'Keine offizielle Seite konfiguriert.', 'Auszug vorübergehend nicht verfügbar.' ),
            'nl' => array( 'Plugininformatie', 'Officiële pluginpagina', 'Geen officiële pagina ingesteld.', 'Samenvatting tijdelijk niet beschikbaar.' ),
        );
        // Prefer the native WordPress language catalog where it is available.
        $english = $labels['en'];
        $selected = $labels[ isset( $labels[ $locale ] ) ? $locale : 'en' ];
        $singular = array(
            __( 'Plugin information', 'taxocommerce-import-export' ),
            __( 'Official plugin page', 'taxocommerce-import-export' ),
            __( 'No official page configured.', 'taxocommerce-import-export' ),
            __( 'Excerpt temporarily unavailable.', 'taxocommerce-import-export' ),
        );
        foreach ( $singular as $index => $translated ) {
            if ( $translated !== $english[ $index ] ) {
                $selected[ $index ] = $translated;
            }
        }
        return $selected;
    }

    /**
     * Fetch only the public REST excerpt at the developer-supplied Plugin URI.
     * Cached by site and source; errors are cached too to protect performance.
     */
    private function excerpt( $url ) {
        $key = 'tc_official_excerpt_' . md5( $url );
        $cached = get_transient( $key );
        if ( is_array( $cached ) && isset( $cached['text'] ) ) {
            return $cached['text'];
        }

        $parts = wp_parse_url( $url );
        $path = is_array( $parts ) && isset( $parts['path'] ) ? trim( $parts['path'], '/' ) : '';
        $slug = $path ? sanitize_title( rawurldecode( basename( $path ) ) ) : '';
        if ( ! is_array( $parts ) || empty( $parts['host'] ) || 'https' !== ( $parts['scheme'] ?? '' ) || ! $slug ) {
            return '';
        }
        $origin = 'https://' . $parts['host'] . ( isset( $parts['port'] ) ? ':' . absint( $parts['port'] ) : '' );
        $endpoint = add_query_arg(
            array( 'slug' => $slug, 'per_page' => 1, '_fields' => 'excerpt,link,status' ),
            $origin . '/wp-json/wp/v2/pages'
        );
        $text = '';
        $response = wp_safe_remote_get(
            $endpoint,
            array( 'timeout' => 4, 'redirection' => 2, 'limit_response_size' => 65536 )
        );
        if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
            $rows = json_decode( wp_remote_retrieve_body( $response ), true );
            if ( is_array( $rows ) && isset( $rows[0]['excerpt']['rendered'], $rows[0]['link'] )
                && is_string( $rows[0]['excerpt']['rendered'] ) ) {
                $source_link = esc_url_raw( $rows[0]['link'], array( 'https' ) );
                $source_host = wp_parse_url( $source_link, PHP_URL_HOST );
                $source_path = wp_parse_url( $source_link, PHP_URL_PATH );
                $returned_slug = sanitize_title( rawurldecode( basename( trim( (string) $source_path, '/' ) ) ) );
                if ( is_string( $source_host ) && strtolower( $source_host ) === strtolower( $parts['host'] )
                    && $returned_slug === $slug ) {
                    $text = wp_html_excerpt(
                        html_entity_decode( wp_strip_all_tags( $rows[0]['excerpt']['rendered'] ), ENT_QUOTES, 'UTF-8' ),
                        700,
                        '…'
                    );
                }
            }
        }
        set_transient( $key, array( 'text' => $text ), $text ? 12 * HOUR_IN_SECONDS : 15 * MINUTE_IN_SECONDS );
        return $text;
    }

    /**
     * Same official excerpt, shown inside the hero (no <details>).
     * Nothing is printed when the plugin has no official page.
     */
    public function render_hero( $slug, $locale ) {
        $words = $this->labels( $locale );
        $plugin_file = dirname( __DIR__ ) . '/' . basename( dirname( __DIR__ ) ) . '.php';
        $page = '';
        if ( is_readable( $plugin_file ) ) {
            $headers = get_file_data( $plugin_file, array( 'uri' => 'Plugin URI' ), 'plugin' );
            $page = isset( $headers['uri'] ) && is_string( $headers['uri'] ) ? $headers['uri'] : '';
        }
        $page = $page ? esc_url_raw( $page, array( 'https' ) ) : '';
        if ( ! $page ) {
            return;
        }
        $excerpt = $this->excerpt( $page );
        echo '<div class="tc-family-hero__info">';
        if ( $excerpt ) {
            echo '<p class="tc-family-hero__excerpt">' . esc_html( $excerpt ) . '</p>';
        }
        echo '<a class="tc-family-hero__link" href="' . esc_url( $page ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $words[1] ) . '</a>';
        echo '</div>';
    }

    public function render( $slug, $locale ) {
        $words = $this->labels( $locale );
        // Plugin URI belongs to the plugin developer. A store administrator
        // cannot change the official documentation source from this screen.
        $plugin_file = dirname( __DIR__ ) . '/' . basename( dirname( __DIR__ ) ) . '.php';
        $page = '';
        if ( is_readable( $plugin_file ) ) {
            $headers = get_file_data( $plugin_file, array( 'uri' => 'Plugin URI' ), 'plugin' );
            $page = isset( $headers['uri'] ) && is_string( $headers['uri'] ) ? $headers['uri'] : '';
        }
        $page = $page ? esc_url_raw( $page, array( 'https' ) ) : '';
        $excerpt = $page ? $this->excerpt( $page ) : '';

        echo '<details class="tc-family-info">';
        echo '<summary>' . esc_html( $words[0] ) . '</summary>';
        echo '<div class="tc-family-info__content">';
        if ( $excerpt ) {
            echo '<p>' . esc_html( $excerpt ) . '</p>';
        } else {
            echo '<p>' . esc_html( $page ? $words[3] : $words[2] ) . '</p>';
        }
        if ( $page ) {
            echo '<p><a href="' . esc_url( $page ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $words[1] ) . '</a></p>';
        }
        echo '</div></details>';
    }
};
