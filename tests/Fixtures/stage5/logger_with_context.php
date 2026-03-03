<?php

function logWithContext($logger): void
{
    $logger->info('job started', ['job_id' => '42', 'corr_id' => 'abc']);
}
