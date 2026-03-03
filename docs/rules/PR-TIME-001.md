# PR-TIME-001 External Call Without Timeout

## What it checks

Detects external HTTP calls where explicit timeout options are missing.

## Why it matters

Without timeouts, workers can block indefinitely on downstream failures and cause queue backlogs.

## Detection model

- AST-first detection for `curl_init` flows without `CURLOPT_TIMEOUT`/`CURLOPT_CONNECTTIMEOUT` via `curl_setopt`.
- AST heuristics for request calls without `timeout`/`connect_timeout` options.

## Confidence

- Medium: timeout omission confirmed in call scope.
- Low: heuristic request pattern without explicit timeout options.

## Typical remediation

- Set timeout values for all external calls.
- Centralize timeout defaults in shared client factories.

## False positive reduction changes (Stage-8)

- Shared options arrays with `timeout`/`connect_timeout` keys in scope now suppress findings.
- Added optional target allowlist via `rule_config.PR-TIME-001.allow_http_targets`.

### Stage-8 examples

```php
$options = ['timeout' => 2.0, 'connect_timeout' => 1.0];
$client->request('GET', $url, $options); // not flagged
```

```php
$client->request('GET', $url); // flagged
```
