<?php

declare(strict_types=1);

namespace ProdAudit\Audit\Insights;

final class StabilityModel
{
    /**
     * @param array<int, array<string, mixed>> $currentFindings
     * @param array<int, array<string, mixed>> $historyReports
     * @return array{stability_by_rule: array<string, float>, stability_by_fingerprint: array<string, float>, persistence_by_fingerprint: array<string, float>, instability_factor: float, overall_stability_score: float}
     */
    public function compute(array $currentFindings, array $historyReports, int $historyWindow = 20): array
    {
        $runs = array_slice($historyReports, -max(0, $historyWindow - 1));
        $runs[] = ['findings' => $currentFindings];

        $fingerprintSeries = $this->fingerprintSeries($runs);
        $windowCount = count($runs);
        $transitionCount = max(1, $windowCount - 1);

        $stabilityByFingerprint = [];
        $persistenceByFingerprint = [];
        foreach ($fingerprintSeries as $fingerprint => $series) {
            $presentCount = 0;
            $flips = 0;
            $last = null;
            foreach ($series as $present) {
                if ($present) {
                    ++$presentCount;
                }

                if ($last !== null && $last !== $present) {
                    ++$flips;
                }

                $last = $present;
            }

            $persistence = $windowCount > 0 ? $presentCount / $windowCount : 0.0;
            $flipRatio = $flips / $transitionCount;
            $stability = max(0.0, min(1.0, $persistence * (1.0 - $flipRatio)));

            $persistenceByFingerprint[$fingerprint] = round($persistence, 6);
            $stabilityByFingerprint[$fingerprint] = round($stability, 6);
        }
        ksort($persistenceByFingerprint, SORT_STRING);
        ksort($stabilityByFingerprint, SORT_STRING);

        $stabilityByRule = [];
        $ruleBuckets = [];
        foreach ($currentFindings as $finding) {
            if (!is_array($finding)) {
                continue;
            }

            $fingerprint = (string) ($finding['fingerprint'] ?? '');
            $ruleId = (string) ($finding['rule_id'] ?? '');
            if ($fingerprint === '' || $ruleId === '') {
                continue;
            }

            $ruleBuckets[$ruleId][] = $stabilityByFingerprint[$fingerprint] ?? 0.0;
        }

        foreach ($ruleBuckets as $ruleId => $scores) {
            $stabilityByRule[$ruleId] = $scores === [] ? 0.0 : round(array_sum($scores) / count($scores), 6);
        }
        ksort($stabilityByRule, SORT_STRING);

        $overallStability = $stabilityByFingerprint === []
            ? 0.0
            : round(array_sum($stabilityByFingerprint) / count($stabilityByFingerprint), 6);

        return [
            'stability_by_rule' => $stabilityByRule,
            'stability_by_fingerprint' => $stabilityByFingerprint,
            'persistence_by_fingerprint' => $persistenceByFingerprint,
            'instability_factor' => round(1.0 - $overallStability, 6),
            'overall_stability_score' => $overallStability,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $runs
     * @return array<string, array<int, bool>>
     */
    private function fingerprintSeries(array $runs): array
    {
        $allFingerprints = [];
        $runFingerprints = [];

        foreach ($runs as $index => $run) {
            $set = [];
            $findings = $run['findings'] ?? [];
            if (is_array($findings)) {
                foreach ($findings as $finding) {
                    if (!is_array($finding)) {
                        continue;
                    }

                    $fingerprint = (string) ($finding['fingerprint'] ?? '');
                    if ($fingerprint === '') {
                        continue;
                    }

                    $set[$fingerprint] = true;
                    $allFingerprints[$fingerprint] = true;
                }
            }

            $runFingerprints[$index] = $set;
        }

        $series = [];
        $fingerprints = array_keys($allFingerprints);
        sort($fingerprints, SORT_STRING);

        foreach ($fingerprints as $fingerprint) {
            $line = [];
            foreach ($runFingerprints as $set) {
                $line[] = isset($set[$fingerprint]);
            }
            $series[$fingerprint] = $line;
        }

        return $series;
    }
}
