<?php

declare(strict_types=1);

namespace ProdAudit\Audit\Insights;

use ProdAudit\Audit\Triage\TriageEvent;

final class NoiseModel
{
    /**
     * @param array<int, array<string, mixed>> $findings
     * @param array<string, string> $effectiveLabels
     * @return array{noise_by_rule: array<string, float>, noise_by_fingerprint: array<string, float>, overall_noise_score: float}
     */
    public function compute(array $findings, array $effectiveLabels): array
    {
        $ruleLabelTotals = [];
        $ruleNoiseTotals = [];

        foreach ($effectiveLabels as $fingerprint => $label) {
            $ruleId = $this->ruleIdForFingerprint($findings, $fingerprint);
            if ($ruleId === null) {
                continue;
            }

            $ruleLabelTotals[$ruleId] = ($ruleLabelTotals[$ruleId] ?? 0) + 1;
            if ($this->isNoiseLabel($label)) {
                $ruleNoiseTotals[$ruleId] = ($ruleNoiseTotals[$ruleId] ?? 0) + 1;
            }
        }

        $noiseByRule = [];
        foreach ($ruleLabelTotals as $ruleId => $total) {
            $noiseByRule[$ruleId] = $total > 0
                ? round(($ruleNoiseTotals[$ruleId] ?? 0) / $total, 6)
                : 0.0;
        }
        ksort($noiseByRule, SORT_STRING);

        $noiseByFingerprint = [];
        foreach ($findings as $finding) {
            if (!is_array($finding)) {
                continue;
            }

            $fingerprint = (string) ($finding['fingerprint'] ?? '');
            $ruleId = (string) ($finding['rule_id'] ?? '');
            if ($fingerprint === '' || $ruleId === '') {
                continue;
            }

            $label = $effectiveLabels[$fingerprint] ?? null;
            if (is_string($label) && $label !== '') {
                $noiseByFingerprint[$fingerprint] = $this->isNoiseLabel($label) ? 1.0 : 0.0;
                continue;
            }

            $noiseByFingerprint[$fingerprint] = $noiseByRule[$ruleId] ?? 0.0;
        }
        ksort($noiseByFingerprint, SORT_STRING);

        $overall = 0.0;
        if ($noiseByFingerprint !== []) {
            $overall = round(array_sum($noiseByFingerprint) / count($noiseByFingerprint), 6);
        }

        return [
            'noise_by_rule' => $noiseByRule,
            'noise_by_fingerprint' => $noiseByFingerprint,
            'overall_noise_score' => $overall,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $findings
     */
    private function ruleIdForFingerprint(array $findings, string $fingerprint): ?string
    {
        foreach ($findings as $finding) {
            if (!is_array($finding)) {
                continue;
            }

            if (($finding['fingerprint'] ?? null) !== $fingerprint) {
                continue;
            }

            $ruleId = (string) ($finding['rule_id'] ?? '');
            return $ruleId === '' ? null : $ruleId;
        }

        return null;
    }

    private function isNoiseLabel(string $label): bool
    {
        return in_array($label, [TriageEvent::LABEL_FALSE_POSITIVE, TriageEvent::LABEL_NOISY], true);
    }
}
