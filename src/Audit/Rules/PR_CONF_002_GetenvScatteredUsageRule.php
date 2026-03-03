<?php

declare(strict_types=1);

namespace ProdAudit\Audit\Rules;

final class PR_CONF_002_GetenvScatteredUsageRule extends PatternHeuristicRule
{
    public function metadata(): RuleMetadata
    {
        return new RuleMetadata(
            id: 'PR-CONF-002',
            title: 'getenv Scattered Usage',
            invariant: false,
            category: Category::CONFIG->value,
            pack: 'config-safety',
            defaultSeverity: Severity::Minor,
            description: 'Detects direct getenv usage in application paths.',
            whyItMatters: 'Scattered getenv calls bypass centralized config validation and defaults.',
        );
    }

    protected function patternGroup(): string
    {
        return 'stage6_config';
    }

    protected function includeRegex(): string
    {
        return '/\bgetenv\s*\(/i';
    }

    protected function confidence(): Confidence
    {
        return Confidence::Medium;
    }

    protected function recommendation(): string
    {
        return 'Route environment access through a centralized configuration layer.';
    }

    protected function tags(): array
    {
        return ['config', 'env', 'safety'];
    }
}
