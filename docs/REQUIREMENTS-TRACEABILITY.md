# CF-06 Requirements-to-Code Traceability

This matrix binds the source candidate to the latest CF-06 master plan **and** the latest central governing plan. It is source-code/automated-evidence traceability only; Hostinger-equivalent staging, qualified-human acceptance, live deployment and operations remain separate evidence classes.

## Functional requirements

| Requirement | Implemented by | Primary evidence |
|---|---|---|
| CF06-FR-001 Locale registry | LocaleService, LocaleValidator, Activator | unit/contracts tests |
| CF06-FR-002 Resource catalog | ResourceService, resources schema, governed route/component/screenshot context refs, bounded structured metadata canonicalization | contracts/review guards + cycle9 round10 + cycle34 round5 |
| CF06-FR-003 Explicit source language/version | ResourceService, project snapshots | contracts |
| CF06-FR-004 Translatability/extraction rules | resource fields, ResourceService, ExtractionService | trace/schema checks |
| CF06-FR-005 Risk classification | RiskPolicy, resource fields | unit/security review |
| CF06-FR-006 Translation projects | ProjectService, projects/project_resources | schema/contracts |
| CF06-FR-007 Assignment/qualification/SoD | ProjectService, assignments, strict UTC due/expiry evidence | code/review checks + cycle10 round5 |
| CF06-FR-008 Translation-unit lifecycle | StateMachine, TranslationService | lifecycle tests |
| CF06-FR-009 Comments and queries | TranslationService versioned comment/query resolution, comments table, contextual-change dependency invalidation | cycle9 round10 + REST/contracts |
| CF06-FR-010 Translation memory | TranslationService, TerminologyService, memory table | contracts |
| CF06-FR-011 External vendor boundary | ProviderService, HttpJsonProvider, UrlGuard | security review |
| CF06-FR-012 Terminology registry | TerminologyService, terminology table | schema/contracts |
| CF06-FR-013 Medical/homeopathic review | RiskPolicy, TranslationService, NumberUnitGuard | unit/security tests |
| CF06-FR-014 Sharīʿah/Islamic review | RiskPolicy, TranslationService | unit/security tests |
| CF06-FR-015 Legal/privacy/security/financial review | RiskPolicy, TranslationService | unit/security tests |
| CF06-FR-016 Transliteration separation | Manifest/architecture boundary with File 26 | ownership review |
| CF06-FR-017 Numbers/units/placeholders | PlaceholderValidator, MessageFormatValidator, NumberUnitGuard | unit tests |
| CF06-FR-018 MT eligibility | RiskPolicy, MachineTranslationService | unit/security tests |
| CF06-FR-019 Provider redaction | Redactor, MachineTranslationService | unit/security tests |
| CF06-FR-020 MT draft-only | MachineTranslationService, TranslationService | contracts/security review |
| CF06-FR-021 Automation suggestions | TerminologyService memory suggestions | contracts |
| CF06-FR-022 Provider feedback/training restriction | providers schema, ProviderService, PlanCompliance | schema/security review |
| CF06-FR-023 Automated linguistic QA | QaService, MessageFormatValidator and validators | unit/contracts tests |
| CF06-FR-024 In-context functional QA | BundleService QA results + `slto_verify_bundle_qa_evidence` + bundle state machine | independent route/device/accessibility evidence; lifecycle/release review; real staging remains external |
| CF06-FR-025 Deterministic locale bundle | DeterministicBundle, BundleService | unit/package tests |
| CF06-FR-026 Coverage/critical thresholds | LocalizationRepository::coverage, QaService | contracts |
| CF06-FR-027 Staged release/rollback | BundleService, bundle state machine | lifecycle/release tests |
| CF06-FR-028 Content translation publication links | ContentLinkService, content_links table, `ContentTranslationPublicationChanged` + `ContentTranslationReconciliationRequired` outbox facts | current resource/unit/native-owner binding + direct and automatic search/retraction reconciliation notification; redirects/search purge remain staging acceptance |
| CF06-FR-029 Style guides | TerminologyService, style_guides table | schema/contracts |
| CF06-FR-030 Translation feedback | FeedbackService, feedback table | schema/contracts |
| CF06-FR-031 Staleness propagation | ResourceService + DependencyInvalidator source/context/terminology/style-policy propagation | lifecycle/plan tests + cycle9 round10 |
| CF06-FR-032 Coverage/quality metrics | MetricsService aggregate unit/word/turnaround/QA/reopen/feedback/coverage definitions, HealthService | cycle9 round10; no actor surveillance dimensions |
| CF06-FR-033 Retention/deletion | PrivacyService, atomic erasure-job minimization, secure payloads/vendor purge | security/privacy review |
| CF06-FR-034 Locale/provider deprecation | LocaleService, ProviderService, state machines, `slto_verify_provider_deprecation_evidence` | purge/credential-revocation/exit evidence + lifecycle tests; real provider exit remains staging evidence |

## Latest CF-06 completion addendum

| Requirement | Source enforcement | Evidence |
|---|---|---|
| CF06-CEN-01 | LocaleValidator, LocaleService, PlanCompliance | American English policy + Urdu/Arabic first-class + deterministic fallback |
| CF06-CEN-02 | Manifest canonical/non-owner boundaries, ContentLinkService | domain owner remains source/publication authority |
| CF06-CEN-03 | RiskPolicy, ProjectService, TranslationService | qualified domain review gate for medical/Islamic/legal/privacy/security/financial material |
| CF06-CEN-04 | RiskPolicy, MachineTranslationService | external MT restricted to low-risk C1 drafts; C2–C5 and high-risk domains denied |
| CF06-CEN-05 | MessageFormatValidator, PlaceholderValidator, NumberUnitGuard, MarkupValidator, BidiValidator, QaService | ICU/placeholder/number/unit/link-markup/bidi structural QA |
| CF06-CEN-06 | TerminologyService, terminology schema | canonical concept, approved/prohibited terms, domain/source/version governance |
| CF06-CEN-07 | ResourceService, DependencyInvalidator, outbox | source correction/retirement stales units/content links, invalidates active bundles, flushes cache and emits downstream degradation/change facts |
| CF06-CEN-08 | LocalizationRepository::coverage, QaService, BundleService | 100% critical current-resource coverage before release |
| CF06-CEN-09 | BidiValidator, LocaleService, File 20/25 integration gates | safe mixed-direction isolates + deterministic locale resolution; visual/route acceptance remains companion/staging evidence |
| CF06-CEN-10 | RiskPolicy, ProviderService, PrivacyService, translation-memory restrictions | private/user data excluded from external MT/reuse by default |

## Native acceptance journeys

| Journey | Source path / gate |
|---|---|
| CF06-NJ-01 | ResourceService → ProjectService → TranslationService → QaService → BundleService staged release |
| CF06-NJ-02 | qualified domain assignment/review + terminology + ContentLinkService owner approval |
| CF06-NJ-03 | ResourceService source correction → stale units/content links → active-bundle invalidation → rebuild/re-review |
| CF06-NJ-04 | RiskPolicy low-risk C1 only → MachineTranslationService draft → human review → normal release gates |
| CF06-NJ-05 | LocaleService deterministic resolve + BidiValidator + File 20/25 integration evidence |
| CF06-NJ-06 | BundleService signed rollback/containment + correction/re-review; source invalidation also removes stale active bundle eligibility |

## Central governing-plan binding

The candidate records and tests the cross-file laws `CEN-GOV-001`, `CEN-OWN-001`, `CEN-BIZ-001`, `CEN-DON-001`, `CEN-BRAND-001`, `CEN-SHELL-001`, `CEN-NUM-001`, `CEN-SAFE-001`, `CEN-PRIV-001` and `CEN-REV-001` through `PlanCompliance`, canonical-owner boundaries, fail-closed runtime activation and companion integration gates. CF-06 does not create paid access, donor advantage, a second shell, search-ranking authority, medical/Sharīʿah source authority or autonomous clinical/financial decisions.

The central localization/internationalization constitution is additionally enforced by translation keys, explicit source locale/version, American-English technical-source policy, Urdu/Arabic RTL support, deterministic fallback, typed placeholders and ICU MessageFormat structure, locale-aware formatting metadata, safe bidi isolates, bounded structured source metadata, human high-risk review, SEO/content-link ownership boundaries and runtime fail-closed gates.

## Founder-approved Future40 expansion

| Requirement | Capability | Source enforcement | Evidence |
|---|---|---|---|
| CF06-FUT-001 | Pseudolocalization Lab | FutureCapabilitiesService::f001; FutureCapabilities registry | tests/future40.php + docs/FUTURE40.md |
| CF06-FUT-002 | Visual Context Translation Editor | FutureCapabilitiesService::f002; FutureCapabilities registry | tests/future40.php + docs/FUTURE40.md |
| CF06-FUT-003 | Device Preview Matrix | FutureCapabilitiesService::f003; FutureCapabilities registry | tests/future40.php + docs/FUTURE40.md |
| CF06-FUT-004 | Source Authoring Linter | FutureCapabilitiesService::f004; FutureCapabilities registry | tests/future40.php + docs/FUTURE40.md |
| CF06-FUT-005 | Semantic Equivalence Checker | FutureCapabilitiesService::f005; FutureCapabilities registry | tests/future40.php + docs/FUTURE40.md |
| CF06-FUT-006 | Translation Risk Diff | FutureCapabilitiesService::f006; FutureCapabilities registry | tests/future40.php + docs/FUTURE40.md |
| CF06-FUT-007 | Automatic Terminology Mining | FutureCapabilitiesService::f007; FutureCapabilities registry | tests/future40.php + docs/FUTURE40.md |
| CF06-FUT-008 | Terminology Concept Graph | FutureCapabilitiesService::f008; FutureCapabilities registry | tests/future40.php + docs/FUTURE40.md |
| CF06-FUT-009 | Citation Integrity Lock | FutureCapabilitiesService::f009; FutureCapabilities registry | tests/future40.php + docs/FUTURE40.md |
| CF06-FUT-010 | Protected Domain Tokens | FutureCapabilitiesService::f010; FutureCapabilities registry | tests/future40.php + docs/FUTURE40.md |
| CF06-FUT-011 | Transcript Localization | FutureCapabilitiesService::f011; FutureCapabilities registry | tests/future40.php + docs/FUTURE40.md |
| CF06-FUT-012 | Subtitle Localization & Timing QA | FutureCapabilitiesService::f012; FutureCapabilities registry | tests/future40.php + docs/FUTURE40.md |
| CF06-FUT-013 | Human-Reviewed AI Dubbing | FutureCapabilitiesService::f013; FutureCapabilities registry | tests/future40.php + docs/FUTURE40.md |
| CF06-FUT-014 | Pronunciation Lexicon | FutureCapabilitiesService::f014; FutureCapabilities registry | tests/future40.php + docs/FUTURE40.md |
| CF06-FUT-015 | PDF/eBook Localization Workflow | FutureCapabilitiesService::f015; FutureCapabilities registry | tests/future40.php + docs/FUTURE40.md |
| CF06-FUT-016 | OCR Intake Review | FutureCapabilitiesService::f016; FutureCapabilities registry | tests/future40.php + docs/FUTURE40.md |
| CF06-FUT-017 | Multilingual Accessibility Text | FutureCapabilitiesService::f017; FutureCapabilities registry | tests/future40.php + docs/FUTURE40.md |
| CF06-FUT-018 | Regional/Dialect Locale Packs | FutureCapabilitiesService::f018; FutureCapabilities registry | tests/future40.php + docs/FUTURE40.md |
| CF06-FUT-019 | Register & Honorific Profiles | FutureCapabilitiesService::f019; FutureCapabilities registry | tests/future40.php + docs/FUTURE40.md |
| CF06-FUT-020 | Hijri/Gregorian Calendar Layer | FutureCapabilitiesService::f020; FutureCapabilities registry | tests/future40.php + docs/FUTURE40.md |
| CF06-FUT-021 | Numeral-System Support | FutureCapabilitiesService::f021; FutureCapabilities registry | tests/future40.php + docs/FUTURE40.md |
| CF06-FUT-022 | Font/Glyph Coverage Scanner | FutureCapabilitiesService::f022; FutureCapabilities registry | tests/future40.php + docs/FUTURE40.md |
| CF06-FUT-023 | Locale Line-Break Engine | FutureCapabilitiesService::f023; FutureCapabilities registry | tests/future40.php + docs/FUTURE40.md |
| CF06-FUT-024 | Input Method Compatibility | FutureCapabilitiesService::f024; FutureCapabilities registry | tests/future40.php + docs/FUTURE40.md |
| CF06-FUT-025 | International SEO Auditor | FutureCapabilitiesService::f025; FutureCapabilities registry | tests/future40.php + docs/FUTURE40.md |
| CF06-FUT-026 | Locale Launch Gate per Feature | FutureCapabilitiesService::f026; FutureCapabilities registry | tests/future40.php + docs/FUTURE40.md |
| CF06-FUT-027 | Critical Copy Kill Switch | FutureCapabilitiesService::f027; FutureCapabilities registry | tests/future40.php + docs/FUTURE40.md |
| CF06-FUT-028 | Emergency Translation Hotfix Lane | FutureCapabilitiesService::f028; FutureCapabilities registry | tests/future40.php + docs/FUTURE40.md |
| CF06-FUT-029 | Delta Locale Bundles | FutureCapabilitiesService::f029; FutureCapabilities registry | tests/future40.php + docs/FUTURE40.md |
| CF06-FUT-030 | Offline Locale Packs | FutureCapabilitiesService::f030; FutureCapabilities registry | tests/future40.php + docs/FUTURE40.md |
| CF06-FUT-031 | Low-Bandwidth Localization Mode | FutureCapabilitiesService::f031; FutureCapabilities registry | tests/future40.php + docs/FUTURE40.md |
| CF06-FUT-032 | Private/Self-Hosted MT Adapter | FutureCapabilitiesService::f032; FutureCapabilities registry | tests/future40.php + docs/FUTURE40.md |
| CF06-FUT-033 | Multi-Provider Translation Router | FutureCapabilitiesService::f033; FutureCapabilities registry | tests/future40.php + docs/FUTURE40.md |
| CF06-FUT-034 | Provider Benchmark Sandbox | FutureCapabilitiesService::f034; FutureCapabilities registry | tests/future40.php + docs/FUTURE40.md |
| CF06-FUT-035 | Data-Residency Routing | FutureCapabilitiesService::f035; FutureCapabilities registry | tests/future40.php + docs/FUTURE40.md |
| CF06-FUT-036 | AI Quality Estimation | FutureCapabilitiesService::f036; FutureCapabilities registry | tests/future40.php + docs/FUTURE40.md |
| CF06-FUT-037 | Translation Debt Forecasting | FutureCapabilitiesService::f037; FutureCapabilities registry | tests/future40.php + docs/FUTURE40.md |
| CF06-FUT-038 | Reviewer Calibration & Adjudication | FutureCapabilitiesService::f038; FutureCapabilities registry | tests/future40.php + docs/FUTURE40.md |
| CF06-FUT-039 | Community Translation Suggestions | FutureCapabilitiesService::f039; FutureCapabilities registry | tests/future40.php + docs/FUTURE40.md |
| CF06-FUT-040 | Founder Localization Command Center | FutureCapabilitiesService::f040; FutureCapabilities registry | tests/future40.php + docs/FUTURE40.md |

All Future40 handlers are source-coded but default-disabled and return evidence-preview/validation envelopes only. They do not themselves publish, deploy, contact a provider, replace a native domain owner, or bypass qualified human review. `FutureRoutes` requires privileged authorization; `PlanCompliance` binds activation to Founder change-control, companion parity, staging, rollback and live verification.

Every row above is a source-code requirement. The canonical facade additionally enforces global byte/node bounds before all per-capability guards. Staging/manual acceptance evidence remains tracked separately and cannot be inferred from this table; live state requires separate exact deployed-source/DB/migration/runtime parity verification.
