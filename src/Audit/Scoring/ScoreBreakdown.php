<?php

declare(strict_types=1);

namespace ProdAudit\Audit\Scoring;

final class ScoreBreakdown
{
    /**
     * @param array<int, array<string, int|string>> $penalties
     * @param array<int, array<string, int|string>> $capsApplied
     */
    public function __construct(
        public readonly int $startingScore,
        public readonly array $penalties,
        public readonly array $capsApplied,
        public readonly int $finalScore,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'starting_score' => $this->startingScore,
            'penalties' => $this->penalties,
            'caps_applied' => $this->capsApplied,
            'final_score' => $this->finalScore,
        ];
    }
}
