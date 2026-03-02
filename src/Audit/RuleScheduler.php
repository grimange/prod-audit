<?php

declare(strict_types=1);

namespace ProdAudit\Audit;

use ProdAudit\Audit\Profiles\ProfileInterface;
use ProdAudit\Audit\Rules\RuleInterface;

final class RuleScheduler
{
    /**
     * @var array<string, RuleInterface>
     */
    private array $rules = [];

    /**
     * @param array<int, RuleInterface> $rules
     */
    public function __construct(array $rules = [])
    {
        foreach ($rules as $rule) {
            $this->rules[$rule->metadata()->id] = $rule;
        }
    }

    /**
     * @return array<int, RuleInterface>
     */
    public function schedule(ProfileInterface $profile): array
    {
        $scheduled = [];
        foreach ($profile->ruleIds() as $ruleId) {
            if (isset($this->rules[$ruleId])) {
                $scheduled[] = $this->rules[$ruleId];
            }
        }

        return $scheduled;
    }
}
