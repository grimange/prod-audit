<?php

declare(strict_types=1);

namespace ProdAudit\Audit\Rules;

final class PR_BOUND_008_LargeArraysWithoutChunkingRule extends PatternHeuristicRule
{
    public function metadata(): RuleMetadata
    {
        return new RuleMetadata(
            id: 'PR-BOUND-008',
            title: 'Large Arrays Without Chunking',
            invariant: false,
            category: Category::BOUNDS->value,
            pack: 'bounds',
            defaultSeverity: Severity::Minor,
            description: 'Detects large array handling patterns without chunking hints.',
            whyItMatters: 'Large batch allocations increase peak memory and latency spikes.',
        );
    }

    protected function patternGroup(): string
    {
        return 'stage6_bounds';
    }

    protected function includeRegex(): string
    {
        return '/\b(array_map|array_filter|foreach|collect)\b/i';
    }

    protected function excludeRegexes(): array
    {
        return ['/\b(chunk|batch|cursor|yield)\b/i'];
    }

    protected function confidence(): Confidence
    {
        return Confidence::Low;
    }

    protected function recommendation(): string
    {
        return 'Process large collections in bounded chunks.';
    }

    protected function tags(): array
    {
        return ['bounds', 'batching', 'memory'];
    }
}
