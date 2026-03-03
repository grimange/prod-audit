<?php

try {
    run_task();
} catch (Throwable $e) {
    $logger->error('failed');
    return null;
}

@file_get_contents($url);
$promise->then($handler);
save_data($row);
