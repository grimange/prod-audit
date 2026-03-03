<?php

declare(strict_types=1);

namespace ProdAudit\Audit\Rules;

final class PR_DOC_003_MissingFailureModesRule extends MissingDocumentRule
{
    public function metadata(): RuleMetadata
    {
        return new RuleMetadata(
            id: 'PR-DOC-003',
            title: 'Missing Failure Modes',
            invariant: false,
            category: Category::DOCS->value,
            pack: 'documentation',
            defaultSeverity: Severity::Minor,
            description: 'Detects missing failure-mode or incident-scenarios documentation.',
            whyItMatters: 'Failure-mode docs improve preparedness for known bad states.',
        );
    }

    protected function requiredPathPatterns(): array
    {
        return ['/failure[-_ ]?mode/i', '/incident/i'];
    }
}
