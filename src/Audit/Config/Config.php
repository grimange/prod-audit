<?php

declare(strict_types=1);

namespace ProdAudit\Audit\Config;

final class Config
{
    /**
     * @param array<string, mixed> $values
     */
    public function __construct(
        private readonly array $values = [],
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function values(): array
    {
        return $this->values;
    }

    /**
     * @return array<int, string>
     */
    public function ignoredDirectories(): array
    {
        $ignored = $this->values['ignored_directories'] ?? [];
        if (!is_array($ignored)) {
            return [];
        }

        $result = [];
        foreach ($ignored as $entry) {
            if (!is_string($entry) || trim($entry) === '') {
                continue;
            }

            $result[] = trim($entry);
        }

        $result = array_values(array_unique($result));
        sort($result, SORT_STRING);

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    public function rule(string $ruleId): array
    {
        $rules = $this->values['rule_config'] ?? [];
        if (!is_array($rules)) {
            return [];
        }

        $rule = $rules[$ruleId] ?? [];

        return is_array($rule) ? $rule : [];
    }

    /**
     * @param array<int, string> $default
     * @return array<int, string>
     */
    public function stringList(string $ruleId, string $key, array $default = []): array
    {
        $value = $this->rule($ruleId)[$key] ?? null;
        if (!is_array($value)) {
            return $default;
        }

        $result = [];
        foreach ($value as $entry) {
            if (!is_string($entry) || trim($entry) === '') {
                continue;
            }

            $result[] = strtolower(trim($entry));
        }

        $result = array_values(array_unique($result));
        sort($result, SORT_STRING);

        return $result;
    }

    public function bool(string $ruleId, string $key, bool $default = false): bool
    {
        $value = $this->rule($ruleId)[$key] ?? null;
        if (is_bool($value)) {
            return $value;
        }

        return $default;
    }
}
