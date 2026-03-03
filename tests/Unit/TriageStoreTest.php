<?php

declare(strict_types=1);

namespace ProdAudit\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ProdAudit\Audit\Triage\TriageEvent;
use ProdAudit\Audit\Triage\TriageStore;

final class TriageStoreTest extends TestCase
{
    private string $out;

    protected function setUp(): void
    {
        $this->out = sys_get_temp_dir() . '/prod-audit-triage-' . uniqid('', true);
        mkdir($this->out, 0777, true);

        file_put_contents($this->out . '/latest.json', json_encode([
            'findings' => [
                ['fingerprint' => 'fp-a', 'rule_id' => 'PR-LOCK-001'],
            ],
        ]));
    }

    protected function tearDown(): void
    {
        if (is_file($this->out . '/triage.jsonl')) {
            unlink($this->out . '/triage.jsonl');
        }
        if (is_file($this->out . '/latest.json')) {
            unlink($this->out . '/latest.json');
        }
        if (is_dir($this->out)) {
            rmdir($this->out);
        }
    }

    public function testAppendReadAndDeterministicOrdering(): void
    {
        $store = new TriageStore();
        $store->append($this->out, new TriageEvent('2026-03-03T00:00:00+00:00', 'fp-a', 'PR-LOCK-001', 'true_positive'));
        $store->append($this->out, new TriageEvent('2026-03-03T00:01:00+00:00', 'fp-b', 'PR-HANG-001', 'needs_investigation'));

        $rows = $store->listEffective($this->out);

        self::assertCount(2, $rows);
        self::assertSame('PR-HANG-001', $rows[0]['rule_id']);
        self::assertSame('PR-LOCK-001', $rows[1]['rule_id']);
    }

    public function testEffectiveLabelUsesLatestLine(): void
    {
        $store = new TriageStore();
        $store->append($this->out, new TriageEvent('2026-03-03T00:00:00+00:00', 'fp-a', 'PR-LOCK-001', 'needs_investigation'));
        $store->append($this->out, new TriageEvent('2026-03-03T00:00:00+00:00', 'fp-a', 'PR-LOCK-001', 'fixed'));

        self::assertSame('fixed', $store->effectiveLabel($this->out, 'fp-a'));
    }
}
