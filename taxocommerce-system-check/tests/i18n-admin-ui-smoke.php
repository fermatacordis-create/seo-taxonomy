<?php
/**
 * Verify administrator interface translations without bootstrapping WordPress.
 *
 * Run: php tests/i18n-admin-ui-smoke.php
 *
 * @package TaxoCommerceSystemCheck
 */
$root = dirname( __DIR__ );
$domain = 'taxocommerce-system-check';
$cases = array(
    'es_ES' => array(
        'Running' => 'En ejecución',
        'Completed' => 'Completado',
        'Plugin Validation · módulos activos' => 'Validación de plugins · módulos activos',
        'System' => 'Sistema',
    ),
    'en_US' => array(
        'Código' => 'Code',
        'Funcionalidades' => 'Functions',
        'Running' => 'Running',
        'System' => 'System',
    ),
    'fr_FR' => array( 'Running' => 'En cours' ),
    'de_DE' => array( 'Running' => 'Läuft' ),
    'nl_NL' => array( 'Running' => 'Wordt uitgevoerd' ),
);
function tcs_i18n_smoke_get( $binary, $key ) {
    $header = unpack( 'Vmagic/Vrevision/Vcount/Voriginal/Vtranslated', substr( $binary, 0, 20 ) );
    if ( ! is_array( $header ) || 0x950412de !== $header['magic'] || 555 !== $header['count'] ) {
        throw new RuntimeException( 'Unexpected gettext MO header or entry count.' );
    }
    for ( $i = 0; $i < $header['count']; ++$i ) {
        $original_entry = unpack( 'Vlength/Voffset', substr( $binary, $header['original'] + $i * 8, 8 ) );
        $original = substr( $binary, $original_entry['offset'], $original_entry['length'] );
        if ( $original !== $key ) {
            continue;
        }
        $translated_entry = unpack( 'Vlength/Voffset', substr( $binary, $header['translated'] + $i * 8, 8 ) );
        return substr( $binary, $translated_entry['offset'], $translated_entry['length'] );
    }
    throw new RuntimeException( 'Missing gettext entry: ' . $key );
}
foreach ( $cases as $locale => $pairs ) {
    $prefix = $root . '/languages/' . $domain . '-' . $locale;
    $po = file_get_contents( $prefix . '.po' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local CLI test fixture.
    $mo = file_get_contents( $prefix . '.mo' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local CLI test fixture.
    if ( false === $po || false === $mo ) {
        throw new RuntimeException( 'Missing language files: ' . $locale );
    }
    foreach ( $pairs as $source => $expected ) {
        if ( false === strpos( $po, 'msgid ' . json_encode( $source, JSON_UNESCAPED_UNICODE ) ) ) {
            throw new RuntimeException( 'Missing PO key: ' . $source );
        }
        if ( tcs_i18n_smoke_get( $mo, $source ) !== $expected ) {
            throw new RuntimeException( 'Incorrect MO translation for ' . $source . ' in ' . $locale );
        }
    }
}
$main = file_get_contents( $root . '/taxocommerce-system-check.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local CLI test fixture.
$module = file_get_contents( $root . '/includes/class-taxocommerce-system-check-module-audit.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local CLI test fixture.
$family = file_get_contents( $root . '/assets/family-runner.js' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local CLI test fixture.
if ( false === strpos( $main, "__( 'Plugin Validation', 'taxocommerce-system-check' )" )
    || false === strpos( $module, "__( 'Very low', 'taxocommerce-system-check' )" )
    || false === strpos( $family, "statusLabel(s.status || 'pending')" )
    || false !== strpos( $family, "'Estado: '" ) ) {
    throw new RuntimeException( 'The visible interface is not fully wired to gettext.' );
}
echo "System Check administration i18n smoke: 5 locales OK.\n";
