<?php

declare(strict_types=1);

namespace ProdAudit\Audit\Tasks;

final class TaskRecommendation
{
    /**
     * @param array<int, string> $relatedRules
     * @param array<int, string> $steps
     * @param array<int, string> $evidenceRefs
     */
    public function __construct(
        public readonly string $id,
        public readonly string $title,
        public readonly string $why,
        public readonly array $relatedRules,
        public readonly string $effort,
        public readonly string $riskReduction,
        public readonly array $steps,
        public readonly array $evidenceRefs,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'why' => $this->why,
            'related_rules' => $this->relatedRules,
            'effort' => $this->effort,
            'risk_reduction' => $this->riskReduction,
            'steps' => $this->steps,
            'evidence_refs' => $this->evidenceRefs,
        ];
    }
}
