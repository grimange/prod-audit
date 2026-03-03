<?php

declare(strict_types=1);

namespace ProdAudit\Audit\Rules;

use ProdAudit\Utils\Fingerprint;

abstract class MissingDocumentRule implements RuleInterface
{
    abstract public function metadata(): RuleMetadata;

    /**
     * @return array<int, string>
     */
    abstract protected function requiredPathPatterns(): array;

    protected function findingMessage(): string
    {
        return $this->metadata()->description;
    }

    protected function recommendation(): string
    {
        return 'Add and maintain the required document as part of production operations readiness.';
    }

    public function evaluate(array $collectorData): RuleResult
    {
        $files = is_array($collectorData['files'] ?? null) ? $collectorData['files'] : [];
        $relativePaths = [];
        foreach ($files as $file) {
            if (!is_array($file)) {
                continue;
            }

            $relativePath = strtolower((string) ($file['relative_path'] ?? ''));
            if ($relativePath !== '') {
                $relativePaths[] = $relativePath;
            }
        }

        sort($relativePaths, SORT_STRING);

        foreach ($relativePaths as $path) {
            foreach ($this->requiredPathPatterns() as $regex) {
                if (preg_match($regex, $path) === 1) {
                    return new RuleResult($this->metadata(), []);
                }
            }
        }

        $metadata = $this->metadata();
        $excerpt = 'Missing required documentation pattern: ' . implode(', ', $this->requiredPathPatterns());
        $evidence = Evidence::create(
            type: 'command_output',
            file: null,
            lineStart: null,
            lineEnd: null,
            excerpt: $excerpt,
        );

        $finding = new Finding(
            id: sprintf('%s-001', $metadata->id),
            ruleId: $metadata->id,
            title: $metadata->title,
            category: $metadata->category,
            severity: $metadata->defaultSeverity,
            confidence: Confidence::High,
            message: $this->findingMessage(),
            impact: $metadata->whyItMatters,
            recommendation: $this->recommendation(),
            effort: 'small',
            tags: [$metadata->pack, $metadata->category, 'documentation'],
            evidence: [$evidence],
            fingerprint: Fingerprint::fromEvidence($metadata->id, [$evidence]),
        );

        return new RuleResult($metadata, [$finding]);
    }
}

