<?php

declare(strict_types=1);

namespace ProdAudit\Audit\Rules;

final class PR_SEC_005_DirectSqlQueryBuildingRule extends PatternHeuristicRule
{
    public function metadata(): RuleMetadata
    {
        return new RuleMetadata(
            id: 'PR-SEC-005',
            title: 'Direct SQL Query Building',
            invariant: false,
            category: Category::SECURITY_BASELINE->value,
            pack: 'security-baseline',
            defaultSeverity: Severity::Major,
            description: 'Detects string-built SQL query patterns.',
            whyItMatters: 'Dynamic SQL string building raises SQL injection and query safety risks.',
        );
    }

    protected function patternGroup(): string
    {
        return 'stage6_security';
    }

    protected function includeRegex(): string
    {
        return '/\b(SELECT|INSERT|UPDATE|DELETE)\b/i';
    }

    protected function excludeRegexes(): array
    {
        return ['/\bprepare\s*\(/i'];
    }

    protected function confidence(): Confidence
    {
        return Confidence::Low;
    }

    protected function recommendation(): string
    {
        return 'Use parameterized queries and prepared statements consistently.';
    }

    protected function tags(): array
    {
        return ['security', 'sql', 'baseline'];
    }
}
