<?php

declare(strict_types=1);

namespace ProdAudit\Audit\Rules;

use ProdAudit\Utils\Fingerprint;

final class PR_HANG_001_InfiniteLoopRule implements InvariantRuleInterface
{
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
        $matches = $collectorData['patterns']['loops'] ?? [];
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
            $hasYieldHint = false;
            $loopMatches = [];

            foreach ($fileMatches as $match) {
                $excerpt = (string) ($match['excerpt'] ?? '');

                if ($this->isInfiniteLoop($excerpt)) {
                    $loopMatches[] = $match;
                }

                if ($this->hasYieldOrTimeout($excerpt)) {
                    $hasYieldHint = true;
                }
            }

            if ($hasYieldHint) {
                continue;
            }

            foreach ($loopMatches as $match) {
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

                $fingerprint = Fingerprint::fromEvidence('PR-HANG-001', [$evidence]);
                $findings[] = new Finding(
                    id: sprintf('PR-HANG-001-%03d', $index),
                    ruleId: 'PR-HANG-001',
                    title: 'Infinite Loop Without Yield',
                    category: 'reliability',
                    severity: Severity::Critical,
                    confidence: Confidence::Medium,
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
                ++$index;
            }
        }

        return new RuleResult($this->metadata(), $findings);
    }

    private function isInfiniteLoop(string $excerpt): bool
    {
        return preg_match('/\bwhile\s*\(\s*true\s*\)|\bfor\s*\(\s*;\s*;\s*\)/i', $excerpt) === 1;
    }

    private function hasYieldOrTimeout(string $excerpt): bool
    {
        return preg_match('/\bsleep\s*\(|\busleep\s*\(|->sleep\s*\(|\byield\b|\btimeout\b|\bheartbeat\b/i', $excerpt) === 1;
    }
}
