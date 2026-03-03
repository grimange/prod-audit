<?php

declare(strict_types=1);

namespace ProdAudit\Audit\Plugins;

use ProdAudit\Audit\Profiles\ProfileRegistry;
use ProdAudit\Audit\Rules\PackRegistry;
use ProdAudit\Audit\Rules\RuleRegistry;

final class PluginLoader
{
    /**
     * @return array{loaded: array<int, string>, failed: array<int, string>}
     */
    public function load(string $targetPath, ProfileRegistry $profiles, RuleRegistry $rules, PackRegistry $packs): array
    {
        $loaded = [];
        $failed = [];

        $plugins = [BuiltInPlugin::class];
        $plugins = array_merge($plugins, $this->pluginClassesFromComposer($targetPath));
        $plugins = array_values(array_unique($plugins));
        sort($plugins, SORT_STRING);

        foreach ($plugins as $pluginClass) {
            if (!class_exists($pluginClass)) {
                $failed[] = $pluginClass;
                continue;
            }

            $plugin = new $pluginClass();
            if (!$plugin instanceof PluginInterface) {
                $failed[] = $pluginClass;
                continue;
            }

            $plugin->register($profiles, $rules, $packs);
            $loaded[] = $plugin->getName();
        }

        sort($loaded, SORT_STRING);
        sort($failed, SORT_STRING);

        return [
            'loaded' => $loaded,
            'failed' => $failed,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function pluginClassesFromComposer(string $targetPath): array
    {
        $composerPath = $this->resolveComposerPath($targetPath);
        if ($composerPath === null || !is_file($composerPath)) {
            return [];
        }

        $raw = file_get_contents($composerPath);
        if (!is_string($raw)) {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [];
        }

        $plugins = $decoded['extra']['prod-audit']['plugins'] ?? [];
        if (!is_array($plugins)) {
            return [];
        }

        $classNames = [];
        foreach ($plugins as $pluginClass) {
            if (is_string($pluginClass) && trim($pluginClass) !== '') {
                $classNames[] = $pluginClass;
            }
        }

        sort($classNames, SORT_STRING);

        return $classNames;
    }

    private function resolveComposerPath(string $targetPath): ?string
    {
        $path = is_dir($targetPath) ? $targetPath : dirname($targetPath);
        $path = realpath($path) ?: $path;
        if (!is_string($path) || $path === '') {
            return $this->currentProjectComposerPath();
        }

        $current = $path;
        while ($current !== '' && $current !== '/' && $current !== '.') {
            $candidate = rtrim($current, '/') . '/composer.json';
            if (is_file($candidate)) {
                return $candidate;
            }

            $parent = dirname($current);
            if ($parent === $current) {
                break;
            }
            $current = $parent;
        }

        return $this->currentProjectComposerPath();
    }

    private function currentProjectComposerPath(): ?string
    {
        $candidate = dirname(__DIR__, 3) . '/composer.json';

        return is_file($candidate) ? $candidate : null;
    }
}
