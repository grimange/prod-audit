<?php

declare(strict_types=1);

namespace ProdAudit\Audit\Rules;

final class EvidenceRef
{
    public function __construct(
        public readonly string $type,
        public readonly string $file,
        public readonly int $startLine,
        public readonly int $endLine,
        public readonly string $excerpt,
    ) {
    }
}
