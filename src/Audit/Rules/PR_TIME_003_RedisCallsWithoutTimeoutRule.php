<?php

declare(strict_types=1);

namespace ProdAudit\Audit\Rules;

final class PR_TIME_003_RedisCallsWithoutTimeoutRule extends AstCallHeuristicRule
{
    public function metadata(): RuleMetadata
    {
        return new RuleMetadata(
            id: 'PR-TIME-003',
            title: 'Redis Calls Without Timeout',
            invariant: false,
            category: Category::TIMEOUTS->value,
            pack: 'timeout',
            defaultSeverity: Severity::Major,
            description: 'Detects Redis call paths missing timeout/expiry safeguards.',
            whyItMatters: 'Redis operations without timeout controls can hang workers during outages.',
        );
    }

    protected function includeCallRegex(): string
    {
        return '/\b(redis|predis|phpredis|get|set|eval|lpush|rpush|blpop)\b/i';
    }

    protected function excludeSnippetRegexes(): array
    {
        return ['/\b(timeout|expire|pexpire|setoption)\b/i'];
    }

    protected function confidence(): Confidence
    {
        return Confidence::Medium;
    }

    protected function recommendation(): string
    {
        return 'Configure Redis operation timeouts and bounded blocking calls.';
    }

    protected function tags(): array
    {
        return ['timeouts', 'redis', 'runtime'];
    }
}
