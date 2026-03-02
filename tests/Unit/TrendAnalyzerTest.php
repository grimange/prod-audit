<?php

declare(strict_types=1);

namespace ProdAudit\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ProdAudit\Audit\Reporting\TrendAnalyzer;

final class TrendAnalyzerTest extends TestCase
{
    private string $historyPath;

    protected function setUp(): void
    {
        $this->historyPath = sys_get_temp_dir() . '/prod-audit-trend-' . uniqid('', true) . '.jsonl';
    }

    protected function tearDown(): void
    {
        if (is_file($this->historyPath)) {
            unlink($this->historyPath);
        }
    }

    public function testComputesScoreDeltaAndRepeatedFingerprints(): void
    {
        $previous = [
            'score' => 92,
            'findings' => [
                ['fingerprint' => 'aaa'],
                ['fingerprint' => 'bbb'],
            ],
        ];

        file_put_contents($this->historyPath, json_encode($previous) . "\n");

        $current = [
            'score' => 95,
            'findings' => [
                ['fingerprint' => 'bbb'],
                ['fingerprint' => 'ccc'],
            ],
        ];

        $trend = (new TrendAnalyzer())->analyze($this->historyPath, $current);

        self::assertSame(92, $trend['previous_score']);
        self::assertSame(3, $trend['score_delta']);
        self::assertSame(['bbb'], $trend['repeated_fingerprints']);
        self::assertFalse($trend['stagnation_detected']);
    }

    public function testDetectsStagnationAcrossThreeRuns(): void
    {
        $run = [
            'score' => 90,
            'findings' => [
                ['fingerprint' => 'same-a'],
                ['fingerprint' => 'same-b'],
            ],
        ];

        $content = json_encode($run) . "\n" . json_encode($run) . "\n";
        file_put_contents($this->historyPath, $content);

        $trend = (new TrendAnalyzer())->analyze($this->historyPath, $run);

        self::assertTrue($trend['stagnation_detected']);
    }
}
