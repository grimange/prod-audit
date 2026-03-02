<?php

declare(strict_types=1);

namespace ProdAudit\Audit\Rules;

final class RuleMetadata
{
    public function __construct(
        public readonly string $id,
        public readonly string $title,
        public readonly string $description,
        public readonly bool $invariant,
    ) {
    }
}
