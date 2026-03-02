<?php

declare(strict_types=1);

namespace ProdAudit\Audit\Reporting;

use RuntimeException;

final class HistoryWriter
{
    /**
     * @param array<string, mixed> $report
     */
    public function append(string $outputDirectory, array $report): void
    {
        $path = rtrim($outputDirectory, '/') . '/history.jsonl';

        $encoded = json_encode($report, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($encoded === false) {
            throw new RuntimeException('Unable to encode history entry.');
        }

        if (file_put_contents($path, $encoded . "\n", FILE_APPEND) === false) {
            throw new RuntimeException('Unable to append history entry.');
        }
    }
}
