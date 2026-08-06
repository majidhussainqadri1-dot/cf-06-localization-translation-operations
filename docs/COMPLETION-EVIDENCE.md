# CF-06 Source Coding Completion Evidence

## Candidate identity

- Candidate: `1.0.0-rc.3`
- Database schema: `1.0.1`
- Public contract: `1.1.0`
- Runtime default: disabled / fail closed
- Requirements: `CF06-FR-001` through `CF06-FR-034`

## Evidence integrity rule

Exact Git head, GitHub Actions run, artifact digest, package SHA-256 and deterministic rebuild result are commit-specific. They are therefore recorded in the Draft Pull Request's final exact-head verification comment after CI completion rather than hard-coded here and allowed to become stale after later commits.

## Required automated evidence

- PHP 8.1 and PHP 8.3 exact-source quality gates;
- 95 executable source/adversarial checks: 20 behavioral, 11 architecture/contract, 4 ownership, 6 security/privacy, 9 lifecycle/resilience, 5 release/acceptance and 40 post-correction guards;
- real WordPress/MySQL activation and integration suite on both PHP versions;
- Composer validation, all PHP syntax, secret-pattern guard and requirements traceability;
- deterministic package rebuild parity, ZIP CRC, manifest, SBOM and source/package parity;
- artifact upload tied to the exact commit.

## Truthful lifecycle status

- Specified: complete
- Coded: complete source candidate
- Packaged: only when current exact-head artifact verification succeeds
- Automated QA: only when current exact-head workflow is green
- Staging accepted: pending
- Live deployed: no
- Operational: no

Hostinger-equivalent staging, real external providers, qualified human linguistic staffing, browser/device/accessibility acceptance, backup/restore and rollback rehearsal, independent security/privacy acceptance, Founder sign-off and live monitoring remain separate release gates.
