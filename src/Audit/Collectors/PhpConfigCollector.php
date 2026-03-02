<?php

declare(strict_types=1);

namespace ProdAudit\Audit\Collectors;

final class PhpConfigCollector
{
    /**
     * @return array<string, string>
     */
    public function collect(): array
    {
        return [
            'php_version' => PHP_VERSION,
            'sapi' => PHP_SAPI,
            'loaded_ini_file' => php_ini_loaded_file() ?: '',
        ];
    }
}
