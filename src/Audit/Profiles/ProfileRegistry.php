<?php

declare(strict_types=1);

namespace ProdAudit\Audit\Profiles;

use InvalidArgumentException;

final class ProfileRegistry
{
    /**
     * @var array<string, ProfileInterface>
     */
    private array $profiles;

    public function __construct()
    {
        $dialer = new Dialer24x7Profile();

        $this->profiles = [
            $dialer->name() => $dialer,
        ];
    }

    public function get(string $name): ProfileInterface
    {
        if (!isset($this->profiles[$name])) {
            throw new InvalidArgumentException(sprintf('Unknown profile "%s".', $name));
        }

        return $this->profiles[$name];
    }
}
