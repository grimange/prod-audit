<?php

declare(strict_types=1);

namespace ProdAudit\Audit\Policy;

final class Policy
{
    public function __construct(
        public readonly string $name,
        public readonly int $maxNewCritical,
        public readonly int $maxNewMajor,
        public readonly bool $requireNoNewInvariants,
        public readonly bool $noRegressions,
    ) {
    }

    public static function preset(string $name): self
    {
        return match ($name) {
            'strict' => new self('strict', 0, 0, true, true),
            'dialer' => new self('dialer', 0, 1, true, true),
            default => new self('default', 1, 3, false, false),
        };
    }

    public function withOverrides(
        ?int $maxNewCritical = null,
        ?int $maxNewMajor = null,
        ?bool $requireNoNewInvariants = null,
        ?bool $noRegressions = null,
    ): self {
        return new self(
            name: $this->name,
            maxNewCritical: $maxNewCritical ?? $this->maxNewCritical,
            maxNewMajor: $maxNewMajor ?? $this->maxNewMajor,
            requireNoNewInvariants: $requireNoNewInvariants ?? $this->requireNoNewInvariants,
            noRegressions: $noRegressions ?? $this->noRegressions,
        );
    }
}
