# Data Dictionary

CF-06 uses 23 bounded relational entities: locales; resources; secure payloads; projects and frozen project resources; translation units; assignments; comments; terminology; style guides; translation memory; providers and vendor jobs; locale bundles; QA results; feedback; content translation links; audit; outbox; jobs; idempotency; rate limits; migrations.

Identifiers are immutable UUIDs except bounded operational tables. All mutable domain records use `row_version`. UTC timestamps are stored and displayed through approved platform locale/time-zone contracts. JSON is used only for versioned bounded adjunct data, never as an authorization substitute. C4/C5/private text is stored by encrypted envelope reference rather than ordinary columns.
