<?php

$cache->set($key, $value, ['ttl' => 60]);
$queue->publish($payload, ['rate_limit' => 50]);
$records = array_slice($records, -1000);
