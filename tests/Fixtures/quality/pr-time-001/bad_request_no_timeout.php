<?php

function time_bad_request($client, string $url): void
{
    $client->request('GET', $url);
}
