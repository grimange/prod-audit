<?php

declare(strict_types=1);

namespace ProdAudit\Audit\Rules;

final class PR_SEC_004_UnsafeEvalUsageRule extends PatternHeuristicRule
{
    public function metadata(): RuleMetadata
    {
        return new RuleMetadata(
            id: 'PR-SEC-004',
            title: 'Unsafe eval Usage',
            invariant: false,
            category: Category::SECURITY_BASELINE->value,
            pack: 'security-baseline',
            defaultSeverity: Severity::Critical,
            description: 'Detects dynamic code execution via eval.',
            whyItMatters: 'Eval-style execution can enable remote code execution vulnerabilities.',
        );
    }

    protected function patternGroup(): string
    {
        return 'stage6_security';
    }

    protected function includeRegex(): string
    {
        return '/\beval\s*\(/i';
    }

    protected function confidence(): Confidence
    {
        return Confidence::High;
    }

    protected function recommendation(): string
    {
        return 'Remove eval and replace with explicit safe parsing logic.';
    }

    protected function tags(): array
    {
        return ['security', 'eval', 'baseline'];
    }
}
