# Forecast

- risk_new_invariant_fail: 0.526121
- risk_score_drop_5: 0.393682
- risk_new_critical: 0.398106

## Top Drivers
- {"type":"file","file":"stage6/bounds_bad.php","churn_score":0,"reason":"hotspot_churn"}
- {"type":"file","file":"stage6/observability_bad.php","churn_score":0,"reason":"hotspot_churn"}
- {"type":"file","file":"stage6/reliability_bad.php","churn_score":0,"reason":"hotspot_churn"}
- {"type":"finding","fingerprint":"07683ece05c987a46801be59f62eb45afed2a6212aacb19d3a08f4b5401e67ff","rule_id":"PR-HANG-001","reason":"persistent_high_rank","persistence":0.416667,"noise":0,"rank":1.833334}
- {"type":"finding","fingerprint":"0b7e80db588653d2c318bf57c7d16da88ac4464405ff0b748703a09bd8540157","rule_id":"PR-HANG-001","reason":"persistent_high_rank","persistence":0.416667,"noise":0,"rank":1.833334}
- {"type":"finding","fingerprint":"4f7120b499f376eb29f0e138161c29db5cd4e122ff3f3790fb1460f63083530f","rule_id":"PR-HANG-001","reason":"persistent_high_rank","persistence":0.416667,"noise":0,"rank":1.833334}
- {"type":"finding","fingerprint":"5bd03d15156f700596ae0e74840f636a21c1d9e518ed28ee54b55f3b23edc7eb","rule_id":"PR-LOCK-001","reason":"persistent_high_rank","persistence":0.416667,"noise":0,"rank":1.833334}
- {"type":"finding","fingerprint":"abc276a35c851e42376566166d91789f540e3ff39c87be09e41ffbe061536c38","rule_id":"PR-HANG-001","reason":"persistent_high_rank","persistence":0.416667,"noise":0,"rank":1.833334}

## Next Checks
- ACT-HANG-001: Add watchdog yield in long loops
- ACT-LOCK-001: Add/verify lock renew Lua owner check
- ACT-BOUND-002: Add bounded cache compaction
- ACT-ERR-001: Remove silent exception swallowing
- ACT-TIME-001: Add timeout to outbound calls
- ACT-BOUND-003: Bound Redis key growth
- ACT-BOUND-GENERIC: Verify bounded growth controls
- ACT-GENERAL-GENERIC: Verify production-readiness guardrails
- ACT-LOCK-002: Add fencing token verification
- ACT-TIME-002: Add timeout to database calls
