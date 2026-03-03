<?php

declare(strict_types=1);

namespace ProdAudit\Audit\Rules;

final class PR_ERR_005_PartialExceptionHandlingRule extends PatternHeuristicRule
{
    public function metadata(): RuleMetadata
    {
        return new RuleMetadata(
            id: 'PR-ERR-005',
            title: 'Partial Exception Handling',
            invariant: false,
            category: Category::ERRORS->value,
            pack: 'error-handling',
            defaultSeverity: Severity::Major,
            description: 'Detects narrow exception handling patterns likely to miss broader failures.',
            whyItMatters: 'Partial handling creates uncaught paths and inconsistent recovery behavior.',
        );
    }

    protected function patternGroup(): string
    {
        return 'stage6_errors';
    }

    protected function includeRegex(): string
    {
        return '/catch\s*\(\s*[A-Za-z_\\]+\s+\$[A-Za-z_]+\s*\)/i';
    }

    protected function excludeRegexes(): array
    {
        return ['/\b(Throwable|Exception)\b/i'];
    }

    protected function confidence(): Confidence
    {
        return Confidence::Low;
    }

    protected function recommendation(): string
    {
        return 'Handle broader exception hierarchies or propagate with context.';
    }

    protected function tags(): array
    {
        return ['errors', 'exceptions', 'resilience'];
    }
}
