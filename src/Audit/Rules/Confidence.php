<?php

declare(strict_types=1);

namespace ProdAudit\Audit\Rules;

enum Confidence: string
{
    case High = 'high';
    case Medium = 'medium';
    case Low = 'low';

    public function multiplier(): float
    {
        return $this === self::Low ? 0.5 : 1.0;
    }
}
