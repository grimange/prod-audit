<?php

declare(strict_types=1);

namespace ProdAudit\Tests\Unit\Rules;

use PHPUnit\Framework\TestCase;
use ProdAudit\Audit\Collectors\AstCollector;
use ProdAudit\Audit\Collectors\FileCollector;
use ProdAudit\Audit\Collectors\PatternCollector;
use ProdAudit\Audit\Rules\PR_BOUND_002_UnboundedArrayGrowthRule;

final class PR_BOUND_002_Test extends TestCase
{
    public function testUnboundedGrowthIsFlagged(): void
    {
        $rule = new PR_BOUND_002_UnboundedArrayGrowthRule();
        $result = $rule->evaluate($this->collectorDataForFixture('stage5/unbounded_array_in_loop.php'));

        self::assertCount(1, $result->findings);
        self::assertSame('PR-BOUND-002', $result->findings[0]->ruleId);
    }

    public function testBoundedGrowthIsNotFlagged(): void
    {
        $rule = new PR_BOUND_002_UnboundedArrayGrowthRule();
        $result = $rule->evaluate($this->collectorDataForFixture('stage5/bounded_array_in_loop.php'));

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
