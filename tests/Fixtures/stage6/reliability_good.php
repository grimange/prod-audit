<?php

$lock = acquire_lock('job', ['fencing_token' => $token, 'ttl' => 120]);
retry_with_backoff($job, ['max_attempts' => 5, 'jitter' => true]);
pcntl_signal(SIGTERM, static fn () => shutdown_worker());
