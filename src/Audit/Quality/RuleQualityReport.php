<?php

declare(strict_types=1);

namespace ProdAudit\Audit\Quality;

final class RuleQualityReport
{
    /**
     * @param array<int, RuleQualityRecord> $records
     * @param array<int, array<string, mixed>> $topNoisyRules
     * @param array<int, array<string, mixed>> $topValuableRules
     */
    public function __construct(
        public readonly array $records,
        public readonly array $topNoisyRules,
        public readonly array $topValuableRules,
        public readonly float $overallNoiseScore,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'overall_noise_score' => $this->overallNoiseScore,
            'rules' => array_map(static fn (RuleQualityRecord $record): array => $record->toArray(), $this->records),
            'top_noisy_rules' => $this->topNoisyRules,
            'top_valuable_rules' => $this->topValuableRules,
        ];
    }
}
