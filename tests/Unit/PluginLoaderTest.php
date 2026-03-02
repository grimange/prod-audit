<?php

declare(strict_types=1);

namespace ProdAudit\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ProdAudit\Audit\Plugins\PluginLoader;
use ProdAudit\Audit\Profiles\ProfileRegistry;
use ProdAudit\Audit\Rules\RuleRegistry;

final class PluginLoaderTest extends TestCase
{
    public function testLoadsBuiltInPluginDeterministically(): void
    {
        $loader = new PluginLoader();
        $profiles = new ProfileRegistry();
        $rules = new RuleRegistry();

        $first = $loader->load(__DIR__, $profiles, $rules);
        $second = $loader->load(__DIR__, $profiles, $rules);

        self::assertSame($first, $second);
        self::assertContains('built-in', $first['loaded']);
        self::assertTrue($rules->has('PR-ERR-001'));
        self::assertTrue($rules->has('PR-HANG-001'));
        self::assertTrue($rules->has('PR-LOCK-001'));
        self::assertSame('dialer-24x7', $profiles->get('dialer-24x7')->name());
    }
}
