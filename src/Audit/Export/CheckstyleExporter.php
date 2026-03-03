<?php

declare(strict_types=1);

namespace ProdAudit\Audit\Export;

use RuntimeException;

final class CheckstyleExporter
{
    /**
     * @param array<string, mixed> $report
     */
    public function write(string $outputDirectory, array $report, bool $includeSuppressed = false): string
    {
        $findings = $this->collectFindings($report, $includeSuppressed);

        $byFile = [];
        foreach ($findings as $finding) {
            $evidence = is_array($finding['evidence'][0] ?? null) ? $finding['evidence'][0] : [];
            $file = (string) ($evidence['file'] ?? 'unknown');
            $byFile[$file][] = $finding;
        }

        ksort($byFile, SORT_STRING);
        foreach ($byFile as &$entries) {
            usort($entries, static fn (array $a, array $b): int => strcmp((string) ($a['fingerprint'] ?? ''), (string) ($b['fingerprint'] ?? '')));
        }

        $xmlLines = ['<?xml version="1.0" encoding="UTF-8"?>', '<checkstyle version="10.0">'];
        foreach ($byFile as $file => $entries) {
            $xmlLines[] = sprintf('  <file name="%s">', $this->xml((string) $file));
            foreach ($entries as $finding) {
                $evidence = is_array($finding['evidence'][0] ?? null) ? $finding['evidence'][0] : [];
                $line = max(1, (int) ($evidence['line_start'] ?? 1));
                $severity = (string) ($finding['severity'] ?? 'info');
                $message = (string) ($finding['message'] ?? $finding['title'] ?? '');
                $source = (string) ($finding['rule_id'] ?? 'prod-audit');

                $xmlLines[] = sprintf(
                    '    <error line="%d" severity="%s" message="%s" source="%s"/>',
                    $line,
                    $this->xml($severity),
                    $this->xml($message),
                    $this->xml($source)
                );
            }
            $xmlLines[] = '  </file>';
        }
        $xmlLines[] = '</checkstyle>';

        $xml = implode("\n", $xmlLines) . "\n";
        $path = rtrim($outputDirectory, '/') . '/latest.checkstyle.xml';

        if (file_put_contents($path, $xml) === false) {
            throw new RuntimeException('Unable to write checkstyle report.');
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

    private function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
