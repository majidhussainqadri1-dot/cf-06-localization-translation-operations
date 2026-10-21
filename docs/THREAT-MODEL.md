# Threat Model

## Protected assets

Original/restricted text, translations, terminology, provider credentials/references, assignment identity, release signatures, audit/outbox integrity and private content relationships.

## Principal threats and controls

- **BOLA/IDOR/forged role:** server-side capability and object/state/version checks.
- **CSRF/replay/duplicate commands:** WP nonces for admin, REST permissions, idempotency keys and request hashes.
- **SQL/XSS/markup injection:** `$wpdb->prepare`, allowlisted columns, WordPress sanitization/escaping and markup equivalence.
- **SSRF/provider exfiltration:** HTTPS-only allowlist, public-IP resolution, no redirects, environment credential references.
- **Private-data MT leakage:** C4/C5/private denial, redaction, bounded provider payloads, training disabled, purge evidence.
- **Bidi/placeholder/number corruption:** dedicated validators and release-blocking QA.
- **Unauthorized publication:** MT remains draft; qualified human/domain review; native owner performs final publication.
- **Stale or tampered bundles:** frozen source hashes, deterministic build, HMAC signature, critical coverage and rollback.
- **Race/lost update:** row versions, transactions/savepoints and atomic activation.
- **Audit tampering:** previous-hash/event-hash chain and minimized payload hashes.
- **Secret disclosure:** no repository credentials; encryption/signing/provider secrets supplied through environment/configuration.

Unknown/failed dependency state denies sensitive operations rather than broadening access.
