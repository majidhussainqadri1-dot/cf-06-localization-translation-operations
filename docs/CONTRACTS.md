# Versioned Contracts

- Namespace: `sabri-localization/v1`.
- Manifest: `sabri_localization_manifest()` and `sabri_localization_manifest` filter.
- Locale query/resolution: public-safe enabled locale DTOs only.
- Active bundle query: hash, signature, version and payload for activated locale only.
- Events: past-tense facts dispatched from the transactional outbox; consumers must be idempotent.
- Every companion action must revalidate current File 00/native-owner state; manifest presence is not authorization.
- Contract version is independent from plugin and schema versions.

- `POST /mt/jobs/{uuid}/review` — qualified domain reviewer confirms that every provider draft entered human editing/review before the provider job can be accepted.
