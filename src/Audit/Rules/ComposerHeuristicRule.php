<?php

declare(strict_types=1);

namespace ProdAudit\Audit\Rules;

use ProdAudit\Utils\Fingerprint;

abstract class ComposerHeuristicRule implements RuleInterface
{
    abstract public function metadata(): RuleMetadata;

    /**
     * @param array<string, mixed> $composer
     * @return array<int, array{package: string, version: string, evidence: string}>
     */
    abstract protected function detect(array $composer): array;

    protected function recommendation(): string
    {
        return 'Review composer dependency policy and update package constraints.';
    }

    public function evaluate(array $collectorData): RuleResult
    {
        $composer = is_array($collectorData['composer'] ?? null) ? $collectorData['composer'] : [];
        if (!((bool) ($composer['exists'] ?? false))) {
            return new RuleResult($this->metadata(), []);
        }

        $issues = $this->detect($composer);
        $findings = [];
        $index = 1;
        $metadata = $this->metadata();
        foreach ($issues as $issue) {
            $evidence = Evidence::create(
                type: 'command_output',
                file: 'composer.json',
                lineStart: null,
                lineEnd: null,
                excerpt: (string) ($issue['evidence'] ?? ''),
            );

            $findings[] = new Finding(
                id: sprintf('%s-%03d', $metadata->id, $index),
                ruleId: $metadata->id,
                title: $metadata->title,
                category: $metadata->category,
                severity: $metadata->defaultSeverity,
                confidence: Confidence::Medium,
                message: sprintf(
                    '%s detected for package "%s".',
                    $metadata->title,
                    (string) ($issue['package'] ?? '')
                ),
                impact: $metadata->whyItMatters,
                recommendation: $this->recommendation(),
                effort: 'small',
                tags: [$metadata->pack, $metadata->category, 'composer'],
                evidence: [$evidence],
                fingerprint: Fingerprint::fromEvidence($metadata->id, [$evidence]),
            );
            ++$index;
        }

        return new RuleResult($metadata, $findings);
    }
}

