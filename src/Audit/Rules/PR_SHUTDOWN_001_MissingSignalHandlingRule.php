<?php

declare(strict_types=1);

namespace ProdAudit\Audit\Rules;

final class PR_SHUTDOWN_001_MissingSignalHandlingRule extends PatternHeuristicRule
{
    public function metadata(): RuleMetadata
    {
        return new RuleMetadata(
            id: 'PR-SHUTDOWN-001',
            title: 'Missing Signal Handling',
            invariant: false,
            category: Category::HANG->value,
            pack: 'reliability',
            defaultSeverity: Severity::Major,
            description: 'Detects long-running loop/process hints without signal handler references.',
            whyItMatters: 'Workers that ignore SIGTERM/SIGINT cannot shut down gracefully.',
        );
    }

    protected function patternGroup(): string
    {
        return 'stage6_reliability';
    }

    protected function includeRegex(): string
    {
        return '/\b(worker|daemon|while\s*\(\s*true\s*\))\b/i';
    }

    protected function excludeRegexes(): array
    {
        return ['/\b(SIGTERM|SIGINT|pcntl_signal|signal)\b/i'];
    }

    protected function confidence(): Confidence
    {
        return Confidence::Low;
    }

    protected function recommendation(): string
    {
        return 'Install explicit signal handlers for graceful shutdown.';
    }

    protected function tags(): array
    {
        return ['shutdown', 'signals', 'reliability'];
    }
}
