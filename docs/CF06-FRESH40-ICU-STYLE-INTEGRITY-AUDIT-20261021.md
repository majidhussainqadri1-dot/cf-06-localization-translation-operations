# CF-06 — Fresh Forty-Round ICU Formatter Style Integrity Audit

Date: 2026-10-21. **Source-only, not Live/WordPress parity.**

## Source freeze and governing references
- Exact developer source before this audit: `ec284333f398858164f23e71a0d41acea8a173e6` (CF-06 1.0.0-rc.5, schema 1.0.1, contract 1.3.0).
- Repository `main` source HEAD: `8df210417b4b8e702771fa5560be00bd25dfff65` (README-only).
- Governing materials: CF-06 v1.1 Future40 amended 2026-09-16, especially **CF06-FR-017 Numbers/Units/Placeholders** and **CF06-FR-023 Automated Linguistic QA**; three-central-plans master governance, File 00 identity/assignment, File 19 notifications, File 20 global language preference, File 25 visual RTL and accessibility, File 26 search/transliteration, source content owners and conditional CF-01/02/03 clinical/support/financial sovereignty.
- Level of evidence: source inspection + pure PHP behavioral fixtures only. NO signed native owner contract acceptance, real provider/staging, deployed artifact/DB/migration, Founder approval or Live retest is implied.

## Newly verified code-level root cause

`MessageFormatValidator::parseArgumentAt()` previously discarded the **comma-delimited format style** of non-selector ICU arguments, returning only `type`. Therefore source `{amount, number, currency}` and target `{amount, number, percent}` both had `format = number`, falsely passing the structural QA despite changing monetary to percentage presentation. The same applied to date/time styles and currency/unit ICU skeletons. Repeated placeholders with one changed style could overwrite or fail to preserve original style identity.

**Repair**: preserve a bounded, nonempty style/skeleton in each formatted argument's source/target identity, reject malformed or style-without-comma syntax, and compare the full formatter style for repeated arguments. Preserve ordinary unstyled ICU formats, nested selector messages, valid human Urdu branch reordering and previous offset/numeric/URL safeguards. The consuming File 20/25 shell remains responsible for runtime rendering, actual ICU/CLDR formatter support and output-context escaping. This QA does not implement a new ICU renderer or turn CF-06 into the canonical clinical/financial owner.

## Forty new distinct test cases

| Round | ID | Adversarial/positive behavior | Executable evidence |
|---:|---|---|---|
| 01 | S01 | Unstyled number preserved | `tests/fresh40-icu-style-parity-20261021.php` |
| 02 | S02 | Currency style preserved | `tests/fresh40-icu-style-parity-20261021.php` |
| 03 | S03 | Percent style preserved | `tests/fresh40-icu-style-parity-20261021.php` |
| 04 | S04 | Integer style preserved | `tests/fresh40-icu-style-parity-20261021.php` |
| 05 | S05 | Short date preserved | `tests/fresh40-icu-style-parity-20261021.php` |
| 06 | S06 | Medium date preserved | `tests/fresh40-icu-style-parity-20261021.php` |
| 07 | S07 | Long date preserved | `tests/fresh40-icu-style-parity-20261021.php` |
| 08 | S08 | Short time preserved | `tests/fresh40-icu-style-parity-20261021.php` |
| 09 | S09 | Long time preserved | `tests/fresh40-icu-style-parity-20261021.php` |
| 10 | S10 | PKR currency skeleton preserved | `tests/fresh40-icu-style-parity-20261021.php` |
| 11 | S11 | USD currency skeleton preserved | `tests/fresh40-icu-style-parity-20261021.php` |
| 12 | S12 | Length/unit skeleton preserved | `tests/fresh40-icu-style-parity-20261021.php` |
| 13 | S13 | Custom date layout preserved | `tests/fresh40-icu-style-parity-20261021.php` |
| 14 | S14 | Time layout preserved | `tests/fresh40-icu-style-parity-20261021.php` |
| 15 | S15 | Harmless whitespace around style | `tests/fresh40-icu-style-parity-20261021.php` |
| 16 | S16 | Repeated identical styled argument | `tests/fresh40-icu-style-parity-20261021.php` |
| 17 | S17 | Independent styles on distinct arguments | `tests/fresh40-icu-style-parity-20261021.php` |
| 18 | S18 | Stable nested select style | `tests/fresh40-icu-style-parity-20261021.php` |
| 19 | S19 | Stable nested plural style | `tests/fresh40-icu-style-parity-20261021.php` |
| 20 | S20 | Unstyled date preserved | `tests/fresh40-icu-style-parity-20261021.php` |
| 21 | S21 | Currency→percent rejected | `tests/fresh40-icu-style-parity-20261021.php` |
| 22 | S22 | Currency→integer rejected | `tests/fresh40-icu-style-parity-20261021.php` |
| 23 | S23 | Percent→currency rejected | `tests/fresh40-icu-style-parity-20261021.php` |
| 24 | S24 | Added number style rejected | `tests/fresh40-icu-style-parity-20261021.php` |
| 25 | S25 | Removed number style rejected | `tests/fresh40-icu-style-parity-20261021.php` |
| 26 | S26 | Short→long date rejected | `tests/fresh40-icu-style-parity-20261021.php` |
| 27 | S27 | Medium→full date rejected | `tests/fresh40-icu-style-parity-20261021.php` |
| 28 | S28 | Short→long time rejected | `tests/fresh40-icu-style-parity-20261021.php` |
| 29 | S29 | PKR→USD skeleton rejected | `tests/fresh40-icu-style-parity-20261021.php` |
| 30 | S30 | Meter→kilometer skeleton rejected | `tests/fresh40-icu-style-parity-20261021.php` |
| 31 | S31 | Date-order skeleton changed rejected | `tests/fresh40-icu-style-parity-20261021.php` |
| 32 | S32 | Conflicting source repeat rejected | `tests/fresh40-icu-style-parity-20261021.php` |
| 33 | S33 | Conflicting target repeat rejected | `tests/fresh40-icu-style-parity-20261021.php` |
| 34 | S34 | Nested semantic style change rejected | `tests/fresh40-icu-style-parity-20261021.php` |
| 35 | S35 | Style without comma rejected | `tests/fresh40-icu-style-parity-20261021.php` |
| 36 | S36 | Empty target style rejected | `tests/fresh40-icu-style-parity-20261021.php` |
| 37 | S37 | Empty source style rejected | `tests/fresh40-icu-style-parity-20261021.php` |
| 38 | S38 | Control character style rejected | `tests/fresh40-icu-style-parity-20261021.php` |
| 39 | S39 | Oversized style rejected | `tests/fresh40-icu-style-parity-20261021.php` |
| 40 | S40 | Date→time formatter change rejected | `tests/fresh40-icu-style-parity-20261021.php` |

40/40 in this document is a **planned execution target** until exact-final-HEAD CI proves it. The new 40 source-safe behavioral probes supplement, rather than replace, prior 40 markup/ICU + 40 ICU offset/formatter + 40 numeric/unit behavioral probes and 40 source-owner anchor checks. These are NOT 40 live journeys. Never claim source CI alone certifies all code or domain translation meaning.

## Blocking external approvals and independent owner controls

1. **Founder** Conditional CF-06 activation/extraction and actual qualification/segregation of translators, clinical, Islamic/Sharīʿah, privacy, legal, finance and security reviewers.
2. **Companion File contract verification**: actual signed native File00/19/20/22/23/24/25/26 (plus CF01/CF02/CF03/domain content owners) runtime API/permission/consent/translation/locale bundle boundaries and acceptance. Their functionality must not be duplicated in CF-06.
3. **Vendor & law**: actual Machine Translation provider risk/region/retention/no-training/erasure/subprocessor/exit contracts. No private or high-risk automatic public translations.
4. **Staging & DB**: exact deployed plugin checksum, schema tables/options/migration rows, representative data, upgrade/recovery, reversible canary release and backup restore.
5. **Independent test acceptance**: Arabic/Urdu RTL, ICU/CLDR real locale layout and numerical/date/monetary display, WCAG 2.2 AA/400% zoom, browsers/mobile, load/security/pentest and high-risk reviewer sign-off.
6. **Release authorization & production parity**: Founder exact-artifact approval, merge/change control, deploy, live smoke, monitored rollback and installed checksum + source/DB parity.

## Mandatory Live Truth Ledger

| Required field | Evidence |
|---|---|
| Repository HEAD (`main`) | `8df210417b4b8e702771fa5560be00bd25dfff65` |
| Exact corrected source candidate | Must obtain from last CI completed successfully |
| Deployed Version | UNVERIFIED |
| DB Version | UNVERIFIED |
| Migration State | UNVERIFIED |
| Live Verification Status | NOT PERFORMED |

**Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**
