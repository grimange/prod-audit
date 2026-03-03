<?php

declare(strict_types=1);

namespace ProdAudit\Audit\Rules;

final class PR_RETRY_001_RetryLoopWithoutBackoffRule extends AstCallHeuristicRule
{
    public function metadata(): RuleMetadata
    {
        return new RuleMetadata(
            id: 'PR-RETRY-001',
            title: 'Retry Loop Without Backoff',
            invariant: false,
            category: Category::HANG->value,
            pack: 'reliability',
            defaultSeverity: Severity::Major,
            description: 'Detects retry call paths without jitter/backoff controls.',
            whyItMatters: 'Retries without backoff amplify outages and increase upstream pressure.',
        );
    }

    protected function includeCallRegex(): string
    {
        return '/\b(retry|attempt|requeue)\b/i';
    }

    protected function excludeSnippetRegexes(): array
    {
        return ['/\b(backoff|jitter|sleep|usleep|delay)\b/i'];
    }

    protected function confidence(): Confidence
    {
        return Confidence::Medium;
    }

    protected function recommendation(): string
    {
        return 'Add bounded exponential backoff with jitter for retries.';
    }

    protected function tags(): array
    {
        return ['retry', 'backoff', 'reliability'];
    }
}
