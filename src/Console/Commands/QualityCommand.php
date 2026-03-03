<?php

declare(strict_types=1);

namespace ProdAudit\Console\Commands;

use ProdAudit\Audit\Quality\QualityEngine;
use ProdAudit\Audit\Rules\RuleRegistry;
use ProdAudit\Utils\PathNormalizer;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

final class QualityCommand extends Command
{
    public function __construct(
        private readonly RuleRegistry $ruleRegistry,
        private readonly QualityEngine $qualityEngine = new QualityEngine(),
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('quality')
            ->setDescription('Generate deterministic rule quality report')
            ->addOption('out', null, InputOption::VALUE_REQUIRED, 'Output directory', 'docs/audit')
            ->addOption('history', null, InputOption::VALUE_REQUIRED, 'History window size', '20');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $out = PathNormalizer::normalize((string) $input->getOption('out'));
            $historyWindow = max(1, (int) (string) $input->getOption('history'));
            if (!is_dir($out) && !mkdir($out, 0777, true) && !is_dir($out)) {
                throw new RuntimeException('Unable to create output directory.');
            }

            $metadataByRule = [];
            foreach ($this->ruleRegistry->ids() as $ruleId) {
                $rule = $this->ruleRegistry->get($ruleId);
                if ($rule === null) {
                    continue;
                }
                $metadataByRule[$ruleId] = $rule->metadata();
            }
            ksort($metadataByRule, SORT_STRING);

            $report = $this->qualityEngine->generate(
                historyPath: $out . '/history.jsonl',
                triagePath: $out . '/triage.jsonl',
                latestPath: $out . '/latest.json',
                ruleMetadataById: $metadataByRule,
                historyWindow: $historyWindow,
            );

            $this->writeJson($out . '/quality.json', $report->toArray());
            $this->writeMarkdown($out . '/quality.md', $report->toArray());

            $output->writeln('Top 10 noisiest rules:');
            foreach ($report->topNoisyRules as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $output->writeln(sprintf(
                    '- %s noise=%.3f findings=%d',
                    (string) ($row['rule_id'] ?? ''),
                    (float) ($row['noise_score'] ?? 0.0),
                    (int) ($row['findings_count'] ?? 0)
                ));
            }

            $output->writeln('Top 10 valuable rules:');
            foreach ($report->topValuableRules as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $output->writeln(sprintf(
                    '- %s value=%.3f persistence=%.3f noise=%.3f',
                    (string) ($row['rule_id'] ?? ''),
                    (float) ($row['value_score'] ?? 0.0),
                    (float) ($row['persistence_rate'] ?? 0.0),
                    (float) ($row['noise_score'] ?? 0.0)
                ));
            }

            return 0;
        } catch (Throwable $throwable) {
            $output->writeln(sprintf('<error>%s</error>', $throwable->getMessage()));

            return 4;
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function writeJson(string $path, array $payload): void
    {
        $encoded = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($encoded === false) {
            throw new RuntimeException('Unable to encode quality JSON.');
        }

        if (file_put_contents($path, $encoded . "\n") === false) {
            throw new RuntimeException('Unable to write quality JSON.');
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function writeMarkdown(string $path, array $payload): void
    {
        $lines = [
            '# Rule Quality Report',
            '',
            sprintf('- overall_noise_score: %.6f', (float) ($payload['overall_noise_score'] ?? 0.0)),
            '',
            '## Top Noisy Rules',
        ];

        $topNoisy = is_array($payload['top_noisy_rules'] ?? null) ? $payload['top_noisy_rules'] : [];
        if ($topNoisy === []) {
            $lines[] = '- none';
        } else {
            foreach ($topNoisy as $row) {
                if (!is_array($row)) {
                    continue;
                }

                $lines[] = sprintf(
                    '- %s noise=%.6f findings=%d',
                    (string) ($row['rule_id'] ?? ''),
                    (float) ($row['noise_score'] ?? 0.0),
                    (int) ($row['findings_count'] ?? 0)
                );
            }
        }

        $lines[] = '';
        $lines[] = '## Top Valuable Rules';
        $topValuable = is_array($payload['top_valuable_rules'] ?? null) ? $payload['top_valuable_rules'] : [];
        if ($topValuable === []) {
            $lines[] = '- none';
        } else {
            foreach ($topValuable as $row) {
                if (!is_array($row)) {
                    continue;
                }

                $lines[] = sprintf(
                    '- %s value=%.6f persistence=%.6f noise=%.6f',
                    (string) ($row['rule_id'] ?? ''),
                    (float) ($row['value_score'] ?? 0.0),
                    (float) ($row['persistence_rate'] ?? 0.0),
                    (float) ($row['noise_score'] ?? 0.0)
                );
            }
        }

        $lines[] = '';
        $lines[] = '## Rule Metrics';
        $rules = is_array($payload['rules'] ?? null) ? $payload['rules'] : [];
        if ($rules === []) {
            $lines[] = '- none';
        } else {
            foreach ($rules as $row) {
                if (!is_array($row)) {
                    continue;
                }

                $lines[] = sprintf(
                    '- %s noise=%.6f precision=%.6f persistence=%.6f labeled=%d findings=%d',
                    (string) ($row['rule_id'] ?? ''),
                    (float) ($row['noise_score'] ?? 0.0),
                    (float) ($row['precision_score'] ?? 0.0),
                    (float) ($row['persistence_rate'] ?? 0.0),
                    (int) ($row['labeled_count'] ?? 0),
                    (int) ($row['findings_count'] ?? 0)
                );
            }
        }

        if (file_put_contents($path, implode("\n", $lines) . "\n") === false) {
            throw new RuntimeException('Unable to write quality markdown.');
        }
    }
}
