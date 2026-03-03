<?php

declare(strict_types=1);

namespace ProdAudit\Audit\Rules;

final class PR_ERR_007_IgnoredReturnValueRule extends PatternHeuristicRule
{
    public function metadata(): RuleMetadata
    {
        return new RuleMetadata(
            id: 'PR-ERR-007',
            title: 'Ignored Return Value',
            invariant: false,
            category: Category::ERRORS->value,
            pack: 'error-handling',
            defaultSeverity: Severity::Minor,
            description: 'Detects side-effect calls where return status is likely ignored.',
            whyItMatters: 'Ignoring return values can hide partial writes and failed operations.',
        );
    }

    protected function patternGroup(): string
    {
        return 'stage6_errors';
    }

    protected function includeRegex(): string
    {
        return '/\b(save|write|publish|dispatch|push|execute)\s*\([^)]*\)\s*;/i';
    }

    protected function excludeRegexes(): array
    {
        return ['/=|if\s*\(|\bassert\b/i'];
    }

    protected function confidence(): Confidence
    {
        return Confidence::Low;
    }

    protected function recommendation(): string
    {
        return 'Check return status and fail fast on unsuccessful operations.';
    }

    protected function tags(): array
    {
        return ['errors', 'return-value', 'robustness'];
    }
}
