# Data Dictionary

CF-06 uses 27 bounded relational entities: locales; resources; secure payloads; projects; frozen project resources; assignments; translation units; comments; terminology; style guides; translation memory; providers; vendor jobs; locale bundles; QA results; feedback; content translation links; audit; outbox; jobs; idempotency; rate limits; migrations; integration evidence; extraction evidence; QA evidence; and release approvals.

Identifiers are immutable UUIDs except bounded operational tables. All mutable domain records use `row_version`. UTC timestamps are stored and displayed through approved platform locale/time-zone contracts. JSON is used only for versioned bounded adjunct data, never as an authorization substitute. C4/C5/private text is stored by encrypted envelope reference rather than ordinary columns.


## Governed resource context and query evidence

- `resources.references_json` is a bounded canonical evidence envelope containing general references plus structured `context_refs`. Context refs are typed as `route`, `component` or `screenshot`; routes are same-origin relative paths and component/screenshot refs are stable identifiers. These values participate in the canonical resource source hash/version.
- `comments.resolution_text`, `comments.status` and `comments.row_version` form the versioned contextual-query answer record. When a resolution changes translation context, CF-06 marks affected translation units/content links stale, invalidates active bundles containing the resource and flushes delivery cache; it does not silently rewrite native-domain source truth.
- Terminology/style-policy activation or retirement uses locale+domain dependency propagation. It stales affected units/content relationships and invalidates affected active locale bundles until re-review.
- Coverage/quality metrics are aggregate. Word workload is explicitly an estimate over active non-private C1-C3 source material; restricted C4/C5/private text is excluded. Turnaround is aggregate released-unit cycle time, not an individual translator-performance metric.
