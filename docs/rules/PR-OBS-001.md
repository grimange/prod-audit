# PR-OBS-001 Missing Logger Correlation Context

## What it checks

Detects logger calls with only a message argument and no context payload.

## Why it matters

Correlation context is required to trace incidents across workers and services.

## Detection model

- AST detection of `info/error/...` logger calls with one argument.

## Confidence

- Medium: logger signature confirms message-only call.

## Typical remediation

- Add structured context arrays.
- Include `corr_id`, `request_id`, or `job_id` consistently.

## False positive reduction changes (Stage-8)

- Added `rule_config.PR-OBS-001.allow_log_methods` allowlist for approved low-signal logger methods.
- Kept AST-only detection; regex-only paths are not used for this rule.

### Stage-8 examples

```php
$logger->info('heartbeat'); // allow via config allow_log_methods=["info"]
```

```php
$logger->error('failed', ['corr_id' => $corrId]); // not flagged (has context)
```
