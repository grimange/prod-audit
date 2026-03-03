<?php

declare(strict_types=1);

namespace ProdAudit\Console\Commands;

use ProdAudit\Audit\Triage\TriageStore;
use ProdAudit\Utils\PathNormalizer;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

final class ReproduceCommand extends Command
{
    public function __construct(
        private readonly TriageStore $triageStore = new TriageStore(),
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('reproduce')
            ->setDescription('Build deterministic debug packet for a finding fingerprint')
            ->addArgument('fingerprint', InputArgument::REQUIRED, 'Finding fingerprint')
            ->addOption('out', null, InputOption::VALUE_REQUIRED, 'Output directory', 'docs/audit');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $out = PathNormalizer::normalize((string) $input->getOption('out'));
            $fingerprint = trim((string) $input->getArgument('fingerprint'));
            if ($fingerprint === '') {
                throw new RuntimeException('Fingerprint is required.');
            }

            $latestPath = $out . '/latest.json';
            $latest = $this->readJson($latestPath);
            $findings = is_array($latest['findings'] ?? null) ? $latest['findings'] : [];
            $finding = $this->findFinding($findings, $fingerprint);
            if ($finding === null) {
                throw new RuntimeException('Fingerprint not found in latest.json.');
            }

            $ruleId = (string) ($finding['rule_id'] ?? '');
            $label = $this->triageStore->effectiveLabel($out, $fingerprint) ?? 'unlabeled';
            $evidence = is_array($finding['evidence'] ?? null) ? $finding['evidence'] : [];
            $snippet = $this->firstSnippet($evidence);

            $packetPath = $out . '/reproduce/' . $fingerprint . '.md';
            $packetDir = dirname($packetPath);
            if (!is_dir($packetDir) && !mkdir($packetDir, 0777, true) && !is_dir($packetDir)) {
                throw new RuntimeException('Unable to create reproduce output directory.');
            }

            $lines = [
                '# Reproduce Packet',
                '',
                '- fingerprint: ' . $fingerprint,
                '- rule_id: ' . $ruleId,
                '- triage_label: ' . $label,
                '- title: ' . (string) ($finding['title'] ?? ''),
                '- message: ' . (string) ($finding['message'] ?? ''),
                '',
                '## Evidence Refs',
            ];

            if ($evidence === []) {
                $lines[] = '- none';
            } else {
                foreach ($evidence as $row) {
                    if (!is_array($row)) {
                        continue;
                    }

                    $lines[] = sprintf(
                        '- %s %s:%d %s',
                        (string) ($row['type'] ?? ''),
                        (string) ($row['file'] ?? ''),
                        (int) ($row['line_start'] ?? 1),
                        (string) ($row['hash'] ?? '')
                    );
                }
            }

            $lines[] = '';
            $lines[] = '## Matched Snippet';
            $lines[] = '';
            $lines[] = '```php';
            $lines[] = $snippet;
            $lines[] = '```';

            if (file_put_contents($packetPath, implode("\n", $lines) . "\n") === false) {
                throw new RuntimeException('Unable to write reproduce packet.');
            }

            $output->writeln('rule_id=' . $ruleId);
            $output->writeln('effective_label=' . $label);
            $output->writeln('snippet=' . str_replace("\n", ' ', $snippet));
            $output->writeln('packet=' . $packetPath);

            return 0;
        } catch (Throwable $throwable) {
            $output->writeln(sprintf('<error>%s</error>', $throwable->getMessage()));

            return 4;
        }
    }

    /**
     * @param array<int, array<string, mixed>> $findings
     * @return array<string, mixed>|null
     */
    private function findFinding(array $findings, string $fingerprint): ?array
    {
        foreach ($findings as $finding) {
            if (!is_array($finding)) {
                continue;
            }

            if ((string) ($finding['fingerprint'] ?? '') === $fingerprint) {
                return $finding;
            }
        }

        return null;
    }

    /**
     * @param array<int, array<string, mixed>> $evidence
     */
    private function firstSnippet(array $evidence): string
    {
        foreach ($evidence as $row) {
            if (!is_array($row)) {
                continue;
            }

            $excerpt = (string) ($row['excerpt'] ?? '');
            if ($excerpt !== '') {
                return $excerpt;
            }
        }

        return '';
    }

    /**
     * @return array<string, mixed>
     */
    private function readJson(string $path): array
    {
        if (!is_file($path)) {
            throw new RuntimeException(sprintf('File not found: %s', $path));
        }

        $raw = file_get_contents($path);
        if (!is_string($raw)) {
            throw new RuntimeException(sprintf('Unable to read file: %s', $path));
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new RuntimeException(sprintf('Invalid JSON file: %s', $path));
        }

        return $decoded;
    }
}
