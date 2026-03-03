<?php

declare(strict_types=1);

namespace ProdAudit\Audit\Rules;

final class PR_DOC_005_MissingConfigDocsRule extends MissingDocumentRule
{
    public function metadata(): RuleMetadata
    {
        return new RuleMetadata(
            id: 'PR-DOC-005',
            title: 'Missing Config Docs',
            invariant: false,
            category: Category::DOCS->value,
            pack: 'documentation',
            defaultSeverity: Severity::Minor,
            description: 'Detects absence of environment/configuration documentation.',
            whyItMatters: 'Missing config docs creates deployment drift and startup failures.',
        );
    }

    protected function requiredPathPatterns(): array
    {
        return ['/config/i', '/env/i'];
    }
}
