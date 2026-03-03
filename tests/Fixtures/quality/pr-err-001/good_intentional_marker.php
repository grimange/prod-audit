<?php

function err_good_intentional(): void
{
    try {
        risky();
    } catch (Throwable $e) {
        // intentional: temporary compatibility fallback
    }
}
