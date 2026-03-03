<?php

function err_bad_return(): void
{
    try {
        risky();
    } catch (Exception $e) {
        return;
    }
}
