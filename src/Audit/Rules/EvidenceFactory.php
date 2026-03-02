<?php

declare(strict_types=1);

namespace ProdAudit\Audit\Rules;

final class EvidenceFactory
{
    public function fromLocation(
        string $type,
        string $file,
        int $startLine,
        int $endLine,
        string $excerpt,
        int $maxLines = 10
    ): Evidence {
        return Evidence::create(
            type: $type,
            file: $file,
            lineStart: $startLine,
            lineEnd: $endLine,
            excerpt: $this->trimToMaxLines($excerpt, $maxLines),
        );
    }

    private function trimToMaxLines(string $excerpt, int $maxLines): string
    {
        $lines = preg_split('/\R/', $excerpt);
        if (!is_array($lines)) {
            return trim($excerpt);
        }

        return trim(implode("\n", array_slice($lines, 0, $maxLines)));
    }
}
