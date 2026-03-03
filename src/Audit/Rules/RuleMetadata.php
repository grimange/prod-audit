<?php

declare(strict_types=1);

namespace ProdAudit\Audit\Rules;

final class RuleMetadata
{
    public function __construct(
        public readonly string $id,
        public readonly string $title,
        public readonly bool $invariant,
        public readonly string $category = 'config',
        public readonly string $pack = 'reliability',
        public readonly Severity $defaultSeverity = Severity::Info,
        public readonly string $description = '',
        public readonly string $whyItMatters = '',
    ) {
    }
}
