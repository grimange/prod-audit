<?php

declare(strict_types=1);

namespace ProdAudit\Audit\Rules;

use ProdAudit\Audit\Config\Config;
use ProdAudit\Utils\Fingerprint;

final class PR_TIME_001_ExternalCallTimeoutRule implements RuleInterface
{
    private readonly EvidenceFactory $evidenceFactory;

    public function __construct(?EvidenceFactory $evidenceFactory = null)
    {
        $this->evidenceFactory = $evidenceFactory ?? new EvidenceFactory();
    }

    public function metadata(): RuleMetadata
    {
        return new RuleMetadata(
            id: 'PR-TIME-001',
            title: 'External Call Without Timeout',
            invariant: false,
            category: Category::TIMEOUTS->value,
            pack: 'timeout',
            defaultSeverity: Severity::Major,
            description: 'Detects external HTTP calls that do not set explicit timeout options.',
            whyItMatters: 'Missing timeouts can block workers and accumulate cascading queue backlogs.',
        );
    }

    public function evaluate(array $collectorData): RuleResult
    {
        $findings = [];
        $index = 1;
        $ast = $collectorData['ast'] ?? [];
        $scopes = is_array($ast['scopes'] ?? null) ? $ast['scopes'] : [];

        foreach ($scopes as $scope) {
            if (!is_array($scope)) {
                continue;
            }

            $calls = is_array($scope['calls'] ?? null) ? $scope['calls'] : [];
            if ($calls === []) {
                continue;
            }
            $timeoutOptionVars = array_map(
                static fn (mixed $value): string => strtolower((string) $value),
                is_array($scope['timeout_option_vars'] ?? null) ? $scope['timeout_option_vars'] : []
            );

            usort($calls, static fn (array $a, array $b): int => ((int) ($a['start_line'] ?? 0)) <=> ((int) ($b['start_line'] ?? 0)));

            $curlInitCalls = [];
            $hasCurlTimeoutSetter = false;

            foreach ($calls as $call) {
                if (!is_array($call)) {
                    continue;
                }

                $name = strtolower((string) ($call['name'] ?? ''));
                if ($name === 'curl_init') {
                    $curlInitCalls[] = $call;
                    continue;
                }

                if ((bool) ($call['is_curl_timeout_setter'] ?? false)) {
                    $hasCurlTimeoutSetter = true;
                    continue;
                }

                if (!$this->isHttpRequestCall($call)) {
                    continue;
                }

                if ((bool) ($call['has_timeout_option'] ?? false) || (bool) ($call['has_connect_timeout_option'] ?? false)) {
                    continue;
                }
                if ($this->hasSharedTimeoutVariable($call, $timeoutOptionVars)) {
                    continue;
                }
                if ($this->isAllowedHttpTarget($collectorData, (string) ($call['target'] ?? ''))) {
                    continue;
                }

                $confidence = ((int) ($call['arg_count'] ?? 0)) >= 2 ? Confidence::Medium : Confidence::Low;
                $findings[] = $this->newFinding(
                    index: $index,
                    file: (string) ($scope['file'] ?? ''),
                    lineStart: (int) ($call['start_line'] ?? 1),
                    lineEnd: (int) ($call['end_line'] ?? 1),
                    snippet: (string) ($call['snippet'] ?? ''),
                    confidence: $confidence,
                );
                ++$index;
            }

            if ($curlInitCalls !== [] && !$hasCurlTimeoutSetter) {
                foreach ($curlInitCalls as $call) {
                    $findings[] = $this->newFinding(
                        index: $index,
                        file: (string) ($scope['file'] ?? ''),
                        lineStart: (int) ($call['start_line'] ?? 1),
                        lineEnd: (int) ($call['end_line'] ?? 1),
                        snippet: (string) ($call['snippet'] ?? ''),
                        confidence: Confidence::Medium,
                    );
                    ++$index;
                }
            }
        }

        return new RuleResult($this->metadata(), $findings);
    }

    /**
     * @param array<string, mixed> $call
     */
    private function isHttpRequestCall(array $call): bool
    {
        $name = strtolower((string) ($call['name'] ?? ''));
        if (!in_array($name, ['request', 'send', 'get', 'post', 'put', 'patch', 'delete'], true)) {
            return false;
        }

        $target = strtolower((string) ($call['target'] ?? ''));

        return $target === ''
            || str_contains($target, 'client')
            || str_contains($target, 'http')
            || str_contains($target, 'guzzle');
    }

    private function newFinding(
        int $index,
        string $file,
        int $lineStart,
        int $lineEnd,
        string $snippet,
        Confidence $confidence,
    ): Finding {
        $evidence = $this->evidenceFactory->fromLocation(
            type: 'ast_node',
            file: $file,
            startLine: $lineStart,
            endLine: $lineEnd,
            excerpt: $snippet,
        );

        return new Finding(
            id: sprintf('PR-TIME-001-%03d', $index),
            ruleId: 'PR-TIME-001',
            title: 'External Call Without Timeout',
            category: Category::TIMEOUTS->value,
            severity: Severity::Major,
            confidence: $confidence,
            message: 'External call missing explicit timeout configuration.',
            impact: 'Requests may block indefinitely and stall workers.',
            recommendation: 'Set timeout and connect_timeout options for external calls.',
            effort: 'small',
            tags: ['timeouts', 'http', 'reliability'],
            evidence: [$evidence],
            fingerprint: Fingerprint::fromEvidence('PR-TIME-001', [$evidence]),
        );
    }

    /**
     * @param array<string, mixed> $call
     * @param array<int, string> $timeoutOptionVars
     */
    private function hasSharedTimeoutVariable(array $call, array $timeoutOptionVars): bool
    {
        $callVars = array_map(
            static fn (mixed $value): string => strtolower((string) $value),
            is_array($call['arg_variables'] ?? null) ? $call['arg_variables'] : []
        );
        foreach ($callVars as $name) {
            if (in_array($name, $timeoutOptionVars, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $collectorData
     */
    private function isAllowedHttpTarget(array $collectorData, string $target): bool
    {
        $config = new Config(is_array($collectorData['config'] ?? null) ? $collectorData['config'] : []);
        $allowedTargets = $config->stringList('PR-TIME-001', 'allow_http_targets', []);
        $target = strtolower(trim($target));
        if ($target === '') {
            return false;
        }

        foreach ($allowedTargets as $allowed) {
            if ($allowed !== '' && str_contains($target, $allowed)) {
                return true;
            }
        }

        return false;
    }
}
