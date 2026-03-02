<?php

declare(strict_types=1);

namespace ProdAudit\Audit\Plugins;

use ProdAudit\Audit\Profiles\ProfileRegistry;
use ProdAudit\Audit\Rules\RuleRegistry;

interface PluginInterface
{
    public function getName(): string;

    public function register(ProfileRegistry $profiles, RuleRegistry $rules): void;
}
