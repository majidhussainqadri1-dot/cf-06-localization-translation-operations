# CF-06 — Localization and Translation Operations

Production-oriented **source candidate** for the conditional localization operations owner of the Sabri Social Homeopathy Platform.

## Truthful lifecycle status

| Evidence class | Current status |
|---|---|
| Specified | Complete — CF06-FR-001…034, CF06-CEN-01…10, CF06-NJ-01…06 and Founder-approved CF06-FUT-001…040 |
| Coded | `1.0.0-rc.5` Future40 source candidate |
| Packaged | Built only after exact-source and WordPress/MySQL quality gates |
| Automated-QA Green | Determined per exact commit by CI |
| Staging-Accepted | No |
| Live-Deployed | No |
| Operational | No |

The runtime, external machine-translation provider and all Future40 capabilities are **disabled by default**. Staging runtime enablement is fail-closed on Founder approval plus structural/security/integration/staffing prerequisites so real acceptance journeys can run; it does **not** claim Staging-Accepted. Production activation additionally requires the full approved environment-acceptance evidence. Future40 activation remains a separate governed change-control path.

## Implemented scope

- BCP 47-style locale registry, direction, format metadata and cycle-safe fallback chains.
- Stable translatable-resource inventory with source language, semantic key, context, screenshots/references, source version/hash, typed placeholders, risk/data classification and staleness propagation.
- Translation projects, frozen source snapshots, units, assignments, qualifications, conflicts, due dates and separation of duties.
- Unit lifecycle, comments/queries, linguistic review, qualified domain review, version concurrency and immutable audit/outbox evidence.
- Terminology/glossaries, prohibited terms, domain style guides and approved translation memory with provenance.
- External machine translation limited to **approved low-risk C1 draft material only**; C2–C5 and medical/Sharīʿah/legal/privacy/security/financial/identity/message/secret domains remain provider-denied.
- ICU MessageFormat plural/select structure, typed placeholders, markup, bidi isolates, number/unit/potency, terminology and critical-coverage QA.
- Durable integration, extraction, QA and independent dual-release approval evidence.
- Deterministic signed locale bundles, staged activation, cache invalidation, exact-unit release, relational rollback and integrity re-verification.
- Source correction/rights-retirement propagation that stales dependent units/content links, invalidates affected active bundles and prevents stale signed bundles from activation, rollback or delivery.
- Content-translation relationships, feedback, coverage/staleness metrics, privacy export/erasure and migration inventory/dry-run.
- Versioned REST contracts, WP-CLI operations, accessible administrator surface, jobs/outbox, idempotency, rate limiting, health and diagnostics.
- Machine-readable reconciliation with the latest central governing laws, CF06-CEN-01…10 and CF06-NJ-01…06.
- Founder-approved **Future40** source implementation: pseudolocalization/context/device QA, source linting, semantic/risk checks, terminology intelligence, citation/token integrity, transcript/subtitle/dubbing/pronunciation workflows, PDF/OCR/accessibility intake, regional/register/calendar/numeral/glyph/line-break/input-method support, international SEO, feature launch gates, critical-copy kill switch, emergency hotfixes, delta/offline/low-bandwidth packs, private MT/provider routing/benchmarking/residency, AI quality estimation, debt forecasting, reviewer calibration, community suggestions and Founder command-center aggregation.

## Future40 activation model

`CF06-FUT-001` through `CF06-FUT-040` are code-present but default-disabled. Their guarded REST surface exposes a catalogue and evidence-preview evaluation only. It does not publish, deploy, contact a provider, mutate native-owner content or bypass qualified human approval. See `docs/FUTURE40.md`.

## Canonical boundaries

CF-06 owns localization **operations**, not original domain truth. File 19 owns notification preferences/transport; File 20 owns the global language switcher/shell placement; Files 22/23 own page/dashboard composer authoring and presentation; File 24 owns independent assurance; File 25 owns visual RTL/LTR component implementation; File 26 owns transliteration and multilingual search ranking; CF-04 owns canonical media processing; each native domain owner approves and publishes its own translated content. CF-06 never turns a cache, bundle, translation memory, provider response, AI estimate or community suggestion into authorization or source of truth.

## V12.3 public-edition policy (source candidate, no deployment)

V12.3 requires an original authored edition and **nine different** approved target editions, not nine languages guessed by CF-06. The bounded PublicEditionPolicy planner validates an effective-dated candidate, distinct targets and reserves, excludes the source via explicitly approved alias rules, and fails closed if independent Founder policy approval or enough reserve targets are absent. It never creates publication or translation jobs.

The new privileged /edition-policy/preview endpoint is read-only despite using POST for bounded structured input. Its independently verified published count is zero until real native-owner evidence is available; only current, public, owner-approved versions may count. CF-06 cannot approve its own policy or independently authorize publication.

The V12.3 ninety-requirement evidence inventory is docs/V12.3-90-REQUIREMENTS-EVIDENCE-REGISTER.json and remains 0/90 independently accepted; it is source traceability, not a coding percentage. The V12.3 reciprocal `hreflang`/canonical URL audit is available only through an authorized, read-only `/content-links/graph/audit` projection. It requires separate native owner and File 26 attestations; no automatic sitemap, translation publication or indexing action is provided. Approved V12.3 language target/reserve policy revisions can be recorded immutably via `EditionPolicyRegistry` with independent Founder approval and authorized step-up; recorded is not activated. Real approved target choices and Live parity remain missing. Cross-project duplicate translation jobs are denied via `TranslationJobConcurrencyGuard` (sorted InnoDB source-row locks and current source/version checks, no schema migration). The disposable WP/MySQL suite includes cancel/retry/version-correction cases; real multi-worker and companion acceptance remain separate. See docs/V12.3-PUBLIC-EDITION-GAP-AUDIT.md for missing Founder language decisions, File 20/21/26 contracts, real reviewer/provider/staging checks, and exact deployed-state gates.

## Development

```bash
bash tools/quality-check.sh
SOURCE_COMMIT="$(git rev-parse HEAD)" python3 tools/build-release.py
```

Runtime encryption requires the PHP OpenSSL extension; this dependency is declared in Composer metadata and exercised by CI. Local release building is bound to the checked-out Git HEAD and now rejects a dirty working tree; `SOURCE_COMMIT` may be supplied explicitly (as above) and must exactly match HEAD. If it is omitted, the builder resolves the same clean HEAD itself. The fixed ZIP archive epoch is a reproducibility control, not a build/deployment timestamp.

The CI matrix additionally performs real WordPress/MySQL activation, schema, index, idempotency, transaction and authorization integration suites across PHP 8.1/8.3 and the pinned declared-minimum/current WordPress versions 6.0.15/7.1.1.

See `docs/ARCHITECTURE.md`, `docs/REQUIREMENTS-TRACEABILITY.md`, `docs/FUTURE40.md`, `docs/FORTY-ROUND-REVIEW-CORRECTION-LEDGER.md`, `docs/THREAT-MODEL.md`, `docs/STAGING.md` and `docs/KNOWN-LIMITATIONS.md`.
