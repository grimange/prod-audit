<?php

declare(strict_types=1);

namespace ProdAudit\Audit\Rules;

final class PR_ERR_006_IgnoredPromiseFutureResultRule extends PatternHeuristicRule
{
    public function metadata(): RuleMetadata
    {
        return new RuleMetadata(
            id: 'PR-ERR-006',
            title: 'Ignored Promise/Future Result',
            invariant: false,
            category: Category::ERRORS->value,
            pack: 'error-handling',
            defaultSeverity: Severity::Major,
            description: 'Detects promise/future usage without wait/get handling.',
            whyItMatters: 'Ignoring async results can silently drop critical failure outcomes.',
        );
    }

    protected function patternGroup(): string
    {
        return 'stage6_errors';
    }

    protected function includeRegex(): string
    {
        return '/\b(promise|future|then\()\b/i';
    }

    protected function excludeRegexes(): array
    {
        return ['/\b(wait|get|await|otherwise|catch)\b/i'];
    }

    protected function confidence(): Confidence
    {
        return Confidence::Low;
    }

    protected function recommendation(): string
    {
        return 'Await or inspect async results and handle failure branches.';
    }

    protected function tags(): array
    {
        return ['errors', 'async', 'promises'];
    }
}
