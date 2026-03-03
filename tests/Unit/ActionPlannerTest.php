<?php

declare(strict_types=1);

namespace ProdAudit\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ProdAudit\Audit\Actions\ActionPlanner;

final class ActionPlannerTest extends TestCase
{
    public function testGeneratesDeterministicActionsWithEvidenceReferences(): void
    {
        $findings = [
            [
                'fingerprint' => 'fp-lock-1',
                'rule_id' => 'PR-LOCK-001',
                'evidence' => [['file' => 'src/Lock.php', 'line_start' => 11]],
            ],
            [
                'fingerprint' => 'fp-hang-1',
                'rule_id' => 'PR-HANG-001',
                'evidence' => [['file' => 'src/Worker.php', 'line_start' => 20]],
            ],
        ];

        $insights = [
            'prioritized_findings' => [
                ['fingerprint' => 'fp-lock-1', 'rule_id' => 'PR-LOCK-001', 'rank' => 9.0, 'persistence' => 0.9, 'noise' => 0.1],
                ['fingerprint' => 'fp-hang-1', 'rule_id' => 'PR-HANG-001', 'rank' => 8.0, 'persistence' => 0.8, 'noise' => 0.2],
            ],
        ];

        $forecast = [
            'risk_new_invariant_fail' => 0.9,
            'risk_score_drop_5' => 0.7,
        ];

        $actions = (new ActionPlanner())->plan($findings, $insights, $forecast);

        self::assertCount(2, $actions);
        self::assertSame('ACT-LOCK-001', $actions[0]->id);
        self::assertNotEmpty($actions[0]->evidenceRefs);
        self::assertStringContainsString('forecast', $actions[0]->whyNow);
    }
}
