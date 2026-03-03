<?php

declare(strict_types=1);

namespace ProdAudit\Audit\Rules;

final class PR_DOC_002_MissingRunbookRule extends MissingDocumentRule
{
    public function metadata(): RuleMetadata
    {
        return new RuleMetadata(
            id: 'PR-DOC-002',
            title: 'Missing Runbook',
            invariant: false,
            category: Category::DOCS->value,
            pack: 'documentation',
            defaultSeverity: Severity::Major,
            description: 'Detects absence of incident/operator runbook documentation.',
            whyItMatters: 'Without runbooks, incident response becomes inconsistent and slower.',
        );
    }

    protected function requiredPathPatterns(): array
    {
        return ['/runbook/i', '/operator-playbook/i'];
    }
}
