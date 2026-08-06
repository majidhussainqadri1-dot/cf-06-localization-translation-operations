# Migration

1. Inventory existing locale/string/domain translation stores and canonical owners.
2. Freeze versioned contracts and produce source key/hash snapshots.
3. Run CF-06 migration dry-run; quarantine malformed, ownerless or conflicting rows.
4. Import idempotently in bounded batches with checkpoints and audit hashes.
5. Dual-read/shadow-compare source, fallback, permissions, staleness and coverage.
6. Reconcile every divergence; no silent overwrite of original/native records.
7. Cut over only on approved staging with backup/restore and rollback evidence.
8. Retire legacy write paths after parity; preserve historical redirects/mappings where needed.

Migration never enables runtime automatically.
