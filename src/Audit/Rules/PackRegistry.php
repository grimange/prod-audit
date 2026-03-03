<?php

declare(strict_types=1);

namespace ProdAudit\Audit\Rules;

use InvalidArgumentException;

final class PackRegistry
{
    /**
     * @var array<string, Pack>
     */
    private array $packs = [];

    public function register(Pack $pack): void
    {
        $this->packs[$pack->name] = new Pack(
            name: $pack->name,
            description: $pack->description,
            ruleIds: $pack->sortedRuleIds(),
            defaultEnabled: $pack->defaultEnabled,
        );

        ksort($this->packs, SORT_STRING);
    }

    public function get(string $name): Pack
    {
        if (!isset($this->packs[$name])) {
            throw new InvalidArgumentException(sprintf('Unknown rule pack "%s".', $name));
        }

        return $this->packs[$name];
    }

    /**
     * @return array<int, string>
     */
    public function names(): array
    {
        return array_keys($this->packs);
    }
}
