<?php

declare(strict_types=1);

namespace ProdAudit\Audit\Rules;

final class PR_CONC_001_SharedMutableStaticStateRule extends PatternHeuristicRule
{
    public function metadata(): RuleMetadata
    {
        return new RuleMetadata(
            id: 'PR-CONC-001',
            title: 'Shared Mutable Static State',
            invariant: false,
            category: Category::LOCKING->value,
            pack: 'reliability',
            defaultSeverity: Severity::Major,
            description: 'Detects mutable static state declarations likely shared across workers.',
            whyItMatters: 'Shared mutable static state introduces cross-request coupling and race risk.',
        );
    }

    protected function patternGroup(): string
    {
        return 'stage6_reliability';
    }

    protected function includeRegex(): string
    {
        return '/\b(static\s+\$|public\s+static\s+\$|private\s+static\s+\$)\b/i';
    }

    protected function confidence(): Confidence
    {
        return Confidence::Medium;
    }

    protected function recommendation(): string
    {
        return 'Prefer immutable constants or scoped instance state over mutable static state.';
    }

    protected function tags(): array
    {
        return ['concurrency', 'state', 'reliability'];
    }
}
