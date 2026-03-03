<?php

declare(strict_types=1);

namespace ProdAudit\Audit\Rules;

use ProdAudit\Utils\Fingerprint;

final class PR_OBS_001_MissingLoggerContextRule implements RuleInterface
{
    private readonly EvidenceFactory $evidenceFactory;

    public function __construct(?EvidenceFactory $evidenceFactory = null)
    {
        $this->evidenceFactory = $evidenceFactory ?? new EvidenceFactory();
    }

    public function metadata(): RuleMetadata
    {
        return new RuleMetadata(
            id: 'PR-OBS-001',
            title: 'Missing Logger Correlation Context',
            invariant: false,
            category: Category::OBSERVABILITY->value,
            pack: 'observability',
            defaultSeverity: Severity::Minor,
            description: 'Detects logger info/error calls without context arrays.',
            whyItMatters: 'Missing correlation context slows incident triage and root-cause analysis.',
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
            foreach ($calls as $call) {
                if (!is_array($call) || !$this->isLoggerCall($call)) {
                    continue;
                }

                if ((int) ($call['arg_count'] ?? 0) !== 1) {
                    continue;
                }

                $evidence = $this->evidenceFactory->fromLocation(
                    type: 'ast_node',
                    file: (string) ($scope['file'] ?? ''),
                    startLine: (int) ($call['start_line'] ?? 1),
                    endLine: (int) ($call['end_line'] ?? 1),
                    excerpt: (string) ($call['snippet'] ?? ''),
                );

                $findings[] = new Finding(
                    id: sprintf('PR-OBS-001-%03d', $index),
                    ruleId: 'PR-OBS-001',
                    title: 'Missing Logger Correlation Context',
                    category: Category::OBSERVABILITY->value,
                    severity: Severity::Minor,
                    confidence: Confidence::Medium,
                    message: 'Logger call does not include correlation/context payload.',
                    impact: 'Investigation fidelity drops when logs cannot be correlated.',
                    recommendation: 'Include context arrays such as corr_id, request_id, or job_id.',
                    effort: 'small',
                    tags: ['observability', 'logging', 'context'],
                    evidence: [$evidence],
                    fingerprint: Fingerprint::fromEvidence('PR-OBS-001', [$evidence]),
                );
                ++$index;
            }
        }

        return new RuleResult($this->metadata(), $findings);
    }

    /**
     * @param array<string, mixed> $call
     */
    private function isLoggerCall(array $call): bool
    {
        $name = strtolower((string) ($call['name'] ?? ''));
        if (!in_array($name, ['debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency'], true)) {
            return false;
        }

        $target = strtolower((string) ($call['target'] ?? ''));

        return $target === 'log' || str_contains($target, 'logger');
    }
}
