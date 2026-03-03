<?php

declare(strict_types=1);

namespace ProdAudit\Audit\Quality;

final class RuleQualityRecord
{
    public function __construct(
        public readonly string $ruleId,
        public readonly string $category,
        public readonly string $pack,
        public readonly bool $invariant,
        public readonly int $findingsCount,
        public readonly int $labeledCount,
        public readonly float $truePositiveRate,
        public readonly float $falsePositiveRate,
        public readonly float $noisyRate,
        public readonly float $persistenceRate,
        public readonly float $churnCorrelation,
        public readonly float $noiseScore,
        public readonly float $precisionScore,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'rule_id' => $this->ruleId,
            'category' => $this->category,
            'pack' => $this->pack,
            'invariant' => $this->invariant,
            'findings_count' => $this->findingsCount,
            'labeled_count' => $this->labeledCount,
            'true_positive_rate' => $this->truePositiveRate,
            'false_positive_rate' => $this->falsePositiveRate,
            'noisy_rate' => $this->noisyRate,
            'persistence_rate' => $this->persistenceRate,
            'churn_correlation' => $this->churnCorrelation,
            'noise_score' => $this->noiseScore,
            'precision_score' => $this->precisionScore,
        ];
    }
}
