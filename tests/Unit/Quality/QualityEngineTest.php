<?php

declare(strict_types=1);

namespace ProdAudit\Tests\Unit\Quality;

use PHPUnit\Framework\TestCase;
use ProdAudit\Audit\Quality\QualityEngine;
use ProdAudit\Audit\Rules\Category;
use ProdAudit\Audit\Rules\RuleMetadata;
use ProdAudit\Audit\Rules\Severity;

final class QualityEngineTest extends TestCase
{
    public function testComputesDeterministicQualityMetrics(): void
    {
        $engine = new QualityEngine();
        $fixtures = dirname(__DIR__, 2) . '/Fixtures/quality_data';

        $report = $engine->generate(
            historyPath: $fixtures . '/history.jsonl',
            triagePath: $fixtures . '/triage.jsonl',
            latestPath: $fixtures . '/latest.json',
            ruleMetadataById: $this->ruleMetadata(),
            historyWindow: 20,
        );

        $payload = $report->toArray();
        self::assertArrayHasKey('overall_noise_score', $payload);
        self::assertArrayHasKey('rules', $payload);
        self::assertNotEmpty($payload['rules']);

        $first = $payload['rules'][0];
        self::assertSame('PR-OBS-001', $first['rule_id']);
        self::assertGreaterThan(0.0, (float) $first['noise_score']);

        self::assertNotEmpty($payload['top_noisy_rules']);
        self::assertSame('PR-OBS-001', $payload['top_noisy_rules'][0]['rule_id']);
    }

    /**
     * @return array<string, RuleMetadata>
     */
    private function ruleMetadata(): array
    {
        $ids = ['PR-OBS-001', 'PR-ERR-001', 'PR-TIME-001', 'PR-LOCK-001', 'PR-HANG-001'];
        $result = [];
        foreach ($ids as $id) {
            $result[$id] = new RuleMetadata(
                id: $id,
                title: $id,
                invariant: str_starts_with($id, 'PR-LOCK') || str_starts_with($id, 'PR-HANG'),
                category: Category::TIMEOUTS->value,
                pack: 'reliability',
                defaultSeverity: Severity::Major,
                description: '',
                whyItMatters: ''
            );
        }

        ksort($result, SORT_STRING);

        return $result;
    }
}
