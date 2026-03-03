<?php

function callRemoteWithTimeout(string $url): string
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_TIMEOUT, 2);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);

    return (string) curl_exec($ch);
}
