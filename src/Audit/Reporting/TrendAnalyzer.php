<?php

declare(strict_types=1);

namespace ProdAudit\Audit\Reporting;

final class TrendAnalyzer
{
    /**
     * @param array<string, mixed> $currentReport
     * @return array<string, mixed>
     */
    public function analyze(string $historyPath, array $currentReport, int $windowSize = 3): array
    {
        $history = $this->readHistory($historyPath);

        /** @var array<string, mixed>|null $previous */
        $previous = null;
        $previousScore = null;
        if ($history !== []) {
            $previous = $history[array_key_last($history)];
            $previousScore = is_int($previous['score'] ?? null) ? $previous['score'] : null;
        }

        $currentScore = (int) ($currentReport['score'] ?? 0);

        $currentFindingsByFingerprint = $this->findingsByFingerprint($currentReport);
        $currentFingerprints = array_keys($currentFindingsByFingerprint);
        sort($currentFingerprints, SORT_STRING);

        $previousFindingsByFingerprint = [];
        $previousFingerprints = [];
        $repeatedFingerprints = [];
        if ($previous !== null) {
            $previousFindingsByFingerprint = $this->findingsByFingerprint($previous);
            $previousFingerprints = array_keys($previousFindingsByFingerprint);
            sort($previousFingerprints, SORT_STRING);
            $repeatedFingerprints = array_values(array_intersect($currentFingerprints, $previousFingerprints));
            sort($repeatedFingerprints, SORT_STRING);
        }

        $newFingerprints = array_values(array_diff($currentFingerprints, $previousFingerprints));
        $resolvedFingerprints = array_values(array_diff($previousFingerprints, $currentFingerprints));
        sort($newFingerprints, SORT_STRING);
        sort($resolvedFingerprints, SORT_STRING);

        $scoreDelta = $previousScore === null ? null : $currentScore - $previousScore;
        $criticalAdded = $this->hasNewCriticalFinding($newFingerprints, $currentFindingsByFingerprint);
        $newInvariantFailure = $this->hasNewInvariantFailure($newFingerprints, $currentFindingsByFingerprint);
        $scoreDropThresholdMet = $scoreDelta !== null && $scoreDelta <= -5;
        $regression = $criticalAdded || $newInvariantFailure || $scoreDropThresholdMet;

        $regressionReasons = [];
        if ($criticalAdded) {
            $regressionReasons[] = 'critical_added';
        }

        if ($newInvariantFailure) {
            $regressionReasons[] = 'invariant_failure_added';
        }

        if ($scoreDropThresholdMet) {
            $regressionReasons[] = 'score_drop_gte_5';
        }

        $stagnationDetected = false;
        if (count($history) >= $windowSize - 1) {
            $windowReports = array_slice($history, -($windowSize - 1));
            $windowReports[] = $currentReport;
            $target = $currentFingerprints;
            $stagnationDetected = true;

            foreach ($windowReports as $report) {
                if ($this->sortedFingerprints($report) !== $target) {
                    $stagnationDetected = false;
                    break;
                }
            }
        }

        return [
            'previous_score' => $previousScore,
            'current_score' => $currentScore,
            'score_delta' => $scoreDelta,
            'new_findings' => count($newFingerprints),
            'resolved_findings' => count($resolvedFingerprints),
            'new_fingerprints' => $newFingerprints,
            'resolved_fingerprints' => $resolvedFingerprints,
            'repeated_fingerprints' => $repeatedFingerprints,
            'regression' => $regression,
            'regression_reasons' => $regressionReasons,
            'stagnation_detected' => $stagnationDetected,
            'window_size' => $windowSize,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function readHistory(string $historyPath): array
    {
        if (!is_file($historyPath)) {
            return [];
        }

        $lines = file($historyPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return [];
        }

        $reports = [];
        foreach ($lines as $line) {
            $decoded = json_decode($line, true);
            if (is_array($decoded)) {
                $reports[] = $decoded;
            }
        }

        return $reports;
    }

    /**
     * @param array<string, mixed> $report
     * @return array<int, string>
     */
    private function sortedFingerprints(array $report): array
    {
        return array_keys($this->findingsByFingerprint($report));
    }

    /**
     * @param array<string, mixed> $report
     * @return array<string, array<string, mixed>>
     */
    private function findingsByFingerprint(array $report): array
    {
        $findings = $report['findings'] ?? [];
        if (!is_array($findings)) {
            return [];
        }

        $byFingerprint = [];
        foreach ($findings as $finding) {
            if (!is_array($finding)) {
                continue;
            }

            $fingerprint = $finding['fingerprint'] ?? null;
            if (!is_string($fingerprint) || $fingerprint === '') {
                continue;
            }

            if (!isset($byFingerprint[$fingerprint])) {
                $byFingerprint[$fingerprint] = $finding;
            }
        }

        ksort($byFingerprint, SORT_STRING);

        return $byFingerprint;
    }

    /**
     * @param array<int, string> $newFingerprints
     * @param array<string, array<string, mixed>> $currentFindingsByFingerprint
     */
    private function hasNewCriticalFinding(array $newFingerprints, array $currentFindingsByFingerprint): bool
    {
        foreach ($newFingerprints as $fingerprint) {
            $finding = $currentFindingsByFingerprint[$fingerprint] ?? null;
            if (!is_array($finding)) {
                continue;
            }

            if (($finding['severity'] ?? null) === 'critical') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int, string> $newFingerprints
     * @param array<string, array<string, mixed>> $currentFindingsByFingerprint
     */
    private function hasNewInvariantFailure(array $newFingerprints, array $currentFindingsByFingerprint): bool
    {
        foreach ($newFingerprints as $fingerprint) {
            $finding = $currentFindingsByFingerprint[$fingerprint] ?? null;
            if (!is_array($finding)) {
                continue;
            }

            if (($finding['invariant_failure'] ?? false) === true) {
                return true;
            }
        }

        return false;
    }
}
