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
        $tasks = is_array($report['tasks'] ?? null) ? $report['tasks'] : [];
        $metrics = is_array($report['scan_metrics'] ?? null) ? $report['scan_metrics'] : [];
        $policyReasons = is_array($report['policy_reasons'] ?? null) ? $report['policy_reasons'] : [];
        $rulePackSummary = is_array($report['rule_pack_summary'] ?? null) ? $report['rule_pack_summary'] : [];
        $insights = is_array($report['insights'] ?? null) ? $report['insights'] : [];
        $forecast = is_array($report['forecast'] ?? null) ? $report['forecast'] : [];
        $actions = is_array($report['actions'] ?? null) ? $report['actions'] : [];

        $lines[] = '## Executive Summary';
        $lines[] = '';
        $lines[] = '- Score: ' . (string) ($report['score'] ?? 0) . '/100 (' . (string) ($report['band'] ?? 'Unknown') . ')';
        $lines[] = '- Findings: ' . count($findings);
        $lines[] = '- Suppressed Findings: ' . count($suppressed);
        $lines[] = '- Baseline Findings: ' . count($baseline);
        $lines[] = '- Regression: ' . ((bool) ($report['regression'] ?? false) ? 'yes' : 'no');
        $lines[] = '- Noise Score: ' . sprintf('%.3f', (float) ($report['noise_score'] ?? 0.0));
        $lines[] = '- Stability Score: ' . sprintf('%.3f', (float) ($report['stability_score'] ?? 0.0));
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

        $lines[] = '## Policy';
        $lines[] = '';
        $lines[] = '- Policy Name: ' . (string) ($report['policy_name'] ?? 'default');
        $lines[] = '- Policy Result: ' . (string) ($report['policy_result'] ?? 'pass');
        if ($policyReasons === []) {
            $lines[] = '- Policy Reasons: none';
        } else {
            $lines[] = '- Policy Reasons: ' . implode(', ', array_map(static fn (mixed $value): string => (string) $value, $policyReasons));
        }
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

        $lines[] = '## Top Risks (Insight-ranked)';
        $lines[] = '';
        $prioritized = is_array($insights['prioritized_findings'] ?? null) ? $insights['prioritized_findings'] : [];
        if ($prioritized === []) {
            $lines[] = 'No insight-ranked risks.';
        } else {
            foreach (array_slice($prioritized, 0, 10) as $entry) {
                if (!is_array($entry)) {
                    continue;
                }

                $lines[] = sprintf(
                    '- [%s] %s `%s` rank=%.3f persistence=%.3f noise=%.3f',
                    (string) ($entry['rule_id'] ?? ''),
                    (string) ($entry['severity'] ?? ''),
                    (string) ($entry['fingerprint'] ?? ''),
                    (float) ($entry['rank'] ?? 0.0),
                    (float) ($entry['persistence'] ?? 0.0),
                    (float) ($entry['noise'] ?? 0.0)
                );
            }
        }
        $lines[] = '';

        $lines[] = '## Insights';
        $lines[] = '';
        $lines[] = '- Noise Score: ' . sprintf('%.3f', (float) ($insights['noise_score'] ?? 0.0));
        $lines[] = '- Stability Score: ' . sprintf('%.3f', (float) ($insights['stability_score'] ?? 0.0));

        $topPersistent = is_array($insights['top_persistent_fingerprints'] ?? null) ? $insights['top_persistent_fingerprints'] : [];
        $lines[] = '- Top persistent risks:';
        if ($topPersistent === []) {
            $lines[] = '  - none';
        } else {
            foreach (array_slice($topPersistent, 0, 5) as $entry) {
                if (!is_array($entry)) {
                    continue;
                }

                $lines[] = sprintf(
                    '  - %s (%s) persistence=%.3f noise=%.3f',
                    (string) ($entry['fingerprint'] ?? ''),
                    (string) ($entry['rule_id'] ?? ''),
                    (float) ($entry['persistence'] ?? 0.0),
                    (float) ($entry['noise'] ?? 0.0)
                );
            }
        }

        $topNoisy = is_array($insights['top_noisy_rules'] ?? null) ? $insights['top_noisy_rules'] : [];
        $lines[] = '- Noisiest rules:';
        if ($topNoisy === []) {
            $lines[] = '  - none';
        } else {
            foreach (array_slice($topNoisy, 0, 5) as $entry) {
                if (!is_array($entry)) {
                    continue;
                }

                $lines[] = sprintf(
                    '  - %s noise=%.3f',
                    (string) ($entry['rule_id'] ?? ''),
                    (float) ($entry['noise'] ?? 0.0)
                );
            }
        }

        $recentlyFixed = is_array($insights['recently_fixed_streaks'] ?? null) ? $insights['recently_fixed_streaks'] : [];
        $lines[] = '- Recently fixed streaks:';
        if ($recentlyFixed === []) {
            $lines[] = '  - none';
        } else {
            foreach (array_slice($recentlyFixed, 0, 5) as $entry) {
                if (!is_array($entry)) {
                    continue;
                }

                $lines[] = sprintf(
                    '  - %s (%s)',
                    (string) ($entry['fingerprint'] ?? ''),
                    (string) ($entry['rule_id'] ?? '')
                );
            }
        }

        $hotspots = is_array($insights['hotspots'] ?? null) ? $insights['hotspots'] : [];
        $lines[] = '- Hotspot files:';
        if ($hotspots === []) {
            $lines[] = '  - none';
        } else {
            foreach (array_slice($hotspots, 0, 5) as $entry) {
                if (!is_array($entry)) {
                    continue;
                }

                $lines[] = sprintf(
                    '  - %s churn=%.3f findings=%d',
                    (string) ($entry['file'] ?? ''),
                    (float) ($entry['churn_score'] ?? 0.0),
                    (int) ($entry['findings'] ?? 0)
                );
            }
        }
        $lines[] = '';

        $lines[] = '## Forecast';
        $lines[] = '';
        $lines[] = '- risk_new_invariant_fail: ' . sprintf('%.3f', (float) ($forecast['risk_new_invariant_fail'] ?? 0.0));
        $lines[] = '- risk_score_drop_5: ' . sprintf('%.3f', (float) ($forecast['risk_score_drop_5'] ?? 0.0));
        $lines[] = '- risk_new_critical: ' . sprintf('%.3f', (float) ($forecast['risk_new_critical'] ?? 0.0));

        $drivers = is_array($forecast['top_drivers'] ?? null) ? $forecast['top_drivers'] : [];
        $lines[] = '- Top drivers:';
        if ($drivers === []) {
            $lines[] = '  - none';
        } else {
            foreach (array_slice($drivers, 0, 5) as $driver) {
                if (!is_array($driver)) {
                    continue;
                }

                $key = (string) ($driver['fingerprint'] ?? $driver['file'] ?? '');
                $reason = (string) ($driver['reason'] ?? '');
                $lines[] = sprintf('  - %s (%s)', $key, $reason);
            }
        }
        $lines[] = '';

        $lines[] = '## Next Best Actions (insight-driven)';
        $lines[] = '';
        if ($actions === []) {
            $lines[] = 'No insight-driven actions.';
        } else {
            foreach (array_slice($actions, 0, 10) as $action) {
                if (!is_array($action)) {
                    continue;
                }

                $lines[] = sprintf(
                    '- [%s] %s why=%s refs=%s',
                    (string) ($action['id'] ?? 'ACTION'),
                    (string) ($action['title'] ?? 'Untitled'),
                    (string) ($action['why_now'] ?? ''),
                    implode(',', array_map(static fn (mixed $v): string => (string) $v, (array) ($action['evidence_refs'] ?? [])))
                );
            }
        }
        $lines[] = '';

        $lines[] = '## Recommended Tasks';
        $lines[] = '';
        if ($tasks === []) {
            $lines[] = 'No recommended tasks.';
        } else {
            foreach (array_slice($tasks, 0, 10) as $task) {
                if (!is_array($task)) {
                    continue;
                }

                $lines[] = sprintf(
                    '- [%s] %s (%s) rules=%s',
                    (string) ($task['effort'] ?? 'M'),
                    (string) ($task['title'] ?? 'Untitled Task'),
                    (string) ($task['id'] ?? 'TASK'),
                    implode(',', array_map(static fn (mixed $rule): string => (string) $rule, (array) ($task['related_rules'] ?? [])))
                );
            }
        }
        $lines[] = '';

        $lines[] = '## Rule Pack Summary';
        $lines[] = '';
        if ($rulePackSummary === []) {
            $lines[] = 'No pack summary available.';
        } else {
            ksort($rulePackSummary, SORT_STRING);
            foreach ($rulePackSummary as $packName => $summary) {
                if (!is_array($summary)) {
                    continue;
                }

                $lines[] = sprintf(
                    '- %s Pack: Rules=%d Findings=%d',
                    ucfirst((string) $packName),
                    (int) ($summary['rules'] ?? 0),
                    (int) ($summary['findings'] ?? 0)
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
        $lines[] = '- Files Scanned: ' . (string) ($metrics['files_scanned_count'] ?? 0);
        $lines[] = '- Rules Executed: ' . (string) ($metrics['rules_executed_count'] ?? 0);
        $lines[] = '- Scan Duration (ms): ' . (string) ($metrics['scan_duration_ms'] ?? 0);
        $lines[] = '';

        return implode("\n", $lines) . "\n";
    }
}
