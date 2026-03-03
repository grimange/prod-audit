<?php

try {
    run_task();
} catch (Throwable $e) {
    $logger->error('failed', ['corr_id' => $corrId]);
    throw $e;
}

$result = save_data($row);
if (!$result) {
    throw new RuntimeException('save failed');
}
$promise->wait();
