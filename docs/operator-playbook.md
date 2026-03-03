# Stage-8 Operator Playbook

## prod-audit Precision Improvement Workflow

This playbook describes the **standard operational loop** for maintaining and improving audit precision in **prod-audit**.

The goal is to continuously:

* Reduce false positives
* Improve rule accuracy
* Maintain deterministic audits
* Keep CI stable
* Improve production-readiness scoring quality

This workflow is designed to be run **weekly or bi-weekly**.

---

# 1. Overview

The Stage-8 workflow is a **closed feedback loop**:

```
Scan → Triage → Quality → Suggestions → Refinement → CI
```

Each step improves the next scan.

---

# 2. Step 1 — Run a Production Audit

Run the audit:

```
vendor/bin/prod-audit scan backend --profile=dialer-24x7 --out=docs/audit
```

This generates:

```
docs/audit/latest.json
docs/audit/latest.md
docs/audit/history.jsonl
```

Review:

* Score
* Invariants
* Top Risks
* Recommended Tasks
* Insights
* Forecast

Focus first on:

* Invariant failures
* Critical findings
* Persistent findings

---

# 3. Step 2 — Triage Findings

List findings:

```
vendor/bin/prod-audit triage-list
```

Label important findings.

Examples:

### True positive

```
vendor/bin/prod-audit triage <fingerprint> --label=true_positive
```

### False positive

```
vendor/bin/prod-audit triage <fingerprint> --label=false_positive
```

### Noisy rule

```
vendor/bin/prod-audit triage <fingerprint> --label=noisy
```

### Fixed issue

```
vendor/bin/prod-audit triage <fingerprint> --label=fixed
```

Labels are stored in:

```
docs/audit/triage.jsonl
```

Rules:

* Every persistent finding should be labeled
* Prefer `true_positive` vs `false_positive`
* Use `noisy` when rule is partially correct

---

# 4. Step 3 — Generate Quality Report

Run:

```
vendor/bin/prod-audit quality --out=docs/audit
```

This generates:

```
docs/audit/quality.json
docs/audit/quality.md
```

Review:

### Top Noisy Rules

These rules need refinement.

Example:

```
PR-OBS-001 noise_score = 0.62
```

Meaning:

* Too many false positives
* Needs refinement

---

### Most Valuable Rules

Example:

```
PR-HANG-001 precision_score = 0.95
```

Meaning:

* Accurate
* Important
* Keep strict

---

# 5. Step 4 — Inspect Noisy Findings

Pick a noisy fingerprint.

Run:

```
vendor/bin/prod-audit reproduce <fingerprint>
```

This generates:

```
docs/audit/reproduce/<fingerprint>.md
```

Review:

* Code snippet
* Evidence
* Rule behavior
* Triage labels

Determine:

* Is rule too broad?
* Missing allowlist?
* Regex fallback inaccurate?
* AST refinement needed?

---

# 6. Step 5 — Generate Suggestions

Run:

```
vendor/bin/prod-audit triage-suggest
```

Output:

```
docs/audit/suggestions.md
```

Example suggestions:

* Add allowlist for logger wrapper
* Require AST confirmation
* Restrict rule to long-running loops
* Add suppression for tests/*

Suggestions are deterministic and mapping-driven.

---

# 7. Step 6 — Refine Rules

Improve selected rules.

Allowed changes:

* AST-first detection
* Allowlist additions
* Context narrowing
* Heuristic improvements

Not allowed:

* Changing scoring math
* Removing evidence
* Non-deterministic logic

After changes run:

```
vendor/bin/phpunit
```

Then:

```
vendor/bin/prod-audit scan tests/Fixtures
```

Ensure:

* Good fixtures clean
* Bad fixtures detected

---

# 8. Step 7 — Verify Noise Reduction

Run:

```
vendor/bin/prod-audit quality
```

Confirm:

* Noise score decreased
* Precision improved

Example:

```
PR-OBS-001
Before: 0.62
After: 0.28
```

---

# 9. Step 8 — CI Validation

Run strict policy:

```
vendor/bin/prod-audit scan backend \
  --profile=dialer-24x7 \
  --policy=strict
```

Optional noise gate:

```
vendor/bin/prod-audit scan backend \
  --profile=dialer-24x7 \
  --max-noise-score=0.40
```

Ensure:

* CI passes
* No regressions
* No invariant failures

---

# 10. Recommended Weekly Routine

### Weekly

```
scan
triage
quality
```

### Monthly

```
triage-suggest
rule refinement
fixture updates
```

---

# 11. When to Refine Rules

Refine a rule if:

* noise_score > 0.40
* false_positive_rate > 0.30
* developers ignore findings
* suppressions increase rapidly

---

# 12. When NOT to Refine Rules

Do NOT refine if:

* Rule catches real issues
* Noise is from test fixtures only
* Suppression is sufficient
* Rule is invariant-critical

Example invariant rules:

```
PR-HANG-001
PR-LOCK-001
```

These must remain strict.

---

# 13. Healthy System Indicators

A healthy prod-audit system has:

* Noise score < 0.30
* Stable fingerprints
* Few suppressions
* Predictable forecasts
* CI stability

---

# 14. Warning Signs

Investigate if:

* Noise score rising
* Forecast risk rising
* Many suppressions
* Same findings repeat for months
* Score fluctuates heavily

---

# 15. Long-Term Improvement Cycle

Over time the system should evolve:

```
More AST rules
Lower noise
Better forecasts
Higher precision
Stable CI
```

Goal:

```
Precision > 0.80
Noise < 0.25
```

---

# 16. Philosophy

prod-audit improves through:

* Evidence
* Determinism
* Feedback
* Refinement

Not through:

* Guessing
* Heuristics without validation
* Changing scores arbitrarily

---

# 17. Quick Command Reference

Run audit:

```
prod-audit scan backend
```

Label finding:

```
prod-audit triage <fingerprint> --label=true_positive
```

Quality report:

```
prod-audit quality
```

Suggestions:

```
prod-audit triage-suggest
```

Reproduce finding:

```
prod-audit reproduce <fingerprint>
```

Forecast:

```
prod-audit forecast
```

---

# 18. Expected Outcome

Following this workflow ensures:

* Continuous precision improvement
* Stable CI audits
* Reduced false positives
* Reliable production readiness scoring

This is the intended operational model for **prod-audit Stage-8 and beyond.**
