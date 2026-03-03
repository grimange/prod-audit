<?php

declare(strict_types=1);

namespace ProdAudit\Audit\Rules;

use ProdAudit\Audit\Config\Config;
use ProdAudit\Utils\Fingerprint;

final class PR_ERR_001_SwallowedExceptionsRule implements RuleInterface
{
    private readonly EvidenceFactory $evidenceFactory;

    public function __construct(?EvidenceFactory $evidenceFactory = null)
    {
        $this->evidenceFactory = $evidenceFactory ?? new EvidenceFactory();
    }

    public function metadata(): RuleMetadata
    {
        return new RuleMetadata(
            id: 'PR-ERR-001',
            title: 'Swallowed Exceptions',
            invariant: false,
            category: Category::ERRORS->value,
            pack: 'error-handling',
            defaultSeverity: Severity::Major,
            description: 'Detects catch blocks that swallow exceptions without escalation or logging.',
            whyItMatters: 'Swallowed exceptions hide production failures and extend incident duration.',
        );
    }

    public function evaluate(array $collectorData): RuleResult
    {
        $findings = [];
        $index = 1;
        $ast = $collectorData['ast'] ?? [];
        $catchBlocks = is_array($ast['catch_blocks'] ?? null) ? $ast['catch_blocks'] : [];
        foreach ($catchBlocks as $catchBlock) {
            if (!is_array($catchBlock)) {
                continue;
            }

            $isEmpty = (bool) ($catchBlock['is_empty'] ?? false);
            $onlyControlFlow = (bool) ($catchBlock['only_control_flow'] ?? false);
            $hasRethrow = (bool) ($catchBlock['has_rethrow'] ?? false);
            $hasObservabilityCall = (bool) ($catchBlock['has_observability_call'] ?? false);
            $snippet = (string) ($catchBlock['snippet'] ?? '');
            $hasIntentionalMarker = $this->allowIntentionalMarker($collectorData) && preg_match('/intentional/i', $snippet) === 1;

            if ((!$isEmpty && !$onlyControlFlow) || $hasRethrow || $hasObservabilityCall || $hasIntentionalMarker) {
                continue;
            }

            $evidence = $this->evidenceFactory->fromLocation(
                type: 'ast_node',
                file: (string) ($catchBlock['file'] ?? ''),
                startLine: (int) ($catchBlock['start_line'] ?? 1),
                endLine: (int) ($catchBlock['end_line'] ?? 1),
                excerpt: $snippet
            );

            $findings[] = $this->newFinding($index, $evidence, Confidence::High);
            ++$index;
        }

        $parsedOkFiles = $this->parsedOkFiles($ast['files'] ?? []);
        $matches = $collectorData['patterns']['exceptions'] ?? [];
        if (is_array($matches)) {
            foreach ($matches as $match) {
                if (!is_array($match)) {
                    continue;
                }

                $file = (string) ($match['file'] ?? '');
                if (isset($parsedOkFiles[$file])) {
                    continue;
                }

                $excerpt = (string) ($match['excerpt'] ?? '');
                if ($this->allowIntentionalMarker($collectorData) && preg_match('/intentional/i', $excerpt) === 1) {
                    continue;
                }
                if ($excerpt === '' || !$this->looksLikeSwallowedCatch($excerpt)) {
                    continue;
                }

                $line = (int) ($match['line'] ?? 1);
                $line = $line > 0 ? $line : 1;
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

    private function looksLikeSwallowedCatch(string $excerpt): bool
    {
        if (preg_match('/catch\s*\([^)]+\)\s*\{\s*\}\s*$/i', $excerpt) === 1) {
            return true;
        }

        return preg_match('/catch\s*\([^)]+\)\s*\{\s*(?:return|break|continue)\s*;\s*\}\s*$/i', $excerpt) === 1;
    }

    private function newFinding(int $index, Evidence $evidence, Confidence $confidence): Finding
    {
        $fingerprint = Fingerprint::fromEvidence('PR-ERR-001', [$evidence]);

        return new Finding(
            id: sprintf('PR-ERR-001-%03d', $index),
            ruleId: 'PR-ERR-001',
            title: 'Swallowed Exceptions',
            category: Category::ERRORS->value,
            severity: Severity::Major,
            confidence: $confidence,
            message: 'Exception caught but not handled or escalated',
            impact: 'Silent failures may hide production errors.',
            recommendation: 'Log or rethrow exceptions.',
            effort: 'small',
            tags: ['exceptions', 'error-handling'],
            evidence: [$evidence],
            fingerprint: $fingerprint,
        );
    }

    /**
     * @param array<string, mixed> $collectorData
     */
    private function allowIntentionalMarker(array $collectorData): bool
    {
        $config = new Config(is_array($collectorData['config'] ?? null) ? $collectorData['config'] : []);

        return $config->bool('PR-ERR-001', 'allow_intentional_marker', true);
    }
}
