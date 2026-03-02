<?php

try {
    work();
} catch (Throwable $e) {}

try {
    work();
} catch (Exception $e) { return; }
