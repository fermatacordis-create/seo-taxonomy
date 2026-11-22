<?php
/**
 * Ensure compiled administrator gettext strings match each active locale.
 *
 * Run: php tests/admin-i18n-smoke.php
 *
 * @package TaxoCommerceInsights
 */
if ( 'cli' !== PHP_SAPI ) {
    exit;
}

$root = dirname( __DIR__ ) . '/languages/taxocommerce-google-bing-';
$expected = array(
    'es_ES' => array(
        'Google settings saved.' => 'Configuración de Google guardada.',
        'Client Secret' => 'Secreto de cliente',
        'Invalid report.' => 'Informe no válido.',
        'Clicks' => 'Clics',
        'Aplicar fechas (sin actualizar)' => 'Aplicar fechas (sin actualizar)',
    ),
    'en_US' => array(
        'Google settings saved.' => 'Google settings saved.',
        'Client Secret' => 'Client Secret',
        'Invalid report.' => 'Invalid report.',
        'Clicks' => 'Clicks',
        'Aplicar fechas (sin actualizar)' => 'Apply dates (without refreshing)',
    ),
    'fr_FR' => array(
        'Google settings saved.' => 'Paramètres Google enregistrés.',
        'Client Secret' => 'Secret client',
        'Invalid report.' => 'Rapport non valide.',
        'Clicks' => 'Clics',
        'Aplicar fechas (sin actualizar)' => 'Appliquer les dates (sans actualiser)',
    ),
    'de_DE' => array(
        'Google settings saved.' => 'Google-Einstellungen gespeichert.',
        'Client Secret' => 'Client-Geheimnis',
        'Invalid report.' => 'Ungültiger Bericht.',
        'Clicks' => 'Klicks',
        'Aplicar fechas (sin actualizar)' => 'Zeitraum anwenden (ohne Aktualisierung)',
    ),
    'nl_NL' => array(
        'Google settings saved.' => 'Google-instellingen opgeslagen.',
        'Client Secret' => 'Clientgeheim',
        'Invalid report.' => 'Ongeldig rapport.',
        'Clicks' => 'Klikken',
        'Aplicar fechas (sin actualizar)' => 'Datums toepassen (zonder bijwerken)',
    ),
);

/**
 * Decode a single GNU gettext MO catalog for the required strings.
 *
 * @param string $mo       Binary catalog.
 * @param array  $required Source strings to read.
 * @return array
 */
function tc_insights_admin_i18n_read( $mo, $required ) {
    if ( strlen( $mo ) < 28 ) {
        throw new RuntimeException( 'Truncated MO catalog.' );
    }
    $header = unpack( 'Vmagic/Vrevision/Vcount/Voriginal/Vtranslated', substr( $mo, 0, 20 ) );
    if ( 0x950412de !== $header['magic'] || 0 !== $header['revision'] ) {
        throw new RuntimeException( 'Invalid MO catalog header.' );
    }
    $found = array();
    for ( $i = 0; $i < $header['count']; ++$i ) {
        $o = unpack( 'Vlength/Voffset', substr( $mo, $header['original'] + $i * 8, 8 ) );
        $t = unpack( 'Vlength/Voffset', substr( $mo, $header['translated'] + $i * 8, 8 ) );
        if ( $o['offset'] + $o['length'] > strlen( $mo ) || $t['offset'] + $t['length'] > strlen( $mo ) ) {
            throw new RuntimeException( 'Malformed MO offset.' );
        }
        $id = substr( $mo, $o['offset'], $o['length'] );
        if ( isset( $required[ $id ] ) ) {
            $found[ $id ] = substr( $mo, $t['offset'], $t['length'] );
        }
    }
    return $found;
}

foreach ( $expected as $locale => $strings ) {
    $path = $root . $locale . '.mo';
    $binary = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- CLI regression only.
    if ( false === $binary ) {
        throw new RuntimeException( 'Missing MO catalog for ' . $locale );
    }
    $actual = tc_insights_admin_i18n_read( $binary, $strings );
    foreach ( $strings as $source => $translation ) {
        if ( ! isset( $actual[ $source ] ) || $actual[ $source ] !== $translation ) {
            throw new RuntimeException( 'Incorrect administrative translation for ' . $locale . ': ' . $source );
        }
    }
    echo $locale . ': Insights administration strings match compiled MO.' . PHP_EOL;
}
