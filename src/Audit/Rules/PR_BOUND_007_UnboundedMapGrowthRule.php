<?php

declare(strict_types=1);

namespace ProdAudit\Audit\Rules;

final class PR_BOUND_007_UnboundedMapGrowthRule extends PatternHeuristicRule
{
    public function metadata(): RuleMetadata
    {
        return new RuleMetadata(
            id: 'PR-BOUND-007',
            title: 'Unbounded Map Growth',
            invariant: false,
            category: Category::BOUNDS->value,
            pack: 'bounds',
            defaultSeverity: Severity::Major,
            description: 'Detects map/array append patterns without clear bound management.',
            whyItMatters: 'Unbounded map growth can produce long-tail memory leaks in workers.',
        );
    }

    protected function patternGroup(): string
    {
        return 'stage6_bounds';
    }

    protected function includeRegex(): string
    {
        return '/\b(map|array)\b.*\[|\[\]\s*=|array_merge\(/i';
    }

    protected function excludeRegexes(): array
    {
        return ['/\b(limit|max|array_slice|unset|reset|chunk)\b/i'];
    }

    protected function confidence(): Confidence
    {
        return Confidence::Low;
    }

    protected function recommendation(): string
    {
        return 'Bound map size and evict stale entries.';
    }

    protected function tags(): array
    {
        return ['bounds', 'memory', 'map'];
    }
}
