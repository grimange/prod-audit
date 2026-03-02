# PR-LOCK-001 Lock Renew Atomicity Heuristic

## What
Detects Redis expire-like lock renew calls without eval/evalsha Lua atomic scope.

## Why
Non-atomic renew logic can cause lock split-brain and ownership violations.

## Detection
- AST-first: detect `expire`/`pexpire`/`setex` calls in scope.
- If scope contains `eval`/`evalsha` with Lua argument, finding is suppressed.
- Regex fallback when AST parse fails.

## How To Fix
- Use owner-scoped Lua renew scripts (`eval`/`evalsha`).
- Keep renew and owner checks atomic in the same operation.

## Example
```php
$lua = 'if redis.call("get", KEYS[1]) == ARGV[1] then return redis.call("pexpire", KEYS[1], ARGV[2]) end return 0';
$redis->eval($lua, [$lockKey, $ownerToken, 30000], 1);
```
