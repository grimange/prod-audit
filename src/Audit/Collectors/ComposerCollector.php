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
            ];
        }

        $data = json_decode((string) file_get_contents($composerPath), true);

        if (!is_array($data)) {
            return [
                'exists' => true,
                'packages' => [],
            ];
        }

        $packages = array_keys((array) ($data['require'] ?? []));
        sort($packages, SORT_STRING);

        return [
            'exists' => true,
            'packages' => $packages,
        ];
    }
}
