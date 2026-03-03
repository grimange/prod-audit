<?php

declare(strict_types=1);

namespace ProdAudit\Audit\Rules;

final class PR_LOCK_003_LockTtlTooSmallRule extends PatternHeuristicRule
{
    public function metadata(): RuleMetadata
    {
        return new RuleMetadata(
            id: 'PR-LOCK-003',
            title: 'Lock TTL Dangerously Small',
            invariant: false,
            category: Category::LOCKING->value,
            pack: 'reliability',
            defaultSeverity: Severity::Major,
            description: 'Detects lock TTL/expire values that are likely too small for production paths.',
            whyItMatters: 'Tiny lock TTL values can expire mid-critical-section and violate mutual exclusion.',
        );
    }

    protected function patternGroup(): string
    {
        return 'stage6_reliability';
    }

    protected function includeRegex(): string
    {
        return '/\b(ttl|expire)\s*\(?\s*(?:[1-9]|[1-5][0-9])\b/i';
    }

    protected function confidence(): Confidence
    {
        return Confidence::Low;
    }

    protected function recommendation(): string
    {
        return 'Use lock TTL values that exceed worst-case critical section execution time.';
    }

    protected function tags(): array
    {
        return ['locking', 'ttl', 'reliability'];
    }
}
