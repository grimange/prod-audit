<?php

function time_bad_curl(string $url): void
{
    $ch = curl_init($url);
    curl_exec($ch);
}
