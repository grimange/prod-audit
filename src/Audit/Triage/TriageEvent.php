<?php

declare(strict_types=1);

namespace ProdAudit\Audit\Triage;

use InvalidArgumentException;

final class TriageEvent
{
    public const LABEL_TRUE_POSITIVE = 'true_positive';
    public const LABEL_FALSE_POSITIVE = 'false_positive';
    public const LABEL_NOISY = 'noisy';
    public const LABEL_FIXED = 'fixed';
    public const LABEL_WONTFIX = 'wontfix';
    public const LABEL_NEEDS_INVESTIGATION = 'needs_investigation';

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $timestampIso = self::requireString($data, 'timestamp_iso');
        $fingerprint = self::requireString($data, 'fingerprint');
        $ruleId = self::requireString($data, 'rule_id');
        $label = self::requireString($data, 'label');

        if (!in_array($label, self::labels(), true)) {
            throw new InvalidArgumentException(sprintf('Unsupported triage label "%s".', $label));
        }

        $note = isset($data['note']) ? (string) $data['note'] : null;
        $actor = isset($data['actor']) ? (string) $data['actor'] : null;

        return new self(
            timestampIso: $timestampIso,
            fingerprint: $fingerprint,
            ruleId: $ruleId,
            label: $label,
            note: $note,
            actor: $actor,
        );
    }

    /**
     * @return array<int, string>
     */
    public static function labels(): array
    {
        return [
            self::LABEL_TRUE_POSITIVE,
            self::LABEL_FALSE_POSITIVE,
            self::LABEL_NOISY,
            self::LABEL_FIXED,
            self::LABEL_WONTFIX,
            self::LABEL_NEEDS_INVESTIGATION,
        ];
    }

    public function __construct(
        public readonly string $timestampIso,
        public readonly string $fingerprint,
        public readonly string $ruleId,
        public readonly string $label,
        public readonly ?string $note = null,
        public readonly ?string $actor = null,
    ) {
        if (!in_array($this->label, self::labels(), true)) {
            throw new InvalidArgumentException(sprintf('Unsupported triage label "%s".', $this->label));
        }

        if ($this->fingerprint === '') {
            throw new InvalidArgumentException('Triage fingerprint must not be empty.');
        }

        if ($this->ruleId === '') {
            throw new InvalidArgumentException('Triage rule_id must not be empty.');
        }
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        $payload = [
            'timestamp_iso' => $this->timestampIso,
            'fingerprint' => $this->fingerprint,
            'rule_id' => $this->ruleId,
            'label' => $this->label,
        ];

        if ($this->note !== null && $this->note !== '') {
            $payload['note'] = $this->note;
        }

        if ($this->actor !== null && $this->actor !== '') {
            $payload['actor'] = $this->actor;
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function requireString(array $data, string $key): string
    {
        $value = $data[$key] ?? null;
        if (!is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException(sprintf('Triage event requires non-empty "%s".', $key));
        }

        return trim($value);
    }
}
