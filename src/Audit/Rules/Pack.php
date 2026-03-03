<?php

declare(strict_types=1);

namespace ProdAudit\Audit\Rules;

final class Pack
{
    /**
     * @param array<int, string> $ruleIds
     */
    public function __construct(
        public readonly string $name,
        public readonly string $description,
        public readonly array $ruleIds,
        public readonly bool $defaultEnabled,
    ) {
    }

    /**
     * @return array<int, string>
     */
    public function sortedRuleIds(): array
    {
        $ids = $this->ruleIds;
        sort($ids, SORT_STRING);

        return $ids;
    }
}
