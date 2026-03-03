<?php

function time_good_literal($client, string $url): void
{
    $client->request('GET', $url, ['timeout' => 2.0, 'connect_timeout' => 1.0]);
}
