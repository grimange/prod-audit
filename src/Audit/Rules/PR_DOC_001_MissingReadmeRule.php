<?php

declare(strict_types=1);

namespace ProdAudit\Audit\Rules;

final class PR_DOC_001_MissingReadmeRule extends MissingDocumentRule
{
    public function metadata(): RuleMetadata
    {
        return new RuleMetadata(
            id: 'PR-DOC-001',
            title: 'Missing README',
            invariant: false,
            category: Category::DOCS->value,
            pack: 'documentation',
            defaultSeverity: Severity::Major,
            description: 'Detects repositories without a top-level README document.',
            whyItMatters: 'Missing README increases onboarding and operational recovery time.',
        );
    }

    protected function requiredPathPatterns(): array
    {
        return ['/^(readme\.md|readme\.txt|readme)$/i'];
    }
}
