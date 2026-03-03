<?php

declare(strict_types=1);

namespace ProdAudit\Audit\Rules;

final class PR_CONF_005_HardcodedPortsRule extends PatternHeuristicRule
{
    public function metadata(): RuleMetadata
    {
        return new RuleMetadata(
            id: 'PR-CONF-005',
            title: 'Hardcoded Ports',
            invariant: false,
            category: Category::CONFIG->value,
            pack: 'config-safety',
            defaultSeverity: Severity::Minor,
            description: 'Detects hardcoded network port literals.',
            whyItMatters: 'Hardcoded ports reduce deploy portability and can conflict across environments.',
        );
    }

    protected function patternGroup(): string
    {
        return 'stage6_config';
    }

    protected function includeRegex(): string
    {
        return '/\b(port\s*=\s*\d{2,5}|:\d{2,5})\b/i';
    }

    protected function confidence(): Confidence
    {
        return Confidence::Low;
    }

    protected function recommendation(): string
    {
        return 'Move port configuration to validated environment-driven settings.';
    }

    protected function tags(): array
    {
        return ['config', 'network', 'ports'];
    }
}
