<?php

declare(strict_types=1);

namespace ProdAudit\Audit\Rules;

final class PR_STATE_001_StateTransitionNotValidatedRule extends PatternHeuristicRule
{
    public function metadata(): RuleMetadata
    {
        return new RuleMetadata(
            id: 'PR-STATE-001',
            title: 'State Transition Not Validated',
            invariant: false,
            category: Category::CONFIG->value,
            pack: 'reliability',
            defaultSeverity: Severity::Major,
            description: 'Detects state transition calls without obvious guard validation hints.',
            whyItMatters: 'Unchecked transitions can move systems into invalid or unrecoverable states.',
        );
    }

    protected function patternGroup(): string
    {
        return 'stage6_reliability';
    }

    protected function includeRegex(): string
    {
        return '/\b(transition|setstate|state\s*=)\b/i';
    }

    protected function excludeRegexes(): array
    {
        return ['/\b(valid|allow|cantransition|guard)\b/i'];
    }

    protected function confidence(): Confidence
    {
        return Confidence::Low;
    }

    protected function recommendation(): string
    {
        return 'Validate state transitions against an explicit state machine.';
    }

    protected function tags(): array
    {
        return ['state', 'validation', 'reliability'];
    }
}
