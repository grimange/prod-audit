<?php

declare(strict_types=1);

namespace ProdAudit\Audit\Rules;

use ProdAudit\Utils\Fingerprint;

final class PR_OBS_005_MissingErrorLogsRule implements RuleInterface
{
    private readonly EvidenceFactory $evidenceFactory;

    public function __construct(?EvidenceFactory $evidenceFactory = null)
    {
        $this->evidenceFactory = $evidenceFactory ?? new EvidenceFactory();
    }

    public function metadata(): RuleMetadata
    {
        return new RuleMetadata(
            id: 'PR-OBS-005',
            title: 'Missing Error Logs',
            invariant: false,
            category: Category::OBSERVABILITY->value,
            pack: 'observability',
            defaultSeverity: Severity::Major,
            description: 'Detects catch blocks that suppress exceptions without emitting error logs.',
            whyItMatters: 'Missing error logs creates blind spots during production incidents.',
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

            if ((bool) ($catchBlock['has_observability_call'] ?? false) || (bool) ($catchBlock['has_rethrow'] ?? false)) {
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
                id: sprintf('PR-OBS-005-%03d', $index),
                ruleId: 'PR-OBS-005',
                title: 'Missing Error Logs',
                category: Category::OBSERVABILITY->value,
                severity: Severity::Major,
                confidence: Confidence::High,
                message: 'Exception handling path has no error logging or escalation.',
                impact: 'Production failures can occur without incident breadcrumbs.',
                recommendation: 'Emit structured error logs before handling or propagating exceptions.',
                effort: 'small',
                tags: ['observability', 'errors', 'logging'],
                evidence: [$evidence],
                fingerprint: Fingerprint::fromEvidence('PR-OBS-005', [$evidence]),
            );
            ++$index;
        }

        return new RuleResult($this->metadata(), $findings);
    }
}
