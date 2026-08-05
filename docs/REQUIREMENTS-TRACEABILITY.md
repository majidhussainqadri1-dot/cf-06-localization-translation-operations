# Requirements Traceability — Foundation 0.1.0

| Requirement | Foundation implementation | Evidence | Status |
|---|---|---|---|
| CF06-FR-001 Locale registry | Canonical locale tags, direction, fallback, versions, status, owner | `LocaleValidator`, `LocaleService`, `slto_locales` | Implemented foundation |
| CF06-FR-002 Resource catalog | Stable key, source/version/hash, context, domain, risk, placeholders, references | `ResourceService`, `slto_resources` | Implemented foundation |
| CF06-FR-003 Source language/version | Source locale is explicit and registered; hash changes increment version | `ResourceService`, `ResourceRepository` | Implemented foundation |
| CF06-FR-004 Translatability rules | Stable semantic key and exact named-placeholder schema; concatenation/extraction CI pending | Resource validation + tests | Partial |
| CF06-FR-005 Risk classification | Low/medium/high/critical plus C1-C3 accepted; C4/C5/private blocked until secure-storage contract | Resource schema and validation | Partial; workflow enforcement pending |
| CF06-FR-006–011 Workflow/people/memory/vendor | Not represented as complete | Later C6-C/C6-F phases | Not started |
| CF06-FR-012–017 Terminology/high-risk controls | Boundary documented; data model/workflows pending | Architecture docs | Not started |
| CF06-FR-018+ MT and release governance | Runtime closed; no provider adapter or public release | Feature gate/status API | Not started |

## Truth-status rule

Version 0.1.0 is a foundation coding candidate only. It is not a complete CF-06 implementation, not staging-accepted, not live-deployed, and not operational.
