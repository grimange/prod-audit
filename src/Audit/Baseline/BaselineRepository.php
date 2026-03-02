<?php

declare(strict_types=1);

namespace ProdAudit\Audit\Baseline;

use ProdAudit\Audit\Rules\Finding;
use RuntimeException;

final class BaselineRepository
{
    /**
     * @return array<int, array{fingerprint: string, rule: string, justification: string, expires: ?string}>
     */
    public function loadActiveEntries(string $path): array
    {
        $decoded = $this->readFile($path);
        $entries = $decoded['accepted_findings'] ?? [];

        if (!is_array($entries)) {
            throw new RuntimeException('Invalid baseline file: accepted_findings must be an array.');
        }

        $active = [];
        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $fingerprint = isset($entry['fingerprint']) && is_string($entry['fingerprint']) ? trim($entry['fingerprint']) : '';
            $rule = isset($entry['rule']) && is_string($entry['rule']) ? trim($entry['rule']) : '';
            $justification = isset($entry['justification']) && is_string($entry['justification']) ? $entry['justification'] : '';
            $expires = isset($entry['expires']) && is_string($entry['expires']) ? trim($entry['expires']) : null;
            $expires = $expires === '' ? null : $expires;

            if ($fingerprint === '' || $rule === '') {
                continue;
            }

            if (!$this->isActive($expires)) {
                continue;
            }

            $active[] = [
                'fingerprint' => $fingerprint,
                'rule' => $rule,
                'justification' => $justification,
                'expires' => $expires,
            ];
        }

        usort(
            $active,
            static fn (array $a, array $b): int => strcmp($a['fingerprint'], $b['fingerprint'])
        );

        return $active;
    }

    /**
     * @param array<int, Finding> $findings
     */
    public function write(string $path, string $profile, int $targetScore, array $findings, string $createdAt): void
    {
        $acceptedFindings = [];
        foreach ($findings as $finding) {
            $acceptedFindings[] = [
                'fingerprint' => $finding->fingerprint,
                'rule' => $finding->ruleId,
                'justification' => '',
                'expires' => null,
            ];
        }

        usort(
            $acceptedFindings,
            static fn (array $a, array $b): int => strcmp((string) $a['fingerprint'], (string) $b['fingerprint'])
        );

        $content = [
            'profile' => $profile,
            'created_at' => $createdAt,
            'target_score' => $targetScore,
            'accepted_findings' => $acceptedFindings,
        ];

        $encoded = json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($encoded === false) {
            throw new RuntimeException('Unable to encode baseline file.');
        }

        if (file_put_contents($path, $encoded . "\n") === false) {
            throw new RuntimeException('Unable to write baseline file.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function readFile(string $path): array
    {
        if (!is_file($path)) {
            throw new RuntimeException(sprintf('Baseline file not found: %s', $path));
        }

        $raw = file_get_contents($path);
        if (!is_string($raw)) {
            throw new RuntimeException('Unable to read baseline file.');
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Baseline file contains invalid JSON.');
        }

        return $decoded;
    }

    private function isActive(?string $expires): bool
    {
        if ($expires === null) {
            return true;
        }

        $timestamp = strtotime($expires);
        if ($timestamp === false) {
            return false;
        }

        return $timestamp >= time();
    }
}
