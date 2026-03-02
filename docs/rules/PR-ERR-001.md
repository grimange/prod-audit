# PR-ERR-001 Swallowed Exceptions

## What
Detects `catch` blocks that do not escalate or record failures.

## Why
Silent exception handling hides production incidents and blocks triage.

## Detection
- AST-first: empty catch body or only `return`/`break`/`continue`.
- Exemptions: rethrow or observability calls (`log`, `logger`, `report`, `emit`, `notify`).
- Regex fallback when AST parse fails.

## How To Fix
- Re-throw after contextual handling, or
- Log/report with useful context and fail safely.

## Example
```php
try {
    $service->run();
} catch (\Throwable $e) {
    logger($e->getMessage());
    throw $e;
}
```
