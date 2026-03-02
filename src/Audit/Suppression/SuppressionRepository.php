<?php

declare(strict_types=1);

namespace ProdAudit\Audit\Suppression;

use RuntimeException;

final class SuppressionRepository
{
    /**
     * @return array<int, array{rule: string, path: ?string, justification: string, expires: ?string}>
     */
    public function loadActiveEntries(string $path): array
    {
        $decoded = $this->readFile($path);
        $entries = $decoded['suppressions'] ?? [];

        if (!is_array($entries)) {
            throw new RuntimeException('Invalid suppressions file: suppressions must be an array.');
        }

        $active = [];
        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $rule = isset($entry['rule']) && is_string($entry['rule']) ? trim($entry['rule']) : '';
            $pathPattern = isset($entry['path']) && is_string($entry['path']) ? trim($entry['path']) : null;
            $pathPattern = $pathPattern === '' ? null : $pathPattern;
            $justification = isset($entry['justification']) && is_string($entry['justification']) ? $entry['justification'] : '';
            $expires = isset($entry['expires']) && is_string($entry['expires']) ? trim($entry['expires']) : null;
            $expires = $expires === '' ? null : $expires;

            if ($rule === '') {
                continue;
            }

            if (!$this->isActive($expires)) {
                continue;
            }

            $active[] = [
                'rule' => $rule,
                'path' => $pathPattern,
                'justification' => $justification,
                'expires' => $expires,
            ];
        }

        usort(
            $active,
            static function (array $a, array $b): int {
                $ruleCompare = strcmp($a['rule'], $b['rule']);
                if ($ruleCompare !== 0) {
                    return $ruleCompare;
                }

                return strcmp((string) ($a['path'] ?? ''), (string) ($b['path'] ?? ''));
            }
        );

        return $active;
    }

    /**
     * @return array<string, mixed>
     */
    private function readFile(string $path): array
    {
        if (!is_file($path)) {
            throw new RuntimeException(sprintf('Suppressions file not found: %s', $path));
        }

        $raw = file_get_contents($path);
        if (!is_string($raw)) {
            throw new RuntimeException('Unable to read suppressions file.');
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Suppressions file contains invalid JSON.');
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
