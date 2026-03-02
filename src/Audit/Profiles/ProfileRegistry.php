<?php

declare(strict_types=1);

namespace ProdAudit\Audit\Profiles;

use InvalidArgumentException;

final class ProfileRegistry
{
    /**
     * @var array<string, ProfileInterface>
     */
    private array $profiles = [];

    public function register(ProfileInterface $profile): void
    {
        $this->profiles[$profile->name()] = $profile;
        ksort($this->profiles, SORT_STRING);
    }

    public function get(string $name): ProfileInterface
    {
        if (!isset($this->profiles[$name])) {
            throw new InvalidArgumentException(sprintf('Unknown profile "%s".', $name));
        }

        return $this->profiles[$name];
    }
}
