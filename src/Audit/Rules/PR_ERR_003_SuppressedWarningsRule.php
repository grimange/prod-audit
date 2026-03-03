<?php

declare(strict_types=1);

namespace ProdAudit\Audit\Rules;

final class PR_ERR_003_SuppressedWarningsRule extends PatternHeuristicRule
{
    public function metadata(): RuleMetadata
    {
        return new RuleMetadata(
            id: 'PR-ERR-003',
            title: 'Suppressed Warnings',
            invariant: false,
            category: Category::ERRORS->value,
            pack: 'error-handling',
            defaultSeverity: Severity::Major,
            description: 'Detects error-suppression operator usage.',
            whyItMatters: 'Suppressed warnings hide production failures and observability signals.',
        );
    }

    protected function patternGroup(): string
    {
        return 'stage6_errors';
    }

    protected function includeRegex(): string
    {
        return '/@\s*[A-Za-z_]/';
    }

    protected function confidence(): Confidence
    {
        return Confidence::High;
    }

    protected function recommendation(): string
    {
        return 'Remove warning suppression and handle error states explicitly.';
    }

    protected function tags(): array
    {
        return ['errors', 'warnings', 'observability'];
    }
}
