<?php

declare(strict_types=1);

namespace ProdAudit\Audit\Rules;

use ProdAudit\Utils\Fingerprint;

final class PR_ERR_004_SilentCatchWithFallbackReturnRule implements RuleInterface
{
    private readonly EvidenceFactory $evidenceFactory;

    public function __construct(?EvidenceFactory $evidenceFactory = null)
    {
        $this->evidenceFactory = $evidenceFactory ?? new EvidenceFactory();
    }

    public function metadata(): RuleMetadata
    {
        return new RuleMetadata(
            id: 'PR-ERR-004',
            title: 'Silent Catch With Fallback Return',
            invariant: false,
            category: Category::ERRORS->value,
            pack: 'error-handling',
            defaultSeverity: Severity::Major,
            description: 'Detects catch blocks that only return fallback control-flow values.',
            whyItMatters: 'Silent fallback returns hide primary failures and produce silent data corruption.',
        );
    }

    public function evaluate(array $collectorData): RuleResult
    {
        $ast = $collectorData['ast'] ?? [];
        $catchBlocks = is_array($ast['catch_blocks'] ?? null) ? $ast['catch_blocks'] : [];

        $findings = [];
        $index = 1;
        foreach ($catchBlocks as $catchBlock) {
            if (!is_array($catchBlock)) {
                continue;
            }

            if (!((bool) ($catchBlock['only_control_flow'] ?? false))) {
                continue;
            }

            if ((bool) ($catchBlock['has_rethrow'] ?? false) || (bool) ($catchBlock['has_observability_call'] ?? false)) {
                continue;
            }

            $evidence = $this->evidenceFactory->fromLocation(
                type: 'ast_node',
                file: (string) ($catchBlock['file'] ?? ''),
                startLine: (int) ($catchBlock['start_line'] ?? 1),
                endLine: (int) ($catchBlock['end_line'] ?? 1),
                excerpt: (string) ($catchBlock['snippet'] ?? ''),
            );

            $findings[] = new Finding(
                id: sprintf('PR-ERR-004-%03d', $index),
                ruleId: 'PR-ERR-004',
                title: 'Silent Catch With Fallback Return',
                category: Category::ERRORS->value,
                severity: Severity::Major,
                confidence: Confidence::High,
                message: 'Catch block silently returns fallback control flow without escalation.',
                impact: 'Critical exceptions may be converted into silent fallback behavior.',
                recommendation: 'Log and escalate exceptions instead of silent fallback returns.',
                effort: 'small',
                tags: ['errors', 'fallback', 'exceptions'],
                evidence: [$evidence],
                fingerprint: Fingerprint::fromEvidence('PR-ERR-004', [$evidence]),
            );
            ++$index;
        }

        return new RuleResult($this->metadata(), $findings);
    }
}
