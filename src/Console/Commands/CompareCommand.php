<?php

declare(strict_types=1);

namespace ProdAudit\Console\Commands;

use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

final class CompareCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('compare')
            ->setDescription('Compare two JSON audit reports')
            ->addArgument('file1', InputArgument::REQUIRED, 'First report JSON file')
            ->addArgument('file2', InputArgument::REQUIRED, 'Second report JSON file');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $report1 = $this->readReport((string) $input->getArgument('file1'));
            $report2 = $this->readReport((string) $input->getArgument('file2'));

            $score1 = (int) ($report1['score'] ?? 0);
            $score2 = (int) ($report2['score'] ?? 0);
            $delta = $score2 - $score1;

            $fingerprints1 = $this->sortedFingerprints($report1);
            $fingerprints2 = $this->sortedFingerprints($report2);

            $new = array_values(array_diff($fingerprints2, $fingerprints1));
            $resolved = array_values(array_diff($fingerprints1, $fingerprints2));
            $repeated = array_values(array_intersect($fingerprints1, $fingerprints2));

            sort($new, SORT_STRING);
            sort($resolved, SORT_STRING);
            sort($repeated, SORT_STRING);

            $output->writeln('Score difference:');
            $output->writeln('');
            $output->writeln(sprintf('Score1: %d', $score1));
            $output->writeln(sprintf('Score2: %d', $score2));
            $output->writeln(sprintf('Delta: %d', $delta));
            $output->writeln('');
            $output->writeln('New Findings:');
            foreach ($this->withFallback($new) as $fingerprint) {
                $output->writeln(sprintf('- %s', $fingerprint));
            }
            $output->writeln('');
            $output->writeln('Resolved Findings:');
            foreach ($this->withFallback($resolved) as $fingerprint) {
                $output->writeln(sprintf('- %s', $fingerprint));
            }
            $output->writeln('');
            $output->writeln('Repeated Findings:');
            foreach ($this->withFallback($repeated) as $fingerprint) {
                $output->writeln(sprintf('- %s', $fingerprint));
            }

            return 0;
        } catch (Throwable $throwable) {
            $output->writeln(sprintf('<error>%s</error>', $throwable->getMessage()));

            return 4;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function readReport(string $path): array
    {
        if (!is_file($path)) {
            throw new RuntimeException(sprintf('Report file not found: %s', $path));
        }

        $raw = file_get_contents($path);
        if (!is_string($raw)) {
            throw new RuntimeException(sprintf('Unable to read report file: %s', $path));
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new RuntimeException(sprintf('Invalid report JSON: %s', $path));
        }

        return $decoded;
    }

    /**
     * @param array<string, mixed> $report
     * @return array<int, string>
     */
    private function sortedFingerprints(array $report): array
    {
        $findings = $report['findings'] ?? [];
        if (!is_array($findings)) {
            return [];
        }

        $fingerprints = [];
        foreach ($findings as $finding) {
            if (!is_array($finding)) {
                continue;
            }

            $fingerprint = $finding['fingerprint'] ?? null;
            if (is_string($fingerprint) && $fingerprint !== '') {
                $fingerprints[] = $fingerprint;
            }
        }

        $fingerprints = array_values(array_unique($fingerprints));
        sort($fingerprints, SORT_STRING);

        return $fingerprints;
    }

    /**
     * @param array<int, string> $items
     * @return array<int, string>
     */
    private function withFallback(array $items): array
    {
        if ($items === []) {
            return ['none'];
        }

        return $items;
    }
}
