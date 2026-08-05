# CF-06 Requirements-to-Code Traceability

| Requirement | Implemented by | Primary evidence |
|---|---|---|
| CF06-FR-001 Locale registry | LocaleService, LocaleValidator, Activator | unit/contracts tests |
| CF06-FR-002 Resource catalog | ResourceService, resources schema | contracts/review 1 |
| CF06-FR-003 Explicit source language/version | ResourceService, project snapshots | contracts |
| CF06-FR-004 Translatability/extraction rules | resource fields, ResourceService | trace/schema checks |
| CF06-FR-005 Risk classification | RiskPolicy, resource fields | unit/security review |
| CF06-FR-006 Translation projects | ProjectService, projects/project_resources | schema/contracts |
| CF06-FR-007 Assignment/qualification/SoD | ProjectService, assignments | code/review checks |
| CF06-FR-008 Translation-unit lifecycle | StateMachine, TranslationService | lifecycle tests |
| CF06-FR-009 Comments and queries | TranslationService, comments table | schema/contracts |
| CF06-FR-010 Translation memory | TranslationService, TerminologyService, memory table | contracts |
| CF06-FR-011 External vendor boundary | ProviderService, HttpJsonProvider, UrlGuard | security review |
| CF06-FR-012 Terminology registry | TerminologyService, terminology table | schema/contracts |
| CF06-FR-013 Medical/homeopathic review | RiskPolicy, TranslationService | unit/security tests |
| CF06-FR-014 Sharīʿah/Islamic review | RiskPolicy, TranslationService | unit/security tests |
| CF06-FR-015 Legal/privacy/security/financial review | RiskPolicy, TranslationService | unit/security tests |
| CF06-FR-016 Transliteration separation | Manifest/architecture boundary with File 26 | ownership review |
| CF06-FR-017 Numbers/units/placeholders | PlaceholderValidator, NumberUnitGuard | unit tests |
| CF06-FR-018 MT eligibility | RiskPolicy, MachineTranslationService | unit/security tests |
| CF06-FR-019 Provider redaction | Redactor, MachineTranslationService | unit/security tests |
| CF06-FR-020 MT draft-only | MachineTranslationService, TranslationService | contracts/security review |
| CF06-FR-021 Automation suggestions | TerminologyService memory suggestions | contracts |
| CF06-FR-022 Provider feedback/training restriction | providers schema, ProviderService | schema/security review |
| CF06-FR-023 Automated linguistic QA | QaService and validators | unit/contracts tests |
| CF06-FR-024 In-context functional QA | QA results, bundle state machine | lifecycle/release review |
| CF06-FR-025 Deterministic locale bundle | DeterministicBundle, BundleService | unit/package tests |
| CF06-FR-026 Coverage/critical thresholds | LocalizationRepository::coverage, QaService | contracts |
| CF06-FR-027 Staged release/rollback | BundleService, bundle state machine | lifecycle/release tests |
| CF06-FR-028 Content translation publication links | ContentLinkService, content_links table | ownership review |
| CF06-FR-029 Style guides | TerminologyService, style_guides table | schema/contracts |
| CF06-FR-030 Translation feedback | FeedbackService, feedback table | schema/contracts |
| CF06-FR-031 Staleness propagation | ResourceService, markDependentUnitsStale | lifecycle tests |
| CF06-FR-032 Coverage/quality metrics | MetricsService, HealthService | contracts/admin evidence |
| CF06-FR-033 Retention/deletion | PrivacyService, secure payloads/vendor purge | security/privacy review |
| CF06-FR-034 Locale/provider deprecation | LocaleService, ProviderService, state machines | lifecycle tests |

Every row is a source-code requirement. Staging/manual acceptance evidence remains tracked separately and cannot be inferred from this table.
