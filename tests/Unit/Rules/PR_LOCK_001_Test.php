<?php

declare(strict_types=1);

namespace ProdAudit\Tests\Unit\Rules;

use PHPUnit\Framework\TestCase;
use ProdAudit\Audit\Collectors\AstCollector;
use ProdAudit\Audit\Collectors\FileCollector;
use ProdAudit\Audit\Collectors\PatternCollector;
use ProdAudit\Audit\Rules\Confidence;
use ProdAudit\Audit\Rules\PR_LOCK_001_LockRenewRule;

final class PR_LOCK_001_Test extends TestCase
{
    public function testBadFixtureProducesFindings(): void
    {
        $rule = new PR_LOCK_001_LockRenewRule();
        $result = $rule->evaluate($this->collectorDataForFixture('bad_lock.php'));

        self::assertNotEmpty($result->findings);
        self::assertTrue($result->findings[0]->invariantFailure);
    }

    public function testGoodFixtureProducesNoFindings(): void
    {
        $rule = new PR_LOCK_001_LockRenewRule();
        $result = $rule->evaluate($this->collectorDataForFixture('good_lock.php'));

        self::assertCount(0, $result->findings);
    }

    public function testAstExpireWithoutEvalUsesMediumConfidence(): void
    {
        $rule = new PR_LOCK_001_LockRenewRule();
        $result = $rule->evaluate($this->collectorDataForFixture('ast/redis_expire_no_eval.php'));

        self::assertCount(1, $result->findings);
        self::assertSame(Confidence::Medium, $result->findings[0]->confidence);
    }

    public function testAstEvalWithLuaIsNotFlagged(): void
    {
        $rule = new PR_LOCK_001_LockRenewRule();
        $result = $rule->evaluate($this->collectorDataForFixture('ast/redis_eval_with_lua.php'));

        self::assertCount(0, $result->findings);
    }

    /**
     * @return array<string, mixed>
     */
    private function collectorDataForFixture(string $fixtureName): array
    {
        $path = $this->fixturePath($fixtureName);
        $files = [[
            'path' => $path,
            'relative_path' => $fixtureName,
            'extension' => 'php',
            'size' => filesize($path) ?: 0,
        ]];

        $fileCollector = new FileCollector();

        return [
            'patterns' => (new PatternCollector())->collect($files),
            'ast' => (new AstCollector())->collect($files, $fileCollector),
        ];
    }

    private function fixturePath(string $fixtureName): string
    {
        return dirname(__DIR__, 2) . '/Fixtures/' . $fixtureName;
    }
}
