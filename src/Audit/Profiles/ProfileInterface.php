<?php

declare(strict_types=1);

namespace ProdAudit\Audit\Profiles;

interface ProfileInterface
{
    public function name(): string;

    public function targetScore(): int;

    /**
     * @return array<int, string>
     */
    public function ruleIds(): array;

    /**
     * @return array<int, string>
     */
    public function invariantRuleIds(): array;
}
