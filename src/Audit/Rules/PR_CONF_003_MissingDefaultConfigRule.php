<?php

declare(strict_types=1);

namespace ProdAudit\Audit\Rules;

final class PR_CONF_003_MissingDefaultConfigRule extends PatternHeuristicRule
{
    public function metadata(): RuleMetadata
    {
        return new RuleMetadata(
            id: 'PR-CONF-003',
            title: 'Missing Default Config',
            invariant: false,
            category: Category::CONFIG->value,
            pack: 'config-safety',
            defaultSeverity: Severity::Major,
            description: 'Detects config access patterns that appear to omit default values.',
            whyItMatters: 'Missing defaults create runtime crashes on incomplete environment setup.',
        );
    }

    protected function patternGroup(): string
    {
        return 'stage6_config';
    }

    protected function includeRegex(): string
    {
        return '/\b(config|getenv)\s*\([^,\)]*\)/i';
    }

    protected function excludeRegexes(): array
    {
        return ['/,|\?\?|default/i'];
    }

    protected function confidence(): Confidence
    {
        return Confidence::Low;
    }

    protected function recommendation(): string
    {
        return 'Provide explicit safe defaults for required configuration keys.';
    }

    protected function tags(): array
    {
        return ['config', 'defaults', 'safety'];
    }
}
