<?php

declare(strict_types=1);

namespace ProdAudit\Audit\Actions;

final class Action
{
    /**
     * @param array<int, string> $ruleIds
     * @param array<int, string> $evidenceRefs
     */
    public function __construct(
        public readonly string $id,
        public readonly string $title,
        public readonly string $whyNow,
        public readonly array $ruleIds,
        public readonly array $evidenceRefs,
        public readonly float $priority,
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
            'why_now' => $this->whyNow,
            'rule_ids' => $this->ruleIds,
            'evidence_refs' => $this->evidenceRefs,
            'priority' => round($this->priority, 6),
        ];
    }
}
