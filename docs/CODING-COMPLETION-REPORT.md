# CF-06 Coding Completion Report

**Candidate:** 1.0.0-rc.4  
**Schema:** 1.0.1  
**Contract:** 1.2.0  
**Runtime default:** disabled / fail closed

The repository source is reconciled against both current governing documents supplied for this work: the consolidated central governing master plan and the CF-06 Localization and Translation Operations master plan/completion addendum.

All original source requirements `CF06-FR-001` through `CF06-FR-034` remain mapped to implementation and automated evidence. The rc4 reconciliation additionally binds `CF06-CEN-01` through `CF06-CEN-10`, native journeys `CF06-NJ-01` through `CF06-NJ-06`, and the applicable central governing laws to implementation/evidence.

The earlier forty distinct review → correction → fresh-retest rounds remain preserved. RC4 adds a new plan-reconciliation correction layer rather than renumbering or pretending those prior forty rounds covered requirements that were supplied later.

## RC4 plan-reconciliation corrections

- ICU MessageFormat plural/select/selectordinal and nested-argument structural validation.
- Safe paired bidi isolates for Urdu/Arabic mixed LTR islands while legacy bidi embedding/override controls remain prohibited.
- External MT restricted to approved low-risk C1 drafts; C2–C5 and high-risk domains fail closed regardless of an approval flag.
- Source correction/rights-retirement propagation to translation units, translated-publication links, active locale bundles, caches and downstream change/degradation events.
- Signed locale bundle source-freshness verification at build, activation, rollback and public delivery.
- Machine-readable central/CF06 plan compliance and extended RTM coverage.
- Candidate/package/public-contract metadata updated coherently to rc4 / contract 1.2.0.

## Exact-source verification law

An accepted exact commit must pass:

- all current behavioral/adversarial unit tests, including ICU, bidi, MT-privacy and bundle-freshness guards;
- architecture/contract, ownership, security/privacy, lifecycle/resilience and release/acceptance suites;
- the existing 40 post-correction guards;
- Composer validation and all PHP syntax checks;
- secret-pattern and expanded requirements-traceability guards;
- WordPress/MySQL integration on PHP 8.1 and PHP 8.3;
- deterministic package rebuild parity, ZIP CRC, manifest/SBOM and source/package parity.

Exact commit, workflow-run, artifact digest and final SHA-256 evidence must be recorded only after the final successful GitHub Actions run so mutable documentation cannot masquerade as exact-head evidence.

## Truthful lifecycle boundary

This report asserts **repository/source coding completion within the approved conditional scope only after the exact rc4 quality gate is green**. It does not assert Hostinger staging, live deployment or operational completion. Those remain separate acceptance gates.
