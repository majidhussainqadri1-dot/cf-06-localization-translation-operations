# Rollback

- Keep the prior plugin package, schema backup, locale bundle and contract manifest immutable.
- Disable CF-06 runtime first; public reading continues through native fallback where safe.
- Roll back an active locale bundle only to its exact `previous_bundle_uuid`; arbitrary historical bundle selection is not a rollback.
- Before bundle rollback, obtain fresh independently verified dual release approvals with recent step-up proof for the exact prior signed bundle.
- Supply a bounded incident/operational reason to `BundleService::rollback`; never edit payload rows directly.
- Revert plugin only after schema compatibility check; additive schema remains non-destructive by default.
- Reconcile outbox/jobs, cache, provider jobs, staleness and content links before reopening writes.
- Restore database only in an isolated environment first; verify encryption keys, holds, deletion evidence and authorization.
- Record exact commit/package/checksum, reason, operator, timestamps and post-rollback smoke tests.
