<?php

function lock_bad_pexpire($redis, string $key): void
{
    $redis->pexpire($key, 10000);
}
