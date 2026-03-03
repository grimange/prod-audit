<?php

declare(strict_types=1);

namespace ProdAudit\Tests\Unit\Quality;

use PHPUnit\Framework\TestCase;
use ProdAudit\Audit\Plugins\BuiltInPlugin;
use ProdAudit\Audit\Profiles\ProfileRegistry;
use ProdAudit\Audit\Quality\FixtureRunner;
use ProdAudit\Audit\Rules\PackRegistry;
use ProdAudit\Audit\Rules\RuleRegistry;

final class FixtureRunnerTest extends TestCase
{
    public function testSuiteDiscoveryAndExecution(): void
    {
        $runner = new FixtureRunner($this->ruleRegistry());
        $suite = $runner->suiteFor($this->fixturesRoot(), 'PR-OBS-001');

        self::assertCount(2, $suite->goodFiles);
        self::assertCount(2, $suite->badFiles);

        $result = $runner->runSuite($suite);
        self::assertSame('PR-OBS-001', $result['rule_id']);
        self::assertCount(2, $result['good']);
        self::assertCount(2, $result['bad']);
    }

    private function fixturesRoot(): string
    {
        return dirname(__DIR__, 2) . '/Fixtures/quality';
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
