<?php

declare(strict_types=1);

namespace ProdAudit\Audit\Quality;

use ProdAudit\Audit\Rules\RuleMetadata;

final class QualityEngine
{
    private const DEFAULT_HISTORY_WINDOW = 20;

    /**
     * @param array<string, RuleMetadata> $ruleMetadataById
     */
    public function generate(
        string $historyPath,
        string $triagePath,
        ?string $latestPath,
        array $ruleMetadataById,
        int $historyWindow = self::DEFAULT_HISTORY_WINDOW,
    ): RuleQualityReport {
        $history = $this->readHistory($historyPath, $historyWindow);
        $latest = $latestPath !== null ? $this->readJson($latestPath) : [];
        $effectiveTriage = $this->effectiveTriage($triagePath);

        $ruleIds = array_keys($ruleMetadataById);
        foreach ($effectiveTriage as $event) {
            $ruleId = (string) ($event['rule_id'] ?? '');
            if ($ruleId !== '' && !in_array($ruleId, $ruleIds, true)) {
                $ruleIds[] = $ruleId;
            }
        }
        sort($ruleIds, SORT_STRING);

        $findingsByRuleLatest = $this->countFindingsByRule($latest);
        $fingerprintRunsByRule = $this->fingerprintRunsByRule($history);
        $persistenceByRule = $this->persistenceByRule($fingerprintRunsByRule, count($history));
        $churnByRule = $this->churnByRule($latest, $history);
        $labelStats = $this->labelStatsByRule($effectiveTriage);

        $records = [];
        foreach ($ruleIds as $ruleId) {
            $meta = $ruleMetadataById[$ruleId] ?? null;
            $labeled = (int) ($labelStats[$ruleId]['labeled'] ?? 0);
            $tp = (int) ($labelStats[$ruleId]['true_positive'] ?? 0);
            $fp = (int) ($labelStats[$ruleId]['false_positive'] ?? 0);
            $noisy = (int) ($labelStats[$ruleId]['noisy'] ?? 0);

            $tpRate = $labeled > 0 ? $tp / $labeled : 0.0;
            $fpRate = $labeled > 0 ? $fp / $labeled : 0.0;
            $noisyRate = $labeled > 0 ? $noisy / $labeled : 0.0;

            $noiseScore = $this->clamp01((0.6 * $fpRate) + (0.4 * $noisyRate));
            $precisionScore = $this->clamp01($tpRate * (1.0 - $noiseScore));

            $records[] = new RuleQualityRecord(
                ruleId: $ruleId,
                category: $meta?->category ?? 'unknown',
                pack: $meta?->pack ?? 'unknown',
                invariant: $meta?->invariant ?? false,
                findingsCount: (int) ($findingsByRuleLatest[$ruleId] ?? 0),
                labeledCount: $labeled,
                truePositiveRate: round($tpRate, 6),
                falsePositiveRate: round($fpRate, 6),
                noisyRate: round($noisyRate, 6),
                persistenceRate: round((float) ($persistenceByRule[$ruleId] ?? 0.0), 6),
                churnCorrelation: round((float) ($churnByRule[$ruleId] ?? 0.0), 6),
                noiseScore: round($noiseScore, 6),
                precisionScore: round($precisionScore, 6),
            );
        }

        usort($records, static function (RuleQualityRecord $a, RuleQualityRecord $b): int {
            $noiseCmp = $b->noiseScore <=> $a->noiseScore;
            if ($noiseCmp !== 0) {
                return $noiseCmp;
            }

            return strcmp($a->ruleId, $b->ruleId);
        });

        $topNoisy = array_map(static fn (RuleQualityRecord $record): array => [
            'rule_id' => $record->ruleId,
            'noise_score' => $record->noiseScore,
            'findings_count' => $record->findingsCount,
        ], array_slice($records, 0, 10));

        $valuable = $records;
        usort($valuable, static function (RuleQualityRecord $a, RuleQualityRecord $b): int {
            $valueA = $a->persistenceRate * (1.0 - $a->noiseScore);
            $valueB = $b->persistenceRate * (1.0 - $b->noiseScore);
            $valueCmp = $valueB <=> $valueA;
            if ($valueCmp !== 0) {
                return $valueCmp;
            }

            return strcmp($a->ruleId, $b->ruleId);
        });

        $topValuable = array_map(static fn (RuleQualityRecord $record): array => [
            'rule_id' => $record->ruleId,
            'value_score' => round($record->persistenceRate * (1.0 - $record->noiseScore), 6),
            'persistence_rate' => $record->persistenceRate,
            'noise_score' => $record->noiseScore,
        ], array_slice($valuable, 0, 10));

        $overallNoiseScore = $this->overallNoiseScore($records);

        return new RuleQualityReport($records, $topNoisy, $topValuable, $overallNoiseScore);
    }

    /**
     * @param array<int, RuleQualityRecord> $records
     */
    public function overallNoiseScore(array $records): float
    {
        $weight = 0;
        $sum = 0.0;
        foreach ($records as $record) {
            $weight += $record->findingsCount;
            $sum += ($record->noiseScore * $record->findingsCount);
        }

        if ($weight === 0) {
            return 0.0;
        }

        return round($sum / $weight, 6);
    }

    /**
     * @param array<string, mixed> $latest
     * @return array<string, int>
     */
    private function countFindingsByRule(array $latest): array
    {
        $findings = is_array($latest['findings'] ?? null) ? $latest['findings'] : [];
        $counts = [];
        foreach ($findings as $finding) {
            if (!is_array($finding)) {
                continue;
            }

            $ruleId = (string) ($finding['rule_id'] ?? '');
            if ($ruleId === '') {
                continue;
            }

            $counts[$ruleId] = ($counts[$ruleId] ?? 0) + 1;
        }

        ksort($counts, SORT_STRING);

        return $counts;
    }

    /**
     * @param array<int, array<string, mixed>> $history
     * @return array<string, array<string, int>>
     */
    private function fingerprintRunsByRule(array $history): array
    {
        $result = [];
        foreach ($history as $runIndex => $report) {
            $findings = is_array($report['findings'] ?? null) ? $report['findings'] : [];
            foreach ($findings as $finding) {
                if (!is_array($finding)) {
                    continue;
                }

                $ruleId = (string) ($finding['rule_id'] ?? '');
                $fingerprint = (string) ($finding['fingerprint'] ?? '');
                if ($ruleId === '' || $fingerprint === '') {
                    continue;
                }

                $result[$ruleId][$fingerprint] = ($result[$ruleId][$fingerprint] ?? 0);
                $result[$ruleId][$fingerprint] |= (1 << $runIndex);
            }
        }

        ksort($result, SORT_STRING);

        return $result;
    }

    /**
     * @param array<string, array<string, int>> $fingerprintRunsByRule
     * @return array<string, float>
     */
    private function persistenceByRule(array $fingerprintRunsByRule, int $runCount): array
    {
        if ($runCount <= 0) {
            return [];
        }

        $result = [];
        foreach ($fingerprintRunsByRule as $ruleId => $fingerprints) {
            if ($fingerprints === []) {
                $result[$ruleId] = 0.0;
                continue;
            }

            $sum = 0.0;
            foreach ($fingerprints as $bitset) {
                $sum += $this->bitCount($bitset) / $runCount;
            }

            $result[$ruleId] = $sum / count($fingerprints);
        }

        ksort($result, SORT_STRING);

        return $result;
    }

    private function bitCount(int $value): int
    {
        $count = 0;
        while ($value !== 0) {
            $count += ($value & 1);
            $value >>= 1;
        }

        return $count;
    }

    /**
     * @param array<string, mixed> $latest
     * @param array<int, array<string, mixed>> $history
     * @return array<string, float>
     */
    private function churnByRule(array $latest, array $history): array
    {
        $hotspots = is_array($latest['insights']['hotspots'] ?? null) ? $latest['insights']['hotspots'] : [];
        if ($hotspots === []) {
            return [];
        }

        $churnByFile = [];
        foreach ($hotspots as $row) {
            if (!is_array($row)) {
                continue;
            }

            $file = (string) ($row['file'] ?? '');
            if ($file === '') {
                continue;
            }
            $churnByFile[$file] = (float) ($row['churn_score'] ?? 0.0);
        }

        if ($churnByFile === []) {
            return [];
        }

        $sum = [];
        $count = [];
        foreach ($history as $report) {
            $findings = is_array($report['findings'] ?? null) ? $report['findings'] : [];
            foreach ($findings as $finding) {
                if (!is_array($finding)) {
                    continue;
                }

                $ruleId = (string) ($finding['rule_id'] ?? '');
                if ($ruleId === '') {
                    continue;
                }

                $files = $this->filesFromFinding($finding);
                if ($files === []) {
                    continue;
                }

                foreach ($files as $file) {
                    if (!isset($churnByFile[$file])) {
                        continue;
                    }

                    $sum[$ruleId] = ($sum[$ruleId] ?? 0.0) + $churnByFile[$file];
                    $count[$ruleId] = ($count[$ruleId] ?? 0) + 1;
                }
            }
        }

        $result = [];
        foreach ($sum as $ruleId => $value) {
            $den = (int) ($count[$ruleId] ?? 0);
            $result[$ruleId] = $den > 0 ? $value / $den : 0.0;
        }

        ksort($result, SORT_STRING);

        return $result;
    }

    /**
     * @param array<string, mixed> $finding
     * @return array<int, string>
     */
    private function filesFromFinding(array $finding): array
    {
        $evidence = is_array($finding['evidence'] ?? null) ? $finding['evidence'] : [];
        $files = [];
        foreach ($evidence as $row) {
            if (!is_array($row)) {
                continue;
            }

            $file = (string) ($row['file'] ?? '');
            if ($file === '') {
                continue;
            }
            $files[] = $file;
        }

        $files = array_values(array_unique($files));
        sort($files, SORT_STRING);

        return $files;
    }

    /**
     * @return array<string, array<string, scalar>>
     */
    private function effectiveTriage(string $triagePath): array
    {
        if (!is_file($triagePath)) {
            return [];
        }

        $lines = file($triagePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($lines)) {
            return [];
        }

        $effective = [];
        foreach ($lines as $line) {
            $decoded = json_decode($line, true);
            if (!is_array($decoded)) {
                continue;
            }

            $fingerprint = (string) ($decoded['fingerprint'] ?? '');
            if ($fingerprint === '') {
                continue;
            }

            $effective[$fingerprint] = [
                'fingerprint' => $fingerprint,
                'rule_id' => (string) ($decoded['rule_id'] ?? ''),
                'label' => (string) ($decoded['label'] ?? ''),
            ];
        }

        ksort($effective, SORT_STRING);

        return $effective;
    }

    /**
     * @param array<string, array<string, scalar>> $effectiveTriage
     * @return array<string, array<string, int>>
     */
    private function labelStatsByRule(array $effectiveTriage): array
    {
        $stats = [];
        foreach ($effectiveTriage as $event) {
            $ruleId = (string) ($event['rule_id'] ?? '');
            $label = (string) ($event['label'] ?? '');
            if ($ruleId === '') {
                continue;
            }

            $stats[$ruleId]['labeled'] = ($stats[$ruleId]['labeled'] ?? 0) + 1;
            if ($label === 'true_positive' || $label === 'false_positive' || $label === 'noisy') {
                $stats[$ruleId][$label] = ($stats[$ruleId][$label] ?? 0) + 1;
            }
        }

        ksort($stats, SORT_STRING);

        return $stats;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function readHistory(string $path, int $limit): array
    {
        if (!is_file($path)) {
            return [];
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($lines)) {
            return [];
        }

        $decoded = [];
        foreach ($lines as $line) {
            $row = json_decode($line, true);
            if (is_array($row)) {
                $decoded[] = $row;
            }
        }

        return array_slice($decoded, -max(1, $limit));
    }

    /**
     * @return array<string, mixed>
     */
    private function readJson(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }

        $raw = file_get_contents($path);
        if (!is_string($raw)) {
            return [];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
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
