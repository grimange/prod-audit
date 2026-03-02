<?php

declare(strict_types=1);

function renewLock($redis, string $key): void
{
    $redis->expire($key, 10);
}
