<?php

function ok_logger($logger, string $corrId): void
{
    $logger->info('processed', ['corr_id' => $corrId]);
}
