# CF-06 — Forty Fresh Numeric and Unit Integrity Reviews — 2026-10-21

## Authority, exact-source freeze and nondeployment
- Source of planned acceptance: **CF-06 Conditional Complete Master Plan v1.1 Future40 amended 2026-09-16**, especially CF06-FR-017 numbers/units/typed placeholders, CF06-FR-023 linguistic QA, six representative journeys (CF06-NJ-01..06), CF06-CEN, and central governing plans on canonical ownership/medical/financial safety. Domain owners and qualified reviewers decide meaning; CF-06 cannot supersede their approval.
- Repository `main` HEAD frozen before patch: `8df210417b4b8e702771fa5560be00bd25dfff65`, README only.
- Exact code candidate frozen before patch: `85dbbb7b5e9dbd511f1c1cc2c7ca985fbdf5864f` in `codex/cf-06-future40-rc5`; this is **NOT** a deployed artifact.
- New bounded branch `audit/cf06-fresh40-numeric-unit-integrity-20261021`; corrected final SHA/CI must come from its exact completed run, not inferred from a prior green run.

## Three confirmed new source defects (scope-limited fixes)

1. **Numeric-order safety:** `NumberUnitGuard::tokens()` called `sort()` on extracted amounts. Therefore `5 mg, 10 mg` could incorrectly be considered equal to `10 mg, 5 mg`, even though pairing and instructions changed. **Fix:** compare source and translation in preserved encounter order, with conservative refusal for ambiguous reorder.
2. **Clinical-unit/currency/sign parity:** A short unit pattern omitted important typed quantity units (e.g. `IU`, `mEq/L`, `mmol/L`, `mg/dL`, `mmHg`, `mcg/kg/min`), prefix currency symbols (e.g. `PKR 500` vs `USD 500`), and leading negative/positive signs. Some textual mutations could leave extracted numeric tokens unchanged. **Fix:** bounded conservative matcher protects numeric+unit, prefixed ISO currency and explicit sign as one value and rejects unsafe changes. This is **not** a full UCUM/CLDR implementation and cannot substitute for human medical/financial review.
3. **Low-risk QA bypass:** `QaService::unit()` passed a conditional strictness flag based on whether a domain qualified as high-risk. For low-risk resource types the numeric/unit check returned immediately, allowing basic figures to drift. The master plan requires protected numerical QA before release, regardless of risk tier. **Fix:** enforce quantity guard for **every** Translation Unit; retain additional qualified human review for high-risk material. A mandatory static CI guard prevents accidental reintroduction of this bypass.

## Forty new independent positive and adversarial fixtures

These are 40 named **synthetic behavioral checks**, not repeated CI runs and not forty Live/WordPress user journeys.

| Round | Case | What is independently checked | Evidence owner |
|---:|---|---|---|
| 1 | N01 | Preserve ordinary mg amount | `tests/fresh40-numeric-unit-20261021.php` |
| 2 | N02 | Normalize insignificant spacing around unit | `tests/fresh40-numeric-unit-20261021.php` |
| 3 | N03 | Preserve two-dose order | `tests/fresh40-numeric-unit-20261021.php` |
| 4 | N04 | Preserve three-dose order | `tests/fresh40-numeric-unit-20261021.php` |
| 5 | N05 | Preserve fractional mL dose | `tests/fresh40-numeric-unit-20261021.php` |
| 6 | N06 | Preserve homeopathic 200C potency | `tests/fresh40-numeric-unit-20261021.php` |
| 7 | N07 | Preserve 30C potency | `tests/fresh40-numeric-unit-20261021.php` |
| 8 | N08 | Preserve 1M potency | `tests/fresh40-numeric-unit-20261021.php` |
| 9 | N09 | Preserve PKR amount | `tests/fresh40-numeric-unit-20261021.php` |
| 10 | N10 | Preserve USD amount | `tests/fresh40-numeric-unit-20261021.php` |
| 11 | N11 | Preserve EUR amount | `tests/fresh40-numeric-unit-20261021.php` |
| 12 | N12 | Preserve IU quantity | `tests/fresh40-numeric-unit-20261021.php` |
| 13 | N13 | Preserve mEq/L quantity | `tests/fresh40-numeric-unit-20261021.php` |
| 14 | N14 | Preserve mmol/L quantity | `tests/fresh40-numeric-unit-20261021.php` |
| 15 | N15 | Preserve negative sign | `tests/fresh40-numeric-unit-20261021.php` |
| 16 | N16 | Preserve positive sign | `tests/fresh40-numeric-unit-20261021.php` |
| 17 | N17 | Preserve mcg/kg/min | `tests/fresh40-numeric-unit-20261021.php` |
| 18 | N18 | Preserve percentage | `tests/fresh40-numeric-unit-20261021.php` |
| 19 | N19 | Preserve mmHg | `tests/fresh40-numeric-unit-20261021.php` |
| 20 | N20 | Preserve multi-field bilingual quantity sequence | `tests/fresh40-numeric-unit-20261021.php` |
| 21 | N21 | Reject swapped distinct doses | `tests/fresh40-numeric-unit-20261021.php` |
| 22 | N22 | Reject swapped same-value different units | `tests/fresh40-numeric-unit-20261021.php` |
| 23 | N23 | Reject mg→mL | `tests/fresh40-numeric-unit-20261021.php` |
| 24 | N24 | Reject IU→mEq | `tests/fresh40-numeric-unit-20261021.php` |
| 25 | N25 | Reject mEq/L→mmol/L | `tests/fresh40-numeric-unit-20261021.php` |
| 26 | N26 | Reject PKR→USD | `tests/fresh40-numeric-unit-20261021.php` |
| 27 | N27 | Reject USD→EUR | `tests/fresh40-numeric-unit-20261021.php` |
| 28 | N28 | Reject dropped negative sign | `tests/fresh40-numeric-unit-20261021.php` |
| 29 | N29 | Reject dropped positive sign | `tests/fresh40-numeric-unit-20261021.php` |
| 30 | N30 | Reject added negative sign | `tests/fresh40-numeric-unit-20261021.php` |
| 31 | N31 | Reject 30C→200C | `tests/fresh40-numeric-unit-20261021.php` |
| 32 | N32 | Reject 0.5→0.05 | `tests/fresh40-numeric-unit-20261021.php` |
| 33 | N33 | Reject omitted dose | `tests/fresh40-numeric-unit-20261021.php` |
| 34 | N34 | Reject added dose | `tests/fresh40-numeric-unit-20261021.php` |
| 35 | N35 | Reject swapped 5mg/5mL | `tests/fresh40-numeric-unit-20261021.php` |
| 36 | N36 | Reject mmol→IU | `tests/fresh40-numeric-unit-20261021.php` |
| 37 | N37 | Reject mg/dL→mmol/L | `tests/fresh40-numeric-unit-20261021.php` |
| 38 | N38 | Reject µg→mg | `tests/fresh40-numeric-unit-20261021.php` |
| 39 | N39 | Reject mmHg→kPa | `tests/fresh40-numeric-unit-20261021.php` |
| 40 | N40 | Reject IU→U | `tests/fresh40-numeric-unit-20261021.php` |

Prior distinct tests remain mandatory: 40 HTML/ICU behaviors, 40 ICU plural offset/formatter behaviors, and 40 CF06-FR/central source ownership probes, plus the wider Future40 and WordPress/PHP matrix. They are related, not evidence of all possible text/clinical edge cases.

## Cross-file governance, no duplicated canonical ownership

- **File 00:** identity, role permissions and reviewer authority; CF-06 must not duplicate authorization ownership.
- **File 19:** notifications/message delivery; CF-06 supplies approved copy only.
- **File 20:** language preference and shell; CF-06 supplies locale bundles and fallback contracts.
- **File 25:** visual RTL/LTR and accessibility; CF-06 supplies linguistic QA evidence, not UI ownership.
- **File 26:** search ranking/transliteration/index and localized URL contract; CF-06 may offer reviewed translated slugs only.
- Clinical/medical (CF01), support (CF02), finance (CF03), media and original content owners retain canonical meaning, source versions, publication rights, signed approvals. No unapproved MT on clinical, financial, private, legal or Islamic high-risk materials.
- The 40 Future40 capabilities remain independently conditional/default-disabled, not activated by a source patch.

## Independent release gates still not removable from repository

1. Founder conditional CF-06 activation and qualified translators/reviewers/clinical/Islamic/legal/financial approval.
2. Versioned native-owner event/translation/shell/search/notification/permission contracts accepted in real integration.
3. External translation provider approvals on region, no training, no private payload/secret leakage, retention, deletion and exit.
4. Actual WordPress staging installed code/db tables/schema/wp_options/migrations, backup restore and rollback exercises.
5. Real Urdu/Arabic RTL/CLDR/ICU/fonts/WCAG/400% zoom/browser/pen-test/performance/clinical QA acceptance.
6. Founder production decision and package-deployment checksum, canary/logs, live retest and parity confirmation.

## Mandatory Live Reality Ledger
- Repository HEAD (main): `8df210417b4b8e702771fa5560be00bd25dfff65`.
- Exact corrected source HEAD: await final CI and release package proof.
- Deployed Version: **UNVERIFIED**.
- DB Version: **UNVERIFIED**.
- Migration State: **UNVERIFIED**.
- Live Verification Status: **NOT PERFORMED**.

**Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**
