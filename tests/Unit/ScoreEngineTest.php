<?php

declare(strict_types=1);

namespace ProdAudit\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ProdAudit\Audit\Rules\Confidence;
use ProdAudit\Audit\Rules\Finding;
use ProdAudit\Audit\Rules\Severity;
use ProdAudit\Audit\Scoring\ScoreEngine;

final class ScoreEngineTest extends TestCase
{
    public function testAppliesSeverityPenaltyAndLowConfidenceAdjustment(): void
    {
        $engine = new ScoreEngine();

        $criticalLow = new Finding(
            id: 'f1',
            ruleId: 'PR-ERR-001',
            title: 'Example',
            category: 'reliability',
            severity: Severity::Critical,
            confidence: Confidence::Low,
            message: 'm',
            impact: 'i',
            recommendation: 'r',
            effort: 's',
            tags: [],
            evidence: [],
            fingerprint: 'fp1',
        );

        $breakdown = $engine->score([$criticalLow], 0);

        self::assertSame(94, $breakdown->finalScore);
        self::assertCount(1, $breakdown->penalties);
        self::assertSame(6, $breakdown->penalties[0]['amount']);
    }

    public function testAppliesMultipleInvariantCap(): void
    {
        $engine = new ScoreEngine();

        $minor = new Finding(
            id: 'f1',
            ruleId: 'PR-DOC-001',
            title: 'Example',
            category: 'maintainability',
            severity: Severity::Minor,
            confidence: Confidence::High,
            message: 'm',
            impact: 'i',
            recommendation: 'r',
            effort: 's',
            tags: [],
            evidence: [],
            fingerprint: 'fp1',
        );

        $breakdown = $engine->score([$minor], 2);

        self::assertSame(80, $breakdown->finalScore);
        self::assertSame('multiple_invariant_fails', $breakdown->capsApplied[0]['reason']);
    }
}
