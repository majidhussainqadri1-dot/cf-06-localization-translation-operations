# Ten-Round Review Cycle 8 — Defect and Correction Ledger

This ledger records the eighth explicit ten-round repository review cycle. Each round was completed as a full review before corrections from that round were applied. The next round began only after the prior round's identified defects were corrected.

| Round | Result | Closed correction scope |
|---|---|---|
| 1 | Defect found and corrected | A legacy lifecycle regression still asserted an obsolete schema/contract boot-denial message. It was aligned with the current exact-version runtime parity law. |
| 2 | Defect found and corrected | A second historical boot-parity regression asserted the same obsolete message and was reconciled with the current fail-closed runtime rule. |
| 3 | Clean | Activation, migration, schema/index verification and WordPress/MySQL integration structure were re-audited with no new defect found. |
| 4 | Clean | REST bounds, authorization, idempotency, WP-CLI authorization and repository concurrency/read-failure behavior were re-audited with no new defect found. |
| 5 | Clean | Project, assignment, translation, resource, content-link, terminology and feedback workflow invariants were re-audited with no new defect found. |
| 6 | Clean | Crypto, URL/SSRF, provider governance, machine-translation and privacy/erasure boundaries were re-audited with no new defect found. |
| 7 | Clean | Bundle build/release/rollback, source freshness, QA evidence, release approval, integration readiness and staging/live truth were re-audited with no new defect found. |
| 8 | Defects found and corrected | Future40 offline and low-bandwidth evidence accepted malformed nested/unbounded text shapes. Canonical key, scalar text, non-empty text and size bounds were added with adversarial regression coverage. |
| 9 | Clean | CI/package exact-source controls, version coherence, documentation truth and repository hygiene were re-audited with no new defect found. |
| 10 | Defects found and corrected | Final exact-head validation exposed historical schema-parity tests coupled to a removed hand-maintained schema map; stale/interpolating Cycle-7 test literals; incomplete explicit companion-boundary wording; a secret-scanner self-test that triggered the scanner itself; and a WordPress 6.0.15 bundled-theme defect that contaminated plugin integration. Historical tests were aligned to current contracts, scanner probes were made non-self-triggering, documentation was made explicit, and WP integration was isolated from theme loading while continuing to exercise the real plugin/MySQL path. |

## Final validation law

Cycle 8 is not considered Automated-QA Green or Packaged merely because the review corrections are committed. Those lifecycle states require a fresh exact-head workflow after the final correction commit, including all PHP/WordPress matrix jobs, WordPress/MySQL integration and deterministic package evidence.

Staging-Accepted, Live-Deployed and Operational remain separate evidence classes. No repository, CI or package evidence may substitute for exact staging/live deployed-code, database/schema, migration and runtime verification.
