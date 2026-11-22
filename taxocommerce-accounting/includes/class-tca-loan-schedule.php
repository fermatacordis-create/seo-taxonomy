<?php
/**
 * Pure, deterministic instalment forecast for fixed-rate amortising loans.
 *
 * Monetary outputs are rounded to cents, with the last instalment adjusted
 * to clear residual principal. Forecasts never post cash or fiscal entries.
 *
 * @package TaxoCommerceAccounting
 */
defined( 'ABSPATH' ) || exit;

final class TCA_Loan_Schedule {
    /**
     * Forecast with nominal annual interest (TIN), monthly compounding.
     * Balloon is extra principal payable with the final regular instalment.
     *
     * @return array<int,array<string,float|int|string>> Empty on invalid terms.
     */
    public static function calculate( $principal, $annual_rate, $months, $first_due_date, $balloon = 0 ) {
        $principal = (float) $principal;
        $annual_rate = (float) $annual_rate;
        $balloon = (float) $balloon;
        $months = (int) $months;
        if ( ! is_finite( $principal ) || ! is_finite( $annual_rate ) || ! is_finite( $balloon ) ||
            $principal <= 0 || $annual_rate < 0 || $annual_rate > 100 ||
            $months < 1 || $months > 360 || $balloon < 0 || $balloon > $principal ||
            ! is_string( $first_due_date ) ||
            ! preg_match( '/^([0-9]{4})-([0-9]{2})-([0-9]{2})$/', $first_due_date, $matches ) ||
            ! checkdate( (int) $matches[2], (int) $matches[3], (int) $matches[1] ) ) {
            return array();
        }
        $rate = $annual_rate / 1200.0;
        $discount = 0.0 === $rate ? 1.0 : pow( 1.0 + $rate, -$months );
        $payment = 0.0 === $rate
            ? ( $principal - $balloon ) / $months
            : ( $principal - $balloon * $discount ) * $rate / ( 1.0 - $discount );
        $regular_cents = max( 0, (int) round( $payment * 100 ) );
        $remaining = (int) round( $principal * 100 );
        $origin_month = ( (int) $matches[1] ) * 12 + (int) $matches[2] - 1;
        $origin_day = (int) $matches[3];
        $rows = array();
        for ( $n = 1; $n <= $months; ++$n ) {
            $interest = (int) round( $remaining * $rate );
            if ( $n === $months ) {
                // Avoid a residual-cent balance, including a planned balloon.
                $principal_part = $remaining;
            } else {
                $principal_part = min( $remaining, max( 0, $regular_cents - $interest ) );
            }
            $due_index = $origin_month + $n - 1;
            $year = (int) floor( $due_index / 12 );
            $month = $due_index % 12 + 1;
            $first_of_month = sprintf( '%04d-%02d-01', $year, $month );
            $last_day = (int) gmdate( 't', strtotime( $first_of_month . ' 12:00:00 UTC' ) );
            $due = sprintf( '%04d-%02d-%02d', $year, $month, min( $origin_day, $last_day ) );
            $remaining -= $principal_part;
            $rows[] = array(
                'number' => $n,
                'due_date' => $due,
                'principal' => round( $principal_part / 100, 2 ),
                'interest' => round( $interest / 100, 2 ),
                'payment' => round( ( $principal_part + $interest ) / 100, 2 ),
                'remaining' => round( $remaining / 100, 2 ),
            );
        }
        return $rows;
    }
}
