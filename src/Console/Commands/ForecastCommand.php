<?php

declare(strict_types=1);

namespace ProdAudit\Console\Commands;

use ProdAudit\Audit\Actions\ActionPlanner;
use ProdAudit\Audit\Forecast\ForecastEngine;
use ProdAudit\Audit\Insights\InsightEngine;
use ProdAudit\Audit\Triage\TriageStore;
use ProdAudit\Utils\PathNormalizer;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

final class ForecastCommand extends Command
{
    public function __construct(
        private readonly InsightEngine $insightEngine = new InsightEngine(),
        private readonly ForecastEngine $forecastEngine = new ForecastEngine(),
        private readonly ActionPlanner $actionPlanner = new ActionPlanner(),
        private readonly TriageStore $triageStore = new TriageStore(),
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('forecast')
            ->setDescription('Generate deterministic risk forecast from report history')
            ->addOption('out', null, InputOption::VALUE_REQUIRED, 'Output directory', 'docs/audit')
            ->addOption('history', null, InputOption::VALUE_REQUIRED, 'History window size', '20');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $out = PathNormalizer::normalize((string) $input->getOption('out'));
            $historyWindow = max(1, (int) (string) $input->getOption('history'));

            $latest = $this->readJsonFile($out . '/latest.json');
            $history = $this->readHistory($out . '/history.jsonl', $historyWindow);
            $labels = $this->triageStore->effectiveLabels($out);

            $insights = $this->insightEngine->generate($latest, $history, $labels)->toArray();
            $actions = $this->actionPlanner->plan(
                is_array($latest['findings'] ?? null) ? $latest['findings'] : [],
                $insights,
                []
            );

            $forecast = $this->forecastEngine->generate(
                $latest,
                $history,
                $insights,
                $labels,
                array_map(static fn ($action): array => $action->toArray(), $actions)
            )->toArray();

            $this->writeJson($out . '/forecast.json', $forecast);
            $this->writeMarkdown($out . '/forecast.md', $forecast);

            $output->writeln(sprintf('risk_new_invariant_fail=%.3f', (float) ($forecast['risk_new_invariant_fail'] ?? 0.0)));
            $output->writeln(sprintf('risk_score_drop_5=%.3f', (float) ($forecast['risk_score_drop_5'] ?? 0.0)));
            $output->writeln(sprintf('risk_new_critical=%.3f', (float) ($forecast['risk_new_critical'] ?? 0.0)));
            $output->writeln(sprintf('forecast_json=%s/forecast.json', $out));

            return 0;
        } catch (Throwable $throwable) {
            $output->writeln(sprintf('<error>%s</error>', $throwable->getMessage()));

            return 4;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function readJsonFile(string $path): array
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

    /**
     * @return array<int, array<string, mixed>>
     */
    private function readHistory(string $path, int $window): array
    {
        if (!is_file($path)) {
            return [];
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
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

        return array_slice($rows, -$window);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function writeJson(string $path, array $payload): void
    {
        $encoded = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($encoded === false) {
            throw new RuntimeException('Unable to encode forecast JSON.');
        }

        if (file_put_contents($path, $encoded . "\n") === false) {
            throw new RuntimeException('Unable to write forecast JSON.');
        }
    }

    /**
     * @param array<string, mixed> $forecast
     */
    private function writeMarkdown(string $path, array $forecast): void
    {
        $lines = [
            '# Forecast',
            '',
            sprintf('- risk_new_invariant_fail: %.6f', (float) ($forecast['risk_new_invariant_fail'] ?? 0.0)),
            sprintf('- risk_score_drop_5: %.6f', (float) ($forecast['risk_score_drop_5'] ?? 0.0)),
            sprintf('- risk_new_critical: %.6f', (float) ($forecast['risk_new_critical'] ?? 0.0)),
            '',
            '## Top Drivers',
        ];

        $drivers = is_array($forecast['top_drivers'] ?? null) ? $forecast['top_drivers'] : [];
        if ($drivers === []) {
            $lines[] = '- none';
        } else {
            foreach ($drivers as $driver) {
                if (!is_array($driver)) {
                    continue;
                }

                $lines[] = '- ' . json_encode($driver, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }
        }

        $lines[] = '';
        $lines[] = '## Next Checks';
        $checks = is_array($forecast['next_checks'] ?? null) ? $forecast['next_checks'] : [];
        if ($checks === []) {
            $lines[] = '- none';
        } else {
            foreach ($checks as $check) {
                if (!is_array($check)) {
                    continue;
                }

                $lines[] = sprintf('- %s: %s', (string) ($check['id'] ?? 'ACTION'), (string) ($check['title'] ?? '')); 
            }
        }

        if (file_put_contents($path, implode("\n", $lines) . "\n") === false) {
            throw new RuntimeException('Unable to write forecast markdown.');
        }
    }
}
