<?php

declare(strict_types=1);

namespace ProdAudit\Audit\Rules;

final class PR_OBS_006_MissingStartupLogsRule extends PatternHeuristicRule
{
    public function metadata(): RuleMetadata
    {
        return new RuleMetadata(
            id: 'PR-OBS-006',
            title: 'Missing Startup Logs',
            invariant: false,
            category: Category::OBSERVABILITY->value,
            pack: 'observability',
            defaultSeverity: Severity::Minor,
            description: 'Detects worker entrypoints without startup log hints.',
            whyItMatters: 'Missing startup logs obscures deploy and process lifecycle visibility.',
        );
    }

    protected function patternGroup(): string
    {
        return 'stage6_observability';
    }

    protected function includeRegex(): string
    {
        return '/\b(function\s+(run|handle|process)|worker|daemon)\b/i';
    }

    protected function excludeRegexes(): array
    {
        return ['/\b(startup|boot|started|logger|log)\b/i'];
    }

    protected function confidence(): Confidence
    {
        return Confidence::Low;
    }

    protected function recommendation(): string
    {
        return 'Emit explicit startup log events with version and runtime metadata.';
    }

    protected function tags(): array
    {
        return ['observability', 'startup', 'operations'];
    }
}
