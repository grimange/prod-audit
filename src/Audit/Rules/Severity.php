<?php

declare(strict_types=1);

namespace ProdAudit\Audit\Rules;

enum Severity: string
{
    case Critical = 'critical';
    case Major = 'major';
    case Minor = 'minor';
    case Info = 'info';

    public function weight(): int
    {
        return match ($this) {
            self::Critical => 4,
            self::Major => 3,
            self::Minor => 2,
            self::Info => 1,
        };
    }
}
