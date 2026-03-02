<?php

declare(strict_types=1);

namespace ProdAudit\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ProdAudit\Audit\Collectors\AstCollector;
use ProdAudit\Audit\Collectors\FileCollector;

final class AstCollectorTest extends TestCase
{
    public function testCollectIsDeterministicAndIndexesNodes(): void
    {
        $files = $this->fixtures();
        $collector = new AstCollector();
        $fileCollector = new FileCollector();

        $first = $collector->collect($files, $fileCollector);
        $second = $collector->collect($files, $fileCollector);

        self::assertSame($first, $second);
        self::assertGreaterThanOrEqual(1, (int) ($first['summary']['ok'] ?? 0));
        self::assertGreaterThanOrEqual(1, count($first['catch_blocks'] ?? []));
        self::assertGreaterThanOrEqual(1, count($first['loops'] ?? []));
        self::assertGreaterThanOrEqual(1, count($first['scopes'] ?? []));
    }

    /**
     * @return array<int, array{path: string, relative_path: string, extension: string, size: int}>
     */
    private function fixtures(): array
    {
        $relativeFiles = [
            'ast/swallowed_empty_catch.php',
            'ast/infinite_loop_no_yield.php',
            'ast/redis_expire_no_eval.php',
        ];

        $files = [];
        foreach ($relativeFiles as $relativePath) {
            $path = dirname(__DIR__) . '/Fixtures/' . $relativePath;
            $files[] = [
                'path' => $path,
                'relative_path' => $relativePath,
                'extension' => 'php',
                'size' => filesize($path) ?: 0,
            ];
        }

        return $files;
    }
}
