<?php

declare(strict_types=1);

namespace ProdAudit\Audit\Rules;

final class PR_BOUND_004_CacheWithoutEvictionRule extends PatternHeuristicRule
{
    public function metadata(): RuleMetadata
    {
        return new RuleMetadata(
            id: 'PR-BOUND-004',
            title: 'Cache Without Eviction',
            invariant: false,
            category: Category::BOUNDS->value,
            pack: 'bounds',
            defaultSeverity: Severity::Major,
            description: 'Detects cache writes that do not provide eviction/TTL hints.',
            whyItMatters: 'Unbounded caches degrade memory locality and can trigger OOM events.',
        );
    }

    protected function patternGroup(): string
    {
        return 'stage6_bounds';
    }

    protected function includeRegex(): string
    {
        return '/\b(cache|remember|put|set)\b/i';
    }

    protected function excludeRegexes(): array
    {
        return ['/\b(ttl|expire|evict|forget|max)\b/i'];
    }

    protected function confidence(): Confidence
    {
        return Confidence::Low;
    }

    protected function recommendation(): string
    {
        return 'Enforce TTL and bounded cache cardinality policies.';
    }

    protected function tags(): array
    {
        return ['bounds', 'cache', 'memory'];
    }
}
