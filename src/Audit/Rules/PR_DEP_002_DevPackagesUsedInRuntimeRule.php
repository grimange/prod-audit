<?php

declare(strict_types=1);

namespace ProdAudit\Audit\Rules;

final class PR_DEP_002_DevPackagesUsedInRuntimeRule extends ComposerHeuristicRule
{
    public function metadata(): RuleMetadata
    {
        return new RuleMetadata(
            id: 'PR-DEP-002',
            title: 'Dev Packages Used In Runtime',
            invariant: false,
            category: Category::DEPS->value,
            pack: 'dependency',
            defaultSeverity: Severity::Major,
            description: 'Detects development-only packages listed in runtime dependencies.',
            whyItMatters: 'Runtime inclusion of dev packages expands attack surface and deployment size.',
        );
    }

    protected function detect(array $composer): array
    {
        $requires = (array) ($composer['require'] ?? []);
        ksort($requires, SORT_STRING);

        $devOnly = ['phpunit/phpunit', 'mockery/mockery', 'friendsofphp/php-cs-fixer', 'phpstan/phpstan'];
        $issues = [];
        foreach ($requires as $package => $version) {
            $packageName = strtolower((string) $package);
            if (!in_array($packageName, $devOnly, true)) {
                continue;
            }

            $issues[] = [
                'package' => (string) $package,
                'version' => (string) $version,
                'evidence' => sprintf('composer require contains dev-only package "%s".', $package),
            ];
        }

        return $issues;
    }
}
