<?php

declare(strict_types=1);

namespace ProdAudit\Audit\Rules;

use ProdAudit\Utils\Fingerprint;

final class PR_ERR_002_LoggingWithoutEscalationRule implements RuleInterface
{
    private readonly EvidenceFactory $evidenceFactory;

    public function __construct(?EvidenceFactory $evidenceFactory = null)
    {
        $this->evidenceFactory = $evidenceFactory ?? new EvidenceFactory();
    }

    public function metadata(): RuleMetadata
    {
        return new RuleMetadata(
            id: 'PR-ERR-002',
            title: 'Logging Without Escalation',
            invariant: false,
            category: Category::ERRORS->value,
            pack: 'error-handling',
            defaultSeverity: Severity::Major,
            description: 'Detects catch handlers that log but never rethrow/escalate failure.',
            whyItMatters: 'Logging-only catch handlers can mask hard failures and continue in corrupt state.',
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

            if (!((bool) ($catchBlock['has_observability_call'] ?? false))) {
                continue;
            }

            if ((bool) ($catchBlock['has_rethrow'] ?? false)) {
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
                id: sprintf('PR-ERR-002-%03d', $index),
                ruleId: 'PR-ERR-002',
                title: 'Logging Without Escalation',
                category: Category::ERRORS->value,
                severity: Severity::Major,
                confidence: Confidence::High,
                message: 'Exception is logged but not escalated or rethrown.',
                impact: 'Execution may continue after severe failures with inconsistent runtime state.',
                recommendation: 'Rethrow, wrap, or explicitly escalate after logging.',
                effort: 'small',
                tags: ['errors', 'exceptions', 'escalation'],
                evidence: [$evidence],
                fingerprint: Fingerprint::fromEvidence('PR-ERR-002', [$evidence]),
            );
            ++$index;
        }

        return new RuleResult($this->metadata(), $findings);
    }
}
