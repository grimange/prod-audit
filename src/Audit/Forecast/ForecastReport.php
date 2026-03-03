<?php

declare(strict_types=1);

namespace ProdAudit\Audit\Forecast;

final class ForecastReport
{
    /**
     * @param array<string, float> $riskRulePackRegression
     * @param array<int, array<string, mixed>> $topDrivers
     * @param array<int, array<string, mixed>> $nextChecks
     */
    public function __construct(
        public readonly float $riskNewInvariantFail,
        public readonly float $riskScoreDrop5,
        public readonly float $riskNewCritical,
        public readonly array $riskRulePackRegression,
        public readonly array $topDrivers,
        public readonly array $nextChecks,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'risk_new_invariant_fail' => $this->riskNewInvariantFail,
            'risk_score_drop_5' => $this->riskScoreDrop5,
            'risk_new_critical' => $this->riskNewCritical,
            'risk_rule_pack_regression' => $this->riskRulePackRegression,
            'top_drivers' => $this->topDrivers,
            'next_checks' => $this->nextChecks,
        ];
    }
}
