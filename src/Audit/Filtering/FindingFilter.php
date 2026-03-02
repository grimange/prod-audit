<?php

declare(strict_types=1);

namespace ProdAudit\Audit\Filtering;

use ProdAudit\Audit\Rules\Finding;

final class FindingFilter
{
    /**
     * @param array<int, Finding> $findings
     * @param array<int, array{fingerprint: string, rule: string, justification: string, expires: ?string}> $baselineEntries
     * @param array<int, array{rule: string, path: ?string, justification: string, expires: ?string}> $suppressionEntries
     * @return array{active: array<int, Finding>, suppressed: array<int, array{finding: Finding, entry: array<string, mixed>}>, baseline: array<int, array{finding: Finding, entry: array<string, mixed}>}
     */
    public function filter(array $findings, array $baselineEntries, array $suppressionEntries): array
    {
        $baselineByFingerprint = [];
        foreach ($baselineEntries as $entry) {
            $baselineByFingerprint[$entry['fingerprint']] = $entry;
        }

        $active = [];
        $suppressed = [];
        $baseline = [];

        foreach ($findings as $finding) {
            $suppressionEntry = $this->matchSuppression($finding, $suppressionEntries);
            if ($suppressionEntry !== null) {
                $suppressed[] = [
                    'finding' => $finding,
                    'entry' => $suppressionEntry,
                ];
                continue;
            }

            $baselineEntry = $baselineByFingerprint[$finding->fingerprint] ?? null;
            if (is_array($baselineEntry)) {
                $baseline[] = [
                    'finding' => $finding,
                    'entry' => $baselineEntry,
                ];
                continue;
            }

            $active[] = $finding;
        }

        return [
            'active' => $active,
            'suppressed' => $suppressed,
            'baseline' => $baseline,
        ];
    }

    /**
     * @param array<int, array{rule: string, path: ?string, justification: string, expires: ?string}> $suppressionEntries
     * @return array{rule: string, path: ?string, justification: string, expires: ?string}|null
     */
    private function matchSuppression(Finding $finding, array $suppressionEntries): ?array
    {
        foreach ($suppressionEntries as $entry) {
            if ($entry['rule'] !== $finding->ruleId) {
                continue;
            }

            $pattern = $entry['path'];
            if ($pattern === null) {
                return $entry;
            }

            foreach ($finding->evidence as $evidence) {
                if ($evidence->file === null || $evidence->file === '') {
                    continue;
                }

                if (fnmatch($pattern, $evidence->file)) {
                    return $entry;
                }
            }
        }

        return null;
    }
}
