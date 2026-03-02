<?php

declare(strict_types=1);

namespace ProdAudit\Audit\Rules;

final class Finding
{
    /**
     * @param array<int, string> $tags
     * @param array<int, Evidence> $evidence
     */
    public function __construct(
        public readonly string $id,
        public readonly string $ruleId,
        public readonly string $title,
        public readonly string $category,
        public readonly Severity $severity,
        public readonly Confidence $confidence,
        public readonly string $message,
        public readonly string $impact,
        public readonly string $recommendation,
        public readonly string $effort,
        public readonly array $tags,
        public readonly array $evidence,
        public readonly string $fingerprint,
        public readonly bool $advisoryOnly = false,
        public readonly bool $invariantFailure = false,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'rule_id' => $this->ruleId,
            'title' => $this->title,
            'category' => $this->category,
            'severity' => $this->severity->value,
            'confidence' => $this->confidence->value,
            'message' => $this->message,
            'impact' => $this->impact,
            'recommendation' => $this->recommendation,
            'effort' => $this->effort,
            'tags' => $this->tags,
            'evidence' => array_map(static fn (Evidence $evidence): array => $evidence->toArray(), $this->evidence),
            'fingerprint' => $this->fingerprint,
            'advisory_only' => $this->advisoryOnly,
            'invariant_failure' => $this->invariantFailure,
        ];
    }
}
