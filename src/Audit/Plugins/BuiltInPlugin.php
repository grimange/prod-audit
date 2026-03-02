<?php

declare(strict_types=1);

namespace ProdAudit\Audit\Plugins;

use ProdAudit\Audit\Profiles\Dialer24x7Profile;
use ProdAudit\Audit\Profiles\ProfileRegistry;
use ProdAudit\Audit\Rules\PR_ERR_001_SwallowedExceptionsRule;
use ProdAudit\Audit\Rules\PR_HANG_001_InfiniteLoopRule;
use ProdAudit\Audit\Rules\PR_LOCK_001_LockRenewRule;
use ProdAudit\Audit\Rules\RuleRegistry;

final class BuiltInPlugin implements PluginInterface
{
    public function getName(): string
    {
        return 'built-in';
    }

    public function register(ProfileRegistry $profiles, RuleRegistry $rules): void
    {
        $profiles->register(new Dialer24x7Profile());
        $rules->register(new PR_ERR_001_SwallowedExceptionsRule());
        $rules->register(new PR_HANG_001_InfiniteLoopRule());
        $rules->register(new PR_LOCK_001_LockRenewRule());
    }
}
