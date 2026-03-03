<?php

declare(strict_types=1);

namespace ProdAudit\Tests\Unit\Quality;

use PHPUnit\Framework\TestCase;
use ProdAudit\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

final class NoiseReductionRegressionTest extends TestCase
{
    private string $outputDirectory;

    protected function setUp(): void
    {
        $this->outputDirectory = sys_get_temp_dir() . '/prod-audit-quality-regression-' . uniqid('', true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->outputDirectory)) {
            $this->removeDirectory($this->outputDirectory);
        }
    }

    public function testScanRetainsBadSignalsAndSuppressesKnownGoodPatterns(): void
    {
        $application = new Application();
        $command = $application->find('scan');
        $tester = new CommandTester($command);

        $tester->execute([
            'path' => 'tests/Fixtures/quality',
            '--profile' => 'dialer-24x7',
            '--out' => $this->outputDirectory,
        ]);

        $report = json_decode((string) file_get_contents($this->outputDirectory . '/latest.json'), true);
        self::assertIsArray($report);
        $findings = is_array($report['findings'] ?? null) ? $report['findings'] : [];

        self::assertTrue($this->hasFindingForFile($findings, 'tests/Fixtures/quality/pr-err-001/bad_empty_catch.php', 'PR-ERR-001'));
        self::assertTrue($this->hasFindingForFile($findings, 'tests/Fixtures/quality/pr-time-001/bad_request_no_timeout.php', 'PR-TIME-001'));

        self::assertFalse($this->hasFindingForFile($findings, 'tests/Fixtures/quality/pr-err-001/good_intentional_marker.php', 'PR-ERR-001'));
        self::assertFalse($this->hasFindingForFile($findings, 'tests/Fixtures/quality/pr-time-001/good_shared_timeout_options.php', 'PR-TIME-001'));
    }

    /**
     * @param array<int, array<string, mixed>> $findings
     */
    private function hasFindingForFile(array $findings, string $file, ?string $ruleId = null): bool
    {
        foreach ($findings as $finding) {
            if (!is_array($finding)) {
                continue;
            }
            if ($ruleId !== null && (string) ($finding['rule_id'] ?? '') !== $ruleId) {
                continue;
            }

            $evidence = is_array($finding['evidence'] ?? null) ? $finding['evidence'] : [];
            foreach ($evidence as $row) {
                if (!is_array($row)) {
                    continue;
                }

                $evidenceFile = (string) ($row['file'] ?? '');
                $shortFile = str_replace('tests/Fixtures/quality/', '', $file);
                if ($evidenceFile === $file || str_ends_with($evidenceFile, $shortFile)) {
                    return true;
                }
            }
        }

        return false;
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
