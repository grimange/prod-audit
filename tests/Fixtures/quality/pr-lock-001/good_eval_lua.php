<?php

function lock_good($redis, string $lua, array $keys): void
{
    $redis->eval($lua, $keys, 1);
}
