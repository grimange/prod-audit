<?php

declare(strict_types=1);

namespace ProdAudit\Console\Commands;

use ProdAudit\Audit\Quality\SuggestionMap;
use ProdAudit\Utils\PathNormalizer;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

final class TriageSuggestCommand extends Command
{
    public function __construct(
        private readonly SuggestionMap $suggestionMap = new SuggestionMap(),
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('triage-suggest')
            ->setDescription('Generate deterministic rule-tuning suggestions from quality + insights')
            ->addOption('out', null, InputOption::VALUE_REQUIRED, 'Output directory', 'docs/audit');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $out = PathNormalizer::normalize((string) $input->getOption('out'));
            $quality = $this->readJson($out . '/quality.json');
            $latest = $this->readJson($out . '/latest.json');

            $rules = is_array($quality['rules'] ?? null) ? $quality['rules'] : [];
            $suggestions = [];

            foreach ($rules as $row) {
                if (!is_array($row)) {
                    continue;
                }

                $ruleId = (string) ($row['rule_id'] ?? '');
                if ($ruleId === '') {
                    continue;
                }

                $noise = (float) ($row['noise_score'] ?? 0.0);
                if ($noise <= 0.0) {
                    continue;
                }

                $patterns = $this->detectPatterns($ruleId, $latest);
                foreach ($patterns as $pattern => $value) {
                    $text = $this->suggestionMap->suggest($ruleId, $pattern, $value);
                    if ($text === null) {
                        continue;
                    }

                    $suggestions[$ruleId . ':' . $pattern . ':' . (string) $value] = [
                        'rule_id' => $ruleId,
                        'pattern' => $pattern,
                        'suggestion' => $text,
                    ];
                }
            }

            $rows = array_values($suggestions);
            usort($rows, static function (array $a, array $b): int {
                $ruleCmp = strcmp((string) ($a['rule_id'] ?? ''), (string) ($b['rule_id'] ?? ''));
                if ($ruleCmp !== 0) {
                    return $ruleCmp;
                }

                $patternCmp = strcmp((string) ($a['pattern'] ?? ''), (string) ($b['pattern'] ?? ''));
                if ($patternCmp !== 0) {
                    return $patternCmp;
                }

                return strcmp((string) ($a['suggestion'] ?? ''), (string) ($b['suggestion'] ?? ''));
            });

            $markdownPath = $out . '/suggestions.md';
            $lines = ['# Rule Tuning Suggestions', ''];
            if ($rows === []) {
                $lines[] = '- No suggestions.';
            } else {
                foreach ($rows as $row) {
                    $lines[] = sprintf('- [%s] %s', (string) ($row['rule_id'] ?? ''), (string) ($row['suggestion'] ?? ''));
                }
            }

            if (file_put_contents($markdownPath, implode("\n", $lines) . "\n") === false) {
                throw new RuntimeException('Unable to write suggestions markdown.');
            }

            foreach ($rows as $row) {
                $output->writeln(sprintf('%s | %s', (string) ($row['rule_id'] ?? ''), (string) ($row['suggestion'] ?? '')));
            }
            $output->writeln('suggestions=' . count($rows));

            return 0;
        } catch (Throwable $throwable) {
            $output->writeln(sprintf('<error>%s</error>', $throwable->getMessage()));

            return 4;
        }
    }

    /**
     * @return array<string, ?string>
     */
    private function detectPatterns(string $ruleId, array $latest): array
    {
        $patterns = [];
        $findings = is_array($latest['findings'] ?? null) ? $latest['findings'] : [];
        $ruleFindings = array_values(array_filter($findings, static fn (mixed $finding): bool => is_array($finding) && (($finding['rule_id'] ?? '') === $ruleId)));
        if ($ruleFindings === []) {
            return $patterns;
        }

        $testEvidence = 0;
        $totalEvidence = 0;
        $regexEvidence = 0;
        $loggerMethods = [];

        foreach ($ruleFindings as $finding) {
            if (!is_array($finding)) {
                continue;
            }

            $evidenceList = is_array($finding['evidence'] ?? null) ? $finding['evidence'] : [];
            foreach ($evidenceList as $evidence) {
                if (!is_array($evidence)) {
                    continue;
                }

                ++$totalEvidence;
                $file = strtolower((string) ($evidence['file'] ?? ''));
                if (str_contains($file, 'tests/')) {
                    ++$testEvidence;
                }

                $type = (string) ($evidence['type'] ?? '');
                if ($type === 'grep_match' || $type === 'file_snippet') {
                    ++$regexEvidence;
                }

                if ($ruleId === 'PR-OBS-001') {
                    $excerpt = (string) ($evidence['excerpt'] ?? '');
                    if (preg_match('/->\s*([a-z_]+)\s*\(/i', $excerpt, $m) === 1) {
                        $loggerMethods[] = strtolower((string) ($m[1] ?? ''));
                    }
                }
            }
        }

        if ($totalEvidence > 0 && ($testEvidence / $totalEvidence) >= 0.5) {
            $patterns['tests_path_concentration'] = null;
        }

        if ($totalEvidence > 0 && ($regexEvidence / $totalEvidence) >= 0.5) {
            $patterns['regex_only_noise'] = null;
        }

        if ($ruleId === 'PR-OBS-001' && $loggerMethods !== []) {
            sort($loggerMethods, SORT_STRING);
            $patterns['logger_method_allowlist'] = $loggerMethods[0];
        }

        if ($ruleId === 'PR-ERR-001') {
            $patterns['intentional_empty_catch'] = null;
        }
        if ($ruleId === 'PR-TIME-001') {
            $patterns['shared_timeout_variable'] = null;
        }
        if ($ruleId === 'PR-LOCK-001') {
            $patterns['owner_token_wrapper'] = null;
        }
        if ($ruleId === 'PR-HANG-001') {
            $patterns['worker_scope_only'] = null;
        }

        ksort($patterns, SORT_STRING);

        return $patterns;
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
