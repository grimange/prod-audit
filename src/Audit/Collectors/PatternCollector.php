<?php

declare(strict_types=1);

namespace ProdAudit\Audit\Collectors;

final class PatternCollector
{
    /**
     * @param array<int, array{path: string, relative_path: string, extension: string, size: int}> $files
     * @param array<int, string> $patterns
     * @return array<string, array<int, array{file: string, line: int, excerpt: string}>>
     */
    public function collect(array $files, array $patterns = []): array
    {
        $defaultGroups = ['exceptions', 'loops', 'redis'];
        $groups = $patterns === [] ? $defaultGroups : $patterns;
        sort($groups, SORT_STRING);

        $result = [];
        foreach ($groups as $group) {
            $result[$group] = [];
        }

        foreach ($files as $file) {
            $path = $file['path'];
            if (!is_file($path)) {
                continue;
            }

            $content = file_get_contents($path);
            if (!is_string($content)) {
                continue;
            }

            foreach ($groups as $group) {
                foreach ($this->matchesForGroup($group, $content, $file['relative_path']) as $match) {
                    $result[$group][] = $match;
                }
            }
        }

        foreach ($result as $group => $matches) {
            usort(
                $matches,
                static function (array $a, array $b): int {
                    $fileCompare = strcmp($a['file'], $b['file']);
                    if ($fileCompare !== 0) {
                        return $fileCompare;
                    }

                    $lineCompare = $a['line'] <=> $b['line'];
                    if ($lineCompare !== 0) {
                        return $lineCompare;
                    }

                    return strcmp($a['excerpt'], $b['excerpt']);
                }
            );

            $result[$group] = $matches;
        }

        return $result;
    }

    /**
     * @return array<int, array{file: string, line: int, excerpt: string}>
     */
    private function matchesForGroup(string $group, string $content, string $relativePath): array
    {
        return match ($group) {
            'exceptions' => $this->extractMatches(
                '/catch\s*\([^)]+\)\s*\{[\s\S]*?\}/i',
                $content,
                $relativePath,
                false
            ),
            'loops' => $this->extractMatches(
                '/\bwhile\s*\(\s*true\s*\)|\bfor\s*\(\s*;\s*;\s*\)|\bsleep\s*\(|\busleep\s*\(|->sleep\s*\(|\byield\b|\btimeout\b|\bheartbeat\b/i',
                $content,
                $relativePath,
                true
            ),
            'redis' => $this->extractMatches(
                '/->\s*(?:p?expire|eval)\s*\(|\b(owner|token|lua)\b/i',
                $content,
                $relativePath,
                true
            ),
            default => [],
        };
    }

    /**
     * @return array<int, array{file: string, line: int, excerpt: string}>
     */
    private function extractMatches(string $regex, string $content, string $relativePath, bool $lineBased): array
    {
        $result = [];
        $status = preg_match_all($regex, $content, $matches, PREG_OFFSET_CAPTURE);
        if ($status === false || $status === 0) {
            return [];
        }

        foreach ($matches[0] as $matchData) {
            $matched = (string) $matchData[0];
            $offset = (int) $matchData[1];
            $line = substr_count(substr($content, 0, $offset), "\n") + 1;
            $excerpt = $lineBased
                ? $this->lineAtOffset($content, $offset)
                : $matched;

            $result[] = [
                'file' => $relativePath,
                'line' => $line,
                'excerpt' => $this->truncateExcerpt($excerpt),
            ];
        }

        return $result;
    }

    private function truncateExcerpt(string $excerpt): string
    {
        $normalized = trim($excerpt);

        if (strlen($normalized) <= 200) {
            return $normalized;
        }

        return substr($normalized, 0, 200);
    }

    private function lineAtOffset(string $content, int $offset): string
    {
        $start = strrpos(substr($content, 0, $offset), "\n");
        $start = $start === false ? 0 : $start + 1;

        $end = strpos($content, "\n", $offset);
        if ($end === false) {
            $end = strlen($content);
        }

        return substr($content, $start, $end - $start);
    }
}
