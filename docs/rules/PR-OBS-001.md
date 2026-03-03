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
