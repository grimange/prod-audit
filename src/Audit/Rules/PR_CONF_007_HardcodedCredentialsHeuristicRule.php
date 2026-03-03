<?php

declare(strict_types=1);

namespace ProdAudit\Audit\Rules;

final class PR_CONF_007_HardcodedCredentialsHeuristicRule extends PatternHeuristicRule
{
    public function metadata(): RuleMetadata
    {
        return new RuleMetadata(
            id: 'PR-CONF-007',
            title: 'Hardcoded Credentials Heuristic',
            invariant: false,
            category: Category::CONFIG->value,
            pack: 'config-safety',
            defaultSeverity: Severity::Critical,
            description: 'Detects credential-like assignments in source files.',
            whyItMatters: 'Hardcoded credentials are a direct production security and compliance risk.',
        );
    }

    protected function patternGroup(): string
    {
        return 'stage6_config';
    }

    protected function includeRegex(): string
    {
        return '/\b(password|passwd|secret|api[_-]?key|token)\b\s*[=:]/i';
    }

    protected function confidence(): Confidence
    {
        return Confidence::Medium;
    }

    protected function recommendation(): string
    {
        return 'Move credentials to a secret manager and rotate exposed values.';
    }

    protected function tags(): array
    {
        return ['config', 'secrets', 'security'];
    }
}
