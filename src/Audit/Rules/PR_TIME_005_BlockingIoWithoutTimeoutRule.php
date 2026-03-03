<?php

declare(strict_types=1);

namespace ProdAudit\Audit\Rules;

final class PR_TIME_005_BlockingIoWithoutTimeoutRule extends AstCallHeuristicRule
{
    public function metadata(): RuleMetadata
    {
        return new RuleMetadata(
            id: 'PR-TIME-005',
            title: 'Blocking IO Without Timeout',
            invariant: false,
            category: Category::TIMEOUTS->value,
            pack: 'timeout',
            defaultSeverity: Severity::Major,
            description: 'Detects blocking IO operations without context timeout parameters.',
            whyItMatters: 'Blocking IO without deadlines can accumulate stuck workers.',
        );
    }

    protected function includeCallRegex(): string
    {
        return '/\b(fread|fgets|file_get_contents|stream_get_contents|read)\b/i';
    }

    protected function excludeSnippetRegexes(): array
    {
        return ['/\b(timeout|stream_context_create|nonblocking)\b/i'];
    }

    protected function confidence(): Confidence
    {
        return Confidence::Medium;
    }

    protected function recommendation(): string
    {
        return 'Use non-blocking IO or explicit timeout contexts.';
    }

    protected function tags(): array
    {
        return ['timeouts', 'io', 'runtime'];
    }
}
