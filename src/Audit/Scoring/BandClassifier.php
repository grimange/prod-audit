<?php

declare(strict_types=1);

namespace ProdAudit\Audit\Scoring;

final class BandClassifier
{
    public function classify(int $score): string
    {
        if ($score >= 95) {
            return 'Production Ready';
        }

        if ($score >= 85) {
            return 'Nearly Ready';
        }

        return 'Not Ready';
    }
}
