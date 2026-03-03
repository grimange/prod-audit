<?php

function collectWithBound(): void
{
    $buffer = [];

    while (true) {
        $buffer[] = microtime(true);
        if (count($buffer) > 1000) {
            $buffer = array_slice($buffer, -500);
        }
    }
}
