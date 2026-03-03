<?php

declare(strict_types=1);

namespace ProdAudit\Audit\Tasks;

final class TaskMap
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public function mapping(): array
    {
        return [
            'PR-LOCK-001' => [
                'id' => 'TASK-LOCK-RENEW-001',
                'title' => 'Harden lock renew atomicity',
                'why' => 'Owner-scoped atomic renew prevents split-brain execution.',
                'effort' => 'M',
                'risk_reduction' => 'High',
                'steps' => [
                    'Replace expire-based renew with Lua owner-token check-and-renew.',
                    'Add owner token validation before extending TTL.',
                    'Add integration test that simulates concurrent renewal.',
                ],
            ],
            'PR-HANG-001' => [
                'id' => 'TASK-LOOP-GUARD-001',
                'title' => 'Add loop guardrails',
                'why' => 'Yield and timeout guards stop hot loops from starving workers.',
                'effort' => 'S',
                'risk_reduction' => 'High',
                'steps' => [
                    'Add sleep/yield in unbounded loops.',
                    'Add deadline or budget decrement checks.',
                    'Emit heartbeat metrics when loop runs long.',
                ],
            ],
            'PR-ERR-001' => [
                'id' => 'TASK-ERR-HANDLE-001',
                'title' => 'Stop swallowing exceptions',
                'why' => 'Escalated exceptions improve failure visibility and MTTR.',
                'effort' => 'S',
                'risk_reduction' => 'Med',
                'steps' => [
                    'Log caught exception with context.',
                    'Rethrow or convert to domain error with cause.',
                    'Add tests for failure-path observability.',
                ],
            ],
            'PR-TIME-001' => [
                'id' => 'TASK-TIMEOUT-001',
                'title' => 'Set explicit external timeouts',
                'why' => 'Timeouts bound downstream latency and protect worker pools.',
                'effort' => 'S',
                'risk_reduction' => 'High',
                'steps' => [
                    'Set request timeout and connect_timeout on HTTP calls.',
                    'Set CURLOPT_TIMEOUT and CURLOPT_CONNECTTIMEOUT for curl flows.',
                    'Centralize defaults in one client factory.',
                ],
            ],
            'PR-BOUND-002' => [
                'id' => 'TASK-BOUND-MEMORY-001',
                'title' => 'Bound in-memory buffers',
                'why' => 'Bounded queues avoid unbounded memory growth in daemons.',
                'effort' => 'M',
                'risk_reduction' => 'High',
                'steps' => [
                    'Add max size checks before appending in loops.',
                    'Compact old entries or stream to external storage.',
                    'Track memory watermark in runtime metrics.',
                ],
            ],
            'PR-OBS-001' => [
                'id' => 'TASK-LOG-CONTEXT-001',
                'title' => 'Add structured log context',
                'why' => 'Correlation IDs make incidents traceable across systems.',
                'effort' => 'S',
                'risk_reduction' => 'Low',
                'steps' => [
                    'Add context array to logger calls.',
                    'Include corr_id/request_id/job_id consistently.',
                    'Update logging helper to enforce context shape.',
                ],
            ],
        ];
    }
}
