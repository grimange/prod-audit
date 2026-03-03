<?php

declare(strict_types=1);

namespace ProdAudit\Audit\Rules;

final class PR_SHUTDOWN_002_UnsafeShutdownCleanupRule extends PatternHeuristicRule
{
    public function metadata(): RuleMetadata
    {
        return new RuleMetadata(
            id: 'PR-SHUTDOWN-002',
            title: 'Unsafe Shutdown Cleanup',
            invariant: false,
            category: Category::ERRORS->value,
            pack: 'reliability',
            defaultSeverity: Severity::Major,
            description: 'Detects cleanup code that appears to suppress errors during shutdown paths.',
            whyItMatters: 'Suppressed cleanup failures leak state and corrupt worker lifecycle handling.',
        );
    }

    protected function patternGroup(): string
    {
        return 'stage6_reliability';
    }

    protected function includeRegex(): string
    {
        return '/\b(shutdown|cleanup)\b.*@|@.*\b(shutdown|cleanup)\b/i';
    }

    protected function confidence(): Confidence
    {
        return Confidence::Low;
    }

    protected function recommendation(): string
    {
        return 'Handle cleanup failures explicitly and emit failure telemetry.';
    }

    protected function tags(): array
    {
        return ['shutdown', 'cleanup', 'error-handling'];
    }
}
