<?php

declare(strict_types=1);

namespace ProdAudit\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ProdAudit\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

final class ScanWithInsightsTest extends TestCase
{
    private string $outputDirectory;

    protected function setUp(): void
    {
        $this->outputDirectory = sys_get_temp_dir() . '/prod-audit-stage7-scan-' . uniqid('', true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->outputDirectory)) {
            $this->removeDirectory($this->outputDirectory);
        }
    }

    public function testScanAddsInsightsForecastAndActionsWithoutBreakingExistingFields(): void
    {
        $application = new Application();
        $command = $application->find('scan');
        $tester = new CommandTester($command);

        $exitCode = $tester->execute([
            'path' => 'tests/Fixtures',
            '--profile' => 'dialer-24x7',
            '--out' => $this->outputDirectory,
        ]);

        self::assertContains($exitCode, [2, 3, 5, 6]);

        $report = json_decode((string) file_get_contents($this->outputDirectory . '/latest.json'), true);
        self::assertIsArray($report);

        self::assertArrayHasKey('insights', $report);
        self::assertArrayHasKey('forecast', $report);
        self::assertArrayHasKey('actions', $report);
        self::assertArrayHasKey('policy_result', $report);
        self::assertArrayHasKey('tasks', $report);
    }

    private function removeDirectory(string $path): void
    {
        $items = scandir($path);
        if (!is_array($items)) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $fullPath = $path . '/' . $item;
            if (is_dir($fullPath)) {
                $this->removeDirectory($fullPath);
                continue;
            }

            unlink($fullPath);
        }

        rmdir($path);
    }
}
