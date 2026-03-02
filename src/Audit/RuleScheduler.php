<?php

declare(strict_types=1);

namespace ProdAudit\Audit;

use ProdAudit\Audit\Profiles\ProfileInterface;
use ProdAudit\Audit\Rules\RuleInterface;
use ProdAudit\Audit\Rules\RuleRegistry;

final class RuleScheduler
{
    public function __construct(
        private readonly RuleRegistry $ruleRegistry
    )
    {
    }

    /**
     * @return array<int, RuleInterface>
    */
    public function schedule(ProfileInterface $profile): array
    {
        $scheduled = [];
        foreach ($profile->ruleIds() as $ruleId) {
            $rule = $this->ruleRegistry->get($ruleId);
            if ($rule instanceof RuleInterface) {
                $scheduled[] = $rule;
            }
        }

        return $scheduled;
    }
}
