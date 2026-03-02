<?php

declare(strict_types=1);

namespace ProdAudit\Tests\Unit\Rules;

use PHPUnit\Framework\TestCase;
use ProdAudit\Audit\Collectors\AstCollector;
use ProdAudit\Audit\Collectors\FileCollector;
use ProdAudit\Audit\Collectors\PatternCollector;
use ProdAudit\Audit\Rules\Confidence;
use ProdAudit\Audit\Rules\PR_HANG_001_InfiniteLoopRule;

final class PR_HANG_001_Test extends TestCase
{
    public function testBadFixtureProducesFindings(): void
    {
        $rule = new PR_HANG_001_InfiniteLoopRule();
        $result = $rule->evaluate($this->collectorDataForFixture('bad_loops.php'));

        self::assertNotEmpty($result->findings);
        self::assertTrue($result->findings[0]->invariantFailure);
    }

    public function testGoodFixtureProducesNoFindings(): void
    {
        $rule = new PR_HANG_001_InfiniteLoopRule();
        $result = $rule->evaluate($this->collectorDataForFixture('good_loops.php'));

        self::assertCount(0, $result->findings);
    }

    public function testAstInfiniteLoopWithoutGuardsUsesHighConfidence(): void
    {
        $rule = new PR_HANG_001_InfiniteLoopRule();
        $result = $rule->evaluate($this->collectorDataForFixture('ast/infinite_loop_no_yield.php'));

        self::assertCount(1, $result->findings);
        self::assertSame(Confidence::High, $result->findings[0]->confidence);
    }

    public function testAstLoopWithBudgetIsNotFlagged(): void
    {
        $rule = new PR_HANG_001_InfiniteLoopRule();
        $result = $rule->evaluate($this->collectorDataForFixture('ast/infinite_loop_with_budget.php'));

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
