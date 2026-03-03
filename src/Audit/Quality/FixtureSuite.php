<?php

declare(strict_types=1);

namespace ProdAudit\Audit\Quality;

final class FixtureSuite
{
    /**
     * @param array<int, string> $goodFiles
     * @param array<int, string> $badFiles
     */
    public function __construct(
        public readonly string $ruleId,
        public readonly array $goodFiles,
        public readonly array $badFiles,
    ) {
    }
}
