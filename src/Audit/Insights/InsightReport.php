<?php

declare(strict_types=1);

namespace ProdAudit\Audit\Insights;

final class InsightReport
{
    /**
     * @param array<string, float> $noiseByRule
     * @param array<string, float> $stabilityByRule
     * @param array<int, array<string, mixed>> $topPersistentFingerprints
     * @param array<int, array<string, mixed>> $topNoisyRules
     * @param array<int, array<string, mixed>> $hotspots
     * @param array<int, array<string, mixed>> $prioritizedFindings
     * @param array<string, string> $confidenceCalibration
     * @param array<int, array<string, mixed>> $recentlyFixedStreaks
     */
    public function __construct(
        public readonly array $noiseByRule,
        public readonly array $stabilityByRule,
        public readonly array $topPersistentFingerprints,
        public readonly array $topNoisyRules,
        public readonly array $hotspots,
        public readonly array $prioritizedFindings,
        public readonly array $confidenceCalibration,
        public readonly float $noiseScore,
        public readonly float $stabilityScore,
        public readonly array $recentlyFixedStreaks,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'noise_by_rule' => $this->noiseByRule,
            'stability_by_rule' => $this->stabilityByRule,
            'top_persistent_fingerprints' => $this->topPersistentFingerprints,
            'top_noisy_rules' => $this->topNoisyRules,
            'hotspots' => $this->hotspots,
            'prioritized_findings' => $this->prioritizedFindings,
            'confidence_calibration' => $this->confidenceCalibration,
            'noise_score' => $this->noiseScore,
            'stability_score' => $this->stabilityScore,
            'recently_fixed_streaks' => $this->recentlyFixedStreaks,
        ];
    }
}
