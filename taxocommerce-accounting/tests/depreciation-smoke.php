<?php
/**
 * Regression: annual fixed-asset expense without a second cash movement or VAT adjustment.
 */
define( 'ABSPATH', __DIR__ );
require dirname( __DIR__ ) . '/includes/class-tca-depreciation.php';

function check_depreciation( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, 'FAIL: ' . $message . PHP_EOL );
        exit( 1 );
    }
}
function tca_valid_date( $date ) {
    $parts = explode( '-', $date );
    return 3 === count( $parts ) && checkdate( (int) $parts[1], (int) $parts[2], (int) $parts[0] );
}

$entry = (object) array(
    'kind' => 'expense',
    'expense_type' => 'fixed_asset',
    'status' => 'validated',
    'service_date' => '2026-11-20',
    'useful_life_years' => 5,
    'amount_base' => '925.90',
    'amount_tax' => '194.44',
    'amount_total' => '1120.34',
    'deductible_pct' => '100.00',
);
// Legacy expense and new asset must use the same depreciation calculation.
$asset = clone $entry;
$asset->kind = 'asset';
check_depreciation( TCA_Depreciation::annual( $asset, 2026 ) === TCA_Depreciation::annual( $entry, 2026 ), 'new and legacy assets amortize identically' );
$current = clone $entry;
$current->expense_type = 'current';
check_depreciation( null === TCA_Depreciation::annual( $current, 2026 ), 'current expense is never amortized' );
$draft = clone $asset;
$draft->status = 'draft';
check_depreciation( null === TCA_Depreciation::annual( $draft, 2026 ), 'draft asset not amortized' );
check_depreciation( TCA_Depreciation::valid( 'fixed_asset', '2026-11-20', '5', '2026-11-20' ), 'valid asset' );
check_depreciation( ! TCA_Depreciation::valid( 'fixed_asset', '2026-11-19', '5', '2026-11-20' ), 'reject in-service date before acquisition' );
check_depreciation( ! TCA_Depreciation::valid( 'fixed_asset', '2026-11-20', '0', '2026-11-20' ), 'reject zero lifespan' );
check_depreciation( ! TCA_Depreciation::valid( 'custom', '', '', '2026-11-20' ), 'reject unsupported classification' );
check_depreciation( TCA_Depreciation::valid( 'current', '', '', '2026-11-20' ), 'current expense needs no useful life' );

check_depreciation( abs( TCA_Depreciation::annual( $entry, 2025 )['expense'] ) < 0.001, 'before service no charge' );
$sum = 0;
for ( $year = 2026; $year <= 2031; ++$year ) {
    $r = TCA_Depreciation::annual( $entry, $year );
    check_depreciation( null !== $r && $r['remaining'] >= 0, 'annual record' );
    $sum += $r['expense'];
}
check_depreciation( abs( $sum - 925.90 ) < 0.001, 'five-year allocation clears cost to the cent' );
check_depreciation( TCA_Depreciation::annual( $entry, 2026 )['expense'] < 100, 'first fiscal year prorated' );
check_depreciation( abs( TCA_Depreciation::annual( $entry, 2031 )['remaining'] ) < 0.001, 'end-year residual zero' );
check_depreciation( (float) $entry->amount_tax === 194.44 && (float) $entry->amount_total === 1120.34, 'original VAT and cash amount untouched' );
$entry->status = 'draft';
check_depreciation( null === TCA_Depreciation::annual( $entry, 2027 ), 'draft not booked as validated depreciation' );
$entry->status = 'validated';
$entry->expense_type = 'current';
check_depreciation( null === TCA_Depreciation::annual( $entry, 2027 ), 'current expense never depreciated' );
$entry->expense_type = 'fixed_asset';
$entry->deductible_pct = '0';
check_depreciation( abs( TCA_Depreciation::cost( $entry ) - 1120.34 ) < 0.001, 'nonrecoverable VAT capitalized in asset cost' );
$entry->deductible_pct = '100';
$entry->service_date = '2024-02-29';
$entry->useful_life_years = 1;
$leap = TCA_Depreciation::annual( $entry, 2024 );
$last = TCA_Depreciation::annual( $entry, 2025 );
check_depreciation( abs( $leap['expense'] + $last['expense'] - 925.90 ) < 0.001, 'leap-day schedule reconciled' );
echo "Accounting depreciation regression: PASS\n";

