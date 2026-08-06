# Architecture and Canonical Ownership

## Governing principle

One localization operations owner, while every original record and final domain decision remains with its native owner. Commands mutate only CF-06-owned entities; companion modules consume versioned queries/events and never write CF-06 tables directly.

## Boundaries

- **File 00:** identity, capabilities, suspension, guardian/entitlement assertions.
- **File 20:** global language switcher, account preference presentation, shell route/slot placement.
- **File 24:** assurance, privacy/security posture and evidence consumption.
- **File 25:** visual RTL/LTR components, typography, responsive behavior and public presentation.
- **File 26:** transliteration, synonyms, multilingual query/ranking and discovery.
- **Native domain owner:** original post/lesson/profile/clinical/legal/financial truth and final translated publication approval.
- **CF-06:** locales, translation resources/units/projects, assignments, terminology, memory, MT draft orchestration, QA and bundle lifecycle.

## Layers

1. `Domain`: locale, workflow, linguistic, security and deterministic-release invariants.
2. `Application`: use cases with server-side authorization prerequisites and transaction boundaries.
3. `Infrastructure`: schema, encrypted payloads, repositories, audit, outbox, jobs and migrations.
4. `Contract`: public manifest, versioned events and cross-file readiness.
5. `Delivery`: REST, WP-CLI and administrator UI.

## Safety invariants

- Runtime disabled by default.
- C4/C5/private text encrypted at rest and excluded from external MT.
- Provider response is only a machine draft; it cannot be published or approved automatically.
- Every state transition is explicit; stale row versions fail with conflict.
- Active bundles require signature, 100% critical coverage and accepted cross-file dependencies.
- Rollback is versioned, audited and cache-invalidating.
- Audit payloads contain hashes/minimized metadata, not unrestricted translated text.
