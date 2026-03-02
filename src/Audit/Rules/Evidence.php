<?php

declare(strict_types=1);

namespace ProdAudit\Audit\Rules;

final class Evidence
{
    public function __construct(
        public readonly string $type,
        public readonly ?string $file,
        public readonly ?int $lineStart,
        public readonly ?int $lineEnd,
        public readonly string $excerpt,
        public readonly string $hash,
    ) {
    }

    public static function create(
        string $type,
        ?string $file,
        ?int $lineStart,
        ?int $lineEnd,
        string $excerpt
    ): self {
        $hash = hash('sha256', implode('|', [
            $type,
            $file ?? '',
            (string) ($lineStart ?? 0),
            (string) ($lineEnd ?? 0),
            $excerpt,
        ]));

        return new self($type, $file, $lineStart, $lineEnd, $excerpt, $hash);
    }

    /**
     * @return array<string, int|string|null>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'file' => $this->file,
            'line_start' => $this->lineStart,
            'line_end' => $this->lineEnd,
            'excerpt' => $this->excerpt,
            'hash' => $this->hash,
        ];
    }
}
