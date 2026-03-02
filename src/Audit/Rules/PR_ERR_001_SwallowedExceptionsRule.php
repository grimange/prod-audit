<?php

declare(strict_types=1);

namespace ProdAudit\Audit\Rules;

use ProdAudit\Utils\Fingerprint;

final class PR_ERR_001_SwallowedExceptionsRule implements RuleInterface
{
    public function metadata(): RuleMetadata
    {
        return new RuleMetadata(
            id: 'PR-ERR-001',
            title: 'Swallowed Exceptions',
            description: 'Detects catch blocks that swallow exceptions without escalation or logging.',
            invariant: false,
        );
    }

    public function evaluate(array $collectorData): RuleResult
    {
        $matches = $collectorData['patterns']['exceptions'] ?? [];
        if (!is_array($matches)) {
            return new RuleResult($this->metadata(), []);
        }

        $findings = [];
        $index = 1;
        foreach ($matches as $match) {
            if (!is_array($match)) {
                continue;
            }

            $excerpt = (string) ($match['excerpt'] ?? '');
            if ($excerpt === '' || !$this->isSwallowedCatch($excerpt)) {
                continue;
            }

            $file = (string) ($match['file'] ?? '');
            $line = (int) ($match['line'] ?? 0);
            $line = $line > 0 ? $line : 1;

            $evidence = Evidence::create(
                type: 'file_snippet',
                file: $file,
                lineStart: $line,
                lineEnd: $line + substr_count($excerpt, "\n"),
                excerpt: $this->trimToMaxLines($excerpt, 10),
            );

            $fingerprint = Fingerprint::fromEvidence('PR-ERR-001', [$evidence]);
            $findings[] = new Finding(
                id: sprintf('PR-ERR-001-%03d', $index),
                ruleId: 'PR-ERR-001',
                title: 'Swallowed Exceptions',
                category: 'reliability',
                severity: Severity::Major,
                confidence: Confidence::High,
                message: 'Exception caught but not handled or escalated',
                impact: 'Silent failures may hide production errors.',
                recommendation: 'Log or rethrow exceptions.',
                effort: 'small',
                tags: ['exceptions', 'error-handling'],
                evidence: [$evidence],
                fingerprint: $fingerprint,
            );
            ++$index;
        }

        return new RuleResult($this->metadata(), $findings);
    }

    private function isSwallowedCatch(string $excerpt): bool
    {
        if (!preg_match('/catch\s*\([^)]+\)\s*\{(?P<body>[\s\S]*?)\}\s*$/i', $excerpt, $matches)) {
            return false;
        }

        $body = trim((string) ($matches['body'] ?? ''));
        if ($body === '') {
            return true;
        }

        if (preg_match('/^(return|break|continue)\s*;\s*$/i', $body) === 1) {
            return true;
        }

        if (preg_match('/\bthrow\b/i', $body) === 1) {
            return false;
        }

        if (preg_match('/\blogger\s*\(|\blog\s*\(/i', $body) === 1) {
            return false;
        }

        return false;
    }

    private function trimToMaxLines(string $excerpt, int $maxLines): string
    {
        $lines = preg_split('/\R/', $excerpt) ?: [];
        $lines = array_slice($lines, 0, $maxLines);

        return implode("\n", $lines);
    }
}
