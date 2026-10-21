# Ten-Round Review Cycle 10 — Defect and Correction Ledger

This ledger records the tenth explicit ten-round repository review cycle for CF-06. Each numbered round was completed as an uninterrupted audit before corrections from that round were applied. The next round began only after the prior round's identified defects were corrected.

| Round | Result | Closed correction scope |
|---|---|---|
| 1 | Defects found and corrected | A historical critical-feedback regression test still expected obsolete defect states and blocked the exact-source quality gate. The assertion was reconciled to the current governed defect lifecycle. |
| 2 | Defects found and corrected | Runtime boot could rely on version markers after an upgrade-lock early return. Boot now unconditionally rechecks exact operational schema parity before service registration. |
| 3 | Defects found and corrected | Audit chain hashes did not bind actor, purpose or event UUID. Those governance identity fields are now included in the event-chain hash. |
| 4 | Clean | Provider URL/DNS, crypto, privacy, MT runtime/purge and credential-reference controls produced no additional proven source defect in this round. |
| 5 | Defects found and corrected | Project/assignment due and expiry evidence accepted permissive date parsing. Security-relevant assignment dates now require exact canonical UTC timestamps. |
| 6 | Defects found and corrected | Direct public bundle lookup could return an active bundle even after its locale became disabled/deprecated. Public delivery now requires an enabled/degraded locale state first. |
| 7 | Defects found and corrected | Future40 calendar preview accepted syntactically valid but impossible Gregorian dates. FUT-020 now requires a real canonical calendar date. |
| 8 | Clean | CI pinning, PHP/WordPress matrix, deterministic exact-head packaging, manifest/SBOM/checksum and dependency metadata produced no additional proven defect in this round. |
| 9 | Defects found and corrected | FR-028 required publication correction/retraction search notification, but the direct content-link workflow lacked an explicit versioned downstream publication-reconciliation fact. The outbox contract and traceability were added. |
| 10 | Defects found and corrected | Final audit found: the quality gate did not execute cycle-10 regression files; a historical schema-index test still expected a removed hand-maintained map; several governed state transitions allowed empty reason evidence; provider deprecation did not independently prove purge/credential-revocation/exit evidence; automatic source staleness did not emit an explicit search-reconciliation fact; and migration documentation did not clearly separate repository dry-run primitives from real source-specific staging backfill evidence. All were corrected or truthfully bounded. |

## Final-round correction details

- tools/quality-check.sh now executes tests/review-cycle10-round-*.php.
- Historical schema-index regression follows canonical DDL-derived unique-index verification.
- Project, translation-review, terminology, style-guide, provider, bundle and feedback transitions require nonempty bounded reason/outcome evidence.
- Provider deprecation fails closed on slto_verify_provider_deprecation_evidence, covering export/purge, credential revocation, exit reconciliation and rollback-window proof.
- Direct publication changes emit ContentTranslationPublicationChanged; automatic source correction/retirement with stale publication links emits ContentTranslationReconciliationRequired.
- Requirement/contracts documentation records the new transition and provider-exit governance.
- Migration documentation now explicitly states that real source-specific legacy backfill, dual-read/shadow comparison, reversible cutover and rollback remain staging evidence until the actual legacy owner contracts/data are available; repository dry-run/checkpoint primitives do not fabricate that evidence.

## Evidence discipline

This ledger proves repository review/correction history only. It does not itself prove current exact-head CI, package, staging, live or operational status. After this ledger is committed, the exact resulting head must pass the complete PHP/WordPress/MySQL quality matrix and deterministic package job before Automated-QA Green or Packaged can be asserted for that head. Staging and live states remain separate evidence classes.
