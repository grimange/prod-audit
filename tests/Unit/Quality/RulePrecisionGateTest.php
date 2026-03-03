<?php

declare(strict_types=1);

namespace ProdAudit\Tests\Unit\Quality;

use PHPUnit\Framework\TestCase;
use ProdAudit\Audit\Plugins\BuiltInPlugin;
use ProdAudit\Audit\Profiles\ProfileRegistry;
use ProdAudit\Audit\Quality\FixtureRunner;
use ProdAudit\Audit\Rules\PackRegistry;
use ProdAudit\Audit\Rules\RuleRegistry;

final class RulePrecisionGateTest extends TestCase
{
    /**
     * @return array<int, string>
     */
    private function upgradedRules(): array
    {
        return ['PR-OBS-001', 'PR-ERR-001', 'PR-TIME-001', 'PR-LOCK-001', 'PR-HANG-001'];
    }

    public function testUpgradedRulesMeetFixturePrecisionContract(): void
    {
        $runner = new FixtureRunner($this->ruleRegistry());
        $fixturesRoot = dirname(__DIR__, 2) . '/Fixtures/quality';

        foreach ($this->upgradedRules() as $ruleId) {
            $suite = $runner->suiteFor($fixturesRoot, $ruleId);
            $result = $runner->runSuite($suite);

            foreach ($result['good'] as $row) {
                self::assertSame(0, (int) $row['findings_count'], sprintf('%s good fixture failed: %s', $ruleId, (string) $row['file']));
            }

            foreach ($result['bad'] as $row) {
                self::assertGreaterThanOrEqual(1, (int) $row['findings_count'], sprintf('%s bad fixture missed: %s', $ruleId, (string) $row['file']));
            }
        }
    }

    private function ruleRegistry(): RuleRegistry
    {
        $profiles = new ProfileRegistry();
        $rules = new RuleRegistry();
        $packs = new PackRegistry();
        (new BuiltInPlugin())->register($profiles, $rules, $packs);

        return $rules;
    }
}
