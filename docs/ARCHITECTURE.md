# CF-06 Foundation Architecture — 0.1.0

## Governing boundary

CF-06 owns localization operations, not the original domain truth. File 20 owns the global language preference and shell surface; File 25 owns RTL/LTR visual implementation; File 26 owns search transliteration and ranking; each native domain owner retains source-content and final high-risk publication authority.

## Safe-default runtime

The plugin installs an auditable foundation but leaves `slto_runtime_enabled` disabled. Public locale resolution returns a controlled 503 until Founder-approved extraction and staging gates are completed. Authorized localization administrators can inspect the registry and register controlled resources during implementation.

## Implemented layers

1. **Bootstrap and activation** — schema, capabilities, seed locales, truthful status.
2. **Locale domain** — BCP 47-style canonicalization and cycle-safe deterministic fallback chains.
3. **Locale repository/service** — locale upsert, status, direction, fallback, format-data versions.
4. **Resource catalog** — stable semantic keys, source locale/version/hash, context, domain, risk/data classes, typed named placeholders, idempotent versioning.
5. **Audit evidence** — mutation audit records contain hashes and metadata, never full source payloads.
6. **REST contracts** — versioned status, locale, resolution, and resource-registration endpoints.
7. **Admin evidence surface** — read-only truthful state and locale registry.
8. **CI** — PHP 8.1/8.3 lint/tests, secret-pattern guard, deterministic candidate packaging.

## Tables

- `{prefix}slto_locales`
- `{prefix}slto_resources`
- `{prefix}slto_audit_events`

No translation units, projects, terminology, memory, vendor jobs, bundles, or release tables are created yet; those belong to later C6-C through C6-F phases and must not be falsely represented as complete.
