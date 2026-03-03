<?php

declare(strict_types=1);

namespace ProdAudit\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ProdAudit\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

final class DocsCheckTest extends TestCase
{
    public function testDocsCheckPassesForRegisteredRules(): void
    {
        $application = new Application();
        $command = $application->find('docs-check');
        $tester = new CommandTester($command);

        $exitCode = $tester->execute([]);

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('Docs parity check passed.', $tester->getDisplay());
    }
}
