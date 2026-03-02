<?php

declare(strict_types=1);

try {
    doWork();
} catch (Throwable $e) {
    logger($e->getMessage());
}
