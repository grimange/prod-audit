<?php

declare(strict_types=1);

namespace ProdAudit\Audit\Scoring;

use ProdAudit\Audit\Rules\Finding;

final class ScoreEngine
{
    private const STARTING_SCORE = 100;

    /**
     * @param array<int, Finding> $findings
     */
    public function score(array $findings, int $invariantFailures): ScoreBreakdown
    {
        $score = self::STARTING_SCORE;
        $penalties = [];

        foreach ($findings as $finding) {
            $basePenalty = match ($finding->severity->value) {
                'critical' => 12,
                'major' => 6,
                'minor' => 2,
                default => 0,
            };

            if ($basePenalty === 0) {
                continue;
            }

            $amount = (int) round($basePenalty * $finding->confidence->multiplier());
            $score -= $amount;
            $penalties[] = [
                'rule_id' => $finding->ruleId,
                'severity' => $finding->severity->value,
                'confidence' => $finding->confidence->value,
                'amount' => $amount,
                'fingerprint' => $finding->fingerprint,
            ];
        }

        $score = max(0, $score);

        $capsApplied = [];
        if ($invariantFailures >= 2) {
            $score = min($score, 80);
            $capsApplied[] = ['reason' => 'multiple_invariant_fails', 'cap' => 80];
        } elseif ($invariantFailures === 1) {
            $score = min($score, 94);
            $capsApplied[] = ['reason' => 'invariant_fail', 'cap' => 94];
        }

        return new ScoreBreakdown(self::STARTING_SCORE, $penalties, $capsApplied, $score);
    }
}
