<?php

function lock_good_evalsha($redis, string $sha, array $keys): void
{
    $redis->evalsha($sha, $keys, 1);
}
