<?php

declare(strict_types=1);

namespace ProdAudit\Audit\Config;

final class ConfigLoader
{
    public function loadConfig(string $path): Config
    {
        return new Config($this->load($path));
    }

    /**
     * @return array<string, mixed>
     */
    public function load(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }

        $config = include $path;

        return is_array($config) ? $config : [];
    }

    /**
     * @param array<string, mixed> $config
     * @return array<int, string>
     */
    public function ignoredDirectories(array $config): array
    {
        return (new Config($config))->ignoredDirectories();
    }
}
