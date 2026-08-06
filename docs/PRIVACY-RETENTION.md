# Privacy, Retention and Provider Exit

- Collect only resource context, assignment and operational metadata required for declared localization purposes.
- C4/C5/private source/target text is encrypted with AES-256-GCM and purpose/owner-bound AAD.
- External MT excludes C4/C5/private data by policy; approved lower-risk jobs are redacted and hash-audited.
- Provider training is hard-disabled; credential values are never stored in plugin tables.
- Privacy export exposes only the requesting user’s assignments/comments/feedback and excludes other actors’ private data.
- Erasure anonymizes eligible operational identity while preserving required security/release/audit evidence; encrypted payload and provider deletion require explicit evidence.
- Vendor jobs have purge dates and deletion evidence. Provider deprecation requires outstanding-job reconciliation and exit proof.
- Retention schedules and lawful/clinical/financial exceptions are finalized before activation per jurisdiction and native owner.
