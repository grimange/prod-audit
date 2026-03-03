<?php

declare(strict_types=1);

namespace ProdAudit\Audit\Quality;

use ProdAudit\Audit\Collectors\AstCollector;
use ProdAudit\Audit\Collectors\FileCollector;
use ProdAudit\Audit\Collectors\PatternCollector;
use ProdAudit\Audit\Rules\RuleRegistry;
use RuntimeException;

final class FixtureRunner
{
    public function __construct(
        private readonly RuleRegistry $ruleRegistry,
        private readonly FileCollector $fileCollector = new FileCollector(),
        private readonly PatternCollector $patternCollector = new PatternCollector(),
        private readonly AstCollector $astCollector = new AstCollector(),
    ) {
    }

    public function suiteFor(string $fixturesRoot, string $ruleId): FixtureSuite
    {
        $ruleDir = rtrim($fixturesRoot, '/') . '/' . strtolower($ruleId);
        if (!is_dir($ruleDir)) {
            throw new RuntimeException(sprintf('Fixture directory not found: %s', $ruleDir));
        }

        $good = glob($ruleDir . '/good_*.php') ?: [];
        $bad = glob($ruleDir . '/bad_*.php') ?: [];

        $good = array_map('strval', $good);
        $bad = array_map('strval', $bad);
        sort($good, SORT_STRING);
        sort($bad, SORT_STRING);

        return new FixtureSuite($ruleId, $good, $bad);
    }

    /**
     * @return array<string, mixed>
     */
    public function runSuite(FixtureSuite $suite): array
    {
        $rule = $this->ruleRegistry->get($suite->ruleId);
        if ($rule === null) {
            throw new RuntimeException(sprintf('Rule not found: %s', $suite->ruleId));
        }

        $goodResults = [];
        foreach ($suite->goodFiles as $path) {
            $result = $rule->evaluate($this->collectorDataForFile($path));
            $goodResults[] = [
                'file' => $path,
                'findings_count' => count($result->findings),
            ];
        }

        $badResults = [];
        foreach ($suite->badFiles as $path) {
            $result = $rule->evaluate($this->collectorDataForFile($path));
            $badResults[] = [
                'file' => $path,
                'findings_count' => count($result->findings),
            ];
        }

        return [
            'rule_id' => $suite->ruleId,
            'good' => $goodResults,
            'bad' => $badResults,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function collectorDataForFile(string $path): array
    {
        $relative = ltrim(str_replace(getcwd() ?: '', '', $path), '/');
        $files = [[
            'path' => $path,
            'relative_path' => $relative,
            'extension' => pathinfo($path, PATHINFO_EXTENSION),
            'size' => filesize($path) ?: 0,
        ]];

        return [
            'patterns' => $this->patternCollector->collect($files),
            'ast' => $this->astCollector->collect($files, $this->fileCollector),
            'config' => [],
        ];
    }
}
