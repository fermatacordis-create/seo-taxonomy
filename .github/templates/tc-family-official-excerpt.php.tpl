<?php
/**
 * Local developer information for a standalone TaxoCommerce plugin.
 *
 * Uses only its bundled WordPress plugin headers. No remote request, cache,
 * WordPress.org API dependency or other TaxoCommerce plugin is required.
 *
 * @package TaxoCommerce
 */
defined( 'ABSPATH' ) || exit;

return new class() {
    /**
     * Localize the panel chrome. The plugin Description remains the author's
     * own metadata, not a remotely fetched or machine-translated excerpt.
     *
     * @param string $locale WordPress administration locale.
     * @return array Two UI labels.
     */
    private function labels( $locale ) {
        $labels = array(
            'en' => array( 'Plugin information', 'Official plugin page' ),
            'es' => array( 'Información del plugin', 'Ver ficha oficial' ),
            'fr' => array( 'Informations sur le plugin', 'Voir la fiche officielle' ),
            'de' => array( 'Plugininformationen', 'Offizielle Pluginseite' ),
            'nl' => array( 'Plugininformatie', 'Officiële pluginpagina' ),
        );
        $language = strtolower( substr( (string) $locale, 0, 2 ) );
        $chosen   = isset( $labels[ $language ] ) ? $labels[ $language ] : $labels['en'];
        $english  = $labels['en'];
        $native   = array(
            __( 'Plugin information', '__TC_DOMAIN__' ),
            __( 'Official plugin page', '__TC_DOMAIN__' ),
        );
        foreach ( $native as $index => $translated ) {
            if ( $translated !== $english[ $index ] ) {
                $chosen[ $index ] = $translated;
            }
        }
        return $chosen;
    }

    /**
     * Show bundled Description and developer Plugin URI without a request.
     *
     * @param string $slug   Reserved for compatibility with existing headers.
     * @param string $locale WordPress administration locale.
     * @return void
     */
    public function render( $slug, $locale ) {
        $labels      = $this->labels( $locale );
        $plugin_dir  = dirname( __DIR__ );
        $plugin_file = $plugin_dir . '/' . basename( $plugin_dir ) . '.php';
        $headers     = is_readable( $plugin_file )
            ? get_file_data( $plugin_file, array( 'uri' => 'Plugin URI', 'description' => 'Description' ), 'plugin' )
            : array();

        $description = isset( $headers['description'] ) && is_string( $headers['description'] )
            ? trim( wp_strip_all_tags( $headers['description'] ) )
            : '';
        $page = isset( $headers['uri'] ) && is_string( $headers['uri'] )
            ? esc_url_raw( $headers['uri'], array( 'https' ) )
            : '';

        echo '<details class="tc-family-info">';
        echo '<summary>' . esc_html( $labels[0] ) . '</summary>';
        echo '<div class="tc-family-info__content">';
        if ( '' !== $description ) {
            echo '<p>' . esc_html( $description ) . '</p>';
        }
        if ( $page ) {
            echo '<p><a href="' . esc_url( $page ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $labels[1] ) . '</a></p>';
        }
        echo '</div></details>';
    }
};
