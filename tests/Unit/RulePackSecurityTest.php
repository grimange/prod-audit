<?php

declare(strict_types=1);

namespace ProdAudit\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ProdAudit\Audit\Collectors\AstCollector;
use ProdAudit\Audit\Collectors\FileCollector;
use ProdAudit\Audit\Collectors\PatternCollector;
use ProdAudit\Audit\Rules\PR_SEC_002_Md5Sha1UsageRule;
use ProdAudit\Audit\Rules\PR_SEC_003_ShellExecUsageRule;
use ProdAudit\Audit\Rules\RuleInterface;

final class RulePackSecurityTest extends TestCase
{
    public function testSecurityPackDetectsFindingsOnBadFixture(): void
    {
        $findings = $this->evaluateRules('stage6/security_bad.php', [
            new PR_SEC_002_Md5Sha1UsageRule(),
            new PR_SEC_003_ShellExecUsageRule(),
        ]);

        self::assertNotEmpty($findings);
    }

    public function testSecurityPackIsCleanOnGoodFixture(): void
    {
        $findings = $this->evaluateRules('stage6/security_good.php', [
            new PR_SEC_002_Md5Sha1UsageRule(),
            new PR_SEC_003_ShellExecUsageRule(),
        ]);

        self::assertSame([], $findings);
    }

    /**
     * @param array<int, RuleInterface> $rules
     * @return array<int, mixed>
     */
    private function evaluateRules(string $fixtureName, array $rules): array
    {
        $collectorData = $this->collectorDataForFixture($fixtureName);
        $findings = [];
        foreach ($rules as $rule) {
            array_push($findings, ...$rule->evaluate($collectorData)->findings);
        }

        return $findings;
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
            'files' => $files,
            'patterns' => (new PatternCollector())->collect($files),
            'ast' => (new AstCollector())->collect($files, $fileCollector),
        ];
    }

    private function fixturePath(string $fixtureName): string
    {
        return dirname(__DIR__) . '/Fixtures/' . $fixtureName;
    }
}
