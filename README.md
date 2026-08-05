# CF-06 — Localization and Translation Operations

Conditional WordPress module for the Sabri Social Homeopathy Platform's locale registry, translatable-resource inventory, translation workflow, terminology, translation memory, controlled machine-translation drafts, linguistic QA, and locale-release governance.

## Current truthful status

| Stage | Status |
|---|---|
| Master specification | Complete |
| Foundation source | 0.1.0 candidate |
| Translation workflow | Not yet implemented |
| Terminology / memory | Not yet implemented |
| MT/TMS adapters | Not yet implemented |
| Locale bundles / release | Not yet implemented |
| Automated QA | Requires GitHub Actions evidence |
| Staging accepted | No |
| Live deployed | No |
| Operational | No |

The conditional runtime is disabled by default. Public locale APIs fail safely until Founder-approved extraction, cross-file contracts, privacy/security review, staffing, staging, rollback, and Definition of Done gates are satisfied.

## Canonical boundary

CF-06 owns localization operations. It does **not** take ownership of original domain content, final medical/Sharīʿah/legal/financial approval, File 20 language preference and shell, File 25 RTL visual implementation, or File 26 search transliteration/ranking.

## Foundation 0.1.0

- BCP 47-style locale registry with deterministic, cycle-safe fallbacks.
- Seed locales: `en-US`, `ur-PK`, and planned `ar`.
- Stable resource catalog with source locale, version, SHA-256 hash, context, risk/data class, and exact typed named placeholders.
- Privacy-minimized audit evidence and transactional writes.
- Versioned REST contracts and a truthful admin status surface.
- PHP 8.1/8.3 lint/tests, secret guard, and deterministic candidate ZIP workflow.

See `docs/ARCHITECTURE.md` and `docs/REQUIREMENTS-TRACEABILITY.md`.
