<?php

declare(strict_types=1);

namespace ProdAudit\Audit\Rules;

final class PR_DEP_005_OutdatedPackagesHeuristicRule extends ComposerHeuristicRule
{
    public function metadata(): RuleMetadata
    {
        return new RuleMetadata(
            id: 'PR-DEP-005',
            title: 'Outdated Packages Heuristic',
            invariant: false,
            category: Category::DEPS->value,
            pack: 'dependency',
            defaultSeverity: Severity::Minor,
            description: 'Detects dependency constraints that are likely stale or pinned to legacy major versions.',
            whyItMatters: 'Stale dependencies increase exposure to known defects and unsupported APIs.',
        );
    }

    protected function detect(array $composer): array
    {
        $requires = array_merge((array) ($composer['require'] ?? []), (array) ($composer['require_dev'] ?? []));
        ksort($requires, SORT_STRING);

        $issues = [];
        foreach ($requires as $package => $version) {
            $normalized = strtolower((string) $version);
            if (
                !str_starts_with($normalized, '^0.')
                && !str_contains($normalized, '<')
                && !str_contains($normalized, '~1.')
            ) {
                continue;
            }

            $issues[] = [
                'package' => (string) $package,
                'version' => (string) $version,
                'evidence' => sprintf('composer dependency "%s" uses legacy/stale-looking constraint "%s".', $package, $version),
            ];
        }

        return $issues;
    }
}
