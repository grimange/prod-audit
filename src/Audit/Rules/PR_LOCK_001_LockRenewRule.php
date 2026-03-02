<?php

declare(strict_types=1);

namespace ProdAudit\Audit\Rules;

use ProdAudit\Utils\Fingerprint;

final class PR_LOCK_001_LockRenewRule implements InvariantRuleInterface
{
    private readonly EvidenceFactory $evidenceFactory;

    public function __construct(?EvidenceFactory $evidenceFactory = null)
    {
        $this->evidenceFactory = $evidenceFactory ?? new EvidenceFactory();
    }

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
        $findings = [];
        $index = 1;
        $ast = $collectorData['ast'] ?? [];
        $scopes = is_array($ast['scopes'] ?? null) ? $ast['scopes'] : [];
        foreach ($scopes as $scope) {
            if (!is_array($scope)) {
                continue;
            }

            $calls = is_array($scope['calls'] ?? null) ? $scope['calls'] : [];
            $hasLuaEval = false;
            $expireCalls = [];

            foreach ($calls as $call) {
                if (!is_array($call)) {
                    continue;
                }

                $name = strtolower((string) ($call['name'] ?? ''));
                $target = strtolower((string) ($call['target'] ?? ''));
                $isRedisTarget = $target === '' || str_contains($target, 'redis');

                if (($name === 'eval' || $name === 'evalsha') && (bool) ($call['has_lua_arg'] ?? false)) {
                    $hasLuaEval = true;
                }

                if (($name === 'expire' || $name === 'pexpire' || $name === 'setex') && $isRedisTarget) {
                    $expireCalls[] = $call;
                }
            }

            if ($expireCalls === [] || $hasLuaEval) {
                continue;
            }

            foreach ($expireCalls as $call) {
                $lineStart = (int) ($call['start_line'] ?? 1);
                $lineEnd = (int) ($call['end_line'] ?? $lineStart);
                $evidence = $this->evidenceFactory->fromLocation(
                    type: 'ast_node',
                    file: (string) ($scope['file'] ?? ''),
                    startLine: $lineStart,
                    endLine: $lineEnd,
                    excerpt: sprintf('%s call without eval/evalsha in scope', (string) ($call['name'] ?? 'expire'))
                );

                $findings[] = $this->newFinding($index, $evidence, Confidence::Medium);
                ++$index;
            }
        }

        $parsedOkFiles = $this->parsedOkFiles($ast['files'] ?? []);
        $matches = $collectorData['patterns']['redis'] ?? [];
        if (is_array($matches)) {
            $byFile = [];
            foreach ($matches as $match) {
                if (!is_array($match)) {
                    continue;
                }

                $file = (string) ($match['file'] ?? '');
                if ($file === '' || isset($parsedOkFiles[$file])) {
                    continue;
                }

                $byFile[$file][] = $match;
            }

            ksort($byFile, SORT_STRING);

            foreach ($byFile as $file => $fileMatches) {
                $hasOwnerScope = false;
                $hasAtomicityHint = false;
                $renewMatches = [];

                foreach ($fileMatches as $match) {
                    $excerpt = (string) ($match['excerpt'] ?? '');
                    if (preg_match('/->\s*(?:pexpire|expire|setex)\s*\(/i', $excerpt) === 1) {
                        $renewMatches[] = $match;
                    }
                    if (preg_match('/\b(owner|token)\b/i', $excerpt) === 1) {
                        $hasOwnerScope = true;
                    }
                    if (preg_match('/\b(lua|eval)\b|->\s*eval(?:sha)?\s*\(/i', $excerpt) === 1) {
                        $hasAtomicityHint = true;
                    }
                }

                if ($renewMatches === [] || $hasOwnerScope || $hasAtomicityHint) {
                    continue;
                }

                foreach ($renewMatches as $match) {
                    $line = (int) ($match['line'] ?? 1);
                    $line = $line > 0 ? $line : 1;
                    $excerpt = (string) ($match['excerpt'] ?? '');
                    $evidence = $this->evidenceFactory->fromLocation(
                        type: 'file_snippet',
                        file: $file,
                        startLine: $line,
                        endLine: $line + substr_count($excerpt, "\n"),
                        excerpt: $excerpt
                    );

                    $findings[] = $this->newFinding($index, $evidence, Confidence::Low);
                    ++$index;
                }
            }
        }

        return new RuleResult($this->metadata(), $findings);
    }

    /**
     * @param array<string, mixed> $files
     * @return array<string, true>
     */
    private function parsedOkFiles(array $files): array
    {
        $result = [];
        foreach ($files as $file => $status) {
            if (!is_array($status)) {
                continue;
            }

            if (($status['status'] ?? 'error') === 'ok') {
                $result[(string) $file] = true;
            }
        }

        return $result;
    }

    private function newFinding(int $index, Evidence $evidence, Confidence $confidence): Finding
    {
        $fingerprint = Fingerprint::fromEvidence('PR-LOCK-001', [$evidence]);

        return new Finding(
            id: sprintf('PR-LOCK-001-%03d', $index),
            ruleId: 'PR-LOCK-001',
            title: 'Lock Renew Atomicity Heuristic',
            category: 'reliability',
            severity: Severity::Critical,
            confidence: $confidence,
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
    }
}
