<?php

declare(strict_types=1);

namespace ProdAudit\Audit\Reporting;

use RuntimeException;

final class JsonReportWriter
{
    /**
     * @param array<string, mixed> $report
     */
    public function write(string $outputDirectory, array $report): void
    {
        $path = rtrim($outputDirectory, '/') . '/latest.json';

        $encoded = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($encoded === false) {
            throw new RuntimeException('Unable to encode JSON report.');
        }

        if (file_put_contents($path, $encoded . "\n") === false) {
            throw new RuntimeException('Unable to write JSON report.');
        }
    }
}
