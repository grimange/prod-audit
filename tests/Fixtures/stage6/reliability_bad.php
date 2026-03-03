<?php

static $globalState = [];
$lock = acquire_lock('job');
retry($job);
while (true) {
    process_job();
}
