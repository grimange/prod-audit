<?php

declare(strict_types=1);

namespace ProdAudit\Audit\Rules;

final class RuleRegistry
{
    /**
     * @var array<string, RuleInterface>
     */
    private array $rules = [];

    public function register(RuleInterface $rule): void
    {
        $this->rules[$rule->metadata()->id] = $rule;
        ksort($this->rules, SORT_STRING);
    }

    public function has(string $ruleId): bool
    {
        return isset($this->rules[$ruleId]);
    }

    public function get(string $ruleId): ?RuleInterface
    {
        return $this->rules[$ruleId] ?? null;
    }

    /**
     * @return array<int, string>
     */
    public function ids(): array
    {
        return array_keys($this->rules);
    }
}
