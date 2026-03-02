<?php

declare(strict_types=1);

function renewLock($redis, string $key, string $owner): void
{
    $lua = 'if redis.call("get", KEYS[1]) == ARGV[1] then return redis.call("expire", KEYS[1], ARGV[2]) end return 0';
    $redis->eval($lua, [$key, $owner, 10], 1);
}
