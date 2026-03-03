<?php

declare(strict_types=1);

namespace ProdAudit\Audit\Rules;

final class PR_BOUND_003_UnboundedRedisKeyGrowthRule extends PatternHeuristicRule
{
    public function metadata(): RuleMetadata
    {
        return new RuleMetadata(
            id: 'PR-BOUND-003',
            title: 'Unbounded Redis Key Growth',
            invariant: false,
            category: Category::BOUNDS->value,
            pack: 'bounds',
            defaultSeverity: Severity::Major,
            description: 'Detects Redis write patterns without expiry controls.',
            whyItMatters: 'Unbounded key growth increases memory pressure and eviction churn.',
        );
    }

    protected function patternGroup(): string
    {
        return 'stage6_bounds';
    }

    protected function includeRegex(): string
    {
        return '/\b(redis|set|hset|lpush|rpush)\b/i';
    }

    protected function excludeRegexes(): array
    {
        return ['/\b(expire|ttl|evict|trim|maxlen)\b/i'];
    }

    protected function confidence(): Confidence
    {
        return Confidence::Low;
    }

    protected function recommendation(): string
    {
        return 'Apply TTL or bounded key/stream trimming policies.';
    }

    protected function tags(): array
    {
        return ['bounds', 'redis', 'memory'];
    }
}
