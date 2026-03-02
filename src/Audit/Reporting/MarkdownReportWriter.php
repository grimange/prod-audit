<?php

declare(strict_types=1);

namespace ProdAudit\Audit\Reporting;

use RuntimeException;

final class MarkdownReportWriter
{
    /**
     * @param array<string, mixed> $report
     */
    public function write(string $outputDirectory, array $report, string $timestamp): void
    {
        $markdown = $this->render($report, $timestamp);
        $latestPath = rtrim($outputDirectory, '/') . '/latest.md';

        if (file_put_contents($latestPath, $markdown) === false) {
            throw new RuntimeException('Unable to write markdown report.');
        }

        $reportsDirectory = rtrim($outputDirectory, '/') . '/reports';
        if (!is_dir($reportsDirectory) && !mkdir($reportsDirectory, 0777, true) && !is_dir($reportsDirectory)) {
            throw new RuntimeException('Unable to create reports directory.');
        }

        $timestampedPath = $reportsDirectory . '/' . $timestamp . '.md';
        if (file_put_contents($timestampedPath, $markdown) === false) {
            throw new RuntimeException('Unable to write timestamped markdown report.');
        }
    }

    /**
     * @param array<string, mixed> $report
     */
    private function render(array $report, string $timestamp): string
    {
        $lines = [];
        $lines[] = '# Production Audit Report';
        $lines[] = '';

        $findings = is_array($report['findings'] ?? null) ? $report['findings'] : [];
        $suppressed = is_array($report['suppressed'] ?? null) ? $report['suppressed'] : [];
        $baseline = is_array($report['baseline'] ?? null) ? $report['baseline'] : [];
        $trend = is_array($report['trend'] ?? null) ? $report['trend'] : [];

        $lines[] = '## Executive Summary';
        $lines[] = '';
        $lines[] = '- Score: ' . (string) ($report['score'] ?? 0) . '/100 (' . (string) ($report['band'] ?? 'Unknown') . ')';
        $lines[] = '- Findings: ' . count($findings);
        $lines[] = '- Suppressed Findings: ' . count($suppressed);
        $lines[] = '- Baseline Findings: ' . count($baseline);
        $lines[] = '- Regression: ' . ((bool) ($report['regression'] ?? false) ? 'yes' : 'no');
        $lines[] = '';

        $lines[] = '## Score';
        $lines[] = '';
        $lines[] = '- Target Score: ' . (string) ($report['target_score'] ?? 0);
        $lines[] = '- Final Score: ' . (string) ($report['score'] ?? 0);
        $lines[] = '- Score Delta: ' . (string) ($trend['score_delta'] ?? 'n/a');
        $lines[] = '';

        $lines[] = '## Invariants';
        $lines[] = '';
        $lines[] = '- Invariant Failures: ' . (string) ($report['invariant_failures'] ?? 0);
        $lines[] = '';

        $lines[] = '## Top Risks';
        $lines[] = '';
        if ($findings === []) {
            $lines[] = 'No active risks.';
        } else {
            foreach (array_slice($findings, 0, 5) as $finding) {
                if (!is_array($finding)) {
                    continue;
                }

                $lines[] = sprintf(
                    '- [%s] %s (%s) `%s`',
                    (string) ($finding['severity'] ?? 'info'),
                    (string) ($finding['title'] ?? 'Untitled'),
                    (string) ($finding['rule_id'] ?? 'N/A'),
                    (string) ($finding['fingerprint'] ?? '')
                );
            }
        }
        $lines[] = '';

        $lines[] = '## Findings';
        $lines[] = '';
        if ($findings === []) {
            $lines[] = 'No active findings.';
        } else {
            foreach ($findings as $finding) {
                if (!is_array($finding)) {
                    continue;
                }

                $lines[] = sprintf(
                    '- [%s] %s (%s) `%s`',
                    (string) ($finding['severity'] ?? 'info'),
                    (string) ($finding['title'] ?? 'Untitled'),
                    (string) ($finding['rule_id'] ?? 'N/A'),
                    (string) ($finding['fingerprint'] ?? '')
                );
            }
        }
        $lines[] = '';

        $lines[] = '## Suppressed Findings';
        $lines[] = '';
        $suppressedSectionEntries = array_merge(
            array_map(
                static fn (array $item): array => ['source' => 'suppression', 'item' => $item],
                $suppressed
            ),
            array_map(
                static fn (array $item): array => ['source' => 'baseline', 'item' => $item],
                $baseline
            )
        );
        if ($suppressedSectionEntries === []) {
            $lines[] = 'No suppressed findings.';
        } else {
            foreach ($suppressedSectionEntries as $entryWithSource) {
                if (!is_array($entryWithSource)) {
                    continue;
                }

                $source = (string) ($entryWithSource['source'] ?? 'suppression');
                $item = $entryWithSource['item'] ?? null;
                if (!is_array($item)) {
                    continue;
                }

                $finding = is_array($item['finding'] ?? null) ? $item['finding'] : [];
                $entry = is_array($item['entry'] ?? null) ? $item['entry'] : [];
                $lines[] = sprintf(
                    '- %s `%s` (source: %s, rule: %s, justification: %s)',
                    (string) ($finding['title'] ?? 'Untitled'),
                    (string) ($finding['fingerprint'] ?? ''),
                    $source,
                    (string) ($entry['rule'] ?? ''),
                    (string) ($entry['justification'] ?? '')
                );
            }
        }
        $lines[] = '';

        $lines[] = '## Baseline Findings';
        $lines[] = '';
        if ($baseline === []) {
            $lines[] = 'No baseline findings.';
        } else {
            foreach ($baseline as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $finding = is_array($item['finding'] ?? null) ? $item['finding'] : [];
                $entry = is_array($item['entry'] ?? null) ? $item['entry'] : [];
                $lines[] = sprintf(
                    '- %s `%s` (rule: %s, justification: %s)',
                    (string) ($finding['title'] ?? 'Untitled'),
                    (string) ($finding['fingerprint'] ?? ''),
                    (string) ($entry['rule'] ?? ''),
                    (string) ($entry['justification'] ?? '')
                );
            }
        }
        $lines[] = '';

        $lines[] = '## Trend';
        $lines[] = '';
        $lines[] = '- Previous Score: ' . (string) ($trend['previous_score'] ?? 'n/a');
        $lines[] = '- Score Delta: ' . (string) ($trend['score_delta'] ?? 'n/a');
        $lines[] = '- New Findings: ' . (string) ($trend['new_findings'] ?? 0);
        $lines[] = '- Resolved Findings: ' . (string) ($trend['resolved_findings'] ?? 0);
        $lines[] = '- Repeated Fingerprints: ' . (string) count((array) ($trend['repeated_fingerprints'] ?? []));
        $lines[] = '- Stagnation Detected: ' . ((bool) ($trend['stagnation_detected'] ?? false) ? 'yes' : 'no');
        $lines[] = '';

        $lines[] = '## Regression Status';
        $lines[] = '';
        $lines[] = '- Regression Detected: ' . ((bool) ($report['regression'] ?? false) ? 'yes' : 'no');
        $reasons = is_array($trend['regression_reasons'] ?? null) ? $trend['regression_reasons'] : [];
        if ($reasons === []) {
            $lines[] = '- Reasons: none';
        } else {
            $lines[] = '- Reasons: ' . implode(', ', array_map(static fn ($value): string => (string) $value, $reasons));
        }
        $lines[] = '';

        $lines[] = '## Appendix';
        $lines[] = '';
        $lines[] = '- Timestamp: ' . $timestamp;
        $lines[] = '- Profile: ' . (string) ($report['profile'] ?? '');
        $lines[] = '- Path: ' . (string) ($report['path'] ?? '');
        $lines[] = '';

        return implode("\n", $lines) . "\n";
    }
}
