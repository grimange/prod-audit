<?php

declare(strict_types=1);

namespace ProdAudit\Audit\Rules;

final class PR_TIME_002_DatabaseCallsWithoutTimeoutRule extends AstCallHeuristicRule
{
    public function metadata(): RuleMetadata
    {
        return new RuleMetadata(
            id: 'PR-TIME-002',
            title: 'Database Calls Without Timeout',
            invariant: false,
            category: Category::TIMEOUTS->value,
            pack: 'timeout',
            defaultSeverity: Severity::Major,
            description: 'Detects database query-style calls without timeout hints.',
            whyItMatters: 'Database calls without timeout controls can stall worker throughput.',
        );
    }

    protected function includeCallRegex(): string
    {
        return '/\b(db|pdo|mysqli|query|execute|prepare|statement)\b/i';
    }

    protected function excludeSnippetRegexes(): array
    {
        return ['/\b(timeout|setattribute|wait_timeout|max_execution_time)\b/i'];
    }

    protected function confidence(): Confidence
    {
        return Confidence::Medium;
    }

    protected function recommendation(): string
    {
        return 'Set explicit connection and query timeout controls on database clients.';
    }

    protected function tags(): array
    {
        return ['timeouts', 'database', 'runtime'];
    }
}
