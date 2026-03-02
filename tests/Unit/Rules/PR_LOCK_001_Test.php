<?php

declare(strict_types=1);

namespace ProdAudit\Tests\Unit\Rules;

use PHPUnit\Framework\TestCase;
use ProdAudit\Audit\Collectors\PatternCollector;
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

        return [
            'patterns' => (new PatternCollector())->collect($files),
        ];
    }

    private function fixturePath(string $fixtureName): string
    {
        return dirname(__DIR__, 2) . '/Fixtures/' . $fixtureName;
    }
}
