<?php
/**
 * Evolucion organica del Analista.
 *
 * Calcula movimiento real entre periodos con los datos locales de Search
 * Console. La metrica es propia y transparente: muestra
 * cuantas consultas avanzan, retroceden o entran en cada tramo de SERP.
 */

defined('ABSPATH') || exit;

if (!function_exists('seo_analista_movement_snapshot')) {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
    function seo_analista_movement_snapshot(array $current_rows, array $previous_rows) {
        $current = seo_analista_rows_map($current_rows, 'query_hash');
        $previous = seo_analista_rows_map($previous_rows, 'query_hash');

        $out = array(
            'improved' => 0,
            'stable' => 0,
            'declined' => 0,
            'new' => 0,
            'lost' => 0,
            'entered_top100' => 0,
            'entered_top50' => 0,
            'entered_top20' => 0,
            'entered_top10' => 0,
            'entered_top3' => 0,
            'left_top100' => 0,
            'net_top100' => 0,
            'net_top50' => 0,
            'net_top20' => 0,
            'net_top10' => 0,
            'net_top3' => 0,
        );

        foreach ($current as $hash => $row) {
            $position = (float) ($row['position'] ?? 0);
            if ($position <= 0) continue;

            if (!isset($previous[$hash])) {
                $out['new']++;
                if ($position <= 100) $out['entered_top100']++;
                if ($position <= 50) $out['entered_top50']++;
                if ($position <= 20) $out['entered_top20']++;
                if ($position <= 10) $out['entered_top10']++;
                if ($position <= 3) $out['entered_top3']++;
                continue;
            }

            $previous_position = (float) ($previous[$hash]['position'] ?? 0);
            if ($previous_position <= 0) {
                $out['new']++;
                continue;
            }

            $movement = $previous_position - $position; // positivo = mejora.
            if ($movement >= 1.0) {
                $out['improved']++;
            } elseif ($movement <= -1.0) {
                $out['declined']++;
            } else {
                $out['stable']++;
            }

            foreach (array(100, 50, 20, 10, 3) as $threshold) {
                if ($previous_position > $threshold && $position <= $threshold) {
                    $out['entered_top' . $threshold]++;
                }
            }
        }

        foreach ($previous as $hash => $row) {
            if (isset($current[$hash])) continue;
            $position = (float) ($row['position'] ?? 0);
            if ($position <= 0) continue;
            $out['lost']++;
            if ($position <= 100) $out['left_top100']++;
        }

        $current_distribution = seo_analista_distribution($current_rows);
        $previous_distribution = seo_analista_distribution($previous_rows);
        $threshold_total = static function(array $distribution, $threshold) {
            $value = (int) ($distribution['top3'] ?? 0);
            if ($threshold >= 10) $value += (int) ($distribution['top10'] ?? 0);
            if ($threshold >= 20) $value += (int) ($distribution['top20'] ?? 0);
            if ($threshold >= 50) $value += (int) ($distribution['top50'] ?? 0);
            if ($threshold >= 100) $value += (int) ($distribution['top100'] ?? 0);
            return $value;
        };

        foreach (array(100, 50, 20, 10, 3) as $threshold) {
            $out['net_top' . $threshold] = $threshold_total($current_distribution, $threshold)
                - $threshold_total($previous_distribution, $threshold);
        }

        return $out;
    }
}


/**
 * Compare the local daily GSC history with already downloaded Search snapshots.
 * Read-only: never refresh a remote provider or write SQL while rendering Analysis.
 */
if (!function_exists('taxocommerce_insights_gsc_trend_health')) {
    function taxocommerce_insights_gsc_trend_health($property_id, $days = 365) {
        $property_id = trim((string) $property_id);
        $days = max(28, min(365, absint($days)));
        $result = array(
            'sql_date' => '', 'snapshot_date' => '', 'finalized_date' => '',
            'snapshot_rows' => array(), 'matching_snapshots' => 0, 'other_snapshots' => 0,
        );
        if ('' === $property_id) return $result;

        if (function_exists('seo_google_latest_data_date')) {
            $result['sql_date'] = (string) seo_google_latest_data_date($property_id);
        }
        if (function_exists('seo_google_finalized_end_date')) {
            $result['finalized_date'] = (string) seo_google_finalized_end_date();
        } else {
            $result['finalized_date'] = gmdate('Y-m-d', time() - (3 * DAY_IN_SECONDS));
        }

        if (!class_exists('TaxoCommerce_Google_Bing_Reports', false)) return $result;
        $snapshots = TaxoCommerce_Google_Bing_Reports::saved_reports('search');
        if (!is_array($snapshots) || !$snapshots) return $result;

        // Older captures first: a later download replaces overlapping daily points.
        uasort($snapshots, static function ($a, $b) {
            return ((int) ($a['downloaded_at'] ?? 0)) <=> ((int) ($b['downloaded_at'] ?? 0));
        });
        $rows = array();
        foreach ($snapshots as $snapshot) {
            if (!is_array($snapshot) || !isset($snapshot['data']) || !is_array($snapshot['data'])) continue;
            $data = $snapshot['data'];
            $source_property = trim((string) ($data['source_property'] ?? ''));
            // Never merge URL-prefix and domain-property datasets based on a guessed equivalence.
            if ('' === $source_property || $source_property !== $property_id) {
                ++$result['other_snapshots'];
                continue;
            }
            ++$result['matching_snapshots'];
            $daily = (array) ($data['daily'] ?? array());
            foreach (array('previous', 'current') as $period) {
                $start = (string) ($snapshot[$period]['start'] ?? '');
                if (!preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $start)) continue;
                $clicks = (array) ($daily[$period . '_clicks'] ?? array());
                $impressions = (array) ($daily[$period . '_impressions'] ?? array());
                $positions = (array) ($daily[$period . '_position'] ?? array());
                $count = min(count($clicks), count($impressions), 90);
                try {
                    $first = new DateTimeImmutable($start);
                } catch (Exception $exception) {
                    continue;
                }
                for ($i = 0; $i < $count; ++$i) {
                    if (!isset($clicks[$i], $impressions[$i])) continue;
                    $date = $first->modify('+' . $i . ' days')->format('Y-m-d');
                    if ($date > $result['finalized_date']) continue;
                    $impression_count = max(0, (float) $impressions[$i]);
                    $rows[$date] = array(
                        'data_date' => $date,
                        'clicks' => max(0, (float) $clicks[$i]),
                        'impressions' => $impression_count,
                        'position' => $impression_count > 0 && isset($positions[$i])
                            ? (float) $positions[$i] : null,
                    );
                }
            }
        }
        if ($rows) {
            ksort($rows);
            $result['snapshot_date'] = (string) array_key_last($rows);
            $result['snapshot_rows'] = array_values($rows);
        }
        return $result;
    }
}

if (!function_exists('seo_analista_trend_series')) {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
    function seo_analista_trend_series($days = 180) {
        $days = max(28, min(365, absint($days)));
        $property_id = seo_analista_resolve_property_id();
        if ($property_id === '') return array();
        $health = taxocommerce_insights_gsc_trend_health($property_id, $days);
        $sql_rows = function_exists('seo_google_get_summary_trend_data')
            ? (array) seo_google_get_summary_trend_data($property_id, $days) : array();
        $merged = array();
        foreach ($sql_rows as $row) {
            $date = (string) ($row['data_date'] ?? '');
            if (preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $date)) $merged[$date] = $row;
        }
        // The persisted SQL total is authoritative where present. Saved API
        // snapshots fill missing days only; no provider call or DB mutation.
        foreach ($health['snapshot_rows'] as $row) {
            $date = (string) $row['data_date'];
            if (!isset($merged[$date])) $merged[$date] = $row;
        }
        if (!$merged) return array();
        ksort($merged);
        $last_date = (string) array_key_last($merged);
        $first_date = gmdate('Y-m-d', strtotime($last_date . ' -' . ($days - 1) . ' days'));
        return array_values(array_filter($merged, static function ($row) use ($first_date) {
            return (string) ($row['data_date'] ?? '') >= $first_date;
        }));
    }
}

if (!function_exists('seo_analista_evolution_snapshot')) {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
    function seo_analista_evolution_snapshot($days = 28) {
        $data = seo_analista_get_data($days);
        return array(
            'ready' => !empty($data['ready']),
            'period' => (array) ($data['period'] ?? array()),
            'visibility_index' => (float) ($data['visibility_index'] ?? 0),
            'previous_visibility_index' => (float) ($data['previous_visibility_index'] ?? 0),
            'distribution' => (array) ($data['distribution'] ?? array()),
            'previous_distribution' => (array) ($data['previous_distribution'] ?? array()),
            'movement' => seo_analista_movement_snapshot(
                (array) ($data['queries'] ?? array()),
                (array) ($data['previous_queries'] ?? array())
            ),
            'trend' => seo_analista_trend_series(max(120, seo_analista_days($days) * 3)),
            'trend_health' => taxocommerce_insights_gsc_trend_health(seo_analista_resolve_property_id(), max(120, seo_analista_days($days) * 3)),
        );
    }
}
