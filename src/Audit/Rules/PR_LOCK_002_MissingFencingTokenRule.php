<?php

declare(strict_types=1);

namespace ProdAudit\Audit\Rules;

final class PR_LOCK_002_MissingFencingTokenRule extends PatternHeuristicRule
{
    public function metadata(): RuleMetadata
    {
        return new RuleMetadata(
            id: 'PR-LOCK-002',
            title: 'Missing Fencing Token',
            invariant: false,
            category: Category::LOCKING->value,
            pack: 'reliability',
            defaultSeverity: Severity::Major,
            description: 'Detects lock operations without fencing/ownership token hints.',
            whyItMatters: 'Missing fencing tokens can cause stale owners to overwrite newer work.',
        );
    }

    protected function patternGroup(): string
    {
        return 'stage6_reliability';
    }

    protected function includeRegex(): string
    {
        return '/\b(lock|acquire|mutex)\b/i';
    }

    protected function excludeRegexes(): array
    {
        return ['/\b(fencing|owner|token)\b/i'];
    }

    protected function confidence(): Confidence
    {
        return Confidence::Low;
    }

    protected function recommendation(): string
    {
        return 'Track and validate fencing/owner token on lock acquisition and release.';
    }

    protected function tags(): array
    {
        return ['locking', 'fencing', 'reliability'];
    }
}
