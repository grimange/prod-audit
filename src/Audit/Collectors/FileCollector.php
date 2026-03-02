<?php

declare(strict_types=1);

namespace ProdAudit\Audit\Collectors;

use ProdAudit\Utils\PathNormalizer;
use Symfony\Component\Finder\Finder;

final class FileCollector
{
    /**
     * @return array<int, array{path: string, relative_path: string, extension: string, size: int}>
     */
    public function collect(string $path): array
    {
        $normalized = PathNormalizer::normalize($path);

        $finder = new Finder();
        $finder->files()->in($normalized)->sortByName();

        $files = [];
        foreach ($finder as $file) {
            $files[] = [
                'path' => PathNormalizer::normalize($file->getPathname()),
                'relative_path' => PathNormalizer::normalize($file->getRelativePathname()),
                'extension' => $file->getExtension(),
                'size' => $file->getSize(),
            ];
        }

        return $files;
    }
}
