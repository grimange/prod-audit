<?php

declare(strict_types=1);

namespace ProdAudit\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ProdAudit\Audit\Export\CheckstyleExporter;
use ProdAudit\Audit\Export\SarifExporter;

final class ExportersTest extends TestCase
{
    private string $outputDirectory;

    protected function setUp(): void
    {
        $this->outputDirectory = sys_get_temp_dir() . '/prod-audit-export-' . uniqid('', true);
        mkdir($this->outputDirectory, 0777, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->outputDirectory)) {
            $this->removeDirectory($this->outputDirectory);
        }
    }

    public function testSarifAndCheckstyleAreDeterministic(): void
    {
        $report = [
            'tool_version' => '0.5.0-stage5',
            'findings' => [[
                'rule_id' => 'PR-ERR-001',
                'severity' => 'major',
                'message' => 'Exception caught but not handled',
                'fingerprint' => 'fp-1',
                'evidence' => [[
                    'file' => 'tests/Fixtures/bad_exceptions.php',
                    'line_start' => 3,
                ]],
            ]],
            'suppressed' => [],
            'baseline' => [],
        ];

        $sarif = new SarifExporter();
        $checkstyle = new CheckstyleExporter();

        $sarifPath1 = $sarif->write($this->outputDirectory, $report);
        $checkPath1 = $checkstyle->write($this->outputDirectory, $report);
        $firstSarif = (string) file_get_contents($sarifPath1);
        $firstCheck = (string) file_get_contents($checkPath1);

        $sarifPath2 = $sarif->write($this->outputDirectory, $report);
        $checkPath2 = $checkstyle->write($this->outputDirectory, $report);
        $secondSarif = (string) file_get_contents($sarifPath2);
        $secondCheck = (string) file_get_contents($checkPath2);

        self::assertSame($firstSarif, $secondSarif);
        self::assertSame($firstCheck, $secondCheck);
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
