<?php

declare(strict_types=1);

namespace ProdAudit\Audit;

use ProdAudit\Audit\Actions\ActionPlanner;
use ProdAudit\Audit\Collectors\AstCollector;
use ProdAudit\Audit\Collectors\ComposerCollector;
use ProdAudit\Audit\Collectors\FileCollector;
use ProdAudit\Audit\Collectors\PatternCollector;
use ProdAudit\Audit\Collectors\PhpConfigCollector;
use ProdAudit\Audit\Filtering\FindingFilter;
use ProdAudit\Audit\Forecast\ForecastEngine;
use ProdAudit\Audit\Insights\InsightEngine;
use ProdAudit\Audit\Profiles\ProfileInterface;
use ProdAudit\Audit\Reporting\HistoryWriter;
use ProdAudit\Audit\Reporting\JsonReportWriter;
use ProdAudit\Audit\Reporting\MarkdownReportWriter;
use ProdAudit\Audit\Reporting\TrendAnalyzer;
use ProdAudit\Audit\Rules\Finding;
use ProdAudit\Audit\Scoring\BandClassifier;
use ProdAudit\Audit\Scoring\ScoreEngine;
use ProdAudit\Audit\Tasks\TaskRecommender;
use ProdAudit\Audit\Triage\TriageStore;

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
        private readonly TaskRecommender $taskRecommender,
        private readonly TriageStore $triageStore = new TriageStore(),
        private readonly InsightEngine $insightEngine = new InsightEngine(),
        private readonly ForecastEngine $forecastEngine = new ForecastEngine(),
        private readonly ActionPlanner $actionPlanner = new ActionPlanner(),
    ) {
    }

    /**
     * @param array<int, array{fingerprint: string, rule: string, justification: string, expires: ?string}> $baselineEntries
     * @param array<int, array{rule: string, path: ?string, justification: string, expires: ?string}> $suppressionEntries
     * @param array<int, string> $ignoredDirectories
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
        bool $writeHistory = true,
        array $ignoredDirectories = [],
    ): array {
        $start = microtime(true);
        $collectorData = $this->collectCollectorData($scanPath, $ignoredDirectories);
        $evaluation = $this->evaluateRules($collectorData, $profile);
        $findings = $evaluation['findings'];
        $filtered = $this->findingFilter->filter($findings, $baselineEntries, $suppressionEntries);

        /** @var array<int, Finding> $activeFindings */
        $activeFindings = $filtered['active'];
        $ruleIdToPack = is_array($evaluation['rule_id_to_pack'] ?? null) ? $evaluation['rule_id_to_pack'] : [];
        $packRuleCounts = is_array($evaluation['pack_rule_counts'] ?? null) ? $evaluation['pack_rule_counts'] : [];

        $invariantFailures = 0;
        foreach ($activeFindings as $finding) {
            if ($finding->invariantFailure) {
                ++$invariantFailures;
            }
        }

        $scoreBreakdown = $this->scoreEngine->score($activeFindings, $invariantFailures);
        $activeFindingArrays = array_map(static fn (Finding $finding): array => $finding->toArray(), $activeFindings);
        $tasks = $this->taskRecommender->recommend($activeFindingArrays, $profile);
        $taskPayload = array_map(static fn ($task): array => $task->toArray(), $tasks);
        $packFindingCounts = [];
        foreach ($activeFindings as $finding) {
            $packName = $ruleIdToPack[$finding->ruleId] ?? 'unpacked';
            $packFindingCounts[$packName] = ($packFindingCounts[$packName] ?? 0) + 1;
        }
        foreach ($packRuleCounts as $packName => $ruleCount) {
            $packFindingCounts[$packName] = $packFindingCounts[$packName] ?? 0;
        }
        ksort($packFindingCounts, SORT_STRING);

        $rulePackSummary = [];
        foreach ($packRuleCounts as $packName => $ruleCount) {
            $rulePackSummary[$packName] = [
                'rules' => $ruleCount,
                'findings' => $packFindingCounts[$packName] ?? 0,
            ];
        }

        $scanDurationMs = (int) round((microtime(true) - $start) * 1000);
        $filesScannedCount = count(is_array($collectorData['files'] ?? null) ? $collectorData['files'] : []);
        $astSummary = is_array($collectorData['ast']['summary'] ?? null)
            ? $collectorData['ast']['summary']
            : ['ok' => 0, 'failed' => 0];

        $report = [
            'timestamp' => $timestamp,
            'profile' => $profile->name(),
            'path' => $scanPath,
            'tool_version' => '0.5.0-stage7',
            'target_score' => $targetScore,
            'score' => $scoreBreakdown->finalScore,
            'band' => $this->bandClassifier->classify($scoreBreakdown->finalScore),
            'invariant_failures' => $invariantFailures,
            'invariants' => [
                'failures' => $invariantFailures,
            ],
            'breakdown' => $scoreBreakdown->toArray(),
            'findings' => $activeFindingArrays,
            'suppressed' => $this->normalizeSuppressedEntries($filtered['suppressed']),
            'baseline' => $this->normalizeSuppressedEntries($filtered['baseline']),
            'tasks' => $taskPayload,
            'summary' => [
                'total_findings' => count($activeFindings),
                'suppressed_findings' => count($filtered['suppressed']),
                'baseline_findings' => count($filtered['baseline']),
            ],
            'scan_metrics' => [
                'files_scanned_count' => $filesScannedCount,
                'php_files_parsed_ok' => (int) ($astSummary['ok'] ?? 0),
                'php_files_parsed_failed' => (int) ($astSummary['failed'] ?? 0),
                'rules_executed_count' => (int) ($evaluation['rules_executed_count'] ?? 0),
                'findings_count_active' => count($activeFindings),
                'findings_count_suppressed' => count($filtered['suppressed']),
                'findings_count_baseline' => count($filtered['baseline']),
                'scan_duration_ms' => $scanDurationMs,
            ],
            'collector_stats' => [
                'ast' => $astSummary,
            ],
            'rule_pack_summary' => $rulePackSummary,
        ];

        $historyPath = rtrim($outputDirectory, '/') . '/history.jsonl';
        $historyReports = $this->readHistoryReports($historyPath, 20);
        $report['trend'] = $this->trendAnalyzer->analyze($historyPath, [
            'score' => $report['score'],
            'findings' => $report['findings'],
        ]);
        $report['regression'] = (bool) ($report['trend']['regression'] ?? false);

        $effectiveLabels = $this->triageStore->effectiveLabels($outputDirectory);
        $churnByFile = $this->getChurnByFile($scanPath, 30);

        $insightReport = $this->insightEngine->generate($report, $historyReports, $effectiveLabels, $churnByFile);
        $report['insights'] = $insightReport->toArray();
        $report['noise_score'] = (float) ($report['insights']['noise_score'] ?? 0.0);
        $report['stability_score'] = (float) ($report['insights']['stability_score'] ?? 0.0);

        $actions = $this->actionPlanner->plan(
            $activeFindingArrays,
            $report['insights'],
            []
        );
        $report['actions'] = array_map(static fn ($action): array => $action->toArray(), $actions);

        $forecast = $this->forecastEngine->generate(
            $report,
            $historyReports,
            $report['insights'],
            $effectiveLabels,
            $report['actions']
        );
        $report['forecast'] = $forecast->toArray();

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
        return $this->evaluateRules($this->collectCollectorData($scanPath), $profile)['findings'];
    }

    /**
     * @param array<string, mixed> $collectorData
     * @return array{
     *   findings: array<int, Finding>,
     *   rules_executed_count: int,
     *   rule_id_to_pack: array<string, string>,
     *   pack_rule_counts: array<string, int>
     * }
     */
    private function evaluateRules(array $collectorData, ProfileInterface $profile): array
    {
        $rules = $this->ruleScheduler->schedule($profile);

        $ruleResults = [];
        foreach ($rules as $rule) {
            $ruleResults[] = $rule->evaluate($collectorData);
        }

        $ruleIdToPack = [];
        $packRuleCounts = [];
        foreach ($rules as $rule) {
            $metadata = $rule->metadata();
            $ruleIdToPack[$metadata->id] = $metadata->pack;
            $packRuleCounts[$metadata->pack] = ($packRuleCounts[$metadata->pack] ?? 0) + 1;
        }
        ksort($ruleIdToPack, SORT_STRING);
        ksort($packRuleCounts, SORT_STRING);

        return [
            'findings' => $this->findingAggregator->aggregate($ruleResults),
            'rules_executed_count' => count($rules),
            'rule_id_to_pack' => $ruleIdToPack,
            'pack_rule_counts' => $packRuleCounts,
        ];
    }

    /**
     * @param array<int, string> $ignoredDirectories
     * @return array<string, mixed>
     */
    private function collectCollectorData(string $scanPath, array $ignoredDirectories = []): array
    {
        $collectorData = [];
        $collectorData['files'] = $ignoredDirectories === []
            ? $this->fileCollector->collect($scanPath)
            : $this->fileCollector->collect($scanPath, ignoredDirectories: $ignoredDirectories);
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

    /**
     * @return array<int, array<string, mixed>>
     */
    private function readHistoryReports(string $historyPath, int $window): array
    {
        if (!is_file($historyPath)) {
            return [];
        }

        $lines = file($historyPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($lines)) {
            return [];
        }

        $rows = [];
        foreach ($lines as $line) {
            $decoded = json_decode($line, true);
            if (is_array($decoded)) {
                $rows[] = $decoded;
            }
        }

        return array_slice($rows, -max(1, $window));
    }

    /**
     * @return array<string, float>
     */
    private function getChurnByFile(string $scanPath, int $lastDays = 30): array
    {
        $dir = is_dir($scanPath) ? $scanPath : dirname($scanPath);
        if (!is_dir($dir)) {
            return [];
        }

        $gitRoot = @shell_exec('git -C ' . escapeshellarg($dir) . ' rev-parse --show-toplevel 2>/dev/null');
        if (!is_string($gitRoot) || trim($gitRoot) === '') {
            return [];
        }

        $root = trim($gitRoot);
        $cmd = sprintf(
            'git -C %s log --name-only --pretty=format: --since="%d days ago" 2>/dev/null',
            escapeshellarg($root),
            $lastDays
        );

        $output = @shell_exec($cmd);
        if (!is_string($output) || $output === '') {
            return [];
        }

        $churn = [];
        foreach (preg_split('/\R/', $output) as $line) {
            $file = trim((string) $line);
            if ($file === '') {
                continue;
            }

            $churn[$file] = ($churn[$file] ?? 0) + 1;
        }

        if ($churn === []) {
            return [];
        }

        ksort($churn, SORT_STRING);

        $max = (float) max($churn);
        $normalized = [];
        foreach ($churn as $file => $count) {
            $normalized[$file] = $max > 0.0 ? round($count / $max, 6) : 0.0;
        }

        return $normalized;
    }
}
