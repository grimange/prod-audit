<?php

$cfg = $config->string('DB_DSN', 'mysql://127.0.0.1:3306/app');
$port = $config->int('REDIS_PORT', 6379);
$host = $config->string('REDIS_HOST', 'redis.internal');
$password = $secrets->get('REDIS_PASSWORD');
