<?php

declare(strict_types=1);

namespace ProdAudit\Tests\Unit\Rules;

use PHPUnit\Framework\TestCase;
use ProdAudit\Audit\Collectors\AstCollector;
use ProdAudit\Audit\Collectors\FileCollector;
use ProdAudit\Audit\Collectors\PatternCollector;
use ProdAudit\Audit\Rules\PR_TIME_001_ExternalCallTimeoutRule;

final class PR_TIME_001_Test extends TestCase
{
    public function testNoTimeoutCurlIsFlagged(): void
    {
        $rule = new PR_TIME_001_ExternalCallTimeoutRule();
        $result = $rule->evaluate($this->collectorDataForFixture('stage5/no_timeout_curl.php'));

        self::assertCount(1, $result->findings);
        self::assertSame('PR-TIME-001', $result->findings[0]->ruleId);
    }

    public function testCurlWithTimeoutIsNotFlagged(): void
    {
        $rule = new PR_TIME_001_ExternalCallTimeoutRule();
        $result = $rule->evaluate($this->collectorDataForFixture('stage5/has_timeout_curl.php'));

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
