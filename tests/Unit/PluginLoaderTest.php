<?php

declare(strict_types=1);

namespace ProdAudit\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ProdAudit\Audit\Plugins\PluginLoader;
use ProdAudit\Audit\Profiles\ProfileRegistry;
use ProdAudit\Audit\Rules\PackRegistry;
use ProdAudit\Audit\Rules\RuleRegistry;

final class PluginLoaderTest extends TestCase
{
    public function testLoadsBuiltInPluginDeterministically(): void
    {
        $loader = new PluginLoader();
        $profiles = new ProfileRegistry();
        $rules = new RuleRegistry();
        $packs = new PackRegistry();

        $first = $loader->load(__DIR__, $profiles, $rules, $packs);
        $second = $loader->load(__DIR__, $profiles, $rules, $packs);

        self::assertSame($first, $second);
        self::assertContains('built-in', $first['loaded']);
        self::assertTrue($rules->has('PR-ERR-001'));
        self::assertTrue($rules->has('PR-HANG-001'));
        self::assertTrue($rules->has('PR-LOCK-001'));
        self::assertTrue($rules->has('PR-TIME-001'));
        self::assertTrue($rules->has('PR-BOUND-002'));
        self::assertTrue($rules->has('PR-OBS-001'));
        self::assertTrue($rules->has('PR-SEC-005'));
        self::assertCount(60, $rules->ids());
        self::assertSame(
            ['bounds', 'config-safety', 'dependency', 'documentation', 'error-handling', 'observability', 'reliability', 'security-baseline', 'timeout'],
            $packs->names()
        );
        self::assertSame('dialer-24x7', $profiles->get('dialer-24x7')->name());
    }
}
