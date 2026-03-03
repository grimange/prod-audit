<?php

declare(strict_types=1);

namespace ProdAudit\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ProdAudit\Audit\Policy\Policy;
use ProdAudit\Audit\Policy\PolicyEvaluator;

final class PolicyModeTest extends TestCase
{
    public function testStrictPolicyFailsWhenNewCriticalIsPresent(): void
    {
        $policy = Policy::preset('strict');
        $evaluator = new PolicyEvaluator();

        $result = $evaluator->evaluate($policy, [
            'regression' => false,
            'findings' => [[
                'fingerprint' => 'a1',
                'severity' => 'critical',
                'invariant_failure' => false,
            ]],
            'trend' => [
                'new_fingerprints' => ['a1'],
            ],
        ]);

        self::assertFalse($result['pass']);
        self::assertNotEmpty($result['reasons']);
    }

    public function testDefaultPolicyPassesWithinThresholds(): void
    {
        $policy = Policy::preset('default');
        $evaluator = new PolicyEvaluator();

        $result = $evaluator->evaluate($policy, [
            'regression' => false,
            'findings' => [[
                'fingerprint' => 'm1',
                'severity' => 'major',
                'invariant_failure' => false,
            ]],
            'trend' => [
                'new_fingerprints' => ['m1'],
            ],
        ]);

        self::assertTrue($result['pass']);
        self::assertSame([], $result['reasons']);
    }
}
