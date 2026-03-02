<?php

declare(strict_types=1);

namespace ProdAudit\Console\Commands;

use ProdAudit\Audit\AuditRunner;
use ProdAudit\Audit\Baseline\BaselineRepository;
use ProdAudit\Audit\Profiles\ProfileRegistry;
use ProdAudit\Utils\PathNormalizer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

final class BaselineCommand extends Command
{
    public function __construct(
        private readonly AuditRunner $auditRunner,
        private readonly ProfileRegistry $profileRegistry,
        private readonly BaselineRepository $baselineRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('baseline')
            ->setDescription('Create production audit baseline file')
            ->addArgument('path', InputArgument::REQUIRED, 'Path to scan')
            ->addOption('profile', null, InputOption::VALUE_REQUIRED, 'Audit profile name', 'dialer-24x7')
            ->addOption('target-score', null, InputOption::VALUE_REQUIRED, 'Target score override')
            ->addOption('file', null, InputOption::VALUE_REQUIRED, 'Baseline output file', 'prod-audit-baseline.json');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $scanPath = PathNormalizer::normalize((string) $input->getArgument('path'));
            $profileName = (string) $input->getOption('profile');
            $profile = $this->profileRegistry->get($profileName);

            $targetScore = (int) ($input->getOption('target-score') !== null
                ? (string) $input->getOption('target-score')
                : (string) $profile->targetScore());

            $baselinePath = PathNormalizer::normalize((string) $input->getOption('file'));
            $findings = $this->auditRunner->collectFindings($scanPath, $profile);

            $this->baselineRepository->write(
                $baselinePath,
                $profile->name(),
                $targetScore,
                $findings,
                gmdate(DATE_ATOM),
            );

            $output->writeln(sprintf('Baseline: %s', $baselinePath));
            $output->writeln(sprintf('Profile: %s', $profile->name()));
            $output->writeln(sprintf('Target: %d', $targetScore));
            $output->writeln(sprintf('Accepted Findings: %d', count($findings)));

            return 0;
        } catch (Throwable $throwable) {
            $output->writeln(sprintf('<error>%s</error>', $throwable->getMessage()));

            return 4;
        }
    }
}
