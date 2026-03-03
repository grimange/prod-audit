<?php

function lock_bad($redis, string $key): void
{
    $redis->expire($key, 10);
}
