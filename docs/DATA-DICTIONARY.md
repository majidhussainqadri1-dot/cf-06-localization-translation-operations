# Data Dictionary

CF-06 uses 27 bounded relational entities: locales; resources; secure payloads; projects; frozen project resources; assignments; translation units; comments; terminology; style guides; translation memory; providers; vendor jobs; locale bundles; QA results; feedback; content translation links; audit; outbox; jobs; idempotency; rate limits; migrations; integration evidence; extraction evidence; QA evidence; and release approvals.

Identifiers are immutable UUIDs except bounded operational tables. All mutable domain records use `row_version`. UTC timestamps are stored and displayed through approved platform locale/time-zone contracts. JSON is used only for versioned bounded adjunct data, never as an authorization substitute. C4/C5/private text is stored by encrypted envelope reference rather than ordinary columns.
