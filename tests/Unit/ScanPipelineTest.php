<?php

declare(strict_types=1);

namespace ProdAudit\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ProdAudit\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

final class ScanPipelineTest extends TestCase
{
    private string $outputDirectory;

    protected function setUp(): void
    {
        $this->outputDirectory = sys_get_temp_dir() . '/prod-audit-scan-' . uniqid('', true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->outputDirectory)) {
            $this->removeDirectory($this->outputDirectory);
        }
    }

    public function testScanFixturesProducesRealFindingsInLatestJson(): void
    {
        $application = new Application();
        $command = $application->find('scan');
        $tester = new CommandTester($command);

        $exitCode = $tester->execute([
            'path' => 'tests/Fixtures',
            '--profile' => 'dialer-24x7',
            '--out' => $this->outputDirectory,
        ]);

        self::assertContains($exitCode, [2, 3]);

        $latestJsonPath = $this->outputDirectory . '/latest.json';
        self::assertFileExists($latestJsonPath);

        $report = json_decode((string) file_get_contents($latestJsonPath), true);
        self::assertIsArray($report);
        self::assertLessThan(100, (int) ($report['score'] ?? 100));

        $findings = $report['findings'] ?? [];
        self::assertIsArray($findings);
        self::assertNotEmpty($findings);
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
