# Ten-Round Review Cycle 9 — Defect and Correction Ledger

This ledger records the ninth explicit ten-round repository review cycle. In every round, the audit was completed in full before any correction from that round was applied. Each round's defects were corrected as a closed set before the next review round began.

| Round | Result | Closed correction scope |
|---|---|---|
| 1 | Defects found and corrected | Audit-chain hashes now bind the exact canonical stored identity values; audit field widths/trace identity are bounded and compound credential/session keys are minimized. |
| 2 | Defects found and corrected | File 00 assertions must explicitly bind the current actor; assignment/action actor checks match; mutation idempotency keys use bounded canonical syntax. |
| 3 | Defect found and corrected | Runtime schema parity now derives and verifies every declared UNIQUE KEY from canonical DDL rather than a hand-selected subset. |
| 4 | Defect found and corrected | HTTP provider credentials reject oversized/control-bearing values before Authorization-header construction. |
| 5 | Defect found and corrected | Translator/reviewer actions independently reverify stored assignment qualification at action time, so revoked competency cannot remain silently usable. |
| 6 | Defect found and corrected | Bundle build rejects duplicate resource keys or translation-unit identities before deterministic signing. |
| 7 | Clean | Future40 canonical facade, release/hotfix, semantic, locale/accessibility and provider-eligibility guard stack produced no new defect in this round. |
| 8 | Clean | CI matrix, exact-source package builder, version metadata, WordPress/PHP compatibility and deterministic package evidence produced no new defect in this round. |
| 9 | Defect found and corrected | REST mutation response trace IDs and audit transition trace IDs are now request-correlated through the canonical audit trace scope. |
| 10 | Defects found and corrected | Master-plan closure found missing contextual-query resolution, missing terminology/style-policy staleness propagation, missing governed route/component/screenshot resource context refs, and materially incomplete aggregate CF06-FR-032 metrics. All were corrected after the full Round-10 audit. |

## Round 10 correction details

- Added versioned contextual-query resolution through `comments.status/resolution_text/row_version`, with current-participant/manager authorization and a dedicated REST mutation.
- Context-affecting query resolutions now mark dependent units/content links stale, invalidate affected active bundles and flush delivery cache instead of leaving stale translations eligible.
- Added conservative locale+domain dependency propagation for terminology and style-policy activation/retirement. This never mutates native-domain source truth; it only removes stale translation projections from release eligibility pending re-review.
- Resource identity now carries bounded typed `route`, `component` and `screenshot` context references. Those references are canonicalized and included in source hash/version evidence.
- Expanded aggregate metrics to expose unit-state counts, current/stale/missing coverage, non-private C1-C3 source-word workload, released-unit cycle time, QA failures by rule, feedback reopen facts and feedback grouped by locale/domain. Restricted text and actor/translator dimensions are excluded.
- Added durable aggregate-safe `TranslationFeedbackReopened`, `TranslationContextQueryResolved` and `LocalizationPolicyChanged` event contracts.
- Updated requirements traceability, data dictionary and contract documentation to match the corrected implementation.

## Evidence boundary

This ledger is repository/source evidence only. The exact commit containing this ledger must still pass the complete current GitHub Actions matrix, WordPress/MySQL integration and deterministic package job before that exact head can be called Automated-QA Green or Packaged. Staging-Accepted, Live-Deployed and Operational remain separate evidence classes. Live truth additionally requires exact deployed-source, database/schema, migration and runtime parity evidence.
