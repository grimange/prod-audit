<?php

declare(strict_types=1);

namespace ProdAudit\Audit\Rules;

final class PR_OBS_003_MissingCorrelationIdRule extends AstCallHeuristicRule
{
    public function metadata(): RuleMetadata
    {
        return new RuleMetadata(
            id: 'PR-OBS-003',
            title: 'Missing Correlation ID',
            invariant: false,
            category: Category::OBSERVABILITY->value,
            pack: 'observability',
            defaultSeverity: Severity::Minor,
            description: 'Detects logging without correlation/trace identifiers.',
            whyItMatters: 'Missing correlation IDs prevents stitching cross-service event timelines.',
        );
    }

    protected function includeCallRegex(): string
    {
        return '/\b(logger|log|info|error|warning|critical|notice)\b/i';
    }

    protected function excludeSnippetRegexes(): array
    {
        return ['/\b(corr_id|correlation|trace_id|request_id)\b/i'];
    }

    protected function confidence(): Confidence
    {
        return Confidence::Medium;
    }

    protected function recommendation(): string
    {
        return 'Propagate and log correlation identifiers end-to-end.';
    }

    protected function tags(): array
    {
        return ['observability', 'logging', 'correlation'];
    }
}
