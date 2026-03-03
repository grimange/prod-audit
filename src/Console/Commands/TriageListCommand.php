<?php

declare(strict_types=1);

namespace ProdAudit\Console\Commands;

use ProdAudit\Audit\Triage\TriageEvent;
use ProdAudit\Audit\Triage\TriageStore;
use ProdAudit\Utils\PathNormalizer;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

final class TriageListCommand extends Command
{
    public function __construct(
        private readonly TriageStore $triageStore = new TriageStore(),
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('triage-list')
            ->setDescription('List effective triage labels')
            ->addOption('label', null, InputOption::VALUE_REQUIRED, 'Filter label')
            ->addOption('rule', null, InputOption::VALUE_REQUIRED, 'Filter rule id')
            ->addOption('out', null, InputOption::VALUE_REQUIRED, 'Output directory', 'docs/audit');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $out = PathNormalizer::normalize((string) $input->getOption('out'));
            $label = $this->optionalString($input->getOption('label'));
            $rule = $this->optionalString($input->getOption('rule'));

            if ($label !== null && !in_array($label, TriageEvent::labels(), true)) {
                throw new RuntimeException('Invalid label filter. Allowed: ' . implode(', ', TriageEvent::labels()));
            }

            $rows = $this->triageStore->listEffective($out, $label, $rule);

            $output->writeln('fingerprint | rule_id | label | timestamp_iso | actor | note');
            foreach ($rows as $row) {
                $output->writeln(sprintf(
                    '%s | %s | %s | %s | %s | %s',
                    (string) ($row['fingerprint'] ?? ''),
                    (string) ($row['rule_id'] ?? ''),
                    (string) ($row['label'] ?? ''),
                    (string) ($row['timestamp_iso'] ?? ''),
                    (string) ($row['actor'] ?? ''),
                    str_replace("\n", ' ', (string) ($row['note'] ?? '')),
                ));
            }

            $output->writeln(sprintf('rows=%d', count($rows)));

            return 0;
        } catch (Throwable $throwable) {
            $output->writeln(sprintf('<error>%s</error>', $throwable->getMessage()));

            return 4;
        }
    }

    private function optionalString(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
