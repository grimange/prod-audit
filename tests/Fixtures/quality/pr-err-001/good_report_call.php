<?php

function err_good_report(): void
{
    try {
        risky();
    } catch (Throwable $e) {
        report($e);
    }
}
