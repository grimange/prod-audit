<?php

declare(strict_types=1);

namespace ProdAudit\Audit\Rules;

final class PR_STATE_002_StateFallbackUnsafeRule extends PatternHeuristicRule
{
    public function metadata(): RuleMetadata
    {
        return new RuleMetadata(
            id: 'PR-STATE-002',
            title: 'State Fallback Logic Unsafe',
            invariant: false,
            category: Category::CONFIG->value,
            pack: 'reliability',
            defaultSeverity: Severity::Minor,
            description: 'Detects state fallback/default branches without safety indicators.',
            whyItMatters: 'Unsafe fallback behavior can mask upstream failures and create data drift.',
        );
    }

    protected function patternGroup(): string
    {
        return 'stage6_reliability';
    }

    protected function includeRegex(): string
    {
        return '/\b(state|fallback|default)\b/i';
    }

    protected function excludeRegexes(): array
    {
        return ['/\b(safe|guard|validate)\b/i'];
    }

    protected function confidence(): Confidence
    {
        return Confidence::Low;
    }

    protected function recommendation(): string
    {
        return 'Make fallback transitions explicit, validated, and observable.';
    }

    protected function tags(): array
    {
        return ['state', 'fallback', 'reliability'];
    }
}
