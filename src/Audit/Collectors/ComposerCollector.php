<?php

declare(strict_types=1);

namespace ProdAudit\Audit\Collectors;

use ProdAudit\Utils\PathNormalizer;

final class ComposerCollector
{
    /**
     * @return array<string, mixed>
     */
    public function collect(string $path): array
    {
        $composerPath = PathNormalizer::normalize($path) . '/composer.json';

        if (!is_file($composerPath)) {
            return [
                'exists' => false,
                'packages' => [],
                'require' => [],
                'require_dev' => [],
            ];
        }

        $data = json_decode((string) file_get_contents($composerPath), true);

        if (!is_array($data)) {
            return [
                'exists' => true,
                'packages' => [],
                'require' => [],
                'require_dev' => [],
            ];
        }

        $require = (array) ($data['require'] ?? []);
        $requireDev = (array) ($data['require-dev'] ?? []);
        $packages = array_values(array_unique(array_merge(array_keys($require), array_keys($requireDev))));
        sort($packages, SORT_STRING);
        ksort($require, SORT_STRING);
        ksort($requireDev, SORT_STRING);

        return [
            'exists' => true,
            'packages' => $packages,
            'require' => $require,
            'require_dev' => $requireDev,
        ];
    }
}
