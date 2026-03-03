<?php

function ok_error($logger, string $jobId): void
{
    $logger->error('failed', ['job_id' => $jobId]);
}
