<?php
/**
 * Regression: never ship translated UI controls as empty gettext messages.
 *
 * When msgstr is empty it MUST be omitted from MO, so WordPress falls back
 * to the original msgid (including the Pages export selector).
 */
if ( 'cli' !== PHP_SAPI ) {
    exit;
}
$root = dirname( __DIR__ );
$domain = 'taxocommerce-import-export';
foreach ( array( 'es_ES', 'en_US', 'fr_FR', 'de_DE', 'nl_NL' ) as $locale ) {
    $path = $root . '/languages/' . $domain . '-' . $locale . '.mo';
    $data = file_get_contents( $path );
    if ( false === $data || strlen( $data ) < 28 ) {
        throw new RuntimeException( 'Missing / truncated MO for ' . $locale );
    }
    $header = unpack( 'Vmagic/Vrevision/Vcount/Voriginal/Vtranslated/Vhash_count/Vhash_offset', substr( $data, 0, 28 ) );
    if ( 0x950412de !== $header['magic'] || 0 !== $header['revision'] ) {
        throw new RuntimeException( 'Unexpected MO header in ' . $locale );
    }
    $count = $header['count'];
    $required_translations = array( 'Páginas', 'Crear CSV por bloques', 'Detener importación actual', 'Descargar CSV terminado', 'No se pudo crear el CSV temporal.', 'Importar ZIP de Media', 'El ZIP no contiene media.csv.', 'Media · imágenes', 'Qué se transporta' );
    $seen_translations = array();
    $expected_localizations = array(
        'es_ES' => 'El nombre del producto no puede quedar vacío.',
        'en_US' => 'The product name cannot be empty.',
        'fr_FR' => 'Le nom du produit ne peut pas être vide.',
        'de_DE' => 'Der Produktname darf nicht leer sein.',
        'nl_NL' => 'De productnaam mag niet leeg zijn.',
    );
    $localized_product_error_seen = false;
    if ( $count > ( strlen( $data ) - 28 ) / 16 ) {
        throw new RuntimeException( 'Invalid MO table size in ' . $locale );
    }
    for ( $i = 0; $i < $count; ++$i ) {
        $o = unpack( 'Vlength/Voffset', substr( $data, $header['original'] + $i * 8, 8 ) );
        $t = unpack( 'Vlength/Voffset', substr( $data, $header['translated'] + $i * 8, 8 ) );
        if ( $o['offset'] + $o['length'] > strlen( $data ) ||
            $t['offset'] + $t['length'] > strlen( $data ) ) {
            throw new RuntimeException( 'Malformed MO offset in ' . $locale );
        }
        $original = substr( $data, $o['offset'], $o['length'] );
        $translated = substr( $data, $t['offset'], $t['length'] );
        if ( 'El nombre del producto no puede quedar vacío.' === $original ) {
            if ( $translated !== $expected_localizations[ $locale ] ) {
                throw new RuntimeException( 'Incorrect locale-specific product error in ' . $locale );
            }
            $localized_product_error_seen = true;
        }
        if ( in_array( $original, $required_translations, true ) && '' !== $translated ) {
            $seen_translations[ $original ] = true;
        }
        if ( '' !== $original && '' === str_replace( chr( 0 ), '', $translated ) ) {
            throw new RuntimeException( 'Empty translation hides UI label in ' . $locale . ': ' . $original );
        }
    }
    if ( ! $localized_product_error_seen ) {
        throw new RuntimeException( 'Missing translated product error in ' . $locale );
    }
    foreach ( $required_translations as $required ) {
        if ( empty( $seen_translations[ $required ] ) ) {
            throw new RuntimeException( 'Missing expected translated UI control for ' . $locale . ': ' . $required );
        }
    }
    echo $locale . ': ' . $count . ' valid MO entries; user-facing controls translated; untranslated messages fallback to msgid.' . PHP_EOL;
}
$exporter = file_get_contents( $root . '/includes/class-taxocommerce-import-export-exporter.php' );
if ( false === strpos( $exporter, "'pages' => array(" ) ||
    false === strpos( $exporter, "'label'       => __( 'Páginas', 'taxocommerce-import-export' )" ) ||
    false === strpos( $exporter, 'name="content_type"' ) ||
    false === strpos( $exporter, "wp_ajax_taxocommerce_ie_export_start" ) ) {
    throw new RuntimeException( 'WordPress Pages export must remain selectable and connected to AJAX.' );
}
echo 'Import-Export MO + Pages selector regression: OK' . PHP_EOL;
