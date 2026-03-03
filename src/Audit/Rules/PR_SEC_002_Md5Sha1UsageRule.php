<?php

declare(strict_types=1);

namespace ProdAudit\Audit\Rules;

final class PR_SEC_002_Md5Sha1UsageRule extends PatternHeuristicRule
{
    public function metadata(): RuleMetadata
    {
        return new RuleMetadata(
            id: 'PR-SEC-002',
            title: 'MD5/SHA1 Usage',
            invariant: false,
            category: Category::SECURITY_BASELINE->value,
            pack: 'security-baseline',
            defaultSeverity: Severity::Major,
            description: 'Detects use of legacy weak hash functions.',
            whyItMatters: 'MD5/SHA1 are weak for integrity and password security use cases.',
        );
    }

    protected function patternGroup(): string
    {
        return 'stage6_security';
    }

    protected function includeRegex(): string
    {
        return '/\b(md5|sha1)\s*\(/i';
    }

    protected function confidence(): Confidence
    {
        return Confidence::High;
    }

    protected function recommendation(): string
    {
        return 'Use modern algorithms such as SHA-256/512 or Argon2/Bcrypt as appropriate.';
    }

    protected function tags(): array
    {
        return ['security', 'crypto', 'baseline'];
    }
}
