# Production Audit Framework

## Comprehensive Implementation Plan (Codex-Friendly)

Project: **prod-audit (PHP Production Audit Framework)**
Goal: Build a **production-grade audit system** capable of scoring codebases for production readiness (0–100 scale) with evidence-based findings, invariant gating, and progress tracking.

---

# 1. Project Goals

The framework must:

* Audit PHP projects or folders
* Produce deterministic reports
* Score production readiness
* Enforce invariants
* Detect regressions
* Detect stagnation (audit loops)
* Integrate with CI
* Support profiles
* Provide evidence-backed findings

The framework is **NOT a linter** and **NOT an auto-fixer**.

It is a **production-readiness auditing system.**

---

# 2. Core Requirements

## 2.1 Determinism

All runs must produce stable results:

* Stable ordering of findings
* Stable scoring
* Stable fingerprints
* Stable report structure

No random ordering allowed.

---

## 2.2 Evidence-Based Findings

Every finding MUST include evidence:

Examples:

* file:line reference
* code snippet
* command output

Findings without evidence must be marked:

Advisory Only

---

## 2.3 Score Model

Score range:

0–100

Bands:

95–100 Production Ready
85–94 Nearly Ready
0–84 Not Ready

Start Score:

100

Penalties subtract from score.

---

# 3. Architecture

```
prod-audit/
    bin/
        prod-audit

    src/

        Console/
            Application.php

            Commands/
                ScanCommand.php
                CompareCommand.php
                BaselineCommand.php
                ExplainRuleCommand.php
                InitCommand.php
                ValidateConfigCommand.php

        Audit/

            AuditRunner.php
            RuleScheduler.php
            FindingAggregator.php

            Profiles/
                ProfileInterface.php
                ProfileRegistry.php

                Dialer24x7Profile.php
                LaravelWorkerProfile.php
                GeneralServiceProfile.php
                LibraryProfile.php

            Rules/

                RuleInterface.php
                InvariantRuleInterface.php

                RuleMetadata.php
                RuleResult.php
                Finding.php
                Evidence.php
                Severity.php
                Confidence.php

            Collectors/

                FileCollector.php
                PatternCollector.php
                ComposerCollector.php
                PhpConfigCollector.php
                PHPUnitCollector.php
                GitCollector.php

            Scoring/

                ScoreEngine.php
                ScoreBreakdown.php
                BandClassifier.php

            Reporting/

                MarkdownReportWriter.php
                JsonReportWriter.php
                HistoryWriter.php
                TrendAnalyzer.php

        Utils/

            Fingerprint.php
            StableSort.php
            PathNormalizer.php

    config/
        profiles.php

    tests/

    docs/

```

---

# 4. CLI Design

Binary:

```
vendor/bin/prod-audit
```

---

## 4.1 Scan Command

```
prod-audit scan <path>
```

Options:

```
--profile=dialer-24x7
--out=docs/audit
--target-score=95
--format=md
--max-file-size=2MB
```

Outputs:

```
docs/audit/latest.md
docs/audit/latest.json
docs/audit/history.jsonl
docs/audit/reports/<timestamp>.md
```

Exit Codes:

```
0 PASS
2 Invariant Fail
3 Score Below Target
4 Tool Error
```

---

## 4.2 Compare Command

```
prod-audit compare report1.json report2.json
```

Shows:

* Score difference
* New findings
* Removed findings
* Repeated findings

---

## 4.3 Baseline Command

```
prod-audit baseline <path>
```

Creates:

```
prod-audit-baseline.json
```

Baseline stores:

* Accepted findings
* Justification
* Owner
* Expiration date

---

## 4.4 Init Command

```
prod-audit init
```

Creates:

```
prod-audit.php
docs/audit/
```

---

## 4.5 Explain Rule

```
prod-audit explain-rule PR-LOCK-001
```

Displays:

* What rule checks
* Why it matters
* Examples
* Fix guidance

---

# 5. Data Models

## 5.1 Finding

```
Finding
--------
id
title
category
severity
confidence
message
impact
recommendation
effort
tags[]
evidence[]
fingerprint
```

---

## 5.2 Evidence

```
Evidence
---------
type

file_snippet
grep_match
command_output

file
line_start
line_end

excerpt
hash
```

---

# 6. Profiles

Profiles define:

* Rules
* Invariants
* Weights
* Caps
* Thresholds

---

## 6.1 Built-in Profiles

### Dialer24x7Profile

Target Score:

95

Invariants:

PR-LOCK-001
PR-HANG-001
PR-BOUND-001

---

### LaravelWorkerProfile

Target Score:

90

Focus:

* queue workers
* retries
* timeouts

---

### GeneralServiceProfile

Target Score:

90

General services.

---

### LibraryProfile

Target Score:

95

Constraints:

* Framework agnostic
* Minimal dependencies

---

# 7. Rule Packs

---

## 7.1 Reliability Pack

### PR-LOCK-001

Atomic owner-scoped lock renew required.

Invariant.

---

### PR-HANG-001

Infinite loops must include timeout or watchdog.

Invariant.

Detect:

```
while(true)
for(;;)
```

Without:

```
sleep
timeout
heartbeat
yield
```

---

### PR-BOUND-001

Unbounded resource growth.

Invariant.

Examples:

* arrays that only grow
* redis keys without ttl

---

### PR-ERR-001

Swallowed exceptions.

Detect:

```
catch(Throwable $e){}
```

---

### PR-ERR-002

Errors logged but not escalated.

---

### PR-TIME-001

External calls without timeout.

---

### PR-SHUTDOWN-001

Unsafe shutdown handling.

---

## 7.2 Observability Pack

### PR-LOG-001

Missing structured logging.

---

### PR-METRIC-001

Critical loops lack metrics hooks.

---

## 7.3 Maintainability Pack

### PR-CONF-001

Config scattered.

---

### PR-DEP-001

Dependency risk.

---

### PR-DOC-001

Missing operational docs.

---

## 7.4 Security Baseline Pack

Lightweight checks only.

---

# 8. Collectors

---

## 8.1 FileCollector

Responsibilities:

* Enumerate files
* Respect ignores
* Provide content handles

---

## 8.2 PatternCollector

Responsibilities:

* Run regex scans
* Cache results

---

## 8.3 ComposerCollector

Extract:

* packages
* versions
* abandoned packages

---

## 8.4 PHPUnitCollector

Optional.

Runs:

```
vendor/bin/phpunit --debug --stderr
```

Captures:

* failures
* hangs

---

## 8.5 GitCollector

Collect:

* commit count
* last commit
* churn hotspots

---

# 9. Scoring Engine

---

## 9.1 Penalties

Critical:

-12

Major:

-6

Minor:

-2

Confidence Adjustment:

Low confidence = 50% penalty

---

## 9.2 Caps

Invariant Fail:

Score capped at:

94

Multiple invariant fails:

Score capped at:

80

---

# 10. Reporting

---

## 10.1 Markdown Report

Sections:

1 Executive Summary

2 Score

3 Invariants

4 Top Risks

5 Findings

6 Recommendations

7 Trend

8 Appendix

---

## 10.2 JSON Report

Machine readable.

---

## 10.3 History

Append-only:

```
history.jsonl
```

---

# 11. Trend Analysis

---

## 11.1 Fingerprints

Fingerprint:

```
ruleID + evidenceHash
```

---

## 11.2 Regression Detection

Detect:

* new critical findings
* score drops

---

## 11.3 Stagnation Detection

If same fingerprints appear across 3 runs:

Flag:

```
STAGNATION DETECTED
```

---

# 12. Configuration

File:

```
prod-audit.php
```

Supports:

```
profile
ignore_paths
rule_overrides
output_folder
target_score
```

---

# 13. CI Integration

Example:

```
vendor/bin/prod-audit scan backend --profile=dialer-24x7
```

CI should fail if:

* invariant fails
* score < target

---

# 14. Testing Strategy

## 14.1 Unit Tests

Test:

* scoring engine
* fingerprint logic
* trend analyzer

---

## 14.2 Rule Tests

Fixture-based tests.

```
tests/Fixtures/
```

Each rule:

Good example.

Bad example.

---

## 14.3 Snapshot Tests

Reports must be deterministic.

Snapshot comparisons required.

---

# 15. Milestones

---

## Phase 1 — Core Engine

* CLI
* Scan command
* FileCollector
* PatternCollector
* Rule engine
* Markdown report
* Score engine

---

## Phase 2 — Production Features

* Profiles
* Invariants
* JSON reports
* History
* Trend detection

---

## Phase 3 — CI Features

* Exit codes
* Baselines
* Compare command

---

## Phase 4 — Advanced Collectors

* PHPUnitCollector
* GitCollector

---

## Phase 5 — Full Rule Set

Target:

50+ rules

---

## Phase 6 — Production Release

Version:

1.0

Requirements:

* Deterministic output
* Stable fingerprints
* Documentation complete
* Self-audit passes

---

# 16. Self-Audit Requirement

The framework MUST audit itself.

Command:

```
prod-audit scan .
```

Target:

95+

```
```
