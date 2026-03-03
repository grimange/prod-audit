<?php

function time_good_shared($client, string $url): void
{
    $options = ['timeout' => 1.5, 'connect_timeout' => 0.5];
    $client->request('GET', $url, $options);
}
