<?php

declare(strict_types=1);

namespace ProdAudit\Audit\Rules;

final class PR_SEC_001_PossibleSecretsInCodeRule extends PatternHeuristicRule
{
    public function metadata(): RuleMetadata
    {
        return new RuleMetadata(
            id: 'PR-SEC-001',
            title: 'Possible Secrets In Code',
            invariant: false,
            category: Category::SECURITY_BASELINE->value,
            pack: 'security-baseline',
            defaultSeverity: Severity::Critical,
            description: 'Detects secret-like literals committed in code.',
            whyItMatters: 'Embedded secrets enable unauthorized access and prolonged compromise windows.',
        );
    }

    protected function patternGroup(): string
    {
        return 'stage6_security';
    }

    protected function includeRegex(): string
    {
        return '/\b(secret|token|password|api[_-]?key)\b\s*[=:]/i';
    }

    protected function confidence(): Confidence
    {
        return Confidence::Medium;
    }

    protected function recommendation(): string
    {
        return 'Remove hardcoded secrets and rotate any exposed credentials.';
    }

    protected function tags(): array
    {
        return ['security', 'secrets', 'baseline'];
    }
}
