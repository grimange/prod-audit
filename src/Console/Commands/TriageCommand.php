<?php

declare(strict_types=1);

namespace ProdAudit\Console\Commands;

use ProdAudit\Audit\Triage\TriageEvent;
use ProdAudit\Audit\Triage\TriageStore;
use ProdAudit\Utils\PathNormalizer;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

final class TriageCommand extends Command
{
    public function __construct(
        private readonly TriageStore $triageStore = new TriageStore(),
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('triage')
            ->setDescription('Label a finding fingerprint in triage store')
            ->addArgument('fingerprint', InputArgument::REQUIRED, 'Finding fingerprint')
            ->addOption('label', null, InputOption::VALUE_REQUIRED, 'Triage label')
            ->addOption('note', null, InputOption::VALUE_REQUIRED, 'Optional note')
            ->addOption('actor', null, InputOption::VALUE_REQUIRED, 'Optional actor')
            ->addOption('out', null, InputOption::VALUE_REQUIRED, 'Output directory', 'docs/audit')
            ->addOption('allow-unknown', null, InputOption::VALUE_NONE, 'Allow unknown fingerprint not present in latest.json');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $out = PathNormalizer::normalize((string) $input->getOption('out'));
            $fingerprint = trim((string) $input->getArgument('fingerprint'));
            $label = trim((string) $input->getOption('label'));

            if ($fingerprint === '') {
                throw new RuntimeException('Fingerprint is required.');
            }

            if ($label === '' || !in_array($label, TriageEvent::labels(), true)) {
                throw new RuntimeException('Invalid label. Allowed: ' . implode(', ', TriageEvent::labels()));
            }

            $finding = $this->triageStore->latestFindingForFingerprint($out, $fingerprint);
            if ($input->getOption('allow-unknown') !== true && $finding === null) {
                throw new RuntimeException('Fingerprint not found in latest.json. Use --allow-unknown to override.');
            }

            $ruleId = is_array($finding) ? (string) ($finding['rule_id'] ?? '') : '';
            if ($ruleId === '' && $finding === null) {
                $effective = $this->triageStore->effectiveEventsByFingerprint($out);
                $ruleId = isset($effective[$fingerprint]) ? $effective[$fingerprint]->ruleId : 'UNKNOWN';
            }

            if ($ruleId === '') {
                $ruleId = 'UNKNOWN';
            }

            $event = new TriageEvent(
                timestampIso: gmdate(DATE_ATOM),
                fingerprint: $fingerprint,
                ruleId: $ruleId,
                label: $label,
                note: $this->optionalString($input->getOption('note')),
                actor: $this->optionalString($input->getOption('actor')),
            );

            $this->triageStore->append($out, $event);
            $effectiveLabel = $this->triageStore->effectiveLabel($out, $fingerprint) ?? 'none';

            $output->writeln(sprintf('triage_event=appended fingerprint=%s label=%s', $fingerprint, $label));
            $output->writeln(sprintf('effective_label=%s', $effectiveLabel));

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
