<?php

function callRemoteWithoutTimeout(string $url): string
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    return (string) curl_exec($ch);
}
