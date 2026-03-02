<?php

declare(strict_types=1);

namespace ProdAudit\Audit\Rules;

interface RuleInterface
{
    public function metadata(): RuleMetadata;

    /**
     * @param array<string, mixed> $collectorData
     */
    public function evaluate(array $collectorData): RuleResult;
}
