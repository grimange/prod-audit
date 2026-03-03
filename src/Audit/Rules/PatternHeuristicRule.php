<?php

declare(strict_types=1);

namespace ProdAudit\Audit\Rules;

use ProdAudit\Utils\Fingerprint;

abstract class PatternHeuristicRule implements RuleInterface
{
    private readonly EvidenceFactory $evidenceFactory;

    public function __construct(?EvidenceFactory $evidenceFactory = null)
    {
        $this->evidenceFactory = $evidenceFactory ?? new EvidenceFactory();
    }

    abstract public function metadata(): RuleMetadata;

    abstract protected function patternGroup(): string;

    abstract protected function includeRegex(): string;

    /**
     * @return array<int, string>
     */
    protected function excludeRegexes(): array
    {
        return [];
    }

    protected function findingMessage(): string
    {
        return $this->metadata()->description;
    }

    protected function impact(): string
    {
        return $this->metadata()->whyItMatters;
    }

    protected function recommendation(): string
    {
        return 'Review and harden this area to satisfy production-readiness controls.';
    }

    protected function effort(): string
    {
        return 'small';
    }

    /**
     * @return array<int, string>
     */
    protected function tags(): array
    {
        return [$this->metadata()->pack, $this->metadata()->category];
    }

    protected function confidence(): Confidence
    {
        return Confidence::Low;
    }

    protected function advisoryOnly(): bool
    {
        return false;
    }

    protected function invariantFailure(): bool
    {
        return $this->metadata()->invariant;
    }

    protected function evidenceType(): string
    {
        return 'grep_match';
    }

    public function evaluate(array $collectorData): RuleResult
    {
        $matches = $collectorData['patterns'][$this->patternGroup()] ?? [];
        if (!is_array($matches)) {
            return new RuleResult($this->metadata(), []);
        }

        $findings = [];
        $index = 1;
        foreach ($matches as $match) {
            if (!is_array($match)) {
                continue;
            }

            $excerpt = (string) ($match['excerpt'] ?? '');
            if ($excerpt === '' || preg_match($this->includeRegex(), $excerpt) !== 1) {
                continue;
            }

            $skip = false;
            foreach ($this->excludeRegexes() as $regex) {
                if (preg_match($regex, $excerpt) === 1) {
                    $skip = true;
                    break;
                }
            }
            if ($skip) {
                continue;
            }

            $line = (int) ($match['line'] ?? 1);
            if ($line < 1) {
                $line = 1;
            }

            $evidence = $this->evidenceFactory->fromLocation(
                type: $this->evidenceType(),
                file: (string) ($match['file'] ?? ''),
                startLine: $line,
                endLine: $line,
                excerpt: $excerpt,
            );

            $metadata = $this->metadata();
            $findings[] = new Finding(
                id: sprintf('%s-%03d', $metadata->id, $index),
                ruleId: $metadata->id,
                title: $metadata->title,
                category: $metadata->category,
                severity: $metadata->defaultSeverity,
                confidence: $this->confidence(),
                message: $this->findingMessage(),
                impact: $this->impact(),
                recommendation: $this->recommendation(),
                effort: $this->effort(),
                tags: $this->tags(),
                evidence: [$evidence],
                fingerprint: Fingerprint::fromEvidence($metadata->id, [$evidence]),
                advisoryOnly: $this->advisoryOnly(),
                invariantFailure: $this->invariantFailure(),
            );
            ++$index;
        }

        return new RuleResult($this->metadata(), $findings);
    }
}

