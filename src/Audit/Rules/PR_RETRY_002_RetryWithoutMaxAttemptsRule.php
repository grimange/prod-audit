<?php

declare(strict_types=1);

namespace ProdAudit\Audit\Rules;

final class PR_RETRY_002_RetryWithoutMaxAttemptsRule extends AstCallHeuristicRule
{
    public function metadata(): RuleMetadata
    {
        return new RuleMetadata(
            id: 'PR-RETRY-002',
            title: 'Retry Without Max Attempts',
            invariant: false,
            category: Category::HANG->value,
            pack: 'reliability',
            defaultSeverity: Severity::Major,
            description: 'Detects retry flows without explicit attempt caps.',
            whyItMatters: 'Unbounded retries can create infinite work loops and resource exhaustion.',
        );
    }

    protected function includeCallRegex(): string
    {
        return '/\b(retry|attempt|requeue)\b/i';
    }

    protected function excludeSnippetRegexes(): array
    {
        return ['/\b(max(?:_| )?attempt|limit|budget)\b/i'];
    }

    protected function confidence(): Confidence
    {
        return Confidence::Medium;
    }

    protected function recommendation(): string
    {
        return 'Enforce max attempt limits and dead-letter handling.';
    }

    protected function tags(): array
    {
        return ['retry', 'limits', 'reliability'];
    }
}
