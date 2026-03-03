<?php

declare(strict_types=1);

namespace ProdAudit\Audit\Rules;

final class PR_DEP_003_NoVersionConstraintsRule extends ComposerHeuristicRule
{
    public function metadata(): RuleMetadata
    {
        return new RuleMetadata(
            id: 'PR-DEP-003',
            title: 'No Version Constraints',
            invariant: false,
            category: Category::DEPS->value,
            pack: 'dependency',
            defaultSeverity: Severity::Major,
            description: 'Detects dependencies with missing or unconstrained versions.',
            whyItMatters: 'Missing constraints make builds non-reproducible and risky to deploy.',
        );
    }

    protected function detect(array $composer): array
    {
        $requires = array_merge((array) ($composer['require'] ?? []), (array) ($composer['require_dev'] ?? []));
        ksort($requires, SORT_STRING);

        $issues = [];
        foreach ($requires as $package => $version) {
            $normalized = trim((string) $version);
            if ($normalized !== '' && $normalized !== '*' && $normalized !== 'dev-master') {
                continue;
            }

            $issues[] = [
                'package' => (string) $package,
                'version' => (string) $version,
                'evidence' => sprintf('composer dependency "%s" uses unconstrained version "%s".', $package, $normalized),
            ];
        }

        return $issues;
    }
}
