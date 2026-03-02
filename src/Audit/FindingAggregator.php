<?php

declare(strict_types=1);

namespace ProdAudit\Audit;

use ProdAudit\Audit\Rules\Finding;
use ProdAudit\Audit\Rules\RuleResult;
use ProdAudit\Utils\StableSort;

final class FindingAggregator
{
    /**
     * @param array<int, RuleResult> $results
     * @return array<int, Finding>
     */
    public function aggregate(array $results): array
    {
        $byFingerprint = [];
        foreach ($results as $result) {
            foreach ($result->findings as $finding) {
                if (!isset($byFingerprint[$finding->fingerprint])) {
                    $byFingerprint[$finding->fingerprint] = $finding;
                }
            }
        }

        $findings = array_values($byFingerprint);

        return StableSort::sort(
            $findings,
            static function (Finding $a, Finding $b): int {
                $severityCompare = $b->severity->weight() <=> $a->severity->weight();
                if ($severityCompare !== 0) {
                    return $severityCompare;
                }

                $ruleCompare = strcmp($a->ruleId, $b->ruleId);
                if ($ruleCompare !== 0) {
                    return $ruleCompare;
                }

                return strcmp($a->fingerprint, $b->fingerprint);
            }
        );
    }
}
