<?php

declare(strict_types=1);

namespace ProdAudit\Audit\Policy;

final class PolicyEvaluator
{
    /**
     * @param array<string, mixed> $report
     * @return array{pass: bool, reasons: array<int, string>, recommended_actions: array<int, string>}
     */
    public function evaluate(Policy $policy, array $report): array
    {
        $findings = is_array($report['findings'] ?? null) ? $report['findings'] : [];
        $trend = is_array($report['trend'] ?? null) ? $report['trend'] : [];

        /** @var array<string, array<string, mixed>> $findingsByFingerprint */
        $findingsByFingerprint = [];
        foreach ($findings as $finding) {
            if (!is_array($finding)) {
                continue;
            }

            $fingerprint = (string) ($finding['fingerprint'] ?? '');
            if ($fingerprint === '') {
                continue;
            }

            $findingsByFingerprint[$fingerprint] = $finding;
        }
        ksort($findingsByFingerprint, SORT_STRING);

        $newFingerprints = is_array($trend['new_fingerprints'] ?? null) ? $trend['new_fingerprints'] : [];
        sort($newFingerprints, SORT_STRING);

        $newCritical = 0;
        $newMajor = 0;
        $newInvariant = 0;

        foreach ($newFingerprints as $fingerprint) {
            if (!is_string($fingerprint) || !isset($findingsByFingerprint[$fingerprint])) {
                continue;
            }

            $finding = $findingsByFingerprint[$fingerprint];
            $severity = (string) ($finding['severity'] ?? '');

            if ($severity === 'critical') {
                ++$newCritical;
            }
            if ($severity === 'major') {
                ++$newMajor;
            }
            if ((bool) ($finding['invariant_failure'] ?? false)) {
                ++$newInvariant;
            }
        }

        $reasons = [];
        $actions = [];

        if ($newCritical > $policy->maxNewCritical) {
            $reasons[] = sprintf('new critical findings %d exceeds max %d', $newCritical, $policy->maxNewCritical);
            $actions[] = 'Remediate new critical findings before merge.';
        }

        if ($newMajor > $policy->maxNewMajor) {
            $reasons[] = sprintf('new major findings %d exceeds max %d', $newMajor, $policy->maxNewMajor);
            $actions[] = 'Reduce newly introduced major findings in this change set.';
        }

        if ($policy->requireNoNewInvariants && $newInvariant > 0) {
            $reasons[] = sprintf('new invariant failures detected: %d', $newInvariant);
            $actions[] = 'Resolve invariant failures; policy requires zero new invariants.';
        }

        if ($policy->noRegressions && ((bool) ($report['regression'] ?? false))) {
            $reasons[] = 'regression detected under no-regressions policy';
            $actions[] = 'Address regression reasons in trend analysis.';
        }

        $reasons = array_values(array_unique($reasons));
        $actions = array_values(array_unique($actions));
        sort($reasons, SORT_STRING);
        sort($actions, SORT_STRING);

        return [
            'pass' => $reasons === [],
            'reasons' => $reasons,
            'recommended_actions' => $actions,
        ];
    }
}
