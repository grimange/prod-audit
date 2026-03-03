<?php

declare(strict_types=1);

namespace ProdAudit\Audit\Triage;

use RuntimeException;

final class TriageStore
{
    private const TRIAGE_FILE = 'triage.jsonl';

    public function append(string $outputDirectory, TriageEvent $event): void
    {
        $path = $this->triagePath($outputDirectory);
        $line = json_encode($event->toArray(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($line === false) {
            throw new RuntimeException('Unable to encode triage event.');
        }

        if (file_put_contents($path, $line . "\n", FILE_APPEND) === false) {
            throw new RuntimeException('Unable to append triage event.');
        }
    }

    /**
     * @return array<int, TriageEvent>
     */
    public function readAll(string $outputDirectory): array
    {
        $path = $this->triagePath($outputDirectory);
        if (!is_file($path)) {
            return [];
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            throw new RuntimeException('Unable to read triage store.');
        }

        $events = [];
        foreach ($lines as $index => $line) {
            $decoded = json_decode($line, true);
            if (!is_array($decoded)) {
                throw new RuntimeException(sprintf('Invalid triage JSON at line %d.', $index + 1));
            }

            $events[] = TriageEvent::fromArray($decoded);
        }

        return $events;
    }

    /**
     * @return array<string, TriageEvent>
     */
    public function effectiveEventsByFingerprint(string $outputDirectory): array
    {
        $effective = [];
        foreach ($this->readAll($outputDirectory) as $event) {
            $effective[$event->fingerprint] = $event;
        }

        ksort($effective, SORT_STRING);

        return $effective;
    }

    public function effectiveLabel(string $outputDirectory, string $fingerprint): ?string
    {
        $effective = $this->effectiveEventsByFingerprint($outputDirectory);
        if (!isset($effective[$fingerprint])) {
            return null;
        }

        return $effective[$fingerprint]->label;
    }

    /**
     * @return array<string, string>
     */
    public function effectiveLabels(string $outputDirectory): array
    {
        $labels = [];
        foreach ($this->effectiveEventsByFingerprint($outputDirectory) as $fingerprint => $event) {
            $labels[$fingerprint] = $event->label;
        }

        ksort($labels, SORT_STRING);

        return $labels;
    }

    public function latestFindingForFingerprint(string $outputDirectory, string $fingerprint): ?array
    {
        $latestPath = rtrim($outputDirectory, '/') . '/latest.json';
        if (!is_file($latestPath)) {
            return null;
        }

        $raw = file_get_contents($latestPath);
        if (!is_string($raw)) {
            return null;
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return null;
        }

        $findings = $decoded['findings'] ?? [];
        if (!is_array($findings)) {
            return null;
        }

        foreach ($findings as $finding) {
            if (!is_array($finding)) {
                continue;
            }

            if (($finding['fingerprint'] ?? null) === $fingerprint) {
                return $finding;
            }
        }

        return null;
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function listEffective(string $outputDirectory, ?string $labelFilter = null, ?string $ruleFilter = null): array
    {
        $rows = [];
        foreach ($this->effectiveEventsByFingerprint($outputDirectory) as $fingerprint => $event) {
            if ($labelFilter !== null && $labelFilter !== '' && $event->label !== $labelFilter) {
                continue;
            }

            if ($ruleFilter !== null && $ruleFilter !== '' && $event->ruleId !== $ruleFilter) {
                continue;
            }

            $rows[] = [
                'fingerprint' => $fingerprint,
                'rule_id' => $event->ruleId,
                'label' => $event->label,
                'timestamp_iso' => $event->timestampIso,
                'note' => $event->note ?? '',
                'actor' => $event->actor ?? '',
            ];
        }

        usort($rows, static function (array $a, array $b): int {
            $aRule = (string) ($a['rule_id'] ?? '');
            $bRule = (string) ($b['rule_id'] ?? '');
            if ($aRule !== $bRule) {
                return strcmp($aRule, $bRule);
            }

            $aLabel = (string) ($a['label'] ?? '');
            $bLabel = (string) ($b['label'] ?? '');
            if ($aLabel !== $bLabel) {
                return strcmp($aLabel, $bLabel);
            }

            return strcmp((string) ($a['fingerprint'] ?? ''), (string) ($b['fingerprint'] ?? ''));
        });

        return $rows;
    }

    private function triagePath(string $outputDirectory): string
    {
        $out = rtrim($outputDirectory, '/');
        if (!is_dir($out) && !mkdir($out, 0777, true) && !is_dir($out)) {
            throw new RuntimeException('Unable to create triage output directory.');
        }

        return $out . '/' . self::TRIAGE_FILE;
    }
}
