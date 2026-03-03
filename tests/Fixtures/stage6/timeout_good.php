<?php

$db->query($sql, ['timeout' => 2]);
$redis->get($key, ['timeout' => 1]);
stream_set_timeout($socket, 2);
$deadline = time() + 3;
while (true) {
    sleep(1);
    if (time() > $deadline) {
        break;
    }
}
