<?php

function runJob($logger): void
{
    $logger->info('processing');
}

try {
    runJob($logger);
} catch (Throwable $e) {
    return;
}
