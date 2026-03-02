<?php

declare(strict_types=1);

namespace ProdAudit\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ProdAudit\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

final class SuppressionTest extends TestCase
{
    private string $outputDirectory;
    private string $suppressionsPath;

    protected function setUp(): void
    {
        $suffix = uniqid('', true);
        $this->outputDirectory = sys_get_temp_dir() . '/prod-audit-suppression-scan-' . $suffix;
        $this->suppressionsPath = sys_get_temp_dir() . '/prod-audit-suppressions-' . $suffix . '.json';
    }

    protected function tearDown(): void
    {
        if (is_file($this->suppressionsPath)) {
            unlink($this->suppressionsPath);
        }

        if (is_dir($this->outputDirectory)) {
            $this->removeDirectory($this->outputDirectory);
        }
    }

    public function testSuppressionRemovesFindingsFromActiveResults(): void
    {
        $this->writeSuppressionsFile(null);

        $application = new Application();
        $scanCommand = $application->find('scan');
        $scanTester = new CommandTester($scanCommand);

        $scanTester->execute([
            'path' => 'tests/Fixtures',
            '--profile' => 'dialer-24x7',
            '--out' => $this->outputDirectory,
            '--suppressions' => $this->suppressionsPath,
        ]);

        $report = json_decode((string) file_get_contents($this->outputDirectory . '/latest.json'), true);
        self::assertIsArray($report);

        $activeRules = array_map(
            static fn (array $finding): string => (string) ($finding['rule_id'] ?? ''),
            $report['findings'] ?? []
        );

        self::assertNotContains('PR-ERR-001', $activeRules);
        self::assertNotEmpty($report['suppressed']);
    }

    public function testExpiredSuppressionReactivatesFindings(): void
    {
        $this->writeSuppressionsFile('2000-01-01T00:00:00+00:00');

        $application = new Application();
        $scanCommand = $application->find('scan');
        $scanTester = new CommandTester($scanCommand);

        $scanTester->execute([
            'path' => 'tests/Fixtures',
            '--profile' => 'dialer-24x7',
            '--out' => $this->outputDirectory,
            '--suppressions' => $this->suppressionsPath,
        ]);

        $report = json_decode((string) file_get_contents($this->outputDirectory . '/latest.json'), true);
        self::assertIsArray($report);

        $activeRules = array_map(
            static fn (array $finding): string => (string) ($finding['rule_id'] ?? ''),
            $report['findings'] ?? []
        );

        self::assertContains('PR-ERR-001', $activeRules);
    }

    private function writeSuppressionsFile(?string $expires): void
    {
        $payload = [
            'suppressions' => [
                [
                    'rule' => 'PR-ERR-001',
                    'path' => '*',
                    'justification' => 'test fixtures',
                    'expires' => $expires,
                ],
            ],
        ];

        file_put_contents(
            $this->suppressionsPath,
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
        );
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
