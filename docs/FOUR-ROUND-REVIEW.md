# Four Review-and-Correction Rounds

1. **Ownership/architecture:** removed duplicate-domain authority, froze File 20/25/26/native boundaries, added content-link projections and versioned manifest.
2. **Security/privacy:** enforced encrypted restricted payloads, redaction, C4/C5 MT denial, HTTPS allowlists, secret references, idempotency/rate limits and safe errors.
3. **Lifecycle/resilience:** completed state machines, source staleness, optimistic concurrency, nested transaction savepoints, audit/outbox/jobs and provider failure reconciliation.
4. **Release/acceptance:** completed deterministic signed bundles, coverage/integration gates, rollback, migration/staging/operations documentation, exact-source CI and package evidence.

Each round has an executable test. Any newly discovered defect reopens review and blocks affected release.

## Final closure pass after the four planned rounds

A final release-closure pass corrected additional edge cases discovered while reconciling all source files as one batch:

- native-domain publication approval now requires a verifiable owner callback rather than a non-empty reference;
- locale bundles can become active only after staged/canary release, never directly from approval;
- provider deprecation counts all unresolved jobs rather than a 500-row sample;
- migration dry-run evidence validates source hashes and fails if its evidence record cannot be persisted;
- WordPress privacy erasure is registered and queues an auditable background operation;
- manually recorded bundle QA now creates audit evidence.
- provider jobs no longer claim `human_reviewed` immediately after machine output; acceptance now requires an explicit qualified review and evidence that every draft entered human editing/review;

All relevant executable review suites were extended and rerun after these corrections.
