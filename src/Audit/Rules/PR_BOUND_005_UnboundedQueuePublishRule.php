<?php

declare(strict_types=1);

namespace ProdAudit\Audit\Rules;

final class PR_BOUND_005_UnboundedQueuePublishRule extends PatternHeuristicRule
{
    public function metadata(): RuleMetadata
    {
        return new RuleMetadata(
            id: 'PR-BOUND-005',
            title: 'Unbounded Queue Publish',
            invariant: false,
            category: Category::BOUNDS->value,
            pack: 'bounds',
            defaultSeverity: Severity::Major,
            description: 'Detects queue publish loops without backpressure or caps.',
            whyItMatters: 'Unbounded publisher throughput can overwhelm downstream consumers.',
        );
    }

    protected function patternGroup(): string
    {
        return 'stage6_bounds';
    }

    protected function includeRegex(): string
    {
        return '/\b(publish|enqueue|dispatch|push)\b/i';
    }

    protected function excludeRegexes(): array
    {
        return ['/\b(limit|rate|throttle|max|backpressure)\b/i'];
    }

    protected function confidence(): Confidence
    {
        return Confidence::Low;
    }

    protected function recommendation(): string
    {
        return 'Add publish rate limits and queue length safeguards.';
    }

    protected function tags(): array
    {
        return ['bounds', 'queue', 'throughput'];
    }
}
