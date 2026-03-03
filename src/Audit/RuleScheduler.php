<?php

declare(strict_types=1);

namespace ProdAudit\Audit;

use ProdAudit\Audit\Profiles\ProfileInterface;
use ProdAudit\Audit\Rules\PackRegistry;
use ProdAudit\Audit\Rules\RuleInterface;
use ProdAudit\Audit\Rules\RuleRegistry;

final class RuleScheduler
{
    public function __construct(
        private readonly RuleRegistry $ruleRegistry,
        private readonly PackRegistry $packRegistry,
    )
    {
    }

    /**
     * @return array<int, RuleInterface>
    */
    public function schedule(ProfileInterface $profile): array
    {
        $scheduledRuleIds = [];

        $packNames = $profile->packNames();
        sort($packNames, SORT_STRING);

        foreach ($packNames as $packName) {
            $pack = $this->packRegistry->get($packName);
            foreach ($pack->sortedRuleIds() as $ruleId) {
                $scheduledRuleIds[$ruleId] = true;
            }
        }

        foreach ($profile->ruleIds() as $ruleId) {
            $scheduledRuleIds[$ruleId] = true;
        }

        $ruleIds = array_keys($scheduledRuleIds);
        sort($ruleIds, SORT_STRING);

        $scheduled = [];
        foreach ($ruleIds as $ruleId) {
            $rule = $this->ruleRegistry->get($ruleId);
            if ($rule instanceof RuleInterface) {
                $scheduled[] = $rule;
            }
        }

        return $scheduled;
    }
}
