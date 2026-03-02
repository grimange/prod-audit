<?php

declare(strict_types=1);

namespace ProdAudit\Audit\Collectors;

use ProdAudit\Utils\PathNormalizer;
use Symfony\Component\Finder\Finder;

final class FileCollector
{
    /**
     * @var array<string, string>
     */
    private array $contentCache = [];

    /**
     * @param array<int, string> $ignoredDirectories
     * @return array<int, array{path: string, relative_path: string, extension: string, size: int}>
     */
    public function collect(
        string $path,
        int $maxFileSizeBytes = 2097152,
        array $ignoredDirectories = ['vendor', 'docs']
    ): array
    {
        $normalized = PathNormalizer::normalize($path);

        $finder = new Finder();
        $finder->files()->in($normalized)->sortByName()->size('<=' . $maxFileSizeBytes);
        if ($ignoredDirectories !== []) {
            $finder->exclude($ignoredDirectories);
        }

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

    public function readContent(string $path): ?string
    {
        if (isset($this->contentCache[$path])) {
            return $this->contentCache[$path];
        }

        $content = file_get_contents($path);
        if (!is_string($content)) {
            return null;
        }

        $this->contentCache[$path] = $content;

        return $content;
    }

    public function snippet(string $path, int $startLine, int $endLine, int $maxLines = 10): string
    {
        $content = $this->readContent($path);
        if ($content === null) {
            return '';
        }

        $lines = preg_split('/\R/', $content);
        if (!is_array($lines) || $lines === []) {
            return '';
        }

        $start = max(1, $startLine);
        $end = max($start, $endLine);
        $length = min($maxLines, ($end - $start) + 1);

        $excerpt = array_slice($lines, $start - 1, $length);

        return trim(implode("\n", $excerpt));
    }
}
