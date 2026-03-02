<?php

declare(strict_types=1);

namespace ProdAudit\Audit\Rules;

use ProdAudit\Utils\Fingerprint;

final class PR_HANG_001_InfiniteLoopRule implements InvariantRuleInterface
{
    private readonly EvidenceFactory $evidenceFactory;

    public function __construct(?EvidenceFactory $evidenceFactory = null)
    {
        $this->evidenceFactory = $evidenceFactory ?? new EvidenceFactory();
    }

    public function metadata(): RuleMetadata
    {
        return new RuleMetadata(
            id: 'PR-HANG-001',
            title: 'Infinite Loop Without Yield',
            description: 'Detects infinite loop patterns without sleep, yield, heartbeat, or timeout hints.',
            invariant: true,
        );
    }

    public function evaluate(array $collectorData): RuleResult
    {
        $findings = [];
        $index = 1;
        $ast = $collectorData['ast'] ?? [];
        $loops = is_array($ast['loops'] ?? null) ? $ast['loops'] : [];
        foreach ($loops as $loop) {
            if (!is_array($loop)) {
                continue;
            }

            $hasGuard = (bool) ($loop['has_sleep'] ?? false)
                || (bool) ($loop['has_yield'] ?? false)
                || (bool) ($loop['has_timeout_check'] ?? false)
                || (bool) ($loop['has_budget_decrement'] ?? false)
                || (bool) ($loop['has_heartbeat_call'] ?? false);

            if ($hasGuard || !((bool) ($loop['body_inspected'] ?? false))) {
                continue;
            }

            $evidence = $this->evidenceFactory->fromLocation(
                type: 'ast_node',
                file: (string) ($loop['file'] ?? ''),
                startLine: (int) ($loop['start_line'] ?? 1),
                endLine: (int) ($loop['end_line'] ?? 1),
                excerpt: (string) ($loop['snippet'] ?? '')
            );

            $findings[] = $this->newFinding($index, $evidence, Confidence::High);
            ++$index;
        }

        $parsedOkFiles = $this->parsedOkFiles($ast['files'] ?? []);
        $matches = $collectorData['patterns']['loops'] ?? [];
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
                $hasYieldHint = false;
                $loopMatches = [];
                foreach ($fileMatches as $match) {
                    $excerpt = (string) ($match['excerpt'] ?? '');
                    if (preg_match('/\bwhile\s*\(\s*true\s*\)|\bfor\s*\(\s*;\s*;\s*\)/i', $excerpt) === 1) {
                        $loopMatches[] = $match;
                    }
                    if (preg_match('/\bsleep\s*\(|\busleep\s*\(|->sleep\s*\(|\byield\b|\btimeout\b|\bheartbeat\b/i', $excerpt) === 1) {
                        $hasYieldHint = true;
                    }
                }

                if ($hasYieldHint) {
                    continue;
                }

                foreach ($loopMatches as $match) {
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

                    $findings[] = $this->newFinding($index, $evidence, Confidence::Medium);
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
        $fingerprint = Fingerprint::fromEvidence('PR-HANG-001', [$evidence]);

        return new Finding(
            id: sprintf('PR-HANG-001-%03d', $index),
            ruleId: 'PR-HANG-001',
            title: 'Infinite Loop Without Yield',
            category: 'reliability',
            severity: Severity::Critical,
            confidence: $confidence,
            message: 'Infinite loop without yield or timeout.',
            impact: 'May cause CPU starvation or uninterruptible hangs.',
            recommendation: 'Add sleep/yield or timeout checks.',
            effort: 'small',
            tags: ['invariant', 'loop', 'availability'],
            evidence: [$evidence],
            fingerprint: $fingerprint,
            advisoryOnly: false,
            invariantFailure: true,
        );
    }
}
