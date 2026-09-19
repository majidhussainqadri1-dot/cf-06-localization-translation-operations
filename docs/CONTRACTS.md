# Versioned Contracts

- Namespace: `sabri-localization/v1`.
- Manifest: `sabri_localization_manifest()` and `sabri_localization_manifest` filter.
- Locale query/resolution: public-safe enabled locale DTOs only.
- Active bundle query: hash, signature, version and payload for activated locale only.
- Events: past-tense facts dispatched from the transactional outbox; consumers must be idempotent.
- Every companion action must revalidate current File 00/native-owner state; manifest presence is not authorization.
- Contract version is independent from plugin and schema versions.

## Human-reviewed machine translation

- `POST /mt/jobs/{uuid}/review` — qualified domain reviewer confirms that every provider draft entered human editing/review before the provider job can be accepted.

## Future40 guarded evidence-preview contracts

- `GET /future-capabilities` — privileged catalogue only. It returns the forty registered capability descriptors, `default_state=disabled`, the governing activation gates and `activation_ready=false`. It is not a public feature-discovery API and is not activation evidence.
- `POST /future-capabilities/{CF06-FUT-NNN}/evaluate` — privileged, bounded **evidence-preview/validation** operation executed through `FutureCapabilitiesFacade`. Request size and structure are bounded, invalid/unknown capabilities fail closed, and internal failures return safe errors.
- The Future40 REST surface has **no activation, publication, provider-send, native-content mutation, release-bypass or domain-owner approval command**.
- A successful preview response does not authorize staging, production, publication, provider use or high-risk translation. `activation_ready` remains false until Founder change-control, privacy/security/domain review, companion-contract parity, real staging acceptance, rollback/restore rehearsal and live deployment verification are separately evidenced.
- Raw `FutureCapabilitiesService` handlers are internal implementation details; application/REST consumers must use the guarded `FutureCapabilitiesFacade` path.
- Per-ID security/privacy/safety rules, owner boundaries and evidence classes are recorded in `docs/FUTURE40-TRACEABILITY-EVIDENCE.md`.


## Independent governance verification hooks

These filters are fail-closed companion contracts; their default result is denial, never approval.

- `slto_verify_assignment_qualification` — File 00/native qualification authority must independently attest the assignee, role, target locale, domain/risk, unit/project and supplied competency evidence before an assignment is created or transferred.
- `slto_verify_provider_purge_evidence` — privacy/provider assurance must independently verify deletion evidence for the exact governed vendor job/provider/reference before CF-06 records the job as purged.
- `slto_verify_bundle_qa_evidence` — every **passing** in-context bundle QA rule must be independently attested for the exact bundle hash/version/locale, reviewer and rule before it can become release evidence. Failed QA can still be recorded without this approval path.
- `slto_verify_qa_evidence` — durable CI/staging/production QA evidence rows are accepted only after an independent verifier attests the exact target, environment, plugin/build identity, test ID and artifact/hash evidence.
- `slto_verify_release_approval_evidence`, `slto_verify_provider_activation_evidence`, `slto_verify_integration_acceptance_evidence`, `slto_verify_extraction_evidence` and `slto_verify_production_activation_evidence` remain separate evidence authorities; none may be inferred from another hook.
- Stored integration acceptance is reverified through `slto_verify_integration_acceptance_evidence` whenever readiness is consumed.
- `slto_verify_staging_acceptance_evidence` is the independent staging-acceptance lifecycle verifier. It is evaluated in staging and re-checked before production truth; it must not be inferred from repository, CI, package or production checkbox state.
- `slto_verify_live_deployment_parity` is a separate production truth verifier. `Live-Deployed` remains false unless the deployment environment is production, production evidence is complete, the installed schema/contract versions match the candidate, an exact 40-character `SLTO_DEPLOYED_SOURCE_COMMIT` is configured, and this verifier independently confirms deployed-source/DB/migration/runtime parity.


## Optimistic-lock and content-publication contracts

- Existing translatable resources, providers and content-translation relationships require an explicit current `row_version` before mutation; a server-fetched current version is not a substitute for the caller's concurrency evidence.
- A published content-translation relationship must bind the current active resource, the matching translation unit, target locale, source version and source hash. Native-owner approval is reverified after those relationships are proven.
- Resource source/version identity covers governed translation-affecting metadata (context, description, placeholders, markup policy, references and translatability evidence) as well as source text/risk/domain classification.
- `slto_verify_staging_acceptance_evidence` is re-run when production truth is evaluated; a boolean carried inside production evidence cannot by itself create `Staging-Accepted`.


## Delivery and staging-activation boundaries

- REST mutation payloads are bounded globally by byte and node count before idempotency or business logic runs.
- WP-CLI status/inventory/jobs/events/bundle-build commands apply the same File 00-bound authorization classes as other privileged delivery surfaces; operators must run them with an authorized WordPress user.
- Staging acceptance is an **outcome** of executing real staging journeys. Staging runtime activation therefore evaluates structural/security/integration/staffing gates but does not require the not-yet-created staging-acceptance result. Production activation retains the full environment-acceptance gate.
- Runtime boot requires the persisted schema and public-contract versions to exactly equal the code constants. Older code refuses activation against newer persisted schema/contract state.


## Context, policy-staleness and aggregate-metrics contracts

- Resource context evidence includes bounded typed route/component/screenshot references and is part of source hash/version identity.
- `POST /comments/{uuid}/resolve` records a versioned contextual-query answer. A resolution explicitly marked `affects_context` cannot silently leave related translations live: dependent units/content links are staled, affected active bundles are invalidated, and delivery cache is flushed.
- Activating/deprecating an active terminology entry or style policy propagates locale+domain staleness through translation units/content links/active bundles. Native source content itself remains owned by its domain owner.
- Metrics expose aggregate coverage/current-stale-missing source-word workload, released-unit turnaround, QA failures, feedback reopen facts and feedback grouped by locale/domain. Restricted source text and individual translator dimensions are excluded from these operational summaries.
