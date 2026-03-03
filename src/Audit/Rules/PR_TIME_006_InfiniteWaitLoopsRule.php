<?php

declare(strict_types=1);

namespace ProdAudit\Audit\Rules;

use ProdAudit\Utils\Fingerprint;

final class PR_TIME_006_InfiniteWaitLoopsRule implements RuleInterface
{
    private readonly EvidenceFactory $evidenceFactory;

    public function __construct(?EvidenceFactory $evidenceFactory = null)
    {
        $this->evidenceFactory = $evidenceFactory ?? new EvidenceFactory();
    }

    public function metadata(): RuleMetadata
    {
        return new RuleMetadata(
            id: 'PR-TIME-006',
            title: 'Infinite Wait Loops',
            invariant: false,
            category: Category::TIMEOUTS->value,
            pack: 'timeout',
            defaultSeverity: Severity::Major,
            description: 'Detects infinite loop structures that wait without explicit timeout budget checks.',
            whyItMatters: 'Infinite wait loops can stall workers and delay graceful failure recovery.',
        );
    }

    public function evaluate(array $collectorData): RuleResult
    {
        $ast = $collectorData['ast'] ?? [];
        $loops = is_array($ast['loops'] ?? null) ? $ast['loops'] : [];

        $findings = [];
        $index = 1;
        foreach ($loops as $loop) {
            if (!is_array($loop)) {
                continue;
            }

            if (!((bool) ($loop['body_inspected'] ?? false))) {
                continue;
            }

            if (!((bool) ($loop['has_sleep'] ?? false) || (bool) ($loop['has_yield'] ?? false))) {
                continue;
            }

            if ((bool) ($loop['has_timeout_check'] ?? false) || (bool) ($loop['has_budget_decrement'] ?? false)) {
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
                id: sprintf('PR-TIME-006-%03d', $index),
                ruleId: 'PR-TIME-006',
                title: 'Infinite Wait Loops',
                category: Category::TIMEOUTS->value,
                severity: Severity::Major,
                confidence: Confidence::High,
                message: 'Infinite wait loop has no explicit timeout budget.',
                impact: 'Blocked workers may persist indefinitely during dependency outages.',
                recommendation: 'Add timeout budget tracking and termination criteria.',
                effort: 'small',
                tags: ['timeouts', 'loops', 'availability'],
                evidence: [$evidence],
                fingerprint: Fingerprint::fromEvidence('PR-TIME-006', [$evidence]),
            );
            ++$index;
        }

        return new RuleResult($this->metadata(), $findings);
    }
}
