<?php

declare(strict_types=1);

namespace ProdAudit\Audit\Rules;

final class PR_BOUND_006_UnboundedLogGrowthRule extends PatternHeuristicRule
{
    public function metadata(): RuleMetadata
    {
        return new RuleMetadata(
            id: 'PR-BOUND-006',
            title: 'Unbounded Log Growth',
            invariant: false,
            category: Category::BOUNDS->value,
            pack: 'bounds',
            defaultSeverity: Severity::Minor,
            description: 'Detects append-style logging without rotation hints.',
            whyItMatters: 'Unbounded logs consume disk and can trigger host-level incidents.',
        );
    }

    protected function patternGroup(): string
    {
        return 'stage6_bounds';
    }

    protected function includeRegex(): string
    {
        return '/\b(file_put_contents|append|log)\b/i';
    }

    protected function excludeRegexes(): array
    {
        return ['/\b(rotate|maxsize|retention|truncate)\b/i'];
    }

    protected function confidence(): Confidence
    {
        return Confidence::Low;
    }

    protected function recommendation(): string
    {
        return 'Enable log rotation and retention limits.';
    }

    protected function tags(): array
    {
        return ['bounds', 'logging', 'storage'];
    }
}
