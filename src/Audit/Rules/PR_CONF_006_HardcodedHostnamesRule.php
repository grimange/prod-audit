<?php

declare(strict_types=1);

namespace ProdAudit\Audit\Rules;

final class PR_CONF_006_HardcodedHostnamesRule extends PatternHeuristicRule
{
    public function metadata(): RuleMetadata
    {
        return new RuleMetadata(
            id: 'PR-CONF-006',
            title: 'Hardcoded Hostnames',
            invariant: false,
            category: Category::CONFIG->value,
            pack: 'config-safety',
            defaultSeverity: Severity::Minor,
            description: 'Detects host literals embedded in runtime code paths.',
            whyItMatters: 'Hardcoded hosts impede failover, staging parity, and disaster recovery.',
        );
    }

    protected function patternGroup(): string
    {
        return 'stage6_config';
    }

    protected function includeRegex(): string
    {
        return '/\b(localhost|127\.0\.0\.1|[a-z0-9.-]+\.(local|internal|corp|lan))\b/i';
    }

    protected function confidence(): Confidence
    {
        return Confidence::Low;
    }

    protected function recommendation(): string
    {
        return 'Externalize hostnames and validate via centralized config.';
    }

    protected function tags(): array
    {
        return ['config', 'network', 'hosts'];
    }
}
