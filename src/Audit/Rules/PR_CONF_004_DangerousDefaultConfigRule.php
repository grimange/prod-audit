<?php

declare(strict_types=1);

namespace ProdAudit\Audit\Rules;

final class PR_CONF_004_DangerousDefaultConfigRule extends PatternHeuristicRule
{
    public function metadata(): RuleMetadata
    {
        return new RuleMetadata(
            id: 'PR-CONF-004',
            title: 'Dangerous Default Config',
            invariant: false,
            category: Category::CONFIG->value,
            pack: 'config-safety',
            defaultSeverity: Severity::Major,
            description: 'Detects risky default configuration values.',
            whyItMatters: 'Dangerous defaults can expose systems to data loss or unauthorized access.',
        );
    }

    protected function patternGroup(): string
    {
        return 'stage6_config';
    }

    protected function includeRegex(): string
    {
        return '/\b(default|fallback)\b.*\b(root|admin|localhost|0\.0\.0\.0|debug)\b/i';
    }

    protected function confidence(): Confidence
    {
        return Confidence::Low;
    }

    protected function recommendation(): string
    {
        return 'Use conservative defaults suitable for production environments.';
    }

    protected function tags(): array
    {
        return ['config', 'defaults', 'risk'];
    }
}
