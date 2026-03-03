# Insights and Feedback Loop

`prod-audit` Stage-7 adds a deterministic feedback loop driven by local repository data.

## Triage labels

Use `triage` to label findings in `docs/audit/triage.jsonl` (append-only):

- `true_positive`
- `false_positive`
- `noisy`
- `fixed`
- `wontfix`
- `needs_investigation`

Latest event for a fingerprint is the effective label.

## Insights model

Insights combine:

- active findings (`latest.json`)
- run history (`history.jsonl`)
- effective triage labels (`triage.jsonl`)
- optional churn inferred from local git log

Deterministic outputs:

- noise score by rule and finding fingerprint
- stability score by rule and finding fingerprint
- confidence calibration overlay for display only
- insight-ranked top risks
- hotspot files

Scoring math is unchanged.

## Forecast model

`forecast` and `scan` include deterministic risk heuristics:

- `risk_new_invariant_fail`
- `risk_score_drop_5`
- `risk_new_critical`
- `risk_rule_pack_regression`

Forecast also emits top drivers and next checks.

## Next best actions

Action planning is deterministic and template-driven from rule IDs, evidence refs, insights, and forecast signals.

## Optional CI gate

`scan --fail-on-forecast-risk=<0..1>` fails with exit code `8` when:

- `forecast.risk_new_invariant_fail >= threshold`, or
- `forecast.risk_score_drop_5 >= threshold`

Default behavior is unchanged when this option is not set.
