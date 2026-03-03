<?php

function hang_good_timeout(): void
{
    $deadline = microtime(true) + 1.0;
    while (true) {
        if (microtime(true) > $deadline) {
            break;
        }
    }
}
