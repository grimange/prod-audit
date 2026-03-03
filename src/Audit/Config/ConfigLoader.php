<?php

declare(strict_types=1);

namespace ProdAudit\Audit\Config;

final class ConfigLoader
{
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
        $ignored = $config['ignored_directories'] ?? [];
        if (!is_array($ignored)) {
            return [];
        }

        $result = [];
        foreach ($ignored as $entry) {
            if (!is_string($entry) || trim($entry) === '') {
                continue;
            }

            $result[] = trim($entry);
        }

        $result = array_values(array_unique($result));
        sort($result, SORT_STRING);

        return $result;
    }
}
