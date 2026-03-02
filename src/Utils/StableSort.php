<?php

declare(strict_types=1);

namespace ProdAudit\Utils;

final class StableSort
{
    /**
     * @template T
     * @param array<int, T> $items
     * @param callable(T, T): int $comparator
     * @return array<int, T>
     */
    public static function sort(array $items, callable $comparator): array
    {
        $indexed = [];
        foreach ($items as $index => $item) {
            $indexed[] = ['index' => $index, 'value' => $item];
        }

        usort(
            $indexed,
            static function (array $a, array $b) use ($comparator): int {
                $result = $comparator($a['value'], $b['value']);
                if ($result !== 0) {
                    return $result;
                }

                return $a['index'] <=> $b['index'];
            }
        );

        return array_values(array_map(static fn (array $item): mixed => $item['value'], $indexed));
    }
}
