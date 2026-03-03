<?php

function collectForever(): void
{
    $buffer = [];

    while (true) {
        $buffer[] = microtime(true);
    }
}
