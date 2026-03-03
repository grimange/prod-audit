<?php

declare(strict_types=1);

namespace ProdAudit\Audit\Rules;

final class PR_DEP_001_AbandonedPackagesRule extends ComposerHeuristicRule
{
    public function metadata(): RuleMetadata
    {
        return new RuleMetadata(
            id: 'PR-DEP-001',
            title: 'Abandoned Packages',
            invariant: false,
            category: Category::DEPS->value,
            pack: 'dependency',
            defaultSeverity: Severity::Major,
            description: 'Detects packages heuristically known as deprecated or abandoned.',
            whyItMatters: 'Abandoned dependencies accumulate unpatched vulnerabilities and operational risk.',
        );
    }

    protected function detect(array $composer): array
    {
        $requires = array_merge((array) ($composer['require'] ?? []), (array) ($composer['require_dev'] ?? []));
        ksort($requires, SORT_STRING);

        $heuristicNeedles = ['swiftmailer', 'zendframework', 'phpunit/php-token-stream'];
        $issues = [];
        foreach ($requires as $package => $version) {
            $packageName = strtolower((string) $package);
            foreach ($heuristicNeedles as $needle) {
                if (!str_contains($packageName, $needle)) {
                    continue;
                }

                $issues[] = [
                    'package' => (string) $package,
                    'version' => (string) $version,
                    'evidence' => sprintf('composer dependency "%s" matches abandoned heuristic "%s".', $package, $needle),
                ];
            }
        }

        return $issues;
    }
}
