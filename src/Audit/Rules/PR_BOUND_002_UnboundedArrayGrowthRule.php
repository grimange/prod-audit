<?php

declare(strict_types=1);

namespace ProdAudit\Audit\Rules;

use ProdAudit\Utils\Fingerprint;

final class PR_BOUND_002_UnboundedArrayGrowthRule implements RuleInterface
{
    private readonly EvidenceFactory $evidenceFactory;

    public function __construct(?EvidenceFactory $evidenceFactory = null)
    {
        $this->evidenceFactory = $evidenceFactory ?? new EvidenceFactory();
    }

    public function metadata(): RuleMetadata
    {
        return new RuleMetadata(
            id: 'PR-BOUND-002',
            title: 'Unbounded In-Memory Array Growth',
            invariant: false,
            category: Category::BOUNDS->value,
            pack: 'bounds',
            defaultSeverity: Severity::Major,
            description: 'Detects array growth inside infinite loops without clear reset or bound checks.',
            whyItMatters: 'Unbounded memory growth leads to OOM kills and service instability.',
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

            if (!((bool) ($loop['body_inspected'] ?? false))) {
                continue;
            }

            if (!((bool) ($loop['has_array_growth'] ?? false))) {
                continue;
            }

            if ((bool) ($loop['has_reset_or_bound'] ?? false)) {
                continue;
            }

            $evidence = $this->evidenceFactory->fromLocation(
                type: 'ast_node',
                file: (string) ($loop['file'] ?? ''),
                startLine: (int) ($loop['start_line'] ?? 1),
                endLine: (int) ($loop['end_line'] ?? 1),
                excerpt: (string) ($loop['snippet'] ?? ''),
            );

            $findings[] = new Finding(
                id: sprintf('PR-BOUND-002-%03d', $index),
                ruleId: 'PR-BOUND-002',
                title: 'Unbounded In-Memory Array Growth',
                category: Category::BOUNDS->value,
                severity: Severity::Major,
                confidence: Confidence::Medium,
                message: 'Infinite loop appends to in-memory array without bound/reset.',
                impact: 'Memory footprint can grow without limit in long-running workers.',
                recommendation: 'Apply bounded buffering, compaction, or external persistence.',
                effort: 'medium',
                tags: ['bounds', 'memory', 'loop'],
                evidence: [$evidence],
                fingerprint: Fingerprint::fromEvidence('PR-BOUND-002', [$evidence]),
            );
            ++$index;
        }

        return new RuleResult($this->metadata(), $findings);
    }
}
