<?php

declare(strict_types=1);

return [
    'ignored_directories' => [],
    'rule_config' => [
        'PR-OBS-001' => [
            'allow_log_methods' => [],
        ],
        'PR-ERR-001' => [
            'allow_intentional_marker' => true,
        ],
        'PR-TIME-001' => [
            'allow_http_targets' => [],
        ],
        'PR-LOCK-001' => [
            'allow_renew_snippet_contains' => [],
        ],
        'PR-HANG-001' => [
            'worker_entrypoint_patterns' => ['worker', 'consumer', 'daemon', 'queue', 'job'],
            'allow_file_contains' => [],
        ],
    ],
];
