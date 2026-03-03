<?php

function hang_bad_worker_busy(): void
{
    for (;;) {
        process_more();
    }
}
