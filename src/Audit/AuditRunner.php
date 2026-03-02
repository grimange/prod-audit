<?php

declare(strict_types=1);

namespace ProdAudit\Audit;

use ProdAudit\Audit\Collectors\ComposerCollector;
use ProdAudit\Audit\Collectors\AstCollector;
use ProdAudit\Audit\Collectors\FileCollector;
use ProdAudit\Audit\Collectors\PatternCollector;
use ProdAudit\Audit\Collectors\PhpConfigCollector;
use ProdAudit\Audit\Filtering\FindingFilter;
use ProdAudit\Audit\Profiles\ProfileInterface;
use ProdAudit\Audit\Reporting\HistoryWriter;
use ProdAudit\Audit\Reporting\JsonReportWriter;
use ProdAudit\Audit\Reporting\MarkdownReportWriter;
use ProdAudit\Audit\Reporting\TrendAnalyzer;
use ProdAudit\Audit\Rules\Finding;
use ProdAudit\Audit\Scoring\BandClassifier;
use ProdAudit\Audit\Scoring\ScoreEngine;

final class AuditRunner
{
    public function __construct(
        private readonly RuleScheduler $ruleScheduler,
        private readonly FindingAggregator $findingAggregator,
        private readonly FileCollector $fileCollector,
        private readonly AstCollector $astCollector,
        private readonly PatternCollector $patternCollector,
        private readonly ComposerCollector $composerCollector,
        private readonly PhpConfigCollector $phpConfigCollector,
        private readonly ScoreEngine $scoreEngine,
        private readonly BandClassifier $bandClassifier,
        private readonly MarkdownReportWriter $markdownReportWriter,
        private readonly JsonReportWriter $jsonReportWriter,
        private readonly HistoryWriter $historyWriter,
        private readonly TrendAnalyzer $trendAnalyzer,
        private readonly FindingFilter $findingFilter,
    ) {
    }

    /**
     * @param array<int, array{fingerprint: string, rule: string, justification: string, expires: ?string}> $baselineEntries
     * @param array<int, array{rule: string, path: ?string, justification: string, expires: ?string}> $suppressionEntries
     * @return array<string, mixed>
     */
    public function run(
        string $scanPath,
        string $outputDirectory,
        ProfileInterface $profile,
        int $targetScore,
        string $timestamp,
        array $baselineEntries = [],
        array $suppressionEntries = [],
        bool $writeHistory = true
    ): array {
        $collectorData = $this->collectCollectorData($scanPath);
        $findings = $this->evaluateRules($collectorData, $profile);
        $filtered = $this->findingFilter->filter($findings, $baselineEntries, $suppressionEntries);

        /** @var array<int, Finding> $activeFindings */
        $activeFindings = $filtered['active'];

        $invariantFailures = 0;
        foreach ($activeFindings as $finding) {
            if ($finding->invariantFailure) {
                ++$invariantFailures;
            }
        }

        $scoreBreakdown = $this->scoreEngine->score($activeFindings, $invariantFailures);

        $report = [
            'timestamp' => $timestamp,
            'profile' => $profile->name(),
            'path' => $scanPath,
            'target_score' => $targetScore,
            'score' => $scoreBreakdown->finalScore,
            'band' => $this->bandClassifier->classify($scoreBreakdown->finalScore),
            'invariant_failures' => $invariantFailures,
            'invariants' => [
                'failures' => $invariantFailures,
            ],
            'breakdown' => $scoreBreakdown->toArray(),
            'findings' => array_map(static fn (Finding $finding): array => $finding->toArray(), $activeFindings),
            'suppressed' => $this->normalizeSuppressedEntries($filtered['suppressed']),
            'baseline' => $this->normalizeSuppressedEntries($filtered['baseline']),
            'summary' => [
                'total_findings' => count($activeFindings),
                'suppressed_findings' => count($filtered['suppressed']),
                'baseline_findings' => count($filtered['baseline']),
            ],
            'collector_stats' => [
                'ast' => $collectorData['ast']['summary'] ?? ['ok' => 0, 'failed' => 0],
            ],
        ];

        $historyPath = rtrim($outputDirectory, '/') . '/history.jsonl';
        $report['trend'] = $this->trendAnalyzer->analyze($historyPath, [
            'score' => $report['score'],
            'findings' => $report['findings'],
        ]);
        $report['regression'] = (bool) ($report['trend']['regression'] ?? false);

        $this->markdownReportWriter->write($outputDirectory, $report, $timestamp);
        $this->jsonReportWriter->write($outputDirectory, $report);
        if ($writeHistory) {
            $this->historyWriter->append($outputDirectory, $report);
        }

        return $report;
    }

    /**
     * @return array<int, Finding>
     */
    public function collectFindings(string $scanPath, ProfileInterface $profile): array
    {
        return $this->evaluateRules($this->collectCollectorData($scanPath), $profile);
    }

    /**
     * @param array<string, mixed> $collectorData
     * @return array<int, Finding>
     */
    private function evaluateRules(array $collectorData, ProfileInterface $profile): array
    {

        $rules = $this->ruleScheduler->schedule($profile);

        $ruleResults = [];
        foreach ($rules as $rule) {
            $ruleResults[] = $rule->evaluate($collectorData);
        }

        return $this->findingAggregator->aggregate($ruleResults);
    }

    /**
     * @return array<string, mixed>
     */
    private function collectCollectorData(string $scanPath): array
    {
        $collectorData = [];
        $collectorData['files'] = $this->fileCollector->collect($scanPath);
        $collectorData['ast'] = $this->astCollector->collect($collectorData['files'], $this->fileCollector);
        $collectorData['patterns'] = $this->patternCollector->collect($collectorData['files']);
        $collectorData['composer'] = $this->composerCollector->collect($scanPath);
        $collectorData['php_config'] = $this->phpConfigCollector->collect();

        return $collectorData;
    }

    /**
     * @param array<int, array{finding: Finding, entry: array<string, mixed>}> $entries
     * @return array<int, array{finding: array<string, mixed>, entry: array<string, mixed>}>
     */
    private function normalizeSuppressedEntries(array $entries): array
    {
        $normalized = [];
        foreach ($entries as $entry) {
            $normalized[] = [
                'finding' => $entry['finding']->toArray(),
                'entry' => $entry['entry'],
            ];
        }

        usort(
            $normalized,
            static fn (array $a, array $b): int => strcmp(
                (string) ($a['finding']['fingerprint'] ?? ''),
                (string) ($b['finding']['fingerprint'] ?? '')
            )
        );

        return $normalized;
    }
}
