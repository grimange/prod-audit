<?php

declare(strict_types=1);

namespace ProdAudit\Audit\Rules;

use ProdAudit\Utils\Fingerprint;

final class PR_LOCK_001_LockRenewRule implements InvariantRuleInterface
{
    public function metadata(): RuleMetadata
    {
        return new RuleMetadata(
            id: 'PR-LOCK-001',
            title: 'Lock Renew Atomicity Heuristic',
            description: 'Detects lock renew calls without owner/token or Lua/eval atomicity hints.',
            invariant: true,
        );
    }

    public function evaluate(array $collectorData): RuleResult
    {
        $matches = $collectorData['patterns']['redis'] ?? [];
        if (!is_array($matches)) {
            return new RuleResult($this->metadata(), []);
        }

        $byFile = [];
        foreach ($matches as $match) {
            if (!is_array($match)) {
                continue;
            }

            $file = (string) ($match['file'] ?? '');
            if ($file === '') {
                continue;
            }

            $byFile[$file][] = $match;
        }

        ksort($byFile, SORT_STRING);

        $findings = [];
        $index = 1;
        foreach ($byFile as $file => $fileMatches) {
            $hasOwnerScope = false;
            $hasAtomicityHint = false;
            $renewMatches = [];

            foreach ($fileMatches as $match) {
                $excerpt = (string) ($match['excerpt'] ?? '');

                if (preg_match('/->\s*(?:pexpire|expire)\s*\(/i', $excerpt) === 1) {
                    $renewMatches[] = $match;
                }

                if (preg_match('/\b(owner|token)\b/i', $excerpt) === 1) {
                    $hasOwnerScope = true;
                }

                if (preg_match('/\b(lua|eval)\b|->\s*eval\s*\(/i', $excerpt) === 1) {
                    $hasAtomicityHint = true;
                }
            }

            if ($renewMatches === [] || $hasOwnerScope || $hasAtomicityHint) {
                continue;
            }

            foreach ($renewMatches as $match) {
                $line = (int) ($match['line'] ?? 0);
                $line = $line > 0 ? $line : 1;
                $excerpt = (string) ($match['excerpt'] ?? '');

                $evidence = Evidence::create(
                    type: 'file_snippet',
                    file: $file,
                    lineStart: $line,
                    lineEnd: $line + substr_count($excerpt, "\n"),
                    excerpt: $excerpt
                );

                $fingerprint = Fingerprint::fromEvidence('PR-LOCK-001', [$evidence]);
                $findings[] = new Finding(
                    id: sprintf('PR-LOCK-001-%03d', $index),
                    ruleId: 'PR-LOCK-001',
                    title: 'Lock Renew Atomicity Heuristic',
                    category: 'reliability',
                    severity: Severity::Critical,
                    confidence: Confidence::Low,
                    message: 'Lock renew may not be owner-scoped or atomic.',
                    impact: 'Split-brain risk possible.',
                    recommendation: 'Use owner-scoped atomic renew (Lua script).',
                    effort: 'medium',
                    tags: ['invariant', 'locking', 'redis'],
                    evidence: [$evidence],
                    fingerprint: $fingerprint,
                    advisoryOnly: false,
                    invariantFailure: true,
                );
                ++$index;
            }
        }

        return new RuleResult($this->metadata(), $findings);
    }
}
