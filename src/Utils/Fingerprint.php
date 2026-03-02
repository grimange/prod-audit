<?php

declare(strict_types=1);

namespace ProdAudit\Utils;

use ProdAudit\Audit\Rules\Evidence;

final class Fingerprint
{
    /**
     * @param array<int, Evidence> $evidence
     */
    public static function fromEvidence(string $ruleId, array $evidence): string
    {
        $evidenceHashes = array_map(
            static fn (Evidence $item): string => $item->hash,
            $evidence
        );

        sort($evidenceHashes, SORT_STRING);

        $evidenceHash = hash('sha256', implode('|', $evidenceHashes));

        return hash('sha256', $ruleId . '|' . $evidenceHash);
    }
}
