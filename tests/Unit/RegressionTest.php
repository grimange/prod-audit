<?php

declare(strict_types=1);

namespace ProdAudit\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ProdAudit\Audit\Reporting\TrendAnalyzer;

final class RegressionTest extends TestCase
{
    private string $historyPath;

    protected function setUp(): void
    {
        $this->historyPath = sys_get_temp_dir() . '/prod-audit-regression-' . uniqid('', true) . '.jsonl';
    }

    protected function tearDown(): void
    {
        if (is_file($this->historyPath)) {
            unlink($this->historyPath);
        }
    }

    public function testDetectsNewResolvedScoreDeltaAndRegression(): void
    {
        $previous = [
            'score' => 95,
            'findings' => [
                ['fingerprint' => 'fp-a', 'severity' => 'major', 'invariant_failure' => false],
                ['fingerprint' => 'fp-old', 'severity' => 'minor', 'invariant_failure' => false],
            ],
        ];
        file_put_contents($this->historyPath, json_encode($previous) . "\n");

        $current = [
            'score' => 89,
            'findings' => [
                ['fingerprint' => 'fp-a', 'severity' => 'major', 'invariant_failure' => false],
                ['fingerprint' => 'fp-new', 'severity' => 'critical', 'invariant_failure' => true],
            ],
        ];

        $trend = (new TrendAnalyzer())->analyze($this->historyPath, $current);

        self::assertSame(-6, $trend['score_delta']);
        self::assertSame(1, $trend['new_findings']);
        self::assertSame(1, $trend['resolved_findings']);
        self::assertSame(['fp-new'], $trend['new_fingerprints']);
        self::assertSame(['fp-old'], $trend['resolved_fingerprints']);
        self::assertTrue($trend['regression']);
    }
}
