<?php

declare(strict_types=1);

namespace ProdAudit\Audit\Rules;

final class RuleResult
{
    /**
     * @param array<int, Finding> $findings
     */
    public function __construct(
        public readonly RuleMetadata $metadata,
        public readonly array $findings,
    ) {
    }
}
