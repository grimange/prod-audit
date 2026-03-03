<?php

declare(strict_types=1);

namespace ProdAudit\Audit\Rules;

final class PR_DEP_004_WildcardDependenciesRule extends ComposerHeuristicRule
{
    public function metadata(): RuleMetadata
    {
        return new RuleMetadata(
            id: 'PR-DEP-004',
            title: 'Wildcard Dependencies',
            invariant: false,
            category: Category::DEPS->value,
            pack: 'dependency',
            defaultSeverity: Severity::Major,
            description: 'Detects wildcard dependency constraints in composer manifests.',
            whyItMatters: 'Wildcard versions introduce drift and break deterministic deploys.',
        );
    }

    protected function detect(array $composer): array
    {
        $requires = array_merge((array) ($composer['require'] ?? []), (array) ($composer['require_dev'] ?? []));
        ksort($requires, SORT_STRING);

        $issues = [];
        foreach ($requires as $package => $version) {
            $normalized = (string) $version;
            if (!str_contains($normalized, '*')) {
                continue;
            }

            $issues[] = [
                'package' => (string) $package,
                'version' => $normalized,
                'evidence' => sprintf('composer dependency "%s" uses wildcard constraint "%s".', $package, $normalized),
            ];
        }

        return $issues;
    }
}
