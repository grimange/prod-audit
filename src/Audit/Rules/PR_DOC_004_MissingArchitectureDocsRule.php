<?php

declare(strict_types=1);

namespace ProdAudit\Audit\Rules;

final class PR_DOC_004_MissingArchitectureDocsRule extends MissingDocumentRule
{
    public function metadata(): RuleMetadata
    {
        return new RuleMetadata(
            id: 'PR-DOC-004',
            title: 'Missing Architecture Docs',
            invariant: false,
            category: Category::DOCS->value,
            pack: 'documentation',
            defaultSeverity: Severity::Minor,
            description: 'Detects absence of architecture/design documentation artifacts.',
            whyItMatters: 'Architecture docs reduce knowledge silos and unsafe production changes.',
        );
    }

    protected function requiredPathPatterns(): array
    {
        return ['/architecture/i', '/design/i', '/implementation-plan/i'];
    }
}
