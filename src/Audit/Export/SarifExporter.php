<?php

declare(strict_types=1);

namespace ProdAudit\Audit\Export;

use RuntimeException;

final class SarifExporter
{
    /**
     * @param array<string, mixed> $report
     */
    public function write(string $outputDirectory, array $report, bool $includeSuppressed = false): string
    {
        $findings = $this->collectFindings($report, $includeSuppressed);

        $results = [];
        foreach ($findings as $finding) {
            $ruleId = (string) ($finding['rule_id'] ?? 'UNKNOWN');
            $message = (string) ($finding['message'] ?? $finding['title'] ?? '');
            $severity = strtoupper((string) ($finding['severity'] ?? 'note'));
            $evidence = is_array($finding['evidence'][0] ?? null) ? $finding['evidence'][0] : [];
            $file = (string) ($evidence['file'] ?? '');
            $line = (int) ($evidence['line_start'] ?? 1);

            $results[] = [
                'ruleId' => $ruleId,
                'level' => match ($severity) {
                    'CRITICAL', 'MAJOR' => 'error',
                    'MINOR' => 'warning',
                    default => 'note',
                },
                'message' => ['text' => $message],
                'locations' => [[
                    'physicalLocation' => [
                        'artifactLocation' => ['uri' => $file],
                        'region' => ['startLine' => max(1, $line)],
                    ],
                ]],
                'fingerprints' => [
                    'primaryLocationLineHash' => (string) ($finding['fingerprint'] ?? ''),
                ],
            ];
        }

        $sarif = [
            '$schema' => 'https://json.schemastore.org/sarif-2.1.0.json',
            'version' => '2.1.0',
            'runs' => [[
                'tool' => [
                    'driver' => [
                        'name' => 'prod-audit',
                        'version' => (string) ($report['tool_version'] ?? 'stage5'),
                    ],
                ],
                'results' => $results,
            ]],
        ];

        $encoded = json_encode($sarif, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($encoded === false) {
            throw new RuntimeException('Unable to encode SARIF report.');
        }

        $path = rtrim($outputDirectory, '/') . '/latest.sarif';
        if (file_put_contents($path, $encoded . "\n") === false) {
            throw new RuntimeException('Unable to write SARIF report.');
        }

        return $path;
    }

    /**
     * @param array<string, mixed> $report
     * @return array<int, array<string, mixed>>
     */
    private function collectFindings(array $report, bool $includeSuppressed): array
    {
        $findings = is_array($report['findings'] ?? null) ? $report['findings'] : [];

        if ($includeSuppressed) {
            foreach (['suppressed', 'baseline'] as $bucket) {
                foreach ((array) ($report[$bucket] ?? []) as $entry) {
                    if (!is_array($entry)) {
                        continue;
                    }

                    $finding = $entry['finding'] ?? null;
                    if (is_array($finding)) {
                        $findings[] = $finding;
                    }
                }
            }
        }

        usort($findings, static fn (array $a, array $b): int => strcmp((string) ($a['fingerprint'] ?? ''), (string) ($b['fingerprint'] ?? '')));

        return $findings;
    }
}
