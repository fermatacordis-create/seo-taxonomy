<?php
/**
 * Validate the five standalone gettext catalogs for Dependiente.
 *
 * @package TaxoCommerceDependiente
 */

if ( 'cli' !== PHP_SAPI ) {
	exit;
}

$root = dirname( __DIR__ );

/**
 * Fail with a useful error rather than producing a false green CI result.
 *
 * @param string $message Diagnostic message.
 * @return void
 */
function tcd_i18n_fail( $message ) {
	fwrite( STDERR, 'FAIL Dependiente i18n: ' . $message . PHP_EOL );
	exit( 1 );
}

/**
 * Decode a PO file without depending on a system gettext binary.
 *
 * @param string $path PO or POT file path.
 * @return array<string,string>
 */
function tcd_i18n_parse_po( $path ) {
	$source = file_get_contents( $path );
	if ( false === $source ) {
		tcd_i18n_fail( 'Unable to read ' . $path );
	}
	$catalog = array();
	foreach ( preg_split( '/\R[ \t]*\R/u', $source ) as $block ) {
		$key    = null;
		$fields = array();
		foreach ( preg_split( '/\R/u', $block ) as $line ) {
			if ( preg_match( '/^(msgid|msgstr) (".*")$/u', $line, $match ) ) {
				$key           = $match[1];
				$fields[$key]  = json_decode( $match[2], true );
				if ( ! is_string( $fields[$key] ) ) {
					tcd_i18n_fail( 'Invalid PO field in ' . $path );
				}
			} elseif ( null !== $key && preg_match( '/^(".*")$/u', $line, $match ) ) {
				$part = json_decode( $match[1], true );
				if ( ! is_string( $part ) ) {
					tcd_i18n_fail( 'Invalid PO continuation in ' . $path );
				}
				$fields[$key] .= $part;
			}
		}
		if ( ! isset( $fields['msgid'], $fields['msgstr'] ) ) {
			continue;
		}
		if ( array_key_exists( $fields['msgid'], $catalog ) ) {
			tcd_i18n_fail( 'Duplicate msgid in ' . $path );
		}
		$catalog[ $fields['msgid'] ] = $fields['msgstr'];
	}
	return $catalog;
}

/**
 * Decode a GNU gettext MO file and validate boundaries.
 *
 * @param string $path MO path.
 * @return array<string,string>
 */
function tcd_i18n_parse_mo( $path ) {
	$data = file_get_contents( $path );
	if ( false === $data || strlen( $data ) < 28 ) {
		tcd_i18n_fail( 'Missing or invalid MO: ' . $path );
	}
	$header = unpack( 'Vmagic/Vrevision/Vcount/VoriginalTable/VtranslatedTable/VhashSize/VhashOffset', substr( $data, 0, 28 ) );
	if ( ! is_array( $header ) || 0x950412de !== $header['magic'] ) {
		tcd_i18n_fail( 'Incorrect MO header: ' . $path );
	}
	$catalog = array();
	for ( $index = 0; $index < $header['count']; $index++ ) {
		$originals   = substr( $data, $header['originalTable'] + 8 * $index, 8 );
		$translated  = substr( $data, $header['translatedTable'] + 8 * $index, 8 );
		if ( 8 !== strlen( $originals ) || 8 !== strlen( $translated ) ) {
			tcd_i18n_fail( 'Truncated MO index: ' . $path );
		}
		$original = unpack( 'Vlength/Voffset', $originals );
		$target   = unpack( 'Vlength/Voffset', $translated );
		if ( $original['offset'] + $original['length'] >= strlen( $data ) || $target['offset'] + $target['length'] >= strlen( $data ) ) {
			tcd_i18n_fail( 'MO data out of bounds: ' . $path );
		}
		$id    = substr( $data, $original['offset'], $original['length'] );
		$value = substr( $data, $target['offset'], $target['length'] );
		if ( isset( $catalog[$id] ) ) {
			tcd_i18n_fail( 'Duplicate MO key: ' . $id );
		}
		$catalog[$id] = $value;
	}
	return $catalog;
}

$pot = tcd_i18n_parse_po( $root . '/languages/taxocommerce-dependiente.pot' );
if ( 369 !== count( $pot ) ) {
	tcd_i18n_fail( 'Unexpected POT size: ' . count( $pot ) );
}
$examples = array(
	'es_ES' => 'Guardar prioridad',
	'en_US' => 'Save priority',
	'fr_FR' => 'Enregistrer la priorité',
	'de_DE' => 'Priorität speichern',
	'nl_NL' => 'Prioriteit opslaan',
);
$required_user_guide = array(
    'es_ES' => '7. Utilizar el buscador en la tienda',
    'en_US' => '7. Using the search on the store',
    'fr_FR' => '7. Utiliser la recherche dans la boutique',
    'de_DE' => '7. Suche im Shop verwenden',
    'nl_NL' => '7. De zoekfunctie in de winkel gebruiken',
);
$required_modern_knowledge = array(
    'es_ES' => 'Conocimiento moderno',
    'en_US' => 'Modern knowledge',
    'fr_FR' => 'Connaissances modernes',
    'de_DE' => 'Modernes Wissen',
    'nl_NL' => 'Moderne kennis',
);
foreach ( $examples as $locale => $expected_label ) {
	$base = $root . '/languages/taxocommerce-dependiente-' . $locale;
	$po   = tcd_i18n_parse_po( $base . '.po' );
	$mo   = tcd_i18n_parse_mo( $base . '.mo' );
	$pot_ids = array_keys( $pot );
	$po_ids  = array_keys( $po );
	sort( $pot_ids, SORT_STRING );
	sort( $po_ids, SORT_STRING );
	if ( $pot_ids !== $po_ids ) {
		tcd_i18n_fail( 'POT/PO keys differ: ' . $locale );
	}
	$translated = array();
	foreach ( $po as $id => $value ) {
		if ( '' === $id || '' !== $value ) {
			$translated[$id] = $value;
		}
	}
	if ( $translated !== $mo ) {
		ksort( $translated, SORT_STRING );
		ksort( $mo, SORT_STRING );
		if ( $translated !== $mo ) {
			tcd_i18n_fail( 'PO/MO mismatch: ' . $locale );
		}
	}
	if ( ! isset( $mo['Guardar prioridad'] ) || $expected_label !== $mo['Guardar prioridad'] || count( $translated ) < 220 ) {
		tcd_i18n_fail( 'Admin label missing or insufficient coverage: ' . $locale );
	}
	if ( ( $mo['7. Utilizar el buscador en la tienda'] ?? '' ) !== $required_user_guide[ $locale ] ) {
        tcd_i18n_fail( 'Search guide translation missing: ' . $locale );
    }
	if ( ( $mo['Conocimiento moderno'] ?? '' ) !== $required_modern_knowledge[ $locale ] ) {
        tcd_i18n_fail( 'Portable knowledge translation missing: ' . $locale );
    }
	echo 'PASS Dependiente gettext ' . $locale . ': ' . ( count( $translated ) - 1 ) . ' translated entries.' . PHP_EOL;
}
