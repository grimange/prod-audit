<?php

declare(strict_types=1);

namespace ProdAudit\Audit\Rules;

final class PR_OBS_002_MissingJobIdentifiersRule extends AstCallHeuristicRule
{
    public function metadata(): RuleMetadata
    {
        return new RuleMetadata(
            id: 'PR-OBS-002',
            title: 'Missing Job Identifiers',
            invariant: false,
            category: Category::OBSERVABILITY->value,
            pack: 'observability',
            defaultSeverity: Severity::Minor,
            description: 'Detects logging calls that likely omit job identifiers.',
            whyItMatters: 'Missing job identifiers slows incident triage for asynchronous workers.',
        );
    }

    protected function includeCallRegex(): string
    {
        return '/\b(logger|log|info|error|warning|critical|notice|job)\b/i';
    }

    protected function excludeSnippetRegexes(): array
    {
        return ['/\b(job_id|jobid|task_id)\b/i'];
    }

    protected function confidence(): Confidence
    {
        return Confidence::Medium;
    }

    protected function recommendation(): string
    {
        return 'Include stable job identifiers in log context.';
    }

    protected function tags(): array
    {
        return ['observability', 'logging', 'job-id'];
    }
}
