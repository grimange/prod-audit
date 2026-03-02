<?php

declare(strict_types=1);

namespace ProdAudit\Utils;

final class PathNormalizer
{
    public static function normalize(string $path): string
    {
        $normalized = str_replace('\\\\', '/', $path);

        return rtrim($normalized, '/');
    }
}
