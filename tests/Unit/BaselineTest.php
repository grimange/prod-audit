<?php

declare(strict_types=1);

namespace ProdAudit\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ProdAudit\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

final class BaselineTest extends TestCase
{
    private string $outputDirectory;
    private string $baselinePath;

    protected function setUp(): void
    {
        $suffix = uniqid('', true);
        $this->outputDirectory = sys_get_temp_dir() . '/prod-audit-baseline-scan-' . $suffix;
        $this->baselinePath = sys_get_temp_dir() . '/prod-audit-baseline-' . $suffix . '.json';
    }

    protected function tearDown(): void
    {
        if (is_file($this->baselinePath)) {
            unlink($this->baselinePath);
        }

        if (is_dir($this->outputDirectory)) {
            $this->removeDirectory($this->outputDirectory);
        }
    }

    public function testBaselineCreationAndFilteringWorks(): void
    {
        $application = new Application();

        $scanCommand = $application->find('scan');
        $scanTester = new CommandTester($scanCommand);
        $scanExit = $scanTester->execute([
            'path' => 'tests/Fixtures',
            '--profile' => 'dialer-24x7',
            '--out' => $this->outputDirectory,
        ]);
        self::assertContains($scanExit, [2, 3]);

        $initialReport = json_decode((string) file_get_contents($this->outputDirectory . '/latest.json'), true);
        self::assertIsArray($initialReport);
        $initialFindings = $initialReport['findings'] ?? [];
        self::assertIsArray($initialFindings);
        self::assertNotEmpty($initialFindings);

        $baselineCommand = $application->find('baseline');
        $baselineTester = new CommandTester($baselineCommand);
        $baselineExit = $baselineTester->execute([
            'path' => 'tests/Fixtures',
            '--profile' => 'dialer-24x7',
            '--file' => $this->baselinePath,
        ]);

        self::assertSame(0, $baselineExit);
        self::assertFileExists($this->baselinePath);

        $baseline = json_decode((string) file_get_contents($this->baselinePath), true);
        self::assertIsArray($baseline);
        self::assertSame('dialer-24x7', $baseline['profile']);
        self::assertSame(95, $baseline['target_score']);
        self::assertCount(count($initialFindings), $baseline['accepted_findings']);

        $filteredExit = $scanTester->execute([
            'path' => 'tests/Fixtures',
            '--profile' => 'dialer-24x7',
            '--out' => $this->outputDirectory,
            '--baseline' => $this->baselinePath,
        ]);

        self::assertSame(0, $filteredExit);

        $filteredReport = json_decode((string) file_get_contents($this->outputDirectory . '/latest.json'), true);
        self::assertIsArray($filteredReport);
        self::assertSame(100, $filteredReport['score']);
        self::assertSame(0, $filteredReport['invariant_failures']);
        self::assertSame([], $filteredReport['findings']);
        self::assertNotEmpty($filteredReport['baseline']);
    }

    public function testExpiredBaselineEntriesReactivateFindings(): void
    {
        $application = new Application();
        $scanCommand = $application->find('scan');
        $scanTester = new CommandTester($scanCommand);

        $scanTester->execute([
            'path' => 'tests/Fixtures',
            '--profile' => 'dialer-24x7',
            '--out' => $this->outputDirectory,
        ]);

        $initialReport = json_decode((string) file_get_contents($this->outputDirectory . '/latest.json'), true);
        self::assertIsArray($initialReport);
        $initialFindings = $initialReport['findings'] ?? [];
        self::assertIsArray($initialFindings);
        self::assertNotEmpty($initialFindings);

        $firstFinding = $initialFindings[0];
        self::assertIsArray($firstFinding);

        $expiredBaseline = [
            'profile' => 'dialer-24x7',
            'created_at' => gmdate(DATE_ATOM),
            'target_score' => 95,
            'accepted_findings' => [
                [
                    'fingerprint' => (string) ($firstFinding['fingerprint'] ?? ''),
                    'rule' => (string) ($firstFinding['rule_id'] ?? ''),
                    'justification' => 'expired test entry',
                    'expires' => '2000-01-01T00:00:00+00:00',
                ],
            ],
        ];

        file_put_contents($this->baselinePath, json_encode($expiredBaseline, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");

        $scanTester->execute([
            'path' => 'tests/Fixtures',
            '--profile' => 'dialer-24x7',
            '--out' => $this->outputDirectory,
            '--baseline' => $this->baselinePath,
        ]);

        $report = json_decode((string) file_get_contents($this->outputDirectory . '/latest.json'), true);
        self::assertIsArray($report);
        $activeFingerprints = array_map(
            static fn (array $finding): string => (string) ($finding['fingerprint'] ?? ''),
            $report['findings'] ?? []
        );

        self::assertContains((string) ($firstFinding['fingerprint'] ?? ''), $activeFingerprints);
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
