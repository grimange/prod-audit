<?php

declare(strict_types=1);

namespace ProdAudit\Audit\Profiles;

final class Dialer24x7Profile implements ProfileInterface
{
    public function name(): string
    {
        return 'dialer-24x7';
    }

    public function targetScore(): int
    {
        return 95;
    }

    public function ruleIds(): array
    {
        return [
            'PR-ERR-001',
            'PR-HANG-001',
            'PR-LOCK-001',
        ];
    }

    public function invariantRuleIds(): array
    {
        return [
            'PR-HANG-001',
            'PR-LOCK-001',
        ];
    }
}
