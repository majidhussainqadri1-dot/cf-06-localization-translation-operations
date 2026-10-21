# CF-06 — Fresh Forty-Round ICU Semantic Integrity Review (2026-10-21)

**This is a new forty-fixture behavioral review, not the earlier forty source-owner text-presence checks.**

## Freeze and authority

- Exact source candidate before changes: `4b73f73fb9b54ea14a19a4d455e52ba000071482` on `codex/cf-06-future40-rc5` (CF-06 `1.0.0-rc.5`, Schema `1.0.1`, Contract `1.3.0`).
- Repository main baseline: `8df210417b4b8e702771fa5560be00bd25dfff65` (README-only).
- Governing source: CF-06 Conditional Complete Master Plan v1.1 with Future40, specifically **CF06-FR-017 immutable numeric/typed placeholder behavior**, **CF06-FR-023 ICU/linguistic structural QA**, CEN-05 ICU/CLDR, and the central governing plan. Clinical, Islamic, privacy, financial and safety wording retains qualified original domain owner human-approval authority.
- This branch is source repository evidence, not a deployed plugin, database, WordPress migration, staging acceptance or owner-native cross-file contract verification.

## Proven root causes and bounded repairs

**DEFECT CF06-20261021-01 — ICU numeric plural-offset corruption allowed.** The source analyzer deliberately tracked selector type/categories, but NOT the numeric `offset` used by ICU plural/selectordinal messages. Thus source `offset:1` and target `offset:2` could pass structural equivalence despite rendering different plural counts. Fix: include a normalized, bounded, nonnegative offset in selector structural metadata; compare exact semantic value, reject missing/added/moved/duplicated or malformed offset, and preserve valid branch reordering and nested plural messages.

**DEFECT CF06-20261021-02 — Same-placeholder formatter type could be silently overwritten.** `scan()` set `$formats[$name] = $type` for every occurrence, so a later use could mask a preceding incompatible type. Fix: reject conflicting previously observed typed formatters for the same argument, whether in source or translation. Existing named argument, ICU branch and nested arguments behavior remains functional.

## Forty independent executable probes

| Review | Distinct ICU subject | Test |
|---:|---|---|
| 01 | Offset 1→2 blocked | `tests/fresh40-icu-offset-format-20261021.php` R01 |
| 02 | Offset removal blocked | `tests/fresh40-icu-offset-format-20261021.php` R02 |
| 03 | Offset addition blocked | `tests/fresh40-icu-offset-format-20261021.php` R03 |
| 04 | Offset 1 preserved | `tests/fresh40-icu-offset-format-20261021.php` R04 |
| 05 | Zero preserved | `tests/fresh40-icu-offset-format-20261021.php` R05 |
| 06 | Zero→1 blocked | `tests/fresh40-icu-offset-format-20261021.php` R06 |
| 07 | Zero-padded 001 normalized | `tests/fresh40-icu-offset-format-20261021.php` R07 |
| 08 | Ordinal 1→2 blocked | `tests/fresh40-icu-offset-format-20261021.php` R08 |
| 09 | Ordinal 2 preserved | `tests/fresh40-icu-offset-format-20261021.php` R09 |
| 10 | Reordered categories preserve offset | `tests/fresh40-icu-offset-format-20261021.php` R10 |
| 11 | Duplicate offset blocked | `tests/fresh40-icu-offset-format-20261021.php` R11 |
| 12 | Late offset blocked | `tests/fresh40-icu-offset-format-20261021.php` R12 |
| 13 | Negative offset blocked | `tests/fresh40-icu-offset-format-20261021.php` R13 |
| 14 | Fractional offset blocked | `tests/fresh40-icu-offset-format-20261021.php` R14 |
| 15 | Excessive offset blocked | `tests/fresh40-icu-offset-format-20261021.php` R15 |
| 16 | Text offset blocked | `tests/fresh40-icu-offset-format-20261021.php` R16 |
| 17 | Select rejects plural-only offset | `tests/fresh40-icu-offset-format-20261021.php` R17 |
| 18 | Missing other blocked | `tests/fresh40-icu-offset-format-20261021.php` R18 |
| 19 | Exact =0→=1 blocked | `tests/fresh40-icu-offset-format-20261021.php` R19 |
| 20 | Ordinal category drift blocked | `tests/fresh40-icu-offset-format-20261021.php` R20 |
| 21 | Offset 12 preserved | `tests/fresh40-icu-offset-format-20261021.php` R21 |
| 22 | Plural count symbol retained | `tests/fresh40-icu-offset-format-20261021.php` R22 |
| 23 | Nested offset kept | `tests/fresh40-icu-offset-format-20261021.php` R23 |
| 24 | Nested offset changed blocked | `tests/fresh40-icu-offset-format-20261021.php` R24 |
| 25 | Argument renamed blocked | `tests/fresh40-icu-offset-format-20261021.php` R25 |
| 26 | Source repeated formatter conflict | `tests/fresh40-icu-offset-format-20261021.php` R26 |
| 27 | Target repeated formatter conflict | `tests/fresh40-icu-offset-format-20261021.php` R27 |
| 28 | Identical repeat allowed | `tests/fresh40-icu-offset-format-20261021.php` R28 |
| 29 | Source date/number conflict | `tests/fresh40-icu-offset-format-20261021.php` R29 |
| 30 | Source time/date conflict | `tests/fresh40-icu-offset-format-20261021.php` R30 |
| 31 | Mixed selector type conflicts | `tests/fresh40-icu-offset-format-20261021.php` R31 |
| 32 | Repeat identical plural valid | `tests/fresh40-icu-offset-format-20261021.php` R32 |
| 33 | Source repeated offset conflicts | `tests/fresh40-icu-offset-format-20261021.php` R33 |
| 34 | Target repeated offset conflicts | `tests/fresh40-icu-offset-format-20261021.php` R34 |
| 35 | Number→date changed blocked | `tests/fresh40-icu-offset-format-20261021.php` R35 |
| 36 | Select→plural drift blocked | `tests/fresh40-icu-offset-format-20261021.php` R36 |
| 37 | New nested placeholder blocked | `tests/fresh40-icu-offset-format-20261021.php` R37 |
| 38 | Same nested placeholder retained | `tests/fresh40-icu-offset-format-20261021.php` R38 |
| 39 | Ordinal branch reorder and offset | `tests/fresh40-icu-offset-format-20261021.php` R39 |
| 40 | Ordinary select without offset | `tests/fresh40-icu-offset-format-20261021.php` R40 |

The 40 scenario outcomes are asserted by the PHP test, including valid Arabic-script/Urdu category reorderings and negative tests. This is a synthetic pure-source behavioral suite. Combined with existing `tests/fresh40-markup-icu-20261021.php`, the repo now gates on **80 distinct added ICU/markup behavioral assertions**, plus `tests/fresh40-plan-owner-20261021.php` 34 FR owner anchors and six cross-file source boundaries. This never substitutes for actual typed native API acceptance, browser rendering, Urdu/Arabic ICU/CLDR locale pack measurement, user accessibility, staging migration, or Live.

## Owner boundaries and release blockers

- File 00 controls identity, current actor/assignment, consent and authorization; File 19 controls notification transport; File 20 owns user language preference and global shell; File 25 owns visual RTL/accessibility components; File 26 owns index/ranking and transliteration. CF-06 owns only locale inventory, translation workflow, terminology, linguistic QA, signed bundle release/fallback after independent conditional activation.
- Original clinical, support, financial, media, educational and religious domain owners control source meaning/approval. No automatic clinical/legal/Sharīʿah/financial publication or unrestricted C2–C5 external MT.
- Six categories of external evidence not yet accessible: Founder Change Control/qualified reviewers; native owner signed contracts; real MT provider privacy/region/no-training/exit; real staging DB/version/migration/backups/rollback; independently witnessed RTL/CLDR/WCAG/load/security tests; approved deployed artifact, checksum, monitoring and live re-test. Do not waive them merely because CI is green.
- Final corrected HEAD + CI + package SHA256 must be obtained from completed exact-HEAD workflow; past commit/test success cannot attest a new commit.

## Live-Reality Ledger

Repository main: `8df210417b4b8e702771fa5560be00bd25dfff65`. Source candidate: this PR final HEAD after its GitHub checks. Deployed Version: **UNVERIFIED**. DB Version: **UNVERIFIED**. Migration State: **UNVERIFIED**. Live Verification Status: **NOT PERFORMED**.

**Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**
