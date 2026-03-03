<?php

declare(strict_types=1);

namespace ProdAudit\Console\Commands;

use ProdAudit\Audit\AuditRunner;
use ProdAudit\Audit\Baseline\BaselineRepository;
use ProdAudit\Audit\Config\ConfigLoader;
use ProdAudit\Audit\Export\CheckstyleExporter;
use ProdAudit\Audit\Export\SarifExporter;
use ProdAudit\Audit\Plugins\PluginLoader;
use ProdAudit\Audit\Policy\Policy;
use ProdAudit\Audit\Policy\PolicyEvaluator;
use ProdAudit\Audit\Profiles\ProfileRegistry;
use ProdAudit\Audit\Quality\QualityEngine;
use ProdAudit\Audit\Reporting\HistoryWriter;
use ProdAudit\Audit\Reporting\JsonReportWriter;
use ProdAudit\Audit\Reporting\MarkdownReportWriter;
use ProdAudit\Audit\Rules\PackRegistry;
use ProdAudit\Audit\Rules\RuleRegistry;
use ProdAudit\Audit\Suppression\SuppressionRepository;
use ProdAudit\Utils\PathNormalizer;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

final class ScanCommand extends Command
{
    public function __construct(
        private readonly AuditRunner $auditRunner,
        private readonly ProfileRegistry $profileRegistry,
        private readonly BaselineRepository $baselineRepository,
        private readonly SuppressionRepository $suppressionRepository,
        private readonly PluginLoader $pluginLoader,
        private readonly RuleRegistry $ruleRegistry,
        private readonly PackRegistry $packRegistry,
        private readonly ConfigLoader $configLoader = new ConfigLoader(),
        private readonly PolicyEvaluator $policyEvaluator = new PolicyEvaluator(),
        private readonly JsonReportWriter $jsonReportWriter = new JsonReportWriter(),
        private readonly MarkdownReportWriter $markdownReportWriter = new MarkdownReportWriter(),
        private readonly HistoryWriter $historyWriter = new HistoryWriter(),
        private readonly SarifExporter $sarifExporter = new SarifExporter(),
        private readonly CheckstyleExporter $checkstyleExporter = new CheckstyleExporter(),
        private readonly QualityEngine $qualityEngine = new QualityEngine(),
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('scan')
            ->setDescription('Run production readiness scan')
            ->addArgument('path', InputArgument::REQUIRED, 'Path to scan')
            ->addOption('profile', null, InputOption::VALUE_REQUIRED, 'Audit profile name', 'dialer-24x7')
            ->addOption('out', null, InputOption::VALUE_REQUIRED, 'Output directory', 'docs/audit')
            ->addOption('target-score', null, InputOption::VALUE_REQUIRED, 'Target score override')
            ->addOption('baseline', null, InputOption::VALUE_REQUIRED, 'Baseline file path')
            ->addOption('suppressions', null, InputOption::VALUE_REQUIRED, 'Suppressions file path', 'prod-audit-suppressions.json')
            ->addOption('policy', null, InputOption::VALUE_REQUIRED, 'Policy mode: default|strict|dialer', 'default')
            ->addOption('no-regressions', null, InputOption::VALUE_NONE, 'Fail policy when regression is detected')
            ->addOption('max-new-critical', null, InputOption::VALUE_REQUIRED, 'Maximum allowed new critical findings')
            ->addOption('max-new-major', null, InputOption::VALUE_REQUIRED, 'Maximum allowed new major findings')
            ->addOption('require-no-new-invariants', null, InputOption::VALUE_REQUIRED, 'Require no new invariant findings true|false')
            ->addOption('export', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Additional export format: sarif|checkstyle|json|md')
            ->addOption('export-include-suppressed', null, InputOption::VALUE_REQUIRED, 'Include suppressed/baseline findings in exports true|false', 'false')
            ->addOption('config', null, InputOption::VALUE_REQUIRED, 'Config file path', 'prod-audit.php')
            ->addOption('ignore-dir', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Additional ignored directory')
            ->addOption('fail-on-forecast-risk', null, InputOption::VALUE_REQUIRED, 'Optional threshold for forecast risk gate (0..1)')
            ->addOption('max-noise-score', null, InputOption::VALUE_REQUIRED, 'Optional quality noise budget gate (0..1)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $scanPath = PathNormalizer::normalize((string) $input->getArgument('path'));
            $outputDirectory = PathNormalizer::normalize((string) $input->getOption('out'));
            $profileName = (string) $input->getOption('profile');
            $this->pluginLoader->load($scanPath, $this->profileRegistry, $this->ruleRegistry, $this->packRegistry);

            if (!is_dir($outputDirectory) && !mkdir($outputDirectory, 0777, true) && !is_dir($outputDirectory)) {
                throw new RuntimeException('Unable to create output directory.');
            }

            $profile = $this->profileRegistry->get($profileName);

            $targetScore = (int) ($input->getOption('target-score') !== null
                ? (string) $input->getOption('target-score')
                : (string) $profile->targetScore());

            $baselineEntries = [];
            $baselinePath = $input->getOption('baseline');
            if (is_string($baselinePath) && trim($baselinePath) !== '') {
                $baselineEntries = $this->baselineRepository->loadActiveEntries(PathNormalizer::normalize($baselinePath));
            }

            $suppressionEntries = [];
            $suppressionsPath = PathNormalizer::normalize((string) $input->getOption('suppressions'));
            if ($suppressionsPath !== '' && is_file($suppressionsPath)) {
                $suppressionEntries = $this->suppressionRepository->loadActiveEntries($suppressionsPath);
            }

            $configPath = PathNormalizer::normalize((string) $input->getOption('config'));
            $config = $this->configLoader->loadConfig($configPath);
            $ignoredDirectories = $config->ignoredDirectories();
            $ignoredDirectories = $this->mergeIgnoredDirectories($ignoredDirectories, $input->getOption('ignore-dir'));

            $timestamp = gmdate('Ymd-His');
            $report = $this->auditRunner->run(
                $scanPath,
                $outputDirectory,
                $profile,
                $targetScore,
                $timestamp,
                $baselineEntries,
                $suppressionEntries,
                false,
                $ignoredDirectories,
                $config,
            );

            $policy = $this->resolvePolicy($input);
            $policyResult = $this->policyEvaluator->evaluate($policy, $report);
            $report['policy_name'] = $policy->name;
            $report['policy_result'] = $policyResult['pass'] ? 'pass' : 'fail';
            $report['policy_reasons'] = $policyResult['reasons'];
            $report['policy_recommended_actions'] = $policyResult['recommended_actions'];

            $this->jsonReportWriter->write($outputDirectory, $report);
            $qualityReport = $this->qualityEngine->generate(
                historyPath: rtrim($outputDirectory, '/') . '/history.jsonl',
                triagePath: rtrim($outputDirectory, '/') . '/triage.jsonl',
                latestPath: rtrim($outputDirectory, '/') . '/latest.json',
                ruleMetadataById: $this->ruleMetadataById(),
                historyWindow: 20,
            );
            $report['quality'] = [
                'overall_noise_score' => $qualityReport->overallNoiseScore,
                'top_noisy_rules' => $qualityReport->topNoisyRules,
            ];

            $this->markdownReportWriter->write($outputDirectory, $report, $timestamp);
            $this->jsonReportWriter->write($outputDirectory, $report);
            $this->historyWriter->append($outputDirectory, $report);

            $includeSuppressed = $this->parseBooleanString((string) $input->getOption('export-include-suppressed'));
            foreach ($this->normalizedExports($input->getOption('export')) as $format) {
                if ($format === 'sarif') {
                    $this->sarifExporter->write($outputDirectory, $report, $includeSuppressed);
                    continue;
                }

                if ($format === 'checkstyle') {
                    $this->checkstyleExporter->write($outputDirectory, $report, $includeSuppressed);
                    continue;
                }
            }

            $output->writeln(sprintf('Profile: %s', $report['profile']));
            $output->writeln(sprintf('Score: %d/100 (%s)', $report['score'], $report['band']));
            $output->writeln(sprintf('Target: %d', $report['target_score']));
            $output->writeln(sprintf('Invariant Failures: %d', $report['invariant_failures']));
            $output->writeln(sprintf('Findings: %d', $report['summary']['total_findings']));
            $output->writeln(sprintf('Suppressed: %d', $report['summary']['suppressed_findings']));
            $output->writeln(sprintf('Baseline: %d', $report['summary']['baseline_findings']));
            $output->writeln(sprintf('Regression: %s', ($report['regression'] ?? false) === true ? 'yes' : 'no'));
            $output->writeln(sprintf(
                'AST parsed: %d ok / %d failed',
                (int) ($report['collector_stats']['ast']['ok'] ?? 0),
                (int) ($report['collector_stats']['ast']['failed'] ?? 0)
            ));
            $output->writeln(sprintf('Files scanned: %d', (int) ($report['scan_metrics']['files_scanned_count'] ?? 0)));
            $output->writeln(sprintf('Rules executed: %d', (int) ($report['scan_metrics']['rules_executed_count'] ?? 0)));
            $output->writeln(sprintf('Scan duration: %d ms', (int) ($report['scan_metrics']['scan_duration_ms'] ?? 0)));
            $output->writeln(sprintf('Policy: %s (%s)', (string) ($report['policy_name'] ?? 'default'), (string) ($report['policy_result'] ?? 'pass')));
            $output->writeln(sprintf('Overall Noise Score: %.3f', (float) ($report['quality']['overall_noise_score'] ?? 0.0)));
            $output->writeln(sprintf('Report: %s/latest.md', $outputDirectory));

            $forecastThreshold = $input->getOption('fail-on-forecast-risk');
            if (is_scalar($forecastThreshold) && (string) $forecastThreshold !== '') {
                $threshold = (float) (string) $forecastThreshold;
                if ($threshold < 0.0 || $threshold > 1.0) {
                    throw new RuntimeException('Option --fail-on-forecast-risk must be within 0..1.');
                }

                $riskInvariant = (float) ($report['forecast']['risk_new_invariant_fail'] ?? 0.0);
                $riskDrop = (float) ($report['forecast']['risk_score_drop_5'] ?? 0.0);
                if ($riskInvariant >= $threshold || $riskDrop >= $threshold) {
                    return 8;
                }
            }

            $noiseThreshold = $input->getOption('max-noise-score');
            if (is_scalar($noiseThreshold) && (string) $noiseThreshold !== '') {
                $threshold = (float) (string) $noiseThreshold;
                if ($threshold < 0.0 || $threshold > 1.0) {
                    throw new RuntimeException('Option --max-noise-score must be within 0..1.');
                }

                $overallNoise = (float) ($report['quality']['overall_noise_score'] ?? 0.0);
                if ($overallNoise > $threshold) {
                    return 9;
                }
            }

            if ((int) $report['invariant_failures'] > 0) {
                return 2;
            }

            if ((int) $report['score'] < (int) $report['target_score']) {
                return 3;
            }

            if (($report['regression'] ?? false) === true) {
                return 5;
            }

            if (($report['policy_result'] ?? 'pass') === 'fail') {
                return 6;
            }

            return 0;
        } catch (Throwable $throwable) {
            $output->writeln(sprintf('<error>%s</error>', $throwable->getMessage()));

            return 4;
        }
    }

    /**
     * @param mixed $raw
     * @return array<int, string>
     */
    private function normalizedExports(mixed $raw): array
    {
        $formats = [];
        foreach ((array) $raw as $entry) {
            if (!is_string($entry) || trim($entry) === '') {
                continue;
            }

            $format = strtolower(trim($entry));
            if (in_array($format, ['md', 'json'], true)) {
                continue;
            }

            if (!in_array($format, ['sarif', 'checkstyle'], true)) {
                throw new RuntimeException(sprintf('Unsupported export format "%s".', $format));
            }

            $formats[$format] = true;
        }

        $result = array_keys($formats);
        sort($result, SORT_STRING);

        return $result;
    }

    /**
     * @param mixed $option
     * @return array<int, string>
     */
    private function mergeIgnoredDirectories(array $fromConfig, mixed $option): array
    {
        $dirs = $fromConfig;
        foreach ((array) $option as $entry) {
            if (!is_string($entry) || trim($entry) === '') {
                continue;
            }

            $dirs[] = trim($entry);
        }

        $dirs = array_values(array_unique($dirs));
        sort($dirs, SORT_STRING);

        return $dirs;
    }

    private function resolvePolicy(InputInterface $input): Policy
    {
        $name = strtolower((string) $input->getOption('policy'));
        if (!in_array($name, ['default', 'strict', 'dialer'], true)) {
            throw new RuntimeException(sprintf('Unsupported policy "%s".', $name));
        }

        $policy = Policy::preset($name);

        $maxNewCritical = $input->getOption('max-new-critical');
        $maxNewMajor = $input->getOption('max-new-major');
        $requireNoNewInvariants = $input->getOption('require-no-new-invariants');

        return $policy->withOverrides(
            maxNewCritical: is_scalar($maxNewCritical) && (string) $maxNewCritical !== '' ? (int) (string) $maxNewCritical : null,
            maxNewMajor: is_scalar($maxNewMajor) && (string) $maxNewMajor !== '' ? (int) (string) $maxNewMajor : null,
            requireNoNewInvariants: is_scalar($requireNoNewInvariants) && (string) $requireNoNewInvariants !== ''
                ? $this->parseBooleanString((string) $requireNoNewInvariants)
                : null,
            noRegressions: $input->getOption('no-regressions') === true ? true : null,
        );
    }

    private function parseBooleanString(string $value): bool
    {
        return match (strtolower(trim($value))) {
            '1', 'true', 'yes', 'on' => true,
            '0', 'false', 'no', 'off', '' => false,
            default => throw new RuntimeException(sprintf('Invalid boolean value "%s".', $value)),
        };
    }

    /**
     * @return array<string, \ProdAudit\Audit\Rules\RuleMetadata>
     */
    private function ruleMetadataById(): array
    {
        $metadata = [];
        foreach ($this->ruleRegistry->ids() as $ruleId) {
            $rule = $this->ruleRegistry->get($ruleId);
            if ($rule === null) {
                continue;
            }

            $metadata[$ruleId] = $rule->metadata();
        }

        ksort($metadata, SORT_STRING);

        return $metadata;
    }
}
