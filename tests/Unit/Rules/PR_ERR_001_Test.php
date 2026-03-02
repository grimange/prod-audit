<?php

declare(strict_types=1);

namespace ProdAudit\Tests\Unit\Rules;

use PHPUnit\Framework\TestCase;
use ProdAudit\Audit\Collectors\AstCollector;
use ProdAudit\Audit\Collectors\FileCollector;
use ProdAudit\Audit\Collectors\PatternCollector;
use ProdAudit\Audit\Rules\Confidence;
use ProdAudit\Audit\Rules\PR_ERR_001_SwallowedExceptionsRule;

final class PR_ERR_001_Test extends TestCase
{
    public function testBadFixtureProducesFindings(): void
    {
        $rule = new PR_ERR_001_SwallowedExceptionsRule();
        $result = $rule->evaluate($this->collectorDataForFixture('bad_exceptions.php'));

        self::assertNotEmpty($result->findings);
        self::assertSame('PR-ERR-001', $result->findings[0]->ruleId);
    }

    public function testGoodFixtureProducesNoFindings(): void
    {
        $rule = new PR_ERR_001_SwallowedExceptionsRule();
        $result = $rule->evaluate($this->collectorDataForFixture('good_exceptions.php'));

        self::assertCount(0, $result->findings);
    }

    public function testAstConfirmedCatchUsesHighConfidence(): void
    {
        $rule = new PR_ERR_001_SwallowedExceptionsRule();
        $result = $rule->evaluate($this->collectorDataForFixture('ast/swallowed_empty_catch.php'));

        self::assertCount(1, $result->findings);
        self::assertSame(Confidence::High, $result->findings[0]->confidence);
        self::assertSame('ast_node', $result->findings[0]->evidence[0]->type);
    }

    public function testAstLoggedCatchIsNotFlagged(): void
    {
        $rule = new PR_ERR_001_SwallowedExceptionsRule();
        $result = $rule->evaluate($this->collectorDataForFixture('ast/catch_logs.php'));

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
