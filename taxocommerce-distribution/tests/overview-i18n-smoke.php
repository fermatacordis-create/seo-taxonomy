<?php
/**
 * Verify the compiled Spanish administration navigation label.
 *
 * Run: php tests/overview-i18n-smoke.php
 *
 * @package TaxoCommerceDistribution
 */
if ( 'cli' !== PHP_SAPI ) {
    exit;
}
$mo_file = dirname( __DIR__ ) . '/languages/taxocommerce-distribution-es_ES.mo';
$mo      = file_get_contents( $mo_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local CLI test.
if ( false === $mo || strlen( $mo ) < 28 ) {
    throw new RuntimeException( 'Missing or truncated Spanish gettext catalog.' );
}
$header = unpack( 'Vmagic/Vrevision/Vcount/Voriginal/Vtranslated', substr( $mo, 0, 20 ) );
if ( 0x950412de !== $header['magic'] || 0 !== $header['revision'] ) {
    throw new RuntimeException( 'Invalid Spanish gettext catalog.' );
}
$found = false;
for ( $i = 0; $i < $header['count']; ++$i ) {
    $key = unpack( 'Vlen/Voff', substr( $mo, $header['original'] + 8 * $i, 8 ) );
    $val = unpack( 'Vlen/Voff', substr( $mo, $header['translated'] + 8 * $i, 8 ) );
    if ( $key['len'] + $key['off'] > strlen( $mo ) || $val['len'] + $val['off'] > strlen( $mo ) ) {
        throw new RuntimeException( 'Malformed gettext MO offsets.' );
    }
    if ( 'Overview' === substr( $mo, $key['off'], $key['len'] ) ) {
        $found = true;
        if ( 'Resumen' !== substr( $mo, $val['off'], $val['len'] ) ) {
            throw new RuntimeException( 'Overview must display as Resumen in Spanish.' );
        }
    }
}
if ( ! $found ) {
    throw new RuntimeException( 'Missing Overview label in Spanish gettext catalog.' );
}
echo "Distribution es_ES Overview translation: PASS.\n";
