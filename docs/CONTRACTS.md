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
