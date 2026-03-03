<?php

function hang_bad_worker(): void
{
    while (true) {
        process_job();
    }
}
