# CF-06 — Fresh Forty-Round Source + Behavioral Audit (21 October 2026)

## Frozen exact source / evidence classes

- Central and module master-plan authority: attached **CF-06 v1.1 Future40 (2026-09-16)**, especially 34 FRs, CF06-CEN-01..10, CF06-NJ-01..06, 40 separately gated Future40 and the harmonized three-central-plan appendix.
- Repository **main**: `8df210417b4b8e702771fa5560be00bd25dfff65`, README only.
- Reviewed exact candidate parent: `e5cc59e0c7c9443c062b9ab34f0360507ef3fd60`, source `1.0.0-rc.5`, contract `1.3.0`, schema `1.0.1`.
- Audit branch: `audit/cf06-fresh40-markup-icu-20261021`. A source-repository branch is NOT a deployed plugin. Verify the **final** SHA and latest CI again after any changes.
- Forty plan review controls and forty separate executable behavioral probes are kept distinct. They do not prove WordPress/Hostinger staging, real File00/19/20/24/25/26 contracts, clinical/legal reviewer staffing, translation provider compliance or Live truth.

## Forty plan-to-code checkpoints

| Round | ID | Actual master-plan / companion boundary | Evidence scope |
|---:|---|---|---|
| 1 | CF06-FR-001 | Locale registry and deterministic fallback | Source owner anchor, independently accepted runtime not verified |
| 2 | CF06-FR-002 | Resource catalog and source hashes | Source owner anchor, independently accepted runtime not verified |
| 3 | CF06-FR-003 | Canonical source-language/version | Source owner anchor, independently accepted runtime not verified |
| 4 | CF06-FR-004 | Translation extraction rules | Source owner anchor, independently accepted runtime not verified |
| 5 | CF06-FR-005 | Risk classification and reviewer class | Source owner anchor, independently accepted runtime not verified |
| 6 | CF06-FR-006 | Translation projects/versioned scope | Source owner anchor, independently accepted runtime not verified |
| 7 | CF06-FR-007 | Assignment/qualification/SoD | Source owner anchor, independently accepted runtime not verified |
| 8 | CF06-FR-008 | Translation-unit state-machine | Source owner anchor, independently accepted runtime not verified |
| 9 | CF06-FR-009 | Comments and contextual queries | Source owner anchor, independently accepted runtime not verified |
| 10 | CF06-FR-010 | Translation memory privacy/reuse | Source owner anchor, independently accepted runtime not verified |
| 11 | CF06-FR-011 | External vendor boundary | Source owner anchor, independently accepted runtime not verified |
| 12 | CF06-FR-012 | Terminology/glossary registry | Source owner anchor, independently accepted runtime not verified |
| 13 | CF06-FR-013 | Medical/domain-qualified human review | Source owner anchor, independently accepted runtime not verified |
| 14 | CF06-FR-014 | Islamic/Sharīʿah review | Source owner anchor, independently accepted runtime not verified |
| 15 | CF06-FR-015 | Legal/privacy/security/financial review | Source owner anchor, independently accepted runtime not verified |
| 16 | CF06-FR-016 | Transliteration not search-owner | Source owner anchor, independently accepted runtime not verified |
| 17 | CF06-FR-017 | Values/units/placeholders immutable | Source owner anchor, independently accepted runtime not verified |
| 18 | CF06-FR-018 | MT draft eligibility C1 | Source owner anchor, independently accepted runtime not verified |
| 19 | CF06-FR-019 | Outbound PII redaction | Source owner anchor, independently accepted runtime not verified |
| 20 | CF06-FR-020 | Machine-translation draft-only lifecycle | Source owner anchor, independently accepted runtime not verified |
| 21 | CF06-FR-021 | Suggestion provenance and human rejection | Source owner anchor, independently accepted runtime not verified |
| 22 | CF06-FR-022 | No provider-training reuse | Source owner anchor, independently accepted runtime not verified |
| 23 | CF06-FR-023 | Linguistic QA incl ICU/HTML/Bidi | Source owner anchor, independently accepted runtime not verified |
| 24 | CF06-FR-024 | In-context RTL and accessible QA | Source owner anchor, independently accepted runtime not verified |
| 25 | CF06-FR-025 | Deterministic signed bundles | Source owner anchor, independently accepted runtime not verified |
| 26 | CF06-FR-026 | Critical 100% coverage gates | Source owner anchor, independently accepted runtime not verified |
| 27 | CF06-FR-027 | Staged activation and rollback | Source owner anchor, independently accepted runtime not verified |
| 28 | CF06-FR-028 | Content-owner approval, source staleness, hreflang | Source owner anchor, independently accepted runtime not verified |
| 29 | CF06-FR-029 | Locale style guides | Source owner anchor, independently accepted runtime not verified |
| 30 | CF06-FR-030 | Translation defect feedback | Source owner anchor, independently accepted runtime not verified |
| 31 | CF06-FR-031 | Source revision propagation to dependent bundles | Source owner anchor, independently accepted runtime not verified |
| 32 | CF06-FR-032 | Aggregate coverage metrics, no surveillance | Source owner anchor, independently accepted runtime not verified |
| 33 | CF06-FR-033 | Retention/deletion/provider purge | Source owner anchor, independently accepted runtime not verified |
| 34 | CF06-FR-034 | Locale/provider deprecation and exit | Source owner anchor, independently accepted runtime not verified |
| 35 | CEN-CROSS-01 | File00 identity and assignment authority | Source contract control, native owner acceptance not verified |
| 36 | CEN-CROSS-02 | File20 preference and shell owner | Source contract control, native owner acceptance not verified |
| 37 | CEN-CROSS-03 | File25 RTL visual and design owner | Source contract control, native owner acceptance not verified |
| 38 | CEN-CROSS-04 | File26 search and transliteration owner | Source contract control, native owner acceptance not verified |
| 39 | CEN-CROSS-05 | Future40 gated default-disabled | Source contract control, native owner acceptance not verified |
| 40 | CEN-CROSS-06 | Exact deployed artifact/DB and live parity | Source contract control, native owner acceptance not verified |

Executed by `tests/fresh40-plan-owner-20261021.php` with 40 uniquely numbered code-owner probes; no raw source claims are misrepresented as complete clinical, financial, linguist or browser journeys.

## Forty independent adversarial behavioral checks

`tests/fresh40-markup-icu-20261021.php` exercises **M01..M28** distinct HTML/URL/attribute/RTL/localized-label scenarios and **I29..I40** ICU MessageFormat/plural/select/formatter and nested-placeholder scenarios. This is 40 executable pure-code checks, not 40 real WordPress journeys.

### Proven fresh defect classes and corrections

1. **Unsafe markup attribute/link integrity:** The original markup comparison discarded HTML attributes and compared only names of tags. An attacker-controlled or incorrect translation could switch a reviewed HTTPS link to `javascript:`, change a canonical URL, or introduce `onclick`/inline CSS without violating the original tag-only QA. **Correction:** bounded allowlist, quoted attribute grammar, decoded URL scheme validation, immutable navigation/security attributes (href/class/rel/target), and consistent tag structure. Legitimate `aria-label`, `title`, `lang` and `dir` localization is preserved with additional validation. HTML output-context escaping remains the consuming UI owner's responsibility.
2. **Duplicate ICU selector ambiguity:** Original `MessageFormatValidator::selectorCategories` silently applied `array_unique` to duplicate plural/select branches. Ambiguous duplicates could pass QA. **Correction:** explicitly reject repeated branch names before equivalence comparison; positive Urdu plural/select reordering retained.
3. **ICU branch-body false placeholder detection:** The original ICU recursive scan included plural/select branch wrapper braces, causing plain English branch prose such as `one {one}` to be misclassified as a named placeholder. This falsely rejected otherwise valid Urdu translations. **Correction:** recurse into the *message inside* each selector branch, preserving real nested arguments while not treating branch delimiters as placeholders. Verified by distinct plural/select reordering and nested ICU fixtures.
4. **New-patch regression found and repaired before acceptance:** the first new link-check regular expression failed PCRE compilation; early CI exposed M05/M08/M09 failures. Replaced with deterministic `parse_url` + explicit scheme/host checks. The same CI also surfaced I30/I31, which led to the independent branch-body root cause above. No first-CI green claim.

## Governing ownership and safety

- CF-06 owns locale/resource registry, translation lifecycle, terminology/memory, linguistic QA and signed bundle/fallback governance **only after conditional activation**.
- File 20 owns language preference/global shell; File 25 owns visual RTL/LTR; File 26 search transliteration/ranking; File 19 notification transport; File 24 assurance; File 00 authenticates/authorizes; clinical (CF01), support (CF02), financial (CF03), media (CF04) and original content/publication remain original-owner decisions.
- Machine translation remains **only clearly labeled low-risk C1 drafts**, human-reviewed before release. No raw private messages, clinical charts, IDs, payment secrets, high-risk source, medical or legal/policy text sent to vendor without independent qualified gate.
- Future40 (CF06-FUT-001..040) source presence is NOT activation; no public automatic high-risk publication or privilege.
- Root-cause-first/no-patch stacking: no live patch because exact deployed CF-06 artifact and schema/migration are unverified.

## Independent external blockers

| Gate | Evidence presently available |
|---|---|
| Founder conditional CF-06 extraction authorization/independent staff | NOT VERIFIED |
| File00/19/20/22/23/24/25/26 and native-domain typed owner contract acceptance | NOT VERIFIED |
| MT provider legal/privacy/region/no-training/deletion/exit | NOT VERIFIED |
| Real staging WordPress/DB schema, upgrade, backfill, backup/restore, canary and rollback | NOT VERIFIED |
| Real mobile/RTL/CLDR/ICU/WCAG 2.2 AA/400%-zoom/browser/load/security/penetration | NOT VERIFIED |
| Production deploy, exact installed source checksum, live regression/smoke/SLO/Founder approval | NOT VERIFIED |

**Truth ledger:** GitHub main `8df210417b4b8e702771fa5560be00bd25dfff65`; corrected candidate HEAD derive from final PR; Deployed Version UNKNOWN; DB Version UNKNOWN; Migration State UNKNOWN; Live Verification Status NOT PERFORMED.

“Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔”
