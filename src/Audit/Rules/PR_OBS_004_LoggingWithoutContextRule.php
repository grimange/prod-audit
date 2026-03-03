<?php

declare(strict_types=1);

namespace ProdAudit\Audit\Rules;

final class PR_OBS_004_LoggingWithoutContextRule extends AstCallHeuristicRule
{
    public function metadata(): RuleMetadata
    {
        return new RuleMetadata(
            id: 'PR-OBS-004',
            title: 'Logging Without Context',
            invariant: false,
            category: Category::OBSERVABILITY->value,
            pack: 'observability',
            defaultSeverity: Severity::Minor,
            description: 'Detects logger calls likely missing structured context payload.',
            whyItMatters: 'Context-free logs reduce incident diagnosis precision.',
        );
    }

    protected function includeCallRegex(): string
    {
        return '/\b(logger|log|info|error|warning|critical|notice)\b/i';
    }

    protected function excludeSnippetRegexes(): array
    {
        return ['/\{.*\}|\[.*\]|\bcontext\b/i'];
    }

    protected function confidence(): Confidence
    {
        return Confidence::Medium;
    }

    protected function recommendation(): string
    {
        return 'Use structured context payloads for key business and runtime fields.';
    }

    protected function tags(): array
    {
        return ['observability', 'logging', 'context'];
    }
}
