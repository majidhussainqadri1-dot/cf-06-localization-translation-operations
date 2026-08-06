# CF-06 — Localization and Translation Operations

Production-oriented **source candidate** for the conditional localization operations owner of the Sabri Social Homeopathy Platform.

## Truthful lifecycle status

| Evidence class | Current status |
|---|---|
| Specified | Complete — CF06-FR-001 through CF06-FR-034 |
| Coded | `1.0.0-rc.3` source candidate after forty review/fix rounds |
| Packaged | Built only after exact-source and WordPress/MySQL quality gates |
| Automated-QA Green | Determined per exact commit by CI |
| Staging-Accepted | No |
| Live-Deployed | No |
| Operational | No |

The runtime and external machine-translation provider are **disabled by default**. Activation remains fail-closed until Founder approval, cross-file contracts, encryption/signing keys, qualified linguistic staffing, provider privacy review, staging, rollback and acceptance evidence exist.

## Implemented scope

- BCP 47-style locale registry, direction, format metadata and cycle-safe fallback chains.
- Stable translatable-resource inventory with source language, semantic key, context, screenshots/references, source version/hash, typed placeholders, risk/data classification and staleness propagation.
- Translation projects, frozen source snapshots, units, assignments, qualifications, conflicts, due dates and separation of duties.
- Unit lifecycle, comments/queries, linguistic review, qualified domain review, version concurrency and immutable audit/outbox evidence.
- Terminology/glossaries, prohibited terms, domain style guides and approved translation memory with provenance.
- Draft-only external MT orchestration with allowlisted HTTPS providers, credential references, redaction, C4/C5 blocking, provider/model/version provenance, validation, human review and deletion evidence.
- Placeholder, markup, bidi, number/unit/potency, terminology and critical-coverage QA.
- Durable integration, extraction, QA and independent dual-release approval evidence.
- Deterministic signed locale bundles, staged activation, cache invalidation, exact-unit release, relational rollback and integrity re-verification.
- Content-translation relationships, feedback, coverage/staleness metrics, privacy export/erasure and migration inventory/dry-run.
- Versioned REST contracts, WP-CLI operations, accessible administrator surface, jobs/outbox, idempotency, rate limiting, health and diagnostics.

## Canonical boundaries

CF-06 owns localization **operations**, not original domain truth. File 20 owns the global language switcher/shell placement; File 25 owns visual RTL/LTR component implementation; File 26 owns transliteration and multilingual search ranking; each native domain owner approves and publishes its own translated content. CF-06 never turns a cache, bundle, translation memory or provider response into authorization or source of truth.

## Development

```bash
bash tools/quality-check.sh
python3 tools/build-release.py
```

The CI matrix additionally performs a real WordPress/MySQL activation, schema, index, idempotency, transaction and authorization integration suite on PHP 8.1 and PHP 8.3.

See `docs/ARCHITECTURE.md`, `docs/REQUIREMENTS-TRACEABILITY.md`, `docs/FORTY-ROUND-REVIEW-CORRECTION-LEDGER.md`, `docs/THREAT-MODEL.md`, `docs/STAGING.md` and `docs/KNOWN-LIMITATIONS.md`.
