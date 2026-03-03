<?php

declare(strict_types=1);

namespace ProdAudit\Audit\Quality;

final class SuggestionMap
{
    /**
     * @var array<string, array<string, string>>
     */
    private const MAP = [
        'PR-OBS-001' => [
            'logger_method_allowlist' => 'Add allowlist for logger method "%s" in PR-OBS-001 rule_config.allow_log_methods.',
            'tests_path_concentration' => 'Add suppression for path glob "tests/*" for PR-OBS-001 if test-only logging patterns are intentional.',
            'regex_only_noise' => 'Tighten PR-OBS-001 heuristic: require AST confirmation before emitting findings.',
        ],
        'PR-ERR-001' => [
            'intentional_empty_catch' => 'Allow empty catch blocks only with explicit "intentional" marker comment in PR-ERR-001 config.',
            'regex_only_noise' => 'Tighten PR-ERR-001 fallback by requiring AST confirmation where parsing is available.',
            'tests_path_concentration' => 'Add suppression for path glob "tests/*" for PR-ERR-001 when fixture-only catches are expected.',
        ],
        'PR-TIME-001' => [
            'shared_timeout_variable' => 'Tighten PR-TIME-001: treat shared options arrays with timeout keys as compliant.',
            'tests_path_concentration' => 'Add suppression for path glob "tests/*" for PR-TIME-001 when network stubs intentionally omit timeouts.',
            'regex_only_noise' => 'Require AST confirmation for timeout findings to reduce regex-only noise in PR-TIME-001.',
        ],
        'PR-LOCK-001' => [
            'owner_token_wrapper' => 'Add allowlist for verified owner-scoped renew wrapper methods in PR-LOCK-001 config.',
            'tests_path_concentration' => 'Add suppression for path glob "tests/*" for PR-LOCK-001 on non-production lock simulation code.',
            'regex_only_noise' => 'Tighten PR-LOCK-001 fallback by requiring AST confirmation when parser succeeds.',
        ],
        'PR-HANG-001' => [
            'worker_scope_only' => 'Restrict PR-HANG-001 to long-running worker contexts (infinite loop / worker entrypoints) in config.',
            'tests_path_concentration' => 'Add suppression for path glob "tests/*" for PR-HANG-001 on synthetic loop fixtures.',
            'regex_only_noise' => 'Require AST loop confirmation before PR-HANG-001 emits fallback findings.',
        ],
    ];

    public function suggest(string $ruleId, string $pattern, ?string $value = null): ?string
    {
        $template = self::MAP[$ruleId][$pattern] ?? null;
        if (!is_string($template) || $template === '') {
            return null;
        }

        if (str_contains($template, '%s')) {
            return sprintf($template, $value ?? 'unknown');
        }

        return $template;
    }
}
