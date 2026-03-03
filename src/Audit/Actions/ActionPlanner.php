<?php

declare(strict_types=1);

namespace ProdAudit\Audit\Actions;

final class ActionPlanner
{
    private const MAX_ACTIONS = 10;

    /**
     * @var array<string, array{id: string, title: string, check: string}>
     */
    private const RULE_ACTIONS = [
        'PR-LOCK-001' => ['id' => 'ACT-LOCK-001', 'title' => 'Add/verify lock renew Lua owner check', 'check' => 'verify lock renew uses owner-scoped Lua check-and-renew'],
        'PR-LOCK-002' => ['id' => 'ACT-LOCK-002', 'title' => 'Add fencing token verification', 'check' => 'verify lock acquisition and write paths enforce fencing token'],
        'PR-HANG-001' => ['id' => 'ACT-HANG-001', 'title' => 'Add watchdog yield in long loops', 'check' => 'verify loop budget, yield, and timeout guards in worker loops'],
        'PR-TIME-001' => ['id' => 'ACT-TIME-001', 'title' => 'Add timeout to outbound calls', 'check' => 'verify explicit timeout and connect timeout on HTTP/curl calls'],
        'PR-TIME-002' => ['id' => 'ACT-TIME-002', 'title' => 'Add timeout to database calls', 'check' => 'verify DB calls use bounded timeout and retry budget'],
        'PR-TIME-003' => ['id' => 'ACT-TIME-003', 'title' => 'Add timeout to Redis calls', 'check' => 'verify Redis client operations are bounded with timeout'],
        'PR-BOUND-002' => ['id' => 'ACT-BOUND-002', 'title' => 'Add bounded cache compaction', 'check' => 'verify in-memory arrays/maps enforce max size and compaction'],
        'PR-BOUND-003' => ['id' => 'ACT-BOUND-003', 'title' => 'Bound Redis key growth', 'check' => 'verify Redis keyspaces are bounded and TTL protected'],
        'PR-ERR-001' => ['id' => 'ACT-ERR-001', 'title' => 'Remove silent exception swallowing', 'check' => 'verify caught exceptions are logged and rethrown/translated'],
    ];

    /**
     * @param array<int, array<string, mixed>> $findings
     * @param array<string, mixed> $insights
     * @param array<string, mixed> $forecast
     * @return array<int, Action>
     */
    public function plan(array $findings, array $insights, array $forecast): array
    {
        $findingByFingerprint = [];
        foreach ($findings as $finding) {
            if (!is_array($finding)) {
                continue;
            }

            $fingerprint = (string) ($finding['fingerprint'] ?? '');
            if ($fingerprint !== '') {
                $findingByFingerprint[$fingerprint] = $finding;
            }
        }

        $prioritized = is_array($insights['prioritized_findings'] ?? null) ? $insights['prioritized_findings'] : [];
        if ($prioritized === []) {
            $prioritized = array_map(static function (array $finding): array {
                return [
                    'fingerprint' => (string) ($finding['fingerprint'] ?? ''),
                    'rule_id' => (string) ($finding['rule_id'] ?? ''),
                    'rank' => 0.0,
                    'persistence' => 0.0,
                    'noise' => 0.0,
                ];
            }, $findings);
        }

        $grouped = [];
        foreach ($prioritized as $item) {
            if (!is_array($item)) {
                continue;
            }

            $ruleId = (string) ($item['rule_id'] ?? '');
            $fingerprint = (string) ($item['fingerprint'] ?? '');
            if ($ruleId === '' || $fingerprint === '') {
                continue;
            }

            $template = $this->templateForRule($ruleId);
            $actionId = $template['id'];

            if (!isset($grouped[$actionId])) {
                $grouped[$actionId] = [
                    'template' => $template,
                    'rule_ids' => [],
                    'evidence_refs' => [],
                    'priority' => 0.0,
                    'persistence_max' => 0.0,
                    'noise_avg_sum' => 0.0,
                    'noise_avg_count' => 0,
                ];
            }

            $grouped[$actionId]['rule_ids'][$ruleId] = true;
            $grouped[$actionId]['priority'] = max($grouped[$actionId]['priority'], (float) ($item['rank'] ?? 0.0));
            $grouped[$actionId]['persistence_max'] = max($grouped[$actionId]['persistence_max'], (float) ($item['persistence'] ?? 0.0));
            $grouped[$actionId]['noise_avg_sum'] += (float) ($item['noise'] ?? 0.0);
            $grouped[$actionId]['noise_avg_count']++;

            $finding = $findingByFingerprint[$fingerprint] ?? null;
            if (is_array($finding)) {
                foreach ($this->evidenceRefs($finding) as $ref) {
                    $grouped[$actionId]['evidence_refs'][$ref] = true;
                }
            }
        }

        ksort($grouped, SORT_STRING);

        $riskInvariant = (float) ($forecast['risk_new_invariant_fail'] ?? 0.0);
        $riskDrop = (float) ($forecast['risk_score_drop_5'] ?? 0.0);

        $actions = [];
        foreach ($grouped as $group) {
            $ruleIds = array_keys($group['rule_ids']);
            sort($ruleIds, SORT_STRING);

            $evidenceRefs = array_keys($group['evidence_refs']);
            sort($evidenceRefs, SORT_STRING);

            $noiseAvg = $group['noise_avg_count'] > 0
                ? $group['noise_avg_sum'] / $group['noise_avg_count']
                : 0.0;

            $whyNow = sprintf(
                '%s; persistence=%.2f noise=%.2f forecast(invariant=%.2f,drop5=%.2f)',
                (string) $group['template']['check'],
                (float) $group['persistence_max'],
                (float) $noiseAvg,
                $riskInvariant,
                $riskDrop
            );

            $actions[] = new Action(
                id: (string) $group['template']['id'],
                title: (string) $group['template']['title'],
                whyNow: $whyNow,
                ruleIds: $ruleIds,
                evidenceRefs: array_slice($evidenceRefs, 0, 10),
                priority: (float) $group['priority'],
            );
        }

        usort($actions, static function (Action $a, Action $b): int {
            $priorityCmp = $b->priority <=> $a->priority;
            if ($priorityCmp !== 0) {
                return $priorityCmp;
            }

            return strcmp($a->id, $b->id);
        });

        return array_slice($actions, 0, self::MAX_ACTIONS);
    }

    /**
     * @return array{id: string, title: string, check: string}
     */
    private function templateForRule(string $ruleId): array
    {
        if (isset(self::RULE_ACTIONS[$ruleId])) {
            return self::RULE_ACTIONS[$ruleId];
        }

        $pack = $this->packFromRuleId($ruleId);

        return match ($pack) {
            'LOCK' => ['id' => 'ACT-LOCK-GENERIC', 'title' => 'Verify lock safety checks', 'check' => 'verify lock ownership, fencing, and TTL safety checks'],
            'HANG' => ['id' => 'ACT-HANG-GENERIC', 'title' => 'Verify loop liveness controls', 'check' => 'verify loops include yield, deadlines, and interruption checks'],
            'TIME' => ['id' => 'ACT-TIME-GENERIC', 'title' => 'Verify timeout coverage', 'check' => 'verify all external I/O paths enforce timeout budgets'],
            'BOUND' => ['id' => 'ACT-BOUND-GENERIC', 'title' => 'Verify bounded growth controls', 'check' => 'verify data structures and queues enforce bounded growth'],
            default => ['id' => 'ACT-GENERAL-GENERIC', 'title' => 'Verify production-readiness guardrails', 'check' => 'verify identified guardrails with targeted regression tests'],
        };
    }

    private function packFromRuleId(string $ruleId): string
    {
        $parts = explode('-', $ruleId);
        if (count($parts) < 2) {
            return 'GENERAL';
        }

        return strtoupper((string) $parts[1]);
    }

    /**
     * @param array<string, mixed> $finding
     * @return array<int, string>
     */
    private function evidenceRefs(array $finding): array
    {
        $refs = [];
        $fingerprint = (string) ($finding['fingerprint'] ?? '');
        if ($fingerprint !== '') {
            $refs[$fingerprint] = true;
        }

        $evidence = $finding['evidence'] ?? [];
        if (is_array($evidence)) {
            foreach ($evidence as $entry) {
                if (!is_array($entry)) {
                    continue;
                }

                $file = (string) ($entry['file'] ?? '');
                $lineStart = (int) ($entry['line_start'] ?? 0);
                if ($file !== '' && $lineStart > 0) {
                    $refs[$file . ':' . $lineStart] = true;
                } elseif ($file !== '') {
                    $refs[$file] = true;
                }
            }
        }

        $result = array_keys($refs);
        sort($result, SORT_STRING);

        return $result;
    }
}
