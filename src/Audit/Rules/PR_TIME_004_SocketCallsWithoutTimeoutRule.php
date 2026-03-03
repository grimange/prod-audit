<?php

declare(strict_types=1);

namespace ProdAudit\Audit\Rules;

final class PR_TIME_004_SocketCallsWithoutTimeoutRule extends AstCallHeuristicRule
{
    public function metadata(): RuleMetadata
    {
        return new RuleMetadata(
            id: 'PR-TIME-004',
            title: 'Socket Calls Without Timeout',
            invariant: false,
            category: Category::TIMEOUTS->value,
            pack: 'timeout',
            defaultSeverity: Severity::Major,
            description: 'Detects socket call sites with no explicit timeout guards.',
            whyItMatters: 'Socket operations can block indefinitely during network partitions.',
        );
    }

    protected function includeCallRegex(): string
    {
        return '/\b(socket|stream_socket_client|fsockopen|connect)\b/i';
    }

    protected function excludeSnippetRegexes(): array
    {
        return ['/\b(timeout|stream_set_timeout|settimeout)\b/i'];
    }

    protected function confidence(): Confidence
    {
        return Confidence::Medium;
    }

    protected function recommendation(): string
    {
        return 'Set socket connect/read deadlines for all network operations.';
    }

    protected function tags(): array
    {
        return ['timeouts', 'network', 'runtime'];
    }
}
