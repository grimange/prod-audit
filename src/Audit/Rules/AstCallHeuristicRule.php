<?php

declare(strict_types=1);

namespace ProdAudit\Audit\Rules;

use ProdAudit\Utils\Fingerprint;

abstract class AstCallHeuristicRule implements RuleInterface
{
    private readonly EvidenceFactory $evidenceFactory;

    public function __construct(?EvidenceFactory $evidenceFactory = null)
    {
        $this->evidenceFactory = $evidenceFactory ?? new EvidenceFactory();
    }

    abstract public function metadata(): RuleMetadata;

    abstract protected function includeCallRegex(): string;

    /**
     * @return array<int, string>
     */
    protected function excludeSnippetRegexes(): array
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
        return 'Harden this call path with explicit production-safe controls.';
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
        return [$this->metadata()->pack, $this->metadata()->category, 'ast'];
    }

    protected function confidence(): Confidence
    {
        return Confidence::Medium;
    }

    protected function advisoryOnly(): bool
    {
        return false;
    }

    protected function invariantFailure(): bool
    {
        return $this->metadata()->invariant;
    }

    /**
     * @param array<string, mixed> $scope
     * @param array<string, mixed> $call
     */
    protected function isMatch(array $scope, array $call): bool
    {
        $name = strtolower((string) ($call['name'] ?? ''));
        $target = strtolower((string) ($call['target'] ?? ''));
        $snippet = strtolower((string) ($call['snippet'] ?? ''));
        $haystack = trim($target . ' ' . $name . ' ' . $snippet);
        if ($haystack === '' || preg_match($this->includeCallRegex(), $haystack) !== 1) {
            return false;
        }

        foreach ($this->excludeSnippetRegexes() as $regex) {
            if (preg_match($regex, $snippet) === 1) {
                return false;
            }
        }

        return true;
    }

    public function evaluate(array $collectorData): RuleResult
    {
        $ast = $collectorData['ast'] ?? [];
        $scopes = is_array($ast['scopes'] ?? null) ? $ast['scopes'] : [];

        $findings = [];
        $index = 1;
        foreach ($scopes as $scope) {
            if (!is_array($scope)) {
                continue;
            }

            $calls = is_array($scope['calls'] ?? null) ? $scope['calls'] : [];
            usort(
                $calls,
                static fn (array $a, array $b): int => ((int) ($a['start_line'] ?? 0)) <=> ((int) ($b['start_line'] ?? 0))
            );

            foreach ($calls as $call) {
                if (!is_array($call) || !$this->isMatch($scope, $call)) {
                    continue;
                }

                $lineStart = (int) ($call['start_line'] ?? 1);
                $lineEnd = (int) ($call['end_line'] ?? $lineStart);
                if ($lineStart < 1) {
                    $lineStart = 1;
                }
                if ($lineEnd < $lineStart) {
                    $lineEnd = $lineStart;
                }

                $evidence = $this->evidenceFactory->fromLocation(
                    type: 'ast_node',
                    file: (string) ($scope['file'] ?? ''),
                    startLine: $lineStart,
                    endLine: $lineEnd,
                    excerpt: (string) ($call['snippet'] ?? ''),
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
        }

        return new RuleResult($this->metadata(), $findings);
    }
}

