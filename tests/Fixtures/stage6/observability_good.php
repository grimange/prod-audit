<?php

function runJob($logger, string $jobId, string $corrId): void
{
    $logger->info('startup', ['job_id' => $jobId, 'corr_id' => $corrId]);
    $logger->info('processing', ['job_id' => $jobId, 'corr_id' => $corrId]);
    $logger->info('shutdown', ['job_id' => $jobId, 'corr_id' => $corrId]);
}
