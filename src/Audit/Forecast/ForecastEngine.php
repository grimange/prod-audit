<?php

declare(strict_types=1);

namespace ProdAudit\Audit\Forecast;

final class ForecastEngine
{
    private const INVARIANT_PRESSURE_DIVISOR = 10.0;
    private const CRITICAL_PRESSURE_DIVISOR = 10.0;

    /**
     * @param array<string, mixed> $latestReport
     * @param array<int, array<string, mixed>> $historyReports
     * @param array<string, mixed> $insights
     * @param array<string, string> $effectiveLabels
     * @param array<int, array<string, mixed>> $nextChecks
     */
    public function generate(
        array $latestReport,
        array $historyReports,
        array $insights,
        array $effectiveLabels,
        array $nextChecks = []
    ): ForecastReport {
        $findings = is_array($latestReport['findings'] ?? null) ? $latestReport['findings'] : [];
        $historyWindow = max(1, min(20, count($historyReports)));

        $regressionRate = $this->regressionRate($historyReports);
        $hotspotFactor = $this->hotspotFactor($insights);
        $invariantPressure = $this->invariantPressure($findings);
        $instabilityFactor = $this->instabilityFactor($insights);
        $criticalPressure = $this->criticalPressure($findings);
        $fixedRevertFactor = $this->fixedRevertFactor($effectiveLabels, $findings);

        $riskScoreDrop5 = $this->clamp01(
            0.35 * $regressionRate
            + 0.25 * $hotspotFactor
            + 0.25 * $invariantPressure
            + 0.15 * $instabilityFactor
        );

        $riskNewInvariantFail = $this->clamp01(
            0.40 * $invariantPressure
            + 0.30 * $regressionRate
            + 0.20 * $instabilityFactor
            + 0.10 * $fixedRevertFactor
        );

        $riskNewCritical = $this->clamp01(
            0.45 * $regressionRate
            + 0.25 * $instabilityFactor
            + 0.20 * $hotspotFactor
            + 0.10 * $criticalPressure
        );

        $riskByPack = $this->riskByPack($latestReport, $instabilityFactor, $regressionRate);
        $drivers = $this->drivers($latestReport, $insights, $effectiveLabels, $historyWindow);

        return new ForecastReport(
            riskNewInvariantFail: round($riskNewInvariantFail, 6),
            riskScoreDrop5: round($riskScoreDrop5, 6),
            riskNewCritical: round($riskNewCritical, 6),
            riskRulePackRegression: $riskByPack,
            topDrivers: $drivers,
            nextChecks: array_slice($nextChecks, 0, 10),
        );
    }

    /**
     * @param array<int, array<string, mixed>> $historyReports
     */
    private function regressionRate(array $historyReports): float
    {
        if ($historyReports === []) {
            return 0.0;
        }

        $regressions = 0;
        foreach ($historyReports as $report) {
            $isRegression = (bool) ($report['regression'] ?? false);
            if (!$isRegression) {
                $trend = $report['trend'] ?? [];
                if (is_array($trend) && ($trend['regression'] ?? false) === true) {
                    $isRegression = true;
                }
            }

            if ($isRegression) {
                ++$regressions;
            }
        }

        return $regressions / count($historyReports);
    }

    /**
     * @param array<string, mixed> $insights
     */
    private function hotspotFactor(array $insights): float
    {
        $hotspots = $insights['hotspots'] ?? [];
        if (!is_array($hotspots) || $hotspots === []) {
            return 0.0;
        }

        $sum = 0.0;
        $count = 0;
        foreach (array_slice($hotspots, 0, 5) as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $sum += (float) ($entry['churn_score'] ?? 0.0);
            ++$count;
        }

        if ($count === 0) {
            return 0.0;
        }

        $max = 0.0;
        foreach ($hotspots as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $max = max($max, (float) ($entry['churn_score'] ?? 0.0));
        }

        if ($max <= 0.0) {
            return 0.0;
        }

        return min(1.0, ($sum / $count) / $max);
    }

    /**
     * @param array<int, array<string, mixed>> $findings
     */
    private function invariantPressure(array $findings): float
    {
        $count = 0;
        foreach ($findings as $finding) {
            if (!is_array($finding)) {
                continue;
            }

            if (($finding['invariant_failure'] ?? false) === true) {
                ++$count;
            }
        }

        return $this->clamp01($count / self::INVARIANT_PRESSURE_DIVISOR);
    }

    /**
     * @param array<string, mixed> $insights
     */
    private function instabilityFactor(array $insights): float
    {
        return $this->clamp01(1.0 - (float) ($insights['stability_score'] ?? 0.0));
    }

    /**
     * @param array<int, array<string, mixed>> $findings
     */
    private function criticalPressure(array $findings): float
    {
        $count = 0;
        foreach ($findings as $finding) {
            if (!is_array($finding)) {
                continue;
            }

            if (($finding['severity'] ?? null) === 'critical') {
                ++$count;
            }
        }

        return $this->clamp01($count / self::CRITICAL_PRESSURE_DIVISOR);
    }

    /**
     * @param array<string, string> $effectiveLabels
     * @param array<int, array<string, mixed>> $findings
     */
    private function fixedRevertFactor(array $effectiveLabels, array $findings): float
    {
        if ($effectiveLabels === []) {
            return 0.0;
        }

        $active = [];
        foreach ($findings as $finding) {
            if (!is_array($finding)) {
                continue;
            }

            $fingerprint = (string) ($finding['fingerprint'] ?? '');
            if ($fingerprint !== '') {
                $active[$fingerprint] = true;
            }
        }

        $fixedCount = 0;
        $revertedCount = 0;
        foreach ($effectiveLabels as $fingerprint => $label) {
            if ($label !== 'fixed') {
                continue;
            }

            ++$fixedCount;
            if (isset($active[$fingerprint])) {
                ++$revertedCount;
            }
        }

        if ($fixedCount === 0) {
            return 0.0;
        }

        return $revertedCount / $fixedCount;
    }

    /**
     * @param array<string, mixed> $latestReport
     * @return array<string, float>
     */
    private function riskByPack(array $latestReport, float $instabilityFactor, float $regressionRate): array
    {
        $summary = $latestReport['rule_pack_summary'] ?? [];
        if (!is_array($summary)) {
            return [];
        }

        $totalFindings = max(1, count((array) ($latestReport['findings'] ?? [])));

        $rows = [];
        foreach ($summary as $pack => $data) {
            if (!is_array($data)) {
                continue;
            }

            $packFindings = (int) ($data['findings'] ?? 0);
            $packWeight = $packFindings / $totalFindings;
            $rows[(string) $pack] = round($this->clamp01(
                0.4 * $regressionRate + 0.3 * $packWeight + 0.3 * $instabilityFactor
            ), 6);
        }

        ksort($rows, SORT_STRING);

        return $rows;
    }

    /**
     * @param array<string, mixed> $latestReport
     * @param array<string, mixed> $insights
     * @param array<string, string> $effectiveLabels
     * @return array<int, array<string, mixed>>
     */
    private function drivers(array $latestReport, array $insights, array $effectiveLabels, int $historyWindow): array
    {
        $drivers = [];
        $prioritized = $insights['prioritized_findings'] ?? [];
        if (is_array($prioritized)) {
            foreach (array_slice($prioritized, 0, 5) as $entry) {
                if (!is_array($entry)) {
                    continue;
                }

                $drivers[] = [
                    'type' => 'finding',
                    'fingerprint' => (string) ($entry['fingerprint'] ?? ''),
                    'rule_id' => (string) ($entry['rule_id'] ?? ''),
                    'reason' => 'persistent_high_rank',
                    'persistence' => (float) ($entry['persistence'] ?? 0.0),
                    'noise' => (float) ($entry['noise'] ?? 0.0),
                    'rank' => (float) ($entry['rank'] ?? 0.0),
                ];
            }
        }

        $hotspots = $insights['hotspots'] ?? [];
        if (is_array($hotspots)) {
            foreach (array_slice($hotspots, 0, 3) as $entry) {
                if (!is_array($entry)) {
                    continue;
                }

                $drivers[] = [
                    'type' => 'file',
                    'file' => (string) ($entry['file'] ?? ''),
                    'churn_score' => (float) ($entry['churn_score'] ?? 0.0),
                    'reason' => 'hotspot_churn',
                ];
            }
        }

        $activeFingerprints = [];
        foreach ((array) ($latestReport['findings'] ?? []) as $finding) {
            if (!is_array($finding)) {
                continue;
            }

            $fingerprint = (string) ($finding['fingerprint'] ?? '');
            if ($fingerprint !== '') {
                $activeFingerprints[$fingerprint] = true;
            }
        }

        foreach ($effectiveLabels as $fingerprint => $label) {
            if ($label === 'fixed' && isset($activeFingerprints[$fingerprint])) {
                $drivers[] = [
                    'type' => 'finding',
                    'fingerprint' => $fingerprint,
                    'reason' => 'fixed_label_reverted',
                    'history_window' => $historyWindow,
                ];
            }
        }

        usort($drivers, static function (array $a, array $b): int {
            $aType = (string) ($a['type'] ?? '');
            $bType = (string) ($b['type'] ?? '');
            if ($aType !== $bType) {
                return strcmp($aType, $bType);
            }

            $aRank = (float) ($a['rank'] ?? 0.0);
            $bRank = (float) ($b['rank'] ?? 0.0);
            $rankCmp = $bRank <=> $aRank;
            if ($rankCmp !== 0) {
                return $rankCmp;
            }

            $aKey = (string) ($a['fingerprint'] ?? $a['file'] ?? '');
            $bKey = (string) ($b['fingerprint'] ?? $b['file'] ?? '');

            return strcmp($aKey, $bKey);
        });

        return array_slice($drivers, 0, 10);
    }

    private function clamp01(float $value): float
    {
        if ($value < 0.0) {
            return 0.0;
        }

        if ($value > 1.0) {
            return 1.0;
        }

        return $value;
    }
}
