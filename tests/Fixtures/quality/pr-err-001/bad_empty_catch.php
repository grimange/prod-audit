<?php

function err_bad_empty(): void
{
    try {
        risky();
    } catch (Throwable $e) {
    }
}
