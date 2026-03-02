<?php

declare(strict_types=1);

namespace ProdAudit\Console\Commands;

use ProdAudit\Audit\AuditRunner;
use ProdAudit\Audit\Baseline\BaselineRepository;
use ProdAudit\Audit\Profiles\ProfileRegistry;
use ProdAudit\Audit\Suppression\SuppressionRepository;
use ProdAudit\Utils\PathNormalizer;
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
            ->addOption('suppressions', null, InputOption::VALUE_REQUIRED, 'Suppressions file path', 'prod-audit-suppressions.json');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $scanPath = PathNormalizer::normalize((string) $input->getArgument('path'));
            $outputDirectory = PathNormalizer::normalize((string) $input->getOption('out'));
            $profileName = (string) $input->getOption('profile');

            if (!is_dir($outputDirectory) && !mkdir($outputDirectory, 0777, true) && !is_dir($outputDirectory)) {
                throw new \RuntimeException('Unable to create output directory.');
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

            $timestamp = gmdate('Ymd-His');
            $report = $this->auditRunner->run(
                $scanPath,
                $outputDirectory,
                $profile,
                $targetScore,
                $timestamp,
                $baselineEntries,
                $suppressionEntries,
            );

            $output->writeln(sprintf('Profile: %s', $report['profile']));
            $output->writeln(sprintf('Score: %d/100 (%s)', $report['score'], $report['band']));
            $output->writeln(sprintf('Target: %d', $report['target_score']));
            $output->writeln(sprintf('Invariant Failures: %d', $report['invariant_failures']));
            $output->writeln(sprintf('Findings: %d', $report['summary']['total_findings']));
            $output->writeln(sprintf('Suppressed: %d', $report['summary']['suppressed_findings']));
            $output->writeln(sprintf('Baseline: %d', $report['summary']['baseline_findings']));
            $output->writeln(sprintf('Regression: %s', ($report['regression'] ?? false) === true ? 'yes' : 'no'));
            $output->writeln(sprintf('Report: %s/latest.md', $outputDirectory));

            if ((int) $report['invariant_failures'] > 0) {
                return 2;
            }

            if ((int) $report['score'] < (int) $report['target_score']) {
                return 3;
            }

            if (($report['regression'] ?? false) === true) {
                return 5;
            }

            return 0;
        } catch (Throwable $throwable) {
            $output->writeln(sprintf('<error>%s</error>', $throwable->getMessage()));

            return 4;
        }
    }
}
