<?php

declare(strict_types=1);

namespace ProdAudit\Audit\Rules;

final class PR_OBS_007_MissingShutdownLogsRule extends PatternHeuristicRule
{
    public function metadata(): RuleMetadata
    {
        return new RuleMetadata(
            id: 'PR-OBS-007',
            title: 'Missing Shutdown Logs',
            invariant: false,
            category: Category::OBSERVABILITY->value,
            pack: 'observability',
            defaultSeverity: Severity::Minor,
            description: 'Detects shutdown/signal handling without closing lifecycle logs.',
            whyItMatters: 'Missing shutdown logs weakens incident timelines and restart analysis.',
        );
    }

    protected function patternGroup(): string
    {
        return 'stage6_observability';
    }

    protected function includeRegex(): string
    {
        return '/\b(shutdown|signal|SIGTERM|SIGINT)\b/i';
    }

    protected function excludeRegexes(): array
    {
        return ['/\b(log|logger|stopped|shutdown complete)\b/i'];
    }

    protected function confidence(): Confidence
    {
        return Confidence::Low;
    }

    protected function recommendation(): string
    {
        return 'Emit structured shutdown completion logs for controlled exits.';
    }

    protected function tags(): array
    {
        return ['observability', 'shutdown', 'operations'];
    }
}
