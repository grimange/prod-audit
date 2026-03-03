<?php

declare(strict_types=1);

namespace ProdAudit\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ProdAudit\Audit\Forecast\ForecastEngine;
use ProdAudit\Audit\Insights\InsightEngine;

final class ForecastEngineTest extends TestCase
{
    public function testForecastIsStableAndIncludesDrivers(): void
    {
        $latest = $this->readJson(__DIR__ . '/../Fixtures/insights/latest.json');
        $history = $this->readJsonl(__DIR__ . '/../Fixtures/insights/history.jsonl');
        $labels = [
            'fp-lock-1' => 'true_positive',
            'fp-hang-1' => 'noisy',
            'fp-time-1' => 'false_positive',
            'fp-fixed-old' => 'fixed',
        ];

        $insights = (new InsightEngine())->generate($latest, $history, $labels, [
            'src/Lock.php' => 1.0,
            'src/Worker.php' => 0.8,
            'src/HttpClient.php' => 0.6,
            'src/Logger.php' => 0.2,
        ])->toArray();

        $forecast = (new ForecastEngine())->generate($latest, $history, $insights, $labels)->toArray();

        self::assertGreaterThanOrEqual(0.0, $forecast['risk_new_invariant_fail']);
        self::assertLessThanOrEqual(1.0, $forecast['risk_new_invariant_fail']);
        self::assertGreaterThan(0.0, $forecast['risk_score_drop_5']);
        self::assertArrayHasKey('reliability', $forecast['risk_rule_pack_regression']);
        self::assertNotEmpty($forecast['top_drivers']);
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
