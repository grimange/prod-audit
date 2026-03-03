<?php

declare(strict_types=1);

namespace ProdAudit\Audit\Insights;

final class InsightEngine
{
    private const HISTORY_WINDOW = 20;
    private const NOISE_DOWNGRADE_THRESHOLD = 0.67;

    private const SEVERITY_WEIGHTS = [
        'critical' => 4.0,
        'major' => 3.0,
        'minor' => 2.0,
        'info' => 1.0,
    ];

    public function __construct(
        private readonly NoiseModel $noiseModel = new NoiseModel(),
        private readonly StabilityModel $stabilityModel = new StabilityModel(),
    ) {
    }

    /**
     * @param array<string, mixed> $latestReport
     * @param array<int, array<string, mixed>> $historyReports
     * @param array<string, string> $effectiveLabels
     * @param array<string, float|int> $churnByFile
     */
    public function generate(
        array $latestReport,
        array $historyReports,
        array $effectiveLabels = [],
        array $churnByFile = []
    ): InsightReport {
        $findings = is_array($latestReport['findings'] ?? null) ? $latestReport['findings'] : [];

        $noise = $this->noiseModel->compute($findings, $effectiveLabels);
        $stability = $this->stabilityModel->compute($findings, $historyReports, self::HISTORY_WINDOW);

        $hotspotByFingerprint = $this->hotspotByFingerprint($findings, $churnByFile);
        $prioritized = $this->prioritizedFindings(
            $findings,
            $noise['noise_by_fingerprint'],
            $stability['persistence_by_fingerprint'],
            $hotspotByFingerprint
        );

        $topPersistent = $this->topPersistent(
            $findings,
            $stability['persistence_by_fingerprint'],
            $noise['noise_by_fingerprint']
        );

        $topNoisyRules = $this->topNoisyRules($noise['noise_by_rule']);
        $hotspots = $this->hotspots($findings, $churnByFile);
        $confidenceCalibration = $this->confidenceCalibration($findings, $noise['noise_by_rule']);
        $recentlyFixed = $this->recentlyFixed($effectiveLabels, $findings);

        return new InsightReport(
            noiseByRule: $noise['noise_by_rule'],
            stabilityByRule: $stability['stability_by_rule'],
            topPersistentFingerprints: $topPersistent,
            topNoisyRules: $topNoisyRules,
            hotspots: $hotspots,
            prioritizedFindings: $prioritized,
            confidenceCalibration: $confidenceCalibration,
            noiseScore: $noise['overall_noise_score'],
            stabilityScore: $stability['overall_stability_score'],
            recentlyFixedStreaks: $recentlyFixed,
        );
    }

    /**
     * @param array<int, array<string, mixed>> $findings
     * @param array<string, float> $noiseByFingerprint
     * @param array<string, float> $persistenceByFingerprint
     * @param array<string, float> $hotspotByFingerprint
     * @return array<int, array<string, mixed>>
     */
    private function prioritizedFindings(
        array $findings,
        array $noiseByFingerprint,
        array $persistenceByFingerprint,
        array $hotspotByFingerprint
    ): array {
        $ranked = [];

        foreach ($findings as $finding) {
            if (!is_array($finding)) {
                continue;
            }

            $fingerprint = (string) ($finding['fingerprint'] ?? '');
            if ($fingerprint === '') {
                continue;
            }

            $severity = (string) ($finding['severity'] ?? 'info');
            $severityWeight = self::SEVERITY_WEIGHTS[$severity] ?? 1.0;
            $persistence = (float) ($persistenceByFingerprint[$fingerprint] ?? 0.0);
            $noise = (float) ($noiseByFingerprint[$fingerprint] ?? 0.0);
            $hotspot = (float) ($hotspotByFingerprint[$fingerprint] ?? 0.0);

            $rank = $severityWeight
                * (0.5 + $persistence)
                * (1.0 - $noise)
                * (0.5 + $hotspot);

            $ranked[] = [
                'fingerprint' => $fingerprint,
                'rule_id' => (string) ($finding['rule_id'] ?? ''),
                'title' => (string) ($finding['title'] ?? ''),
                'severity' => $severity,
                'persistence' => round($persistence, 6),
                'noise' => round($noise, 6),
                'hotspot_factor' => round($hotspot, 6),
                'rank' => round($rank, 6),
            ];
        }

        usort($ranked, static function (array $a, array $b): int {
            $rankCmp = ((float) $b['rank']) <=> ((float) $a['rank']);
            if ($rankCmp !== 0) {
                return $rankCmp;
            }

            $severityCmp = ((self::SEVERITY_WEIGHTS[(string) ($b['severity'] ?? 'info')] ?? 1.0)
                <=> (self::SEVERITY_WEIGHTS[(string) ($a['severity'] ?? 'info')] ?? 1.0));
            if ($severityCmp !== 0) {
                return $severityCmp;
            }

            return strcmp((string) ($a['fingerprint'] ?? ''), (string) ($b['fingerprint'] ?? ''));
        });

        return $ranked;
    }

    /**
     * @param array<int, array<string, mixed>> $findings
     * @param array<string, float> $persistenceByFingerprint
     * @param array<string, float> $noiseByFingerprint
     * @return array<int, array<string, mixed>>
     */
    private function topPersistent(array $findings, array $persistenceByFingerprint, array $noiseByFingerprint): array
    {
        $rows = [];
        foreach ($findings as $finding) {
            if (!is_array($finding)) {
                continue;
            }

            $fingerprint = (string) ($finding['fingerprint'] ?? '');
            if ($fingerprint === '') {
                continue;
            }

            $rows[] = [
                'fingerprint' => $fingerprint,
                'rule_id' => (string) ($finding['rule_id'] ?? ''),
                'severity' => (string) ($finding['severity'] ?? ''),
                'persistence' => round((float) ($persistenceByFingerprint[$fingerprint] ?? 0.0), 6),
                'noise' => round((float) ($noiseByFingerprint[$fingerprint] ?? 0.0), 6),
            ];
        }

        usort($rows, static function (array $a, array $b): int {
            $cmp = ((float) $b['persistence']) <=> ((float) $a['persistence']);
            if ($cmp !== 0) {
                return $cmp;
            }

            return strcmp((string) ($a['fingerprint'] ?? ''), (string) ($b['fingerprint'] ?? ''));
        });

        return array_slice($rows, 0, 10);
    }

    /**
     * @param array<string, float> $noiseByRule
     * @return array<int, array<string, mixed>>
     */
    private function topNoisyRules(array $noiseByRule): array
    {
        $rows = [];
        foreach ($noiseByRule as $ruleId => $noise) {
            $rows[] = [
                'rule_id' => $ruleId,
                'noise' => round((float) $noise, 6),
            ];
        }

        usort($rows, static function (array $a, array $b): int {
            $cmp = ((float) $b['noise']) <=> ((float) $a['noise']);
            if ($cmp !== 0) {
                return $cmp;
            }

            return strcmp((string) ($a['rule_id'] ?? ''), (string) ($b['rule_id'] ?? ''));
        });

        return array_slice($rows, 0, 10);
    }

    /**
     * @param array<int, array<string, mixed>> $findings
     * @param array<string, float|int> $churnByFile
     * @return array<int, array<string, mixed>>
     */
    private function hotspots(array $findings, array $churnByFile): array
    {
        $rows = [];
        foreach ($findings as $finding) {
            if (!is_array($finding)) {
                continue;
            }

            $files = $this->filesFromFindingEvidence($finding);
            foreach ($files as $file) {
                if (!isset($rows[$file])) {
                    $rows[$file] = ['file' => $file, 'churn_score' => 0.0, 'findings' => 0];
                }

                $rows[$file]['findings']++;
                $rows[$file]['churn_score'] = (float) ($churnByFile[$file] ?? 0.0);
            }
        }

        $result = array_values($rows);
        usort($result, static function (array $a, array $b): int {
            $cmp = ((float) ($b['churn_score'] ?? 0.0)) <=> ((float) ($a['churn_score'] ?? 0.0));
            if ($cmp !== 0) {
                return $cmp;
            }

            $countCmp = ((int) ($b['findings'] ?? 0)) <=> ((int) ($a['findings'] ?? 0));
            if ($countCmp !== 0) {
                return $countCmp;
            }

            return strcmp((string) ($a['file'] ?? ''), (string) ($b['file'] ?? ''));
        });

        return array_slice($result, 0, 10);
    }

    /**
     * @param array<int, array<string, mixed>> $findings
     * @param array<string, float> $noiseByRule
     * @return array<string, string>
     */
    private function confidenceCalibration(array $findings, array $noiseByRule): array
    {
        $overlay = [];
        foreach ($findings as $finding) {
            if (!is_array($finding)) {
                continue;
            }

            $fingerprint = (string) ($finding['fingerprint'] ?? '');
            $ruleId = (string) ($finding['rule_id'] ?? '');
            $confidence = (string) ($finding['confidence'] ?? 'medium');
            if ($fingerprint === '' || $ruleId === '') {
                continue;
            }

            $noise = (float) ($noiseByRule[$ruleId] ?? 0.0);
            $overlay[$fingerprint] = $noise >= self::NOISE_DOWNGRADE_THRESHOLD
                ? $this->downgradeConfidence($confidence)
                : $confidence;
        }

        ksort($overlay, SORT_STRING);

        return $overlay;
    }

    private function downgradeConfidence(string $confidence): string
    {
        return match (strtolower($confidence)) {
            'high' => 'medium',
            'medium' => 'low',
            default => 'low',
        };
    }

    /**
     * @param array<int, array<string, mixed>> $findings
     * @param array<string, float|int> $churnByFile
     * @return array<string, float>
     */
    private function hotspotByFingerprint(array $findings, array $churnByFile): array
    {
        $maxChurn = 0.0;
        foreach ($churnByFile as $value) {
            $maxChurn = max($maxChurn, (float) $value);
        }

        $result = [];
        foreach ($findings as $finding) {
            if (!is_array($finding)) {
                continue;
            }

            $fingerprint = (string) ($finding['fingerprint'] ?? '');
            if ($fingerprint === '') {
                continue;
            }

            $files = $this->filesFromFindingEvidence($finding);
            if ($files === []) {
                $result[$fingerprint] = 0.0;
                continue;
            }

            $sum = 0.0;
            foreach ($files as $file) {
                $sum += (float) ($churnByFile[$file] ?? 0.0);
            }

            $avg = $sum / count($files);
            $result[$fingerprint] = $maxChurn > 0.0 ? round(min(1.0, $avg / $maxChurn), 6) : 0.0;
        }

        ksort($result, SORT_STRING);

        return $result;
    }

    /**
     * @param array<string, mixed> $finding
     * @return array<int, string>
     */
    private function filesFromFindingEvidence(array $finding): array
    {
        $evidence = $finding['evidence'] ?? [];
        if (!is_array($evidence)) {
            return [];
        }

        $files = [];
        foreach ($evidence as $item) {
            if (!is_array($item)) {
                continue;
            }

            $file = $item['file'] ?? null;
            if (is_string($file) && $file !== '') {
                $files[$file] = true;
            }
        }

        $result = array_keys($files);
        sort($result, SORT_STRING);

        return $result;
    }

    /**
     * @param array<string, string> $effectiveLabels
     * @param array<int, array<string, mixed>> $findings
     * @return array<int, array<string, mixed>>
     */
    private function recentlyFixed(array $effectiveLabels, array $findings): array
    {
        $active = [];
        $ruleByFingerprint = [];
        foreach ($findings as $finding) {
            if (!is_array($finding)) {
                continue;
            }

            $fingerprint = (string) ($finding['fingerprint'] ?? '');
            if ($fingerprint === '') {
                continue;
            }

            $active[$fingerprint] = true;
            $ruleByFingerprint[$fingerprint] = (string) ($finding['rule_id'] ?? '');
        }

        $rows = [];
        foreach ($effectiveLabels as $fingerprint => $label) {
            if ($label !== 'fixed') {
                continue;
            }

            if (isset($active[$fingerprint])) {
                continue;
            }

            $rows[] = [
                'fingerprint' => $fingerprint,
                'rule_id' => $ruleByFingerprint[$fingerprint] ?? '',
                'label' => 'fixed',
            ];
        }

        usort($rows, static fn (array $a, array $b): int => strcmp((string) ($a['fingerprint'] ?? ''), (string) ($b['fingerprint'] ?? '')));

        return $rows;
    }
}
