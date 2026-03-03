<?php

declare(strict_types=1);

namespace ProdAudit\Audit\Rules;

final class PR_SEC_003_ShellExecUsageRule extends PatternHeuristicRule
{
    public function metadata(): RuleMetadata
    {
        return new RuleMetadata(
            id: 'PR-SEC-003',
            title: 'Shell Exec Usage',
            invariant: false,
            category: Category::SECURITY_BASELINE->value,
            pack: 'security-baseline',
            defaultSeverity: Severity::Major,
            description: 'Detects shell execution primitives in code.',
            whyItMatters: 'Shell execution expands command-injection attack surface.',
        );
    }

    protected function patternGroup(): string
    {
        return 'stage6_security';
    }

    protected function includeRegex(): string
    {
        return '/\b(shell_exec|exec|passthru|system|proc_open|popen)\s*\(/i';
    }

    protected function confidence(): Confidence
    {
        return Confidence::High;
    }

    protected function recommendation(): string
    {
        return 'Avoid shell primitives or strictly sanitize and isolate command execution.';
    }

    protected function tags(): array
    {
        return ['security', 'command-exec', 'baseline'];
    }
}
