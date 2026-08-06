# Rollback

- Keep the prior plugin package, schema backup, locale bundle and contract manifest immutable.
- Disable CF-06 runtime first; public reading continues through native fallback where safe.
- Roll back active locale bundle through `BundleService::rollback`, never by editing payload rows.
- Revert plugin only after schema compatibility check; additive schema remains non-destructive by default.
- Reconcile outbox/jobs, cache, provider jobs, staleness and content links before reopening writes.
- Restore database only in an isolated environment first; verify encryption keys, holds, deletion evidence and authorization.
- Record exact commit/package/checksum, reason, operator, timestamps and post-rollback smoke tests.
