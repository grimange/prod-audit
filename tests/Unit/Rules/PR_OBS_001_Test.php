<?php

declare(strict_types=1);

namespace ProdAudit\Tests\Unit\Rules;

use PHPUnit\Framework\TestCase;
use ProdAudit\Audit\Collectors\AstCollector;
use ProdAudit\Audit\Collectors\FileCollector;
use ProdAudit\Audit\Collectors\PatternCollector;
use ProdAudit\Audit\Rules\PR_OBS_001_MissingLoggerContextRule;

final class PR_OBS_001_Test extends TestCase
{
    public function testLoggerWithoutContextIsFlagged(): void
    {
        $rule = new PR_OBS_001_MissingLoggerContextRule();
        $result = $rule->evaluate($this->collectorDataForFixture('stage5/logger_no_context.php'));

        self::assertCount(1, $result->findings);
        self::assertSame('PR-OBS-001', $result->findings[0]->ruleId);
    }

    public function testLoggerWithContextIsNotFlagged(): void
    {
        $rule = new PR_OBS_001_MissingLoggerContextRule();
        $result = $rule->evaluate($this->collectorDataForFixture('stage5/logger_with_context.php'));

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
