<?php

declare(strict_types=1);

namespace ProdAudit\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ProdAudit\Audit\Insights\InsightEngine;

final class InsightEngineTest extends TestCase
{
    public function testNoiseAndPersistentRankingAreDeterministic(): void
    {
        $latest = $this->readJson(__DIR__ . '/../Fixtures/insights/latest.json');
        $history = $this->readJsonl(__DIR__ . '/../Fixtures/insights/history.jsonl');
        $labels = [
            'fp-lock-1' => 'true_positive',
            'fp-hang-1' => 'noisy',
            'fp-time-1' => 'false_positive',
            'fp-obs-1' => 'needs_investigation',
        ];

        $insights = (new InsightEngine())->generate($latest, $history, $labels, [
            'src/Lock.php' => 1.0,
            'src/Worker.php' => 0.8,
            'src/HttpClient.php' => 0.6,
            'src/Logger.php' => 0.2,
        ])->toArray();

        self::assertSame(1.0, $insights['noise_by_rule']['PR-HANG-001']);
        self::assertSame(1.0, $insights['noise_by_rule']['PR-TIME-001']);
        self::assertSame('fp-lock-1', $insights['top_persistent_fingerprints'][0]['fingerprint']);
        self::assertSame('PR-HANG-001', $insights['top_noisy_rules'][0]['rule_id']);
        self::assertNotEmpty($insights['prioritized_findings']);
    }

    /**
     * @return array<string, mixed>
     */
    private function readJson(string $path): array
    {
        $raw = file_get_contents($path);
        self::assertIsString($raw);
        $decoded = json_decode($raw, true);
        self::assertIsArray($decoded);

        return $decoded;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function readJsonl(string $path): array
    {
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        self::assertIsArray($lines);

        $rows = [];
        foreach ($lines as $line) {
            $decoded = json_decode($line, true);
            self::assertIsArray($decoded);
            $rows[] = $decoded;
        }

        return $rows;
    }
}
