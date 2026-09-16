# CF-06 Source Coding Completion Evidence

## Candidate identity

- Candidate: `1.0.0-rc.5`
- Database schema: `1.0.1`
- Public contract: `1.3.0`
- Runtime default: disabled / fail closed
- Functional requirements: `CF06-FR-001` through `CF06-FR-034`
- Latest completion requirements: `CF06-CEN-01` through `CF06-CEN-10`
- Native acceptance journeys: `CF06-NJ-01` through `CF06-NJ-06`
- Founder-approved Future40: `CF06-FUT-001` through `CF06-FUT-040`
- Governing basis: latest supplied consolidated central plan + latest supplied CF-06 plan + Founder-approved Future40 amendment

## Evidence integrity rule

Exact Git head, GitHub Actions run, artifact digest, package SHA-256 and deterministic rebuild result are commit-specific. They are therefore recorded in the Pull Request's final exact-head verification record after CI completion rather than hard-coded here and allowed to become stale after later commits.

## Required automated evidence

- PHP 8.1 and PHP 8.3 exact-source quality gates;
- original behavioral, architecture/contract, ownership, security/privacy, lifecycle and release/acceptance suites;
- 40 prior post-correction guards;
- rc4 plan-reconciliation tests for ICU MessageFormat, balanced bidi isolates, low-risk C1-only external MT, source-correction propagation and stale-bundle rejection;
- Future40 registry/handler tests covering every `CF06-FUT-001` through `CF06-FUT-040` under a disabled-by-default fail-closed policy;
- real WordPress/MySQL activation and integration suite on both PHP versions;
- Composer validation, all PHP syntax, secret-pattern guard and expanded requirements traceability;
- deterministic package rebuild parity, ZIP CRC, manifest, SBOM and source/package parity;
- artifact upload tied to the exact commit.

## Future40 source-completion boundary

All forty Future40 capabilities are represented by executable handler code, canonical IDs, guarded REST evidence-preview access, RTM rows and automated tests. This source candidate intentionally does **not** treat those handlers as production activation. They cannot auto-publish, cannot replace domain-owner approval, cannot bypass qualified human review, and cannot contact providers merely through the preview route.

## Truthful lifecycle status

- Specified: complete for approved conditional + Future40 scope
- Coded: rc5 Future40 source candidate; exact-head acceptance requires green CI
- Packaged: only when current exact-head artifact verification succeeds
- Automated QA: only when current exact-head workflow is green
- Staging accepted: pending
- Live deployed: no
- Operational: no

Hostinger-equivalent staging, real companion integrations/providers, qualified human linguistic/domain staffing, browser/device/accessibility acceptance, real media/OCR/dubbing/calendar/font/provider infrastructure, backup/restore and rollback rehearsal, independent security/privacy acceptance, Founder release sign-off and live monitoring remain separate release gates.
