# PR-HANG-001 Infinite Loop Without Yield

## What
Detects unbounded loops with no yield, timeout, budget decrement, or approved heartbeat.

## Why
Unbounded loops can starve workers and create non-recoverable runtime hangs.

## Detection
- AST-first: `while (true)` / `for (;;)` loop body inspection.
- Guard signals: `sleep`/`usleep`, `yield`, time checks, budget decrement, allowlisted heartbeat call.
- Regex fallback when AST parse fails.

## How To Fix
- Add bounded termination logic.
- Add explicit yield/sleep points.
- Add timeout and heartbeat checks for long-running loops.

## Example
```php
$deadline = microtime(true) + 5.0;
while (true) {
    if (microtime(true) > $deadline) {
        break;
    }
    usleep(1000);
}
```
