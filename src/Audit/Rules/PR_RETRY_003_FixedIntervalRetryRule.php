<?php

declare(strict_types=1);

namespace ProdAudit\Audit\Rules;

final class PR_RETRY_003_FixedIntervalRetryRule extends AstCallHeuristicRule
{
    public function metadata(): RuleMetadata
    {
        return new RuleMetadata(
            id: 'PR-RETRY-003',
            title: 'Fixed Interval Retry',
            invariant: false,
            category: Category::HANG->value,
            pack: 'reliability',
            defaultSeverity: Severity::Minor,
            description: 'Detects fixed interval retry patterns likely to synchronize worker storms.',
            whyItMatters: 'Fixed intervals synchronize retries and worsen failure domains.',
        );
    }

    protected function includeCallRegex(): string
    {
        return '/\b(retry|sleep\s*\(\s*\d+|delay\s*\(\s*\d+)\b/i';
    }

    protected function excludeSnippetRegexes(): array
    {
        return ['/\b(random|jitter)\b/i'];
    }

    protected function confidence(): Confidence
    {
        return Confidence::Low;
    }

    protected function recommendation(): string
    {
        return 'Add random jitter to retry intervals.';
    }

    protected function tags(): array
    {
        return ['retry', 'jitter', 'reliability'];
    }
}
