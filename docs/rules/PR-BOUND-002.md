# PR-BOUND-002 Unbounded In-Memory Array Growth

## What it checks

Detects infinite-loop contexts appending to arrays without reset or bounds.

## Why it matters

Unbounded arrays can exhaust memory in long-running workers and trigger restarts or crashes.

## Detection model

- AST detection of array growth operations in infinite loops.
- Requires absence of bound/reset signals in the same loop body.

## Confidence

- Medium: AST-confirmed growth in loop with no bound/reset signal.

## Typical remediation

- Add explicit max-size checks.
- Evict/compact old entries.
- Move high-volume state to an external store.
