<?php
/**
 * WordPress-localized, static developer Plugin URI link for a standalone plugin.
 *
 * Uses only its own bundled PHP plugin metadata. No remote requests, caches,
 * options or TaxoCommerce Core dependency are required.
 *
 * @package TaxoCommerce
 */
defined( 'ABSPATH' ) || exit;

return new class() {
    /**
     * Translate the link text using WordPress, with a local fallback.
     *
     * @param string $locale WordPress administration locale.
     * @return string
     */
    private function label( $locale ) {
        $label = __( 'Official plugin page', 'taxocommerce-auditor' );
        if ( 'Official plugin page' !== $label ) {
            return $label;
        }
        $translations = array(
            'es' => 'Ver ficha oficial',
            'en' => 'Official plugin page',
            'fr' => 'Voir la fiche officielle',
            'de' => 'Offizielle Pluginseite',
            'nl' => 'Officiële pluginpagina',
        );
        $language = strtolower( substr( (string) $locale, 0, 2 ) );
        return $translations[ $language ] ?? $translations['en'];
    }

    /**
     * Render a single link underneath the visible plugin Description.
     *
     * @param string $slug   Reserved for legacy renderer compatibility.
     * @param string $locale WordPress administration locale.
     * @return void
     */
    public function render( $slug, $locale ) {
        $plugin_dir  = dirname( __DIR__ );
        $plugin_file = $plugin_dir . '/' . basename( $plugin_dir ) . '.php';
        $headers     = is_readable( $plugin_file )
            ? get_file_data( $plugin_file, array( 'uri' => 'Plugin URI' ), 'plugin' )
            : array();
        $page = isset( $headers['uri'] ) && is_string( $headers['uri'] )
            ? esc_url_raw( $headers['uri'], array( 'https' ) )
            : '';

        if ( '' === $page ) {
            return;
        }

        echo '<a class="tc-family-hero__link" href="' . esc_url( $page ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $this->label( $locale ) ) . '</a>';
    }
};
